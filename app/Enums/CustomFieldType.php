<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case Textarea = 'textarea';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texto',
            self::Number => 'Número',
            self::Date => 'Data',
            self::Select => 'Lista suspensa',
            self::Checkbox => 'Caixa de seleção',
            self::Textarea => 'Texto longo',
        };
    }

    public function hasOptions(): bool
    {
        return $this === self::Select;
    }
}
