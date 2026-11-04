<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'telefono' => $this->wa_id,
            'acepta_avisos' => (bool) $this->acepta_avisos,
            'tiene_cuenta' => $this->user_id !== null,
            'citas_count' => $this->whenCounted('citas'),
            'creado_en' => Fecha::iso($this->created_at),
        ];
    }
}
