<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Bot\InterpreteIA;
use App\Services\WhatsApp\PlantillaService;
use App\Support\Telefono;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;

/** Estado de la conexión con Meta: lo que falta configurar y cómo van las plantillas. */
class WhatsAppController extends Controller
{
    public function index(PlantillaService $plantillas, InterpreteIA $ia)
    {
        $config = [
            'Token de acceso'       => filled(config('whatsapp.access_token')),
            'Phone number ID'       => filled(config('whatsapp.phone_id')),
            'WABA ID'               => filled(config('whatsapp.waba_id')),
            'Verify token'          => filled(config('whatsapp.verify_token')),
            'App secret (firma)'    => filled(config('whatsapp.app_secret')),
            'IA para texto libre'   => $ia->disponible(),
        ];

        $enMeta = null;
        $error  = null;

        if ($config['Token de acceso'] && $config['WABA ID']) {
            $respuesta = $plantillas->listar();

            $respuesta->successful()
                ? $enMeta = collect(data_get($respuesta->json(), 'data', []))->keyBy('name')
                : $error = data_get($respuesta->json(), 'error.message', 'HTTP ' . $respuesta->status());
        }

        return view('whatsapp.index', [
            'config'     => $config,
            'catalogo'   => config('whatsapp.plantillas'),
            'enMeta'     => $enMeta,
            'error'      => $error,
            'webhookUrl' => url('/api/whatsapp/webhook'),
        ]);
    }

    public function prueba(Request $request, WhatsAppService $whatsapp)
    {
        $datos = $request->validate(['telefono' => ['required', 'string', 'max:25']]);
        $waId  = Telefono::aWaId($datos['telefono']);

        if (! $waId) {
            return back()->withErrors(['telefono' => 'Número no válido.']);
        }

        // hello_world viene aprobada en toda cuenta nueva: sirve para probar el token.
        $r = $whatsapp->enviarPlantilla($waId, 'hello_world', 'en_US');

        return $r->successful()
            ? back()->with('ok', 'Mensaje de prueba enviado.')
            : back()->withErrors(['telefono' => 'Meta rechazó el envío: ' . data_get($r->json(), 'error.message', 'HTTP ' . $r->status())]);
    }
}
