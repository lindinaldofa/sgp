<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Contact;
use App\Services\GeocodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeocodeContactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $contactId) {}

    public function handle(GeocodingService $geocodingService): void
    {
        $contact = Contact::find($this->contactId);

        if (! $contact || ! $contact->zip_code) {
            return;
        }

        $address = implode(', ', array_filter([
            $contact->address,
            $contact->neighborhood,
            $contact->city,
            $contact->state,
            'Brasil',
        ]));

        $coordinates = $geocodingService->geocode($address);

        if ($coordinates) {
            $contact->updateQuietly([
                'latitude' => $coordinates['lat'],
                'longitude' => $coordinates['lng'],
            ]);
        }
    }
}
