<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\TagResource;
use App\Jobs\SyncContactSegmentsJob;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tags = Tag::query()
            ->when($request->input('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();

        return TagResource::collection($tags);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $tag = Tag::create([
            'tenant_id' => $this->typedUser($request)->tenant_id,
            ...$validated,
        ]);

        return response()->json(new TagResource($tag), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'color' => ['sometimes', 'nullable', 'string', 'max:7'],
        ]);

        $tag->update($validated);

        return response()->json(new TagResource($tag));
    }

    public function destroy(int $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);
        $tag->delete();

        return response()->json(null, 204);
    }

    public function attach(Request $request, string $ulid): JsonResponse
    {
        $contact = Contact::where('ulid', $ulid)->firstOrFail();

        $validated = $request->validate([
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $contact->tags()->syncWithoutDetaching($validated['tag_ids']);
        SyncContactSegmentsJob::dispatch($contact->id);

        return response()->json([
            'message' => 'Tags attached successfully.',
            'tags' => TagResource::collection($contact->tags()->get()),
        ]);
    }

    public function detach(string $ulid, int $tagId): JsonResponse
    {
        $contact = Contact::where('ulid', $ulid)->firstOrFail();
        $contact->tags()->detach($tagId);
        SyncContactSegmentsJob::dispatch($contact->id);

        return response()->json(null, 204);
    }
}
