<?php

declare(strict_types=1);

use App\Http\Controllers\PublicStorageController;
use Illuminate\Support\Facades\Route;

// Fallback para servir arquivos do disco "public" quando o symlink
// public/storage não existe (veja PublicStorageController) — precisa estar
// ANTES do catch-all do SPA abaixo para ter prioridade no roteamento.
Route::get('storage/{path}', [PublicStorageController::class, 'show'])->where('path', '.*');

// Serve a SPA para qualquer rota não-API (Vue Router gerencia o restante)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
