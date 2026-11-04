<?php

namespace App\Services\Agenda;

use App\Models\BloqueoAgenda;
use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Models\Servicio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Única fuente de verdad sobre qué horarios están libres.
 *
 * Nadie más calcula disponibilidad: ni el controlador, ni la IA, ni el panel,
 * ni las apps. Si esta clase dice que un horario está libre, lo está.
 *
 * Regla de agenda flexible: cada servicio se divide en etapas. Solo las etapas
 * con ocupa_estilista = true bloquean a la estilista. Durante una etapa de
 * procesamiento (por ejemplo, un tinte reposando) puede atender a otra clienta.
 *
 * Ejemplo: Tinte 9:00-12:00 (aplicación 9:00-9:30 activa, procesamiento
 * 9:30-11:30, enjuague 11:30-12:00 activa) admite un corte 9:30-10:30 y otro
 * 10:30-11:30, porque caen en el procesamiento del tinte.
 */
class DisponibilidadService
{
    /** Valor por defecto; se configura con AGENDA_PASO_MINUTOS. */
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
    public function dentroDelHorario(Carbon $inicio, Carbon $fin, ?HorarioAtencion $horario = null): bool
    {
        $horario ??= $this->horarioDe($inicio);

        if (! $horario) {
            return false;
        }

        // Una cita no puede cruzar la medianoche hacia otro día de atención.
        if (! $inicio->isSameDay($fin) && ! $fin->equalTo($inicio->copy()->startOfDay()->addDay())) {
            return false;
        }

        $abre = $inicio->copy()->setTimeFromTimeString($horario->abre);
        $cierra = $inicio->copy()->setTimeFromTimeString($horario->cierra);

        return $inicio->greaterThanOrEqualTo($abre) && $fin->lessThanOrEqualTo($cierra);
    }

    /**
     * Bloques con horas reales que ocuparía el servicio si empieza en $inicio.
     *
     * @return Collection<int, array{orden:int, nombre:string, inicio:Carbon, fin:Carbon, ocupa_estilista:bool}>
     */
    public function planificar(Servicio $servicio, Carbon $inicio): Collection
    {
        $cursor = $inicio->copy();

        return $servicio->etapasEfectivas()->map(function (array $etapa) use (&$cursor) {
            $bloque = [
                'orden' => $etapa['orden'],
                'nombre' => $etapa['nombre'],
                'inicio' => $cursor->copy(),
                'fin' => $cursor->copy()->addMinutes($etapa['duracion_minutos']),
                'ocupa_estilista' => $etapa['ocupa_estilista'],
            ];
            $cursor = $bloque['fin']->copy();

            return $bloque;
        })->values();
    }

    /** ¿Cabe el servicio completo empezando en $inicio? Ignora opcionalmente una cita (al reprogramar). */
    public function evaluar(Servicio $servicio, Carbon $inicio, ?int $ignorarCitaId = null): ResultadoDisponibilidad
    {
        $servicio->loadMissing('etapas');
        $plan = $this->planificar($servicio, $inicio);
        $contexto = $this->contexto($inicio, $ignorarCitaId);

        return $this->evaluarPlan($plan, $contexto);
    }

    /**
     * Compatibilidad con el laboratorio: ¿está libre el intervalo exacto,
     * tratado como una sola etapa activa?
     */
    public function estaLibre(Carbon $inicio, Carbon $fin, ?int $ignorarCitaId = null): bool
    {
        $plan = collect([[
            'orden' => 1, 'nombre' => 'Intervalo', 'inicio' => $inicio->copy(),
            'fin' => $fin->copy(), 'ocupa_estilista' => true,
        ]]);

        return $this->evaluarPlan($plan, $this->contexto($inicio, $ignorarCitaId))->libre;
    }

    /**
     * Todos los horarios en los que ese servicio cabe completo ese día.
     *
     * @return Collection<int, array{inicio: Carbon, fin: Carbon}>
     */
    public function slotsDisponibles(Servicio $servicio, Carbon $dia, ?int $paso = null): Collection
    {
        $paso = $paso ?: (int) config('agenda.paso_minutos', self::PASO_MINUTOS);
        $servicio->loadMissing('etapas');
        $contexto = $this->contexto($dia);
        $horario = $contexto['horario'];

        if (! $horario) {
            return collect();
        }

        $abre = $dia->copy()->setTimeFromTimeString($horario->abre);
        $cierra = $dia->copy()->setTimeFromTimeString($horario->cierra);

        $slots = collect();
        $cursor = $abre->copy();

        while (true) {
            $plan = $this->planificar($servicio, $cursor);
            $fin = $plan->last()['fin'];

            if ($fin->greaterThan($cierra)) {
                break;
            }

            if ($this->evaluarPlan($plan, $contexto)->libre) {
                $slots->push(['inicio' => $cursor->copy(), 'fin' => $fin->copy()]);
            }

            $cursor->addMinutes($paso);
        }

        return $slots;
    }

