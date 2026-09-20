<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Jobs\SyncContactSegmentsJob;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->input('per_page', 30), 100);

        $query = Contact::query()->with(['tags', 'leader:id,name', 'createdBy:id,name']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('cpf', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($engagement = $request->input('engagement')) {
            $query->where('engagement_score', $engagement);
        }

        if ($leaderId = $request->input('leader_id')) {
            $query->where('leader_id', $leaderId);
        }

        if ($zone = $request->input('zone')) {
            $query->where('electoral_zone', $zone);
        }

        if ($city = $request->input('city')) {
            $query->where('city', 'like', "%{$city}%");
        }

        if ($state = $request->input('state')) {
            $query->where('state', $state);
        }

        if ($birthMonth = $request->input('birth_month')) {
            $query->whereMonth('birth_date', (int) $birthMonth);
        }

        if ($request->boolean('birth_today')) {
            $query->whereMonth('birth_date', now()->month)
                ->whereDay('birth_date', now()->day);
        }

        if ($request->has('lgpd_consent')) {
            $query->where('lgpd_consent', $request->boolean('lgpd_consent'));
        }

        if ($tagIds = $request->input('tag_ids')) {
            $tagIds = is_array($tagIds) ? $tagIds : [$tagIds];
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
        }

        return ContactResource::collection($query->paginate($perPage));
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        $contact = Contact::create($data);

        if ($tagIds !== null) {
            $contact->tags()->sync($tagIds);
            SyncContactSegmentsJob::dispatch($contact->id);
        }

        return (new ContactResource($contact->load('tags')))->response()->setStatusCode(201);
    }

    public function show(string $ulid): JsonResponse
    {
        $contact = Contact::with('tags', 'leader')->where('ulid', $ulid)->firstOrFail();

        return (new ContactResource($contact))->response();
    }

    public function update(UpdateContactRequest $request, string $ulid): JsonResponse
    {
        $contact = Contact::where('ulid', $ulid)->firstOrFail();

        $data = $request->validated();
        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        $contact->update($data);

        if ($tagIds !== null) {
            $contact->tags()->sync($tagIds);
            SyncContactSegmentsJob::dispatch($contact->id);
        }

        return (new ContactResource($contact->load('tags')))->response();
    }

    public function destroy(string $ulid): JsonResponse
    {
        $contact = Contact::where('ulid', $ulid)->firstOrFail();
        $contact->delete();

        return response()->json(null, 204);
    }

    public function restore(string $ulid): JsonResponse
    {
        $contact = Contact::withTrashed()->where('ulid', $ulid)->firstOrFail();
        $contact->restore();

        return (new ContactResource($contact->load('tags')))->response();
    }
}
