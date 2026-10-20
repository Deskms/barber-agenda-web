<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorarioAtencion extends Model
{
    protected $table = 'horarios_atencion';

    /** La migración no lleva timestamps(); sin esto Eloquent escribiría columnas inexistentes. */
    public $timestamps = false;

    protected $fillable = ['dia_semana', 'abre', 'cierra', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
