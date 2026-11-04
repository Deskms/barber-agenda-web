<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\EventoWhatsapp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    /**
     * GET /api/whatsapp/webhook
     * Handshake que Meta ejecuta UNA vez, al registrar la URL.
     */
    public function verify(Request $request)
    {
        $params = $request->query();

        $mode = $params['hub_mode'] ?? $params['hub.mode'] ?? null;
        $token = $params['hub_verify_token'] ?? $params['hub.verify_token'] ?? null;
        $challenge = $params['hub_challenge'] ?? $params['hub.challenge'] ?? null;

        if ($mode === 'subscribe' && $token === config('whatsapp.verify_token')) {
            Log::channel('whatsapp')->info('Webhook verificado correctamente.');

            return response($challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        Log::channel('whatsapp')->warning('Verificación de webhook rechazada', [
            'mode' => $mode,
            'ip' => $request->ip(),
        ]);

        return response('Forbidden', 403);
    }

    /**
     * POST /api/whatsapp/webhook
     *
     * Siempre responde 200, incluso si el procesamiento falla: un 500 hace que
     * Meta reintente el mismo evento y, si insiste, deshabilite la suscripción.
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        Log::channel('whatsapp')->info('Evento recibido', $payload);

        try {
            $this->procesar($payload);
        } catch (Throwable $e) {
            Log::channel('whatsapp')->error('Error al procesar el webhook', [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile().':'.$e->getLine(),
            ]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /** Un solo POST puede traer varios entries, cambios, mensajes y estados. */
    private function procesar(array $payload): void
    {
        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $value = data_get($change, 'value', []);

                foreach (data_get($value, 'messages', []) as $mensaje) {
                    $this->registrarMensaje($value, $mensaje);
                }

                foreach (data_get($value, 'statuses', []) as $estado) {
                    $this->registrarEstado($estado);
                }
            }
        }
    }

    /**
     * Mensaje entrante de una clienta.
     *
     * El wamid lleva índice único en la base de datos: si Meta reintenta el
     * mismo evento, firstOrCreate lo detecta y no se procesa dos veces. La
     * garantía la da la base, no una comprobación en PHP: dos peticiones
     * simultáneas podrían pasar ambas por un if.
     */
    private function registrarMensaje(array $value, array $mensaje): void
    {
        $wamid = data_get($mensaje, 'id');

        if (! $wamid) {
            return;
        }

        $evento = EventoWhatsapp::firstOrCreate(
            ['wamid' => $wamid],
            ['tipo' => EventoWhatsapp::TIPO_MENSAJE, 'payload' => $mensaje],
        );

        if (! $evento->wasRecentlyCreated) {
            Log::channel('whatsapp')->info('Evento duplicado ignorado', ['wamid' => $wamid]);

            return;
        }

        $waId = data_get($mensaje, 'from');

        if (! $waId) {
            return;
        }

        // El nombre viene gratis en el perfil: registramos a la clienta sin preguntarle nada.
        $nombre = data_get($value, 'contacts.0.profile.name');
        $cliente = Cliente::firstOrCreate(['wa_id' => $waId], ['nombre' => $nombre]);

        // Solo se completa el nombre si no hay uno: el que capturó la dueña o la
        // clienta en su cuenta tiene prioridad sobre el nombre de perfil.
        if ($nombre && ! $cliente->nombre) {
            $cliente->update(['nombre' => $nombre]);
        }

        // Enlaza el mensaje con la clienta para el historial de conversación (SCRUM-40).
        $evento->forceFill(['cliente_id' => $cliente->id, 'direccion' => EventoWhatsapp::ENTRANTE])->save();

        Log::channel('whatsapp')->info('Mensaje de clienta', [
            'cliente_id' => $cliente->id,
            'nombre' => $cliente->nombre,
            'wa_id' => $cliente->wa_id,
            'tipo' => data_get($mensaje, 'type'),
            'texto' => data_get($mensaje, 'text.body'),
        ]);

        // Aquí entrará la interpretación con IA en la siguiente fase.
    }

    /**
     * Acuse de recibo de un mensaje que nosotros enviamos.
     *
     * Un mismo wamid genera sent, delivered y read, así que la llave de
     * idempotencia combina ambos: si usáramos solo el wamid, el segundo
     * estado chocaría con el índice único.
     */
    private function registrarEstado(array $estado): void
    {
        $wamid = data_get($estado, 'id');
        $tipo = data_get($estado, 'status');

        if (! $wamid || ! $tipo) {
            return;
        }

        EventoWhatsapp::firstOrCreate(
            ['wamid' => "{$wamid}:{$tipo}"],
            ['tipo' => EventoWhatsapp::TIPO_ESTADO, 'payload' => $estado],
        );
    }
}
