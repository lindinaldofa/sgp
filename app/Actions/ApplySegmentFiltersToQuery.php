<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Database\Eloquent\Builder;

/**
 * Translates a segment's stored filter rules into query constraints. Shared by
 * segment membership recalculation (bulk and per-contact) so the definition of
 * "matches the segment" lives in exactly one place.
 */
final class ApplySegmentFiltersToQuery
{
    private const ALLOWED_NATIVE_FIELDS = [
        'type', 'gender', 'city', 'state', 'neighborhood', 'profession',
        'electoral_zone', 'electoral_section', 'zip_code', 'engagement_score',
        'lgpd_consent', 'birth_date', 'created_at', 'leader_id',
    ];

    /** @param array<int, array{field?: string, operator?: string, value?: mixed}> $rules */
    public function apply(Builder $query, array $rules): Builder
    {
        foreach ($rules as $rule) {
            $field = (string) ($rule['field'] ?? '');
            $operator = (string) ($rule['operator'] ?? '');
            $value = $rule['value'] ?? null;

            if ($field === '' || $operator === '') {
                continue;
            }

            if ($field === 'tags') {
                $this->applyTagsRule($query, $operator, (array) $value);

                continue;
            }

            $isCustom = str_starts_with($field, 'custom.');

            if (! $isCustom && ! in_array($field, self::ALLOWED_NATIVE_FIELDS, true)) {
                continue;
            }

            $column = $isCustom ? 'custom_data->'.substr($field, 7) : $field;

            $this->applyOperator($query, $column, $operator, $value);
        }

        return $query;
    }

    private function applyOperator(Builder $query, string $column, string $operator, mixed $value): void
    {
        match ($operator) {
            'equals' => $query->where($column, $value),
            'not_equals' => $query->where($column, '!=', $value),
            'contains' => $query->where($column, 'like', '%'.$value.'%'),
            'not_contains' => $query->where($column, 'not like', '%'.$value.'%'),
            'is_empty' => $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, '')),
            'is_not_empty' => $query->where(fn ($q) => $q->whereNotNull($column)->where($column, '!=', '')),
            'greater_than' => $query->where($column, '>', $value),
            'less_than' => $query->where($column, '<', $value),
            'between' => $query->whereBetween($column, [
                is_array($value) ? ($value[0] ?? null) : null,
                is_array($value) ? ($value[1] ?? null) : null,
            ]),
            'in' => $query->whereIn($column, (array) $value),
            'not_in' => $query->whereNotIn($column, (array) $value),
            'before' => $query->where($column, '<', $value),
            'after' => $query->where($column, '>', $value),
            default => null,
        };
    }

    /** @param array<int, mixed> $tagIds */
    private function applyTagsRule(Builder $query, string $operator, array $tagIds): void
    {
        if (empty($tagIds)) {
            return;
        }

        match ($operator) {
            'any_of' => $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds)),
            'all_of' => $query->where(function ($q) use ($tagIds) {
                foreach ($tagIds as $tagId) {
                    $q->whereHas('tags', fn ($qq) => $qq->where('tags.id', $tagId));
                }
            }),
            'none_of' => $query->whereDoesntHave('tags', fn ($q) => $q->whereIn('tags.id', $tagIds)),
            default => null,
        };
    }
}
