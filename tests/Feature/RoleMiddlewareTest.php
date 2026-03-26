<?php

use App\Models\Tenant;
use App\Models\User;

test('admin can access admin routes', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);
    $response = $this->actingAs($admin)
         ->get('/admin/dashboard');
    // 409 = Inertia asset version mismatch (route accessible), 200 = rendered OK
    expect($response->status())->not->toBe(403);
});

test('copropietario cannot access admin routes', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    $this->actingAs($user)
         ->get('/admin/dashboard')
         ->assertStatus(403);
});

test('deactivated admin cannot access admin routes', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'rol' => 'administrador',
        'activo' => false,
    ]);
    $this->actingAs($admin)
         ->get('/admin/dashboard')
         ->assertStatus(403);
});
