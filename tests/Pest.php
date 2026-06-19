<?php

declare(strict_types=1);
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Configuração global dos testes Pest
// Executa antes de cada teste

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature');

// Helpers globais disponíveis em todos os testes
expect()->extend('toBeValidUlid', function () {
    return $this->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
});

expect()->extend('toBeE164Phone', function () {
    return $this->toMatch('/^\+[1-9]\d{1,14}$/');
});

// Verificar que não há vazamento de dados entre tenants
expect()->extend('toBelongToTenant', function (int $tenantId) {
    return $this->each(fn ($item) => $item->tenant_id->toBe($tenantId));
});
