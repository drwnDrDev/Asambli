# Fixes de Pruebas de Usuario — Fase Compliance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corregir los 11 hallazgos de pruebas de usuario de la fase compliance: doble conteo de quórum, huecos legales de voto delegado y mayorías especiales, captura de quórum para reuniones virtuales, y lote de UI/config.

**Architecture:** `QuorumService` gana un método público `presenciaCoeficiente()` deduplicado que usan el widget, el bloqueo de apertura y los snapshots. `VotoService` evalúa mora del poderdante en votos delegados. Nueva tabla `asistencia_eventos` + columnas JSON `quorum_apertura`/`quorum_cierre` en `votaciones` para el acta.

**Tech Stack:** Laravel 12, PHP 8.5, Pest, React 18, Inertia.js, Tailwind CSS

**Spec:** `docs/superpowers/specs/2026-07-02-fixes-pruebas-usuario-compliance-design.md`

## Global Constraints

- Comandos dentro del contenedor: `./sail artisan test --no-coverage`, `./sail npm run build`.
- Commits sin línea `Co-Authored-By` (preferencia del proyecto).
- Suite completa verde antes de cada commit.
- Tests de flujos copropietario deben simular el flujo PIN **sin** `current_tenant` en el contenedor (`app()->forgetInstance('current_tenant')` antes del request/llamada) — este binding manual enmascaró bugs reales.
- El bloqueo de apertura por mayorías especiales **NO** se exime con `BYPASS_QUORUM`.
- Relación `Copropietario→unidades` es hasMany (plural); nunca usar `->unidad`.
- Frontend: verificar compilación con `./sail npm run build` al final de cada task que toque JSX.

---

### Task 1: QuorumService — deduplicar poderdantes y filtrar por reunión

**Files:**
- Modify: `app/Services/QuorumService.php`
- Test: `tests/Feature/Services/QuorumServiceDedupTest.php`

**Interfaces:**
- Produces: `QuorumService::presenciaCoeficiente(Reunion $reunion): array` con claves `total` (float), `presente` (float), `porcentaje` (float, 2 decimales). Tasks 5 y 8 dependen de este método.
- `calcular(Reunion): array` conserva su contrato actual (claves `tipo`, `total`, `presente`, `porcentaje_presente`, `quorum_requerido`, `tiene_quorum`).

- [x] **Step 1: Escribir tests que fallan**

Crear `tests/Feature/Services/QuorumServiceDedupTest.php`:

```php
<?php

namespace Tests\Feature\Services;

use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Services\QuorumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuorumServiceDedupTest extends TestCase
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
        Poder::create([
            'tenant_id'     => $this->tenant->id,
            'reunion_id'    => $reunionId ?? $this->reunion->id,
            'apoderado_id'  => $apoderado->id,
            'poderdante_id' => $poderdante->id,
            'estado'        => 'aprobado',
        ]);
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
```

- [x] **Step 2: Correr para confirmar que fallan**

Run: `./sail artisan test tests/Feature/Services/QuorumServiceDedupTest.php --no-coverage`
Expected: FAIL — `poderdante_con_asistencia_propia...` espera 70.0 pero recibe 100.0 (doble conteo); `presencia_coeficiente...` falla con método inexistente. Nota: si `Poder::create` falla por un hook `booted()` del modelo, envolver en `Poder::withoutEvents(fn () => Poder::create([...]))` dentro del helper `crearPoder`.

- [x] **Step 3: Implementar en QuorumService**

Reemplazar `calcularPorCoeficiente()` completo y agregar el método público, en `app/Services/QuorumService.php`:

```php
    /**
     * Presencia SIEMPRE por coeficiente (Arts. 45/46 Ley 675 hablan de
     * coeficientes), independiente del tipo_voto_peso de la reunión.
     * Deduplica poderdantes que ya tienen asistencia propia.
     */
    public function presenciaCoeficiente(Reunion $reunion): array
    {
        $totalCoeficiente = (float) Unidad::withoutGlobalScopes()
            ->where('tenant_id', $reunion->tenant_id)
            ->where('activo', true)
            ->sum('coeficiente');

        $presenteIds = Asistencia::where('reunion_id', $reunion->id)
            ->where('confirmada_por_admin', true)
            ->pluck('copropietario_id');

        $coeficientePresente = (float) Unidad::withoutGlobalScopes()
            ->whereIn('copropietario_id', $presenteIds)
            ->sum('coeficiente');

        // Poderdantes representados que NO tienen asistencia propia
        // (los que sí la tienen ya se contaron arriba — evita doble conteo)
        $coeficienteDelegados = 0.0;
        if ($presenteIds->isNotEmpty()) {
            $poderdanteIds = Poder::withoutGlobalScopes()
                ->where('reunion_id', $reunion->id)
                ->where('estado', 'aprobado')
                ->whereIn('apoderado_id', $presenteIds)
                ->whereNotIn('poderdante_id', $presenteIds)
                ->pluck('poderdante_id');

            if ($poderdanteIds->isNotEmpty()) {
                $coeficienteDelegados = (float) Unidad::withoutGlobalScopes()
                    ->whereIn('copropietario_id', $poderdanteIds)
                    ->sum('coeficiente');
            }
        }

        $presente = $coeficientePresente + $coeficienteDelegados;

        return [
            'total'      => $totalCoeficiente,
            'presente'   => $presente,
            'porcentaje' => $totalCoeficiente > 0
                ? round(($presente / $totalCoeficiente) * 100, 2)
                : 0.0,
        ];
    }

    private function calcularPorCoeficiente(Reunion $reunion): array
    {
        $presencia = $this->presenciaCoeficiente($reunion);

        return [
            'tipo'                => 'coeficiente',
            'total'               => $presencia['total'],
            'presente'            => $presencia['presente'],
            'porcentaje_presente' => $presencia['porcentaje'],
            'quorum_requerido'    => (float) $reunion->quorum_requerido,
            'tiene_quorum'        => $presencia['porcentaje'] >= $reunion->quorum_requerido,
        ];
    }
```

En `calcularPorUnidad()`, reemplazar el bloque de poderdantes:

```php
        // Poderdantes representados por asistentes (Ley 675)
        $poderdantesRepresentados = 0;
        if ($presenteIds->isNotEmpty()) {
            $poderdantesRepresentados = Poder::withoutGlobalScopes()
                ->where('estado', 'aprobado')
                ->whereIn('apoderado_id', $presenteIds)
                ->count();
        }
```

por:

```php
        // Poderdantes representados sin asistencia propia (evita doble conteo)
        $poderdantesRepresentados = 0;
        if ($presenteIds->isNotEmpty()) {
            $poderdantesRepresentados = Poder::withoutGlobalScopes()
                ->where('reunion_id', $reunion->id)
                ->where('estado', 'aprobado')
                ->whereIn('apoderado_id', $presenteIds)
                ->whereNotIn('poderdante_id', $presenteIds)
                ->count();
        }
```

- [x] **Step 4: Correr los tests**

Run: `./sail artisan test tests/Feature/Services/QuorumServiceDedupTest.php --no-coverage`
Expected: 5 passed.

- [x] **Step 5: Suite completa**

Run: `./sail artisan test --no-coverage`
Expected: sin regresiones. Si algún test existente dependía del doble conteo, es un test incorrecto: ajustar sus expectativas al valor deduplicado y anotarlo en el commit.

- [x] **Step 6: Commit**

```bash
git add app/Services/QuorumService.php tests/Feature/Services/QuorumServiceDedupTest.php
git commit -m "fix: quorum no cuenta doble a poderdantes con asistencia propia y filtra poderes por reunion"
```

---

### Task 2: VotoService — mora del poderdante en votos delegados

**Files:**
- Modify: `app/Services/VotoService.php:22-28,49-59`
- Test: `tests/Feature/Services/VotoServiceMoraDelegadoTest.php`

**Interfaces:**
- Consumes: `VotoService::votar(Votacion, Copropietario, int $opcionId, Request, ?int $enNombreDeId)` (firma sin cambios).
- Reglas resultantes: voto propio de moroso → bloqueado; voto en nombre de poderdante moroso → bloqueado; apoderado moroso votando por poderdante al día → permitido.

- [x] **Step 1: Escribir tests que fallan**

Crear `tests/Feature/Services/VotoServiceMoraDelegadoTest.php`:

```php
<?php

namespace Tests\Feature\Services;

use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['restringir_voto_morosos' => true]);
        app()->instance('current_tenant', $this->tenant);

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
        Poder::create([
            'tenant_id'     => $this->tenant->id,
            'reunion_id'    => $this->reunion->id,
            'apoderado_id'  => $apoderado->id,
            'poderdante_id' => $poderdante->id,
            'estado'        => 'aprobado',
        ]);
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
```

- [x] **Step 2: Correr para confirmar el estado inicial**

Run: `./sail artisan test tests/Feature/Services/VotoServiceMoraDelegadoTest.php --no-coverage`
Expected: FALLAN `voto_delegado_de_poderdante_moroso_es_bloqueado` (hoy se permite) y `apoderado_moroso_si_puede_votar_por_poderdante_al_dia` (hoy el check global bloquea al apoderado moroso incluso para votos ajenos). Los otros dos pasan. Nota `BYPASS_QUORUM`: si el entorno de test no lo tiene, la asistencia confirmada de los propios actores da quórum según `quorum_requerido` del factory; si un test falla por quórum, agregar `'quorum_requerido' => 1.0` al `Reunion::factory()`.

- [x] **Step 3: Implementar en VotoService**

En `app/Services/VotoService.php`, reemplazar:

```php
        // Art. 38 Ley 675: copropietarios en mora no pueden votar.
        // El flujo PIN no tiene User → current_tenant no está en el contenedor;
        // el tenant se resuelve desde la reunión de la votación.
        $votacion->loadMissing('reunion.tenant');
        if ($votacion->reunion->tenant->restringir_voto_morosos && $copropietario->en_mora) {
            return ['success' => false, 'error' => 'No puede votar: copropietario en mora (Art. 38 Ley 675).'];
        }
```

por:

```php
        // Art. 38 Ley 675: la mora suspende el derecho de voto PROPIO.
        // El flujo PIN no tiene User → current_tenant no está en el contenedor;
        // el tenant se resuelve desde la reunión de la votación.
        $votacion->loadMissing('reunion.tenant');
        $restringirMorosos = (bool) $votacion->reunion->tenant->restringir_voto_morosos;

        if ($enNombreDeId === null && $restringirMorosos && $copropietario->en_mora) {
            return ['success' => false, 'error' => 'No puede votar: copropietario en mora (Art. 38 Ley 675).'];
        }

        // La mora del poderdante tampoco se elude delegando el voto.
        if ($enNombreDeId !== null && $restringirMorosos) {
            $poderdante = Copropietario::withoutGlobalScopes()->find($enNombreDeId);
            if ($poderdante?->en_mora) {
                return [
                    'success' => false,
                    'error'   => "El poderdante {$poderdante->nombre} está en mora y no puede votar, ni directamente ni mediante apoderado (Art. 38, Ley 675 de 2001).",
                ];
            }
        }
```

