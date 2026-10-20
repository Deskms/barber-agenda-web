@extends('layouts.app')

@section('titulo', 'Mover cita')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="tarjeta p-4">
            <h1 class="h4 mb-1">Mover cita</h1>
            <p class="text-secondary">{{ $cita->cliente->nombre }} · {{ $cita->servicio->nombre }} · ahora el {{ $cita->inicio->translatedFormat('l j \d\e F, H:i') }}</p>
            <form method="post" action="{{ route('citas.update', $cita) }}">
                @csrf
                @method('put')
                <select id="servicio" class="d-none"><option value="{{ $cita->servicio_id }}" selected></option></select>
                <div class="mb-3">
                    <label class="form-label" for="fecha">Nuevo día</label>
                    <input class="form-control" type="date" id="fecha" name="fecha" value="{{ old('fecha', $cita->inicio->toDateString()) }}" min="{{ now()->toDateString() }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="hora">Nuevo horario</label>
                    <select class="form-select" id="hora" name="hora" required data-preferida="{{ old('hora', $cita->inicio->format('H:i')) }}"></select>
                    <div class="form-text" id="ayudaHora"></div>
                </div>
                <p class="small text-secondary">El horario anterior quedará libre para otras clientas.</p>
                <button class="btn btn-acento">Mover</button>
                <a class="btn btn-link" href="{{ route('agenda', ['fecha' => $cita->inicio->toDateString()]) }}">Volver</a>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('citas._horarios', ['ignorar' => $cita->id])
@endpush
