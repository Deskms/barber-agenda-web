<?php

namespace Database\Factories;

use App\Models\Servicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Servicio> */
class ServicioFactory extends Factory
{
    protected $model = Servicio::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'duracion_minutos' => 60,
            'precio' => 200,
            'activo' => true,
        ];
    }
}
