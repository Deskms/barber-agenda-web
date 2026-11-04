<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando el intervalo solicitado no puede agendarse:
 * queda fuera del horario de atención, choca con un bloqueo, la estilista
 * está ocupada o alguien más lo acaba de tomar.
 */
class HorarioNoDisponibleException extends RuntimeException
{
    public function __construct(
        string $message = 'Ese horario no está disponible.',
        public readonly ?string $motivo = null,
    ) {
        parent::__construct($message);
    }
}
