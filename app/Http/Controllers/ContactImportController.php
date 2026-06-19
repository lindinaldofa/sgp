<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessContactImportJob;
use App\Models\ContactImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactImportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $file = $request->file('file');
        $filename = $file->store('imports', 'local');

        $import = ContactImport::create([
            'tenant_id' => $this->typedUser($request)->tenant_id,
            'user_id' => auth()->id(),
            'filename' => $filename,
            'status' => 'pending',
        ]);

        ProcessContactImportJob::dispatch($import);

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'filename' => $import->filename,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $import = ContactImport::findOrFail($id);

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'filename' => $import->filename,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'error_rows' => $import->error_rows,
            'error_log' => $import->error_log,
            'started_at' => $import->started_at?->toIso8601String(),
            'completed_at' => $import->completed_at?->toIso8601String(),
            'created_at' => $import->created_at?->toIso8601String(),
        ]);
    }
}
