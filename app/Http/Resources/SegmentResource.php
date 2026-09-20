<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SegmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'description' => $this->description,
            'filters' => $this->filters ?? [],
            'isActive' => (bool) $this->is_active,
            'contactsCount' => $this->when(isset($this->contacts_count), fn () => (int) $this->contacts_count),
            'lastRecalculatedAt' => $this->last_recalculated_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
