<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->ulid,
            'name'            => $this->name,
            'description'     => $this->description,
            'mimeType'        => $this->mime_type,
            'size'            => $this->size,
            'version'         => $this->version,
            'isTemplate'      => (bool) $this->is_template,
            'visibility'      => $this->visibility,
            'downloadedCount' => (int) $this->downloaded_count,
            'folder'          => new DocumentFolderResource($this->whenLoaded('folder')),
            'createdAt'       => $this->created_at?->toIso8601String(),
        ];
    }
}
