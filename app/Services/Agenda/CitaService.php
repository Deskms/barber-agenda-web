<?php

namespace App\Services\Agenda;

use App\Events\EspacioLiberado;
use App\Exceptions\CitaNoModificableException;
use App\Exceptions\HorarioNoDisponibleException;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\HorarioAtencion;
use App\Models\Hueco;
use App\Models\Servicio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Crear, cancelar, reprogramar y cerrar citas.
 *
 * Toda escritura sobre la agenda pasa por aquí, venga del panel, de la web,
 * de WhatsApp o de la app. Ningún canal escribe citas por su cuenta.
 */
class CitaService
{
    public function __construct(
        private readonly DisponibilidadService $disponibilidad,
    ) {}

    /**
     * @throws HorarioNoDisponibleException
     */
    public function agendar(
        Cliente $cliente,
        Servicio $servicio,
        Carbon $inicio,
        string $origen = Cita::ORIGEN_WHATSAPP,
        ?string $notas = null,
    ): Cita {
        return DB::transaction(function () use ($cliente, $servicio, $inicio, $origen, $notas) {
            $this->bloquearDia($inicio);

            // La revalidación ocurre DENTRO de la transacción, no antes.
            // Entre que se ofrece un horario y la clienta confirma pueden
            // pasar minutos, y alguien más pudo haberlo tomado.
            $resultado = $this->disponibilidad->evaluar($servicio, $inicio);

            if (! $resultado->libre) {
                throw new HorarioNoDisponibleException($resultado->mensaje(), $resultado->motivo);
            }

            $plan = $this->disponibilidad->planificar($servicio, $inicio);

            $cita = Cita::create([
                'cliente_id' => $cliente->id,
                'servicio_id' => $servicio->id,
                'inicio' => $inicio,
                'fin' => $plan->last()['fin'],
                'estado' => Cita::CONFIRMADA,
                'origen' => $origen,
                'notas' => $notas,
            ]);

            $this->guardarBloques($cita, $plan);

            return $cita->load(['bloques', 'servicio', 'cliente']);
        });
    }

    /**
     * Cancela la cita y libera su intervalo.
     * Es idempotente: cancelar dos veces no genera dos espacios liberados.
     */
    public function cancelar(Cita $cita): Cita
    {
        if ($cita->estaCancelada()) {
            return $cita;
        }

        if (! $cita->estaConfirmada()) {
            throw new CitaNoModificableException('Solo se pueden cancelar citas confirmadas.');
        }

        $cita->forceFill([
            'estado' => Cita::CANCELADA,
            'cancelada_en' => Carbon::now(),
        ])->save();

        // El intervalo ya quedó libre por el scope vigentes().
        // El evento avisa al resto del sistema que hay un hueco que vender.
        EspacioLiberado::dispatch($cita->fresh(['servicio', 'cliente']), null, null, Hueco::MOTIVO_CANCELACION);

        return $cita;
    }

    /**
     * @throws HorarioNoDisponibleException
     */
    public function reprogramar(Cita $cita, Carbon $nuevoInicio): Cita
    {
        if (! $cita->estaConfirmada()) {
            throw new CitaNoModificableException('Solo se pueden reprogramar citas confirmadas.');
        }

        if ($cita->inicio->lessThan(Carbon::now())) {
            throw new CitaNoModificableException('La cita ya empezó o ya pasó; no se puede reprogramar.');
        }

        return DB::transaction(function () use ($cita, $nuevoInicio) {
            $this->bloquearDia($nuevoInicio);

            $resultado = $this->disponibilidad->evaluar($cita->servicio, $nuevoInicio, $cita->id);

            if (! $resultado->libre) {
                throw new HorarioNoDisponibleException($resultado->mensaje(), $resultado->motivo);
            }

            $anteriorInicio = $cita->inicio->copy();
            $anteriorFin = $cita->fin->copy();
            $plan = $this->disponibilidad->planificar($cita->servicio, $nuevoInicio);

            $cita->forceFill([
                'inicio' => $nuevoInicio,
                'fin' => $plan->last()['fin'],
                'reprogramada_en' => Carbon::now(),
            ])->save();

            $cita->bloques()->delete();
            $this->guardarBloques($cita, $plan);

            // El hueco que deja el horario anterior también es vendible.
            EspacioLiberado::dispatch($cita, $anteriorInicio, $anteriorFin, Hueco::MOTIVO_REPROGRAMACION);

            return $cita->load(['bloques', 'servicio', 'cliente']);
        });
    }

    /** Registra si la clienta fue atendida o no llegó. Una cita completada cuenta como visita. */
    public function registrarAtencion(Cita $cita, string $estado): Cita
    {
        if (! in_array($estado, [Cita::COMPLETADA, Cita::NO_ASISTIO], true)) {
            throw new CitaNoModificableException('Estado no válido para registrar la atención.');
        }

        if (! $cita->estaConfirmada()) {
            throw new CitaNoModificableException('Solo se registra la atención de citas confirmadas.');
        }

        if ($cita->inicio->greaterThan(Carbon::now())) {
            throw new CitaNoModificableException('La cita todavía no empieza.');
        }

        $cita->forceFill(['estado' => $estado, 'atendida_en' => Carbon::now()])->save();

        return $cita->load(['bloques', 'servicio', 'cliente']);
    }

    /**
     * Serializa las reservas del mismo día de la semana: dos solicitudes
     * simultáneas esperan turno y la segunda ve la cita de la primera.
     * En PostgreSQL es un SELECT ... FOR UPDATE; en SQLite las escrituras ya son seriales.
     */
    private function bloquearDia(Carbon $inicio): void
    {
        HorarioAtencion::query()
            ->where('dia_semana', $inicio->dayOfWeek)
            ->lockForUpdate()
            ->get();
    }

    private function guardarBloques(Cita $cita, Collection $plan): void
    {
        foreach ($plan as $bloque) {
            $cita->bloques()->create([
                'orden' => $bloque['orden'],
                'nombre' => $bloque['nombre'],
                'inicio' => $bloque['inicio'],
                'fin' => $bloque['fin'],
                'ocupa_estilista' => $bloque['ocupa_estilista'],
            ]);
        }
    }
}
