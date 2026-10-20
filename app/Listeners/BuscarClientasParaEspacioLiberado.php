<?php

namespace App\Listeners;

use App\Events\EspacioLiberado;
use App\Jobs\DifundirHuecoAutomatico;
use App\Models\Cita;
use App\Models\Cliente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Cuando se libera un espacio, lo registra y, si la dueña prendió los avisos
 * automáticos, manda a la cola el aviso a las clientas de esa sección.
 */
class BuscarClientasParaEspacioLiberado
{
    public function handle(EspacioLiberado $evento): void
    {
        Log::channel('whatsapp')->info('Espacio liberado', [
            'servicio' => $evento->cita->servicio?->nombre,
            'seccion'  => $evento->cita->seccion_id,
            'inicio'   => $evento->inicio()->format('Y-m-d H:i'),
            'fin'      => $evento->fin()->format('Y-m-d H:i'),
        ]);

        // Después del commit: si la transacción que liberó el hueco se
        // revierte, no se avisa de un hueco que nunca existió.
        DifundirHuecoAutomatico::dispatch(
            $evento->cita->id,
            $evento->cita->cliente_id,
            $evento->cita->seccion_id,
            $evento->inicio()->toDateTimeString(),
            $evento->fin()->toDateTimeString(),
        )->afterCommit();
    }

    /**
     * Elegibles: dieron consentimiento, no son quien canceló, y no tienen
     * ya otra cita que choque con ese mismo intervalo.
     *
     * @return Collection<int, Cliente>
     */
    public function candidatas(EspacioLiberado $evento): Collection
    {
        $inicio = $evento->inicio();
        $fin    = $evento->fin();

        $ocupadas = Cita::query()
            ->vigentes()
            ->queChocanCon($inicio, $fin)
            ->pluck('cliente_id');

        return Cliente::query()
            ->notificables()
            ->whereKeyNot($evento->cita->cliente_id)
            ->whereNotIn('id', $ocupadas)
            ->get();
    }
}
