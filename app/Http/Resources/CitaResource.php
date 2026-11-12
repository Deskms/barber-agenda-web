<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CitaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'estado'      => $this->estado,
            'origen'      => $this->origen,
            'inicio'      => $this->inicio->toIso8601String(),
            'fin'         => $this->fin->toIso8601String(),
            'puede_cancelarse' => $this->estado === 'confirmada' && $this->inicio->isFuture(),
            'servicio'    => new ServicioResource($this->whenLoaded('servicio')),
            'seccion'     => new SeccionResource($this->whenLoaded('seccion')),
        ];
    }
}
