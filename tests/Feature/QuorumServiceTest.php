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

    $u1 = Unidad::factory()->create(['coeficiente' => 30.00000]);
    $u2 = Unidad::factory()->create(['coeficiente' => 70.00000]);

    $c1 = Copropietario::factory()->create(['unidad_id' => $u1->id]);
    Copropietario::factory()->create(['unidad_id' => $u2->id]);

    // Solo c1 está presente
    Asistencia::create([
        'reunion_id' => $reunion->id,
        'copropietario_id' => $c1->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion' => now(),
    ]);

    $service = app(QuorumService::class);
    $result = $service->calcular($reunion);

    expect($result['porcentaje_presente'])->toBe(30.0);
    expect($result['tiene_quorum'])->toBeFalse();
});

test('quorum se alcanza con suficiente coeficiente', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create(['tipo_voto_peso' => 'coeficiente', 'quorum_requerido' => 50.00]);

    $u1 = Unidad::factory()->create(['coeficiente' => 60.00000]);
    $c1 = Copropietario::factory()->create(['unidad_id' => $u1->id]);

    Asistencia::create([
        'reunion_id' => $reunion->id,
        'copropietario_id' => $c1->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion' => now(),
    ]);

    $result = app(QuorumService::class)->calcular($reunion);

    expect($result['tiene_quorum'])->toBeTrue();
});
