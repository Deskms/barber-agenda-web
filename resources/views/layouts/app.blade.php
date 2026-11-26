<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Barber Agenda">
    <meta name="format-detection" content="telephone=no">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#f9f9fb">
    <title>@yield('titulo', 'Agenda') · {{ config('whatsapp.negocio.nombre') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    {{-- La fuente se carga sin bloquear: con datos móviles la página se pinta de inmediato con la fuente del sistema. --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"></noscript>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/panel.css') }}?v={{ filemtime(public_path('css/panel.css')) }}">
</head>
<body>
@auth
@php
    // Íconos de línea propios (24x24, trazo 2).
    $iconos = [
        'agenda'      => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'semana'      => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v11M15 10v11"/>',
        'tablero'     => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'reportes'    => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
        'huecos'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'promociones' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1z"/><path d="M15 9a4 4 0 0 1 0 6M18 6a8 8 0 0 1 0 12"/>',
        'clientas'    => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 4a4 4 0 0 1 0 8M22 21a7 7 0 0 0-4-6.3"/>',
        'servicios'   => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4 8.1 15.9M14.5 14.5 20 20M8.1 8.1 12 12"/>',
        'horario'     => '<circle cx="12" cy="12" r="9"/><path d="M12 6v6h5"/>',
        'whatsapp'    => '<path d="M21 12a9 9 0 0 1-13.5 7.8L3 21l1.2-4.5A9 9 0 1 1 21 12z"/>',
    ];
    $menu = [
        ['agenda', 'Agenda', 'agenda', ['agenda', 'agenda.semana']],
        ['tablero', 'Tablero', 'tablero', ['tablero']],
        ['reportes', 'Reportes', 'reportes', ['reportes*']],
        ['huecos', 'Espacios', 'huecos', ['huecos*']],
        ['clientas', 'Clientas', 'clientes', ['clientes*']],
        ['promociones', 'Promociones', 'promociones', ['promociones*']],
        ['servicios', 'Servicios', 'servicios', ['servicios']],
        ['horario', 'Horario', 'horarios', ['horarios']],
        ['whatsapp', 'WhatsApp', 'whatsapp', ['whatsapp']],
    ];
    $usuario = auth()->user();
    $iniciales = collect(explode(' ', trim($usuario->name ?? 'U')))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
@endphp
<nav class="navbar navbar-expand-xl navbar-light barra">
    <div class="container-fluid px-3">
        <a class="navbar-brand marca me-3" href="{{ route('agenda') }}">
            <span class="logo">BA</span> Barber<span>Agenda</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-label="Menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav me-auto gap-1">
                @foreach ($menu as [$icono, $texto, $ruta, $patrones])
                    <li class="nav-item">
                        <a class="nav-link @if(request()->routeIs(...$patrones)) active @endif" href="{{ route($ruta) }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconos[$icono] !!}</svg>
                            {{ $texto }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <a class="btn btn-sm btn-outline-primary btn-landing me-xl-3 my-2 my-xl-0" href="{{ asset('landing/index.html') }}" target="_blank" rel="noopener" title="Ver la página pública del salón">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
                Landing page
            </a>
            <a class="btn btn-sm btn-outline-primary btn-landing btn-presentacion me-xl-3 my-2 my-xl-0" href="{{ asset('presentacion/index.html') }}" target="_blank" rel="noopener" title="Abrir la presentación del proyecto">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M12 16v4M8 20h8M10 8l4 2-4 2z"/></svg>
                Presentación
            </a>
            <div class="usuario py-2 py-xl-0">
                <span class="avatar claro">{{ mb_strtoupper($iniciales) }}</span>
                <div class="me-2">
                    <div class="fw-bold">{{ $usuario->name }}</div>
                    <small>{{ config('whatsapp.negocio.nombre') }}</small>
                </div>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary">Salir</button>
                </form>
            </div>
        </div>
    </div>
</nav>
@endauth

<main class="@yield('ancho', 'container-lg') py-3">
    @if (session('ok'))
        <div class="alert alert-success">{{ session('ok') }}</div>
    @endif
    @if ($errors->any() && ! request()->routeIs('login'))
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @yield('contenido')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
