<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ContactImport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'filename',
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
            'error_log' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'error_rows' => 'integer',
        ];
    }
}
