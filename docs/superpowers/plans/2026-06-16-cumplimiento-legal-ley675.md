# Cumplimiento Legal Ley 675 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar el núcleo legal de Asambli: tipos de reunión correctos, catálogo de decisiones con mayorías automáticas (simple/70%/unanimidad) y restricción de voto para copropietarios en mora (Art. 38 Ley 675).

**Architecture:** Tabla `tipos_decision` como catálogo legal global seeded; campo `tipo_decision_id` en `votaciones` que fija automáticamente la mayoría requerida; lógica en `RecalcularResultadosVotacion` que usa denominador correcto (presentes vs. edificio completo); flag `en_mora` en `copropietarios` verificado en `VotoService` antes de permitir votar.

**Tech Stack:** Laravel 12, PHP 8.5, Pest, React 18, Inertia.js, Tailwind CSS

**Spec:** `docs/superpowers/specs/2026-06-16-cumplimiento-legal-ley675-design.md`

---

## Task 1: Migration

**Files:**
- Create: `database/migrations/2026_06_16_000001_add_legal_compliance_fields.php`

- [x] **Crear el archivo de migración**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Reestructurar tipo de reunión
        Schema::table('reuniones', function (Blueprint $table) {
            $table->enum('tipo_cuerpo', ['asamblea', 'consejo'])
                  ->default('asamblea')->after('titulo');
            $table->enum('tipo_convocatoria', ['ordinaria', 'extraordinaria'])
                  ->default('ordinaria')->after('tipo_cuerpo');
        });

        DB::statement("UPDATE reuniones SET tipo_cuerpo='asamblea',  tipo_convocatoria='ordinaria'      WHERE tipo='asamblea'");
        DB::statement("UPDATE reuniones SET tipo_cuerpo='asamblea',  tipo_convocatoria='extraordinaria' WHERE tipo='extraordinaria'");
        DB::statement("UPDATE reuniones SET tipo_cuerpo='consejo',   tipo_convocatoria='ordinaria'      WHERE tipo='consejo'");

        Schema::table('reuniones', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });

        // 2. Catálogo legal de tipos de decisión
        Schema::create('tipos_decision', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->text('descripcion');
            $table->enum('tipo_mayoria', ['simple', 'calificada_70', 'unanimidad']);
            $table->json('aplica_en');
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();
        });

        // 3. FK en votaciones + campo resultado
        Schema::table('votaciones', function (Blueprint $table) {
            $table->foreignId('tipo_decision_id')->nullable()
                  ->constrained('tipos_decision')->nullOnDelete()
                  ->after('descripcion');
            $table->enum('resultado', ['pendiente', 'aprobada', 'rechazada'])
                  ->default('pendiente')->after('tipo_decision_id');
        });

        // 4. Estado de mora en copropietarios
        Schema::table('copropietarios', function (Blueprint $table) {
            $table->boolean('en_mora')->default(false)->after('activo');
        });

        // 5. Configuración de mora en tenants
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('restringir_voto_morosos')->default(true)->after('max_poderes_por_delegado');
        });
    }

    public function down(): void
    {
        Schema::table('votaciones', function (Blueprint $table) {
            $table->dropForeign(['tipo_decision_id']);
            $table->dropColumn(['tipo_decision_id', 'resultado']);
        });

        Schema::dropIfExists('tipos_decision');

        Schema::table('reuniones', function (Blueprint $table) {
            $table->enum('tipo', ['asamblea', 'consejo', 'extraordinaria'])
                  ->default('asamblea')->after('titulo');
        });

        DB::statement("UPDATE reuniones SET tipo='asamblea'       WHERE tipo_cuerpo='asamblea' AND tipo_convocatoria='ordinaria'");
        DB::statement("UPDATE reuniones SET tipo='extraordinaria' WHERE tipo_cuerpo='asamblea' AND tipo_convocatoria='extraordinaria'");
        DB::statement("UPDATE reuniones SET tipo='consejo'        WHERE tipo_cuerpo='consejo'");

        Schema::table('reuniones', function (Blueprint $table) {
            $table->dropColumn(['tipo_cuerpo', 'tipo_convocatoria']);
        });

        Schema::table('copropietarios', function (Blueprint $table) {
            $table->dropColumn('en_mora');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('restringir_voto_morosos');
        });
    }
};
```

- [x] **Correr la migración y verificar**

```bash
./sail artisan migrate
```

Esperado: sin errores. Verificar con:

```bash
./sail artisan tinker --execute="echo Schema::hasColumn('reuniones','tipo_cuerpo') ? 'OK' : 'FAIL';"
./sail artisan tinker --execute="echo Schema::hasTable('tipos_decision') ? 'OK' : 'FAIL';"
```

---

## Task 2: TipoDecision model + Seeder

**Files:**
- Create: `app/Models/TipoDecision.php`
- Create: `database/seeders/TiposDecisionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [x] **Crear modelo TipoDecision**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoDecision extends Model
{
    protected $table = 'tipos_decision';

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'tipo_mayoria', 'aplica_en', 'orden',
    ];

    protected $casts = [
        'aplica_en' => 'array',
    ];

    public static function paraAsamblea(): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereJsonContains('aplica_en', 'asamblea')->orderBy('orden')->get();
    }
}
```

- [x] **Crear seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\TipoDecision;
use Illuminate\Database\Seeder;

class TiposDecisionSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'codigo'       => 'presupuesto_anual',
                'nombre'       => 'Aprobación del presupuesto anual',
                'descripcion'  => 'Aprobación del presupuesto de ingresos y gastos para el siguiente período. Art. 38, Ley 675/2001.',
                'tipo_mayoria' => 'simple',
                'aplica_en'    => ['asamblea'],
                'orden'        => 1,
            ],
            [
                'codigo'       => 'estados_financieros',
                'nombre'       => 'Aprobación de estados financieros',
                'descripcion'  => 'Aprobación de los estados de cuentas del período anterior. Art. 38, Ley 675/2001.',
                'tipo_mayoria' => 'simple',
                'aplica_en'    => ['asamblea'],
                'orden'        => 2,
            ],
            [
                'codigo'       => 'eleccion_consejo',
                'nombre'       => 'Elección del consejo de administración',
                'descripcion'  => 'Elección de los miembros del consejo de administración. Art. 36, Ley 675/2001.',
                'tipo_mayoria' => 'simple',
                'aplica_en'    => ['asamblea'],
                'orden'        => 3,
            ],
            [
                'codigo'       => 'eleccion_administrador',
                'nombre'       => 'Elección o ratificación del administrador',
                'descripcion'  => 'Designación o ratificación del administrador del conjunto. Art. 50, Ley 675/2001.',
                'tipo_mayoria' => 'simple',
                'aplica_en'    => ['asamblea', 'consejo'],
                'orden'        => 4,
            ],
            [
                'codigo'       => 'cuota_administracion',
                'nombre'       => 'Aprobación de la cuota de administración',
                'descripcion'  => 'Fijación del valor de la cuota ordinaria de administración. Art. 38, Ley 675/2001.',
                'tipo_mayoria' => 'simple',
                'aplica_en'    => ['asamblea'],
                'orden'        => 5,
            ],
            [
                'codigo'       => 'decision_ordinaria',
                'nombre'       => 'Otra decisión ordinaria',
                'descripcion'  => 'Cualquier decisión de la asamblea no tipificada en los artículos de mayoría calificada. Art. 45, Ley 675/2001.',
                'tipo_mayoria' => 'simple',
                'aplica_en'    => ['asamblea', 'consejo'],
                'orden'        => 6,
            ],
            [
                'codigo'       => 'reforma_reglamento',
                'nombre'       => 'Reforma al reglamento de propiedad horizontal',
                'descripcion'  => 'Modificación del reglamento de propiedad horizontal. Requiere el 70% del total de coeficientes del conjunto. Art. 46, Ley 675/2001.',
                'tipo_mayoria' => 'calificada_70',
                'aplica_en'    => ['asamblea'],
                'orden'        => 7,
            ],
            [
                'codigo'       => 'cambio_destinacion',
                'nombre'       => 'Cambio de destinación de bienes comunes',
                'descripcion'  => 'Cambio de uso o destinación de bienes comunes del conjunto. Requiere el 70% del total de coeficientes. Art. 46, Ley 675/2001.',
                'tipo_mayoria' => 'calificada_70',
                'aplica_en'    => ['asamblea'],
                'orden'        => 8,
            ],
            [
                'codigo'       => 'desafectacion_bienes',
                'nombre'       => 'Desafectación de bienes comunes no esenciales',
                'descripcion'  => 'Desafectación del carácter común de bienes no esenciales del conjunto. Requiere el 70% del total de coeficientes. Art. 46, Ley 675/2001.',
                'tipo_mayoria' => 'calificada_70',
                'aplica_en'    => ['asamblea'],
                'orden'        => 9,
            ],
            [
                'codigo'       => 'gravamenes_bienes',
                'nombre'       => 'Constitución de gravámenes sobre bienes comunes',
                'descripcion'  => 'Constitución de hipotecas, prendas u otros gravámenes sobre bienes comunes. Requiere el 70% del total de coeficientes. Art. 46, Ley 675/2001.',
                'tipo_mayoria' => 'calificada_70',
                'aplica_en'    => ['asamblea'],
                'orden'        => 10,
            ],
            [
                'codigo'       => 'reconstruccion_mejoras',
                'nombre'       => 'Obras de reconstrucción o mejoras no urgentes',
                'descripcion'  => 'Obras de reconstrucción del edificio o mejoras que no sean de urgencia. Requiere el 70% del total de coeficientes. Art. 46, Ley 675/2001.',
                'tipo_mayoria' => 'calificada_70',
                'aplica_en'    => ['asamblea'],
                'orden'        => 11,
            ],
            [
                'codigo'       => 'extincion_regimen',
                'nombre'       => 'Extinción voluntaria del régimen de PH',
                'descripcion'  => 'Extinción voluntaria del régimen de propiedad horizontal. Requiere unanimidad de todos los propietarios. Art. 9, Ley 675/2001.',
                'tipo_mayoria' => 'unanimidad',
                'aplica_en'    => ['asamblea'],
                'orden'        => 12,
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoDecision::updateOrCreate(['codigo' => $tipo['codigo']], $tipo);
        }
    }
}
```

