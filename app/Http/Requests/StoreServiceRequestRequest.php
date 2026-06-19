<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestSource;
use App\Enums\ServiceRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contact_id'  => ['required', 'string', 'exists:contacts,ulid'],
            'category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'subject'     => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', Rule::enum(ServiceRequestStatus::class)],
            'priority'    => ['nullable', Rule::enum(ServiceRequestPriority::class)],
            'source'      => ['nullable', Rule::enum(ServiceRequestSource::class)],
            'due_date'    => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
