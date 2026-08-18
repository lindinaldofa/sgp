<?php

declare(strict_types=1);

namespace App\Data;

final readonly class CreateIntegrationSourceData
{
    public function __construct(
        public int $tenantId,
        public string $name,
        public ?int $tagId = null,
    ) {}
}
