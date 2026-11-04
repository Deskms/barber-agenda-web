<?php

namespace App\Support;

/**
 * Normaliza teléfonos para que la misma clienta no quede duplicada entre la
 * web, el panel y la app Android (acordarlo también con el backend móvil).
 *
 * México: se guardan los 10 dígitos nacionales ("33 1234 5678", "+52 33 1234
 * 5678" y "+52 1 33 1234 5678" quedan como "3312345678").
 * Otros países: dígitos del formato internacional sin el "+".
 */
final class Telefono
{
    public static function normalizar(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefono);

        if ($digitos === '' || $digitos === null) {
            return null;
        }

        if (strlen($digitos) === 10) {
            return $digitos;
        }

        // +52 33 1234 5678
        if (strlen($digitos) === 12 && str_starts_with($digitos, '52')) {
            return substr($digitos, 2);
        }

        // +52 1 33 1234 5678 (formato móvil antiguo)
        if (strlen($digitos) === 13 && str_starts_with($digitos, '521')) {
            return substr($digitos, 3);
        }

        if (strlen($digitos) >= 11 && strlen($digitos) <= 15) {
            return $digitos;
        }

        return null;
    }
}
