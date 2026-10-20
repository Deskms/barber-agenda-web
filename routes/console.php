<?php

use Illuminate\Support\Facades\Schedule;

// Cada hora en horario razonable: si una corrida falla, la siguiente
// reintenta, y el UPDATE condicional evita recordatorios duplicados.
Schedule::command('citas:recordar')
    ->hourlyAt(5)
    ->between('9:00', '20:00')
    ->withoutOverlapping();

// "Ya te toca volver": una vez al día, a media mañana. El comando no hace
// nada si la dueña lo tiene apagado en Promociones.
Schedule::command('campanas:regresos')
    ->dailyAt('11:00')
    ->withoutOverlapping();
