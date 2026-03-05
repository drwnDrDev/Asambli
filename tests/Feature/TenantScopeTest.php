<?php

use App\Models\Tenant;
use App\Models\User;

test('tenant scope isolates data between tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    // Crear usuarios en cada tenant
    User::factory()->create(['tenant_id' => $tenantA->id, 'email' => 'a@test.com']);
    User::factory()->create(['tenant_id' => $tenantB->id, 'email' => 'b@test.com']);

    // Simular contexto del tenant A
    app()->instance('current_tenant', $tenantA);

    expect(User::count())->toBe(1);
    expect(User::first()->email)->toBe('a@test.com');
});
