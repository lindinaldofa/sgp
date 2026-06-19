<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestUpdate extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'service_request_id',
        'user_id',
        'old_status',
        'new_status',
        'note',
        'notify_contact',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'notify_contact' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
