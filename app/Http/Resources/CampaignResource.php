<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Segment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'id' => $this->ulid,
            'name' => $this->name,
            'channel' => $this->channel?->value,
            'channelLabel' => $this->channel?->label(),
            'status' => $this->status?->value,
            'statusLabel' => $this->status?->label(),
            'subject' => $this->subject,
            'bodyHtml' => $this->body_html,
            'bodyText' => $this->body_text,
            'whatsappMessageType' => $this->whatsapp_message_type?->value,
            'mediaUrl' => $this->media_path ? url(Storage::url($this->media_path)) : null,
            'segmentFilters' => $this->segmentFiltersForOutput(),
            'totalRecipients' => (int) $this->total_recipients,
            'sentCount' => $sentCount,
            'deliveredCount' => (int) $this->delivered_count,
            'openedCount' => $openedCount,
            'clickedCount' => (int) $this->clicked_count,
            'failedCount' => (int) $this->failed_count,
            'openRate' => $openRate,
            'scheduledAt' => $this->scheduled_at?->toIso8601String(),
            'startedAt' => $this->started_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'recipientsCount' => $this->whenCounted('recipients'),
        ];
    }

    /**
     * segment_filters.segment_ids is stored as internal numeric FKs (for cheap
     * querying at send time); the API only ever exposes segment ULIDs, so it's
     * translated back here.
     *
     * @return array<string, mixed>|null
     */
    private function segmentFiltersForOutput(): ?array
    {
        $filters = $this->segment_filters;

        if (! is_array($filters) || ($filters['audience_type'] ?? null) !== 'segment' || empty($filters['segment_ids'])) {
            return $filters;
        }

        $filters['segment_ids'] = Segment::whereIn('id', $filters['segment_ids'])->pluck('ulid')->all();

        return $filters;
    }
}
