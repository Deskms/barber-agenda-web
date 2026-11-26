<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use App\Services\Movil\AccesoGoogle;
use App\Services\Movil\AccesoMovil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AccesoMovil $acceso,
        private readonly AccesoGoogle $google,
    ) {}

    /** POST /api/movil/auth/codigo */
    public function codigo(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'telefono' => ['required', 'string', 'regex:/^[0-9+\s-]{10,20}$/'],
        ]);

        $resultado = $this->acceso->enviarCodigo($datos['telefono'], $request->ip());

        return response()->json([
            'mensaje'           => 'Si el número es válido, llegará un código por WhatsApp.',
            'vigencia_minutos'  => AccesoMovil::VIGENCIA_MINUTOS,
            // Solo fuera de producción, para desarrollar sin depender de Meta.
            'codigo_de_prueba'  => $resultado['devuelto'],
        ], 202);
    }

    /** POST /api/movil/auth/token */
    public function token(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'telefono'    => ['required', 'string'],
            'codigo'      => ['required', 'string', 'digits:6'],
            'dispositivo' => ['nullable', 'string', 'max:60'],
            // Viene de auth/google: al confirmar el número se liga la cuenta de Google.
            'enlace_google' => ['nullable', 'string', 'max:2000'],
        ]);

        // Se revisa antes de canjear para no gastar el código si el enlace ya venció.
        $perfilGoogle = null;
        if (! empty($datos['enlace_google'])) {
            $perfilGoogle = $this->google->leerEnlace($datos['enlace_google']);

            if (! $perfilGoogle) {
                return response()->json(['mensaje' => 'Pasó mucho tiempo desde que elegiste tu cuenta de Google. Vuelve a tocar «Continuar con Google».'], 422);
            }
        }

        $cliente = $this->acceso->canjear($datos['telefono'], $datos['codigo']);

        if (! $cliente) {
            return response()->json(['mensaje' => 'Código inválido o vencido.'], 422);
        }

        if ($perfilGoogle) {
            $otra = $this->google->clienteDe($perfilGoogle['sub']);

            if ($otra && $otra->id !== $cliente->id) {
                return response()->json(['mensaje' => 'Esa cuenta de Google ya está ligada a otro número.'], 409);
            }

            $this->google->ligar($cliente, $perfilGoogle);
        }

        return $this->emitir($cliente, $datos['dispositivo'] ?? null);
    }

    /**
     * POST /api/movil/auth/google
     *
     * Con la cuenta ya ligada entra directo. La primera vez responde
     * requiere_telefono y un enlace_google: la app pide el número, manda el
     * código de siempre y en auth/token incluye ese enlace.
     */
    public function google(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'id_token'    => ['required', 'string', 'max:4096'],
            'dispositivo' => ['nullable', 'string', 'max:60'],
        ]);

        $perfil = $this->google->verificar($datos['id_token']);

        if (! $perfil) {
            return response()->json(['mensaje' => 'No pudimos verificar tu cuenta de Google. Intenta de nuevo o entra con tu número.'], 422);
        }

        if ($cliente = $this->google->clienteDe($perfil['sub'])) {
            return $this->emitir($cliente, $datos['dispositivo'] ?? null);
        }

        return response()->json([
            'requiere_telefono' => true,
            'mensaje'           => 'Confirma tu número de WhatsApp una sola vez para ligarlo a tu cuenta de Google.',
            'enlace_google'     => $this->google->enlace($perfil),
            'email'             => $perfil['email'],
            'nombre'            => $perfil['nombre'],
            'vigencia_minutos'  => AccesoGoogle::VIGENCIA_ENLACE_MINUTOS,
        ]);
    }

    private function emitir(Cliente $cliente, ?string $dispositivo): JsonResponse
    {
        $token = $cliente->createToken(
            $dispositivo ?: 'app-movil',
            ['movil'],
            now()->addDays(60),
        );

        return response()->json([
            'token'      => $token->plainTextToken,
            'expira_en'  => $token->accessToken->expires_at?->toIso8601String(),
            'cliente'    => new ClienteResource($cliente),
        ]);
    }

    /** POST /api/movil/auth/salir */
    public function salir(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['mensaje' => 'Sesión cerrada.']);
    }
}
