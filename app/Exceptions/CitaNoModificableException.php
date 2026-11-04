<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La cita ya no admite el cambio solicitado (por ejemplo, cancelar una cita
 * completada o reprogramar una cita que ya pasó).
 */
class CitaNoModificableException extends RuntimeException {}
