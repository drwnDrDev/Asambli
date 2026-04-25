<?php

use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;
use App\Notifications\AccesoReunionNotification;
use App\Services\ConvocatoriaService;
use Illuminate\Support\Facades\Notification;

test('convocatoria envia notificacion a todos los copropietarios activos', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);
    $reunion = Reunion::factory()->create(['tenant_id' => $tenant->id, 'creado_por' => $admin->id]);

    $copro1 = Copropietario::factory()->create(['tenant_id' => $tenant->id, 'activo' => true, 'email' => 'c1@test.com']);
    $copro2 = Copropietario::factory()->create(['tenant_id' => $tenant->id, 'activo' => true, 'email' => 'c2@test.com']);

    Unidad::factory()->create(['tenant_id' => $tenant->id, 'copropietario_id' => $copro1->id]);
    Unidad::factory()->create(['tenant_id' => $tenant->id, 'copropietario_id' => $copro2->id]);

    app(ConvocatoriaService::class)->enviar($reunion);

    Notification::assertSentTo([$copro1, $copro2], AccesoReunionNotification::class);
    expect($reunion->fresh()->convocatoria_envios)->toBe(1);
});
