@extends('layouts.app')

@section('titulo', 'Entrar')

@section('contenido')
<div class="row justify-content-center mt-5">
    <div class="col-sm-8 col-md-5">
        <div class="tarjeta p-4">
            <h1 class="h4 mb-1">✂️ {{ config('whatsapp.negocio.nombre') }}</h1>
            <p class="text-secondary mb-4">Panel de la agenda</p>
            <form method="post" action="{{ url('/login') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Correo</label>
                    <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Contraseña</label>
                    <input class="form-control" id="password" name="password" type="password" required autocomplete="current-password">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="recordar" value="1" id="recordar">
                    <label class="form-check-label" for="recordar">Mantener sesión en este dispositivo</label>
                </div>
                <button class="btn btn-acento w-100">Entrar</button>
            </form>
        </div>
    </div>
</div>
@endsection
