<?php

namespace App\Http\Controllers\Api\Movil;

use App\Exceptions\HorarioNoDisponibleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CitaResource;
use App\Http\Resources\Fecha;
use App\Models\Cita;
use App\Models\Servicio;
use App\Services\Agenda\CitaService;
use App\Services\Agenda\DisponibilidadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CitaController extends Controller
{
    public function __construct(
        private readonly CitaService $citas,
        private readonly DisponibilidadService $disponibilidad,
    ) {}

    /**
     * GET /api/movil/horarios?servicio_id=1&fecha=2026-11-15
     * Devuelve los horarios disponibles para un servicio a partir de una fecha.
     */
    public function horarios(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'servicio_id' => ['required', 'integer', Rule::exists('servicios', 'id')->where('activo', true)],
            'fecha' => ['required', 'date'],
        ]);

        $servicio = Servicio::with('etapas')->findOrFail($datos['servicio_id']);
        $inicioDia = $this->fecha($datos['fecha'])->startOfDay();
        $desde = $inicioDia->greaterThan(Carbon::now()) ? $inicioDia : Carbon::now();

        $slots = $this->disponibilidad
            ->proximosSlots($servicio, $desde, (int) config('agenda.slots_movil', 10))
            ->map(fn ($s) => [
                'inicio' => Fecha::iso($s['inicio']),
                'fin' => Fecha::iso($s['fin']),
            ])
            ->values();

        return response()->json([
            'servicio_id' => $servicio->id,
            'fecha' => $inicioDia->toDateString(),
            'horarios' => $slots,
        ]);
    }

    /**
     * GET /api/movil/citas
     * Lista las citas de la clienta autenticada.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'desde' => ['sometimes', 'date'],
            'hasta' => ['sometimes', 'date'],
            'estado' => ['sometimes', Rule::in(Cita::ESTADOS)],
        ]);

        $clienteId = $request->user()->cliente?->id ?? 0;

        $consulta = Cita::query()
            ->with(['servicio', 'cliente', 'bloques'])
            ->where('cliente_id', $clienteId)
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('desde'), fn ($q) => $q->where('inicio', '>=', Carbon::parse($request->input('desde'))))
            ->when($request->filled('hasta'), fn ($q) => $q->where('inicio', '<=', Carbon::parse($request->input('hasta'))))
            ->orderBy('inicio');

        return CitaResource::collection($consulta->paginate(50));
    }

    /**
     * POST /api/movil/citas
     * Agenda una nueva cita para la clienta (201) o devuelve alternativas (409).
     */
    public function store(Request $request): JsonResponse
    {
        $cliente = $request->user()->cliente;

        if (! $cliente) {
            throw ValidationException::withMessages(['cliente' => 'Tu cuenta no tiene una clienta asociada.']);
        }

        $datos = $request->validate([
            'servicio_id' => ['required', 'integer', Rule::exists('servicios', 'id')->where('activo', true)],
            'inicio' => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $servicio = Servicio::with('etapas')->findOrFail($datos['servicio_id']);
        $inicio = $this->fecha($datos['inicio']);
        $origen = defined(Cita::class . '::ORIGEN_MOVIL') ? Cita::ORIGEN_MOVIL : Cita::ORIGEN_WEB;

        try {
            $cita = $this->citas->agendar($cliente, $servicio, $inicio, $origen, $datos['notas'] ?? null);
        } catch (HorarioNoDisponibleException $e) {
            return $this->conflicto($e, $servicio, $inicio);
        }

        return (new CitaResource($cita))->response()->setStatusCode(201);
    }

    /**
     * GET /api/movil/citas/{cita}
     */
    public function show(Cita $cita): CitaResource
    {
        Gate::authorize('view', $cita);

        return new CitaResource($cita->load(['servicio', 'cliente', 'bloques']));
    }

    /**
     * PATCH /api/movil/citas/{cita}
     * Reprograma una cita de la clienta.
     */
    public function update(Request $request, Cita $cita): JsonResponse|CitaResource
    {
        Gate::authorize('update', $cita);

        $datos = $request->validate(['inicio' => ['required', 'date']]);
        $inicio = $this->fecha($datos['inicio']);

        try {
            $cita = $this->citas->reprogramar($cita->load('servicio.etapas'), $inicio);
        } catch (HorarioNoDisponibleException $e) {
            return $this->conflicto($e, $cita->servicio, $inicio);
        }

        return new CitaResource($cita);
    }

    /**
     * POST /api/movil/citas/{cita}/cancelar
     */
    public function cancelar(Request $request, Cita $cita): CitaResource
    {
        Gate::authorize('cancel', $cita);

        if ($cita->inicio->lessThanOrEqualTo(Carbon::now())) {
            throw ValidationException::withMessages(['cita' => 'Solo puedes cancelar citas que aún no empiezan.']);
        }

        return new CitaResource($this->citas->cancelar($cita)->load(['servicio', 'cliente', 'bloques']));
    }

    private function fecha(string $valor): Carbon
    {
        return Carbon::parse($valor)->setTimezone(config('app.timezone'))->seconds(0);
    }

    private function conflicto(HorarioNoDisponibleException $e, Servicio $servicio, Carbon $inicio): JsonResponse
    {
        $desde = $inicio->greaterThan(Carbon::now()) ? $inicio->copy()->startOfDay() : Carbon::now();

        return response()->json([
            'message' => $e->getMessage(),
            'motivo' => $e->motivo,
            'alternativas' => $this->disponibilidad
                ->proximosSlots($servicio, $desde, (int) config('agenda.alternativas', 3))
                ->map(fn ($s) => ['inicio' => Fecha::iso($s['inicio']), 'fin' => Fecha::iso($s['fin'])])
                ->values(),
        ], 409);
    }
}