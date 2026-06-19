<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $documents = Document::with('folder')
            ->when($request->filled('folder_id'), fn ($q) => $q->where('folder_id', $request->folder_id))
            ->when($request->has('folder_id') && $request->input('folder_id') === null, fn ($q) => $q->whereNull('folder_id'))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'ilike', '%'.$request->search.'%'))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return DocumentResource::collection($documents);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file'        => ['required', 'file', 'max:51200'],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'folder_id'   => ['nullable', 'integer', 'exists:document_folders,id'],
            'visibility'  => ['nullable', 'string', 'in:private,team,public'],
            'is_template' => ['boolean'],
        ]);

        $file = $request->file('file');
        $path = $file->store('documents', 'local');

        $document = Document::create([
            'name'             => $request->input('name'),
            'description'      => $request->input('description'),
            'folder_id'        => $request->input('folder_id'),
            'visibility'       => $request->input('visibility', 'team'),
            'is_template'      => $request->boolean('is_template', false),
            'path'             => $path,
            'mime_type'        => $file->getMimeType(),
            'size'             => $file->getSize(),
            'created_by'       => $this->typedUser($request)->id,
            'version'          => 1,
            'downloaded_count' => 0,
        ]);

        return (new DocumentResource($document->load('folder')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $ulid): DocumentResource
    {
        $document = Document::with('folder')
            ->where('ulid', $ulid)
            ->firstOrFail();

        return new DocumentResource($document);
    }

    public function update(Request $request, string $ulid): DocumentResource
    {
        $document = Document::where('ulid', $ulid)->firstOrFail();

        $data = $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'folder_id'    => ['nullable', 'integer', 'exists:document_folders,id'],
            'visibility'   => ['sometimes', 'nullable', 'string', 'in:private,team,public'],
            'is_template'  => ['boolean'],
            'template_vars'=> ['nullable', 'array'],
        ]);

        $document->update($data);

        return new DocumentResource($document->refresh()->load('folder'));
    }

    public function destroy(string $ulid): JsonResponse
    {
        $document = Document::where('ulid', $ulid)->firstOrFail();

        Storage::delete($document->path);
        $document->delete();

        return response()->json(null, 204);
    }

    public function download(string $ulid): mixed
    {
        $document = Document::where('ulid', $ulid)->firstOrFail();

        $document->increment('downloaded_count');

        return Storage::download($document->path, $document->name);
    }

    public function versions(string $ulid): JsonResponse
    {
        $document = Document::where('ulid', $ulid)->firstOrFail();

        $versions = DocumentVersion::where('document_id', $document->id)
            ->orderByDesc('version')
            ->get();

        return response()->json($versions);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'name'        => ['required', 'string', 'max:255'],
            'variables'   => ['nullable', 'array'],
        ]);

        $template = Document::findOrFail((int) $data['document_id']);

        abort_if(! $template->is_template, 422, 'The specified document is not a template.');

        $variables = $data['variables'] ?? [];
        $bodyText  = (string) ($template->body_text ?? '');

        foreach ($variables as $key => $value) {
            $bodyText = str_replace("{{{$key}}}", (string) $value, $bodyText);
        }

        $newDocument = Document::create([
            'name'             => $data['name'],
            'description'      => $template->description,
            'folder_id'        => $template->folder_id,
            'visibility'       => $template->visibility,
            'path'             => $template->path,
            'mime_type'        => $template->mime_type,
            'size'             => $template->size,
            'body_text'        => $bodyText,
            'is_template'      => false,
            'template_vars'    => $variables,
            'created_by'       => $this->typedUser($request)->id,
            'version'          => 1,
            'downloaded_count' => 0,
        ]);

        return (new DocumentResource($newDocument))
            ->response()
            ->setStatusCode(201);
    }
}
