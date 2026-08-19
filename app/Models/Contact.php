<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactGender;
use App\Enums\ContactType;
use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Contact extends Model
{
    use BelongsToTenant, HasAuditLog, SoftDeletes;

    protected $fillable = [
        'ulid',
        'tenant_id',
        'name',
        'cpf',
        'birth_date',
        'email',
        'phone',
        'whatsapp',
        'instagram',
        'tiktok',
        'facebook',
        'twitter',
        'photo_path',
        'gender',
        'type',
        'engagement_score',
        'notes',
        'profession',
        'zip_code',
        'address',
        'address_number',
        'neighborhood',
        'city',
        'state',
        'latitude',
        'longitude',
        'electoral_zone',
        'electoral_section',
        'leader_id',
        'mautic_id',
        'lgpd_consent',
        'lgpd_consent_at',
        'unsubscribed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'gender' => ContactGender::class,
            'type' => ContactType::class,
            'engagement_score' => 'integer',
            'lgpd_consent' => 'boolean',
            'lgpd_consent_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'birth_date' => 'date',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Contact $contact): void {
            if (empty($contact->ulid)) {
                $contact->ulid = Str::ulid()->toBase32();
            }
            // Consent is presumed unless a caller explicitly says otherwise (e.g. an
            // unchecked LGPD field on a form). Set here — not just as a DB column
            // default — so the in-memory model reflects it immediately after create(),
            // without requiring a fresh()/refresh() round-trip.
            if (is_null($contact->lgpd_consent)) {
                $contact->lgpd_consent = true;
            }
        });
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'contact_tags');
    }

    /** @return BelongsTo<Contact, $this> */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'leader_id');
    }

    /** @return HasMany<Contact, $this> */
    public function followers(): HasMany
    {
        return $this->hasMany(Contact::class, 'leader_id');
    }

    /** @return HasMany<ContactInteractionLog, $this> */
    public function interactionLogs(): HasMany
    {
        return $this->hasMany(ContactInteractionLog::class);
    }

    /** @return HasMany<ContactImport, $this> */
    public function imports(): HasMany
    {
        return $this->hasMany(ContactImport::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
