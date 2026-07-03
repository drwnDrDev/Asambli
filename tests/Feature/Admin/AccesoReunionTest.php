<?php

use App\Models\AccesoReunion;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $this->tenant);
    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'rol'       => 'administrador',
        'activo'    => true,
    ]);
});

it('admin puede ver la lista de acceso de una reunion', function () {
    $reunion = Reunion::factory()->for($this->tenant)->create();
    $copropietario = Copropietario::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email'     => 'test@example.com',
    ]);
    AccesoReunion::factory()->create([
        'copropietario_id' => $copropietario->id,
        'reunion_id'       => $reunion->id,
        'pin_plain'        => '123456',
        'activo'           => true,
    ]);

    $response = $this->actingAs($this->admin)
        ->withHeaders(['X-Inertia' => 'true'])
        ->get("/admin/reuniones/{$reunion->id}/lista-acceso");

    expect($response->status())->not->toBe(403);
});

it('usuario no autenticado no puede ver la lista de acceso', function () {
    $reunion = Reunion::factory()->for($this->tenant)->create();

    $this->get("/admin/reuniones/{$reunion->id}/lista-acceso")
        ->assertRedirect();
});

it('copropietario no puede acceder a la lista de acceso', function () {
    $reunion = Reunion::factory()->for($this->tenant)->create();
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'rol'       => 'copropietario',
    ]);

    $this->actingAs($user)
        ->get("/admin/reuniones/{$reunion->id}/lista-acceso")
        ->assertStatus(403);
});

test('lista de acceso pagina y filtra por busqueda', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador', 'activo' => true]);
    $reunion = Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => \App\Enums\ReunionEstado::AnteSala]);

    foreach (range(1, 30) as $i) {
        $c = Copropietario::factory()->create(['tenant_id' => $tenant->id, 'nombre' => "Copropietario {$i}"]);
        AccesoReunion::create([
            'copropietario_id' => $c->id,
            'reunion_id'       => $reunion->id,
            'pin_hash'         => bcrypt('000000'),
            'pin_plain'        => '000000',
            'activo'           => true,
        ]);
    }

    $manifest = public_path('build/manifest.json');
    $version = file_exists($manifest) ? hash_file('xxh128', $manifest) : '';

    // Paginación: 25 por página
    $props = $this->actingAs($admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
        ->get("/admin/reuniones/{$reunion->id}/lista-acceso")
        ->json('props');
    expect(count($props['accesos']['data']))->toBe(25)
        ->and($props['accesos']['total'])->toBe(30);

    // Búsqueda
    $props = $this->actingAs($admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
        ->get("/admin/reuniones/{$reunion->id}/lista-acceso?q=Copropietario 3")
        ->json('props');
    expect($props['accesos']['total'])->toBe(2); // "Copropietario 3" y "Copropietario 30"
});
