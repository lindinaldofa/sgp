<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignChannel;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'channel',
        'subject',
        'body_html',
        'body_text',
        'variables',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'channel' => CampaignChannel::class,
            'variables' => 'array',
            'is_system' => 'boolean',
        ];
    }
}
