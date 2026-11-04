<?php

namespace App\Services\Agenda;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Hueco;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HuecoService
{
    public function __construct(
        private readonly DisponibilidadService $disponibilidad,
    ) {}

    /** ¿El servicio original todavía cabe en el hueco? (nadie lo tomó ni se bloqueó). */
    public function sigueLibre(Hueco $hueco): bool
    {
        if ($hueco->fin->lessThanOrEqualTo(Carbon::now()) || ! $hueco->servicio) {
            return false;
        }

        return $this->disponibilidad->evaluar($hueco->servicio, $hueco->inicio->copy())->libre;
    }

    /**
     * Elegibles para el aviso: dieron consentimiento, no son quien liberó el
     * hueco, no tienen otra cita que choque y no recibieron ya este hueco.
     *
     * @return Collection<int, Cliente>
     */
    public function candidatas(Hueco $hueco): Collection
    {
        $hueco->loadMissing('cita');

        $ocupadas = Cita::query()
            ->vigentes()
            ->queChocanCon($hueco->inicio, $hueco->fin)
            ->pluck('cliente_id');

        $yaAvisadas = $hueco->difusiones()->pluck('cliente_id');

        return Cliente::query()
            ->notificables()
            ->when($hueco->cita, fn ($q) => $q->whereKeyNot($hueco->cita->cliente_id))
            ->whereNotIn('id', $ocupadas)
            ->whereNotIn('id', $yaAvisadas)
            ->orderBy('nombre')
            ->get();
    }
}
