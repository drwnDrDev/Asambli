<?php

namespace Tests\Feature\Services;

use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\Votacion;
use App\Services\VotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VotoServiceMoraTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Copropietario $copropietario;
    private Reunion $reunion;
    private Votacion $votacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'restringir_voto_morosos' => true,
        ]);
        app()->instance('current_tenant', $this->tenant);

        $this->copropietario = Copropietario::factory()->create([
            'tenant_id' => $this->tenant->id,
            'en_mora'   => false,
        ]);

        Unidad::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'copropietario_id' => $this->copropietario->id,
            'coeficiente'      => 10.00,
        ]);

        $this->reunion = Reunion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'estado'    => 'en_curso',
        ]);

        Asistencia::create([
            'reunion_id'           => $this->reunion->id,
            'copropietario_id'     => $this->copropietario->id,
            'confirmada_por_admin' => true,
            'hora_confirmacion'    => now(),
        ]);

        $this->votacion = Votacion::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'reunion_id' => $this->reunion->id,
            'estado'     => 'abierta',
        ]);
    }

    /** @test */
    public function moroso_con_restriccion_activa_no_puede_votar(): void
    {
        $this->copropietario->update(['en_mora' => true]);
        $this->tenant->update(['restringir_voto_morosos' => true]);

        $opcion = $this->votacion->opciones()->create(['texto' => 'Sí', 'orden' => 1]);

        $service = app(VotoService::class);
        $result  = $service->votar(
            $this->votacion,
            $this->copropietario,
            $opcion->id,
            request()
        );

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->toContain('mora');
    }

    /** @test */
    public function moroso_con_restriccion_desactivada_puede_votar(): void
    {
        $this->copropietario->update(['en_mora' => true]);
        $this->tenant->update(['restringir_voto_morosos' => false]);

        $opcion = $this->votacion->opciones()->create(['texto' => 'Sí', 'orden' => 1]);

        $service = app(VotoService::class);
        $result  = $service->votar(
            $this->votacion,
            $this->copropietario,
            $opcion->id,
            request()
        );

        expect($result['success'])->toBeTrue();
    }

    /** @test */
    public function no_moroso_con_restriccion_activa_puede_votar(): void
    {
        $this->copropietario->update(['en_mora' => false]);
        $this->tenant->update(['restringir_voto_morosos' => true]);

        $opcion = $this->votacion->opciones()->create(['texto' => 'Sí', 'orden' => 1]);

        $service = app(VotoService::class);
        $result  = $service->votar(
            $this->votacion,
            $this->copropietario,
            $opcion->id,
            request()
        );

        expect($result['success'])->toBeTrue();
    }

    /** @test */
    public function restriccion_mora_aplica_independientemente_del_tipo_votacion(): void
    {
        // El bloqueo es para TODAS las votaciones (Art. 38 Ley 675),
        // no solo para votaciones de cuotas.
        $this->copropietario->update(['en_mora' => true]);
        $this->tenant->update(['restringir_voto_morosos' => true]);

        // Crear otro tipo de votación (reformas, etc.)
        $otraVotacion = Votacion::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'reunion_id' => $this->reunion->id,
            'estado'     => 'abierta',
            'pregunta'   => '¿Aprobar reforma al reglamento?',
        ]);
        $otraOpcion = $otraVotacion->opciones()->create(['texto' => 'Sí', 'orden' => 1]);

        $service = app(VotoService::class);
        $result  = $service->votar(
            $otraVotacion,
            $this->copropietario,
            $otraOpcion->id,
            request()
        );

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->toContain('mora');
    }
}
