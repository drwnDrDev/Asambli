# ASAMBLI Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Construir un SaaS multi-tenant web para gestionar asambleas de propiedad horizontal en Colombia con votaciones en tiempo real, quórum dinámico y reportes auditables.

**Architecture:** Monolito Laravel 11 + Inertia.js (React) con multi-tenancy por `tenant_id` en base de datos compartida MySQL. WebSockets via Laravel Reverb solo para broadcasting — los votos se procesan siempre via HTTP + transacciones DB atómicas para garantizar integridad.

**Tech Stack:** Laravel 11, React 18, Inertia.js, Tailwind CSS, MySQL 8, Redis, Laravel Reverb, Laravel Horizon, Laravel Breeze, DomPDF, Pest (testing)

---

## Estado de Progreso

| Task | Estado | Notas |
|------|--------|-------|
| Task 1 | ✅ COMPLETADA | Docker Desktop instalado y corriendo |
| Task 2 | ✅ COMPLETADA | Laravel 12 + Sail Docker (PHP 8.5, MySQL 8.4, Redis). Wrapper `./sail` creado para Windows/Git Bash |
| Task 3 | ✅ COMPLETADA | Breeze + Inertia React + Tailwind instalados, build OK |
| Task 4 | ✅ COMPLETADA | DB `asambli` configurada, migraciones base corridas |
| Task 5 | ✅ COMPLETADA | Reverb v1.8, Horizon v5.45, DomPDF v3.1, league/csv v9.28 instalados |
| Task 6 | ✅ COMPLETADA | Tenant model + migration + factory + Pest configurado |
| Task 7 | ✅ COMPLETADA | BelongsToTenant trait + TenantScope + tenant_id/rol en users |
| Task 8 | ✅ COMPLETADA | SetTenantContext middleware registrado en web group |
| Task 9 | ✅ COMPLETADA | RequireRole middleware + alias `role` + admin routes protegidas |
| Task 10 | ✅ COMPLETADA | MagicLink model + MagicLinkService + /acceso/{token} route |
| Task 11 | ✅ COMPLETADA | Unidad + Copropietario models con BelongsToTenant |
| Task 12 | ✅ COMPLETADA | PadronImportService con validación coeficientes CSV |
| Task 13+ | ⬜ PENDIENTE | |

> **Nota entorno:** Windows 11 Git Bash sin WSL2. Usar `./sail artisan`, `./sail composer`, `./sail npm` en lugar de `./vendor/bin/sail`. Docker se inicia con `WWWGROUP=1000 WWWUSER=1000 docker compose up -d`.

---

## FASE 0: Entorno y Setup del Proyecto

### Task 1: Instalar Prerrequisitos (Windows)

**Requisitos previos a instalar manualmente:**

1. **Docker Desktop** → https://www.docker.com/products/docker-desktop/
   - Instalar y arrancar Docker Desktop
   - Verificar: `docker --version` → debe mostrar versión

2. **Node.js LTS** → https://nodejs.org/
   - Verificar: `node --version` y `npm --version`

3. **PHP 8.3 + Composer** (para correr comandos fuera de Docker si es necesario)
   - PHP: https://windows.php.net/download/ (Thread Safe x64)
   - Composer: https://getcomposer.org/download/
   - Verificar: `php --version` y `composer --version`

**Step 1: Verificar Docker**
```bash
docker --version
docker compose version
```
Expected: versiones instaladas sin error.

---

### Task 2: Crear Proyecto Laravel con Sail

**Step 1: Crear proyecto Laravel 11**
```bash
cd c:/drwnDev
composer create-project laravel/laravel ASAMBLI
cd ASAMBLI
```

**Step 2: Instalar Laravel Sail**
```bash
composer require laravel/sail --dev
php artisan sail:install
```
Cuando pregunte qué servicios: seleccionar **mysql** y **redis**

**Step 3: Arrancar Sail (Docker)**
```bash
./vendor/bin/sail up -d
```
Primera vez descarga imágenes — puede tardar varios minutos.

**Step 4: Verificar que funciona**
```bash
./vendor/bin/sail artisan --version
```
Expected: `Laravel Framework 11.x.x`

**Step 5: Crear alias para no escribir ./vendor/bin/sail siempre**
En bash (~/.bashrc o ~/.zshrc):
```bash
alias sail='./vendor/bin/sail'
```
De aquí en adelante usamos `sail` en lugar de `./vendor/bin/sail`.

**Step 6: Commit inicial**
```bash
git init
git add .
git commit -m "feat: initial Laravel 11 project with Sail"
```

---

### Task 3: Instalar Stack Frontend (Breeze + Inertia + React + Tailwind)

**Step 1: Instalar Laravel Breeze**
```bash
sail composer require laravel/breeze --dev
```

**Step 2: Instalar scaffold con Inertia + React**
```bash
sail artisan breeze:install react
```
Esto instala: Inertia.js, React, Tailwind CSS, Vite, y scaffolding de auth básico.

**Step 3: Instalar dependencias Node**
```bash
sail npm install
```

**Step 4: Compilar assets**
```bash
sail npm run build
```

**Step 5: Verificar en browser**
Abrir http://localhost — debe mostrar la página de bienvenida de Laravel con estilos Tailwind.

**Step 6: Commit**
```bash
git add .
git commit -m "feat: install Breeze with Inertia React and Tailwind"
```

---

### Task 4: Configurar Base de Datos y Variables de Entorno

**Step 1: Verificar `.env`**

Sail ya configura esto automáticamente, pero verificar que `.env` tenga:
```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**Step 2: Renombrar la base de datos a `asambli`**

Editar `.env`:
```env
DB_DATABASE=asambli
```

Crear la BD:
```bash
sail mysql -e "CREATE DATABASE IF NOT EXISTS asambli;"
```

**Step 3: Correr migraciones base**
```bash
sail artisan migrate
```
Expected: migrations corridas sin error (users, password_resets, etc.)

**Step 4: Commit**
```bash
git add .env.example
git commit -m "chore: configure database and environment"
```

---

### Task 5: Instalar Paquetes Adicionales

**Step 1: Instalar todos los paquetes necesarios**
```bash
sail composer require \
  barryvdh/laravel-dompdf \
  league/csv
```

**Step 2: Instalar Laravel Reverb (WebSockets)**
```bash
sail composer require laravel/reverb
sail artisan reverb:install
```

**Step 3: Instalar Laravel Horizon (Queue monitoring)**
```bash
sail composer require laravel/horizon
sail artisan horizon:install
```

**Step 4: Publicar configuraciones**
```bash
sail artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider"
```

**Step 5: Verificar instalaciones**
```bash
sail artisan about
```
Expected: lista de paquetes sin errores.

**Step 6: Commit**
```bash
git add .
git commit -m "feat: install Reverb, Horizon, DomPDF, and CSV packages"
```

---

## FASE 1: Multi-tenancy Foundation

### Task 6: Modelo Tenant y Migración

**Files:**
- Create: `database/migrations/xxxx_create_tenants_table.php`
- Create: `app/Models/Tenant.php`
- Create: `tests/Feature/TenantTest.php`

**Step 1: Escribir el test**
```php
// tests/Feature/TenantTest.php
<?php

use App\Models\Tenant;

test('tenant can be created with required fields', function () {
    $tenant = Tenant::factory()->create([
        'nombre' => 'Conjunto Residencial El Prado',
        'nit' => '900123456-1',
    ]);

    expect($tenant->nombre)->toBe('Conjunto Residencial El Prado');
    expect($tenant->max_poderes_por_delegado)->toBe(2); // default
    expect($tenant->activo)->toBeTrue(); // default
});
```

**Step 2: Correr test — debe fallar**
```bash
sail artisan test tests/Feature/TenantTest.php
```
Expected: FAIL — clase Tenant no existe.

**Step 3: Crear migración**
```bash
sail artisan make:migration create_tenants_table
```

Editar el archivo generado en `database/migrations/`:
```php
public function up(): void
{
    Schema::create('tenants', function (Blueprint $table) {
        $table->id();
        $table->string('nombre');
        $table->string('nit')->unique();
        $table->string('direccion')->nullable();
        $table->string('ciudad')->nullable();
        $table->string('logo_url')->nullable();
        $table->unsignedTinyInteger('max_poderes_por_delegado')->default(2);
        $table->boolean('activo')->default(true);
        $table->timestamps();
    });
}
```

**Step 4: Crear modelo**
```bash
sail artisan make:model Tenant --factory
```

Editar `app/Models/Tenant.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre', 'nit', 'direccion', 'ciudad',
        'logo_url', 'max_poderes_por_delegado', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'max_poderes_por_delegado' => 'integer',
    ];
}
```

Editar `database/factories/TenantFactory.php`:
```php
public function definition(): array
{
    return [
        'nombre' => fake()->company() . ' Conjunto',
        'nit' => fake()->numerify('#########-#'),
        'direccion' => fake()->address(),
        'ciudad' => fake()->randomElement(['Bogotá', 'Medellín', 'Cali', 'Barranquilla']),
        'max_poderes_por_delegado' => 2,
        'activo' => true,
    ];
}
```

**Step 5: Correr migración**
```bash
sail artisan migrate
```

**Step 6: Correr test — debe pasar**
```bash
sail artisan test tests/Feature/TenantTest.php
```
Expected: PASS

**Step 7: Commit**
```bash
git add .
git commit -m "feat: add Tenant model and migration"
```

---

### Task 7: BelongsToTenant Trait (Global Scope)

**Files:**
- Create: `app/Traits/BelongsToTenant.php`
- Create: `app/Scopes/TenantScope.php`
- Create: `tests/Feature/TenantScopeTest.php`

**Step 1: Escribir test**
```php
// tests/Feature/TenantScopeTest.php
<?php

use App\Models\Tenant;
use App\Models\User;

