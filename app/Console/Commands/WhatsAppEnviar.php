<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;

/**
 * Envía un mensaje de prueba sin pasar por la agenda.
 *
 *   php artisan whatsapp:enviar 5213334895913 "Hola desde Laravel"
 *   php artisan whatsapp:enviar 5213334895913 --plantilla=hello_world --idioma=en_US
 */
class WhatsAppEnviar extends Command
{
    protected $signature = 'whatsapp:enviar
                            {wa_id : Número destino en formato internacional, sin + (ej. 5213334895913)}
                            {texto? : Texto libre. Solo funciona dentro de la ventana de 24 h}
                            {--plantilla= : Nombre de una plantilla aprobada}
                            {--idioma=es_MX : Código de idioma de la plantilla}
                            {--param=* : Parámetros del cuerpo de la plantilla}';

    protected $description = 'Envía un mensaje de WhatsApp de prueba usando la Cloud API';

    public function handle(WhatsAppService $whatsapp): int
    {
        $waId = $this->argument('wa_id');
        $texto = $this->argument('texto');
        $plantilla = $this->option('plantilla');

        if (! $texto && ! $plantilla) {
            $this->error('Necesitas un texto o una --plantilla.');

            return self::FAILURE;
        }

        $respuesta = $plantilla
            ? $whatsapp->enviarPlantilla($waId, $plantilla, $this->option('idioma'), $this->option('param'))
            : $whatsapp->enviarTexto($waId, $texto);

        $this->line('');

        if ($respuesta->successful()) {
            $this->info('Enviado. wamid: '.data_get($respuesta->json(), 'messages.0.id'));
            $this->line('');

            return self::SUCCESS;
        }

        $this->error('Meta rechazó el envío (HTTP '.$respuesta->status().')');
        $this->line('  '.data_get($respuesta->json(), 'error.message', 'sin detalle'));

        $codigo = data_get($respuesta->json(), 'error.code');

        $this->line('');

        match ((int) $codigo) {
            190 => $this->comment('  Código 190: el access token caducó. Genera uno nuevo en el Paso 1 de Meta.'),
            131047 => $this->comment('  Código 131047: la ventana de 24 h está cerrada. Escribe primero desde el celular al número de prueba, o usa una plantilla.'),
            131030 => $this->comment('  Código 131030: ese número no está en la lista de destinatarios autorizados.'),
            default => null,
        };

        $this->line('');

        return self::FAILURE;
    }
}
