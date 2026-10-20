<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Único punto desde el que sale un mensaje hacia WhatsApp.
 *
 * Dos formas de enviar, y la diferencia es una regla de Meta, no nuestra:
 *
 *   texto libre  → solo dentro de la ventana de 24 h que abre la clienta
 *                  cuando ella escribe primero.
 *   plantilla    → obligatoria para iniciar una conversación (recordatorios,
 *                  confirmaciones, avisos de espacio liberado). Debe estar
 *                  aprobada por Meta antes de poder usarse.
 */
class WhatsAppService
{
    public function enviarTexto(string $waId, string $texto): Response
    {
        return $this->enviar([
            'messaging_product' => 'whatsapp',
            'to'                => $waId,
            'type'              => 'text',
            'text'              => ['preview_url' => false, 'body' => $texto],
        ]);
    }

    /**
     * @param  array<int, string>  $parametros  Valores para los {{1}}, {{2}}… del cuerpo.
     */
    public function enviarPlantilla(
        string $waId,
        string $plantilla,
        string $idioma = 'es_MX',
        array $parametros = [],
    ): Response {
        $template = [
            'name'     => $plantilla,
            'language' => ['code' => $idioma],
        ];

        if ($parametros !== []) {
            $template['components'] = [[
                'type'       => 'body',
                'parameters' => array_map(
                    fn ($valor) => ['type' => 'text', 'text' => (string) $valor],
                    $parametros,
                ),
            ]];
        }

        return $this->enviar([
            'messaging_product' => 'whatsapp',
            'to'                => $waId,
            'type'              => 'template',
            'template'          => $template,
        ]);
    }

    /**
     * Hasta 3 botones de respuesta rápida. El id regresa en el webhook
     * como interactive.button_reply.id cuando la clienta toca uno.
     *
     * @param  array<string, string>  $botones  id => título (máx. 20 caracteres)
     */
    public function enviarBotones(string $waId, string $texto, array $botones): Response
    {
        return $this->enviar([
            'messaging_product' => 'whatsapp',
            'to'                => $waId,
            'type'              => 'interactive',
            'interactive'       => [
                'type'   => 'button',
                'body'   => ['text' => $texto],
                'action' => ['buttons' => collect($botones)->take(3)->map(fn ($titulo, $id) => [
                    'type'  => 'reply',
                    'reply' => ['id' => (string) $id, 'title' => mb_substr($titulo, 0, 20)],
                ])->values()->all()],
            ],
        ]);
    }

    /**
     * Lista desplegable de hasta 10 opciones. Meta rechaza títulos de fila
     * de más de 24 caracteres y descripciones de más de 72.
     *
     * @param  array<int, array{id:string,title:string,description?:string}>  $filas
     */
    public function enviarLista(string $waId, string $texto, string $boton, array $filas, string $seccion = 'Opciones'): Response
    {
        $filas = collect($filas)->take(10)->map(fn (array $f) => array_filter([
            'id'          => $f['id'],
            'title'       => mb_substr($f['title'], 0, 24),
            'description' => isset($f['description']) ? mb_substr($f['description'], 0, 72) : null,
        ]))->values()->all();

        return $this->enviar([
            'messaging_product' => 'whatsapp',
            'to'                => $waId,
            'type'              => 'interactive',
            'interactive'       => [
                'type'   => 'list',
                'body'   => ['text' => $texto],
                'action' => [
                    'button'   => mb_substr($boton, 0, 20),
                    'sections' => [['title' => mb_substr($seccion, 0, 24), 'rows' => $filas]],
                ],
            ],
        ]);
    }

    private function endpoint(): string
    {
        return sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('whatsapp.api_version'),
            config('whatsapp.phone_id'),
        );
    }

    private function enviar(array $payload): Response
    {
        // withToken() antepone "Bearer " solo. Es el mismo encabezado que
        // faltaba cuando la Graph API contestó "object does not exist".
        $respuesta = Http::withToken(config('whatsapp.access_token'))
            ->acceptJson()
            ->timeout(15)
            ->post($this->endpoint(), $payload);

        $contexto = [
            'to'     => $payload['to'],
            'tipo'   => $payload['type'],
            'status' => $respuesta->status(),
            'cuerpo' => $respuesta->json(),
        ];

        if ($respuesta->successful()) {
            Log::channel('whatsapp')->info('Mensaje enviado', $contexto);
        } else {
            Log::channel('whatsapp')->error('Fallo al enviar mensaje', $contexto);
        }

        return $respuesta;
    }
}
