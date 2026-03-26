<?php

use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create([
        'tenant_id' => null,
        'rol' => 'super_admin',
        'activo' => true,
    ]);
});

test('crear tenant con admin crea ambos registros', function () {
    $this->actingAs($this->superAdmin)
        ->post('/super-admin/tenants', [
            'nombre'       => 'Conjunto Prueba',
            'nit'          => '900000001-1',
            'admin_nombre' => 'Admin Test',
            'admin_email'  => 'admin@prueba.com',
            'admin_password' => 'password123',
        ])
        ->assertRedirect();

    $tenant = Tenant::withoutGlobalScopes()->where('nit', '900000001-1')->first();
    expect($tenant)->not->toBeNull();
    expect(User::withoutGlobalScopes()->where('email', 'admin@prueba.com')->first())->not->toBeNull();
});

test('crear tenant sin admin es válido', function () {
    $this->actingAs($this->superAdmin)
        ->post('/super-admin/tenants', [
            'nombre' => 'Conjunto Sin Admin',
            'nit'    => '900000002-1',
        ])
        ->assertRedirect();

    expect(Tenant::withoutGlobalScopes()->where('nit', '900000002-1')->exists())->toBeTrue();
});

test('agregar admin a tenant existente', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($this->superAdmin)
        ->post("/super-admin/tenants/{$tenant->id}/admins", [
            'nombre'   => 'Nuevo Admin',
            'email'    => 'nuevo@admin.com',
            'password' => 'password123',
        ])
        ->assertRedirect();

    expect(User::withoutGlobalScopes()
        ->where('email', 'nuevo@admin.com')
        ->where('rol', 'administrador')
        ->where('tenant_id', $tenant->id)
        ->exists()
    )->toBeTrue();
});

test('super admin puede toggle activo de usuario', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'rol' => 'administrador',
        'activo' => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->patch("/super-admin/tenants/{$tenant->id}/users/{$admin->id}/toggle")
        ->assertRedirect();

    expect($admin->fresh()->activo)->toBeFalse();
});

test('no se puede toggle al super admin propio', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($this->superAdmin)
        ->patch("/super-admin/tenants/{$tenant->id}/users/{$this->superAdmin->id}/toggle")
        ->assertStatus(422);
});
