<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpenseStatus: string
{
    case Contracted = 'contracted';
    case Paid       = 'paid';
    case Overdue    = 'overdue';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Contracted => 'Contratado',
            self::Paid       => 'Pago',
            self::Overdue    => 'Pagamento atrasado',
            self::Cancelled  => 'Cancelado',
        };
    }

    /** @return ExpenseStatus[] */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Contracted => [self::Paid, self::Overdue, self::Cancelled],
            self::Overdue    => [self::Paid, self::Cancelled],
            self::Cancelled  => [self::Contracted],
            self::Paid       => [self::Cancelled],
        };
    }
}