    /**
     * Los primeros N horarios libres a partir de un momento, buscando hacia adelante.
     *
     * @return Collection<int, array{inicio: Carbon, fin: Carbon}>
     */
    public function proximosSlots(Servicio $servicio, Carbon $desde, int $cuantos = 3, ?int $diasMaximos = null): Collection
    {
        $diasMaximos ??= (int) config('agenda.dias_busqueda', 14);
        $encontrados = collect();
        $dia = $desde->copy()->startOfDay();

        for ($i = 0; $i < $diasMaximos && $encontrados->count() < $cuantos; $i++) {
            $encontrados = $encontrados->concat(
                $this->slotsDisponibles($servicio, $dia->copy())
                    ->filter(fn (array $s) => $s['inicio']->greaterThanOrEqualTo($desde))
            );

            $dia->addDay();
        }

        return $encontrados->take($cuantos)->values();
    }

    /**
     * Todo lo que hace falta para evaluar un día con pocas consultas: horario,
     * citas vigentes con sus bloques y bloqueos de la agenda.
     *
     * @return array{horario: ?HorarioAtencion, citas: Collection<int, Cita>, bloqueos: Collection<int, BloqueoAgenda>}
     */
    private function contexto(Carbon $dia, ?int $ignorarCitaId = null): array
    {
        $desde = $dia->copy()->startOfDay();
        $hasta = $desde->copy()->addDay();

        return [
            'horario' => $this->horarioDe($desde),
            'citas' => Cita::query()
                ->vigentes()
                ->queChocanCon($desde, $hasta)
                ->when($ignorarCitaId, fn ($q) => $q->whereKeyNot($ignorarCitaId))
                ->with('bloques')
                ->get(),
            'bloqueos' => BloqueoAgenda::query()->queChocanCon($desde, $hasta)->get(),
        ];
    }

    /**
     * @param  Collection<int, array{orden:int, nombre:string, inicio:Carbon, fin:Carbon, ocupa_estilista:bool}>  $plan
     * @param  array{horario: ?HorarioAtencion, citas: Collection<int, Cita>, bloqueos: Collection<int, BloqueoAgenda>}  $contexto
     */
    private function evaluarPlan(Collection $plan, array $contexto): ResultadoDisponibilidad
    {
        $inicio = $plan->first()['inicio'];
        $fin = $plan->last()['fin'];

        if ($inicio->lessThan(Carbon::now())) {
            return ResultadoDisponibilidad::ocupado('en_el_pasado');
        }

        if (! $contexto['horario']) {
            return ResultadoDisponibilidad::ocupado('dia_sin_atencion');
        }

        if (! $this->dentroDelHorario($inicio, $fin, $contexto['horario'])) {
            return ResultadoDisponibilidad::ocupado('fuera_de_horario');
        }

        // Un bloqueo cubre a la cita completa: si la clienta sigue en el local
        // durante el procesamiento, el local debe estar abierto.
        $bloqueada = $contexto['bloqueos']->contains(
            fn (BloqueoAgenda $b) => $inicio->lessThan($b->fin) && $fin->greaterThan($b->inicio)
        );

        if ($bloqueada) {
            return ResultadoDisponibilidad::ocupado('bloqueado');
        }

        $activosNuevos = $plan->where('ocupa_estilista', true);

        foreach ($contexto['citas'] as $cita) {
            foreach ($this->bloquesActivos($cita) as [$bInicio, $bFin]) {
                foreach ($activosNuevos as $nuevo) {
                    if ($nuevo['inicio']->lessThan($bFin) && $nuevo['fin']->greaterThan($bInicio)) {
                        return ResultadoDisponibilidad::ocupado('estilista_ocupada');
                    }
                }
            }
        }

        $maximo = config('agenda.max_clientas_simultaneas');

        if ($maximo !== null && $this->maximoSimultaneo($inicio, $fin, $contexto['citas']) + 1 > (int) $maximo) {
            return ResultadoDisponibilidad::ocupado('capacidad_llena');
        }

        return ResultadoDisponibilidad::libre();
    }

    /**
     * Intervalos en los que una cita ocupa a la estilista. Las citas creadas
     * antes de existir las etapas no tienen bloques: se toman completas.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function bloquesActivos(Cita $cita): array
    {
        if ($cita->bloques->isEmpty()) {
            return [[$cita->inicio, $cita->fin]];
        }

        return $cita->bloques
            ->where('ocupa_estilista', true)
            ->map(fn ($b) => [$b->inicio, $b->fin])
            ->values()
            ->all();
    }

    /** Cuántas citas existentes coinciden a la vez, como máximo, dentro de [$inicio, $fin). */
    private function maximoSimultaneo(Carbon $inicio, Carbon $fin, Collection $citas): int
    {
        $traslapadas = $citas->filter(fn (Cita $c) => $c->inicio->lessThan($fin) && $c->fin->greaterThan($inicio));

        // El máximo de traslapes ocurre en el inicio de alguna cita (o en $inicio).
        $puntos = $traslapadas->map(fn (Cita $c) => $c->inicio->greaterThan($inicio) ? $c->inicio : $inicio)
            ->push($inicio);

        return (int) $puntos->map(fn (Carbon $t) => $traslapadas->filter(
            fn (Cita $c) => $c->inicio->lessThanOrEqualTo($t) && $c->fin->greaterThan($t)
        )->count())->max();
    }
}
