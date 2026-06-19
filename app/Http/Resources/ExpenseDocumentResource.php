<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExpenseDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExpenseDocument */
class ExpenseDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ExpenseDocument $doc */
        $doc = $this->resource;

        return [
            'id'           => $doc->ulid,
            'documentType' => $doc->document_type,
            'typeLabel'    => $doc->typeLabel(),
            'originalName' => $doc->original_name,
            'createdAt'    => $doc->created_at?->toIso8601String(),
        ];
    }
}
