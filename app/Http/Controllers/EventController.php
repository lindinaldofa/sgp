<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\EventType;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $events = Event::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('starts_from'), fn ($q) => $q->where('starts_at', '>=', $request->starts_from))
            ->when($request->filled('starts_to'), fn ($q) => $q->where('starts_at', '<=', $request->starts_to))
            ->orderBy('starts_at')
            ->paginate($request->integer('per_page', 15));

        return EventResource::collection($events);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::enum(EventType::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'is_online' => ['boolean'],
            'online_url' => ['nullable', 'url', 'max:500'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['boolean'],
            'territory_id' => ['nullable', 'integer', 'exists:territories,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'reminder_at' => ['nullable', 'date'],
        ]);

        $data['created_by'] = $this->typedUser($request)->id;

        $event = Event::create($data);

        return (new EventResource($event))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $ulid): EventResource
    {
        $event = Event::with(['attendees', 'contact', 'territory'])
            ->where('ulid', $ulid)
            ->firstOrFail();

        return new EventResource($event);
    }

    public function update(Request $request, string $ulid): EventResource
    {
        $event = Event::where('ulid', $ulid)->firstOrFail();

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'required', Rule::enum(EventType::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'is_online' => ['boolean'],
            'online_url' => ['nullable', 'url', 'max:500'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['boolean'],
            'territory_id' => ['nullable', 'integer', 'exists:territories,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'reminder_at' => ['nullable', 'date'],
        ]);

        $event->update($data);

        return new EventResource($event->fresh());
    }

    public function destroy(string $ulid): JsonResponse
    {
        $event = Event::where('ulid', $ulid)->firstOrFail();

        $event->delete();

        return response()->json(null, 204);
    }

    public function calendar(Request $request): JsonResponse
    {
        $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $year = (int) $request->year;
        $month = (int) $request->month;

        $events = Event::query()
            ->whereYear('starts_at', $year)
            ->whereMonth('starts_at', $month)
            ->orderBy('starts_at')
            ->get();

        $formatted = $events->map(fn (Event $event) => [
            'id' => $event->ulid,
            'title' => $event->title,
            'start' => $event->starts_at?->toIso8601String(),
            'end' => $event->ends_at?->toIso8601String(),
            'allDay' => $event->all_day,
            'extendedProps' => [
                'type' => $event->type?->value,
                'type_label' => $event->type?->label(),
                'location' => $event->location,
                'attendance' => $event->attendance?->value,
            ],
        ]);

        return response()->json($formatted);
    }

    public function recordAttendance(Request $request, string $ulid): EventResource
    {
        $event = Event::where('ulid', $ulid)->firstOrFail();

        $data = $request->validate([
            'attendance' => ['required', Rule::enum(AttendanceStatus::class)],
            'absence_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $event->update($data);

        return new EventResource($event->fresh());
    }

    public function upcoming(Request $request): AnonymousResourceCollection
    {
        $days = $request->integer('days', 7);

        $events = Event::query()
            ->where('starts_at', '>=', now())
            ->where('starts_at', '<=', now()->addDays($days))
            ->orderBy('starts_at')
            ->paginate($request->integer('per_page', 15));

        return EventResource::collection($events);
    }
}
