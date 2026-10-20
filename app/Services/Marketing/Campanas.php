<?php

namespace App\Services\Marketing;

use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Difusion;
use App\Models\Recontacto;
use App\Models\Servicio;
use App\Services\Agenda\DisponibilidadService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Mensajes que el negocio manda por iniciativa propia: avisos de huecos y
 * "ya te toca volver".
 *
 * La regla de costo de Meta manda el orden:
 *   - Si la clienta escribió en las últimas 24 h, se le contesta con texto y
 *     botones normales: entra en los mensajes de servicio gratis.
 *   - Si no, solo se puede con plantilla de marketing, que se cobra. Esas
 *     se limitan por hueco y por un presupuesto mensual que fija la dueña.
 */
class Campanas
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly DisponibilidadService $disponibilidad,
    ) {}

    public function costoPorMensaje(): float
    {
        return round((float) config('whatsapp.costo_marketing_usd') * (float) config('whatsapp.tipo_cambio'), 2);
    }

    /** Lo gastado este mes en plantillas de marketing entregadas a Meta (estimado, MXN). */
    public function gastoDelMes(): float
    {
        $desde = Carbon::now()->startOfMonth();

        $pagados = Difusion::where('gratis', false)->where('estado', 'enviada')->where('created_at', '>=', $desde)->count()
            + Recontacto::where('gratis', false)->where('estado', 'enviado')->where('created_at', '>=', $desde)->count();

        return round($pagados * $this->costoPorMensaje(), 2);
    }

    public function hayPresupuesto(): bool
    {
        return $this->gastoDelMes() + $this->costoPorMensaje() <= (float) Ajuste::valor('presupuesto_mensual');
    }

    // ------------------------------------------------------------------
    // Huecos
    // ------------------------------------------------------------------

    /**
     * Clientas a las que tiene sentido avisar de un hueco en una sección:
     * aceptan avisos, ya han usado esa sección, no son quien liberó el hueco
     * y no tienen otra cita a esa hora. Primero las que tienen la ventana de
     * 24 h abierta (avisarles es gratis), luego las que vinieron más reciente.
     *
     * @return Collection<int, Cliente>
     */
    public function candidatasHueco(?int $seccionId, Carbon $inicio, Carbon $fin, ?int $excluirClienteId = null): Collection
    {
        $ocupadas = Cita::query()->vigentes()->queChocanCon($inicio, $fin)->pluck('cliente_id');

        $ultimaVisita = Cita::query()
            ->vigentes()
            ->deLaSeccion($seccionId)
            ->where('inicio', '>=', Carbon::now()->subDays(180))
            ->where('inicio', '<', Carbon::now())
            ->selectRaw('cliente_id, MAX(inicio) as ultima')
            ->groupBy('cliente_id')
            ->pluck('ultima', 'cliente_id');

        return Cliente::query()
            ->notificables()
            ->whereIn('id', $ultimaVisita->keys())
            ->whereNotIn('id', $ocupadas)
            ->when($excluirClienteId, fn ($q) => $q->whereKeyNot($excluirClienteId))
            ->get()
            ->sortBy([
                fn (Cliente $a, Cliente $b) => $b->ventanaAbierta() <=> $a->ventanaAbierta(),
                fn (Cliente $a, Cliente $b) => strcmp((string) $ultimaVisita[$b->id], (string) $ultimaVisita[$a->id]),
            ])
            ->values();
    }

    /**
     * Avisa de un hueco sin que la dueña lo pida. Solo corre si ella lo
     * prendió en el panel, y nunca pasa del tope por hueco ni del presupuesto.
     *
     * @return array{gratis:int, pagadas:int, omitidas:int}
     */
    public function difundirHuecoAutomatico(int $citaId, ?int $excluirClienteId, ?int $seccionId, Carbon $inicio, Carbon $fin): array
    {
        $resultado = ['gratis' => 0, 'pagadas' => 0, 'omitidas' => 0];

        // Un hueco que empieza en menos de una hora ya no le sirve a nadie.
        if (! Ajuste::activo('huecos_auto') || $inicio->lessThan(Carbon::now()->addHour())) {
            return $resultado;
        }

        if (! $this->disponibilidad->estaLibre($inicio, $fin, seccionId: $seccionId)) {
            return $resultado;
        }

        $maxPagadas = (int) Ajuste::valor('huecos_max');

        foreach ($this->candidatasHueco($seccionId, $inicio, $fin, $excluirClienteId) as $clienta) {
            $gratis = $clienta->ventanaAbierta();

            if (! $gratis && ($resultado['pagadas'] >= $maxPagadas || ! $this->hayPresupuesto())) {
                $resultado['omitidas']++;

                continue;
            }

            $difusion = Difusion::firstOrCreate(
                ['cita_id' => $citaId, 'cliente_id' => $clienta->id, 'inicio' => $inicio],
                ['seccion_id' => $seccionId, 'fin' => $fin, 'plantilla' => $gratis ? 'texto' : 'espacio_liberado', 'gratis' => $gratis, 'automatica' => true],
            );

            if (! $difusion->wasRecentlyCreated) {
                continue;
            }

            $respuesta = $this->enviarAvisoDeHueco($clienta, $difusion);
            $this->registrar($difusion, $respuesta, 'enviada');

            if ($respuesta->successful()) {
                $gratis ? $resultado['gratis']++ : $resultado['pagadas']++;
            }
        }

        return $resultado;
    }

    public function enviarAvisoDeHueco(Cliente $clienta, Difusion $difusion): Response
    {
        $servicio = $this->servicioHabitual($clienta, $difusion->seccion_id);
        $dia      = $difusion->inicio->translatedFormat('l j \d\e F');
        $hora     = $difusion->inicio->format('H:i');

        if ($difusion->gratis) {
            return $this->whatsapp->enviarBotones(
                $clienta->wa_id,
                "¡Hola {$this->nombre($clienta)}! Se liberó un espacio para {$servicio?->nombre} el {$dia} a las {$hora}. ¿Te lo apartamos?",
                ["dif:{$difusion->id}" => 'Lo quiero', 'menu:inicio' => 'No, gracias'],
            );
        }

        return $this->whatsapp->enviarPlantilla(
            $clienta->wa_id,
            'espacio_liberado',
            config('whatsapp.plantillas.espacio_liberado.language', 'es_MX'),
            [$this->nombre($clienta), $servicio?->nombre ?? 'tu servicio', $dia, $hora],
        );
    }

    /** El servicio que esa clienta suele pedir en esa sección (el más reciente). */
    public function servicioHabitual(Cliente $clienta, ?int $seccionId): ?Servicio
    {
        $ultima = $clienta->citas()
            ->with('servicio')
            ->deLaSeccion($seccionId)
            ->whereHas('servicio', fn ($q) => $q->where('activo', true))
            ->latest('inicio')
            ->first();

        return $ultima?->servicio ?? Servicio::activos()->where('seccion_id', $seccionId)->orderBy('duracion_minutos')->first();
    }

    // ------------------------------------------------------------------
    // "Ya te toca volver"
    // ------------------------------------------------------------------

    /**
     * Clientas a las que ya les toca volver: su última visita por un
     * servicio con "volver cada X días" fue hace X días o más (hasta 60 días
     * después; más allá ya no tiene sentido insistir), no tienen cita futura
     * y no se les escribió por esto en los últimos X días.
     *
     * @return Collection<int, array{cliente: Cliente, servicio: Servicio, dias: int, gratis: bool}>
     */
    public function lesTocaVolver(): Collection
    {
        $hoy       = Carbon::now();
        $conFutura = Cita::query()->vigentes()->where('inicio', '>', $hoy)->pluck('cliente_id')->unique();
        $lista     = collect();

        foreach (Servicio::activos()->whereNotNull('volver_cada_dias')->get() as $servicio) {
            $n = $servicio->volver_cada_dias;

            $ultimas = Cita::query()
                ->vigentes()
                ->where('servicio_id', $servicio->id)
                ->where('inicio', '<', $hoy)
                ->selectRaw('cliente_id, MAX(inicio) as ultima')
                ->groupBy('cliente_id')
                ->get();

            foreach ($ultimas as $fila) {
                $dias = (int) Carbon::parse($fila->ultima)->startOfDay()->diffInDays($hoy->copy()->startOfDay());

                if ($dias < $n || $dias > $n + 60 || $conFutura->contains($fila->cliente_id)) {
                    continue;
                }

                $yaSeLeEscribio = Recontacto::where('cliente_id', $fila->cliente_id)
                    ->where('created_at', '>=', $hoy->copy()->subDays($n))
                    ->exists();

                $cliente = Cliente::find($fila->cliente_id);

                if ($yaSeLeEscribio || ! $cliente?->acepta_avisos || $lista->contains(fn ($l) => $l['cliente']->id === $cliente->id)) {
                    continue;
                }

                $lista->push(['cliente' => $cliente, 'servicio' => $servicio, 'dias' => $dias, 'gratis' => $cliente->ventanaAbierta()]);
            }
        }

        return $lista->sortByDesc('dias')->values();
    }

    /**
     * Escribe a quienes les toca volver. Gratis si tienen la ventana abierta;
     * si no, plantilla de marketing mientras alcance el presupuesto.
     *
     * @return array{gratis:int, pagadas:int, omitidas:int}
     */
    public function enviarRegresos(): array
    {
        $resultado = ['gratis' => 0, 'pagadas' => 0, 'omitidas' => 0];

        foreach ($this->lesTocaVolver() as $item) {
            ['cliente' => $clienta, 'servicio' => $servicio, 'dias' => $dias, 'gratis' => $gratis] = $item;

            if (! $gratis && ! $this->hayPresupuesto()) {
                $resultado['omitidas']++;

                continue;
            }

            $registro = Recontacto::create([
                'cliente_id'        => $clienta->id,
                'servicio_id'       => $servicio->id,
                'dias_desde_visita' => $dias,
                'gratis'            => $gratis,
            ]);

            $respuesta = $gratis
                ? $this->whatsapp->enviarBotones(
                    $clienta->wa_id,
                    "¡Hola {$this->nombre($clienta)}! Ya pasaron {$dias} días desde tu último {$servicio->nombre}. ¿Te apartamos un lugar?",
                    ["srv:{$servicio->id}" => 'Ver horarios', 'menu:inicio' => 'Ahora no'],
                )
                : $this->whatsapp->enviarPlantilla(
                    $clienta->wa_id,
                    'volver_a_agendar',
                    config('whatsapp.plantillas.volver_a_agendar.language', 'es_MX'),
                    [$this->nombre($clienta), (string) $dias, $servicio->nombre],
                );

            $this->registrar($registro, $respuesta, 'enviado');

            if ($respuesta->successful()) {
                $gratis ? $resultado['gratis']++ : $resultado['pagadas']++;
            }
        }

        return $resultado;
    }

    private function registrar($registro, Response $respuesta, string $exito): void
    {
        $registro->update($respuesta->successful()
            ? ['estado' => $exito, 'wamid' => data_get($respuesta->json(), 'messages.0.id')]
            : ['estado' => 'fallida', 'error' => Str::limit((string) data_get($respuesta->json(), 'error.message', 'HTTP ' . $respuesta->status()), 480)]);
    }

    private function nombre(Cliente $cliente): string
    {
        return $cliente->nombre ? (string) Str::of($cliente->nombre)->before(' ') : '😊';
    }
}
