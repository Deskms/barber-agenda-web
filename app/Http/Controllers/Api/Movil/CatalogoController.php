<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use App\Http\Resources\SeccionResource;
use App\Models\Seccion;
use App\Models\Servicio;
use App\Services\Agenda\DisponibilidadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CatalogoController extends Controller
{
    public function __construct(
        private readonly DisponibilidadService $disponibilidad,
    ) {}

    /** GET /api/movil/catalogo */
    public function catalogo(): JsonResponse
    {
        $secciones = Seccion::query()
            ->where('activo', true)
            ->with(['servicios' => fn ($q) => $q->activos()->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();

        return response()->json(['data' => SeccionResource::collection($secciones)]);
    }

    /** GET /api/movil/disponibilidad?servicio_id=1&fecha=2026-11-14 */
    public function disponibilidad(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'servicio_id' => ['required', 'integer'],
            'fecha'       => ['required', 'date_format:Y-m-d'],
        ]);

        $servicio = Servicio::activos()->findOrFail($datos['servicio_id']);
        $dia      = Carbon::createFromFormat('Y-m-d', $datos['fecha'])->startOfDay();

        $slots = $this->disponibilidad->slotsDisponibles($servicio, $dia);

        return response()->json([
            'fecha'       => $dia->toDateString(),
            'servicio_id' => $servicio->id,
            'data'        => $slots->map(fn ($s) => [
                'inicio' => $s['inicio']->toIso8601String(),
                'fin'    => $s['fin']->toIso8601String(),
                'hora'   => $s['inicio']->format('H:i'),
            ])->values(),
        ]);
    }
}
