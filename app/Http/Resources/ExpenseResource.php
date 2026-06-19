<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->ulid,
            'category'      => $this->category?->value,
            'categoryLabel' => $this->category?->label(),
            'description'   => $this->description,
            'supplier'      => $this->supplier,
            'supplierCnpj'  => $this->supplier_cnpj,
            'amount'        => (float) $this->amount,
            'expenseDate'   => $this->expense_date?->toDateString(),
            'status'        => $this->status?->value,
            'statusLabel'   => $this->status?->label(),
            'notes'     => $this->notes,
            'documents' => ExpenseDocumentResource::collection($this->whenLoaded('documents')),
            'approvedBy'    => new UserResource($this->whenLoaded('approvedBy')),
            'approvedAt'    => $this->approved_at?->toIso8601String(),
            'createdAt'     => $this->created_at?->toIso8601String(),
            'updatedAt'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
