<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormSubmissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'ulid'        => $this->ulid,
            'submittedAt' => $this->submitted_at->toIso8601String(),
            'ipAddress'   => $this->ip_address,
            'contact'     => $this->when(
                $this->relationLoaded('contact') && $this->contact,
                fn () => [
                    'id'   => $this->contact->id,
                    'ulid' => $this->contact->ulid,
                    'name' => $this->contact->name,
                ],
            ),
            'values' => $this->when(
                $this->relationLoaded('values'),
                fn () => $this->values->map(fn ($v) => [
                    'fieldName'  => $v->field_name,
                    'fieldLabel' => $v->field_label,
                    'value'      => $v->value,
                ]),
            ),
        ];
    }
}
