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

## Relación Copropietario ↔ Unidad (CRÍTICO)

- `Copropietario` tiene `unidades()` → **hasMany** (un propietario puede tener varias unidades)
- `Unidad` tiene `copropietario()` → **belongsTo**
- `Asistencia` solo tiene `copropietario_id` — **no tiene `unidad_id`**
- La relación singular `->unidad` **no existe** — siempre usar `->unidades` (plural)

### Cómo mostrar unidades según contexto

| Contexto | Formato |
|----------|---------|
| PDF acta / CSV | Una fila por unidad (explícito y auditable) |
| Vistas React (listas, tablas) | Números unidos: `"101, 201"` · coeficiente sumado |
| Canal de presencia WebSocket | `unidades->pluck('numero')->join(', ')` · `unidades->sum('coeficiente')` |

### Eager loading correcto
```php
// ✅ Correcto
->with('copropietario.user', 'copropietario.unidades')
$a->copropietario->unidades  // Collection

// ❌ Incorrecto — relación no existe
->with('copropietario.unidad')
$a->copropietario->unidad
```

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

## Broadcast de resultados (CRÍTICO)

El job `RecalcularResultadosVotacion` usa **`dispatchSync()`** — NO `dispatch()`.

- Con `dispatch()` el job va a la cola y requiere Horizon corriendo. En la práctica esto causó que los resultados no se vieran en tiempo real (11 jobs acumulados sin procesar).
- Con `dispatchSync()` el recálculo y broadcast ocurren sincrónicamente en el mismo request del voto.
- **Horizon NO es necesario** para el flujo de votación en tiempo real.
- El broadcast está envuelto en try-catch: si falla, el voto ya está en la BD y el error se loggea.

## Plan de implementación
`docs/plans/2026-03-04-asambli-implementation-plan.md`

**Estado:** Tasks 1–24 completadas ✅ — Tasks 25–26 (deploy) pendientes hasta decisión del usuario.

**Ciclo 1 completo (2026-03-19):** Flujo end-to-end funcional — conducción, votación en tiempo real, sala copropietario, exportación PDF/CSV, auditoría.

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
