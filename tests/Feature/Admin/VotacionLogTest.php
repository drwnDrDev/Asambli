<?php

use App\Enums\ReunionEstado;
use App\Models\Copropietario;
use App\Models\ReunionLog;
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
});

test('abrir votacion crea reunion_log con snapshot de quorum', function () {
    $reunion = Reunion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'estado'     => ReunionEstado::EnCurso,
        'creado_por' => $this->admin->id,
    ]);
    $votacion = Votacion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'reunion_id' => $reunion->id,
        'estado'     => 'creada',
        'creada_por' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('votaciones.abrir', $votacion))
        ->assertRedirect();

    $log = ReunionLog::where('reunion_id', $reunion->id)
        ->where('accion', 'votacion_abierta')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->metadata['votacion_id'])->toBe($votacion->id);
    expect($log->metadata['pregunta'])->toBe($votacion->pregunta);
    expect($log->metadata)->toHaveKey('quorum_porcentaje');
    expect($log->metadata)->toHaveKey('tiene_quorum');
    expect($log->metadata)->toHaveKey('quorum_requerido');
});

test('cerrar votacion crea reunion_log con total_votos y resultado_ganador', function () {
    $reunion = Reunion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'estado'     => ReunionEstado::EnCurso,
        'creado_por' => $this->admin->id,
    ]);
    $votacion = Votacion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'reunion_id' => $reunion->id,
        'estado'     => 'abierta',
        'creada_por' => $this->admin->id,
    ]);
    $opcion = $votacion->opciones()->create(['texto' => 'Sí']);

    $userVotante = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $copropietario = Copropietario::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id'   => $userVotante->id,
    ]);
    \App\Models\Voto::create([
        'tenant_id'         => $this->tenant->id,
        'votacion_id'       => $votacion->id,
        'copropietario_id'  => $copropietario->id,
        'en_nombre_de'      => null,
        'opcion_id'         => $opcion->id,
        'peso'              => 1.0,
        'hash_verificacion' => hash('sha256', 'test'),
        'created_at'        => now(),
    ]);

    $this->actingAs($this->admin)
        ->post(route('votaciones.cerrar', $votacion))
        ->assertRedirect();

    $log = ReunionLog::where('reunion_id', $reunion->id)
        ->where('accion', 'votacion_cerrada')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->metadata['votacion_id'])->toBe($votacion->id);
    expect($log->metadata['total_votos'])->toBe(1);
    expect($log->metadata['resultado_ganador'])->toBe('Sí');
});