test('tenant scope isolates data between tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    // Crear usuarios en cada tenant
    User::factory()->create(['tenant_id' => $tenantA->id, 'email' => 'a@test.com']);
    User::factory()->create(['tenant_id' => $tenantB->id, 'email' => 'b@test.com']);

    // Simular contexto del tenant A
    app()->instance('current_tenant', $tenantA);

    expect(User::count())->toBe(1);
    expect(User::first()->email)->toBe('a@test.com');
});
```

**Step 2: Correr test — debe fallar**
```bash
sail artisan test tests/Feature/TenantScopeTest.php
```

**Step 3: Crear TenantScope**
```php
// app/Scopes/TenantScope.php
<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->has('current_tenant')) {
            $builder->where($model->getTable() . '.tenant_id', app('current_tenant')->id);
        }
    }
}
```

**Step 4: Crear Trait**
```php
// app/Traits/BelongsToTenant.php
<?php

namespace App\Traits;

use App\Scopes\TenantScope;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (app()->has('current_tenant') && empty($model->tenant_id)) {
                $model->tenant_id = app('current_tenant')->id;
            }
        });
    }
}
```

**Step 5: Agregar `tenant_id` a la migración de users**
```bash
sail artisan make:migration add_tenant_id_and_rol_to_users_table
```

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete()->after('id');
        $table->enum('rol', ['super_admin', 'administrador', 'copropietario'])->default('copropietario')->after('email');
    });
}
```

**Step 6: Aplicar trait al modelo User**

Editar `app/Models/User.php` — agregar:
```php
use App\Traits\BelongsToTenant;

class User extends Authenticatable
{
    use BelongsToTenant;
    // ...
    protected $fillable = [
        'tenant_id', 'name', 'email', 'password', 'rol',
    ];
}
```

**Step 7: Correr migración y tests**
```bash
sail artisan migrate
sail artisan test tests/Feature/TenantScopeTest.php
```
Expected: PASS

**Step 8: Commit**
```bash
git add .
git commit -m "feat: add BelongsToTenant trait and TenantScope global scope"
```

---

### Task 8: Middleware de Resolución de Tenant

**Files:**
- Create: `app/Http/Middleware/SetTenantContext.php`
- Modify: `bootstrap/app.php`

**Step 1: Crear middleware**
```bash
sail artisan make:middleware SetTenantContext
```

```php
// app/Http/Middleware/SetTenantContext.php
<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class SetTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->tenant_id) {
            $tenant = Tenant::withoutGlobalScopes()->find($user->tenant_id);
            if ($tenant && $tenant->activo) {
                app()->instance('current_tenant', $tenant);
            }
        }

        return $next($request);
    }
}
```

**Step 2: Registrar middleware en `bootstrap/app.php`**
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', \App\Http\Middleware\SetTenantContext::class);
})
```

**Step 3: Commit**
```bash
git add .
git commit -m "feat: add SetTenantContext middleware"
```

---

## FASE 2: Autenticación y Roles

### Task 9: Middleware de Roles

**Files:**
- Create: `app/Http/Middleware/RequireRole.php`
- Create: `tests/Feature/RoleMiddlewareTest.php`

**Step 1: Test**
```php
// tests/Feature/RoleMiddlewareTest.php
<?php

use App\Models\User;

test('admin can access admin routes', function () {
    $admin = User::factory()->create(['rol' => 'administrador']);
    $this->actingAs($admin)
         ->get('/admin/dashboard')
         ->assertStatus(200);
});

test('copropietario cannot access admin routes', function () {
    $user = User::factory()->create(['rol' => 'copropietario']);
    $this->actingAs($user)
         ->get('/admin/dashboard')
         ->assertStatus(403);
});
```

**Step 2: Crear middleware**
```bash
sail artisan make:middleware RequireRole
```

```php
// app/Http/Middleware/RequireRole.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user() || !in_array($request->user()->rol, $roles)) {
            abort(403, 'No autorizado.');
        }

        return $next($request);
    }
}
```

**Step 3: Registrar alias en `bootstrap/app.php`**
```php
$middleware->alias([
    'role' => \App\Http\Middleware\RequireRole::class,
]);
```

**Step 4: Correr tests**
```bash
sail artisan test tests/Feature/RoleMiddlewareTest.php
```

**Step 5: Commit**
```bash
git add .
git commit -m "feat: add role-based middleware"
```

---

### Task 10: Magic Links para Copropietarios

**Files:**
- Create: `database/migrations/xxxx_create_magic_links_table.php`
- Create: `app/Models/MagicLink.php`
- Create: `app/Services/MagicLinkService.php`
- Create: `tests/Feature/MagicLinkTest.php`

**Step 1: Test**
```php
// tests/Feature/MagicLinkTest.php
<?php

use App\Models\User;
use App\Services\MagicLinkService;

test('magic link is created for user', function () {
    $user = User::factory()->create(['rol' => 'copropietario']);
    $service = app(MagicLinkService::class);

    $link = $service->generate($user);

    expect($link)->toContain('/acceso/');
    expect($user->magicLinks()->count())->toBe(1);
});

test('magic link expires after 48 hours', function () {
    $user = User::factory()->create(['rol' => 'copropietario']);
    $service = app(MagicLinkService::class);
    $service->generate($user);

    $magicLink = $user->magicLinks()->first();
    $magicLink->update(['expires_at' => now()->subHour()]);

    $response = $this->get('/acceso/' . $magicLink->token);
    $response->assertStatus(410); // Gone
});
```

**Step 2: Crear migración**
```bash
sail artisan make:migration create_magic_links_table
```

```php
public function up(): void
{
    Schema::create('magic_links', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('reunion_id')->nullable()->constrained()->nullOnDelete();
        $table->string('token', 64)->unique();
        $table->timestamp('expires_at');
        $table->timestamp('used_at')->nullable();
        $table->timestamps();
    });
}
```

**Step 3: Crear modelo**
```php
// app/Models/MagicLink.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MagicLink extends Model
{
    protected $fillable = ['user_id', 'reunion_id', 'token', 'expires_at', 'used_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return is_null($this->used_at) && $this->expires_at->isFuture();
    }
}
```

**Step 4: Crear servicio**
```php
// app/Services/MagicLinkService.php
<?php

namespace App\Services;

use App\Models\MagicLink;
use App\Models\User;
use Illuminate\Support\Str;

class MagicLinkService
{
    public function generate(User $user, ?int $reunionId = null): string
    {
        $token = Str::random(64);

        MagicLink::create([
            'user_id' => $user->id,
            'reunion_id' => $reunionId,
            'token' => $token,
            'expires_at' => now()->addHours(48),
        ]);

        return url('/acceso/' . $token);
    }

    public function validate(string $token): ?MagicLink
    {
        $link = MagicLink::with('user')
            ->where('token', $token)
            ->first();

        if (!$link || !$link->isValid()) {
            return null;
        }

        return $link;
    }

    public function consume(MagicLink $link): void
    {
        $link->update(['used_at' => now()]);
    }
}
```

**Step 5: Correr migración y tests**
```bash
sail artisan migrate
sail artisan test tests/Feature/MagicLinkTest.php
```

**Step 6: Commit**
```bash
git add .
git commit -m "feat: magic link authentication for copropietarios"
```

---

## FASE 3: Modelos de Dominio

### Task 11: Unidades y Copropietarios

**Files:**
- Create: `database/migrations/xxxx_create_unidades_table.php`
- Create: `database/migrations/xxxx_create_copropietarios_table.php`
- Create: `app/Models/Unidad.php`
- Create: `app/Models/Copropietario.php`
- Create: `tests/Feature/UnidadTest.php`

**Step 1: Test**
```php
// tests/Feature/UnidadTest.php
<?php

use App\Models\Tenant;
use App\Models\Unidad;

test('coeficientes de un tenant suman 100', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    Unidad::factory()->create(['coeficiente' => 50.00000]);
    Unidad::factory()->create(['coeficiente' => 50.00000]);

    $total = Unidad::sum('coeficiente');
    expect((float) $total)->toBe(100.0);
});

test('unidad pertenece a tenant correcto via scope', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    app()->instance('current_tenant', $tenantA);
    Unidad::factory()->create(['numero' => '101']);

    app()->instance('current_tenant', $tenantB);
    expect(Unidad::count())->toBe(0); // no ve las del tenant A
});
```

**Step 2: Crear migraciones**
```bash
sail artisan make:migration create_unidades_table
sail artisan make:migration create_copropietarios_table
```

Migración unidades:
```php
public function up(): void
{
    Schema::create('unidades', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $table->string('numero');
        $table->enum('tipo', ['apartamento', 'local', 'parqueadero', 'otro'])->default('apartamento');
        $table->decimal('coeficiente', 8, 5);
        $table->string('torre')->nullable();
        $table->string('piso')->nullable();
        $table->boolean('activo')->default(true);
        $table->timestamps();

        $table->unique(['tenant_id', 'numero']);
    });
}
```

Migración copropietarios:
```php
public function up(): void
{
    Schema::create('copropietarios', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('unidad_id')->constrained()->cascadeOnDelete();
        $table->boolean('es_residente')->default(true);
        $table->string('telefono')->nullable();
        $table->boolean('activo')->default(true);
        $table->timestamps();

        $table->unique(['tenant_id', 'user_id']);
    });
}
```

**Step 3: Crear modelos**
```php
// app/Models/Unidad.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unidad extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'numero', 'tipo', 'coeficiente', 'torre', 'piso', 'activo',
    ];

    protected $casts = [
        'coeficiente' => 'decimal:5',
        'activo' => 'boolean',
    ];

    public function copropietarios()
    {
        return $this->hasMany(Copropietario::class);
    }
}
```

```php
// app/Models/Copropietario.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Copropietario extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'unidad_id', 'es_residente', 'telefono', 'activo',
    ];

    protected $casts = [
        'es_residente' => 'boolean',
        'activo' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function unidad()
    {
        return $this->belongsTo(Unidad::class);
    }
}
```

**Step 4: Correr migración y tests**
```bash
sail artisan migrate
sail artisan test tests/Feature/UnidadTest.php
```

**Step 5: Commit**
```bash
git add .
git commit -m "feat: Unidad and Copropietario models with tenant isolation"
```

---

### Task 12: Importación CSV/Excel del Padrón

**Files:**
- Create: `app/Services/PadronImportService.php`
- Create: `tests/Feature/PadronImportTest.php`
- Create: `storage/app/examples/padron_ejemplo.csv`

**Step 1: Test**
```php
// tests/Feature/PadronImportTest.php
<?php

