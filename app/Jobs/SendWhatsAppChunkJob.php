<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Services\OpenWaService;
use App\Services\TemplateVariableResolverService;
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

    public function handle(OpenWaService $whatsapp, TemplateVariableResolverService $resolver): void
    {
        $campaign = Campaign::with('tenant')->findOrFail($this->campaignId);
        $tenant = $campaign->tenant ?? throw new \RuntimeException("Campaign {$campaign->id} has no tenant");
        $recipients = CampaignRecipient::with('contact')->findMany($this->recipientIds);

        foreach ($recipients as $recipient) {
            $contact = $recipient->contact;

            if (! $contact instanceof Contact) {
                $recipient->update(['status' => 'failed', 'error_message' => 'Contact not found']);
                $campaign->increment('failed_count');

                continue;
            }

            $phone = $contact->whatsapp ?? $contact->phone ?? '';

            if (! $phone) {
                $recipient->update(['status' => 'failed', 'error_message' => 'No phone number']);
                $campaign->increment('failed_count');

                continue;
            }

            $message = $resolver->resolve($campaign->body_text ?? '', $contact, $tenant);

            $success = $whatsapp->sendText($phone, $message, (int) $campaign->tenant_id);

            if ($success) {
                $recipient->update(['status' => 'sent', 'sent_at' => now()]);
                $campaign->increment('sent_count');
            } else {
                $recipient->update(['status' => 'failed', 'error_message' => 'OpenWA send failed']);
                $campaign->increment('failed_count');
            }
        }
    }
}
