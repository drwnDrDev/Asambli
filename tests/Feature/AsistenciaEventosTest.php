<?php

use App\Enums\ReunionEstado;
use App\Models\AsistenciaEvento;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;

function inertiaVersionEventos(): string
{
    $manifest = public_path('build/manifest.json');
    return file_exists($manifest) ? hash_file('xxh128', $manifest) : '';
}

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $this->tenant);
    $this->reunion = Reunion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'estado'    => ReunionEstado::AnteSala,
    ]);
});

test('entrar a la sala por PIN registra evento entrada auto_sala', function () {
    $copro = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
    Unidad::factory()->create(['tenant_id' => $this->tenant->id, 'copropietario_id' => $copro->id, 'coeficiente' => 50.0]);

    $token = \Illuminate\Support\Str::random(64);
    \App\Models\AccesoReunion::create([
        'copropietario_id' => $copro->id,
        'reunion_id'       => $this->reunion->id,
        'pin_hash'         => bcrypt('000000'),
        'session_token'    => $token,
        'activo'           => true,
    ]);

    app()->forgetInstance('current_tenant');

    $this->withSession(['copropietario_session_token' => $token])
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersionEventos()])
        ->get("/sala/{$this->reunion->id}")
        ->assertStatus(200);

    $evento = AsistenciaEvento::withoutGlobalScopes()
        ->where('reunion_id', $this->reunion->id)
        ->where('copropietario_id', $copro->id)
        ->first();

    expect($evento)->not->toBeNull()
        ->and($evento->tipo)->toBe('entrada')
        ->and($evento->origen)->toBe('auto_sala')
        ->and($evento->quorum_resultante)->toBeGreaterThan(0);
});

test('reentrar a la sala no duplica el evento de entrada', function () {
    $copro = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
    Unidad::factory()->create(['tenant_id' => $this->tenant->id, 'copropietario_id' => $copro->id, 'coeficiente' => 50.0]);

    $token = \Illuminate\Support\Str::random(64);
    \App\Models\AccesoReunion::create([
        'copropietario_id' => $copro->id,
        'reunion_id'       => $this->reunion->id,
        'pin_hash'         => bcrypt('000000'),
        'session_token'    => $token,
        'activo'           => true,
    ]);

    app()->forgetInstance('current_tenant');

    foreach ([1, 2] as $i) {
        $this->withSession(['copropietario_session_token' => $token])
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersionEventos()])
            ->get("/sala/{$this->reunion->id}");
    }

    expect(AsistenciaEvento::withoutGlobalScopes()
        ->where('reunion_id', $this->reunion->id)
        ->where('copropietario_id', $copro->id)
        ->count())->toBe(1);
});

test('entrar como apoderado registra evento representado para cada poderdante', function () {
    $admin      = User::factory()->create(['tenant_id' => $this->tenant->id, 'rol' => 'administrador', 'activo' => true]);
    $apoderado  = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
    $poderdante = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
    Unidad::factory()->create(['tenant_id' => $this->tenant->id, 'copropietario_id' => $apoderado->id, 'coeficiente' => 50.0]);
    Unidad::factory()->create(['tenant_id' => $this->tenant->id, 'copropietario_id' => $poderdante->id, 'coeficiente' => 50.0]);

    Poder::withoutEvents(fn () => Poder::create([
        'tenant_id'      => $this->tenant->id,
        'reunion_id'     => $this->reunion->id,
        'apoderado_id'   => $apoderado->id,
        'poderdante_id'  => $poderdante->id,
        'estado'         => 'aprobado',
        'registrado_por' => $admin->id,
    ]));

    $token = \Illuminate\Support\Str::random(64);
    \App\Models\AccesoReunion::create([
        'copropietario_id' => $apoderado->id,
        'reunion_id'       => $this->reunion->id,
        'pin_hash'         => bcrypt('000000'),
        'session_token'    => $token,
        'activo'           => true,
    ]);

    app()->forgetInstance('current_tenant');

    $this->withSession(['copropietario_session_token' => $token])
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersionEventos()])
        ->get("/sala/{$this->reunion->id}");

    $eventoRepresentado = AsistenciaEvento::withoutGlobalScopes()
        ->where('reunion_id', $this->reunion->id)
        ->where('copropietario_id', $poderdante->id)
        ->first();

    expect($eventoRepresentado)->not->toBeNull()
        ->and($eventoRepresentado->origen)->toBe('representado');
});

test('confirmacion manual del admin registra evento entrada admin', function () {
    $admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'rol'       => 'administrador',
        'activo'    => true,
    ]);
    $copro = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
    Unidad::factory()->create(['tenant_id' => $this->tenant->id, 'copropietario_id' => $copro->id, 'coeficiente' => 50.0]);

    $this->actingAs($admin)
        ->post("/admin/reuniones/{$this->reunion->id}/copropietarios/{$copro->id}/asistencia");

    $evento = AsistenciaEvento::withoutGlobalScopes()
        ->where('reunion_id', $this->reunion->id)
        ->where('copropietario_id', $copro->id)
        ->first();

    expect($evento)->not->toBeNull()
        ->and($evento->origen)->toBe('admin');
});
