<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $reporte['titulo'] }}</title>
<style>
    @page { margin: 28px 32px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1c1c1e; }
    .marca { font-size: 9px; color: #6f4e37; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; }
    h1 { font-size: 17px; margin: 2px 0 2px; }
    .sub { color: #636366; margin-bottom: 12px; }
    .resumen { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px 10px; }
    .resumen td { background: #f2f2f7; border-radius: 6px; padding: 6px 8px; width: 25%; vertical-align: top; }
    .resumen .et { color: #636366; font-size: 8.5px; }
    .resumen .va { font-weight: bold; font-size: 11px; }
    table.datos { width: 100%; border-collapse: collapse; }
    table.datos th { background: #1e3a5f; color: #fff; text-align: left; padding: 5px 6px; font-size: 9px; }
    table.datos td { padding: 4px 6px; border-bottom: 1px solid #e5e5ea; }
    table.datos tr:nth-child(even) td { background: #fafafc; }
    .num { text-align: right; }
    .total td { font-weight: bold; border-top: 1.5px solid #1e3a5f; }
    .nota, .pie { color: #8e8e93; font-size: 8.5px; margin-top: 8px; }
</style>
</head>
<body>
<div class="marca">{{ config('whatsapp.negocio.nombre') }} · Barber Agenda</div>
<h1>{{ $reporte['titulo'] }}</h1>
<div class="sub">{{ $etiqueta }}</div>

@if ($reporte['resumen'] !== [])
    <table class="resumen">
        @foreach (array_chunk($reporte['resumen'], 4, true) as $renglon)
            <tr>
                @foreach ($renglon as $et => $va)
                    <td><div class="et">{{ $et }}</div><div class="va">{{ $va }}</div></td>
                @endforeach
            </tr>
        @endforeach
    </table>
@endif

<table class="datos">
    <thead><tr>
        @foreach ($reporte['columnas'] as [$nombre, $tipoCol])
            <th class="@if($tipoCol !== 'texto') num @endif">{{ $nombre }}</th>
        @endforeach
    </tr></thead>
    <tbody>
        @forelse ($reporte['filas'] as $fila)
            <tr>
                @foreach ($fila as $i => $celda)
                    @php $tipoCol = $reporte['columnas'][$i][1] ?? 'texto'; @endphp
                    <td class="@if($tipoCol !== 'texto') num @endif">{{ \App\Http\Controllers\Panel\ReporteController::formatear($celda, $tipoCol) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($reporte['columnas']) }}">No hay citas en este periodo.</td></tr>
        @endforelse
        @if (! empty($reporte['total']))
            <tr class="total">
                @foreach ($reporte['total'] as $i => $celda)
                    @php $tipoCol = $reporte['columnas'][$i][1] ?? 'texto'; @endphp
                    <td class="@if($tipoCol !== 'texto') num @endif">{{ \App\Http\Controllers\Panel\ReporteController::formatear($celda, $tipoCol) }}</td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>

@if ($reporte['nota'])<div class="nota">{{ $reporte['nota'] }}</div>@endif
<div class="pie">Generado el {{ now()->format('d/m/Y H:i') }}. Montos estimados con el precio actual de cada servicio; las citas no guardan lo cobrado.</div>
</body>
</html>
