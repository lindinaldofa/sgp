<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\SendNewContactAutomationJob;
use App\Models\Contact;
use App\Models\CustomField;
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
            $customData = $this->filterCustomFields((int) $source->tenant_id, $data['custom_fields'] ?? []);
            unset($data['custom_fields']);

            $contact = $this->findOrCreateContact->execute(
                tenantId: (int) $source->tenant_id,
                nativeData: $data,
                customData: $customData,
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

    /**
     * Only tenant-defined, currently active custom field keys are allowed
     * through — an external integration is untrusted input, so an unknown
     * key (typo, stale integration after a field was renamed/deleted, or a
     * deliberate attempt to write arbitrary JSON) is silently dropped rather
     * than polluting custom_data.
     *
     * @param array<string, mixed> $customFields
     * @return array<string, mixed>
     */
    private function filterCustomFields(int $tenantId, mixed $customFields): array
    {
        if (! is_array($customFields) || $customFields === []) {
            return [];
        }

        $allowedKeys = CustomField::where('tenant_id', $tenantId)->where('is_active', true)->pluck('key');

        return array_intersect_key($customFields, array_flip($allowedKeys->all()));
    }
}
