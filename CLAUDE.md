# ASAMBLI — Contexto para Claude

## Qué es este proyecto
SaaS multi-tenant para gestionar asambleas de propiedad horizontal en Colombia.
Votaciones en tiempo real, quórum dinámico, reportes auditables con hash SHA-256.

## Stack
- **Backend:** Laravel 12, PHP 8.5, MySQL 8.4, Redis
- **Frontend:** React 18, Inertia.js, Tailwind CSS, Vite
- **Real-time:** Laravel Reverb (WebSockets via Echo/Pusher)
- **Auth:** Laravel Breeze + Magic Links para copropietarios
- **Testing:** Pest con RefreshDatabase
- **Otros:** DomPDF (PDF), league/csv (CSV), Laravel Horizon

## Entorno (CRÍTICO)
- **OS:** Windows 11, Git Bash (MINGW64) — sin WSL2
- **App corre en:** `http://localhost` (puerto 80 via Docker, NO :8000)
- **Comandos:**
  ```bash
  ./sail artisan ...    # php artisan dentro del contenedor
  ./sail composer ...
  ./sail npm ...
  ./sail test ...       # php artisan test
  ./sail mysql asambli  # consola MySQL
  # Alternativa directa:
  docker compose exec laravel.test php artisan ...
  ```
- **Levantar Docker:**
  ```bash
  WWWGROUP=1000 WWWUSER=1000 docker compose up -d
  ```
- **Contenedores:** `asambli-laravel.test-1`, `asambli-mysql-1`, `asambli-redis-1`

## Roles de usuario
| Rol | Quién | Acceso |
|-----|-------|--------|
| `super_admin` | Dueño del SaaS | `/super-admin/tenants` — gestiona todos los conjuntos |
| `administrador` | Admin del conjunto (cliente) | `/admin/*` — conduce reuniones del edificio |
| `copropietario` | Residente/propietario | `/sala/*` — vota en reuniones |

## Multi-tenancy
- Shared DB con columna `tenant_id` en todas las tablas de dominio
- Trait `BelongsToTenant` + `TenantScope` → filtrado automático
- Middleware `SetTenantContext` → vincula tenant al contenedor de Laravel
- `super_admin` tiene `tenant_id = null` (acceso a todos via `withoutGlobalScopes()`)

## Tablas con nombres no estándar (IMPORTANTE)
Laravel no pluraliza correctamente en español. Estas tablas requieren:
1. `protected $table = '...'` explícito en el modelo
2. `->constrained('nombre_tabla')` explícito en migraciones

| Modelo | Tabla |
|--------|-------|
| `Reunion` | `reuniones` |
| `Unidad` | `unidades` |
| `Votacion` | `votaciones` |
| `Poder` | `poderes` |
| `OpcionVotacion` | `opciones_votacion` |

## Patrones clave

### Factories en tests
```php
// Siempre usar lazy evaluation para tenant_id en factories:
'tenant_id' => fn() => app()->has('current_tenant')
    ? app('current_tenant')->id
    : \App\Models\Tenant::factory()
```

### Tests con tenant context
```php
app()->instance('current_tenant', $tenant);
```

### Inertia en tests (evitar error de Vite manifest)
```php
$this->actingAs($user)
     ->withHeaders(['X-Inertia' => 'true'])
     ->get('/admin/dashboard')
     ->assertStatus(200);
// O verificar que no sea 403:
expect($response->status())->not->toBe(403);
```

## Flags de desarrollo (CRÍTICO — no llevar a producción)

| Variable `.env` | Valor dev | Efecto |
|-----------------|-----------|--------|
| `BYPASS_QUORUM` | `true` | Omite la validación de quórum en `VotoService` — permite votar sin importar el % de asistencia confirmada |

> Implementado en `config/app.php` (`bypass_quorum`) y `app/Services/VotoService.php`.
> En producción esta variable no debe existir (el default es `false`).

## Plan de implementación
`docs/plans/2026-03-04-asambli-implementation-plan.md`

**Estado:** Tasks 1–24 completadas ✅ — Tasks 25–26 (deploy) pendientes hasta decisión del usuario.

## Comandos frecuentes
```bash
# Tests
./sail artisan test --no-coverage

# Compilar frontend
./sail npm run build

# Dev con hot-reload
./sail npm run dev   # (en segundo plano o terminal separada)

# Reverb (WebSockets)
./sail artisan reverb:start

# Horizon (colas)
./sail artisan horizon

# Tinker
./sail artisan tinker

# Migraciones
./sail artisan migrate
./sail artisan migrate:fresh --seed
```
