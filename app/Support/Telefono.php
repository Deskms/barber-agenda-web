<?php

namespace App\Support;

/**
 * Normaliza teléfonos al formato wa_id que usa WhatsApp.
 *
 * WhatsApp identifica a los números móviles de México como 521 + 10 dígitos.
 * Si la web guardara "3312345678" y el webhook "5213312345678", la misma
 * clienta quedaría duplicada. Por eso todos los canales deben normalizar igual
 * (acordarlo también con el backend móvil).
 */
final class Telefono
{
    public static function normalizar(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefono);

        if ($digitos === '' || $digitos === null) {
            return null;
        }

        // 10 dígitos: número nacional de México.
        if (strlen($digitos) === 10) {
            return '521'.$digitos;
        }

        // 52 + 10 dígitos sin el 1 móvil.
        if (strlen($digitos) === 12 && str_starts_with($digitos, '52')) {
            return '521'.substr($digitos, 2);
        }

        // Otros formatos internacionales válidos (E.164 sin el +).
        if (strlen($digitos) >= 11 && strlen($digitos) <= 15) {
            return $digitos;
        }

        return null;
    }
}
