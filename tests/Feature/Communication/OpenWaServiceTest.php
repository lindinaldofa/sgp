<?php

declare(strict_types=1);

use App\Models\CommunicationConfig;
use App\Models\Tenant;
use App\Services\OpenWaService;
use Illuminate\Support\Facades\Http;

it('sends a whatsapp text message through the configured OpenWA instance', function () {
    $tenant = Tenant::factory()->create();
    CommunicationConfig::create([
        'tenant_id' => $tenant->id,
        'openwa_base_url' => 'https://wa.example.com/',
        'openwa_api_key' => 'secret-key',
        'openwa_session_id' => 'default',
    ]);

    Http::fake(['wa.example.com/*' => Http::response(['id' => 'msg-1'], 200)]);

    $sent = app(OpenWaService::class)->sendText('(11) 98888-7777', 'Olá!', $tenant->id);

    expect($sent)->toBeTrue();
    Http::assertSent(fn ($request) => $request->url() === 'https://wa.example.com/api/sessions/default/messages/send-text'
        && $request->hasHeader('X-API-Key', 'secret-key')
        && $request['chatId'] === '5511988887777@c.us'
        && $request['text'] === 'Olá!',
    );
});

it('does not add a duplicate country code when the phone already has one', function () {
    $tenant = Tenant::factory()->create();
    CommunicationConfig::create([
        'tenant_id' => $tenant->id,
        'openwa_base_url' => 'https://wa.example.com',
        'openwa_api_key' => 'secret-key',
        'openwa_session_id' => 'default',
    ]);

    Http::fake(['wa.example.com/*' => Http::response(['id' => 'msg-1'], 200)]);

    app(OpenWaService::class)->sendText('5511988887777', 'Olá!', $tenant->id);

    Http::assertSent(fn ($request) => $request['chatId'] === '5511988887777@c.us');
});

it('returns false without calling OpenWA when the tenant has no config', function () {
    $tenant = Tenant::factory()->create();

    Http::fake();

    $sent = app(OpenWaService::class)->sendText('11988887777', 'Olá!', $tenant->id);

    expect($sent)->toBeFalse();
    Http::assertNothingSent();
});

it('returns false when the OpenWA server responds with an error', function () {
    $tenant = Tenant::factory()->create();
    CommunicationConfig::create([
        'tenant_id' => $tenant->id,
        'openwa_base_url' => 'https://wa.example.com',
        'openwa_api_key' => 'secret-key',
        'openwa_session_id' => 'default',
    ]);

    Http::fake(['wa.example.com/*' => Http::response(['message' => 'session not connected'], 422)]);

    $sent = app(OpenWaService::class)->sendText('11988887777', 'Olá!', $tenant->id);

    expect($sent)->toBeFalse();
});
