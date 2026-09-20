<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SyncContactSegmentMembershipAction;
use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncContactSegmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $contactId) {}

    public function handle(SyncContactSegmentMembershipAction $action): void
    {
        $contact = Contact::withoutGlobalScopes()->find($this->contactId);

        if ($contact) {
            $action->execute($contact);
        }
    }
}
