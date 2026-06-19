<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropositionStatusHistory extends Model
{
    protected $table = 'proposition_status_history';

    public $timestamps = false;

    protected $fillable = [
        'proposition_id', 'user_id', 'old_status', 'new_status', 'note', 'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /** @return BelongsTo<Proposition, $this> */
    public function proposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
