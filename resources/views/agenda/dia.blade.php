@extends('layouts.app')

@section('titulo', 'Agenda')

@php
    $estados = ['confirmada' => 'Confirmada', 'cancelada' => 'Cancelada', 'completada' => 'Atendida', 'no_asistio' => 'No asistió'];
    $linea = collect($citas)->map(fn ($c) => ['tipo' => 'cita', 'inicio' => $c->inicio, 'cita' => $c])
        ->concat(collect($huecos)->map(fn ($h) => ['tipo' => 'libre', 'inicio' => $h['inicio'], 'fin' => $h['fin']]))
        ->sortBy(fn ($e) => $e['inicio']->timestamp . ($e['tipo'] === 'cita' ? '1' : '0'));
    $vigentes = $citas->whereIn('estado', ['confirmada', 'completada']);
@endphp

@section('contenido')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('agenda', ['fecha' => $dia->copy()->subDay()->toDateString()]) }}" aria-label="Día anterior">‹</a>
    <form method="get" action="{{ route('agenda') }}">
        <input type="date" name="fecha" value="{{ $dia->toDateString() }}" class="form-control form-control-sm" onchange="this.form.submit()">
    </form>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('agenda', ['fecha' => $dia->copy()->addDay()->toDateString()]) }}" aria-label="Día siguiente">›</a>
    @unless ($dia->isToday())
        <a class="btn btn-link btn-sm" href="{{ route('agenda') }}">Hoy</a>
    @endunless
    <a class="btn btn-acento ms-auto" href="{{ route('citas.create', ['fecha' => $dia->toDateString()]) }}">+ Nueva cita</a>
</div>

<h1 class="h4 mb-1">{{ \Illuminate\Support\Str::ucfirst($dia->translatedFormat('l j \d\e F')) }}</h1>
<p class="text-secondary">
    @if ($horario)
        Abierto de {{ substr($horario->abre, 0, 5) }} a {{ substr($horario->cierra, 0, 5) }} ·
        {{ $vigentes->count() }} {{ $vigentes->count() === 1 ? 'cita' : 'citas' }} ·
        ${{ number_format($vigentes->sum(fn ($c) => (float) $c->servicio->precio), 0) }} estimado
    @else
        Cerrado este día.
    @endif
</p>

@forelse ($linea as $e)
    @if ($e['tipo'] === 'libre')
        <div class="linea">
            <div class="hora">{{ $e['inicio']->format('H:i') }}</div>
            <a class="bloque libre text-decoration-none" href="{{ route('citas.create', ['fecha' => $dia->toDateString(), 'hora' => $e['inicio']->format('H:i')]) }}">
                Libre hasta las {{ $e['fin']->format('H:i') }} <small>({{ $e['inicio']->diffInMinutes($e['fin']) }} min) · agendar aquí</small>
            </a>
        </div>
    @else
        @php $c = $e['cita']; @endphp
        <div class="linea">
            <div class="hora">{{ $c->inicio->format('H:i') }}</div>
            <div class="bloque {{ $c->estado }}">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <strong>{{ $c->cliente->nombre ?? 'Sin nombre' }}</strong> · {{ $c->servicio->nombre }}
                        <span class="origen {{ $c->origen }}">{{ $c->origen }}</span>
                        <div class="small text-secondary">
                            {{ $c->inicio->format('H:i') }}–{{ $c->fin->format('H:i') }} · {{ $estados[$c->estado] ?? $c->estado }}
                            · <a href="{{ route('clientes.show', $c->cliente) }}">{{ \App\Support\Telefono::legible($c->cliente->wa_id) }}</a>
                        </div>
                        @if ($c->notas)<div class="small mt-1">📝 {{ $c->notas }}</div>@endif
                    </div>
                    @unless ($c->estado === 'cancelada')
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Acciones</button>
                            <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 15rem">
                                @foreach (['completada' => 'Marcar atendida', 'no_asistio' => 'No asistió', 'confirmada' => 'Volver a confirmada'] as $valor => $texto)
                                    @continue($c->estado === $valor)
                                    <form method="post" action="{{ route('citas.estado', $c) }}">
                                        @csrf
                                        <input type="hidden" name="estado" value="{{ $valor }}">
                                        <button class="dropdown-item">{{ $texto }}</button>
                                    </form>
                                @endforeach
                                @if ($c->inicio->isFuture())
                                    <a class="dropdown-item" href="{{ route('citas.edit', $c) }}">Mover a otro horario</a>
                                    <hr class="dropdown-divider">
                                    <form method="post" action="{{ route('citas.cancelar', $c) }}" onsubmit="return confirm('¿Cancelar esta cita? El horario quedará libre.')">
                                        @csrf
                                        <div class="form-check small mx-2">
                                            <input class="form-check-input" type="checkbox" name="avisar" value="1" id="avisar{{ $c->id }}" checked>
                                            <label class="form-check-label" for="avisar{{ $c->id }}">Avisarle por WhatsApp</label>
                                        </div>
                                        <button class="dropdown-item text-danger">Cancelar cita</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endunless
                </div>
            </div>
        </div>
    @endif
@empty
    <div class="tarjeta p-4 text-center text-secondary">No hay citas este día.</div>
@endforelse
@endsection

@push('scripts')
@include('agenda._vivo', ['desde' => $dia->toDateString(), 'hasta' => $dia->toDateString()])
@endpush
