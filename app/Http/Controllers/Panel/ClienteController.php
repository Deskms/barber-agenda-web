<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\EventoWhatsapp;
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

    public function show(Cliente $cliente)
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

        return view('clientes.show', compact('cliente', 'citas', 'mensajes'));
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
}
