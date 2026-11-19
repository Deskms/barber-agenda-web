<?php

namespace App\Http\Controllers\Api\Movil;

use App\Exceptions\HorarioNoDisponibleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CitaResource;
use App\Http\Resources\ServicioResource;
use App\Models\Cita;
use App\Services\Agenda\CitaService;
use App\Services\Agenda\Huecos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Huecos que se le avisaron a la clienta por WhatsApp y todavía puede tomar.
 * Aceptar intenta agendar; el servidor decide quién se queda el lugar.
 */
class OfertaController extends Controller
{
    /** GET /api/movil/ofertas */
    public function index(Request $request, Huecos $huecos): JsonResponse
    {
        $ofertas = $huecos->ofertasPara($request->user())->map(fn (array $o) => [
            'id'       => $o['difusion']->id,
            'inicio'   => $o['difusion']->inicio->toIso8601String(),
            'servicio' => new ServicioResource($o['servicio']),
        ]);

        return response()->json(['data' => $ofertas]);
    }

    /** POST /api/movil/ofertas/{oferta}/aceptar */
    public function aceptar(Request $request, int $oferta, Huecos $huecos, CitaService $citas): JsonResponse
    {
        $cliente  = $request->user();
        $difusion = \App\Models\Difusion::where('cliente_id', $cliente->id)->findOrFail($oferta);

        if ($difusion->inicio->isPast() || $difusion->estado !== Huecos::ENVIADA) {
            return response()->json(['mensaje' => 'Esa oferta ya no está vigente.'], 409);
        }

        $servicio = $huecos->servicioPara($cliente, $difusion);
        if (! $servicio) {
            return response()->json(['mensaje' => 'Ese espacio ya no está disponible. Elige otro horario en Agendar.'], 409);
        }

        try {
            $cita = $citas->agendar($cliente, $servicio, $difusion->inicio->copy(), Cita::ORIGEN_MOVIL);
        } catch (HorarioNoDisponibleException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 409);
        }

        return response()->json(['data' => new CitaResource($cita->load(['servicio', 'seccion']))], 201);
    }
}
