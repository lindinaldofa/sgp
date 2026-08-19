<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CommunicationConfig;
use App\Models\Contact;
use App\Services\TemplateVariableResolverService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailCampaignChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<int, int> $recipientIds */
    public function __construct(
        private readonly int $campaignId,
        private readonly array $recipientIds,
    ) {}

    public function handle(TemplateVariableResolverService $resolver): void
    {
        $campaign = Campaign::with('tenant')->findOrFail($this->campaignId);
        $tenant = $campaign->tenant ?? throw new \RuntimeException("Campaign {$campaign->id} has no tenant");
        $recipients = CampaignRecipient::with('contact')->findMany($this->recipientIds);

        foreach ($recipients as $recipient) {
            try {
                $contact = $recipient->contact;
                if (! $contact instanceof Contact) {
                    $recipient->update(['status' => 'failed', 'error_message' => 'Contact not found']);
                    $campaign->increment('failed_count');

                    continue;
                }

                if ($contact->unsubscribed_at) {
                    $recipient->update(['status' => 'unsubscribed']);

                    continue;
                }

                $body = $resolver->resolve($campaign->body_html ?? $campaign->body_text ?? '', $contact, $tenant);
                $body .= $this->unsubscribeFooter($recipient, $tenant->name);
                $subject = $resolver->resolve($campaign->subject ?? $campaign->name, $contact, $tenant);

                $config = CommunicationConfig::where('tenant_id', $campaign->tenant_id)->first();
                $from = $config?->ses_from_email ?? config('mail.from.address');
                $fromName = $config?->ses_from_name ?? config('mail.from.name');

                Mail::html($body, function ($message) use ($contact, $subject, $from, $fromName) {
                    $message->to($contact->email)
                        ->subject($subject)
                        ->from($from, $fromName);
                });

                $recipient->update(['status' => 'sent', 'sent_at' => now()]);
                $campaign->increment('sent_count');
            } catch (\Throwable $e) {
                $recipient->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                $campaign->increment('failed_count');
                Log::warning("Email send failed for recipient {$recipient->id}: ".$e->getMessage());
            }
        }
    }

    private function unsubscribeFooter(CampaignRecipient $recipient, string $tenantName): string
    {
        $url = $recipient->unsubscribeUrl();

        return <<<HTML
            <hr style="margin-top:24px;border:none;border-top:1px solid #e2e8f0;">
            <p style="font-size:12px;color:#94a3b8;margin-top:12px;">
                Você está recebendo este e-mail porque tem um relacionamento com {$tenantName}.
                Se não deseja mais receber nossas mensagens,
                <a href="{$url}" style="color:#94a3b8;">clique aqui para se descadastrar</a>.
            </p>
            HTML;
    }
}
