<?php
use App\Enums\ReunionEstado;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\ReunionLog;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Votacion;
use App\Models\OpcionVotacion;

/**
 * Compute the Inertia asset version the same way the Inertia middleware does,
 * so tests don't trigger the 409 version-mismatch redirect.
 */
function inertiaVersion(): string
{
    $manifest = public_path('build/manifest.json');
    if (file_exists($manifest)) {
        return hash_file('xxh128', $manifest);
    }
    return '';
}

it('show includes estadoReunion in inertia props', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    Copropietario::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id]);

    $reunion = Reunion::factory()->create([
        'tenant_id' => $tenant->id,
        'estado'    => ReunionEstado::EnCurso,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
        ->get("/sala/{$reunion->id}");

    $response->assertStatus(200);
    $props = $response->json('props');
    expect($props)->toHaveKey('estadoReunion')
        ->and($props['estadoReunion'])->toBe('en_curso');
});

it('show includes feedInicial with reunion log entries', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    Copropietario::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id]);

    $reunion = Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => ReunionEstado::EnCurso]);
    ReunionLog::create([
        'reunion_id'  => $reunion->id,
        'user_id'     => $user->id,
        'accion'      => 'estado_cambiado_a_en_curso',
        'observacion' => 'Iniciando',
        'metadata'    => [],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
        ->get("/sala/{$reunion->id}");

    $props = $response->json('props');
    expect($props)->toHaveKey('feedInicial')
        ->and($props['feedInicial'])->toBeArray()
        ->and(count($props['feedInicial']))->toBeGreaterThanOrEqual(1);
});

it('show includes resultadosActuales only when copropietario already voted', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    $copro = Copropietario::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id]);

    $reunion = Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => ReunionEstado::EnCurso]);
    $votacion = Votacion::factory()->create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id, 'estado' => 'abierta']);
    // OpcionVotacionFactory does not exist — create directly (no tenant_id on OpcionVotacion)
    \App\Models\OpcionVotacion::create(['votacion_id' => $votacion->id, 'texto' => 'SÍ', 'orden' => 1]);

    // No vote cast: resultadosActuales must be null
    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
        ->get("/sala/{$reunion->id}");

    $props = $response->json('props');
    expect($props['resultadosActuales'])->toBeNull();
});

it('historial includes finalizada, cancelada, and reprogramada reuniones', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    Copropietario::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id]);

    Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => ReunionEstado::Finalizada]);
    Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => ReunionEstado::Cancelada]);
    Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => ReunionEstado::Reprogramada]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
        ->get('/historial');

    $props = $response->json('props');
    expect(count($props['reuniones']))->toBe(3);
});

it('show includes resultadosActuales with correct format when copropietario already voted', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $copro = Copropietario::factory()->create(['tenant_id' => $tenant->id]);

    $reunion = Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => ReunionEstado::EnCurso]);
    $votacion = Votacion::factory()->create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id, 'estado' => 'abierta']);
    $opcion1 = \App\Models\OpcionVotacion::create(['votacion_id' => $votacion->id, 'texto' => 'SÍ', 'orden' => 1]);
    \App\Models\OpcionVotacion::create(['votacion_id' => $votacion->id, 'texto' => 'NO', 'orden' => 2]);

    // Cast a vote so yaVotoPor is not empty
    \App\Models\Voto::create([
        'tenant_id'           => $tenant->id,
        'votacion_id'         => $votacion->id,
        'copropietario_id'    => $copro->id,
        'en_nombre_de'        => null,
        'opcion_id'           => $opcion1->id,
        'peso'                => 0.5,
        'ip_address'          => '127.0.0.1',
        'user_agent'          => 'test',
        'hash_verificacion'   => 'hash123',
    ]);

    // Autenticar via copropietario guard (PIN-based session)
    $token = \Illuminate\Support\Str::random(64);
    \App\Models\AccesoReunion::create([
        'copropietario_id' => $copro->id,
        'reunion_id'       => $reunion->id,
        'pin_hash'         => bcrypt('000000'),
        'session_token'    => $token,
        'activo'           => true,
    ]);

    $response = $this->withSession(['copropietario_session_token' => $token])
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
        ->get("/sala/{$reunion->id}");

    $props = $response->json('props');
    expect($props['resultadosActuales'])->not->toBeNull()
        ->and($props['resultadosActuales'])->toBeArray()
        ->and(count($props['resultadosActuales']))->toBe(2);

    foreach ($props['resultadosActuales'] as $resultado) {
        expect($resultado)->toHaveKeys(['opcion_id', 'texto', 'count', 'peso_total']);
    }
});
