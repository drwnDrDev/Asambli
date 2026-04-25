<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Copropietario>
 */
class CopropietarioFactory extends Factory
{
    public function definition(): array
    {
        $tipos = ['CC', 'CE', 'NIT', 'PP', 'TI', 'PEP'];
        return [
            'tenant_id'        => fn() => app()->has('current_tenant') ? app('current_tenant')->id : \App\Models\Tenant::factory(),
            'nombre'           => fake()->name(),
            'email'            => fake()->unique()->safeEmail(),
            'tipo_documento'   => fake()->randomElement($tipos),
            'numero_documento' => fake()->unique()->numerify('##########'),
            'es_residente'     => true,
            'es_externo'       => false,
            'activo'           => true,
        ];
    }
}
