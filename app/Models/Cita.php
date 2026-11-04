<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cita extends Model
{
    protected $table = 'citas';

    public const CONFIRMADA = 'confirmada';

    public const CANCELADA = 'cancelada';

    public const COMPLETADA = 'completada';

    public const NO_ASISTIO = 'no_asistio';

    public const ESTADOS = [self::CONFIRMADA, self::CANCELADA, self::COMPLETADA, self::NO_ASISTIO];

    public const ORIGEN_APP = 'app';

    public const ORIGEN_PANEL = 'panel';

    public const ORIGEN_WEB = 'web';

    protected $fillable = [
        'cliente_id', 'servicio_id', 'inicio', 'fin',
        'estado', 'origen', 'notas', 'cancelada_en', 'reprogramada_en', 'atendida_en',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'cancelada_en' => 'datetime',
            'reprogramada_en' => 'datetime',
            'atendida_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function bloques(): HasMany
    {
        return $this->hasMany(CitaBloque::class)->orderBy('orden');
    }

    /**
     * Citas que ocupan lugar en la agenda.
     * Una cita cancelada queda fuera: por eso su horario vuelve a estar libre solo.
     */
    public function scopeVigentes(Builder $q): Builder
    {
        return $q->whereIn('estado', [self::CONFIRMADA, self::COMPLETADA]);
    }

    /**
     * Citas cuyo intervalo se traslapa con [$inicio, $fin).
     *
     * Estrictamente < y > : una cita 10:00-11:00 y otra 11:00-12:00 NO chocan.
     */
    public function scopeQueChocanCon(Builder $q, $inicio, $fin): Builder
    {
        return $q->where('inicio', '<', $fin)
            ->where('fin', '>', $inicio);
    }

    public function scopeDelDia(Builder $q, $dia): Builder
    {
        return $q->whereDate('inicio', $dia);
    }

    public function estaCancelada(): bool
    {
        return $this->estado === self::CANCELADA;
    }

    public function estaConfirmada(): bool
    {
        return $this->estado === self::CONFIRMADA;
    }
}
