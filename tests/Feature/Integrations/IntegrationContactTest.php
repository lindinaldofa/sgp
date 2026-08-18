<?php

declare(strict_types=1);

use App\Enums\CampaignChannel;
use App\Jobs\SendNewContactAutomationJob;
use App\Models\AutomationRule;
use App\Models\CommunicationConfig;
use App\Models\Contact;
use App\Models\IntegrationSource;
use App\Models\MessageTemplate;
use App\Models\Tag;
use App\Models\Tenant;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

function createIntegrationSource(Tenant $tenant, string $tagName = 'origem: site'): array
{
    $tag = Tag::create(['tenant_id' => $tenant->id, 'name' => $tagName]);
    $plainToken = 'plain-token-'.uniqid();
    $source = IntegrationSource::create([
        'tenant_id' => $tenant->id,
        'name' => $tagName,
        'token' => IntegrationSource::hashToken($plainToken),
        'tag_id' => $tag->id,
    ]);

    return [$source, $plainToken, $tag];
}

it('rejects requests without a valid token', function () {
    $this->postJson('/api/v1/integrations/contacts', ['name' => 'Sem token'])
        ->assertUnauthorized();

    $this->withHeader('Authorization', 'Bearer invalid-token')
        ->postJson('/api/v1/integrations/contacts', ['name' => 'Token invalido'])
        ->assertUnauthorized();
});

it('creates a contact via integration token and tags it with the source origin tag', function () {
    Bus::fake();
    $tenant = Tenant::factory()->create();
    [$source, $plainToken, $tag] = createIntegrationSource($tenant);

    $response = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson('/api/v1/integrations/contacts', [
            'name' => 'Maria Souza',
            'email' => 'maria@example.com',
            'whatsapp' => '11999998888',
        ]);

    $response->assertCreated();

    $contact = Contact::where('tenant_id', $tenant->id)->where('email', 'maria@example.com')->first();
    expect($contact)->not->toBeNull();
    expect($contact->tags()->pluck('tags.id'))->toContain($tag->id);

    $this->assertDatabaseHas('integration_sources', ['id' => $source->id]);
    expect($source->refresh()->last_used_at)->not->toBeNull();

    Bus::assertDispatched(SendNewContactAutomationJob::class, fn ($job) => $job->contactId === $contact->id && $job->tagId === $tag->id,
    );
});

it('reuses an existing contact instead of duplicating and does not retrigger automation', function () {
    Bus::fake();
    $tenant = Tenant::factory()->create();
    [$source, $plainToken, $tag] = createIntegrationSource($tenant);

    $existing = Contact::factory()->create(['tenant_id' => $tenant->id, 'email' => 'joao@example.com']);

    $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson('/api/v1/integrations/contacts', [
            'name' => 'João Atualizado',
            'email' => 'joao@example.com',
        ])
        ->assertCreated();

    expect(Contact::where('tenant_id', $tenant->id)->where('email', 'joao@example.com')->count())->toBe(1);
    expect($existing->fresh()->name)->toBe('João Atualizado');
    expect($existing->fresh()->tags()->pluck('tags.id'))->toContain($tag->id);

    Bus::assertNotDispatched(SendNewContactAutomationJob::class);
});

it('sends a whatsapp automation message via OpenWA when a matching new-contact rule exists for the tag', function () {
    Http::fake(['painel.example.com/*' => Http::response(['id' => 'msg-1'], 200)]);

    $tenant = Tenant::factory()->create();
    [$source, $plainToken, $tag] = createIntegrationSource($tenant, 'origem: landing-page');

    CommunicationConfig::create([
        'tenant_id' => $tenant->id,
        'openwa_base_url' => 'https://painel.example.com',
        'openwa_api_key' => 'test-api-key',
        'openwa_session_id' => 'default',
    ]);

    $template = MessageTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => 'boas_vindas_landing',
        'channel' => CampaignChannel::WhatsApp,
        'body_text' => 'Bem-vindo, {{contact.first_name}}!',
    ]);

    AutomationRule::create([
        'tenant_id' => $tenant->id,
        'name' => 'Boas-vindas landing page',
        'trigger' => 'new_contact',
        'tag_id' => $tag->id,
        'channel' => CampaignChannel::WhatsApp,
        'template_id' => $template->id,
        'is_active' => true,
    ]);

    $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson('/api/v1/integrations/contacts', [
            'name' => 'Carlos Lead',
            'whatsapp' => '11988887777',
            'email' => 'carlos@example.com',
            'lgpd_consent' => true,
        ])
        ->assertCreated();

    Http::assertSent(fn ($request) => $request->url() === 'https://painel.example.com/api/sessions/default/messages/send-text'
        && $request->hasHeader('X-API-Key', 'test-api-key')
        && $request['chatId'] === '5511988887777@c.us'
        && $request['text'] === 'Bem-vindo, Carlos!',
    );
});
