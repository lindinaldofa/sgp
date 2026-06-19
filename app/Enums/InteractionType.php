<?php

declare(strict_types=1);

namespace App\Enums;

enum InteractionType: string
{
    case Note = 'note';
    case Call = 'call';
    case Visit = 'visit';
    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
    case Meeting = 'meeting';

    public function label(): string
    {
        return match ($this) {
            self::Note => 'Anotação',
            self::Call => 'Ligação',
            self::Visit => 'Visita',
            self::Email => 'E-mail',
            self::Sms => 'SMS',
            self::WhatsApp => 'WhatsApp',
            self::Meeting => 'Reunião',
        };
    }
}
