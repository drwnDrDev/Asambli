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
