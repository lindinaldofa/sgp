<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\SendNewContactAutomationJob;
use App\Models\Contact;
use App\Models\IntegrationSource;
use Illuminate\Support\Facades\DB;

final class CreateContactFromIntegrationAction
{
    public function __construct(
        private readonly FindOrCreateContactAction $findOrCreateContact,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(IntegrationSource $source, array $data): Contact
    {
        return DB::transaction(function () use ($source, $data) {
            $contact = $this->findOrCreateContact->execute(
                tenantId: (int) $source->tenant_id,
                nativeData: $data,
                tagIds: [$source->tag_id],
            );

            // Upstream validation (IntegrationContactController) already requires
            // name or email, so FindOrCreateContactAction never returns null here.
            if (! $contact) {
                throw new \RuntimeException('Contact creation unexpectedly returned null.');
            }

            $source->update(['last_used_at' => now()]);

            // Campaign-backed rules react to both creation and update (they dedupe
            // via CampaignRecipient); legacy template-backed rules only fire on
            // creation — see SendNewContactAutomationJob for details.
            SendNewContactAutomationJob::dispatch($contact->id, $source->tag_id, $contact->wasRecentlyCreated);

            return $contact;
        });
    }
}
