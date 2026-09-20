<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactImport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'tag_id',
        'filename',
        'headers',
        'delimiter',
        'column_mapping',
        'status',
        'total_rows',
        'processed_rows',
        'error_rows',
        'error_log',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'tag_id' => 'integer',
            'headers' => 'array',
            'column_mapping' => 'array',
            'error_log' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'error_rows' => 'integer',
        ];
    }

    /** @return BelongsTo<Tag, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }
}
