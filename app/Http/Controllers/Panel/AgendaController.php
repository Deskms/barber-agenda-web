<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Models\Seccion;
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
            ->with(['cliente', 'servicio', 'seccion'])
            ->delDia($dia)
            ->orderBy('inicio')
            ->get();

        // Las canceladas no se pintan en el calendario (su horario ya está libre):
        // se listan en el panel lateral.
        $canceladas = $citas->where('estado', Cita::CANCELADA)->values();
        $citas      = $citas->where('estado', '!=', Cita::CANCELADA)->values();

        // Una columna por sección: cada una tiene su propia agenda y sus huecos.
        $secciones = Seccion::query()->where('activo', true)->orderBy('nombre')->get();
        $agendas   = $secciones->map(fn (Seccion $s) => ['titulo' => $s->nombre, 'citas' => $citas->where('seccion_id', $s->id)]);
        $agendas   = $agendas->values();

        $sinSeccion = $citas->whereNull('seccion_id');

        if ($secciones->isEmpty() || $sinSeccion->isNotEmpty()) {
            $agendas->push(['titulo' => $secciones->isEmpty() ? null : 'Sin sección', 'citas' => $sinSeccion]);
        }

        $agendas = $agendas->map(fn (array $a) => $a + [
            'huecos' => $horario ? $this->huecos($dia, $horario, $a['citas']) : [],
        ]);

        // Rango de horas del calendario: el horario de atención, ampliado si
        // hubiera alguna cita fuera de él; un día cerrado muestra 9 a 20.
        $desde = $horario ? $dia->copy()->setTimeFromTimeString($horario->abre) : $dia->copy()->setTime(9, 0);
        $hasta = $horario ? $dia->copy()->setTimeFromTimeString($horario->cierra) : $dia->copy()->setTime(20, 0);
        if ($citas->isNotEmpty()) {
            $primera = $citas->sortBy('inicio')->first()->inicio;
            $ultima  = $citas->sortByDesc('fin')->first()->fin;
            $desde   = $primera->lessThan($desde) ? $primera->copy() : $desde;
            $hasta   = $ultima->greaterThan($hasta) ? $ultima->copy() : $hasta;
        }
        $desde->minute(0)->second(0);
        if ($hasta->minute > 0) {
            $hasta->addHour()->minute(0);
        }

        // Para la marca "NUEVA": la primera cita que tuvo cada clienta.
        $primeras = Cita::query()
            ->whereIn('cliente_id', $citas->pluck('cliente_id')->unique())
            ->selectRaw('cliente_id, MIN(id) as primera')
            ->groupBy('cliente_id')
            ->pluck('primera', 'cliente_id');

        return view('agenda.dia', [
            'dia'        => $dia,
            'horario'    => $horario,
            'citas'      => $citas,
            'canceladas' => $canceladas,
            'agendas'    => $agendas,
            'desde'      => $desde,
            'hasta'      => $hasta,
            'primeras'   => $primeras,
            'elegida'    => (int) $request->query('cita'),
            'firma'      => $this->firma($dia, $dia),
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
