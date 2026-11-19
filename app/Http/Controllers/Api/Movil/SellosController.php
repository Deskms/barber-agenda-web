<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Services\Lealtad\TarjetaSellos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellosController extends Controller
{
    public function show(Request $request, TarjetaSellos $sellos): JsonResponse
    {
        $cliente = $request->user();
        $estado  = $sellos->estado($cliente);

        return response()->json(['data' => [
            'meta'                => $estado['meta'],
            'premio'              => $estado['premio'],
            'sellos'              => $estado['sellos'],
            'faltan'              => $estado['faltan'],
            'premios_disponibles' => $estado['premios'],
            'total_ganados'       => $estado['ganados'],
            'tarjeta_url'         => $sellos->url($cliente),
        ]]);
    }
}