use App\Models\Tenant;
use App\Services\PadronImportService;

test('importa copropietarios desde CSV correctamente', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $csv = "numero,tipo,coeficiente,torre,nombre,email\n";
    $csv .= "101,apartamento,1.52300,A,Juan Pérez,juan@test.com\n";
    $csv .= "102,apartamento,1.52300,A,María García,maria@test.com\n";

    $service = app(PadronImportService::class);
    $result = $service->importFromString($csv, $tenant);

    expect($result['imported'])->toBe(2);
    expect($result['errors'])->toBeEmpty();
});

test('rechaza CSV con coeficientes mayores a 100', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $csv = "numero,tipo,coeficiente,torre,nombre,email\n";
    $csv .= "101,apartamento,60.00000,A,Juan,juan@test.com\n";
    $csv .= "102,apartamento,60.00000,A,María,maria@test.com\n";

    $service = app(PadronImportService::class);
    $result = $service->importFromString($csv, $tenant);

    expect($result['errors'])->not->toBeEmpty();
});
```

**Step 2: Crear servicio de importación**
```php
// app/Services/PadronImportService.php
<?php

namespace App\Services;

use App\Models\Copropietario;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class PadronImportService
{
    public function importFromString(string $csvContent, Tenant $tenant): array
    {
        $csv = Reader::createFromString($csvContent);
        $csv->setHeaderOffset(0);

        $records = collect($csv->getRecords());
        $totalCoeficiente = $records->sum('coeficiente');

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
                            'name' => $row['nombre'] ?? $row['email'],
                            'password' => bcrypt(\Str::random(16)),
                            'rol' => 'copropietario',
                        ]
                    );

                    $unidad = Unidad::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'numero' => $row['numero']],
                        [
                            'tipo' => $row['tipo'] ?? 'apartamento',
                            'coeficiente' => $row['coeficiente'],
                            'torre' => $row['torre'] ?? null,
                            'piso' => $row['piso'] ?? null,
                        ]
                    );

                    Copropietario::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                        ['unidad_id' => $unidad->id, 'activo' => true]
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

**Step 3: Correr tests**
```bash
sail artisan test tests/Feature/PadronImportTest.php
```

**Step 4: Crear ejemplo CSV**
```bash
mkdir -p storage/app/examples
```

Crear archivo `storage/app/examples/padron_ejemplo.csv`:
```
numero,tipo,coeficiente,torre,piso,nombre,email,telefono
101,apartamento,1.52300,A,1,Juan Pérez,juan@ejemplo.com,3001234567
102,apartamento,1.52300,A,1,María García,maria@ejemplo.com,3007654321
```

**Step 5: Commit**
```bash
git add .
git commit -m "feat: CSV padron import service with coeficiente validation"
```

---

### Task 13: Modelos de Reunión

**Files:**
- Create: `database/migrations/xxxx_create_reuniones_table.php`
- Create: `database/migrations/xxxx_create_reunion_logs_table.php`
- Create: `app/Models/Reunion.php`
- Create: `app/Models/ReunionLog.php`
- Create: `tests/Feature/ReunionTest.php`

**Step 1: Test**
```php
// tests/Feature/ReunionTest.php
<?php

use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\User;

test('reunion starts as borrador', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);

    $reunion = Reunion::factory()->create(['creado_por' => $admin->id]);

    expect($reunion->estado)->toBe('borrador');
});

test('reunion logs every state change', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);

    $reunion = Reunion::factory()->create(['creado_por' => $admin->id]);
    $reunion->transicionarA('convocada', $admin);

    expect($reunion->logs()->count())->toBe(1);
    expect($reunion->logs()->first()->accion)->toBe('estado_cambiado_a_convocada');
});
```

**Step 2: Crear migraciones**
```bash
sail artisan make:migration create_reuniones_table
sail artisan make:migration create_reunion_logs_table
```

Migración reuniones:
```php
public function up(): void
{
    Schema::create('reuniones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $table->string('titulo');
        $table->enum('tipo', ['asamblea', 'consejo', 'extraordinaria'])->default('asamblea');
        $table->enum('tipo_voto_peso', ['coeficiente', 'unidad'])->default('coeficiente');
        $table->decimal('quorum_requerido', 5, 2)->default(50.00);
        $table->enum('estado', ['borrador', 'convocada', 'en_curso', 'finalizada'])->default('borrador');
        $table->timestamp('fecha_programada')->nullable();
        $table->timestamp('fecha_inicio')->nullable();
        $table->timestamp('fecha_fin')->nullable();
        $table->timestamp('convocatoria_enviada_at')->nullable();
        $table->foreignId('creado_por')->constrained('users');
        $table->timestamps();
    });
}
```

Migración reunion_logs:
```php
public function up(): void
{
    Schema::create('reunion_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('reunion_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        $table->string('accion');
        $table->json('metadata')->nullable();
        $table->timestamp('created_at')->useCurrent();
        // Sin updated_at — append-only
    });
}
```

**Step 3: Crear modelos**
```php
// app/Models/Reunion.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reunion extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'titulo', 'tipo', 'tipo_voto_peso',
        'quorum_requerido', 'estado', 'fecha_programada',
        'fecha_inicio', 'fecha_fin', 'convocatoria_enviada_at', 'creado_por',
    ];

    protected $casts = [
        'fecha_programada' => 'datetime',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'convocatoria_enviada_at' => 'datetime',
        'quorum_requerido' => 'decimal:2',
    ];

    public function logs()
    {
        return $this->hasMany(ReunionLog::class);
    }

    public function asistencia()
    {
        return $this->hasMany(Asistencia::class);
    }

    public function votaciones()
    {
        return $this->hasMany(Votacion::class);
    }

    public function transicionarA(string $nuevoEstado, User $user, array $metadata = []): void
    {
        $estadoAnterior = $this->estado;
        $this->update(['estado' => $nuevoEstado]);

        ReunionLog::create([
            'reunion_id' => $this->id,
            'user_id' => $user->id,
            'accion' => "estado_cambiado_a_{$nuevoEstado}",
            'metadata' => array_merge($metadata, ['estado_anterior' => $estadoAnterior]),
        ]);

        if ($nuevoEstado === 'en_curso') {
            $this->update(['fecha_inicio' => now()]);
        }

        if ($nuevoEstado === 'finalizada') {
            $this->update(['fecha_fin' => now()]);
        }
    }
}
```

```php
// app/Models/ReunionLog.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReunionLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['reunion_id', 'user_id', 'accion', 'metadata', 'created_at'];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($log) {
            $log->created_at = now();
        });
    }
}
```

**Step 4: Correr migración y tests**
```bash
sail artisan migrate
sail artisan test tests/Feature/ReunionTest.php
```

**Step 5: Commit**
```bash
git add .
git commit -m "feat: Reunion model with state machine and append-only logs"
```

---

### Task 14: Modelos de Asistencia y Poderes

**Files:**
- Create: `database/migrations/xxxx_create_asistencias_table.php`
- Create: `database/migrations/xxxx_create_poderes_table.php`
- Create: `app/Models/Asistencia.php`
- Create: `app/Models/Poder.php`
- Create: `tests/Feature/PoderesTest.php`

**Step 1: Test**
```php
// tests/Feature/PoderesTest.php
<?php

use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Models\Tenant;

test('un apoderado no puede tener más poderes que el máximo del tenant', function () {
    $tenant = Tenant::factory()->create(['max_poderes_por_delegado' => 2]);
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create();
    $apoderado = Copropietario::factory()->create();
    $poderdante1 = Copropietario::factory()->create();
    $poderdante2 = Copropietario::factory()->create();
    $poderdante3 = Copropietario::factory()->create();

    Poder::create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id,
        'apoderado_id' => $apoderado->id, 'poderdante_id' => $poderdante1->id]);
    Poder::create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id,
        'apoderado_id' => $apoderado->id, 'poderdante_id' => $poderdante2->id]);

    expect(fn() => Poder::create(['reunion_id' => $reunion->id, 'tenant_id' => $tenant->id,
        'apoderado_id' => $apoderado->id, 'poderdante_id' => $poderdante3->id]))
        ->toThrow(\Exception::class);
});
```

**Step 2: Crear migraciones**
```bash
sail artisan make:migration create_asistencias_table
sail artisan make:migration create_poderes_table
```

Migración asistencias:
```php
public function up(): void
{
    Schema::create('asistencias', function (Blueprint $table) {
        $table->id();
        $table->foreignId('reunion_id')->constrained()->cascadeOnDelete();
        $table->foreignId('copropietario_id')->constrained()->cascadeOnDelete();
        $table->boolean('confirmada_por_admin')->default(false);
        $table->timestamp('hora_confirmacion')->nullable();
        $table->json('vota_por_poderes')->nullable();
        $table->timestamps();

        $table->unique(['reunion_id', 'copropietario_id']);
    });
}
```

Migración poderes:
```php
public function up(): void
{
    Schema::create('poderes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $table->foreignId('reunion_id')->constrained()->cascadeOnDelete();
        $table->foreignId('apoderado_id')->constrained('copropietarios')->cascadeOnDelete();
        $table->foreignId('poderdante_id')->constrained('copropietarios')->cascadeOnDelete();
        $table->string('documento_url')->nullable();
        $table->foreignId('registrado_por')->constrained('users');
        $table->timestamps();

        $table->unique(['reunion_id', 'poderdante_id']); // un solo poder por poderdante por reunión
    });
}
```

