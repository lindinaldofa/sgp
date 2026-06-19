<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function view(User $authUser, User $user): bool
    {
        return $authUser->tenant_id === $user->tenant_id
            && $authUser->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function create(User $authUser): bool
    {
        return $authUser->isAdmin();
    }

    public function update(User $authUser, User $user): bool
    {
        return $authUser->isAdmin() && $authUser->tenant_id === $user->tenant_id;
    }

    public function delete(User $authUser, User $user): bool
    {
        return $authUser->isAdmin()
            && $authUser->tenant_id === $user->tenant_id
            && $authUser->id !== $user->id;
    }
}
