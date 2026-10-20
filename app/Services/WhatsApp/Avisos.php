<?php

namespace App\Services\WhatsApp;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Difusion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Mensajes que inicia el negocio. Fuera de la ventana de 24 h Meta solo
 * acepta plantillas aprobadas, así que todo lo de aquí sale por plantilla.
 */
class Avisos
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function confirmacion(Cita $cita): bool
    {
        return $this->plantilla('confirmacion_cita', $cita);
    }

    public function recordatorio(Cita $cita): bool
    {
        return $this->plantilla('recordatorio_cita', $cita);
    }

    public function cancelacion(Cita $cita): bool
    {
        return $this->plantilla('cancelacion', $cita, 'cita_cancelada');
    }

    /**
     * Avisa de un hueco a las clientas elegidas por la dueña.
     *
     * El índice único (cita_id, cliente_id) de difusiones es el que evita
     * avisarle dos veces a la misma clienta del mismo hueco, aunque la
     * dueña presione el botón dos veces.
     *
     * @param  Collection<int, Cliente>  $clientas
     * @return array{enviadas:int, fallidas:int, omitidas:int}
     */
    public function difundirHueco(Cita $cancelada, Carbon $inicio, Collection $clientas): array
    {
        $resultado = ['enviadas' => 0, 'fallidas' => 0, 'omitidas' => 0];
        $servicio  = $cancelada->servicio;

        foreach ($clientas as $clienta) {
            $difusion = Difusion::firstOrCreate(
                ['cita_id' => $cancelada->id, 'cliente_id' => $clienta->id],
                ['inicio' => $inicio, 'fin' => $cancelada->fin, 'plantilla' => 'espacio_liberado'],
            );

            if (! $difusion->wasRecentlyCreated) {
                $resultado['omitidas']++;

                continue;
            }

            $respuesta = $this->whatsapp->enviarPlantilla(
                $clienta->wa_id,
                'espacio_liberado',
                config('whatsapp.plantillas.espacio_liberado.language', 'es_MX'),
                [$this->nombre($clienta), $servicio->nombre, $this->dia($inicio), $inicio->format('H:i')],
            );

            $difusion->update($respuesta->successful()
                ? ['estado' => 'enviada', 'wamid' => data_get($respuesta->json(), 'messages.0.id')]
                : ['estado' => 'fallida', 'error' => Str::limit((string) data_get($respuesta->json(), 'error.message', 'HTTP ' . $respuesta->status()), 480)]);

            $respuesta->successful() ? $resultado['enviadas']++ : $resultado['fallidas']++;
        }

        return $resultado;
    }

    private function plantilla(string $tipo, Cita $cita, ?string $nombre = null): bool
    {
        $nombre ??= $tipo === 'cancelacion' ? 'cita_cancelada' : $tipo;
        $cita->loadMissing(['cliente', 'servicio']);

        if (! $cita->cliente?->wa_id || ! config('whatsapp.access_token')) {
            return false;
        }

        return $this->whatsapp->enviarPlantilla(
            $cita->cliente->wa_id,
            $nombre,
            config("whatsapp.plantillas.{$nombre}.language", 'es_MX'),
            [$this->nombre($cita->cliente), $cita->servicio->nombre, $this->dia($cita->inicio), $cita->inicio->format('H:i')],
        )->successful();
    }

    private function nombre(Cliente $cliente): string
    {
        return $cliente->nombre ? (string) Str::of($cliente->nombre)->before(' ') : '😊';
    }

    private function dia(Carbon $dia): string
    {
        return $dia->translatedFormat('l j \d\e F');
    }
}
