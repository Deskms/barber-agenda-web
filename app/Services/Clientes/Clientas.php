<?php

namespace App\Services\Clientes;

use App\Models\Cita;
use App\Models\Cliente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bajas e inactividad de clientas.
 *
 * Borrar = anonimizar: se quitan nombre, teléfono, correo y accesos, y se
 * cancelan sus citas futuras. Las citas pasadas se quedan (como "Clienta
 * eliminada") para que ventas y reportes de meses anteriores no cambien.
 * El número queda libre: si vuelve a escribir, se registra como clienta nueva.
 */
class Clientas
{
    /** Días sin visita para considerarla inactiva. */
    public const DIAS_INACTIVA = 20;

    public const NOMBRE_ELIMINADA = 'Clienta eliminada';

    public function eliminar(Cliente $cliente): void
    {
        if ($cliente->eliminada_en) {
            return;
        }

        DB::transaction(function () use ($cliente) {
            $ahora = Carbon::now();

            // Se cancelan en silencio: sin evento de "espacio liberado", para
            // que borrar a alguien no mande avisos de WhatsApp a otras clientas.
            $cliente->citas()
                ->where('estado', Cita::CONFIRMADA)
                ->where('inicio', '>', $ahora)
                ->update(['estado' => Cita::CANCELADA, 'cancelada_en' => $ahora]);

            // Fuera de la app: tokens y códigos pendientes.
            $cliente->tokens()->delete();
            DB::table('codigos_acceso')->where('cliente_id', $cliente->id)->delete();

            $cliente->forceFill([
                'wa_id'             => 'eliminada-' . $cliente->id . '-' . Str::lower(Str::random(8)),
                'nombre'            => self::NOMBRE_ELIMINADA,
                'google_sub'        => null,
                'email'             => null,
                'notas'             => null,
                'acepta_avisos'     => false,
                'tarjeta_token'     => null,
                'ultimo_mensaje_en' => null,
                'inactiva_desde'    => null,
                'eliminada_en'      => $ahora,
            ])->save();
        });
    }

    /**
     * Marca inactivas a las clientas sin visitas (cita confirmada o completada)
     * en los últimos DIAS_INACTIVA días ni cita próxima, y reactiva a las que
     * ya volvieron a agendar. Regresa [marcadas, reactivadas].
     *
     * @return array{0:int,1:int}
     */
    public function actualizarInactivas(): array
    {
        $ahora = Carbon::now();
        $limite = $ahora->copy()->subDays(self::DIAS_INACTIVA);

        $visita = fn ($q) => $q->whereIn('estado', [Cita::CONFIRMADA, Cita::COMPLETADA])
            ->where('inicio', '>=', $limite);

        $marcadas = Cliente::query()
            ->vigentes()
            ->whereNull('inactiva_desde')
            ->where('created_at', '<', $limite)
            ->whereDoesntHave('citas', $visita)
            ->update(['inactiva_desde' => $ahora]);

        $reactivadas = Cliente::query()
            ->vigentes()
            ->whereNotNull('inactiva_desde')
            ->whereHas('citas', $visita)
            ->update(['inactiva_desde' => null]);

        return [$marcadas, $reactivadas];
    }

    /** Al agendar vuelve a estar activa sin esperar a la revisión de la noche. */
    public function reactivar(Cliente $cliente): void
    {
        if ($cliente->inactiva_desde) {
            $cliente->forceFill(['inactiva_desde' => null])->save();
        }
    }
}
