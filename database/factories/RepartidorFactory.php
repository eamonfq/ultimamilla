<?php

namespace Database\Factories;

use App\Enums\Ciudad;
use App\Models\Repartidor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repartidor>
 */
class RepartidorFactory extends Factory
{
    protected $model = Repartidor::class;

    public function definition(): array
    {
        return [
            'cedula' => fake()->unique()->numerify('##########'),
            'nombre' => fake()->name(),
            'telefono' => fake()->numerify('30########'),
            'ciudad' => fake()->randomElement(Ciudad::cases()),
            'placa' => fake()->bothify('???###'),
            'pin' => '1234',
            'cupo_personalizado' => null,
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
