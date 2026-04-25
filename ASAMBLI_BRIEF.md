# ASAMBLI — Product Brief

> Documento de referencia para material comercial: presentaciones, flyers, publicidad.
> Extraído del código fuente real. Versión: abril 2026.

---

## 1. Descripción del Producto y Problema que Resuelve

**ASAMBLI** es una plataforma SaaS para la gestión digital de asambleas de propiedad horizontal en Colombia.

### El problema
Las asambleas de copropietarios en Colombia son un proceso legalmente exigido pero operativamente caótico: listas de asistencia en papel, votaciones a mano alzada sin trazabilidad, quórum calculado manualmente, actas que tardan días en redactarse, delegaciones de voto (poderes) sin control y resultados que los copropietarios no pueden verificar.

### La solución
ASAMBLI digitaliza todo el ciclo de una asamblea — desde la convocatoria hasta el acta firmada — con votaciones en tiempo real, quórum dinámico visible para todos, acceso desde el celular sin instalar nada, y reportes auditables con integridad verificable.

### Mercado objetivo
Conjuntos residenciales, edificios y unidades de propiedad horizontal en Colombia (comunidades sometidas a la Ley 675 de 2001).

---

## 2. Stack Técnico Completo

### Backend
| Tecnología | Versión | Rol |
|---|---|---|
| PHP | 8.5 | Lenguaje servidor |
| Laravel | 12 | Framework principal |
| MySQL | 8.4 | Base de datos relacional |
| Redis | — | Cache, colas, sesiones |
| Laravel Reverb | 1.8 | Servidor WebSocket propio |
| Laravel Horizon | 5.x | Monitoreo de colas |
| Laravel Sanctum | 4.x | Autenticación API/sesión |
| Laravel Breeze | 2.x | Scaffolding de auth |
| DomPDF (barryvdh) | 3.x | Generación de actas en PDF |
| league/csv | 9.x | Exportación de votos en CSV |
| spatie/simple-excel | 3.x | Importación de padrón (CSV/XLSX) |
| Resend | 1.x | Envío de emails transaccionales |
| Ziggy (tightenco) | 2.x | Rutas Laravel en JavaScript |

### Frontend
| Tecnología | Versión | Rol |
|---|---|---|
| React | 18 | UI components |
| Inertia.js | 2.x | SPA sin API REST explícita |
| Tailwind CSS | 3.x | Estilos utilitarios |
| Vite | 7.x | Bundler y dev server |
| Laravel Echo | 2.x | Cliente WebSocket |
| Pusher-js | 8.x | Driver de Echo para Reverb |
| lucide-react | 0.577 | Íconos |
| qrcode.react | 4.x | Generación de códigos QR |
| @headlessui/react | 2.x | Componentes accesibles |

### Infraestructura / DevOps
- **Docker** (Laravel Sail) — entorno contenedorizado local
- **WebSockets propios** vía Laravel Reverb (no depende de Pusher.com)
- Multi-tenancy con base de datos compartida (`tenant_id` en todas las tablas)

---

## 3. Módulos y Features Implementados

### 3.1 Super-Admin — Gestión de la Plataforma
- **Gestión de tenants (conjuntos)**: crear, editar, activar/desactivar conjuntos residenciales
- **Asignación de administradores**: vincular usuarios con rol `administrador` a un conjunto
- **Vista de auditoría global**: historial de eventos por tenant
- **Creación de reuniones**: el super_admin crea las reuniones para cada conjunto

### 3.2 Administrador — Gestión del Conjunto
- **Dashboard**: resumen de reuniones activas y próximas
- **Gestión de reuniones**: ver, conducir, exportar y auditar reuniones
- **Gestión de copropietarios**: CRUD completo con búsqueda en tiempo real y paginación
- **Padrón de copropietarios**: importación masiva vía CSV/XLSX (copropietarios + unidades + coeficientes)
- **Gestión de poderes**: aprobar, rechazar y registrar delegaciones de voto
- **Configuración del conjunto**: ajustes propios del tenant (nombre, % quórum requerido, etc.)

