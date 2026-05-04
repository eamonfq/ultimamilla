<?php

namespace Database\Factories;

use App\Enums\EstadoReserva;
use App\Models\Repartidor;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reserva>
 */
class ReservaFactory extends Factory
{
    protected $model = Reserva::class;

    public function definition(): array
    {
        return [
            'repartidor_id' => Repartidor::factory(),
            'fecha_operacion' => Carbon::tomorrow('America/Bogota'),
            'paquetes' => fake()->numberBetween(10, 50),
            'rutas' => fake()->numberBetween(1, 3),
            'estado' => EstadoReserva::Activa,
        ];
    }

    public function cancelada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoReserva::Cancelada,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'locked_at' => now('America/Bogota'),
        ]);
    }
}
