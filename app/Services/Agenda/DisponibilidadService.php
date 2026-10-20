<?php

namespace App\Services\Agenda;

use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Models\Servicio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Única fuente de verdad sobre qué horarios están libres.
 *
 * Nadie más calcula disponibilidad: ni el controlador, ni la IA, ni el panel.
 * Si esta clase dice que un intervalo está libre, lo está.
 */
class DisponibilidadService
{
    /** Cada cuántos minutos se ofrece un inicio de cita. */
    public const PASO_MINUTOS = 15;

    /** El horario de atención vigente para ese día de la semana, si el negocio abre. */
    public function horarioDe(Carbon $dia): ?HorarioAtencion
    {
        return HorarioAtencion::query()
            ->where('dia_semana', $dia->dayOfWeek)
            ->where('activo', true)
            ->first();
    }

    /** ¿El intervalo completo cabe dentro del horario de atención de ese día? */
    public function dentroDelHorario(Carbon $inicio, Carbon $fin): bool
    {
        $horario = $this->horarioDe($inicio);

        if (! $horario) {
            return false;
        }

        // Una cita no puede cruzar la medianoche hacia otro día de atención.
        if (! $inicio->isSameDay($fin) && ! $fin->equalTo($inicio->copy()->startOfDay()->addDay())) {
            return false;
        }

        $abre   = $inicio->copy()->setTimeFromTimeString($horario->abre);
        $cierra = $inicio->copy()->setTimeFromTimeString($horario->cierra);

        return $inicio->greaterThanOrEqualTo($abre) && $fin->lessThanOrEqualTo($cierra);
    }

    /** ¿Está libre este intervalo exacto? Ignora opcionalmente una cita (útil al reprogramar). */
    public function estaLibre(Carbon $inicio, Carbon $fin, ?int $ignorarCitaId = null): bool
    {
        if (! $this->dentroDelHorario($inicio, $fin)) {
            return false;
        }

        return ! Cita::query()
            ->vigentes()
            ->queChocanCon($inicio, $fin)
            ->when($ignorarCitaId, fn ($q) => $q->whereKeyNot($ignorarCitaId))
            ->exists();
    }

    /**
     * Todos los horarios en los que ese servicio cabe completo ese día.
     *
     * @return Collection<int, array{inicio: Carbon, fin: Carbon}>
     */
    public function slotsDisponibles(Servicio $servicio, Carbon $dia, ?int $paso = null, ?int $ignorarCitaId = null): Collection
    {
        $paso    = $paso ?: self::PASO_MINUTOS;
        $horario = $this->horarioDe($dia);

        if (! $horario) {
            return collect();
        }

        $abre   = $dia->copy()->setTimeFromTimeString($horario->abre);
        $cierra = $dia->copy()->setTimeFromTimeString($horario->cierra);
        $ahora  = Carbon::now();

        // Una sola consulta para todo el día; el traslape se evalúa en memoria.
        $ocupadas = Cita::query()
            ->vigentes()
            ->queChocanCon($abre, $cierra)
            ->when($ignorarCitaId, fn ($q) => $q->whereKeyNot($ignorarCitaId))
            ->get(['inicio', 'fin']);

        $slots  = collect();
        $cursor = $abre->copy();

        while (true) {
            $fin = $cursor->copy()->addMinutes($servicio->duracion_minutos);

            if ($fin->greaterThan($cierra)) {
                break;
            }

            $enElPasado = $cursor->lessThan($ahora);

            $choca = $ocupadas->contains(
                fn (Cita $c) => $cursor->lessThan($c->fin) && $fin->greaterThan($c->inicio)
            );

            if (! $enElPasado && ! $choca) {
                $slots->push(['inicio' => $cursor->copy(), 'fin' => $fin->copy()]);
            }

            $cursor->addMinutes($paso);
        }

        return $slots;
    }

    /**
     * Los primeros N horarios libres a partir de un día, buscando hacia adelante.
     * Es lo que la IA necesita para responder "¿tienes lugar el sábado?".
     *
     * @return Collection<int, array{inicio: Carbon, fin: Carbon}>
     */
    public function proximosSlots(Servicio $servicio, Carbon $desde, int $cuantos = 3, int $diasMaximos = 14): Collection
    {
        $encontrados = collect();
        $dia         = $desde->copy()->startOfDay();

        for ($i = 0; $i < $diasMaximos && $encontrados->count() < $cuantos; $i++) {
            $encontrados = $encontrados->concat(
                $this->slotsDisponibles($servicio, $dia->copy())
            );

            $dia->addDay();
        }

        return $encontrados->take($cuantos)->values();
    }
}
