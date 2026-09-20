<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\FindOrCreateContactAction;
use App\Models\ContactImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessContactImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(
        public readonly ContactImport $import,
    ) {}

    public function handle(FindOrCreateContactAction $findOrCreateContact): void
    {
        $import = $this->import;
        $import->update(['status' => 'processing', 'started_at' => now()]);

        $fullPath = Storage::disk('local')->path($import->filename);
        $handle = fopen($fullPath, 'r');

        if ($handle === false) {
            $import->update(['status' => 'failed', 'completed_at' => now()]);

            return;
        }

        $headers = $import->headers ?? [];
        /** @var array<string, string> $mapping header => target field key ("custom.{key}" for custom fields) */
        $mapping = $import->column_mapping ?? [];
        $tagIds = $import->tag_id ? [$import->tag_id] : [];

        fgetcsv($handle, 0, ','); // skip header row, already captured on upload

        $processed = 0;
        $errors = 0;
        $errorLog = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rowNumber++;

            if ($row === [null]) {
                continue;
            }

            try {
                $valuesByTarget = [];
                foreach ($headers as $i => $header) {
                    $target = $mapping[$header] ?? null;
                    if (! $target) {
                        continue;
                    }
                    $value = $row[$i] ?? null;
                    if ($value !== null && $value !== '') {
                        $valuesByTarget[$target] = $value;
                    }
                }

                [$native, $custom] = FindOrCreateContactAction::splitNativeAndCustom($valuesByTarget);

                if (empty($native) && empty($custom)) {
                    continue;
                }

                // Imports never trigger campaign automations — a bulk import
                // sharing a tag with an active automation would otherwise fan
                // out one send per row, which nobody asked for here.
                $findOrCreateContact->execute(
                    tenantId: (int) $import->tenant_id,
                    nativeData: $native,
                    customData: $custom,
                    tagIds: $tagIds,
                );

                $processed++;
            } catch (\Throwable $e) {
                $errors++;
                $errorLog[] = ['row' => $rowNumber, 'error' => $e->getMessage()];
            }

            if (($processed + $errors) % 50 === 0) {
                $import->update(['processed_rows' => $processed, 'error_rows' => $errors]);
            }
        }

        fclose($handle);

        $import->update([
            'status' => 'completed',
            'processed_rows' => $processed,
            'error_rows' => $errors,
            'error_log' => $errorLog ?: null,
            'completed_at' => now(),
        ]);
    }
}
