<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\BloqueoResource;
use App\Http\Resources\CitaResource;
use App\Models\BloqueoAgenda;
use App\Models\Cita;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/** Fechas y horarios en los que la dueña no atiende. */
class BloqueoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'desde' => ['sometimes', 'date'],
            'hasta' => ['sometimes', 'date', 'after_or_equal:desde'],
        ]);

        $desde = $request->filled('desde') ? Carbon::parse($request->input('desde'))->startOfDay() : Carbon::today();

        return BloqueoResource::collection(
            BloqueoAgenda::query()
                ->where('fin', '>', $desde)
                ->when($request->filled('hasta'), fn ($q) => $q->where('inicio', '<', Carbon::parse($request->input('hasta'))->endOfDay()))
                ->orderBy('inicio')
                ->get()
        );
    }

    /**
     * POST /api/web/admin/bloqueos
     * Un bloqueo no cancela citas existentes: las devuelve como aviso para que
     * la dueña decida qué hacer con ellas.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'inicio' => ['required', 'date'],
            'fin' => ['required', 'date', 'after:inicio'],
            'motivo' => ['nullable', 'string', 'max:150'],
        ]);

        $bloqueo = BloqueoAgenda::create([
            'inicio' => Carbon::parse($datos['inicio']),
            'fin' => Carbon::parse($datos['fin']),
            'motivo' => $datos['motivo'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        $afectadas = Cita::query()
            ->where('estado', Cita::CONFIRMADA)
            ->queChocanCon($bloqueo->inicio, $bloqueo->fin)
            ->with(['cliente', 'servicio'])
            ->orderBy('inicio')
            ->get();

        return response()->json([
            'data' => new BloqueoResource($bloqueo),
            'citas_afectadas' => CitaResource::collection($afectadas),
        ], 201);
    }

    public function destroy(BloqueoAgenda $bloqueo): JsonResponse
    {
        $bloqueo->delete();

        return response()->json(['message' => 'Bloqueo eliminado.']);
    }
}