### 3.3 Conducción de Reuniones (Tiempo Real)
Panel de control en vivo para el administrador durante la asamblea:
- **KPIs en tiempo real**: estado de la reunión, quórum oficial (DB), presencia online (WebSocket), conectados totales, votaciones activas
- **Ciclo de vida completo**: estados `borrador → convocada → ante_sala → en_curso → suspendida/finalizada/cancelada`
- **Sala de espera (ante_sala)**: lista de copropietarios conectados antes de iniciar
- **Confirmación de asistencia**: marcar presencia física de cada copropietario
- **Avisos en tiempo real**: enviar mensajes instantáneos a todos los conectados
- **Proyección en pantalla**: vista de pantalla grande para proyector (fullscreen, auto-actualizable)
- **Lista de acceso QR**: generación y visualización de QR para que copropietarios ingresen

### 3.4 Votaciones
- **CRUD de votaciones**: crear, editar y eliminar votaciones con opciones personalizadas
- **Opciones configurables**: por defecto Sí / No / Abstención, editables antes de abrir
- **Ciclo de votación**: estados `creada → abierta → cerrada`
- **Resultados en tiempo real**: barras de progreso que se actualizan voto a voto (broadcast sincrónico, sin colas)
- **Ponderación por coeficiente**: cada voto vale según el coeficiente de la unidad del copropietario
- **Ticker de votos recientes**: feed en vivo de las unidades que van votando
- **Resultados históricos**: expandibles por votación cerrada con ganador destacado
- **Vista de resultados admin**: página dedicada de resultados por votación

### 3.5 Sala del Copropietario
Interfaz mobile-first (diseño oscuro) donde el copropietario participa:
- **Mis reuniones**: listado de reuniones vigentes con estado en tiempo real
- **Vista de reunión en curso**: estado de conexión, quórum visible, votación activa
- **Votación con un toque**: el copropietario selecciona su opción; confirmación visual inmediata
- **Indicador de conexión**: dot verde/naranja/rojo con estado de WebSocket
- **Historial de reuniones**: acceso a las asambleas pasadas y sus resultados

### 3.6 Acceso Flexible (Sin Fricción)
Múltiples mecanismos de acceso para maximizar participación:
- **Login con documento + PIN**: sin crear contraseña, ideal para primera asamblea
- **Acceso por QR**: el administrador genera un token QR para entrada rápida
- **Acceso rápido PIN**: flujo simplificado por número de documento y PIN de 6 dígitos
- **Onboarding guiado**: flujo de bienvenida para copropietarios nuevos (configura nombre, documento, contraseña)
- **Magic link** (deprecado, redirige al nuevo flujo de sala/login)

### 3.7 Poderes (Delegaciones de Voto)
- **Registro de poder**: el copropietario delega su voto a otro copropietario o a un delegado externo
- **Delegados externos**: personas no residentes que representan a un copropietario (identificados con badge "D")
- **Flujo de aprobación admin**: el administrador aprueba o rechaza poderes antes de la reunión
- **Verificación de delegado activo**: consulta si un copropietario ya tiene un delegado asignado
- **Peso acumulado**: el delegado suma el coeficiente de sus representados al votar

### 3.8 Quórum Dinámico
Sistema de doble quórum para máxima transparencia:
- **Quórum oficial**: basado en asistencias confirmadas en BD (permanente, auditable)
- **Quórum de presencia online**: calculado en tiempo real desde el canal de presencia WebSocket
- **Validación automática**: el sistema bloquea votaciones si no hay quórum (configurable por `BYPASS_QUORUM` en dev)

### 3.9 Reportes y Auditoría
- **Acta en PDF**: generada con DomPDF, incluye lista de asistentes, votaciones y resultados
- **Export CSV de asistencias**: una fila por unidad, con nombre, documento, coeficiente
- **Export CSV de votos**: detalle de cada voto emitido
- **Log de auditoría**: historial inmutable de todas las acciones (transiciones de estado, cambios, quién y cuándo)
- **Auditoría por tenant**: disponible para super_admin con vista completa

---

## 4. Flujos de Usuario Principales

### Flujo Super-Admin
```
Login → Dashboard → Crear Tenant → Asignar Administrador
                 → Crear Reunión para Tenant → Configurar agenda
```

### Flujo Administrador (día de la asamblea)
```
Login → Reuniones → Conducir Reunión
  → Ante Sala: ver quién se conecta en tiempo real
  → Iniciar: quórum calculado automáticamente
  → En Curso:
      ├── Confirmar asistencias físicas
      ├── Crear votación → Abrir → votos en tiempo real → Cerrar → resultados
      ├── Enviar avisos a todos los conectados
      └── Proyectar en pantalla secundaria
  → Finalizar → Exportar PDF/CSV del acta
```

