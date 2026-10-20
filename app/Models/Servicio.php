<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servicio extends Model
{
    protected $table = 'servicios';

    protected $fillable = ['nombre', 'duracion_minutos', 'precio', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'precio' => 'decimal:2',
        ];
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }
}
