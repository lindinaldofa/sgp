<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateIntegrationSourceAction;
use App\Actions\RegenerateIntegrationSourceTokenAction;
use App\Data\CreateIntegrationSourceData;
use App\Http\Resources\IntegrationSourceResource;
use App\Models\IntegrationSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IntegrationSourceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $sources = IntegrationSource::with('tag')->latest()->get();

        return IntegrationSourceResource::collection($sources);
    }

    public function store(Request $request, CreateIntegrationSourceAction $action): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tag_id' => ['nullable', 'integer', 'exists:tags,id'],
        ]);

        $result = $action->execute(new CreateIntegrationSourceData(
            tenantId: $this->typedUser($request)->tenant_id,
            name: $validated['name'],
            tagId: $validated['tag_id'] ?? null,
        ));

        return response()->json([
            'source' => new IntegrationSourceResource($result['source']->load('tag')),
            'token' => $result['plainToken'],
        ], 201);
    }

    public function update(Request $request, string $ulid): JsonResponse
    {
        $source = IntegrationSource::where('ulid', $ulid)->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'tag_id' => ['sometimes', 'required', 'integer', 'exists:tags,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $source->update($validated);

        return response()->json(new IntegrationSourceResource($source->refresh()->load('tag')));
    }

    public function destroy(string $ulid): JsonResponse
    {
        $source = IntegrationSource::where('ulid', $ulid)->firstOrFail();
        $source->delete();

        return response()->json(null, 204);
    }

    public function regenerateToken(string $ulid, RegenerateIntegrationSourceTokenAction $action): JsonResponse
    {
        $source = IntegrationSource::where('ulid', $ulid)->firstOrFail();
        $result = $action->execute($source);

        return response()->json([
            'source' => new IntegrationSourceResource($result['source']->load('tag')),
            'token' => $result['plainToken'],
        ]);
    }
}
