<?php

use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\User;

function adminInertiaVersion(): string
{
    $manifest = public_path('build/manifest.json');
    if (file_exists($manifest)) {
        return hash_file('xxh128', $manifest);
    }
    return '';
}

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $this->tenant);
    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'rol' => 'administrador',
        'activo' => true,
    ]);
});

test('index devuelve solo copropietarios (no externos) en tab default', function () {
    Copropietario::factory()->create(['tenant_id' => $this->tenant->id, 'es_externo' => false]);
    Copropietario::factory()->create(['tenant_id' => $this->tenant->id, 'es_externo' => true]);

    $response = $this->actingAs($this->admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => adminInertiaVersion()])
        ->get('/admin/copropietarios');

    $response->assertStatus(200);
    $data = $response->json('props.copropietarios.data');
    expect(collect($data)->where('es_externo', true)->count())->toBe(0);
    expect(collect($data)->where('es_externo', false)->count())->toBe(1);
});

test('tab externos devuelve solo externos', function () {
    Copropietario::factory()->create(['tenant_id' => $this->tenant->id, 'es_externo' => false]);
    Copropietario::factory()->create(['tenant_id' => $this->tenant->id, 'es_externo' => true]);

    $response = $this->actingAs($this->admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => adminInertiaVersion()])
        ->get('/admin/copropietarios?tab=externos');

    $data = $response->json('props.copropietarios.data');
    expect(collect($data)->where('es_externo', true)->count())->toBe(1);
    expect(collect($data)->where('es_externo', false)->count())->toBe(0);
});

test('búsqueda filtra por nombre', function () {
    Copropietario::factory()->create(['tenant_id' => $this->tenant->id, 'nombre' => 'Juan Pérez', 'es_externo' => false]);
    Copropietario::factory()->create(['tenant_id' => $this->tenant->id, 'nombre' => 'María López', 'es_externo' => false]);

    $response = $this->actingAs($this->admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => adminInertiaVersion()])
        ->get('/admin/copropietarios?search=Juan');

    $data = $response->json('props.copropietarios.data');
    expect(count($data))->toBe(1);
    expect($data[0]['nombre'])->toBe('Juan Pérez');
});

test('resultado está paginado', function () {
    for ($i = 0; $i < 25; $i++) {
        Copropietario::factory()->create(['tenant_id' => $this->tenant->id, 'es_externo' => false]);
    }

    $response = $this->actingAs($this->admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => adminInertiaVersion()])
        ->get('/admin/copropietarios');

    $data = $response->json('props.copropietarios');
    expect(count($data['data']))->toBe(20);
    expect($data['total'])->toBe(25);
});

test('no se puede eliminar externo con poder activo en reunion vigente', function () {
    $externo = Copropietario::factory()->create([
        'tenant_id' => $this->tenant->id,
        'es_externo' => true,
    ]);

    $adminUser = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'administrador']);
    $reunion = Reunion::factory()->create(['creado_por' => $adminUser->id, 'estado' => 'en_curso']);

    $poderdante = Copropietario::factory()->create([
        'tenant_id' => $this->tenant->id,
        'es_externo' => false,
    ]);

    Poder::create([
        'tenant_id'      => $this->tenant->id,
        'reunion_id'     => $reunion->id,
        'apoderado_id'   => $externo->id,
        'poderdante_id'  => $poderdante->id,
        'registrado_por' => $adminUser->id,
        'estado'         => 'aprobado',
        'aprobado_por'   => $adminUser->id,
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/copropietarios/{$externo->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Copropietario::find($externo->id))->not->toBeNull();
});

test('se puede eliminar externo cuyo poder corresponde a reunion finalizada', function () {
    $externo = Copropietario::factory()->create([
        'tenant_id' => $this->tenant->id,
        'es_externo' => true,
    ]);

    $adminUser = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'administrador']);
    $reunion = Reunion::factory()->create(['creado_por' => $adminUser->id, 'estado' => 'finalizada']);

    $poderdante = Copropietario::factory()->create([
        'tenant_id' => $this->tenant->id,
        'es_externo' => false,
    ]);

    Poder::create([
        'tenant_id'      => $this->tenant->id,
        'reunion_id'     => $reunion->id,
        'apoderado_id'   => $externo->id,
        'poderdante_id'  => $poderdante->id,
        'registrado_por' => $adminUser->id,
        'estado'         => 'aprobado',
        'aprobado_por'   => $adminUser->id,
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/copropietarios/{$externo->id}")
        ->assertRedirect()
        ->assertSessionHas('success')
        ->assertSessionMissing('error');

    expect(Copropietario::find($externo->id))->toBeNull();
});

test('no se puede eliminar externo con poder pendiente en reunion vigente', function () {
    $externo = Copropietario::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'es_externo' => true,
    ]);

    $adminUser = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'administrador']);
    $reunion = Reunion::factory()->create(['creado_por' => $adminUser->id, 'estado' => 'ante_sala']);

    $poderdante = Copropietario::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'es_externo' => false,
    ]);

    Poder::create([
        'tenant_id'      => $this->tenant->id,
        'reunion_id'     => $reunion->id,
        'apoderado_id'   => $externo->id,
        'poderdante_id'  => $poderdante->id,
        'registrado_por' => $adminUser->id,
        'estado'         => 'pendiente',
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/copropietarios/{$externo->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Copropietario::find($externo->id))->not->toBeNull();
});
