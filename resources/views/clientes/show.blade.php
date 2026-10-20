@extends('layouts.app')

@section('titulo', $cliente->nombre ?? 'Clienta')

@section('contenido')
<div class="row g-3">
    <div class="col-lg-5">
        <form method="post" action="{{ route('clientes.update', $cliente) }}" class="tarjeta p-3">
            @csrf
            @method('put')
            <h1 class="h5">{{ $cliente->nombre ?? 'Sin nombre' }}</h1>
            <p class="text-secondary small">WhatsApp {{ \App\Support\Telefono::legible($cliente->wa_id) }} · desde {{ $cliente->created_at->translatedFormat('j M Y') }}</p>
            <div class="mb-2"><label class="form-label small">Nombre</label><input class="form-control" name="nombre" value="{{ $cliente->nombre }}" required maxlength="120"></div>
            <div class="mb-2"><label class="form-label small">Notas</label><textarea class="form-control" name="notas" rows="2" maxlength="500">{{ $cliente->notas }}</textarea></div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="acepta_avisos" value="1" id="avisos" @checked($cliente->acepta_avisos)>
                <label class="form-check-label" for="avisos">Acepta avisos de espacios libres</label>
            </div>
            <button class="btn btn-outline-secondary btn-sm">Guardar</button>
            <a class="btn btn-acento btn-sm" href="{{ route('citas.create', ['cliente' => $cliente->id]) }}">Agendar cita</a>
        </form>

        <div class="tarjeta p-3 mt-3">
            <h2 class="h6">Lo que ha escrito por WhatsApp</h2>
            @forelse ($mensajes as $m)
                <div class="small border-bottom py-1">
                    <span class="text-secondary">{{ $m->created_at->format('d/m H:i') }}</span>
                    {{ data_get($m->payload, 'text.body') ?? data_get($m->payload, 'interactive.button_reply.title') ?? data_get($m->payload, 'interactive.list_reply.title') ?? '[' . data_get($m->payload, 'type') . ']' }}
                </div>
            @empty
                <p class="small text-secondary m-0">Sin mensajes.</p>
            @endforelse
        </div>
    </div>
    <div class="col-lg-7">
        <div class="tarjeta p-3">
            <h2 class="h6">Citas</h2>
            <table class="table table-sm mb-0">
                <tbody>
                @forelse ($citas as $c)
                    <tr>
                        <td><a href="{{ route('agenda', ['fecha' => $c->inicio->toDateString()]) }}">{{ $c->inicio->translatedFormat('D j M Y, H:i') }}</a></td>
                        <td>{{ $c->servicio->nombre }}</td>
                        <td>{{ $c->estado }}</td>
                        <td><span class="origen {{ $c->origen }}">{{ $c->origen }}</span></td>
                    </tr>
                @empty
                    <tr><td class="text-secondary">Sin citas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
