<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropositionAttachment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'proposition_id', 'filename', 'path', 'size', 'type', 'created_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Proposition, $this> */
    public function proposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class);
    }
}
