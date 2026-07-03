# Fixes de pruebas de usuario — Fase compliance Ley 675 (Design)

**Fecha:** 2026-07-02
**Branch:** `feat/cumplimiento-legal-ley675`
**Origen:** Notas de pruebas de usuario sobre la implementación de
`docs/superpowers/specs/2026-06-16-cumplimiento-legal-ley675-design.md`.

## Contexto

Las 12 tareas del plan de cumplimiento legal están implementadas. Las pruebas
de usuario encontraron 11 hallazgos: 2 huecos legales críticos, 1 bug de
cálculo de quórum, 1 feature de captura de quórum para compliance, y un lote
de ajustes de UI/config. Este documento diseña la corrección de todos ellos.

Ya corregido antes de esta spec (commit `9c90976`): error 500
`Target class [current_tenant] does not exist` en el flujo PIN — el tenant
ahora se resuelve desde la reunión, no desde el contenedor.

## Decisiones tomadas con el usuario

| Tema | Decisión |
|------|----------|
| Jerarquía de nomenclatura (etapas/torres/interiores/pisos) | **Diferida** a fase propia (cambio de modelo de datos de unidades). |
| Representante legal del tenant | **Diferido** a fase propia. |
| Apertura de votación con mayoría especial sin presencia suficiente | **Bloqueo duro** en backend: calificada_70 exige coeficiente presente ≥ 70%; unanimidad exige 100%. `BYPASS_QUORUM` **no** aplica a esta regla. |
| Copropietario en mora actuando como apoderado | **Sí puede** ejercer los votos de sus poderdantes al día. Solo su voto propio está suspendido (Art. 38). |
| Poder cuyo poderdante está en mora | Se permite registrar/aprobar; el módulo de Poderes solo **advierte** al admin. El bloqueo real es al votar. |
| Captura de quórum | **Eventos de asistencia + snapshot por votación** (ver C2). |

## Orden de implementación

```
C1 (bug quórum) → B1 + B2 (legales) → C2 (captura) → A1–A5 + D1 (UI/config) → D2 (fechas)
```

C1 va primero porque B2 y C2 dependen de que `QuorumService` entregue el
número correcto.

---

## Grupo C — Quórum

### C1. Bug: doble conteo en `QuorumService` (crítico)

**Causa raíz (confirmada leyendo el código):**

1. Cuando un apoderado entra a la sala, `SalaReunionController::show()` crea
   filas de `Asistencia` para él **y para cada poderdante** representado.
2. `QuorumService::calcularPorCoeficiente()` suma el coeficiente de todos los
   copropietarios con asistencia confirmada (poderdantes incluidos) y luego
   suma **otra vez** el coeficiente de los poderdantes de poderes aprobados
   cuyo apoderado está presente (`$coeficienteDelegados`).
3. Defecto adicional: la consulta de `Poder` no filtra por `reunion_id`, así
   que poderes aprobados de otras reuniones del tenant contaminan el cálculo.

El mismo doble conteo existe en `calcularPorUnidad()`. Esto explica que el
widget de "Quórum oficial" en Conducir muestre **más** que el valor real,
mientras el valor persistido al finalizar (calculado por otra vía) es correcto.

**Fix:**

- En ambas variantes de `QuorumService`: excluir de la suma de delegados a los
  poderdantes que ya tienen asistencia propia confirmada (deduplicar por
  `copropietario_id`).
- Filtrar los poderes por `reunion_id` de la reunión calculada.
- Se conserva la creación de `Asistencia` para poderdantes (correcto para el
  acta: quedaron representados).

**Tests (TDD):** apoderado presente con 2 poderdantes (cuenta 3, una vez cada
uno); poderdante con asistencia propia además del poder (cuenta 1 vez); poder
aprobado de otra reunión (no cuenta); variante por unidad con los mismos casos.

### C2. Captura de quórum durante la reunión (compliance / reuniones virtuales)

**Eventos de asistencia — tabla nueva `asistencia_eventos`:**

| Columna | Tipo | Notas |
|---------|------|-------|
| `id` | bigint | |
| `tenant_id` | FK | trait `BelongsToTenant` |
| `reunion_id` | FK `reuniones` | |
| `copropietario_id` | FK `copropietarios` | |
| `tipo` | enum `entrada`, `salida` | |
| `origen` | enum `auto_sala`, `admin`, `representado` | quién/qué originó el evento |
| `quorum_resultante` | decimal(8,4) | % de quórum inmediatamente después del evento |
| `created_at` | timestamp | |

Se escribe un evento en cada punto donde hoy se crea/confirma o desconfirma
una asistencia (entrada auto al entrar a la sala, confirmación/desconfirmación
manual del admin, registro de representados al entrar el apoderado). La
desconexión del canal de presencia sigue siendo solo display (sin evento),
igual que hoy. Con la secuencia de eventos, el quórum es reconstruible en
cualquier instante de la reunión — requisito de reuniones virtuales.

**Snapshot por votación — columnas JSON en `votaciones`:**

- `quorum_apertura` (nullable json): array completo de `QuorumService::calcular()`
  persistido en `VotacionController::abrir()`.
- `quorum_cierre` (nullable json): ídem en `cerrar()`.

`abrir()` ya calcula el quórum hoy (lo deja en `ReunionLog.metadata`); solo se
persiste además estructurado. El acta PDF muestra el quórum de apertura/cierre
de cada votación, que es lo que valida legalmente cada decisión.

---

## Grupo B — Fixes legales críticos

### B1. Bloqueo de voto delegado de poderdante en mora (Art. 38)

Hoy el bloqueo de mora solo evalúa al copropietario que vota; un moroso puede
"lavar" la restricción delegando su voto.

