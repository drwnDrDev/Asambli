<?php

use App\Models\Tenant;
use App\Models\User;

test('admin can access admin routes', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);
    $this->actingAs($admin)
         ->get('/admin/dashboard')
         ->assertStatus(200);
});

test('copropietario cannot access admin routes', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    $this->actingAs($user)
         ->get('/admin/dashboard')
         ->assertStatus(403);
});
