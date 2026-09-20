<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RecalculateSegmentMembershipAction;
use App\Http\Resources\ContactResource;
use App\Http\Resources\SegmentResource;
use App\Models\Contact;
use App\Models\Segment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SegmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $segments = Segment::query()
            ->withCount('contacts')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%'))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return SegmentResource::collection($segments);
    }

    public function store(Request $request, RecalculateSegmentMembershipAction $recalculate): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'filters' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $segment = Segment::create($data);
        $recalculate->execute($segment);

        return (new SegmentResource($segment->fresh()->loadCount('contacts')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $ulid): SegmentResource
    {
        $segment = Segment::where('ulid', $ulid)->withCount('contacts')->firstOrFail();

        return new SegmentResource($segment);
    }

    public function update(Request $request, string $ulid, RecalculateSegmentMembershipAction $recalculate): SegmentResource
    {
        $segment = Segment::where('ulid', $ulid)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'filters' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $filtersChanged = array_key_exists('filters', $data) && $data['filters'] !== $segment->filters;

        $segment->update($data);

        if ($filtersChanged) {
            $recalculate->execute($segment);
        }

        return new SegmentResource($segment->fresh()->loadCount('contacts'));
    }

    public function destroy(string $ulid): JsonResponse
    {
        Segment::where('ulid', $ulid)->firstOrFail()->delete();

        return response()->json(null, 204);
    }

    public function recalculate(string $ulid, RecalculateSegmentMembershipAction $recalculate): JsonResponse
    {
        $segment = Segment::where('ulid', $ulid)->firstOrFail();
        $total = $recalculate->execute($segment);

        return response()->json([
            'message' => "Segmento recalculado: {$total} contato(s).",
            'contactsCount' => $total,
        ]);
    }

    public function contacts(string $ulid): AnonymousResourceCollection
    {
        $segment = Segment::where('ulid', $ulid)->firstOrFail();

        $contacts = $segment->contacts()->with('tags')->paginate(15);

        return ContactResource::collection($contacts);
    }

    public function addContact(Request $request, string $ulid): JsonResponse
    {
        $segment = Segment::where('ulid', $ulid)->firstOrFail();

        $data = $request->validate([
            'contact_id' => ['required', 'string', 'exists:contacts,ulid'],
        ]);

        $contact = Contact::where('ulid', $data['contact_id'])->firstOrFail();

        $this->addContactToSegment($segment, $contact);

        return (new ContactResource($contact->load('tags')))
            ->response()
            ->setStatusCode(201);
    }

    public function bulkAddContacts(Request $request, string $ulid): JsonResponse
    {
        $segment = Segment::where('ulid', $ulid)->firstOrFail();

        $data = $request->validate([
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['string', 'exists:contacts,ulid'],
        ]);

        $contacts = Contact::whereIn('ulid', $data['contact_ids'])->get();

        foreach ($contacts as $contact) {
            $this->addContactToSegment($segment, $contact);
        }

        return response()->json([
            'message' => "{$contacts->count()} contato(s) adicionado(s) ao segmento \"{$segment->name}\".",
            'contactsCount' => DB::table('segment_contact')->where('segment_id', $segment->id)->count(),
        ]);
    }

    /**
     * Upserts a contact as a segment member with source=manual — "sticky"
     * membership that survives future filter recalculations even if the
     * contact stops matching. Shared by the single and bulk add endpoints.
     */
    private function addContactToSegment(Segment $segment, Contact $contact): void
    {
        $existing = DB::table('segment_contact')
            ->where('segment_id', $segment->id)
            ->where('contact_id', $contact->id)
            ->first();

        if ($existing) {
            if ($existing->source !== 'manual') {
                DB::table('segment_contact')
                    ->where('segment_id', $segment->id)
                    ->where('contact_id', $contact->id)
                    ->update(['source' => 'manual', 'updated_at' => now()]);
            }
        } else {
            DB::table('segment_contact')->insert([
                'segment_id' => $segment->id,
                'contact_id' => $contact->id,
                'source' => 'manual',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function removeContact(string $ulid, string $contactUlid): JsonResponse
    {
        $segment = Segment::where('ulid', $ulid)->firstOrFail();
        $contact = Contact::where('ulid', $contactUlid)->firstOrFail();

        DB::table('segment_contact')
            ->where('segment_id', $segment->id)
            ->where('contact_id', $contact->id)
            ->delete();

        return response()->json(null, 204);
    }
}
