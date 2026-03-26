<?php

use App\Models\Tenant;
use App\Models\User;

function superAdminInertiaVersion(): string
{
    $manifest = public_path('build/manifest.json');
    if (file_exists($manifest)) {
        return hash_file('xxh128', $manifest);
    }
    return '';
}

test('super admin puede ver el dashboard', function () {
    $superAdmin = User::factory()->create([
        'tenant_id' => null,
        'rol' => 'super_admin',
        'activo' => true,
    ]);

    $this->actingAs($superAdmin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => superAdminInertiaVersion()])
        ->get('/super-admin/dashboard')
        ->assertStatus(200);
});

test('administrador no puede ver el dashboard de super admin', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);

    $this->actingAs($admin)
        ->get('/super-admin/dashboard')
        ->assertStatus(403);
});
