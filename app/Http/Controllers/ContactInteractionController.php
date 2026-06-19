<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InteractionType;
use App\Http\Resources\ContactInteractionResource;
use App\Models\Contact;
use App\Models\ContactInteractionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ContactInteractionController extends Controller
{
    public function index(string $ulid): AnonymousResourceCollection
    {
        $contact = Contact::where('ulid', $ulid)->firstOrFail();

        $interactions = ContactInteractionLog::with('user')
            ->where('contact_id', $contact->id)
            ->orderByDesc('occurred_at')
            ->get();

        return ContactInteractionResource::collection($interactions);
    }

    public function store(Request $request, string $ulid): JsonResponse
    {
        $contact = Contact::where('ulid', $ulid)->firstOrFail();

        $validated = $request->validate([
            'type' => ['required', Rule::enum(InteractionType::class)],
            'description' => ['required', 'string'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $interaction = ContactInteractionLog::create([
            'tenant_id' => $this->typedUser($request)->tenant_id,
            'contact_id' => $contact->id,
            'user_id' => auth()->id(),
            'type' => $validated['type'],
            'description' => $validated['description'],
            'occurred_at' => $validated['occurred_at'] ?? now(),
        ]);

        return response()->json(new ContactInteractionResource($interaction), 201);
    }
}
