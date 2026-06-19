<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Form extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ulid', 'tenant_id', 'name', 'slug', 'description',
        'is_active', 'is_landing_page', 'landing_title', 'landing_logo_path',
        'success_message', 'redirect_url',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id'       => 'integer',
            'is_active'       => 'boolean',
            'is_landing_page' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Form $form) {
            if (empty($form->ulid)) {
                $form->ulid = Str::ulid()->toString();
            }
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->name) . '-' . Str::random(6);
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<FormField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    /** @return HasMany<FormSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }
}
