<?php

use App\Models\Reunion;
use App\Models\Tenant;
use App\Services\ReporteService;

test('genera PDF para una reunion finalizada', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create([
        'tenant_id' => $tenant->id,
        'estado' => 'finalizada',
    ]);

    $pdf = app(ReporteService::class)->generarPdf($reunion);

    expect($pdf)->toBeInstanceOf(\Barryvdh\DomPDF\PDF::class);
});

test('genera CSV con datos de asistencia', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => 'finalizada']);

    $csv = app(ReporteService::class)->generarCsvAsistencia($reunion);

    expect($csv)->toContain('unidad,copropietario,coeficiente');
});
