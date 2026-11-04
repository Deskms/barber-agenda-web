<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Difusion extends Model
{
    protected $table = 'difusiones';

    public const ENVIADO = 'enviado';

    public const FALLIDO = 'fallido';

    protected $fillable = [
        'espacio_liberado_id', 'cliente_id', 'plantilla', 'estado_envio', 'wamid', 'error', 'enviada_en',
    ];

    protected function casts(): array
    {
        return ['enviada_en' => 'datetime'];
    }

    public function hueco(): BelongsTo
    {
        return $this->belongsTo(Hueco::class, 'espacio_liberado_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
