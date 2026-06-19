<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('allows a user to login with valid credentials', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user'])
        ->assertJsonPath('user.email', 'test@example.com');
});

it('rejects invalid credentials', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrong',
    ])->assertStatus(401);
});

it('rejects login for inactive user', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'inactive@example.com',
        'password' => Hash::make('password'),
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'inactive@example.com',
        'password' => 'password',
    ])->assertStatus(403);
});

it('returns authenticated user info from /me', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});

it('allows user to logout', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->postJson('/api/v1/auth/logout')
        ->assertOk();
});
