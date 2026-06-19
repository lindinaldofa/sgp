<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactInteractionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'contactId'   => $this->contact_id,
            'userId'      => $this->user_id,
            'userName'    => $this->whenLoaded('user', fn () => $this->user?->name, null),
            'type'        => $this->type?->value,
            'description' => $this->description,
            'occurredAt'  => $this->occurred_at?->toIso8601String(),
            'createdAt'   => $this->created_at?->toIso8601String(),
        ];
    }
}
