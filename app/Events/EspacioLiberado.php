<?php

namespace App\Events;

use App\Models\Cita;
use App\Models\Hueco;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Un intervalo de la agenda quedó libre (cancelación o reprogramación).
 *
 * Este evento mantiene separada la agenda de quien aprovecha el hueco: la
 * agenda solo anuncia que hay un horario libre (citas de última hora).
 *
 * Al reprogramar, la cita ya tiene su horario nuevo; por eso el evento puede
 * llevar el intervalo anterior de forma explícita.
 */
class EspacioLiberado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Cita $cita,
        public readonly ?Carbon $inicioLiberado = null,
        public readonly ?Carbon $finLiberado = null,
        public readonly string $motivo = Hueco::MOTIVO_CANCELACION,
    ) {}

    public function inicio(): Carbon
    {
        return $this->inicioLiberado ?? $this->cita->inicio;
    }

    public function fin(): Carbon
    {
        return $this->finLiberado ?? $this->cita->fin;
    }
}
