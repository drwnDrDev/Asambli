# ASAMBLI — Diseño del Sistema
**Fecha:** 2026-03-04
**Estado:** Aprobado
**Nivel:** MVP SaaS Multi-tenant

---

## 1. Descripción General

App web SaaS multi-tenant para gestionar asambleas y reuniones de propiedad horizontal en Colombia bajo la Ley 675. Permite crear reuniones, gestionar votaciones en tiempo real, calcular quórum dinámico y generar reportes auditables.

**Mercado objetivo:** Conjuntos residenciales y de oficinas en Colombia (pequeños <50 unidades hasta grandes 200-500 unidades).

---

## 2. Roles del Sistema

| Rol | Alcance | Capacidades |
|---|---|---|
| `super_admin` | Plataforma | CRUD tenants, impersonation, métricas globales |
| `administrador` | Por tenant | Gestión completa de su conjunto |
| `copropietario` | Por tenant | Votar, ver historial de resultados |

---

## 3. Tipos de Reunión

| Tipo | Peso del voto | Quórum base |
|---|---|---|
| Asamblea ordinaria | Coeficiente (Ley 675) | Configurable por reunión |
| Consejo de administración | Unidad (1 voto = 1 unidad) | Configurable por reunión |
| Asamblea extraordinaria | Coeficiente | Configurable por reunión |

---

## 4. Ciclo de Vida de una Reunión

```
Borrador → Convocada → En curso → Finalizada
```

- Cada transición se registra en `reunion_logs` (append-only, inmutable).
- **Convocada:** dispara notificaciones por email a todos los copropietarios activos.
- **En curso:** habilita confirmación de asistencia y apertura de votaciones.
- **Finalizada:** bloquea cambios, habilita generación de reportes.

---

## 5. Ciclo de Vida de una Votación

```
Creada → Abierta → Cerrada
              └──→ Pausada (si quórum falla durante votación abierta)
```

**Precondiciones para abrir:**
- Reunión en estado `en_curso`
- Quórum alcanzado (verificado en DB)

**Tipos de votación:**
- `si_no`: Sí / No
- `si_no_abstencion`: Sí / No / Abstención
- `opcion_multiple`: N opciones definidas por el admin

**Privacidad:** Todas las votaciones son secretas por defecto. Solo el admin ve el detalle nominal en el módulo de auditoría (acción registrada en log).

---

## 6. Quórum Dinámico

- Calculado desde la tabla `asistencia` en DB (no desde estado del socket).
- Se recalcula en cada evento relevante: nueva confirmación, desconexión confirmada.
- Si el quórum cae durante una votación abierta → votación pasa a `pausada` automáticamente.
- El admin puede reactivarla cuando el quórum se restablezca.
- Umbral configurable por reunión en el campo `quorum_requerido`.

---

## 7. Delegaciones (Poderes Notariales)

- El admin registra poderes antes o durante la reunión.
- Límite configurable por tenant (`max_poderes_por_delegado`, default 2 según Ley 675 Art. 45).
- Un poderdante solo puede otorgar un poder por reunión (`UNIQUE(reunion_id, poderdante_id)`).
- Al votar, el delegado ve sus votos separados: el propio + uno por cada apoderado.
- Cada voto se registra individualmente con su peso correspondiente.

---

## 8. Autenticación de Copropietarios

1. Admin importa/registra copropietarios con su email.
2. Al convocar la reunión, el sistema envía un **magic link** único por copropietario.
3. El día de la reunión, el admin confirma presencia física/virtual en el panel.
4. Solo los marcados como presentes pueden votar.

---

## 9. Arquitectura de Confiabilidad (crítica)

> **Regla de oro: La base de datos es la única fuente de verdad. WebSockets solo transmiten, nunca deciden.**

### Flujo de un voto

```
HTTP POST /votes (síncrono)
    ↓
Transacción DB atómica:
  1. Verificar reunión En curso
  2. Verificar quórum vigente
  3. Verificar votación Abierta
  4. Verificar no-duplicado (UNIQUE constraint)
  5. INSERT voto con hash, timestamp, IP
  6. COMMIT
    ↓
Queue Job → RecalcularResultados
    ↓
Reverb broadcast → todos los clientes
```

### Garantías

| Riesgo | Mitigación |
|---|---|
| Voto duplicado | `UNIQUE(votacion_id, copropietario_id, en_nombre_de)` en DB |
| Voto sin quórum | Verificación dentro de transacción DB |
| Fallo WebSocket | Voto ya en DB; cliente hace polling como fallback |
| Alta concurrencia | Queue workers serializan el procesamiento |
| Quórum fantasma | Quórum desde tabla `asistencia`, no desde sockets |

---

## 10. Modelo de Datos

