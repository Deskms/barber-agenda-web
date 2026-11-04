<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = ['wa_id', 'nombre', 'acepta_avisos'];

    protected function casts(): array
    {
        return ['acepta_avisos' => 'boolean'];
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
}
