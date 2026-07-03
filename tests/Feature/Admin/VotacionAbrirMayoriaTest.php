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

function presenciaConCoeficiente(Tenant $tenant, Reunion $reunion, array $coeficientes, int $presentes): void
{
    foreach ($coeficientes as $i => $coef) {
        $c = Copropietario::factory()->create(['tenant_id' => $tenant->id]);
        Unidad::factory()->create([
            'tenant_id'        => $tenant->id,
            'copropietario_id' => $c->id,
            'coeficiente'      => $coef,
        ]);
        if ($i < $presentes) {
            Asistencia::create([
                'reunion_id'           => $reunion->id,
                'copropietario_id'     => $c->id,
                'confirmada_por_admin' => true,
                'hora_confirmacion'    => now(),
            ]);
        }
    }
}

function votacionConMayoria(Tenant $tenant, Reunion $reunion, User $admin, string $tipoMayoria): Votacion
{
    $tipo = TipoDecision::create([
        'codigo'       => 'abrir_test_' . $tipoMayoria . '_' . uniqid(),
        'nombre'       => 'Test',
        'descripcion'  => 'Test',
        'tipo_mayoria' => $tipoMayoria,
        'aplica_en'    => ['asamblea'],
        'orden'        => 99,
    ]);

    return Votacion::factory()->create([
        'tenant_id'        => $tenant->id,
        'reunion_id'       => $reunion->id,
        'estado'           => 'creada',
        'tipo_decision_id' => $tipo->id,
        'creada_por'       => $admin->id,
    ]);
}

test('calificada_70 no abre con presencia menor a 70%', function () {
    presenciaConCoeficiente($this->tenant, $this->reunion, [40.0, 29.0, 31.0], presentes: 2); // 69%
    $votacion = votacionConMayoria($this->tenant, $this->reunion, $this->admin, 'calificada_70');

    $this->actingAs($this->admin)
        ->post(route('admin.votaciones.abrir', $votacion))
        ->assertSessionHas('error');

    expect($votacion->fresh()->estado)->toBe('creada');
});

test('calificada_70 abre con presencia de 70% exacto', function () {
    presenciaConCoeficiente($this->tenant, $this->reunion, [40.0, 30.0, 30.0], presentes: 2); // 70%
    $votacion = votacionConMayoria($this->tenant, $this->reunion, $this->admin, 'calificada_70');

    $this->actingAs($this->admin)
        ->post(route('admin.votaciones.abrir', $votacion))
        ->assertSessionMissing('error');

    expect($votacion->fresh()->estado)->toBe('abierta');
});

test('unanimidad no abre sin 100% de presencia', function () {
    presenciaConCoeficiente($this->tenant, $this->reunion, [50.0, 49.0, 1.0], presentes: 2); // 99%
    $votacion = votacionConMayoria($this->tenant, $this->reunion, $this->admin, 'unanimidad');

    $this->actingAs($this->admin)
        ->post(route('admin.votaciones.abrir', $votacion))
        ->assertSessionHas('error');

    expect($votacion->fresh()->estado)->toBe('creada');
});

test('unanimidad abre con 100% de presencia', function () {
    presenciaConCoeficiente($this->tenant, $this->reunion, [50.0, 50.0], presentes: 2);
    $votacion = votacionConMayoria($this->tenant, $this->reunion, $this->admin, 'unanimidad');

    $this->actingAs($this->admin)
        ->post(route('admin.votaciones.abrir', $votacion))
        ->assertSessionMissing('error');

    expect($votacion->fresh()->estado)->toBe('abierta');
});

test('mayoria simple abre sin restriccion de presencia', function () {
    presenciaConCoeficiente($this->tenant, $this->reunion, [10.0, 90.0], presentes: 1); // 10%
    $votacion = votacionConMayoria($this->tenant, $this->reunion, $this->admin, 'simple');

    $this->actingAs($this->admin)
        ->post(route('admin.votaciones.abrir', $votacion))
        ->assertSessionMissing('error');

    expect($votacion->fresh()->estado)->toBe('abierta');
});

test('bypass_quorum no exime el bloqueo de apertura', function () {
    config(['app.bypass_quorum' => true]);
    presenciaConCoeficiente($this->tenant, $this->reunion, [40.0, 60.0], presentes: 1); // 40%
    $votacion = votacionConMayoria($this->tenant, $this->reunion, $this->admin, 'calificada_70');

    $this->actingAs($this->admin)
        ->post(route('admin.votaciones.abrir', $votacion))
        ->assertSessionHas('error');

    expect($votacion->fresh()->estado)->toBe('creada');
});
