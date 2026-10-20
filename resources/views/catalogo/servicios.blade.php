@extends('layouts.app')

@section('titulo', 'Servicios')

@section('contenido')
<h1 class="h4 mb-3">Servicios</h1>
<p class="text-secondary">La duración define cuánto espacio ocupa cada cita en la agenda. Un servicio desactivado deja de ofrecerse, pero sus citas ya agendadas siguen igual.</p>

<div class="tarjeta p-3 mb-4">
    <h2 class="h6">Agregar servicio</h2>
    <form method="post" action="{{ route('servicios.store') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-sm-4"><label class="form-label small">Nombre</label><input class="form-control" name="nombre" required maxlength="60"></div>
        <div class="col-6 col-sm-2"><label class="form-label small">Minutos</label><input class="form-control" name="duracion_minutos" type="number" min="15" max="480" step="15" value="60" required></div>
        <div class="col-6 col-sm-2"><label class="form-label small">Precio $</label><input class="form-control" name="precio" type="number" min="0" step="1"></div>
        <input type="hidden" name="activo" value="1">
        <div class="col-sm-2"><button class="btn btn-acento w-100">Agregar</button></div>
    </form>
</div>

@foreach ($servicios as $s)
    <form method="post" action="{{ route('servicios.update', $s) }}" class="tarjeta p-3 mb-2 row g-2 align-items-end mx-0">
        @csrf
        @method('put')
        <div class="col-sm-4"><label class="form-label small">Nombre</label><input class="form-control" name="nombre" value="{{ $s->nombre }}" required maxlength="60"></div>
        <div class="col-4 col-sm-2"><label class="form-label small">Minutos</label><input class="form-control" name="duracion_minutos" type="number" min="15" max="480" step="15" value="{{ $s->duracion_minutos }}" required></div>
        <div class="col-4 col-sm-2"><label class="form-label small">Precio $</label><input class="form-control" name="precio" type="number" min="0" step="1" value="{{ $s->precio !== null ? (int) $s->precio : '' }}"></div>
        <div class="col-4 col-sm-2">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo{{ $s->id }}" @checked($s->activo)>
                <label class="form-check-label small" for="activo{{ $s->id }}">Activo</label>
            </div>
        </div>
        <div class="col-sm-2"><button class="btn btn-outline-secondary w-100">Guardar</button></div>
    </form>
@endforeach
@endsection
