<?php

use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\User;

test('reunion starts as borrador', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);

    $reunion = Reunion::factory()->create(['creado_por' => $admin->id]);

    expect($reunion->estado)->toBe('borrador');
});

test('reunion logs every state change', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);

    $reunion = Reunion::factory()->create(['creado_por' => $admin->id]);
    $reunion->transicionarA('convocada', $admin);

    expect($reunion->logs()->count())->toBe(1);
    expect($reunion->logs()->first()->accion)->toBe('estado_cambiado_a_convocada');
});