**Fix en `VotoService::votar()`:** cuando `$enNombreDeId !== null`, cargar el
poderdante y aplicar el mismo check: si `poderdante->en_mora` y
`reunion->tenant->restringir_voto_morosos`, rechazar con error explícito que
nombre al poderdante. Reglas resultantes:

- Voto propio de moroso → bloqueado (ya implementado).
- Voto `en_nombre_de` poderdante moroso → **bloqueado** (nuevo).
- Apoderado moroso votando por poderdantes al día → **permitido** (su voto
  propio sigue bloqueado).

**Frontend:**

- Sala del copropietario: la opción del poderdante moroso aparece deshabilitada
  con leyenda de mora (el dato `en_mora` del poderdante viaja en
  `poderdantesRepresentados`). El backend valida siempre.
- Módulo Poderes (form de creación y lista): badge/alerta "Poderdante en mora"
  cuando aplique. No bloquea registro ni aprobación.

**Tests (TDD):** los 3 escenarios de reglas + caso con restricción del tenant
desactivada (todo permitido). Simular flujo PIN (sin `current_tenant` en el
contenedor), como en `VotoServiceMoraTest`.

### B2. Bloqueo de apertura de votación con mayoría especial

**Fix en `VotacionController::abrir()`:** antes de abrir, con el quórum ya
calculado ahí:

- `tipo_mayoria === 'calificada_70'` y coeficiente presente < 70% del total →
  rechazar apertura.
- `tipo_mayoria === 'unanimidad'` y coeficiente presente < 100% → rechazar.
- El error indica presencia actual vs. requerida.
- `BYPASS_QUORUM` no exime esta regla (es imposibilidad matemática de
  aprobación, no quórum de instalación).
- El umbral se evalúa **siempre por coeficiente** (los Arts. 45/46 Ley 675
  hablan de coeficientes), independiente de `tipo_voto_peso`.

**Frontend (Conducir):** el botón "Abrir" de votaciones con mayoría especial se
deshabilita con el motivo visible cuando el quórum en pantalla no alcanza el
umbral (cortesía de UX; la validación real es backend).

**Tests (TDD):** apertura rechazada con 69.99% para calificada_70; permitida
con 70%; unanimidad rechazada con 99%; permitida con 100%; mayoría simple abre
sin restricción; `BYPASS_QUORUM=true` no exime.

---

## Grupo A — Lote UI

### A1. Estado de deudor visible (admin)

- `Copropietarios/Show`: badge "Al día" (verde) / "En mora" (rojo/ámbar).
- `Copropietarios/Index`: en filas con `en_mora=true`, borde izquierdo + fondo
  tenue de alerta, además del badge.

### A2. Poderes/Create — búsqueda de poderdante

Reemplazar el `<select>` de poderdante por el mismo input con búsqueda que usa
el campo de delegado. Si ese input está inline, extraerlo a componente
compartido (`Components/BuscadorCopropietario.jsx`) y usarlo en ambos campos.

### A3. Super-admin/Tenant/Show — índice de copropietarios

Tabla paginada server-side (`paginate()` + links Inertia). Columnas: nombre,
documento, unidades (números unidos), coeficiente sumado, activo, en_mora.

### A4. Reunión/lista-acceso

- Paginación server-side + input de búsqueda (nombre, documento, unidad).
- PIN oculto por defecto (`••••••`), click para revelar por fila.
- Botón/link "Lista de acceso" en `Reuniones/Show`.

### A5. Texto de resultados neutral

Reemplazar "Ganó: Sí (44%)" en Conducir, sala, feed y acta PDF/CSV:

- Con tipo de decisión: `Resultado: Aprobada (44%)` / `Resultado: Rechazada (44%)`
  usando el resultado legal calculado.
- Sin tipo de decisión: `Mayor votación: Sí (44%)`.

El porcentaje mostrado no cambia respecto a hoy (peso de la opción mayoritaria
sobre el total de votos emitidos); solo cambia la etiqueta.

---

## Grupo D — Config y transversal

### D1. Super-admin — exponer `restringir_voto_morosos`

Agregar el toggle a los formularios de creación y edición de tenants del
super-admin, junto a `max_poderes_por_delegado`. El campo ya existe en DB,
modelo y panel admin del conjunto.

### D2. Auditoría de fechas/hora/zona (`America/Bogota`)

- La DB sigue en UTC (estándar Laravel); la conversión es al mostrar.
- Frontend: helper único `resources/js/utils/fecha.js` con
  `Intl.DateTimeFormat('es-CO', { timeZone: 'America/Bogota' })` en variantes
  `fechaCorta`, `fechaHora`, `hora`. Barrido de todas las páginas que
  renderizan fechas para usarlo (el barrido produce lista verificable de
  vistas tocadas).
- Backend: revisar `config/app.php` y los renders server-side (acta PDF DomPDF,
  CSV) para formatear con `America/Bogota`.

---

## Fuera de alcance (fases futuras)

- Jerarquía de nomenclatura de unidades (etapas, torres, interiores, pisos).
- Representante legal del tenant.
- Registro de eventos de salida desde el canal de presencia (la desconexión
  WebSocket sigue siendo solo display).

## Testing

- Todo lo de Grupos B y C con TDD (Pest, patrón de `VotoServiceMoraTest` /
  `SalaReunionShowTest`, incluyendo el escenario PIN sin `current_tenant`).
- Grupo A/D: tests de feature ligeros donde hay backend (paginación, búsqueda,
  validación de tenant form); los cambios puramente visuales se verifican
  manualmente.
- Suite completa verde antes de cada commit (convención del proyecto:
  un commit por tarea, sin firma Co-Authored-By).
