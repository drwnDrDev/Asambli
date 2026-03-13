# Onboarding y Acceso Rápido para Copropietarios

**Fecha:** 2026-03-13
**Rama:** main

## Contexto

Antes de este cambio, los copropietarios accedían exclusivamente mediante magic links de un solo uso enviados por email. No existía contraseña propia ni flujo de activación de cuenta.

Este cambio introduce:
1. **Onboarding** via magic link: el copropietario confirma sus datos y crea su contraseña la primera vez.
2. **Login normal** con email + contraseña (ya existía en Breeze, ahora es el flujo principal).
3. **Plan B — PIN numérico**: el admin genera un PIN de 6 dígitos que el copropietario usa con su cédula.
4. **Plan B — QR de reunión**: el admin proyecta/imprime un QR; el copropietario escanea e ingresa su cédula.

---

## Flujos

### Flujo principal: primer acceso (onboarding)

```
Admin crea copropietario en /admin/copropietarios
        ↓
Sistema genera magic link tipo 'onboarding' (48h)
Envía email con OnboardingInvitation
        ↓
Copropietario hace clic → GET /bienvenida/{token}
        ↓
Página de onboarding: ver datos, editar nombre/documento/teléfono, crear contraseña
        ↓
POST /bienvenida/{token} → guarda datos, marca onboarded_at, consume link, login
        ↓
Redirect → /sala
```

### Flujo normal: login con contraseña

```
GET /login → email + contraseña
        ↓
Redirect según rol:
  copropietario → /sala
  administrador → /admin/dashboard
  super_admin   → /super-admin/tenants
```

### Plan B — PIN numérico

```
Admin → /admin/copropietarios/{id} → "Generar nuevo PIN"
        ↓
Sistema genera PIN de 6 dígitos, válido 72h
Admin lo comparte por WhatsApp, teléfono o impreso
        ↓
Copropietario → GET /acceso-rapido
Ingresa: tipo documento + número documento + PIN
        ↓
POST /acceso-rapido → valida PIN → login → /sala
```

### Plan B — QR de reunión (presencial)

```
Admin → /admin/reuniones/{id} → "Generar QR de acceso"
        ↓
Sistema genera qr_token (64 chars), válido 72h
Admin proyecta en pantalla o imprime
        ↓
Copropietario escanea → GET /sala/entrada/{qr_token}
Ingresa su número de cédula
        ↓
POST /sala/entrada/{qr_token} → valida cédula en el tenant → login → /sala/{reunion}
```

---

## Migraciones

| Archivo | Cambios |
|---------|---------|
| `2026_03_13_000001_add_onboarding_fields_to_users_table` | `onboarded_at` (timestamp), `quick_pin` (char 6), `pin_expires_at` (timestamp) en `users` |
| `2026_03_13_000002_add_type_to_magic_links_table` | `type` varchar(20) default `'convocatoria'` en `magic_links` |
| `2026_03_13_000003_add_qr_token_to_reuniones_table` | `qr_token` (varchar 64 unique), `qr_expires_at` (timestamp) en `reuniones` |

```bash
./sail artisan migrate
```

---

## Archivos nuevos

### Backend

| Archivo | Descripción |
|---------|-------------|
| `app/Http/Controllers/Auth/OnboardingController.php` | `show()` / `store()` para `/bienvenida/{token}` |
| `app/Http/Controllers/Auth/QuickAccessController.php` | PIN login (`showPin`, `storePin`) + QR entrada (`showQr`, `storeQr`) |
| `app/Notifications/OnboardingInvitation.php` | Email de bienvenida con enlace de onboarding |

### Frontend

| Archivo | Descripción |
|---------|-------------|
| `resources/js/Pages/Onboarding/Index.jsx` | Formulario de primer acceso |
| `resources/js/Pages/Auth/AccesoRapido.jsx` | Login con documento + PIN |
| `resources/js/Pages/Copropietario/EntradaQR.jsx` | Entrada por QR (solo cédula) |

---

## Archivos modificados

### Backend

