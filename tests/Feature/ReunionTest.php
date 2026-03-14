<?php

use App\Enums\ReunionEstado;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReunionTransicionService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $this->tenant);
    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'rol'       => 'administrador',
    ]);
    $this->reunion  = Reunion::factory()->create(['creado_por' => $this->admin->id]);
    $this->service  = new ReunionTransicionService();
});

test('reunion starts as borrador', function () {
    expect($this->reunion->estado)->toBe(ReunionEstado::Borrador);
});

test('valid transition borrador to ante_sala creates log with observacion and estado_anterior', function () {
    $this->service->transicionar(
        $this->reunion,
        ReunionEstado::AnteSala,
        $this->admin,
        'Convocatoria enviada a todos los propietarios.'
    );

    $this->reunion->refresh();

    expect($this->reunion->estado)->toBe(ReunionEstado::AnteSala);

    $log = $this->reunion->logs()->first();
    expect($log)->not->toBeNull();
    expect($log->accion)->toBe('estado_cambiado_a_ante_sala');
    expect($log->observacion)->toBe('Convocatoria enviada a todos los propietarios.');
    expect($log->metadata['estado_anterior'])->toBe('borrador');
});

test('transitioning without observacion throws InvalidArgumentException', function () {
    expect(fn () => $this->service->transicionar(
        $this->reunion,
        ReunionEstado::AnteSala,
        $this->admin,
        '   '
    ))->toThrow(\InvalidArgumentException::class);
});

test('invalid transition borrador to finalizada throws InvalidArgumentException', function () {
    expect(fn () => $this->service->transicionar(
        $this->reunion,
        ReunionEstado::Finalizada,
        $this->admin,
        'Intentando saltar estados.'
    ))->toThrow(\InvalidArgumentException::class);
});

test('transitioning to en_curso sets fecha_inicio', function () {
    $this->service->transicionar(
        $this->reunion,
        ReunionEstado::AnteSala,
        $this->admin,
        'Sala abierta para participantes.'
    );

    $this->service->transicionar(
        $this->reunion,
        ReunionEstado::EnCurso,
        $this->admin,
        'Quórum alcanzado, inicio de asamblea.'
    );

    $this->reunion->refresh();

    expect($this->reunion->estado)->toBe(ReunionEstado::EnCurso);
    expect($this->reunion->fecha_inicio)->not->toBeNull();
});

test('transitioning to finalizada sets fecha_fin', function () {
    $this->service->transicionar($this->reunion, ReunionEstado::AnteSala,  $this->admin, 'Sala abierta.');
    $this->service->transicionar($this->reunion, ReunionEstado::EnCurso,   $this->admin, 'Asamblea iniciada.');
    $this->service->transicionar($this->reunion, ReunionEstado::Finalizada, $this->admin, 'Todos los puntos tratados.');

    $this->reunion->refresh();

    expect($this->reunion->estado)->toBe(ReunionEstado::Finalizada);
    expect($this->reunion->fecha_fin)->not->toBeNull();
});

test('suspension and reactivation en_curso to suspendida back to en_curso', function () {
    $this->service->transicionar($this->reunion, ReunionEstado::AnteSala,  $this->admin, 'Sala lista.');
    $this->service->transicionar($this->reunion, ReunionEstado::EnCurso,   $this->admin, 'Inicio de asamblea.');
    $this->service->transicionar($this->reunion, ReunionEstado::Suspendida, $this->admin, 'Pausa técnica de 15 minutos.');
    $this->service->transicionar($this->reunion, ReunionEstado::EnCurso,   $this->admin, 'Reanudación tras pausa.');

    $this->reunion->refresh();

    expect($this->reunion->estado)->toBe(ReunionEstado::EnCurso);
    expect($this->reunion->logs()->count())->toBe(4);
});

