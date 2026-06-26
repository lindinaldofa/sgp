<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\EventType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'ulid', 'tenant_id', 'created_by', 'title', 'description', 'type',
        'location', 'latitude', 'longitude', 'is_online', 'online_url',
        'starts_at', 'ends_at', 'all_day', 'is_recurrent', 'recurrence_rule',
        'territory_id', 'contact_id', 'attendance', 'absence_reason',
        'reminder_at', 'reminder_sent',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'type' => EventType::class,
        'attendance' => AttendanceStatus::class,
        'is_online' => 'boolean',
        'all_day' => 'boolean',
        'is_recurrent' => 'boolean',
        'reminder_sent' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'reminder_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected static function booted(): void
    {
        static::creating(function (Event $model) {
            if (empty($model->ulid)) {
                $model->ulid = Str::ulid()->toBase32();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Territory, $this> */
    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return HasMany<EventAttendee, $this> */
    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }
}
