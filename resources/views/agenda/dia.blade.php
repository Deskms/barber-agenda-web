@extends('layouts.app')

@section('titulo', 'Agenda')
@section('ancho', 'container-fluid px-3')

@php
    $estados = ['confirmada' => 'Confirmada', 'completada' => 'Atendida', 'no_asistio' => 'No asistió', 'cancelada' => 'Cancelada'];
    $colorEstado = ['confirmada' => 'var(--b-confirmada)', 'completada' => 'var(--b-completada)', 'no_asistio' => 'var(--b-falta)', 'cancelada' => '#d1d1d6'];
    $origenes = ['whatsapp' => 'WhatsApp', 'panel' => 'Panel', 'movil' => 'App'];

    // 1 hora = 84 px. Todo el calendario se posiciona con esta escala.
    $pxHora = 84;
    $y = fn ($t) => (int) round($desde->diffInMinutes($t) * $pxHora / 60);
    $alto = (int) round($desde->diffInMinutes($hasta) * $pxHora / 60);
    $horas = [];
    for ($h = $desde->copy(); $h->lte($hasta); $h->addHour()) { $horas[] = $h->copy(); }

    $iniciales = fn (?string $nombre) => mb_strtoupper(collect(explode(' ', trim($nombre ?? '?')))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('') ?: '?');
    // Tonos sobrios de la paleta (negro, café, azul ballena) con texto blanco.
    $tonos = ['#1e3a5f', '#6f4e37', '#3a3a3c', '#4a6886', '#8c6a50', '#111113'];
    $tono = fn ($id) => $tonos[$id % count($tonos)];

    $claseCita = fn ($c) => $c->estado === 'confirmada' && $c->llegada_en ? 'en-local' : $c->estado;
    $textoEstado = fn ($c) => $c->estado === 'confirmada' && $c->llegada_en ? 'En el local' : ($estados[$c->estado] ?? $c->estado);
    $colorDe = fn ($c) => $c->estado === 'confirmada' && $c->llegada_en ? 'var(--b-local)' : ($colorEstado[$c->estado] ?? '#ccc');

    $ingreso = $citas->sum(fn ($c) => (float) ($c->servicio->precio ?? 0));
    $libres  = collect($agendas)->flatMap(fn ($a) => $a['huecos'])->sum(fn ($h) => $h['inicio']->diffInMinutes($h['fin']));
    $columnas = count($agendas);
@endphp

@section('contenido')
@include('agenda._barra', [
    'vista'     => 'dia',
    'fecha'     => $dia->toDateString(),
    'etiqueta'  => \Illuminate\Support\Str::ucfirst($dia->translatedFormat('l, j \d\e F Y')),
    'anterior'  => route('agenda', ['fecha' => $dia->copy()->subDay()->toDateString()]),
    'siguiente' => route('agenda', ['fecha' => $dia->copy()->addDay()->toDateString()]),
])

