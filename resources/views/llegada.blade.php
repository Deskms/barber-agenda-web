@extends('layouts.app')

@section('titulo', 'Registrar llegada')

@section('contenido')
<div style="max-width: 420px" class="mx-auto text-center">
    <h1 class="h4 fw-bold mt-2">{{ config('whatsapp.negocio.nombre') }}</h1>
    <div class="tarjeta p-4 mt-3">
        <p class="mb-2">Para registrar tu llegada, abre la app <strong>Barber Agenda</strong> y toca <strong>Ya llegué</strong> para escanear este código.</p>
        <p class="small text-secondary mb-0">Si no tienes la app, avísale a la persona que te atiende.</p>
    </div>
</div>
@endsection
