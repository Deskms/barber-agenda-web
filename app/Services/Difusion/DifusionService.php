<?php

namespace App\Services\Difusion;

use App\Exceptions\WhatsAppNoConfiguradoException;
use App\Models\Cliente;
use App\Models\Difusion;
use App\Models\EventoWhatsapp;
use App\Models\Hueco;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Envía el aviso de espacio liberado a las clientas que eligió la dueña.
 * La difusión nunca es automática: solo se llama desde el panel (SCRUM-35/36).
 */
class DifusionService
{
    public const PLANTILLA = 'espacio_liberado';

    public function __construct(
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function configurado(): bool
    {
        return filled(config('whatsapp.access_token')) && filled(config('whatsapp.phone_id'));
    }

    /**
     * @param  Collection<int, Cliente>  $clientes  Ya filtradas como elegibles.
     * @return array{enviadas: array<int,int>, fallidas: array<int, array{cliente_id:int, error:string}>}
     */
    public function difundir(Hueco $hueco, Collection $clientes): array
    {
        if (! $this->configurado()) {
            throw new WhatsAppNoConfiguradoException('WhatsApp no está configurado en este servidor.');
        }

        $plantilla = config('whatsapp.plantillas.'.self::PLANTILLA);
        $inicio = $hueco->inicio->copy()->locale('es');
        $enviadas = [];
        $fallidas = [];

        foreach ($clientes as $cliente) {
            $parametros = [
                $cliente->nombre ?: 'hola',
                $hueco->servicio?->nombre ?? 'tu servicio',
                $inicio->translatedFormat('l j \d\e F'),
                $inicio->format('H:i'),
            ];

            try {
                $respuesta = $this->whatsapp->enviarPlantilla(
                    $cliente->wa_id,
                    $plantilla['name'] ?? self::PLANTILLA,
                    $plantilla['language'] ?? 'es_MX',
                    $parametros,
                );
                $ok = $respuesta->successful();
                $wamid = $respuesta->json('messages.0.id');
                $error = $ok ? null : (string) ($respuesta->json('error.message') ?? 'Error HTTP '.$respuesta->status());
            } catch (Throwable $e) {
                $ok = false;
                $wamid = null;
                $error = $e->getMessage();
            }

            Difusion::create([
                'espacio_liberado_id' => $hueco->id,
                'cliente_id' => $cliente->id,
                'plantilla' => self::PLANTILLA,
                'estado_envio' => $ok ? Difusion::ENVIADO : Difusion::FALLIDO,
                'wamid' => $wamid,
                'error' => $error,
                'enviada_en' => $ok ? Carbon::now() : null,
            ]);

            if ($ok && $wamid) {
                EventoWhatsapp::firstOrCreate(
                    ['wamid' => $wamid],
                    [
                        'cliente_id' => $cliente->id,
                        'tipo' => EventoWhatsapp::TIPO_MENSAJE,
                        'direccion' => EventoWhatsapp::SALIENTE,
                        'payload' => ['plantilla' => self::PLANTILLA, 'parametros' => $parametros],
                    ],
                );
            }

            $ok ? $enviadas[] = $cliente->id : $fallidas[] = ['cliente_id' => $cliente->id, 'error' => $error];
        }

        return ['enviadas' => $enviadas, 'fallidas' => $fallidas];
    }
}
