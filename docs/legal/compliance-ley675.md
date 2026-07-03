# Compliance Legal — Asambli
## Referencia: Ley 675 de 2001 y normas complementarias

**Última revisión:** 2026-07-03  
**Alcance:** Requisitos legales que el sistema debe satisfacer para que las asambleas sean válidas según la ley colombiana de propiedad horizontal.

> **Actualización 2026-07-03 (branch `feat/cumplimiento-legal-ley675`):** implementado el núcleo de mayorías legales (catálogo `tipos_decision`, mayoría simple / calificada 70% / unanimidad con denominador sobre el total del conjunto), restricción de voto para morosos (propio y vía poder), bloqueo de apertura de votaciones calificadas sin presencia mínima, snapshots de quórum por votación en el acta, y log de eventos de asistencia con quórum resultante (soporte para reuniones virtuales L2190).

**Leyenda de estado:**
- ✅ Implementado — cumple el requisito
- 🟡 Parcial — existe base pero faltan validaciones o flujos
- ❌ Pendiente — no existe en el sistema
- ⚠️ Atención — implementado pero necesita revisión por riesgo de nulidad

---

## 1. Estructura del conjunto y coeficientes

| # | Norma | Requerimiento | Feature en Asambli | Estado | Notas |
|---|-------|---------------|-------------------|--------|-------|
| 1.1 | L675 Art. 26 | Cada unidad privada tiene un coeficiente de copropiedad que determina su participación en decisiones | Campo `coeficiente` en tabla `unidades` | ✅ | Decimal(8,5) |
| 1.2 | L675 Art. 26 | Los coeficientes de todas las unidades de un conjunto suman 100 (o 1 en proporción) | Validación de suma total | ❌ | No hay validación al crear/editar unidades. El sistema asume que el admin ingresa bien los datos. |
| 1.3 | L675 Art. 19 | Las unidades se clasifican por tipo (residencial, comercial, parqueadero, etc.) | Campo `tipo` en `unidades` (apartamento/local/parqueadero/otro) | ✅ | |
| 1.4 | L675 Art. 56 | En conjuntos por etapas, cada etapa puede sesionar de forma independiente y tiene su propio presupuesto | Entidad `Etapa` con relación a `unidades` y `reuniones` | ❌ | No existe. Actualmente se usa `torre` como agrupador informal, sin lógica de etapa. |
| 1.5 | L675 Art. 56 | La asamblea general de etapas puede sesionar cuando todas las etapas están incorporadas | Lógica de asamblea conjunta de etapas | ❌ | Depende de implementar 1.4 primero. |

---

## 2. Convocatoria a asamblea

| # | Norma | Requerimiento | Feature en Asambli | Estado | Notas |
|---|-------|---------------|-------------------|--------|-------|
| 2.1 | L675 Art. 27 | Asamblea ordinaria: convocatoria con mínimo **15 días hábiles** de antelación | Campo `fecha_programada` y `convocatoria_enviada_at` en `reuniones` | 🟡 | Los campos existen pero no hay validación de que se envió con ≥15 días hábiles antes de la fecha. |
| 2.2 | L675 Art. 27 | Asamblea extraordinaria: convocatoria con antelación definida en el reglamento (mínimo 5 días hábiles como práctica estándar) | Campos `tipo_cuerpo` + `tipo_convocatoria` en `reuniones` | 🟡 | Los tipos ya se distinguen (asamblea/consejo · ordinaria/extraordinaria). Falta validación de plazos por tipo. |
| 2.3 | L675 Art. 27 | La convocatoria debe incluir el orden del día | Orden del día como campo en reunión | ❌ | No existe campo de orden del día. La reunión solo tiene `titulo`. |
| 2.4 | L675 Art. 27 | La convocatoria debe especificar fecha, hora y lugar (o plataforma virtual) | Campos `fecha_programada` + lugar/URL | 🟡 | `fecha_programada` existe. No hay campo de lugar ni URL de plataforma. |
| 2.5 | L2190/2022 Art. 3 | Para reuniones virtuales, la convocatoria debe especificar la plataforma y cómo acceder | Campo de plataforma/URL en convocatoria virtual | ❌ | No existe. |
| 2.6 | L675 Art. 27 | Límite de veces que se puede repetir una convocatoria sin quórum (segunda citación) | Flujo de segunda citación | ❌ | No existe. El sistema no maneja el concepto de segunda citación con quórum reducido. |