### Flujo Copropietario (primera vez)
```
Email de bienvenida → /bienvenida/{token} → Onboarding (nombre, doc, contraseña)
→ Login → Sala → Mis Reuniones → Entrar a reunión → Votar
```

### Flujo Copropietario (acceso rápido, sin cuenta)
```
/sala/login/{reunion} → Ingresar documento + PIN
→ Sala de la reunión → Votar
```

### Flujo Copropietario (acceso QR)
```
Escanear QR del admin → /sala/entrada/{token} → Verificar documento
→ Sala de la reunión → Votar
```

### Flujo de Poder
```
Copropietario → Sala → Mi poder → Crear poder → Seleccionar delegado
→ Admin recibe solicitud → Aprobar/Rechazar
→ Delegado puede votar representando al poderdante
```

---

## 5. Integraciones Externas

| Servicio | Uso | Tipo |
|---|---|---|
| **Resend** | Emails transaccionales: onboarding, avisos de reunión | API externa (resend.com) |
| **Laravel Reverb** | Servidor WebSocket auto-hospedado | Servicio propio |
| **Pusher-js** | Librería cliente para WebSockets | SDK (driver de Echo) |
| **QRCode.react** | Generación visual de códigos QR en el navegador | Librería frontend |
| **DomPDF** | Renderizado de actas PDF desde plantillas Blade | Librería PHP |
| **Bunny Fonts** | Tipografías DM Sans, JetBrains Mono, Fraunces | CDN externo |

---

## 6. Estado Actual

| Área | Estado |
|---|---|
| Backend core (modelos, servicios, auth) | ✅ Completo |
| Flujo end-to-end conducción + votación | ✅ Completo |
| Sala del copropietario (votación real-time) | ✅ Completo |
| Acceso por documento + PIN | ✅ Completo |
| Acceso por QR | ✅ Completo |
| Onboarding de copropietarios | ✅ Completo |
| Poderes / delegaciones de voto | ✅ Completo |
| Importación de padrón (CSV/XLSX) | ✅ Completo |
| Exportación PDF y CSV del acta | ✅ Completo |
| Auditoría de eventos | ✅ Completo |
| Proyección en pantalla (modo sala) | ✅ Completo |
| Super-admin (gestión de tenants) | ✅ Completo |
| Multi-tenancy (aislamiento por tenant) | ✅ Completo |
| Deploy / infraestructura producción | ⏳ Pendiente |
| Suite de tests automatizados | ✅ Implementada (Pest) |

**Ciclos completados:** Ciclo 1 (flujo core), Ciclo 2 (poderes + super-admin), Ciclo 3 (identidad y acceso sin User account).

---

## 7. Diferenciales Clave

### 1. Sin fricción para el copropietario
Acceso por número de documento y PIN de 6 dígitos — no requiere app, no requiere contraseña, funciona desde cualquier celular con navegador. El copropietario no necesita haberse registrado antes.

### 2. Tiempo real de extremo a extremo
Todos los participantes ven los votos, el quórum y los resultados actualizarse al instante via WebSockets propios (no depende de terceros). El administrador ve exactamente quién está conectado y cuánto coeficiente representan en vivo.

### 3. Quórum dual y transparente
ASAMBLI muestra simultáneamente el quórum oficial (asistencias confirmadas, base para el acta) y el quórum de presencia online (conexiones WebSocket activas). Eliminando ambigüedades legales y operativas.

### 4. Ponderación por coeficiente real
Las votaciones no cuentan cabezas — cuentan coeficientes. Cada voto vale según el porcentaje de copropiedad registrado en el padrón. Cumple con la Ley 675.

### 5. Poderes digitales con trazabilidad
Las delegaciones de voto se registran, aprueban y auditan dentro de la plataforma. El delegado aparece como tal en el acta y en la pantalla de conducción (badge "D").

### 6. Acta instantánea y auditable
Al finalizar la reunión el administrador genera el PDF del acta en segundos, con lista completa de asistentes (por unidad), todas las votaciones y sus resultados ponderados. El log de auditoría es inmutable.

### 7. Multi-tenant nativo
Un solo SaaS sirve a múltiples conjuntos residenciales con aislamiento total de datos. El super-admin gestiona todos los conjuntos; cada administrador solo ve el suyo.

