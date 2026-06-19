<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestSource;
use App\Enums\ServiceRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contact_id' => ['sometimes', 'integer', 'exists:contacts,id'],
            'category_id' => ['sometimes', 'integer', 'exists:service_categories,id'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', Rule::enum(ServiceRequestStatus::class)],
            'priority' => ['sometimes', 'nullable', Rule::enum(ServiceRequestPriority::class)],
            'source' => ['sometimes', 'nullable', Rule::enum(ServiceRequestSource::class)],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
