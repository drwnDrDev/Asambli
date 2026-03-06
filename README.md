# ASAMBLI

SaaS multi-tenant para gestionar **asambleas de propiedad horizontal** en Colombia.
Votaciones en tiempo real, quorum dinamico, reportes auditables con hash SHA-256.

---

## Stack

| Capa | Tecnologia |
|------|-----------|
| Backend | Laravel 12, PHP 8.5 |
| Base de datos | MySQL 8.4 |
| Cache / Colas | Redis |
| Frontend | React 18, Inertia.js, Tailwind CSS, Vite |
| Tiempo real | Laravel Reverb (WebSockets) |
| Colas | Laravel Horizon |
| Auth | Laravel Breeze + Magic Links |
| Testing | Pest con RefreshDatabase |
| PDF / CSV | DomPDF, league/csv |

---

## Prerequisitos

Instala esto **en tu maquina host** antes de continuar:

- [Git](https://git-scm.com/) + **Git Bash** (en Windows es obligatorio — no uses PowerShell ni CMD)
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) con Docker Compose v2 incluido
- Nada mas. Node, PHP y Composer corren **dentro del contenedor**.

> **macOS / Linux:** reemplaza `WWWGROUP=1000 WWWUSER=1000` por los valores de `id -u` e `id -g` de tu usuario.

---

## 1. Clonar el repositorio

```bash
git clone <URL-del-repo> asambli
cd asambli
```

---

## 2. Configurar el entorno

Copia el archivo de ejemplo:

```bash
cp .env.example .env
```

Luego edita `.env` y reemplaza **todo el bloque de configuracion** con los valores de abajo.
Estas son las variables minimas para dev local con Docker:

```env
APP_NAME=ASAMBLI
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_CO

LOG_CHANNEL=stack
LOG_LEVEL=debug

# Base de datos (MySQL dentro del contenedor Docker)
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=asambli
DB_USERNAME=sail
DB_PASSWORD=password

# Redis (dentro del contenedor Docker)
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

SESSION_DRIVER=database
SESSION_LIFETIME=120
QUEUE_CONNECTION=database
CACHE_STORE=redis
BROADCAST_CONNECTION=reverb
FILESYSTEM_DISK=local

# Reverb - WebSockets (valores para dev local)
REVERB_APP_ID=asambli
REVERB_APP_KEY=asambli-key
REVERB_APP_SECRET=asambli-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

MAIL_MAILER=log
```

> **Atencion:** `APP_KEY` se genera en el paso 4. Dejalo vacio por ahora.

---

## 3. Levantar Docker

```bash
# Windows (Git Bash) — siempre con estas variables de entorno:
WWWGROUP=1000 WWWUSER=1000 docker compose up -d

# Alternativa con el wrapper ./sail:
./sail up -d
```

La primera vez Docker descarga las imagenes y construye el contenedor PHP (~3-5 min).
Cuando termine, verifica que los tres servicios esten corriendo:

```bash
docker compose ps
```

Debes ver algo como:

```
NAME                        STATUS
asambli-laravel.test-1      Up
asambli-mysql-1             Up (healthy)
asambli-redis-1             Up (healthy)
```

---

## 4. Primer arranque

Ejecuta estos comandos en orden:

```bash
# Generar la clave de la aplicacion
./sail artisan key:generate

# Correr todas las migraciones y seeders
./sail artisan migrate --seed

# Compilar los assets del frontend
./sail npm run build
```

---

## 5. Verificar que funciona

Abre **http://localhost** en tu navegador. Debes ver la pantalla de login de ASAMBLI.

### Usuarios de prueba (generados por los seeders)

| Rol | Email | Password | Ruta inicial |
|-----|-------|----------|-------------|
| `super_admin` | super@asambli.co | `password` | `/super-admin/tenants` |
| `administrador` | admin@asambli.co | `password` | `/admin/dashboard` |
| `copropietario` | copro@asambli.co | `password` | `/sala` |

---

## 6. Flujo de desarrollo diario

