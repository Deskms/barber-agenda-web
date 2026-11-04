<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\HorarioResource;
use App\Models\HorarioAtencion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HorarioController extends Controller
{
    /** GET /api/web/horarios — los 7 días; un día sin registro aparece cerrado. */
    public function index(): AnonymousResourceCollection
    {
        $registrados = HorarioAtencion::all()->keyBy('dia_semana');

        $semana = collect(range(0, 6))->map(fn (int $dia) => $registrados->get($dia)
            ?? new HorarioAtencion(['dia_semana' => $dia, 'abre' => '00:00', 'cierra' => '00:00', 'activo' => false]));

        return HorarioResource::collection($semana);
    }

    /** PUT /api/web/admin/horarios/{dia} — dia: 0 = domingo … 6 = sábado. */
    public function update(Request $request, int $dia): JsonResponse
    {
        abort_unless($dia >= 0 && $dia <= 6, 404);

        $datos = $request->validate([
            'activo' => ['required', 'boolean'],
            'abre' => ['required_if:activo,true', 'nullable', 'date_format:H:i'],
            'cierra' => ['required_if:activo,true', 'nullable', 'date_format:H:i', 'after:abre'],
        ]);

        $horario = HorarioAtencion::firstOrNew(['dia_semana' => $dia]);
        $horario->activo = $datos['activo'];
        $horario->abre = $datos['abre'] ?? $horario->abre ?? '00:00';
        $horario->cierra = $datos['cierra'] ?? $horario->cierra ?? '00:00';
        $horario->save();

        // PUT es idempotente: responde 200 tanto si creó el día como si lo actualizó.
        return (new HorarioResource($horario))->response()->setStatusCode(200);
    }
}
