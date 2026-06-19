<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;

it('lists contacts for authenticated user', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Contact::factory()->count(5)->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->getJson('/api/v1/contacts');

    $response->assertOk()
        ->assertJsonCount(5, 'data');
});

it('creates a contact', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->postJson('/api/v1/contacts', [
        'name' => 'João Silva',
        'email' => 'joao@example.com',
        'type' => 'voter',
        'lgpd_consent' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'João Silva');

    $this->assertDatabaseHas('contacts', ['email' => 'joao@example.com', 'tenant_id' => $tenant->id]);
});

it('returns a contact by ulid', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $contact = Contact::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->getJson("/api/v1/contacts/{$contact->ulid}")
        ->assertOk()
        ->assertJsonPath('ulid', $contact->ulid);
});

it('soft-deletes a contact', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Admin]);
    $contact = Contact::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->deleteJson("/api/v1/contacts/{$contact->ulid}")
        ->assertNoContent();

    $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
});

it('generates a unique ulid for each contact', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->postJson('/api/v1/contacts', ['name' => 'Contato 1']);
    $this->actingAs($user)->postJson('/api/v1/contacts', ['name' => 'Contato 2']);

    $ulids = Contact::where('tenant_id', $tenant->id)->pluck('ulid');
    expect($ulids)->toHaveCount(2)->each->toBeValidUlid();
});
