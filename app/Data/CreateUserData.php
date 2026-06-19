<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\UserRole;

final readonly class CreateUserData
{
    public function __construct(
        public int $tenantId,
        public string $name,
        public string $email,
        public UserRole $role,
        public string $password = '',
    ) {}
}
