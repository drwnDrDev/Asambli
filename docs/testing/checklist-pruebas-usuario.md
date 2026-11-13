# Checklist de pruebas de usuario — ASAMBLI

> Referencia para cada ronda de pruebas manuales. Copiar la sección "Registro de ronda" al final
> para anotar fecha, rama y hallazgos. Actualizar este archivo cuando se agreguen flujos nuevos.
>
> **Leyenda:** `[ ]` funcionalidad implementada — probar · ⚠️ implementada con problema conocido ·
> 🔍 posible bug a confirmar · ❌ **no implementada** (secciones 6–7) — verificar que la app no
> falle ni engañe al usuario, y marcar `[x]` cuando se implemente y se pruebe.

---

## 0. Preparación

- [ ] Docker arriba: `WWWGROUP=1000 WWWUSER=1000 docker compose up -d`
- [ ] Tests verdes: `./sail artisan test --no-coverage`
- [ ] Assets: `./sail pnpm run build` (o `./sail pnpm run dev` para hot-reload)
- [ ] Reverb corriendo: `./sail artisan reverb:start` (sin esto no hay tiempo real)
- [ ] (Opcional) Base limpia: `./sail artisan migrate:fresh --seed`
- [ ] `BYPASS_QUORUM` en `.env`: decidir si se prueba con `true` o `false` (anotarlo en el registro)
- [ ] Tener a mano 3 navegadores/ventanas aisladas: **admin**, **copropietario A**, **copropietario B** (incógnito / perfiles distintos)

Credenciales seed: `super@asambli.co` / `password` · `admin@conjuntotest.co` / `password`

---

## 1. Super admin (`/super-admin`)

### 1.1 Tenants
- [x] Login como super admin → redirige a `/super-admin/dashboard`
- [x] Crear tenant **con** admin → se crean tenant y usuario admin
- [x] Crear tenant **sin** admin → válido; luego agregar admin desde el show del tenant
- [x] Editar tenant: nombre, `max_poderes`, toggle **restringir voto morosos**, producto
- [x] Activar/desactivar un usuario admin (toggle); no permite desactivarse a sí mismo
- [?] Show del tenant: índice paginado de copropietarios
- [ ] Auditoría del tenant (`/super-admin/tenants/{id}/auditoria`) lista acciones

### 1.2 Padrón
- [ ] Importar padrón CSV válido → crea copropietarios y unidades
- [x] Importar XLSX válido
- [ ] Archivo con errores (columnas faltantes, coeficiente inválido, documento duplicado) → mensajes claros, no importa basura
- [ ] Copropietario con **varias unidades** se importa como un solo copropietario con N unidades
- [x] Re-importar el mismo archivo no duplica registros

### 1.3 Reuniones (creación)
- [ ] Crear reunión desde super admin: `tipo_cuerpo` (asamblea/consejo), `tipo_convocatoria` (ordinaria/extraordinaria), modalidad, peso de voto (coeficiente/unidad), quórum requerido, fecha
- [ ] Reset de convocatoria funciona y queda en auditoría
- [ ] Eliminar copropietario (solo super admin)

---

## 2. Administrador (`/admin`)

### 2.1 Acceso y aislamiento
- [ ] Login admin → `/admin/dashboard`
- [ ] Admin NO puede entrar a `/super-admin/*` (403)
- [ ] Admin del tenant A no ve datos del tenant B (probar cambiando IDs en la URL)
- [ ] Configuración del conjunto (`/admin/configuracion`) guarda cambios

### 2.2 Copropietarios
- [ ] Listado con búsqueda; muestra unidades unidas (`"101, 201"`) y coeficiente sumado
- [ ] Crear / editar copropietario
- [ ] Marcar **en mora** → badge visible en index y show
- [ ] Quitar mora → badge desaparece

