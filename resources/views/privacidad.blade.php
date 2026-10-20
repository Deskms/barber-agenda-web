@extends('layouts.app')

@section('titulo', 'Aviso de privacidad')

@section('contenido')
<div class="tarjeta p-4" style="max-width: 760px">
    <h1 class="h4">Aviso de privacidad</h1>
    <p><strong>{{ config('whatsapp.negocio.nombre') }}</strong> usa este sistema para agendar citas por WhatsApp y desde su panel interno.</p>
    <h2 class="h6 mt-3">Qué datos guardamos</h2>
    <p>Tu número de WhatsApp, el nombre de tu perfil, los mensajes que nos envías a este número y tus citas (servicio, día y hora).</p>
    <h2 class="h6 mt-3">Para qué los usamos</h2>
    <p>Para agendar, confirmar, recordar y cancelar tus citas. Si lo aceptas, también para avisarte cuando se libera un espacio. Puedes dejar de recibir esos avisos escribiendo <strong>BAJA</strong> en cualquier momento.</p>
    <h2 class="h6 mt-3">Con quién los compartimos</h2>
    <p>Los mensajes viajan por WhatsApp Business Platform de Meta. Para entender mensajes escritos con texto libre usamos un servicio de inteligencia artificial que solo recibe el texto del mensaje y el catálogo de servicios; no vendemos ni compartimos tus datos con nadie más.</p>
    <h2 class="h6 mt-3">Tus derechos</h2>
    <p>Puedes pedir que corrijamos o borremos tus datos escribiéndonos por el mismo WhatsApp.</p>
</div>
@endsection
