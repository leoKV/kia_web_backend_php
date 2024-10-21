<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CancionController;
use App\Http\Controllers\CancionTagController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ClienteController;
use App\Http\Middleware\CorsMiddleware;

// Aplicar el middleware CorsMiddleware a todas las rutas
Route::middleware([CorsMiddleware::class])->group(function () {
    Route::prefix('kia_web')->group(function () {
        Route::prefix('cancion')->group(function () {
            Route::get('/', [CancionController::class, 'getAllCanciones']);
            Route::get('/buscar', [CancionController::class, 'getCancionesByNombre']);
            Route::get('/detalle', [CancionController::class, 'getCancionDetailById']);
            Route::get('/whatsapp', [CancionController::class, 'getNumeroWhatsapp']);
            Route::get('/categoria', [CancionController::class, 'getCancionesByCategoria']);
        });

        Route::prefix('cancionTag')->group(function () {
            Route::get('/', [CancionTagController::class, 'getCancionesByTags']);
        });

        Route::prefix('tag')->group(function () {
            Route::get('/tipo', [TagController::class, 'getTagsByTipoTag']);
        });

        Route::prefix('usuario')->group(function () {
            Route::post('/login', [ClienteController::class, 'loginCliente']);
            Route::get('/pedido', [ClienteController::class, 'getPedidosByClienteId']);
        });
    });
});