**Step 3: Crear modelos**
```php
// app/Models/Poder.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Poder extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'reunion_id', 'apoderado_id', 'poderdante_id',
        'documento_url', 'registrado_por',
    ];

    protected static function booted(): void
    {
        static::creating(function ($poder) {
            $tenant = app('current_tenant');
            $maxPoderes = $tenant->max_poderes_por_delegado;

            $count = static::withoutGlobalScopes()
                ->where('reunion_id', $poder->reunion_id)
                ->where('apoderado_id', $poder->apoderado_id)
                ->count();

            if ($count >= $maxPoderes) {
                throw new \Exception(
                    "Este apoderado ya tiene el máximo de {$maxPoderes} poderes en esta reunión."
                );
            }
        });
    }
}
```

```php
// app/Models/Asistencia.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    protected $fillable = [
        'reunion_id', 'copropietario_id', 'confirmada_por_admin',
        'hora_confirmacion', 'vota_por_poderes',
    ];

    protected $casts = [
        'confirmada_por_admin' => 'boolean',
        'hora_confirmacion' => 'datetime',
        'vota_por_poderes' => 'array',
    ];

    public function copropietario()
    {
        return $this->belongsTo(Copropietario::class);
    }
}
```

**Step 4: Correr migración y tests**
```bash
sail artisan migrate
sail artisan test tests/Feature/PoderesTest.php
```

**Step 5: Commit**
```bash
git add .
git commit -m "feat: Asistencia and Poder models with delegation limits"
```

---

### Task 15: Modelos de Votación y Votos

**Files:**
- Create: `database/migrations/xxxx_create_votaciones_table.php`
- Create: `database/migrations/xxxx_create_opciones_votacion_table.php`
- Create: `database/migrations/xxxx_create_votos_table.php`
- Create: `app/Models/Votacion.php`
- Create: `app/Models/OpcionVotacion.php`
- Create: `app/Models/Voto.php`

**Step 1: Crear migraciones**
```bash
sail artisan make:migration create_votaciones_table
sail artisan make:migration create_opciones_votacion_table
sail artisan make:migration create_votos_table
```

Migración votaciones:
```php
public function up(): void
{
    Schema::create('votaciones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $table->foreignId('reunion_id')->constrained()->cascadeOnDelete();
        $table->string('titulo');
        $table->text('descripcion')->nullable();
        $table->enum('tipo', ['si_no', 'si_no_abstencion', 'opcion_multiple'])->default('si_no');
        $table->boolean('es_secreta')->default(true);
        $table->enum('estado', ['creada', 'abierta', 'cerrada', 'pausada'])->default('creada');
        $table->timestamp('abierta_at')->nullable();
        $table->timestamp('cerrada_at')->nullable();
        $table->foreignId('creada_por')->constrained('users');
        $table->timestamps();
    });
}
```

Migración opciones:
```php
public function up(): void
{
    Schema::create('opciones_votacion', function (Blueprint $table) {
        $table->id();
        $table->foreignId('votacion_id')->constrained()->cascadeOnDelete();
        $table->string('texto');
        $table->unsignedTinyInteger('orden')->default(0);
        $table->timestamps();
    });
}
```

Migración votos:
```php
public function up(): void
{
    Schema::create('votos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $table->foreignId('votacion_id')->constrained()->cascadeOnDelete();
        $table->foreignId('copropietario_id')->constrained()->cascadeOnDelete();
        $table->foreignId('en_nombre_de')->nullable()->constrained('copropietarios')->nullOnDelete();
        $table->foreignId('opcion_id')->constrained('opciones_votacion');
        $table->decimal('peso', 8, 5)->default(1.00000);
        $table->string('ip_address', 45)->nullable();
        $table->string('user_agent')->nullable();
        $table->string('hash_verificacion', 64);
        $table->timestamp('created_at')->useCurrent();
        // Sin updated_at — votos son inmutables

        // Garantía de no duplicados
        $table->unique(['votacion_id', 'copropietario_id', 'en_nombre_de']);
    });
}
```

**Step 2: Crear modelos**
```php
// app/Models/Votacion.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Votacion extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'reunion_id', 'titulo', 'descripcion',
        'tipo', 'es_secreta', 'estado', 'abierta_at', 'cerrada_at', 'creada_por',
    ];

    protected $casts = [
        'es_secreta' => 'boolean',
        'abierta_at' => 'datetime',
        'cerrada_at' => 'datetime',
    ];

    public function opciones()
    {
        return $this->hasMany(OpcionVotacion::class)->orderBy('orden');
    }

    public function votos()
    {
        return $this->hasMany(Voto::class);
    }
}
```

```php
// app/Models/Voto.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Voto extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'votacion_id', 'copropietario_id', 'en_nombre_de',
        'opcion_id', 'peso', 'ip_address', 'user_agent', 'hash_verificacion', 'created_at',
    ];

    protected $casts = [
        'peso' => 'decimal:5',
        'created_at' => 'datetime',
    ];
}
```

**Step 3: Correr migración**
```bash
sail artisan migrate
sail artisan test
```
Expected: todos los tests pasando.

**Step 4: Commit**
```bash
git add .
git commit -m "feat: Votacion, OpcionVotacion, and Voto models with immutability constraints"
```

---

## FASE 4: Servicios de Negocio Críticos

### Task 16: QuorumService

**Files:**
- Create: `app/Services/QuorumService.php`
- Create: `tests/Feature/QuorumServiceTest.php`

**Step 1: Test**
```php
// tests/Feature/QuorumServiceTest.php
<?php

use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;
use App\Services\QuorumService;

test('quorum se calcula por coeficiente para asambleas', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create(['tipo_voto_peso' => 'coeficiente', 'quorum_requerido' => 50.00]);

    // Crear 2 unidades con coeficiente 30 y 70
    $u1 = Unidad::factory()->create(['coeficiente' => 30.00000]);
    $u2 = Unidad::factory()->create(['coeficiente' => 70.00000]);

    $c1 = Copropietario::factory()->create(['unidad_id' => $u1->id]);
    $c2 = Copropietario::factory()->create(['unidad_id' => $u2->id]);

    // Solo c1 está presente
    Asistencia::create([
        'reunion_id' => $reunion->id,
        'copropietario_id' => $c1->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion' => now(),
    ]);

    $service = app(QuorumService::class);
    $result = $service->calcular($reunion);

    expect($result['porcentaje_presente'])->toBe(30.0);
    expect($result['tiene_quorum'])->toBeFalse();
});

test('quorum se alcanza con suficiente coeficiente', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create(['tipo_voto_peso' => 'coeficiente', 'quorum_requerido' => 50.00]);

    $u1 = Unidad::factory()->create(['coeficiente' => 60.00000]);
    $c1 = Copropietario::factory()->create(['unidad_id' => $u1->id]);

    Asistencia::create([
        'reunion_id' => $reunion->id,
        'copropietario_id' => $c1->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion' => now(),
    ]);

    $result = app(QuorumService::class)->calcular($reunion);

    expect($result['tiene_quorum'])->toBeTrue();
});
```

**Step 2: Crear servicio**
```php
// app/Services/QuorumService.php
<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Unidad;

class QuorumService
{
    public function calcular(Reunion $reunion): array
    {
        if ($reunion->tipo_voto_peso === 'coeficiente') {
            return $this->calcularPorCoeficiente($reunion);
        }

        return $this->calcularPorUnidad($reunion);
    }

    private function calcularPorCoeficiente(Reunion $reunion): array
    {
        $totalCoeficiente = Unidad::withoutGlobalScopes()
            ->where('tenant_id', $reunion->tenant_id)
            ->where('activo', true)
            ->sum('coeficiente');

        $presenteIds = Asistencia::where('reunion_id', $reunion->id)
            ->where('confirmada_por_admin', true)
            ->pluck('copropietario_id');

        $coeficientePresente = Copropietario::withoutGlobalScopes()
            ->whereIn('id', $presenteIds)
            ->join('unidades', 'copropietarios.unidad_id', '=', 'unidades.id')
            ->sum('unidades.coeficiente');

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

    private function calcularPorUnidad(Reunion $reunion): array
    {
        $totalUnidades = Copropietario::withoutGlobalScopes()
            ->where('tenant_id', $reunion->tenant_id)
            ->where('activo', true)
            ->count();

        $presentes = Asistencia::where('reunion_id', $reunion->id)
            ->where('confirmada_por_admin', true)
            ->count();

        $porcentaje = $totalUnidades > 0
            ? round(($presentes / $totalUnidades) * 100, 2)
            : 0;

        return [
            'tipo' => 'unidad',
            'total' => $totalUnidades,
            'presente' => $presentes,
            'porcentaje_presente' => $porcentaje,
            'quorum_requerido' => (float) $reunion->quorum_requerido,
            'tiene_quorum' => $porcentaje >= $reunion->quorum_requerido,
        ];
    }
}
```

**Step 3: Correr tests**
```bash
sail artisan test tests/Feature/QuorumServiceTest.php
```
Expected: PASS

**Step 4: Commit**
```bash
git add .
git commit -m "feat: QuorumService calculates dynamic quorum by coeficiente or unit"
```

---

### Task 17: VotoService (Integridad Garantizada)

**Files:**
- Create: `app/Services/VotoService.php`
- Create: `tests/Feature/VotoServiceTest.php`

