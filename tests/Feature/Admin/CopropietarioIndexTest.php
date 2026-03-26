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
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'copropietario']);
    Copropietario::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'es_externo' => false]);

    $userExt = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'copropietario']);
    Copropietario::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $userExt->id, 'es_externo' => true]);

    $response = $this->actingAs($this->admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => adminInertiaVersion()])
        ->get('/admin/copropietarios');

    $response->assertStatus(200);
    $data = $response->json('props.copropietarios.data');
    expect(collect($data)->where('es_externo', true)->count())->toBe(0);
    expect(collect($data)->where('es_externo', false)->count())->toBe(1);
});

test('tab externos devuelve solo externos', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'copropietario']);
    Copropietario::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'es_externo' => false]);

    $userExt = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'copropietario']);
    Copropietario::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $userExt->id, 'es_externo' => true]);

    $response = $this->actingAs($this->admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => adminInertiaVersion()])
        ->get('/admin/copropietarios?tab=externos');

    $data = $response->json('props.copropietarios.data');
    expect(collect($data)->where('es_externo', true)->count())->toBe(1);
    expect(collect($data)->where('es_externo', false)->count())->toBe(0);
});

test('búsqueda filtra por nombre', function () {
    $userA = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Juan Pérez', 'rol' => 'copropietario']);
    Copropietario::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $userA->id, 'es_externo' => false]);

    $userB = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'María López', 'rol' => 'copropietario']);
    Copropietario::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $userB->id, 'es_externo' => false]);

    $response = $this->actingAs($this->admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => adminInertiaVersion()])
        ->get('/admin/copropietarios?search=Juan');

    $data = $response->json('props.copropietarios.data');
    expect(count($data))->toBe(1);
    expect($data[0]['user']['name'])->toBe('Juan Pérez');
});

test('resultado está paginado', function () {
    for ($i = 0; $i < 25; $i++) {
        $u = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'copropietario']);
        Copropietario::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $u->id, 'es_externo' => false]);
    }

    $response = $this->actingAs($this->admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => adminInertiaVersion()])
        ->get('/admin/copropietarios');

    $data = $response->json('props.copropietarios');
    expect(count($data['data']))->toBe(20);
    expect($data['total'])->toBe(25);
});

test('no se puede eliminar externo con poder activo en reunion vigente', function () {
    $userExt = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'copropietario']);
    $externo = Copropietario::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id'   => $userExt->id,
        'es_externo' => true,
    ]);

    // Reunión activa (en_curso)
    $adminUser = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'administrador']);
    $reunion = Reunion::factory()->create(['creado_por' => $adminUser->id, 'estado' => 'en_curso']);

    $poderdanteUser = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'copropietario']);
    $poderdante = Copropietario::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id'   => $poderdanteUser->id,
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

    expect(Copropietario::find($externo->id))->not->toBeNull(); // still exists
});

test('se puede eliminar externo cuyo poder corresponde a reunion finalizada', function () {
    $userExt = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'copropietario']);
    $externo = Copropietario::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id'   => $userExt->id,
        'es_externo' => true,
    ]);

    $adminUser = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'administrador']);
    $reunion = Reunion::factory()->create(['creado_por' => $adminUser->id, 'estado' => 'finalizada']);

    $poderdanteUser = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'copropietario']);
    $poderdante = Copropietario::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id'   => $poderdanteUser->id,
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
        ->assertRedirect();

    expect(Copropietario::find($externo->id))->toBeNull(); // deleted
});
