<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Difusion;
use App\Models\HorarioAtencion;
use App\Models\Seccion;
use App\Services\Marketing\Campanas;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Indicadores del negocio calculados solo con datos de la base.
 *
 * Los montos usan el precio actual de cada servicio (las citas no guardan el
 * precio cobrado). Por eso se presentan como estimados.
 */
class TableroController extends Controller
{
    public function __invoke(Request $request, Campanas $campanas)
    {
        $datos = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
        ]);

        $desde = isset($datos['mes'])
            ? Carbon::createFromFormat('Y-m', $datos['mes'])->startOfMonth()
            : Carbon::now()->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();

        $citas = Cita::query()
            ->with(['servicio', 'seccion', 'cliente'])
            ->whereBetween('inicio', [$desde, $hasta])
            ->get();

        $porEstado = fn (string $estado) => $citas->where('estado', $estado);
        $suma = fn (Collection $c) => round($c->sum(fn (Cita $x) => (float) ($x->servicio?->precio ?? 0)), 2);

        $recuperadas = $citas->whereNotNull('difusion_id');

        $hoy = Cita::query()
            ->whereBetween('inicio', [Carbon::today(), Carbon::today()->endOfDay()])
            ->get();

        return view('tablero.index', [
            'mes'   => $desde,
            'hoy'   => [
                'citas'       => $hoy->whereIn('estado', [Cita::CONFIRMADA, Cita::COMPLETADA])->count(),
                'llegadas'    => $hoy->whereNotNull('llegada_en')->count(),
                'completadas' => $hoy->where('estado', Cita::COMPLETADA)->count(),
            ],
            'conteo' => [
                'completadas'  => $porEstado(Cita::COMPLETADA)->count(),
                'confirmadas'  => $porEstado(Cita::CONFIRMADA)->count(),
                'canceladas'   => $porEstado(Cita::CANCELADA)->count(),
                'inasistencias'=> $porEstado(Cita::NO_ASISTIO)->count(),
            ],
            'ingresos' => [
                // Confirmadas que todavía no se atienden: dinero que puede entrar.
                'potencial' => $suma($porEstado(Cita::CONFIRMADA)),
                // Atendidas: dinero que efectivamente entró (a precio de lista).
                'obtenido'  => $suma($porEstado(Cita::COMPLETADA)),
                // Cancelaciones y faltas: dinero que se fue de la agenda.
                'perdido'   => $suma($porEstado(Cita::CANCELADA)->merge($porEstado(Cita::NO_ASISTIO))),
            ],
            'recuperacion' => [
                'avisos'      => Difusion::whereBetween('created_at', [$desde, $hasta])->whereIn('estado', ['enviada', 'aceptada', 'vencida'])->count(),
                'espacios'    => $recuperadas->whereIn('estado', [Cita::CONFIRMADA, Cita::COMPLETADA])->count(),
                'confirmado'  => $suma($recuperadas->where('estado', Cita::CONFIRMADA)),
                'obtenido'    => $suma($recuperadas->where('estado', Cita::COMPLETADA)),
                'costo'       => $desde->isSameMonth(Carbon::now()) ? $campanas->gastoDelMes() : null,
            ],
            'ocupacion' => $this->ocupacion($citas, $desde, $hasta),
            'servicios' => $citas->whereIn('estado', [Cita::CONFIRMADA, Cita::COMPLETADA])
                ->groupBy(fn (Cita $c) => $c->servicio?->nombre ?? 'Sin servicio')
                ->map->count()
                ->sortDesc()
                ->take(5),
            'faltistas' => $porEstado(Cita::NO_ASISTIO)
                ->groupBy('cliente_id')
                ->map(fn (Collection $c) => ['cliente' => $c->first()->cliente, 'faltas' => $c->count()])
                ->sortByDesc('faltas')
                ->take(5)
                ->values(),
        ]);
    }

    /**
     * Minutos ocupados entre minutos abiertos, por sección. Cada sección tiene
     * su propia agenda, así que todas comparten el mismo horario de atención.
     *
     * @return Collection<int, array{seccion: string, porcentaje: int, horas: float}>
     */
    private function ocupacion(Collection $citas, Carbon $desde, Carbon $hasta): Collection
    {
        $horario = HorarioAtencion::query()->where('activo', true)->get()->keyBy('dia_semana');

        $abiertos = 0;
        for ($dia = $desde->copy(); $dia->lte($hasta); $dia->addDay()) {
            $h = $horario->get($dia->dayOfWeek);
            if ($h) {
                $abiertos += Carbon::parse($h->abre)->diffInMinutes(Carbon::parse($h->cierra));
            }
        }

        return Seccion::query()->orderBy('nombre')->get()->map(function (Seccion $s) use ($citas, $abiertos) {
            $minutos = $citas->where('seccion_id', $s->id)
                ->whereIn('estado', [Cita::CONFIRMADA, Cita::COMPLETADA])
                ->sum(fn (Cita $c) => $c->inicio->diffInMinutes($c->fin));

            return [
                'seccion'    => $s->nombre,
                'porcentaje' => $abiertos > 0 ? (int) round(100 * $minutos / $abiertos) : 0,
                'horas'      => round($minutos / 60, 1),
            ];
        });
    }
}
