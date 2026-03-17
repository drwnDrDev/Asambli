# Copropietario Sala Real-time Experience — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dar al copropietario una experiencia en tiempo real completa en `Sala/Show.jsx`: modal de confirmación de voto, flip card post-voto con resultados en vivo, status bar con conexión/estado/quórum, y feed cronológico de la asamblea.

**Architecture:** Nuevo evento público `ResultadosPublicosVotacion` (datos reducidos, sin ticker de unidad) disparado desde el Job existente junto al evento privado ya existente para el admin. Nuevo evento público `EstadoReunionCambiado` disparado desde `ReunionTransicionService::transicionar()` (punto canónico de todas las transiciones). `SalaReunionController::show()` enriquece los props con `feedInicial` (desde `ReunionLog` + votaciones cerradas) y `resultadosActuales`. Frontend rediseñado con estética "Cámara Cívica Digital": dark navy + ámbar + Fraunces.

**Tech Stack:** Laravel 12, Pest, React 18, Inertia.js, Laravel Reverb/Echo, Tailwind CSS, CSS Custom Properties

**Spec:** `docs/superpowers/specs/2026-03-17-copropietario-sala-realtime-design.md`

---

## Mapa de Archivos

| Archivo | Acción | Responsabilidad |
|---------|--------|-----------------|
| `app/Events/ResultadosPublicosVotacion.php` | Crear | Evento público de resultados sin datos de quién votó |
| `app/Events/EstadoReunionCambiado.php` | Crear | Evento público de cambio de estado de reunión |
| `app/Jobs/RecalcularResultadosVotacion.php` | Modificar | Disparar también `ResultadosPublicosVotacion` |
| `app/Services/ReunionTransicionService.php` | Modificar | Disparar `EstadoReunionCambiado` después de `$reunion->save()` |
| `app/Http/Controllers/Copropietario/SalaReunionController.php` | Modificar | Añadir `feedInicial`, `estadoReunion`, `resultadosActuales` a props + expandir filtro historial |
| `resources/css/sala-theme.css` | Crear | Variables CSS dark navy + ámbar + fuente Fraunces |
| `resources/css/app.css` | Modificar | Importar `sala-theme.css` |
| `resources/js/Pages/Copropietario/Sala/Show.jsx` | Reescribir | Status bar, card flip, modal confirmación, feed cronológico |

**No tocar:**
- `app/Events/ResultadosVotacionActualizados.php` — canal privado admin intacto
- `app/Services/VotoService.php` — lógica de voto intacta
- `resources/js/Pages/Admin/Reuniones/Conducir.jsx`
- `resources/js/Pages/Admin/Reuniones/Proyeccion.jsx`

---

## Chunk 1: Nuevos Eventos Backend

### Task 1: Crear evento `ResultadosPublicosVotacion`

**Files:**
- Create: `app/Events/ResultadosPublicosVotacion.php`
- Test: `tests/Feature/Events/ResultadosPublicosVotacionTest.php`

- [ ] **Step 1: Escribir el test**

```php
<?php
// tests/Feature/Events/ResultadosPublicosVotacionTest.php
use App\Events\ResultadosPublicosVotacion;
use Illuminate\Broadcasting\Channel;

it('broadcasts on the public reunion channel', function () {
    $votacion = \App\Models\Votacion::factory()->create();

    $event = new ResultadosPublicosVotacion($votacion, [
        ['opcion_id' => 1, 'texto' => 'SÍ', 'count' => 5, 'peso_total' => 0.5],
    ]);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(Channel::class)
        ->and($channels[0]->name)->toBe("reunion.{$votacion->reunion_id}");
});

it('broadcasts with reduced payload without ultimo_voto_unidad', function () {
    $votacion = \App\Models\Votacion::factory()->create();
    $resultados = [
        ['opcion_id' => 1, 'texto' => 'SÍ', 'count' => 5, 'peso_total' => 0.5],
        ['opcion_id' => 2, 'texto' => 'NO', 'count' => 3, 'peso_total' => 0.3],
    ];

    $event = new ResultadosPublicosVotacion($votacion, $resultados);
    $payload = $event->broadcastWith();

    expect($payload)->toHaveKey('votacion_id', $votacion->id)
        ->and($payload)->toHaveKey('resultados')
        ->and($payload)->not->toHaveKey('ultimo_voto_unidad');
});
```

- [ ] **Step 2: Ejecutar y verificar que falla**

```bash
./sail artisan test tests/Feature/Events/ResultadosPublicosVotacionTest.php --no-coverage
```
Expected: FAIL — clase no existe.

- [ ] **Step 3: Crear el evento**

```php
<?php
// app/Events/ResultadosPublicosVotacion.php
namespace App\Events;

use App\Models\Votacion;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResultadosPublicosVotacion implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Votacion $votacion,
        public readonly array $resultados,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("reunion.{$this->votacion->reunion_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'votacion_id' => $this->votacion->id,
            'resultados'  => $this->resultados,
        ];
    }
}
```

- [ ] **Step 4: Ejecutar y verificar que pasa**

```bash
./sail artisan test tests/Feature/Events/ResultadosPublicosVotacionTest.php --no-coverage
```
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Events/ResultadosPublicosVotacion.php tests/Feature/Events/ResultadosPublicosVotacionTest.php
git commit -m "feat: add ResultadosPublicosVotacion public broadcast event"
```

---

### Task 2: Crear evento `EstadoReunionCambiado`

**Files:**
- Create: `app/Events/EstadoReunionCambiado.php`
- Test: `tests/Feature/Events/EstadoReunionCambiadoTest.php`

- [ ] **Step 1: Escribir el test**

```php
<?php
// tests/Feature/Events/EstadoReunionCambiadoTest.php
use App\Events\EstadoReunionCambiado;
use Illuminate\Broadcasting\Channel;

it('broadcasts on the public reunion channel', function () {
    $reunion = \App\Models\Reunion::factory()->create();

    $event = new EstadoReunionCambiado($reunion, 'en_curso');

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(Channel::class)
        ->and($channels[0]->name)->toBe("reunion.{$reunion->id}");
});

