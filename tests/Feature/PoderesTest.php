<?php

use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\User;

test('un apoderado no puede tener más poderes que el máximo del tenant', function () {
    $tenant = Tenant::factory()->create(['max_poderes_por_delegado' => 2]);
    app()->instance('current_tenant', $tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);
    $reunion = Reunion::factory()->create(['creado_por' => $admin->id]);

    $apoderado   = Copropietario::factory()->create();
    $poderdante1 = Copropietario::factory()->create();
    $poderdante2 = Copropietario::factory()->create();
    $poderdante3 = Copropietario::factory()->create();

    Poder::create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id,
        'apoderado_id' => $apoderado->id, 'poderdante_id' => $poderdante1->id, 'registrado_por' => $admin->id]);
    Poder::create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id,
        'apoderado_id' => $apoderado->id, 'poderdante_id' => $poderdante2->id, 'registrado_por' => $admin->id]);

    expect(fn() => Poder::create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id,
        'apoderado_id' => $apoderado->id, 'poderdante_id' => $poderdante3->id, 'registrado_por' => $admin->id]))
        ->toThrow(\Exception::class);
});
