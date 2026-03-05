<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Unidad>
 */
class UnidadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => \App\Models\Tenant::factory(),
            'numero' => fake()->numerify('###'),
            'tipo' => fake()->randomElement(['apartamento', 'local', 'parqueadero', 'otro']),
            'coeficiente' => fake()->randomFloat(5, 0.5, 5.0),
            'torre' => fake()->randomElement(['A', 'B', 'C', null]),
            'piso' => (string) fake()->numberBetween(1, 20),
            'activo' => true,
        ];
    }
}