- [x] **Agregar seeder al DatabaseSeeder**

En `database/seeders/DatabaseSeeder.php`, agregar antes de la creación del tenant:

```php
$this->call(TiposDecisionSeeder::class);
```

- [x] **Correr el seeder y verificar**

```bash
./sail artisan db:seed --class=TiposDecisionSeeder
./sail artisan tinker --execute="echo \App\Models\TipoDecision::count() . ' tipos';"
```

Esperado: `12 tipos`

- [x] **Commit**

```bash
git add database/migrations/2026_06_16_000001_add_legal_compliance_fields.php \
        app/Models/TipoDecision.php \
        database/seeders/TiposDecisionSeeder.php \
        database/seeders/DatabaseSeeder.php
git commit -m "feat: migration y catálogo de tipos de decisión Ley 675"
```

---

## Task 3: Actualizar modelos y factory

**Files:**
- Modify: `app/Models/Reunion.php`
- Modify: `app/Models/Votacion.php`
- Modify: `app/Models/Copropietario.php`
- Modify: `app/Models/Tenant.php`
- Modify: `database/factories/ReunionFactory.php`

- [x] **Actualizar Reunion.php**

Reemplazar el `$fillable` y `$casts` completos:

```php
protected $fillable = [
    'tenant_id', 'titulo', 'tipo_cuerpo', 'tipo_convocatoria', 'tipo_voto_peso',
    'quorum_requerido', 'estado', 'fecha_programada',
    'fecha_inicio', 'fecha_fin', 'convocatoria_enviada_at', 'creado_por',
    'qr_token', 'qr_expires_at', 'modalidad', 'convocatoria_envios',
];
```

Los `$casts` no cambian (no había cast de `tipo`).

- [x] **Actualizar Votacion.php**

Agregar `tipo_decision_id` y `resultado` a `$fillable`, agregar `$casts`, agregar relación:

```php
protected $fillable = [
    'tenant_id', 'reunion_id', 'tipo_decision_id', 'pregunta', 'descripcion',
    'tipo', 'es_secreta', 'estado', 'resultado', 'abierta_at', 'cerrada_at', 'creada_por',
];

protected $casts = [
    'es_secreta'  => 'boolean',
    'abierta_at'  => 'datetime',
    'cerrada_at'  => 'datetime',
];

public function tipoDecision(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(\App\Models\TipoDecision::class);
}
```

- [x] **Actualizar Copropietario.php**

Agregar `en_mora` a `$fillable` y a `$casts`:

```php
// En $fillable, agregar:
'en_mora',

// En $casts, agregar:
'en_mora' => 'boolean',
```

- [x] **Actualizar Tenant.php**

Agregar `restringir_voto_morosos` a `$fillable` y a `$casts`:

```php
// En $fillable, agregar:
'restringir_voto_morosos',

// En $casts, agregar:
'restringir_voto_morosos' => 'boolean',
```

- [x] **Actualizar ReunionFactory.php**

Reemplazar `'tipo' => 'asamblea'` por:

```php
'tipo_cuerpo'       => 'asamblea',
'tipo_convocatoria' => 'ordinaria',
```

- [x] **Correr los tests existentes para confirmar que nada se rompió**

```bash
./sail artisan test --no-coverage
```

Esperado: todos los tests pasan (algunos pueden fallar por referencias a `tipo` — se corrigen en el Task 7).

- [x] **Commit**

```bash
git add app/Models/Reunion.php app/Models/Votacion.php \
        app/Models/Copropietario.php app/Models/Tenant.php \
        database/factories/ReunionFactory.php
git commit -m "feat: actualizar modelos con campos de cumplimiento legal"
```

---

## Task 4: TDD — VotoService restricción de mora

**Files:**
- Create: `tests/Feature/VotoServiceMoraTest.php`
- Modify: `app/Services/VotoService.php`

- [x] **Escribir los tests (deben fallar)**

```php
<?php

use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\Votacion;
use App\Services\VotoService;

function setupMoraContext(bool $enMora = true, bool $restringir = true): array
{
    $tenant = Tenant::factory()->create(['restringir_voto_morosos' => $restringir]);
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create([
        'tenant_id'       => $tenant->id,
        'estado'          => 'en_curso',
        'tipo_voto_peso'  => 'coeficiente',
        'quorum_requerido' => 1.0,
    ]);

    $copropietario = Copropietario::factory()->create([
        'tenant_id' => $tenant->id,
        'en_mora'   => $enMora,
    ]);

    Unidad::factory()->create([
        'tenant_id'        => $tenant->id,
        'copropietario_id' => $copropietario->id,
        'coeficiente'      => 100.0,
    ]);

    Asistencia::create([
        'reunion_id'          => $reunion->id,
        'copropietario_id'    => $copropietario->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion'   => now(),
    ]);

    $votacion = Votacion::factory()->create([
        'tenant_id'  => $tenant->id,
        'reunion_id' => $reunion->id,
        'estado'     => 'abierta',
    ]);

    $opcion = $votacion->opciones()->create(['texto' => 'Sí', 'orden' => 1]);

    return compact('tenant', 'reunion', 'copropietario', 'votacion', 'opcion');
}

test('copropietario en mora no puede votar cuando restriccion esta activa', function () {
    $ctx = setupMoraContext(enMora: true, restringir: true);

    $result = app(VotoService::class)->votar(
        votacion:      $ctx['votacion'],
        copropietario: $ctx['copropietario'],
        opcionId:      $ctx['opcion']->id,
        request:       request(),
    );

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('mora');
});

test('copropietario en mora puede votar cuando restriccion esta desactivada', function () {
    $ctx = setupMoraContext(enMora: true, restringir: false);

    $result = app(VotoService::class)->votar(
        votacion:      $ctx['votacion'],
        copropietario: $ctx['copropietario'],
        opcionId:      $ctx['opcion']->id,
        request:       request(),
    );

    expect($result['success'])->toBeTrue();
});

test('copropietario sin mora puede votar normalmente', function () {
    $ctx = setupMoraContext(enMora: false, restringir: true);

    $result = app(VotoService::class)->votar(
        votacion:      $ctx['votacion'],
        copropietario: $ctx['copropietario'],
        opcionId:      $ctx['opcion']->id,
        request:       request(),
    );

    expect($result['success'])->toBeTrue();
});

test('restriccion de mora aplica a todas las votaciones de la reunion', function () {
    $ctx = setupMoraContext(enMora: true, restringir: true);

    $votacion2 = Votacion::factory()->create([
        'tenant_id'  => $ctx['tenant']->id,
        'reunion_id' => $ctx['reunion']->id,
        'estado'     => 'abierta',
    ]);
    $opcion2 = $votacion2->opciones()->create(['texto' => 'No', 'orden' => 1]);

    $result = app(VotoService::class)->votar(
        votacion:      $votacion2,
        copropietario: $ctx['copropietario'],
        opcionId:      $opcion2->id,
        request:       request(),
    );

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('mora');
});
```

- [x] **Correr para confirmar que fallan**

```bash
./sail artisan test tests/Feature/VotoServiceMoraTest.php --no-coverage
```

Esperado: 4 fallos (campo `en_mora` y `restringir_voto_morosos` no existen aún en la lógica del servicio).

- [x] **Implementar el check en VotoService**

En `app/Services/VotoService.php`, dentro de la transacción en `votar()`, agregar como **primer paso** antes del check de reunión en curso:

