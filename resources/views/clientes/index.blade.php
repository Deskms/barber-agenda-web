@extends('layouts.app')

@section('titulo', 'Clientas')

@section('contenido')
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <h1 class="h4 m-0">Clientas</h1>
    <form class="ms-auto d-flex gap-2" method="get">
        <input class="form-control form-control-sm" name="q" value="{{ $buscar }}" placeholder="Nombre o teléfono">
        <button class="btn btn-sm btn-outline-secondary">Buscar</button>
    </form>
</div>

<div class="tarjeta">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Nombre</th><th>WhatsApp</th><th class="text-center">Citas</th><th>Avisos de espacios</th><th></th></tr></thead>
            <tbody>
            @forelse ($clientes as $c)
                <tr>
                    <td><a href="{{ route('clientes.show', $c) }}">{{ $c->nombre ?? 'Sin nombre' }}</a></td>
                    <td>{{ \App\Support\Telefono::legible($c->wa_id) }}</td>
                    <td class="text-center">{{ $c->citas_count }}</td>
                    <td>{!! $c->acepta_avisos ? '<span class="estado-ok">Sí</span>' : '<span class="text-secondary">No</span>' !!}</td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('citas.create', ['cliente' => $c->id]) }}">Agendar</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-secondary p-4">Todavía no hay clientas. Se registran solas al escribir por WhatsApp.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $clientes->links('pagination::bootstrap-5') }}</div>
@endsection
