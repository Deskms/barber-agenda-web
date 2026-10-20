<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Difusion extends Model
{
    protected $table = 'difusiones';

    protected $fillable = ['cita_id', 'cliente_id', 'seccion_id', 'gratis', 'automatica', 'inicio', 'fin', 'plantilla', 'estado', 'wamid', 'error'];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin'    => 'datetime',
            'gratis' => 'boolean',
            'automatica' => 'boolean',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
