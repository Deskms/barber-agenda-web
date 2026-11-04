<?php

namespace App\Listeners;

use App\Events\EspacioLiberado;
use App\Models\Cita;
use App\Models\Cliente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Cuando se libera un espacio, arma la lista de clientas a las que tiene
 * sentido avisarles.
 *
 * De momento solo registra la lista en el log. El envío real requiere
 * plantillas aprobadas por Meta, y eso es la siguiente fase.
 */
class BuscarClientasParaEspacioLiberado
{
    public function handle(EspacioLiberado $evento): void
    {
        $candidatas = $this->candidatas($evento);

        Log::channel('whatsapp')->info('Espacio liberado', [
            'servicio' => $evento->cita->servicio?->nombre,
            'inicio' => $evento->inicio()->format('Y-m-d H:i'),
            'fin' => $evento->fin()->format('Y-m-d H:i'),
            'candidatas' => $candidatas->map(fn (Cliente $c) => [
                'wa_id' => $c->wa_id,
                'nombre' => $c->nombre,
            ])->all(),
        ]);
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
        $fin = $evento->fin();

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
