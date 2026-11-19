@extends('layouts.app')

@section('titulo', $cliente->nombre ?? 'Clienta')

@section('contenido')
<div class="row g-3">
    <div class="col-lg-5">
        <form method="post" action="{{ route('clientes.update', $cliente) }}" class="tarjeta p-3">
            @csrf
            @method('put')
            <h1 class="h5">{{ $cliente->nombre ?? 'Sin nombre' }}</h1>
            <p class="text-secondary small">WhatsApp {{ \App\Support\Telefono::legible($cliente->wa_id) }} · desde {{ $cliente->created_at->translatedFormat('j M Y') }}
                · <span class="{{ $faltas > 0 ? 'text-danger fw-semibold' : '' }}">{{ $faltas }} {{ $faltas === 1 ? 'falta' : 'faltas' }} en 90 días</span></p>
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
            <h2 class="h6">Tarjeta de sellos</h2>
            <p class="mb-1"><strong>{{ $sellos['sellos'] }} de {{ $sellos['meta'] }}</strong> sellos · {{ $sellos['ganados'] }} citas completadas en total.</p>
            @if ($sellos['premios'] > 0)
                <form method="post" action="{{ route('clientes.canjear', $cliente) }}" class="mb-2" onsubmit="return confirm('¿Entregar {{ $sellos['premio'] }}?')">
                    @csrf
                    <button class="btn btn-acento btn-sm">Entregar premio ({{ $sellos['premios'] }} disponible{{ $sellos['premios'] === 1 ? '' : 's' }})</button>
                </form>
            @else
                <p class="small text-secondary">Le faltan {{ $sellos['faltan'] }} para: {{ $sellos['premio'] }}.</p>
            @endif
            <div class="d-flex gap-3 align-items-center">
                <div id="qr-tarjeta" class="flex-shrink-0"></div>
                <div class="small">
                    <p class="text-secondary mb-1">Que la clienta escanee el código para abrir su tarjeta.</p>
                    <a href="{{ $tarjetaUrl }}" target="_blank" rel="noopener" class="text-break">Abrir tarjeta</a>
                </div>
            </div>
            @if ($canjes->isNotEmpty())
                <div class="small text-secondary mt-2">Últimos premios: {{ $canjes->map(fn ($c) => $c->canjeado_en->format('d/m/Y'))->join(', ') }}</div>
            @endif
        </div>

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

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    if (window.QRCode) {
        new QRCode(document.getElementById('qr-tarjeta'), { text: @json($tarjetaUrl), width: 112, height: 112 });
    }
</script>
@endpush
