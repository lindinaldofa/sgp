<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropositionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->ulid,
            'type'            => $this->type?->value,
            'number'          => $this->number,
            'year'            => $this->year,
            'title'           => $this->title,
            'summary'         => $this->summary,
            'status'          => $this->status?->value,
            'theme'           => $this->theme,
            'ourVote'         => $this->our_vote?->value,
            'voteJustification' => $this->vote_justification,
            'votesFor'        => (int) ($this->votes_for ?? 0),
            'votesAgainst'    => (int) ($this->votes_against ?? 0),
            'votesAbstained'  => (int) ($this->votes_abstained ?? 0),
            'filedAt'         => $this->filed_at?->toDateString(),
            'votedAt'         => $this->voted_at?->toDateString(),
            'publishedAt'     => $this->published_at?->toIso8601String(),
            'createdAt'       => $this->created_at?->toIso8601String(),
            'updatedAt'       => $this->updated_at?->toIso8601String(),
            'territory'       => $this->whenLoaded('territory', fn () => ['name' => $this->territory?->name]),
            'contact'         => $this->whenLoaded('contact', fn () => [
                'id'   => $this->contact?->ulid,
                'name' => $this->contact?->name,
            ]),
        ];
    }
}