- [x] **Step 4: Correr los tests**

Run: `./sail artisan test tests/Feature/Services/VotoServiceMoraDelegadoTest.php --no-coverage`
Expected: 4 passed.

- [x] **Step 5: Suite completa**

Run: `./sail artisan test --no-coverage`
Expected: sin regresiones (los tests de `VotoServiceMoraTest` existentes cubren voto propio y siguen pasando).

- [x] **Step 6: Commit**

```bash
git add app/Services/VotoService.php tests/Feature/Services/VotoServiceMoraDelegadoTest.php
git commit -m "fix: mora del poderdante bloquea voto delegado; apoderado moroso vota por terceros (Art. 38)"
```

---

### Task 3: Sala — deshabilitar voto de poderdante moroso en la UI

**Files:**
- Modify: `app/Http/Controllers/Copropietario/SalaReunionController.php:131-144`
- Modify: `resources/js/Pages/Copropietario/Sala/Show.jsx:154,251-281,402,615`
- Test: `tests/Feature/Copropietario/SalaReunionShowTest.php`

**Interfaces:**
- Produces: prop Inertia `restringirMorosos` (bool) y clave `en_mora` (bool) en cada elemento de `poderdantesRepresentados`. Los modelos `Poder` en la prop `poderes` ya serializan `poderdante.en_mora`.

- [x] **Step 1: Test del nuevo prop (falla)**

Agregar al final de `tests/Feature/Copropietario/SalaReunionShowTest.php` (usa los helpers ya presentes en ese archivo):

```php
it('show expone restringirMorosos y en_mora de poderdantes representados', function () {
    $tenant = Tenant::factory()->create(['restringir_voto_morosos' => true]);
    app()->instance('current_tenant', $tenant);

    $apoderado  = Copropietario::factory()->create(['tenant_id' => $tenant->id]);
    $poderdante = Copropietario::factory()->create(['tenant_id' => $tenant->id, 'en_mora' => true]);
    $reunion = Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => ReunionEstado::EnCurso]);

    \App\Models\Poder::create([
        'tenant_id'     => $tenant->id,
        'reunion_id'    => $reunion->id,
        'apoderado_id'  => $apoderado->id,
        'poderdante_id' => $poderdante->id,
        'estado'        => 'aprobado',
    ]);

    $token = \Illuminate\Support\Str::random(64);
    \App\Models\AccesoReunion::create([
        'copropietario_id' => $apoderado->id,
        'reunion_id'       => $reunion->id,
        'pin_hash'         => bcrypt('000000'),
        'session_token'    => $token,
        'activo'           => true,
    ]);

    app()->forgetInstance('current_tenant');

    $response = $this->withSession(['copropietario_session_token' => $token])
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
        ->get("/sala/{$reunion->id}");

    $response->assertStatus(200);
    expect($response->json('props.restringirMorosos'))->toBeTrue();
    $reps = $response->json('props.poderdantesRepresentados');
    expect($reps[0]['en_mora'])->toBeTrue();
});
```

- [x] **Step 2: Correr para confirmar que falla**

Run: `./sail artisan test tests/Feature/Copropietario/SalaReunionShowTest.php --no-coverage`
Expected: FAIL — `restringirMorosos` es null.

- [x] **Step 3: Backend — SalaReunionController**

En `show()`, reemplazar:

```php
        $poderdantesRepresentados = $poderes->map(fn($p) => [
            'id'      => $p->poderdante_id,
            'nombre'  => $p->poderdante?->nombre,
            'unidades' => $p->poderdante?->unidades?->pluck('numero') ?? [],
        ])->values();
```

por:

```php
        $poderdantesRepresentados = $poderes->map(fn($p) => [
            'id'       => $p->poderdante_id,
            'nombre'   => $p->poderdante?->nombre,
            'unidades' => $p->poderdante?->unidades?->pluck('numero') ?? [],
            'en_mora'  => (bool) ($p->poderdante?->en_mora ?? false),
        ])->values();
```

Y reemplazar:

```php
        // El flujo PIN no tiene User → SetTenantContext no registra current_tenant.
        // El tenant correcto siempre es el de la reunión.
        $enMora = ($copropietario?->en_mora ?? false)
            && (bool) $reunion->tenant?->restringir_voto_morosos;

        return Inertia::render('Copropietario/Sala/Show', compact(
            'reunion', 'quorum', 'poderes', 'yaVotoPor', 'votacionAbierta',
            'resultadosActuales', 'feedInicial', 'estadoReunion', 'esDelegadoExterno',
            'poderdantesRepresentados', 'enMora'
        ));
```

por:

```php
        // El flujo PIN no tiene User → SetTenantContext no registra current_tenant.
        // El tenant correcto siempre es el de la reunión.
        $restringirMorosos = (bool) $reunion->tenant?->restringir_voto_morosos;
        $enMora = ($copropietario?->en_mora ?? false) && $restringirMorosos;

        return Inertia::render('Copropietario/Sala/Show', compact(
            'reunion', 'quorum', 'poderes', 'yaVotoPor', 'votacionAbierta',
            'resultadosActuales', 'feedInicial', 'estadoReunion', 'esDelegadoExterno',
            'poderdantesRepresentados', 'enMora', 'restringirMorosos'
        ));
```

- [x] **Step 4: Correr el test (pasa)**

Run: `./sail artisan test tests/Feature/Copropietario/SalaReunionShowTest.php --no-coverage`
Expected: PASS.

- [x] **Step 5: Frontend — Show.jsx**

En `resources/js/Pages/Copropietario/Sala/Show.jsx`:

1. Firma de `VotacionCard` (línea ~154): agregar `restringirMorosos = false`:

```jsx
function VotacionCard({ votacionActiva, resultados, yaVotoPor, poderes, onVotar, loading, esDelegadoExterno, enMora = false, restringirMorosos = false }) {
```

2. En el bloque `{poderes.map(poder => {` (línea ~251), reemplazar:

```jsx
                {poderes.map(poder => {
                    const yaVotoPoder = yaVotoPor.includes(poder.poderdante_id)
                    return (
                        <div key={poder.id} className="border-t pt-4 mt-4" style={{ borderColor: 'var(--sala-border)' }}>
                            <p className="text-[10px] uppercase tracking-widest mb-2" style={{ color: 'var(--sala-amber)' }}>
                                En nombre de: {poder.poderdante?.nombre}
                            </p>
                            {!yaVotoPoder ? (
```

por:

```jsx
                {poderes.map(poder => {
                    const yaVotoPoder = yaVotoPor.includes(poder.poderdante_id)
                    const poderdanteEnMora = restringirMorosos && poder.poderdante?.en_mora
                    return (
                        <div key={poder.id} className="border-t pt-4 mt-4" style={{ borderColor: 'var(--sala-border)' }}>
                            <p className="text-[10px] uppercase tracking-widest mb-2" style={{ color: 'var(--sala-amber)' }}>
                                En nombre de: {poder.poderdante?.nombre}
                            </p>
                            {poderdanteEnMora ? (
                                <p
                                    className="text-xs px-3 py-2.5 rounded-xl"
                                    style={{ background: 'rgba(248,113,113,0.10)', border: '1px solid rgba(248,113,113,0.35)', color: '#f87171' }}
                                >
                                    Poderdante en mora — su voto está suspendido (Art. 38, Ley 675)
                                </p>
                            ) : !yaVotoPoder ? (
```

(El resto del bloque — opciones y "✓ Voto registrado" — queda igual; el ternario existente `: (` pasa a ser el tercer brazo.)

3. En el componente de página, extraer el prop (línea ~402, junto a `enMora = false`):

```jsx
    restringirMorosos = false,
```

4. En el uso de `<VotacionCard` (línea ~615, junto a `enMora={enMora}`):

```jsx
                    restringirMorosos={restringirMorosos}
```

5. En la lista de poderdantes representados (línea ~596), reemplazar:

```jsx
                                <li key={p.id}>
                                    {p.nombre}
                                    {p.unidades?.length > 0 && (
                                        <span style={{ color: 'var(--sala-text-muted)' }}> · Unid. {p.unidades.join(', ')}</span>
                                    )}
                                </li>
```

por:

```jsx
                                <li key={p.id}>
                                    {p.nombre}
                                    {p.unidades?.length > 0 && (
                                        <span style={{ color: 'var(--sala-text-muted)' }}> · Unid. {p.unidades.join(', ')}</span>
                                    )}
                                    {restringirMorosos && p.en_mora && (
                                        <span style={{ color: '#f87171' }}> · en mora (voto suspendido)</span>
                                    )}
                                </li>
```

- [x] **Step 6: Compilar y suite completa**

Run: `./sail npm run build && ./sail artisan test --no-coverage`
Expected: build OK, tests verdes.

- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/Copropietario/SalaReunionController.php \
        resources/js/Pages/Copropietario/Sala/Show.jsx \
        tests/Feature/Copropietario/SalaReunionShowTest.php
git commit -m "feat: sala deshabilita voto delegado de poderdante en mora con aviso visual"
```

---

### Task 4: Poderes — advertencia de poderdante en mora en la lista

**Files:**
- Modify: `resources/js/Pages/Admin/Poderes/Index.jsx:29-33`

(La advertencia en el formulario de creación se implementa en el Task 10 junto con el buscador de poderdante, para no tocar el mismo bloque dos veces.)

- [x] **Step 1: Agregar badge en la tarjeta del poder**

En `resources/js/Pages/Admin/Poderes/Index.jsx`, en el componente de tarjeta (línea ~31), reemplazar:

```jsx
                    Delegado por: <span className="text-gray-600">{poderdante?.nombre}</span>
```

por:

```jsx
                    Delegado por: <span className="text-gray-600">{poderdante?.nombre}</span>
                    {poderdante?.en_mora && (
                        <span className="ml-2 inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-50 text-red-600 border border-red-200">
                            Poderdante en mora
                        </span>
                    )}
