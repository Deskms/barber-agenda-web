<?php

namespace App\Http\Controllers\Api\Movil;

use App\Exceptions\HorarioNoDisponibleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CitaResource;
use App\Models\Cita;
use App\Models\Servicio;
use App\Services\Agenda\CitaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Citas de la clienta autenticada.
 *
 * Ninguna consulta parte del id de la URL: todas arrancan de las citas de
 * quien trae el token. Así no existe el momento en que el sistema tiene en
 * memoria la cita de otra persona y luego decide no mostrarla.
 */
class CitaController extends Controller
{
    public function __construct(
        private readonly CitaService $citas,
    ) {}

    /** GET /api/movil/citas */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['nullable', 'in:confirmada,cancelada,completada,no_asistio'],
            'desde'  => ['nullable', 'date_format:Y-m-d'],
        ]);

        $citas = $request->user()->citas()
            ->with(['servicio', 'seccion'])
            ->when($datos['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($datos['desde'] ?? null, fn ($q, $desde) => $q->where('inicio', '>=', $desde))
            ->orderByDesc('inicio')
            ->limit(100)
            ->get();

        return response()->json(['data' => CitaResource::collection($citas)]);
    }

    /** POST /api/movil/citas */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'servicio_id' => ['required', 'integer'],
            'inicio'      => ['required', 'date_format:Y-m-d\TH:i'],
        ]);

        $servicio = Servicio::activos()->findOrFail($datos['servicio_id']);
        $inicio   = Carbon::createFromFormat('Y-m-d\TH:i', $datos['inicio']);

        try {
            $cita = $this->citas->agendar(
                $request->user(),
                $servicio,
                $inicio,
                Cita::ORIGEN_MOVIL,
            );
        } catch (HorarioNoDisponibleException $e) {
            // 409: la petición está bien formada, pero el horario ya no existe.
            return response()->json(['mensaje' => $e->getMessage()], 409);
        }

        return response()->json(
            ['data' => new CitaResource($cita->load(['servicio', 'seccion']))],
            201,
        );
    }

    /** POST /api/movil/citas/{cita}/cancelar */
    public function cancelar(Request $request, int $cita): JsonResponse
    {
        // findOrFail sobre las citas de la clienta: una cita ajena devuelve 404,
        // no 403, para no confirmar que ese identificador existe.
        $propia = $request->user()->citas()->findOrFail($cita);

        if ($propia->inicio->isPast()) {
            return response()->json(['mensaje' => 'Esa cita ya pasó.'], 409);
        }

        $this->citas->cancelar($propia);

        return response()->json(
            ['data' => new CitaResource($propia->fresh()->load(['servicio', 'seccion']))],
        );
    }
}
