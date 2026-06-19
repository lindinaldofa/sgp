<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property ServiceRequest $resource */
class ServiceRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->ulid,
            'subject'     => $this->subject,
            'description' => $this->description,
            'status'      => $this->status?->value,
            'priority'    => $this->priority?->value,
            'source'      => $this->source?->value,
            'dueDate'     => $this->due_date?->toDateString(),
            'resolvedAt'  => $this->resolved_at?->toIso8601String(),
            'isOverdue'   => $this->resource->isOverdue(),
            'createdAt'   => $this->created_at?->toIso8601String(),
            'updatedAt'   => $this->updated_at?->toIso8601String(),
            'category'    => new ServiceCategoryResource($this->whenLoaded('category')),
            'contact'     => new ContactResource($this->whenLoaded('contact')),
            'assignedTo'  => new UserResource($this->whenLoaded('assignedTo')),
        ];
    }
}
