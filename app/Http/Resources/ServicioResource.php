<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'nombre'           => $this->nombre,
            'duracion_minutos' => (int) $this->duracion_minutos,
            'precio'           => $this->precio === null ? null : (float) $this->precio,
            'seccion_id'       => $this->seccion_id,
        ];
    }
}