```sql
tenants
  id, nombre, nit, direccion, ciudad, logo_url,
  max_poderes_por_delegado (default 2),
  activo, created_at, updated_at

users
  id, tenant_id*, nombre, email, password,
  rol ENUM(super_admin, administrador, copropietario),
  email_verified_at, created_at

unidades
  id, tenant_id*, numero, tipo ENUM(apartamento, local, parqueadero, otro),
  coeficiente DECIMAL(8,5), torre, piso, activo, created_at
  -- Validación: SUM(coeficiente) por tenant ≈ 100.00000

copropietarios
  id, tenant_id*, user_id*, unidad_id*,
  es_residente BOOL, telefono, activo, created_at

poderes
  id, tenant_id*, reunion_id*,
  apoderado_id*, poderdante_id*,
  documento_url, registrado_por,
  created_at
  -- UNIQUE(reunion_id, poderdante_id)

reuniones
  id, tenant_id*, titulo, tipo ENUM(asamblea, consejo, extraordinaria),
  tipo_voto_peso ENUM(coeficiente, unidad),
  quorum_requerido DECIMAL(5,2),
  estado ENUM(borrador, convocada, en_curso, finalizada),
  fecha_programada, fecha_inicio, fecha_fin,
  convocatoria_enviada_at, creado_por, created_at

reunion_logs
  id, reunion_id*, user_id*, accion, metadata JSON,
  created_at  -- append-only, sin updated_at

asistencia
  id, reunion_id*, copropietario_id*,
  confirmada_por_admin BOOL, hora_confirmacion,
  vota_por_poderes JSON,
  created_at

votaciones
  id, tenant_id*, reunion_id*, titulo, descripcion,
  tipo ENUM(si_no, si_no_abstencion, opcion_multiple),
  es_secreta BOOL DEFAULT true,
  estado ENUM(creada, abierta, cerrada, pausada),
  abierta_at, cerrada_at, creada_por, created_at

opciones_votacion
  id, votacion_id*, texto, orden

votos
  id, tenant_id*, votacion_id*, copropietario_id*,
  en_nombre_de (copropietario_id nullable),
  opcion_id*, peso DECIMAL(8,5),
  ip_address, user_agent, hash_verificacion,
  created_at  -- inmutable, sin updated_at, sin soft delete
  -- UNIQUE(votacion_id, copropietario_id, en_nombre_de)

notificaciones_log
  id, tenant_id*, reunion_id*, canal ENUM(email, whatsapp, sms),
  copropietario_id*, enviada_at, estado, metadata JSON
```

---

## 11. Módulos de la Aplicación

### Super Admin
- CRUD de tenants (conjuntos)
- Impersonation de administradores
- Métricas globales de uso

### Administrador
- Configuración del conjunto (datos, logo, parámetros)
- Padrón: importar CSV/Excel + CRUD manual con validación de coeficientes
- Gestión de reuniones: crear, convocar, conducir, finalizar
- Registro de poderes
- Confirmación de asistencia (en sala)
- Gestión de votaciones en tiempo real
- Auditoría nominal (acción registrada en log)
- Generación de reportes PDF + CSV

### Copropietario (móvil)
- Acceso por magic link desde convocatoria
- Sala de espera con % de quórum
- Pantalla de votación activa (botones grandes, confirmación explícita)
- Voto separado por cada poder recibido
- Historial de resultados de reuniones pasadas

---

## 12. Reportes

### PDF (informe formal — DomPDF)
1. Encabezado: tipo, fecha, quórum requerido vs alcanzado
2. Asistentes y poderes registrados
3. Votaciones: resultados agregados por votación (sin detalle nominal)
4. Log cronológico de eventos
5. Hash SHA-256 del documento al pie
6. Leyenda: "Pendiente firma del administrador"

### CSV (datos para administrador) — entregado como .zip
- `asistencia.csv`: unidad, copropietario, coeficiente, hora, poderes
- `votaciones.csv`: título, tipo, opciones, votos, pesos, porcentajes, tiempos

### Auditoría nominal
- Solo accesible desde panel de auditoría del admin
- Requiere acción explícita
- Descarga queda registrada en `reunion_logs`

---

## 13. Stack Técnico

| Capa | Tecnología |
|---|---|
| Backend | Laravel 11 |
| Frontend | React + Inertia.js |
| Estilos | Tailwind CSS |
| Real-time | Laravel Reverb (WebSockets) |
| Queues | Laravel Horizon + Redis |
| PDF | Laravel DomPDF |
| Email | Laravel Notifications + SMTP/Mailgun |
| Base de datos | MySQL 8 |
| Auth | Laravel Breeze + Sanctum + magic links custom |
| Multi-tenancy | Eloquent Global Scopes (sin paquetes externos) |

### Infraestructura MVP (VPS único)
```
Nginx → PHP 8.3 → Laravel 11
MySQL 8
Redis (queues + cache + Reverb)
Supervisor (queue workers + Reverb server)
```

---

## 14. Notas de Escalabilidad

- Canales de notificación: interfaz `NotificationChannel` → agregar WhatsApp/SMS sin tocar lógica
- Infraestructura: VPS único → múltiples workers → servidores separados sin cambios en código
- Multi-tenancy: Eloquent scopes → migrable a schemas separados si se requiere mayor aislamiento
- Real-time: Reverb self-hosted → Pusher/Ably managed con un cambio de driver

---

## 15. Notas para Desarrollador Junior

- El plan de implementación incluirá comandos exactos de instalación paso a paso
- Cada fase del desarrollo será incremental y verificable
- Se priorizará convención sobre configuración (Laravel way)
- Herramientas incluidas: Laravel Sail para desarrollo local (Docker)
