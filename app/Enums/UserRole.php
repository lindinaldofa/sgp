<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Assessor = 'assessor';
    case Financial = 'financial';
    case Leader = 'leader';
    case Readonly = 'readonly';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Manager => 'Gestor',
            self::Assessor => 'Assessor',
            self::Financial => 'Financeiro',
            self::Leader => 'Liderança',
            self::Readonly => 'Somente Leitura',
        };
    }

    public function canManageFinancial(): bool
    {
        return in_array($this, [self::Admin, self::Financial]);
    }

    public function canManageUsers(): bool
    {
        return in_array($this, [self::Admin, self::Manager]);
    }

    public function canAccessCrm(): bool
    {
        return in_array($this, [self::Admin, self::Manager, self::Assessor]);
    }
}