<div class="agenda-dia">
    {{-- ===================== Calendario ===================== --}}
    <div class="cal-scroll">
        <div class="cal" style="grid-template-columns: var(--col-horas) repeat({{ $columnas }}, minmax(var(--col-min), 1fr)); --hora: {{ $pxHora }}px;">
            <div class="cabecera vacia"></div>
            @foreach ($agendas as $i => $agenda)
                <div class="cabecera">
                    <span class="avatar seccion" style="background: {{ $tono($i) }}">{{ $iniciales($agenda['titulo'] ?? 'Agenda') }}</span>
                    {{ $agenda['titulo'] ?? 'Agenda' }}
                </div>
            @endforeach

            <div class="horas" style="height: {{ $alto }}px">
                @foreach ($horas as $h)
                    @if (! $loop->first)
                        <span style="top: {{ $y($h) }}px">{{ $h->format('H:i') }}</span>
                    @endif
                @endforeach
            </div>

            @foreach ($agendas as $agenda)
                <div class="columna" style="height: {{ $alto }}px">
                    @unless ($horario)
                        <div class="cerrado">Cerrado</div>
                    @endunless

                    @foreach ($agenda['huecos'] as $hueco)
                        @php $min = $hueco['inicio']->diffInMinutes($hueco['fin']); @endphp
                        @continue($min < 15)
                        <a class="libre" style="top: {{ $y($hueco['inicio']) + 2 }}px; height: {{ max(18, $y($hueco['fin']) - $y($hueco['inicio']) - 4) }}px"
                           href="{{ route('citas.create', ['fecha' => $dia->toDateString(), 'hora' => $hueco['inicio']->format('H:i')]) }}"
                           title="Agendar a las {{ $hueco['inicio']->format('H:i') }}">Libre hasta las {{ $hueco['fin']->format('H:i') }} · + agendar</a>
                    @endforeach

                    @foreach ($agenda['citas'] as $c)
                        <a class="cita {{ $claseCita($c) }} @if($elegida === $c->id) sel @endif"
                           style="top: {{ $y($c->inicio) + 1 }}px; height: {{ max(22, $y($c->fin) - $y($c->inicio) - 2) }}px"
                           href="{{ route('agenda', ['fecha' => $dia->toDateString(), 'cita' => $c->id]) }}"
                           data-abrir="{{ $c->id }}">
                            <div class="servicio">{{ $c->servicio->nombre }}</div>
                            <div class="cliente">{{ $c->cliente->nombre ?? 'Sin nombre' }}</div>
                            <div class="horario">{{ $c->inicio->format('H:i') }} – {{ $c->fin->format('H:i') }}</div>
                            <div class="marcas">
                                @if ($c->difusion_id)<span class="etiqueta recuperada" title="Agendó en un hueco que se le avisó">Recuperada</span>@endif
                                @if (($primeras[$c->cliente_id] ?? null) === $c->id)<span class="etiqueta">Nueva</span>@endif
                            </div>
                        </a>
                    @endforeach

                    @if ($dia->isToday() && now()->between($desde, $hasta))
                        <div class="ahora" style="top: {{ $y(now()) }}px"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- ===================== Panel lateral ===================== --}}
    <aside class="lateral">
        {{-- Fondo oscuro detrás de la hoja de detalle en celular y tableta; tocarlo la cierra. --}}
        <div class="velo" data-cerrar></div>

        {{-- Resumen del día: se ve cuando no hay cita elegida. --}}
        <div id="resumen" @if($elegida && $citas->contains('id', $elegida)) hidden @endif>
            <div class="seccion"><div class="titulo">Resumen del día</div></div>
            <div class="seccion">
                <div class="dato mb-1">
                    @if ($horario)
                        Abierto de <strong>{{ substr($horario->abre, 0, 5) }}</strong> a <strong>{{ substr($horario->cierra, 0, 5) }}</strong>
                    @else
                        <strong>Cerrado este día</strong>
                    @endif
                </div>
                <div class="dato">Citas: <strong>{{ $citas->count() }}</strong> · Llegadas: <strong>{{ $citas->whereNotNull('llegada_en')->count() }}</strong></div>
                <div class="dato">Atendidas: <strong>{{ $citas->where('estado', 'completada')->count() }}</strong> · Faltas: <strong>{{ $citas->where('estado', 'no_asistio')->count() }}</strong></div>
                <div class="dato">Ingreso estimado: <strong>${{ number_format($ingreso, 0) }}</strong></div>
                <div class="dato">Tiempo libre: <strong>{{ intdiv((int) $libres, 60) }} h {{ (int) $libres % 60 }} min</strong></div>
            </div>
            <div class="seccion">
                <div class="fw-bold mb-2">Colores</div>
                @foreach (['confirmada' => 'Confirmada', 'local' => 'En el local', 'completada' => 'Atendida', 'falta' => 'No asistió'] as $k => $t)
                    <div class="dato small"><span class="punto" style="background: var(--b-{{ $k }})"></span>{{ $t }}</div>
                @endforeach
            </div>
            <div class="seccion">
                <div class="fw-bold mb-2">Canceladas hoy ({{ $canceladas->count() }})</div>
                @forelse ($canceladas as $c)
                    <div class="dato small">{{ $c->inicio->format('H:i') }} · {{ $c->cliente->nombre ?? 'Sin nombre' }} · {{ $c->servicio->nombre }}</div>
                @empty
                    <div class="dato small">Ninguna.</div>
                @endforelse
                @if ($canceladas->isNotEmpty())
                    <a class="small d-inline-block mt-2" href="{{ route('huecos') }}">Ver espacios liberados →</a>
                @endif
            </div>
            <div class="seccion dato small">Toca una cita para ver su detalle.</div>
        </div>

        {{-- Detalle de cada cita, oculto hasta que se elige. --}}
        @foreach ($citas as $c)
            @php
                $wa = $c->cliente->wa_id;
                $tel = preg_replace('/^521(\d{10})$/', '52$1', (string) $wa);
                $min = $c->inicio->diffInMinutes($c->fin);
            @endphp
            <div class="detalle" data-cita="{{ $c->id }}" @unless($elegida === $c->id) hidden @endunless>
                <div class="seccion titulo">
                    Cita
                    <span class="d-flex gap-2 align-items-center">
                        @if ($c->inicio->isFuture() && $c->estado === 'confirmada')
                            <a class="small" href="{{ route('citas.edit', $c) }}" title="Mover a otro horario">Mover</a>
                        @endif
                        <a class="small text-secondary" href="{{ route('agenda', ['fecha' => $dia->toDateString()]) }}" data-cerrar title="Cerrar">✕</a>
                    </span>
                </div>

                <div class="seccion renglon align-items-center">
                    <div class="fw-bold"><span class="punto" style="background: {{ $colorDe($c) }}"></span>{{ $textoEstado($c) }}</div>
                    @if ($c->estado === 'confirmada')
                        <form method="post" action="{{ route('citas.estado', $c) }}">
                            @csrf
                            <input type="hidden" name="estado" value="completada">
                            <button class="btn-pastilla">MARCAR ATENDIDA</button>
                        </form>
                    @endif
                </div>

                <div class="seccion renglon">
                    <div class="dato">El <strong>{{ $c->inicio->translatedFormat('D j \d\e M') }}</strong></div>
                    <div class="dato">A las <strong>{{ $c->inicio->format('H:i') }}</strong></div>
                </div>

                <div class="seccion">
                    <div class="d-flex align-items-center gap-3">
                        <span class="avatar" style="width: 52px; height: 52px; font-size: 1.05rem; background: {{ $tono($c->cliente_id) }}">{{ $iniciales($c->cliente->nombre) }}</span>
                        <div class="flex-grow-1">
                            <a class="fw-bold text-decoration-none" href="{{ route('clientes.show', $c->cliente) }}">{{ $c->cliente->nombre ?? 'Sin nombre' }}</a>
                            <div class="dato small">Clienta desde {{ $c->cliente->created_at->translatedFormat('F Y') }}</div>
                        </div>
                        <a class="icono-btn" href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" title="Escribirle por WhatsApp">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 1-13.5 7.8L3 21l1.2-4.5A9 9 0 1 1 21 12z"/></svg>
                        </a>
                        <a class="icono-btn" href="tel:+{{ $tel }}" title="Llamar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>
                        </a>
                    </div>
                    <div class="dato small mt-2">WhatsApp {{ \App\Support\Telefono::legible($wa) }}</div>
                </div>

                <div class="seccion">
                    <div class="renglon fw-bold mb-1">
                        <span>{{ $c->servicio->nombre }}</span>
                        <span>{{ $c->servicio->precio !== null ? '$' . number_format((float) $c->servicio->precio, 0) : '—' }}</span>
                    </div>
                    <div class="dato">en <strong>{{ $c->seccion->nombre ?? 'Sin sección' }}</strong></div>
                    <div class="dato">a las <strong>{{ $c->inicio->format('H:i') }}</strong> por <strong>{{ $min >= 60 ? intdiv((int) $min, 60) . ' h ' . ((int) $min % 60 ? (int) $min % 60 . ' min' : '') : (int) $min . ' min' }}</strong></div>
                    @if ($c->llegada_en)
                        <div class="dato">Llegó a las <strong>{{ $c->llegada_en->format('H:i') }}</strong></div>
                    @endif
                    @if ($c->difusion_id)
                        <div class="dato estado-ok small mt-1">Espacio recuperado: agendó en un hueco que se le avisó.</div>
                    @endif
                    @if ($c->notas)
                        <div class="dato small mt-1">Nota: {{ $c->notas }}</div>
                    @endif
                </div>

                <div class="seccion">
                    <div class="fw-bold mb-2">Cambiar estado</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach (['completada' => 'Atendida', 'no_asistio' => 'No asistió', 'confirmada' => 'Confirmada'] as $valor => $texto)
                            @continue($c->estado === $valor)
                            <form method="post" action="{{ route('citas.estado', $c) }}">
                                @csrf
                                <input type="hidden" name="estado" value="{{ $valor }}">
                                <button class="btn btn-outline-secondary btn-sm">{{ $texto }}</button>
                            </form>
                        @endforeach
                    </div>
                    @if ($c->inicio->isFuture())
                        <form method="post" action="{{ route('citas.cancelar', $c) }}" class="mt-3" onsubmit="return confirm('¿Cancelar esta cita? El horario quedará libre.')">
                            @csrf
                            <div class="form-check small">
                                <input class="form-check-input" type="checkbox" name="avisar" value="1" id="avisar{{ $c->id }}" checked>
                                <label class="form-check-label" for="avisar{{ $c->id }}">Avisarle por WhatsApp</label>
                            </div>
                            <button class="btn btn-sm btn-outline-danger mt-1">Cancelar cita</button>
                        </form>
                    @endif
                </div>

                <div class="seccion dato small">
                    <div class="fw-bold text-dark mb-1">Detalles</div>
                    <div>Agendada el {{ $c->created_at->translatedFormat('D j \d\e M, H:i') }}</div>
                    <div>Por <span class="origen {{ $c->origen }}">{{ $origenes[$c->origen] ?? $c->origen }}</span></div>
                    <div>Actualizada el {{ $c->updated_at->translatedFormat('D j \d\e M, H:i') }}</div>
                </div>
            </div>
        @endforeach
    </aside>
