<?php

namespace App\Services\Lealtad;

use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Cliente;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tarjeta de sellos: un sello por cada cita completada.
 *
 * Los sellos se calculan de las citas, no se guardan. La dueña solo puede
 * marcar citas como completadas y entregar premios; nunca sumar sellos.
 */
class TarjetaSellos
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function meta(): int
    {
        return max(1, (int) Ajuste::valor('sellos_meta'));
    }

    public function premio(): string
    {
        return (string) Ajuste::valor('sellos_premio');
    }

    /**
     * @return array{meta:int, premio:string, ganados:int, sellos:int, premios:int, faltan:int}
     */
    public function estado(Cliente $cliente): array
    {
        $meta    = $this->meta();
        $ganados = $this->ganados($cliente);
        $usados  = (int) $cliente->canjes()->sum('sellos');

        $disponibles = max(0, $ganados - $usados);
        $premios     = intdiv($disponibles, $meta);
        $enTarjeta   = $disponibles - $premios * $meta;

        return [
            'meta'    => $meta,
            'premio'  => $this->premio(),
            'ganados' => $ganados,
            'sellos'  => $enTarjeta,
            'premios' => $premios,
            'faltan'  => $meta - $enTarjeta,
        ];
    }

    /** Identificador aleatorio de la tarjeta pública; se crea la primera vez. */
    public function token(Cliente $cliente): string
    {
        if (! $cliente->tarjeta_token) {
            $cliente->forceFill(['tarjeta_token' => bin2hex(random_bytes(20))])->save();
        }

        return $cliente->tarjeta_token;
    }

    public function url(Cliente $cliente): string
    {
        return route('tarjeta', $this->token($cliente));
    }

    /** Entrega un premio. Con el bloqueo, dos clics seguidos no canjean dos veces. */
    public function canjear(Cliente $cliente): bool
    {
        return DB::transaction(function () use ($cliente) {
            $bloqueada = Cliente::whereKey($cliente->id)->lockForUpdate()->firstOrFail();
            $estado    = $this->estado($bloqueada);

            if ($estado['premios'] < 1) {
                return false;
            }

            $bloqueada->canjes()->create([
                'sellos'      => $estado['meta'],
                'premio'      => Str::limit($estado['premio'], 115),
                'canjeado_en' => now(),
            ]);

            return true;
        });
    }

    /**
     * Se llama al marcar una cita como completada. Si la clienta acaba de
     * llenar una tarjeta, le avisa por WhatsApp una sola vez por meta.
     */
    public function revisarMeta(Cliente $cliente): bool
    {
        $alcanzadas = intdiv($this->ganados($cliente), $this->meta());

        if ($alcanzadas < 1) {
            return false;
        }

        // Solo una petición gana este UPDATE: no hay avisos duplicados.
        $marcada = Cliente::whereKey($cliente->id)
            ->where('metas_avisadas', '<', $alcanzadas)
            ->update(['metas_avisadas' => $alcanzadas]);

        if ($marcada === 0 || ! $cliente->acepta_avisos || ! config('whatsapp.access_token')) {
            return false;
        }

        try {
            return $this->whatsapp->enviarPlantilla(
                $cliente->wa_id,
                'tarjeta_completa',
                config('whatsapp.plantillas.tarjeta_completa.language', 'es_MX'),
                [
                    $cliente->nombre ? (string) Str::of($cliente->nombre)->before(' ') : '😊',
                    (string) config('whatsapp.negocio.nombre'),
                    $this->premio(),
                ],
            )->successful();
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('No se pudo avisar la tarjeta completa', [
                'cliente_id' => $cliente->id,
                'mensaje'    => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function ganados(Cliente $cliente): int
    {
        return $cliente->citas()->where('estado', Cita::COMPLETADA)->count();
    }
}