**Step 1: Test**
```php
// tests/Feature/VotoServiceTest.php
<?php

use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\Votacion;
use App\Models\Voto;
use App\Services\VotoService;

function setupVotoContext(): array
{
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create([
        'tenant_id' => $tenant->id,
        'estado' => 'en_curso',
        'tipo_voto_peso' => 'coeficiente',
        'quorum_requerido' => 1.0, // muy bajo para el test
    ]);

    $unidad = Unidad::factory()->create(['tenant_id' => $tenant->id, 'coeficiente' => 100.0]);
    $copropietario = Copropietario::factory()->create(['tenant_id' => $tenant->id, 'unidad_id' => $unidad->id]);

    Asistencia::create([
        'reunion_id' => $reunion->id,
        'copropietario_id' => $copropietario->id,
        'confirmada_por_admin' => true,
        'hora_confirmacion' => now(),
    ]);

    $votacion = Votacion::factory()->create([
        'tenant_id' => $tenant->id,
        'reunion_id' => $reunion->id,
        'estado' => 'abierta',
        'tipo' => 'si_no',
    ]);

    return compact('tenant', 'reunion', 'unidad', 'copropietario', 'votacion');
}

test('copropietario puede votar exitosamente', function () {
    $data = setupVotoContext();
    $opcion = $data['votacion']->opciones()->create(['texto' => 'Sí', 'orden' => 1]);

    $service = app(VotoService::class);
    $result = $service->votar(
        votacion: $data['votacion'],
        copropietario: $data['copropietario'],
        opcionId: $opcion->id,
        request: request()
    );

    expect($result['success'])->toBeTrue();
    expect(Voto::count())->toBe(1);
});

test('no se puede votar dos veces', function () {
    $data = setupVotoContext();
    $opcion = $data['votacion']->opciones()->create(['texto' => 'Sí', 'orden' => 1]);

    $service = app(VotoService::class);
    $service->votar($data['votacion'], $data['copropietario'], $opcion->id, request());

    $result = $service->votar($data['votacion'], $data['copropietario'], $opcion->id, request());

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('ya votó');
});

test('no se puede votar si la votacion esta cerrada', function () {
    $data = setupVotoContext();
    $data['votacion']->update(['estado' => 'cerrada']);
    $opcion = $data['votacion']->opciones()->create(['texto' => 'Sí', 'orden' => 1]);

    $result = app(VotoService::class)->votar(
        $data['votacion'], $data['copropietario'], $opcion->id, request()
    );

    expect($result['success'])->toBeFalse();
});
```

**Step 2: Crear servicio**
```php
// app/Services/VotoService.php
<?php

namespace App\Services;

use App\Models\Copropietario;
use App\Models\Voto;
use App\Models\Votacion;
use App\Jobs\RecalcularResultadosVotacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VotoService
{
    public function votar(
        Votacion $votacion,
        Copropietario $copropietario,
        int $opcionId,
        Request $request,
        ?int $enNombreDeId = null
    ): array {
        try {
            DB::transaction(function () use ($votacion, $copropietario, $opcionId, $request, $enNombreDeId) {
                // 1. Verificar reunión en curso
                if ($votacion->reunion->estado !== 'en_curso') {
                    throw new \Exception('La reunión no está en curso.');
                }

                // 2. Verificar quórum
                $quorumService = app(QuorumService::class);
                $quorum = $quorumService->calcular($votacion->reunion);
                if (!$quorum['tiene_quorum']) {
                    throw new \Exception('No hay quórum suficiente para votar.');
                }

                // 3. Verificar votación abierta
                if ($votacion->estado !== 'abierta') {
                    throw new \Exception('La votación no está abierta.');
                }

                // 4. Verificar no duplicado
                $existe = Voto::withoutGlobalScopes()
                    ->where('votacion_id', $votacion->id)
                    ->where('copropietario_id', $copropietario->id)
                    ->where('en_nombre_de', $enNombreDeId)
                    ->exists();

                if ($existe) {
                    throw new \Exception('Este copropietario ya votó en esta votación.');
                }

                // 5. Calcular peso
                $peso = $this->calcularPeso($votacion, $enNombreDeId ?? $copropietario->id);

                // 6. Generar hash de verificación
                $hash = hash('sha256', implode('|', [
                    $votacion->id,
                    $copropietario->id,
                    $opcionId,
                    now()->toISOString(),
                    config('app.key'),
                ]));

                // 7. Insertar voto (inmutable)
                Voto::create([
                    'tenant_id' => $votacion->tenant_id,
                    'votacion_id' => $votacion->id,
                    'copropietario_id' => $copropietario->id,
                    'en_nombre_de' => $enNombreDeId,
                    'opcion_id' => $opcionId,
                    'peso' => $peso,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'hash_verificacion' => $hash,
                    'created_at' => now(),
                ]);
            });

            // 8. Disparar job para recalcular y broadcast (fuera de la transacción)
            RecalcularResultadosVotacion::dispatch($votacion->id);

            return ['success' => true];

        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return ['success' => false, 'error' => 'Este copropietario ya votó en esta votación.'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function calcularPeso(Votacion $votacion, int $copropietarioId): float
    {
        if ($votacion->reunion->tipo_voto_peso === 'unidad') {
            return 1.0;
        }

        $copropietario = Copropietario::withoutGlobalScopes()->with('unidad')->find($copropietarioId);
        return (float) $copropietario->unidad->coeficiente;
    }
}
```

**Step 3: Crear Job para broadcast**
```bash
sail artisan make:job RecalcularResultadosVotacion
```

```php
// app/Jobs/RecalcularResultadosVotacion.php
<?php

namespace App\Jobs;

use App\Models\Votacion;
use App\Models\Voto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalcularResultadosVotacion implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $votacionId) {}

    public function handle(): void
    {
        $votacion = Votacion::with('opciones')->withoutGlobalScopes()->find($this->votacionId);

        if (!$votacion) return;

        $resultados = $votacion->opciones->map(function ($opcion) use ($votacion) {
            $votos = Voto::withoutGlobalScopes()
                ->where('votacion_id', $votacion->id)
                ->where('opcion_id', $opcion->id);

            return [
                'opcion_id' => $opcion->id,
                'texto' => $opcion->texto,
                'count' => $votos->count(),
                'peso_total' => (float) $votos->sum('peso'),
            ];
        });

        // Broadcast via Reverb
        broadcast(new \App\Events\ResultadosVotacionActualizados($votacion, $resultados->toArray()));
    }
}
```

**Step 4: Correr tests**
```bash
sail artisan test tests/Feature/VotoServiceTest.php
```
Expected: PASS

**Step 5: Commit**
```bash
git add .
git commit -m "feat: VotoService with atomic transactions and integrity guarantees"
```

---

## FASE 5: Tiempo Real (Reverb)

### Task 18: Configurar Reverb y Eventos

**Files:**
- Create: `app/Events/ResultadosVotacionActualizados.php`
- Create: `app/Events/QuorumActualizado.php`
- Create: `app/Events/EstadoVotacionCambiado.php`
- Modify: `config/reverb.php`

**Step 1: Crear eventos**
```bash
sail artisan make:event ResultadosVotacionActualizados
sail artisan make:event QuorumActualizado
sail artisan make:event EstadoVotacionCambiado
```

```php
// app/Events/ResultadosVotacionActualizados.php
<?php

namespace App\Events;

use App\Models\Votacion;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ResultadosVotacionActualizados implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Votacion $votacion,
        public array $resultados
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
            'resultados' => $this->resultados,
        ];
    }
}
```

```php
// app/Events/QuorumActualizado.php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class QuorumActualizado implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public int $reunionId,
        public array $quorumData
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("reunion.{$this->reunionId}")];
    }
}
```

```php
// app/Events/EstadoVotacionCambiado.php
<?php

namespace App\Events;

use App\Models\Votacion;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class EstadoVotacionCambiado implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(public Votacion $votacion) {}

    public function broadcastOn(): array
    {
        return [new Channel("reunion.{$this->votacion->reunion_id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'votacion_id' => $this->votacion->id,
            'estado' => $this->votacion->estado,
            'titulo' => $this->votacion->titulo,
        ];
    }
}
```

**Step 2: Configurar `.env` para Reverb**
```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=asambli-app
REVERB_APP_KEY=asambli-key-local
REVERB_APP_SECRET=asambli-secret-local
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

**Step 3: Arrancar Reverb en desarrollo**
```bash
sail artisan reverb:start
```
Dejar corriendo en una terminal aparte.

**Step 4: Configurar queue para usar Redis**

En `.env`:
```env
QUEUE_CONNECTION=redis
```

**Step 5: Arrancar Horizon**
```bash
sail artisan horizon
```
Dejar corriendo en otra terminal aparte.

**Step 6: Commit**
```bash
git add .
git commit -m "feat: Reverb events for real-time voting results and quorum updates"
```

---

## FASE 6: Notificaciones

### Task 19: Notificación de Convocatoria por Email

**Files:**
- Create: `app/Notifications/ConvocatoriaReunion.php`
- Create: `app/Services/ConvocatoriaService.php`
- Create: `tests/Feature/ConvocatoriaTest.php`

**Step 1: Test**
```php
// tests/Feature/ConvocatoriaTest.php
<?php

use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConvocatoriaService;
use Illuminate\Support\Facades\Notification;

test('convocatoria envia notificacion a todos los copropietarios activos', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'administrador']);
    $reunion = Reunion::factory()->create(['tenant_id' => $tenant->id, 'creado_por' => $admin->id]);

    $user1 = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    $user2 = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);

    Copropietario::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user1->id, 'activo' => true]);
    Copropietario::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user2->id, 'activo' => true]);

    app(ConvocatoriaService::class)->enviar($reunion, $admin);

    Notification::assertSentTo([$user1, $user2], \App\Notifications\ConvocatoriaReunion::class);
    expect($reunion->fresh()->convocatoria_enviada_at)->not->toBeNull();
});
```

**Step 2: Crear notificación**
```bash
sail artisan make:notification ConvocatoriaReunion
```

```php
// app/Notifications/ConvocatoriaReunion.php
<?php

namespace App\Notifications;

use App\Models\Reunion;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConvocatoriaReunion extends Notification
{
    public function __construct(
        public Reunion $reunion,
        public string $magicLink
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
        // Futuro: ['mail', 'whatsapp', 'sms']
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Convocatoria: {$this->reunion->titulo}")
            ->greeting("Estimado/a {$notifiable->name},")
            ->line("Está convocado/a a la siguiente reunión:")
            ->line("**{$this->reunion->titulo}**")
            ->line("Fecha: " . $this->reunion->fecha_programada?->format('d/m/Y H:i'))
            ->action('Acceder a la Reunión', $this->magicLink)
            ->line("Este enlace es personal e intransferible. Válido por 48 horas.")
            ->salutation("Atentamente, la Administración");
    }
}
```

**Step 3: Crear servicio de convocatoria**
```php
// app/Services/ConvocatoriaService.php
<?php