```php
// 0. Verificar mora (Art. 38, Ley 675 de 2001)
$votacion->loadMissing('reunion.tenant');
if ($copropietario->en_mora && $votacion->reunion->tenant->restringir_voto_morosos) {
    throw new \Exception('Tiene cuotas de administración en mora y no puede votar en esta asamblea (Art. 38, Ley 675 de 2001).');
}
```

- [x] **Correr para confirmar que pasan**

```bash
./sail artisan test tests/Feature/VotoServiceMoraTest.php --no-coverage
```

Esperado: 4 passed.

- [x] **Correr suite completa**

```bash
./sail artisan test --no-coverage
```

Esperado: sin regresiones.

- [x] **Commit**

```bash
git add tests/Feature/VotoServiceMoraTest.php app/Services/VotoService.php
git commit -m "feat: restricción de voto para copropietarios en mora (Art. 38 Ley 675)"
```

---

## Task 5: TDD — RecalcularResultadosVotacion con mayorías

**Files:**
- Modify: `app/Events/ResultadosVotacionActualizados.php`
- Modify: `app/Jobs/RecalcularResultadosVotacion.php`
- Create: `tests/Feature/Jobs/RecalcularResultadosMayoriasTest.php`

- [x] **Actualizar el evento para incluir datos de mayoría**

Reemplazar `app/Events/ResultadosVotacionActualizados.php` completo:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResultadosVotacionActualizados implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly \App\Models\Votacion $votacion,
        public readonly array $resultados,
        public readonly ?string $ultimoVotoUnidad = null,
        public readonly ?array $mayoriaData = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('reunion.' . $this->votacion->reunion_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'votacion_id'        => $this->votacion->id,
            'resultados'         => $this->resultados,
            'ultimo_voto_unidad' => $this->ultimoVotoUnidad,
            'mayoria'            => $this->mayoriaData,
        ];
    }
}
```

- [x] **Escribir los tests (deben fallar)**

```php
<?php

use App\Jobs\RecalcularResultadosVotacion;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\TipoDecision;
use App\Models\Unidad;
use App\Models\Votacion;
use App\Models\Voto;
use Illuminate\Support\Facades\Event;

function makeTipoDecision(string $tipoMayoria): TipoDecision
{
    return TipoDecision::create([
        'codigo'       => 'test_' . $tipoMayoria . '_' . uniqid(),
        'nombre'       => 'Test ' . $tipoMayoria,
        'descripcion'  => 'Para tests',
        'tipo_mayoria' => $tipoMayoria,
        'aplica_en'    => ['asamblea'],
        'orden'        => 99,
    ]);
}

function makeVotoContext(string $tipoMayoria): array
{
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    // Edificio con 100 puntos de coeficiente total
    $c1 = Copropietario::factory()->create(['tenant_id' => $tenant->id]);
    $c2 = Copropietario::factory()->create(['tenant_id' => $tenant->id]);
    $c3 = Copropietario::factory()->create(['tenant_id' => $tenant->id]);

    Unidad::factory()->create(['tenant_id' => $tenant->id, 'copropietario_id' => $c1->id, 'coeficiente' => 40.0]);
    Unidad::factory()->create(['tenant_id' => $tenant->id, 'copropietario_id' => $c2->id, 'coeficiente' => 40.0]);
    Unidad::factory()->create(['tenant_id' => $tenant->id, 'copropietario_id' => $c3->id, 'coeficiente' => 20.0]);

    $reunion = Reunion::factory()->create([
        'tenant_id'      => $tenant->id,
        'estado'         => 'en_curso',
        'tipo_voto_peso' => 'coeficiente',
    ]);

    $tipoDecision = makeTipoDecision($tipoMayoria);

    $votacion = Votacion::factory()->create([
        'tenant_id'        => $tenant->id,
        'reunion_id'       => $reunion->id,
        'estado'           => 'abierta',
        'tipo_decision_id' => $tipoDecision->id,
    ]);

    $opcionSi = $votacion->opciones()->create(['texto' => 'Sí', 'orden' => 1]);
    $opcionNo = $votacion->opciones()->create(['texto' => 'No', 'orden' => 2]);

    return compact('tenant', 'reunion', 'votacion', 'c1', 'c2', 'c3', 'opcionSi', 'opcionNo');
}

function crearVoto(Votacion $votacion, Copropietario $copropietario, int $opcionId, float $peso): void
{
    Voto::create([
        'tenant_id'         => $votacion->tenant_id,
        'votacion_id'       => $votacion->id,
        'copropietario_id'  => $copropietario->id,
        'opcion_id'         => $opcionId,
        'peso'              => $peso,
        'ip_address'        => '127.0.0.1',
        'user_agent'        => 'test',
        'hash_verificacion' => hash('sha256', uniqid()),
    ]);
}

// — Tests mayoría simple —

test('simple: aprobada cuando votos si superan votos no', function () {
    Event::fake();
    $ctx = makeVotoContext('simple');

    crearVoto($ctx['votacion'], $ctx['c1'], $ctx['opcionSi']->id, 40.0);
    crearVoto($ctx['votacion'], $ctx['c2'], $ctx['opcionNo']->id, 40.0);
    crearVoto($ctx['votacion'], $ctx['c3'], $ctx['opcionSi']->id, 20.0); // 60 SI vs 40 NO

    RecalcularResultadosVotacion::dispatchSync($ctx['votacion']->id, null);

    $mayoriaData = Event::dispatched(\App\Events\ResultadosVotacionActualizados::class)[0][0]->mayoriaData;
    expect($mayoriaData['aprobada'])->toBeTrue();
    expect($mayoriaData['tipo_mayoria'])->toBe('simple');
});

test('simple: rechazada cuando votos no superan votos si', function () {
    Event::fake();
    $ctx = makeVotoContext('simple');

    crearVoto($ctx['votacion'], $ctx['c1'], $ctx['opcionNo']->id, 40.0);
    crearVoto($ctx['votacion'], $ctx['c2'], $ctx['opcionNo']->id, 40.0);
    crearVoto($ctx['votacion'], $ctx['c3'], $ctx['opcionSi']->id, 20.0); // 20 SI vs 80 NO

    RecalcularResultadosVotacion::dispatchSync($ctx['votacion']->id, null);

    $mayoriaData = Event::dispatched(\App\Events\ResultadosVotacionActualizados::class)[0][0]->mayoriaData;
    expect($mayoriaData['aprobada'])->toBeFalse();
});

// — Tests mayoría calificada 70% —

test('calificada_70: aprobada cuando si alcanza 70% del total del edificio', function () {
    Event::fake();
    $ctx = makeVotoContext('calificada_70');

    // c1(40) + c2(40) = 80 → 80% del total (100) → aprobada
    crearVoto($ctx['votacion'], $ctx['c1'], $ctx['opcionSi']->id, 40.0);
    crearVoto($ctx['votacion'], $ctx['c2'], $ctx['opcionSi']->id, 40.0);
    crearVoto($ctx['votacion'], $ctx['c3'], $ctx['opcionNo']->id, 20.0);

    RecalcularResultadosVotacion::dispatchSync($ctx['votacion']->id, null);

    $mayoriaData = Event::dispatched(\App\Events\ResultadosVotacionActualizados::class)[0][0]->mayoriaData;
    expect($mayoriaData['aprobada'])->toBeTrue();
    expect($mayoriaData['tipo_mayoria'])->toBe('calificada_70');
    expect($mayoriaData['total_denominador'])->toBe(100.0);
    expect($mayoriaData['porcentaje_si'])->toBe(80.0);
});

test('calificada_70: rechazada cuando si no alcanza 70% del edificio aunque 100% de presentes apruebe', function () {
    Event::fake();
    $ctx = makeVotoContext('calificada_70');

    // Solo c3 presente y vota SI: 20/100 = 20% → rechazada aunque sea 100% de los presentes
    crearVoto($ctx['votacion'], $ctx['c3'], $ctx['opcionSi']->id, 20.0);

    RecalcularResultadosVotacion::dispatchSync($ctx['votacion']->id, null);

    $mayoriaData = Event::dispatched(\App\Events\ResultadosVotacionActualizados::class)[0][0]->mayoriaData;
    expect($mayoriaData['aprobada'])->toBeFalse();
    expect($mayoriaData['porcentaje_si'])->toBe(20.0);
});

test('calificada_70: rechazada con exactamente 60% del edificio a favor', function () {
    Event::fake();
    $ctx = makeVotoContext('calificada_70');

    // c1(40) + c3(20) = 60 → 60% del total → rechazada (necesita >= 70%)
    crearVoto($ctx['votacion'], $ctx['c1'], $ctx['opcionSi']->id, 40.0);
    crearVoto($ctx['votacion'], $ctx['c3'], $ctx['opcionSi']->id, 20.0);
    crearVoto($ctx['votacion'], $ctx['c2'], $ctx['opcionNo']->id, 40.0);

    RecalcularResultadosVotacion::dispatchSync($ctx['votacion']->id, null);

    $mayoriaData = Event::dispatched(\App\Events\ResultadosVotacionActualizados::class)[0][0]->mayoriaData;
    expect($mayoriaData['aprobada'])->toBeFalse();
    expect($mayoriaData['porcentaje_si'])->toBe(60.0);
});

