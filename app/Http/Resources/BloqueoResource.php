<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BloqueoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inicio' => Fecha::iso($this->inicio),
            'fin' => Fecha::iso($this->fin),
            'motivo' => $this->motivo,
        ];
    }
}
