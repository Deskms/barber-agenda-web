<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CitaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estado' => $this->estado,
            'origen' => $this->origen,
            'inicio' => Fecha::iso($this->inicio),
            'fin' => Fecha::iso($this->fin),
            'notas' => $this->notas,
            'servicio' => $this->whenLoaded('servicio', fn () => [
                'id' => $this->servicio->id,
                'nombre' => $this->servicio->nombre,
                'precio' => $this->servicio->precio !== null ? (float) $this->servicio->precio : null,
            ]),
            'cliente' => $this->whenLoaded('cliente', fn () => [
                'id' => $this->cliente->id,
                'nombre' => $this->cliente->nombre,
                'telefono' => $this->cliente->telefono,
            ]),
            'bloques' => $this->whenLoaded('bloques', fn () => $this->bloques->map(fn ($b) => [
                'orden' => $b->orden,
                'nombre' => $b->nombre,
                'inicio' => Fecha::iso($b->inicio),
                'fin' => Fecha::iso($b->fin),
                'ocupa_estilista' => (bool) $b->ocupa_estilista,
            ])->values()),
            'cancelada_en' => Fecha::iso($this->cancelada_en),
            'reprogramada_en' => Fecha::iso($this->reprogramada_en),
            'atendida_en' => Fecha::iso($this->atendida_en),
            'creada_en' => Fecha::iso($this->created_at),
            'actualizada_en' => Fecha::iso($this->updated_at),
        ];
    }
}