// — Tests unanimidad —

test('unanimidad: aprobada solo cuando todos votan si', function () {
    Event::fake();
    $ctx = makeVotoContext('unanimidad');

    crearVoto($ctx['votacion'], $ctx['c1'], $ctx['opcionSi']->id, 40.0);
    crearVoto($ctx['votacion'], $ctx['c2'], $ctx['opcionSi']->id, 40.0);
    crearVoto($ctx['votacion'], $ctx['c3'], $ctx['opcionSi']->id, 20.0); // 100/100 = 100%

    RecalcularResultadosVotacion::dispatchSync($ctx['votacion']->id, null);

    $mayoriaData = Event::dispatched(\App\Events\ResultadosVotacionActualizados::class)[0][0]->mayoriaData;
    expect($mayoriaData['aprobada'])->toBeTrue();
    expect($mayoriaData['tipo_mayoria'])->toBe('unanimidad');
});

test('unanimidad: rechazada cuando falta un voto', function () {
    Event::fake();
    $ctx = makeVotoContext('unanimidad');

    // c1 y c2 votan SI (80/100 = 80%), c3 no vota → rechazada
    crearVoto($ctx['votacion'], $ctx['c1'], $ctx['opcionSi']->id, 40.0);
    crearVoto($ctx['votacion'], $ctx['c2'], $ctx['opcionSi']->id, 40.0);

    RecalcularResultadosVotacion::dispatchSync($ctx['votacion']->id, null);

    $mayoriaData = Event::dispatched(\App\Events\ResultadosVotacionActualizados::class)[0][0]->mayoriaData;
    expect($mayoriaData['aprobada'])->toBeFalse();
});

// — Sin tipo_decision (backward compat) —

test('sin tipo_decision el broadcast no incluye mayoriaData', function () {
    Event::fake();
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => 'en_curso']);
    $votacion = Votacion::factory()->create([
        'tenant_id'        => $tenant->id,
        'reunion_id'       => $reunion->id,
        'estado'           => 'abierta',
        'tipo_decision_id' => null,
    ]);

    RecalcularResultadosVotacion::dispatchSync($votacion->id, null);

    $event = Event::dispatched(\App\Events\ResultadosVotacionActualizados::class)[0][0];
    expect($event->mayoriaData)->toBeNull();
});
```

- [x] **Correr para confirmar que fallan**

```bash
./sail artisan test tests/Feature/Jobs/RecalcularResultadosMayoriasTest.php --no-coverage
```

Esperado: todos fallan porque el Job aún no calcula mayoriaData.

- [x] **Actualizar el Job**

Reemplazar `app/Jobs/RecalcularResultadosVotacion.php` completo:

```php
<?php

namespace App\Jobs;

use App\Models\Unidad;
use App\Models\Voto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalcularResultadosVotacion implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $votacionId,
        public ?int $copropietarioId = null
    ) {}

    public function handle(): void
    {
        $votacion = \App\Models\Votacion::with('opciones', 'tipoDecision', 'reunion')
            ->withoutGlobalScopes()
            ->find($this->votacionId);

        if (!$votacion) return;

        $resultados = $votacion->opciones->map(function ($opcion) use ($votacion) {
            $votos = Voto::withoutGlobalScopes()
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
            $numeros = $copropietario?->unidades->pluck('numero')->filter()->values();
            $ultimoVotoUnidad = $numeros?->isNotEmpty() ? $numeros->join(', ') : null;
        }

        $mayoriaData = $this->calcularMayoriaData($votacion, $resultados->toArray());

        broadcast(new \App\Events\ResultadosVotacionActualizados(
            $votacion,
            $resultados->toArray(),
            $ultimoVotoUnidad,
            $mayoriaData
        ));
        broadcast(new \App\Events\ResultadosPublicosVotacion($votacion, $resultados->toArray()));
    }

    private function calcularMayoriaData(\App\Models\Votacion $votacion, array $resultados): ?array
    {
        if (!$votacion->tipoDecision) {
            return null;
        }

        $tipoMayoria = $votacion->tipoDecision->tipo_mayoria;

        // Identificar opción SI (primera opción que contiene "sí" o "si", o la primera opción)
        $pesoSi = 0.0;
        $pesoNo = 0.0;

        foreach ($resultados as $r) {
            $texto = mb_strtolower($r['texto']);
            if (str_contains($texto, 'sí') || str_contains($texto, 'si') || str_contains($texto, 'favor') || str_contains($texto, 'aprueba')) {
                $pesoSi += $r['peso_total'];
            } elseif (!str_contains($texto, 'abstenci')) {
                $pesoNo += $r['peso_total'];
            }
        }

        if ($tipoMayoria === 'simple') {
            $aprobada = $pesoSi > $pesoNo;
            return [
                'tipo_mayoria'     => 'simple',
                'umbral_requerido' => null,
                'total_denominador' => null,
                'porcentaje_si'    => null,
                'aprobada'         => $aprobada,
            ];
        }

        // calificada_70 y unanimidad: denominador = total coeficiente del edificio
        $totalEdificio = (float) Unidad::withoutGlobalScopes()
            ->where('tenant_id', $votacion->reunion->tenant_id)
            ->where('activo', true)
            ->sum('coeficiente');

        $porcentajeSi = $totalEdificio > 0
            ? round(($pesoSi / $totalEdificio) * 100, 2)
            : 0.0;

        if ($tipoMayoria === 'calificada_70') {
            return [
                'tipo_mayoria'      => 'calificada_70',
                'umbral_requerido'  => 70.0,
                'total_denominador' => $totalEdificio,
                'porcentaje_si'     => $porcentajeSi,
                'aprobada'          => $porcentajeSi >= 70.0,
            ];
        }

        // unanimidad
        return [
            'tipo_mayoria'      => 'unanimidad',
            'umbral_requerido'  => 100.0,
            'total_denominador' => $totalEdificio,
            'porcentaje_si'     => $porcentajeSi,
            'aprobada'          => round($pesoSi, 2) >= round($totalEdificio, 2),
        ];
    }
}
```

- [x] **Correr los tests**

```bash
./sail artisan test tests/Feature/Jobs/RecalcularResultadosMayoriasTest.php --no-coverage
```

Esperado: 8 passed.

- [x] **Correr suite completa**

```bash
./sail artisan test --no-coverage
```

- [x] **Commit**

```bash
git add app/Events/ResultadosVotacionActualizados.php \
        app/Jobs/RecalcularResultadosVotacion.php \
        tests/Feature/Jobs/RecalcularResultadosMayoriasTest.php
git commit -m "feat: lógica de mayorías (simple/70%/unanimidad) en RecalcularResultadosVotacion"
```

---

## Task 6: TDD — VotacionController::cerrar persiste resultado

**Files:**
- Modify: `app/Http/Controllers/Admin/VotacionController.php`
- Create: `tests/Feature/Admin/VotacionCerrarResultadoTest.php`

- [x] **Escribir los tests**

```php
<?php

use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\TipoDecision;
use App\Models\Unidad;
use App\Models\Votacion;
use App\Models\Voto;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function setupCerrarContext(string $tipoMayoria): array
{
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id]);

    $c1 = Copropietario::factory()->create(['tenant_id' => $tenant->id]);
    $c2 = Copropietario::factory()->create(['tenant_id' => $tenant->id]);
    Unidad::factory()->create(['tenant_id' => $tenant->id, 'copropietario_id' => $c1->id, 'coeficiente' => 60.0]);
    Unidad::factory()->create(['tenant_id' => $tenant->id, 'copropietario_id' => $c2->id, 'coeficiente' => 40.0]);

    $reunion = Reunion::factory()->create([
        'tenant_id'      => $tenant->id,
        'estado'         => 'en_curso',
        'tipo_voto_peso' => 'coeficiente',
        'creado_por'     => $admin->id,
    ]);

    $tipoDecision = TipoDecision::create([
        'codigo'       => 'test_cerrar_' . $tipoMayoria . '_' . uniqid(),
        'nombre'       => 'Test',
        'descripcion'  => 'Test',
        'tipo_mayoria' => $tipoMayoria,
        'aplica_en'    => ['asamblea'],
        'orden'        => 99,
    ]);

    $votacion = Votacion::factory()->create([
        'tenant_id'        => $tenant->id,
        'reunion_id'       => $reunion->id,
        'estado'           => 'abierta',
        'tipo_decision_id' => $tipoDecision->id,
        'creada_por'       => $admin->id,
    ]);

    $opcionSi = $votacion->opciones()->create(['texto' => 'Sí', 'orden' => 1]);
    $opcionNo = $votacion->opciones()->create(['texto' => 'No', 'orden' => 2]);

    return compact('admin', 'tenant', 'reunion', 'votacion', 'c1', 'c2', 'opcionSi', 'opcionNo');
}

