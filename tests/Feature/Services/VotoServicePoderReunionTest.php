<?php

namespace Tests\Feature\Services;

use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Votacion;
use App\Services\VotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica que el VotoService valida el poder contra la reunión correcta,
 * evitando que un poder de reunión B autorice votos en reunión A.
 */
class VotoServicePoderReunionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Reunion $reunionA;
    private Reunion $reunionB;
    private Votacion $votacionA;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['restringir_voto_morosos' => false]);
        app()->instance('current_tenant', $this->tenant);
        $this->adminUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->reunionA = Reunion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'estado'    => 'en_curso',
        ]);
        $this->reunionB = Reunion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'estado'    => 'en_curso',
        ]);

        $this->votacionA = Votacion::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'reunion_id' => $this->reunionA->id,
            'estado'     => 'abierta',
        ]);
        $this->votacionA->opciones()->create(['texto' => 'Sí', 'orden' => 1]);
    }

    private function copropietario(): Copropietario
    {
        $c = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
        Unidad::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'copropietario_id' => $c->id,
            'coeficiente'      => 50.0,
        ]);
        Asistencia::create([
            'reunion_id'           => $this->reunionA->id,
            'copropietario_id'     => $c->id,
            'confirmada_por_admin' => true,
            'hora_confirmacion'    => now(),
        ]);
        return $c;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function poder_de_otra_reunion_no_autoriza_voto_delegado(): void
    {
        $apoderado  = $this->copropietario();
        $poderdante = $this->copropietario();

        // El poder pertenece a reunión B, NO a reunión A
        Poder::withoutEvents(fn () => Poder::create([
            'tenant_id'      => $this->tenant->id,
            'reunion_id'     => $this->reunionB->id,
            'apoderado_id'   => $apoderado->id,
            'poderdante_id'  => $poderdante->id,
            'estado'         => 'aprobado',
            'registrado_por' => $this->adminUser->id,
        ]));

        app()->forgetInstance('current_tenant');
        $result = app(VotoService::class)->votar(
            $this->votacionA, $apoderado, $this->votacionA->opciones->first()->id, request(), $poderdante->id
        );

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->toContain('poder');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function poder_de_la_misma_reunion_si_autoriza_voto_delegado(): void
    {
        $apoderado  = $this->copropietario();
        $poderdante = $this->copropietario();

        // El poder pertenece a reunión A — debe funcionar
        Poder::withoutEvents(fn () => Poder::create([
            'tenant_id'      => $this->tenant->id,
            'reunion_id'     => $this->reunionA->id,
            'apoderado_id'   => $apoderado->id,
            'poderdante_id'  => $poderdante->id,
            'estado'         => 'aprobado',
            'registrado_por' => $this->adminUser->id,
        ]));

        app()->forgetInstance('current_tenant');
        $result = app(VotoService::class)->votar(
            $this->votacionA, $apoderado, $this->votacionA->opciones->first()->id, request(), $poderdante->id
        );

        expect($result['success'])->toBeTrue();
    }
}
