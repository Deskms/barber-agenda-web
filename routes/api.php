<?php

use App\Http\Controllers\Api\Web\AgendaController;
use App\Http\Controllers\Api\Web\AuthController;
use App\Http\Controllers\Api\Web\BloqueoController;
use App\Http\Controllers\Api\Web\CitaController;
use App\Http\Controllers\Api\Web\ClienteController;
use App\Http\Controllers\Api\Web\DisponibilidadController;
use App\Http\Controllers\Api\Web\HorarioController;
use App\Http\Controllers\Api\Web\HuecoController;
use App\Http\Controllers\Api\Web\MiCuentaController;
use App\Http\Controllers\Api\Web\ServicioController;
use App\Http\Controllers\Api\Web\UsuarioController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WhatsApp (Meta Cloud API)
|--------------------------------------------------------------------------
*/
Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);

/*
|--------------------------------------------------------------------------
| API del backend WEB (Dominic) — la consume el frontend web (Franc)
|--------------------------------------------------------------------------
|
| Prefijo /api/web. Autenticación con token Bearer de Sanctum.
| Los endpoints de la app Android (/api/clientas/*, /api/citas, …) son del
| backend móvil (José) y no viven aquí. Ver docs/INTEGRACION_FRONTEND_WEB_FRANC.md.
|
*/
Route::prefix('web')->group(function () {
    // Públicos
    Route::post('auth/registro', [AuthController::class, 'registro'])->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('servicios', [ServicioController::class, 'index']);
    Route::get('servicios/{servicio}', [ServicioController::class, 'show']);
    Route::get('horarios', [HorarioController::class, 'index']);
    Route::get('disponibilidad', DisponibilidadController::class);

    // Cualquier usuario autenticado y activo (dueña o clienta)
    Route::middleware(['auth:sanctum', 'activo'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('citas', [CitaController::class, 'index']);
        Route::post('citas', [CitaController::class, 'store']);
        Route::get('citas/{cita}', [CitaController::class, 'show']);
        Route::patch('citas/{cita}', [CitaController::class, 'update']);
        Route::post('citas/{cita}/cancelar', [CitaController::class, 'cancelar']);

        Route::get('mi/historial', [MiCuentaController::class, 'historial'])->middleware('rol:cliente');

        // Solo la dueña
        Route::prefix('admin')->middleware('rol:admin')->group(function () {
            Route::get('usuarios', [UsuarioController::class, 'index']);
            Route::post('usuarios', [UsuarioController::class, 'store']);
            Route::patch('usuarios/{usuario}', [UsuarioController::class, 'update']);

            Route::get('servicios', [ServicioController::class, 'adminIndex']);
            Route::post('servicios', [ServicioController::class, 'store']);
            Route::put('servicios/{servicio}', [ServicioController::class, 'update']);
            Route::delete('servicios/{servicio}', [ServicioController::class, 'destroy']);

            Route::put('horarios/{dia}', [HorarioController::class, 'update'])->whereNumber('dia');

            Route::get('bloqueos', [BloqueoController::class, 'index']);
            Route::post('bloqueos', [BloqueoController::class, 'store']);
            Route::delete('bloqueos/{bloqueo}', [BloqueoController::class, 'destroy']);

            Route::get('clientes', [ClienteController::class, 'index']);
            Route::post('clientes', [ClienteController::class, 'store']);
            Route::get('clientes/{cliente}', [ClienteController::class, 'show']);
            Route::patch('clientes/{cliente}', [ClienteController::class, 'update']);
            Route::get('clientes/{cliente}/historial', [ClienteController::class, 'historial']);
            Route::get('clientes/{cliente}/mensajes', [ClienteController::class, 'mensajes']);

            Route::get('agenda', [AgendaController::class, 'index']);
            Route::get('agenda/cambios', [AgendaController::class, 'cambios']);
            Route::patch('citas/{cita}/estado', [CitaController::class, 'estado']);

            Route::get('huecos', [HuecoController::class, 'index']);
            Route::get('huecos/{hueco}/candidatas', [HuecoController::class, 'candidatas']);
            Route::post('huecos/{hueco}/difundir', [HuecoController::class, 'difundir']);
        });
    });
});
