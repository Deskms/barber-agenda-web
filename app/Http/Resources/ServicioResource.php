<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('etapas');

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'duracion_minutos' => (int) $this->duracion_minutos,
            'precio' => $this->precio !== null ? (float) $this->precio : null,
            'activo' => (bool) $this->activo,
            'etapas_configuradas' => $this->etapas->isNotEmpty(),
            'etapas' => $this->etapasEfectivas()->all(),
        ];
    }
}
