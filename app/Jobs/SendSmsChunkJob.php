<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CommunicationConfig;
use App\Models\Contact;
use App\Services\TemplateVariableResolverService;
use Aws\Sns\SnsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSmsChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<int, int> $recipientIds */
    public function __construct(
        private readonly int $campaignId,
        private readonly array $recipientIds,
    ) {}

    public function handle(TemplateVariableResolverService $resolver): void
    {
        $campaign = Campaign::with('tenant')->findOrFail($this->campaignId);
        $tenant = $campaign->tenant ?? throw new \RuntimeException("Campaign {$campaign->id} has no tenant");
        $config = CommunicationConfig::where('tenant_id', $campaign->tenant_id)->first();

        if (! $config) {
            return;
        }

        $snsClient = new SnsClient([
            'region' => $config->sns_region ?? $config->aws_region,
            'version' => 'latest',
            'credentials' => [
                'key' => config('services.aws.key'),
                'secret' => config('services.aws.secret'),
            ],
        ]);

        $recipients = CampaignRecipient::with('contact')->findMany($this->recipientIds);

        foreach ($recipients as $recipient) {
            $contact = $recipient->contact;
            if (! $contact instanceof Contact) {
                $recipient->update(['status' => 'failed', 'error_message' => 'Contact not found']);
                $campaign->increment('failed_count');

                continue;
            }

            $phone = $contact->phone ?? $contact->whatsapp ?? '';

            if (! $phone) {
                $recipient->update(['status' => 'failed', 'error_message' => 'No phone']);
                $campaign->increment('failed_count');

                continue;
            }

            try {
                $message = $resolver->resolve($campaign->body_text ?? '', $contact, $tenant);

                $snsClient->publish([
                    'PhoneNumber' => preg_replace('/\D/', '', '+55'.$phone),
                    'Message' => mb_substr($message, 0, 160),
                    'MessageAttributes' => [
                        'AWS.SNS.SMS.SenderID' => [
                            'DataType' => 'String',
                            'StringValue' => $config->sns_sms_sender_id ?? 'SGP',
                        ],
                        'AWS.SNS.SMS.SMSType' => [
                            'DataType' => 'String',
                            'StringValue' => 'Promotional',
                        ],
                    ],
                ]);

                $recipient->update(['status' => 'sent', 'sent_at' => now()]);
                $campaign->increment('sent_count');
            } catch (\Throwable $e) {
                $recipient->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                $campaign->increment('failed_count');
                Log::error("SMS send failed for recipient {$recipient->id}: ".$e->getMessage());
            }
        }
    }
}