### 2.3 Poderes
- [ ] Registrar poder: buscador de poderdante y apoderado; aviso visible si alguno está en mora
- [ ] Poder registrado por el admin queda **aprobado automáticamente** (no pasa por pendiente)
- [ ] Poder queda asociado a una **reunión vigente** (sin reunión vigente → error)
- [ ] Delegado **externo** (no copropietario): nombre, tipo/número de documento, email, empresa → se crea y puede entrar a la sala
- [ ] Delegado externo que ya existe (mismo email o documento) se reutiliza, no se duplica
- [ ] Rechazar / eliminar poder → el apoderado pierde la representación
- [ ] Respeta `max_poderes_por_delegado` del tenant (intentar exceder → error)
  - 🔍 el conteo incluye poderes de **todas** las reuniones (no filtra `reunion_id`): probar si un poder viejo bloquea al delegado en una reunión nueva
- [ ] Copropietario que ya delegó su voto no puede ser apoderado
- [ ] No permite poder a sí mismo
- [ ] Un poder aprobado para la reunión X **no** aparece ni cuenta en la reunión Y

### 2.4 Ciclo de vida de la reunión
Transiciones válidas: `borrador → ante_sala → en_curso ⇄ suspendida → finalizada` · cancelar desde cualquier no-terminal · reprogramar solo desde ante_sala

- [ ] Convocar → se generan PINs y se envían notificaciones (revisar log/mail)
- [ ] Abrir ante sala (exige observación si aplica)
- [ ] Iniciar → `en_curso`
- [ ] Suspender y reactivar
- [ ] Reprogramar desde ante sala
- [ ] Cancelar
- [ ] Finalizar → PINs quedan anulados
- [ ] Transición inválida (p.ej. finalizar desde borrador) no aparece / es rechazada
- [ ] Cada transición aparece en auditoría con estado anterior

### 2.5 Lista de acceso
- [ ] Link visible en estados ante_sala / en_curso / suspendida
- [ ] Búsqueda y paginación
- [ ] PIN oculto por defecto, se puede revelar individualmente
- [ ] Activar / desactivar acceso de un copropietario → ese copropietario no puede entrar
- [ ] Reenviar acceso
- [ ] Imprimir lista (⚠️ conocido: los PINs salen ocultos)

### 2.6 Conducir reunión (`/admin/reuniones/{id}/conducir`)
- [ ] Panel de quórum se actualiza en tiempo real al entrar copropietarios
- [ ] Modo **coeficiente**: % por suma de coeficientes; modo **unidad**: por conteo
- [ ] Confirmar asistencia manual de un copropietario (origen `admin`)
- [ ] Quórum de presencia manual (`quorum-presencia`)
- [ ] Copropietario presente **y** representado por poder no se cuenta doble
- [ ] Enviar aviso a la sala → aparece en las pantallas de copropietarios
- [ ] Generar QR de entrada → mensaje "válido por 72 horas" (❌ el QR no lleva a ninguna pantalla, ver 6.1)
- [ ] Vista de proyección (`/proyeccion`) muestra quórum y votación activa

### 2.7 Votaciones
- [ ] Crear votación de cada tipo: `si_no`, `si_no_abstencion`, `opcion_multiple`
- [ ] Seleccionar **tipo de decisión** (12 del catálogo) → muestra mayoría (simple / calificada 70% / unanimidad)
- [ ] Editar y eliminar votación en estado `creada`
- [ ] Abrir votación → llega en tiempo real a la sala
- [ ] **Bloqueo legal:** abrir votación `calificada_70` con presencia < 70% → rechazada con mensaje (aun con `BYPASS_QUORUM=true`)
- [ ] **Bloqueo legal:** abrir `unanimidad` con presencia < 100% → rechazada
- [ ] Con presencia suficiente → abre normal
- [ ] Resultados se actualizan en vivo mientras votan
- [ ] Cerrar votación → resultado legal (aprobada/rechazada) correcto según mayoría
  - simple: sobre votos emitidos
  - calificada_70 / unanimidad: sobre **coeficiente total del conjunto**
- [ ] Recargar Conducir con votación cerrada (⚠️ conocido: `mayoriaData` llega null)
- [ ] Vista de resultados (`/admin/votaciones/{id}/resultados`)

