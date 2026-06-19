<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentFolderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'visibility' => $this->visibility,
            'children' => DocumentFolderResource::collection($this->whenLoaded('children')),
            'documents_count' => $this->whenCounted('documents'),
        ];
    }
}