### Levantar el entorno

```bash
./sail up -d
```

### Compilar frontend con hot-reload

En una terminal separada:

```bash
./sail npm run dev
```

El frontend se sirve con HMR en el mismo puerto 80 a traves de Vite + Inertia.

### Correr los tests

```bash
./sail artisan test --no-coverage
```

### Ver los logs en tiempo real

```bash
./sail artisan pail
```

### Apagar Docker

```bash
./sail down
```

---

## 7. Servicios opcionales en desarrollo

### Reverb — WebSockets (votaciones en tiempo real)

```bash
./sail artisan reverb:start
```

### Horizon — Panel de colas

```bash
./sail artisan horizon
```

Accede al panel en: **http://localhost/horizon**

---

## 8. Comandos frecuentes

| Tarea | Comando |
|-------|---------|
| Artisan | `./sail artisan <comando>` |
| Composer | `./sail composer <comando>` |
| NPM | `./sail npm <comando>` |
| Tests | `./sail artisan test --no-coverage` |
| Consola MySQL | `./sail mysql asambli` |
| Tinker (REPL) | `./sail artisan tinker` |
| Migraciones | `./sail artisan migrate` |
| Reset + seed | `./sail artisan migrate:fresh --seed` |
| Estado migraciones | `./sail artisan migrate:status` |
| Limpiar cache | `./sail artisan cache:clear` |
| Rutas | `./sail artisan route:list` |

---

## 9. Estructura del proyecto

```
app/
  Http/
    Controllers/       # Controladores por rol (Admin/, SuperAdmin/, Sala/)
    Middleware/        # SetTenantContext, role checks
  Models/              # Eloquent models (Tenant, User, Reunion, Unidad, Votacion...)
  Services/            # Logica de negocio (QuorumService, VotingService...)
database/
  migrations/          # Esquema de la BD
  seeders/             # Datos de prueba
resources/
  js/
    Pages/             # Componentes React por seccion (Admin/, SuperAdmin/, Sala/)
    Components/        # Componentes reutilizables
    Layouts/           # AdminLayout, GuestLayout, etc.
routes/
  web.php              # Todas las rutas web
  channels.php         # Canales de broadcasting (Reverb)
```

---

## 10. Multi-tenancy

El sistema es **shared database, shared schema**. Cada tabla de dominio tiene `tenant_id`.

- El trait `BelongsToTenant` + `TenantScope` filtra automaticamente todas las queries.
- El middleware `SetTenantContext` vincula el tenant al contenedor de Laravel en cada request.
- El `super_admin` tiene `tenant_id = null` y puede ver todos los tenants con `withoutGlobalScopes()`.

### Tablas con nombres no estandar

Laravel no pluraliza bien en espanol. Estos modelos definen `$table` explicitamente:

| Modelo | Tabla |
|--------|-------|
| `Reunion` | `reuniones` |
| `Unidad` | `unidades` |
| `Votacion` | `votaciones` |
| `Poder` | `poderes` |
| `OpcionVotacion` | `opciones_votacion` |

---

## 11. Roles y acceso

| Rol | Quien es | Ruta base |
|-----|----------|-----------|
| `super_admin` | Dueno del SaaS | `/super-admin/*` |
| `administrador` | Admin del conjunto (cliente) | `/admin/*` |
| `copropietario` | Residente / propietario | `/sala/*` |

---

## Notas para Windows

- Usa siempre **Git Bash** (MINGW64), no PowerShell ni CMD.
- El prefijo `WWWGROUP=1000 WWWUSER=1000` es necesario para que los archivos generados dentro del contenedor tengan los permisos correctos en tu disco.
- Si Docker no levanta, verifica que Docker Desktop este corriendo antes de ejecutar `docker compose up`.
- El proyecto corre en **http://localhost** (puerto 80), no en el 8000.

---

## Contacto

Si tienes dudas sobre el proyecto, contacta al lider tecnico antes de hacer cambios en las migraciones o en la logica de quorum/votaciones.
