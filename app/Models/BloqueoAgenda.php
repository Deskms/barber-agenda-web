<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloqueoAgenda extends Model
{
    protected $table = 'bloqueos_agenda';

    protected $fillable = ['inicio', 'fin', 'motivo', 'user_id'];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeQueChocanCon(Builder $q, $inicio, $fin): Builder
    {
        return $q->where('inicio', '<', $fin)->where('fin', '>', $inicio);
    }
}
