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

- [Git](https://git-scm.com/)
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) con Docker Compose v2 incluido
- Nada mas. Node, PHP y Composer corren **dentro del contenedor**.

Elige tu entorno segun tu sistema operativo:

| Sistema | Opcion recomendada |
|---------|-------------------|
| Windows | **WSL2 + Ubuntu** (ver seccion abajo) — es la opcion mas estable |
| Windows (alternativa) | Git Bash (MINGW64) — funciona pero con algunas limitaciones |
| macOS / Linux | Terminal nativa |

---

## Entorno Windows — Opcion A: WSL2 (recomendado)

WSL2 ofrece mejor rendimiento de I/O, compatibilidad total con scripts Unix y es el backend preferido de Docker Desktop en Windows.

### 1. Habilitar WSL2

Abre PowerShell como Administrador y ejecuta:

```powershell
wsl --install
```

Esto instala WSL2 con Ubuntu por defecto. Reinicia cuando lo pida.

Si ya tienes WSL instalado, asegurate de estar en version 2:

```powershell
wsl --set-default-version 2
wsl --list --verbose   # verifica que tu distro dice "2" en la columna VERSION
```

### 2. Configurar Docker Desktop para WSL2

1. Abre Docker Desktop → **Settings → General** → activa **"Use the WSL 2 based engine"**
2. Ve a **Settings → Resources → WSL Integration** → activa tu distro de Ubuntu

Verifica desde dentro de WSL:

```bash
docker --version          # debe mostrar la version de Docker
docker compose version    # debe mostrar v2.x
```

### 3. Clonar el repositorio dentro de WSL2

> **Importante:** Clona el repo **dentro del filesystem de WSL2** (`~/` o `/home/tu-usuario/`), no en `/mnt/c/...`.
> Trabajar en `/mnt/c/` tiene un rendimiento muy degradado y puede causar problemas con permisos.

```bash
# Dentro de la terminal WSL2 (Ubuntu):
cd ~
git clone <URL-del-repo> asambli
cd asambli
```

### 4. Variables de usuario para Docker

Dentro de WSL2 usa los valores reales de tu usuario:

```bash
id -u   # tu WWWUSER
id -g   # tu WWWGROUP
```

En la mayoria de instalaciones nuevas de Ubuntu en WSL2 ambos son `1000`.
Usa esos valores al levantar Docker (ver paso 3).

---

## Entorno Windows — Opcion B: Git Bash

Si prefieres no usar WSL2, puedes trabajar directamente desde Git Bash (MINGW64):

- Instala [Git for Windows](https://gitforwindows.org/) — incluye Git Bash
- Usa siempre Git Bash, **nunca PowerShell ni CMD**
- El prefijo `WWWGROUP=1000 WWWUSER=1000` es fijo para esta opcion
- Clona el repo en una ruta sin espacios, por ejemplo `C:\drwnDev\asambli`

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

El archivo `.env.example` ya contiene los valores correctos para desarrollo local con Docker.
Solo necesitas ajustar lo siguiente segun tu caso:

| Variable | Cuando cambiarla |
|----------|-----------------|
| `APP_KEY` | Se genera en el paso 4 — dejar vacio por ahora |
| `WWWGROUP` / `WWWUSER` | Solo si `id -u` / `id -g` en tu sistema no son `1000` |
| `BYPASS_QUORUM` | Dejar en `false` en produccion. `true` solo para pruebas locales de votaciones sin quorum |

El bloque completo de variables es:

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
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
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
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=reverb
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=redis

# Reverb - WebSockets (valores para dev local)
REVERB_APP_ID=asambli
REVERB_APP_KEY=asambli-key
REVERB_APP_SECRET=asambli-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_APP_NAME="${APP_NAME}"
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@asambli.co"
MAIL_FROM_NAME="${APP_NAME}"

# Flag de desarrollo — NO llevar a produccion
# true = permite votar sin importar el % de quorum confirmado
BYPASS_QUORUM=false
```

---

## 3. Levantar Docker

**WSL2 o macOS/Linux:**

```bash
WWWGROUP=$(id -g) WWWUSER=$(id -u) docker compose up -d
```

**Git Bash (Windows nativo):**

```bash
WWWGROUP=1000 WWWUSER=1000 docker compose up -d
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

## 12. Solucion de problemas frecuentes

### Docker no levanta en Windows

- Verifica que Docker Desktop este corriendo antes de ejecutar `docker compose up`.
- En WSL2: asegurate de que la integracion de WSL este activada en Docker Desktop (Settings → Resources → WSL Integration).
- Si ves errores de permisos, verifica que `WWWGROUP` y `WWWUSER` coincidan con `id -g` / `id -u`.

### El puerto 80 esta ocupado

Otro proceso (IIS, Apache local, otro contenedor) puede estar usando el puerto 80. Detienelo o cambia el puerto en `docker-compose.yml`.

### Cambios en el frontend no se reflejan

```bash
./sail npm run build   # reconstruye los assets
```

O usa `./sail npm run dev` durante el desarrollo para hot-reload automatico.

### Error de manifest de Vite en tests

Agrega el header Inertia en tus tests HTTP:

```php
$this->actingAs($user)
     ->withHeaders(['X-Inertia' => 'true'])
     ->get('/ruta')
     ->assertStatus(200);
```

### Quorum no se cumple en pruebas locales

Activa el flag de desarrollo en `.env`:

```env
BYPASS_QUORUM=true
```

> Nunca llevar este flag a produccion.

---

## Contacto

Si tienes dudas sobre el proyecto, contacta al lider tecnico antes de hacer cambios en las migraciones o en la logica de quorum/votaciones.
