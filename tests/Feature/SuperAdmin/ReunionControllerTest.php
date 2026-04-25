<?php

use App\Enums\ReunionEstado;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function superAdmin(): User
{
    return User::factory()->create([
        'tenant_id' => null,
        'rol'       => 'super_admin',
        'activo'    => true,
    ]);
}

it('super_admin puede ver el formulario de crear reunion', function () {
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs(superAdmin())
        ->withHeaders(['X-Inertia' => 'true'])
        ->get("/super-admin/tenants/{$tenant->id}/reuniones/create");

    expect($response->status())->not->toBe(403);
});

it('super_admin puede crear una reunion para un tenant', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs(superAdmin())
        ->post("/super-admin/tenants/{$tenant->id}/reuniones", [
            'titulo'           => 'Asamblea Test 2026',
            'tipo'             => 'asamblea',
            'tipo_voto_peso'   => 'coeficiente',
            'quorum_requerido' => 51,
            'fecha_programada' => null,
        ]);

    $reunion = Reunion::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('titulo', 'Asamblea Test 2026')
        ->first();

    expect($reunion)->not->toBeNull();
    expect($reunion->estado)->toBe(ReunionEstado::Borrador);
    expect($reunion->modalidad)->toBe('presencial');
    expect($reunion->tenant_id)->toBe($tenant->id);
});

it('administrador no puede crear reuniones via super-admin routes', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);

    $this->actingAs($admin)
        ->post("/super-admin/tenants/{$tenant->id}/reuniones", [
            'titulo' => 'Intento no autorizado',
            'tipo'   => 'asamblea',
        ])
        ->assertStatus(403);
});

it('super_admin puede resetear convocatoria_envios', function () {
    $tenant = Tenant::factory()->create();
    $reunion = Reunion::factory()->for($tenant)->create(['convocatoria_envios' => 2]);

    $this->actingAs(superAdmin())
        ->post("/super-admin/reuniones/{$reunion->id}/reset-convocatoria");

    expect($reunion->fresh()->convocatoria_envios)->toBe(0);
});

it('reset convocatoria falla si la reunion esta en curso', function () {
    $tenant = Tenant::factory()->create();
    $reunion = Reunion::factory()->for($tenant)->create([
        'convocatoria_envios' => 2,
        'estado'              => ReunionEstado::EnCurso,
    ]);

    $this->actingAs(superAdmin())
        ->post("/super-admin/reuniones/{$reunion->id}/reset-convocatoria")
        ->assertStatus(422);
});
