<?php

use App\Models\Tenant;
use App\Services\PadronImportService;

test('importa copropietarios desde CSV correctamente', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $csv = "numero,tipo,coeficiente,torre,nombre,email\n";
    $csv .= "101,apartamento,1.52300,A,Juan Pérez,juan@test.com\n";
    $csv .= "102,apartamento,1.52300,A,María García,maria@test.com\n";

    $service = app(PadronImportService::class);
    $result = $service->importFromString($csv, $tenant);

    expect($result['imported'])->toBe(2);
    expect($result['errors'])->toBeEmpty();
});

test('rechaza CSV con coeficientes mayores a 100', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $csv = "numero,tipo,coeficiente,torre,nombre,email\n";
    $csv .= "101,apartamento,60.00000,A,Juan,juan@test.com\n";
    $csv .= "102,apartamento,60.00000,A,María,maria@test.com\n";

    $service = app(PadronImportService::class);
    $result = $service->importFromString($csv, $tenant);

    expect($result['errors'])->not->toBeEmpty();
});
