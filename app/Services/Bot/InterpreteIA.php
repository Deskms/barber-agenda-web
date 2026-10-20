<?php

namespace App\Services\Bot;

use Anthropic\Client;
use App\Models\Servicio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Convierte un mensaje de texto libre ("¿tienes lugar el sábado para corte?")
 * en una intención estructurada.
 *
 * La IA solo clasifica. No contesta a la clienta, no inventa horarios y no
 * escribe en la base: el bot toma la intención y vuelve a pasar por
 * DisponibilidadService y CitaService, que son la única fuente de verdad.
 */
class InterpreteIA
{
    public const INTENCIONES = ['agendar', 'mis_citas', 'cancelar', 'info', 'saludo', 'baja', 'alta', 'otro'];

    public function disponible(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * @param  Collection<int, Servicio>  $servicios
     * @return array{intencion:string,servicio_id:?int,fecha:?string,hora:?string}|null
     */
    public function interpretar(string $texto, Collection $servicios, ?Carbon $ahora = null): ?array
    {
        if (! $this->disponible()) {
            return null;
        }

        $ahora ??= Carbon::now();

        $catalogo = $servicios
            ->map(fn (Servicio $s) => "- id {$s->id}: {$s->nombre} ({$s->duracion_minutos} min)")
            ->implode("\n");

        $sistema = <<<TXT
        Clasificas mensajes de WhatsApp que recibe una estética y barbería en México para agendar citas.
        No respondes a la clienta: solo devuelves la intención en el formato pedido.

        Intenciones:
        - agendar: quiere una cita o pregunta si hay lugar.
        - mis_citas: quiere ver sus citas.
        - cancelar: quiere cancelar o mover una cita.
        - info: pregunta horarios de atención, precios, servicios o ubicación.
        - saludo: solo saluda o agradece.
        - baja: pide no recibir más avisos ni promociones.
        - alta: pide volver a recibir avisos.
        - otro: cualquier otra cosa, incluidas preguntas ajenas al negocio.

        servicio_id: el id del catálogo que mejor corresponda, o null si no menciona servicio.
        fecha: YYYY-MM-DD si menciona un día ("mañana", "el sábado"), resuelto contra la fecha actual; null si no.
        hora: HH:MM en 24 h si menciona una hora; null si no. "En la tarde" sin hora exacta es null.
        TXT;

        $usuario = "Fecha y hora actual: {$ahora->translatedFormat('l j \d\e F \d\e Y, H:i')} ({$ahora->toDateString()}).\n"
            . "Catálogo:\n{$catalogo}\n\nMensaje de la clienta:\n<mensaje>{$texto}</mensaje>";

        try {
            $client = new Client(apiKey: config('services.anthropic.key'));

            $respuesta = $client->messages->create(
                model: config('services.anthropic.model'),
                maxTokens: 4000,
                system: $sistema,
                messages: [['role' => 'user', 'content' => $usuario]],
                outputConfig: [
                    'effort' => config('services.anthropic.effort'),
                    'format' => [
                        'type'   => 'json_schema',
                        'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'intencion'   => ['type' => 'string', 'enum' => self::INTENCIONES],
                                'servicio_id' => ['type' => ['integer', 'null']],
                                'fecha'       => ['type' => ['string', 'null']],
                                'hora'        => ['type' => ['string', 'null']],
                            ],
                            'required'             => ['intencion', 'servicio_id', 'fecha', 'hora'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            );

            if ($respuesta->stopReason !== 'end_turn') {
                Log::channel('whatsapp')->warning('La IA no terminó la clasificación', ['stop_reason' => $respuesta->stopReason]);

                return null;
            }

            foreach ($respuesta->content as $bloque) {
                if ($bloque->type === 'text') {
                    return $this->sanear(json_decode($bloque->text, true) ?? [], $servicios);
                }
            }
        } catch (Throwable $e) {
            // Sin IA el bot sigue funcionando con su menú: nunca dejamos a la clienta sin respuesta.
            Log::channel('whatsapp')->error('Falló la interpretación con IA', ['mensaje' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * El esquema garantiza la forma, no el contenido: un id que no existe o
     * una fecha mal escrita se descartan aquí y no llegan a la agenda.
     */
    private function sanear(array $datos, Collection $servicios): ?array
    {
        $intencion = $datos['intencion'] ?? null;

        if (! in_array($intencion, self::INTENCIONES, true)) {
            return null;
        }

        $servicioId = $datos['servicio_id'] ?? null;
        $fecha      = $datos['fecha'] ?? null;
        $hora       = $datos['hora'] ?? null;

        return [
            'intencion'   => $intencion,
            'servicio_id' => $servicios->contains('id', $servicioId) ? (int) $servicioId : null,
            'fecha'       => is_string($fecha) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : null,
            'hora'        => is_string($hora) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) ? $hora : null,
        ];
    }
}
