<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->company() . ' Conjunto',
            'nit' => fake()->numerify('#########-#'),
            'direccion' => fake()->address(),
            'ciudad' => fake()->randomElement(['Bogotá', 'Medellín', 'Cali', 'Barranquilla']),
            'max_poderes_por_delegado' => 2,
            'activo' => true,
        ];
    }
}