test('cerrar votacion simple aprobada guarda resultado aprobada', function () {
    Event::fake();
    $ctx = setupCerrarContext('simple');

    // c1(60) vota SI, c2(40) vota NO → SI > NO → aprobada
    Voto::create(['tenant_id' => $ctx['tenant']->id, 'votacion_id' => $ctx['votacion']->id, 'copropietario_id' => $ctx['c1']->id, 'opcion_id' => $ctx['opcionSi']->id, 'peso' => 60.0, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'hash_verificacion' => hash('sha256', '1')]);
    Voto::create(['tenant_id' => $ctx['tenant']->id, 'votacion_id' => $ctx['votacion']->id, 'copropietario_id' => $ctx['c2']->id, 'opcion_id' => $ctx['opcionNo']->id, 'peso' => 40.0, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'hash_verificacion' => hash('sha256', '2')]);

    $this->actingAs($ctx['admin'])
         ->post("/admin/reuniones/{$ctx['reunion']->id}/votaciones/{$ctx['votacion']->id}/cerrar");

    $ctx['votacion']->refresh();
    expect($ctx['votacion']->resultado)->toBe('aprobada');
    expect($ctx['votacion']->estado)->toBe('cerrada');
});

test('cerrar votacion calificada_70 rechazada cuando si es menos de 70%', function () {
    Event::fake();
    $ctx = setupCerrarContext('calificada_70');

    // c1(60) vota SI → 60/100 = 60% → rechazada
    Voto::create(['tenant_id' => $ctx['tenant']->id, 'votacion_id' => $ctx['votacion']->id, 'copropietario_id' => $ctx['c1']->id, 'opcion_id' => $ctx['opcionSi']->id, 'peso' => 60.0, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'hash_verificacion' => hash('sha256', '3')]);
    Voto::create(['tenant_id' => $ctx['tenant']->id, 'votacion_id' => $ctx['votacion']->id, 'copropietario_id' => $ctx['c2']->id, 'opcion_id' => $ctx['opcionNo']->id, 'peso' => 40.0, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'hash_verificacion' => hash('sha256', '4')]);

    $this->actingAs($ctx['admin'])
         ->post("/admin/reuniones/{$ctx['reunion']->id}/votaciones/{$ctx['votacion']->id}/cerrar");

    $ctx['votacion']->refresh();
    expect($ctx['votacion']->resultado)->toBe('rechazada');
});
```

- [x] **Verificar que la ruta de cerrar votación existe**

```bash
grep "cerrar" /home/dwndz/Projects/Asambli/routes/web.php
```

Si no existe con el path `/admin/reuniones/{reunion}/votaciones/{votacion}/cerrar`, ajustar el test para usar la ruta correcta existente.

- [x] **Correr para confirmar que fallan**

```bash
./sail artisan test tests/Feature/Admin/VotacionCerrarResultadoTest.php --no-coverage
```

- [x] **Implementar en VotacionController::cerrar()**

Reemplazar el método `cerrar` completo en `VotacionController.php`:

```php
public function cerrar(Votacion $votacion)
{
    $votacion->load('reunion.tenant', 'opciones', 'tipoDecision');

    $totalVotos = Voto::withoutGlobalScopes()
        ->where('votacion_id', $votacion->id)
        ->count();

    $ganadora = $votacion->opciones->map(function ($opcion) use ($votacion) {
        return [
            'texto'      => $opcion->texto,
            'peso_total' => (float) Voto::withoutGlobalScopes()
                ->where('votacion_id', $votacion->id)
                ->where('opcion_id', $opcion->id)
                ->sum('peso'),
        ];
    })->sortByDesc('peso_total')->first();

    $resultado = $this->calcularResultado($votacion);

    $votacion->update([
        'estado'      => 'cerrada',
        'cerrada_at'  => now(),
        'resultado'   => $resultado,
    ]);

    broadcast(new \App\Events\EstadoVotacionCambiado($votacion));

    ReunionLog::create([
        'reunion_id' => $votacion->reunion_id,
        'user_id'    => auth()->id(),
        'accion'     => 'votacion_cerrada',
        'metadata'   => [
            'votacion_id'       => $votacion->id,
            'pregunta'          => $votacion->pregunta,
            'total_votos'       => $totalVotos,
            'resultado'         => $resultado,
            'tipo_mayoria'      => $votacion->tipoDecision?->tipo_mayoria,
            'resultado_ganador' => $ganadora['texto'] ?? null,
            'peso_ganador'      => $ganadora['peso_total'] ?? 0,
        ],
    ]);

    return back()->with('success', 'Votación cerrada.');
}

private function calcularResultado(Votacion $votacion): string
{
    if (!$votacion->tipoDecision) {
        return 'pendiente';
    }

    $pesoSi = 0.0;
    $pesoNo = 0.0;

    foreach ($votacion->opciones as $opcion) {
        $peso = (float) Voto::withoutGlobalScopes()
            ->where('votacion_id', $votacion->id)
            ->where('opcion_id', $opcion->id)
            ->sum('peso');

        $texto = mb_strtolower($opcion->texto);
        if (str_contains($texto, 'sí') || str_contains($texto, 'si') || str_contains($texto, 'favor') || str_contains($texto, 'aprueba')) {
            $pesoSi += $peso;
        } elseif (!str_contains($texto, 'abstenci')) {
            $pesoNo += $peso;
        }
    }

    $tipoMayoria = $votacion->tipoDecision->tipo_mayoria;

    if ($tipoMayoria === 'simple') {
        return $pesoSi > $pesoNo ? 'aprobada' : 'rechazada';
    }

    $totalEdificio = (float) \App\Models\Unidad::withoutGlobalScopes()
        ->where('tenant_id', $votacion->reunion->tenant_id)
        ->where('activo', true)
        ->sum('coeficiente');

    if ($totalEdificio <= 0) {
        return 'rechazada';
    }

    $porcentajeSi = ($pesoSi / $totalEdificio) * 100;

    if ($tipoMayoria === 'calificada_70') {
        return $porcentajeSi >= 70.0 ? 'aprobada' : 'rechazada';
    }

    // unanimidad
    return round($pesoSi, 2) >= round($totalEdificio, 2) ? 'aprobada' : 'rechazada';
}
```

- [x] **Correr los tests**

```bash
./sail artisan test tests/Feature/Admin/VotacionCerrarResultadoTest.php --no-coverage
```

Esperado: 2 passed.

- [x] **Correr suite completa**

```bash
./sail artisan test --no-coverage
```

- [x] **Commit**

```bash
git add app/Http/Controllers/Admin/VotacionController.php \
        tests/Feature/Admin/VotacionCerrarResultadoTest.php
