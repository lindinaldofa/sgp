<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecipientStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class CampaignRecipient extends Model
{
    protected $fillable = [
        'campaign_id',
        'contact_id',
        'status',
        'external_id',
        'error_message',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecipientStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Public, permanently-signed unsubscribe link for this recipient. Points at the
     * SPA route (not the API route directly) so the click lands on a page instead of
     * raw JSON; the frontend forwards the signature/expires query params to the API.
     */
    public function unsubscribeUrl(): string
    {
        $signed = URL::signedRoute('unsubscribe.handle', ['recipient' => $this->id]);
        $query = parse_url($signed, PHP_URL_QUERY);

        return rtrim(config('app.url'), '/')."/unsubscribe/{$this->id}?{$query}";
    }
}
