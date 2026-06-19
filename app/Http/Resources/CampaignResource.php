<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $sentCount = (int) $this->sent_count;
        $openedCount = (int) $this->opened_count;

        $openRate = $sentCount > 0
            ? round(($openedCount / $sentCount) * 100, 1)
            : 0.0;

        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'channel' => $this->channel?->value,
            'channel_label' => $this->channel?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'subject' => $this->subject,
            'total_recipients' => (int) $this->total_recipients,
            'sent_count' => $sentCount,
            'delivered_count' => (int) $this->delivered_count,
            'opened_count' => $openedCount,
            'failed_count' => (int) $this->failed_count,
            'open_rate' => $openRate,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'recipients_count' => $this->whenCounted('recipients'),
        ];
    }
}
