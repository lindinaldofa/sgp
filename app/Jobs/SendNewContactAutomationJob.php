<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\AddContactToCampaignAction;
use App\Enums\CampaignChannel;
use App\Models\AutomationRule;
use App\Models\Contact;
use App\Models\Tenant;
use App\Services\OpenWaService;
use App\Services\TemplateVariableResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Reacts to a contact being created or updated through an integration source
 * that carries a given tag. Two kinds of "new_contact" automation rules exist:
 *
 * - Campaign-backed rules (campaign_id set): fire on both creation and update.
 *   Idempotent — a contact only ever becomes a recipient of a given campaign
 *   once, so repeated integration submissions never resend it.
 * - Legacy template-backed rules (template_id set, WhatsApp-only): fire only
 *   once, on creation, as before — there is no per-contact tracking table for
 *   this path, so re-firing on every update would resend on every submission.
 */
class SendNewContactAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $contactId,
        public readonly int $tagId,
        public readonly bool $isNewContact = true,
    ) {}

    public function handle(OpenWaService $whatsapp, TemplateVariableResolverService $resolver): void
    {
        $contact = Contact::withoutGlobalScopes()->find($this->contactId);

        if (! $contact || ! $contact->lgpd_consent || $contact->unsubscribed_at) {
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
            ->with(['template', 'campaign'])
            ->get();

        foreach ($rules as $rule) {
            if ($rule->campaign_id) {
                $this->triggerCampaign($rule, $contact);

                continue;
            }

            if (! $this->isNewContact) {
                continue;
            }

            $this->triggerTemplate($rule, $contact, $tenant, $whatsapp, $resolver);
        }
    }

    private function triggerCampaign(AutomationRule $rule, Contact $contact): void
    {
        $campaign = $rule->campaign;
        if (! $campaign) {
            Log::info("Automation rule {$rule->id} skipped: campaign not found");

            return;
        }

        app(AddContactToCampaignAction::class)->execute($campaign, $contact, dispatch: 'always');
    }

    private function triggerTemplate(
        AutomationRule $rule,
        Contact $contact,
        Tenant $tenant,
        OpenWaService $whatsapp,
        TemplateVariableResolverService $resolver,
    ): void {
        if ($rule->channel !== CampaignChannel::WhatsApp || ! $rule->template) {
            Log::info("Automation rule {$rule->id} skipped: channel not implemented or missing template");

            return;
        }

        $phone = $contact->whatsapp ?? $contact->phone ?? '';
        if (! $phone) {
            Log::warning("New-contact automation {$rule->id} skipped for contact {$contact->id}: no phone number");

            return;
        }

        $message = $resolver->resolve($rule->template->body_text ?? '', $contact, $tenant);

        $sent = $whatsapp->sendText($phone, $message, (int) $rule->tenant_id);

        if (! $sent) {
            Log::warning("New-contact automation {$rule->id} failed to send WhatsApp to contact {$contact->id}");
        }
    }
}
