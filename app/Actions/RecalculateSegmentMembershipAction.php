<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Contact;
use App\Models\Segment;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes a segment's full membership from its filter rules. Members added
 * manually (source=manual) are never touched here — only filter-sourced rows
 * are added or dropped, mirroring how they were created.
 */
final class RecalculateSegmentMembershipAction
{
    public function __construct(private readonly ApplySegmentFiltersToQuery $applyFilters) {}

    public function execute(Segment $segment): int
    {
        $query = Contact::query()->where('tenant_id', $segment->tenant_id);
        $this->applyFilters->apply($query, $segment->filters ?? []);
        $matchedIds = $query->pluck('id');

        $existingIds = DB::table('segment_contact')->where('segment_id', $segment->id)->pluck('contact_id');
        $toAdd = $matchedIds->diff($existingIds);

        if ($toAdd->isNotEmpty()) {
            $now = now();
            $rows = $toAdd->map(fn ($id) => [
                'segment_id' => $segment->id,
                'contact_id' => $id,
                'source' => 'filter',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('segment_contact')->insert($chunk);
            }
        }

        DB::table('segment_contact')
            ->where('segment_id', $segment->id)
            ->where('source', 'filter')
            ->whereNotIn('contact_id', $matchedIds->all())
            ->delete();

        $total = DB::table('segment_contact')->where('segment_id', $segment->id)->count();
        $segment->update(['last_recalculated_at' => now()]);

        return $total;
    }
}
