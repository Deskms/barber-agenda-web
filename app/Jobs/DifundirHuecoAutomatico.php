<?php

namespace App\Jobs;

use App\Services\Marketing\Campanas;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Avisa de un hueco en segundo plano, para que cancelar o mover una cita
 * desde el panel o WhatsApp no espere a que salgan los mensajes.
 *
 * Recibe el intervalo como valores y no como modelo: al mover una cita el
 * modelo ya trae el horario nuevo, y lo que se avisa es el que quedó libre.
 */
class DifundirHuecoAutomatico implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $citaId,
        public readonly ?int $excluirClienteId,
        public readonly ?int $seccionId,
        public readonly string $inicio,
        public readonly string $fin,
    ) {}

    public function handle(Campanas $campanas): void
    {
        $r = $campanas->difundirHuecoAutomatico(
            $this->citaId,
            $this->excluirClienteId,
            $this->seccionId,
            Carbon::parse($this->inicio),
            Carbon::parse($this->fin),
        );

        if ($r['gratis'] + $r['pagadas'] + $r['omitidas'] > 0) {
            Log::channel('whatsapp')->info('Hueco avisado automáticamente', ['cita_id' => $this->citaId, 'inicio' => $this->inicio] + $r);
        }
    }
}
