<?php

namespace App\Exceptions;

/**
 * La clienta acumuló demasiadas faltas y ya no puede agendar sola por la app
 * o por WhatsApp. La dueña sí puede agendarle desde el panel.
 *
 * Hereda de HorarioNoDisponibleException para que todos los canales, que ya
 * manejan esa excepción, muestren el mensaje sin código adicional.
 */
class AgendaBloqueadaException extends HorarioNoDisponibleException
{
}
