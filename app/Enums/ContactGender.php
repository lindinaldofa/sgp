<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactGender: string
{
    case Male = 'M';
    case Female = 'F';
    case NonBinary = 'NB';
    case NotInformed = 'NI';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Masculino',
            self::Female => 'Feminino',
            self::NonBinary => 'Não Binário',
            self::NotInformed => 'Não Informado',
        };
    }
}
