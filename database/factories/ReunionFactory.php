<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reunion>
 */
class ReunionFactory extends Factory
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
            'titulo' => fake()->sentence(4),
            'tipo' => 'asamblea',
            'tipo_voto_peso' => 'coeficiente',
            'quorum_requerido' => 50.00,
            'estado' => 'borrador',
            'creado_por' => \App\Models\User::factory(),
        ];
    }
}
