@extends('layouts.app')

@section('titulo', 'Tablero')

@section('contenido')
@php
    $pesos = fn ($n) => '$' . number_format((float) $n, 0);
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h4 m-0">Tablero · {{ \Illuminate\Support\Str::ucfirst($mes->translatedFormat('F Y')) }}</h1>
    <form method="get" class="ms-auto d-flex gap-2">
        <input type="month" name="mes" class="form-control form-control-sm" value="{{ $mes->format('Y-m') }}">
        <button class="btn btn-sm btn-outline-secondary">Ver</button>
        <a class="btn btn-sm btn-acento" href="{{ route('reportes', ['periodo' => 'mes', 'fecha' => $mes->toDateString()]) }}">Reportes y exportar</a>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="tarjeta p-3 h-100">
        <div class="small text-secondary">Hoy</div>
        <div class="fs-4 fw-bold">{{ $hoy['citas'] }} citas</div>
        <div class="small">{{ $hoy['llegadas'] }} llegadas registradas · {{ $hoy['completadas'] }} atendidas</div>
    </div></div>
    <div class="col-md-4"><div class="tarjeta p-3 h-100">
        <div class="small text-secondary">Citas del mes</div>
        <div class="small">Atendidas: <strong>{{ $conteo['completadas'] }}</strong> · Por atender: <strong>{{ $conteo['confirmadas'] }}</strong></div>
        <div class="small">Canceladas: <strong>{{ $conteo['canceladas'] }}</strong> · Inasistencias: <strong>{{ $conteo['inasistencias'] }}</strong></div>
    </div></div>
    <div class="col-md-4"><div class="tarjeta p-3 h-100">
        <div class="small text-secondary">Ingresos del mes (estimados)</div>
        <div class="small">Obtenidos (atendidas): <strong>{{ $pesos($ingresos['obtenido']) }}</strong></div>
        <div class="small">Potenciales (por atender): <strong>{{ $pesos($ingresos['potencial']) }}</strong></div>
        <div class="small">Perdidos (cancelaciones y faltas): <strong>{{ $pesos($ingresos['perdido']) }}</strong></div>
    </div></div>
</div>

<div class="tarjeta p-3 mb-3">
    <h2 class="h6">Recuperación de espacios</h2>
    <div class="row g-2 small">
        <div class="col-6 col-md-3">Avisos enviados<br><strong class="fs-5">{{ $recuperacion['avisos'] }}</strong></div>
        <div class="col-6 col-md-3">Espacios recuperados<br><strong class="fs-5">{{ $recuperacion['espacios'] }}</strong></div>
        <div class="col-6 col-md-3">Recuperado confirmado<br><strong class="fs-5">{{ $pesos($recuperacion['confirmado']) }}</strong></div>
        <div class="col-6 col-md-3">Recuperado obtenido<br><strong class="fs-5">{{ $pesos($recuperacion['obtenido']) }}</strong></div>
    </div>
    <p class="small text-secondary mt-2 mb-0">
        Costo estimado de mensajes:
        {{ $recuperacion['costo'] === null ? 'solo se calcula para el mes en curso' : $pesos($recuperacion['costo']) . ' MXN' }}.
        Un espacio cuenta como recuperado cuando la clienta agenda en el hueco que se le avisó.
    </p>
</div>

<div class="row g-3">
    <div class="col-md-4"><div class="tarjeta p-3 h-100">
        <h2 class="h6">Ocupación por sección</h2>
        @forelse ($ocupacion as $o)
            <div class="small">{{ $o['seccion'] }}: <strong>{{ $o['porcentaje'] }} %</strong> ({{ $o['horas'] }} h)</div>
            <div class="progress mb-2" style="height: 6px"><div class="progress-bar" style="width: {{ min(100, $o['porcentaje']) }}%"></div></div>
        @empty
            <p class="small text-secondary m-0">Sin secciones.</p>
        @endforelse
    </div></div>
    <div class="col-md-4"><div class="tarjeta p-3 h-100">
        <h2 class="h6">Servicios más solicitados</h2>
        @forelse ($servicios as $nombre => $total)
            <div class="small d-flex justify-content-between"><span>{{ $nombre }}</span><strong>{{ $total }}</strong></div>
        @empty
            <p class="small text-secondary m-0">Sin citas este mes.</p>
        @endforelse
    </div></div>
    <div class="col-md-4"><div class="tarjeta p-3 h-100">
        <h2 class="h6">Más inasistencias</h2>
        @forelse ($faltistas as $f)
            <div class="small d-flex justify-content-between">
                <a href="{{ route('clientes.show', $f['cliente']) }}">{{ $f['cliente']->nombre ?? 'Sin nombre' }}</a><strong>{{ $f['faltas'] }}</strong>
            </div>
        @empty
            <p class="small text-secondary m-0">Nadie faltó este mes.</p>
        @endforelse
    </div></div>
</div>
@endsection
