<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AssessorRoute extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'ulid',
        'tenant_id',
        'user_id',
        'name',
        'status',
        'route_geojson',
        'planned_date',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'route_geojson' => 'array',
            'planned_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AssessorRoute $route): void {
            if (empty($route->ulid)) {
                $route->ulid = Str::ulid()->toBase32();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AssessorRouteStop, $this> */
    public function stops(): HasMany
    {
        return $this->hasMany(AssessorRouteStop::class, 'route_id');
    }
}
