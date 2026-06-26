<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Document extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'ulid', 'tenant_id', 'folder_id', 'created_by', 'name', 'description',
        'path', 'size', 'mime_type', 'version', 'is_template', 'template_vars',
        'visibility', 'downloaded_count',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'size' => 'integer',
        'version' => 'integer',
        'is_template' => 'boolean',
        'template_vars' => 'array',
        'downloaded_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Document $model) {
            if (empty($model->ulid)) {
                $model->ulid = Str::ulid()->toBase32();
            }
        });
    }

    /** @return BelongsTo<DocumentFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }
}
