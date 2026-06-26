<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PropositionStatus;
use App\Enums\PropositionType;
use App\Enums\VoteChoice;
use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Proposition extends Model
{
    use BelongsToTenant, HasAuditLog, SoftDeletes;

    protected $fillable = [
        'ulid', 'tenant_id', 'user_id', 'type', 'number', 'year', 'title',
        'summary', 'full_text', 'status', 'theme', 'territory_id', 'contact_id',
        'votes_for', 'votes_against', 'votes_abstained', 'our_vote',
        'vote_justification', 'filed_at', 'voted_at', 'published_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'type' => PropositionType::class,
        'status' => PropositionStatus::class,
        'our_vote' => VoteChoice::class,
        'filed_at' => 'date',
        'voted_at' => 'date',
        'published_at' => 'datetime',
        'votes_for' => 'integer',
        'votes_against' => 'integer',
        'votes_abstained' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Proposition $model) {
            if (empty($model->ulid)) {
                $model->ulid = Str::ulid()->toBase32();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /** @return HasMany<PropositionAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(PropositionAttachment::class);
    }

    /** @return HasMany<PropositionStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(PropositionStatusHistory::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
