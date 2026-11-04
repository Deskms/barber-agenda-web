<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HorarioResource extends JsonResource
{
    public const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    public function toArray(Request $request): array
    {
        return [
            'dia_semana' => (int) $this->dia_semana,
            'dia' => self::DIAS[(int) $this->dia_semana],
            'abre' => substr((string) $this->abre, 0, 5),
            'cierra' => substr((string) $this->cierra, 0, 5),
            'activo' => (bool) $this->activo,
        ];
    }
}