it('broadcasts estado and timestamp', function () {
    $reunion = \App\Models\Reunion::factory()->create();

    $event = new EstadoReunionCambiado($reunion, 'suspendida');
    $payload = $event->broadcastWith();

    expect($payload)->toHaveKey('estado', 'suspendida')
        ->and($payload)->toHaveKey('timestamp');
});
```

- [ ] **Step 2: Ejecutar y verificar que falla**

```bash
./sail artisan test tests/Feature/Events/EstadoReunionCambiadoTest.php --no-coverage
```
Expected: FAIL — clase no existe.

- [ ] **Step 3: Crear el evento**

```php
<?php
// app/Events/EstadoReunionCambiado.php
namespace App\Events;

use App\Models\Reunion;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EstadoReunionCambiado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Reunion $reunion,
        public readonly string $estado,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("reunion.{$this->reunion->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'estado'    => $this->estado,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 4: Ejecutar y verificar que pasa**

```bash
./sail artisan test tests/Feature/Events/EstadoReunionCambiadoTest.php --no-coverage
```
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Events/EstadoReunionCambiado.php tests/Feature/Events/EstadoReunionCambiadoTest.php
git commit -m "feat: add EstadoReunionCambiado public broadcast event"
```

---

## Chunk 2: Backend — Job + Service

### Task 3: Modificar Job para disparar `ResultadosPublicosVotacion`

**Files:**
- Modify: `app/Jobs/RecalcularResultadosVotacion.php`
- Test: `tests/Feature/Jobs/RecalcularResultadosVotacionTest.php` (nuevo o ampliar si existe)

- [ ] **Step 1: Verificar si existe test actual del Job**

```bash
ls tests/Feature/Jobs/ 2>/dev/null || echo "no existe"
```

- [ ] **Step 2: Escribir el test**

```php
<?php
// tests/Feature/Jobs/RecalcularResultadosVotacionTest.php
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
    // OpcionVotacionFactory no existe — crear directamente (OpcionVotacion no tiene tenant_id)
    \App\Models\OpcionVotacion::create(['votacion_id' => $votacion->id, 'texto' => 'SÍ', 'orden' => 1]);

    (new RecalcularResultadosVotacion($votacion->id))->handle();

    Event::assertDispatched(ResultadosVotacionActualizados::class);
    Event::assertDispatched(ResultadosPublicosVotacion::class, function ($e) use ($votacion) {
        return $e->votacion->id === $votacion->id
            && isset($e->resultados)
            && !isset($e->resultados[0]['ultimo_voto_unidad']);
    });
});
```

- [ ] **Step 3: Ejecutar y verificar que falla**

```bash
./sail artisan test tests/Feature/Jobs/RecalcularResultadosVotacionTest.php --no-coverage
```
Expected: FAIL — solo dispara un evento, no el nuevo.

- [ ] **Step 4: Modificar el Job**

Abrir `app/Jobs/RecalcularResultadosVotacion.php`. Al final del método `handle()`, después de la línea que hace `broadcast(new \App\Events\ResultadosVotacionActualizados(...))`, agregar:

```php
broadcast(new \App\Events\ResultadosPublicosVotacion($votacion, $resultados->toArray()));
```

El método `handle()` completo queda:

```php
public function handle(): void
{
    $votacion = \App\Models\Votacion::with('opciones')->withoutGlobalScopes()->find($this->votacionId);

    if (!$votacion) return;

    $resultados = $votacion->opciones->map(function ($opcion) use ($votacion) {
        $votos = \App\Models\Voto::withoutGlobalScopes()
            ->where('votacion_id', $votacion->id)
            ->where('opcion_id', $opcion->id);

        return [
            'opcion_id'  => $opcion->id,
            'texto'      => $opcion->texto,
            'count'      => $votos->count(),
            'peso_total' => (float) $votos->sum('peso'),
        ];
    });

    $ultimoVotoUnidad = null;
    if ($this->copropietarioId) {
        $copropietario = \App\Models\Copropietario::withoutGlobalScopes()
            ->with('unidades')
            ->find($this->copropietarioId);
        $ultimoVotoUnidad = $copropietario?->unidades->first()?->numero;
    }

    broadcast(new \App\Events\ResultadosVotacionActualizados($votacion, $resultados->toArray(), $ultimoVotoUnidad));
    broadcast(new \App\Events\ResultadosPublicosVotacion($votacion, $resultados->toArray()));
}
```

- [ ] **Step 5: Ejecutar y verificar que pasa**

```bash
./sail artisan test tests/Feature/Jobs/RecalcularResultadosVotacionTest.php --no-coverage
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Jobs/RecalcularResultadosVotacion.php tests/Feature/Jobs/RecalcularResultadosVotacionTest.php
git commit -m "feat: dispatch ResultadosPublicosVotacion from RecalcularResultadosVotacion job"
```

---

### Task 4: Modificar `ReunionTransicionService` para disparar `EstadoReunionCambiado`

**Files:**
- Modify: `app/Services/ReunionTransicionService.php`
- Test: `tests/Feature/Services/ReunionTransicionServiceTest.php` (nuevo o ampliar si existe)

- [ ] **Step 1: Verificar si existe test actual del Service**

```bash
ls tests/Feature/Services/ 2>/dev/null || echo "no existe"
```

- [ ] **Step 2: Escribir el test**

```php
<?php
// tests/Feature/Services/ReunionTransicionServiceTest.php
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
```

- [ ] **Step 3: Ejecutar y verificar que falla**

```bash
./sail artisan test tests/Feature/Services/ReunionTransicionServiceTest.php --no-coverage
```
Expected: FAIL — no se dispara el evento.

- [ ] **Step 4: Modificar el Service**

En `app/Services/ReunionTransicionService.php`, al final del método `transicionar()`, después de `ReunionLog::create([...])`, agregar:

```php
broadcast(new \App\Events\EstadoReunionCambiado($reunion, $nuevoEstado->value));
```

El final del método queda:

```php
        $reunion->save();

        ReunionLog::create([
            'reunion_id'  => $reunion->id,
            'user_id'     => $user->id,
            'accion'      => "estado_cambiado_a_{$nuevoEstado->value}",
            'observacion' => $observacion,
            'metadata'    => array_merge($metadata, ['estado_anterior' => $estadoActual->value]),
        ]);

        broadcast(new \App\Events\EstadoReunionCambiado($reunion, $nuevoEstado->value));
    }
