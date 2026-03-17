<?php
use App\Events\EstadoReunionCambiado;
use App\Enums\ReunionEstado;
use App\Services\ReunionTransicionService;
use Illuminate\Support\Facades\Event;

it('broadcasts EstadoReunionCambiado after a valid transition', function () {
    Event::fake([EstadoReunionCambiado::class]);

    $tenant = \App\Models\Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $admin = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
    $reunion = \App\Models\Reunion::factory()->create([
        'tenant_id' => $tenant->id,
        'estado'    => ReunionEstado::AnteSala,
    ]);

    $service = new ReunionTransicionService();
    $service->transicionar($reunion, ReunionEstado::EnCurso, $admin, 'Iniciando reunión');

    Event::assertDispatched(EstadoReunionCambiado::class, function ($e) use ($reunion) {
        return $e->reunion->id === $reunion->id
            && $e->estado === ReunionEstado::EnCurso->value;
    });
});

it('does not broadcast if transition is invalid', function () {
    Event::fake([EstadoReunionCambiado::class]);

    $tenant = \App\Models\Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $admin = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
    $reunion = \App\Models\Reunion::factory()->create([
        'tenant_id' => $tenant->id,
        'estado'    => ReunionEstado::AnteSala,
    ]);

    $service = new ReunionTransicionService();

    expect(fn() => $service->transicionar($reunion, ReunionEstado::Finalizada, $admin, 'Forzar fin'))
        ->toThrow(\InvalidArgumentException::class);

    Event::assertNotDispatched(EstadoReunionCambiado::class);
});
