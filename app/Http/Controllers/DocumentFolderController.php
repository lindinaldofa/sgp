<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\DocumentFolderResource;
use App\Models\Document;
use App\Models\DocumentFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentFolderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $folders = DocumentFolder::with('children.children')
            ->where('tenant_id', $this->typedUser($request)->tenant_id)
            ->whereNull('parent_id')
            ->get();

        return DocumentFolderResource::collection($folders);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:document_folders,id'],
            'visibility' => ['nullable', 'string', 'in:private,team,public'],
        ]);

        $data['created_by'] = $this->typedUser($request)->id;
        $data['tenant_id'] = $this->typedUser($request)->tenant_id;

        $folder = DocumentFolder::create($data);

        return (new DocumentFolderResource($folder))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(int $id): JsonResponse
    {
        $folder = DocumentFolder::findOrFail($id);

        abort_if(
            Document::where('folder_id', $id)->exists(),
            422,
            'Cannot delete a folder that contains documents.',
        );

        $folder->delete();

        return response()->json(null, 204);
    }
}
