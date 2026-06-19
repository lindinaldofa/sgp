<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEventRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $events = Event::where('reminder_sent', false)
            ->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', now())
            ->where('starts_at', '>', now())
            ->with('createdBy')
            ->get();

        foreach ($events as $event) {
            // TODO: send WhatsApp/SMS/email reminder to event creator and attendees
            $event->update(['reminder_sent' => true]);
        }
    }
}
