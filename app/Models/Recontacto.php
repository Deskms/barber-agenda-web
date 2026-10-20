<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un mensaje de "ya te toca volver" enviado a una clienta. */
class Recontacto extends Model
{
    protected $table = 'recontactos';

    protected $fillable = ['cliente_id', 'servicio_id', 'dias_desde_visita', 'gratis', 'estado', 'wamid', 'error'];

    protected function casts(): array
    {
        return ['gratis' => 'boolean'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }
}
