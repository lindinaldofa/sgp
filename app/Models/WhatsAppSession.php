<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WhatsAppSession extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'tenant_id',
        'ulid',
        'name',
        'session_id',
        'is_active',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WhatsAppSession $session): void {
            if (empty($session->ulid)) {
                $session->ulid = Str::ulid()->toBase32();
            }
        });
    }

    /** @return HasMany<Campaign, $this> */
    public function campaigns(): HasMany
    {
        // Explicit FK: Eloquent's default inference snake_cases "WhatsAppSession"
        // to "whats_app_session_id" (splits at the internal capital), not the
        // actual "whatsapp_session_id" column.
        return $this->hasMany(Campaign::class, 'whatsapp_session_id');
    }
}
