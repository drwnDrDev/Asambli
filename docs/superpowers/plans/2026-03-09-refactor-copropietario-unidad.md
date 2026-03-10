# Refactor Copropietario-Unidad: One Owner per Unit + Document Identity

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enforce one owner per unit (unidad.copropietario_id FK) while allowing one copropietario to own multiple units; add tipo_documento + numero_documento to copropietarios for identity validation.

**Architecture:** Move ownership from copropietarios.unidad_id (old many-to-many leak) to unidades.copropietario_id (FK nullable, cascadeOnDelete→nullOnDelete). Copropietario becomes a pure identity profile (one per user per tenant). QuorumService recalculates coeficiente from unidades.copropietario_id.

**Tech Stack:** Laravel 12, Pest, MySQL 8.4, Inertia/React JSX

---

## Chunk 1: Schema + Models

### Task 1: Migrations — refactor copropietarios and unidades tables

**Files:**
- Create: `database/migrations/2026_03_09_000001_refactor_copropietarios_remove_unidad_add_documento.php`
- Create: `database/migrations/2026_03_09_000002_add_copropietario_id_to_unidades.php`

- [ ] **Step 1: Create migration 1 — modify copropietarios**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('copropietarios', function (Blueprint $table) {
            // Drop old unique (tenant_id, user_id, unidad_id)
            $table->dropUnique(['tenant_id', 'user_id', 'unidad_id']);
            // Drop unidad_id FK and column
            $table->dropForeign(['unidad_id']);
            $table->dropColumn('unidad_id');
            // Add document fields
            $table->string('tipo_documento', 10)->nullable()->after('user_id');
            $table->string('numero_documento', 30)->nullable()->after('tipo_documento');
            // Restore simple unique per tenant
            $table->unique(['tenant_id', 'user_id']);
            // Unique document identity per tenant
            $table->unique(['tenant_id', 'tipo_documento', 'numero_documento']);
        });
    }

    public function down(): void
    {
        Schema::table('copropietarios', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'tipo_documento', 'numero_documento']);
            $table->dropUnique(['tenant_id', 'user_id']);
            $table->dropColumn(['tipo_documento', 'numero_documento']);
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->unique(['tenant_id', 'user_id', 'unidad_id']);
        });
    }
};
```

- [ ] **Step 2: Create migration 2 — add copropietario_id to unidades**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->foreignId('copropietario_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('copropietarios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropForeign(['copropietario_id']);
            $table->dropColumn('copropietario_id');
        });
    }
};
```

- [ ] **Step 3: Run migrations**

```bash
./sail artisan migrate
```
Expected: 2 migrations applied, no errors.

---

### Task 2: Update models

**Files:**
- Modify: `app/Models/Copropietario.php`
- Modify: `app/Models/Unidad.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Update Copropietario model**

Replace entire file:
```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Copropietario extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'tipo_documento', 'numero_documento',
        'es_residente', 'telefono', 'activo',
    ];

    protected $casts = [
        'es_residente' => 'boolean',
        'activo' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function unidades()
    {
        return $this->hasMany(Unidad::class);
    }
}
```

- [ ] **Step 2: Update Unidad model**

Replace entire file:
```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unidad extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'unidades';

    protected $fillable = [
        'tenant_id', 'copropietario_id', 'numero', 'tipo', 'coeficiente', 'torre', 'piso', 'activo',
    ];

    protected $casts = [
        'coeficiente' => 'decimal:5',
        'activo' => 'boolean',
    ];

    public function copropietario()
    {
        return $this->belongsTo(Copropietario::class);
    }
}
```

- [ ] **Step 3: Add copropietario() hasOne to User**

In `app/Models/User.php`, add after `magicLinks()`:
```php
public function copropietario()
{
    return $this->hasOne(Copropietario::class);
}
```

---

### Task 3: Update CopropietarioFactory

**Files:**
- Modify: `database/factories/CopropietarioFactory.php`

- [ ] **Step 1: Remove unidad_id, add documento fields**

Replace the `definition()` method:
```php
public function definition(): array
{
    $tipos = ['CC', 'CE', 'NIT', 'PP', 'TI', 'PEP'];
    return [
        'tenant_id' => fn() => app()->has('current_tenant') ? app('current_tenant')->id : \App\Models\Tenant::factory(),
        'user_id' => \App\Models\User::factory(),
        'tipo_documento' => fake()->randomElement($tipos),
        'numero_documento' => fake()->unique()->numerify('##########'),
        'es_residente' => true,
        'activo' => true,
    ];
}
```

---

## Chunk 2: Tests — fix broken, write new

### Task 4: Fix QuorumServiceTest

**Files:**
- Modify: `tests/Feature/QuorumServiceTest.php`

The old tests create copropietarios with `unidad_id`. After the refactor, the relationship is reversed: `unidad.copropietario_id`. Tests must create the unidad and then assign copropietario_id.

- [ ] **Step 1: Rewrite QuorumServiceTest**

Replace entire file:
```php
<?php

use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Services\QuorumService;