```

- [ ] **Step 5: Ejecutar y verificar que pasa**

```bash
./sail artisan test tests/Feature/Services/ReunionTransicionServiceTest.php --no-coverage
```
Expected: PASS (2 tests).

- [ ] **Step 6: Correr suite completa para verificar no hay regresiones**

```bash
./sail artisan test --no-coverage
```
Expected: todos los tests previos siguen en PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/ReunionTransicionService.php tests/Feature/Services/ReunionTransicionServiceTest.php
git commit -m "feat: broadcast EstadoReunionCambiado from ReunionTransicionService"
```

---

## Chunk 3: Backend — SalaReunionController

### Task 5: Enriquecer `show()` y corregir `historial()`

**Files:**
- Modify: `app/Http/Controllers/Copropietario/SalaReunionController.php`
- Test: `tests/Feature/Copropietario/SalaReunionShowTest.php`

- [ ] **Step 1: Escribir el test**

```php
<?php
// tests/Feature/Copropietario/SalaReunionShowTest.php
use App\Enums\ReunionEstado;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\ReunionLog;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Votacion;
use App\Models\OpcionVotacion;

it('show includes estadoReunion in inertia props', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    Copropietario::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id]);

    $reunion = Reunion::factory()->create([
        'tenant_id' => $tenant->id,
        'estado'    => ReunionEstado::EnCurso,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->get("/sala/{$reunion->id}");

    $response->assertStatus(200);
    $props = $response->json('props');
    expect($props)->toHaveKey('estadoReunion')
        ->and($props['estadoReunion'])->toBe('en_curso');
});

it('show includes feedInicial with reunion log entries', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    Copropietario::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id]);

    $reunion = Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => ReunionEstado::EnCurso]);
    ReunionLog::create([
        'reunion_id'  => $reunion->id,
        'user_id'     => $user->id,
        'accion'      => 'estado_cambiado_a_en_curso',
        'observacion' => 'Iniciando',
        'metadata'    => [],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->get("/sala/{$reunion->id}");

    $props = $response->json('props');
    expect($props)->toHaveKey('feedInicial')
        ->and($props['feedInicial'])->toBeArray()
        ->and(count($props['feedInicial']))->toBeGreaterThanOrEqual(1);
});

it('show includes resultadosActuales only when copropietario already voted', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    $copro = Copropietario::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id]);

    $reunion = Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => ReunionEstado::EnCurso]);
    $votacion = Votacion::factory()->create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id, 'estado' => 'abierta']);
    // OpcionVotacionFactory no existe — crear directamente (OpcionVotacion no tiene tenant_id)
    \App\Models\OpcionVotacion::create(['votacion_id' => $votacion->id, 'texto' => 'SÍ', 'orden' => 1]);

    // Sin voto: resultadosActuales debe ser null
    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->get("/sala/{$reunion->id}");

    $props = $response->json('props');
    expect($props['resultadosActuales'])->toBeNull();
});

it('historial includes finalizada, cancelada, and reprogramada reuniones', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    Copropietario::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id]);

    Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => ReunionEstado::Finalizada]);
    Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => ReunionEstado::Cancelada]);
    Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => ReunionEstado::Reprogramada]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->get('/historial');

    $props = $response->json('props');
    expect(count($props['reuniones']))->toBe(3);
});
```

- [ ] **Step 2: Ejecutar y verificar que falla**

```bash
./sail artisan test tests/Feature/Copropietario/SalaReunionShowTest.php --no-coverage
```
Expected: FAIL (props nuevos no existen aún).

- [ ] **Step 3: Modificar `SalaReunionController`**

Reemplazar el método `show()` y `historial()` con:

