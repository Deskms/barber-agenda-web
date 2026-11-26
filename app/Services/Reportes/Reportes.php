<?php

namespace App\Services\Reportes;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\HorarioAtencion;
use App\Models\Seccion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reportes de la dueña, calculados solo con lo que hay en la base.
 *
 * Cada reporte regresa la misma forma (título, columnas, filas y un resumen)
 * para que la pantalla, el PDF y el Excel salgan de un solo lugar.
 * Los montos usan el precio actual del servicio: las citas no guardan lo
 * cobrado, por eso se rotulan como estimados.
 */
class Reportes
{
    public const PERIODOS = ['dia' => 'Día', 'semana' => 'Semana', 'mes' => 'Mes'];

    public const TIPOS = [
        'resumen'       => 'Resumen general',
        'servicios'     => 'Servicios más pedidos',
        'ventas'        => 'Ventas por día',
        'tiempos'       => 'Tiempos y ocupación',
        'horas'         => 'Horas más pedidas',
        'clientas'      => 'Clientas',
        'cancelaciones' => 'Cancelaciones y faltas',
        'detalle'       => 'Detalle de citas',
    ];

    /** Citas que sí ocupan agenda (las que cuentan como pedidas). */
    private const VIGENTES = [Cita::CONFIRMADA, Cita::COMPLETADA];

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    public function periodo(string $periodo, Carbon $fecha): array
    {
        return match ($periodo) {
            'dia' => [
                $fecha->copy()->startOfDay(),
                $fecha->copy()->endOfDay(),
                Str::ucfirst($fecha->translatedFormat('l j \d\e F Y')),
            ],
            'semana' => [
                $fecha->copy()->startOfWeek(Carbon::MONDAY),
                $fecha->copy()->endOfWeek(Carbon::SUNDAY),
                'Semana del ' . $fecha->copy()->startOfWeek(Carbon::MONDAY)->translatedFormat('j \d\e F')
                    . ' al ' . $fecha->copy()->endOfWeek(Carbon::SUNDAY)->translatedFormat('j \d\e F Y'),
            ],
            default => [
                $fecha->copy()->startOfMonth(),
                $fecha->copy()->endOfMonth(),
                Str::ucfirst($fecha->translatedFormat('F Y')),
            ],
        };
    }

    /**
     * @return array{titulo: string, columnas: array<int, array{0: string, 1: string}>, filas: array<int, array<int, mixed>>, resumen: array<string, string>, nota: ?string}
     */
    public function generar(string $tipo, Carbon $desde, Carbon $hasta): array
    {
        $citas = Cita::query()
            ->with(['servicio', 'seccion', 'cliente'])
            ->whereBetween('inicio', [$desde, $hasta])
            ->orderBy('inicio')
            ->get();

        $reporte = match ($tipo) {
            'servicios'     => $this->servicios($citas),
            'ventas'        => $this->ventas($citas, $desde, $hasta),
            'tiempos'       => $this->tiempos($citas, $desde, $hasta),
            'horas'         => $this->horas($citas),
            'clientas'      => $this->clientas($citas),
            'cancelaciones' => $this->cancelaciones($citas),
            'detalle'       => $this->detalle($citas),
            default         => $this->resumen($citas, $desde, $hasta),
        };

        return $reporte + ['titulo' => self::TIPOS[$tipo] ?? self::TIPOS['resumen'], 'nota' => null];
    }

    // ------------------------------------------------------------------ reportes

    private function resumen(Collection $citas, Carbon $desde, Carbon $hasta): array
    {
        $estado = fn (string $e) => $citas->where('estado', $e);
        $clientasAtendidas = $estado(Cita::COMPLETADA)->pluck('cliente_id')->unique();
        $nuevas = Cliente::whereBetween('created_at', [$desde, $hasta])->count();
        $recuperadas = $citas->whereNotNull('difusion_id')->whereIn('estado', self::VIGENTES);

        $filas = [
            ['Citas agendadas (sin canceladas)', $citas->whereIn('estado', [...self::VIGENTES, Cita::NO_ASISTIO])->count()],
            ['Atendidas', $estado(Cita::COMPLETADA)->count()],
            ['Por atender', $estado(Cita::CONFIRMADA)->count()],
            ['Canceladas', $estado(Cita::CANCELADA)->count()],
            ['No asistió', $estado(Cita::NO_ASISTIO)->count()],
            ['Ingreso obtenido (atendidas)', $this->suma($estado(Cita::COMPLETADA))],
            ['Ingreso por cobrar (por atender)', $this->suma($estado(Cita::CONFIRMADA))],
            ['Ingreso perdido (canceladas y faltas)', $this->suma($estado(Cita::CANCELADA)->merge($estado(Cita::NO_ASISTIO)))],
            ['Ticket promedio (atendidas)', $estado(Cita::COMPLETADA)->count() ? round($this->suma($estado(Cita::COMPLETADA)) / $estado(Cita::COMPLETADA)->count(), 2) : 0],
            ['Clientas atendidas', $clientasAtendidas->count()],
            ['Clientas nuevas registradas', $nuevas],
            ['Espacios recuperados con avisos', $recuperadas->count()],
            ['Citas por WhatsApp', $citas->where('origen', Cita::ORIGEN_WHATSAPP)->count()],
            ['Citas por la app', $citas->where('origen', Cita::ORIGEN_MOVIL)->count()],
            ['Citas desde el panel', $citas->where('origen', Cita::ORIGEN_PANEL)->count()],
        ];

        $dinero = [5, 6, 7, 8];

        return [
            'columnas' => [['Indicador', 'texto'], ['Valor', 'numero']],
            'filas'    => array_map(fn ($f, $i) => in_array($i, $dinero, true) ? [$f[0], ['dinero', $f[1]]] : $f, $filas, array_keys($filas)),
            'resumen'  => [],
        ];
    }

