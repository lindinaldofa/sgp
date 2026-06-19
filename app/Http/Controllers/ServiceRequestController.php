<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ServiceRequestStatus;
use App\Http\Requests\StoreServiceRequestRequest;
use App\Http\Requests\UpdateServiceRequestRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Jobs\NotifyContactOnStatusChangeJob;
use App\Models\Contact;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttachment;
use App\Models\ServiceRequestUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ServiceRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->input('per_page', 30), 100);

        $query = ServiceRequest::query()->with('contact', 'category', 'assignedTo');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        if ($assignedTo = $request->input('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($contactId = $request->input('contact_id')) {
            $query->where('contact_id', $contactId);
        }

        if ($contactUlid = $request->input('contact_ulid')) {
            $contactRecord = Contact::where('ulid', $contactUlid)->first();
            $query->where('contact_id', $contactRecord?->id ?? 0);
        }

        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }

        if ($request->boolean('overdue')) {
            $resolvedStatuses = [ServiceRequestStatus::Resolved->value, ServiceRequestStatus::Archived->value];
            $query->whereNotNull('due_date')
                ->where('due_date', '<', now())
                ->whereNotIn('status', $resolvedStatuses);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($createdFrom = $request->input('created_from')) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }

        if ($createdTo = $request->input('created_to')) {
            $query->whereDate('created_at', '<=', $createdTo);
        }

        return ServiceRequestResource::collection($query->orderByDesc('created_at')->paginate($perPage));
    }

    public function store(StoreServiceRequestRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Resolve contact ULID → numeric id
        $contact = Contact::where('ulid', $data['contact_id'])->firstOrFail();
        $data['contact_id'] = $contact->id;

        $data['tenant_id'] = $this->typedUser($request)->tenant_id;
        $data['created_by'] = auth()->id();

        $serviceRequest = ServiceRequest::create($data);

        return response()->json(
            new ServiceRequestResource($serviceRequest->load('contact', 'category', 'assignedTo')),
            201,
        );
    }

    public function show(string $ulid): JsonResponse
    {
        $serviceRequest = ServiceRequest::with('contact', 'category', 'assignedTo', 'updates')
            ->where('ulid', $ulid)
            ->firstOrFail();

        return response()->json(new ServiceRequestResource($serviceRequest));
    }

    public function update(UpdateServiceRequestRequest $request, string $ulid): JsonResponse
    {
        $serviceRequest = ServiceRequest::where('ulid', $ulid)->firstOrFail();
        $serviceRequest->update($request->validated());

        return response()->json(
            new ServiceRequestResource($serviceRequest->load('contact', 'category', 'assignedTo')),
        );
    }

    public function destroy(string $ulid): JsonResponse
    {
        $serviceRequest = ServiceRequest::where('ulid', $ulid)->firstOrFail();
        $serviceRequest->delete();

        return response()->json(null, 204);
    }

    public function updateStatus(Request $request, string $ulid): JsonResponse
    {
        $serviceRequest = ServiceRequest::where('ulid', $ulid)->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ServiceRequestStatus::class)],
            'note' => ['nullable', 'string'],
            'notify_contact' => ['nullable', 'boolean'],
        ]);

        $oldStatus = $serviceRequest->status?->value;

        $updateData = ['status' => $validated['status']];

        if ($validated['status'] === ServiceRequestStatus::Resolved->value) {
            $updateData['resolved_at'] = now();
        }

        $serviceRequest->update($updateData);

        ServiceRequestUpdate::create([
            'service_request_id' => $serviceRequest->id,
            'user_id' => auth()->id(),
            'old_status' => $oldStatus,
            'new_status' => $validated['status'],
            'note' => $validated['note'] ?? null,
            'notify_contact' => $validated['notify_contact'] ?? false,
            'created_at' => now(),
        ]);

        if ($validated['notify_contact'] ?? false) {
            NotifyContactOnStatusChangeJob::dispatch($serviceRequest);
        }

        return response()->json(
            new ServiceRequestResource($serviceRequest->load('contact', 'category', 'assignedTo')),
        );
    }

    public function addUpdate(Request $request, string $ulid): JsonResponse
    {
        $serviceRequest = ServiceRequest::where('ulid', $ulid)->firstOrFail();

        $validated = $request->validate([
            'note' => ['required', 'string'],
        ]);

        $update = ServiceRequestUpdate::create([
            'service_request_id' => $serviceRequest->id,
            'user_id' => auth()->id(),
            'old_status' => null,
            'new_status' => null,
            'note' => $validated['note'],
            'notify_contact' => false,
            'created_at' => now(),
        ]);

        return response()->json([
            'id' => $update->id,
            'service_request_id' => $update->service_request_id,
            'user_id' => $update->user_id,
            'note' => $update->note,
            'created_at' => $update->created_at?->toIso8601String(),
        ], 201);
    }

    public function uploadAttachment(Request $request, string $ulid): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,png,doc,docx', 'max:10240'],
        ]);

        $serviceRequest = ServiceRequest::where('ulid', $ulid)->firstOrFail();

        $uploaded = $request->file('file');
        $path = $uploaded->store("service-requests/{$ulid}", 's3');

        $attachment = ServiceRequestAttachment::create([
            'service_request_id' => $serviceRequest->id,
            'user_id' => auth()->id(),
            'filename' => $uploaded->getClientOriginalName(),
            'path' => $path,
            'size' => $uploaded->getSize(),
            'mime_type' => $uploaded->getMimeType(),
            'created_at' => now(),
        ]);

        return response()->json([
            'id' => $attachment->id,
            'filename' => $attachment->filename,
            'path' => $attachment->path,
            'size' => $attachment->size,
            'mime_type' => $attachment->mime_type,
        ], 201);
    }
}
