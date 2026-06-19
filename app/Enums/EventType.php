<?php

declare(strict_types=1);

namespace App\Enums;

enum EventType: string
{
    case Session = 'session';
    case Meeting = 'meeting';
    case Visit = 'visit';
    case PublicEvent = 'public_event';
    case Hearing = 'hearing';
    case Travel = 'travel';
    case Personal = 'personal';

    public function label(): string
    {
        return match ($this) {
            self::Session => 'Sessão',
            self::Meeting => 'Reunião',
            self::Visit => 'Visita',
            self::PublicEvent => 'Evento Público',
            self::Hearing => 'Audiência Pública',
            self::Travel => 'Viagem',
            self::Personal => 'Pessoal',
        };
    }
}
