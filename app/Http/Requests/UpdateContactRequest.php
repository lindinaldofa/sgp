<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ContactType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalize = [];

        if ($this->filled('zip_code')) {
            $normalize['zip_code'] = preg_replace('/\D/', '', (string) $this->input('zip_code'));
        }

        if ($this->filled('cpf')) {
            $normalize['cpf'] = preg_replace('/\D/', '', (string) $this->input('cpf'));
        }

        if ($normalize !== []) {
            $this->merge($normalize);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'instagram' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tiktok' => ['sometimes', 'nullable', 'string', 'max:100'],
            'facebook' => ['sometimes', 'nullable', 'string', 'max:150'],
            'twitter' => ['sometimes', 'nullable', 'string', 'max:100'],
            'cpf' => ['sometimes', 'nullable', 'string', 'max:14'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'nullable', Rule::enum(ContactType::class)],
            'engagement_score' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'profession' => ['sometimes', 'nullable', 'string', 'max:255'],
            'zip_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'neighborhood' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:2'],
            'latitude' => ['sometimes', 'nullable', 'numeric'],
            'longitude' => ['sometimes', 'nullable', 'numeric'],
            'electoral_zone' => ['sometimes', 'nullable', 'string', 'max:10'],
            'electoral_section' => ['sometimes', 'nullable', 'string', 'max:10'],
            'leader_id' => ['sometimes', 'nullable', 'integer', 'exists:contacts,id'],
            'lgpd_consent' => ['sometimes', 'nullable', 'boolean'],
            'custom_data' => ['sometimes', 'nullable', 'array'],
            'tag_ids' => ['sometimes', 'nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ];
    }
}
