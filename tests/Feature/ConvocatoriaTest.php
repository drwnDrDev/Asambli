<?php

use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;
use App\Services\ConvocatoriaService;
use Illuminate\Support\Facades\Notification;

test('convocatoria envia notificacion a todos los copropietarios activos', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);
    $reunion = Reunion::factory()->create(['tenant_id' => $tenant->id, 'creado_por' => $admin->id]);

    $user1 = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    $user2 = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);

    $unidad1 = Unidad::factory()->create();
    $unidad2 = Unidad::factory()->create();

    Copropietario::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user1->id, 'unidad_id' => $unidad1->id, 'activo' => true]);
    Copropietario::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user2->id, 'unidad_id' => $unidad2->id, 'activo' => true]);

    app(ConvocatoriaService::class)->enviar($reunion, $admin);

    Notification::assertSentTo([$user1, $user2], \App\Notifications\ConvocatoriaReunion::class);
    expect($reunion->fresh()->convocatoria_enviada_at)->not->toBeNull();
});
