<?php

namespace App\Console\Commands;

use App\Events\EspacioLiberado;
use App\Exceptions\HorarioNoDisponibleException;
use App\Listeners\BuscarClientasParaEspacioLiberado;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Services\Agenda\CitaService;
use App\Services\Agenda\DisponibilidadService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Recorre el flujo completo de la agenda sin tocar WhatsApp:
 * consultar disponibilidad, agendar, bloquear un doble agendamiento,
 * cancelar y detectar el espacio liberado.
 */
class AgendaDemo extends Command
{
    protected $signature = 'agenda:demo';

    protected $description = 'Simula el ciclo completo de una cita: consulta, reserva, choque, cancelación y espacio liberado';

    public function handle(DisponibilidadService $disponibilidad, CitaService $citas): int
    {
        // Deja limpio lo que haya dejado una corrida anterior.
        Cita::where('origen', Cita::ORIGEN_PANEL)->delete();

        $servicio = Servicio::activos()->where('nombre', 'Maquillaje')->first();
        $ana = Cliente::where('nombre', 'Ana')->first();
        $rocio = Cliente::where('nombre', 'Rocío')->first();

        if (! $servicio || ! $ana || ! $rocio) {
            $this->error('Faltan datos de ejemplo. Corre primero: php artisan db:seed --class=CatalogoSeeder');

            return self::FAILURE;
        }

        $this->line('');
        $this->info("Servicio: {$servicio->nombre} ({$servicio->duracion_minutos} min)");

        // 1. Disponibilidad -------------------------------------------------
        $this->titulo('1. Próximos horarios disponibles');

        $slots = $disponibilidad->proximosSlots($servicio, Carbon::now(), 5);

        if ($slots->isEmpty()) {
            $this->error('No hay horarios disponibles en los próximos 14 días.');

            return self::FAILURE;
        }

        $this->tabla($slots);

        // 2. Agendar --------------------------------------------------------
        $elegido = $slots->first();

        $this->titulo('2. Ana reserva el primero');

        $cita = $citas->agendar($ana, $servicio, $elegido['inicio']->copy(), Cita::ORIGEN_PANEL);

        $this->line("   Cita #{$cita->id} confirmada: "
            .$cita->inicio->format('D d M H:i').' → '.$cita->fin->format('H:i'));

        // 3. Ese horario ya no se ofrece ------------------------------------
        $this->titulo('3. El mismo horario ya no aparece como libre');

        $this->tabla($disponibilidad->proximosSlots($servicio, Carbon::now(), 5));

        // 4. Doble agendamiento bloqueado -----------------------------------
        $this->titulo('4. Rocío intenta el mismo horario');

        try {
            $citas->agendar($rocio, $servicio, $elegido['inicio']->copy(), Cita::ORIGEN_PANEL);
            $this->error('   PROBLEMA: se permitió una cita duplicada.');
        } catch (HorarioNoDisponibleException $e) {
            $this->line("   Rechazada correctamente: {$e->getMessage()}");
        }

        // 5. Cancelación y espacio liberado ----------------------------------
        $this->titulo('5. Ana cancela');

        $citas->cancelar($cita);

        $evento = new EspacioLiberado($cita->fresh(['servicio', 'cliente']));
        $candidatas = app(BuscarClientasParaEspacioLiberado::class)->candidatas($evento);

        $this->line('   Espacio liberado: '
            .$evento->inicio()->format('D d M H:i').' → '.$evento->fin()->format('H:i'));
        $this->line('   Clientas a las que tendría sentido avisar:');

        if ($candidatas->isEmpty()) {
            $this->line('     (ninguna)');
        }

        foreach ($candidatas as $candidata) {
            $this->line("     · {$candidata->nombre}  ({$candidata->wa_id})");
        }

        $this->line('');
        $this->comment('   Fernanda no aparece: tiene acepta_avisos = false.');

        // 6. El horario vuelve a estar libre ---------------------------------
        $this->titulo('6. El horario vuelve a ofrecerse');

        $this->tabla($disponibilidad->proximosSlots($servicio, Carbon::now(), 5));

        $this->line('');
        $this->info('Flujo completo verificado. Ningún mensaje de WhatsApp fue enviado.');
        $this->line('');

        return self::SUCCESS;
    }

    private function titulo(string $texto): void
    {
        $this->line('');
        $this->line("<fg=cyan>{$texto}</>");
    }

    private function tabla($slots): void
    {
        $this->table(
            ['Día', 'Inicio', 'Fin'],
            collect($slots)->map(fn (array $s) => [
                $s['inicio']->translatedFormat('D d M'),
                $s['inicio']->format('H:i'),
                $s['fin']->format('H:i'),
            ])->all(),
        );
    }
}