test('quorum se calcula por coeficiente para asambleas', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create(['tipo_voto_peso' => 'coeficiente', 'quorum_requerido' => 50.00]);

    $c1 = Copropietario::factory()->create();
    $c2 = Copropietario::factory()->create();

    Unidad::factory()->create(['copropietario_id' => $c1->id, 'coeficiente' => 30.00000]);
    Unidad::factory()->create(['copropietario_id' => $c2->id, 'coeficiente' => 70.00000]);

    // Solo c1 está presente
    Asistencia::create([
        'reunion_id' => $reunion->id,
        'copropietario_id' => $c1->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion' => now(),
    ]);

    $result = app(QuorumService::class)->calcular($reunion);

    expect($result['porcentaje_presente'])->toBe(30.0);
    expect($result['tiene_quorum'])->toBeFalse();
});

test('quorum se alcanza con suficiente coeficiente', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create(['tipo_voto_peso' => 'coeficiente', 'quorum_requerido' => 50.00]);

    $c1 = Copropietario::factory()->create();
    Unidad::factory()->create(['copropietario_id' => $c1->id, 'coeficiente' => 60.00000]);

    Asistencia::create([
        'reunion_id' => $reunion->id,
        'copropietario_id' => $c1->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion' => now(),
    ]);

    $result = app(QuorumService::class)->calcular($reunion);

    expect($result['tiene_quorum'])->toBeTrue();
});

test('copropietario con multiple unidades suma todo su coeficiente', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create(['tipo_voto_peso' => 'coeficiente', 'quorum_requerido' => 50.00]);

    $c1 = Copropietario::factory()->create();
    Unidad::factory()->create(['copropietario_id' => $c1->id, 'coeficiente' => 30.00000]);
    Unidad::factory()->create(['copropietario_id' => $c1->id, 'coeficiente' => 25.00000]);
    // total c1 = 55%

    Asistencia::create([
        'reunion_id' => $reunion->id,
        'copropietario_id' => $c1->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion' => now(),
    ]);

    $result = app(QuorumService::class)->calcular($reunion);

    expect($result['tiene_quorum'])->toBeTrue();
    expect($result['presente'])->toBe(55.0);
});
```

- [ ] **Step 2: Run test to confirm it fails (QuorumService not updated yet)**

```bash
./sail artisan test tests/Feature/QuorumServiceTest.php --no-coverage
```
Expected: FAIL — QuorumService still uses old JOIN.

---

### Task 5: Fix QuorumService

**Files:**
- Modify: `app/Services/QuorumService.php`

- [ ] **Step 1: Update calcularPorCoeficiente to use new relationship**

Replace `calcularPorCoeficiente`:
```php
private function calcularPorCoeficiente(Reunion $reunion): array
{
    $totalCoeficiente = Unidad::withoutGlobalScopes()
        ->where('tenant_id', $reunion->tenant_id)
        ->where('activo', true)
        ->sum('coeficiente');

    $presenteIds = Asistencia::where('reunion_id', $reunion->id)
        ->where('confirmada_por_admin', true)
        ->pluck('copropietario_id');

    $coeficientePresente = Unidad::withoutGlobalScopes()
        ->whereIn('copropietario_id', $presenteIds)
        ->sum('coeficiente');

    $porcentaje = $totalCoeficiente > 0
        ? round(($coeficientePresente / $totalCoeficiente) * 100, 2)
        : 0;

    return [
        'tipo' => 'coeficiente',
        'total' => (float) $totalCoeficiente,
        'presente' => (float) $coeficientePresente,
        'porcentaje_presente' => $porcentaje,
        'quorum_requerido' => (float) $reunion->quorum_requerido,
        'tiene_quorum' => $porcentaje >= $reunion->quorum_requerido,
    ];
}
```

- [ ] **Step 2: Run tests**

```bash
./sail artisan test tests/Feature/QuorumServiceTest.php --no-coverage
```
Expected: 3 tests PASS.

---

### Task 6: Fix PoderesTest

**Files:**
- Modify: `tests/Feature/PoderesTest.php`

The test creates copropietarios with `unidad_id` — just remove those references since poderes don't use unidades directly.

- [ ] **Step 1: Remove unidad_id from PoderesTest**

Replace entire file:
```php
<?php

use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\User;

test('un apoderado no puede tener más poderes que el máximo del tenant', function () {
    $tenant = Tenant::factory()->create(['max_poderes_por_delegado' => 2]);
    app()->instance('current_tenant', $tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);
    $reunion = Reunion::factory()->create(['creado_por' => $admin->id]);

    $apoderado   = Copropietario::factory()->create();
    $poderdante1 = Copropietario::factory()->create();
    $poderdante2 = Copropietario::factory()->create();
    $poderdante3 = Copropietario::factory()->create();

    Poder::create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id,
        'apoderado_id' => $apoderado->id, 'poderdante_id' => $poderdante1->id, 'registrado_por' => $admin->id]);
    Poder::create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id,
        'apoderado_id' => $apoderado->id, 'poderdante_id' => $poderdante2->id, 'registrado_por' => $admin->id]);

    expect(fn() => Poder::create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id,
        'apoderado_id' => $apoderado->id, 'poderdante_id' => $poderdante3->id, 'registrado_por' => $admin->id]))
        ->toThrow(\Exception::class);
});
```

- [ ] **Step 2: Run tests**

```bash
./sail artisan test tests/Feature/PoderesTest.php --no-coverage
```
Expected: 1 test PASS.

---

### Task 7: Fix PadronImportTest and PadronImportService

**Files:**
- Modify: `tests/Feature/PadronImportTest.php`
- Modify: `app/Services/PadronImportService.php`

The import now must: (1) find/create Copropietario by `(tenant_id, user_id)`, (2) update Unidad with `copropietario_id`. The CSV gains optional `tipo_documento` + `numero_documento` columns.

- [ ] **Step 1: Rewrite PadronImportTest**

Replace entire file:
```php
<?php