test('estaActiva returns false for terminal states and true for active states', function () {
    // Active state: borrador
    expect($this->reunion->estaActiva())->toBeTrue();

    // Move to ante_sala — still active
    $this->service->transicionar($this->reunion, ReunionEstado::AnteSala, $this->admin, 'Sala abierta.');
    $this->reunion->refresh();
    expect($this->reunion->estaActiva())->toBeTrue();

    // Terminal: reprogramada
    $reprogramada = Reunion::factory()->create(['creado_por' => $this->admin->id]);
    $this->service->transicionar($reprogramada, ReunionEstado::AnteSala,    $this->admin, 'Sala lista.');
    $this->service->transicionar($reprogramada, ReunionEstado::Reprogramada, $this->admin, 'Fecha cambiada por lluvia.');
    $reprogramada->refresh();
    expect($reprogramada->estaActiva())->toBeFalse();

    // Terminal: cancelada (direct from borrador)
    $cancelada = Reunion::factory()->create(['creado_por' => $this->admin->id]);
    $this->service->transicionar($cancelada, ReunionEstado::Cancelada, $this->admin, 'Sin quórum mínimo.');
    $cancelada->refresh();
    expect($cancelada->estaActiva())->toBeFalse();

    // Terminal: finalizada
    $finalizada = Reunion::factory()->create(['creado_por' => $this->admin->id]);
    $this->service->transicionar($finalizada, ReunionEstado::AnteSala,   $this->admin, 'Sala lista.');
    $this->service->transicionar($finalizada, ReunionEstado::EnCurso,    $this->admin, 'Asamblea iniciada.');
    $this->service->transicionar($finalizada, ReunionEstado::Finalizada, $this->admin, 'Asamblea concluida.');
    $finalizada->refresh();
    expect($finalizada->estaActiva())->toBeFalse();
});

test('reunion can be cancelled from ante_sala en_curso and suspendida', function () {
    // Cancel from ante_sala
    $r1 = Reunion::factory()->create(['creado_por' => $this->admin->id]);
    $this->service->transicionar($r1, ReunionEstado::AnteSala,  $this->admin, 'Sala lista.');
    $this->service->transicionar($r1, ReunionEstado::Cancelada, $this->admin, 'No hubo quórum en ante sala.');
    $r1->refresh();
    expect($r1->estado)->toBe(ReunionEstado::Cancelada);

    // Cancel from en_curso
    $r2 = Reunion::factory()->create(['creado_por' => $this->admin->id]);
    $this->service->transicionar($r2, ReunionEstado::AnteSala,  $this->admin, 'Sala lista.');
    $this->service->transicionar($r2, ReunionEstado::EnCurso,   $this->admin, 'Asamblea iniciada.');
    $this->service->transicionar($r2, ReunionEstado::Cancelada, $this->admin, 'Emergencia en el edificio.');
    $r2->refresh();
    expect($r2->estado)->toBe(ReunionEstado::Cancelada);

    // Cancel from suspendida
    $r3 = Reunion::factory()->create(['creado_por' => $this->admin->id]);
    $this->service->transicionar($r3, ReunionEstado::AnteSala,   $this->admin, 'Sala lista.');
    $this->service->transicionar($r3, ReunionEstado::EnCurso,    $this->admin, 'Asamblea iniciada.');
    $this->service->transicionar($r3, ReunionEstado::Suspendida, $this->admin, 'Pausa indefinida.');
    $this->service->transicionar($r3, ReunionEstado::Cancelada,  $this->admin, 'No se retomó la sesión.');
    $r3->refresh();
    expect($r3->estado)->toBe(ReunionEstado::Cancelada);
});

test('reunion can be reprogramada from ante_sala', function () {
    $this->service->transicionar($this->reunion, ReunionEstado::AnteSala,    $this->admin, 'Sala abierta.');
    $this->service->transicionar($this->reunion, ReunionEstado::Reprogramada, $this->admin, 'Nueva fecha acordada por el consejo.');

    $this->reunion->refresh();

    expect($this->reunion->estado)->toBe(ReunionEstado::Reprogramada);
    expect($this->reunion->estaActiva())->toBeFalse();
});