### 2.8 Reportes y auditoría
- [ ] Acta PDF: datos de reunión, asistentes (una fila por unidad), votaciones, resultado legal, **snapshots de quórum de apertura/cierre** por votación, hash SHA-256
- [ ] Fechas/horas del PDF en hora Colombia (⚠️ conocido: mezcla 12h/24h y % sin formato)
- [ ] CSV de asistencia (una fila por unidad)
- [ ] CSV de votos
- [ ] Auditoría de la reunión lista transiciones, votos, asistencias

---

## 3. Copropietario (sala)

### 3.1 Acceso
- [ ] `/sala/login/{reunion}` con documento + PIN correcto → entra a la sala
- [ ] Documento o PIN incorrecto → "Documento o PIN incorrecto." (sin revelar cuál)
- [ ] Copropietario inactivo o con acceso desactivado → no entra
- [ ] Login en un segundo dispositivo rota el token → la sesión anterior queda inválida
- [ ] Tras finalizar la reunión el PIN ya no sirve

### 3.2 Sala en vivo
- [ ] Al entrar se registra asistencia (origen `auto_sala`) y el quórum del admin sube en vivo
- [ ] Ve sus unidades y coeficiente sumado
- [ ] Recibe apertura de votación sin recargar
- [ ] Vota → confirmación; no puede votar dos veces
- [ ] No puede votar en votación cerrada
- [ ] Recibe avisos del admin
- [ ] Recargar la página recupera el estado actual (`estado-actual`)
- [ ] Historial (`/historial`) muestra reuniones y votos pasados

### 3.3 Voto delegado (poderes)
- [ ] Apoderado ve a sus poderdantes **de esta reunión** (pantalla DelegadoActivo)
- [ ] Vota por sí mismo y por cada poderdante por separado
- [ ] Poderdante representado queda registrado como asistente (origen `representado`)
- [ ] Poderdante que también entra personalmente → no se cuenta doble en quórum

### 3.4 Mora (Art. 38) — con `restringir_voto_morosos` activo en el tenant
- [ ] Copropietario moroso **no** puede votar por sí mismo (mensaje claro)
- [ ] Apoderado al día **no** puede votar por un poderdante moroso (mensaje nombra al poderdante)
- [ ] Apoderado **moroso** **sí** puede votar por poderdantes al día
- [ ] Con el toggle desactivado, los morosos votan normal

---

## 4. Transversales

- [ ] Fechas en toda la UI en formato es-CO, zona America/Bogota
- [ ] Responsive: sala del copropietario usable en celular (390px)
- [ ] Desconectar/reconectar red del copropietario → la sala se re-sincroniza
- [ ] Varios copropietarios votando a la vez → conteo correcto, sin votos perdidos
- [ ] Mensajes de error en español y comprensibles
- [ ] Sin errores en consola del navegador ni en `storage/logs/laravel.log`

---

## 5. Escenario end-to-end sugerido (smoke test, ~30 min)

1. Super admin crea tenant + admin, importa padrón (≥ 5 copropietarios, uno con 2 unidades), crea reunión.
2. Admin marca 1 copropietario en mora, registra 1 poder para esa reunión y lo aprueba.
3. Admin convoca → ante sala → inicia.
4. Copropietarios A (apoderado) y B entran por PIN; verificar quórum en vivo.
5. Votación simple: abrir, votar (incluye voto delegado), cerrar → resultado correcto.
6. Votación calificada_70 con presencia < 70% → bloqueada. Confirmar más asistencia → abre.
7. Moroso intenta votar → bloqueado.
8. Finalizar reunión → descargar PDF y CSVs → revisar snapshots, hash y fechas.

---

## 6. Funcionalidades NO implementadas — producto

Hoy no existen. Al probar: confirmar que su ausencia no rompe nada ni engaña al usuario.
Cuando se implementen, convertir cada bloque en casos de prueba normales.

### 6.1 Entrada por QR
- [ ] ❌ Escanear QR → pantalla de entrada (`EntradaQR.jsx` existe pero sin ruta; `qr_token` se genera y nadie lo consume)
- [ ] ❌ QR expirado (> 72 h) → mensaje claro
- [ ] ❌ Regenerar QR invalida el anterior

### 6.2 Asistencia: salidas
- [ ] ❌ Registrar **salida** de un copropietario (enum `salida` existe en `asistencia_eventos`, nada lo escribe)
- [ ] ❌ Quórum baja en vivo cuando alguien sale
- [ ] ❌ Acta/auditoría muestra hora de salida

