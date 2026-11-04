<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\BloqueoResource;
use App\Http\Resources\CitaResource;
use App\Http\Resources\Fecha;
use App\Http\Resources\HorarioResource;
use App\Http\Resources\HuecoResource;
use App\Models\BloqueoAgenda;
use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Models\Hueco;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Vista de agenda del panel de la dueña (SCRUM-23) y sondeo de cambios (SCRUM-39). */
class AgendaController extends Controller
{
    /** GET /api/web/admin/agenda?fecha=2026-11-10&vista=dia|semana */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha' => ['sometimes', 'date_format:Y-m-d'],
            'vista' => ['sometimes', Rule::in(['dia', 'semana'])],
        ]);

        $fecha = isset($datos['fecha']) ? Carbon::createFromFormat('Y-m-d', $datos['fecha'])->startOfDay() : Carbon::today();
        $vista = $datos['vista'] ?? 'dia';
        $desde = $vista === 'semana' ? $fecha->copy()->startOfWeek(Carbon::MONDAY) : $fecha->copy();
        $hasta = $vista === 'semana' ? $desde->copy()->addWeek() : $desde->copy()->addDay();

        return response()->json([
            'vista' => $vista,
            'desde' => Fecha::iso($desde),
            'hasta' => Fecha::iso($hasta),
            'horarios' => HorarioResource::collection(HorarioAtencion::orderBy('dia_semana')->get()),
            'citas' => CitaResource::collection(
                Cita::query()->queChocanCon($desde, $hasta)->with(['cliente', 'servicio', 'bloques'])->orderBy('inicio')->get()
            ),
            'bloqueos' => BloqueoResource::collection(
                BloqueoAgenda::query()->queChocanCon($desde, $hasta)->orderBy('inicio')->get()
            ),
            'huecos' => HuecoResource::collection(
                Hueco::query()->where('inicio', '<', $hasta)->where('fin', '>', $desde)
                    ->where('fin', '>', Carbon::now())->with('servicio')->orderBy('inicio')->get()
            ),
        ]);
    }

    /**
     * GET /api/web/admin/agenda/cambios?desde=2026-11-10T09:00:00-06:00
     * Devuelve las citas creadas o modificadas después de "desde". El panel
     * lo consulta cada 30 segundos y usa "servidor_ahora" en la siguiente llamada.
     */
    public function cambios(Request $request): JsonResponse
    {
        $datos = $request->validate(['desde' => ['required', 'date']]);
        $ahora = Carbon::now();

        return response()->json([
            'servidor_ahora' => Fecha::iso($ahora),
            'citas' => CitaResource::collection(
                Cita::query()
                    ->where('updated_at', '>', Carbon::parse($datos['desde'])->setTimezone(config('app.timezone')))
                    ->with(['cliente', 'servicio', 'bloques'])
                    ->orderBy('updated_at')
                    ->get()
            ),
        ]);
    }
}
