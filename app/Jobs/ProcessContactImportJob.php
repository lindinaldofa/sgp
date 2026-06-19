<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ContactImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessContactImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly ContactImport $import,
    ) {}

    public function handle(): void
    {
        // TODO: implement CSV parsing and contact creation logic
    }
}