### 6.3 Pantallas huérfanas (decidir: implementar o borrar)
- [ ] ❌ `Auth/AccesoRapido.jsx` — sin ruta
- [ ] ❌ `Onboarding/Index.jsx` — sin ruta

### 6.4 Infraestructura
- [ ] ❌ Tasks 25–26 del plan original (decisión final de deploy/infra)
- [ ] ❌ Reverb como daemon en producción (supervisor) — hoy se arranca a mano

---

## 7. Funcionalidades NO implementadas — cumplimiento legal

Fuente: `docs/legal/compliance-ley675.md` (número de ítem entre paréntesis). 🟡 = parcial.

### 7.1 Quórum y mora
- [ ] 🟡 Morosos **excluidos del quórum** de deliberación y votación (3.4 / 8.3) — 🔴 riesgo de nulidad
- [ ] ❌ Verificación de paz y salvo **antes** de la reunión; bloquear cambios de `en_mora` durante la reunión (8.4)
- [ ] ❌ Diferenciar **voz vs. voto** en la sala (3.5)

### 7.2 Convocatoria
- [ ] 🟡 Validar plazo: ordinaria ≥ 15 días hábiles, extraordinaria según reglamento (2.1 / 2.2)
- [ ] ❌ **Orden del día** en la convocatoria (2.3)
- [ ] 🟡 Lugar físico / **URL de plataforma** virtual en la convocatoria (2.4 / 2.5)
- [ ] ❌ **Segunda citación** con quórum reducido (2.6 / 3.2)

### 7.3 Poderes y apoderados
- [ ] ❌ Admin, miembros del consejo y cónyuges **no pueden ser apoderados** (5.2)
- [ ] 🟡 Adjuntar **documento escrito** del poder (upload, hoy solo `documento_url` texto) (5.4)
- [ ] ❌ Administrador externo no puede votar a nombre propio (4.8)

### 7.4 Acta
- [ ] ❌ Orden del día tratado en el PDF (7.2)
- [ ] ❌ Designación y **firma** de presidente y secretario (7.1)
- [ ] 🟡 Declaración de **medios tecnológicos** usados (reuniones virtuales) (6.3)
- [ ] ❌ URL de **grabación** de la reunión virtual (6.4)
- [ ] ❌ Ciclo de **aprobación** del acta / 30 días de objeción (7.7)
- [ ] ❌ **Libro de actas** consultable por copropietarios (7.8)

### 7.5 Reuniones virtuales y producto
- [ ] 🟡 Verificación de **identidad** más allá del PIN (6.2)
- [ ] ❌ Gate de funciones por **`tenant.producto`** (presencial / virtual / ambos) (6.6)

### 7.6 Estructura del conjunto
- [ ] ❌ Validar que los **coeficientes sumen 100** al crear/editar unidades e importar padrón (1.2)
- [ ] ❌ **Etapas** con sesión y presupuesto propios; asamblea conjunta de etapas (1.4 / 1.5)
- [ ] ❌ Jerarquía de nomenclatura: etapas / torres / interiores / pisos
- [ ] ❌ Representante legal del tenant
- [ ] 🟡 Fecha de designación del administrador (9.1); conflicto admin-propietario (9.2)
- [ ] ❌ Módulo de **consejo de administración** (> 30 unidades) (9.3)

---

## Problemas conocidos (no reportar como nuevos)

- Mayoría calificada: denominador sin filtro `activo=true` en `calcularResultado`/`RecalcularResultados`
- `mayoriaData` null al recargar Conducir
- Impresión de lista de acceso con PINs ocultos
- Acta: formatos de hora 12h/24h mixtos; porcentajes sin `number_format`
- Opciones de votación antiguas sin backfill `orden=0`
- Morosos aún cuentan para el quórum (checklist legal 3.4/8.3 — fase pendiente)

---

## Registro de ronda

```
Fecha:
Rama / commit:
BYPASS_QUORUM:
Probado por:

Hallazgos:
- [ ] (sección) descripción — severidad (bloqueante / mayor / menor)
```
