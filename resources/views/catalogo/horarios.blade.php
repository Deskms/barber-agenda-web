@extends('layouts.app')

@section('titulo', 'Horario')

@section('contenido')
<h1 class="h4 mb-3">Horario de atención</h1>
<p class="text-secondary">Solo se ofrecen citas que caben completas dentro de este horario.</p>

<form method="post" action="{{ route('horarios.update') }}" class="tarjeta p-3">
    @csrf
    @method('put')
    @foreach ([1, 2, 3, 4, 5, 6, 0] as $d)
        @php $h = $horarios->get($d); @endphp
        <div class="row g-2 align-items-center mb-2">
            <div class="col-4 col-sm-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="dias[{{ $d }}][activo]" value="1" id="dia{{ $d }}" @checked(old("dias.$d.activo", $h?->activo))>
                    <label class="form-check-label" for="dia{{ $d }}">{{ $dias[$d] }}</label>
                </div>
            </div>
            <div class="col-4 col-sm-3"><input class="form-control" type="time" name="dias[{{ $d }}][abre]" value="{{ old("dias.$d.abre", $h ? substr($h->abre, 0, 5) : '10:00') }}" aria-label="Abre {{ $dias[$d] }}"></div>
            <div class="col-4 col-sm-3"><input class="form-control" type="time" name="dias[{{ $d }}][cierra]" value="{{ old("dias.$d.cierra", $h ? substr($h->cierra, 0, 5) : '19:00') }}" aria-label="Cierra {{ $dias[$d] }}"></div>
        </div>
    @endforeach
    <button class="btn btn-acento mt-2">Guardar horario</button>
</form>
@endsection
