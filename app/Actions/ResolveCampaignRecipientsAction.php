<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;

/**
 * Builds the CampaignRecipient rows for a campaign from its segment_filters
 * snapshot, re-resolving the audience at send time. Safe to call more than
 * once for the same campaign: contacts already added as recipients are left
 * untouched (their status is not reset).
 */
final class ResolveCampaignRecipientsAction
{
    public function execute(Campaign $campaign): int
    {
        $filters = $campaign->segment_filters ?? [];
        $audienceType = $filters['audience_type'] ?? 'all';

        $query = Contact::where('tenant_id', $campaign->tenant_id)
            ->where('lgpd_consent', true)
            ->whereNull('unsubscribed_at');

        if ($audienceType === 'tag' && ! empty($filters['tag_ids'])) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $filters['tag_ids']));
        } elseif ($audienceType === 'type' && ! empty($filters['contact_types'])) {
            $query->whereIn('type', $filters['contact_types']);
        }

        $contactIds = $query->pluck('id');

        $existingContactIds = CampaignRecipient::where('campaign_id', $campaign->id)->pluck('contact_id');
        $newContactIds = $contactIds->diff($existingContactIds);

        if ($newContactIds->isNotEmpty()) {
            $now = now();
            $rows = $newContactIds->map(fn ($contactId) => [
                'campaign_id' => $campaign->id,
                'contact_id' => $contactId,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                CampaignRecipient::insert($chunk);
            }
        }

        $total = CampaignRecipient::where('campaign_id', $campaign->id)->count();
        $campaign->update(['total_recipients' => $total]);

        return $total;
    }
}
