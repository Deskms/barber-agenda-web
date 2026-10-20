@extends('layouts.app')

@section('titulo', 'Promociones')

@section('contenido')
<h1 class="h4 mb-1">Promociones</h1>
<p class="text-secondary">Contestarle a quien te escribió en las últimas 24 h es gratis. A los demás solo se les puede escribir con una plantilla de promoción de Meta, que cuesta unos <strong>${{ number_format($costo, 2) }} MXN</strong> por mensaje. Aquí decides cuánto gastar como máximo.</p>

<div class="row g-3">
    <div class="col-lg-5">
        <form method="post" action="{{ route('promociones.guardar') }}" class="tarjeta p-3 mb-3">
            @csrf
            @method('put')
            <div class="form-check form-switch mb-1">
                <input class="form-check-input" type="checkbox" name="huecos_auto" value="1" id="huecos_auto" @checked($ajustes['huecos_auto'] === '1')>
                <label class="form-check-label fw-semibold" for="huecos_auto">Avisar huecos automáticamente</label>
            </div>
            <p class="small text-secondary">Cuando se cancela o se mueve una cita, avisa a los clientes que usan esa sección (por ejemplo, un hueco en cortes solo a quienes se cortan el pelo). Primero a los que escribieron en las últimas 24 h, gratis; el primero que confirma se lo queda.</p>
            <div class="mb-3">
                <label class="form-label small" for="huecos_max">Máximo de avisos pagados por hueco</label>
                <input class="form-control" type="number" min="0" max="50" name="huecos_max" id="huecos_max" value="{{ $ajustes['huecos_max'] }}">
            </div>

            <div class="form-check form-switch mb-1">
                <input class="form-check-input" type="checkbox" name="regreso_auto" value="1" id="regreso_auto" @checked($ajustes['regreso_auto'] === '1')>
                <label class="form-check-label fw-semibold" for="regreso_auto">"Ya te toca volver", cada día a las 11:00</label>
            </div>
            <p class="small text-secondary">Usa los días de "Volver cada" de cada servicio en Servicios (el corte trae 15).</p>

            <div class="mb-3">
                <label class="form-label small" for="presupuesto_mensual">Presupuesto al mes (MXN)</label>
                <input class="form-control" type="number" min="0" step="1" name="presupuesto_mensual" id="presupuesto_mensual" value="{{ $ajustes['presupuesto_mensual'] }}">
                <div class="form-text">Gastado este mes: <strong>${{ number_format($gasto, 2) }}</strong>. Al llegar al tope solo se envían los mensajes gratis.</div>
            </div>
            <button class="btn btn-acento">Guardar</button>
        </form>

        <div class="tarjeta p-3">
            <h2 class="h6">Página de espacios libres (gratis)</h2>
            <p class="small text-secondary">Compártela en tu estado de WhatsApp, Instagram o Facebook. Cada horario abre WhatsApp con el mensaje ya escrito; como el cliente escribe primero, lo que le contestes no se cobra.</p>
            <a href="{{ $linkLibres }}" target="_blank" rel="noopener" class="text-break">{{ $linkLibres }}</a>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="tarjeta p-3 mb-3">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <h2 class="h6 m-0">Les toca volver ({{ $regresos->count() }})</h2>
                @if ($regresos->isNotEmpty())
                    <form method="post" action="{{ route('promociones.regresos') }}" class="ms-auto" onsubmit="return confirm('¿Enviar ahora el mensaje a {{ $regresos->count() }} clientes? Los que no escribieron en 24 h cuestan ${{ number_format($costo, 2) }} c/u.')">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary">Enviar ahora</button>
                    </form>
                @endif
            </div>
            <table class="table table-sm mb-0">
                <thead><tr><th>Cliente</th><th>Servicio</th><th class="text-end">Días</th><th>Costo</th></tr></thead>
                <tbody>
                @forelse ($regresos as $r)
                    <tr>
                        <td><a href="{{ route('clientes.show', $r['cliente']) }}">{{ $r['cliente']->nombre ?? 'Sin nombre' }}</a></td>
                        <td>{{ $r['servicio']->nombre }}</td>
                        <td class="text-end">{{ $r['dias'] }}</td>
                        <td>{!! $r['gratis'] ? '<span class="estado-ok">gratis</span>' : '$' . number_format($costo, 2) !!}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-secondary">Nadie pendiente por ahora.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="tarjeta p-3">
            <h2 class="h6">Últimos envíos</h2>
            @foreach ($difusiones as $d)
                <div class="small border-bottom py-1">
                    Hueco {{ $d->inicio->translatedFormat('D j M H:i') }} → {{ $d->cliente->nombre }} · {{ $d->estado }} · {{ $d->gratis ? 'gratis' : 'pagado' }}{{ $d->automatica ? ' · automático' : '' }}
                </div>
            @endforeach
            @foreach ($recontactos as $r)
                <div class="small border-bottom py-1">
                    Volver ({{ $r->servicio->nombre }}, {{ $r->dias_desde_visita }} días) → {{ $r->cliente->nombre }} · {{ $r->estado }} · {{ $r->gratis ? 'gratis' : 'pagado' }}
                </div>
            @endforeach
            @if ($difusiones->isEmpty() && $recontactos->isEmpty())
                <p class="small text-secondary m-0">Todavía no se ha enviado nada.</p>
            @endif
        </div>
    </div>
</div>
@endsection
