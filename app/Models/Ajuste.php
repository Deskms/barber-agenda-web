<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ajustes sencillos de clave/valor que la dueña cambia desde el panel. */
class Ajuste extends Model
{
    protected $table = 'ajustes';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['clave', 'valor'];

    /** Valores por defecto: todo lo que cuesta dinero empieza apagado. */
    public const DEFAULTS = [
        'huecos_auto'          => '0',  // avisar huecos sin que la dueña lo pida
        'huecos_max'           => '5',  // máximo de avisos pagados por hueco
        'regreso_auto'         => '0',  // recordatorio de "ya te toca volver"
        'presupuesto_mensual'  => '100', // MXN al mes en mensajes de marketing
    ];

    public static function valor(string $clave): ?string
    {
        return static::find($clave)?->valor ?? (self::DEFAULTS[$clave] ?? null);
    }

    public static function activo(string $clave): bool
    {
        return static::valor($clave) === '1';
    }

    public static function guardar(string $clave, ?string $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }
}
