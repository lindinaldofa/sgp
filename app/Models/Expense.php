<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Traits\BelongsToTenant;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Expense extends Model
{
    use BelongsToTenant, HasAuditLog;

    protected $fillable = [
        'ulid', 'tenant_id', 'user_id', 'category', 'description', 'supplier',
        'supplier_cnpj', 'amount', 'expense_date', 'receipt_path', 'status',
        'approved_by', 'approved_at', 'notes',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'category' => ExpenseCategory::class,
        'status' => ExpenseStatus::class,
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Expense $model) {
            if (empty($model->ulid)) {
                $model->ulid = Str::ulid()->toBase32();
            }
        });
    }

    /** @return HasMany<ExpenseDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ExpenseDocument::class)->latest('created_at');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
