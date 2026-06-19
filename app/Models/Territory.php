<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Territory extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'color',
        'geojson',
        'leader_id',
        'assigned_user_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'geojson' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'leader_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
