<?php

namespace App\Services\Agenda;

use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Cliente;
use Illuminate\Support\Str;

/**
 * Registro de llegada con el QR del mostrador.
 *
 * El QR solo prueba que la clienta está en el local: no crea ni cambia citas,
 * solo marca la hora de llegada de la cita que ya tenía.
 */
class Llegadas
{
    /** Margen para llegar antes y tarde, en minutos. */
    public const ANTES = 60;
    public const DESPUES = 30;

    /** Código del mostrador. Se crea la primera vez y se puede cambiar. */
    public function codigo(): string
    {
        $codigo = Ajuste::valor('codigo_mostrador');

        if (! $codigo) {
            $codigo = $this->cambiarCodigo();
        }

        return $codigo;
    }

    public function cambiarCodigo(): string
    {
        $codigo = Str::lower(Str::random(32));
        Ajuste::guardar('codigo_mostrador', $codigo);

        return $codigo;
    }

    public function url(): string
    {
        return route('llegada', $this->codigo());
    }

    /** Acepta el código solo o la dirección completa que trae el QR. */
    public function codigoValido(string $leido): bool
    {
        $leido = trim($leido);
        $codigo = Str::afterLast(rtrim($leido, '/'), '/');

        return hash_equals($this->codigo(), $codigo);
    }

    /** La cita confirmada de hoy que está por empezar o acaba de empezar. */
    public function citaActual(Cliente $cliente): ?Cita
    {
        $ahora = now();

        return $cliente->citas()
            ->where('estado', Cita::CONFIRMADA)
            ->whereBetween('inicio', [$ahora->copy()->subMinutes(self::DESPUES), $ahora->copy()->addMinutes(self::ANTES)])
            ->orderBy('inicio')
            ->first();
    }

    /** La siguiente cita confirmada, para decirle cuándo podrá registrar su llegada. */
    public function proximaCita(Cliente $cliente): ?Cita
    {
        return $cliente->citas()
            ->where('estado', Cita::CONFIRMADA)
            ->where('inicio', '>', now())
            ->orderBy('inicio')
            ->first();
    }

    /** Explica por qué no se registró la llegada y cuándo sí se podrá. */
    public function mensajeSinCita(Cliente $cliente): string
    {
        $proxima = $this->proximaCita($cliente);

        if (! $proxima) {
            return 'No tienes una cita próxima. Agenda una desde la pestaña Agendar.';
        }

        $cuando = $proxima->inicio->isSameDay(now())
            ? 'hoy a las ' . $proxima->inicio->format('H:i')
            : 'el ' . $proxima->inicio->translatedFormat('l j \d\e F') . ' a las ' . $proxima->inicio->format('H:i');

        return "Tu próxima cita es {$cuando}. Puedes registrar tu llegada desde " . self::ANTES . ' minutos antes.';
    }

    /** Marca la llegada una sola vez; repetir el escaneo no cambia la hora. */
    public function registrar(Cita $cita): Cita
    {
        if (! $cita->llegada_en) {
            $cita->forceFill(['llegada_en' => now()])->save();
        }

        return $cita;
    }
}
