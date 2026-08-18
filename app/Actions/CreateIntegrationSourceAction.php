<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\CreateIntegrationSourceData;
use App\Models\IntegrationSource;
use App\Models\Tag;
use Illuminate\Support\Str;

final class CreateIntegrationSourceAction
{
    /** @return array{source: IntegrationSource, plainToken: string} */
    public function execute(CreateIntegrationSourceData $data): array
    {
        $tagId = $data->tagId ?? Tag::firstOrCreate(
            ['tenant_id' => $data->tenantId, 'name' => "origem: {$data->name}"],
        )->id;

        $plainToken = Str::random(40);

        $source = IntegrationSource::create([
            'tenant_id' => $data->tenantId,
            'name' => $data->name,
            'token' => IntegrationSource::hashToken($plainToken),
            'tag_id' => $tagId,
            'is_active' => true,
        ]);

        return ['source' => $source, 'plainToken' => $plainToken];
    }
}
