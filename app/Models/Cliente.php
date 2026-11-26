<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Cliente extends Authenticatable
{
    // La app movil se autentica como la clienta, no como la duena.
    use HasApiTokens;

    protected $table = 'clientes';

    protected $fillable = ['wa_id', 'nombre', 'acepta_avisos', 'notas', 'ultimo_mensaje_en'];

    protected function casts(): array
    {
        return [
            'acepta_avisos' => 'boolean', 'ultimo_mensaje_en' => 'datetime', 'metas_avisadas' => 'integer',
            'eliminada_en' => 'datetime', 'inactiva_desde' => 'datetime',
        ];
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /** Premios de la tarjeta de sellos ya entregados. */
    public function canjes(): HasMany
    {
        return $this->hasMany(CanjeSello::class);
    }

    /** Clientas que dieron consentimiento para recibir avisos de espacios liberados. */
    public function scopeNotificables(Builder $q): Builder
    {
        return $q->where('acepta_avisos', true)->whereNull('eliminada_en');
    }

    /** Las que no se han borrado desde el panel. */
    public function scopeVigentes(Builder $q): Builder
    {
        return $q->whereNull('eliminada_en');
    }

    /**
     * Meta deja contestar sin plantilla durante 24 h después del último
     * mensaje de la clienta. Dejamos una hora de margen.
     */
    public function ventanaAbierta(): bool
    {
        return $this->ultimo_mensaje_en !== null
            && $this->ultimo_mensaje_en->greaterThan(now()->subHours(23));
    }
}
