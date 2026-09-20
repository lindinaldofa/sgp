<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\FindOrCreateContactAction;
use App\Actions\RecalculateSegmentMembershipAction;
use App\Models\ContactImport;
use App\Models\Segment;
use App\Support\ContactSegmentSyncSuppressor;
use App\Support\CsvFileReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessContactImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(
        public readonly ContactImport $import,
    ) {}

    public function handle(FindOrCreateContactAction $findOrCreateContact, RecalculateSegmentMembershipAction $recalculateSegment): void
    {
        // With QUEUE_CONNECTION=sync this runs inline inside the HTTP request
        // that triggered it, so PHP's own max_execution_time (often 30s on
        // shared hosting) — not Laravel's $timeout above, which only applies
        // to a real queue worker process — is what would otherwise kill a
        // large import mid-way with a bare, unhelpful 500.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $import = $this->import;

        try {
            $this->process($import, $findOrCreateContact, $recalculateSegment);
        } catch (\Throwable $e) {
            Log::error("Contact import {$import->id} failed: {$e->getMessage()}");
            $import->update([
                'status' => 'failed',
                'error_log' => array_merge($import->error_log ?? [], [['row' => null, 'error' => $e->getMessage()]]),
                'completed_at' => now(),
            ]);
        }
    }

    private function process(ContactImport $import, FindOrCreateContactAction $findOrCreateContact, RecalculateSegmentMembershipAction $recalculateSegment): void
    {
        $import->update(['status' => 'processing', 'started_at' => now()]);

        $fullPath = Storage::disk('local')->path($import->filename);
        $reader = CsvFileReader::open($fullPath);

        if (! $reader) {
            $import->update(['status' => 'failed', 'completed_at' => now()]);

            return;
        }

        $headers = $import->headers ?? [];
        /** @var array<string, string> $mapping header => target field key ("custom.{key}" for custom fields) */
        $mapping = $import->column_mapping ?? [];
        $tagIds = $import->tag_id ? [$import->tag_id] : [];

        $reader->readRow(); // skip header row, already captured on upload

        $processed = 0;
        $errors = 0;
        $errorLog = [];
        $rowNumber = 1;

        // Per-row segment sync would fan out a full segment-matching pass for
        // every single row (see ContactSegmentSyncSuppressor) — suppressed
        // here and replaced with one bulk recalculation per segment below.
        ContactSegmentSyncSuppressor::suppress(function () use (
            $reader, $headers, $mapping, $tagIds, $import, $findOrCreateContact,
            &$processed, &$errors, &$errorLog, &$rowNumber,
        ) {
            while (($row = $reader->readRow()) !== null) {
                $rowNumber++;

                if (count($row) === 1 && ($row[0] === null || $row[0] === '')) {
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
        });

        $reader->close();

        if ($processed > 0) {
            Segment::where('tenant_id', $import->tenant_id)->where('is_active', true)->get()
                ->each(fn (Segment $segment) => $recalculateSegment->execute($segment));
        }

        $import->update([
            'status' => 'completed',
            'processed_rows' => $processed,
            'error_rows' => $errors,
            'error_log' => $errorLog ?: null,
            'completed_at' => now(),
        ]);
    }
}
