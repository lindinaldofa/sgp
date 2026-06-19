<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AssessorRoute;
use App\Models\AssessorRouteStop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessorRouteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $routes = AssessorRoute::with('stops')
            ->when($request->input('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->orderByDesc('planned_date')
            ->get();

        return response()->json(['data' => $routes]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'planned_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'stops' => ['nullable', 'array'],
            'stops.*.contact_id' => ['required_with:stops', 'integer', 'exists:contacts,id'],
            'stops.*.order' => ['nullable', 'integer'],
            'stops.*.notes' => ['nullable', 'string'],
        ]);

        $route = AssessorRoute::create([
            'tenant_id' => $this->typedUser($request)->tenant_id,
            'user_id' => $validated['user_id'] ?? auth()->id(),
            'name' => $validated['name'],
            'planned_date' => $validated['planned_date'] ?? null,
            'status' => $validated['status'] ?? 'draft',
        ]);

        foreach ($validated['stops'] ?? [] as $index => $stopData) {
            AssessorRouteStop::create([
                'route_id' => $route->id,
                'contact_id' => $stopData['contact_id'],
                'order' => $stopData['order'] ?? $index,
                'notes' => $stopData['notes'] ?? null,
                'visited' => false,
            ]);
        }

        return response()->json($route->load('stops'), 201);
    }

    public function show(string $ulid): JsonResponse
    {
        $route = AssessorRoute::with('stops.contact')->where('ulid', $ulid)->firstOrFail();

        return response()->json($route);
    }

    public function update(Request $request, string $ulid): JsonResponse
    {
        $route = AssessorRoute::where('ulid', $ulid)->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'planned_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $route->update($validated);

        return response()->json($route->load('stops'));
    }

    public function destroy(string $ulid): JsonResponse
    {
        $route = AssessorRoute::where('ulid', $ulid)->firstOrFail();
        $route->stops()->delete();
        $route->delete();

        return response()->json(null, 204);
    }

    /**
     * Optimizes the route stop order using a nearest-neighbor heuristic.
     *
     * Starts from the first stop (by current order) and at each step picks
     * the closest unvisited stop by Euclidean distance of lat/lng.
     * Falls back to original order if contacts lack coordinates.
     */
    public function optimize(string $ulid): JsonResponse
    {
        $route = AssessorRoute::with('stops.contact')->where('ulid', $ulid)->firstOrFail();

        $stops = $route->stops->sortBy('order')->values();

        if ($stops->count() < 2) {
            return response()->json($route->load('stops.contact'));
        }

        $count = $stops->count();
        $remaining = range(0, $count - 1);
        $current = array_shift($remaining);
        $orderedIndices = [$current];

        while (! empty($remaining)) {
            $stop = $stops[$current];
            $currentLat = (float) ($stop->contact->latitude ?? 0);
            $currentLng = (float) ($stop->contact->longitude ?? 0);

            $nearestKey = null;
            $nearestDist = PHP_FLOAT_MAX;

            foreach ($remaining as $key => $idx) {
                $candidate = $stops[$idx];
                $lat = (float) ($candidate->contact->latitude ?? 0);
                $lng = (float) ($candidate->contact->longitude ?? 0);
                $dist = (($lat - $currentLat) ** 2) + (($lng - $currentLng) ** 2);

                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearestKey = $key;
                }
            }

            $current = $remaining[$nearestKey ?? throw new \RuntimeException('No nearest stop found')];
            $orderedIndices[] = $current;
            unset($remaining[$nearestKey]);
            $remaining = array_values($remaining);
        }

        foreach ($orderedIndices as $newOrder => $stopIdx) {
            $stop = $stops[$stopIdx] ?? throw new \RuntimeException("Stop index {$stopIdx} not found");
            AssessorRouteStop::where('id', $stop->getKey())->update(['order' => $newOrder]);
        }

        return response()->json($route->refresh()->load('stops.contact'));
    }

    public function visitStop(string $ulid, int $stopId): JsonResponse
    {
        $route = AssessorRoute::where('ulid', $ulid)->firstOrFail();

        $stop = AssessorRouteStop::where('id', $stopId)
            ->where('route_id', $route->id)
            ->firstOrFail();

        $stop->update([
            'visited' => true,
            'visited_at' => now(),
        ]);

        return response()->json([
            'id' => $stop->id,
            'route_id' => $stop->route_id,
            'contact_id' => $stop->contact_id,
            'order' => $stop->order,
            'visited' => $stop->visited,
            'visited_at' => $stop->visited_at?->toIso8601String(),
            'notes' => $stop->notes,
        ]);
    }
}