use App\Models\Copropietario;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Services\PadronImportService;
use Illuminate\Http\UploadedFile;

test('importa copropietarios desde CSV correctamente', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $csv = "numero,tipo,coeficiente,torre,nombre,email\n";
    $csv .= "101,apartamento,1.52300,A,Juan Pérez,juan@test.com\n";
    $csv .= "102,apartamento,1.52300,A,María García,maria@test.com\n";

    $file = UploadedFile::fake()->createWithContent('padron.csv', $csv);

    $result = app(PadronImportService::class)->importFromFile($file, $tenant);

    expect($result['imported'])->toBe(2);
    expect($result['errors'])->toBeEmpty();
    expect(Copropietario::count())->toBe(2);
    expect(Unidad::count())->toBe(2);
    // Cada unidad tiene su copropietario_id asignado
    expect(Unidad::whereNotNull('copropietario_id')->count())->toBe(2);
});

test('importa con tipo y numero de documento', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $csv = "numero,tipo,coeficiente,nombre,email,tipo_documento,numero_documento\n";
    $csv .= "101,apartamento,5.00000,Juan Pérez,juan@test.com,CC,12345678\n";

    $file = UploadedFile::fake()->createWithContent('padron.csv', $csv);

    $result = app(PadronImportService::class)->importFromFile($file, $tenant);

    expect($result['imported'])->toBe(1);
    $copro = Copropietario::first();
    expect($copro->tipo_documento)->toBe('CC');
    expect($copro->numero_documento)->toBe('12345678');
});

test('rechaza CSV con coeficientes mayores a 100', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $csv = "numero,tipo,coeficiente,torre,nombre,email\n";
    $csv .= "101,apartamento,60.00000,A,Juan,juan@test.com\n";
    $csv .= "102,apartamento,60.00000,A,María,maria@test.com\n";

    $file = UploadedFile::fake()->createWithContent('padron.csv', $csv);

    $result = app(PadronImportService::class)->importFromFile($file, $tenant);

    expect($result['errors'])->not->toBeEmpty();
});

test('copropietario con multiples unidades: 1 copropietario, N unidades', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    // Mismo email → mismo copropietario, dos unidades diferentes
    $csv = "numero,tipo,coeficiente,torre,nombre,email\n";
    $csv .= "101,apartamento,1.00000,A,Juan Pérez,juan@test.com\n";
    $csv .= "102,apartamento,1.00000,A,Juan Pérez,juan@test.com\n";

    $file = UploadedFile::fake()->createWithContent('padron.csv', $csv);

    $result = app(PadronImportService::class)->importFromFile($file, $tenant);

    expect($result['imported'])->toBe(2);
    expect($result['errors'])->toBeEmpty();
    expect(Copropietario::count())->toBe(1); // un solo perfil
    expect(Unidad::count())->toBe(2);        // dos unidades
    expect(Unidad::whereNotNull('copropietario_id')->count())->toBe(2);
});

test('reutiliza el mismo usuario en dos tenants distintos y crea copropietarios separados', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $csv = "numero,tipo,coeficiente,torre,nombre,email\n";
    $csv .= "101,apartamento,1.00000,A,Juan,juan@test.com\n";

    app()->instance('current_tenant', $tenantA);
    $fileA = UploadedFile::fake()->createWithContent('padron.csv', $csv);
    app(PadronImportService::class)->importFromFile($fileA, $tenantA);

    app()->instance('current_tenant', $tenantB);
    $fileB = UploadedFile::fake()->createWithContent('padron.csv', $csv);
    $result = app(PadronImportService::class)->importFromFile($fileB, $tenantB);

    // Un solo User global con ese email
    expect(\App\Models\User::withoutGlobalScopes()->where('email', 'juan@test.com')->count())->toBe(1);
    // Pero dos Copropietarios, uno por tenant
    expect(\App\Models\Copropietario::withoutGlobalScopes()->count())->toBe(2);
    expect($result['imported'])->toBe(1);
});
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
./sail artisan test tests/Feature/PadronImportTest.php --no-coverage
```
Expected: FAIL — service not updated yet.

- [ ] **Step 3: Rewrite PadronImportService**

Replace entire file:
```php
<?php

namespace App\Services;

use App\Models\Copropietario;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelReader;

