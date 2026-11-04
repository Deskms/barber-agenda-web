<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CitaBloque extends Model
{
    protected $table = 'cita_bloques';

    public $timestamps = false;

    protected $fillable = ['cita_id', 'orden', 'nombre', 'inicio', 'fin', 'ocupa_estilista'];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'ocupa_estilista' => 'boolean',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }
}
