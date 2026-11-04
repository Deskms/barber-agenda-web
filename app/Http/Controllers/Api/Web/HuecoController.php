<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClienteResource;
use App\Http\Resources\HuecoResource;
use App\Models\Hueco;
use App\Services\Agenda\HuecoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/** Espacios liberados para citas de última hora (SCRUM-29). La app móvil avisa a las candidatas. */
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
            'hueco' => new HuecoResource($hueco),
            'candidatas' => ClienteResource::collection($this->huecos->candidatas($hueco)),
        ]);
    }
}
