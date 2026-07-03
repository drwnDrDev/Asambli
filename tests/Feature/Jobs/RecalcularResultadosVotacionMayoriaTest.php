<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RecalcularResultadosVotacion;
use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\OpcionVotacion;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\TipoDecision;
use App\Models\Unidad;
use App\Models\Votacion;
use App\Models\Voto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RecalcularResultadosVotacionMayoriaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Reunion $reunion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app()->instance('current_tenant', $this->tenant);

        $this->reunion = Reunion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'estado'    => 'en_curso',
        ]);
    }

    private function crearCopropietarioConUnidad(float $coeficiente): array
    {
        $copropietario = Copropietario::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $unidad = Unidad::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'copropietario_id' => $copropietario->id,
            'coeficiente'      => $coeficiente,
        ]);
        Asistencia::create([
            'reunion_id'          => $this->reunion->id,
            'copropietario_id'    => $copropietario->id,
            'confirmada_por_admin' => true,
        ]);
        return [$copropietario, $unidad];
    }

    /** @test */
    public function mayoria_simple_aprobada_cuando_mas_del_50_vota_favor(): void
    {
        $tipoDecision = TipoDecision::factory()->create([
            'tipo_mayoria' => 'simple',
            'aplica_en'    => ['asamblea'],
        ]);

        $votacion = Votacion::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'reunion_id'       => $this->reunion->id,
            'tipo_decision_id' => $tipoDecision->id,
            'estado'           => 'cerrada',
        ]);

        $opcionFavor = OpcionVotacion::create([
            'votacion_id' => $votacion->id,
            'orden'       => 1,
            'texto'       => 'Sí',
        ]);
        $opcionContra = OpcionVotacion::create([
            'votacion_id' => $votacion->id,
            'orden'       => 2,
            'texto'       => 'No',
        ]);

        [$cop1] = $this->crearCopropietarioConUnidad(60.0); // vota favor
        [$cop2] = $this->crearCopropietarioConUnidad(40.0); // vota contra

        Voto::create([
            'tenant_id'        => $this->tenant->id,
            'votacion_id'      => $votacion->id,
            'copropietario_id' => $cop1->id,
            'opcion_id'        => $opcionFavor->id,
            'peso'             => 60.0,
            'hash_verificacion' => sha1('cop1'),
        ]);
        Voto::create([
            'tenant_id'        => $this->tenant->id,
            'votacion_id'      => $votacion->id,
            'copropietario_id' => $cop2->id,
            'opcion_id'        => $opcionContra->id,
            'peso'             => 40.0,
            'hash_verificacion' => sha1('cop2'),
        ]);

        Event::fake();
        RecalcularResultadosVotacion::dispatchSync($votacion);

        Event::assertDispatched(\App\Events\ResultadosVotacionActualizados::class, function ($event) {
            return $event->mayoriaData['tipo_mayoria'] === 'simple'
                && $event->mayoriaData['resultado_tentativo'] === 'aprobada'
                && $event->mayoriaData['porcentaje_favor'] === 60.0;
        });
    }

    /** @test */
    public function mayoria_simple_rechazada_cuando_50_o_menos_vota_favor(): void
    {
        $tipoDecision = TipoDecision::factory()->create([
            'tipo_mayoria' => 'simple',
            'aplica_en'    => ['asamblea'],
        ]);

        $votacion = Votacion::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'reunion_id'       => $this->reunion->id,
            'tipo_decision_id' => $tipoDecision->id,
            'estado'           => 'cerrada',
        ]);

        $opcionFavor = OpcionVotacion::create([
            'votacion_id' => $votacion->id,
            'orden'       => 1,
            'texto'       => 'Sí',
        ]);
        $opcionContra = OpcionVotacion::create([
            'votacion_id' => $votacion->id,
            'orden'       => 2,
            'texto'       => 'No',
        ]);

        [$cop1] = $this->crearCopropietarioConUnidad(50.0);
        [$cop2] = $this->crearCopropietarioConUnidad(50.0);

        Voto::create([
            'tenant_id'        => $this->tenant->id,
            'votacion_id'      => $votacion->id,
            'copropietario_id' => $cop1->id,
            'opcion_id'        => $opcionFavor->id,
            'peso'             => 50.0,
            'hash_verificacion' => sha1('cop1b'),
        ]);
        Voto::create([
            'tenant_id'        => $this->tenant->id,
            'votacion_id'      => $votacion->id,
            'copropietario_id' => $cop2->id,
            'opcion_id'        => $opcionContra->id,
            'peso'             => 50.0,
            'hash_verificacion' => sha1('cop2b'),
        ]);

        Event::fake();
        RecalcularResultadosVotacion::dispatchSync($votacion);

        Event::assertDispatched(\App\Events\ResultadosVotacionActualizados::class, function ($event) {
            return $event->mayoriaData['resultado_tentativo'] === 'rechazada';
        });
    }

    /** @test */
    public function mayoria_calificada_70_usa_coeficiente_total_edificio(): void
    {
        // Edificio total = 100 (cop1=60 + cop2=40)
        // Solo cop1 vota favor con peso=60 → 60/100 = 60% < 70% → rechazada
        $tipoDecision = TipoDecision::factory()->create([
            'tipo_mayoria' => 'calificada_70',
            'aplica_en'    => ['asamblea'],
        ]);

        $votacion = Votacion::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'reunion_id'       => $this->reunion->id,
            'tipo_decision_id' => $tipoDecision->id,
            'estado'           => 'cerrada',
        ]);

        $opcionFavor = OpcionVotacion::create([
            'votacion_id' => $votacion->id,
            'orden'       => 1,
            'texto'       => 'Sí',
        ]);

        [$cop1] = $this->crearCopropietarioConUnidad(60.0);
        [$cop2] = $this->crearCopropietarioConUnidad(40.0); // no vota

        Voto::create([
            'tenant_id'        => $this->tenant->id,
            'votacion_id'      => $votacion->id,
            'copropietario_id' => $cop1->id,
            'opcion_id'        => $opcionFavor->id,
            'peso'             => 60.0,
            'hash_verificacion' => sha1('cop1c'),
        ]);

        Event::fake();
        RecalcularResultadosVotacion::dispatchSync($votacion);

        Event::assertDispatched(\App\Events\ResultadosVotacionActualizados::class, function ($event) {
            return $event->mayoriaData['tipo_mayoria'] === 'calificada_70'
                && $event->mayoriaData['total_edificio'] === 100.0
                && $event->mayoriaData['resultado_tentativo'] === 'rechazada'; // 60/100 < 70%
        });
    }

    /** @test */
    public function mayoria_calificada_70_aprobada_cuando_alcanza_70_por_ciento(): void
    {
        $tipoDecision = TipoDecision::factory()->create([
            'tipo_mayoria' => 'calificada_70',
            'aplica_en'    => ['asamblea'],
        ]);

        $votacion = Votacion::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'reunion_id'       => $this->reunion->id,
            'tipo_decision_id' => $tipoDecision->id,
            'estado'           => 'cerrada',
        ]);

        $opcionFavor = OpcionVotacion::create([
            'votacion_id' => $votacion->id,
            'orden'       => 1,
            'texto'       => 'Sí',
        ]);

        [$cop1] = $this->crearCopropietarioConUnidad(70.0); // vota favor
        [$cop2] = $this->crearCopropietarioConUnidad(30.0); // no vota

        Voto::create([
            'tenant_id'        => $this->tenant->id,
            'votacion_id'      => $votacion->id,
            'copropietario_id' => $cop1->id,
            'opcion_id'        => $opcionFavor->id,
            'peso'             => 70.0,
            'hash_verificacion' => sha1('cop1d'),
        ]);

        Event::fake();
        RecalcularResultadosVotacion::dispatchSync($votacion);

        Event::assertDispatched(\App\Events\ResultadosVotacionActualizados::class, function ($event) {
            return $event->mayoriaData['resultado_tentativo'] === 'aprobada'; // 70/100 = 70% ✓
        });
    }

    /** @test */
    public function unanimidad_aprobada_solo_cuando_todos_votan_favor(): void
    {
        $tipoDecision = TipoDecision::factory()->create([
            'tipo_mayoria' => 'unanimidad',
            'aplica_en'    => ['asamblea'],
        ]);

        $votacion = Votacion::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'reunion_id'       => $this->reunion->id,
            'tipo_decision_id' => $tipoDecision->id,
            'estado'           => 'cerrada',
        ]);

        $opcionFavor = OpcionVotacion::create([
            'votacion_id' => $votacion->id,
            'orden'       => 1,
            'texto'       => 'Sí',
        ]);

        [$cop1] = $this->crearCopropietarioConUnidad(50.0);
        [$cop2] = $this->crearCopropietarioConUnidad(50.0);

        Voto::create([
            'tenant_id'        => $this->tenant->id,
            'votacion_id'      => $votacion->id,
            'copropietario_id' => $cop1->id,
            'opcion_id'        => $opcionFavor->id,
            'peso'             => 50.0,
            'hash_verificacion' => sha1('cop1e'),
        ]);
        Voto::create([
            'tenant_id'        => $this->tenant->id,
            'votacion_id'      => $votacion->id,
            'copropietario_id' => $cop2->id,
            'opcion_id'        => $opcionFavor->id,
            'peso'             => 50.0,
            'hash_verificacion' => sha1('cop2e'),
        ]);

        Event::fake();
        RecalcularResultadosVotacion::dispatchSync($votacion);

        Event::assertDispatched(\App\Events\ResultadosVotacionActualizados::class, function ($event) {
            return $event->mayoriaData['tipo_mayoria'] === 'unanimidad'
                && $event->mayoriaData['resultado_tentativo'] === 'aprobada';
        });
    }

    /** @test */
    public function unanimidad_rechazada_si_hay_un_voto_en_contra(): void
    {
        $tipoDecision = TipoDecision::factory()->create([
            'tipo_mayoria' => 'unanimidad',
            'aplica_en'    => ['asamblea'],
        ]);

        $votacion = Votacion::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'reunion_id'       => $this->reunion->id,
            'tipo_decision_id' => $tipoDecision->id,
            'estado'           => 'cerrada',
        ]);

        $opcionFavor = OpcionVotacion::create([
            'votacion_id' => $votacion->id,
            'orden'       => 1,
            'texto'       => 'Sí',
        ]);
        $opcionContra = OpcionVotacion::create([
            'votacion_id' => $votacion->id,
            'orden'       => 2,
            'texto'       => 'No',
        ]);

        [$cop1] = $this->crearCopropietarioConUnidad(80.0);
        [$cop2] = $this->crearCopropietarioConUnidad(20.0);

        Voto::create([
            'tenant_id'        => $this->tenant->id,
            'votacion_id'      => $votacion->id,
            'copropietario_id' => $cop1->id,
            'opcion_id'        => $opcionFavor->id,
            'peso'             => 80.0,
            'hash_verificacion' => sha1('cop1f'),
        ]);
        Voto::create([
            'tenant_id'        => $this->tenant->id,
            'votacion_id'      => $votacion->id,
            'copropietario_id' => $cop2->id,
            'opcion_id'        => $opcionContra->id,
            'peso'             => 20.0,
            'hash_verificacion' => sha1('cop2f'),
        ]);

        Event::fake();
        RecalcularResultadosVotacion::dispatchSync($votacion);

        Event::assertDispatched(\App\Events\ResultadosVotacionActualizados::class, function ($event) {
            return $event->mayoriaData['resultado_tentativo'] === 'rechazada';
        });
    }

    /** @test */
    public function sin_tipo_decision_usa_mayoria_simple_por_defecto(): void
    {
        $votacion = Votacion::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'reunion_id'       => $this->reunion->id,
            'tipo_decision_id' => null,
            'estado'           => 'cerrada',
        ]);

        $opcionFavor = OpcionVotacion::create([
            'votacion_id' => $votacion->id,
            'orden'       => 1,
            'texto'       => 'Sí',
        ]);

        [$cop1] = $this->crearCopropietarioConUnidad(60.0);

        Voto::create([
            'tenant_id'        => $this->tenant->id,
            'votacion_id'      => $votacion->id,
            'copropietario_id' => $cop1->id,
            'opcion_id'        => $opcionFavor->id,
            'peso'             => 60.0,
            'hash_verificacion' => sha1('cop1g'),
        ]);

        Event::fake();
        RecalcularResultadosVotacion::dispatchSync($votacion);

        Event::assertDispatched(\App\Events\ResultadosVotacionActualizados::class, function ($event) {
            return $event->mayoriaData['tipo_mayoria'] === 'simple';
        });
    }

    /** @test */
    public function mayoria_data_incluida_en_broadcast(): void
    {
        $votacion = Votacion::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'reunion_id' => $this->reunion->id,
            'estado'     => 'cerrada',
        ]);

        OpcionVotacion::create([
            'votacion_id' => $votacion->id,
            'orden'       => 1,
            'texto'       => 'Sí',
        ]);

        Event::fake();
        RecalcularResultadosVotacion::dispatchSync($votacion);

        Event::assertDispatched(\App\Events\ResultadosVotacionActualizados::class, function ($event) {
            return isset($event->mayoriaData)
                && isset($event->mayoriaData['tipo_mayoria'])
                && isset($event->mayoriaData['resultado_tentativo']);
        });
    }
}
