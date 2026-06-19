<?php

declare(strict_types=1);

namespace App\Enums;

enum RecipientStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case Failed = 'failed';
    case Unsubscribed = 'unsubscribed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Sent => 'Enviado',
            self::Delivered => 'Entregue',
            self::Opened => 'Aberto',
            self::Clicked => 'Clicado',
            self::Failed => 'Falhou',
            self::Unsubscribed => 'Descadastrado',
        };
    }
}
