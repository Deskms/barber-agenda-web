@extends('layouts.app')

@section('titulo', 'WhatsApp')

@section('contenido')
<h1 class="h4 mb-3">Conexión con WhatsApp</h1>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="tarjeta p-3 mb-3">
            <h2 class="h6">Configuración</h2>
            @foreach ($config as $nombre => $listo)
                <div class="d-flex justify-content-between border-bottom py-1">
                    <span>{{ $nombre }}</span>
                    {!! $listo ? '<span class="estado-ok">✓ listo</span>' : '<span class="estado-falta">falta</span>' !!}
                </div>
            @endforeach
            <p class="small text-secondary mt-2 mb-1">URL del webhook para Meta:</p>
            <code class="small d-block text-break">{{ $webhookUrl }}</code>
            <p class="small text-secondary mt-2 mb-0">Campos a suscribir: <code>messages</code>.</p>
        </div>

        <form method="post" action="{{ route('whatsapp.prueba') }}" class="tarjeta p-3">
            @csrf
            <h2 class="h6">Mensaje de prueba</h2>
            <p class="small text-secondary">Envía la plantilla <code>hello_world</code>. Con el número de prueba de Meta, el destino debe estar en la lista de destinatarios autorizados.</p>
            <div class="d-flex gap-2">
                <input class="form-control" name="telefono" placeholder="33 1234 5678" inputmode="tel" required>
                <button class="btn btn-outline-secondary">Enviar</button>
            </div>
        </form>
    </div>

    <div class="col-lg-7">
        <div class="tarjeta p-3">
            <h2 class="h6">Plantillas</h2>
            @if ($error)
                <div class="alert alert-warning small">Meta respondió: {{ $error }}</div>
            @elseif ($enMeta === null)
                <p class="small text-secondary">Configura el token y el WABA ID para ver el estado en Meta.</p>
            @endif
            @foreach ($catalogo as $clave => $p)
                @php $meta = $enMeta?->get($clave); @endphp
                <div class="border-bottom py-2">
                    <div class="d-flex justify-content-between">
                        <strong>{{ $clave }}</strong>
                        <span class="small">{{ $p['category'] }} · {{ $meta['status'] ?? ($enMeta ? 'no enviada' : '—') }}</span>
                    </div>
                    <div class="small text-secondary">{{ $p['body'] }}</div>
                </div>
            @endforeach
            <p class="small text-secondary mt-2 mb-0">Para mandarlas a revisión: <code>php artisan whatsapp:plantillas --crear</code></p>
        </div>
    </div>
</div>
@endsection
