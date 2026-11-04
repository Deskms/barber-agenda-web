<?php

namespace App\Http\Controllers\Api\Web;

use App\Exceptions\HorarioNoDisponibleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CitaResource;
use App\Http\Resources\Fecha;
use App\Models\Cita;
use App\Models\Cliente;
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
     * GET /api/web/citas
     * La dueña ve todas (con filtros); una clienta, solo las suyas.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'desde' => ['sometimes', 'date'],
            'hasta' => ['sometimes', 'date'],
            'estado' => ['sometimes', Rule::in(Cita::ESTADOS)],
            'cliente_id' => ['sometimes', 'integer'],
        ]);

        $usuario = $request->user();

        $consulta = Cita::query()
            ->with(['servicio', 'cliente', 'bloques'])
            ->when(! $usuario->esAdmin(), fn ($q) => $q->where('cliente_id', $usuario->cliente?->id ?? 0))
            ->when($usuario->esAdmin() && $request->filled('cliente_id'), fn ($q) => $q->where('cliente_id', $request->integer('cliente_id')))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('desde'), fn ($q) => $q->where('inicio', '>=', Carbon::parse($request->input('desde'))))
            ->when($request->filled('hasta'), fn ($q) => $q->where('inicio', '<=', Carbon::parse($request->input('hasta'))))
            ->orderBy('inicio');

        return CitaResource::collection($consulta->paginate(50));
    }

    /**
     * POST /api/web/citas
     * 201 con la cita, o 409 con alternativas si el horario ya no está libre.
     */
    public function store(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'servicio_id' => ['required', 'integer', Rule::exists('servicios', 'id')->where('activo', true)],
            'inicio' => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:500'],
            'cliente_id' => $usuario->esAdmin() ? ['required', 'integer', 'exists:clientes,id'] : ['prohibited'],
        ]);

        $cliente = $usuario->esAdmin() ? Cliente::findOrFail($datos['cliente_id']) : $usuario->cliente;

        if (! $cliente) {
            throw ValidationException::withMessages(['cliente' => 'Tu cuenta no tiene una clienta asociada.']);
        }

        $servicio = Servicio::with('etapas')->findOrFail($datos['servicio_id']);
        $inicio = $this->fecha($datos['inicio']);
        $origen = $usuario->esAdmin() ? Cita::ORIGEN_PANEL : Cita::ORIGEN_WEB;

        try {
            $cita = $this->citas->agendar($cliente, $servicio, $inicio, $origen, $datos['notas'] ?? null);
        } catch (HorarioNoDisponibleException $e) {
            return $this->conflicto($e, $servicio, $inicio);
        }

        return (new CitaResource($cita))->response()->setStatusCode(201);
    }

    /** GET /api/web/citas/{cita} */
    public function show(Cita $cita): CitaResource
    {
        Gate::authorize('view', $cita);

        return new CitaResource($cita->load(['servicio', 'cliente', 'bloques']));
    }

    /** PATCH /api/web/citas/{cita} — reprograma a un nuevo inicio. */
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

    /** POST /api/web/citas/{cita}/cancelar */
    public function cancelar(Request $request, Cita $cita): CitaResource
    {
        Gate::authorize('cancel', $cita);

        // Una clienta solo cancela citas que aún no empiezan; la dueña puede cancelar cualquiera confirmada.
        if (! $request->user()->esAdmin() && $cita->inicio->lessThanOrEqualTo(Carbon::now())) {
            throw ValidationException::withMessages(['cita' => 'Solo puedes cancelar citas que aún no empiezan.']);
        }

        return new CitaResource($this->citas->cancelar($cita)->load(['servicio', 'cliente', 'bloques']));
    }

    /** PATCH /api/web/admin/citas/{cita}/estado — completada (visita) o no_asistio. */
    public function estado(Request $request, Cita $cita): CitaResource
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in([Cita::COMPLETADA, Cita::NO_ASISTIO])],
        ]);

        return new CitaResource($this->citas->registrarAtencion($cita, $datos['estado']));
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
