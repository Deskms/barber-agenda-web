<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Difusion;
use App\Models\Recontacto;
use App\Services\Agenda\Llegadas;
use App\Services\Marketing\Campanas;
use Illuminate\Http\Request;

/** Avisos automáticos de huecos, "ya te toca volver" y cuánto se lleva gastado. */
class PromocionesController extends Controller
{
    public function index(Campanas $campanas, Llegadas $llegadas)
    {
        return view('promociones.index', [
            'ajustes'   => collect(array_keys(Ajuste::DEFAULTS))->mapWithKeys(fn ($k) => [$k => Ajuste::valor($k)]),
            'gasto'     => $campanas->gastoDelMes(),
            'costo'     => $campanas->costoPorMensaje(),
            'regresos'  => $campanas->lesTocaVolver(),
            'difusiones'=> Difusion::with(['cliente'])->latest()->limit(15)->get(),
            'recontactos' => Recontacto::with(['cliente', 'servicio'])->latest()->limit(15)->get(),
            'linkLibres'  => route('libres'),
            'linkLlegada' => $llegadas->url(),
        ]);
    }

    public function guardar(Request $request)
    {
        $datos = $request->validate([
            'huecos_max'          => ['required', 'integer', 'min:0', 'max:50'],
            'presupuesto_mensual' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        Ajuste::guardar('huecos_auto', $request->boolean('huecos_auto') ? '1' : '0');
        Ajuste::guardar('regreso_auto', $request->boolean('regreso_auto') ? '1' : '0');
        Ajuste::guardar('huecos_max', (string) $datos['huecos_max']);
        Ajuste::guardar('presupuesto_mensual', (string) $datos['presupuesto_mensual']);

        return back()->with('ok', 'Promociones guardadas.');
    }

    /** Nuevo QR de llegada. El impreso deja de servir (por si alguien lo fotografió). */
    public function cambiarMostrador(Llegadas $llegadas)
    {
        $llegadas->cambiarCodigo();

        return back()->with('ok', 'Código de llegada cambiado. Imprime el QR nuevo.');
    }

    /** Faltas toleradas en 90 días antes de que la clienta ya no pueda agendar sola. */
    public function guardarFaltas(Request $request)
    {
        $datos = $request->validate([
            'faltas_bloqueo' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        Ajuste::guardar('faltas_bloqueo', (string) $datos['faltas_bloqueo']);

        return back()->with('ok', 'Regla de inasistencias guardada.');
    }

    /** Regla de la tarjeta de sellos. Cambiarla no borra sellos ya ganados. */
    public function guardarSellos(Request $request)
    {
        $datos = $request->validate([
            'sellos_meta'   => ['required', 'integer', 'min:2', 'max:50'],
            'sellos_premio' => ['required', 'string', 'max:115'],
        ]);

        Ajuste::guardar('sellos_meta', (string) $datos['sellos_meta']);
        Ajuste::guardar('sellos_premio', trim($datos['sellos_premio']));

        return back()->with('ok', 'Tarjeta de sellos guardada.');
    }

    /** Manda ahora el "ya te toca volver", sin esperar a las 11:00. */
    public function enviarRegresos(Campanas $campanas)
    {
        $r = $campanas->enviarRegresos();

        return back()->with('ok', "Enviados gratis: {$r['gratis']}. Pagados: {$r['pagadas']}. Sin presupuesto: {$r['omitidas']}.");
    }
}
