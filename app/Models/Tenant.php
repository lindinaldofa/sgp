<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    protected $fillable = [
        'ulid', 'name', 'slug', 'logo_path', 'plan', 'is_active',
        'settings', 'storage_used', 'storage_limit',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'storage_used' => 'integer',
        'storage_limit' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (empty($tenant->ulid)) {
                $tenant->ulid = Str::ulid()->toBase32();
            }
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function storageUsedPercentage(): float
    {
        if ($this->storage_limit === 0) {
            return 0.0;
        }

        return round(($this->storage_used / $this->storage_limit) * 100, 2);
    }
}
