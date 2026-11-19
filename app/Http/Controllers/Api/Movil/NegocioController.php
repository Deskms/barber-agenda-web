<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** Datos del local para la brújula y el botón "Cómo llegar" de la app. */
class NegocioController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $negocio = config('whatsapp.negocio');

        return response()->json(['data' => [
            'nombre'    => $negocio['nombre'] ?? null,
            'direccion' => ($negocio['direccion'] ?? '') ?: null,
            'lat'       => $negocio['lat'] ?? null,
            'lng'       => $negocio['lng'] ?? null,
        ]]);
    }
}
