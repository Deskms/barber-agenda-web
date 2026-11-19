<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un premio entregado a la clienta; descuenta los sellos de una meta. */
class CanjeSello extends Model
{
    protected $table = 'canjes_sellos';

    protected $fillable = ['cliente_id', 'sellos', 'premio', 'canjeado_en'];

    protected function casts(): array
    {
        return ['canjeado_en' => 'datetime', 'sellos' => 'integer'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
