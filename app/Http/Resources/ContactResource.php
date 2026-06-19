<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->ulid,
            'name'             => $this->name,
            'cpf'              => $this->cpf,
            'birthDate'        => $this->birth_date?->toDateString(),
            'email'            => $this->email,
            'phone'            => $this->phone,
            'whatsapp'         => $this->whatsapp,
            'instagram'        => $this->instagram,
            'tiktok'           => $this->tiktok,
            'facebook'         => $this->facebook,
            'twitter'          => $this->twitter,
            'photoPath'        => $this->photo_path,
            'gender'           => $this->gender?->value,
            'type'             => $this->type?->value,
            'engagementScore'  => $this->engagement_score,
            'notes'            => $this->notes,
            'profession'       => $this->profession,
            'zipCode'          => $this->zip_code,
            'address'          => $this->address,
            'addressNumber'    => $this->address_number,
            'neighborhood'     => $this->neighborhood,
            'city'             => $this->city,
            'state'            => $this->state,
            'latitude'         => $this->latitude ? (float) $this->latitude : null,
            'longitude'        => $this->longitude ? (float) $this->longitude : null,
            'electoralZone'    => $this->electoral_zone,
            'electoralSection' => $this->electoral_section,
            'leaderId'         => $this->leader_id,
            'lgpdConsent'      => (bool) $this->lgpd_consent,
            'lgpdConsentAt'    => $this->lgpd_consent_at?->toIso8601String(),
            'tags'             => TagResource::collection($this->whenLoaded('tags')),
            'createdAt'        => $this->created_at?->toIso8601String(),
            'updatedAt'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
