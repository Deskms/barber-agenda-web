<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Cliente;
use App\Models\User;
use App\Support\Telefono;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Administración de usuarios (solo la dueña). */
class UsuarioController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['rol' => ['sometimes', Rule::in(User::ROLES)]]);

        $usuarios = User::query()
            ->with('cliente')
            ->when($request->filled('rol'), fn ($q) => $q->where('rol', $request->string('rol')))
            ->orderBy('name')
            ->paginate(20);

        return UserResource::collection($usuarios);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'rol' => ['required', Rule::in(User::ROLES)],
            'telefono' => ['required_if:rol,'.User::ROL_CLIENTE, 'nullable', 'string', 'max:20'],
        ]);

        $waId = null;

        if ($datos['rol'] === User::ROL_CLIENTE) {
            $waId = Telefono::normalizar($datos['telefono'] ?? null);

            if (! $waId) {
                throw ValidationException::withMessages(['telefono' => 'El teléfono no es válido.']);
            }

            if (Cliente::where('wa_id', $waId)->whereNotNull('user_id')->exists()) {
                throw ValidationException::withMessages(['telefono' => 'Ese teléfono ya tiene una cuenta.']);
            }
        }

        $usuario = DB::transaction(function () use ($datos, $waId) {
            $usuario = User::create([
                'name' => $datos['nombre'],
                'email' => $datos['email'],
                'password' => $datos['password'],
                'rol' => $datos['rol'],
                'activo' => true,
            ]);

            if ($waId) {
                $cliente = Cliente::firstOrNew(['wa_id' => $waId]);
                $cliente->user_id = $usuario->id;
                $cliente->nombre = $cliente->nombre ?: $datos['nombre'];
                $cliente->save();
            }

            return $usuario;
        });

        return (new UserResource($usuario->load('cliente')))->response()->setStatusCode(201);
    }

    public function update(Request $request, User $usuario): UserResource
    {
        $datos = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:120'],
            'rol' => ['sometimes', Rule::in(User::ROLES)],
            'activo' => ['sometimes', 'boolean'],
        ]);

        if ($usuario->is($request->user())) {
            if (($datos['activo'] ?? true) === false || (($datos['rol'] ?? User::ROL_ADMIN) !== User::ROL_ADMIN)) {
                throw ValidationException::withMessages(['usuario' => 'No puedes desactivarte ni quitarte el rol de administradora.']);
            }
        }

        $usuario->fill(array_filter([
            'name' => $datos['nombre'] ?? null,
            'rol' => $datos['rol'] ?? null,
        ], fn ($v) => $v !== null));

        if (array_key_exists('activo', $datos)) {
            $usuario->activo = $datos['activo'];
        }

        $usuario->save();

        // Al desactivar una cuenta se cierran todas sus sesiones.
        if (! $usuario->activo) {
            $usuario->tokens()->delete();
        }

        return new UserResource($usuario->load('cliente'));
    }
}
