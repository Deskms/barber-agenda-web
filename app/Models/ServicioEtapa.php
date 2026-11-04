<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioEtapa extends Model
{
    protected $table = 'servicio_etapas';

    protected $fillable = ['servicio_id', 'orden', 'nombre', 'duracion_minutos', 'ocupa_estilista'];

    protected function casts(): array
    {
        return ['ocupa_estilista' => 'boolean'];
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }
}
