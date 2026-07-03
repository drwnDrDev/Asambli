<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TipoDecisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo'       => fake()->unique()->slug(2),
            'nombre'       => fake()->sentence(3),
            'descripcion'  => fake()->sentence(),
            'tipo_mayoria' => 'simple',
            'aplica_en'    => ['asamblea'],
            'orden'        => fake()->numberBetween(1, 20),
        ];
    }
}
