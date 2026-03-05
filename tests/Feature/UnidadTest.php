<?php

use App\Models\Tenant;
use App\Models\Unidad;

test('coeficientes de un tenant suman 100', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    Unidad::factory()->create(['coeficiente' => 50.00000]);
    Unidad::factory()->create(['coeficiente' => 50.00000]);

    $total = Unidad::sum('coeficiente');
    expect((float) $total)->toBe(100.0);
});

test('unidad pertenece a tenant correcto via scope', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    app()->instance('current_tenant', $tenantA);
    Unidad::factory()->create(['numero' => '101']);

    app()->instance('current_tenant', $tenantB);
    expect(Unidad::count())->toBe(0); // no ve las del tenant A
});
