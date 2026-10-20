<?php

use App\Http\Controllers\Panel\AgendaController;
use App\Http\Controllers\Panel\AuthController;
use App\Http\Controllers\Panel\CatalogoController;
use App\Http\Controllers\Panel\CitaController;
use App\Http\Controllers\Panel\ClienteController;
use App\Http\Controllers\Panel\HuecoController;
use App\Http\Controllers\Panel\WhatsAppController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});

Route::view('/privacidad', 'privacidad')->name('privacidad');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [AgendaController::class, 'dia'])->name('agenda');
    Route::get('/semana', [AgendaController::class, 'semana'])->name('agenda.semana');
    Route::get('/agenda/feed', [AgendaController::class, 'feed'])->name('agenda.feed');

    Route::get('/citas/nueva', [CitaController::class, 'create'])->name('citas.create');
    Route::post('/citas', [CitaController::class, 'store'])->name('citas.store');
    Route::get('/citas/disponibilidad', [CitaController::class, 'disponibilidad'])->name('citas.disponibilidad');
    Route::get('/citas/{cita}/mover', [CitaController::class, 'edit'])->name('citas.edit');
    Route::put('/citas/{cita}', [CitaController::class, 'update'])->name('citas.update');
    Route::post('/citas/{cita}/cancelar', [CitaController::class, 'cancelar'])->name('citas.cancelar');
    Route::post('/citas/{cita}/estado', [CitaController::class, 'estado'])->name('citas.estado');

    Route::get('/servicios', [CatalogoController::class, 'servicios'])->name('servicios');
    Route::post('/servicios', [CatalogoController::class, 'guardarServicio'])->name('servicios.store');
    Route::put('/servicios/{servicio}', [CatalogoController::class, 'guardarServicio'])->name('servicios.update');
    Route::get('/horarios', [CatalogoController::class, 'horarios'])->name('horarios');
    Route::put('/horarios', [CatalogoController::class, 'guardarHorarios'])->name('horarios.update');

    Route::get('/clientas', [ClienteController::class, 'index'])->name('clientes');
    Route::get('/clientas/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
    Route::put('/clientas/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');

    Route::get('/huecos', [HuecoController::class, 'index'])->name('huecos');
    Route::get('/huecos/{cita}', [HuecoController::class, 'show'])->name('huecos.show');
    Route::post('/huecos/{cita}/difundir', [HuecoController::class, 'difundir'])->name('huecos.difundir');

    Route::get('/whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp');
    Route::post('/whatsapp/prueba', [WhatsAppController::class, 'prueba'])->name('whatsapp.prueba')->middleware('throttle:5,1');
});
