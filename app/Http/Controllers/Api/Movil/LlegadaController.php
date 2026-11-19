<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Http\Resources\CitaResource;
use App\Services\Agenda\Llegadas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** La clienta escanea con la cámara el QR del mostrador al llegar. */
class LlegadaController extends Controller
{
    public function store(Request $request, Llegadas $llegadas): JsonResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:255'],
        ]);

        if (! $llegadas->codigoValido($datos['codigo'])) {
            return response()->json(['mensaje' => 'Ese código no es el del mostrador.'], 422);
        }

        $cita = $llegadas->citaActual($request->user());

        if (! $cita) {
            return response()->json(['mensaje' => $llegadas->mensajeSinCita($request->user())], 404);
        }

        $llegadas->registrar($cita);

        return response()->json([
            'mensaje' => 'Llegada registrada. Te atendemos en un momento.',
            'data'    => new CitaResource($cita->load(['servicio', 'seccion'])),
        ]);
    }
}
