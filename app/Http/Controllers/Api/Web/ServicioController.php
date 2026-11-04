<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServicioResource;
use App\Models\Servicio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioController extends Controller
{
    /** GET /api/web/servicios — catálogo público: solo servicios activos. */
    public function index(): AnonymousResourceCollection
    {
        return ServicioResource::collection(Servicio::activos()->with('etapas')->orderBy('nombre')->get());
    }

    /** GET /api/web/servicios/{servicio} — un servicio inactivo no se muestra al público. */
    public function show(Servicio $servicio): ServicioResource
    {
        abort_unless($servicio->activo, 404);

        return new ServicioResource($servicio->load('etapas'));
    }

    /** GET /api/web/admin/servicios — incluye inactivos. */
    public function adminIndex(): AnonymousResourceCollection
    {
        return ServicioResource::collection(Servicio::with('etapas')->orderBy('nombre')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request, creando: true);

        $servicio = DB::transaction(function () use ($datos) {
            $servicio = Servicio::create([
                'nombre' => $datos['nombre'],
                'precio' => $datos['precio'] ?? null,
                'activo' => $datos['activo'] ?? true,
                'duracion_minutos' => $this->duracion($datos),
            ]);

            $this->guardarEtapas($servicio, $datos['etapas'] ?? null);

            return $servicio;
        });

        return (new ServicioResource($servicio->load('etapas')))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/web/admin/servicios/{servicio}
     * Las citas ya agendadas conservan sus bloques: el cambio aplica a reservas nuevas.
     */
    public function update(Request $request, Servicio $servicio): ServicioResource
    {
        $datos = $this->validar($request, creando: false);

        DB::transaction(function () use ($servicio, $datos) {
            $servicio->fill(array_intersect_key($datos, array_flip(['nombre', 'precio', 'activo'])));

            if (array_key_exists('etapas', $datos) || array_key_exists('duracion_minutos', $datos)) {
                $servicio->duracion_minutos = $this->duracion($datos);
            }

            $servicio->save();

            if (array_key_exists('etapas', $datos)) {
                $servicio->etapas()->delete();
                $this->guardarEtapas($servicio, $datos['etapas']);
            }
        });

        return new ServicioResource($servicio->fresh('etapas'));
    }

    /**
     * DELETE /api/web/admin/servicios/{servicio}
     * No se borra: se desactiva para conservar el historial de citas.
     */
    public function destroy(Servicio $servicio): ServicioResource
    {
        $servicio->update(['activo' => false]);

        return new ServicioResource($servicio->fresh('etapas'));
    }

    private function validar(Request $request, bool $creando): array
    {
        $req = $creando ? 'required' : 'sometimes';

        $datos = $request->validate([
            'nombre' => [$req, 'string', 'max:120'],
            'precio' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999'],
            'activo' => ['sometimes', 'boolean'],
            'duracion_minutos' => [$creando ? 'required_without:etapas' : 'sometimes', 'integer', 'min:5', 'max:720'],
            'etapas' => ['sometimes', 'nullable', 'array', 'min:1', 'max:10'],
            'etapas.*.nombre' => ['required', 'string', 'max:80'],
            'etapas.*.duracion_minutos' => ['required', 'integer', 'min:5', 'max:600'],
            'etapas.*.ocupa_estilista' => ['required', 'boolean'],
        ]);

        if (! empty($datos['etapas']) && ! collect($datos['etapas'])->contains(fn ($e) => (bool) $e['ocupa_estilista'])) {
            throw ValidationException::withMessages(['etapas' => 'Al menos una etapa debe ocupar a la estilista.']);
        }

        if (! empty($datos['etapas']) && collect($datos['etapas'])->sum('duracion_minutos') > 720) {
            throw ValidationException::withMessages(['etapas' => 'La duración total no puede pasar de 12 horas.']);
        }

        return $datos;
    }

    /** Con etapas, la duración es la suma; sin etapas, la que se envió. */
    private function duracion(array $datos): int
    {
        return ! empty($datos['etapas'])
            ? (int) collect($datos['etapas'])->sum('duracion_minutos')
            : (int) $datos['duracion_minutos'];
    }

    private function guardarEtapas(Servicio $servicio, ?array $etapas): void
    {
        foreach (array_values($etapas ?? []) as $i => $etapa) {
            $servicio->etapas()->create([
                'orden' => $i + 1,
                'nombre' => $etapa['nombre'],
                'duracion_minutos' => $etapa['duracion_minutos'],
                'ocupa_estilista' => (bool) $etapa['ocupa_estilista'],
            ]);
        }
    }
}
