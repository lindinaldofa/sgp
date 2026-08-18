<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\IntegrationSource;
use Illuminate\Support\Str;

final class RegenerateIntegrationSourceTokenAction
{
    /** @return array{source: IntegrationSource, plainToken: string} */
    public function execute(IntegrationSource $source): array
    {
        $plainToken = Str::random(40);

        $source->update(['token' => IntegrationSource::hashToken($plainToken)]);

        return ['source' => $source, 'plainToken' => $plainToken];
    }
}
