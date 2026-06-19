<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\CreateUserData;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CreateUserAction
{
    public function execute(CreateUserData $data): User
    {
        return User::create([
            'tenant_id' => $data->tenantId,
            'name' => $data->name,
            'email' => $data->email,
            'role' => $data->role,
            'password' => Hash::make($data->password ?: Str::random(16)),
        ]);
    }
}
