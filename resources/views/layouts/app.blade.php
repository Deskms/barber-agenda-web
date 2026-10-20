<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('titulo', 'Agenda') · {{ config('whatsapp.negocio.nombre') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/panel.css') }}">
</head>
<body>
@auth
<nav class="navbar navbar-expand-lg navbar-dark barra">
    <div class="container-lg">
        <a class="navbar-brand fw-bold marca" href="{{ route('agenda') }}">TuNegocio<span>Citas</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-label="Menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link @if(request()->routeIs('agenda')) active @endif" href="{{ route('agenda') }}">Hoy</a></li>
                <li class="nav-item"><a class="nav-link @if(request()->routeIs('agenda.semana')) active @endif" href="{{ route('agenda.semana') }}">Semana</a></li>
                <li class="nav-item"><a class="nav-link @if(request()->routeIs('huecos*')) active @endif" href="{{ route('huecos') }}">Espacios libres</a></li>
                <li class="nav-item"><a class="nav-link @if(request()->routeIs('clientes*')) active @endif" href="{{ route('clientes') }}">Clientas</a></li>
                <li class="nav-item"><a class="nav-link @if(request()->routeIs('servicios')) active @endif" href="{{ route('servicios') }}">Servicios</a></li>
                <li class="nav-item"><a class="nav-link @if(request()->routeIs('horarios')) active @endif" href="{{ route('horarios') }}">Horario</a></li>
                <li class="nav-item"><a class="nav-link @if(request()->routeIs('whatsapp')) active @endif" href="{{ route('whatsapp') }}">WhatsApp</a></li>
            </ul>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-sm btn-outline-light">Salir</button>
            </form>
        </div>
    </div>
</nav>
@endauth

<main class="container-lg py-3">
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
