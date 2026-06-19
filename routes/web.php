<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Serve a SPA para qualquer rota não-API (Vue Router gerencia o restante)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
