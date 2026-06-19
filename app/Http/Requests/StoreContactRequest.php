<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ContactType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp'  => ['nullable', 'string', 'max:30'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'tiktok'    => ['nullable', 'string', 'max:100'],
            'facebook'  => ['nullable', 'string', 'max:150'],
            'twitter'   => ['nullable', 'string', 'max:100'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string'],
            'type' => ['nullable', Rule::enum(ContactType::class)],
            'engagement_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string'],
            'profession' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_number' => ['nullable', 'string', 'max:20'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:2'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'electoral_zone' => ['nullable', 'string', 'max:10'],
            'electoral_section' => ['nullable', 'string', 'max:10'],
            'leader_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'lgpd_consent' => ['nullable', 'boolean'],
        ];
    }
}