| Archivo | Cambios |
|---------|---------|
| `app/Services/MagicLinkService.php` | `generate()` acepta `$type = 'convocatoria'`; genera URL `/bienvenida/{token}` si type es `'onboarding'` |
| `app/Models/User.php` | `$fillable` + casts: `onboarded_at`, `quick_pin`, `pin_expires_at`. Helper `isOnboarded()` |
| `app/Models/MagicLink.php` | `type` en `$fillable` |
| `app/Models/Reunion.php` | `qr_token`, `qr_expires_at` en `$fillable` + casts |
| `app/Http/Controllers/Admin/CopropietarioController.php` | `store()` auto-envía onboarding; nuevos métodos `generatePin()` y `reenviarBienvenida()` |
| `app/Http/Controllers/Admin/ReunionController.php` | Nuevo método `generarQr()` |
| `app/Http/Middleware/HandleInertiaRequests.php` | Flash `pin` compartido via Inertia shared props |

### Frontend

| Archivo | Cambios |
|---------|---------|
| `resources/js/Pages/Auth/Login.jsx` | Link "Acceder con PIN" al final del formulario |
| `resources/js/Pages/Admin/Copropietarios/Show.jsx` | Sección "Acceso": estado onboarding + generación de PIN |
| `resources/js/Pages/Admin/Copropietarios/Index.jsx` | Badge "Sin activar" para copropietarios no onboarded |
| `resources/js/Pages/Admin/Reuniones/Show.jsx` | Sección QR con visualización via `qrcode.react` |

### Rutas (`routes/web.php`)

Rutas nuevas (todas sin middleware `auth`):

```
GET  /bienvenida/{token}       → OnboardingController::show     (onboarding.show)
POST /bienvenida/{token}       → OnboardingController::store    (onboarding.store)
GET  /acceso-rapido            → QuickAccessController::showPin (quick-access.pin)
POST /acceso-rapido            → QuickAccessController::storePin (quick-access.pin.store)
GET  /sala/entrada/{token}     → QuickAccessController::showQr  (quick-access.qr)
POST /sala/entrada/{token}     → QuickAccessController::storeQr (quick-access.qr.store)
```

Rutas nuevas (dentro del grupo admin):

```
POST /admin/copropietarios/{id}/generar-pin         (admin.copropietarios.generar-pin)
POST /admin/copropietarios/{id}/reenviar-bienvenida (admin.copropietarios.reenviar-bienvenida)
POST /admin/reuniones/{id}/generar-qr               (admin.reuniones.generar-qr)
```

---

## Dependencias nuevas

| Paquete | Tipo | Uso |
|---------|------|-----|
| `qrcode.react` | npm | Renderizar QR en la página de reunión del admin |

---

## Seeder de prueba

```bash
./sail artisan db:seed --class=CopropietarioOnboardingSeeder
```

Crea 3 copropietarios en el primer tenant disponible:

| Nombre | Email | Estado | Acceso |
|--------|-------|--------|--------|
| Ana García | ana@test.com | Sin activar | Magic link de onboarding |
| Luis Torres | luis@test.com | Activado | `password` |
| Carmen Ruiz | carmen@test.com | Activado + PIN | `password` · PIN: `123456` · Cédula: CC 3003003003 |

---

## Comandos para probar

```bash
# Aplicar migraciones
./sail artisan migrate

# Cargar datos de prueba
./sail artisan db:seed --class=CopropietarioOnboardingSeeder

# Compilar frontend
./sail npm run build

# Dev con hot-reload
./sail npm run dev
```

---

## Notas técnicas

- **Seguridad del QR general**: el QR de reunión valida cédula contra el `tenant_id` de la reunión. Cualquier persona con el QR + una cédula válida del conjunto puede entrar; esto es aceptable para el contexto de asamblea presencial comunitaria.
- **Orden de rutas**: `/sala/entrada/{token}` debe declararse **antes** del grupo `/sala/{reunion}` para evitar que el wildcard del grupo autenticado intercepte la ruta pública.
- **Transacción vs login**: `auth()->login()` se ejecuta **fuera** del `DB::transaction()` en `OnboardingController::store()` para evitar sesión activa con datos revertidos ante un fallo.
- **Magic links de convocatoria**: el flujo existente (`/acceso/{token}`) no fue modificado. Sigue funcionando como acceso directo a `/sala` para convocatorias.
