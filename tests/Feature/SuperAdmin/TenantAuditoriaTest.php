<?php

use App\Models\Reunion;
use App\Models\ReunionLog;
use App\Models\Tenant;
use App\Models\User;

function auditoriaInertiaHeaders(): array
{
    $manifest = public_path('build/manifest.json');
    $version = file_exists($manifest) ? hash_file('xxh128', $manifest) : '';
    return ['X-Inertia' => 'true', 'X-Inertia-Version' => $version];
}

test('super admin puede ver auditoria del tenant', function () {
    $superAdmin = User::factory()->create(['tenant_id' => null, 'rol' => 'super_admin', 'activo' => true]);
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);
    $reunion = Reunion::factory()->create(['creado_por' => $admin->id]);
    ReunionLog::create([
        'reunion_id' => $reunion->id,
        'user_id'    => $admin->id,
        'accion'     => 'reunion_iniciada',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($superAdmin)
        ->withHeaders(auditoriaInertiaHeaders())
        ->get("/super-admin/tenants/{$tenant->id}/auditoria");

    $response->assertStatus(200);
});

test('super admin puede filtrar auditoria por reunion', function () {
    $superAdmin = User::factory()->create(['tenant_id' => null, 'rol' => 'super_admin', 'activo' => true]);
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);
    $reunion = Reunion::factory()->create(['creado_por' => $admin->id]);
    ReunionLog::create(['reunion_id' => $reunion->id, 'user_id' => $admin->id, 'accion' => 'test_accion', 'created_at' => now()]);

    $response = $this->actingAs($superAdmin)
        ->withHeaders(auditoriaInertiaHeaders())
        ->get("/super-admin/tenants/{$tenant->id}/auditoria?reunion_id={$reunion->id}");

    $response->assertStatus(200);
});

test('administrador no puede ver auditoria de otro tenant', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador', 'activo' => true]);

    $this->actingAs($admin)
        ->get("/super-admin/tenants/{$tenant->id}/auditoria")
        ->assertStatus(403);
});
