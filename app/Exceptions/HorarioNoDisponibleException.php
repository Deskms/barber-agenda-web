<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando el intervalo solicitado no puede agendarse:
 * queda fuera del horario de atención o acaba de ser ocupado por alguien más.
 */
class HorarioNoDisponibleException extends RuntimeException
{
}
