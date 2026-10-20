<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\HorarioAtencion;
use App\Models\Servicio;
use Illuminate\Http\Request;

/** Servicios y horarios de atención: lo que define qué se puede reservar. */
class CatalogoController extends Controller
{
    private const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    public function servicios()
    {
        return view('catalogo.servicios', ['servicios' => Servicio::orderByDesc('activo')->orderBy('nombre')->get()]);
    }

    public function guardarServicio(Request $request, ?Servicio $servicio = null)
    {
        $datos = $request->validate([
            'nombre'           => ['required', 'string', 'max:60'],
            'duracion_minutos' => ['required', 'integer', 'min:15', 'max:480', 'multiple_of:15'],
            'precio'           => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'activo'           => ['nullable', 'boolean'],
        ], ['duracion_minutos.multiple_of' => 'La duración debe ser múltiplo de 15 minutos.']);

        $datos['activo'] = $request->boolean('activo');

        // Desactivar saca el servicio de la oferta; las citas ya agendadas no cambian.
        $servicio?->exists ? $servicio->update($datos) : Servicio::create($datos);

        return redirect()->route('servicios')->with('ok', 'Servicio guardado.');
    }

    public function horarios()
    {
        $horarios = HorarioAtencion::all()->keyBy('dia_semana');

        return view('catalogo.horarios', ['horarios' => $horarios, 'dias' => self::DIAS]);
    }

    public function guardarHorarios(Request $request)
    {
        $datos = $request->validate([
            'dias'          => ['array'],
            'dias.*.activo' => ['nullable', 'boolean'],
            'dias.*.abre'   => ['nullable', 'date_format:H:i'],
            'dias.*.cierra' => ['nullable', 'date_format:H:i'],
        ]);

        foreach (range(0, 6) as $dia) {
            $fila   = $datos['dias'][$dia] ?? [];
            $activo = ! empty($fila['activo']);

            if ($activo && (empty($fila['abre']) || empty($fila['cierra']) || $fila['abre'] >= $fila['cierra'])) {
                return back()->withInput()->withErrors(["dias.{$dia}" => self::DIAS[$dia] . ': la hora de cierre debe ser después de la de apertura.']);
            }

            HorarioAtencion::updateOrCreate(
                ['dia_semana' => $dia],
                ['activo' => $activo, 'abre' => $fila['abre'] ?? '10:00', 'cierra' => $fila['cierra'] ?? '19:00'],
            );
        }

        return back()->with('ok', 'Horario guardado.');
    }
}
