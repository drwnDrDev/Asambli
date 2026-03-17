<?php
use App\Events\ResultadosPublicosVotacion;
use App\Events\ResultadosVotacionActualizados;
use App\Jobs\RecalcularResultadosVotacion;
use Illuminate\Support\Facades\Event;

it('dispatches both ResultadosVotacionActualizados (private) and ResultadosPublicosVotacion (public)', function () {
    Event::fake([ResultadosVotacionActualizados::class, ResultadosPublicosVotacion::class]);

    $tenant = \App\Models\Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = \App\Models\Reunion::factory()->create(['tenant_id' => $tenant->id]);
    $votacion = \App\Models\Votacion::factory()->create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id]);
    // OpcionVotacionFactory does not exist — create directly (OpcionVotacion has no tenant_id)
    \App\Models\OpcionVotacion::create(['votacion_id' => $votacion->id, 'texto' => 'SÍ', 'orden' => 1]);

    (new RecalcularResultadosVotacion($votacion->id))->handle();

    Event::assertDispatched(ResultadosVotacionActualizados::class);
    Event::assertDispatched(ResultadosPublicosVotacion::class, function ($e) use ($votacion) {
        return $e->votacion->id === $votacion->id
            && isset($e->resultados)
            && !isset($e->resultados[0]['ultimo_voto_unidad']);
    });
});
