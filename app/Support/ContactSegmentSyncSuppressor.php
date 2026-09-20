<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bulk contact operations (CSV import) create/update many contacts in a
 * tight loop; dispatching a per-contact SyncContactSegmentsJob for each one
 * is fine under a real queue worker, but under QUEUE_CONNECTION=sync it runs
 * inline and multiplies the request's cost by every active segment for
 * every row — a realistic way to blow through a shared host's execution
 * time limit. Bulk callers wrap their loop in `suppress()` and instead
 * recalculate affected segments once, in bulk, after the loop finishes.
 */
final class ContactSegmentSyncSuppressor
{
    private static bool $suppressed = false;

    public static function suppress(callable $callback): void
    {
        $previous = self::$suppressed;
        self::$suppressed = true;

        try {
            $callback();
        } finally {
            self::$suppressed = $previous;
        }
    }

    public static function isSuppressed(): bool
    {
        return self::$suppressed;
    }
}
