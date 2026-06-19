<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Tenant $resource */
class TenantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'plan' => $this->plan,
            'is_active' => $this->is_active,
            'settings' => $this->settings,
            'storage_used' => $this->storage_used,
            'storage_limit' => $this->storage_limit,
            'storage_used_percentage' => $this->resource->storageUsedPercentage(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
