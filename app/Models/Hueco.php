<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Espacio liberado por una cancelación o una reprogramación ("hueco" en el glosario).
 */
class Hueco extends Model
{
    protected $table = 'espacios_liberados';

    public const MOTIVO_CANCELACION = 'cancelacion';

    public const MOTIVO_REPROGRAMACION = 'reprogramacion';

    protected $fillable = ['cita_id', 'servicio_id', 'inicio', 'fin', 'motivo'];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }
}
