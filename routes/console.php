<?php

use Illuminate\Support\Facades\Schedule;

// Cada hora en horario razonable: si una corrida falla, la siguiente
// reintenta, y el UPDATE condicional evita recordatorios duplicados.
Schedule::command('citas:recordar')
    ->hourlyAt(5)
    ->between('9:00', '20:00')
    ->withoutOverlapping();
