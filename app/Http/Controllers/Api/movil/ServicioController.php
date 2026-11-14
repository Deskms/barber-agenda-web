<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServicioResource;
use App\Models\Servicio;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServicioController extends Controller
{
    /**
     * GET /api/movil/servicios
     * Catálogo de servicios activos con sus etapas para la app Android.
     */
    public function index(): AnonymousResourceCollection
    {
        return ServicioResource::collection(
            Servicio::activos()->with('etapas')->orderBy('nombre')->get()
        );
    }

    /**
     * GET /api/movil/servicios/{servicio}
     * Detalle de un servicio activo. Si está inactivo devuelve 404.
     */
    public function show(Servicio $servicio): ServicioResource
    {
        abort_unless($servicio->activo, 404);

        return new ServicioResource($servicio->load('etapas'));
    }
}