<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->ulid,
            'title'         => $this->title,
            'description'   => $this->description,
            'type'          => $this->type?->value,
            'location'      => $this->location,
            'isOnline'      => (bool) $this->is_online,
            'onlineUrl'     => $this->online_url,
            'startsAt'      => $this->starts_at?->toIso8601String(),
            'endsAt'        => $this->ends_at?->toIso8601String(),
            'allDay'        => (bool) $this->all_day,
            'attendance'    => $this->attendance?->value,
            'absenceReason' => $this->absence_reason,
            'createdAt'     => $this->created_at?->toIso8601String(),
            'contact'       => new ContactResource($this->whenLoaded('contact')),
        ];
    }
}
