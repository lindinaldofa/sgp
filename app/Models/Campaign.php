<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignChannel;
use App\Enums\CampaignStatus;
use App\Enums\WhatsAppMessageType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Campaign extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'ulid',
        'tenant_id',
        'user_id',
        'name',
        'channel',
        'status',
        'subject',
        'body_html',
        'body_text',
        'whatsapp_message_type',
        'media_path',
        'segment_filters',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'opened_count',
        'clicked_count',
        'failed_count',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'channel' => CampaignChannel::class,
            'status' => CampaignStatus::class,
            'whatsapp_message_type' => WhatsAppMessageType::class,
            'segment_filters' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign): void {
            if (empty($campaign->ulid)) {
                $campaign->ulid = Str::ulid()->toBase32();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CampaignRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
