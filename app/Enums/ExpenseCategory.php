<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpenseCategory: string
{
    case Staff = 'staff';
    case Office = 'office';
    case Travel = 'travel';
    case Communication = 'communication';
    case Consulting = 'consulting';
    case Events = 'events';
    case Material = 'material';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Pessoal',
            self::Office => 'Escritório',
            self::Travel => 'Viagens',
            self::Communication => 'Comunicação',
            self::Consulting => 'Consultoria',
            self::Events => 'Eventos',
            self::Material => 'Material',
            self::Other => 'Outros',
        };
    }
}
