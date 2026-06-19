<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GeoController extends Controller
{
    public function contacts(Request $request): JsonResponse
    {
        $tenantId = $this->typedUser($request)->tenant_id;

        $contacts = Contact::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('ulid', 'name', 'latitude', 'longitude', 'type', 'engagement_score')
            ->limit(5000)
            ->get()
            ->map(fn (Contact $c) => [
                'id' => $c->ulid,
                'name' => $c->name,
                'lat' => (float) $c->latitude,
                'lng' => (float) $c->longitude,
                'type' => $c->type?->value,
                'engagement' => $c->engagement_score,
            ]);

        return response()->json([
            'data' => $contacts,
            'meta' => ['total' => $contacts->count()],
        ]);
    }

    public function heatmap(Request $request): JsonResponse
    {
        $tenantId = $this->typedUser($request)->tenant_id;

        $data = DB::table('contacts')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('latitude')
            ->whereNull('deleted_at')
            ->select(DB::raw('neighborhood, electoral_zone, city, COUNT(*) as total, AVG(latitude) as center_lat, AVG(longitude) as center_lng'))
            ->groupBy('neighborhood', 'electoral_zone', 'city')
            ->get();

        return response()->json(['data' => $data]);
    }
}
