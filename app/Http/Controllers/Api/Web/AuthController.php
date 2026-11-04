<?php

namespace App\Http\Controllers\Api\Web;

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
     * POST /api/web/auth/registro
     * Crea la cuenta de una clienta. Si la dueña ya la había registrado con ese
     * teléfono, se vincula a esa clienta en lugar de duplicarla.
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

        return response()->json([
            'token' => $usuario->createToken('web')->plainTextToken,
            'tipo' => 'Bearer',
            'usuario' => new UserResource($usuario->load('cliente')),
        ], 201);
    }

    /** POST /api/web/auth/login */
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $usuario = User::where('email', $datos['email'])->first();

        if (! $usuario || ! Hash::check($datos['password'], $usuario->password)) {
            throw ValidationException::withMessages(['email' => 'Las credenciales no son correctas.']);
        }

        if (! $usuario->activo) {
            return response()->json(['message' => 'La cuenta está desactivada.'], 403);
        }

        return response()->json([
            'token' => $usuario->createToken('web')->plainTextToken,
            'tipo' => 'Bearer',
            'usuario' => new UserResource($usuario->load('cliente')),
        ]);
    }

    /** POST /api/web/auth/logout — revoca solo el token con el que se hizo la petición. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    /** GET /api/web/auth/me */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('cliente'));
    }
}
