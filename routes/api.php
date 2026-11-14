<?php

use App\Http\Controllers\Api\Movil\AuthController as MovilAuthController;
use App\Http\Controllers\Api\Movil\CitaController as MovilCitaController;
use App\Http\Controllers\Api\Movil\ServicioController as MovilServicioController;
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
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API del backend WEB (Dominic) — la consume el frontend web (Franc)
|--------------------------------------------------------------------------
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

            Route::get('agenda', [AgendaController::class, 'index']);
            Route::get('agenda/cambios', [AgendaController::class, 'cambios']);
            Route::patch('citas/{cita}/estado', [CitaController::class, 'estado']);

            Route::get('huecos', [HuecoController::class, 'index']);
            Route::get('huecos/{hueco}/candidatas', [HuecoController::class, 'candidatas']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| API del backend MÓVIL (José Manuel Castellanos) — App Android (Rodrigo)
|--------------------------------------------------------------------------
| Prefijo /api/movil (SCRUM-55 a SCRUM-57)
*/
Route::prefix('movil')->group(function () {
    // Públicos: Registro, Login, Catálogo de servicios y Horarios/Disponibilidad
    Route::post('auth/registro', [MovilAuthController::class, 'registro'])->middleware('throttle:10,1');
    Route::post('auth/login', [MovilAuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('servicios', [MovilServicioController::class, 'index']);
    Route::get('servicios/{servicio}', [MovilServicioController::class, 'show']);
    Route::get('horarios', [HorarioController::class, 'index']);
    Route::get('disponibilidad', DisponibilidadController::class);
    Route::get('slots', [MovilCitaController::class, 'horarios']);

    // Protegidos: Clienta autenticada con token Bearer de Sanctum
    Route::middleware(['auth:sanctum', 'activo', 'rol:cliente'])->group(function () {
        Route::post('auth/logout', [MovilAuthController::class, 'logout']);
        Route::get('auth/me', [MovilAuthController::class, 'me']);

        Route::get('citas', [MovilCitaController::class, 'index']);
        Route::post('citas', [MovilCitaController::class, 'store']);
        Route::get('citas/{cita}', [MovilCitaController::class, 'show']);
        Route::patch('citas/{cita}', [MovilCitaController::class, 'update']);
        Route::post('citas/{cita}/cancelar', [MovilCitaController::class, 'cancelar']);

        Route::get('mi/historial', [MiCuentaController::class, 'historial']);
    });
});