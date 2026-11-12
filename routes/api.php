<?php

use App\Http\Controllers\Api\Movil\AuthController;
use App\Http\Controllers\Api\Movil\CatalogoController;
use App\Http\Controllers\Api\Movil\CitaController;
use App\Http\Controllers\Api\Movil\PerfilController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])->middleware('throttle:120,1');

/*
|--------------------------------------------------------------------------
| API de la aplicación móvil
|--------------------------------------------------------------------------
|
| La app Android consume estos endpoints contra la MISMA base y los MISMOS
| servicios de dominio que el panel web. No hay lógica de agenda duplicada:
| agendar y cancelar pasan por CitaService, con su transacción y su bloqueo.
|
| Contrato y ejemplos: docs/API_MOVIL.md y docs/openapi.yaml
|
*/
Route::prefix('movil')->name('movil.')->group(function () {

    // Entrada por código de WhatsApp. Más apretado que el resto porque cada
    // petición cuesta un mensaje y es la superficie que alguien intentaría abusar.
    Route::post('auth/codigo', [AuthController::class, 'codigo'])
        ->middleware('throttle:5,10')
        ->name('auth.codigo');

    Route::post('auth/token', [AuthController::class, 'token'])
        ->middleware('throttle:10,10')
        ->name('auth.token');

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::post('auth/salir', [AuthController::class, 'salir'])->name('auth.salir');

        Route::get('catalogo', [CatalogoController::class, 'catalogo'])->name('catalogo');
        Route::get('disponibilidad', [CatalogoController::class, 'disponibilidad'])->name('disponibilidad');

        Route::get('citas', [CitaController::class, 'index'])->name('citas.index');
        Route::post('citas', [CitaController::class, 'store'])->name('citas.store');
        Route::post('citas/{cita}/cancelar', [CitaController::class, 'cancelar'])->name('citas.cancelar');

        Route::get('perfil', [PerfilController::class, 'show'])->name('perfil.show');
        Route::put('perfil', [PerfilController::class, 'update'])->name('perfil.update');
    });
});
