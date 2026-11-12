<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClienteResource;
use App\Services\Movil\AccesoMovil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AccesoMovil $acceso,
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
        ]);

        $cliente = $this->acceso->canjear($datos['telefono'], $datos['codigo']);

        if (! $cliente) {
            return response()->json(['mensaje' => 'Código inválido o vencido.'], 422);
        }

        $token = $cliente->createToken(
            $datos['dispositivo'] ?? 'app-movil',
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
