<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CampaignChannel;
use App\Enums\CampaignStatus;
use App\Jobs\SendEmailCampaignChunkJob;
use App\Jobs\SendSmsChunkJob;
use App\Jobs\SendWhatsAppChunkJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;

/**
 * Adds a contact as a campaign recipient, idempotently (a contact only ever
 * becomes a recipient of a given campaign once). Shared by the "new_contact"
 * automation trigger and the admin's manual "add contact to campaign" action,
 * which differ only in when the send is fired:
 *
 * - 'always': the campaign is a perpetual trigger (automation rules), not a
 *   one-off blast — it never has a single "sent" moment, so every new match
 *   dispatches immediately regardless of status.
 * - 'if_active': a manual add to a still-draft campaign just queues the
 *   recipient for the next explicit send; only campaigns already
 *   sending/sent fire immediately for the newly added contact.
 */
final class AddContactToCampaignAction
{
    /**
     * @param 'always'|'if_active' $dispatch
     * @return array{recipient: CampaignRecipient, created: bool}
     */
    public function execute(Campaign $campaign, Contact $contact, string $dispatch = 'if_active'): array
    {
        $recipient = CampaignRecipient::firstOrCreate(
            ['campaign_id' => $campaign->id, 'contact_id' => $contact->id],
            ['status' => 'pending'],
        );

        if (! $recipient->wasRecentlyCreated) {
            return ['recipient' => $recipient, 'created' => false];
        }

        $campaign->increment('total_recipients');

        $shouldDispatch = $dispatch === 'always'
            || ($dispatch === 'if_active' && in_array($campaign->status, [CampaignStatus::Sending, CampaignStatus::Sent], true));

        if ($shouldDispatch) {
            match ($campaign->channel) {
                CampaignChannel::Email => SendEmailCampaignChunkJob::dispatch($campaign->id, [$recipient->id]),
                CampaignChannel::Sms => SendSmsChunkJob::dispatch($campaign->id, [$recipient->id]),
                CampaignChannel::WhatsApp => SendWhatsAppChunkJob::dispatch($campaign->id, [$recipient->id]),
            };
        }

        return ['recipient' => $recipient, 'created' => true];
    }
}
