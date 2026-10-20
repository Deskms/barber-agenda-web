<?php

namespace App\Services\Bot;

use App\Exceptions\HorarioNoDisponibleException;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\HorarioAtencion;
use App\Models\Servicio;
use App\Services\Agenda\CitaService;
use App\Services\Agenda\DisponibilidadService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Conversación de reserva por WhatsApp.
 *
 * El estado viaja en los ids de los botones y listas ("hora:3:2026-10-21T10:00"),
 * no en la base: dos clientas conversando a la vez no se pueden pisar, y una
 * clienta que toca un botón viejo recibe una respuesta coherente porque todo
 * se revalida al momento.
 *
 * Todo id que llega se trata como entrada no confiable: la clienta puede
 * mandar cualquier cosa, así que se valida servicio, fecha y propiedad de la cita.
 */
class BotConversacion
{
    private const DIAS_A_BUSCAR = 14;

    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly DisponibilidadService $disponibilidad,
        private readonly CitaService $citas,
        private readonly InterpreteIA $ia,
    ) {}

    /** Punto de entrada: un mensaje entrante de la clienta. */
    public function atender(Cliente $cliente, array $mensaje): void
    {
        $tipo = data_get($mensaje, 'type');

        if ($tipo === 'interactive') {
            $id = data_get($mensaje, 'interactive.button_reply.id')
                ?? data_get($mensaje, 'interactive.list_reply.id');

            $this->accion($cliente, (string) $id);

            return;
        }

        if ($tipo === 'button') {
            // Botón de respuesta rápida dentro de una plantilla.
            $this->texto($cliente, (string) data_get($mensaje, 'button.text'));

            return;
        }

        if ($tipo === 'text') {
            $this->texto($cliente, (string) data_get($mensaje, 'text.body'));

            return;
        }

        $this->whatsapp->enviarTexto($cliente->wa_id, 'Por ahora solo entiendo texto. Escribe "cita" para agendar o "menú" para ver opciones.');
    }

    // ---------------------------------------------------------------------
    // Texto libre
    // ---------------------------------------------------------------------

    private function texto(Cliente $cliente, string $texto): void
    {
        $limpio = Str::of($texto)->lower()->ascii()->trim()->toString();

        // Consentimiento: palabra exacta, sin pasar por la IA. Es un tema
        // legal y no queremos que dependa de una interpretación.
        if (in_array($limpio, ['baja', 'stop', 'alto', 'no mas avisos', 'ya no'], true)) {
            $this->darDeBaja($cliente);

            return;
        }

        if (in_array($limpio, ['alta', 'avisos', 'si avisos'], true)) {
            $cliente->update(['acepta_avisos' => true]);
            $this->whatsapp->enviarTexto($cliente->wa_id, 'Listo, te avisaremos cuando se libere un espacio. Escribe BAJA cuando ya no quieras recibirlos.');

            return;
        }

        if (in_array($limpio, ['menu', 'hola', 'inicio', 'opciones', 'buenas', 'buenos dias', 'buenas tardes'], true)) {
            $this->menu($cliente);

            return;
        }

        $intencion = $this->ia->interpretar($texto, $this->servicios());

        if ($intencion === null) {
            $intencion = ['intencion' => $this->porPalabras($limpio), 'servicio_id' => null, 'fecha' => null, 'hora' => null];
        }

        match ($intencion['intencion']) {
            'agendar'   => $this->agendarDesdeIntencion($cliente, $intencion),
            'mis_citas', 'cancelar' => $this->misCitas($cliente),
            'info'      => $this->info($cliente),
            'baja'      => $this->darDeBaja($cliente),
            'alta'      => $this->texto($cliente, 'alta'),
            default     => $this->menu($cliente),
        };
    }

    /** Respaldo sin IA: lo suficiente para que el bot no quede mudo. */
    private function porPalabras(string $texto): string
    {
        return match (true) {
            Str::contains($texto, ['cancel', 'mover', 'cambiar mi', 'mis citas', 'mi cita']) => 'mis_citas',
            Str::contains($texto, ['cita', 'agendar', 'reservar', 'apartar', 'lugar', 'espacio', 'turno']) => 'agendar',
            Str::contains($texto, ['horario', 'precio', 'cuanto', 'cuesta', 'donde', 'ubicacion', 'servicios', 'abren']) => 'info',
            default => 'otro',
        };
    }

    private function agendarDesdeIntencion(Cliente $cliente, array $intencion): void
    {
        $servicio = $intencion['servicio_id'] ? $this->servicio($intencion['servicio_id']) : null;

        if (! $servicio) {
            $this->elegirServicio($cliente);

            return;
        }

        $dia = $intencion['fecha'] ? $this->fecha($intencion['fecha']) : null;

        if (! $dia) {
            $this->elegirDia($cliente, $servicio);

            return;
        }

        if ($intencion['hora']) {
            $inicio = $dia->copy()->setTimeFromTimeString($intencion['hora']);
            $fin    = $inicio->copy()->addMinutes($servicio->duracion_minutos);

            if ($inicio->isFuture() && $this->disponibilidad->estaLibre($inicio, $fin)) {
                $this->pedirConfirmacion($cliente, $servicio, $inicio);

                return;
            }

            $this->whatsapp->enviarTexto($cliente->wa_id, 'Ese horario no está disponible. Te muestro lo que hay ese día.');
        }

        $this->elegirHora($cliente, $servicio, $dia);
    }

    // ---------------------------------------------------------------------
    // Botones y listas
    // ---------------------------------------------------------------------

    private function accion(Cliente $cliente, string $id): void
    {
        $partes = explode(':', $id, 3);

        match ($partes[0]) {
            'menu'     => match ($partes[1] ?? '') {
                'agendar' => $this->elegirServicio($cliente),
                'citas'   => $this->misCitas($cliente),
                'info'    => $this->info($cliente),
                default   => $this->menu($cliente),
            },
            'srv'      => ($s = $this->servicio($partes[1] ?? null)) ? $this->elegirDia($cliente, $s) : $this->elegirServicio($cliente),
            'dia'      => $this->accionDia($cliente, $partes),
            'hora'     => $this->accionHora($cliente, $partes, confirmar: false),
            'conf'     => $this->accionHora($cliente, $partes, confirmar: true),
            'ver'      => $this->verCita($cliente, $partes[1] ?? null),
            'cancelar' => $this->preguntarCancelacion($cliente, $partes[1] ?? null),
            'cancelok' => $this->cancelar($cliente, $partes[1] ?? null),
            default    => $this->menu($cliente),
        };
    }

    /** dia:{servicio}:{Y-m-d} y, opcionalmente, una hora desde la cual seguir ofreciendo. */
    private function accionDia(Cliente $cliente, array $partes): void
    {
        $servicio = $this->servicio($partes[1] ?? null);

        if (! $servicio) {
            $this->elegirServicio($cliente);

            return;
        }

        [$fecha, $desde] = array_pad(explode('|', $partes[2] ?? '', 2), 2, null);
        $dia = $this->fecha($fecha);

        $dia ? $this->elegirHora($cliente, $servicio, $dia, $desde) : $this->elegirDia($cliente, $servicio);
    }

    /** hora:{servicio}:{Y-m-dTH:i} → pide confirmación; conf:… → agenda. */
    private function accionHora(Cliente $cliente, array $partes, bool $confirmar): void
    {
        $servicio = $this->servicio($partes[1] ?? null);
        $inicio   = $this->momento($partes[2] ?? null);

        if (! $servicio || ! $inicio) {
            $this->elegirServicio($cliente);

            return;
        }

        if (! $confirmar) {
            $this->pedirConfirmacion($cliente, $servicio, $inicio);

            return;
        }

        try {
            $cita = $this->citas->agendar($cliente, $servicio, $inicio, Cita::ORIGEN_WHATSAPP);
        } catch (HorarioNoDisponibleException) {
            $this->whatsapp->enviarTexto($cliente->wa_id, 'Uy, ese horario se acaba de ocupar. Te muestro los que siguen libres.');
            $this->elegirHora($cliente, $servicio, $inicio->copy()->startOfDay());

            return;
        }

        $direccion = config('whatsapp.negocio.direccion');

        $this->whatsapp->enviarTexto(
            $cliente->wa_id,
            "¡Listo! Tu cita de {$servicio->nombre} quedó confirmada para el {$this->diaLargo($cita->inicio)} a las {$cita->inicio->format('H:i')}."
            . ($direccion ? "\n\n📍 {$direccion}" : '')
            . "\n\nSi necesitas cancelar, escribe \"mis citas\".",
        );
    }

    // ---------------------------------------------------------------------
    // Pantallas del flujo
    // ---------------------------------------------------------------------

    private function menu(Cliente $cliente): void
    {
        $nombre = $cliente->nombre ? ' ' . Str::of($cliente->nombre)->before(' ') : '';

        $this->whatsapp->enviarBotones(
            $cliente->wa_id,
            "¡Hola{$nombre}! Soy el asistente de citas de " . config('whatsapp.negocio.nombre') . '. ¿Qué necesitas?',
            ['menu:agendar' => 'Agendar cita', 'menu:citas' => 'Mis citas', 'menu:info' => 'Horarios y precios'],
        );
    }

    private function elegirServicio(Cliente $cliente): void
    {
        $servicios = $this->servicios();

        if ($servicios->isEmpty()) {
            $this->whatsapp->enviarTexto($cliente->wa_id, 'En este momento no hay servicios disponibles para agendar.');

            return;
        }

        $this->whatsapp->enviarLista(
            $cliente->wa_id,
            '¿Qué servicio quieres?',
            'Ver servicios',
            $servicios->map(fn (Servicio $s) => [
                'id'          => "srv:{$s->id}",
                'title'       => $s->nombre,
                'description' => $s->duracion_minutos . ' min' . ($s->precio !== null ? ' · $' . number_format((float) $s->precio, 0) : ''),
            ])->all(),
            'Servicios',
        );
    }

    private function elegirDia(Cliente $cliente, Servicio $servicio): void
    {
        $filas = collect();
        $dia   = Carbon::today();

        for ($i = 0; $i < self::DIAS_A_BUSCAR && $filas->count() < 10; $i++, $dia->addDay()) {
            $libres = $this->disponibilidad->slotsDisponibles($servicio, $dia->copy());

            if ($libres->isNotEmpty()) {
                $filas->push([
                    'id'          => "dia:{$servicio->id}:{$dia->toDateString()}",
                    'title'       => Str::ucfirst($dia->translatedFormat('l j M')),
                    'description' => $libres->count() . ' horarios, desde las ' . $libres->first()['inicio']->format('H:i'),
                ]);
            }
        }

        if ($filas->isEmpty()) {
            $this->whatsapp->enviarTexto($cliente->wa_id, "No hay lugar para {$servicio->nombre} en las próximas dos semanas. Escríbenos más adelante o pide otro servicio.");

            return;
        }

        $this->whatsapp->enviarLista($cliente->wa_id, "{$servicio->nombre}: ¿qué día te queda?", 'Ver días', $filas->all(), 'Días con lugar');
    }

    private function elegirHora(Cliente $cliente, Servicio $servicio, Carbon $dia, ?string $desde = null): void
    {
        $libres = $this->disponibilidad->slotsDisponibles($servicio, $dia->copy()->startOfDay());

        if ($desde) {
            $libres = $libres->filter(fn (array $s) => $s['inicio']->format('H:i') > $desde)->values();
        }

        if ($libres->isEmpty()) {
            $this->whatsapp->enviarTexto($cliente->wa_id, "Ese día ya no hay lugar para {$servicio->nombre}.");
            $this->elegirDia($cliente, $servicio);

            return;
        }

        // Primero la "tabla" del día, para que la clienta vea de un vistazo qué
        // está libre y qué ocupado, y entienda por qué un servicio largo
        // (un tinte de 3 h) tiene menos opciones que un corte.
        if (! $desde) {
            $this->whatsapp->enviarTexto($cliente->wa_id, $this->tablaDelDia($servicio, $dia));
        }

        // Con pasos de 15 minutos un día tiene decenas de opciones; ofrecemos
        // cada media hora y dejamos la última fila para "más tarde".
        $ofrecidos = $libres->filter(fn (array $s) => in_array((int) $s['inicio']->format('i'), [0, 30], true))->values();
        $ofrecidos = $ofrecidos->isEmpty() ? $libres : $ofrecidos;

        $filas = $ofrecidos->take(9)->map(fn (array $s) => [
            'id'    => "hora:{$servicio->id}:{$s['inicio']->format('Y-m-d\TH:i')}",
            'title' => $s['inicio']->format('H:i') . ' a ' . $s['fin']->format('H:i'),
        ]);

        if ($ofrecidos->count() > 9) {
            $ultimo = $ofrecidos[8]['inicio']->format('H:i');
            $filas->push(['id' => "dia:{$servicio->id}:{$dia->toDateString()}|{$ultimo}", 'title' => 'Más tarde…']);
        }

        $this->whatsapp->enviarLista(
            $cliente->wa_id,
            "{$servicio->nombre}, " . $this->diaLargo($dia) . '. Elige tu horario:',
            'Ver horarios',
            $filas->all(),
            'Horarios libres',
        );
    }

    private function tablaDelDia(Servicio $servicio, Carbon $dia): string
    {
        $filas = $this->disponibilidad->tramosDelDia($dia->copy()->startOfDay())
            ->map(fn (array $t) => ($t['libre'] ? '🟢' : '🔴') . " {$t['inicio']->format('H:i')} – {$t['fin']->format('H:i')}  " . ($t['libre'] ? 'libre' : 'ocupado'))
            ->implode("\n");

        return '📅 *' . Str::ucfirst($this->diaLargo($dia)) . "*\n\n{$filas}\n\n"
            . "*{$servicio->nombre}* dura " . $this->duracion($servicio->duracion_minutos)
            . ', así que solo te muestro los horarios donde cabe completo.';
    }

    private function duracion(int $minutos): string
    {
        $h = intdiv($minutos, 60);
        $m = $minutos % 60;

        return trim(($h ? "{$h} h " : '') . ($m ? "{$m} min" : ''));
    }

    private function pedirConfirmacion(Cliente $cliente, Servicio $servicio, Carbon $inicio): void
    {
        $precio = $servicio->precio !== null ? ' ($' . number_format((float) $servicio->precio, 0) . ')' : '';
        $clave  = $inicio->format('Y-m-d\TH:i');

        $this->whatsapp->enviarBotones(
            $cliente->wa_id,
            "¿Confirmo tu cita?\n\n✂️ {$servicio->nombre}{$precio}\n📅 " . Str::ucfirst($this->diaLargo($inicio)) . "\n🕐 {$inicio->format('H:i')}",
            ["conf:{$servicio->id}:{$clave}" => 'Confirmar', "dia:{$servicio->id}:{$inicio->toDateString()}" => 'Otro horario', 'menu:inicio' => 'Cancelar'],
        );
    }

    private function misCitas(Cliente $cliente): void
    {
        $proximas = $cliente->citas()
            ->with('servicio')
            ->where('estado', Cita::CONFIRMADA)
            ->where('inicio', '>', Carbon::now())
            ->orderBy('inicio')
            ->limit(10)
            ->get();

        if ($proximas->isEmpty()) {
            $this->whatsapp->enviarBotones($cliente->wa_id, 'No tienes citas próximas.', ['menu:agendar' => 'Agendar cita', 'menu:info' => 'Horarios y precios']);

            return;
        }

        $this->whatsapp->enviarLista(
            $cliente->wa_id,
            'Estas son tus próximas citas. Elige una para ver opciones.',
            'Ver mis citas',
            $proximas->map(fn (Cita $c) => [
                'id'          => "ver:{$c->id}",
                'title'       => Str::ucfirst($c->inicio->translatedFormat('D j M H:i')),
                'description' => $c->servicio->nombre,
            ])->all(),
            'Próximas citas',
        );
    }

    private function verCita(Cliente $cliente, ?string $id): void
    {
        $cita = $this->citaPropia($cliente, $id);

        if (! $cita) {
            $this->misCitas($cliente);

            return;
        }

        $this->whatsapp->enviarBotones(
            $cliente->wa_id,
            "{$cita->servicio->nombre}\n" . Str::ucfirst($this->diaLargo($cita->inicio)) . " a las {$cita->inicio->format('H:i')}",
            ["cancelar:{$cita->id}" => 'Cancelar cita', 'menu:inicio' => 'Volver'],
        );
    }

    private function preguntarCancelacion(Cliente $cliente, ?string $id): void
    {
        $cita = $this->citaPropia($cliente, $id);

        if (! $cita) {
            $this->misCitas($cliente);

            return;
        }

        $this->whatsapp->enviarBotones(
            $cliente->wa_id,
            "¿Seguro que quieres cancelar tu cita de {$cita->servicio->nombre} del {$this->diaLargo($cita->inicio)} a las {$cita->inicio->format('H:i')}?",
            ["cancelok:{$cita->id}" => 'Sí, cancelar', "ver:{$cita->id}" => 'No'],
        );
    }

    private function cancelar(Cliente $cliente, ?string $id): void
    {
        $cita = $this->citaPropia($cliente, $id);

        if (! $cita) {
            $this->whatsapp->enviarTexto($cliente->wa_id, 'Esa cita ya no está activa.');

            return;
        }

        $this->citas->cancelar($cita);

        $this->whatsapp->enviarBotones(
            $cliente->wa_id,
            'Tu cita quedó cancelada. ¿Quieres agendar otra?',
            ['menu:agendar' => 'Agendar cita', 'menu:inicio' => 'No, gracias'],
        );
    }

    private function info(Cliente $cliente): void
    {
        $dias  = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        $horas = HorarioAtencion::query()->where('activo', true)->orderBy('dia_semana')->get()
            ->map(fn (HorarioAtencion $h) => '• ' . Str::ucfirst($dias[$h->dia_semana]) . ': ' . substr($h->abre, 0, 5) . ' a ' . substr($h->cierra, 0, 5))
            ->implode("\n");

        $precios = $this->servicios()
            ->map(fn (Servicio $s) => "• {$s->nombre}: " . ($s->precio !== null ? '$' . number_format((float) $s->precio, 0) : 'consultar') . " ({$s->duracion_minutos} min)")
            ->implode("\n");

        $direccion = config('whatsapp.negocio.direccion');

        $this->whatsapp->enviarBotones(
            $cliente->wa_id,
            "🕐 *Horario*\n{$horas}\n\n💈 *Servicios*\n{$precios}" . ($direccion ? "\n\n📍 {$direccion}" : ''),
            ['menu:agendar' => 'Agendar cita', 'menu:citas' => 'Mis citas'],
        );
    }

    private function darDeBaja(Cliente $cliente): void
    {
        $cliente->update(['acepta_avisos' => false]);
        $this->whatsapp->enviarTexto($cliente->wa_id, 'Listo, ya no te enviaremos avisos de espacios libres. Tus citas y recordatorios siguen igual. Escribe ALTA si cambias de opinión.');
    }

    // ---------------------------------------------------------------------
    // Validación de lo que manda la clienta
    // ---------------------------------------------------------------------

    /** @return Collection<int, Servicio> */
    private function servicios(): Collection
    {
        return Servicio::activos()->orderBy('nombre')->limit(10)->get();
    }

    private function servicio(mixed $id): ?Servicio
    {
        return ctype_digit((string) $id) ? Servicio::activos()->find((int) $id) : null;
    }

    private function fecha(?string $fecha): ?Carbon
    {
        if (! $fecha || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return null;
        }

        try {
            $dia = Carbon::createFromFormat('Y-m-d', $fecha)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        // Ni días pasados ni más allá de lo que ofrecemos.
        return $dia->betweenIncluded(Carbon::today(), Carbon::today()->addDays(self::DIAS_A_BUSCAR * 2)) ? $dia : null;
    }

    private function momento(?string $valor): ?Carbon
    {
        if (! $valor || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $valor)) {
            return null;
        }

        $inicio = Carbon::createFromFormat('Y-m-d\TH:i', $valor)->second(0);

        return $inicio->isFuture() && $this->fecha($inicio->toDateString()) ? $inicio : null;
    }

    private function citaPropia(Cliente $cliente, ?string $id): ?Cita
    {
        if (! ctype_digit((string) $id)) {
            return null;
        }

        return $cliente->citas()
            ->with('servicio')
            ->whereKey((int) $id)
            ->where('estado', Cita::CONFIRMADA)
            ->where('inicio', '>', Carbon::now())
            ->first();
    }

    private function diaLargo(Carbon $dia): string
    {
        return $dia->translatedFormat('l j \d\e F');
    }
}
