<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'role_label' => $this->role->label(),
            'is_active' => $this->is_active,
            'avatar_path' => $this->avatar_path,
            'leader_slug' => $this->leader_slug,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at'    => $this->created_at->toIso8601String(),
            'tenant'        => $this->when(
                $this->relationLoaded('tenant') && $this->tenant,
                fn () => [
                    'id'        => $this->tenant->id,
                    'name'      => $this->tenant->name,
                    'slug'      => $this->tenant->slug,
                    'plan'      => $this->tenant->plan,
                    'isActive'  => $this->tenant->is_active,
                    'logoPath'  => $this->tenant->logo_path
                        ? Storage::url($this->tenant->logo_path)
                        : null,
                ],
            ),
        ];
    }
}