class PadronImportService
{
    public function importFromFile(UploadedFile $file, Tenant $tenant): array
    {
        $rows = SimpleExcelReader::create($file->getRealPath(), $file->getClientOriginalExtension())->getRows();

        $records = collect($rows);

        $totalCoeficiente = $records->sum(fn($r) => (float) str_replace(',', '.', $r['coeficiente'] ?? 0));

        if ($totalCoeficiente > 100.001) {
            return [
                'imported' => 0,
                'errors' => ["La suma de coeficientes ({$totalCoeficiente}) supera 100."],
            ];
        }

        $imported = 0;
        $errors = [];

        DB::transaction(function () use ($records, $tenant, &$imported, &$errors) {
            foreach ($records as $index => $row) {
                $line = $index + 2;

                if (empty($row['numero']) || empty($row['email']) || empty($row['coeficiente'])) {
                    $errors[] = "Línea {$line}: campos requeridos faltantes (numero, email, coeficiente).";
                    continue;
                }

                try {
                    $user = User::withoutGlobalScopes()->firstOrCreate(
                        ['email' => $row['email']],
                        [
                            'tenant_id' => $tenant->id,
                            'name'      => $row['nombre'] ?: $row['email'],
                            'password'  => Str::random(16),
                            'rol'       => 'copropietario',
                        ]
                    );

                    $coproData = [
                        'es_residente' => isset($row['es_residente'])
                            ? filter_var($row['es_residente'], FILTER_VALIDATE_BOOLEAN)
                            : true,
                        'telefono' => $row['telefono'] ?? null,
                        'activo'   => true,
                    ];

                    if (!empty($row['tipo_documento'])) {
                        $coproData['tipo_documento']   = $row['tipo_documento'];
                        $coproData['numero_documento'] = $row['numero_documento'] ?? null;
                    }

                    $copropietario = Copropietario::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                        $coproData
                    );

                    Unidad::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'numero' => $row['numero']],
                        [
                            'copropietario_id' => $copropietario->id,
                            'tipo'             => $row['tipo'] ?? 'apartamento',
                            'coeficiente'      => (float) str_replace(',', '.', $row['coeficiente']),
                            'torre'            => $row['torre'] ?? null,
                            'piso'             => $row['piso'] ?? null,
                            'activo'           => true,
                        ]
                    );

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Línea {$line}: " . $e->getMessage();
                }
            }
        });

        return ['imported' => $imported, 'errors' => $errors];
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./sail artisan test tests/Feature/PadronImportTest.php --no-coverage
```
Expected: 5 tests PASS.

---

## Chunk 3: Controller + Views

### Task 8: Update CopropietarioController

**Files:**
- Modify: `app/Http/Controllers/Admin/CopropietarioController.php`

Changes: (1) store/update now create copropietario without unidad_id, then update unidades; (2) load `unidades` instead of `unidad`; (3) add tipo/numero_documento validation; (4) unidad assignment is now optional at copropietario creation.

- [ ] **Step 1: Replace CopropietarioController**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Unidad;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CopropietarioController extends Controller
{
    public function index()
    {
        $copropietarios = Copropietario::with(['user', 'unidades'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/Copropietarios/Index', [
            'copropietarios' => $copropietarios,
        ]);
    }

    public function create()
    {
        $unidades = Unidad::whereNull('copropietario_id')->orderBy('numero')->get();

        return Inertia::render('Admin/Copropietarios/Create', [
            'unidades' => $unidades,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'          => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'tipo_documento'  => 'nullable|in:CC,CE,NIT,PP,TI,PEP',
            'numero_documento'=> 'nullable|string|max:30',
            'telefono'        => 'nullable|string|max:20',
            'es_residente'    => 'boolean',
            'unidades'        => 'array',
            'unidades.*'      => 'exists:unidades,id',
        ]);

        $tenant = app('current_tenant');

        DB::transaction(function () use ($data, $tenant) {
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name'      => $data['nombre'],
                'email'     => $data['email'],
                'password'  => bcrypt(Str::random(16)),
                'rol'       => 'copropietario',
            ]);

            $copropietario = Copropietario::create([
                'tenant_id'        => $tenant->id,
                'user_id'          => $user->id,
                'tipo_documento'   => $data['tipo_documento'] ?? null,
                'numero_documento' => $data['numero_documento'] ?? null,
                'telefono'         => $data['telefono'] ?? null,
                'es_residente'     => $data['es_residente'] ?? false,
                'activo'           => true,
            ]);

            if (!empty($data['unidades'])) {
                Unidad::whereIn('id', $data['unidades'])->update(['copropietario_id' => $copropietario->id]);
            }
        });

        return redirect()->route('admin.copropietarios.index')
            ->with('success', 'Copropietario creado exitosamente.');
    }

    public function show(Copropietario $copropietario)
    {
        $copropietario->load(['user', 'unidades']);

        return Inertia::render('Admin/Copropietarios/Show', [
            'copropietario' => $copropietario,
        ]);
    }

    public function edit(Copropietario $copropietario)
    {
        $copropietario->load(['user', 'unidades']);
        // Unidades libres + las ya asignadas a este copropietario
        $unidades = Unidad::where(function ($q) use ($copropietario) {
            $q->whereNull('copropietario_id')
              ->orWhere('copropietario_id', $copropietario->id);
        })->orderBy('numero')->get();

        return Inertia::render('Admin/Copropietarios/Edit', [
            'copropietario' => $copropietario,
            'unidades'      => $unidades,
        ]);
    }

    public function update(Request $request, Copropietario $copropietario)
    {
        $data = $request->validate([
            'nombre'          => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email,' . $copropietario->user_id,
            'tipo_documento'  => 'nullable|in:CC,CE,NIT,PP,TI,PEP',
            'numero_documento'=> 'nullable|string|max:30',
            'telefono'        => 'nullable|string|max:20',
            'es_residente'    => 'boolean',
            'activo'          => 'boolean',
            'unidades'        => 'array',
            'unidades.*'      => 'exists:unidades,id',
        ]);

        DB::transaction(function () use ($data, $copropietario) {
            $copropietario->user->update([
                'name'  => $data['nombre'],
                'email' => $data['email'],
            ]);

            $copropietario->update([
                'tipo_documento'   => $data['tipo_documento'] ?? null,
                'numero_documento' => $data['numero_documento'] ?? null,
                'telefono'         => $data['telefono'] ?? null,
                'es_residente'     => $data['es_residente'] ?? false,
                'activo'           => $data['activo'] ?? true,
            ]);

            // Desasignar todas sus unidades, reasignar las enviadas
            Unidad::where('copropietario_id', $copropietario->id)->update(['copropietario_id' => null]);
            if (!empty($data['unidades'])) {
                Unidad::whereIn('id', $data['unidades'])->update(['copropietario_id' => $copropietario->id]);
            }
        });

        return redirect()->route('admin.copropietarios.show', $copropietario)
            ->with('success', 'Copropietario actualizado.');
    }

    public function destroy(Copropietario $copropietario)
    {
        $user = $copropietario->user;
        $copropietario->delete(); // nullOnDelete liberará sus unidades
        $user?->delete();

        return redirect()->route('admin.copropietarios.index')
            ->with('success', 'Copropietario eliminado.');
    }
}
```

