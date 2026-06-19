<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyContactOnStatusChangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly ServiceRequest $serviceRequest,
    ) {}

    public function handle(): void
    {
        // TODO: implement contact notification logic (e.g. WhatsApp/SMS/email)
    }
}
