<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cliente> */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        return [
            'wa_id' => '521'.fake()->unique()->numerify('33########'),
            'nombre' => fake()->firstName(),
            'acepta_avisos' => false,
        ];
    }

    public function conAvisos(): static
    {
        return $this->state(fn () => ['acepta_avisos' => true]);
    }
}
