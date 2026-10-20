@extends('layouts.app')

@section('titulo', 'Avisar espacio')

@php
    $plantilla = config('whatsapp.plantillas.espacio_liberado.body');
    $vista = str_replace(['{{1}}', '{{2}}', '{{3}}', '{{4}}'], ['Ana', $cita->servicio->nombre, $cita->inicio->translatedFormat('l j \d\e F'), $cita->inicio->format('H:i')], $plantilla);
@endphp

@section('contenido')
<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" action="{{ route('huecos.difundir', $cita) }}" class="tarjeta p-3" onsubmit="return confirm('¿Enviar el aviso a ' + this.querySelectorAll('input[name=\'clientas[]\']:checked').length + ' clientas?')">
            @csrf
            <h1 class="h5">Avisar del espacio del {{ $cita->inicio->translatedFormat('l j \d\e F') }} a las {{ $cita->inicio->format('H:i') }}</h1>
            <p class="small text-secondary">Clientas que aceptan avisos, no tienen otra cita a esa hora y no han sido avisadas de este espacio.</p>

            @forelse ($candidatas as $c)
                <div class="form-check">
                    <input class="form-check-input sel" type="checkbox" name="clientas[]" value="{{ $c->id }}" id="c{{ $c->id }}" checked>
                    <label class="form-check-label" for="c{{ $c->id }}">{{ $c->nombre ?? 'Sin nombre' }} <span class="text-secondary small">{{ \App\Support\Telefono::legible($c->wa_id) }}</span></label>
                </div>
            @empty
                <p class="text-secondary">No hay clientas elegibles.</p>
            @endforelse

            @if ($candidatas->isNotEmpty())
                <p class="mt-3 mb-2">Se enviará a <strong id="cuantas">{{ $candidatas->count() }}</strong> clientas. Costo aproximado: <strong id="costo">US${{ number_format($candidatas->count() * $costo, 2) }}</strong> <span class="small text-secondary">(plantilla de marketing, tarifa de Meta para México)</span></p>
                <button class="btn btn-acento">Enviar aviso</button>
            @endif
            <a class="btn btn-link" href="{{ route('huecos') }}">Volver</a>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="tarjeta p-3 mb-3">
            <h2 class="h6">Así le llega</h2>
            <div class="p-2 rounded" style="background:#dcf8c6">{{ $vista }}</div>
        </div>
        @if ($avisadas->isNotEmpty())
            <div class="tarjeta p-3">
                <h2 class="h6">Ya avisadas</h2>
                @foreach ($avisadas as $d)
                    <div class="small">{{ $d->cliente->nombre }} · {{ $d->estado }} · {{ $d->created_at->format('d/m H:i') }} @if($d->error)<span class="text-danger">{{ $d->error }}</span>@endif</div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const costo = @json($costo);
    const actualizar = () => {
        const n = document.querySelectorAll('.sel:checked').length;
        const a = document.getElementById('cuantas'); if (a) a.textContent = n;
        const b = document.getElementById('costo'); if (b) b.textContent = 'US$' + (n * costo).toFixed(2);
    };
    document.querySelectorAll('.sel').forEach(el => el.addEventListener('change', actualizar));
})();
</script>
@endpush
