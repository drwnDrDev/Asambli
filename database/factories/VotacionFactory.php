<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Votacion>
 */
class VotacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn() => app()->has('current_tenant') ? app('current_tenant')->id : \App\Models\Tenant::factory(),
            'reunion_id' => \App\Models\Reunion::factory(),
            'pregunta' => fake()->sentence(3) . '?',
            'tipo' => 'si_no',
            'es_secreta' => true,
            'estado' => 'creada',
            'creada_por' => \App\Models\User::factory(),
        ];
    }
}
