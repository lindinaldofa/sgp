<?php

declare(strict_types=1);

use App\Models\IntegrationSource;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;

it('creates an integration source and auto-creates an origin tag', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->postJson('/api/v1/integration-sources', [
        'name' => 'Site institucional',
    ]);

    $response->assertCreated()
        ->assertJsonPath('source.name', 'Site institucional')
        ->assertJsonStructure(['source' => ['ulid', 'name', 'tag'], 'token']);

    $this->assertDatabaseHas('integration_sources', [
        'tenant_id' => $tenant->id,
        'name' => 'Site institucional',
    ]);
    $this->assertDatabaseHas('tags', [
        'tenant_id' => $tenant->id,
        'name' => 'origem: Site institucional',
    ]);
});

it('reveals the plain token only once and regenerates it', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $tag = Tag::create(['tenant_id' => $tenant->id, 'name' => 'App Mobile']);
    $source = IntegrationSource::create([
        'tenant_id' => $tenant->id,
        'name' => 'App Mobile',
        'token' => IntegrationSource::hashToken('old-token'),
        'tag_id' => $tag->id,
    ]);

    $response = $this->actingAs($user)->postJson("/api/v1/integration-sources/{$source->ulid}/regenerate-token");

    $response->assertOk()->assertJsonStructure(['token']);
    $newToken = $response->json('token');

    expect($newToken)->not->toBe('old-token');
    expect(IntegrationSource::findActiveByPlainToken('old-token'))->toBeNull();
    expect(IntegrationSource::findActiveByPlainToken($newToken)->id)->toBe($source->id);
});