```

- [x] **Step 2: Compilar y verificar manualmente**

Run: `./sail npm run build`
Expected: build OK. Verificación manual: en `/admin/poderes`, un poder cuyo poderdante tenga `en_mora=true` muestra el badge rojo.

- [x] **Step 3: Commit**

```bash
git add resources/js/Pages/Admin/Poderes/Index.jsx
git commit -m "feat: badge de poderdante en mora en lista de poderes"
```

---

### Task 5: Bloqueo de apertura de votación con mayoría especial (backend)

**Files:**
- Modify: `app/Http/Controllers/Admin/VotacionController.php:106-131` (método `abrir`)
- Test: `tests/Feature/Admin/VotacionAbrirMayoriaTest.php`

**Interfaces:**
- Consumes: `QuorumService::presenciaCoeficiente(Reunion): array{total, presente, porcentaje}` (Task 1).
- Produces: `abrir()` redirige con `session('error')` y NO abre la votación cuando la presencia por coeficiente es menor al umbral (70% calificada_70, 100% unanimidad). `BYPASS_QUORUM` no exime.

- [x] **Step 1: Escribir tests que fallan**

Crear `tests/Feature/Admin/VotacionAbrirMayoriaTest.php` (patrón de `tests/Feature/Admin/VotacionLogTest.php`):

```php
<?php

use App\Enums\ReunionEstado;
use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\TipoDecision;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Votacion;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $this->tenant);
    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'rol'       => 'administrador',
        'activo'    => true,
    ]);
    $this->reunion = Reunion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'estado'     => ReunionEstado::EnCurso,
        'creado_por' => $this->admin->id,
    ]);
});

function presenciaConCoeficiente(Tenant $tenant, Reunion $reunion, array $coeficientes, int $presentes): void
{
    foreach ($coeficientes as $i => $coef) {
        $c = Copropietario::factory()->create(['tenant_id' => $tenant->id]);
        Unidad::factory()->create([
            'tenant_id'        => $tenant->id,
            'copropietario_id' => $c->id,
            'coeficiente'      => $coef,
        ]);
        if ($i < $presentes) {
            Asistencia::create([
                'reunion_id'           => $reunion->id,
                'copropietario_id'     => $c->id,
                'confirmada_por_admin' => true,
                'hora_confirmacion'    => now(),
            ]);
        }
    }
}

function votacionConMayoria(Tenant $tenant, Reunion $reunion, User $admin, string $tipoMayoria): Votacion
{
    $tipo = TipoDecision::create([
        'codigo'       => 'abrir_test_' . $tipoMayoria . '_' . uniqid(),
        'nombre'       => 'Test',
        'descripcion'  => 'Test',
        'tipo_mayoria' => $tipoMayoria,
        'aplica_en'    => ['asamblea'],
        'orden'        => 99,
    ]);

    return Votacion::factory()->create([
        'tenant_id'        => $tenant->id,
        'reunion_id'       => $reunion->id,
        'estado'           => 'creada',
        'tipo_decision_id' => $tipo->id,
        'creada_por'       => $admin->id,
    ]);
}

test('calificada_70 no abre con presencia menor a 70%', function () {
    presenciaConCoeficiente($this->tenant, $this->reunion, [40.0, 29.0, 31.0], presentes: 2); // 69%
    $votacion = votacionConMayoria($this->tenant, $this->reunion, $this->admin, 'calificada_70');

    $this->actingAs($this->admin)
        ->post(route('admin.votaciones.abrir', $votacion))
        ->assertSessionHas('error');

    expect($votacion->fresh()->estado)->toBe('creada');
});

test('calificada_70 abre con presencia de 70% exacto', function () {
    presenciaConCoeficiente($this->tenant, $this->reunion, [40.0, 30.0, 30.0], presentes: 2); // 70%
    $votacion = votacionConMayoria($this->tenant, $this->reunion, $this->admin, 'calificada_70');

    $this->actingAs($this->admin)
        ->post(route('admin.votaciones.abrir', $votacion))
        ->assertSessionMissing('error');

    expect($votacion->fresh()->estado)->toBe('abierta');
});

test('unanimidad no abre sin 100% de presencia', function () {
    presenciaConCoeficiente($this->tenant, $this->reunion, [50.0, 49.0, 1.0], presentes: 2); // 99%
    $votacion = votacionConMayoria($this->tenant, $this->reunion, $this->admin, 'unanimidad');

    $this->actingAs($this->admin)
        ->post(route('admin.votaciones.abrir', $votacion))
        ->assertSessionHas('error');

    expect($votacion->fresh()->estado)->toBe('creada');
});

test('unanimidad abre con 100% de presencia', function () {
    presenciaConCoeficiente($this->tenant, $this->reunion, [50.0, 50.0], presentes: 2);
    $votacion = votacionConMayoria($this->tenant, $this->reunion, $this->admin, 'unanimidad');

    $this->actingAs($this->admin)
        ->post(route('admin.votaciones.abrir', $votacion))
        ->assertSessionMissing('error');

    expect($votacion->fresh()->estado)->toBe('abierta');
});

test('mayoria simple abre sin restriccion de presencia', function () {
    presenciaConCoeficiente($this->tenant, $this->reunion, [10.0, 90.0], presentes: 1); // 10%
    $votacion = votacionConMayoria($this->tenant, $this->reunion, $this->admin, 'simple');

    $this->actingAs($this->admin)
        ->post(route('admin.votaciones.abrir', $votacion))
        ->assertSessionMissing('error');

    expect($votacion->fresh()->estado)->toBe('abierta');
});

test('bypass_quorum no exime el bloqueo de apertura', function () {
    config(['app.bypass_quorum' => true]);
    presenciaConCoeficiente($this->tenant, $this->reunion, [40.0, 60.0], presentes: 1); // 40%
    $votacion = votacionConMayoria($this->tenant, $this->reunion, $this->admin, 'calificada_70');

    $this->actingAs($this->admin)
        ->post(route('admin.votaciones.abrir', $votacion))
        ->assertSessionHas('error');

    expect($votacion->fresh()->estado)->toBe('creada');
});
```

- [x] **Step 2: Correr para confirmar que fallan**

Run: `./sail artisan test tests/Feature/Admin/VotacionAbrirMayoriaTest.php --no-coverage`
Expected: fallan los 3 tests de bloqueo (hoy siempre abre); pasan los de apertura permitida.

- [x] **Step 3: Implementar en `abrir()`**

En `app/Http/Controllers/Admin/VotacionController.php`, reemplazar el inicio del método:

```php
    public function abrir(Votacion $votacion)
    {
        $votacion->load('reunion');
        $quorum = $this->quorumService->calcular($votacion->reunion);

        $votacion->update(['estado' => 'abierta', 'abierta_at' => now()]);
```

por:

```php
    public function abrir(Votacion $votacion)
    {
        $votacion->load('reunion', 'tipoDecision');
        $quorum = $this->quorumService->calcular($votacion->reunion);

        // Arts. 45/46 Ley 675: sin la presencia mínima por coeficiente, la
        // aprobación es matemáticamente imposible — no se permite abrir.
        // Este bloqueo NO se exime con BYPASS_QUORUM (flag de dev para
        // quórum de instalación, no para umbrales de mayoría).
        $tipoMayoria = $votacion->tipoDecision?->tipo_mayoria;
        if (in_array($tipoMayoria, ['calificada_70', 'unanimidad'], true)) {
            $umbral    = $tipoMayoria === 'calificada_70' ? 70.0 : 100.0;
            $presencia = $this->quorumService->presenciaCoeficiente($votacion->reunion);

            if ($presencia['porcentaje'] < $umbral) {
                return back()->with('error', sprintf(
                    'No se puede abrir la votación: la decisión requiere %s%% de coeficiente presente y actualmente hay %s%% (Art. %s, Ley 675 de 2001).',
                    rtrim(rtrim(number_format($umbral, 1), '0'), '.'),
                    $presencia['porcentaje'],
                    $tipoMayoria === 'calificada_70' ? '46' : '9'
                ));
            }
        }

        $votacion->update(['estado' => 'abierta', 'abierta_at' => now()]);
```

- [x] **Step 4: Correr los tests**

Run: `./sail artisan test tests/Feature/Admin/VotacionAbrirMayoriaTest.php --no-coverage`
Expected: 6 passed.

- [x] **Step 5: Suite completa**

Run: `./sail artisan test --no-coverage`
Expected: sin regresiones.

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/VotacionController.php tests/Feature/Admin/VotacionAbrirMayoriaTest.php
git commit -m "feat: bloqueo de apertura de votacion sin presencia minima para mayorias especiales"
```

---

### Task 6: Bloqueo de apertura — UX en Conducir

**Files:**
- Modify: `resources/js/Pages/Admin/Reuniones/Conducir.jsx:299-303,530-540`

**Interfaces:**
- Consumes: `session('error')` compartido como `flash.error` por `HandleInertiaRequests` (ya existe); `quorum` state (widget oficial); `v.tipo_decision.tipo_mayoria` (las votaciones se cargan `with('tipoDecision')`).

- [x] **Step 1: Mostrar flash.error**

Junto al bloque de `flash?.success` (línea ~299), agregar:

```jsx
            {flash?.error && (
                <div className="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
                    {flash.error}
                </div>
            )}
```

- [x] **Step 2: Deshabilitar el botón Abrir cuando no alcanza el umbral**

Antes del `return` del componente (junto a los cálculos existentes de la línea ~197), agregar:

```jsx
    // Umbral de presencia para mayorías especiales (solo UX — el backend valida siempre).
    // El % oficial del widget es por coeficiente solo cuando la reunión pesa por coeficiente.
    const presenciaParaUmbral = quorum?.tipo === 'coeficiente' ? quorum.porcentaje_presente : null
    const umbralApertura = (v) => {
        const m = v.tipo_decision?.tipo_mayoria
        if (m === 'calificada_70') return 70
        if (m === 'unanimidad') return 100
        return null
    }
```

En el botón de abrir (línea ~535), reemplazar:

```jsx
                                                        <button onClick={() => abrirVotacion(v.id)}
```

por (conservando las clases/props existentes del botón y agregando la lógica):

```jsx
                                                        {(() => {
                                                            const umbral = umbralApertura(v)
                                                            const bloqueada = umbral !== null && presenciaParaUmbral !== null && presenciaParaUmbral < umbral
                                                            return (
                                                        <button onClick={() => !bloqueada && abrirVotacion(v.id)}
                                                            disabled={bloqueada}
                                                            title={bloqueada ? `Requiere ${umbral}% de coeficiente presente (hay ${presenciaParaUmbral}%)` : undefined}
```

y cerrar el IIFE después del cierre del `<button>...</button>` original:

```jsx
                                                            )
                                                        })()}
```

Si el botón bloqueado queda deshabilitado, agregar debajo del botón (dentro del IIFE, envolviendo botón y aviso en un fragment `<>...</>`):

```jsx
                                                        {bloqueada && (
                                                            <p className="text-[10px] text-red-500 mt-0.5">
                                                                Requiere {umbral}% presente · hay {presenciaParaUmbral}%
                                                            </p>
                                                        )}
```

- [x] **Step 3: Compilar y verificar manualmente**

Run: `./sail npm run build`
Expected: build OK. Manual: crear votación `calificada_70` con presencia < 70% → botón deshabilitado con motivo; forzar el POST (o quitar disabled desde devtools) → flash de error del backend.

- [x] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/Reuniones/Conducir.jsx
git commit -m "feat: Conducir deshabilita apertura de votaciones con mayoria especial sin presencia y muestra flash de error"
```

---

### Task 7: Eventos de asistencia (`asistencia_eventos`)

**Files:**
- Create: `database/migrations/2026_07_02_000001_create_asistencia_eventos_table.php`
- Create: `app/Models/AsistenciaEvento.php`
- Modify: `app/Http/Controllers/Copropietario/SalaReunionController.php:54-79`
- Modify: `app/Http/Controllers/Admin/ReunionController.php:207-220` (método `confirmarAsistencia`)
- Test: `tests/Feature/AsistenciaEventosTest.php`

**Interfaces:**
- Produces: modelo `App\Models\AsistenciaEvento` con `$fillable = ['tenant_id','reunion_id','copropietario_id','tipo','origen','quorum_resultante']`; tabla `asistencia_eventos`. `tipo` ∈ {`entrada`,`salida`}; `origen` ∈ {`auto_sala`,`admin`,`representado`}.

- [x] **Step 1: Migración**

Crear `database/migrations/2026_07_02_000001_create_asistencia_eventos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencia_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('reunion_id')->constrained('reuniones')->cascadeOnDelete();
            $table->foreignId('copropietario_id')->constrained('copropietarios')->cascadeOnDelete();
            $table->enum('tipo', ['entrada', 'salida']);
            $table->enum('origen', ['auto_sala', 'admin', 'representado']);
            $table->decimal('quorum_resultante', 8, 4)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reunion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencia_eventos');
    }
};
```

- [x] **Step 2: Modelo**

Crear `app/Models/AsistenciaEvento.php`:

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AsistenciaEvento extends Model
{
    use BelongsToTenant;

    protected $table = 'asistencia_eventos';

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'reunion_id', 'copropietario_id',
        'tipo', 'origen', 'quorum_resultante',
    ];

    protected $casts = [
        'quorum_resultante' => 'float',
    ];

    public function copropietario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Copropietario::class);
    }
}
```

