<?php

namespace App\Support;

/**
 * WhatsApp identifica a cada persona por su wa_id: el número internacional
 * sin "+". En México los webhooks llegan con el prefijo 521 + 10 dígitos,
 * así que normalizamos a ese formato para no duplicar clientas.
 */
class Telefono
{
    public static function aWaId(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefono);

        return match (true) {
            strlen($digitos) === 10                                  => '521' . $digitos,
            strlen($digitos) === 12 && str_starts_with($digitos, '52') => '521' . substr($digitos, 2),
            strlen($digitos) === 13 && str_starts_with($digitos, '521') => $digitos,
            strlen($digitos) >= 11 && strlen($digitos) <= 15 && ! str_starts_with($digitos, '52') => $digitos,
            default                                                  => null,
        };
    }

    /** 5213312345678 → 33 1234 5678, para mostrarlo en el panel. */
    public static function legible(?string $waId): string
    {
        if ($waId && preg_match('/^521(\d{2})(\d{4})(\d{4})$/', $waId, $m)) {
            return "{$m[1]} {$m[2]} {$m[3]}";
        }

        return (string) $waId;
    }
}
