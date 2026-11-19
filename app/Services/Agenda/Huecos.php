<?php

namespace App\Services\Agenda;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Difusion;
use App\Models\HorarioAtencion;
use App\Models\Servicio;
use App\Services\Marketing\Campanas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ofertas de huecos liberados: apartado temporal, vencimiento y ofertas
 * vigentes para la app.
 *
 * Aceptar una oferta nunca reserva por sí solo: la cita se crea solo cuando
 * CitaService la valida dentro de su transacción.
 */
class Huecos
{
    /** Minutos que se le guarda el lugar a quien dijo "lo quiero". */
    public const MINUTOS_APARTADO = 10;

    public const ENVIADA  = 'enviada';
    public const ACEPTADA = 'aceptada';
    public const VENCIDA  = 'vencida';

    /**
     * Aparta el hueco para quien respondió primero. Devuelve false si otra
     * clienta ya lo tiene apartado.
     */
    public function apartar(Difusion $difusion): bool
    {
        return DB::transaction(function () use ($difusion) {
            // Mismo candado que CitaService: serializa por día de la semana.
            HorarioAtencion::query()
                ->where('dia_semana', $difusion->inicio->dayOfWeek)
                ->lockForUpdate()
                ->first();

            if ($this->apartadoPorOtra($difusion->cliente_id, $difusion->seccion_id, $difusion->inicio, $difusion->fin)) {
                return false;
            }

            $difusion->forceFill(['apartado_hasta' => Carbon::now()->addMinutes(self::MINUTOS_APARTADO)])->save();

            return true;
        });
    }

    /** ¿Otra clienta tiene apartado un hueco que choca con este intervalo? */
    public function apartadoPorOtra(?int $clienteId, ?int $seccionId, Carbon $inicio, Carbon $fin): bool
    {
        return Difusion::query()
            ->where('apartado_hasta', '>', Carbon::now())
            ->when($clienteId, fn ($q) => $q->where('cliente_id', '!=', $clienteId))
            ->where(fn ($q) => $seccionId === null
                ? $q->whereNull('seccion_id')
                : $q->where('seccion_id', $seccionId)->orWhereNull('seccion_id'))
            ->where('inicio', '<', $fin)
            ->where('fin', '>', $inicio)
            ->exists();
    }

    /**
     * Si la cita llena un hueco que se le ofreció a esta clienta, la liga al
     * aviso. Así se cuentan espacios e ingresos recuperados con datos reales.
     */
    public function registrarRecuperacion(Cita $cita): void
    {
        $difusion = Difusion::query()
            ->where('cliente_id', $cita->cliente_id)
            ->whereIn('estado', [self::ENVIADA, 'pendiente'])
            ->where('created_at', '>=', Carbon::now()->subDays(2))
            ->where('inicio', '<=', $cita->inicio)
            ->where('fin', '>', $cita->inicio)
            ->where(fn ($q) => $q->where('seccion_id', $cita->seccion_id)->orWhereNull('seccion_id'))
            ->latest()
            ->first();

        if (! $difusion) {
            return;
        }

        $difusion->forceFill(['estado' => self::ACEPTADA, 'apartado_hasta' => null])->save();
        $cita->forceFill(['difusion_id' => $difusion->id])->save();
    }

    /**
     * El servicio con el que esta clienta puede tomar el hueco ahora mismo,
     * o null si ya no cabe ninguno (alguien lo ocupó o lo tiene apartado).
     *
     * Primero se prueba el servicio que se le anunció en el aviso: el de la
     * cita cancelada si lo mandó la dueña desde el panel, o su servicio
     * habitual si fue automático. Si ese no cabe, se prueba el otro. Antes se
     * usaba solo el habitual, y si era más largo que el hueco la oferta
     * desaparecía aunque el aviso dijera que había lugar.
     */
    public function servicioPara(Cliente $cliente, Difusion $difusion): ?Servicio
    {
        $disponibilidad = app(DisponibilidadService::class);
        $habitual       = app(Campanas::class)->servicioHabitual($cliente, $difusion->seccion_id);
        $avisado        = $difusion->cita?->servicio;

        $candidatos = collect($difusion->automatica ? [$habitual, $avisado] : [$avisado, $habitual])
            ->filter(fn (?Servicio $s) => $s !== null && $s->activo)
            ->unique('id');

        foreach ($candidatos as $servicio) {
            $fin = $difusion->inicio->copy()->addMinutes($servicio->duracion_minutos);

            if ($disponibilidad->estaLibre($difusion->inicio, $fin, seccionId: $servicio->seccion_id)
                && ! $this->apartadoPorOtra($cliente->id, $servicio->seccion_id, $difusion->inicio, $fin)) {
                return $servicio;
            }
        }

        return null;
    }

    /**
     * Ofertas que esta clienta todavía puede tomar desde la app: avisos
     * enviados en las últimas 48 h, futuros, libres y sin apartado ajeno.
     *
     * @return Collection<int, array{difusion: Difusion, servicio: Servicio}>
     */
    public function ofertasPara(Cliente $cliente): Collection
    {
        return Difusion::query()
            ->with('cita.servicio')
            ->where('cliente_id', $cliente->id)
            ->where('estado', self::ENVIADA)
            ->where('created_at', '>=', Carbon::now()->subHours(48))
            ->where('inicio', '>', Carbon::now())
            ->orderBy('inicio')
            ->limit(10)
            ->get()
            ->map(function (Difusion $d) use ($cliente) {
                $servicio = $this->servicioPara($cliente, $d);

                return $servicio ? ['difusion' => $d, 'servicio' => $servicio] : null;
            })
            ->filter()
            ->values();
    }

    /** Marca como vencidos los avisos de huecos que ya pasaron. */
    public function vencer(): int
    {
        return Difusion::query()
            ->whereIn('estado', [self::ENVIADA, 'pendiente'])
            ->where('inicio', '<=', Carbon::now())
            ->update(['estado' => self::VENCIDA, 'apartado_hasta' => null]);
    }
}
