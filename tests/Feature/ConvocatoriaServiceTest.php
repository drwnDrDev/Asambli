<?php

use App\Models\AccesoReunion;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Services\ConvocatoriaService;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('genera acceso_reunion con PIN para cada copropietario activo', function () {
    $tenant = Tenant::factory()->create();
    $reunion = Reunion::factory()->for($tenant)->create(['convocatoria_envios' => 0]);
    Copropietario::factory(3)->create([
        'tenant_id' => $tenant->id,
        'activo'    => true,
        'email'     => fn() => fake()->email(),
    ]);

    app()->instance('current_tenant', $tenant);
    Notification::fake();

    app(ConvocatoriaService::class)->enviar($reunion);

    expect(AccesoReunion::where('reunion_id', $reunion->id)->count())->toBe(3);
    $reunion->refresh();
    expect($reunion->convocatoria_envios)->toBe(1);
});

it('no permite más de 2 envíos', function () {
    $tenant = Tenant::factory()->create();
    $reunion = Reunion::factory()->for($tenant)->create(['convocatoria_envios' => 2]);

    app()->instance('current_tenant', $tenant);

    expect(fn() => app(ConvocatoriaService::class)->enviar($reunion))
        ->toThrow(\RuntimeException::class, 'límite de convocatorias');
});

it('regenera PINs en el segundo envío sin duplicar registros', function () {
    $tenant = Tenant::factory()->create();
    $reunion = Reunion::factory()->for($tenant)->create(['convocatoria_envios' => 1]);
    $copropietario = Copropietario::factory()->create([
        'tenant_id' => $tenant->id,
        'activo'    => true,
        'email'     => fake()->email(),
    ]);
    $accesoOriginal = AccesoReunion::factory()->create([
        'copropietario_id' => $copropietario->id,
        'reunion_id'       => $reunion->id,
        'pin_hash'         => password_hash('111111', PASSWORD_BCRYPT),
    ]);

    app()->instance('current_tenant', $tenant);
    Notification::fake();

    app(ConvocatoriaService::class)->enviar($reunion);

    expect(AccesoReunion::where('reunion_id', $reunion->id)->count())->toBe(1);
    $accesoOriginal->refresh();
    expect($accesoOriginal->verificarPin('111111'))->toBeFalse(); // PIN regenerado
});
