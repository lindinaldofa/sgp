<?php

declare(strict_types=1);

// =============================================================
// Testes Arquiteturais — Pest Arch
// Garante que as convenções do projeto são seguidas
// =============================================================

arch('Todos os arquivos PHP usam strict_types')
    ->expect('App')
    ->toUseStrictTypes();

arch('Controllers estendem a classe Controller base')
    ->expect('App\Http\Controllers')
    ->toExtend('App\Http\Controllers\Controller')
    ->ignoring('App\Http\Controllers\Controller');

arch('Actions têm apenas um método público: execute()')
    ->expect('App\Actions')
    ->toHaveMethod('execute');

arch('DTOs são readonly e final')
    ->expect('App\Data')
    ->toBeFinal()
    ->toBeReadonly();

arch('Models estendem apenas Eloquent Model')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('Enums são backed enums com string ou int')
    ->expect('App\Enums')
    ->toBeStringBackedEnum();

arch('Policies estendem a classe base ou implementam Gate')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy');

arch('Jobs são dispatcháveis')
    ->expect('App\Jobs')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

arch('Resources estendem JsonResource')
    ->expect('App\Http\Resources')
    ->toExtend('Illuminate\Http\Resources\Json\JsonResource');

arch('Form Requests estendem FormRequest')
    ->expect('App\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('Services não usam DB Facade diretamente')
    ->expect('App\Services')
    ->not->toUse('Illuminate\Support\Facades\DB');
