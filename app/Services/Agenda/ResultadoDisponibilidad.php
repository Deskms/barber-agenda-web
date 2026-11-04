<?php

namespace App\Services\Agenda;

/**
 * Resultado de evaluar si un servicio cabe en un horario.
 * El motivo es un código estable que el frontend puede usar para decidir qué mostrar.
 */
final class ResultadoDisponibilidad
{
    public const MENSAJES = [
        'en_el_pasado' => 'Ese horario ya pasó.',
        'dia_sin_atencion' => 'Ese día no hay atención.',
        'fuera_de_horario' => 'Ese horario queda fuera del horario de atención.',
        'bloqueado' => 'La agenda está bloqueada en ese horario.',
        'estilista_ocupada' => 'La estilista está ocupada en ese horario.',
        'capacidad_llena' => 'Ya se atiende al máximo de clientas en ese horario.',
    ];

    private function __construct(
        public readonly bool $libre,
        public readonly ?string $motivo,
    ) {}

    public static function libre(): self
    {
        return new self(true, null);
    }

    public static function ocupado(string $motivo): self
    {
        return new self(false, $motivo);
    }

    public function mensaje(): string
    {
        return $this->libre ? 'Horario disponible.' : (self::MENSAJES[$this->motivo] ?? 'Ese horario no está disponible.');
    }
}
