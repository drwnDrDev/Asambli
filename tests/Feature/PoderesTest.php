<?php

use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;

test('un apoderado no puede tener más poderes que el máximo del tenant', function () {
    $tenant = Tenant::factory()->create(['max_poderes_por_delegado' => 2]);
    app()->instance('current_tenant', $tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);
    $reunion = Reunion::factory()->create(['creado_por' => $admin->id]);

    $unidad1 = Unidad::factory()->create();
    $unidad2 = Unidad::factory()->create();
    $unidad3 = Unidad::factory()->create();
    $unidad4 = Unidad::factory()->create();

    $apoderado = Copropietario::factory()->create(['unidad_id' => $unidad1->id]);
    $poderdante1 = Copropietario::factory()->create(['unidad_id' => $unidad2->id]);
    $poderdante2 = Copropietario::factory()->create(['unidad_id' => $unidad3->id]);
    $poderdante3 = Copropietario::factory()->create(['unidad_id' => $unidad4->id]);

    Poder::create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id,
        'apoderado_id' => $apoderado->id, 'poderdante_id' => $poderdante1->id, 'registrado_por' => $admin->id]);
    Poder::create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id,
        'apoderado_id' => $apoderado->id, 'poderdante_id' => $poderdante2->id, 'registrado_por' => $admin->id]);

    expect(fn() => Poder::create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id,
        'apoderado_id' => $apoderado->id, 'poderdante_id' => $poderdante3->id, 'registrado_por' => $admin->id]))
        ->toThrow(\Exception::class);
});
