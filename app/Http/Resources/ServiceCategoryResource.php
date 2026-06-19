<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'icon'     => $this->icon,
            'color'    => $this->color,
            'parentId' => $this->parent_id,
            'children' => ServiceCategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
