<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Una parte del local con su propia agenda: lo que pasa en una no ocupa a las otras. */
class Seccion extends Model
{
    protected $table = 'secciones';

    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class);
    }
}
