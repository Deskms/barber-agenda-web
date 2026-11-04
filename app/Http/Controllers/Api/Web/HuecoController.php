<?php

namespace App\Http\Controllers\Api\Web;

use App\Exceptions\WhatsAppNoConfiguradoException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClienteResource;
use App\Http\Resources\HuecoResource;
use App\Models\Hueco;
use App\Services\Agenda\HuecoService;
use App\Services\Difusion\DifusionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/** Espacios liberados y su difusión manual por WhatsApp (SCRUM-29, 35, 36, 42). */
class HuecoController extends Controller
{
    public function __construct(
        private readonly HuecoService $huecos,
    ) {}

    /** GET /api/web/admin/huecos — huecos futuros, con "sigue_libre" calculado en el momento. */
    public function index(): AnonymousResourceCollection
    {
        $lista = Hueco::query()
            ->where('fin', '>', Carbon::now())
            ->with(['servicio.etapas', 'cita'])
            ->withCount('difusiones')
            ->orderBy('inicio')
            ->get()
            ->each(fn (Hueco $h) => $h->sigue_libre = $this->huecos->sigueLibre($h));

        return HuecoResource::collection($lista);
    }

    /** GET /api/web/admin/huecos/{hueco}/candidatas */
    public function candidatas(Hueco $hueco): JsonResponse
    {
        $hueco->load(['servicio.etapas', 'cita']);
        $hueco->sigue_libre = $this->huecos->sigueLibre($hueco);

        return response()->json([
            'hueco' => new HuecoResource($hueco->loadCount('difusiones')),
            'candidatas' => ClienteResource::collection($this->huecos->candidatas($hueco)),
        ]);
    }

    /**
     * POST /api/web/admin/huecos/{hueco}/difundir { "cliente_ids": [1, 2] }
     * Solo se envía a las elegibles; el resto se devuelve como omitidas con la razón.
     */
    public function difundir(Request $request, Hueco $hueco, DifusionService $difusion): JsonResponse
    {
        $datos = $request->validate([
            'cliente_ids' => ['required', 'array', 'min:1', 'max:200'],
            'cliente_ids.*' => ['integer', 'distinct', 'exists:clientes,id'],
        ]);

        $hueco->load(['servicio.etapas', 'cita']);

        if ($hueco->fin->lessThanOrEqualTo(Carbon::now())) {
            return response()->json(['message' => 'El hueco ya pasó.'], 422);
        }

        if (! $this->huecos->sigueLibre($hueco)) {
            return response()->json(['message' => 'El hueco ya no está libre; no se difunde.'], 409);
        }

        $elegibles = $this->huecos->candidatas($hueco)->keyBy('id');
        $elegidas = collect($datos['cliente_ids'])->filter(fn ($id) => $elegibles->has($id));
        $omitidas = collect($datos['cliente_ids'])->reject(fn ($id) => $elegibles->has($id))
            ->map(fn ($id) => ['cliente_id' => $id, 'razon' => 'No es elegible: sin consentimiento, ya avisada, con cita en ese horario o es quien liberó el hueco.'])
            ->values();

        try {
            $resultado = $difusion->difundir($hueco, $elegidas->map(fn ($id) => $elegibles->get($id))->values());
        } catch (WhatsAppNoConfiguradoException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json([
            'hueco_id' => $hueco->id,
            'enviadas' => $resultado['enviadas'],
            'fallidas' => $resultado['fallidas'],
            'omitidas' => $omitidas,
        ]);
    }
}
