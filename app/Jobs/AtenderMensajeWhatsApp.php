<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Services\Bot\BotConversacion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * La respuesta del bot sale de la cola y no dentro del webhook: Meta espera
 * un 200 en segundos, y consultar a la IA o a la agenda puede tardar más.
 */
class AtenderMensajeWhatsApp implements ShouldQueue
{
    use Queueable;

    /** Un reintento enviaría mensajes duplicados a la clienta. */
    public int $tries = 1;

    public function __construct(
        public readonly int $clienteId,
        public readonly array $mensaje,
    ) {}

    public function handle(BotConversacion $bot): void
    {
        $cliente = Cliente::find($this->clienteId);

        if ($cliente) {
            $bot->atender($cliente, $this->mensaje);
        }
    }
}
