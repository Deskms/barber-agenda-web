@extends('layouts.app')

@section('titulo', 'Semana')

@section('contenido')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('agenda.semana', ['fecha' => $inicio->copy()->subWeek()->toDateString()]) }}">‹ Anterior</a>
    <h1 class="h5 m-0">Semana del {{ $inicio->translatedFormat('j \d\e F') }}</h1>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('agenda.semana', ['fecha' => $inicio->copy()->addWeek()->toDateString()]) }}">Siguiente ›</a>
    <a class="btn btn-acento ms-auto" href="{{ route('citas.create') }}">+ Nueva cita</a>
</div>

<div class="semana">
    @foreach (range(0, 6) as $i)
        @php $dia = $inicio->copy()->addDays($i); $delDia = $citas->get($dia->toDateString(), collect()); @endphp
        <div class="dia @if($dia->isToday()) hoy @endif">
            <a class="fw-semibold text-decoration-none d-block mb-2" href="{{ route('agenda', ['fecha' => $dia->toDateString()]) }}">
                {{ \Illuminate\Support\Str::ucfirst($dia->translatedFormat('D j')) }}
            </a>
            @forelse ($delDia as $c)
                <div class="mini {{ $c->estado }}">
                    {{ $c->inicio->format('H:i') }} {{ \Illuminate\Support\Str::limit($c->cliente->nombre ?? '—', 14) }}<br>
                    <span class="text-secondary">{{ $c->servicio->nombre }}</span>
                </div>
            @empty
                <div class="small text-secondary">Sin citas</div>
            @endforelse
        </div>
    @endforeach
</div>
@endsection

@push('scripts')
@include('agenda._vivo', ['desde' => $inicio->toDateString(), 'hasta' => $inicio->copy()->addDays(6)->toDateString()])
@endpush
