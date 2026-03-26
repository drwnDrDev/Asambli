<?php

use App\Enums\ReunionEstado;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Votacion;
use App\Models\Voto;
use App\Services\ReporteService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $this->tenant);
    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'rol'       => 'administrador',
        'activo'    => true,
    ]);
});

test('generarCsvVotos incluye cabecera correcta', function () {
    $reunion = Reunion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'estado'     => ReunionEstado::Finalizada,
        'creado_por' => $this->admin->id,
    ]);

    $csv = app(ReporteService::class)->generarCsvVotos($reunion);

    expect($csv)->toContain('votacion_id,pregunta,copropietario,unidades,opcion,peso,en_nombre_de,ip_address,hora,hash');
});

test('generarCsvVotos incluye una fila por voto con datos correctos', function () {
    $reunion = Reunion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'estado'     => ReunionEstado::Finalizada,
        'creado_por' => $this->admin->id,
    ]);
    $votacion = Votacion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'reunion_id' => $reunion->id,
        'estado'     => 'cerrada',
        'creada_por' => $this->admin->id,
    ]);
    $opcion = $votacion->opciones()->create(['texto' => 'Aprobado']);

    $userVotante = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Juan Pérez']);
    $copropietario = Copropietario::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id'   => $userVotante->id,
    ]);
    Unidad::factory()->create([
        'tenant_id'        => $this->tenant->id,
        'copropietario_id' => $copropietario->id,
        'numero'           => '101',
    ]);

    Voto::create([
        'tenant_id'         => $this->tenant->id,
        'votacion_id'       => $votacion->id,
        'copropietario_id'  => $copropietario->id,
        'en_nombre_de'      => null,
        'opcion_id'         => $opcion->id,
        'peso'              => 1.5,
        'ip_address'        => '127.0.0.1',
        'hash_verificacion' => hash('sha256', 'test-hash'),
        'created_at'        => now(),
    ]);

    $csv = app(ReporteService::class)->generarCsvVotos($reunion);

    expect($csv)->toContain('Aprobado');
    expect($csv)->toContain('Juan Pérez');
    expect($csv)->toContain('101');
    expect($csv)->toContain('1.5');
});

test('generarCsvVotos incluye en_nombre_de cuando el voto es delegado', function () {
    $reunion = Reunion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'estado'     => ReunionEstado::Finalizada,
        'creado_por' => $this->admin->id,
    ]);
    $votacion = Votacion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'reunion_id' => $reunion->id,
        'estado'     => 'cerrada',
        'creada_por' => $this->admin->id,
    ]);
    $opcion = $votacion->opciones()->create(['texto' => 'Sí']);

    $userApoderado = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Carlos Delegado']);
    $apoderado = Copropietario::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id'   => $userApoderado->id,
    ]);

    $userPoderdante = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Poderdante']);
    $poderdante = Copropietario::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id'   => $userPoderdante->id,
    ]);

    Voto::create([
        'tenant_id'         => $this->tenant->id,
        'votacion_id'       => $votacion->id,
        'copropietario_id'  => $apoderado->id,
        'en_nombre_de'      => $poderdante->id,
        'opcion_id'         => $opcion->id,
        'peso'              => 2.0,
        'hash_verificacion' => hash('sha256', 'delegado-test'),
        'created_at'        => now(),
    ]);

    $csv = app(ReporteService::class)->generarCsvVotos($reunion);

    expect($csv)->toContain('Ana Poderdante');
});

test('ruta csv-votos descarga archivo csv', function () {
    $reunion = Reunion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'estado'     => ReunionEstado::Finalizada,
        'creado_por' => $this->admin->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('reuniones.reporte-csv-votos', $reunion));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
