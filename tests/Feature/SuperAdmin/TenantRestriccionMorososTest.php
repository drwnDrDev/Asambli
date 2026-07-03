<?php

use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['rol' => 'super_admin', 'tenant_id' => null, 'activo' => true]);
});

test('super admin puede crear tenant con restriccion de morosos desactivada', function () {
    $response = $this->actingAs($this->superAdmin)->post('/super-admin/tenants', [
        'nombre'                    => 'Conjunto Test Morosos',
        'nit'                       => 'NIT-TEST-001',
        'max_poderes_por_delegado'  => 2,
        'restringir_voto_morosos'   => false,
    ]);

    $tenant = Tenant::withoutGlobalScopes()->where('nombre', 'Conjunto Test Morosos')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->restringir_voto_morosos)->toBeFalse();
});

test('super admin puede actualizar la restriccion de morosos', function () {
    $tenant = Tenant::factory()->create(['restringir_voto_morosos' => true]);

    $this->actingAs($this->superAdmin)->put("/super-admin/tenants/{$tenant->id}", [
        'nombre'                   => $tenant->nombre,
        'max_poderes_por_delegado' => $tenant->max_poderes_por_delegado,
        'restringir_voto_morosos'  => false,
    ]);

    expect($tenant->fresh()->restringir_voto_morosos)->toBeFalse();
});
