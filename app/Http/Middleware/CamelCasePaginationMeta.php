<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's paginator always serializes `meta` with snake_case keys
 * (current_page, last_page, per_page...), but every hand-written Resource in
 * this app outputs camelCase — the frontend's PaginatedResponse/PaginationMeta
 * types only ever recognize the camelCase form. Left alone, `meta.lastPage` is
 * `undefined` on every paginated list, which silently hides the paginator
 * (`v-if="meta.lastPage > 1"` never passes). This normalizes just the
 * pagination `meta` block so every endpoint matches what the frontend expects,
 * without having to touch each controller individually.
 */
class CamelCasePaginationMeta
{
    private const MAP = [
        'current_page' => 'currentPage',
        'first_page_url' => 'firstPageUrl',
        'last_page' => 'lastPage',
        'last_page_url' => 'lastPageUrl',
        'next_page_url' => 'nextPageUrl',
        'prev_page_url' => 'prevPageUrl',
        'per_page' => 'perPage',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $data = $response->getData(true);

        if (! is_array($data) || ! is_array($data['meta'] ?? null)) {
            return $response;
        }

        foreach (self::MAP as $from => $to) {
            if (array_key_exists($from, $data['meta'])) {
                $data['meta'][$to] = $data['meta'][$from];
                unset($data['meta'][$from]);
            }
        }

        // The per-page numbered link list Laravel nests inside meta isn't used
        // by the frontend (it reads the top-level `links` block instead).
        unset($data['meta']['links']);

        return $response->setData($data);
    }
}
