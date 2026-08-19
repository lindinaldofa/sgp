<?php

declare(strict_types=1);

namespace App\Enums;

enum WhatsAppMessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texto',
            self::Image => 'Imagem',
            self::Video => 'Vídeo',
            self::Audio => 'Áudio',
            self::Document => 'Documento',
        };
    }

    public function requiresMedia(): bool
    {
        return $this !== self::Text;
    }
}
