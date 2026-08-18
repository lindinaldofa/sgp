<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CampaignChannel;
use App\Models\AutomationRule;
use App\Models\Contact;
use App\Services\OpenWaService;
use App\Services\TemplateVariableResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNewContactAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $contactId,
        public readonly int $tagId,
    ) {}

    public function handle(OpenWaService $whatsapp, TemplateVariableResolverService $resolver): void
    {
        $contact = Contact::withoutGlobalScopes()->find($this->contactId);

        if (! $contact || ! $contact->lgpd_consent) {
            return;
        }

        $tenant = $contact->tenant;
        if (! $tenant) {
            return;
        }

        $rules = AutomationRule::withoutGlobalScopes()
            ->where('tenant_id', $contact->tenant_id)
            ->where('trigger', 'new_contact')
            ->where('tag_id', $this->tagId)
            ->where('is_active', true)
            ->with('template')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->channel !== CampaignChannel::WhatsApp || ! $rule->template) {
                Log::info("Automation rule {$rule->id} skipped: channel not implemented or missing template");

                continue;
            }

            $phone = $contact->whatsapp ?? $contact->phone ?? '';
            if (! $phone) {
                Log::warning("New-contact automation {$rule->id} skipped for contact {$contact->id}: no phone number");

                continue;
            }

            $message = $resolver->resolve($rule->template->body_text ?? '', $contact, $tenant);

            $sent = $whatsapp->sendText($phone, $message, (int) $rule->tenant_id);

            if (! $sent) {
                Log::warning("New-contact automation {$rule->id} failed to send WhatsApp to contact {$contact->id}");
            }
        }
    }
}
