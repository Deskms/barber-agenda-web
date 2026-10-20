<?php

namespace App\Http\Controllers;

use App\Models\Seccion;
use App\Services\Agenda\DisponibilidadService;
use Illuminate\Support\Carbon;

/**
 * Página pública "Espacios libres" para compartir gratis en estados de
 * WhatsApp, Instagram o Facebook. Cada hora libre abre WhatsApp con el
 * mensaje ya escrito: la clienta escribe primero, y todo lo que se le
 * conteste en las siguientes 24 h no se cobra como marketing.
 */
class LibresController extends Controller
{
    public function __invoke(DisponibilidadService $disponibilidad)
    {
        $secciones = Seccion::where('activo', true)->orderBy('nombre')->get();
        $dias      = collect();

        for ($i = 0, $dia = Carbon::today(); $i < 7 && $dias->count() < 3; $i++, $dia->addDay()) {
            if (! $disponibilidad->horarioDe($dia)) {
                continue;
            }

            $mapas = $secciones->mapWithKeys(fn (Seccion $s) => [
                $s->nombre => $disponibilidad->mapaPorHora($dia->copy(), $s->id)
                    ->filter(fn ($h) => in_array($h['estado'], ['libre', 'acompanada'], true))
                    ->values(),
            ])->filter(fn ($horas) => $horas->isNotEmpty());

            if ($mapas->isNotEmpty()) {
                $dias->push(['dia' => $dia->copy(), 'mapas' => $mapas]);
            }
        }

        return view('libres', [
            'dias'   => $dias,
            'numero' => config('whatsapp.numero_publico'),
        ]);
    }
}
