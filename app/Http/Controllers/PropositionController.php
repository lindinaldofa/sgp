<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PropositionStatus;
use App\Enums\PropositionType;
use App\Enums\VoteChoice;
use App\Http\Resources\PropositionResource;
use App\Models\Proposition;
use App\Models\PropositionStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PropositionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $propositions = Proposition::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('theme'), fn ($q) => $q->where('theme', $request->theme))
            ->when($request->filled('year'), fn ($q) => $q->where('year', (int) $request->year))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return PropositionResource::collection($propositions);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(PropositionType::class)],
            'number' => ['required', 'string', 'max:50'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'title' => ['required', 'string', 'max:500'],
            'summary' => ['nullable', 'string'],
            'full_text' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(PropositionStatus::class)],
            'theme' => ['nullable', 'string', 'max:255'],
            'territory_id' => ['nullable', 'integer', 'exists:territories,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'our_vote' => ['nullable', Rule::enum(VoteChoice::class)],
            'vote_justification' => ['nullable', 'string'],
            'filed_at' => ['nullable', 'date'],
            'voted_at' => ['nullable', 'date'],
        ]);

        $data['user_id'] = $this->typedUser($request)->id;
        $data['status'] = $data['status'] ?? PropositionStatus::Draft;

        $proposition = Proposition::create($data);

        return (new PropositionResource($proposition))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $ulid): PropositionResource
    {
        $proposition = Proposition::with(['attachments', 'statusHistory', 'territory', 'contact'])
            ->where('ulid', $ulid)
            ->firstOrFail();

        return new PropositionResource($proposition);
    }

    public function update(Request $request, string $ulid): PropositionResource
    {
        $proposition = Proposition::where('ulid', $ulid)->firstOrFail();

        $data = $request->validate([
            'type' => ['sometimes', 'required', Rule::enum(PropositionType::class)],
            'number' => ['sometimes', 'required', 'string', 'max:50'],
            'year' => ['sometimes', 'required', 'integer', 'min:2000', 'max:2100'],
            'title' => ['sometimes', 'required', 'string', 'max:500'],
            'summary' => ['nullable', 'string'],
            'full_text' => ['nullable', 'string'],
            'theme' => ['nullable', 'string', 'max:255'],
            'territory_id' => ['nullable', 'integer', 'exists:territories,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'our_vote' => ['nullable', Rule::enum(VoteChoice::class)],
            'vote_justification' => ['nullable', 'string'],
            'filed_at' => ['nullable', 'date'],
            'voted_at' => ['nullable', 'date'],
            'votes_for' => ['nullable', 'integer', 'min:0'],
            'votes_against' => ['nullable', 'integer', 'min:0'],
            'votes_abstained' => ['nullable', 'integer', 'min:0'],
        ]);

        $proposition->update($data);

        return new PropositionResource($proposition->fresh());
    }

    public function destroy(string $ulid): JsonResponse
    {
        $proposition = Proposition::where('ulid', $ulid)->firstOrFail();

        $proposition->delete();

        return response()->json(null, 204);
    }

    public function updateStatus(Request $request, string $ulid): PropositionResource
    {
        $proposition = Proposition::where('ulid', $ulid)->firstOrFail();

        $data = $request->validate([
            'new_status' => ['required', Rule::enum(PropositionStatus::class)],
            'note' => ['nullable', 'string'],
        ]);

        $oldStatus = $proposition->status?->value;

        $proposition->update(['status' => $data['new_status']]);

        PropositionStatusHistory::create([
            'proposition_id' => $proposition->id,
            'user_id' => $this->typedUser($request)->id,
            'old_status' => $oldStatus,
            'new_status' => $data['new_status'],
            'note' => $data['note'] ?? null,
            'changed_at' => now(),
        ]);

        return new PropositionResource($proposition->fresh());
    }

    public function publish(string $ulid): PropositionResource
    {
        $proposition = Proposition::where('ulid', $ulid)->firstOrFail();

        $proposition->update(['published_at' => now()]);

        return new PropositionResource($proposition->fresh());
    }

    public function stats(Request $request): JsonResponse
    {
        $stats = Proposition::query()
            ->select('type', 'status', DB::raw('count(*) as total'))
            ->groupBy('type', 'status')
            ->get();

        return response()->json($stats);
    }
}
