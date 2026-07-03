<?php

use App\Enums\ReunionEstado;
use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
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

    $copro = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
    Unidad::factory()->create(['tenant_id' => $this->tenant->id, 'copropietario_id' => $copro->id, 'coeficiente' => 100.0]);
    Asistencia::create([
        'reunion_id'           => $this->reunion->id,
        'copropietario_id'     => $copro->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion'    => now(),
    ]);
});

test('abrir votacion persiste quorum_apertura', function () {
    $votacion = Votacion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'reunion_id' => $this->reunion->id,
        'estado'     => 'creada',
        'creada_por' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->post(route('admin.votaciones.abrir', $votacion));

    $votacion->refresh();
    expect($votacion->quorum_apertura)->toBeArray()
        ->and($votacion->quorum_apertura)->toHaveKeys(['porcentaje_presente', 'presente', 'total', 'tiene_quorum']);
});

test('cerrar votacion persiste quorum_cierre', function () {
    $votacion = Votacion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'reunion_id' => $this->reunion->id,
        'estado'     => 'abierta',
        'creada_por' => $this->admin->id,
    ]);
    $votacion->opciones()->create(['texto' => 'Sí', 'orden' => 1]);

    $this->actingAs($this->admin)->post(route('admin.votaciones.cerrar', $votacion));

    $votacion->refresh();
    expect($votacion->quorum_cierre)->toBeArray()
        ->and($votacion->quorum_cierre)->toHaveKey('porcentaje_presente');
});