    private function servicios(Collection $citas): array
    {
        $vigentes = $citas->whereIn('estado', self::VIGENTES);
        $total    = max(1, $vigentes->count());

        $filas = $vigentes
            ->groupBy(fn (Cita $c) => $c->servicio_id)
            ->map(function (Collection $grupo) use ($total, $citas) {
                $servicio = $grupo->first()->servicio;
                $atendidas = $grupo->where('estado', Cita::COMPLETADA);

                return [
                    $servicio?->nombre ?? 'Sin servicio',
                    $grupo->first()->seccion?->nombre ?? '—',
                    $grupo->count(),
                    $atendidas->count(),
                    $citas->where('servicio_id', $grupo->first()->servicio_id)->whereIn('estado', [Cita::CANCELADA, Cita::NO_ASISTIO])->count(),
                    round(100 * $grupo->count() / $total, 1),
                    $this->suma($atendidas),
                ];
            })
            ->sortByDesc(fn ($f) => $f[2])
            ->values()
            ->all();

        $primero = $filas[0][0] ?? null;

        return [
            'columnas' => [['Servicio', 'texto'], ['Sección', 'texto'], ['Pedidas', 'entero'], ['Atendidas', 'entero'], ['Canceladas o faltas', 'entero'], ['% del total', 'porcentaje'], ['Ingreso obtenido', 'dinero']],
            'filas'    => $filas,
            'resumen'  => array_filter([
                'Más pedido'      => $primero,
                'Total de citas'  => (string) $vigentes->count(),
                'Ingreso obtenido'=> $this->pesos($this->suma($vigentes->where('estado', Cita::COMPLETADA))),
            ]),
        ];
    }

    private function ventas(Collection $citas, Carbon $desde, Carbon $hasta): array
    {
        $filas = [];
        $totales = [0, 0.0, 0.0, 0.0];

        for ($dia = $desde->copy()->startOfDay(); $dia->lte($hasta); $dia->addDay()) {
            $delDia = $citas->filter(fn (Cita $c) => $c->inicio->isSameDay($dia));

            if ($delDia->isEmpty() && $desde->diffInDays($hasta) > 0) {
                continue;
            }

            $atendidas = $delDia->where('estado', Cita::COMPLETADA);
            $fila = [
                Str::ucfirst($dia->translatedFormat('D j M')),
                $atendidas->count(),
                $this->suma($atendidas),
                $this->suma($delDia->where('estado', Cita::CONFIRMADA)),
                $this->suma($delDia->whereIn('estado', [Cita::CANCELADA, Cita::NO_ASISTIO])),
            ];
            $filas[] = $fila;
            $totales = [$totales[0] + $fila[1], $totales[1] + $fila[2], $totales[2] + $fila[3], $totales[3] + $fila[4]];
        }

        $mejor = collect($filas)->sortByDesc(fn ($f) => $f[2])->first();

        return [
            'columnas' => [['Día', 'texto'], ['Citas atendidas', 'entero'], ['Obtenido', 'dinero'], ['Por cobrar', 'dinero'], ['Perdido', 'dinero']],
            'filas'    => $filas,
            'total'    => ['Total', $totales[0], round($totales[1], 2), round($totales[2], 2), round($totales[3], 2)],
            'resumen'  => array_filter([
                'Obtenido'       => $this->pesos($totales[1]),
                'Por cobrar'     => $this->pesos($totales[2]),
                'Perdido'        => $this->pesos($totales[3]),
                'Mejor día'      => $mejor && $mejor[2] > 0 ? $mejor[0] . ' (' . $this->pesos($mejor[2]) . ')' : null,
            ]),
        ];
    }

