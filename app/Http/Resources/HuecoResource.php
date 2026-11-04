<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HuecoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inicio' => Fecha::iso($this->inicio),
            'fin' => Fecha::iso($this->fin),
            'motivo' => $this->motivo,
            'cita_id' => $this->cita_id,
            'servicio' => $this->servicio ? ['id' => $this->servicio->id, 'nombre' => $this->servicio->nombre] : null,
            'sigue_libre' => $this->when(isset($this->sigue_libre), fn () => (bool) $this->sigue_libre),
        ];
    }
}
