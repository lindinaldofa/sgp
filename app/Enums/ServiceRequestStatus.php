<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceRequestStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Resolved = 'resolved';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Novo',
            self::InProgress => 'Em Andamento',
            self::Waiting => 'Aguardando',
            self::Resolved => 'Resolvido',
            self::Archived => 'Arquivado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => '#3B82F6',
            self::InProgress => '#F59E0B',
            self::Waiting => '#8B5CF6',
            self::Resolved => '#10B981',
            self::Archived => '#6B7280',
        };
    }
}