    private function tiempos(Collection $citas, Carbon $desde, Carbon $hasta): array
    {
        $vigentes = $citas->whereIn('estado', self::VIGENTES);

        $filas = $vigentes
            ->groupBy('servicio_id')
            ->map(function (Collection $grupo) {
                $minutos = $grupo->sum(fn (Cita $c) => $c->inicio->diffInMinutes($c->fin));
                $llegadas = $grupo->filter(fn (Cita $c) => $c->llegada_en !== null);
                // Positivo: llegó antes de su hora. Negativo: llegó tarde.
                $anticipacion = $llegadas->isNotEmpty()
                    ? round($llegadas->avg(fn (Cita $c) => $c->llegada_en->diffInMinutes($c->inicio, false)), 1)
                    : null;

                return [
                    $grupo->first()->servicio?->nombre ?? 'Sin servicio',
                    (int) ($grupo->first()->servicio?->duracion_minutos ?? 0),
                    $grupo->count(),
                    round($minutos / 60, 1),
                    $llegadas->count(),
                    $anticipacion ?? '—',
                ];
            })
            ->sortByDesc(fn ($f) => $f[3])
            ->values()
            ->all();

        $resumen = [];
        foreach ($this->ocupacion($vigentes, $desde, $hasta) as $s) {
            $resumen['Ocupación ' . $s['seccion']] = $s['porcentaje'] . ' % (' . $s['horas'] . ' h)';
        }

        $llegadas = $vigentes->filter(fn (Cita $c) => $c->llegada_en !== null);
        $resumen['Horas agendadas'] = round($vigentes->sum(fn (Cita $c) => $c->inicio->diffInMinutes($c->fin)) / 60, 1) . ' h';
        $resumen['Llegada promedio'] = $llegadas->isEmpty()
            ? 'Sin llegadas registradas'
            : $this->textoAnticipacion($llegadas->avg(fn (Cita $c) => $c->llegada_en->diffInMinutes($c->inicio, false)));

        return [
            'columnas' => [['Servicio', 'texto'], ['Duración (min)', 'entero'], ['Citas', 'entero'], ['Horas ocupadas', 'decimal'], ['Llegadas registradas', 'entero'], ['Min. de anticipación', 'decimal']],
            'filas'    => $filas,
            'resumen'  => $resumen,
            'nota'     => 'Minutos de anticipación: positivo si la clienta llegó antes de su hora; negativo si llegó tarde.',
        ];
    }

    private function horas(Collection $citas): array
    {
        $vigentes = $citas->whereIn('estado', self::VIGENTES);
        $total    = max(1, $vigentes->count());

        $filas = $vigentes
            ->groupBy(fn (Cita $c) => $c->inicio->format('H') . ':00')
            ->map(fn (Collection $g, string $hora) => [$hora, $g->count(), round(100 * $g->count() / $total, 1)])
            ->sortKeys()
            ->values()
            ->all();

        $dias = $vigentes
            ->groupBy(fn (Cita $c) => $c->inicio->dayOfWeekIso)
            ->map->count()
            ->sortDesc();

        $pico = collect($filas)->sortByDesc(fn ($f) => $f[1])->first();

        return [
            'columnas' => [['Hora', 'texto'], ['Citas', 'entero'], ['% del total', 'porcentaje']],
            'filas'    => $filas,
            'resumen'  => array_filter([
                'Hora más pedida' => $pico ? $pico[0] . ' (' . $pico[1] . ' citas)' : null,
                'Día más pedido'  => $dias->isNotEmpty() ? Str::ucfirst(Carbon::now()->startOfWeek(Carbon::MONDAY)->addDays($dias->keys()->first() - 1)->translatedFormat('l')) . ' (' . $dias->first() . ' citas)' : null,
            ]),
        ];
    }

    private function clientas(Collection $citas): array
    {
        $filas = $citas
            ->groupBy('cliente_id')
            ->map(fn (Collection $g) => [
                $g->first()->cliente?->nombre ?? 'Sin nombre',
                $g->whereIn('estado', [...self::VIGENTES, Cita::NO_ASISTIO])->count(),
                $g->where('estado', Cita::COMPLETADA)->count(),
                $g->where('estado', Cita::NO_ASISTIO)->count(),
                $g->where('estado', Cita::CANCELADA)->count(),
                $this->suma($g->where('estado', Cita::COMPLETADA)),
                optional($g->where('estado', Cita::COMPLETADA)->max('inicio'))->format('d/m/Y') ?? '—',
            ])
            ->sortByDesc(fn ($f) => [$f[5], $f[1]])
            ->values()
            ->all();

        return [
            'columnas' => [['Clienta', 'texto'], ['Citas', 'entero'], ['Atendidas', 'entero'], ['Faltas', 'entero'], ['Canceladas', 'entero'], ['Gasto estimado', 'dinero'], ['Última visita', 'texto']],
            'filas'    => $filas,
            'resumen'  => ['Clientas con citas' => (string) count($filas)],
        ];
    }

