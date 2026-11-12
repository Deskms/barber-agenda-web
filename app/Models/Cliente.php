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
        return ['acepta_avisos' => 'boolean', 'ultimo_mensaje_en' => 'datetime'];
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /** Clientas que dieron consentimiento para recibir avisos de espacios liberados. */
    public function scopeNotificables(Builder $q): Builder
    {
        return $q->where('acepta_avisos', true);
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
