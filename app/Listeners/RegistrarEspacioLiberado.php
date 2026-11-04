<?php

namespace App\Listeners;

use App\Events\EspacioLiberado;
use App\Models\Hueco;
use Illuminate\Support\Carbon;

/**
 * Guarda el hueco para que la dueña lo vea en el panel y decida si lo difunde.
 * Un hueco en el pasado no se registra: ya no hay nada que vender.
 */
class RegistrarEspacioLiberado
{
    public function handle(EspacioLiberado $evento): void
    {
        if ($evento->fin()->lessThanOrEqualTo(Carbon::now())) {
            return;
        }

        Hueco::create([
            'cita_id' => $evento->cita->id,
            'servicio_id' => $evento->cita->servicio_id,
            'inicio' => $evento->inicio(),
            'fin' => $evento->fin(),
            'motivo' => $evento->motivo,
        ]);
    }
}
