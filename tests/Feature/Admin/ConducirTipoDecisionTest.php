<?php

use App\Enums\ReunionEstado;
use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\TipoDecision;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Votacion;

function inertiaVersionConducir(): string
{
    $manifest = public_path('build/manifest.json');
    return file_exists($manifest) ? hash_file('xxh128', $manifest) : '';
}

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $this->tenant);
    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'rol'       => 'administrador',
        'activo'    => true,
    ]);
    $this->reunion = Reunion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'estado'     => ReunionEstado::EnCurso,
        'creado_por' => $this->admin->id,
    ]);
});

test('conducir serializa tipo_decision con tipo_mayoria en votaciones', function () {
    $tipo = TipoDecision::create([
        'codigo'       => 'calificada_70_test_' . uniqid(),
        'nombre'       => 'Mayoría Calificada 70%',
        'descripcion'  => 'Requiere 70% de coeficiente presente',
        'tipo_mayoria' => 'calificada_70',
        'aplica_en'    => ['asamblea'],
        'orden'        => 99,
    ]);

    $votacion = Votacion::factory()->create([
        'tenant_id'        => $this->tenant->id,
        'reunion_id'       => $this->reunion->id,
        'estado'           => 'creada',
        'tipo_decision_id' => $tipo->id,
        'creada_por'       => $this->admin->id,
    ]);
    $votacion->opciones()->create(['texto' => 'Sí', 'orden' => 1]);
    $votacion->opciones()->create(['texto' => 'No', 'orden' => 2]);

    $response = $this->actingAs($this->admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersionConducir()])
        ->get("/admin/reuniones/{$this->reunion->id}/conducir");

    $response->assertStatus(200);
    $props = $response->json('props');

    expect($props)->toHaveKey('votaciones');
    $vProps = collect($props['votaciones'])->firstWhere('id', $votacion->id);
    expect($vProps)->not->toBeNull()
        ->and($vProps)->toHaveKey('tipo_decision')
        ->and($vProps['tipo_decision'])->not->toBeNull()
        ->and($vProps['tipo_decision']['tipo_mayoria'])->toBe('calificada_70');
});

test('show serializa tipo_decision con tipo_mayoria en votaciones', function () {
    $tipo = TipoDecision::create([
        'codigo'       => 'unanimidad_test_' . uniqid(),
        'nombre'       => 'Unanimidad',
        'descripcion'  => 'Requiere 100% de coeficiente presente',
        'tipo_mayoria' => 'unanimidad',
        'aplica_en'    => ['asamblea'],
        'orden'        => 99,
    ]);

    $votacion = Votacion::factory()->create([
        'tenant_id'        => $this->tenant->id,
        'reunion_id'       => $this->reunion->id,
        'estado'           => 'creada',
        'tipo_decision_id' => $tipo->id,
        'creada_por'       => $this->admin->id,
    ]);
    $votacion->opciones()->create(['texto' => 'Sí', 'orden' => 1]);
    $votacion->opciones()->create(['texto' => 'No', 'orden' => 2]);

    $response = $this->actingAs($this->admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersionConducir()])
        ->get("/admin/reuniones/{$this->reunion->id}");

    $response->assertStatus(200);
    $props = $response->json('props');

    expect($props)->toHaveKey('votaciones');
    $vProps = collect($props['votaciones'])->firstWhere('id', $votacion->id);
    expect($vProps)->not->toBeNull()
        ->and($vProps)->toHaveKey('tipo_decision')
        ->and($vProps['tipo_decision'])->not->toBeNull()
        ->and($vProps['tipo_decision']['tipo_mayoria'])->toBe('unanimidad');
});

test('conducir serializa tipo_decision nulo cuando votacion no tiene tipo asignado', function () {
    $votacion = Votacion::factory()->create([
        'tenant_id'        => $this->tenant->id,
        'reunion_id'       => $this->reunion->id,
        'estado'           => 'creada',
        'tipo_decision_id' => null,
        'creada_por'       => $this->admin->id,
    ]);
    $votacion->opciones()->create(['texto' => 'Sí', 'orden' => 1]);
    $votacion->opciones()->create(['texto' => 'No', 'orden' => 2]);

    $response = $this->actingAs($this->admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersionConducir()])
        ->get("/admin/reuniones/{$this->reunion->id}/conducir");

    $response->assertStatus(200);
    $props = $response->json('props');
    $vProps = collect($props['votaciones'])->firstWhere('id', $votacion->id);

    expect($vProps)->not->toBeNull()
        ->and($vProps['tipo_decision'])->toBeNull();
});
