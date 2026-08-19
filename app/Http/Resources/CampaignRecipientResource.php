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
            'campaignId' => $this->campaign_id,
            'status' => $this->status?->value,
            'sentAt' => $this->sent_at?->toIso8601String(),
            'deliveredAt' => $this->delivered_at?->toIso8601String(),
            'openedAt' => $this->opened_at?->toIso8601String(),
            'clickedAt' => $this->clicked_at?->toIso8601String(),
            'errorMessage' => $this->error_message,
            'contact' => new ContactResource($this->whenLoaded('contact')),
        ];
    }
}
