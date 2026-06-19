<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestSource;
use App\Enums\ServiceRequestStatus;
use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ServiceRequest extends Model
{
    use BelongsToTenant, HasAuditLog, SoftDeletes;

    protected $fillable = [
        'ulid',
        'tenant_id',
        'contact_id',
        'assigned_to',
        'created_by',
        'category_id',
        'subject',
        'description',
        'status',
        'priority',
        'source',
        'resolved_at',
        'due_date',
        'internal_notes',
        'mautic_ticket_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ServiceRequestStatus::class,
            'priority' => ServiceRequestPriority::class,
            'source' => ServiceRequestSource::class,
            'resolved_at' => 'datetime',
            'due_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ServiceRequest $serviceRequest): void {
            if (empty($serviceRequest->ulid)) {
                $serviceRequest->ulid = Str::ulid()->toBase32();
            }
        });
    }

    public function isOverdue(): bool
    {
        if ($this->due_date === null) {
            return false;
        }

        $resolvedStatuses = [ServiceRequestStatus::Resolved, ServiceRequestStatus::Archived];

        return $this->due_date->isPast()
            && ! in_array($this->status, $resolvedStatuses, true);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<ServiceCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    /** @return HasMany<ServiceRequestUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(ServiceRequestUpdate::class);
    }

    /** @return HasMany<ServiceRequestAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ServiceRequestAttachment::class);
    }
}
