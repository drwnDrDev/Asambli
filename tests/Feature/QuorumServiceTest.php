<?php

use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Services\QuorumService;

test('quorum se calcula por coeficiente para asambleas', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create(['tipo_voto_peso' => 'coeficiente', 'quorum_requerido' => 50.00]);

    $c1 = Copropietario::factory()->create();
    $c2 = Copropietario::factory()->create();

    Unidad::factory()->create(['copropietario_id' => $c1->id, 'coeficiente' => 30.00000]);
    Unidad::factory()->create(['copropietario_id' => $c2->id, 'coeficiente' => 70.00000]);

    // Solo c1 está presente
    Asistencia::create([
        'reunion_id' => $reunion->id,
        'copropietario_id' => $c1->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion' => now(),
    ]);

    $result = app(QuorumService::class)->calcular($reunion);

    expect($result['porcentaje_presente'])->toBe(30.0);
    expect($result['tiene_quorum'])->toBeFalse();
});

test('quorum se alcanza con suficiente coeficiente', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create(['tipo_voto_peso' => 'coeficiente', 'quorum_requerido' => 50.00]);

    $c1 = Copropietario::factory()->create();
    Unidad::factory()->create(['copropietario_id' => $c1->id, 'coeficiente' => 60.00000]);

    Asistencia::create([
        'reunion_id' => $reunion->id,
        'copropietario_id' => $c1->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion' => now(),
    ]);

    $result = app(QuorumService::class)->calcular($reunion);

    expect($result['tiene_quorum'])->toBeTrue();
});

test('copropietario con multiple unidades suma todo su coeficiente', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create(['tipo_voto_peso' => 'coeficiente', 'quorum_requerido' => 50.00]);

    $c1 = Copropietario::factory()->create();
    Unidad::factory()->create(['copropietario_id' => $c1->id, 'coeficiente' => 30.00000]);
    Unidad::factory()->create(['copropietario_id' => $c1->id, 'coeficiente' => 25.00000]);
    // total c1 = 55%

    Asistencia::create([
        'reunion_id' => $reunion->id,
        'copropietario_id' => $c1->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion' => now(),
    ]);

    $result = app(QuorumService::class)->calcular($reunion);

    expect($result['tiene_quorum'])->toBeTrue();
    expect($result['presente'])->toBe(55.0);
});
