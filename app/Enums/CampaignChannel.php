<?php

declare(strict_types=1);

namespace App\Enums;

enum CampaignChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Sms => 'SMS',
            self::WhatsApp => 'WhatsApp',
        };
    }
}
