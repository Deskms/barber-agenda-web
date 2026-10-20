@extends('layouts.app')

@section('titulo', 'Nueva cita')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="tarjeta p-4">
            <h1 class="h4 mb-3">Nueva cita</h1>
            <form method="post" action="{{ route('citas.store') }}" id="formCita">
                @csrf
                @if ($cliente)
                    <input type="hidden" name="cliente_id" value="{{ $cliente->id }}">
                    <p class="mb-3">Clienta: <strong>{{ $cliente->nombre }}</strong> · {{ \App\Support\Telefono::legible($cliente->wa_id) }}</p>
                @else
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="nombre">Nombre</label>
                            <input class="form-control" id="nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="120">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="telefono">Celular (WhatsApp)</label>
                            <input class="form-control" id="telefono" name="telefono" value="{{ old('telefono') }}" required inputmode="tel" placeholder="33 1234 5678">
                            <div class="form-text">Si ya es clienta, se usa su registro.</div>
                        </div>
                    </div>
                @endif

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="servicio">Servicio</label>
                        <select class="form-select" id="servicio" name="servicio_id" required>
                            @foreach ($servicios as $s)
                                <option value="{{ $s->id }}" @selected(old('servicio_id') == $s->id)>{{ $s->nombre }} · {{ $s->duracion_minutos }} min @if($s->precio !== null)· ${{ number_format((float) $s->precio, 0) }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="fecha">Día</label>
                        <input class="form-control" type="date" id="fecha" name="fecha" value="{{ old('fecha', $fecha) }}" min="{{ now()->toDateString() }}" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="hora">Horario libre</label>
                    <select class="form-select" id="hora" name="hora" required data-preferida="{{ old('hora', $hora) }}"></select>
                    <div class="form-text" id="ayudaHora"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="notas">Notas (opcional)</label>
                    <input class="form-control" id="notas" name="notas" value="{{ old('notas') }}" maxlength="500">
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="avisar" value="1" id="avisar" @checked(old('avisar', true))>
                    <label class="form-check-label" for="avisar">Enviarle la confirmación por WhatsApp</label>
                </div>

                <button class="btn btn-acento">Agendar</button>
                <a class="btn btn-link" href="{{ url()->previous() }}">Cancelar</a>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('citas._horarios', ['ignorar' => null])
@endpush