    private function cancelaciones(Collection $citas): array
    {
        $perdidas = $citas->whereIn('estado', [Cita::CANCELADA, Cita::NO_ASISTIO]);

        return [
            'columnas' => [['Fecha', 'texto'], ['Hora', 'texto'], ['Clienta', 'texto'], ['Servicio', 'texto'], ['Estado', 'texto'], ['Origen', 'texto'], ['Monto perdido', 'dinero']],
            'filas'    => $perdidas->map(fn (Cita $c) => [
                $c->inicio->format('d/m/Y'),
                $c->inicio->format('H:i'),
                $c->cliente?->nombre ?? 'Sin nombre',
                $c->servicio?->nombre ?? '—',
                $c->estado === Cita::CANCELADA ? 'Cancelada' : 'No asistió',
                $this->origen($c->origen),
                (float) ($c->servicio?->precio ?? 0),
            ])->values()->all(),
            'resumen'  => [
                'Canceladas'     => (string) $perdidas->where('estado', Cita::CANCELADA)->count(),
                'No asistió'     => (string) $perdidas->where('estado', Cita::NO_ASISTIO)->count(),
                'Monto perdido'  => $this->pesos($this->suma($perdidas)),
            ],
        ];
    }

    private function detalle(Collection $citas): array
    {
        $estados = [Cita::CONFIRMADA => 'Confirmada', Cita::COMPLETADA => 'Atendida', Cita::CANCELADA => 'Cancelada', Cita::NO_ASISTIO => 'No asistió'];

        return [
            'columnas' => [['Fecha', 'texto'], ['Hora', 'texto'], ['Clienta', 'texto'], ['Servicio', 'texto'], ['Sección', 'texto'], ['Estado', 'texto'], ['Origen', 'texto'], ['Precio', 'dinero']],
            'filas'    => $citas->map(fn (Cita $c) => [
                $c->inicio->format('d/m/Y'),
                $c->inicio->format('H:i') . '–' . $c->fin->format('H:i'),
                $c->cliente?->nombre ?? 'Sin nombre',
                $c->servicio?->nombre ?? '—',
                $c->seccion?->nombre ?? '—',
                $estados[$c->estado] ?? $c->estado,
                $this->origen($c->origen),
                (float) ($c->servicio?->precio ?? 0),
            ])->values()->all(),
            'resumen'  => ['Citas' => (string) $citas->count()],
        ];
    }

    // ------------------------------------------------------------------ apoyo

    private function suma(Collection $citas): float
    {
        return round($citas->sum(fn (Cita $c) => (float) ($c->servicio?->precio ?? 0)), 2);
    }

    public function pesos(float|int $n): string
    {
        return '$' . number_format((float) $n, 2);
    }

    private function origen(?string $origen): string
    {
        return ['whatsapp' => 'WhatsApp', 'panel' => 'Panel', 'movil' => 'App'][$origen] ?? (string) $origen;
    }

    private function textoAnticipacion(float $minutos): string
    {
        $m = (int) round(abs($minutos));

        return $minutos >= 0 ? "{$m} min antes de su hora" : "{$m} min tarde";
    }

    /** @return Collection<int, array{seccion: string, porcentaje: int, horas: float}> */
    private function ocupacion(Collection $vigentes, Carbon $desde, Carbon $hasta): Collection
    {
        $horario  = HorarioAtencion::query()->where('activo', true)->get()->keyBy('dia_semana');
        $abiertos = 0;

        for ($dia = $desde->copy()->startOfDay(); $dia->lte($hasta); $dia->addDay()) {
            $h = $horario->get($dia->dayOfWeek);
            if ($h) {
                $abiertos += Carbon::parse($h->abre)->diffInMinutes(Carbon::parse($h->cierra));
            }
        }

        return Seccion::query()->orderBy('nombre')->get()->map(function (Seccion $s) use ($vigentes, $abiertos) {
            $minutos = $vigentes->where('seccion_id', $s->id)->sum(fn (Cita $c) => $c->inicio->diffInMinutes($c->fin));

            return [
                'seccion'    => $s->nombre,
                'porcentaje' => $abiertos > 0 ? (int) round(100 * $minutos / $abiertos) : 0,
                'horas'      => round($minutos / 60, 1),
            ];
        });
    }
}