Correr la migración: `./sail artisan migrate`
Expected: sin errores.

- [x] **Step 3: Tests que fallan**

Crear `tests/Feature/AsistenciaEventosTest.php`:

```php
<?php

use App\Enums\ReunionEstado;
use App\Models\AsistenciaEvento;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;

function inertiaVersionEventos(): string
{
    $manifest = public_path('build/manifest.json');
    return file_exists($manifest) ? hash_file('xxh128', $manifest) : '';
}

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $this->tenant);
    $this->reunion = Reunion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'estado'    => ReunionEstado::AnteSala,
    ]);
});

test('entrar a la sala por PIN registra evento entrada auto_sala', function () {
    $copro = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
    Unidad::factory()->create(['tenant_id' => $this->tenant->id, 'copropietario_id' => $copro->id, 'coeficiente' => 50.0]);

    $token = \Illuminate\Support\Str::random(64);
    \App\Models\AccesoReunion::create([
        'copropietario_id' => $copro->id,
        'reunion_id'       => $this->reunion->id,
        'pin_hash'         => bcrypt('000000'),
        'session_token'    => $token,
        'activo'           => true,
    ]);

    app()->forgetInstance('current_tenant');

    $this->withSession(['copropietario_session_token' => $token])
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersionEventos()])
        ->get("/sala/{$this->reunion->id}")
        ->assertStatus(200);

    $evento = AsistenciaEvento::withoutGlobalScopes()
        ->where('reunion_id', $this->reunion->id)
        ->where('copropietario_id', $copro->id)
        ->first();

    expect($evento)->not->toBeNull()
        ->and($evento->tipo)->toBe('entrada')
        ->and($evento->origen)->toBe('auto_sala')
        ->and($evento->quorum_resultante)->toBeGreaterThan(0);
});

test('reentrar a la sala no duplica el evento de entrada', function () {
    $copro = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
    Unidad::factory()->create(['tenant_id' => $this->tenant->id, 'copropietario_id' => $copro->id, 'coeficiente' => 50.0]);

    $token = \Illuminate\Support\Str::random(64);
    \App\Models\AccesoReunion::create([
        'copropietario_id' => $copro->id,
        'reunion_id'       => $this->reunion->id,
        'pin_hash'         => bcrypt('000000'),
        'session_token'    => $token,
        'activo'           => true,
    ]);

    app()->forgetInstance('current_tenant');

    foreach ([1, 2] as $i) {
        $this->withSession(['copropietario_session_token' => $token])
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersionEventos()])
            ->get("/sala/{$this->reunion->id}");
    }

    expect(AsistenciaEvento::withoutGlobalScopes()
        ->where('reunion_id', $this->reunion->id)
        ->where('copropietario_id', $copro->id)
        ->count())->toBe(1);
});

test('entrar como apoderado registra evento representado para cada poderdante', function () {
    $apoderado  = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
    $poderdante = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
    Unidad::factory()->create(['tenant_id' => $this->tenant->id, 'copropietario_id' => $apoderado->id, 'coeficiente' => 50.0]);
    Unidad::factory()->create(['tenant_id' => $this->tenant->id, 'copropietario_id' => $poderdante->id, 'coeficiente' => 50.0]);

    Poder::create([
        'tenant_id'     => $this->tenant->id,
        'reunion_id'    => $this->reunion->id,
        'apoderado_id'  => $apoderado->id,
        'poderdante_id' => $poderdante->id,
        'estado'        => 'aprobado',
    ]);

    $token = \Illuminate\Support\Str::random(64);
    \App\Models\AccesoReunion::create([
        'copropietario_id' => $apoderado->id,
        'reunion_id'       => $this->reunion->id,
        'pin_hash'         => bcrypt('000000'),
        'session_token'    => $token,
        'activo'           => true,
    ]);

    app()->forgetInstance('current_tenant');

    $this->withSession(['copropietario_session_token' => $token])
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersionEventos()])
        ->get("/sala/{$this->reunion->id}");

    $eventoRepresentado = AsistenciaEvento::withoutGlobalScopes()
        ->where('reunion_id', $this->reunion->id)
        ->where('copropietario_id', $poderdante->id)
        ->first();

    expect($eventoRepresentado)->not->toBeNull()
        ->and($eventoRepresentado->origen)->toBe('representado');
});

test('confirmacion manual del admin registra evento entrada admin', function () {
    $admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'rol'       => 'administrador',
        'activo'    => true,
    ]);
    $copro = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
    Unidad::factory()->create(['tenant_id' => $this->tenant->id, 'copropietario_id' => $copro->id, 'coeficiente' => 50.0]);

    $this->actingAs($admin)
        ->post("/admin/reuniones/{$this->reunion->id}/copropietarios/{$copro->id}/asistencia");

    $evento = AsistenciaEvento::withoutGlobalScopes()
        ->where('reunion_id', $this->reunion->id)
        ->where('copropietario_id', $copro->id)
        ->first();

    expect($evento)->not->toBeNull()
        ->and($evento->origen)->toBe('admin');
});
```

- [x] **Step 4: Correr para confirmar que fallan**

Run: `./sail artisan test tests/Feature/AsistenciaEventosTest.php --no-coverage`
Expected: 4 fallos (no se crean eventos aún).

- [x] **Step 5: Registrar eventos en SalaReunionController**

En `show()`, reemplazar el bloque de auto-registro:

```php
            // Registrar asistencia del copropietario que entra físicamente
            Asistencia::updateOrCreate(
                ['reunion_id' => $reunion->id, 'copropietario_id' => $copropietario->id],
                ['confirmada_por_admin' => true, 'hora_confirmacion' => now()]
            );
```

por:

```php
            // Registrar asistencia del copropietario que entra físicamente
            $asistencia = Asistencia::updateOrCreate(
                ['reunion_id' => $reunion->id, 'copropietario_id' => $copropietario->id],
                ['confirmada_por_admin' => true, 'hora_confirmacion' => now()]
            );
            $nuevasEntradas = $asistencia->wasRecentlyCreated
                ? [[$copropietario->id, 'auto_sala']]
                : [];
```

Y el bloque de poderdantes:

```php
            // Registrar asistencia para cada poderdante representado
            foreach ($poderes as $poder) {
                Asistencia::updateOrCreate(
                    ['reunion_id' => $reunion->id, 'copropietario_id' => $poder->poderdante_id],
                    ['confirmada_por_admin' => true, 'hora_confirmacion' => now()]
                );
            }

            $quorum = $this->quorumService->calcular($reunion);
            broadcast(new QuorumActualizado($reunion->id, $quorum));
```

por:

```php
            // Registrar asistencia para cada poderdante representado
            foreach ($poderes as $poder) {
                $asistenciaPoderdante = Asistencia::updateOrCreate(
                    ['reunion_id' => $reunion->id, 'copropietario_id' => $poder->poderdante_id],
                    ['confirmada_por_admin' => true, 'hora_confirmacion' => now()]
                );
                if ($asistenciaPoderdante->wasRecentlyCreated) {
                    $nuevasEntradas[] = [$poder->poderdante_id, 'representado'];
                }
            }

            $quorum = $this->quorumService->calcular($reunion);

            // Log auditable de entradas (reconstruye el quórum en cualquier instante)
            foreach ($nuevasEntradas as [$copropietarioId, $origen]) {
                \App\Models\AsistenciaEvento::create([
                    'tenant_id'         => $reunion->tenant_id,
                    'reunion_id'        => $reunion->id,
                    'copropietario_id'  => $copropietarioId,
                    'tipo'              => 'entrada',
                    'origen'            => $origen,
                    'quorum_resultante' => $quorum['porcentaje_presente'],
                ]);
            }

            broadcast(new QuorumActualizado($reunion->id, $quorum));
```

- [x] **Step 6: Registrar evento en confirmarAsistencia (admin)**

En `app/Http/Controllers/Admin/ReunionController.php`, reemplazar el método completo:

```php
    public function confirmarAsistencia(Reunion $reunion, Copropietario $copropietario)
    {
        \App\Models\Asistencia::updateOrCreate(
            ['reunion_id' => $reunion->id, 'copropietario_id' => $copropietario->id],
            ['confirmada_por_admin' => true, 'hora_confirmacion' => now()]
        );

        broadcast(new \App\Events\QuorumActualizado(
            $reunion->id,
            app(QuorumService::class)->calcular($reunion)
        ));

        return back()->with('success', 'Asistencia confirmada.');
    }
```

por:

