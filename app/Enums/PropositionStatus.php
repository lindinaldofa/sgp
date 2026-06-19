<?php

declare(strict_types=1);

namespace App\Enums;

enum PropositionStatus: string
{
    case Draft = 'draft';
    case Filed = 'filed';
    case InCommittee = 'in_committee';
    case Voted = 'voted';
    case Approved = 'approved';
    case Vetoed = 'vetoed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Filed => 'Protocolado',
            self::InCommittee => 'Em Comissão',
            self::Voted => 'Votado',
            self::Approved => 'Aprovado',
            self::Vetoed => 'Vetado',
            self::Archived => 'Arquivado',
        };
    }
}
