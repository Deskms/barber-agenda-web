<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoWhatsapp extends Model
{
    protected $table = 'eventos_whatsapp';

    public const TIPO_MENSAJE = 'mensaje';
    public const TIPO_ESTADO  = 'estado';

    protected $fillable = ['wamid', 'tipo', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
