<?php

namespace App\Models;

use Database\Factories\ServicioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Servicio extends Model
{
    /** @use HasFactory<ServicioFactory> */
    use HasFactory;

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

    public function etapas(): HasMany
    {
        return $this->hasMany(ServicioEtapa::class)->orderBy('orden');
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    /**
     * Etapas con las que se planifica una cita.
     *
     * Un servicio sin etapas configuradas se comporta como antes: una sola
     * etapa activa con la duración completa del servicio.
     *
     * @return Collection<int, array{orden:int, nombre:string, duracion_minutos:int, ocupa_estilista:bool}>
     */
    public function etapasEfectivas(): Collection
    {
        $etapas = $this->relationLoaded('etapas') ? $this->etapas : $this->etapas()->get();

        if ($etapas->isEmpty()) {
            return collect([[
                'orden' => 1,
                'nombre' => $this->nombre,
                'duracion_minutos' => (int) $this->duracion_minutos,
                'ocupa_estilista' => true,
            ]]);
        }

        return $etapas->map(fn (ServicioEtapa $e) => [
            'orden' => (int) $e->orden,
            'nombre' => $e->nombre,
            'duracion_minutos' => (int) $e->duracion_minutos,
            'ocupa_estilista' => (bool) $e->ocupa_estilista,
        ])->values();
    }
}