git commit -m "feat: cerrar votación persiste resultado según tipo de mayoría"
```

---

## Task 7: Actualizar controllers y rutas backend

**Files:**
- Modify: `app/Http/Controllers/Admin/ReunionController.php`
- Modify: `app/Http/Controllers/Admin/VotacionController.php` (método store y update)
- Modify: `app/Http/Controllers/Admin/CopropietarioController.php`
- Modify: `app/Http/Controllers/Admin/TenantSettingsController.php`
- Modify: `app/Http/Controllers/SuperAdmin/ReunionController.php`

- [x] **ReunionController — store y update: reemplazar `tipo` por `tipo_cuerpo` + `tipo_convocatoria`**

El controller actual no tiene `store` ni `update` propios (las reuniones admin las crea el SuperAdmin). Solo necesita que `show()` y `conducir()` pasen `tipos_decision` a las vistas.

En `show()`, agregar al `return Inertia::render(...)`:

```php
public function show(Reunion $reunion)
{
    $quorum = $this->quorumService->calcular($reunion);
    $asistencias = $reunion->asistencias()->where('confirmada_por_admin', true)->pluck('copropietario_id')->toArray();
    $copropietarios = Copropietario::withoutGlobalScopes()
        ->where('tenant_id', $reunion->tenant_id)
        ->with('user', 'unidades')
        ->get()
        ->map(fn($c) => array_merge($c->toArray(), ['asistencia' => in_array($c->id, $asistencias)]));
    $votaciones = $reunion->votaciones()->with('opciones', 'tipoDecision')->get();
    $tiposDecision = \App\Models\TipoDecision::orderBy('orden')->get();

    return Inertia::render('Admin/Reuniones/Show', compact('reunion', 'quorum', 'copropietarios', 'votaciones', 'tiposDecision'));
}
```

En `conducir()`, mismo cambio — agregar `'tipoDecision'` al eager loading de votaciones y pasar `$tiposDecision`:

```php
public function conducir(Reunion $reunion)
{
    $quorum = $this->quorumService->calcular($reunion);
    $asistencias = $reunion->asistencias()->where('confirmada_por_admin', true)->pluck('copropietario_id')->toArray();
    $copropietarios = Copropietario::withoutGlobalScopes()
        ->where('tenant_id', $reunion->tenant_id)
        ->with('user', 'unidades')
        ->get()
        ->map(fn($c) => array_merge($c->toArray(), ['asistencia' => in_array($c->id, $asistencias)]));
    $votaciones = $reunion->votaciones()->with('opciones', 'tipoDecision')->get();
    $tiposDecision = \App\Models\TipoDecision::orderBy('orden')->get();

    $resultadosIniciales = [];
    foreach ($votaciones->whereIn('estado', ['abierta', 'cerrada']) as $votacion) {
        $resultadosIniciales[$votacion->id] = $votacion->opciones->map(function ($opcion) use ($votacion) {
            $votos = \App\Models\Voto::withoutGlobalScopes()
                ->where('votacion_id', $votacion->id)
                ->where('opcion_id', $opcion->id);
            return [
                'opcion_id'  => $opcion->id,
                'texto'      => $opcion->texto,
                'count'      => $votos->count(),
                'peso_total' => (float) $votos->sum('peso'),
            ];
        })->toArray();
    }

    return Inertia::render('Admin/Reuniones/Conducir', compact('reunion', 'quorum', 'copropietarios', 'votaciones', 'resultadosIniciales', 'tiposDecision'));
}
```

- [x] **VotacionController — store: agregar `tipo_decision_id`**

En el método `store()`, actualizar validación y creación:

```php
public function store(Request $request, Reunion $reunion)
{
    $data = $request->validate([
        'tipo_decision_id' => 'required|exists:tipos_decision,id',
        'pregunta'         => 'required|string|max:500',
        'descripcion'      => 'nullable|string|max:2000',
        'opciones'         => 'required|array|min:2',
        'opciones.*.texto' => 'required|string|max:255',
    ]);

    $votacion = $reunion->votaciones()->create([
        'tipo_decision_id' => $data['tipo_decision_id'],
        'pregunta'         => $data['pregunta'],
        'descripcion'      => $data['descripcion'] ?? null,
        'estado'           => 'creada',
        'creada_por'       => auth()->id(),
        'tenant_id'        => $reunion->tenant_id,
    ]);

    foreach ($data['opciones'] as $opcion) {
        $votacion->opciones()->create(['texto' => $opcion['texto']]);
    }

    $votacion->load('opciones', 'tipoDecision');
    broadcast(new VotacionModificada($votacion, 'created'));

    return back()->with('success', 'Votación creada.');
}
```

- [x] **VotacionController — update: agregar `tipo_decision_id`**

En el método `update()`, actualizar validación y update:

```php
public function update(Request $request, Votacion $votacion)
{
    if ($votacion->estado !== 'creada') {
        abort(403, 'Solo se pueden editar votaciones en estado creada.');
    }

    $data = $request->validate([
        'tipo_decision_id' => 'required|exists:tipos_decision,id',
        'pregunta'         => 'required|string|max:500',
        'descripcion'      => 'nullable|string|max:2000',
        'opciones'         => 'required|array|min:2',
        'opciones.*.texto' => 'required|string|max:255',
    ]);

    $votacion->update([
        'tipo_decision_id' => $data['tipo_decision_id'],
        'pregunta'         => $data['pregunta'],
        'descripcion'      => $data['descripcion'] ?? null,
    ]);

    $votacion->opciones()->delete();
    foreach ($data['opciones'] as $opcion) {
        $votacion->opciones()->create(['texto' => $opcion['texto']]);
    }

    $votacion->load('opciones', 'tipoDecision');
    broadcast(new VotacionModificada($votacion, 'updated'));

    return back()->with('success', 'Votación actualizada.');
}
```

- [x] **CopropietarioController — update: agregar `en_mora`**

En el método `update()`, agregar a la validación:

```php
'en_mora' => 'boolean',
```

Y en el `$copropietario->update(...)`:

```php
'en_mora' => $data['en_mora'] ?? false,
```

- [x] **TenantSettingsController — update: agregar `restringir_voto_morosos`**

En el método `update()`, actualizar validación y fillable list:

```php
$data = $request->validate([
    'nombre'                    => 'required|string|max:255',
    'direccion'                 => 'nullable|string|max:255',
    'ciudad'                    => 'nullable|string|max:100',
    'max_poderes_por_delegado'  => 'required|integer|min:1|max:10',
    'restringir_voto_morosos'   => 'boolean',
]);

$tenant->update($data);
```

- [x] **SuperAdmin ReunionController — actualizar validación**

En `app/Http/Controllers/SuperAdmin/ReunionController.php`, método `store()`, reemplazar validación de `tipo`:

```php
$validated = $request->validate([
    'titulo'            => 'required|string|max:255',
    'tipo_cuerpo'       => 'required|in:asamblea,consejo',
    'tipo_convocatoria' => 'required|in:ordinaria,extraordinaria',
    'tipo_voto_peso'    => 'required|in:coeficiente,unidad',
    'quorum_requerido'  => 'required|numeric|min:1|max:100',
    'fecha_programada'  => 'nullable|date',
]);
```

Y en el `Reunion::create([...$validated, ...])` — el array spread ya incluirá los nuevos campos.

- [x] **Correr suite completa**

```bash
./sail artisan test --no-coverage
```

- [x] **Commit**

```bash
git add app/Http/Controllers/Admin/ReunionController.php \
        app/Http/Controllers/Admin/VotacionController.php \
        app/Http/Controllers/Admin/CopropietarioController.php \
        app/Http/Controllers/Admin/TenantSettingsController.php \
        app/Http/Controllers/SuperAdmin/ReunionController.php
