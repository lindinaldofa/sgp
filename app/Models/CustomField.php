<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomFieldType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomField extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'key',
        'label',
        'type',
        'options',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'type' => CustomFieldType::class,
            'options' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomField $field): void {
            if (empty($field->key)) {
                $field->key = self::uniqueKeyFor((int) $field->tenant_id, Str::slug($field->label, '_'));
            }
        });
    }

    private static function uniqueKeyFor(int $tenantId, string $base): string
    {
        $key = $base ?: 'campo';
        $suffix = 1;

        while (static::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('key', $key)->exists()) {
            $suffix++;
            $key = "{$base}_{$suffix}";
        }

        return $key;
    }
}
