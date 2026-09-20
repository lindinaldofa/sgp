<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessContactImportJob;
use App\Models\ContactImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContactImportController extends Controller
{
    /** Step 1: upload the CSV and return its header row + a small preview. */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $path = $request->file('file')->store('imports', 'local');
        $fullPath = Storage::disk('local')->path($path);

        $handle = fopen($fullPath, 'r');
        if ($handle === false) {
            return response()->json(['message' => 'Não foi possível ler o arquivo enviado.'], 422);
        }

        $headers = fgetcsv($handle, 0, ',') ?: [];
        $headers = array_map(fn ($h) => trim((string) $h), $headers);

        $preview = [];
        $totalRows = 0;
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if ($row === [null]) {
                continue;
            }
            $totalRows++;
            if (count($preview) < 5) {
                $preview[] = $row;
            }
        }
        fclose($handle);

        if (empty($headers)) {
            Storage::disk('local')->delete($path);

            return response()->json(['message' => 'O arquivo está vazio ou não é um CSV válido.'], 422);
        }

        $import = ContactImport::create([
            'tenant_id' => $this->typedUser($request)->tenant_id,
            'user_id' => auth()->id(),
            'filename' => $path,
            'headers' => $headers,
            'status' => 'mapping',
            'total_rows' => $totalRows,
        ]);

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'headers' => $headers,
            'previewRows' => $preview,
            'totalRows' => $totalRows,
        ], 201);
    }

    /** Step 2: receive the column → field mapping and the tag, then process. */
    public function map(Request $request, int $id): JsonResponse
    {
        $import = ContactImport::findOrFail($id);

        abort_if($import->status !== 'mapping', 422, 'This import has already been mapped.');

        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string'],
            'tag_id' => ['nullable', 'integer', 'exists:tags,id'],
        ]);

        $mapping = array_filter($data['mapping'], fn ($target) => filled($target));

        if (empty($mapping)) {
            return response()->json(['message' => 'Mapeie pelo menos uma coluna para um campo.'], 422);
        }

        $import->update([
            'column_mapping' => $mapping,
            'tag_id' => $data['tag_id'] ?? null,
            'status' => 'pending',
        ]);

        ProcessContactImportJob::dispatch($import);

        return response()->json(['id' => $import->id, 'status' => $import->status]);
    }

    /** Step 3: poll progress. */
    public function show(int $id): JsonResponse
    {
        $import = ContactImport::with('tag')->findOrFail($id);

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'filename' => $import->filename,
            'headers' => $import->headers,
            'columnMapping' => $import->column_mapping,
            'tag' => $import->tag ? ['id' => $import->tag->id, 'name' => $import->tag->name] : null,
            'totalRows' => $import->total_rows,
            'processedRows' => $import->processed_rows,
            'errorRows' => $import->error_rows,
            'errorLog' => $import->error_log,
            'startedAt' => $import->started_at?->toIso8601String(),
            'completedAt' => $import->completed_at?->toIso8601String(),
            'createdAt' => $import->created_at?->toIso8601String(),
        ]);
    }
}
