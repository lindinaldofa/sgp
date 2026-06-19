<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Territory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerritoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $territories = Territory::with('leader', 'assignedUser')
            ->where('tenant_id', $this->typedUser($request)->tenant_id)
            ->get();

        return response()->json(['data' => $territories]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'geojson' => ['required', 'json'],
            'color' => ['nullable', 'string', 'max:7'],
            'leader_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $territory = Territory::create([
            'tenant_id' => $this->typedUser($request)->tenant_id,
            ...$validated,
        ]);

        return response()->json($territory, 201);
    }

    public function show(int $id): JsonResponse
    {
        $territory = Territory::with('leader', 'assignedUser')->findOrFail($id);

        return response()->json($territory);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $territory = Territory::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'color' => ['sometimes', 'nullable', 'string', 'max:7'],
            'geojson' => ['sometimes', 'nullable', 'array'],
            'leader_id' => ['sometimes', 'nullable', 'integer', 'exists:contacts,id'],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $territory->update($validated);

        return response()->json($territory);
    }

    public function destroy(int $id): JsonResponse
    {
        $territory = Territory::findOrFail($id);
        $territory->delete();

        return response()->json(null, 204);
    }

    /**
     * Returns contacts inside a territory polygon.
     *
     * TODO: Replace with a proper point-in-polygon service (e.g. PostGIS ST_Contains)
     * that checks whether each contact's lat/lng falls inside territory->geojson polygon.
     * For now, returns all contacts with coordinates for the tenant.
     */
    public function contactsInTerritory(int $id): JsonResponse
    {
        $territory = Territory::findOrFail($id);

        // TODO: use a PointInPolygonService to filter contacts by territory->geojson boundary
        $contacts = Contact::where('tenant_id', $territory->tenant_id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'ulid', 'name', 'latitude', 'longitude', 'type', 'engagement_score')
            ->limit(500)
            ->get()
            ->map(fn (Contact $c) => [
                'id' => $c->id,
                'ulid' => $c->ulid,
                'name' => $c->name,
                'latitude' => (float) $c->latitude,
                'longitude' => (float) $c->longitude,
                'type' => $c->type?->value,
                'engagement_score' => $c->engagement_score,
            ]);

        return response()->json([
            'territory' => $territory->only('id', 'name', 'color', 'geojson'),
            'contacts' => $contacts,
            'total' => $contacts->count(),
        ]);
    }
}
