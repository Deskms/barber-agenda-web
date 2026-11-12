<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'telefono'      => $this->wa_id,
            'nombre'        => $this->nombre,
            'acepta_avisos' => (bool) $this->acepta_avisos,
        ];
    }
}
