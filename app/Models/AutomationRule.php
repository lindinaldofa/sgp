<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignChannel;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'trigger',
        'tag_id',
        'channel',
        'template_id',
        'is_active',
        'send_time',
        'days_before',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'tag_id' => 'integer',
            'channel' => CampaignChannel::class,
            'is_active' => 'boolean',
            'days_before' => 'integer',
        ];
    }

    /** @return BelongsTo<MessageTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    /** @return BelongsTo<Tag, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }
}
