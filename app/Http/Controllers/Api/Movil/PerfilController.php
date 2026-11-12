<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClienteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerfilController extends Controller
{
    /** GET /api/movil/perfil */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => new ClienteResource($request->user())]);
    }

    /** PUT /api/movil/perfil */
    public function update(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre'        => ['nullable', 'string', 'max:80'],
            'acepta_avisos' => ['nullable', 'boolean'],
        ]);

        $request->user()->update(array_filter(
            $datos,
            fn ($valor) => $valor !== null,
        ));

        return response()->json(['data' => new ClienteResource($request->user()->fresh())]);
    }
}