```php
public function show(Reunion $reunion)
{
    $quorum = $this->quorumService->calcular($reunion);
    $copropietario = Copropietario::where('user_id', auth()->id())->first();

    $poderes = $copropietario
        ? Poder::withoutGlobalScopes()
            ->where('reunion_id', $reunion->id)
            ->where('apoderado_id', $copropietario->id)
            ->with('poderdante.user')
            ->get()
        : collect();

    $votacionAbierta = $reunion->votaciones()->with('opciones')->where('estado', 'abierta')->first();

    $yaVotoPor = [];
    if ($votacionAbierta && $copropietario) {
        $yaVotoPor = Voto::withoutGlobalScopes()
            ->where('votacion_id', $votacionAbierta->id)
            ->where(function ($q) use ($copropietario) {
                $q->where('copropietario_id', $copropietario->id)
                  ->orWhere('en_nombre_de', $copropietario->id);
            })
            ->pluck('en_nombre_de')
            ->map(fn($v) => $v ?? 'propio')
            ->toArray();
    }

    // Resultados actuales: solo si ya votó y hay votación abierta
    $resultadosActuales = null;
    if ($votacionAbierta && !empty($yaVotoPor)) {
        $resultadosActuales = $votacionAbierta->opciones->map(function ($opcion) use ($votacionAbierta) {
            $votos = \App\Models\Voto::withoutGlobalScopes()
                ->where('votacion_id', $votacionAbierta->id)
                ->where('opcion_id', $opcion->id);
            return [
                'opcion_id'  => $opcion->id,
                'texto'      => $opcion->texto,
                'count'      => $votos->count(),
                'peso_total' => (float) $votos->sum('peso'),
            ];
        })->toArray();
    }

    // Feed inicial: logs de estado + votaciones cerradas con ganador
    $feedInicial = $this->buildFeedInicial($reunion);

    $estadoReunion = $reunion->estado instanceof \App\Enums\ReunionEstado
        ? $reunion->estado->value
        : $reunion->estado;

    return Inertia::render('Copropietario/Sala/Show', compact(
        'reunion', 'quorum', 'poderes', 'yaVotoPor', 'votacionAbierta',
        'resultadosActuales', 'feedInicial', 'estadoReunion'
    ));
}

private function buildFeedInicial(Reunion $reunion): array
{
    $items = collect();

    // 1. Logs de estado de la reunión desde ReunionLog
    $logs = \App\Models\ReunionLog::withoutGlobalScopes()
        ->where('reunion_id', $reunion->id)
        ->where('accion', 'like', 'estado_cambiado_a_%')
        ->orderBy('created_at')
        ->get();

    foreach ($logs as $log) {
        $estado = str_replace('estado_cambiado_a_', '', $log->accion);
        $items->push([
            'tipo'      => 'estado_reunion',
            'estado'    => $estado,
            'timestamp' => $log->created_at->toIso8601String(),
        ]);
    }

    // 2. Votaciones cerradas con opción ganadora
    $votacionesCerradas = $reunion->votaciones()
        ->with('opciones')
        ->where('estado', 'cerrada')
        ->orderBy('updated_at')
        ->get();

    foreach ($votacionesCerradas as $votacion) {
        $ganadora = null;
        $pesoMax = -1;
        $pesoTotal = 0;

        foreach ($votacion->opciones as $opcion) {
            $peso = (float) \App\Models\Voto::withoutGlobalScopes()
                ->where('votacion_id', $votacion->id)
                ->where('opcion_id', $opcion->id)
                ->sum('peso');
            $pesoTotal += $peso;
            if ($peso > $pesoMax) {
                $pesoMax = $peso;
                $ganadora = $opcion;
            }
        }

        $pct = $pesoTotal > 0 ? round(($pesoMax / $pesoTotal) * 100, 1) : 0;

        $items->push([
            'tipo'         => 'votacion_cerrada',
            'votacion_id'  => $votacion->id,
            'pregunta'     => $votacion->pregunta,
            'ganadora'     => $ganadora?->texto,
            'ganadora_pct' => $pct,
            'timestamp'    => $votacion->updated_at->toIso8601String(),
        ]);
    }

    return $items->sortBy('timestamp')->values()->toArray();
}

public function historial()
{
    // withoutGlobalScopes() + tenant_id explícito — mismo patrón que show() e index()
    $copropietario = Copropietario::where('user_id', auth()->id())->first();
    $tenantId = $copropietario?->tenant_id ?? app('current_tenant')->id;

    $reuniones = Reunion::withoutGlobalScopes()
        ->where('tenant_id', $tenantId)
        ->whereIn('estado', ['finalizada', 'cancelada', 'reprogramada'])
        ->latest()
        ->get();

    return Inertia::render('Copropietario/Sala/Historial', compact('reuniones'));
}
```

- [ ] **Step 4: Ejecutar y verificar que pasa**

```bash
./sail artisan test tests/Feature/Copropietario/SalaReunionShowTest.php --no-coverage
```
Expected: PASS (4 tests).

- [ ] **Step 5: Correr suite completa**

```bash
./sail artisan test --no-coverage
```
Expected: sin regresiones.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Copropietario/SalaReunionController.php tests/Feature/Copropietario/SalaReunionShowTest.php
git commit -m "feat: enrich SalaReunionController with feedInicial, estadoReunion, resultadosActuales"
```

---

## Chunk 4: Frontend — CSS Theme + Show.jsx Redesign

### Task 6: CSS — sala-theme.css con variables dark navy + ámbar + Fraunces

**Files:**
- Create: `resources/css/sala-theme.css`
- Modify: `resources/css/app.css`

- [ ] **Step 1: Crear `sala-theme.css`**

```css
/* resources/css/sala-theme.css */
@import url('https://fonts.bunny.net/css?family=fraunces:400,600,700ital&display=swap');

:root {
  /* ── Sala dark background ─────────────── */
  --sala-bg:             #0a0f1e;
  --sala-surface:        #111827;
  --sala-surface-raised: #1a2235;
  --sala-border:         #1e2d45;

  /* ── Accent: amber (votación activa) ───── */
  --sala-amber:          #f59e0b;
  --sala-amber-dark:     #d97706;
  --sala-amber-glow:     rgba(245, 158, 11, 0.15);
  --sala-amber-border:   rgba(245, 158, 11, 0.4);

  /* ── Status colors ──────────────────────── */
  --sala-green:          #10b981;
  --sala-green-bg:       rgba(16, 185, 129, 0.12);
  --sala-orange:         #f97316;
  --sala-orange-bg:      rgba(249, 115, 22, 0.12);
  --sala-red:            #ef4444;
  --sala-red-bg:         rgba(239, 68, 68, 0.10);
  --sala-blue:           #3b82f6;
  --sala-blue-bg:        rgba(59, 130, 246, 0.10);

  /* ── Text ───────────────────────────────── */
  --sala-text:           #e8edf5;
  --sala-text-muted:     #6b7a99;
  --sala-text-faint:     #3d4f6e;

  /* ── Typography ─────────────────────────── */
  --sala-font-display:   'Fraunces', Georgia, serif;
  --sala-font-body:      'DM Sans', sans-serif;
}
```

- [ ] **Step 2: Importar en `app.css`**

Abrir `resources/css/app.css` y agregar al principio (después de `@import './admin-theme.css'` si existe, o al inicio):

```css
@import './sala-theme.css';
```

- [ ] **Step 3: Compilar y verificar que no hay errores**

```bash
./sail npm run build 2>&1 | tail -20
```
Expected: build exitoso sin errores.

- [ ] **Step 4: Commit**

```bash
git add resources/css/sala-theme.css resources/css/app.css
git commit -m "feat: add sala-theme CSS with dark navy, amber accents, and Fraunces font"
```

---

### Task 7: Reescribir `Sala/Show.jsx`

**Files:**
- Modify: `resources/js/Pages/Copropietario/Sala/Show.jsx`

**Nota:** Este es el rediseño completo. El componente anterior de 152 líneas se reemplaza íntegramente. El `SalaLayout` y el canal de presencia se mantienen.

- [ ] **Step 1: Reemplazar `Show.jsx` con el nuevo diseño**

```jsx
// resources/js/Pages/Copropietario/Sala/Show.jsx
import { useState, useEffect, useRef } from 'react'
import { router } from '@inertiajs/react'
import SalaLayout from '@/Layouts/SalaLayout'
import echo from '@/echo'