git commit -m "feat: controllers actualizados con campos de cumplimiento legal"
```

---

## Task 8: Frontend — Reuniones Create y Edit

**Files:**
- Modify: `resources/js/Pages/Admin/Reuniones/Create.jsx`
- Modify: `resources/js/Pages/Admin/Reuniones/Edit.jsx`

- [x] **Actualizar Create.jsx**

Reemplazar el formulario completo de `resources/js/Pages/Admin/Reuniones/Create.jsx`:

```jsx
import AdminLayout from '@/Layouts/AdminLayout'
import { useForm } from '@inertiajs/react'

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        titulo:             '',
        tipo_cuerpo:        'asamblea',
        tipo_convocatoria:  'ordinaria',
        tipo_voto_peso:     'coeficiente',
        quorum_requerido:   51,
        fecha_programada:   '',
    })

    const submit = (e) => {
        e.preventDefault()
        post('/admin/reuniones')
    }

    const selectClass = "w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
    const inputClass  = "w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
    const labelClass  = "block text-sm font-medium text-gray-700 mb-1"

    return (
        <AdminLayout title="Nueva Reunión">
            <div className="max-w-xl">
                <form onSubmit={submit} className="bg-white rounded-lg shadow p-6 space-y-5">
                    <div>
                        <label className={labelClass}>Título *</label>
                        <input type="text" value={data.titulo} onChange={e => setData('titulo', e.target.value)}
                            className={inputClass} placeholder="Ej: Asamblea Ordinaria 2026" />
                        {errors.titulo && <p className="text-red-500 text-xs mt-1">{errors.titulo}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className={labelClass}>Tipo de cuerpo *</label>
                            <select value={data.tipo_cuerpo} onChange={e => setData('tipo_cuerpo', e.target.value)} className={selectClass}>
                                <option value="asamblea">Asamblea de propietarios</option>
                                <option value="consejo">Consejo de administración</option>
                            </select>
                        </div>
                        <div>
                            <label className={labelClass}>Tipo de convocatoria *</label>
                            <select value={data.tipo_convocatoria} onChange={e => setData('tipo_convocatoria', e.target.value)} className={selectClass}>
                                <option value="ordinaria">Ordinaria</option>
                                <option value="extraordinaria">Extraordinaria</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label className={labelClass}>Sistema de voto *</label>
                        <select value={data.tipo_voto_peso} onChange={e => setData('tipo_voto_peso', e.target.value)} className={selectClass}>
                            <option value="coeficiente">Por coeficiente de propiedad</option>
                            <option value="unidad">Por unidad (1 unidad = 1 voto)</option>
                        </select>
                    </div>

                    <div>
                        <label className={labelClass}>Quórum requerido (%) *</label>
                        <input type="number" min="1" max="100" value={data.quorum_requerido}
                            onChange={e => setData('quorum_requerido', e.target.value)} className={inputClass} />
                        {errors.quorum_requerido && <p className="text-red-500 text-xs mt-1">{errors.quorum_requerido}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>Fecha programada</label>
                        <input type="datetime-local" value={data.fecha_programada}
                            onChange={e => setData('fecha_programada', e.target.value)} className={inputClass} />
                    </div>

                    <div className="flex gap-3 pt-2">
                        <button type="submit" disabled={processing}
                            className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition">
                            {processing ? 'Creando...' : 'Crear reunión'}
                        </button>
                        <a href="/admin/reuniones" className="px-5 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </AdminLayout>
    )
}
```

- [x] **Leer Edit.jsx y replicar el mismo cambio**

```bash
cat /home/dwndz/Projects/Asambli/resources/js/Pages/Admin/Reuniones/Edit.jsx
```

Localizar el selector de `tipo` y reemplazarlo con los dos selectores `tipo_cuerpo` + `tipo_convocatoria` del mismo estilo que Create.jsx. Actualizar el `useForm` inicial para usar `tipo_cuerpo: reunion.tipo_cuerpo` y `tipo_convocatoria: reunion.tipo_convocatoria` en lugar de `tipo: reunion.tipo`.

- [x] **Verificar build**

```bash
./sail npm run build 2>&1 | tail -5
```

Esperado: sin errores de compilación.

- [x] **Commit**

```bash
git add resources/js/Pages/Admin/Reuniones/Create.jsx \
        resources/js/Pages/Admin/Reuniones/Edit.jsx
git commit -m "feat: formularios de reunión con tipo_cuerpo y tipo_convocatoria"
```

---

## Task 9: Frontend — Componente TipoDecisionSelector + Votación en Show y Conducir

**Files:**
- Create: `resources/js/Components/TipoDecisionSelector.jsx`
- Modify: `resources/js/Pages/Admin/Reuniones/Show.jsx`
- Modify: `resources/js/Pages/Admin/Reuniones/Conducir.jsx`

- [x] **Crear TipoDecisionSelector.jsx**

```jsx
const ETIQUETAS_MAYORIA = {
    simple:        'Mayoría simple — más de la mitad de votos presentes',
    calificada_70: 'Mayoría calificada — 70% del total del edificio',
    unanimidad:    'Unanimidad — 100% del total del edificio',
}

const ADVERTENCIAS_MAYORIA = {
    calificada_70: 'Esta decisión requiere el 70% del total de coeficientes del conjunto, no el 70% de los presentes. Si los asistentes suman menos del 70% del edificio, la votación no podrá aprobarse aunque todos voten a favor.',
    unanimidad:    'Esta decisión requiere el voto favorable del 100% del total de coeficientes del conjunto. No puede aprobarse si cualquier propietario vota en contra o no vota.',
}

export default function TipoDecisionSelector({ tiposDecision, value, onChange, error }) {
    const grupos = {
        simple:        tiposDecision.filter(t => t.tipo_mayoria === 'simple'),
        calificada_70: tiposDecision.filter(t => t.tipo_mayoria === 'calificada_70'),
        unanimidad:    tiposDecision.filter(t => t.tipo_mayoria === 'unanimidad'),
    }

    const seleccionado = tiposDecision.find(t => t.id === Number(value))

    return (
        <div className="space-y-3">
            <label className="block text-sm font-medium text-app-text-secondary">
                Tipo de decisión *
            </label>

            <select
                value={value ?? ''}
                onChange={e => onChange(e.target.value ? Number(e.target.value) : null)}
                className="w-full px-3 py-2 text-sm border border-surface-border rounded-lg bg-surface text-app-text-primary focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand"
            >
                <option value="">— Seleccionar tipo de decisión —</option>
                {Object.entries(grupos).map(([mayoria, tipos]) =>
                    tipos.length > 0 && (
                        <optgroup key={mayoria} label={ETIQUETAS_MAYORIA[mayoria]}>
                            {tipos.map(t => (
                                <option key={t.id} value={t.id}>{t.nombre}</option>
                            ))}
                        </optgroup>
                    )
                )}
            </select>

            {seleccionado && ADVERTENCIAS_MAYORIA[seleccionado.tipo_mayoria] && (
                <div className="flex gap-2 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs">
                    <span className="shrink-0">⚠</span>
                    <p>{ADVERTENCIAS_MAYORIA[seleccionado.tipo_mayoria]}</p>
                </div>
            )}

            {seleccionado && (
                <p className="text-xs text-app-text-muted">{seleccionado.descripcion}</p>
            )}

            {error && <p className="text-xs text-danger">{error}</p>}
        </div>
    )
}
```

- [x] **Actualizar el formulario de crear/editar votación en Show.jsx**

Leer el archivo actual para entender la estructura:

```bash
grep -n "votacion\|Votacion\|form\|useState\|modal" /home/dwndz/Projects/Asambli/resources/js/Pages/Admin/Reuniones/Show.jsx | head -30
```

Localizar el `useForm` o estado que maneja la creación de votaciones y agregar `tipo_decision_id: null`. En el formulario de creación (disponible cuando `reunion.estado === 'borrador'` o similar), agregar el componente antes del campo `pregunta`:

```jsx
import TipoDecisionSelector from '@/Components/TipoDecisionSelector'

// En el useForm:
tipo_decision_id: null,

// En el JSX del formulario, antes del input de pregunta:
<TipoDecisionSelector
    tiposDecision={tiposDecision}
    value={data.tipo_decision_id}
    onChange={val => setData('tipo_decision_id', val)}
    error={errors.tipo_decision_id}
/>
```

El prop `tiposDecision` viene de `usePage().props.tiposDecision` o del prop directo de la página.

- [x] **Actualizar el formulario de crear votación en Conducir.jsx**

Hacer el mismo cambio en `Conducir.jsx` — localizar el formulario/modal de nueva votación:

```bash
grep -n "pregunta\|tipo_decision\|form\|modal\|Modal" /home/dwndz/Projects/Asambli/resources/js/Pages/Admin/Reuniones/Conducir.jsx | head -20
```

Agregar `import TipoDecisionSelector` y el componente en el formulario de nueva votación, con `tipo_decision_id` en el estado inicial.

- [x] **Verificar build**

```bash
./sail npm run build 2>&1 | tail -5
```

- [x] **Commit**

```bash
git add resources/js/Components/TipoDecisionSelector.jsx \
        resources/js/Pages/Admin/Reuniones/Show.jsx \
        resources/js/Pages/Admin/Reuniones/Conducir.jsx
git commit -m "feat: selector de tipo de decisión con mayorías automáticas"
```

---

## Task 10: Frontend — Panel de resultados en Conducir con tipo de mayoría

**Files:**
- Modify: `resources/js/Pages/Admin/Reuniones/Conducir.jsx`

- [x] **Localizar el componente/sección de resultados en Conducir**

```bash
grep -n "resultado\|peso_total\|porcentaje\|aprobad\|VotacionCard\|ResultadosCard" \
    /home/dwndz/Projects/Asambli/resources/js/Pages/Admin/Reuniones/Conducir.jsx | head -20
```

- [x] **Crear componente de resultados con mayoría**

En `Conducir.jsx`, agregar (o reemplazar si ya existe) el componente de visualización de resultados:

```jsx
function ResultadosMayoria({ votacion, resultados, mayoriaData }) {
    if (!mayoriaData) {
        // backward compat: sin tipo_decision, mostrar resultados simples
        return (
            <div className="space-y-1">
                {resultados.map(r => (
                    <div key={r.opcion_id} className="text-sm">
                        <span className="font-medium">{r.texto}:</span> {r.count} votos
                        {votacion.reunion?.tipo_voto_peso === 'coeficiente' && ` (${r.peso_total.toFixed(2)} coef.)`}
                    </div>
                ))}
            </div>
        )
    }

    const etiquetaMayoria = {
        simple:        'Mayoría simple',
        calificada_70: 'Mayoría calificada — 70% del edificio',
        unanimidad:    'Unanimidad',
    }[mayoriaData.tipo_mayoria]

    const totalVotos = resultados.reduce((sum, r) => sum + r.peso_total, 0)
    const opcionSi   = resultados.find(r => {
        const t = r.texto.toLowerCase()
        return t.includes('sí') || t.includes('si') || t.includes('favor')
    })

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2">
                <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-brand/10 text-brand">
                    {etiquetaMayoria}
                </span>
                {mayoriaData.aprobada !== null && (
                    <span className={`text-xs font-bold px-2 py-0.5 rounded-full ${
                        mayoriaData.aprobada
                            ? 'bg-success/10 text-success'
                            : 'bg-danger/10 text-danger'
                    }`}>
                        {mayoriaData.aprobada ? '✓ APROBADA' : '✗ NO APROBADA'}
                    </span>
                )}
            </div>

            {/* Barra de progreso */}
            {mayoriaData.tipo_mayoria !== 'simple' && (
                <div>
                    <div className="flex justify-between text-xs text-app-text-muted mb-1">
                        <span>A favor: {mayoriaData.porcentaje_si}%</span>
                        <span>Requerido: {mayoriaData.umbral_requerido}%</span>
                    </div>
                    <div className="relative h-3 bg-surface-border rounded-full overflow-hidden">
                        <div
                            className={`h-full rounded-full transition-all ${mayoriaData.aprobada ? 'bg-success' : 'bg-brand'}`}
                            style={{ width: `${Math.min(mayoriaData.porcentaje_si, 100)}%` }}
                        />
                        {/* Línea de umbral */}
                        <div
                            className="absolute top-0 bottom-0 w-0.5 bg-danger"
                            style={{ left: `${mayoriaData.umbral_requerido}%` }}
                        />
                    </div>
                    <p className="text-xs text-app-text-muted mt-1">
                        Del total del edificio ({mayoriaData.total_denominador} coef.)
                    </p>
                </div>
            )}

            {mayoriaData.tipo_mayoria === 'simple' && (
                <div className="space-y-1">
                    {resultados.map(r => (
                        <div key={r.opcion_id} className="flex justify-between text-sm">
                            <span>{r.texto}</span>
                            <span className="font-medium">{r.peso_total.toFixed(2)}</span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    )
}
```

- [x] **Conectar el componente a los resultados que llegan por broadcast**

Agregar un estado separado `mayoriaDataMap` para no romper las referencias existentes a `resultados[votacion.id]`:

```jsx
// Junto al useState de resultados existente, agregar:
const [mayoriaDataMap, setMayoriaDataMap] = useState({})
```

En el listener de `ResultadosVotacionActualizados`, actualizar ambos estados:

```jsx
// El listener existente actualiza resultados normalmente (no cambiar esa línea)
setResultados(prev => ({ ...prev, [e.votacion_id]: e.resultados }))
// Agregar esta línea para capturar mayoriaData:
setMayoriaDataMap(prev => ({ ...prev, [e.votacion_id]: e.mayoria ?? null }))
```

Pasar `mayoriaData={mayoriaDataMap[votacion.id] ?? null}` al componente `ResultadosMayoria`.

- [x] **Verificar build**

```bash
./sail npm run build 2>&1 | tail -5
```

- [x] **Commit**

```bash
git add resources/js/Pages/Admin/Reuniones/Conducir.jsx
git commit -m "feat: panel de resultados muestra tipo de mayoría y umbral correcto"
```

---

## Task 11: Frontend — Sala del copropietario — restricción de mora

**Files:**
- Modify: `resources/js/Pages/Copropietario/Sala/Show.jsx`

Primero verificar que el backend pasa `en_mora` al copropietario en la sala:

- [x] **Verificar que en_mora llega a la sala**

```bash
grep -n "copropietario\|auth\|en_mora" /home/dwndz/Projects/Asambli/app/Http/Controllers/Copropietario/SalaReunionController.php 2>/dev/null | head -20
# Si el archivo está en otro path:
find /home/dwndz/Projects/Asambli/app -name "SalaReunionController.php"
```

En el controller que sirve la sala, verificar que el copropietario cargado incluye `en_mora`. Si el copropietario se pasa al componente a través de `auth()->user()` o como prop, el campo ya estará disponible al ser parte del modelo.

Si el controller pasa explícitamente el copropietario como array, agregar `en_mora` a los campos seleccionados.

- [x] **Actualizar VotacionCard en Show.jsx para manejar mora**

En `resources/js/Pages/Copropietario/Sala/Show.jsx`, localizar `VotacionCard` (línea ~154 según la lectura previa) y agregar el prop `enMora`:

```jsx
// En la firma del componente VotacionCard, agregar:
function VotacionCard({ votacionActiva, resultados, yaVotoPor, poderes, onVotar, loading, esDelegadoExterno, enMora }) {
```

Dentro de `VotacionCard`, antes del render de los botones de voto, agregar:

```jsx
if (enMora) {
    return (
        <div className="space-y-4">
            {/* Mostrar la pregunta */}
            <div>
                <p className="text-sm font-semibold text-app-text-primary">{votacionActiva.pregunta}</p>
                {votacionActiva.descripcion && (
                    <p className="text-xs text-app-text-muted mt-1">{votacionActiva.descripcion}</p>
                )}
            </div>
            {/* Mensaje de restricción */}
            <div className="p-4 rounded-xl border border-amber-200 bg-amber-50 space-y-1">
                <p className="text-sm font-semibold text-amber-800">No puede votar en esta asamblea</p>
                <p className="text-xs text-amber-700">
                    Tiene cuotas de administración en mora. De acuerdo con el Art. 38 de la Ley 675 de 2001,
                    los propietarios con 3 o más cuotas en mora no tienen derecho a votar.
                </p>
                <p className="text-xs text-amber-600 font-medium">
                    Contacte al administrador para regularizar su situación.
                </p>
            </div>
        </div>
    )
}
```

Localizar donde se llama `VotacionCard` y pasar el prop:

```jsx
<VotacionCard
    // ... props existentes ...
    enMora={copropietario?.en_mora ?? false}
/>
```

- [x] **Verificar build**

```bash
./sail npm run build 2>&1 | tail -5
```

- [x] **Commit**

```bash
git add resources/js/Pages/Copropietario/Sala/Show.jsx
git commit -m "feat: sala copropietario muestra restricción de mora (Art. 38)"
```

---

## Task 12: Frontend — Toggles de mora en admin

**Files:**
- Modify: `resources/js/Pages/Admin/Copropietarios/Edit.jsx`
- Modify: `resources/js/Pages/Admin/Configuracion.jsx`

- [x] **Agregar toggle en_mora a Copropietarios/Edit.jsx**

Agregar `en_mora` al `useForm`:

```jsx
en_mora: copropietario.en_mora ?? false,
```

Agregar el toggle en el formulario, después del campo `activo`:

```jsx
<div className="flex items-start gap-3 p-3 rounded-lg border border-surface-border bg-surface">
    <input
        id="en_mora"
        type="checkbox"
        checked={data.en_mora}
        onChange={e => setData('en_mora', e.target.checked)}
        className="mt-0.5 h-4 w-4 rounded border-surface-border text-danger focus:ring-danger"
    />
    <div>
        <label htmlFor="en_mora" className="block text-sm font-medium text-app-text-primary cursor-pointer">
            En mora (3 o más cuotas sin pagar)
        </label>
        <p className="text-xs text-app-text-muted mt-0.5">
            Al activar, este copropietario no podrá votar en ninguna asamblea (Art. 38, Ley 675/2001).
        </p>
    </div>
</div>
```

- [x] **Agregar toggle restringir_voto_morosos a Configuracion.jsx**

Agregar al `useForm`:

```jsx
restringir_voto_morosos: tenant.restringir_voto_morosos ?? true,
```

Agregar el toggle en el formulario después del campo `max_poderes_por_delegado`:

```jsx
<div className="flex items-start gap-3 p-3 rounded-lg border border-surface-border bg-surface">
    <input
        id="restringir_mora"
        type="checkbox"
        checked={data.restringir_voto_morosos}
        onChange={e => setData('restringir_voto_morosos', e.target.checked)}
        className="mt-0.5 h-4 w-4 rounded border-surface-border text-brand focus:ring-brand"
    />
    <div>
        <label htmlFor="restringir_mora" className="block text-sm font-medium text-app-text-primary cursor-pointer">
            Restringir votación a copropietarios en mora
        </label>
        <p className="text-xs text-app-text-muted mt-0.5">
            Recomendado · Art. 38, Ley 675/2001. Si desactiva esta opción, el conjunto asume
            la responsabilidad legal de permitir votar a propietarios con cuotas en mora.
        </p>
    </div>
</div>
```

- [x] **Verificar build final**

```bash
./sail npm run build 2>&1 | tail -5
```

- [x] **Correr suite completa de tests**

```bash
./sail artisan test --no-coverage
```

Esperado: todos los tests pasan.

- [x] **Commit final**

```bash
git add resources/js/Pages/Admin/Copropietarios/Edit.jsx \
        resources/js/Pages/Admin/Configuracion.jsx
git commit -m "feat: toggles de mora en perfil de copropietario y configuración del conjunto"
```

---

## Verificación final

- [x] Migración limpia desde cero: `./sail artisan migrate:fresh --seed` sin errores
- [x] `TipoDecision::count()` retorna 12
- [x] Tests pasan: `./sail artisan test --no-coverage`
- [x] Build sin errores: `./sail npm run build`
- [x] Verificar en UI: crear reunión muestra `tipo_cuerpo` + `tipo_convocatoria`
- [x] Verificar en UI: crear votación muestra `TipoDecisionSelector` con grupos por mayoría
- [x] Verificar que el advertencia de 70% aparece al seleccionar `reforma_reglamento`
