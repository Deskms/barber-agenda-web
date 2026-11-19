{{-- Barra de herramientas común a Día y Semana. --}}
<div class="herramientas">
    <a class="btn btn-outline-secondary btn-sm px-3" href="{{ route($vista === 'dia' ? 'agenda' : 'agenda.semana') }}">Hoy</a>
    <a class="btn btn-outline-secondary btn-sm" href="{{ $anterior }}" aria-label="Anterior">‹</a>
    <a class="btn btn-outline-secondary btn-sm" href="{{ $siguiente }}" aria-label="Siguiente">›</a>
    <form method="get" action="{{ route($vista === 'dia' ? 'agenda' : 'agenda.semana') }}" class="fecha ms-1">
        {{ $etiqueta }} <span aria-hidden="true">▾</span>
        <input type="date" name="fecha" value="{{ $fecha }}" onchange="this.form.submit()" aria-label="Elegir fecha">
    </form>
    <div class="ms-auto d-flex gap-2 align-items-center">
        <a class="btn btn-acento btn-sm px-3" href="{{ route('citas.create', ['fecha' => $fecha]) }}">+ Nueva cita</a>
        <div class="segmentado">
            <a class="@if($vista === 'dia') activo @endif" href="{{ route('agenda', ['fecha' => $fecha]) }}">Día</a>
            <a class="@if($vista === 'semana') activo @endif" href="{{ route('agenda.semana', ['fecha' => $fecha]) }}">Semana</a>
        </div>
    </div>
</div>
