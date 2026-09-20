<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Segment extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'ulid',
        'name',
        'description',
        'filters',
        'is_active',
        'last_recalculated_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'filters' => 'array',
            'is_active' => 'boolean',
            'last_recalculated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Segment $segment): void {
            if (empty($segment->ulid)) {
                $segment->ulid = Str::ulid()->toBase32();
            }
        });
    }

    /** @return BelongsToMany<Contact, $this> */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'segment_contact')
            ->withPivot('source')
            ->withTimestamps();
    }
}
