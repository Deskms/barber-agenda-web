@extends('layouts.app')

@section('titulo', 'Clientas')

@section('contenido')
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <h1 class="h4 m-0">Clientas</h1>
    <form class="ms-auto d-flex gap-2" method="get">
        @if ($estado !== 'todas')<input type="hidden" name="estado" value="{{ $estado }}">@endif
        <input class="form-control form-control-sm" name="q" value="{{ $buscar }}" placeholder="Nombre o teléfono">
        <button class="btn btn-sm btn-outline-secondary">Buscar</button>
    </form>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="btn-group btn-group-sm" role="group" aria-label="Filtrar clientas">
        @foreach (['todas' => 'Todas', 'activas' => 'Activas', 'inactivas' => 'Inactivas (' . $inactivas . ')'] as $clave => $texto)
            <a class="btn {{ $estado === $clave ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('clientes', array_filter(['estado' => $clave === 'todas' ? null : $clave, 'q' => $buscar ?: null])) }}">{{ $texto }}</a>
        @endforeach
    </div>
    <small class="text-secondary">Inactiva = sin visitas en los últimos {{ $dias }} días. Vuelve a activa sola cuando agenda.</small>
</div>

<div class="tarjeta">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 tabla-tarjetas">
            <thead><tr><th>Nombre</th><th>WhatsApp</th><th class="text-center">Citas</th><th>Avisos de espacios</th><th></th></tr></thead>
            <tbody>
            @forelse ($clientes as $c)
                <tr>
                    <td>
                        <a href="{{ route('clientes.show', $c) }}">{{ $c->nombre ?? 'Sin nombre' }}</a>
                        @if ($c->inactiva_desde)
                            <span class="badge rounded-pill text-bg-light border ms-1" title="Marcada inactiva el {{ $c->inactiva_desde->translatedFormat('j \\d\\e F') }}">Inactiva</span>
                        @endif
                    </td>
                    <td>{{ \App\Support\Telefono::legible($c->wa_id) }}</td>
                    <td class="text-center">{{ $c->citas_count }}</td>
                    <td>{!! $c->acepta_avisos ? '<span class="estado-ok">Sí</span>' : '<span class="text-secondary">No</span>' !!}</td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('citas.create', ['cliente' => $c->id]) }}">Agendar</a>
                        <form class="d-inline" method="post" action="{{ route('clientes.destroy', $c) }}"
                              onsubmit="return confirm('¿Borrar a {{ e(addslashes($c->nombre ?? 'esta clienta')) }}?\n\nSe quitan su nombre, teléfono y acceso a la app, y se cancelan sus citas próximas (sin avisar a nadie). Sus citas pasadas quedan en los reportes como «Clienta eliminada».\n\nSi vuelve a escribir, se registra como nueva.');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Borrar</button>
                        </form>
                    </td>
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
