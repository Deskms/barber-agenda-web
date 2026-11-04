<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\HorarioAtencion;
use App\Models\Servicio;
use Illuminate\Database\Seeder;

/**
 * Catálogo para desarrollo y pruebas locales. Es reejecutable: no duplica.
 * No usar en producción: los precios, duraciones y etapas son de ejemplo y
 * deben confirmarse con la dueña.
 */
class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $servicios = [
            ['nombre' => 'Corte de cabello', 'duracion_minutos' => 60,  'precio' => 180],
            ['nombre' => 'Peinado',          'duracion_minutos' => 90,  'precio' => 350],
            ['nombre' => 'Maquillaje',       'duracion_minutos' => 120, 'precio' => 800],
            ['nombre' => 'Manicura',         'duracion_minutos' => 45,  'precio' => 250],
            ['nombre' => 'Corte de hombre',  'duracion_minutos' => 60,  'precio' => 150],
        ];

        foreach ($servicios as $servicio) {
            Servicio::firstOrCreate(['nombre' => $servicio['nombre']], $servicio);
        }

        // Servicio con tiempo de procesamiento: durante el reposo del tinte la
        // estilista puede atender a otra clienta. Etapas de EJEMPLO.
        $tinte = Servicio::firstOrCreate(
            ['nombre' => 'Tinte'],
            ['duracion_minutos' => 180, 'precio' => 900],
        );

        if ($tinte->etapas()->doesntExist()) {
            $tinte->etapas()->createMany([
                ['orden' => 1, 'nombre' => 'Aplicación del tinte', 'duracion_minutos' => 30,  'ocupa_estilista' => true],
                ['orden' => 2, 'nombre' => 'Procesamiento',        'duracion_minutos' => 120, 'ocupa_estilista' => false],
                ['orden' => 3, 'nombre' => 'Enjuague y secado',    'duracion_minutos' => 30,  'ocupa_estilista' => true],
            ]);
        }

        // Martes a sábado, 10:00 a 19:00. 0 = domingo ... 6 = sábado.
        foreach ([2, 3, 4, 5, 6] as $dia) {
            HorarioAtencion::firstOrCreate(
                ['dia_semana' => $dia],
                ['abre' => '10:00', 'cierra' => '19:00', 'activo' => true],
            );
        }

        // Clientas de prueba con números inventados. Una sin consentimiento de avisos.
        $clientas = [
            ['telefono' => '3331111111', 'nombre' => 'Ana',      'acepta_avisos' => true],
            ['telefono' => '3332222222', 'nombre' => 'Rocío',    'acepta_avisos' => true],
            ['telefono' => '3333333333', 'nombre' => 'Fernanda', 'acepta_avisos' => false],
        ];

        foreach ($clientas as $clienta) {
            Cliente::firstOrCreate(['telefono' => $clienta['telefono']], $clienta);
        }
    }
}
