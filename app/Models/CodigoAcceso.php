<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Código de un solo uso para entrar a la app móvil. */
class CodigoAcceso extends Model
{
    protected $table = 'codigos_acceso';

    /** Después de cinco intentos fallidos el código se considera quemado. */
    public const INTENTOS_MAXIMOS = 5;

    protected $fillable = ['cliente_id', 'codigo_hash', 'expira_en', 'usado_en', 'intentos', 'ip'];

    protected function casts(): array
    {
        return ['expira_en' => 'datetime', 'usado_en' => 'datetime'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function scopeUtilizables(Builder $q): Builder
    {
        return $q->whereNull('usado_en')
                 ->where('expira_en', '>', now())
                 ->where('intentos', '<', self::INTENTOS_MAXIMOS);
    }
}