namespace App\Services;

use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\User;
use App\Notifications\ConvocatoriaReunion;

class ConvocatoriaService
{
    public function __construct(private MagicLinkService $magicLinkService) {}

    public function enviar(Reunion $reunion, User $admin): void
    {
        $copropietarios = Copropietario::withoutGlobalScopes()
            ->where('tenant_id', $reunion->tenant_id)
            ->where('activo', true)
            ->with('user')
            ->get();

        foreach ($copropietarios as $copropietario) {
            $link = $this->magicLinkService->generate($copropietario->user, $reunion->id);
            $copropietario->user->notify(new ConvocatoriaReunion($reunion, $link));
        }

        $reunion->update(['convocatoria_enviada_at' => now()]);
        $reunion->transicionarA('convocada', $admin, ['total_notificados' => $copropietarios->count()]);
    }
}
```

**Step 4: Correr tests**
```bash
sail artisan test tests/Feature/ConvocatoriaTest.php
```
Expected: PASS

**Step 5: Commit**
```bash
git add .
git commit -m "feat: ConvocatoriaService sends magic link emails to all copropietarios"
```

---

## FASE 7: Reportes

### Task 20: Generación de PDF y CSV

**Files:**
- Create: `app/Services/ReporteService.php`
- Create: `resources/views/reportes/acta.blade.php`
- Create: `tests/Feature/ReporteServiceTest.php`

**Step 1: Test**
```php
// tests/Feature/ReporteServiceTest.php
<?php

use App\Models\Reunion;
use App\Models\Tenant;
use App\Services\ReporteService;

test('genera PDF para una reunion finalizada', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create([
        'tenant_id' => $tenant->id,
        'estado' => 'finalizada',
    ]);

    $pdf = app(ReporteService::class)->generarPdf($reunion);

    expect($pdf)->toBeInstanceOf(\Barryvdh\DomPDF\PDF::class);
});

test('genera CSV con datos de asistencia', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current_tenant', $tenant);

    $reunion = Reunion::factory()->create(['tenant_id' => $tenant->id, 'estado' => 'finalizada']);

    $csv = app(ReporteService::class)->generarCsvAsistencia($reunion);

    expect($csv)->toContain('unidad,copropietario,coeficiente');
});
```

**Step 2: Crear vista Blade para PDF**
```bash
mkdir -p resources/views/reportes
```

Crear `resources/views/reportes/acta.blade.php`:
```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
        h1 { font-size: 16px; text-align: center; }
        h2 { font-size: 13px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #f0f0f0; padding: 6px; text-align: left; }
        td { padding: 5px; border-bottom: 1px solid #eee; }
        .footer { font-size: 9px; color: #888; text-align: center; margin-top: 40px; }
    </style>
</head>
<body>
    <h1>{{ $reunion->titulo }}</h1>
    <p style="text-align:center">{{ $tenant->nombre }} — {{ $tenant->nit }}</p>

    <h2>1. Información General</h2>
    <table>
        <tr><th>Tipo</th><td>{{ ucfirst($reunion->tipo) }}</td></tr>
        <tr><th>Fecha</th><td>{{ $reunion->fecha_inicio?->format('d/m/Y H:i') }}</td></tr>
        <tr><th>Quórum requerido</th><td>{{ $reunion->quorum_requerido }}%</td></tr>
        <tr><th>Quórum alcanzado</th><td>{{ $quorum['porcentaje_presente'] }}%</td></tr>
        <tr><th>Estado</th><td>{{ ucfirst($reunion->estado) }}</td></tr>
    </table>

    <h2>2. Asistentes</h2>
    <table>
        <tr><th>Unidad</th><th>Copropietario</th><th>Coeficiente</th><th>Hora</th></tr>
        @foreach($asistentes as $a)
        <tr>
            <td>{{ $a->copropietario->unidad->numero }}</td>
            <td>{{ $a->copropietario->user->name }}</td>
            <td>{{ $a->copropietario->unidad->coeficiente }}%</td>
            <td>{{ $a->hora_confirmacion?->format('H:i') }}</td>
        </tr>
        @endforeach
    </table>

    <h2>3. Votaciones</h2>
    @foreach($votaciones as $v)
    <p><strong>{{ $v->titulo }}</strong> ({{ $v->estado }})</p>
    <table>
        <tr><th>Opción</th><th>Votos</th><th>Peso</th><th>%</th></tr>
        @php $pesoTotal = collect($v->resultados)->sum('peso_total'); @endphp
        @foreach($v->resultados as $r)
        <tr>
            <td>{{ $r['texto'] }}</td>
            <td>{{ $r['count'] }}</td>
            <td>{{ number_format($r['peso_total'], 2) }}</td>
            <td>{{ $pesoTotal > 0 ? number_format(($r['peso_total'] / $pesoTotal) * 100, 1) : 0 }}%</td>
        </tr>
        @endforeach
    </table>
    @endforeach

    <h2>4. Log de Eventos</h2>
    <table>
        <tr><th>Fecha/Hora</th><th>Acción</th></tr>
        @foreach($logs as $log)
        <tr>
            <td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
            <td>{{ $log->accion }}</td>
        </tr>
        @endforeach
    </table>

    <div class="footer">
        Hash del documento: {{ $hash }}<br>
        Generado por ASAMBLI el {{ now()->format('d/m/Y H:i:s') }}<br>
        <strong>Pendiente de firma por el Administrador</strong>
    </div>
</body>
</html>
```

**Step 3: Crear ReporteService**
```php
// app/Services/ReporteService.php
<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Reunion;
use App\Models\Voto;
use App\Models\Votacion;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteService
{
    public function __construct(private QuorumService $quorumService) {}

    public function generarPdf(Reunion $reunion)
    {
        $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($reunion->tenant_id);
        $quorum = $this->quorumService->calcular($reunion);

        $asistentes = Asistencia::where('reunion_id', $reunion->id)
            ->where('confirmada_por_admin', true)
            ->with('copropietario.user', 'copropietario.unidad')
            ->get();

        $votaciones = Votacion::withoutGlobalScopes()
            ->where('reunion_id', $reunion->id)
            ->with('opciones')
            ->get()
            ->map(function ($v) {
                $v->resultados = $v->opciones->map(fn($o) => [
                    'texto' => $o->texto,
                    'count' => Voto::withoutGlobalScopes()->where('votacion_id', $v->id)->where('opcion_id', $o->id)->count(),
                    'peso_total' => (float) Voto::withoutGlobalScopes()->where('votacion_id', $v->id)->where('opcion_id', $o->id)->sum('peso'),
                ])->toArray();
                return $v;
            });

        $logs = $reunion->logs()->orderBy('created_at')->get();

        $contenido = view('reportes.acta', compact('reunion', 'tenant', 'quorum', 'asistentes', 'votaciones', 'logs', ))->render();
        $hash = hash('sha256', $contenido . config('app.key'));

        return Pdf::loadView('reportes.acta', compact(
            'reunion', 'tenant', 'quorum', 'asistentes', 'votaciones', 'logs', 'hash'
        ));
    }

    public function generarCsvAsistencia(Reunion $reunion): string
    {
        $rows = ["unidad,copropietario,coeficiente,hora_confirmacion\n"];

        $asistentes = Asistencia::where('reunion_id', $reunion->id)
            ->where('confirmada_por_admin', true)
            ->with('copropietario.user', 'copropietario.unidad')
            ->get();

        foreach ($asistentes as $a) {
            $rows[] = implode(',', [
                $a->copropietario->unidad->numero,
                '"' . $a->copropietario->user->name . '"',
                $a->copropietario->unidad->coeficiente,
                $a->hora_confirmacion?->format('d/m/Y H:i:s'),
            ]) . "\n";
        }

        return implode('', $rows);
    }
}
```

**Step 4: Correr tests**
```bash
sail artisan test tests/Feature/ReporteServiceTest.php
```
Expected: PASS

**Step 5: Commit**
```bash
git add .
git commit -m "feat: PDF and CSV report generation with SHA-256 document hash"
```

---

## FASE 8: HTTP Controllers y Rutas

### Task 21: Rutas y Controllers Base

**Files:**
- Modify: `routes/web.php`
- Create: `app/Http/Controllers/Admin/DashboardController.php`
- Create: `app/Http/Controllers/Admin/ReunionController.php`
- Create: `app/Http/Controllers/Admin/VotacionController.php`
- Create: `app/Http/Controllers/Admin/PadronController.php`
- Create: `app/Http/Controllers/Copropietario/SalaReunionController.php`
- Create: `app/Http/Controllers/Copropietario/VotoController.php`
- Create: `app/Http/Controllers/Auth/MagicLinkController.php`
- Create: `app/Http/Controllers/SuperAdmin/TenantController.php`

**Step 1: Definir rutas**

Editar `routes/web.php`:
```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Copropietario;
use App\Http\Controllers\SuperAdmin;

// Magic link auth
Route::get('/acceso/{token}', [MagicLinkController::class, 'acceder'])->name('magic.acceder');

// Super Admin
Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('super.')->group(function () {
    Route::resource('tenants', SuperAdmin\TenantController::class);
    Route::post('tenants/{tenant}/impersonate', [SuperAdmin\TenantController::class, 'impersonate'])->name('tenants.impersonate');
});

// Admin del conjunto
Route::middleware(['auth', 'role:administrador'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // Padrón
    Route::get('padron', [Admin\PadronController::class, 'index'])->name('padron.index');
    Route::post('padron/import', [Admin\PadronController::class, 'import'])->name('padron.import');
    Route::resource('padron/unidades', Admin\UnidadController::class)->except(['show']);
    Route::resource('padron/copropietarios', Admin\CopropietarioController::class)->except(['show']);

    // Reuniones
    Route::resource('reuniones', Admin\ReunionController::class);
    Route::post('reuniones/{reunion}/convocar', [Admin\ReunionController::class, 'convocar'])->name('reuniones.convocar');
    Route::post('reuniones/{reunion}/iniciar', [Admin\ReunionController::class, 'iniciar'])->name('reuniones.iniciar');
    Route::post('reuniones/{reunion}/finalizar', [Admin\ReunionController::class, 'finalizar'])->name('reuniones.finalizar');
    Route::post('reuniones/{reunion}/asistencia/{copropietario}', [Admin\ReunionController::class, 'confirmarAsistencia'])->name('reuniones.asistencia');
    Route::resource('reuniones.poderes', Admin\PoderController::class)->only(['index', 'store', 'destroy']);

    // Votaciones
    Route::resource('reuniones.votaciones', Admin\VotacionController::class)->only(['store', 'destroy']);
    Route::post('votaciones/{votacion}/abrir', [Admin\VotacionController::class, 'abrir'])->name('votaciones.abrir');
    Route::post('votaciones/{votacion}/cerrar', [Admin\VotacionController::class, 'cerrar'])->name('votaciones.cerrar');

    // Reportes
    Route::get('reuniones/{reunion}/reporte-pdf', [Admin\ReunionController::class, 'reportePdf'])->name('reuniones.pdf');
    Route::get('reuniones/{reunion}/reporte-csv', [Admin\ReunionController::class, 'reporteCsv'])->name('reuniones.csv');
    Route::get('reuniones/{reunion}/auditoria', [Admin\ReunionController::class, 'auditoria'])->name('reuniones.auditoria');
});

// Copropietario (votación)
Route::middleware(['auth', 'role:copropietario'])->prefix('sala')->name('sala.')->group(function () {
    Route::get('/', [Copropietario\SalaReunionController::class, 'index'])->name('index');
    Route::get('{reunion}', [Copropietario\SalaReunionController::class, 'show'])->name('show');
    Route::post('votar', [Copropietario\VotoController::class, 'store'])->name('votar');
    Route::get('historial', [Copropietario\SalaReunionController::class, 'historial'])->name('historial');
});
```

**Step 2: Crear MagicLinkController**
```php
// app/Http/Controllers/Auth/MagicLinkController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;

class MagicLinkController extends Controller
{
    public function __construct(private MagicLinkService $service) {}

    public function acceder(string $token)
    {
        $link = $this->service->validate($token);

        if (!$link) {
            abort(410, 'Este enlace ha expirado o ya fue utilizado.');
        }

        $this->service->consume($link);
        auth()->login($link->user);

        return redirect()->route('sala.index');
    }
}
```

**Step 3: Crear VotoController**
```php
// app/Http/Controllers/Copropietario/VotoController.php
<?php

namespace App\Http\Controllers\Copropietario;

use App\Http\Controllers\Controller;
use App\Models\Votacion;
use App\Models\Copropietario;
use App\Services\VotoService;
use Illuminate\Http\Request;

class VotoController extends Controller
{
    public function __construct(private VotoService $votoService) {}

    public function store(Request $request)
    {
        $request->validate([
            'votacion_id' => 'required|integer',
            'opcion_id' => 'required|integer',
            'en_nombre_de' => 'nullable|integer',
        ]);

        $votacion = Votacion::findOrFail($request->votacion_id);
        $copropietario = Copropietario::where('user_id', auth()->id())->firstOrFail();

        $result = $this->votoService->votar(
            $votacion,
            $copropietario,
            $request->opcion_id,
            $request,
            $request->en_nombre_de
        );

        if (!$result['success']) {
            return back()->withErrors(['voto' => $result['error']]);
        }

        return back()->with('success', 'Voto registrado correctamente.');
    }
}
```

**Step 4: Commit**
```bash
git add .
git commit -m "feat: routes and base controllers for admin, copropietario, and super admin"
```

---

## FASE 9: Frontend React (Inertia)

### Task 22: Layout Admin y Copropietario

**Files:**
- Create: `resources/js/Layouts/AdminLayout.jsx`
- Create: `resources/js/Layouts/SalaLayout.jsx`
- Modify: `resources/js/app.jsx`

**Step 1: Instalar dependencias de frontend**
```bash
sail npm install @headlessui/react lucide-react
```

**Step 2: Crear AdminLayout**
```jsx
// resources/js/Layouts/AdminLayout.jsx
import { Link, usePage } from '@inertiajs/react'

export default function AdminLayout({ children, title }) {
    const { auth } = usePage().props

    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="bg-white shadow-sm">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16 items-center">
                        <div className="flex items-center gap-6">
                            <span className="font-bold text-blue-700 text-lg">ASAMBLI</span>
                            <Link href="/admin/dashboard" className="text-sm text-gray-600 hover:text-gray-900">Dashboard</Link>
                            <Link href="/admin/padron" className="text-sm text-gray-600 hover:text-gray-900">Padrón</Link>
                            <Link href="/admin/reuniones" className="text-sm text-gray-600 hover:text-gray-900">Reuniones</Link>
                        </div>
                        <span className="text-sm text-gray-500">{auth.user.name}</span>
                    </div>
                </div>
            </nav>

            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {title && <h1 className="text-2xl font-bold text-gray-900 mb-6">{title}</h1>}
                {children}
            </main>
        </div>
    )
}
```

**Step 3: Crear SalaLayout (mobile-first)**
```jsx
// resources/js/Layouts/SalaLayout.jsx
import { usePage } from '@inertiajs/react'

export default function SalaLayout({ children }) {
    return (
        <div className="min-h-screen bg-slate-900 text-white">
            <header className="bg-slate-800 px-4 py-3 flex justify-between items-center">
                <span className="font-bold text-blue-400">ASAMBLI</span>
                <span className="text-sm text-slate-400">
                    {usePage().props.auth.user.name}
                </span>
            </header>
            <main className="px-4 py-6 max-w-lg mx-auto">
                {children}
            </main>
        </div>
    )
}
```

**Step 4: Compilar**
```bash
sail npm run build
```

**Step 5: Commit**
```bash
git add .
git commit -m "feat: AdminLayout and SalaLayout responsive components"
```

---

### Task 23: Panel de Conducción de Reunión (Admin)

**Files:**
- Create: `resources/js/Pages/Admin/Reuniones/Conducir.jsx`
- Create: `app/Http/Controllers/Admin/ReunionController.php` (método conducir)

**Step 1: Crear componente React con Reverb**
```jsx
// resources/js/Pages/Admin/Reuniones/Conducir.jsx
import { useState, useEffect } from 'react'
import { router, usePage } from '@inertiajs/react'
import AdminLayout from '@/Layouts/AdminLayout'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Configurar Echo para Reverb
window.Pusher = Pusher
const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws'],
})

