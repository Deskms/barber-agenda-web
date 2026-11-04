<?php

namespace App\Models;

use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = ['user_id', 'telefono', 'nombre', 'acepta_avisos'];

    protected function casts(): array
    {
        return ['acepta_avisos' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /** Clientas que aceptaron avisos de citas de última hora (horarios liberados). */
    public function scopeNotificables(Builder $q): Builder
    {
        return $q->where('acepta_avisos', true);
    }
}
