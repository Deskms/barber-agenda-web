<?php

namespace App\Events;

use App\Models\Cita;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Un intervalo de la agenda quedó libre (cancelación o reprogramación).
 *
 * Este evento mantiene separada la lógica de citas de la lógica de WhatsApp:
 * la agenda no sabe que existe WhatsApp, solo anuncia que hay un hueco.
 */
class EspacioLiberado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Cita $cita,
    ) {}

    public function inicio(): Carbon
    {
        return $this->cita->inicio;
    }

    public function fin(): Carbon
    {
        return $this->cita->fin;
    }
}