```php
    public function confirmarAsistencia(Reunion $reunion, Copropietario $copropietario)
    {
        $asistencia = \App\Models\Asistencia::updateOrCreate(
            ['reunion_id' => $reunion->id, 'copropietario_id' => $copropietario->id],
            ['confirmada_por_admin' => true, 'hora_confirmacion' => now()]
        );

        $quorum = app(QuorumService::class)->calcular($reunion);

        if ($asistencia->wasRecentlyCreated) {
            \App\Models\AsistenciaEvento::create([
                'tenant_id'         => $reunion->tenant_id,
                'reunion_id'        => $reunion->id,
                'copropietario_id'  => $copropietario->id,
                'tipo'              => 'entrada',
                'origen'            => 'admin',
                'quorum_resultante' => $quorum['porcentaje_presente'],
            ]);
        }

        broadcast(new \App\Events\QuorumActualizado($reunion->id, $quorum));

        return back()->with('success', 'Asistencia confirmada.');
    }
```

- [x] **Step 7: Correr los tests**

Run: `./sail artisan test tests/Feature/AsistenciaEventosTest.php --no-coverage`
Expected: 4 passed.

- [x] **Step 8: Suite completa y commit**

Run: `./sail artisan test --no-coverage`
Expected: verde.

```bash
git add database/migrations/2026_07_02_000001_create_asistencia_eventos_table.php \
        app/Models/AsistenciaEvento.php \
        app/Http/Controllers/Copropietario/SalaReunionController.php \
        app/Http/Controllers/Admin/ReunionController.php \
        tests/Feature/AsistenciaEventosTest.php
git commit -m "feat: log de eventos de asistencia con quorum resultante (compliance reuniones virtuales)"
```

---

### Task 8: Snapshots de quórum por votación + acta

**Files:**
- Create: `database/migrations/2026_07_02_000002_add_quorum_snapshots_to_votaciones.php`
- Modify: `app/Models/Votacion.php:21-25`
- Modify: `app/Http/Controllers/Admin/VotacionController.php` (métodos `abrir` y `cerrar`)
- Modify: `resources/views/reportes/acta.blade.php:79-84`
- Test: `tests/Feature/Admin/VotacionQuorumSnapshotTest.php`

**Interfaces:**
- Produces: `votaciones.quorum_apertura` y `votaciones.quorum_cierre` (json nullable, cast `array`), con la estructura de `QuorumService::calcular()`.

- [x] **Step 1: Migración**

Crear `database/migrations/2026_07_02_000002_add_quorum_snapshots_to_votaciones.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('votaciones', function (Blueprint $table) {
            $table->json('quorum_apertura')->nullable()->after('resultado');
            $table->json('quorum_cierre')->nullable()->after('quorum_apertura');
        });
    }

    public function down(): void
    {
        Schema::table('votaciones', function (Blueprint $table) {
            $table->dropColumn(['quorum_apertura', 'quorum_cierre']);
        });
    }
};
```

Run: `./sail artisan migrate`

- [x] **Step 2: Casts y fillable en Votacion**

En `app/Models/Votacion.php`, agregar a `$fillable`: `'quorum_apertura', 'quorum_cierre',` y en `$casts`:

```php
        'quorum_apertura' => 'array',
        'quorum_cierre'   => 'array',
```

- [x] **Step 3: Tests que fallan**

Crear `tests/Feature/Admin/VotacionQuorumSnapshotTest.php`:

```php
<?php

use App\Enums\ReunionEstado;
use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Votacion;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $this->tenant);
    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'rol'       => 'administrador',
        'activo'    => true,
    ]);
    $this->reunion = Reunion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'estado'     => ReunionEstado::EnCurso,
        'creado_por' => $this->admin->id,
    ]);

    $copro = Copropietario::factory()->create(['tenant_id' => $this->tenant->id]);
    Unidad::factory()->create(['tenant_id' => $this->tenant->id, 'copropietario_id' => $copro->id, 'coeficiente' => 100.0]);
    Asistencia::create([
        'reunion_id'           => $this->reunion->id,
        'copropietario_id'     => $copro->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion'    => now(),
    ]);
});

test('abrir votacion persiste quorum_apertura', function () {
    $votacion = Votacion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'reunion_id' => $this->reunion->id,
        'estado'     => 'creada',
        'creada_por' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->post(route('admin.votaciones.abrir', $votacion));

    $votacion->refresh();
    expect($votacion->quorum_apertura)->toBeArray()
        ->and($votacion->quorum_apertura)->toHaveKeys(['porcentaje_presente', 'presente', 'total', 'tiene_quorum']);
});

test('cerrar votacion persiste quorum_cierre', function () {
    $votacion = Votacion::factory()->create([
        'tenant_id'  => $this->tenant->id,
        'reunion_id' => $this->reunion->id,
        'estado'     => 'abierta',
        'creada_por' => $this->admin->id,
    ]);
    $votacion->opciones()->create(['texto' => 'Sí', 'orden' => 1]);

    $this->actingAs($this->admin)->post(route('admin.votaciones.cerrar', $votacion));

    $votacion->refresh();
    expect($votacion->quorum_cierre)->toBeArray()
        ->and($votacion->quorum_cierre)->toHaveKey('porcentaje_presente');
});
```

Nota: si `route('admin.votaciones.cerrar', ...)` no existe con ese nombre, buscar el nombre real con `./sail artisan route:list | grep cerrar` y ajustar.

- [x] **Step 4: Correr para confirmar que fallan**

Run: `./sail artisan test tests/Feature/Admin/VotacionQuorumSnapshotTest.php --no-coverage`
Expected: 2 fallos (`quorum_apertura` null).

- [x] **Step 5: Persistir en abrir() y cerrar()**

En `abrir()` (tras el bloqueo de mayorías del Task 5), reemplazar:

```php
        $votacion->update(['estado' => 'abierta', 'abierta_at' => now()]);
```

por:

```php
        $votacion->update([
            'estado'          => 'abierta',
            'abierta_at'      => now(),
            'quorum_apertura' => $quorum,
        ]);
```

En `cerrar()`, localizar el `$votacion->update([...])` que fija `'estado' => 'cerrada'` y agregar la clave:

```php
            'quorum_cierre' => $this->quorumService->calcular($votacion->reunion),
```

(Si `cerrar()` no tiene `$votacion->load('reunion')` previo, agregarlo. `$this->quorumService` ya está inyectado en el constructor del controller.)

- [x] **Step 6: Acta PDF**

En `resources/views/reportes/acta.blade.php`, dentro del `@foreach($votaciones as $v)` (línea ~79), agregar después de la línea del título/pregunta de la votación (identificarla visualmente en el loop):

```blade
        @if($v->quorum_apertura)
            <p style="font-size: 10px; color: #555; margin: 2px 0;">
                Quórum al abrir: {{ $v->quorum_apertura['porcentaje_presente'] }}%
                @if($v->quorum_cierre)
                    · Quórum al cerrar: {{ $v->quorum_cierre['porcentaje_presente'] }}%
                @endif
            </p>
        @endif
```

- [x] **Step 7: Correr tests y suite**

Run: `./sail artisan test tests/Feature/Admin/VotacionQuorumSnapshotTest.php --no-coverage && ./sail artisan test --no-coverage`
Expected: todo verde.

- [x] **Step 8: Commit**

```bash
git add database/migrations/2026_07_02_000002_add_quorum_snapshots_to_votaciones.php \
        app/Models/Votacion.php app/Http/Controllers/Admin/VotacionController.php \
        resources/views/reportes/acta.blade.php \
        tests/Feature/Admin/VotacionQuorumSnapshotTest.php
git commit -m "feat: snapshots de quorum al abrir/cerrar votacion y quorum por decision en el acta"
```

---

### Task 9: Copropietarios — estado de deudor en Index y Show

**Files:**
- Modify: `resources/js/Pages/Admin/Copropietarios/Index.jsx:138,158-160`
- Modify: `resources/js/Pages/Admin/Copropietarios/Show.jsx:47-49`

- [x] **Step 1: Index — fila con acento y badge**

En la tabla de copropietarios internos (línea ~138), reemplazar:

```jsx
                        <tr key={c.id} className="hover:bg-surface-hover transition-colors">
```

por:

```jsx
                        <tr key={c.id} className={`hover:bg-surface-hover transition-colors ${c.en_mora ? 'bg-danger-bg/40 border-l-2 border-danger' : ''}`}>
```

Junto al badge Activo/Inactivo (línea ~158), agregar después del `</span>` de ese badge:

```jsx
                                    {c.en_mora && (
                                        <span className="ml-1.5 inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-danger-bg text-danger">
                                            En mora
                                        </span>
                                    )}
```

- [x] **Step 2: Show — badge de estado de mora**

En `Show.jsx`, reemplazar el badge de estado (línea ~47):

```jsx
                        <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${copropietario.activo ? 'bg-success-bg text-success' : 'bg-danger-bg text-danger'}`}>
                            {copropietario.activo ? 'Activo' : 'Inactivo'}
                        </span>
```

por:

```jsx
                        <div className="flex items-center gap-1.5">
                            <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${copropietario.activo ? 'bg-success-bg text-success' : 'bg-danger-bg text-danger'}`}>
                                {copropietario.activo ? 'Activo' : 'Inactivo'}
                            </span>
                            <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${copropietario.en_mora ? 'bg-danger-bg text-danger' : 'bg-success-bg text-success'}`}>
                                {copropietario.en_mora ? 'En mora' : 'Al día'}
                            </span>
                        </div>
```

- [x] **Step 3: Compilar, verificar y commit**

Run: `./sail npm run build`
Expected: build OK. Manual: `/admin/copropietarios` muestra filas acentuadas para morosos; el detalle muestra "En mora"/"Al día".

```bash
git add resources/js/Pages/Admin/Copropietarios/Index.jsx resources/js/Pages/Admin/Copropietarios/Show.jsx
git commit -m "feat: estado de mora visible en index y show de copropietarios"
```

---

### Task 10: Componente BuscadorCopropietario + poderdante con búsqueda

**Files:**
- Create: `resources/js/Components/BuscadorCopropietario.jsx`
- Modify: `resources/js/Pages/Admin/Poderes/Index.jsx:174-190` (select de poderdante) y `:216-278` (búsqueda de apoderado)

**Interfaces:**
- Produces: `<BuscadorCopropietario copropietarios={[]} seleccionado={obj|null} onSeleccionar={fn} onLimpiar={fn} label="" placeholder="" />`. Cada copropietario debe traer `id, nombre, numero_documento, unidades[], en_mora`.

- [x] **Step 1: Crear el componente**

Crear `resources/js/Components/BuscadorCopropietario.jsx`:

```jsx
import { useState } from 'react'

