<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ContactType;
use App\Jobs\SendNewContactAutomationJob;
use App\Models\Contact;
use App\Models\IntegrationSource;
use Illuminate\Support\Facades\DB;

final class CreateContactFromIntegrationAction
{
    /** @param array<string, mixed> $data */
    public function execute(IntegrationSource $source, array $data): Contact
    {
        return DB::transaction(function () use ($source, $data) {
            $contact = $this->findOrCreateContact($source, $data);
            $wasRecentlyCreated = $contact->wasRecentlyCreated;

            $contact->tags()->syncWithoutDetaching([$source->tag_id]);
            $source->update(['last_used_at' => now()]);

            if ($wasRecentlyCreated) {
                SendNewContactAutomationJob::dispatch($contact->id, $source->tag_id);
            }

            return $contact;
        });
    }

    /** @param array<string, mixed> $data */
    private function findOrCreateContact(IntegrationSource $source, array $data): Contact
    {
        $tenantId = (int) $source->tenant_id;
        $email = $data['email'] ?? null;
        $cpf = isset($data['cpf']) ? preg_replace('/\D/', '', (string) $data['cpf']) : null;
        $phone = $data['phone'] ?? $data['whatsapp'] ?? null;

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

        $fillable = collect($data)->only([
            'name', 'email', 'phone', 'whatsapp', 'cpf',
            'birth_date', 'gender', 'profession',
            'zip_code', 'address', 'address_number',
            'neighborhood', 'city', 'state',
            'instagram', 'tiktok', 'facebook', 'twitter',
            'lgpd_consent',
        ])->toArray();

        if (isset($fillable['cpf'])) {
            $fillable['cpf'] = preg_replace('/\D/', '', (string) $fillable['cpf']);
        }
        if (isset($fillable['zip_code'])) {
            $fillable['zip_code'] = preg_replace('/\D/', '', (string) $fillable['zip_code']);
        }
        if (! empty($fillable['lgpd_consent'])) {
            $fillable['lgpd_consent_at'] = now();
        }

        if ($contact) {
            $contact->update($fillable);

            return $contact;
        }

        $fillable['tenant_id'] = $tenantId;
        $fillable['type'] = ContactType::Voter->value;
        $fillable['name'] = $fillable['name'] ?? $email ?? 'Sem nome';

        return Contact::create($fillable);
    }
}
