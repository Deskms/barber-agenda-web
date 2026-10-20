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
    public const CANCELADA  = 'cancelada';
    public const COMPLETADA = 'completada';
    public const NO_ASISTIO = 'no_asistio';

    public const ORIGEN_WHATSAPP = 'whatsapp';
    public const ORIGEN_PANEL    = 'panel';

    protected $fillable = [
        'cliente_id', 'servicio_id', 'seccion_id', 'inicio', 'fin',
        'estado', 'origen', 'notas', 'cancelada_en', 'recordatorio_enviado_en',
    ];

    protected function casts(): array
    {
        return [
            'inicio'       => 'datetime',
            'fin'          => 'datetime',
            'cancelada_en' => 'datetime',
            'recordatorio_enviado_en' => 'datetime',
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

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    public function difusiones(): HasMany
    {
        return $this->hasMany(Difusion::class);
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
     * Con <= y >= el sistema perdería huecos válidos de la agenda.
     */
    public function scopeQueChocanCon(Builder $q, $inicio, $fin): Builder
    {
        return $q->where('inicio', '<', $fin)
                 ->where('fin', '>', $inicio);
    }

    /**
     * Solo las citas de esa sección. Sin sección (null) el local funciona
     * como una sola agenda, igual que antes de existir las secciones.
     */
    public function scopeDeLaSeccion(Builder $q, ?int $seccionId): Builder
    {
        return $q->where('seccion_id', $seccionId);
    }

    public function scopeDelDia(Builder $q, $dia): Builder
    {
        return $q->whereDate('inicio', $dia);
    }

    public function estaCancelada(): bool
    {
        return $this->estado === self::CANCELADA;
    }
}
