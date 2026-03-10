<?php

use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\Votacion;
use App\Models\Voto;
use App\Services\VotoService;

function setupVotoContext(): array
{
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create([
        'tenant_id' => $tenant->id,
        'estado' => 'en_curso',
        'tipo_voto_peso' => 'coeficiente',
        'quorum_requerido' => 1.0,
    ]);

    $copropietario = Copropietario::factory()->create(['tenant_id' => $tenant->id]);
    $unidad = Unidad::factory()->create(['tenant_id' => $tenant->id, 'copropietario_id' => $copropietario->id, 'coeficiente' => 100.0]);

    Asistencia::create([
        'reunion_id' => $reunion->id,
        'copropietario_id' => $copropietario->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion' => now(),
    ]);

    $votacion = Votacion::factory()->create([
        'tenant_id' => $tenant->id,
        'reunion_id' => $reunion->id,
        'estado' => 'abierta',
        'tipo' => 'si_no',
    ]);

    return compact('tenant', 'reunion', 'unidad', 'copropietario', 'votacion');
}

test('copropietario puede votar exitosamente', function () {
    $data = setupVotoContext();
    $opcion = $data['votacion']->opciones()->create(['texto' => 'Sí', 'orden' => 1]);

    $service = app(VotoService::class);
    $result = $service->votar(
        votacion: $data['votacion'],
        copropietario: $data['copropietario'],
        opcionId: $opcion->id,
        request: request()
    );

    expect($result['success'])->toBeTrue();
    expect(Voto::withoutGlobalScopes()->count())->toBe(1);
});

test('no se puede votar dos veces', function () {
    $data = setupVotoContext();
    $opcion = $data['votacion']->opciones()->create(['texto' => 'Sí', 'orden' => 1]);

    $service = app(VotoService::class);
    $service->votar($data['votacion'], $data['copropietario'], $opcion->id, request());

    $result = $service->votar($data['votacion'], $data['copropietario'], $opcion->id, request());

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('ya votó');
});

test('no se puede votar si la votacion esta cerrada', function () {
    $data = setupVotoContext();
    $data['votacion']->update(['estado' => 'cerrada']);
    $opcion = $data['votacion']->opciones()->create(['texto' => 'Sí', 'orden' => 1]);

    $result = app(VotoService::class)->votar(
        $data['votacion'], $data['copropietario'], $opcion->id, request()
    );

    expect($result['success'])->toBeFalse();
});
