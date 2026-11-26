<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Administra las plantillas de mensaje de la WABA.
 *
 * Ojo con la diferencia de endpoints, porque es la que confunde:
 *
 *   /{PHONE_NUMBER_ID}/messages           → ENVIAR un mensaje   (WhatsAppService)
 *   /{WABA_ID}/message_templates          → CREAR o LISTAR plantillas (aquí)
 *
 * Una plantilla no vive en el número, vive en la cuenta de WhatsApp Business.
 * Por eso aquí el identificador es el WABA_ID y no el PHONE_NUMBER_ID.
 */
class PlantillaService
{
    /** @return Response Lista de plantillas con su estado de revisión. */
    public function listar(int $limite = 50): Response
    {
        return $this->cliente()->get($this->endpoint(), [
            'fields' => 'name,language,category,status,quality_score,components',
            'limit'  => $limite,
        ]);
    }

    /**
     * Manda una plantilla a revisión de Meta.
     *
     * Reglas de formato que Meta rechaza sin piedad:
     *   - el cuerpo no puede empezar ni terminar con una variable
     *   - no puede haber dos variables seguidas ({{1}} {{2}})
     *   - cada variable necesita un valor de ejemplo
     *
     * @param  array{name:string,language:string,category:string,body:string,ejemplos:array<int,string>}  $definicion
     */
    public function crear(array $definicion): Response
    {
        $payload = ! empty($definicion['autenticacion'])
            ? $this->payloadAutenticacion($definicion)
            : $this->payloadNormal($definicion);

        $respuesta = $this->cliente()->post($this->endpoint(), $payload);

        $contexto = [
            'plantilla' => $definicion['name'],
            'status'    => $respuesta->status(),
            'cuerpo'    => $respuesta->json(),
        ];

        $respuesta->successful()
            ? Log::channel('whatsapp')->info('Plantilla enviada a revisión', $contexto)
            : Log::channel('whatsapp')->error('Fallo al crear plantilla', $contexto);

        return $respuesta;
    }

    /**
     * Formato que Meta exige para AUTHENTICATION: cuerpo predefinido con la
     * recomendación de seguridad, pie con la vigencia y botón de copiar código.
     */
    public function payloadAutenticacion(array $definicion): array
    {
        $minutos = (int) ($definicion['vence_minutos'] ?? 10);

        return [
            'name'                     => $definicion['name'],
            'language'                 => $definicion['language'],
            'category'                 => 'AUTHENTICATION',
            'message_send_ttl_seconds' => $minutos * 60,
            'components'               => [
                ['type' => 'BODY', 'add_security_recommendation' => true],
                ['type' => 'FOOTER', 'code_expiration_minutes' => $minutos],
                ['type' => 'BUTTONS', 'buttons' => [[
                    'type'     => 'OTP',
                    'otp_type' => 'COPY_CODE',
                    'text'     => $definicion['texto_boton'] ?? 'Copiar código',
                ]]],
            ],
        ];
    }

    private function payloadNormal(array $definicion): array
    {
        return [
            'name'       => $definicion['name'],
            'language'   => $definicion['language'],
            'category'   => strtoupper($definicion['category']),
            'components' => [[
                'type'    => 'BODY',
                'text'    => $definicion['body'],
                'example' => ['body_text' => [array_values($definicion['ejemplos'])]],
            ]],
        ];
    }

    public function eliminar(string $nombre): Response
    {
        // Meta espera el nombre como query string, no como cuerpo JSON.
        return $this->cliente()->delete($this->endpoint() . '?name=' . urlencode($nombre));
    }

    private function endpoint(): string
    {
        return sprintf(
            'https://graph.facebook.com/%s/%s/message_templates',
            config('whatsapp.api_version'),
            config('whatsapp.waba_id'),
        );
    }

    private function cliente()
    {
        return Http::withToken(config('whatsapp.access_token'))
            ->acceptJson()
            ->timeout(20);
    }
}
