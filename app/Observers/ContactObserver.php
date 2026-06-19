<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\GeocodeContactJob;
use App\Jobs\SyncContactToMauticJob;
use App\Models\Contact;

class ContactObserver
{
    public function created(Contact $contact): void
    {
        if ($contact->address && ! $contact->latitude) {
            GeocodeContactJob::dispatch($contact->id)->delay(now()->addSeconds(5));
        }

        SyncContactToMauticJob::dispatch($contact->id);
    }

    public function updated(Contact $contact): void
    {
        if ($contact->wasChanged(['address', 'city', 'state', 'zip_code'])) {
            GeocodeContactJob::dispatch($contact->id)->delay(now()->addSeconds(5));
        }

        if ($contact->wasChanged(['name', 'email', 'phone', 'whatsapp', 'type'])) {
            SyncContactToMauticJob::dispatch($contact->id);
        }
    }
}
