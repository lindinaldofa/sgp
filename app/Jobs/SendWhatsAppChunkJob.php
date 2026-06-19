<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\WhatsAppCloudApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<int, int> $recipientIds */
    public function __construct(
        private readonly int $campaignId,
        private readonly array $recipientIds,
    ) {}

    public function handle(WhatsAppCloudApiService $whatsapp): void
    {
        $campaign = Campaign::findOrFail($this->campaignId);
        $recipients = CampaignRecipient::with('contact')->findMany($this->recipientIds);

        foreach ($recipients as $recipient) {
            $contact = $recipient->contact;
            $phone = $contact->whatsapp ?? $contact->phone ?? '';

            if (! $phone) {
                $recipient->update(['status' => 'failed', 'error_message' => 'No phone number']);
                $campaign->increment('failed_count');

                continue;
            }

            $success = $whatsapp->sendTemplate(
                $phone,
                $campaign->whatsapp_template_name ?? 'hello_world',
                $campaign->tenant_id,
                [],
                $campaign->whatsapp_template_lang,
            );

            if ($success) {
                $recipient->update(['status' => 'sent', 'sent_at' => now()]);
                $campaign->increment('sent_count');
            } else {
                $recipient->update(['status' => 'failed', 'error_message' => 'WhatsApp API error']);
                $campaign->increment('failed_count');
            }
        }
    }
}
