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

    $copro1 = Copropietario::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user1->id, 'activo' => true]);
    $copro2 = Copropietario::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user2->id, 'activo' => true]);

    Unidad::factory()->create(['tenant_id' => $tenant->id, 'copropietario_id' => $copro1->id]);
    Unidad::factory()->create(['tenant_id' => $tenant->id, 'copropietario_id' => $copro2->id]);

    app(ConvocatoriaService::class)->enviar($reunion, $admin);

    Notification::assertSentTo([$user1, $user2], \App\Notifications\ConvocatoriaReunion::class);
    expect($reunion->fresh()->convocatoria_enviada_at)->not->toBeNull();
});
