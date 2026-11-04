<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\CitaResource;
use App\Http\Resources\ClienteResource;
use App\Http\Resources\Fecha;
use App\Models\Cita;
use App\Models\Cliente;
use App\Support\Telefono;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/** Clientas y su consentimiento (SCRUM-26), historial (visitas) y conversación (SCRUM-40). */
class ClienteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['buscar' => ['sometimes', 'string', 'max:120']]);
        $buscar = $request->string('buscar')->trim()->toString();

        return ClienteResource::collection(
            Cliente::query()
                ->withCount('citas')
                ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('wa_id', 'like', '%'.preg_replace('/\D+/', '', $buscar).'%')))
                ->orderBy('nombre')
                ->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'telefono' => ['required', 'string', 'max:20'],
            'acepta_avisos' => ['sometimes', 'boolean'],
        ]);

        $waId = $this->telefonoUnico($datos['telefono']);

        $cliente = Cliente::create([
            'nombre' => $datos['nombre'],
            'wa_id' => $waId,
            'acepta_avisos' => $datos['acepta_avisos'] ?? false,
        ]);

        return (new ClienteResource($cliente))->response()->setStatusCode(201);
    }

    public function show(Cliente $cliente): ClienteResource
    {
        return new ClienteResource($cliente->loadCount('citas'));
    }

    public function update(Request $request, Cliente $cliente): ClienteResource
    {
        $datos = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:120'],
            'telefono' => ['sometimes', 'string', 'max:20'],
            'acepta_avisos' => ['sometimes', 'boolean'],
        ]);

        if (isset($datos['telefono'])) {
            $cliente->wa_id = $this->telefonoUnico($datos['telefono'], $cliente->id);
        }

        $cliente->fill(array_intersect_key($datos, array_flip(['nombre', 'acepta_avisos'])))->save();

        return new ClienteResource($cliente->loadCount('citas'));
    }

    /** GET /api/web/admin/clientes/{cliente}/historial */
    public function historial(Cliente $cliente): JsonResponse
    {
        return response()->json($this->armarHistorial($cliente));
    }

    /** GET /api/web/admin/clientes/{cliente}/mensajes — solo lectura. */
    public function mensajes(Cliente $cliente): JsonResponse
    {
        $eventos = $cliente->eventosWhatsapp()->where('tipo', 'mensaje')->orderBy('created_at')->get();

        return response()->json([
            'cliente_id' => $cliente->id,
            'mensajes' => $eventos->map(fn ($e) => [
                'wamid' => $e->wamid,
                'direccion' => $e->direccion,
                'tipo' => data_get($e->payload, 'type', data_get($e->payload, 'plantilla') ? 'plantilla' : null),
                'texto' => data_get($e->payload, 'text.body')
                    ?? data_get($e->payload, 'interactive.list_reply.title')
                    ?? data_get($e->payload, 'interactive.button_reply.title')
                    ?? (data_get($e->payload, 'plantilla') ? 'Plantilla: '.data_get($e->payload, 'plantilla') : null),
                'fecha' => Fecha::iso($e->created_at),
            ])->values(),
        ]);
    }

    /** Resumen usado también por la cuenta de la clienta. */
    public static function armarHistorial(Cliente $cliente): array
    {
        $citas = $cliente->citas()->with(['servicio', 'bloques'])->orderByDesc('inicio')->get();
        $visitas = $citas->where('estado', Cita::COMPLETADA);

        return [
            'cliente' => new ClienteResource($cliente),
            'resumen' => [
                'visitas' => $visitas->count(),
                'no_asistio' => $citas->where('estado', Cita::NO_ASISTIO)->count(),
                'canceladas' => $citas->where('estado', Cita::CANCELADA)->count(),
                'ultima_visita' => Fecha::iso($visitas->first()?->inicio),
            ],
            'citas' => CitaResource::collection($citas),
        ];
    }

    private function telefonoUnico(string $telefono, ?int $ignorarId = null): string
    {
        $waId = Telefono::normalizar($telefono);

        if (! $waId) {
            throw ValidationException::withMessages(['telefono' => 'El teléfono no es válido.']);
        }

        if (Cliente::where('wa_id', $waId)->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))->exists()) {
            throw ValidationException::withMessages(['telefono' => 'Ya existe una clienta con ese teléfono.']);
        }

        return $waId;
    }
}
