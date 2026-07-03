<?php

namespace Tests\Feature\Services;

use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;
use App\Services\QuorumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuorumServiceDedupTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Reunion $reunion;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        app()->instance('current_tenant', $this->tenant);
        $this->user = User::factory()->create();
        $this->reunion = Reunion::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'estado'         => 'en_curso',
            'tipo_voto_peso' => 'coeficiente',
        ]);
    }

    private function copropietarioConUnidad(float $coeficiente): Copropietario
    {
        $c = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
        Unidad::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'copropietario_id' => $c->id,
            'coeficiente'      => $coeficiente,
        ]);
        return $c;
    }

    private function confirmarAsistencia(Copropietario $c): void
    {
        Asistencia::create([
            'reunion_id'           => $this->reunion->id,
            'copropietario_id'     => $c->id,
            'confirmada_por_admin' => true,
            'hora_confirmacion'    => now(),
        ]);
    }

    private function crearPoder(Copropietario $apoderado, Copropietario $poderdante, ?int $reunionId = null): void
    {
        Poder::withoutEvents(fn () => Poder::create([
            'tenant_id'     => $this->tenant->id,
            'reunion_id'    => $reunionId ?? $this->reunion->id,
            'apoderado_id'  => $apoderado->id,
            'poderdante_id' => $poderdante->id,
            'estado'        => 'aprobado',
            'registrado_por' => $this->user->id,
        ]));
    }

    /** @test */
    public function poderdante_con_asistencia_propia_no_se_cuenta_dos_veces(): void
    {
        // Escenario real: el apoderado entra a la sala y el sistema registra
        // asistencia para él Y para su poderdante. El poderdante NO debe
        // sumarse otra vez como delegado.
        $apoderado  = $this->copropietarioConUnidad(40.0);
        $poderdante = $this->copropietarioConUnidad(30.0);
        $this->copropietarioConUnidad(30.0); // ausente — completa 100

        $this->crearPoder($apoderado, $poderdante);
        $this->confirmarAsistencia($apoderado);
        $this->confirmarAsistencia($poderdante); // auto-registrada al entrar el apoderado

        $quorum = app(QuorumService::class)->calcular($this->reunion);

        expect($quorum['presente'])->toBe(70.0)
            ->and($quorum['porcentaje_presente'])->toBe(70.0);
    }

    /** @test */
    public function poderdante_sin_asistencia_propia_se_cuenta_una_vez_como_delegado(): void
    {
        $apoderado  = $this->copropietarioConUnidad(40.0);
        $poderdante = $this->copropietarioConUnidad(30.0);
        $this->copropietarioConUnidad(30.0); // ausente

        $this->crearPoder($apoderado, $poderdante);
        $this->confirmarAsistencia($apoderado); // solo el apoderado tiene asistencia

        $quorum = app(QuorumService::class)->calcular($this->reunion);

        expect($quorum['presente'])->toBe(70.0);
    }

    /** @test */
    public function poder_de_otra_reunion_no_contamina_el_quorum(): void
    {
        $apoderado  = $this->copropietarioConUnidad(40.0);
        $poderdante = $this->copropietarioConUnidad(30.0);
        $this->copropietarioConUnidad(30.0);

        $otraReunion = Reunion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'estado'    => 'finalizada',
        ]);
        $this->crearPoder($apoderado, $poderdante, $otraReunion->id);
        $this->confirmarAsistencia($apoderado);

        $quorum = app(QuorumService::class)->calcular($this->reunion);

        expect($quorum['presente'])->toBe(40.0);
    }

    /** @test */
    public function variante_por_unidad_tambien_deduplica(): void
    {
        $this->reunion->update(['tipo_voto_peso' => 'unidad']);

        $apoderado  = $this->copropietarioConUnidad(40.0);
        $poderdante = $this->copropietarioConUnidad(30.0);
        $this->copropietarioConUnidad(30.0);

        $this->crearPoder($apoderado, $poderdante);
        $this->confirmarAsistencia($apoderado);
        $this->confirmarAsistencia($poderdante);

        $quorum = app(QuorumService::class)->calcular($this->reunion);

        // 2 de 3 copropietarios presentes — no 3 de 3
        expect($quorum['presente'])->toBe(2);
    }

    /** @test */
    public function presencia_coeficiente_funciona_aunque_la_reunion_pese_por_unidad(): void
    {
        $this->reunion->update(['tipo_voto_peso' => 'unidad']);

        $apoderado = $this->copropietarioConUnidad(40.0);
        $this->copropietarioConUnidad(60.0);
        $this->confirmarAsistencia($apoderado);

        $presencia = app(QuorumService::class)->presenciaCoeficiente($this->reunion);

        expect($presencia['total'])->toBe(100.0)
            ->and($presencia['presente'])->toBe(40.0)
            ->and($presencia['porcentaje'])->toBe(40.0);
    }
}
