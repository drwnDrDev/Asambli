<?php

use App\Models\Copropietario;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Services\PadronImportService;
use Illuminate\Http\UploadedFile;

test('importa copropietarios desde CSV correctamente', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $csv = "numero,tipo,coeficiente,torre,nombre,email,tipo_documento,numero_documento\n";
    $csv .= "101,apartamento,1.52300,A,Juan Pérez,juan@test.com,CC,11111111\n";
    $csv .= "102,apartamento,1.52300,A,María García,maria@test.com,CC,22222222\n";

    $file = UploadedFile::fake()->createWithContent('padron.csv', $csv);

    $result = app(PadronImportService::class)->importFromFile($file, $tenant);

    expect($result['imported'])->toBe(2);
    expect($result['errors'])->toBeEmpty();
    expect(Copropietario::count())->toBe(2);
    expect(Unidad::count())->toBe(2);
    expect(Unidad::whereNotNull('copropietario_id')->count())->toBe(2);
});

test('importa con tipo y numero de documento', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $csv = "numero,tipo,coeficiente,nombre,email,tipo_documento,numero_documento\n";
    $csv .= "101,apartamento,5.00000,Juan Pérez,juan@test.com,CC,12345678\n";

    $file = UploadedFile::fake()->createWithContent('padron.csv', $csv);

    $result = app(PadronImportService::class)->importFromFile($file, $tenant);

    expect($result['imported'])->toBe(1);
    $copro = Copropietario::first();
    expect($copro->tipo_documento)->toBe('CC');
    expect($copro->numero_documento)->toBe('12345678');
    expect($copro->nombre)->toBe('Juan Pérez');
});

test('rechaza CSV con coeficientes mayores a 100', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $csv = "numero,tipo,coeficiente,torre,nombre,email,tipo_documento,numero_documento\n";
    $csv .= "101,apartamento,60.00000,A,Juan,juan@test.com,CC,11111111\n";
    $csv .= "102,apartamento,60.00000,A,María,maria@test.com,CC,22222222\n";

    $file = UploadedFile::fake()->createWithContent('padron.csv', $csv);

    $result = app(PadronImportService::class)->importFromFile($file, $tenant);

    expect($result['errors'])->not->toBeEmpty();
});

test('copropietario con multiples unidades: 1 copropietario, N unidades', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    // Mismo tipo+documento → mismo copropietario, dos unidades diferentes
    $csv = "numero,tipo,coeficiente,torre,nombre,email,tipo_documento,numero_documento\n";
    $csv .= "101,apartamento,1.00000,A,Juan Pérez,juan@test.com,CC,11111111\n";
    $csv .= "102,apartamento,1.00000,A,Juan Pérez,juan@test.com,CC,11111111\n";

    $file = UploadedFile::fake()->createWithContent('padron.csv', $csv);

    $result = app(PadronImportService::class)->importFromFile($file, $tenant);

    expect($result['imported'])->toBe(2);
    expect($result['errors'])->toBeEmpty();
    expect(Copropietario::count())->toBe(1); // un solo perfil
    expect(Unidad::count())->toBe(2);        // dos unidades
    expect(Unidad::whereNotNull('copropietario_id')->count())->toBe(2);
});

test('crea copropietarios separados para tenants distintos con el mismo documento', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $csv = "numero,tipo,coeficiente,torre,nombre,email,tipo_documento,numero_documento\n";
    $csv .= "101,apartamento,1.00000,A,Juan,juan@test.com,CC,11111111\n";

    app()->instance('current_tenant', $tenantA);
    $fileA = UploadedFile::fake()->createWithContent('padron.csv', $csv);
    app(PadronImportService::class)->importFromFile($fileA, $tenantA);

    app()->instance('current_tenant', $tenantB);
    $fileB = UploadedFile::fake()->createWithContent('padron.csv', $csv);
    $result = app(PadronImportService::class)->importFromFile($fileB, $tenantB);

    // Dos Copropietarios separados, uno por tenant
    expect(\App\Models\Copropietario::withoutGlobalScopes()->count())->toBe(2);
    expect($result['imported'])->toBe(1);
});
