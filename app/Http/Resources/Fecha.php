<?php

namespace App\Http\Resources;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/** Todas las fechas salen en ISO 8601 con el desfase de America/Mexico_City. */
final class Fecha
{
    public static function iso(?DateTimeInterface $fecha): ?string
    {
        return $fecha ? Carbon::instance($fecha)->setTimezone(config('app.timezone'))->toIso8601String() : null;
    }
}
