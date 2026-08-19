<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'isActive' => $this->is_active,
            'isLandingPage' => $this->is_landing_page,
            'landingTitle' => $this->landing_title,
            'landingLogoPath' => $this->landing_logo_path,
            'successMessage' => $this->success_message,
            'redirectUrl' => $this->redirect_url,
            'tagId' => $this->tag_id,
            'tag' => new TagResource($this->whenLoaded('tag')),
            'submissionsCount' => $this->whenCounted('submissions'),
            'fields' => $this->when(
                $this->relationLoaded('fields'),
                fn () => $this->fields->map(fn ($f) => [
                    'id' => $f->id,
                    'type' => $f->type->value,
                    'label' => $f->label,
                    'name' => $f->name,
                    'placeholder' => $f->placeholder,
                    'helpText' => $f->help_text,
                    'defaultValue' => $f->default_value,
                    'isRequired' => $f->is_required,
                    'mapsTo' => $f->maps_to,
                    'options' => $f->options ?? [],
                    'sortOrder' => $f->sort_order,
                ]),
            ),
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
