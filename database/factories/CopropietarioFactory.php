<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Copropietario>
 */
class CopropietarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tipos = ['CC', 'CE', 'NIT', 'PP', 'TI', 'PEP'];
        return [
            'tenant_id' => fn() => app()->has('current_tenant') ? app('current_tenant')->id : \App\Models\Tenant::factory(),
            'user_id' => \App\Models\User::factory(),
            'tipo_documento' => fake()->randomElement($tipos),
            'numero_documento' => fake()->unique()->numerify('##########'),
            'es_residente' => true,
            'activo' => true,
        ];
    }
}