---

## 3. Quórum para deliberar

| # | Norma | Requerimiento | Feature en Asambli | Estado | Notas |
|---|-------|---------------|-------------------|--------|-------|
| 3.1 | L675 Art. 29 | **Primera citación:** quórum mínimo = más de la mitad de los coeficientes (>50%) | `quorum_requerido` configurable en reunión + `QuorumService` | ✅ | El admin debe ingresar 50 manualmente. No hay default por tipo de reunión. |
| 3.2 | L675 Art. 29 | **Segunda citación:** la asamblea sesiona con cualquier número de asistentes (quórum reducido) | Estado de segunda citación con quórum diferente | ❌ | No existe. El sistema tiene un solo `quorum_requerido` fijo por reunión. |
| 3.3 | L675 Art. 29 | El quórum se calcula incluyendo los poderes otorgados a copropietarios presentes | Poderes incluidos en el cálculo de quórum | ✅ | Implementado en `QuorumService` (coeficiente y unidad) |
| 3.4 | L675 Art. 38 | Los copropietarios en mora **no cuentan para el quórum** (ni para deliberar ni para votar) | Exclusión de deudores del cálculo de quórum | 🟡 | Ya existe `en_mora` y el bloqueo de voto (4.7). Los morosos aún cuentan en `QuorumService` para el quórum de deliberación — exclusión pendiente. |
| 3.5 | L675 | El administrador lleva el control de quién tiene voz y voto | Diferenciación voz vs. voto en la sala | ❌ | No existe. Actualmente todos los asistentes pueden votar. |

---

## 4. Votaciones — Mayorías y tipos de decisión

| # | Norma | Requerimiento | Feature en Asambli | Estado | Notas |
|---|-------|---------------|-------------------|--------|-------|
| 4.1 | L675 Art. 45 | **Mayoría ordinaria:** más de la mitad de los coeficientes de los copropietarios *presentes* | `tipo_decision_id` en votaciones + resultado legal persistido (`votaciones.resultado`) | ✅ | Cada votación se clasifica por tipo de decisión; al cerrar se persiste `aprobada`/`rechazada`. |
| 4.2 | L675 Art. 46 | **Mayoría calificada (70%):** algunas decisiones requieren el 70% del total de coeficientes del conjunto, **no solo de los presentes** | `tipo_mayoria=calificada_70` con denominador = coeficiente total del conjunto | ✅ | Implementado en `RecalcularResultadosVotacion` y `VotacionController::calcularResultado`. Además la apertura se **bloquea** si la presencia por coeficiente es <70% (aprobación matemáticamente imposible), sin excepción por `BYPASS_QUORUM`. |
| 4.3 | L675 Art. 46 | Decisiones que requieren mayoría calificada del 70%: reforma al reglamento, cambio de destinación de bienes comunes, desafectación de bienes comunes no esenciales | Catálogo `tipos_decision` (12 tipos seeded) | ✅ | `TiposDecisionSeeder`: presupuesto, estados financieros, elecciones, cuotas, reformas, destinación, desafectación, gravámenes, obras, extinción. |
| 4.4 | L675 Art. 46 | Decisiones que requieren unanimidad o quórum especial: liquidación voluntaria, reconstrucción parcial, extinción del régimen | `tipo_mayoria=unanimidad` | ✅ | Aprobación exige 100% del coeficiente; la apertura exige 100% de presencia. |
| 4.5 | L675 | El resultado de una votación calificada se calcula sobre el 100% del conjunto, no sobre los presentes | Denominador dinámico por tipo de mayoría | ✅ | Simple: sobre votos emitidos. Calificada/unanimidad: sobre coeficiente total del conjunto. Snapshots `quorum_apertura`/`quorum_cierre` respaldan cada decisión. |
| 4.6 | L675 Art. 45 | La abstención no es un voto en contra en mayoría ordinaria | Abstención excluida del cómputo sí/no | ✅ | El cálculo de mayoría excluye opciones de abstención del peso "no". |
| 4.7 | L675 Art. 38 | Los copropietarios en mora **no pueden votar** | Bloqueo en `VotoService` (voto propio y voto delegado) | ✅ | Moroso no vota por sí ni a través de apoderado; apoderado moroso sí ejerce votos de poderdantes al día. Configurable por tenant (`restringir_voto_morosos`). UI: opciones deshabilitadas con aviso Art. 38. |
| 4.8 | L675 | El administrador no puede votar a nombre propio (si es externo/empresa) | Restricción de voto para administrador externo | ❌ | No existe. Los externos actualmente pueden votar si están como copropietarios. |

