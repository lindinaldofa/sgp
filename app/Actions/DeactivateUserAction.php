<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final class DeactivateUserAction
{
    public function execute(User $user): void
    {
        $user->update(['is_active' => false]);
        $user->tokens()->delete();
    }
}
