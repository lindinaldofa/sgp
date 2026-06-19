<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactType: string
{
    case Voter = 'voter';
    case Supporter = 'supporter';
    case Leader = 'leader';
    case Donor = 'donor';
    case Volunteer = 'volunteer';

    public function label(): string
    {
        return match ($this) {
            self::Voter => 'Eleitor',
            self::Supporter => 'Apoiador',
            self::Leader => 'Liderança',
            self::Donor => 'Doador',
            self::Volunteer => 'Voluntário',
        };
    }
}
