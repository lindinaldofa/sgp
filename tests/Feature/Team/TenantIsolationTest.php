<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;

it('cannot access contacts from another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => UserRole::Admin]);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => UserRole::Admin]);

    Contact::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Contato do Tenant B']);

    $response = $this->actingAs($userA)->getJson('/api/v1/contacts');
    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->not->toContain('Contato do Tenant B');
});

it('cannot view a contact from another tenant by ulid', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => UserRole::Admin]);
    $contactB = Contact::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA)->getJson("/api/v1/contacts/{$contactB->ulid}")
        ->assertNotFound();
});

it('blocks inactive tenant users from accessing API', function () {
    $tenant = Tenant::factory()->create(['is_active' => false]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

    $this->actingAs($user)->getJson('/api/v1/contacts')
        ->assertStatus(403);
});

it('blocks inactive users from accessing API', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

    $this->actingAs($user)->getJson('/api/v1/contacts')
        ->assertStatus(403);
});