---

### Task 9: Update frontend views

**Files:**
- Modify: `resources/js/Pages/Admin/Copropietarios/Index.jsx`
- Modify: `resources/js/Pages/Admin/Copropietarios/Create.jsx`
- Modify: `resources/js/Pages/Admin/Copropietarios/Edit.jsx`
- Modify: `resources/js/Pages/Admin/Copropietarios/Show.jsx`

- [ ] **Step 1: Update Index.jsx**

Replace the `<table>` column "Unidad / Coeficiente" section to show unidades count and total coeficiente. Replace entire file:

```jsx
import AdminLayout from '@/Layouts/AdminLayout'
import { Link, usePage, router } from '@inertiajs/react'

export default function Index({ copropietarios = [] }) {
    const { flash } = usePage().props

    const destroy = (id) => {
        if (confirm('¿Eliminar este copropietario? Esta acción también eliminará su usuario.')) {
            router.delete(`/admin/copropietarios/${id}`, { preserveScroll: true })
        }
    }

    return (
        <AdminLayout title="Copropietarios">
            {flash?.success && (
                <div className="mb-4 px-4 py-3 rounded-lg bg-success-bg border border-success text-success text-sm">
                    {flash.success}
                </div>
            )}

            <div className="flex justify-end mb-4">
                <Link
                    href="/admin/copropietarios/create"
                    className="inline-flex items-center gap-2 px-4 py-2 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded-lg transition-colors"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nuevo copropietario
                </Link>
            </div>

            <div className="bg-surface rounded-xl border border-surface-border overflow-hidden">
                {copropietarios.length === 0 ? (
                    <div className="text-center py-16 text-app-text-muted">
                        <svg className="w-10 h-10 mx-auto mb-3 opacity-30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                        </svg>
                        <p className="text-sm">No hay copropietarios registrados.</p>
                        <Link href="/admin/copropietarios/create" className="text-sm text-brand hover:underline mt-1 inline-block">
                            Crear el primero
                        </Link>
                    </div>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-content-bg border-b border-surface-border">
                            <tr>
                                <th className="text-left px-5 py-3 font-medium text-app-text-muted">Nombre</th>
                                <th className="text-left px-5 py-3 font-medium text-app-text-muted">Documento</th>
                                <th className="text-left px-5 py-3 font-medium text-app-text-muted">Unidades</th>
                                <th className="text-left px-5 py-3 font-medium text-app-text-muted">Coef. total</th>
                                <th className="text-left px-5 py-3 font-medium text-app-text-muted">Estado</th>
                                <th className="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-surface-border">
                            {copropietarios.map(c => {
                                const coefTotal = (c.unidades ?? []).reduce((s, u) => s + parseFloat(u.coeficiente ?? 0), 0)
                                return (
                                    <tr key={c.id} className="hover:bg-surface-hover transition-colors">
                                        <td className="px-5 py-3.5">
                                            <div className="font-medium text-app-text-primary">{c.user?.name}</div>
                                            <div className="text-xs text-app-text-muted">{c.user?.email}</div>
                                        </td>
                                        <td className="px-5 py-3.5 text-app-text-secondary">
                                            {c.tipo_documento && c.numero_documento
                                                ? `${c.tipo_documento} ${c.numero_documento}`
                                                : '—'}
                                        </td>
                                        <td className="px-5 py-3.5 text-app-text-secondary">
                                            {(c.unidades ?? []).length > 0
                                                ? (c.unidades ?? []).map(u => u.numero).join(', ')
                                                : <span className="text-app-text-muted">Sin asignar</span>}
                                        </td>
                                        <td className="px-5 py-3.5 font-mono text-app-text-secondary text-xs">
                                            {coefTotal > 0 ? `${coefTotal.toFixed(5)}%` : '—'}
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${c.activo ? 'bg-success-bg text-success' : 'bg-danger-bg text-danger'}`}>
                                                {c.activo ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <div className="flex items-center justify-end gap-3">
                                                <Link href={`/admin/copropietarios/${c.id}`} className="text-xs text-brand hover:underline">Ver</Link>
                                                <Link href={`/admin/copropietarios/${c.id}/edit`} className="text-xs text-app-text-secondary hover:text-brand">Editar</Link>
                                                <button onClick={() => destroy(c.id)} className="text-xs text-app-text-muted hover:text-danger transition-colors">Eliminar</button>
                                            </div>
                                        </td>
                                    </tr>
                                )
                            })}
                        </tbody>
                    </table>
                )}
            </div>
        </AdminLayout>
    )
}
```

- [ ] **Step 2: Update Create.jsx**

Replace entire file:
```jsx
import AdminLayout from '@/Layouts/AdminLayout'
import { Link, useForm } from '@inertiajs/react'

