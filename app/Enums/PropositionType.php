<?php

declare(strict_types=1);

namespace App\Enums;

enum PropositionType: string
{
    case Bill = 'bill';
    case Amendment = 'amendment';
    case Motion = 'motion';
    case Requirement = 'requirement';
    case Indication = 'indication';
    case Decree = 'decree';

    public function label(): string
    {
        return match ($this) {
            self::Bill => 'Projeto de Lei',
            self::Amendment => 'Emenda',
            self::Motion => 'Moção',
            self::Requirement => 'Requerimento',
            self::Indication => 'Indicação',
            self::Decree => 'Decreto',
        };
    }
}
