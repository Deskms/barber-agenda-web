<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Cliente;
use App\Models\User;
use App\Support\Telefono;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/movil/auth/registro
     * Crea la cuenta de una clienta desde Android. Si ya estaba registrada
     * por teléfono, se vincula a esa clienta sin duplicarla.
     */
    public function registro(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telefono' => ['required', 'string', 'max:20', function (string $campo, mixed $valor, \Closure $fallar) {
                if (! Telefono::normalizar((string) $valor)) {
                    $fallar('El teléfono no es válido. Usa 10 dígitos o el formato internacional.');
                }
            }],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'acepta_avisos' => ['sometimes', 'boolean'],
            'dispositivo' => ['sometimes', 'string', 'max:100'],
        ]);

        $telefono = Telefono::normalizar($datos['telefono']);

        $existente = Cliente::where('telefono', $telefono)->first();

        if ($existente && $existente->user_id !== null) {
            throw ValidationException::withMessages(['telefono' => 'Ese teléfono ya tiene una cuenta.']);
        }

        $usuario = DB::transaction(function () use ($datos, $telefono, $existente) {
            $usuario = User::create([
                'name' => $datos['nombre'],
                'email' => $datos['email'],
                'password' => $datos['password'],
                'rol' => User::ROL_CLIENTE,
                'activo' => true,
            ]);

            $cliente = $existente ?? new Cliente(['telefono' => $telefono]);
            $cliente->user_id = $usuario->id;
            $cliente->nombre = $cliente->nombre ?: $datos['nombre'];

            if (array_key_exists('acepta_avisos', $datos)) {
                $cliente->acepta_avisos = (bool) $datos['acepta_avisos'];
            }

            $cliente->save();

            return $usuario;
        });

        $nombreToken = $datos['dispositivo'] ?? 'android';

        return response()->json([
            'token' => $usuario->createToken($nombreToken)->plainTextToken,
            'tipo' => 'Bearer',
            'usuario' => new UserResource($usuario->load('cliente')),
        ], 201);
    }

    /**
     * POST /api/movil/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'dispositivo' => ['sometimes', 'string', 'max:100'],
        ]);

        $usuario = User::where('email', $datos['email'])->first();

        if (! $usuario || ! Hash::check($datos['password'], $usuario->password)) {
            throw ValidationException::withMessages(['email' => 'Las credenciales no son correctas.']);
        }

        if (! $usuario->activo) {
            return response()->json(['message' => 'La cuenta está desactivada.'], 403);
        }

        $nombreToken = $datos['dispositivo'] ?? 'android';

        return response()->json([
            'token' => $usuario->createToken($nombreToken)->plainTextToken,
            'tipo' => 'Bearer',
            'usuario' => new UserResource($usuario->load('cliente')),
        ]);
    }

    /**
     * POST /api/movil/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    /**
     * GET /api/movil/auth/me
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('cliente'));
    }
}