export default function Conducir({ reunion, quorum: initialQuorum, copropietarios }) {
    const [quorum, setQuorum] = useState(initialQuorum)
    const [votacionActiva, setVotacionActiva] = useState(null)
    const [resultados, setResultados] = useState({})

    useEffect(() => {
        const channel = echo.channel(`reunion.${reunion.id}`)

        channel.listen('.QuorumActualizado', (e) => {
            setQuorum(e.quorumData)
        })

        channel.listen('.EstadoVotacionCambiado', (e) => {
            if (e.estado === 'abierta') setVotacionActiva(e)
            if (e.estado === 'cerrada') setVotacionActiva(null)
        })

        channel.listen('.ResultadosVotacionActualizados', (e) => {
            setResultados(prev => ({ ...prev, [e.votacion_id]: e.resultados }))
        })

        return () => echo.leave(`reunion.${reunion.id}`)
    }, [reunion.id])

    const confirmarAsistencia = (copropietarioId) => {
        router.post(`/admin/reuniones/${reunion.id}/asistencia/${copropietarioId}`, {}, {
            preserveScroll: true,
        })
    }

    return (
        <AdminLayout title={reunion.titulo}>
            {/* Indicador de Quórum */}
            <div className={`rounded-lg p-4 mb-6 ${quorum.tiene_quorum ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}`}>
                <div className="flex justify-between items-center">
                    <div>
                        <p className="font-semibold text-lg">
                            Quórum: {quorum.porcentaje_presente}%
                        </p>
                        <p className="text-sm text-gray-600">
                            Requerido: {quorum.quorum_requerido}% — {quorum.presente} de {quorum.total} {quorum.tipo === 'coeficiente' ? 'puntos de coeficiente' : 'unidades'}
                        </p>
                    </div>
                    <span className={`text-2xl font-bold ${quorum.tiene_quorum ? 'text-green-600' : 'text-red-600'}`}>
                        {quorum.tiene_quorum ? '✓ HAY QUÓRUM' : '✗ SIN QUÓRUM'}
                    </span>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Lista de asistencia */}
                <div className="bg-white rounded-lg shadow p-4">
                    <h2 className="font-semibold text-gray-900 mb-4">Asistencia</h2>
                    <div className="space-y-2 max-h-96 overflow-y-auto">
                        {copropietarios.map(c => (
                            <div key={c.id} className="flex justify-between items-center py-2 border-b">
                                <div>
                                    <p className="text-sm font-medium">{c.user.name}</p>
                                    <p className="text-xs text-gray-500">Unidad {c.unidad.numero} — {c.unidad.coeficiente}%</p>
                                </div>
                                {c.asistencia ? (
                                    <span className="text-xs text-green-600 font-medium">✓ Presente</span>
                                ) : (
                                    <button
                                        onClick={() => confirmarAsistencia(c.id)}
                                        className="text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700"
                                    >
                                        Confirmar
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                {/* Panel de votaciones */}
                <div className="bg-white rounded-lg shadow p-4">
                    <h2 className="font-semibold text-gray-900 mb-4">Votaciones</h2>
                    {/* Aquí va el componente de gestión de votaciones */}
                    <p className="text-sm text-gray-500">Panel de votaciones activas</p>
                </div>
            </div>
        </AdminLayout>
    )
}
```

**Step 2: Instalar laravel-echo y pusher-js**
```bash
sail npm install laravel-echo pusher-js
```

**Step 3: Compilar y verificar**
```bash
sail npm run build
```

**Step 4: Commit**
```bash
git add .
git commit -m "feat: ReunionConducir panel with real-time quorum and Reverb integration"
```

---

### Task 24: Pantalla de Votación para Copropietario (Mobile)

**Files:**
- Create: `resources/js/Pages/Sala/Show.jsx`

```jsx
// resources/js/Pages/Sala/Show.jsx
import { useState, useEffect } from 'react'
import { router } from '@inertiajs/react'
import SalaLayout from '@/Layouts/SalaLayout'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher
const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws'],
})

export default function SalaShow({ reunion, quorum: initialQuorum, poderes = [], yaVotoPor = [] }) {
    const [quorum, setQuorum] = useState(initialQuorum)
    const [votacionActiva, setVotacionActiva] = useState(null)
    const [votando, setVotando] = useState(false)
    const [votosEmitidos, setVotosEmitidos] = useState(yaVotoPor)

    useEffect(() => {
        const channel = echo.channel(`reunion.${reunion.id}`)

        channel.listen('.QuorumActualizado', (e) => setQuorum(e.quorumData))
        channel.listen('.EstadoVotacionCambiado', (e) => {
            setVotacionActiva(e.estado === 'abierta' ? e : null)
        })

        return () => echo.leave(`reunion.${reunion.id}`)
    }, [reunion.id])

    const emitirVoto = (opcionId, enNombreDeId = null) => {
        if (votando) return
        setVotando(true)

        router.post('/sala/votar', {
            votacion_id: votacionActiva.votacion_id,
            opcion_id: opcionId,
            en_nombre_de: enNombreDeId,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setVotosEmitidos(prev => [...prev, enNombreDeId ?? 'propio'])
            },
            onFinish: () => setVotando(false),
        })
    }

    return (
        <SalaLayout>
            {/* Estado de quórum */}
            <div className={`rounded-xl p-4 mb-6 text-center ${quorum.tiene_quorum ? 'bg-green-900/50' : 'bg-red-900/50'}`}>
                <p className="text-sm text-slate-400 mb-1">Quórum de la reunión</p>
                <p className="text-3xl font-bold">{quorum.porcentaje_presente}%</p>
                <p className={`text-sm mt-1 ${quorum.tiene_quorum ? 'text-green-400' : 'text-red-400'}`}>
                    {quorum.tiene_quorum ? 'Quórum alcanzado' : 'Sin quórum suficiente'}
                </p>
            </div>

            {/* Votación activa */}
            {votacionActiva ? (
                <div className="bg-slate-800 rounded-xl p-5">
                    <h2 className="font-bold text-lg mb-2">{votacionActiva.titulo}</h2>
                    <p className="text-slate-400 text-sm mb-4">Selecciona tu voto</p>

                    {/* Voto propio */}
                    {!votosEmitidos.includes('propio') && (
                        <div className="mb-4">
                            <p className="text-xs text-slate-500 uppercase mb-2">Tu voto</p>
                            {votacionActiva.opciones?.map(opcion => (
                                <button
                                    key={opcion.id}
                                    onClick={() => emitirVoto(opcion.id)}
                                    disabled={votando}
                                    className="w-full mb-2 py-4 text-lg font-semibold rounded-xl bg-blue-600 hover:bg-blue-500 active:scale-95 transition disabled:opacity-50"
                                >
                                    {opcion.texto}
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Votos como apoderado */}
                    {poderes.map(poder => (
                        !votosEmitidos.includes(poder.poderdante_id) && (
                            <div key={poder.id} className="mb-4 border-t border-slate-700 pt-4">
                                <p className="text-xs text-yellow-400 uppercase mb-2">
                                    En nombre de: {poder.poderdante.user.name}
                                </p>
                                {votacionActiva.opciones?.map(opcion => (
                                    <button
                                        key={opcion.id}
                                        onClick={() => emitirVoto(opcion.id, poder.poderdante_id)}
                                        disabled={votando}
                                        className="w-full mb-2 py-3 text-base font-medium rounded-xl bg-yellow-700 hover:bg-yellow-600 active:scale-95 transition disabled:opacity-50"
                                    >
                                        {opcion.texto}
                                    </button>
                                ))}
                            </div>
                        )
                    ))}

                    {votosEmitidos.length > 0 && (
                        <p className="text-center text-green-400 text-sm mt-2">
                            ✓ {votosEmitidos.length} voto(s) registrado(s)
                        </p>
                    )}
                </div>
            ) : (
                <div className="bg-slate-800 rounded-xl p-8 text-center">
                    <div className="text-4xl mb-3">⏳</div>
                    <p className="text-slate-400">Esperando que el administrador abra una votación...</p>
                </div>
            )}
        </SalaLayout>
    )
}
```

**Step 2: Compilar**
```bash
sail npm run build
```

**Step 3: Commit**
```bash
git add .
git commit -m "feat: mobile voting screen with real-time updates and delegation support"
```

---

## FASE 10: Despliegue en VPS

### Task 25: Preparar Servidor (Ubuntu 22.04)

**Step 1: Conectarse al VPS**
```bash
ssh root@TU_IP_DEL_VPS
```

**Step 2: Actualizar sistema**
```bash
apt update && apt upgrade -y
```

**Step 3: Instalar dependencias del servidor**
```bash
# PHP 8.3
add-apt-repository ppa:ondrej/php -y
apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-redis php8.3-gd \
  php8.3-intl php8.3-bcmath

# Nginx
apt install -y nginx

# MySQL 8
apt install -y mysql-server
mysql_secure_installation

# Redis
apt install -y redis-server

# Node.js
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Supervisor
apt install -y supervisor
```

**Step 4: Crear base de datos de producción**
```bash
mysql -u root -p
```
```sql
CREATE DATABASE asambli_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'asambli'@'localhost' IDENTIFIED BY 'TU_PASSWORD_SEGURO';
GRANT ALL PRIVILEGES ON asambli_prod.* TO 'asambli'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

### Task 26: Desplegar Aplicación

**Step 1: Subir código al servidor**
```bash
# En el servidor
mkdir -p /var/www/asambli
cd /var/www/asambli
git clone TU_REPOSITORIO_URL .
```

**Step 2: Instalar dependencias**
```bash
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

**Step 3: Configurar `.env` de producción**
```bash
cp .env.example .env
nano .env
```

Valores críticos para producción:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tudominio.com

DB_DATABASE=asambli_prod
DB_USERNAME=asambli
DB_PASSWORD=TU_PASSWORD_SEGURO

QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=asambli-prod
REVERB_APP_KEY=GENERA_CON_openssl_rand_hex_32
REVERB_APP_SECRET=GENERA_CON_openssl_rand_hex_32
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=https

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=TU_MAILGUN_USER
MAIL_PASSWORD=TU_MAILGUN_PASSWORD
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME=ASAMBLI
```

**Step 4: Generar claves y migrar**
```bash
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Step 5: Configurar Nginx**
```bash
nano /etc/nginx/sites-available/asambli
```

```nginx
server {
    listen 80;
    server_name tudominio.com www.tudominio.com;
    root /var/www/asambli/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Reverb WebSocket proxy
    location /app/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/asambli /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

**Step 6: Configurar SSL con Let's Encrypt**
```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d tudominio.com -d www.tudominio.com
```

**Step 7: Configurar Supervisor para Queue Workers y Reverb**

Crear `/etc/supervisor/conf.d/asambli-worker.conf`:
```ini
[program:asambli-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/asambli/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/asambli/storage/logs/worker.log
stopwaitsecs=3600
```

Crear `/etc/supervisor/conf.d/asambli-reverb.conf`:
```ini
[program:asambli-reverb]
process_name=%(program_name)s
command=php /var/www/asambli/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/asambli/storage/logs/reverb.log
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start all
supervisorctl status
```

**Step 8: Permisos correctos**
```bash
chown -R www-data:www-data /var/www/asambli/storage
chown -R www-data:www-data /var/www/asambli/bootstrap/cache
chmod -R 775 /var/www/asambli/storage
chmod -R 775 /var/www/asambli/bootstrap/cache
```

**Step 9: Verificar que todo funciona**
```bash
# Verificar workers
supervisorctl status

# Verificar Reverb
curl http://localhost:8080

# Verificar app
curl https://tudominio.com
```

**Step 10: Commit final**
```bash
git add .
git commit -m "chore: deployment configuration and documentation"
```

---

## Resumen de Fases

| Fase | Descripción | Tareas |
|---|---|---|
| 0 | Entorno y setup | 1–5 |
| 1 | Multi-tenancy | 6–8 |
| 2 | Auth y roles | 9–10 |
| 3 | Modelos de dominio | 11–15 |
| 4 | Servicios críticos | 16–17 |
| 5 | Tiempo real (Reverb) | 18 |
| 6 | Notificaciones | 19 |
| 7 | Reportes | 20 |
| 8 | Controllers y rutas | 21 |
| 9 | Frontend React | 22–24 |
| 10 | Despliegue VPS | 25–26 |

---

## Comandos de Desarrollo Diario

```bash
# Arrancar todo el entorno
sail up -d

# En terminales separadas:
sail artisan reverb:start      # WebSockets
sail artisan horizon           # Queue monitor
sail npm run dev               # Vite hot-reload

# Correr tests
sail artisan test

# Ver logs de queues
sail artisan horizon           # UI en /horizon

# Correr migración nueva
sail artisan migrate

# Crear nuevo controlador
sail artisan make:controller Admin/NombreController

# Crear nueva migración
sail artisan make:migration create_tabla_x

# Limpiar caché en desarrollo
sail artisan cache:clear && sail artisan config:clear
```