// ─── helpers ─────────────────────────────────────────────────────────────────

const TERMINAL_STATES = ['finalizada', 'cancelada', 'reprogramada']

function calcPct(pesoTotal, allResultados) {
    const suma = allResultados.reduce((acc, r) => acc + r.peso_total, 0)
    if (!suma) return 0
    return Math.round((pesoTotal / suma) * 100 * 10) / 10
}

function formatTime(isoString) {
    if (!isoString) return ''
    return new Date(isoString).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' })
}

// ─── sub-components ───────────────────────────────────────────────────────────

function ConnectionDot({ status }) {
    const colors = {
        connected:     'bg-emerald-400',
        reconnecting:  'bg-orange-400 animate-pulse',
        disconnected:  'bg-red-500',
    }
    const labels = {
        connected:    'Conectado',
        reconnecting: 'Reconectando…',
        disconnected: 'Sin conexión',
    }
    return (
        <span className="flex items-center gap-1.5 text-xs">
            <span className={`w-2 h-2 rounded-full ${colors[status]}`} />
            <span style={{ color: 'var(--sala-text-muted)' }}>{labels[status]}</span>
        </span>
    )
}

function EstadoBadge({ estado }) {
    const map = {
        en_curso:      { label: 'EN CURSO',      cls: 'text-emerald-400 border-emerald-800 bg-emerald-950/60' },
        ante_sala:     { label: 'ANTE SALA',      cls: 'text-blue-400 border-blue-800 bg-blue-950/60' },
        suspendida:    { label: 'SUSPENDIDA',     cls: 'text-orange-400 border-orange-800 bg-orange-950/60' },
        finalizada:    { label: 'FINALIZADA',     cls: 'text-red-400 border-red-900 bg-red-950/40' },
        cancelada:     { label: 'CANCELADA',      cls: 'text-red-400 border-red-900 bg-red-950/40' },
        reprogramada:  { label: 'REPROGRAMADA',   cls: 'text-red-400 border-red-900 bg-red-950/40' },
    }
    const { label, cls } = map[estado] ?? { label: estado?.toUpperCase(), cls: 'text-slate-400 border-slate-700' }
    return (
        <span className={`text-[10px] font-bold tracking-widest px-2 py-0.5 rounded border ${cls}`}>
            {label}
        </span>
    )
}

