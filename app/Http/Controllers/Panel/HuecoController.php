<?php

namespace App\Http\Controllers\Panel;

use App\Events\EspacioLiberado;
use App\Http\Controllers\Controller;
use App\Listeners\BuscarClientasParaEspacioLiberado;
use App\Models\Cita;
use App\Models\Cliente;
use App\Services\Agenda\DisponibilidadService;
use App\Services\WhatsApp\Avisos;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Huecos que dejaron las cancelaciones. La difusión NUNCA es automática:
 * la dueña ve a quién se enviaría, cuánto cuesta, y decide.
 */
class HuecoController extends Controller
{
    /** Costo aproximado de una plantilla de marketing en México (USD). Ajustable en .env. */
    private function costoUnitario(): float
    {
        return (float) config('whatsapp.costo_marketing_usd');
    }

    public function index(DisponibilidadService $disponibilidad)
    {
        $huecos = Cita::query()
            ->with(['servicio', 'cliente'])
            ->withCount('difusiones')
            ->where('estado', Cita::CANCELADA)
            ->where('inicio', '>', Carbon::now())
            ->orderBy('inicio')
            ->limit(30)
            ->get()
            // Si alguien ya ocupó ese horario, ya no es un hueco que vender.
            ->filter(fn (Cita $c) => $disponibilidad->estaLibre($c->inicio, $c->fin, seccionId: $c->seccion_id));

        return view('huecos.index', ['huecos' => $huecos]);
    }

    public function show(Cita $cita, BuscarClientasParaEspacioLiberado $buscador)
    {
        abort_unless($cita->estaCancelada(), 404);

        $candidatas = $this->candidatas($cita, $buscador);

        return view('huecos.show', [
            'cita'       => $cita->load('servicio'),
            'candidatas' => $candidatas,
            'costo'      => $this->costoUnitario(),
            'avisadas'   => $cita->difusiones()->with('cliente')->get(),
        ]);
    }

    public function difundir(Request $request, Cita $cita, BuscarClientasParaEspacioLiberado $buscador, Avisos $avisos, DisponibilidadService $disponibilidad)
    {
        abort_unless($cita->estaCancelada(), 404);

        $datos = $request->validate(['clientas' => ['required', 'array', 'min:1'], 'clientas.*' => ['integer']]);

        if (! $disponibilidad->estaLibre($cita->inicio, $cita->fin, seccionId: $cita->seccion_id)) {
            return redirect()->route('huecos')->withErrors(['hueco' => 'Ese horario ya se ocupó; no se envió nada.']);
        }

        // Solo se envía a quien de verdad es elegible, aunque el formulario traiga otros ids.
        $elegidas = $this->candidatas($cita, $buscador)->whereIn('id', $datos['clientas']);

        $r = $avisos->difundirHueco($cita->load('servicio'), $cita->inicio, $elegidas);

        return redirect()->route('huecos.show', $cita)
            ->with('ok', "Enviadas: {$r['enviadas']}. Fallidas: {$r['fallidas']}. Ya avisadas antes: {$r['omitidas']}.");
    }

    private function candidatas(Cita $cita, BuscarClientasParaEspacioLiberado $buscador)
    {
        $yaAvisadas = $cita->difusiones()->pluck('cliente_id');

        return $buscador->candidatas(new EspacioLiberado($cita))
            ->reject(fn (Cliente $c) => $yaAvisadas->contains($c->id))
            ->values();
    }
}