---

## 5. Poderes y representación

| # | Norma | Requerimiento | Feature en Asambli | Estado | Notas |
|---|-------|---------------|-------------------|--------|-------|
| 5.1 | L675 Art. 30 | Un copropietario puede delegar su voto en cualquier persona (no necesariamente otro copropietario) | Poder hacia copropietario externo | ✅ | Implementado con `es_externo` |
| 5.2 | L675 Art. 30 | El administrador, los miembros del consejo y sus cónyuges **no pueden ser apoderados** de otros en asamblea | Restricción de quién puede ser apoderado | ❌ | No hay validación. Un admin podría ser apoderado de copropietarios. |
| 5.3 | L675 Art. 30 / Reglamento | Límite de poderes por delegado (típicamente 2-3; el reglamento puede definirlo) | Campo `max_poderes_por_delegado` en tenant | ✅ | Implementado. Configurable por conjunto. |
| 5.4 | L675 | El poder debe ser escrito (no verbal) | Campo de poder con evidencia / upload | 🟡 | El poder existe en el sistema pero no hay campo para adjuntar documento escrito. |
| 5.5 | L675 Art. 30 | El poder debe especificar para qué asamblea específica aplica (no genérico) | Poder vinculado a una reunión | ✅ | `Poder` tiene `reunion_id` |

---

## 6. Modalidad virtual (Ley 2190 de 2022)

| # | Norma | Requerimiento | Feature en Asambli | Estado | Notas |
|---|-------|---------------|-------------------|--------|-------|
| 6.1 | L2190/2022 | Las asambleas virtuales y mixtas son válidas si garantizan identificación del participante, deliberación y votación | Sala virtual con autenticación + votación + log de asistencia | 🟡 | La sala existe (PIN + acceso) y ahora `asistencia_eventos` registra cada entrada con timestamp y quórum resultante (trazabilidad de presencia durante toda la reunión). Falta verificación de identidad documental y constancia de medios en el acta. |
| 6.2 | L2190/2022 | La plataforma debe garantizar la identidad de cada participante | Mecanismo de verificación de identidad | 🟡 | Se usa PIN por QR. No hay verificación de documento de identidad. |
| 6.3 | L2190/2022 | El acta debe dejar constancia de los medios tecnológicos utilizados | Campo en acta / PDF de modalidad virtual | 🟡 | El PDF de acta existe pero no incluye declaración de medios tecnológicos. |
| 6.4 | L2190/2022 | La grabación de la reunión virtual no es obligatoria pero sí recomendada como soporte | Referencia a grabación en el acta | ❌ | No existe campo para URL de grabación. |
| 6.5 | L2190/2022 | En reunión mixta, los participantes virtuales tienen los mismos derechos que los presenciales | Sala unificada sin discriminar modalidad | ✅ | No hay diferencia en derechos según canal de acceso. |
| 6.6 | L675 / L2190 | El tipo de producto contratado (presencial/virtual/ambos) debe restringir las funciones disponibles | Gate de funcionalidad por `tenant.producto` | ❌ | El campo `producto` existe en `tenants` pero no hay ningún middleware o lógica que lo respete. |

---

## 7. Actas y auditoría

