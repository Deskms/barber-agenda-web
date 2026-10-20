<?php

namespace App\Services\Agenda;

use App\Events\EspacioLiberado;
use App\Exceptions\HorarioNoDisponibleException;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\HorarioAtencion;
use App\Models\Servicio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Crear, cancelar y reprogramar citas.
 *
 * Toda escritura sobre la agenda pasa por aquí. La IA nunca escribe en la base
 * de datos: a lo mucho llama a estos métodos, y estos vuelven a validar.
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
    ): Cita {
        return DB::transaction(function () use ($cliente, $servicio, $inicio, $origen) {
            $fin = $inicio->copy()->addMinutes($servicio->duracion_minutos);

            if (! $this->disponibilidad->dentroDelHorario($inicio, $fin)) {
                throw new HorarioNoDisponibleException(
                    'Ese horario queda fuera del horario de atención.'
                );
            }

            if ($inicio->isPast()) {
                throw new HorarioNoDisponibleException('Ese horario ya pasó.');
            }

            $this->bloquearDia($inicio);

            // La revalidación ocurre DENTRO de la transacción, no antes.
            // Entre que se ofrece un horario y la clienta responde "sí" pueden
            // pasar minutos, y alguien más pudo haberlo tomado.
            // Cada sección tiene su propia agenda: solo choca con citas de la misma.
            $ocupado = Cita::query()
                ->vigentes()
                ->deLaSeccion($servicio->seccion_id)
                ->queChocanCon($inicio, $fin)
                ->lockForUpdate()
                ->exists();

            if ($ocupado) {
                throw new HorarioNoDisponibleException(
                    'Ese espacio acaba de ser ocupado.'
                );
            }

            // Aunque haya lugar en otra sección, la misma clienta no puede
            // estar en dos sillas a la vez.
            $yaTieneOtra = $cliente->citas()
                ->vigentes()
                ->queChocanCon($inicio, $fin)
                ->exists();

            if ($yaTieneOtra) {
                throw new HorarioNoDisponibleException(
                    'Ya tienes otra cita a esa hora.'
                );
            }

            return Cita::create([
                'cliente_id'  => $cliente->id,
                'servicio_id' => $servicio->id,
                'seccion_id'  => $servicio->seccion_id,
                'inicio'      => $inicio,
                'fin'         => $fin,
                'estado'      => Cita::CONFIRMADA,
                'origen'      => $origen,
            ]);
        });
    }

    /**
     * Cancela la cita y libera su intervalo.
     * Es idempotente: cancelar dos veces no dispara dos avisos.
     */
    public function cancelar(Cita $cita): Cita
    {
        if ($cita->estaCancelada()) {
            return $cita;
        }

        $cita->forceFill([
            'estado'       => Cita::CANCELADA,
            'cancelada_en' => Carbon::now(),
        ])->save();

        // El intervalo ya quedó libre por el scope vigentes().
        // El evento solo avisa al resto del sistema que hay un hueco que vender.
        EspacioLiberado::dispatch($cita->fresh(['servicio', 'cliente']));

        return $cita;
    }

    /**
     * @throws HorarioNoDisponibleException
     */
    public function reprogramar(Cita $cita, Carbon $nuevoInicio): Cita
    {
        return DB::transaction(function () use ($cita, $nuevoInicio) {
            $nuevoFin = $nuevoInicio->copy()->addMinutes($cita->servicio->duracion_minutos);

            if ($nuevoInicio->isPast()) {
                throw new HorarioNoDisponibleException('Ese horario ya pasó.');
            }

            $this->bloquearDia($nuevoInicio);

            if (! $this->disponibilidad->estaLibre($nuevoInicio, $nuevoFin, $cita->id, $cita->seccion_id)) {
                throw new HorarioNoDisponibleException(
                    'Ese horario no está disponible.'
                );
            }

            $anterior = clone $cita;

            $cita->forceFill([
                'inicio'                  => $nuevoInicio,
                'fin'                     => $nuevoFin,
                'recordatorio_enviado_en' => null,
            ])->save();

            // El hueco que deja la cita anterior también es vendible.
            EspacioLiberado::dispatch($anterior);

            return $cita;
        });
    }

    /**
     * Serializa las reservas del mismo día de la semana.
     *
     * lockForUpdate() sobre citas que chocan no basta: si todavía no hay
     * ninguna cita en ese horario, no hay fila que bloquear y dos
     * transacciones simultáneas pasan la revisión. Bloquear la fila del
     * horario de atención hace que la segunda espere a que la primera
     * termine, y entonces sí ve la cita recién creada.
     */
    private function bloquearDia(Carbon $inicio): void
    {
        HorarioAtencion::query()
            ->where('dia_semana', $inicio->dayOfWeek)
            ->lockForUpdate()
            ->first();
    }
}
