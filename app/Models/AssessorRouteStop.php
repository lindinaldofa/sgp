<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessorRouteStop extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'route_id',
        'contact_id',
        'order',
        'visited',
        'visited_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'visited' => 'boolean',
            'visited_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AssessorRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(AssessorRoute::class, 'route_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
