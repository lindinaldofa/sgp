<?php

declare(strict_types=1);

namespace App\Enums;

enum VoteChoice: string
{
    case For = 'for';
    case Against = 'against';
    case Abstained = 'abstained';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::For => 'A Favor',
            self::Against => 'Contra',
            self::Abstained => 'Abstenção',
            self::Absent => 'Ausente',
        };
    }
}