| # | Norma | Requerimiento | Feature en Asambli | Estado | Notas |
|---|-------|---------------|-------------------|--------|-------|
| 7.1 | L675 Art. 46 | El acta debe ser firmada por el presidente y el secretario de la asamblea | Firma de acta (física o digital) | ❌ | El PDF se genera pero no hay flujo de "firma" ni identificación del presidente/secretario. |
| 7.2 | L675 Art. 46 | El acta debe incluir el orden del día tratado | Orden del día en el PDF de acta | ❌ | El PDF existe pero no incluye orden del día (ver 2.3). |
| 7.3 | L675 Art. 46 | El acta debe registrar el quórum inicial y el quórum al momento de cada votación | Columnas `quorum_apertura`/`quorum_cierre` en votaciones + acta PDF | ✅ | Snapshot completo del quórum al abrir y cerrar cada votación, mostrado por decisión en el acta. Además, `asistencia_eventos` registra cada entrada con el quórum resultante (reconstruible en cualquier instante). |
| 7.4 | L675 Art. 46 | El acta debe listar los copropietarios presentes y los representados por poder | Lista de asistentes en el acta | ✅ | El PDF incluye la lista de asistentes con unidades. |
| 7.5 | L675 | Los votos deben ser trazables con identificación del copropietario (excepto voto secreto) | Hash de verificación por voto | ✅ | SHA-256 por voto en `votos.hash_verificacion` |
| 7.6 | L675 | En votación secreta, el resultado es válido pero no se revela quién votó qué | Modo `es_secreta = true` que oculta detalles | ✅ | Implementado |
| 7.7 | L675 | El acta aprobada tiene 30 días para objeción por copropietarios | Flujo de aprobación de acta | ❌ | No existe. Las actas son generadas automáticamente sin ciclo de aprobación. |
| 7.8 | L675 | El libro de actas debe estar disponible para consulta de copropietarios | Acceso a actas históricas | ❌ | No existe sección de historial de actas para copropietarios. |

---

## 8. Estado de mora y obligaciones

| # | Norma | Requerimiento | Feature en Asambli | Estado | Notas |
|---|-------|---------------|-------------------|--------|-------|
| 8.1 | L675 Art. 38 | El sistema debe saber si un copropietario tiene 3 o más cuotas en mora | Campo booleano `en_mora` en `copropietarios` | ✅ | Marcado manual por el admin (Asambli no tiene contabilidad; integración contable es roadmap largo plazo). |
| 8.2 | L675 Art. 38 | Un copropietario en mora puede asistir a la asamblea (tiene voz) pero no puede votar | Entra a la sala y cuenta asistencia; voto bloqueado | ✅ | El moroso accede a la sala normalmente; sus opciones de voto aparecen deshabilitadas con aviso Art. 38 y el backend rechaza el voto (propio y delegado). |
| 8.3 | L675 Art. 38 | Los copropietarios en mora no cuentan para el quórum de votación | Exclusión del cálculo de quórum votante | ❌ | `QuorumService` aún no discrimina por estado de mora (ver 3.4). Pendiente para próxima fase. |
| 8.4 | L675 Art. 38 | El paz y salvo de cuotas debe verificarse antes de la asamblea, no durante | Workflow de verificación previo a la reunión | ❌ | No existe. El admin puede actualizar `en_mora` en cualquier momento. |
| 8.5 | L675 | El administrador puede actualizar el estado de mora de cada copropietario | Toggle `en_mora` en panel admin + `restringir_voto_morosos` por tenant | ✅ | Editable en Copropietarios (admin) y visible en index/show con badge. La restricción se activa/desactiva por conjunto (admin y super-admin). |

---

## 9. Administración y roles

| # | Norma | Requerimiento | Feature en Asambli | Estado | Notas |
|---|-------|---------------|-------------------|--------|-------|
| 9.1 | L675 Art. 50 | El administrador es designado por la asamblea o el consejo | Registro del administrador con fecha de designación | 🟡 | `TenantAdministrador` existe pero sin fecha de designación ni respaldo de decisión. |
| 9.2 | L675 Art. 51 | El administrador no puede ser propietario de unidades en el mismo conjunto (salvo reglamento) | Validación de conflicto de interés | ❌ | No existe. |
| 9.3 | L675 | El consejo de administración existe en conjuntos con más de 30 unidades | Entidad Consejo de Administración | ❌ | No existe módulo de consejo. Out of scope inicial pero relevante para conjuntos grandes. |

