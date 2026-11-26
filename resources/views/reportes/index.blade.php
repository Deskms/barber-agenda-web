@extends('layouts.app')

@section('titulo', 'Reportes')

@section('contenido')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h4 m-0">Reportes</h1>
    <div class="ms-auto d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('reportes.pdf', $filtros) }}">Descargar PDF</a>
        <a class="btn btn-sm btn-acento" href="{{ route('reportes.excel', $filtros) }}">Descargar Excel</a>
    </div>
</div>

<form method="get" class="tarjeta p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-auto">
            <label class="form-label d-block">Periodo</label>
            <div class="segmentado">
                @foreach ($periodos as $clave => $texto)
                    <a class="@if($periodo === $clave) activo @endif" href="{{ route('reportes', array_merge($filtros, ['periodo' => $clave])) }}">{{ $texto }}</a>
                @endforeach
            </div>
            <input type="hidden" name="periodo" value="{{ $periodo }}">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="fecha">{{ $periodo === 'dia' ? 'Día' : ($periodo === 'semana' ? 'Cualquier día de la semana' : 'Cualquier día del mes') }}</label>
            <input class="form-control" type="date" id="fecha" name="fecha" value="{{ $fecha->toDateString() }}">
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label" for="tipo">Tipo de reporte</label>
            <select class="form-select" id="tipo" name="tipo">
                @foreach ($tipos as $clave => $texto)
                    <option value="{{ $clave }}" @selected($tipo === $clave)>{{ $texto }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-auto">
            <button class="btn btn-outline-secondary w-100">Ver</button>
        </div>
    </div>
</form>

<div class="tarjeta">
    <div class="p-3 border-bottom">
        <div class="fw-bold">{{ $reporte['titulo'] }}</div>
        <div class="small text-secondary">{{ $etiqueta }} · montos estimados con el precio actual de cada servicio</div>
    </div>

    @if ($reporte['resumen'] !== [])
        <div class="row g-2 p-3 border-bottom">
            @foreach ($reporte['resumen'] as $etiquetaResumen => $valor)
                <div class="col-6 col-md-3">
                    <div class="small text-secondary">{{ $etiquetaResumen }}</div>
                    <div class="fw-bold">{{ $valor }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    @foreach ($reporte['columnas'] as [$nombre, $tipoCol])
                        <th class="@if($tipoCol !== 'texto') text-end @endif">{{ $nombre }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($reporte['filas'] as $fila)
                    <tr>
                        @foreach ($fila as $i => $celda)
                            @php $tipoCol = $reporte['columnas'][$i][1] ?? 'texto'; @endphp
                            <td class="@if($tipoCol !== 'texto') text-end @endif">{{ \App\Http\Controllers\Panel\ReporteController::formatear($celda, $tipoCol) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($reporte['columnas']) }}" class="text-center text-secondary p-4">No hay citas en este periodo.</td></tr>
                @endforelse
                @if (! empty($reporte['total']))
                    <tr class="fw-bold">
                        @foreach ($reporte['total'] as $i => $celda)
                            @php $tipoCol = $reporte['columnas'][$i][1] ?? 'texto'; @endphp
                            <td class="@if($tipoCol !== 'texto') text-end @endif">{{ \App\Http\Controllers\Panel\ReporteController::formatear($celda, $tipoCol) }}</td>
                        @endforeach
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    @if ($reporte['nota'])
        <div class="p-3 small text-secondary border-top">{{ $reporte['nota'] }}</div>
    @endif
</div>
@endsection