function QuorumPill({ quorum }) {
    const pct = quorum?.porcentaje_presente ?? 0
    const ok = quorum?.tiene_quorum
    return (
        <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${ok ? 'bg-emerald-900/50 text-emerald-400' : 'bg-slate-800 text-slate-400'}`}>
            {pct}% Q
        </span>
    )
}

function StatusBar({ connStatus, estadoReunion, quorum }) {
    return (
        <div
            className="sticky top-0 z-30 flex items-center justify-between px-4 py-2.5 border-b"
            style={{ background: '#0c111d', borderColor: 'var(--sala-border)' }}
        >
            <ConnectionDot status={connStatus} />
            <EstadoBadge estado={estadoReunion} />
            <QuorumPill quorum={quorum} />
        </div>
    )
}

function ConfirmModal({ opcion, onConfirm, onCancel, loading }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" style={{ background: 'rgba(0,0,0,0.75)' }}>
            <div
                className="w-full max-w-sm rounded-2xl p-6 shadow-2xl"
                style={{ background: 'var(--sala-surface-raised)', border: '1px solid var(--sala-border)' }}
            >
                <h3 className="text-base font-semibold mb-4" style={{ color: 'var(--sala-text)' }}>
                    Confirmar voto
                </h3>
                <p className="text-sm mb-3" style={{ color: 'var(--sala-text-muted)' }}>Vas a votar por:</p>
                <div
                    className="rounded-xl px-4 py-3 mb-4 text-sm font-semibold flex items-center gap-2"
                    style={{
                        border: '1.5px solid var(--sala-amber-border)',
                        background: 'var(--sala-amber-glow)',
                        color: 'var(--sala-amber)',
                    }}
                >
                    <span>✦</span>
                    <span>{opcion.texto}</span>
                </div>
                <p className="text-xs mb-6" style={{ color: 'var(--sala-text-muted)' }}>
                    Esta acción no se puede deshacer.
                </p>
                <div className="flex gap-3">
                    <button
                        onClick={onCancel}
                        disabled={loading}
                        className="flex-1 py-2.5 rounded-xl text-sm font-medium border transition disabled:opacity-50"
                        style={{ borderColor: 'var(--sala-border)', color: 'var(--sala-text-muted)' }}
                    >
                        Cancelar
                    </button>
                    <button
                        onClick={onConfirm}
                        disabled={loading}
                        className="flex-1 py-2.5 rounded-xl text-sm font-semibold transition disabled:opacity-50 active:scale-95"
                        style={{ background: 'var(--sala-amber)', color: '#0a0f1e' }}
                    >
                        {loading ? 'Enviando…' : 'Confirmar →'}
                    </button>
                </div>
            </div>
        </div>
    )
}

function ResultBar({ opcion, resultados, esVotada }) {
    const pct = calcPct(opcion.peso_total, resultados)
    return (
        <div className="mb-3">
            <div className="flex justify-between items-center mb-1">
                <span className={`text-sm font-medium ${esVotada ? '' : ''}`} style={{ color: esVotada ? 'var(--sala-green)' : 'var(--sala-text)' }}>
                    {esVotada && '✓ '}{opcion.texto}
                </span>
                <span className="text-xs tabular-nums" style={{ color: 'var(--sala-text-muted)' }}>{pct}%</span>
            </div>
            <div className="h-2 rounded-full overflow-hidden" style={{ background: 'var(--sala-border)' }}>
                <div
                    className="h-full rounded-full transition-all duration-700"
                    style={{
                        width: `${pct}%`,
                        background: esVotada ? 'var(--sala-green)' : 'var(--sala-amber)',
                    }}
                />
            </div>
        </div>
    )
}

function VotacionCard({ votacionActiva, resultados, yaVotoPor, poderes, onVotar, loading }) {
    const [pendingOpcion, setPendingOpcion] = useState(null)
    const yaVotoPropio = yaVotoPor.includes('propio')

    if (!votacionActiva) {
        return (
            <div
                className="rounded-2xl p-10 text-center mb-6"
                style={{ background: 'var(--sala-surface)', border: '1px solid var(--sala-border)' }}
            >
                <div className="text-4xl mb-4">⏳</div>
                <p className="text-sm" style={{ color: 'var(--sala-text-muted)' }}>
                    El administrador abrirá la siguiente votación.
                </p>
            </div>
        )
    }

    return (
        <>
            {pendingOpcion && (
                <ConfirmModal
                    opcion={pendingOpcion}
                    loading={loading}
                    onConfirm={() => {
                        onVotar(pendingOpcion.id, pendingOpcion._enNombreDe ?? null)
                        setPendingOpcion(null)
                    }}
                    onCancel={() => setPendingOpcion(null)}
                />
            )}

            <div
                className="rounded-2xl p-5 mb-6"
                style={{
                    background: 'var(--sala-surface)',
                    border: '1.5px solid var(--sala-amber-border)',
                    boxShadow: '0 0 24px var(--sala-amber-glow)',
                }}
            >
                <p className="text-[10px] font-bold tracking-widest mb-3" style={{ color: 'var(--sala-amber)' }}>
                    VOTACIÓN ABIERTA
                </p>

                <h2
                    className="text-xl font-semibold leading-snug mb-5"
                    style={{ fontFamily: 'var(--sala-font-display)', color: 'var(--sala-text)' }}
                >
                    {votacionActiva.pregunta}
                </h2>

                {/* Estado: pendiente de votar propio */}
                {!yaVotoPropio && (
                    <div className="mb-5">
                        <p className="text-[10px] uppercase tracking-widest mb-2" style={{ color: 'var(--sala-text-muted)' }}>Tu voto</p>
                        <div className="space-y-2">
                            {votacionActiva.opciones.map(opcion => (
                                <button
                                    key={opcion.id}
                                    onClick={() => setPendingOpcion(opcion)}
                                    disabled={loading}
                                    className="w-full py-3.5 text-sm font-semibold rounded-xl transition active:scale-95 disabled:opacity-50"
                                    style={{
                                        background: 'var(--sala-surface-raised)',
                                        border: '1px solid var(--sala-border)',
                                        color: 'var(--sala-text)',
                                    }}
                                    onMouseEnter={e => e.currentTarget.style.borderColor = 'var(--sala-amber)'}
                                    onMouseLeave={e => e.currentTarget.style.borderColor = 'var(--sala-border)'}
                                >
                                    {opcion.texto}
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {/* Estado: ya votó — mostrar resultados */}
                {yaVotoPropio && resultados && (
                    <div className="mb-4">
                        {resultados.map(r => (
                            <ResultBar
                                key={r.opcion_id}
                                opcion={r}
                                resultados={resultados}
                                esVotada={false}
                            />
                        ))}
                        <p className="text-xs mt-3 text-center" style={{ color: 'var(--sala-green)' }}>
                            ✓ Tu voto fue registrado
                        </p>
                    </div>
                )}

                {/* Poderes: votos en nombre de */}
                {poderes.map(poder => {
                    const yaVotoPoder = yaVotoPor.includes(poder.poderdante_id)
                    return (
                        <div key={poder.id} className="border-t pt-4 mt-4" style={{ borderColor: 'var(--sala-border)' }}>
                            <p className="text-[10px] uppercase tracking-widest mb-2" style={{ color: 'var(--sala-amber)' }}>
                                En nombre de: {poder.poderdante?.user?.name}
                            </p>
                            {!yaVotoPoder ? (
                                <div className="space-y-2">
                                    {votacionActiva.opciones.map(opcion => (
                                        <button
                                            key={opcion.id}
                                            onClick={() => setPendingOpcion({ ...opcion, _enNombreDe: poder.poderdante_id })}
                                            disabled={loading}
                                            className="w-full py-3 text-sm font-medium rounded-xl transition active:scale-95 disabled:opacity-50"
                                            style={{
                                                background: 'var(--sala-surface-raised)',
                                                border: '1px solid var(--sala-amber-border)',
                                                color: 'var(--sala-text)',
                                            }}
                                        >
                                            {opcion.texto}
                                        </button>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-xs" style={{ color: 'var(--sala-green)' }}>✓ Voto registrado</p>
                            )}
                        </div>
                    )
                })}
            </div>
        </>
    )
}

function FeedItem({ item }) {
    const iconMap = {
        en_curso:       { icon: '▶', color: 'var(--sala-green)',  bg: 'var(--sala-green-bg)' },
        ante_sala:      { icon: '◉', color: 'var(--sala-blue)',   bg: 'var(--sala-blue-bg)' },
        suspendida:     { icon: '⏸', color: 'var(--sala-orange)', bg: 'var(--sala-orange-bg)' },
        finalizada:     { icon: '⏹', color: 'var(--sala-red)',    bg: 'var(--sala-red-bg)' },
        cancelada:      { icon: '✕', color: 'var(--sala-red)',    bg: 'var(--sala-red-bg)' },
        reprogramada:   { icon: '↺', color: 'var(--sala-red)',    bg: 'var(--sala-red-bg)' },
    }
    const estadoLabels = {
        en_curso:     'Reunión iniciada',
        ante_sala:    'Ante sala abierta',
        suspendida:   'Reunión suspendida',
        finalizada:   'Reunión finalizada',
        cancelada:    'Reunión cancelada',
        reprogramada: 'Reunión reprogramada',
    }

    if (item.tipo === 'estado_reunion') {
        const { icon, color, bg } = iconMap[item.estado] ?? { icon: '●', color: 'var(--sala-text-muted)', bg: 'transparent' }
        return (
            <div className="flex items-center gap-3 py-2.5">
                <span className="w-7 h-7 rounded-full flex items-center justify-center text-xs flex-shrink-0" style={{ background: bg, color }}>
                    {icon}
                </span>
                <span className="text-sm flex-1" style={{ color: 'var(--sala-text-muted)' }}>
                    {estadoLabels[item.estado] ?? item.estado}
                </span>
                <span className="text-xs tabular-nums flex-shrink-0" style={{ color: 'var(--sala-text-faint)' }}>
                    {formatTime(item.timestamp)}
                </span>
            </div>
        )
    }

    if (item.tipo === 'votacion_cerrada') {
        return (
            <div
                className="rounded-xl p-3 my-1.5"
                style={{ background: 'var(--sala-surface)', border: '1px solid var(--sala-border)' }}
            >
                <div className="flex items-start gap-2.5">
                    <span className="text-base flex-shrink-0 mt-0.5">🗳</span>
                    <div className="flex-1 min-w-0">
                        <p className="text-xs font-medium truncate" style={{ color: 'var(--sala-text)' }}>
                            {item.pregunta}
                        </p>
                        <p className="text-xs mt-0.5" style={{ color: 'var(--sala-green)' }}>
                            Ganó: {item.ganadora} ({item.ganadora_pct}%)
                        </p>
                    </div>
                    <span className="text-xs tabular-nums flex-shrink-0" style={{ color: 'var(--sala-text-faint)' }}>
                        {formatTime(item.timestamp)}
                    </span>
                </div>
            </div>
        )
    }

    if (item.tipo === 'aviso') {
        return (
            <div className="flex items-start gap-3 py-2.5">
                <span className="text-base flex-shrink-0">📢</span>
                <span className="text-sm flex-1" style={{ color: 'var(--sala-text-muted)' }}>{item.mensaje}</span>
                <span className="text-xs tabular-nums flex-shrink-0" style={{ color: 'var(--sala-text-faint)' }}>
                    {formatTime(item.timestamp)}
                </span>
            </div>
        )
    }

    return null
}

function TerminalBanner({ estado, countdown }) {
    const labels = {
        finalizada:   'La reunión ha finalizado.',
        cancelada:    'La reunión fue cancelada.',
        reprogramada: 'La reunión fue reprogramada.',
    }
    return (
        <div
            className="fixed inset-x-0 top-0 z-40 px-4 py-3 text-center text-sm font-medium"
            style={{ background: 'var(--sala-red)', color: '#fff' }}
        >
            {labels[estado] ?? 'La reunión terminó.'} Redirigiendo en {countdown}s…
        </div>
    )
}

// ─── main component ────────────────────────────────────────────────────────────

export default function SalaShow({
    reunion,
    quorum: initialQuorum,
    poderes = [],
    yaVotoPor: initialYaVotoPor = [],
    votacionAbierta = null,
    resultadosActuales: initialResultados = null,
    feedInicial = [],
    estadoReunion: initialEstadoReunion,
}) {
    const [connStatus, setConnStatus]       = useState('connected')
    const [quorum, setQuorum]               = useState(initialQuorum)
    const [estadoReunion, setEstadoReunion] = useState(initialEstadoReunion)
    const [votacionActiva, setVotacionActiva] = useState(
        votacionAbierta ? {
            votacion_id: votacionAbierta.id,
            pregunta:    votacionAbierta.pregunta,
            estado:      votacionAbierta.estado,
            opciones:    votacionAbierta.opciones ?? [],
        } : null
    )
    const [votando, setVotando]             = useState(false)
    const [yaVotoPor, setYaVotoPor]         = useState(initialYaVotoPor)
    const [resultados, setResultados]       = useState(initialResultados)
    const [aviso, setAviso]                 = useState(null)
    const [feed, setFeed]                   = useState([...feedInicial].reverse()) // más reciente primero
    const [terminalCountdown, setTerminalCountdown] = useState(null)
    const countdownRef = useRef(null)
    // Ref para evitar stale closures en listeners WebSocket (no re-registran con cada render)
    const votacionActivaRef = useRef(votacionActiva)
    useEffect(() => { votacionActivaRef.current = votacionActiva }, [votacionActiva])

    // WebSocket setup — se registra una sola vez; usa votacionActivaRef.current para evitar stale closures
    useEffect(() => {
        const channel = echo.channel(`reunion.${reunion.id}`)

        channel
            .listen('QuorumActualizado', (e) => setQuorum(e.quorumData))
            .listen('EstadoVotacionCambiado', (e) => {
                if (e.estado === 'abierta') {
                    setVotacionActiva(e)
                    setResultados(null)
                } else {
                    // Votación cerrada: mover al feed
                    setVotacionActiva(null)
                    setResultados(null)
                }
            })
            .listen('VotacionModificada', (e) => {
                // Usar ref para evitar stale closure — votacionActivaRef.current siempre es el valor actual
                if (e.accion === 'updated' && votacionActivaRef.current?.votacion_id === e.votacion_id) {
                    setVotacionActiva(prev => ({
                        ...prev,
                        pregunta: e.pregunta,
                        opciones: e.opciones ?? prev.opciones,
                    }))
                }
            })
            .listen('AvisoEnviado', (e) => {
                setAviso({ mensaje: e.mensaje, ts: e.enviado_at })
                setFeed(prev => [{ tipo: 'aviso', mensaje: e.mensaje, timestamp: e.enviado_at }, ...prev])
                setTimeout(() => setAviso(null), 10000)
            })
            .listen('ResultadosPublicosVotacion', (e) => {
                // Usar ref para evitar stale closure — evita doble-binding con segundo useEffect
                if (votacionActivaRef.current && e.votacion_id === votacionActivaRef.current.votacion_id) {
                    setResultados(e.resultados)
                }
            })
            .listen('EstadoReunionCambiado', (e) => {
                setEstadoReunion(e.estado)
                setFeed(prev => [{ tipo: 'estado_reunion', estado: e.estado, timestamp: e.timestamp }, ...prev])

                if (TERMINAL_STATES.includes(e.estado)) {
                    startTerminalCountdown()
                }
            })

        echo.connector.pusher?.connection?.bind('connected',      () => setConnStatus('connected'))
        echo.connector.pusher?.connection?.bind('connecting',     () => setConnStatus('reconnecting'))
        echo.connector.pusher?.connection?.bind('disconnected',   () => setConnStatus('disconnected'))
        echo.connector.pusher?.connection?.bind('unavailable',    () => setConnStatus('disconnected'))

        echo.join(`presence-reunion.${reunion.id}`)

        return () => {
            echo.leave(`reunion.${reunion.id}`)
            echo.leave(`presence-reunion.${reunion.id}`)
            if (countdownRef.current) clearInterval(countdownRef.current)
        }
    }, [reunion.id])

    function startTerminalCountdown() {
        setTerminalCountdown(10)
        countdownRef.current = setInterval(() => {
            setTerminalCountdown(prev => {
                if (prev <= 1) {
                    clearInterval(countdownRef.current)
                    router.visit('/historial')
                    return 0
                }
                return prev - 1
            })
        }, 1000)
    }

    const emitirVoto = (opcionId, enNombreDeId = null) => {
        if (votando) return
        setVotando(true)

        router.post('/votos', {
            votacion_id: votacionActiva.votacion_id,
            opcion_id:   opcionId,
            en_nombre_de: enNombreDeId,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setYaVotoPor(prev => [...prev, enNombreDeId ?? 'propio'])
            },
            onFinish: () => setVotando(false),
        })
    }

    const isTerminal = TERMINAL_STATES.includes(estadoReunion)

    return (
        <div style={{ minHeight: '100dvh', background: 'var(--sala-bg)', fontFamily: 'var(--sala-font-body)', color: 'var(--sala-text)' }}>
            {/* Aviso flotante */}
            {aviso && (
                <div className="fixed top-16 left-1/2 -translate-x-1/2 z-50 w-[calc(100%-2rem)] max-w-sm rounded-xl px-4 py-3 shadow-2xl flex items-start gap-3"
                    style={{ background: 'var(--sala-amber)', color: '#0a0f1e' }}
                >
                    <span className="text-lg">📢</span>
                    <span className="flex-1 text-sm font-medium">{aviso.mensaje}</span>
                    <button onClick={() => setAviso(null)} className="font-bold text-lg leading-none opacity-60 hover:opacity-100">✕</button>
                </div>
            )}

            {/* Banner terminal */}
            {isTerminal && terminalCountdown !== null && (
                <TerminalBanner estado={estadoReunion} countdown={terminalCountdown} />
            )}

            <StatusBar connStatus={connStatus} estadoReunion={estadoReunion} quorum={quorum} />

            <div className="px-4 py-5 max-w-lg mx-auto">
                {/* Header */}
                <div className="mb-5">
                    <p className="text-xs uppercase tracking-wide mb-0.5" style={{ color: 'var(--sala-text-muted)' }}>
                        {reunion.tipo}
                    </p>
                    <h1 className="text-lg font-semibold" style={{ color: 'var(--sala-text)' }}>
                        {reunion.titulo}
                    </h1>
                </div>

                {/* Votación activa */}
                <VotacionCard
                    votacionActiva={votacionActiva}
                    resultados={resultados}
                    yaVotoPor={yaVotoPor}
                    poderes={poderes}
                    onVotar={emitirVoto}
                    loading={votando}
                />

                {/* Feed cronológico */}
                {feed.length > 0 && (
                    <div>
                        <p className="text-[10px] uppercase tracking-widest mb-3" style={{ color: 'var(--sala-text-faint)' }}>
                            Cronología
                        </p>
                        <div className="divide-y" style={{ borderColor: 'var(--sala-border)' }}>
                            {feed.map((item, i) => (
                                <FeedItem key={i} item={item} />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    )
}
```

- [ ] **Step 2: Compilar**

```bash
./sail npm run build 2>&1 | tail -20
```
Expected: build exitoso sin errores.

- [ ] **Step 3: Verificar manualmente en browser**

Con Docker corriendo, abrir `http://localhost` como copropietario:
1. Entrar a `/sala/{reunion_id}` — verificar status bar visible con punto verde, badge estado, quórum
2. Abrir una votación desde el admin en `/admin/reuniones/{id}` — verificar que aparece la card ámbar en la sala
3. Click en una opción — verificar que aparece el modal de confirmación
4. Click "Confirmar" — verificar que el botón desaparece y aparecen las barras de resultados
5. Desde el admin emitir un aviso — verificar banner flotante + entrada en el feed
6. Cerrar la votación desde el admin — verificar que el card desaparece y aparece en el feed como "Votación cerrada"

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Copropietario/Sala/Show.jsx
git commit -m "feat: redesign Sala/Show.jsx with status bar, vote confirmation modal, flip card, and chronological feed"
```

---

### Task 8: Verificación final y suite de tests

- [ ] **Step 1: Correr suite completa**

```bash
./sail artisan test --no-coverage
```
Expected: todos los tests en PASS, sin regresiones en admin ni copropietario.

- [ ] **Step 2: Compilar assets de producción**

```bash
./sail npm run build
```
Expected: sin warnings ni errores.

- [ ] **Step 3: Commit final si hay cambios pendientes**

```bash
git status
# Si hay cambios sin commitear:
git add -A
git commit -m "chore: final cleanup after copropietario sala realtime feature"
```
