<?php

namespace Database\Seeders;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Servicio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos para que el equipo de la app móvil pruebe sin tocar la agenda real.
 *
 *   php artisan db:seed --class=PruebasMovilSeeder
 *
 * Los números empiezan con 52155500 y no corresponden a nadie: si el sistema
 * intentara mandarles un mensaje, Meta lo rechaza por no estar verificados.
 */
class PruebasMovilSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogoSeeder::class);

        $clientas = [
            ['wa_id' => '5215550000001', 'nombre' => 'Prueba Uno',  'acepta_avisos' => true],
            ['wa_id' => '5215550000002', 'nombre' => 'Prueba Dos',  'acepta_avisos' => false],
            ['wa_id' => '5215550000003', 'nombre' => 'Prueba Tres', 'acepta_avisos' => true],
        ];

        foreach ($clientas as $datos) {
            Cliente::firstOrCreate(['wa_id' => $datos['wa_id']], $datos);
        }

        $uno     = Cliente::where('wa_id', '5215550000001')->firstOrFail();
        $dos     = Cliente::where('wa_id', '5215550000002')->firstOrFail();
        $corte   = Servicio::where('nombre', 'Corte de cabello')->firstOrFail();
        $peinado = Servicio::where('nombre', 'Peinado')->firstOrFail();

        // Un día de negocio próximo: el catálogo abre de martes a sábado.
        $dia = Carbon::now()->startOfDay();
        while (! in_array($dia->dayOfWeek, [2, 3, 4, 5, 6], true)) {
            $dia->addDay();
        }
        if ($dia->isToday()) {
            $dia->addWeek();
        }

        $this->cita($uno, $corte, $dia->copy()->setTime(11, 0), Cita::CONFIRMADA);
        $this->cita($dos, $peinado, $dia->copy()->setTime(13, 0), Cita::CONFIRMADA);

        // Una cita pasada y completada, para que la pantalla de historial
        // tenga algo que mostrar.
        $this->cita($uno, $corte, $dia->copy()->subWeek()->setTime(16, 0), Cita::COMPLETADA);

        $this->command?->info('Clientas de prueba: 5215550000001, 5215550000002, 5215550000003');
    }

    private function cita(Cliente $cliente, Servicio $servicio, Carbon $inicio, string $estado): void
    {
        $existe = Cita::where('cliente_id', $cliente->id)
            ->where('inicio', $inicio)
            ->exists();

        if ($existe) {
            return;
        }

        Cita::create([
            'cliente_id'  => $cliente->id,
            'servicio_id' => $servicio->id,
            'seccion_id'  => $servicio->seccion_id,
            'inicio'      => $inicio,
            'fin'         => $inicio->copy()->addMinutes($servicio->duracion_minutos),
            'estado'      => $estado,
            'origen'      => Cita::ORIGEN_MOVIL,
        ]);
    }
}
