<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationRuleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'trigger' => $this->trigger,
            'tagId' => $this->tag_id,
            'tag' => new TagResource($this->whenLoaded('tag')),
            'campaignId' => $this->campaign_id,
            'campaign' => $this->whenLoaded('campaign', fn () => $this->campaign ? [
                'id' => $this->campaign->ulid,
                'name' => $this->campaign->name,
                'channel' => $this->campaign->channel?->value,
            ] : null),
            'channel' => $this->channel?->value,
            'templateId' => $this->template_id,
            'template' => $this->whenLoaded('template', fn () => $this->template ? [
                'id' => $this->template->id,
                'name' => $this->template->name,
            ] : null),
            'isActive' => $this->is_active,
            'sendTime' => $this->send_time,
            'daysBefore' => $this->days_before,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
