@extends('layouts.app')

@section('titulo', 'Tarjeta de sellos')

@section('contenido')
<style>
    .sellos { display: grid; grid-template-columns: repeat(5, 1fr); gap: .6rem; }
    .sello { aspect-ratio: 1; border-radius: 50%; border: 2px dashed #d1d1d6; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #8e8e93; }
    .sello.lleno { border-style: solid; border-color: var(--ballena, #1e3a5f); background: var(--ballena, #1e3a5f); color: #fff; }
</style>

<div style="max-width: 420px" class="mx-auto">
    <h1 class="h4 fw-bold text-center mt-2">{{ config('whatsapp.negocio.nombre') }}</h1>
    <p class="text-center text-secondary mb-3">Tarjeta de sellos{{ $nombre ? ' de ' . $nombre : '' }}</p>

    <div class="tarjeta p-3">
        <div class="sellos">
            @for ($i = 1; $i <= $estado['meta']; $i++)
                <div class="sello {{ $i <= $estado['sellos'] ? 'lleno' : '' }}">{{ $i <= $estado['sellos'] ? '✓' : $i }}</div>
            @endfor
        </div>

        <p class="text-center mt-3 mb-1">
            <strong>{{ $estado['sellos'] }} de {{ $estado['meta'] }}</strong> sellos.
            Te faltan {{ $estado['faltan'] }} para: <strong>{{ $estado['premio'] }}</strong>.
        </p>

        @if ($estado['premios'] > 0)
            <div class="alert alert-success mt-3 mb-0 text-center">
                Tienes {{ $estado['premios'] }} {{ $estado['premios'] === 1 ? 'premio listo' : 'premios listos' }}: {{ $estado['premio'] }}.
                Muestra esta pantalla en tu próxima visita.
            </div>
        @endif
    </div>

    <p class="small text-secondary text-center mt-3">Ganas un sello por cada cita completada. Guarda esta página en tu teléfono.</p>
</div>
@endsection
