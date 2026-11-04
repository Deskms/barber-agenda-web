<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\Fecha;
use App\Http\Resources\HorarioResource;
use App\Models\Servicio;
use App\Services\Agenda\DisponibilidadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DisponibilidadController extends Controller
{
    /** GET /api/web/disponibilidad?servicio_id=1&fecha=2026-11-10 */
    public function __invoke(Request $request, DisponibilidadService $disponibilidad): JsonResponse
    {
        $datos = $request->validate([
            'servicio_id' => ['required', 'integer', Rule::exists('servicios', 'id')->where('activo', true)],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);

        $servicio = Servicio::with('etapas')->findOrFail($datos['servicio_id']);
        $dia = Carbon::createFromFormat('Y-m-d', $datos['fecha'])->startOfDay();
        $horario = $disponibilidad->horarioDe($dia);

        return response()->json([
            'servicio_id' => $servicio->id,
            'fecha' => $dia->toDateString(),
            'horario' => $horario ? new HorarioResource($horario) : null,
            'slots' => $disponibilidad->slotsDisponibles($servicio, $dia)->map(fn ($s) => [
                'inicio' => Fecha::iso($s['inicio']),
                'fin' => Fecha::iso($s['fin']),
            ])->values(),
        ]);
    }
}
