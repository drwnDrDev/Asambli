# Spec: Copropietario Sala — Real-time Experience

**Fecha:** 2026-03-17
**Rama:** main

---

## Objetivo

Mejorar `Sala/Show.jsx` del copropietario para que tenga una experiencia en tiempo real completa durante la asamblea: confirmación de voto explícita, transición visual post-voto hacia resultados en vivo, indicador de conexión, estado de reunión, quórum dinámico, y feed cronológico de eventos de la asamblea.

---

## Contexto

### Lo que ya funciona (no modificar)
- `Admin/Reuniones/Conducir.jsx` — tiempo real completo ✅
- `Admin/Reuniones/Proyeccion.jsx` — resultados en vivo ✅
- Copropietario ya recibe: `QuorumActualizado`, `EstadoVotacionCambiado`, `AvisoEnviado`, `VotacionModificada` ✅
- `VotoService` — transacciones atómicas + hash SHA-256 ✅
- Job `RecalcularResultadosVotacion` — dispara `ResultadosVotacionActualizados` (private) ✅

### Problemas actuales
1. `ResultadosVotacionActualizados` es evento `private` — copropietario no lo recibe
2. Confirmación de voto es solo local (estado React), sin feedback visual claro
3. No hay visibilidad del estado general de la reunión (iniciada, receso, finalizada)
4. No hay indicador de conexión WebSocket
5. No hay feed cronológico de eventos de la asamblea

---

## Arquitectura de la Solución

### Enfoque: Evento público paralelo + estado inicial desde backend

**Principio:** No modificar el canal privado existente del admin. Crear un evento público nuevo `ResultadosPublicosVotacion` con datos reducidos (sin detalle de quién votó desde qué unidad).

#### Nuevo evento: `ResultadosPublicosVotacion`
- **Canal:** `public reunion.{id}`
- **Disparado por:** Job `RecalcularResultadosVotacion` (junto al evento privado existente)
- **Payload:**
  ```json
  {
    "votacion_id": 1,
    "resultados": [
      { "opcion_id": 1, "texto": "SÍ", "count": 12, "peso_total": 0.623 },
      { "opcion_id": 2, "texto": "NO", "count": 7, "peso_total": 0.377 }
    ]
  }
  ```
  **Nota:** se usa `peso_total` (suma de coeficientes) igual que el job existente. El porcentaje se calcula en frontend: `(peso_total / suma_total) * 100`.
- **No incluye:** unidad del último votante, timestamps individuales

#### Nuevo evento: `EstadoReunionCambiado`
- **Canal:** `public reunion.{id}`
- **Disparado por:** `ReunionTransicionService::transicionar()` al final, después de `$reunion->save()` — es el punto canónico donde ocurren **todas** las transiciones de estado (el controller solo delega a este service)
- **Payload:** `{ "estado": "en_curso" | "suspendida" | "finalizada" | "cancelada" | "reprogramada", "timestamp": "..." }`
- **Nota:** El valor correcto del enum es `suspendida` (no `en_receso`). Los estados terminales son `finalizada`, `cancelada` y `reprogramada`.

#### Carga inicial del feed (backend → Inertia props)
`SalaReunionController::show()` incluye en los props:
- `feedInicial`: array ordenado cronológicamente (más antiguo primero) construido desde:
  - `ReunionLog` — fuente de cambios de estado de la reunión (ya poblado por `ReunionTransicionService`)
  - Votaciones `cerradas` de la reunión con su opción ganadora (max `peso_total` entre sus `OpcionVotacion`, calculado via `withoutGlobalScopes()` sobre `Voto`)
  - **Avisos:** los avisos del admin NO son persistidos en DB actualmente — no aparecerán en el feed al recargar. Se acepta esta limitación; los avisos solo se ven en tiempo real.
- `estadoReunion`: estado actual de la reunión (valor del enum `ReunionEstado`)
- `votacionAbierta`: votación activa si existe (con opciones)
- `resultadosActuales`: resultados parciales de la votación abierta, **solo si el copropietario ya votó** (`yaVotoPor` no está vacío). Calculado igual que `ReunionController::proyeccion()`, usando `withoutGlobalScopes()` sobre `Voto` para evitar que el `TenantScope` filtre a cero. Formato: `[{ opcion_id, texto, count, peso_total }]`. El porcentaje (`peso_pct`) se calcula en el frontend como `(peso_total / suma_total) * 100`.

---

## Componentes UI

