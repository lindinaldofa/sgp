<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FormSubmission extends Model
{
    protected $fillable = [
        'ulid', 'form_id', 'tenant_id', 'contact_id',
        'ip_address', 'user_agent', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FormSubmission $submission) {
            if (empty($submission->ulid)) {
                $submission->ulid = Str::ulid()->toString();
            }
        });
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return HasMany<FormSubmissionValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(FormSubmissionValue::class, 'submission_id');
    }
}
