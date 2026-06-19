<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExpenseDocument extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'ulid', 'expense_id', 'tenant_id', 'document_type', 'original_name', 'path',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ExpenseDocument $model) {
            if (empty($model->ulid)) {
                $model->ulid = Str::ulid()->toBase32();
            }
        });
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function typeLabel(): string
    {
        return match ($this->document_type) {
            'receipt' => 'Comprovante',
            'invoice' => 'Fatura',
            'nf'      => 'Nota Fiscal',
            'bill'    => 'Recibo',
            default   => 'Outro',
        };
    }
}
