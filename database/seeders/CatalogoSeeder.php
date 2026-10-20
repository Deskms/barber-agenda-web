<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\HorarioAtencion;
use App\Models\Servicio;
use Illuminate\Database\Seeder;

class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $servicios = [
            ['nombre' => 'Corte de cabello', 'duracion_minutos' => 60,  'precio' => 180],
            ['nombre' => 'Peinado',          'duracion_minutos' => 90,  'precio' => 350],
            ['nombre' => 'Maquillaje',       'duracion_minutos' => 120, 'precio' => 800],
            ['nombre' => 'Manicura',         'duracion_minutos' => 45,  'precio' => 250],
            ['nombre' => 'Tinte o decoloración', 'duracion_minutos' => 180, 'precio' => 900],
        ];

        foreach ($servicios as $servicio) {
            Servicio::firstOrCreate(['nombre' => $servicio['nombre']], $servicio);
        }

        // Martes a sábado, 10:00 a 19:00. 0 = domingo ... 6 = sábado.
        foreach ([2, 3, 4, 5, 6] as $dia) {
            HorarioAtencion::firstOrCreate(
                ['dia_semana' => $dia],
                ['abre' => '10:00', 'cierra' => '19:00', 'activo' => true],
            );
        }

        // Clientas de prueba. Números inventados salvo el propio, que es el
        // destinatario autorizado en Meta.
        $clientas = [
            ['wa_id' => '5213334895913', 'nombre' => 'Domi (pruebas)', 'acepta_avisos' => true],
            ['wa_id' => '5213331111111', 'nombre' => 'Ana',            'acepta_avisos' => true],
            ['wa_id' => '5213332222222', 'nombre' => 'Rocío',          'acepta_avisos' => true],
            ['wa_id' => '5213333333333', 'nombre' => 'Fernanda',       'acepta_avisos' => false],
        ];

        foreach ($clientas as $clienta) {
            Cliente::firstOrCreate(['wa_id' => $clienta['wa_id']], $clienta);
        }
    }
}
