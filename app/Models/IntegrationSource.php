<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class IntegrationSource extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'ulid',
        'tenant_id',
        'name',
        'token',
        'tag_id',
        'is_active',
        'last_used_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'tag_id' => 'integer',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (IntegrationSource $source): void {
            if (empty($source->ulid)) {
                $source->ulid = Str::ulid()->toBase32();
            }
        });
    }

    /** @return BelongsTo<Tag, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public static function findActiveByPlainToken(string $plainToken): ?self
    {
        return static::withoutGlobalScopes()
            ->where('token', self::hashToken($plainToken))
            ->where('is_active', true)
            ->first();
    }
}
