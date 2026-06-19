<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\Tenant;
use App\Services\TemplateVariableResolverService;

it('resolves contact variables in a template', function () {
    $tenant = new Tenant(['name' => 'Gabinete Deputado Exemplo', 'settings' => ['phone' => '(82) 3333-4444']]);
    $contact = new Contact([
        'name' => 'Maria Oliveira',
        'city' => 'Maceió',
        'state' => 'AL',
        'electoral_zone' => '12',
    ]);

    $resolver = new TemplateVariableResolverService;

    $template = 'Olá, {{contact.first_name}}! Você é de {{contact.city}}. Gabinete: {{tenant.name}}';
    $result = $resolver->resolve($template, $contact, $tenant);

    expect($result)->toBe('Olá, Maria! Você é de Maceió. Gabinete: Gabinete Deputado Exemplo');
});

it('handles contacts with single name', function () {
    $tenant = new Tenant(['name' => 'SGP', 'settings' => []]);
    $contact = new Contact(['name' => 'Abelardo', 'city' => null, 'electoral_zone' => null]);

    $resolver = new TemplateVariableResolverService;
    $result = $resolver->resolve('{{contact.name}} - {{contact.first_name}}', $contact, $tenant);

    expect($result)->toBe('Abelardo - Abelardo');
});
