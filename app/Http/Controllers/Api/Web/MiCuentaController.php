<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Lo que una clienta ve de sí misma en la web. */
class MiCuentaController extends Controller
{
    /** GET /api/web/mi/historial — servicios realizados y resumen de visitas. */
    public function historial(Request $request): JsonResponse
    {
        $cliente = $request->user()->cliente;

        abort_unless($cliente, 404, 'Tu cuenta no tiene una clienta asociada.');

        return response()->json(ClienteController::armarHistorial($cliente));
    }
}
