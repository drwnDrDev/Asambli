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

class VotoServiceMoraDelegadoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Reunion $reunion;
    private Votacion $votacion;
    private $opcion;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['restringir_voto_morosos' => true]);
        app()->instance('current_tenant', $this->tenant);
        $this->user = User::factory()->create();

        $this->reunion = Reunion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'estado'    => 'en_curso',
        ]);
        $this->votacion = Votacion::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'reunion_id' => $this->reunion->id,
            'estado'     => 'abierta',
        ]);
        $this->opcion = $this->votacion->opciones()->create(['texto' => 'Sí', 'orden' => 1]);
    }

    private function copropietario(bool $enMora, float $coeficiente = 25.0): Copropietario
    {
        $c = Copropietario::factory()->create([
            'tenant_id' => $this->tenant->id,
            'en_mora'   => $enMora,
        ]);
        Unidad::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'copropietario_id' => $c->id,
            'coeficiente'      => $coeficiente,
        ]);
        Asistencia::create([
            'reunion_id'           => $this->reunion->id,
            'copropietario_id'     => $c->id,
            'confirmada_por_admin' => true,
            'hora_confirmacion'    => now(),
        ]);
        return $c;
    }

    private function darPoder(Copropietario $apoderado, Copropietario $poderdante): void
    {
        Poder::withoutEvents(fn () => Poder::create([
            'tenant_id'     => $this->tenant->id,
            'reunion_id'    => $this->reunion->id,
            'apoderado_id'  => $apoderado->id,
            'poderdante_id' => $poderdante->id,
            'estado'        => 'aprobado',
            'registrado_por' => $this->user->id,
        ]));
    }

    private function votar(Copropietario $quienVota, ?int $enNombreDe = null): array
    {
        // Flujo PIN real: sin current_tenant en el contenedor
        app()->forgetInstance('current_tenant');
        return app(VotoService::class)->votar(
            $this->votacion, $quienVota, $this->opcion->id, request(), $enNombreDe
        );
    }

    /** @test */
    public function voto_delegado_de_poderdante_moroso_es_bloqueado(): void
    {
        $apoderado  = $this->copropietario(enMora: false);
        $poderdante = $this->copropietario(enMora: true);
        $this->darPoder($apoderado, $poderdante);

        $result = $this->votar($apoderado, enNombreDe: $poderdante->id);

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->toContain('mora');
    }

    /** @test */
    public function apoderado_moroso_si_puede_votar_por_poderdante_al_dia(): void
    {
        $apoderado  = $this->copropietario(enMora: true);
        $poderdante = $this->copropietario(enMora: false);
        $this->darPoder($apoderado, $poderdante);

        $result = $this->votar($apoderado, enNombreDe: $poderdante->id);

        expect($result['success'])->toBeTrue();
    }

    /** @test */
    public function apoderado_moroso_sigue_sin_poder_votar_por_si_mismo(): void
    {
        $moroso = $this->copropietario(enMora: true);

        $result = $this->votar($moroso);

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->toContain('mora');
    }

    /** @test */
    public function con_restriccion_desactivada_todo_se_permite(): void
    {
        $this->tenant->update(['restringir_voto_morosos' => false]);
        $apoderado  = $this->copropietario(enMora: true);
        $poderdante = $this->copropietario(enMora: true);
        $this->darPoder($apoderado, $poderdante);

        $result = $this->votar($apoderado, enNombreDe: $poderdante->id);

        expect($result['success'])->toBeTrue();
    }
}
