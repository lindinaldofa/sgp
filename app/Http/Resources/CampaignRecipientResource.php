<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignRecipientResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaign_id' => $this->campaign_id,
            'status' => $this->status?->value,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'clicked_at' => $this->clicked_at?->toIso8601String(),
            'error_message' => $this->error_message,
            'contact' => new ContactResource($this->whenLoaded('contact')),
        ];
    }
}