### Estética: "Cámara Cívica Digital"
- **Fondo:** `#0a0f1e` (dark navy)
- **Acentos:** ámbar `#f59e0b` (votación activa, confirmación), verde `#10b981` (quórum OK, voto confirmado)
- **Tipografía display:** Fraunces (preguntas de votación) — Google Fonts via bunny.net
- **Tipografía cuerpo:** DM Sans (ya instalado en admin-theme.css)
- **Consistencia:** mismo `#0c111d` del sidebar admin para status bar

---

### 1. Status Bar (sticky top)

Tres elementos en una barra fija:

| Elemento | Estados |
|----------|---------|
| Dot de conexión | `●` verde animado (conectado) / naranja parpadeante (reconectando) / rojo (desconectado) |
| Badge estado reunión | `EN CURSO` verde / `SUSPENDIDA` naranja / `FINALIZADA` · `CANCELADA` · `REPROGRAMADA` rojo tenue |
| Pill quórum | `67% Q` verde si tiene quórum / gris si no |

Cuando la reunión finaliza: banner sobre el status bar invitando a salir, con auto-redirect tras 10 segundos.

---

### 2. Card de Votación Activa (protagonista)

Card elevada `full-width`, borde ámbar, ~60vh. Tiene **dos estados**:

#### Estado A: Pendiente de voto
```
┌─────────────────────────────────┐
│ VOTACIÓN ABIERTA                │
│                                 │
│  ¿Pregunta de la votación?      │  ← Fraunces display
│                                 │
│  [ Opción A ]                   │
│  [ Opción B ]                   │
│  [ Opción C ]                   │
└─────────────────────────────────┘
```
- Click en opción → abre modal de confirmación
- Opciones: botones full-width, hover con borde ámbar

#### Modal de Confirmación (overlay)
```
┌─────────────────────────────┐
│  Confirmar voto             │
│                             │
│  Vas a votar por:           │
│  ┌───────────────────────┐  │
│  │  ✦  Opción B          │  │  ← borde ámbar
│  └───────────────────────┘  │
│                             │
│  Esta acción no se puede    │
│  deshacer.                  │
│                             │
│  [Cancelar]  [Confirmar →]  │
└─────────────────────────────┘
```
- "Confirmar" en ámbar sólido
- "Cancelar" en ghost/outline

#### Estado B: Ya votó (flip animado)
```
┌─────────────────────────────────┐
│ VOTACIÓN EN CURSO               │
│                                 │
│  ¿Pregunta de la votación?      │
│                                 │
│  ✓ Votaste: Opción B            │  ← verde, badge fijo
│                                 │
│  Opción A  ████░░░░  45%        │
│  Opción B  █████████ 55%        │  ← actualización en vivo
│  Opción C  ░░░░░░░░░  0%        │
└─────────────────────────────────┘
```
- Transición flip CSS al confirmar voto
- Barras animadas, actualizadas por `ResultadosPublicosVotacion`
- Badge "✓ Votaste: X" permanente
- Si el admin cierra la votación: card muestra ganador resaltado, luego baja al feed

#### Estado vacío (sin votación activa)
```
┌─────────────────────────────────┐
│                                 │
│   Sin votación activa           │
│   El administrador abrirá       │
│   la siguiente votación.        │
│                                 │
└─────────────────────────────────┘
```
Card con borde sutil, sin acento ámbar.

---

### 3. Feed Cronológico

Lista vertical, más reciente arriba, que crece con nuevos eventos WebSocket.

**Tipos de entrada:**

| Tipo | Icono | Acento |
|------|-------|--------|
| Reunión iniciada / reanudada | `▶` | verde |
| Reunión suspendida (`suspendida`) | `⏸` | naranja |
| Reunión finalizada / cancelada / reprogramada | `⏹` | rojo tenue |
| Votación cerrada + ganador | icono de urna | azul |
| Aviso del admin | icono de megáfono | ámbar |

**Votación cerrada en feed:**
```
┌─────────────────────────────────┐
│ 🗳 Votación cerrada  10:32      │
│   "Aprobación presupuesto"      │
│   Ganó: ✅ SÍ  (62.3%)         │
└─────────────────────────────────┘
```
Solo muestra la opción ganadora y su porcentaje — no el desglose completo.

**Carga:** `feedInicial` desde backend en props de Inertia. Nuevos eventos se prependean con animación slide-in.

---

## Flujo de Datos

