@extends('layouts.app')

@section('titulo', 'Espacios libres')

@section('contenido')
<h1 class="h4 mb-1">Espacios que dejaron cancelaciones</h1>
<p class="text-secondary">Puedes avisar a las clientas que aceptan avisos. Nada se envía sin que tú lo confirmes.</p>

@forelse ($huecos as $h)
    <div class="tarjeta p-3 mb-2 d-flex flex-wrap gap-2 align-items-center">
        <div>
            <strong>{{ \Illuminate\Support\Str::ucfirst($h->inicio->translatedFormat('l j \d\e F')) }}, {{ $h->inicio->format('H:i') }}–{{ $h->fin->format('H:i') }}</strong>
            <div class="small text-secondary">Era {{ $h->servicio->nombre }} de {{ $h->cliente->nombre }} · avisadas: {{ $h->difusiones_count }}</div>
        </div>
        <div class="ms-auto d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('citas.create', ['fecha' => $h->inicio->toDateString(), 'hora' => $h->inicio->format('H:i')]) }}">Agendar a alguien</a>
            <a class="btn btn-sm btn-acento" href="{{ route('huecos.show', $h) }}">Avisar por WhatsApp</a>
        </div>
    </div>
@empty
    <div class="tarjeta p-4 text-center text-secondary">No hay espacios liberados próximos.</div>
@endforelse
@endsection
