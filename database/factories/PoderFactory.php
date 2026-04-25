<?php

namespace Database\Factories;

use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoderFactory extends Factory
{
    public function definition(): array
    {
        $tenant = app()->has('current_tenant')
            ? app('current_tenant')
            : Tenant::factory()->create();

        // Create distinct copropietarios inline to avoid booted() uniqueness collision
        $apoderado = Copropietario::factory()->create(['tenant_id' => $tenant->id]);
        $poderdante = Copropietario::factory()->create(['tenant_id' => $tenant->id]);

        return [
            'tenant_id'      => $tenant->id,
            'reunion_id'     => Reunion::factory()->create(['tenant_id' => $tenant->id])->id,
            'apoderado_id'   => $apoderado->id,
            'poderdante_id'  => $poderdante->id,
            'registrado_por' => User::factory()->create(['tenant_id' => $tenant->id])->id,
            'estado'         => 'pendiente',
            'documento_url'  => null,
        ];
    }
}