### 8. Infraestructura autónoma
Los WebSockets corren en el propio servidor (Laravel Reverb), sin dependencia de Pusher.com ni costos variables por mensaje. Arquitectura pensada para escalar sin costos sorpresa.

---

## 8. Identidad Visual y Estilo

### Paleta de Colores — Panel Admin (tema claro/oscuro)

| Variable | Hex | Uso |
|---|---|---|
| Brand principal | `#2563eb` | Botones primarios, acentos, logo |
| Brand oscuro | `#1d4ed8` | Hover de botones principales |
| Brand claro | `#eff6ff` | Fondos de elementos activos |
| Sidebar fondo | `#0c111d` | Barra lateral de navegación |
| Sidebar borde | `#1a2234` | Divisores del sidebar |
| Sidebar activo | `#1e3055` | Ítem de navegación seleccionado |
| Sidebar texto | `#7a8fa8` | Texto inactivo del sidebar |
| Sidebar texto activo | `#e8edf5` | Texto activo del sidebar |
| Fondo contenido | `#f4f6fb` | Área principal de trabajo |
| Superficie | `#ffffff` | Cards y paneles |
| Texto primario | `#0d1526` | Títulos y texto principal |
| Texto secundario | `#556070` | Subtítulos y labels |
| Texto muted | `#8a95a8` | Texto auxiliar |
| Éxito | `#10b981` | Confirmaciones, quórum alcanzado |
| Peligro | `#ef4444` | Errores, cancelaciones |
| Advertencia | `#f59e0b` | Estados suspendidos, alertas |
| Info | `#06b6d4` | Notificaciones informativas |

### Paleta de Colores — Sala Copropietario (tema oscuro)

| Variable | Hex | Uso |
|---|---|---|
| Fondo sala | `#0a0f1e` | Fondo principal de la sala |
| Superficie sala | `#111827` | Cards y contenedores |
| Superficie elevada | `#1a2235` | Elementos sobre superficie |
| Borde sala | `#1e2d45` | Divisores y bordes |
| Amber (votación activa) | `#f59e0b` | Highlight de votación en curso |
| Verde sala | `#10b981` | Confirmación de voto, quórum |
| Azul sala | `#3b82f6` | Acento secundario |
| Texto sala | `#e8edf5` | Texto principal en oscuro |
| Texto muted sala | `#6b7a99` | Texto auxiliar en oscuro |

### Tipografía

| Fuente | Peso | Uso |
|---|---|---|
| **DM Sans** | 400, 500, 600, 700 | Interfaz admin completa, sala (body) |
| **JetBrains Mono** | 400, 500 | Datos técnicos, hashes, códigos |
| **Fraunces** | 400, 600, 700 (italic) | Display de la sala copropietario (serif elegante) |

### Estilo de UI

- **Admin**: sidebar oscuro profundo (`#0c111d`) + contenido en fondo claro (`#f4f6fb`) — contraste ejecutivo
- **Sala copropietario**: dark mode total, mobile-first, max-width centrado, diseño limpio sin distracciones
- **Radios**: `6px` (sm), `10px` (md), `14px` (lg) — esquinas suaves, no abruptas
- **Sombras**: sutiles (no dramáticas), profundidad sin peso
- **Íconos**: Lucide React (línea fina, stroke 2px)
- **Logo**: ícono de onda/pulso (ECG) dentro de cuadrado redondeado en brand blue + wordmark `ASAMBLI` en negrita

---

## 9. Nomenclatura del Dominio (Glosario)

| Término en App | Significado |
|---|---|
| **Tenant** | Conjunto residencial cliente del SaaS |
| **Reunion** | Asamblea (ordinaria o extraordinaria) |
| **Copropietario** | Propietario de una o varias unidades |
| **Unidad** | Apartamento, casa, local, parqueadero, etc. |
| **Coeficiente** | % de copropiedad de una unidad (suma total = 100%) |
| **Poder** | Delegación de voto de un copropietario a otro |
| **Delegado externo** | Persona sin unidad propia que actúa con poder |
| **Asistencia** | Registro permanente de presencia en una reunión |
| **Quórum oficial** | % coeficiente con asistencia confirmada en BD |
| **Presencia online** | % coeficiente conectado por WebSocket en tiempo real |
| **Ante sala** | Sala de espera previa al inicio formal de la asamblea |
| **Votacion** | Pregunta puesta a consideración de la asamblea |
| **Voto** | Respuesta emitida por un copropietario (ponderada por coeficiente) |
