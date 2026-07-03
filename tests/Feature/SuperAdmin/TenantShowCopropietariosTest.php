<?php

use App\Models\Copropietario;
use App\Models\Tenant;
use App\Models\User;

test('tenant show incluye copropietarios paginados', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $superAdmin = User::factory()->create(['rol' => 'super_admin', 'tenant_id' => null, 'activo' => true]);

    Copropietario::factory()->count(20)->create(['tenant_id' => $tenant->id]);

    $manifest = public_path('build/manifest.json');
    $version = file_exists($manifest) ? hash_file('xxh128', $manifest) : '';

    $response = $this->actingAs($superAdmin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
        ->get("/super-admin/tenants/{$tenant->id}");

    $response->assertStatus(200);
    $props = $response->json('props');
    expect($props)->toHaveKey('copropietarios')
        ->and(count($props['copropietarios']['data']))->toBe(15)
        ->and($props['copropietarios']['total'])->toBe(20);
});
