<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create([
            'ulid' => Str::ulid()->toBase32(),
            'name' => 'SGP',
            'slug' => 'sgp',
            'plan' => 'advanced',
            'is_active' => true,
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Administrador',
            'email' => 'admin@sgp.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Assessor Demo',
            'email' => 'assessor@sgp.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Assessor,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
