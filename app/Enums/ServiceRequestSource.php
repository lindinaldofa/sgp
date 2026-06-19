<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceRequestSource: string
{
    case Manual = 'manual';
    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case Phone = 'phone';
    case Portal = 'portal';
    case InPerson = 'in_person';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::WhatsApp => 'WhatsApp',
            self::Email => 'E-mail',
            self::Phone => 'Telefone',
            self::Portal => 'Portal',
            self::InPerson => 'Presencial',
        };
    }
}
