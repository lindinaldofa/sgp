<?php

declare(strict_types=1);

namespace App\Jobs;

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

class SendBirthdayMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(OpenWaService $whatsapp, TemplateVariableResolverService $resolver): void
    {
        $today = now();

        $rules = AutomationRule::where('trigger', 'birthday')
            ->where('is_active', true)
            ->with(['template', 'tenant'])
            ->get();

        foreach ($rules as $rule) {
            if (! $rule->template || ! $rule->tenant) {
                continue;
            }

            $contacts = Contact::where('tenant_id', $rule->tenant_id)
                ->where('lgpd_consent', true)
                ->whereMonth('birth_date', $today->month)
                ->whereDay('birth_date', $today->day)
                ->get();

            foreach ($contacts as $contact) {
                try {
                    $phone = $contact->whatsapp ?? $contact->phone ?? '';
                    if (! $phone) {
                        continue;
                    }

                    $message = $resolver->resolve($rule->template->body_text ?? '', $contact, $rule->tenant);

                    $whatsapp->sendText($phone, $message, (int) $rule->tenant_id);
                } catch (\Throwable $e) {
                    Log::warning("Birthday message failed for contact {$contact->id}: ".$e->getMessage());
                }
            }
        }
    }
}
