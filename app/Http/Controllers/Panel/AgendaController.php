<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Services\Agenda\DisponibilidadService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AgendaController extends Controller
{
    public function dia(Request $request, DisponibilidadService $disponibilidad)
    {
        $dia     = $this->fecha($request->query('fecha'));
        $horario = $disponibilidad->horarioDe($dia);

        $citas = Cita::query()
            ->with(['cliente', 'servicio'])
            ->delDia($dia)
            ->orderBy('inicio')
            ->get();

        return view('agenda.dia', [
            'dia'     => $dia,
            'horario' => $horario,
            'citas'   => $citas,
            'huecos'  => $horario ? $this->huecos($dia, $horario, $citas) : [],
            'firma'   => $this->firma($dia, $dia),
        ]);
    }

    public function semana(Request $request)
    {
        $inicio = $this->fecha($request->query('fecha'))->startOfWeek(Carbon::MONDAY);
        $fin    = $inicio->copy()->addDays(6)->endOfDay();

        $citas = Cita::query()
            ->with(['cliente', 'servicio'])
            ->whereBetween('inicio', [$inicio, $fin])
            ->orderBy('inicio')
            ->get()
            ->groupBy(fn (Cita $c) => $c->inicio->toDateString());

        return view('agenda.semana', [
            'inicio' => $inicio,
            'citas'  => $citas,
            'firma'  => $this->firma($inicio, $fin),
        ]);
    }

    /**
     * El panel pregunta cada 20 s si cambió algo en el rango que muestra.
     * Solo devuelve una huella: si difiere, la página se recarga sola.
     */
    public function feed(Request $request)
    {
        $desde = $this->fecha($request->query('desde'));
        $hasta = $this->fecha($request->query('hasta', $desde->toDateString()));

        return response()->json(['firma' => $this->firma($desde, $hasta)]);
    }

    private function firma(Carbon $desde, Carbon $hasta): string
    {
        $resumen = Cita::query()
            ->whereBetween('inicio', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->selectRaw('COUNT(*) as total, MAX(updated_at) as ultima')
            ->first();

        return md5($resumen->total . '|' . $resumen->ultima);
    }

    /** Intervalos libres entre citas vigentes, para pintarlos en la agenda. */
    private function huecos(Carbon $dia, HorarioAtencion $horario, Collection $citas): array
    {
        $cursor = $dia->copy()->setTimeFromTimeString($horario->abre);
        $cierre = $dia->copy()->setTimeFromTimeString($horario->cierra);
        $huecos = [];

        foreach ($citas->whereIn('estado', [Cita::CONFIRMADA, Cita::COMPLETADA])->sortBy('inicio') as $cita) {
            if ($cita->inicio->greaterThan($cursor)) {
                $huecos[] = ['inicio' => $cursor->copy(), 'fin' => $cita->inicio->copy()];
            }

            if ($cita->fin->greaterThan($cursor)) {
                $cursor = $cita->fin->copy();
            }
        }

        if ($cursor->lessThan($cierre)) {
            $huecos[] = ['inicio' => $cursor->copy(), 'fin' => $cierre];
        }

        return $huecos;
    }

    private function fecha(?string $valor): Carbon
    {
        if ($valor && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $valor)->startOfDay();
            } catch (\Throwable) {
            }
        }

        return Carbon::today();
    }
}
