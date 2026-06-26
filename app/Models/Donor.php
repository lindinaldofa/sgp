<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donor extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'contact_id', 'name', 'cpf_cnpj', 'amount',
        'donated_at', 'campaign', 'receipt_issued', 'created_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'amount' => 'decimal:2',
        'donated_at' => 'date',
        'receipt_issued' => 'boolean',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
