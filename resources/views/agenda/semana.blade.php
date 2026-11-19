@extends('layouts.app')

@section('titulo', 'Semana')

@section('contenido')
@include('agenda._barra', [
    'vista'     => 'semana',
    'fecha'     => $inicio->toDateString(),
    'etiqueta'  => 'Semana del ' . $inicio->translatedFormat('j \d\e F'),
    'anterior'  => route('agenda.semana', ['fecha' => $inicio->copy()->subWeek()->toDateString()]),
    'siguiente' => route('agenda.semana', ['fecha' => $inicio->copy()->addWeek()->toDateString()]),
])

<div class="semana">
    @foreach (range(0, 6) as $i)
        @php $dia = $inicio->copy()->addDays($i); $delDia = $citas->get($dia->toDateString(), collect()); @endphp
        <div class="dia @if($dia->isToday()) hoy @endif">
            <a class="fw-semibold text-decoration-none d-block mb-2" href="{{ route('agenda', ['fecha' => $dia->toDateString()]) }}">
                {{ \Illuminate\Support\Str::ucfirst($dia->translatedFormat('D j')) }}
            </a>
            @forelse ($delDia as $c)
                <a class="mini d-block text-decoration-none text-reset {{ $c->estado }}" href="{{ route('agenda', ['fecha' => $dia->toDateString(), 'cita' => $c->id]) }}">
                    {{ $c->inicio->format('H:i') }} {{ \Illuminate\Support\Str::limit($c->cliente->nombre ?? '—', 14) }}<br>
                    <span class="text-secondary">{{ $c->servicio->nombre }}</span>
                </a>
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
