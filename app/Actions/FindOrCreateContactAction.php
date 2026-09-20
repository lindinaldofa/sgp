<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ContactType;
use App\Jobs\SyncContactSegmentsJob;
use App\Models\Contact;
use App\Support\ContactSegmentSyncSuppressor;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Shared find-or-create-by-email/CPF/phone logic used by every "contacts flow
 * in from somewhere else" entry point: public integrations, form submissions,
 * and CSV import. Consolidated here so the dedup rules, digit-stripping and
 * LGPD consent handling stay identical across all three instead of drifting.
 */
final class FindOrCreateContactAction
{
    /**
     * Matches the `contacts` table's column lengths — free-text sources (CSV
     * import especially) routinely exceed these and would otherwise crash
     * the whole row with a raw SQL "data too long" error.
     */
    private const FIELD_MAX_LENGTHS = [
        'cpf' => 11,
        'zip_code' => 8,
        'neighborhood' => 100,
        'city' => 100,
        'state' => 2,
    ];

    private const STATE_NAME_TO_UF = [
        'ACRE' => 'AC', 'ALAGOAS' => 'AL', 'AMAPA' => 'AP', 'AMAZONAS' => 'AM',
        'BAHIA' => 'BA', 'CEARA' => 'CE', 'DISTRITO FEDERAL' => 'DF',
        'ESPIRITO SANTO' => 'ES', 'GOIAS' => 'GO', 'MARANHAO' => 'MA',
        'MATO GROSSO' => 'MT', 'MATO GROSSO DO SUL' => 'MS', 'MINAS GERAIS' => 'MG',
        'PARA' => 'PA', 'PARAIBA' => 'PB', 'PARANA' => 'PR', 'PERNAMBUCO' => 'PE',
        'PIAUI' => 'PI', 'RIO DE JANEIRO' => 'RJ', 'RIO GRANDE DO NORTE' => 'RN',
        'RIO GRANDE DO SUL' => 'RS', 'RONDONIA' => 'RO', 'RORAIMA' => 'RR',
        'SANTA CATARINA' => 'SC', 'SAO PAULO' => 'SP', 'SERGIPE' => 'SE', 'TOCANTINS' => 'TO',
    ];

    private const BIRTH_DATE_FORMATS = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'];

    /**
     * @param array<string, mixed> $nativeData flat contact column => value (name, email, phone, ...)
     * @param array<string, mixed> $customData custom_fields.key => value
     * @param int[] $tagIds tags to attach — additive only, never detaches existing ones
     */
    public function execute(int $tenantId, array $nativeData, array $customData = [], array $tagIds = []): ?Contact
    {
        $email = $nativeData['email'] ?? null;
        $cpf = isset($nativeData['cpf']) ? preg_replace('/\D/', '', (string) $nativeData['cpf']) : null;
        $phone = $nativeData['phone'] ?? $nativeData['whatsapp'] ?? null;

        $contact = null;
        if ($email) {
            $contact = Contact::where('tenant_id', $tenantId)->where('email', $email)->first();
        }
        if (! $contact && $cpf) {
            $contact = Contact::where('tenant_id', $tenantId)->where('cpf', $cpf)->first();
        }
        if (! $contact && $phone) {
            $contact = Contact::where('tenant_id', $tenantId)
                ->where(fn ($q) => $q->where('phone', $phone)->orWhere('whatsapp', $phone))
                ->first();
        }

        $allowedFields = [
            'name', 'email', 'phone', 'whatsapp', 'cpf',
            'birth_date', 'gender', 'profession',
            'zip_code', 'address', 'address_number',
            'neighborhood', 'city', 'state',
            'instagram', 'tiktok', 'facebook', 'twitter',
            'lgpd_consent',
        ];

        $fillable = array_intersect_key($nativeData, array_flip($allowedFields));

        if (isset($fillable['cpf'])) {
            $fillable['cpf'] = preg_replace('/\D/', '', (string) $fillable['cpf']) ?? '';
        }
        if (isset($fillable['zip_code'])) {
            $fillable['zip_code'] = preg_replace('/\D/', '', (string) $fillable['zip_code']) ?? '';
        }
        if (isset($fillable['state'])) {
            $fillable['state'] = self::normalizeState((string) $fillable['state']);
        }
        if (array_key_exists('birth_date', $fillable)) {
            $parsedBirthDate = self::parseBirthDate($fillable['birth_date']);
            if ($parsedBirthDate) {
                $fillable['birth_date'] = $parsedBirthDate;
            } else {
                unset($fillable['birth_date']);
            }
        }
        if (array_key_exists('lgpd_consent', $fillable)) {
            $fillable['lgpd_consent'] = filter_var($fillable['lgpd_consent'], FILTER_VALIDATE_BOOLEAN);
            if ($fillable['lgpd_consent']) {
                $fillable['lgpd_consent_at'] = now();
            }
        }

        foreach (self::FIELD_MAX_LENGTHS as $field => $maxLength) {
            if (isset($fillable[$field]) && is_string($fillable[$field])) {
                $fillable[$field] = mb_substr($fillable[$field], 0, $maxLength);
            }
        }

        if (! $contact && empty($fillable['name'] ?? null) && empty($email)) {
            return null;
        }

        if ($customData !== []) {
            $fillable['custom_data'] = array_merge($contact?->custom_data ?? [], $customData);
        }

        if ($contact) {
            $contact->update($fillable);
        } else {
            $fillable['tenant_id'] = $tenantId;
            $fillable['type'] = ContactType::Voter->value;
            $fillable['name'] = $fillable['name'] ?? $email ?? 'Sem nome';
            $contact = Contact::create($fillable);
        }

        if ($tagIds !== []) {
            $contact->tags()->syncWithoutDetaching($tagIds);

            if (! ContactSegmentSyncSuppressor::isSuppressed()) {
                SyncContactSegmentsJob::dispatch($contact->id);
            }
        }

        return $contact;
    }

    /**
     * Accepts either a UF abbreviation ("AL") or a full state name
     * ("Alagoas", accents/case-insensitive, as free-text CSV exports often
     * have it) and returns the 2-letter UF. Falls back to truncating
     * whatever was given so an unrecognized value never breaks the insert.
     */
    private static function normalizeState(string $value): string
    {
        $normalized = strtoupper(trim(Str::ascii($value)));

        return self::STATE_NAME_TO_UF[$normalized] ?? mb_substr($normalized, 0, 2);
    }

    /**
     * CSV exports (and some external sources) send birth dates as free text
     * in whatever format the origin system used — most commonly Brazilian
     * d/m/Y, which Carbon's default parser rejects outright. Tries a short
     * list of formats and gives up silently (returns null) rather than
     * throwing, so one unparseable date doesn't fail the entire row.
     */
    private static function parseBirthDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        foreach (self::BIRTH_DATE_FORMATS as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Splits a flat "target field key => value" map into native contact
     * columns vs. custom field values. A target of `custom.{key}` routes to
     * the custom field; everything else is treated as a native column. Used
     * by both form submissions and CSV import, which each resolve their own
     * "target" per source column/field before calling this.
     *
     * @param array<string, mixed> $valuesByTarget
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function splitNativeAndCustom(array $valuesByTarget): array
    {
        $native = [];
        $custom = [];

        foreach ($valuesByTarget as $target => $value) {
            if (! is_string($target) || $target === '') {
                continue;
            }

            if (str_starts_with($target, 'custom.')) {
                $custom[substr($target, 7)] = $value;
            } else {
                $native[$target] = $value;
            }
        }

        return [$native, $custom];
    }
}
