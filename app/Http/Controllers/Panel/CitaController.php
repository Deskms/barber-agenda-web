<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\HorarioNoDisponibleException;
use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Services\Agenda\CitaService;
use App\Services\Agenda\DisponibilidadService;
use App\Services\WhatsApp\Avisos;
use App\Support\Telefono;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CitaController extends Controller
{
    public function __construct(
        private readonly CitaService $citas,
        private readonly DisponibilidadService $disponibilidad,
        private readonly Avisos $avisos,
    ) {}

    public function create(Request $request)
    {
        return view('citas.form', [
            'servicios' => Servicio::activos()->orderBy('nombre')->get(),
            'fecha'     => $request->query('fecha', Carbon::today()->toDateString()),
            'hora'      => $request->query('hora'),
            'cliente'   => $request->filled('cliente') ? Cliente::find($request->query('cliente')) : null,
        ]);
    }

    /** Horarios libres para el formulario. Sale del mismo servicio que usa WhatsApp. */
    public function disponibilidad(Request $request)
    {
        $datos = $request->validate([
            'servicio_id' => ['required', 'integer'],
            'fecha'       => ['required', 'date_format:Y-m-d'],
            'ignorar'     => ['nullable', 'integer'],
        ]);

        $servicio = Servicio::activos()->findOrFail($datos['servicio_id']);
        $dia      = Carbon::createFromFormat('Y-m-d', $datos['fecha'])->startOfDay();

        // Al mover una cita, su propio horario cuenta como libre.
        $slots = $this->disponibilidad->slotsDisponibles($servicio, $dia, ignorarCitaId: $datos['ignorar'] ?? null);

        return response()->json([
            'horarios' => $slots->map(fn ($s) => [
                'valor' => $s['inicio']->format('H:i'),
                'texto' => $s['inicio']->format('H:i') . ' – ' . $s['fin']->format('H:i'),
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'servicio_id' => ['required', 'integer'],
            'fecha'       => ['required', 'date_format:Y-m-d'],
            'hora'        => ['required', 'date_format:H:i'],
            'cliente_id'  => ['nullable', 'integer', 'exists:clientes,id'],
            'nombre'      => ['required_without:cliente_id', 'nullable', 'string', 'max:120'],
            'telefono'    => ['required_without:cliente_id', 'nullable', 'string', 'max:25'],
            'notas'       => ['nullable', 'string', 'max:500'],
        ]);

        $servicio = Servicio::activos()->find($datos['servicio_id']);

        if (! $servicio) {
            throw ValidationException::withMessages(['servicio_id' => 'Ese servicio no está activo.']);
        }

        $cliente = $this->cliente($datos);
        $inicio  = Carbon::createFromFormat('Y-m-d H:i', "{$datos['fecha']} {$datos['hora']}")->second(0);

        try {
            $cita = $this->citas->agendar($cliente, $servicio, $inicio, Cita::ORIGEN_PANEL);
        } catch (HorarioNoDisponibleException $e) {
            // El horario se ocupó mientras la dueña llenaba el formulario.
            return back()->withInput()->withErrors(['hora' => $e->getMessage() . ' Elige otro de la lista actualizada.']);
        }

        if (! empty($datos['notas'])) {
            $cita->update(['notas' => $datos['notas']]);
        }

        $mensaje = 'Cita agendada.';

        if ($request->boolean('avisar')) {
            $mensaje .= $this->avisos->confirmacion($cita)
                ? ' Se le envió la confirmación por WhatsApp.'
                : ' No se pudo enviar el WhatsApp (revisa la sección WhatsApp).';
        }

        return redirect()->route('agenda', ['fecha' => $inicio->toDateString()])->with('ok', $mensaje);
    }

    public function cancelar(Request $request, Cita $cita)
    {
        $yaEstaba = $cita->estaCancelada();
        $this->citas->cancelar($cita);

        $mensaje = 'Cita cancelada. El horario quedó libre.';

        if (! $yaEstaba && $request->boolean('avisar')) {
            $mensaje .= $this->avisos->cancelacion($cita) ? ' Se avisó a la clienta.' : ' No se pudo avisar por WhatsApp.';
        }

        return back()->with('ok', $mensaje);
    }

    public function estado(Request $request, Cita $cita)
    {
        $datos = $request->validate([
            'estado' => ['required', 'in:' . implode(',', [Cita::COMPLETADA, Cita::NO_ASISTIO, Cita::CONFIRMADA])],
        ]);

        if ($cita->estaCancelada()) {
            return back()->withErrors(['estado' => 'Una cita cancelada no cambia de estado.']);
        }

        $cita->update(['estado' => $datos['estado']]);

        return back()->with('ok', 'Estado actualizado.');
    }

    public function edit(Cita $cita)
    {
        abort_if($cita->estaCancelada(), 404);

        return view('citas.mover', ['cita' => $cita->load(['cliente', 'servicio'])]);
    }

    public function update(Request $request, Cita $cita)
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora'  => ['required', 'date_format:H:i'],
        ]);

        abort_if($cita->estaCancelada(), 404);

        $inicio = Carbon::createFromFormat('Y-m-d H:i', "{$datos['fecha']} {$datos['hora']}")->second(0);

        try {
            $this->citas->reprogramar($cita->load('servicio'), $inicio);
        } catch (HorarioNoDisponibleException $e) {
            return back()->withInput()->withErrors(['hora' => $e->getMessage()]);
        }

        return redirect()->route('agenda', ['fecha' => $inicio->toDateString()])->with('ok', 'Cita movida. El horario anterior quedó libre.');
    }

    private function cliente(array $datos): Cliente
    {
        if (! empty($datos['cliente_id'])) {
            return Cliente::findOrFail($datos['cliente_id']);
        }

        $waId = Telefono::aWaId($datos['telefono']);

        if (! $waId) {
            throw ValidationException::withMessages(['telefono' => 'Escribe el celular a 10 dígitos.']);
        }

        $cliente = Cliente::firstOrCreate(['wa_id' => $waId], ['nombre' => $datos['nombre']]);

        if (! $cliente->nombre && $datos['nombre']) {
            $cliente->update(['nombre' => $datos['nombre']]);
        }

        return $cliente;
    }
}
