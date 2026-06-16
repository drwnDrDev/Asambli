<?php

namespace Tests\Feature\Controllers\Admin;

use App\Models\Copropietario;
use App\Models\OpcionVotacion;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\TipoDecision;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Votacion;
use App\Models\Voto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VotacionControllerCerrarTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $adminUser;
    private Reunion $reunion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app()->instance('current_tenant', $this->tenant);

        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rol'       => 'administrador',
        ]);

        $this->reunion = Reunion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'estado'    => 'en_curso',
        ]);
    }

    private function crearVotacionConVotos(string $tipoMayoria, float $pesoFavor, float $pesoContra): Votacion
    {
        $tipoDecision = TipoDecision::factory()->create([
            'tipo_mayoria' => $tipoMayoria,
        ]);

        $votacion = Votacion::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'reunion_id'       => $this->reunion->id,
            'tipo_decision_id' => $tipoDecision->id,
            'estado'           => 'abierta',
            'resultado'        => 'pendiente',
        ]);

        $opcionFavor = OpcionVotacion::create([
            'votacion_id' => $votacion->id,
            'texto'       => 'Sí',
            'orden'       => 1,
        ]);
        $opcionContra = OpcionVotacion::create([
            'votacion_id' => $votacion->id,
            'texto'       => 'No',
            'orden'       => 2,
        ]);

        if ($pesoFavor > 0) {
            $cop = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
            Unidad::factory()->create([
                'tenant_id'        => $this->tenant->id,
                'copropietario_id' => $cop->id,
                'coeficiente'      => $pesoFavor,
            ]);
            Voto::create([
                'tenant_id'         => $this->tenant->id,
                'votacion_id'       => $votacion->id,
                'copropietario_id'  => $cop->id,
                'opcion_id'         => $opcionFavor->id,
                'peso'              => $pesoFavor,
                'hash_verificacion' => sha1('favor-' . $votacion->id),
            ]);
        }

        if ($pesoContra > 0) {
            $cop = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
            Unidad::factory()->create([
                'tenant_id'        => $this->tenant->id,
                'copropietario_id' => $cop->id,
                'coeficiente'      => $pesoContra,
            ]);
            Voto::create([
                'tenant_id'         => $this->tenant->id,
                'votacion_id'       => $votacion->id,
                'copropietario_id'  => $cop->id,
                'opcion_id'         => $opcionContra->id,
                'peso'              => $pesoContra,
                'hash_verificacion' => sha1('contra-' . $votacion->id),
            ]);
        }

        return $votacion;
    }

    /** @test */
    public function cerrar_persiste_resultado_aprobada_con_mayoria_simple(): void
    {
        $votacion = $this->crearVotacionConVotos('simple', pesoFavor: 60.0, pesoContra: 40.0);

        $this->actingAs($this->adminUser)
            ->post("/admin/votaciones/{$votacion->id}/cerrar")
            ->assertRedirect();

        $this->assertDatabaseHas('votaciones', [
            'id'        => $votacion->id,
            'estado'    => 'cerrada',
            'resultado' => 'aprobada',
        ]);
    }

    /** @test */
    public function cerrar_persiste_resultado_rechazada_con_mayoria_simple(): void
    {
        $votacion = $this->crearVotacionConVotos('simple', pesoFavor: 40.0, pesoContra: 60.0);

        $this->actingAs($this->adminUser)
            ->post("/admin/votaciones/{$votacion->id}/cerrar")
            ->assertRedirect();

        $this->assertDatabaseHas('votaciones', [
            'id'        => $votacion->id,
            'estado'    => 'cerrada',
            'resultado' => 'rechazada',
        ]);
    }

    /** @test */
    public function cerrar_persiste_resultado_rechazada_con_calificada_70_insuficiente(): void
    {
        // Edificio total = 100 (60 + 40), favor = 60 → 60% < 70% → rechazada
        $votacion = $this->crearVotacionConVotos('calificada_70', pesoFavor: 60.0, pesoContra: 40.0);

        $this->actingAs($this->adminUser)
            ->post("/admin/votaciones/{$votacion->id}/cerrar")
            ->assertRedirect();

        $this->assertDatabaseHas('votaciones', [
            'id'        => $votacion->id,
            'resultado' => 'rechazada',
        ]);
    }
}
