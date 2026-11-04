<?php

/*
|--------------------------------------------------------------------------
| Reglas configurables de la agenda
|--------------------------------------------------------------------------
|
| La disponibilidad real depende de las etapas de cada servicio (tabla
| servicio_etapas): solo las etapas con ocupa_estilista = true bloquean a la
| estilista. Estos valores ajustan el resto de las reglas sin tocar código.
|
*/

return [
    // Cada cuántos minutos se ofrece un inicio de cita.
    'paso_minutos' => (int) env('AGENDA_PASO_MINUTOS', 15),

    // Máximo de clientas atendidas al mismo tiempo en el local (sillas,
    // lavabos). Vacío = sin límite: solo se valida el tiempo activo de la
    // estilista. DECISIÓN PENDIENTE con la dueña (ver docs/BACKEND_WEB_DOMINIC.md).
    'max_clientas_simultaneas' => env('AGENDA_MAX_CLIENTAS_SIMULTANEAS') !== null
        && env('AGENDA_MAX_CLIENTAS_SIMULTANEAS') !== ''
        ? (int) env('AGENDA_MAX_CLIENTAS_SIMULTANEAS')
        : null,

    // Alternativas que se ofrecen cuando un horario ya no está libre.
    'alternativas' => (int) env('AGENDA_ALTERNATIVAS', 3),

    // Días hacia adelante en los que se buscan alternativas.
    'dias_busqueda' => (int) env('AGENDA_DIAS_BUSQUEDA', 14),
];
