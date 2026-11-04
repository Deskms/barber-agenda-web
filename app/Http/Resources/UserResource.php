<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->name,
            'email' => $this->email,
            'rol' => $this->rol,
            'activo' => (bool) $this->activo,
            'cliente' => $this->whenLoaded('cliente', fn () => $this->cliente ? new ClienteResource($this->cliente) : null),
        ];
    }
}
