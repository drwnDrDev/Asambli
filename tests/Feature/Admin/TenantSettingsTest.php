<?php

use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $this->tenant);
    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'rol' => 'administrador',
        'activo' => true,
    ]);
});

test('admin puede ver configuración', function () {
    $manifest = public_path('build/manifest.json');
    $assetVersion = file_exists($manifest) ? hash_file('xxh128', $manifest) : '';

    $this->actingAs($this->admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $assetVersion])
        ->get('/admin/configuracion')
        ->assertStatus(200);
});

test('admin puede actualizar nombre y ciudad', function () {
    $this->actingAs($this->admin)
        ->patch('/admin/configuracion', [
            'nombre'                  => 'Nuevo Nombre',
            'ciudad'                  => 'Bogotá',
            'max_poderes_por_delegado' => 3,
        ])
        ->assertRedirect('/admin/configuracion');

    expect($this->tenant->fresh()->nombre)->toBe('Nuevo Nombre');
    expect($this->tenant->fresh()->max_poderes_por_delegado)->toBe(3);
});

test('copropietario no puede acceder a configuración', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'copropietario', 'activo' => true]);

    $this->actingAs($user)
        ->get('/admin/configuracion')
        ->assertStatus(403);
});
