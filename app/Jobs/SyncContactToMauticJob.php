<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncContactToMauticJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $contactId) {}

    public function handle(): void
    {
        $contact = Contact::find($this->contactId);

        if (! $contact) {
            return;
        }

        $mauticUrl = config('services.mautic.url');
        $mauticUser = config('services.mautic.user');
        $mauticPass = config('services.mautic.password');

        if (! $mauticUrl) {
            return;
        }

        $nameParts = explode(' ', $contact->name, 2);
        $payload = [
            'firstname' => $nameParts[0],
            'lastname' => $nameParts[1] ?? '',
            'email' => $contact->email,
            'phone' => $contact->phone,
            'mobile' => $contact->whatsapp,
            'city' => $contact->city,
            'state' => $contact->state,
            'sgp_type' => $contact->type?->value,
            'sgp_engagement' => $contact->engagement_score,
        ];

        try {
            if ($contact->mautic_id) {
                Http::withBasicAuth($mauticUser, $mauticPass)
                    ->patch("{$mauticUrl}/api/contacts/{$contact->mautic_id}/edit", $payload);
            } else {
                $response = Http::withBasicAuth($mauticUser, $mauticPass)
                    ->post("{$mauticUrl}/api/contacts/new", $payload);

                if ($response->successful()) {
                    $contact->updateQuietly(['mautic_id' => $response->json('contact.id')]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Mautic sync failed for contact '.$contact->id.': '.$e->getMessage());
        }
    }
}
