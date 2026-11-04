<?php

namespace Tests\Concerns;

use App\Models\Cliente;
use App\Models\HorarioAtencion;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/**
 * Escenario común: hoy es lunes 9 de noviembre de 2026, 08:00 (America/Mexico_City).
 * Se atiende de martes a sábado, 09:00 a 19:00. El martes de prueba es el 10 de noviembre.
 */
trait CreaAgenda
{
    protected function prepararAgenda(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 08:00:00'));

        foreach ([2, 3, 4, 5, 6] as $dia) {
            HorarioAtencion::create(['dia_semana' => $dia, 'abre' => '09:00', 'cierra' => '19:00', 'activo' => true]);
        }
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function clienta(array $atributos = []): User
    {
        $usuario = User::factory()->create();
        Cliente::factory()->create(['user_id' => $usuario->id] + $atributos);

        return $usuario->load('cliente');
    }

    protected function como(User $usuario): static
    {
        Sanctum::actingAs($usuario);

        return $this;
    }

    protected function servicioSimple(string $nombre = 'Corte de hombre', int $minutos = 60): Servicio
    {
        return Servicio::factory()->create(['nombre' => $nombre, 'duracion_minutos' => $minutos]);
    }

    /** Tinte de 3 horas: aplicación 30 min (activa), procesamiento 120 min, enjuague 30 min (activa). */
    protected function tinte(): Servicio
    {
        $tinte = Servicio::factory()->create(['nombre' => 'Tinte', 'duracion_minutos' => 180]);
        $tinte->etapas()->createMany([
            ['orden' => 1, 'nombre' => 'Aplicación', 'duracion_minutos' => 30, 'ocupa_estilista' => true],
            ['orden' => 2, 'nombre' => 'Procesamiento', 'duracion_minutos' => 120, 'ocupa_estilista' => false],
            ['orden' => 3, 'nombre' => 'Enjuague y secado', 'duracion_minutos' => 30, 'ocupa_estilista' => true],
        ]);

        return $tinte;
    }

    /** La dueña agenda directamente para una clienta. */
    protected function agendarComoAdmin(User $admin, Servicio $servicio, Cliente $cliente, string $inicio)
    {
        return $this->como($admin)->postJson('/api/web/citas', [
            'servicio_id' => $servicio->id,
            'cliente_id' => $cliente->id,
            'inicio' => $inicio,
        ]);
    }
}
