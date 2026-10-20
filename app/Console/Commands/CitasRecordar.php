<?php

namespace App\Console\Commands;

use App\Models\Cita;
use App\Services\WhatsApp\Avisos;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Envía el recordatorio de las citas de mañana.
 *
 * Corre cada hora desde el programador. Cada cita se "reclama" con un UPDATE
 * condicional antes de enviar: si la tarea corre dos veces a la vez, solo una
 * consigue la fila y la clienta recibe un solo recordatorio.
 */
class CitasRecordar extends Command
{
    protected $signature = 'citas:recordar {--simular : Muestra a quién se le enviaría sin enviar nada}';

    protected $description = 'Envía por WhatsApp el recordatorio de las citas del día siguiente';

    public function handle(Avisos $avisos): int
    {
        $manana = Carbon::tomorrow();

        $pendientes = Cita::query()
            ->with(['cliente', 'servicio'])
            ->where('estado', Cita::CONFIRMADA)
            ->whereNull('recordatorio_enviado_en')
            ->whereBetween('inicio', [$manana->copy()->startOfDay(), $manana->copy()->endOfDay()])
            ->get();

        if ($pendientes->isEmpty()) {
            $this->info('No hay recordatorios pendientes para mañana.');

            return self::SUCCESS;
        }

        foreach ($pendientes as $cita) {
            $etiqueta = "#{$cita->id} {$cita->cliente->nombre} {$cita->inicio->format('d/m H:i')}";

            if ($this->option('simular')) {
                $this->line("  · {$etiqueta}");

                continue;
            }

            $reclamada = Cita::query()
                ->whereKey($cita->id)
                ->whereNull('recordatorio_enviado_en')
                ->update(['recordatorio_enviado_en' => Carbon::now()]);

            if (! $reclamada) {
                continue;
            }

            if ($avisos->recordatorio($cita)) {
                $this->info("  ✓ {$etiqueta}");
            } else {
                // Se libera para que el siguiente ciclo lo reintente.
                Cita::whereKey($cita->id)->update(['recordatorio_enviado_en' => null]);
                $this->error("  ✗ {$etiqueta}");
            }
        }

        return self::SUCCESS;
    }
}
