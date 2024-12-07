<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CancionController;
use App\Http\Controllers\CancionTagController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\AdminController;
use App\Http\Middleware\CorsMiddleware;

// Aplicar el middleware CorsMiddleware a todas las rutas
Route::middleware([CorsMiddleware::class])->group(function () {
    Route::prefix('kia_web')->group(function () {
        Route::prefix('cancion')->group(function () {
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
            Route::post('/loginClienteClave', [ClienteController::class, 'loginClienteClave']);
            Route::get('/pedido', [ClienteController::class, 'getPedidosByClienteId']);
            Route::post('/updateEstadoPagoCliente', [ClienteController::class, 'updateEstadoPagoCliente']);
            Route::get('/getCancionesRandom', [ClienteController::class, 'getCancionesRandom']);
            Route::post('/updateComentarioCancion', [ClienteController::class, 'updateComentarioCancion']);
            Route::post('/updateDescargas', [ClienteController::class, 'updateDescargas']);
        });

        Route::prefix('admin')->group(function () {
            Route::post('/login', [AdminController::class, 'loginAdmin']);
            Route::get('/cliente', [AdminController::class, 'getClientes']);
            Route::get('/getClientesPage', [AdminController::class, 'getClientesPage']);
            Route::get('/getClientesByNombre', [AdminController::class, 'getClientesByNombre']);
            Route::get('/getCreadores', [AdminController::class, 'getCreadores']);
            Route::get('/pedido', [AdminController::class, 'getPedidosClientes']);
            Route::get('/getPedidosEstadisticas', [AdminController::class, 'getPedidosEstadisticas']);
            Route::post('/addPedido', [AdminController::class, 'agregarPedido']);
            Route::post('/closePedido', [AdminController::class, 'closePedido']);
            Route::post('/openPedido', [AdminController::class, 'openPedido']);
            Route::post('/copyUrl', [AdminController::class, 'copyUrl']);
            Route::post('/copyPedido', [AdminController::class, 'copyPedido']);
            Route::post('/addCancionPedido', [AdminController::class, 'addCancionPedido']);
            Route::post('/deleteCancionPedido', [AdminController::class, 'deleteCancionPedido']);
            Route::get('/getPorcentajeCancion', [AdminController::class, 'getPorcentajeCancion']);
            Route::post('/messageCliente', [AdminController::class, 'messageCliente']);
            Route::post('/updateCostoCancion', [AdminController::class, 'updateCostoCancion']);
            Route::post('/updateEstadoPago', [AdminController::class, 'updateEstadoPago']);
            Route::post('/updateCaracteristica', [AdminController::class, 'updateCaracteristica']);
            Route::get('/getCaracteristicas', [AdminController::class, 'getCaracteristicas']);
            Route::post('/valorCancionC', [AdminController::class, 'valorCancionC']);
            Route::post('/updateNotificado', [AdminController::class, 'updateNotificado']);
            Route::get('/getCancionesFiltro', [AdminController::class, 'getCancionesFiltro']);
            Route::post('/crudCliente', [AdminController::class, 'crudCliente']);
            Route::post('/updateClaveCliente', [AdminController::class, 'updateClaveCliente']);
            //PAGOS
            Route::get('/getPagosCreadores', [AdminController::class, 'getPagosCreadores']);
            Route::post('/updateCancionPago', [AdminController::class, 'updateCancionPago']);
            Route::post('/updateCostoCancionPago', [AdminController::class, 'updateCostoCancionPago']);
            Route::post('/sendPago', [AdminController::class, 'sendPago']);
            Route::post('/closePago', [AdminController::class, 'closePago']);
            Route::post('/openPago', [AdminController::class, 'openPago']);
            Route::post('/eliminarPago', [AdminController::class, 'eliminarPago']);
            Route::post('/deleteCancionPago', [AdminController::class, 'deleteCancionPago']);
        });
    });
});

