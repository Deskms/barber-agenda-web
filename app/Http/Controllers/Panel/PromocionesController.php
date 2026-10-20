<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Difusion;
use App\Models\Recontacto;
use App\Services\Marketing\Campanas;
use Illuminate\Http\Request;

/** Avisos automáticos de huecos, "ya te toca volver" y cuánto se lleva gastado. */
class PromocionesController extends Controller
{
    public function index(Campanas $campanas)
    {
        return view('promociones.index', [
            'ajustes'   => collect(array_keys(Ajuste::DEFAULTS))->mapWithKeys(fn ($k) => [$k => Ajuste::valor($k)]),
            'gasto'     => $campanas->gastoDelMes(),
            'costo'     => $campanas->costoPorMensaje(),
            'regresos'  => $campanas->lesTocaVolver(),
            'difusiones'=> Difusion::with(['cliente'])->latest()->limit(15)->get(),
            'recontactos' => Recontacto::with(['cliente', 'servicio'])->latest()->limit(15)->get(),
            'linkLibres'  => route('libres'),
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

    /** Manda ahora el "ya te toca volver", sin esperar a las 11:00. */
    public function enviarRegresos(Campanas $campanas)
    {
        $r = $campanas->enviarRegresos();

        return back()->with('ok', "Enviados gratis: {$r['gratis']}. Pagados: {$r['pagadas']}. Sin presupuesto: {$r['omitidas']}.");
    }
}
