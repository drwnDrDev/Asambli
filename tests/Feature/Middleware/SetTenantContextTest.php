<?php

use App\Models\Tenant;
use App\Models\TenantAdministrador;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('establece tenant context para admin desde tenant_administradores', function () {
    $tenant = Tenant::factory()->create(['activo' => true]);
    $admin = User::factory()->create(['rol' => 'administrador', 'tenant_id' => null]);
    TenantAdministrador::create([
        'user_id' => $admin->id,
        'tenant_id' => $tenant->id,
        'activo' => true,
    ]);

    $this->actingAs($admin)
         ->withHeaders(['X-Inertia' => 'true'])
         ->get('/admin/dashboard');

    expect(app()->has('current_tenant'))->toBeTrue();
    expect(app('current_tenant')->id)->toBe($tenant->id);
});

it('respeta selected_tenant_id en sesión para admins multi-tenant', function () {
    $tenant1 = Tenant::factory()->create(['activo' => true]);
    $tenant2 = Tenant::factory()->create(['activo' => true]);
    $admin = User::factory()->create(['rol' => 'administrador', 'tenant_id' => null]);
    TenantAdministrador::create(['user_id' => $admin->id, 'tenant_id' => $tenant1->id, 'activo' => true]);
    TenantAdministrador::create(['user_id' => $admin->id, 'tenant_id' => $tenant2->id, 'activo' => true]);

    $this->actingAs($admin)
         ->withSession(['selected_tenant_id' => $tenant2->id])
         ->withHeaders(['X-Inertia' => 'true'])
         ->get('/admin/dashboard');

    expect(app()->has('current_tenant'))->toBeTrue();
    expect(app('current_tenant')->id)->toBe($tenant2->id);
});

it('no establece tenant context para super_admin', function () {
    $superAdmin = User::factory()->create(['rol' => 'super_admin', 'tenant_id' => null]);

    $this->actingAs($superAdmin)
         ->withHeaders(['X-Inertia' => 'true'])
         ->get('/super-admin/tenants');

    // super_admin no debe tener current_tenant (tiene acceso global)
    expect(app()->has('current_tenant'))->toBeFalse();
});
