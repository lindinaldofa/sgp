<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ContactType;
use App\Jobs\SendNewContactAutomationJob;
use App\Models\Contact;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ProcessFormSubmissionAction
{
    /** @param array<string, mixed> $values */
    public function execute(Form $form, array $values, Request $request): FormSubmission
    {
        return DB::transaction(function () use ($form, $values, $request) {
            $contactData = $this->extractContactData($form, $values);
            $contact = $this->findOrCreateContact((int) $form->tenant_id, $contactData);

            if ($contact && $form->tag_id) {
                $wasNewContact = $contact->wasRecentlyCreated;

                $contact->tags()->syncWithoutDetaching([$form->tag_id]);

                SendNewContactAutomationJob::dispatch($contact->id, $form->tag_id, $wasNewContact);
            }

            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'tenant_id' => $form->tenant_id,
                'contact_id' => $contact?->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'submitted_at' => now(),
            ]);

            foreach ($form->fields as $field) {
                if ($field->type->isLayout()) {
                    continue;
                }
                $value = $values[$field->name] ?? null;
                $submission->values()->create([
                    'field_name' => $field->name,
                    'field_label' => $field->label,
                    'value' => is_bool($value) ? ($value ? '1' : '0') : $value,
                ]);
            }

            return $submission;
        });
    }

    /**
     * Build an array of contact-mapped field values from the submission.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function extractContactData(Form $form, array $values): array
    {
        $data = [];
        foreach ($form->fields as $field) {
            if (! $field->maps_to) {
                continue;
            }

            // The LGPD checkbox is authoritative whenever the form declares it: an
            // unchecked box (or one the visitor never touched) is still an explicit
            // "no", so it must not be confused with "field absent from the form",
            // which is what lets contacts fall back to the tenant-wide default.
            if ($field->maps_to === 'lgpd_consent') {
                $data['lgpd_consent'] = filter_var($values[$field->name] ?? false, FILTER_VALIDATE_BOOLEAN);

                continue;
            }

            if (array_key_exists($field->name, $values)) {
                $data[$field->maps_to] = $values[$field->name];
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function findOrCreateContact(int $tenantId, array $data): ?Contact
    {
        if (empty($data)) {
            return null;
        }

        $email = $data['email'] ?? null;
        $cpf = isset($data['cpf']) ? preg_replace('/\D/', '', (string) $data['cpf']) : null;
        $phone = $data['phone'] ?? null;

        // Try to find by email, then CPF, then phone
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

        $contactFields = [
            'name', 'email', 'phone', 'whatsapp', 'cpf',
            'birth_date', 'gender', 'profession',
            'zip_code', 'address', 'address_number',
            'neighborhood', 'city', 'state',
            'instagram', 'tiktok', 'facebook', 'twitter',
            'lgpd_consent',
        ];

        $fillable = array_intersect_key($data, array_flip($contactFields));

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

        if ($contact) {
            $contact->update($fillable);
        } elseif (! empty($fillable['name'] ?? null) || ! empty($email)) {
            $fillable['tenant_id'] = $tenantId;
            $fillable['type'] = ContactType::Voter->value;
            $fillable['name'] = $fillable['name'] ?? ($email ?? 'Sem nome');
            $contact = Contact::create($fillable);
        }

        return $contact;
    }
}
