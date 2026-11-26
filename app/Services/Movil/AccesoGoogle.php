<?php

namespace App\Services\Movil;

use App\Models\Cliente;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Entrada a la app con la cuenta de Google.
 *
 * La app manda el "ID token" que le da Google y aquí se verifica con Google
 * mismo (tokeninfo): que sea para nuestra app (aud), que lo emitió Google
 * (iss), que no haya vencido y que el correo esté verificado.
 *
 * Google no dice el número de WhatsApp, y todo el negocio (citas, avisos,
 * tarjeta) cuelga de ese número. Por eso la primera vez se pide confirmar
 * el número con el código de siempre y entonces se ligan las dos cosas.
 */
class AccesoGoogle
{
    /** Minutos que tiene la clienta para confirmar su número después de elegir su cuenta. */
    public const VIGENCIA_ENLACE_MINUTOS = 15;

    /**
     * @return array{sub: string, email: ?string, nombre: ?string}|null
     */
    public function verificar(string $idToken): ?array
    {
        $clientes = $this->clientesPermitidos();

        if ($clientes === []) {
            Log::warning('Inicio con Google sin GOOGLE_CLIENT_ID configurado.');

            return null;
        }

        try {
            $respuesta = Http::timeout(8)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo verificar el token de Google', ['mensaje' => $e->getMessage()]);

            return null;
        }

        if (! $respuesta->successful()) {
            return null;
        }

        $datos = $respuesta->json();

        $valido = in_array($datos['aud'] ?? null, $clientes, true)
            && in_array($datos['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            && (int) ($datos['exp'] ?? 0) > Carbon::now()->timestamp
            && in_array((string) ($datos['email_verified'] ?? ''), ['true', '1'], true)
            && ! empty($datos['sub']);

        if (! $valido) {
            return null;
        }

        return [
            'sub'    => (string) $datos['sub'],
            'email'  => $datos['email'] ?? null,
            'nombre' => $datos['name'] ?? null,
        ];
    }

    public function clienteDe(string $sub): ?Cliente
    {
        return Cliente::where('google_sub', $sub)->first();
    }

    /** Comprobante cifrado de "ya eligió su cuenta de Google", para ligarla al confirmar el número. */
    public function enlace(array $perfil): string
    {
        return Crypt::encryptString(json_encode([
            'sub'    => $perfil['sub'],
            'email'  => $perfil['email'],
            'nombre' => $perfil['nombre'],
            'vence'  => Carbon::now()->addMinutes(self::VIGENCIA_ENLACE_MINUTOS)->timestamp,
        ]));
    }

    /** @return array{sub: string, email: ?string, nombre: ?string}|null */
    public function leerEnlace(string $enlace): ?array
    {
        try {
            $datos = json_decode(Crypt::decryptString($enlace), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($datos) || empty($datos['sub']) || (int) ($datos['vence'] ?? 0) < Carbon::now()->timestamp) {
            return null;
        }

        return ['sub' => $datos['sub'], 'email' => $datos['email'] ?? null, 'nombre' => $datos['nombre'] ?? null];
    }

    /** Liga la cuenta de Google al registro de la clienta (y le pone nombre si no tenía). */
    public function ligar(Cliente $cliente, array $perfil): void
    {
        $cliente->forceFill([
            'google_sub' => $perfil['sub'],
            'email'      => $perfil['email'],
            'nombre'     => $cliente->nombre ?: $perfil['nombre'],
        ])->save();
    }

    /** @return array<int, string> */
    private function clientesPermitidos(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) config('services.google.client_id')))));
    }
}
