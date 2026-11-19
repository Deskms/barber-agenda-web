<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\EventoWhatsapp;
use App\Services\Lealtad\TarjetaSellos;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $buscar = trim((string) $request->query('q'));

        $clientes = Cliente::query()
            ->withCount('citas')
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nombre', 'like', '%' . addcslashes($buscar, '%_\\') . '%')
                ->orWhere('wa_id', 'like', '%' . preg_replace('/\D+/', '', $buscar) . '%')))
            ->orderBy('nombre')
            ->paginate(30)
            ->withQueryString();

        return view('clientes.index', ['clientes' => $clientes, 'buscar' => $buscar]);
    }

    public function show(Cliente $cliente, TarjetaSellos $sellos)
    {
        $citas = $cliente->citas()->with('servicio')->orderByDesc('inicio')->limit(50)->get();

        // El historial sale de los eventos guardados por el webhook: solo lo
        // que escribió la clienta. Lo que responde el bot queda en el log.
        $mensajes = EventoWhatsapp::query()
            ->where('tipo', EventoWhatsapp::TIPO_MENSAJE)
            ->where('payload->from', $cliente->wa_id)
            ->latest()
            ->limit(50)
            ->get();

        return view('clientes.show', [
            'cliente'    => $cliente,
            'citas'      => $citas,
            'mensajes'   => $mensajes,
            'sellos'     => $sellos->estado($cliente),
            'tarjetaUrl' => $sellos->url($cliente),
            'canjes'     => $cliente->canjes()->latest('canjeado_en')->limit(5)->get(),
            'faltas'     => app(\App\Services\Agenda\CitaService::class)->faltasRecientes($cliente),
        ]);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'notas'  => ['nullable', 'string', 'max:500'],
        ]);

        $datos['acepta_avisos'] = $request->boolean('acepta_avisos');
        $cliente->update($datos);

        return back()->with('ok', 'Clienta actualizada.');
    }

    /** Entrega un premio de la tarjeta de sellos. */
    public function canjear(Cliente $cliente, TarjetaSellos $sellos)
    {
        if (! $sellos->canjear($cliente)) {
            return back()->withErrors(['sellos' => 'Esta clienta todavía no tiene un premio para entregar.']);
        }

        return back()->with('ok', 'Premio entregado: ' . $sellos->premio() . '.');
    }
}
