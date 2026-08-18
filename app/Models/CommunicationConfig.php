<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CommunicationConfig extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $hidden = [
        'openwa_api_key',
    ];

    protected function casts(): array
    {
        return [
            'daily_email_limit' => 'integer',
            'daily_sms_limit' => 'integer',
            'daily_whatsapp_limit' => 'integer',
        ];
    }
}
