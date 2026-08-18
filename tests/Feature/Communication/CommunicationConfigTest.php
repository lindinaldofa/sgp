<?php

declare(strict_types=1);

use App\Models\CommunicationConfig;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('saves the openwa and ses configuration for the tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->putJson('/api/v1/communication-config', [
        'ses_from_email' => 'contato@example.com',
        'ses_from_name' => 'Gabinete',
        'openwa_base_url' => 'https://wa.example.com',
        'openwa_api_key' => 'secret-key',
        'openwa_session_id' => 'default',
    ]);

    $response->assertOk()
        ->assertJsonPath('openwa_base_url', 'https://wa.example.com')
        ->assertJsonMissing(['openwa_api_key' => 'secret-key']);

    $this->assertDatabaseHas('communication_configs', [
        'tenant_id' => $tenant->id,
        'openwa_base_url' => 'https://wa.example.com',
        'openwa_session_id' => 'default',
    ]);
});

it('sends a whatsapp test message via the test endpoint', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    CommunicationConfig::create([
        'tenant_id' => $tenant->id,
        'openwa_base_url' => 'https://wa.example.com',
        'openwa_api_key' => 'secret-key',
        'openwa_session_id' => 'default',
    ]);

    Http::fake(['wa.example.com/*' => Http::response(['id' => 'msg-1'], 200)]);

    $this->actingAs($user)->postJson('/api/v1/communication-config/test', [
        'channel' => 'whatsapp',
        'to' => '11988887777',
    ])->assertOk();

    Http::assertSent(fn ($request) => $request['chatId'] === '5511988887777@c.us');
});

it('sends an email test message via the test endpoint', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    CommunicationConfig::create([
        'tenant_id' => $tenant->id,
        'ses_from_email' => 'contato@example.com',
        'ses_from_name' => 'Gabinete',
    ]);

    // Mail::html() bypasses the Mailable-based fake, so we only assert it
    // resolves without throwing against the configured mailer (log driver in tests).
    $this->actingAs($user)->postJson('/api/v1/communication-config/test', [
        'channel' => 'email',
        'to' => 'destinatario@example.com',
    ])->assertOk();
});

it('fails the email test when no ses sender is configured', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->postJson('/api/v1/communication-config/test', [
        'channel' => 'email',
        'to' => 'destinatario@example.com',
    ])->assertStatus(422);
});

it('fails the whatsapp test when openwa is not configured', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->postJson('/api/v1/communication-config/test', [
        'channel' => 'whatsapp',
        'to' => '11988887777',
    ])->assertStatus(422);
});