const TIPOS_DOC = ['CC', 'CE', 'NIT', 'PP', 'TI', 'PEP']

export default function Create({ unidades = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
        email: '',
        tipo_documento: '',
        numero_documento: '',
        telefono: '',
        es_residente: false,
        unidades: [],
    })

    const submit = (e) => {
        e.preventDefault()
        post('/admin/copropietarios')
    }

    const toggleUnidad = (id) => {
        setData('unidades', data.unidades.includes(id)
            ? data.unidades.filter(u => u !== id)
            : [...data.unidades, id])
    }

    const inputClass = "w-full px-3.5 py-2.5 rounded-lg border border-surface-border bg-surface text-app-text-primary text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors"
    const labelClass = "block text-sm font-medium text-app-text-secondary mb-1.5"
    const errorClass = "mt-1 text-xs text-danger"

    return (
        <AdminLayout title="Nuevo Copropietario">
            <div className="mb-5">
                <Link href="/admin/copropietarios" className="text-sm text-app-text-muted hover:text-brand transition-colors">
                    ← Copropietarios
                </Link>
            </div>

            <div className="max-w-lg bg-surface rounded-xl border border-surface-border p-6">
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className={labelClass}>Nombre completo</label>
                        <input type="text" value={data.nombre} onChange={e => setData('nombre', e.target.value)}
                            className={inputClass} placeholder="Juan Pérez" autoFocus />
                        {errors.nombre && <p className={errorClass}>{errors.nombre}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>Correo electrónico</label>
                        <input type="email" value={data.email} onChange={e => setData('email', e.target.value)}
                            className={inputClass} placeholder="juan@ejemplo.com" />
                        {errors.email && <p className={errorClass}>{errors.email}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className={labelClass}>Tipo documento <span className="text-app-text-muted font-normal">(opcional)</span></label>
                            <select value={data.tipo_documento} onChange={e => setData('tipo_documento', e.target.value)} className={inputClass}>
                                <option value="">Seleccionar...</option>
                                {TIPOS_DOC.map(t => <option key={t} value={t}>{t}</option>)}
                            </select>
                            {errors.tipo_documento && <p className={errorClass}>{errors.tipo_documento}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Número documento</label>
                            <input type="text" value={data.numero_documento} onChange={e => setData('numero_documento', e.target.value)}
                                className={inputClass} placeholder="12345678" />
                            {errors.numero_documento && <p className={errorClass}>{errors.numero_documento}</p>}
                        </div>
                    </div>

                    <div>
                        <label className={labelClass}>Teléfono <span className="text-app-text-muted font-normal">(opcional)</span></label>
                        <input type="text" value={data.telefono} onChange={e => setData('telefono', e.target.value)}
                            className={inputClass} placeholder="+57 300 000 0000" />
                        {errors.telefono && <p className={errorClass}>{errors.telefono}</p>}
                    </div>

                    {unidades.length > 0 && (
                        <div>
                            <label className={labelClass}>Unidades a asignar <span className="text-app-text-muted font-normal">(opcional)</span></label>
                            <div className="space-y-1.5 max-h-48 overflow-y-auto border border-surface-border rounded-lg p-3">
                                {unidades.map(u => (
                                    <label key={u.id} className="flex items-center gap-2.5 cursor-pointer py-0.5">
                                        <input type="checkbox"
                                            checked={data.unidades.includes(u.id)}
                                            onChange={() => toggleUnidad(u.id)}
                                            className="w-4 h-4 accent-brand rounded" />
                                        <span className="text-sm text-app-text-secondary">
                                            {u.numero} — {u.tipo} <span className="font-mono text-xs">({u.coeficiente}%)</span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                            {errors.unidades && <p className={errorClass}>{errors.unidades}</p>}
                        </div>
                    )}

                    <div>
                        <label className="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" checked={data.es_residente}
                                onChange={e => setData('es_residente', e.target.checked)}
                                className="w-4 h-4 accent-brand rounded" />
                            <span className="text-sm text-app-text-secondary">Es residente</span>
                        </label>
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <button type="submit" disabled={processing}
                            className="px-5 py-2.5 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded-lg transition-colors disabled:opacity-60">
                            {processing ? 'Guardando...' : 'Crear copropietario'}
                        </button>
                        <Link href="/admin/copropietarios"
                            className="px-5 py-2.5 border border-surface-border text-sm font-medium text-app-text-secondary hover:text-app-text-primary rounded-lg transition-colors">
                            Cancelar
                        </Link>
                    </div>
                </form>
            </div>
        </AdminLayout>
    )
}
```

- [ ] **Step 3: Update Edit.jsx**

Replace entire file:
```jsx
import AdminLayout from '@/Layouts/AdminLayout'
import { Link, useForm } from '@inertiajs/react'

const TIPOS_DOC = ['CC', 'CE', 'NIT', 'PP', 'TI', 'PEP']

export default function Edit({ copropietario, unidades = [] }) {
    const asignadasIds = (copropietario.unidades ?? []).map(u => u.id)

    const { data, setData, patch, processing, errors } = useForm({
        nombre: copropietario.user?.name ?? '',
        email: copropietario.user?.email ?? '',
        tipo_documento: copropietario.tipo_documento ?? '',
        numero_documento: copropietario.numero_documento ?? '',
        telefono: copropietario.telefono ?? '',
        es_residente: copropietario.es_residente ?? false,
        activo: copropietario.activo ?? true,
        unidades: asignadasIds,
    })

    const submit = (e) => {
        e.preventDefault()
        patch(`/admin/copropietarios/${copropietario.id}`)
    }

    const toggleUnidad = (id) => {
        setData('unidades', data.unidades.includes(id)
            ? data.unidades.filter(u => u !== id)
            : [...data.unidades, id])
    }

    const inputClass = "w-full px-3.5 py-2.5 rounded-lg border border-surface-border bg-surface text-app-text-primary text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors"
    const labelClass = "block text-sm font-medium text-app-text-secondary mb-1.5"
    const errorClass = "mt-1 text-xs text-danger"

    return (
        <AdminLayout title={`Editar — ${copropietario.user?.name}`}>
            <div className="mb-5">
                <Link href={`/admin/copropietarios/${copropietario.id}`} className="text-sm text-app-text-muted hover:text-brand transition-colors">
                    ← {copropietario.user?.name}
                </Link>
            </div>

            <div className="max-w-lg bg-surface rounded-xl border border-surface-border p-6">
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className={labelClass}>Nombre completo</label>
                        <input type="text" value={data.nombre} onChange={e => setData('nombre', e.target.value)} className={inputClass} />
                        {errors.nombre && <p className={errorClass}>{errors.nombre}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>Correo electrónico</label>
                        <input type="email" value={data.email} onChange={e => setData('email', e.target.value)} className={inputClass} />
                        {errors.email && <p className={errorClass}>{errors.email}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className={labelClass}>Tipo documento</label>
                            <select value={data.tipo_documento} onChange={e => setData('tipo_documento', e.target.value)} className={inputClass}>
                                <option value="">Seleccionar...</option>
                                {TIPOS_DOC.map(t => <option key={t} value={t}>{t}</option>)}
                            </select>
                            {errors.tipo_documento && <p className={errorClass}>{errors.tipo_documento}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Número documento</label>
                            <input type="text" value={data.numero_documento} onChange={e => setData('numero_documento', e.target.value)} className={inputClass} />
                            {errors.numero_documento && <p className={errorClass}>{errors.numero_documento}</p>}
                        </div>
                    </div>

                    <div>
                        <label className={labelClass}>Teléfono</label>
                        <input type="text" value={data.telefono} onChange={e => setData('telefono', e.target.value)} className={inputClass} />
                        {errors.telefono && <p className={errorClass}>{errors.telefono}</p>}
                    </div>

                    {unidades.length > 0 && (
                        <div>
                            <label className={labelClass}>Unidades asignadas</label>
                            <div className="space-y-1.5 max-h-48 overflow-y-auto border border-surface-border rounded-lg p-3">
                                {unidades.map(u => (
                                    <label key={u.id} className="flex items-center gap-2.5 cursor-pointer py-0.5">
                                        <input type="checkbox"
                                            checked={data.unidades.includes(u.id)}
                                            onChange={() => toggleUnidad(u.id)}
                                            className="w-4 h-4 accent-brand rounded" />
                                        <span className="text-sm text-app-text-secondary">
                                            {u.numero} — {u.tipo} <span className="font-mono text-xs">({u.coeficiente}%)</span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                            {errors.unidades && <p className={errorClass}>{errors.unidades}</p>}
                        </div>
                    )}

                    <div className="flex gap-5">
                        <label className="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" checked={data.es_residente}
                                onChange={e => setData('es_residente', e.target.checked)}
                                className="w-4 h-4 accent-brand rounded" />
                            <span className="text-sm text-app-text-secondary">Es residente</span>
                        </label>
                        <label className="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" checked={data.activo}
                                onChange={e => setData('activo', e.target.checked)}
                                className="w-4 h-4 accent-brand rounded" />
                            <span className="text-sm text-app-text-secondary">Activo</span>
                        </label>
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <button type="submit" disabled={processing}
                            className="px-5 py-2.5 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded-lg transition-colors disabled:opacity-60">
                            {processing ? 'Guardando...' : 'Guardar cambios'}
                        </button>
                        <Link href={`/admin/copropietarios/${copropietario.id}`}
                            className="px-5 py-2.5 border border-surface-border text-sm font-medium text-app-text-secondary hover:text-app-text-primary rounded-lg transition-colors">
                            Cancelar
                        </Link>
                    </div>
                </form>
            </div>
        </AdminLayout>
    )
}
```

- [ ] **Step 4: Update Show.jsx**

Replace entire file:
```jsx
import AdminLayout from '@/Layouts/AdminLayout'
import { Link, usePage, router } from '@inertiajs/react'

export default function Show({ copropietario }) {
    const { flash } = usePage().props
    const { user, unidades = [] } = copropietario

    const coefTotal = unidades.reduce((s, u) => s + parseFloat(u.coeficiente ?? 0), 0)

    const destroy = () => {
        if (confirm('¿Eliminar este copropietario y su usuario asociado?')) {
            router.delete(`/admin/copropietarios/${copropietario.id}`)
        }
    }

    return (
        <AdminLayout title={user?.name ?? 'Copropietario'}>
            {flash?.success && (
                <div className="mb-4 px-4 py-3 rounded-lg bg-success-bg border border-success text-success text-sm">
                    {flash.success}
                </div>
            )}

            <div className="mb-5 flex items-center gap-3">
                <Link href="/admin/copropietarios" className="text-sm text-app-text-muted hover:text-brand transition-colors">
                    ← Copropietarios
                </Link>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                {/* Info principal */}
                <div className="lg:col-span-2 bg-surface rounded-xl border border-surface-border p-6">
                    <div className="flex items-start justify-between mb-5">
                        <div className="flex items-center gap-3">
                            <div className="w-12 h-12 rounded-full bg-brand-light flex items-center justify-center text-brand font-bold text-lg flex-shrink-0">
                                {user?.name?.charAt(0)?.toUpperCase() ?? '?'}
                            </div>
                            <div>
                                <h2 className="text-lg font-bold text-app-text-primary">{user?.name}</h2>
                                <p className="text-sm text-app-text-muted">{user?.email}</p>
                            </div>
                        </div>
                        <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${copropietario.activo ? 'bg-success-bg text-success' : 'bg-danger-bg text-danger'}`}>
                            {copropietario.activo ? 'Activo' : 'Inactivo'}
                        </span>
                    </div>

                    <dl className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt className="text-app-text-muted font-medium mb-0.5">Documento</dt>
                            <dd className="text-app-text-primary">
                                {copropietario.tipo_documento && copropietario.numero_documento
                                    ? `${copropietario.tipo_documento} ${copropietario.numero_documento}`
                                    : '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-app-text-muted font-medium mb-0.5">Teléfono</dt>
                            <dd className="text-app-text-primary">{copropietario.telefono ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-app-text-muted font-medium mb-0.5">Es residente</dt>
                            <dd className="text-app-text-primary">{copropietario.es_residente ? 'Sí' : 'No'}</dd>
                        </div>
                        <div>
                            <dt className="text-app-text-muted font-medium mb-0.5">Coef. total</dt>
                            <dd className="font-mono text-app-text-primary">{coefTotal > 0 ? `${coefTotal.toFixed(5)}%` : '—'}</dd>
                        </div>
                    </dl>
                </div>

                {/* Unidades */}
                <div className="bg-surface rounded-xl border border-surface-border p-6">
                    <h3 className="text-sm font-semibold text-app-text-muted uppercase tracking-wide mb-4">
                        Unidades ({unidades.length})
                    </h3>
                    {unidades.length > 0 ? (
                        <div className="space-y-3">
                            {unidades.map(u => (
                                <dl key={u.id} className="text-sm border-b border-surface-border pb-3 last:border-0 last:pb-0">
                                    <div className="flex justify-between items-center">
                                        <dd className="text-app-text-primary font-semibold">Unidad {u.numero}</dd>
                                        <dd className="font-mono text-xs text-app-text-secondary">{u.coeficiente}%</dd>
                                    </div>
                                    <dd className="text-app-text-muted capitalize text-xs mt-0.5">
                                        {u.tipo}{u.torre ? ` · Torre ${u.torre}` : ''}{u.piso ? ` · Piso ${u.piso}` : ''}
                                    </dd>
                                </dl>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-app-text-muted">Sin unidades asignadas</p>
                    )}
                </div>
            </div>

            <div className="mt-5 flex items-center gap-3">
                <Link
                    href={`/admin/copropietarios/${copropietario.id}/edit`}
                    className="px-4 py-2 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded-lg transition-colors"
                >
                    Editar
                </Link>
                <button
                    onClick={destroy}
                    className="px-4 py-2 border border-surface-border text-sm font-medium text-danger hover:bg-danger-bg rounded-lg transition-colors"
                >
                    Eliminar
                </button>
            </div>
        </AdminLayout>
    )
}
```

---

## Chunk 4: Final verification

### Task 10: Run full test suite and build

- [ ] **Step 1: Run all tests**

```bash
./sail artisan test --no-coverage
```
Expected: All tests pass. Pay attention to: QuorumServiceTest, PoderesTest, PadronImportTest, UnidadTest, TenantScopeTest.

- [ ] **Step 2: Build frontend**

```bash
./sail npm run build
```
Expected: No errors.

- [ ] **Step 3: Verify migrations are clean**

```bash
./sail artisan migrate:status
```
Expected: All migrations show "Ran".