```
COPROPIETARIO                    BACKEND                      ADMIN
    |                               |                            |
    | ─── POST /votos ──────────>   |                            |
    |                          VotoService                       |
    |                          RecalcularResultadosJob           |
    |                               |──► ResultadosVotacionActualizados (private) ──► Admin/Proyeccion
    |                               |──► ResultadosPublicosVotacion (public) ──────► Copropietario
    |                               |
    | <── QuorumActualizado ─────── |
    | <── EstadoVotacionCambiado ── |  (admin abre/cierra votación)
    | <── AvisoEnviado ──────────── |  (admin envía aviso)
    | <── EstadoReunionCambiado ─── |  (admin cambia estado reunión)
    | <── ResultadosPublicosVotacion|  (después de cada voto)
```

---

## Archivos a Crear/Modificar

### Backend
| Archivo | Acción | Descripción |
|---------|--------|-------------|
| `app/Events/ResultadosPublicosVotacion.php` | Crear | Evento público con datos reducidos de resultados |
| `app/Events/EstadoReunionCambiado.php` | Crear | Evento público de cambio de estado de reunión |
| `app/Jobs/RecalcularResultadosVotacion.php` | Modificar | Disparar también `ResultadosPublicosVotacion` |
| `app/Services/ReunionTransicionService.php` | Modificar | Disparar `EstadoReunionCambiado` al final de `transicionar()`, después de `$reunion->save()` — punto canónico de todas las transiciones |
| `app/Http/Controllers/Copropietario/SalaReunionController.php` | Modificar | Incluir `feedInicial`, `estadoReunion`, `resultadosActuales` en props |

### Frontend
| Archivo | Acción | Descripción |
|---------|--------|-------------|
| `resources/js/Pages/Copropietario/Sala/Show.jsx` | Modificar | Rediseño completo con todos los componentes nuevos. Listeners WebSocket: `QuorumActualizado`, `EstadoVotacionCambiado`, `AvisoEnviado`, `VotacionModificada` (ya existentes) + `ResultadosPublicosVotacion` y `EstadoReunionCambiado` (nuevos). `VotacionModificada` actualiza `votacionActiva` state en acción `updated` para evitar que el copropietario vote sobre datos desactualizados. |
| `resources/css/app.css` o `sala-theme.css` | Modificar/Crear | Variables CSS para dark navy + ámbar + Fraunces |

### No modificar
- `app/Events/ResultadosVotacionActualizados.php` — canal privado admin intacto
- `resources/js/Pages/Admin/Reuniones/Conducir.jsx` — no tocar
- `resources/js/Pages/Admin/Reuniones/Proyeccion.jsx` — no tocar
- `app/Services/VotoService.php` — lógica de voto intacta

---

## Consideraciones Técnicas

- **Orden de rutas:** `/sala/entrada/{token}` ya está declarado antes del grupo `/sala/{reunion}` — no modificar
- **BYPASS_QUORUM:** flag de dev existente — `VotoService` no se toca, sigue respetando esta config
- **Transacción vs broadcast:** el evento `ResultadosPublicosVotacion` se dispara desde el Job (fuera de transacción DB), igual que el evento privado existente
- **Auto-redirect al finalizar:** cuando llega `EstadoReunionCambiado` con estado terminal (`finalizada`, `cancelada`, `reprogramada`), mostrar banner descriptivo + redirect a `/historial` tras 10 segundos. Al implementar, expandir el filtro de `SalaReunionController::historial()` de `where('estado', 'finalizada')` a `whereIn('estado', ['finalizada', 'cancelada', 'reprogramada'])` para que la reunión recién terminada aparezca en la lista.
- **Poderes (delegación):** el flujo de voto delegado existente se mantiene — el modal de confirmación aplica también para votos en nombre de apoderados
- **Fraunces font:** cargar via bunny.net (igual que DM Sans en admin-theme.css) para evitar llamadas a Google Fonts directamente

---

## Criterios de Éxito

1. Copropietario ve modal de confirmación antes de emitir su voto
2. Tras confirmar, el card hace flip y muestra resultados en tiempo real
3. Resultados se actualizan en vivo con cada voto emitido por cualquier copropietario
4. Status bar muestra conexión, estado de reunión y quórum actualizados en tiempo real
5. Feed cronológico carga historial al entrar y recibe nuevos eventos en vivo
6. Al finalizar la reunión, se notifica al copropietario y se redirige automáticamente
7. El flujo del admin (Conducir, Proyeccion) no se ve afectado en absoluto
