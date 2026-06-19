<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ContactType;
use App\Models\Contact;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ProcessFormSubmissionAction
{
    /** @param array<string, string> $values */
    public function execute(Form $form, array $values, Request $request): FormSubmission
    {
        return DB::transaction(function () use ($form, $values, $request) {
            $contactData = $this->extractContactData($form, $values);
            $contact     = $this->findOrCreateContact((int) $form->tenant_id, $contactData);

            $submission = FormSubmission::create([
                'form_id'      => $form->id,
                'tenant_id'    => $form->tenant_id,
                'contact_id'   => $contact?->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'submitted_at' => now(),
            ]);

            foreach ($form->fields as $field) {
                if ($field->type->isLayout()) {
                    continue;
                }
                $submission->values()->create([
                    'field_name'  => $field->name,
                    'field_label' => $field->label,
                    'value'       => $values[$field->name] ?? null,
                ]);
            }

            return $submission;
        });
    }

    /**
     * Build an array of contact-mapped field values from the submission.
     *
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function extractContactData(Form $form, array $values): array
    {
        $data = [];
        foreach ($form->fields as $field) {
            if ($field->maps_to && isset($values[$field->name])) {
                $data[$field->maps_to] = $values[$field->name];
            }
        }

        return $data;
    }

    /** @param array<string, string> $data */
    private function findOrCreateContact(int $tenantId, array $data): ?Contact
    {
        if (empty($data)) {
            return null;
        }

        $email = $data['email'] ?? null;
        $cpf   = isset($data['cpf']) ? preg_replace('/\D/', '', $data['cpf']) : null;
        $phone = $data['phone'] ?? null;

        // Try to find by email, then CPF, then phone
        $contact = null;
        if ($email) {
            $contact = Contact::where('tenant_id', $tenantId)->where('email', $email)->first();
        }
        if (!$contact && $cpf) {
            $contact = Contact::where('tenant_id', $tenantId)->where('cpf', $cpf)->first();
        }
        if (!$contact && $phone) {
            $contact = Contact::where('tenant_id', $tenantId)
                ->where(fn ($q) => $q->where('phone', $phone)->orWhere('whatsapp', $phone))
                ->first();
        }

        $contactFields = [
            'name', 'email', 'phone', 'whatsapp', 'cpf',
            'birth_date', 'gender', 'profession',
            'zip_code', 'address', 'address_number',
            'neighborhood', 'city', 'state',
        ];

        $fillable = array_intersect_key($data, array_flip($contactFields));

        if (isset($fillable['cpf'])) {
            $fillable['cpf'] = preg_replace('/\D/', '', $fillable['cpf']) ?? '';
        }
        if (isset($fillable['zip_code'])) {
            $fillable['zip_code'] = preg_replace('/\D/', '', $fillable['zip_code']) ?? '';
        }

        if ($contact) {
            $contact->update($fillable);
        } elseif (!empty($fillable['name'] ?? null) || !empty($email)) {
            $fillable['tenant_id'] = $tenantId;
            $fillable['type']      = ContactType::Voter->value;
            $fillable['name']      = $fillable['name'] ?? ($email ?? 'Sem nome');
            $contact               = Contact::create($fillable);
        }

        return $contact;
    }
}
