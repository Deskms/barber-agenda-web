@extends('layouts.app')

@section('titulo', 'Entrar')

@section('contenido')
<div class="login-tn">
    <h1>TuNegocio<span>Citas</span></h1>
    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif
    <form method="post" action="{{ url('/login') }}">
        @csrf
        <label for="email">Correo</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        <label for="password">Contraseña</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
        <label class="check"><input type="checkbox" name="recordar" value="1"> Recordarme en este equipo</label>
        <button class="entrar">Entrar</button>
    </form>
</div>
@endsection
