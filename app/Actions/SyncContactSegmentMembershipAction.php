<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Contact;
use App\Models\Segment;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates a single contact against every active segment of its tenant,
 * keeping filter-sourced membership in sync in near real time whenever the
 * contact (or its tags) change. Manually-added/removed rows are never
 * touched — only source=filter membership is added or dropped here.
 */
final class SyncContactSegmentMembershipAction
{
    public function __construct(private readonly ApplySegmentFiltersToQuery $applyFilters) {}

    public function execute(Contact $contact): void
    {
        $segments = Segment::withoutGlobalScopes()
            ->where('tenant_id', $contact->tenant_id)
            ->where('is_active', true)
            ->get();

        foreach ($segments as $segment) {
            $query = Contact::withoutGlobalScopes()
                ->where('tenant_id', $contact->tenant_id)
                ->where('id', $contact->id);
            $this->applyFilters->apply($query, $segment->filters ?? []);
            $matches = $query->exists();

            $existing = DB::table('segment_contact')
                ->where('segment_id', $segment->id)
                ->where('contact_id', $contact->id)
                ->first();

            if ($matches && ! $existing) {
                DB::table('segment_contact')->insert([
                    'segment_id' => $segment->id,
                    'contact_id' => $contact->id,
                    'source' => 'filter',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif (! $matches && $existing && $existing->source === 'filter') {
                DB::table('segment_contact')
                    ->where('segment_id', $segment->id)
                    ->where('contact_id', $contact->id)
                    ->delete();
            }
        }
    }
}