export default function BuscadorCopropietario({
    copropietarios = [],
    seleccionado = null,
    onSeleccionar,
    onLimpiar,
    label = 'Buscar copropietario *',
    placeholder = 'Nombre, documento o unidad…',
    children = null, // contenido extra bajo la tarjeta de seleccionado (ej. elegibilidad)
}) {
    const [busqueda, setBusqueda] = useState('')

    const filtrados = copropietarios.filter(c => {
        const q = busqueda.toLowerCase()
        return (
            c.nombre?.toLowerCase().includes(q) ||
            (c.numero_documento ?? '').toLowerCase().includes(q) ||
            (c.unidades ?? []).some(u => u.numero?.toLowerCase().includes(q))
        )
    })

    const seleccionar = (c) => {
        onSeleccionar(c)
        setBusqueda('')
    }

    if (seleccionado) {
        return (
            <div>
                <div className="flex items-center justify-between border border-gray-200 rounded-lg px-3 py-2.5 bg-white">
                    <div>
                        <p className="text-sm font-medium text-gray-800">
                            {seleccionado.nombre}
                            {seleccionado.en_mora && (
                                <span className="ml-2 inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-50 text-red-600 border border-red-200">
                                    En mora
                                </span>
                            )}
                        </p>
                        <p className="text-xs text-gray-400 mt-0.5">
                            {seleccionado.numero_documento && `Doc: ${seleccionado.numero_documento} · `}
                            Unidades: {seleccionado.unidades?.map(u => u.numero).join(', ') || '—'}
                        </p>
                    </div>
                    <button type="button" onClick={onLimpiar} className="text-xs text-gray-400 hover:text-red-500 ml-3">✕</button>
                </div>
                {children}
            </div>
        )
    }

    return (
        <div>
            <label className="text-xs text-gray-500 block mb-1">{label}</label>
            <input
                type="text"
                value={busqueda}
                onChange={e => setBusqueda(e.target.value)}
                placeholder={placeholder}
                className="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-1"
            />
            {busqueda.length > 0 && (
                <div className="border border-gray-200 rounded bg-white max-h-40 overflow-y-auto shadow-sm">
                    {filtrados.length === 0 ? (
                        <p className="text-xs text-gray-400 px-3 py-2">Sin resultados</p>
                    ) : filtrados.map(c => (
                        <button
                            key={c.id}
                            type="button"
                            onClick={() => seleccionar(c)}
                            className="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 transition border-b border-gray-100 last:border-0"
                        >
                            <span className="font-medium">{c.nombre}</span>
                            {c.en_mora && (
                                <span className="ml-1.5 inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-50 text-red-600">en mora</span>
                            )}
                            <span className="text-gray-400 text-xs ml-2">
                                {c.numero_documento && `Doc: ${c.numero_documento} · `}
                                {c.unidades?.map(u => u.numero).join(', ')}
                            </span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    )
}
```

- [x] **Step 2: Usarlo para el poderdante**

En `resources/js/Pages/Admin/Poderes/Index.jsx`:

1. Importar arriba: `import BuscadorCopropietario from '@/Components/BuscadorCopropietario'`
2. En `CrearPoderForm`, agregar estado: `const [poderdanteSeleccionado, setPoderdanteSeleccionado] = useState(null)`
3. Reemplazar el bloque del select de poderdante (líneas ~174-190):

```jsx
            {/* Poderdante */}
            <div>
                <label className="text-xs text-gray-500 block mb-1">Copropietario que otorga el poder (poderdante) *</label>
                <select
                    value={data.poderdante_id}
                    onChange={e => setData('poderdante_id', e.target.value)}
                    className="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Seleccionar copropietario…</option>
                    {copropietarios.map(c => (
                        <option key={c.id} value={c.id}>
                            {c.nombre} — {c.unidades?.map(u => u.numero).join(', ') || 'sin unidades'}
                        </option>
                    ))}
                </select>
                {errors.poderdante_id && <p className="text-red-500 text-xs mt-0.5">{errors.poderdante_id}</p>}
            </div>
```

por:

```jsx
            {/* Poderdante */}
            <div>
                <label className="text-xs text-gray-500 block mb-1">Copropietario que otorga el poder (poderdante) *</label>
                <BuscadorCopropietario
                    copropietarios={copropietarios}
                    seleccionado={poderdanteSeleccionado}
                    onSeleccionar={c => { setPoderdanteSeleccionado(c); setData('poderdante_id', c.id) }}
                    onLimpiar={() => { setPoderdanteSeleccionado(null); setData('poderdante_id', '') }}
                    label="Buscar poderdante *"
                >
                    {poderdanteSeleccionado?.en_mora && (
                        <p className="mt-1.5 px-3 py-2 rounded text-xs font-medium bg-yellow-50 border border-yellow-200 text-yellow-700">
                            ⚠ Poderdante en mora: su voto delegado será bloqueado en las votaciones mientras la restricción del conjunto esté activa (Art. 38, Ley 675).
                        </p>
                    )}
                </BuscadorCopropietario>
                {errors.poderdante_id && <p className="text-red-500 text-xs mt-0.5">{errors.poderdante_id}</p>}
            </div>
```

4. En `cambiarModo` y en `onSuccess` del submit, agregar `setPoderdanteSeleccionado(null)`.
5. Reemplazar el bloque de búsqueda del apoderado (líneas ~216-278, dentro de `{modo === 'copropietario' && (...)}`) por el mismo componente, conservando la verificación de elegibilidad:

```jsx
            {modo === 'copropietario' && (
                <div>
                    <BuscadorCopropietario
                        copropietarios={copropietariosFiltradosParaApoderado ?? copropietarios}
                        seleccionado={apoderadoSeleccionado}
                        onSeleccionar={seleccionar}
                        onLimpiar={limpiarSeleccion}
                        label="Buscar copropietario delegado *"
                    >
                        {verificando && <p className="text-xs text-gray-400 mt-1.5">Verificando elegibilidad…</p>}
                        {elegibilidad && !verificando && (
                            <div className={`mt-1.5 px-3 py-2 rounded text-xs font-medium ${
                                elegibilidad.bloqueado
                                    ? 'bg-red-50 border border-red-200 text-red-700'
                                    : elegibilidad.info
                                        ? 'bg-yellow-50 border border-yellow-200 text-yellow-700'
                                        : 'bg-green-50 border border-green-200 text-green-700'
                            }`}>
                                {elegibilidad.bloqueado
                                    ? `✕ ${elegibilidad.motivo}`
                                    : elegibilidad.info
                                        ? `ⓘ ${elegibilidad.info}`
                                        : '✓ Elegible como delegado'}
                            </div>
                        )}
                    </BuscadorCopropietario>
                    {errors.apoderado_copropietario_id && (
                        <p className="text-red-500 text-xs mt-0.5">{errors.apoderado_copropietario_id}</p>
                    )}
                </div>
            )}
```

Con esto los estados `busqueda` y `copropietariosFiltrados` locales del form quedan sin uso: eliminarlos (`const [busqueda, setBusqueda]` y el filtro de líneas 83-90). Si `seleccionar()` referenciaba `setBusqueda`, quitar esa línea.

- [x] **Step 3: Compilar, verificar y commit**

Run: `./sail npm run build`
Expected: build OK. Manual: crear poder buscando poderdante por nombre/documento/unidad; poderdante moroso muestra advertencia amarilla.

```bash
git add resources/js/Components/BuscadorCopropietario.jsx resources/js/Pages/Admin/Poderes/Index.jsx
git commit -m "feat: buscador compartido de copropietarios en form de poderes con aviso de mora"
```

---

### Task 11: Super-admin tenant/show — copropietarios paginados

**Files:**
- Modify: `app/Http/Controllers/SuperAdmin/TenantController.php:75-92` (método `show`)
- Modify: `resources/js/Pages/SuperAdmin/Tenants/Show.jsx`
- Test: `tests/Feature/SuperAdmin/TenantShowCopropietariosTest.php`

- [x] **Step 1: Test que falla**

Crear `tests/Feature/SuperAdmin/TenantShowCopropietariosTest.php`:

```php
<?php

use App\Models\Copropietario;
use App\Models\Tenant;
use App\Models\User;

test('tenant show incluye copropietarios paginados', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $superAdmin = User::factory()->create(['rol' => 'super_admin', 'tenant_id' => null, 'activo' => true]);

    Copropietario::factory()->count(20)->create(['tenant_id' => $tenant->id]);

    $manifest = public_path('build/manifest.json');
    $version = file_exists($manifest) ? hash_file('xxh128', $manifest) : '';

    $response = $this->actingAs($superAdmin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
        ->get("/super-admin/tenants/{$tenant->id}");

    $response->assertStatus(200);
    $props = $response->json('props');
    expect($props)->toHaveKey('copropietarios')
        ->and(count($props['copropietarios']['data']))->toBe(15)
        ->and($props['copropietarios']['total'])->toBe(20);
});
```

Nota: verificar la URL real del show con `./sail artisan route:list | grep "super-admin/tenants"` y ajustar si difiere.

- [x] **Step 2: Correr para confirmar que falla**

Run: `./sail artisan test tests/Feature/SuperAdmin/TenantShowCopropietariosTest.php --no-coverage`
Expected: FAIL — prop `copropietarios` inexistente.

- [x] **Step 3: Backend**

En `TenantController::show()`, después del cálculo de `$stats`, agregar:

```php
        $copropietarios = Copropietario::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('es_externo', false)
            ->with('unidades')
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($c) => [
                'id'          => $c->id,
                'nombre'      => $c->nombre,
                'documento'   => $c->numero_documento,
                'unidades'    => $c->unidades->pluck('numero')->join(', '),
                'coeficiente' => (float) $c->unidades->sum('coeficiente'),
                'activo'      => (bool) $c->activo,
                'en_mora'     => (bool) $c->en_mora,
            ]);

        return Inertia::render('SuperAdmin/Tenants/Show', compact('tenant', 'stats', 'reuniones', 'copropietarios'));
```

(reemplazando el `return` existente).

- [x] **Step 4: Frontend**

En `resources/js/Pages/SuperAdmin/Tenants/Show.jsx`:

1. Firma del componente: agregar `copropietarios = { data: [], links: [] }` a los props.
2. Importar `Link` de `@inertiajs/react` si no está.
3. Agregar al final del contenido de la página (después de la sección de reuniones existente):

```jsx
            {/* Copropietarios */}
            <div className="mt-6 bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div className="px-5 py-4 border-b border-gray-100">
                    <h3 className="font-semibold text-gray-800 text-sm">Copropietarios ({copropietarios.total ?? copropietarios.data.length})</h3>
                </div>
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-xs text-gray-400 uppercase tracking-wide">
                            <th className="px-5 py-2.5">Nombre</th>
                            <th className="px-5 py-2.5">Documento</th>
                            <th className="px-5 py-2.5">Unidades</th>
                            <th className="px-5 py-2.5 text-right">Coeficiente</th>
                            <th className="px-5 py-2.5">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {copropietarios.data.map(c => (
                            <tr key={c.id} className="border-t border-gray-100">
                                <td className="px-5 py-2.5 font-medium text-gray-700">{c.nombre}</td>
                                <td className="px-5 py-2.5 text-gray-500">{c.documento ?? '—'}</td>
                                <td className="px-5 py-2.5 text-gray-500">{c.unidades || '—'}</td>
                                <td className="px-5 py-2.5 text-right tabular-nums text-gray-500">{c.coeficiente.toFixed(2)}%</td>
                                <td className="px-5 py-2.5">
                                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${c.activo ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'}`}>
                                        {c.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                    {c.en_mora && (
                                        <span className="ml-1 inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600">En mora</span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {copropietarios.links?.length > 3 && (
                    <div className="px-5 py-3 border-t border-gray-100 flex flex-wrap gap-1">
                        {copropietarios.links.map((l, i) => (
                            <Link
                                key={i}
                                href={l.url ?? '#'}
                                preserveScroll
                                className={`px-2.5 py-1 rounded text-xs ${l.active ? 'bg-blue-600 text-white' : l.url ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300 pointer-events-none'}`}
                                dangerouslySetInnerHTML={{ __html: l.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
```

- [x] **Step 5: Correr test, compilar, suite y commit**

Run: `./sail artisan test tests/Feature/SuperAdmin/TenantShowCopropietariosTest.php --no-coverage && ./sail npm run build && ./sail artisan test --no-coverage`
Expected: verde.

```bash
git add app/Http/Controllers/SuperAdmin/TenantController.php \
        resources/js/Pages/SuperAdmin/Tenants/Show.jsx \
        tests/Feature/SuperAdmin/TenantShowCopropietariosTest.php
git commit -m "feat: indice paginado de copropietarios en tenant show del super-admin"
```

---

### Task 12: Lista de acceso — búsqueda, paginación, PIN oculto y acceso desde Show

**Files:**
- Modify: `app/Http/Controllers/Admin/AccesoReunionController.php:13-47` (método `show`)
- Modify: `resources/js/Pages/Admin/Reuniones/ListaAcceso.jsx` (reescritura)
- Modify: `resources/js/Pages/Admin/Reuniones/Show.jsx:289` (agregar link)
- Test: `tests/Feature/Admin/AccesoReunionTest.php` (agregar casos)

- [x] **Step 1: Tests de búsqueda y paginación (fallan)**

Agregar al final de `tests/Feature/Admin/AccesoReunionTest.php` (respetando el estilo/beforeEach existente del archivo — si no hay helper de admin, replicar el `beforeEach` de `VotacionLogTest.php`):

```php
test('lista de acceso pagina y filtra por busqueda', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador', 'activo' => true]);
    $reunion = Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => \App\Enums\ReunionEstado::AnteSala]);

    foreach (range(1, 30) as $i) {
        $c = Copropietario::factory()->create(['tenant_id' => $tenant->id, 'nombre' => "Copropietario {$i}"]);
        AccesoReunion::create([
            'copropietario_id' => $c->id,
            'reunion_id'       => $reunion->id,
            'pin_hash'         => bcrypt('000000'),
            'pin_plain'        => '000000',
            'activo'           => true,
        ]);
    }

    $manifest = public_path('build/manifest.json');
    $version = file_exists($manifest) ? hash_file('xxh128', $manifest) : '';

    // Paginación: 25 por página
    $props = $this->actingAs($admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
        ->get("/admin/reuniones/{$reunion->id}/lista-acceso")
        ->json('props');
    expect(count($props['accesos']['data']))->toBe(25)
        ->and($props['accesos']['total'])->toBe(30);

    // Búsqueda
    $props = $this->actingAs($admin)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
        ->get("/admin/reuniones/{$reunion->id}/lista-acceso?q=Copropietario 3")
        ->json('props');
    expect($props['accesos']['total'])->toBe(2); // "Copropietario 3" y "Copropietario 30"
});
```

(Agregar los `use` que falten al inicio del archivo: `App\Models\AccesoReunion`, `App\Models\Copropietario`, `App\Models\Reunion`, `App\Models\Tenant`, `App\Models\User`.)

- [x] **Step 2: Correr para confirmar que falla**

Run: `./sail artisan test tests/Feature/Admin/AccesoReunionTest.php --no-coverage`
Expected: el test nuevo falla (`accesos` es array plano, sin `data`/`total`).

- [x] **Step 3: Backend — paginación + búsqueda**

En `AccesoReunionController::show()`, reemplazar la firma y la consulta:

```php
    public function show(Reunion $reunion)
    {
```

por:

```php
    public function show(\Illuminate\Http\Request $request, Reunion $reunion)
    {
        $q = trim((string) $request->query('q', ''));
```

y reemplazar la cadena `->orderBy('activo', 'desc')->get()->map(...)` por:

```php
            ->when($q !== '', fn ($query) => $query->whereHas('copropietario', fn ($c) =>
                $c->where('nombre', 'like', "%{$q}%")
                  ->orWhere('numero_documento', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhereHas('unidades', fn ($u) => $u->where('numero', 'like', "%{$q}%"))
            ))
            ->orderBy('activo', 'desc')
            ->paginate(25)
            ->withQueryString()
            ->through(fn($a) => [
                'id'               => $a->id,
                'nombre'           => $a->copropietario->email
                                        ? ($a->copropietario->email)
                                        : $a->copropietario->numero_documento,
                'numero_documento' => $a->copropietario->numero_documento,
                'email'            => $a->copropietario->email,
                'unidades'         => $a->copropietario->unidades->pluck('numero')->join(', '),
                'pin'              => $a->pin_plain,
                'activo'           => $a->activo,
                'es_externo'       => $a->copropietario->es_externo,
            ]);
```

y en el render agregar el filtro actual:

```php
        return Inertia::render('Admin/Reuniones/ListaAcceso', [
            'reunion' => $reunion->only('id', 'titulo', 'estado', 'fecha_programada', 'convocatoria_envios'),
            'accesos' => $accesos,
            'filtro'  => $q,
        ]);
```

- [x] **Step 4: Frontend — reescribir ListaAcceso.jsx**

Reescribir `resources/js/Pages/Admin/Reuniones/ListaAcceso.jsx`. Conservar del archivo actual: imports, layout, y los handlers de `reenviar`/`desactivar`/`activar` (rutas `POST .../lista-acceso/{acceso}/reenviar`, `PATCH .../desactivar`, `PATCH .../activar`). Cambios estructurales:

1. Props: `{ reunion, accesos, filtro = '' }` — `accesos` ahora es paginador (`accesos.data`, `accesos.links`, `accesos.total`).
2. Una sola tabla (columna Estado con badge Activo/Inactivo en lugar de dos tablas separadas), iterando `accesos.data`.
3. Input de búsqueda con debounce que navega con Inertia:

```jsx
import { useState } from 'react'
import { router } from '@inertiajs/react'

// dentro del componente:
const [q, setQ] = useState(filtro)
const buscar = (valor) => {
    setQ(valor)
    clearTimeout(window.__laTimer)
    window.__laTimer = setTimeout(() => {
        router.get(`/admin/reuniones/${reunion.id}/lista-acceso`, valor ? { q: valor } : {}, {
            preserveState: true, preserveScroll: true, replace: true,
        })
    }, 300)
}

// en el JSX, encima de la tabla:
<input
    type="text"
    value={q}
    onChange={e => buscar(e.target.value)}
    placeholder="Buscar por nombre, documento, email o unidad…"
    className="w-full sm:w-80 border border-gray-300 rounded-lg px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-blue-500"
/>
```

4. PIN oculto por defecto con revelado por fila:

```jsx
const [pinesVisibles, setPinesVisibles] = useState({})
const togglePin = (id) => setPinesVisibles(prev => ({ ...prev, [id]: !prev[id] }))

// celda de PIN:
<td className="px-4 py-3 text-center font-mono">
    {a.pin ? (
        <button
            type="button"
            onClick={() => togglePin(a.id)}
            title={pinesVisibles[a.id] ? 'Ocultar PIN' : 'Mostrar PIN'}
            className="tracking-widest text-gray-700 hover:text-blue-600"
        >
            {pinesVisibles[a.id] ? a.pin : '••••••'}
        </button>
    ) : (
        <span className="text-app-text-muted font-normal text-xs">—</span>
    )}
</td>
```

5. Links de paginación al pie de la tabla:

```jsx
{accesos.links?.length > 3 && (
    <div className="px-4 py-3 border-t border-gray-100 flex flex-wrap gap-1">
        {accesos.links.map((l, i) => (
            <Link
                key={i}
                href={l.url ?? '#'}
                preserveScroll
                preserveState
                className={`px-2.5 py-1 rounded text-xs ${l.active ? 'bg-blue-600 text-white' : l.url ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300 pointer-events-none'}`}
                dangerouslySetInnerHTML={{ __html: l.label }}
            />
        ))}
    </div>
)}
```

(importar `Link` de `@inertiajs/react` si no está).

- [x] **Step 5: Link desde Reuniones/Show**

En `resources/js/Pages/Admin/Reuniones/Show.jsx`, junto a los botones de reportes (línea ~289), agregar antes del primer `<a href=.../reporte/pdf`:

```jsx
                            <Link href={`/admin/reuniones/${reunion.id}/lista-acceso`}
                                className="px-3 py-1.5 rounded-lg text-sm border border-surface-border text-app-text-primary hover:bg-surface-hover transition-colors">
                                Lista de acceso
                            </Link>
```

- [x] **Step 6: Correr tests, compilar, suite y commit**

Run: `./sail artisan test tests/Feature/Admin/AccesoReunionTest.php --no-coverage && ./sail npm run build && ./sail artisan test --no-coverage`
Expected: verde (si otros tests del archivo asumían `accesos` plano, actualizarlos a `accesos.data`).

```bash
git add app/Http/Controllers/Admin/AccesoReunionController.php \
        resources/js/Pages/Admin/Reuniones/ListaAcceso.jsx \
        resources/js/Pages/Admin/Reuniones/Show.jsx \
        tests/Feature/Admin/AccesoReunionTest.php
git commit -m "feat: lista de acceso con busqueda, paginacion y PIN oculto; link desde reunion show"
```

---

### Task 13: Texto de resultados neutral

**Files:**
- Modify: `resources/js/Pages/Admin/Reuniones/Conducir.jsx:562-566`
- Modify: `resources/js/Pages/Copropietario/Sala/Show.jsx:346-348`

- [x] **Step 1: Conducir — resultado legal o mayor votación**

Reemplazar (línea ~562):

```jsx
                                                {vGanadora && (
                                                    <p className="text-xs text-green-700 font-medium mb-1.5">
                                                        Ganó: {vGanadora.texto} ({vTotalPeso > 0 ? ((parseFloat(vGanadora.peso_total) / vTotalPeso) * 100).toFixed(1) : 0}%)
                                                    </p>
                                                )}
```

por:

```jsx
                                                {vGanadora && (
                                                    <p className={`text-xs font-medium mb-1.5 ${v.resultado === 'rechazada' ? 'text-red-700' : 'text-green-700'}`}>
                                                        {v.tipo_decision && v.resultado !== 'pendiente'
                                                            ? `Resultado: ${v.resultado === 'aprobada' ? 'Aprobada' : 'Rechazada'}`
                                                            : `Mayor votación: ${vGanadora.texto}`}
                                                        {' '}({vTotalPeso > 0 ? ((parseFloat(vGanadora.peso_total) / vTotalPeso) * 100).toFixed(1) : 0}%)
                                                    </p>
                                                )}
```

- [x] **Step 2: Sala — feed neutral**

En `resources/js/Pages/Copropietario/Sala/Show.jsx` (línea ~347), reemplazar:

```jsx
                            Ganó: {item.ganadora} ({item.ganadora_pct}%)
```

por:

```jsx
                            Mayor votación: {item.ganadora} ({item.ganadora_pct}%)
```

- [x] **Step 3: Verificar que no queden "Ganó"**

Run: `grep -rn "Ganó" resources/js app/ resources/views/`
Expected: sin resultados.

- [x] **Step 4: Compilar y commit**

Run: `./sail npm run build`

```bash
git add resources/js/Pages/Admin/Reuniones/Conducir.jsx resources/js/Pages/Copropietario/Sala/Show.jsx
git commit -m "feat: lenguaje neutral en resultados de votacion (Resultado/Mayor votacion)"
```

---

### Task 14: Super-admin — exponer `restringir_voto_morosos` en create/edit de tenants

**Files:**
- Modify: `app/Http/Controllers/SuperAdmin/TenantController.php:33,47,105`
- Modify: `resources/js/Pages/SuperAdmin/Tenants/Create.jsx:7,42-43`
- Modify: `resources/js/Pages/SuperAdmin/Tenants/Edit.jsx:9` y campo correspondiente
- Test: `tests/Feature/SuperAdmin/TenantRestriccionMorososTest.php`

- [x] **Step 1: Test que falla**

Crear `tests/Feature/SuperAdmin/TenantRestriccionMorososTest.php`:

```php
<?php

use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['rol' => 'super_admin', 'tenant_id' => null, 'activo' => true]);
});

test('super admin puede crear tenant con restriccion de morosos desactivada', function () {
    $response = $this->actingAs($this->superAdmin)->post('/super-admin/tenants', [
        'nombre'                    => 'Conjunto Test Morosos',
        'max_poderes_por_delegado'  => 2,
        'restringir_voto_morosos'   => false,
    ]);

    $tenant = Tenant::withoutGlobalScopes()->where('nombre', 'Conjunto Test Morosos')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->restringir_voto_morosos)->toBeFalse();
});

test('super admin puede actualizar la restriccion de morosos', function () {
    $tenant = Tenant::factory()->create(['restringir_voto_morosos' => true]);

    $this->actingAs($this->superAdmin)->put("/super-admin/tenants/{$tenant->id}", [
        'nombre'                   => $tenant->nombre,
        'max_poderes_por_delegado' => $tenant->max_poderes_por_delegado,
        'restringir_voto_morosos'  => false,
    ]);

    expect($tenant->fresh()->restringir_voto_morosos)->toBeFalse();
});
```

Nota: si el `store` del controller exige más campos requeridos (revisar la validación en `TenantController::store`), agregarlos al payload del test con valores válidos.

- [x] **Step 2: Correr para confirmar que falla**

Run: `./sail artisan test tests/Feature/SuperAdmin/TenantRestriccionMorososTest.php --no-coverage`
Expected: FAIL — el campo se ignora (queda default `true`).

- [x] **Step 3: Backend**

En `TenantController`, en las validaciones de `store` (línea ~33) y `update` (línea ~105), agregar:

```php
            'restringir_voto_morosos' => 'boolean',
```

En el array de creación de `store` (línea ~47), agregar:

```php
                'restringir_voto_morosos' => $data['restringir_voto_morosos'] ?? true,
```

En `update`, verificar que el `update($data)` incluya el campo (si el update es con lista explícita de claves, agregarla).

- [x] **Step 4: Frontend**

En `Create.jsx`: agregar al `useForm` inicial (línea ~7): `restringir_voto_morosos: true,` y después del campo de `max_poderes_por_delegado` (línea ~42):

```jsx
                    <label className="flex items-center gap-2.5 mt-3 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={data.restringir_voto_morosos}
                            onChange={e => setData('restringir_voto_morosos', e.target.checked)}
                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span className="text-sm text-gray-700">
                            Restringir voto de copropietarios en mora
                            <span className="block text-xs text-gray-400">Art. 38, Ley 675 de 2001 — el admin del conjunto puede cambiarlo luego</span>
                        </span>
                    </label>
```

En `Edit.jsx`: agregar al `useForm` (línea ~9): `restringir_voto_morosos: tenant.restringir_voto_morosos,` y el mismo checkbox junto al campo de max poderes.

- [x] **Step 5: Correr tests, compilar, suite y commit**

Run: `./sail artisan test tests/Feature/SuperAdmin/ --no-coverage && ./sail npm run build && ./sail artisan test --no-coverage`
Expected: verde.

```bash
git add app/Http/Controllers/SuperAdmin/TenantController.php \
        resources/js/Pages/SuperAdmin/Tenants/Create.jsx \
        resources/js/Pages/SuperAdmin/Tenants/Edit.jsx \
        tests/Feature/SuperAdmin/TenantRestriccionMorososTest.php
git commit -m "feat: toggle restringir_voto_morosos en create/edit de tenants (super-admin)"
```

---

### Task 15: Fechas/hora en `America/Bogota`

**Files:**
- Create: `resources/js/utils/fecha.js`
- Modify: `resources/js/Pages/Copropietario/Sala/Show.jsx`, `resources/js/Pages/SuperAdmin/Tenants/Padron.jsx`, `resources/js/Pages/Admin/Reuniones/Show.jsx`, `resources/js/Pages/SuperAdmin/Tenants/Show.jsx` (usos de `toLocale*`)
- Modify: `resources/views/reportes/acta.blade.php` (salidas de fecha)

**Interfaces:**
- Produces: `fechaCorta(iso)`, `fechaHora(iso)`, `hora(iso)` exportadas desde `resources/js/utils/fecha.js`. Todas devuelven `'—'` con entrada falsy.

- [x] **Step 1: Crear el helper**

Crear `resources/js/utils/fecha.js`:

```js
const TZ = 'America/Bogota'
const LOCALE = 'es-CO'

const safe = (iso, formatter) => {
    if (!iso) return '—'
    const d = new Date(iso)
    return isNaN(d) ? '—' : formatter.format(d)
}

const fmtFechaCorta = new Intl.DateTimeFormat(LOCALE, { dateStyle: 'medium', timeZone: TZ })
const fmtFechaHora  = new Intl.DateTimeFormat(LOCALE, { dateStyle: 'medium', timeStyle: 'short', timeZone: TZ })
const fmtHora       = new Intl.DateTimeFormat(LOCALE, { timeStyle: 'short', timeZone: TZ })

export const fechaCorta = (iso) => safe(iso, fmtFechaCorta)   // 2 jul 2026
export const fechaHora  = (iso) => safe(iso, fmtFechaHora)    // 2 jul 2026, 3:45 p. m.
export const hora       = (iso) => safe(iso, fmtHora)         // 3:45 p. m.
```

- [x] **Step 2: Barrido frontend**

Run: `grep -rn "toLocaleDateString\|toLocaleString\|toLocaleTimeString" resources/js/Pages resources/js/Components`

Para cada ocurrencia en los 4 archivos detectados (`Copropietario/Sala/Show.jsx`, `SuperAdmin/Tenants/Padron.jsx`, `Admin/Reuniones/Show.jsx`, `SuperAdmin/Tenants/Show.jsx`), aplicar la regla mecánica:

- `new Date(x).toLocaleDateString(...)` → `fechaCorta(x)`
- `new Date(x).toLocaleString(...)` → `fechaHora(x)`
- `new Date(x).toLocaleTimeString(...)` → `hora(x)`

agregando el import en cada archivo: `import { fechaCorta, fechaHora, hora } from '@/utils/fecha'` (importar solo las funciones usadas). Si una ocurrencia ya formatea dentro de una función local (ej. `formatTime` en la sala), reemplazar el **cuerpo** de esa función para delegar en el helper, sin cambiar sus call-sites.

Al terminar, repetir el grep: `toLocale*` no debe aparecer en `resources/js` (salvo usos no relacionados con fechas, si los hubiera — documentarlos en el commit).

- [x] **Step 3: Backend — acta PDF**

Run: `grep -n "fecha\|created_at\|hora" resources/views/reportes/acta.blade.php`

Para cada salida de un atributo Carbon (ej. `{{ $reunion->fecha_inicio }}`, `{{ $a->hora_confirmacion }}`, `{{ $log->created_at }}`), aplicar la regla mecánica:

```blade
{{ $modelo->campo?->timezone('America/Bogota')->format('d/m/Y h:i A') }}
```

(para campos solo-fecha usar `->format('d/m/Y')`). Verificar `config/app.php`: `timezone` debe seguir en `'UTC'` — la DB guarda UTC y la conversión es solo al mostrar. No cambiar `app.timezone`.

- [x] **Step 4: Compilar, verificar y suite**

Run: `./sail npm run build && ./sail artisan test --no-coverage`
Expected: verde. Manual: una reunión con `fecha_programada` conocida muestra la hora Bogotá (UTC-5) en Show del admin, sala y acta PDF.

- [x] **Step 5: Commit**

```bash
git add resources/js/utils/fecha.js resources/js/Pages resources/views/reportes/acta.blade.php
git commit -m "feat: fechas en zona America/Bogota con helper central es-CO en frontend y acta"
```

---

## Verificación final del plan

- [x] Suite completa: `./sail artisan test --no-coverage` → verde.
- [x] Build: `./sail npm run build` → OK.
- [x] Prueba E2E manual del flujo crítico: ante-sala → entrada de copropietarios (quórum correcto sin doble conteo) → votación calificada_70 bloqueada con <70% → moroso bloqueado directo y vía poder → cierre con snapshots → acta con quórum por votación y fechas Bogotá.
