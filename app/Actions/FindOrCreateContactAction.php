<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ContactType;
use App\Models\Contact;

/**
 * Shared find-or-create-by-email/CPF/phone logic used by every "contacts flow
 * in from somewhere else" entry point: public integrations, form submissions,
 * and CSV import. Consolidated here so the dedup rules, digit-stripping and
 * LGPD consent handling stay identical across all three instead of drifting.
 */
final class FindOrCreateContactAction
{
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
        if (array_key_exists('lgpd_consent', $fillable)) {
            $fillable['lgpd_consent'] = filter_var($fillable['lgpd_consent'], FILTER_VALIDATE_BOOLEAN);
            if ($fillable['lgpd_consent']) {
                $fillable['lgpd_consent_at'] = now();
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
        }

        return $contact;
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
