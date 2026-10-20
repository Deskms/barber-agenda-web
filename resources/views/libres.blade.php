@extends('layouts.app')

@section('titulo', 'Espacios libres')

@section('contenido')
<div style="max-width: 620px" class="mx-auto">
    <h1 class="h3 fw-bold text-center mt-2">TuNegocio<span style="color: var(--acento)">Citas</span></h1>
    <p class="text-center text-secondary">Espacios libres. Toca una hora y apártala por WhatsApp.</p>

    @forelse ($dias as $d)
        <div class="tarjeta p-3 mb-3">
            <h2 class="h5">{{ \Illuminate\Support\Str::ucfirst($d['dia']->isToday() ? 'hoy, ' . $d['dia']->translatedFormat('j \d\e F') : $d['dia']->translatedFormat('l j \d\e F')) }}</h2>
            @foreach ($d['mapas'] as $seccion => $horas)
                <div class="small text-uppercase text-secondary mt-2 mb-1">{{ $seccion }}</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($horas as $h)
                        @php
                            $texto = 'Hola, quiero apartar el espacio de las ' . $h['hora']->format('H:i') . ' del ' . $d['dia']->translatedFormat('l j \d\e F') . ' en ' . $seccion;
                        @endphp
                        @if ($numero)
                            <a class="btn btn-outline-success" href="https://wa.me/{{ $numero }}?text={{ rawurlencode($texto) }}">{{ $h['hora']->format('H:i') }}</a>
                        @else
                            <span class="btn btn-outline-success disabled">{{ $h['hora']->format('H:i') }}</span>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>
    @empty
        <div class="tarjeta p-4 text-center text-secondary">No hay espacios libres en los próximos días.</div>
    @endforelse
</div>
@endsection