</div>
@endsection

@push('scripts')
<script>
// Abrir el detalle sin recargar; la URL guarda la cita elegida para que la
// recarga automática (cada 20 s si hay cambios) la conserve.
(function () {
    const resumen = document.getElementById('resumen');
    const detalles = document.querySelectorAll('.detalle');
    const citas = document.querySelectorAll('[data-abrir]');

    function mostrar(id) {
        detalles.forEach(d => d.hidden = d.dataset.cita !== id);
        resumen.hidden = !!id;
        citas.forEach(c => c.classList.toggle('sel', c.dataset.abrir === id));
        const url = new URL(location.href);
        id ? url.searchParams.set('cita', id) : url.searchParams.delete('cita');
        history.replaceState(null, '', url);
        hoja();
    }

    // En celular y tableta el detalle se abre como hoja inferior.
    const movil = window.matchMedia('(max-width: 991.98px)');
    function hoja() {
        document.body.classList.toggle('con-hoja', movil.matches && !!document.querySelector('.detalle:not([hidden])'));
    }
    hoja();
    movil.addEventListener ? movil.addEventListener('change', hoja) : movil.addListener(hoja);

    citas.forEach(c => c.addEventListener('click', e => { e.preventDefault(); mostrar(c.dataset.abrir); }));
    document.querySelectorAll('[data-cerrar]').forEach(b => b.addEventListener('click', e => { e.preventDefault(); mostrar(null); }));
})();
</script>
@include('agenda._vivo', ['desde' => $dia->toDateString(), 'hasta' => $dia->toDateString()])
@endpush