---

## Resumen ejecutivo

### Críticos para validez legal (riesgo de nulidad de decisiones)

| Prioridad | Ítem | Artículo | Estado 2026-07-03 |
|-----------|------|---------|-------------------|
| 🔴 1 | Mayorías especiales (70% sobre total del conjunto) | L675 Art. 46 | ✅ Implementado (incl. bloqueo de apertura sin presencia mínima) |
| 🔴 2 | Restricción de voto y quórum para deudores en mora | L675 Art. 38 | 🟡 Voto bloqueado (propio y delegado) ✅ · exclusión del quórum pendiente (3.4/8.3) |
| 🔴 3 | Campo `en_mora` en copropietarios + UI para el admin | L675 Art. 38 | ✅ Implementado (toggle admin + badges + config por tenant) |

### Importantes para completitud del proceso (riesgo de impugnación)

| Prioridad | Ítem | Artículo | Estado 2026-07-03 |
|-----------|------|---------|-------------------|
| 🟠 4 | Orden del día en reunión y en acta PDF | L675 Art. 27, 46 | ❌ Pendiente |
| 🟠 5 | Firma electrónica de acta (presidente/secretario) | L675 Art. 46 | ❌ Pendiente |
| 🟠 6 | Snapshot de quórum por votación en acta | L675 Art. 46 | ✅ Implementado (+ log de eventos de asistencia con quórum resultante) |
| 🟠 7 | Validación de plazo de convocatoria (15 días hábiles) | L675 Art. 27 | ❌ Pendiente (los tipos ordinaria/extraordinaria ya existen) |
| 🟠 8 | Gate de funcionalidad por `tenant.producto` | L2190/2022 | ❌ Pendiente |

### Deseables para buenas prácticas (no invalidan pero fortalecen)

| Prioridad | Ítem | Artículo |
|-----------|------|---------|
| 🟡 9 | Segunda citación con quórum reducido | L675 Art. 29 |
| 🟡 10 | Etapas de conjunto | L675 Art. 56 |
| 🟡 11 | Restricción de apoderados (admin/consejo no pueden serlo) | L675 Art. 30 |
| 🟡 12 | URL de grabación en acta virtual | L2190/2022 |
| 🟡 13 | Historial de actas para copropietarios | L675 Art. 46 |
| 🟡 14 | Validación de suma de coeficientes = 100 | L675 Art. 26 |

---

## Notas legales adicionales

### Ley 2190 de 2022 — Reuniones virtuales
Permite expresamente las asambleas virtuales y mixtas. Los requisitos centrales son: identificación confiable del participante, posibilidad de deliberar en tiempo real, y que el acta deje constancia del medio tecnológico. No exige grabación pero sí recomendada como respaldo.

### Segunda citación (Art. 29)
Si la primera convocatoria no logra quórum, se puede hacer una segunda citación dentro de los 10 días hábiles siguientes. En segunda citación la asamblea sesiona válidamente con los asistentes presentes, **excepto** para las decisiones que requieren mayoría calificada, que mantienen el umbral del 70% sobre el total del conjunto.

### Mayoría calificada vs. quórum
Son conceptos distintos que el sistema debe manejar por separado:
- **Quórum para deliberar:** porcentaje mínimo de asistentes para que la asamblea sea válida
- **Mayoría para aprobar:** porcentaje de votos necesario para que una decisión sea aprobada
- La mayoría calificada (70%) se calcula sobre el **total del conjunto**, no sobre los presentes

### Alcance del módulo de deudores
La Ley 675 habla de "tres o más cuotas de administración en mora". Asambli no tiene módulo de contabilidad, por lo que el administrador debe poder marcar manualmente el estado de mora de cada copropietario. Una integración con software contable (Siigo, Alegra, etc.) es un roadmap de largo plazo.
