<?php

use App\Models\AccesoReunion;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Services\PoderService;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('genera acceso_reunion para el apoderado externo al aprobar un poder', function () {
    Notification::fake();

    $reunion = Reunion::factory()->create();
    app()->instance('current_tenant', \App\Models\Tenant::find($reunion->tenant_id));

    $apoderado = Copropietario::factory()->create([
        'tenant_id'  => $reunion->tenant_id,
        'es_externo' => true,
        'email'      => 'delegado@example.com',
    ]);

    $poder = Poder::factory()->create([
        'tenant_id'    => $reunion->tenant_id,
        'reunion_id'   => $reunion->id,
        'apoderado_id' => $apoderado->id,
        'estado'       => 'pendiente',
    ]);

    app(PoderService::class)->aprobar($poder, reunionId: $reunion->id);

    expect(AccesoReunion::where('copropietario_id', $apoderado->id)
        ->where('reunion_id', $reunion->id)
        ->where('activo', true)
        ->exists()
    )->toBeTrue();

    Notification::assertSentTo($apoderado, \App\Notifications\AccesoDelegadoNotification::class);
});

it('desactiva el acceso del apoderado al revocar el poder', function () {
    $reunion = Reunion::factory()->create();
    app()->instance('current_tenant', \App\Models\Tenant::find($reunion->tenant_id));

    $apoderado = Copropietario::factory()->create([
        'tenant_id'  => $reunion->tenant_id,
        'es_externo' => true,
    ]);

    $poder = Poder::factory()->create([
        'tenant_id'    => $reunion->tenant_id,
        'reunion_id'   => $reunion->id,
        'apoderado_id' => $apoderado->id,
        'estado'       => 'aprobado',
    ]);

    AccesoReunion::factory()->create([
        'copropietario_id' => $apoderado->id,
        'reunion_id'       => $reunion->id,
        'activo'           => true,
    ]);

    app(PoderService::class)->revocar($poder);

    expect(AccesoReunion::where('copropietario_id', $apoderado->id)
        ->where('reunion_id', $reunion->id)
        ->first()->activo
    )->toBeFalse();
});
