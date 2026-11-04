<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoWhatsapp extends Model
{
    protected $table = 'eventos_whatsapp';

    public const TIPO_MENSAJE = 'mensaje';

    public const TIPO_ESTADO = 'estado';

    public const ENTRANTE = 'entrante';

    public const SALIENTE = 'saliente';

    protected $fillable = ['cliente_id', 'wamid', 'tipo', 'direccion', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
