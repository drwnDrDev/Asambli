# Spec: Cumplimiento Legal Ley 675 — Core de Reuniones y Votaciones

**Fecha:** 2026-06-16  
**Estado:** Aprobado — listo para implementación  
**Referencia legal:** Ley 675 de 2001 (Régimen de Propiedad Horizontal), Ley 2190 de 2022  
**Compliance doc:** `docs/legal/compliance-ley675.md`

---

## Alcance de este sprint

**Incluido:**
- Reestructuración del tipo de reunión (tipo_cuerpo + tipo_convocatoria)
- Catálogo legal de tipos de decisión con mayorías automáticas
- Lógica de mayoría simple, calificada (70%) y unanimidad
- Restricción de voto para copropietarios en mora (Art. 38)
- UI actualizada en Conducir, Show/borrador, Sala del copropietario y Configuración

**Excluido explícitamente:**
- Reuniones de consejo de administración (sin enforcement de membresía)
- Modalidad virtual y mixta
- Módulo de segunda citación con quórum reducido
- Integración contable para cálculo automático de mora

---

## 1. Schema

### 1.1 Tabla `reuniones` — reestructurar `tipo`

**Eliminar:** columna `tipo` enum ['asamblea', 'consejo', 'extraordinaria']

**Agregar:**
```sql
tipo_cuerpo       ENUM('asamblea', 'consejo')          NOT NULL DEFAULT 'asamblea'
tipo_convocatoria ENUM('ordinaria', 'extraordinaria')  NOT NULL DEFAULT 'ordinaria'
```

**Migración de datos existentes:**

| Valor actual | tipo_cuerpo | tipo_convocatoria |
|---|---|---|
| `asamblea` | `asamblea` | `ordinaria` |
| `extraordinaria` | `asamblea` | `extraordinaria` |
| `consejo` | `consejo` | `ordinaria` |

### 1.2 Nueva tabla `tipos_decision`

Tabla global (sin `tenant_id`). Solo lectura para todos los tenants. Alimentada por seeder. No tiene soft delete.

```sql
CREATE TABLE tipos_decision (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo      VARCHAR(50)  NOT NULL UNIQUE,
    nombre      VARCHAR(150) NOT NULL,
    descripcion TEXT         NOT NULL,
    tipo_mayoria ENUM('simple', 'calificada_70', 'unanimidad') NOT NULL,
    aplica_en   JSON         NOT NULL,  -- ['asamblea'] | ['consejo'] | ['asamblea','consejo']
    orden       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL
);
```

**Catálogo completo (seeder):**

| orden | codigo | nombre | tipo_mayoria | aplica_en |
|-------|--------|--------|-------------|-----------|
| 1 | `presupuesto_anual` | Aprobación del presupuesto anual | simple | [asamblea] |
| 2 | `estados_financieros` | Aprobación de estados financieros | simple | [asamblea] |
| 3 | `eleccion_consejo` | Elección del consejo de administración | simple | [asamblea] |
| 4 | `eleccion_administrador` | Elección o ratificación del administrador | simple | [asamblea, consejo] |
| 5 | `cuota_administracion` | Aprobación de la cuota de administración | simple | [asamblea] |
| 6 | `decision_ordinaria` | Otra decisión ordinaria | simple | [asamblea, consejo] |
| 7 | `reforma_reglamento` | Reforma al reglamento de PH | calificada_70 | [asamblea] |
| 8 | `cambio_destinacion` | Cambio de destinación de bienes comunes | calificada_70 | [asamblea] |
| 9 | `desafectacion_bienes` | Desafectación de bienes comunes no esenciales | calificada_70 | [asamblea] |
| 10 | `gravamenes_bienes` | Constitución de gravámenes sobre bienes comunes | calificada_70 | [asamblea] |
| 11 | `reconstruccion_mejoras` | Obras de reconstrucción o mejoras no urgentes | calificada_70 | [asamblea] |
| 12 | `extincion_regimen` | Extinción voluntaria del régimen de PH | unanimidad | [asamblea] |

### 1.3 Tabla `votaciones` — cambios

**Agregar:**
```sql
tipo_decision_id  BIGINT UNSIGNED NULL  -- FK → tipos_decision
resultado         ENUM('pendiente', 'aprobada', 'rechazada') NOT NULL DEFAULT 'pendiente'
```

`tipo_decision_id` es nullable para compatibilidad con registros existentes. Obligatorio en la validación de Laravel para votaciones nuevas.

El campo `tipo` existente (si_no / si_no_abstencion / opcion_multiple) se mantiene — describe el *formato* de la pregunta, no la categoría legal.

### 1.4 Tabla `copropietarios` — agregar estado de mora

```sql
en_mora  BOOLEAN NOT NULL DEFAULT FALSE
```

### 1.5 Tabla `tenants` — agregar configuración de mora

```sql
restringir_voto_morosos  BOOLEAN NOT NULL DEFAULT TRUE
```

Default `true` — cumple Art. 38 out-of-the-box. Si el conjunto decide no aplicarlo, activa manualmente bajo su responsabilidad legal.

---

## 2. Modelo `TipoDecision`

Nuevo modelo de solo lectura. Sin `BelongsToTenant`. Cast de `aplica_en` como array.

```php
class TipoDecision extends Model
{
    protected $table = 'tipos_decision';
    protected $guarded = ['id'];
    protected $casts = ['aplica_en' => 'array'];

    // Retorna los tipos disponibles para un tipo_cuerpo dado
    public static function paraAsamblea(): Collection
    {
        return static::whereJsonContains('aplica_en', 'asamblea')->orderBy('orden')->get();
    }
}
```

Relación en `Votacion`:
```php
public function tipoDecision(): BelongsTo
{
    return $this->belongsTo(TipoDecision::class);
}
```

---

## 3. Lógica de servicios

### 3.1 `QuorumService` — sin cambios

El quórum de deliberación (Art. 29) cuenta a todos los presentes, incluyendo morosos. La ley solo les restringe el voto, no la presencia para validar el quórum de la asamblea. No hay cambios en este servicio.

### 3.2 `VotoService` — restricción de mora

Agregar check **antes** del check de quórum, como primer paso de la transacción:

```php
// 0. Verificar mora (Art. 38, Ley 675)
if ($copropietario->en_mora && $votacion->reunion->tenant->restringir_voto_morosos) {
    throw new \Exception('Tiene cuotas de administración en mora. No puede votar en esta asamblea (Art. 38, Ley 675 de 2001).');
}
```

La restricción aplica a todas las votaciones de la reunión, sin excepción por tipo de decisión.

### 3.3 `RecalcularResultadosVotacion` — lógica de mayorías

El Job determina el resultado según `tipo_mayoria` de la votación. **El denominador cambia según el tipo.**

#### Mayoría simple
```
Aprobada si: peso_votos_SI > peso_votos_NO
Denominador: votos_SI + votos_NO  (abstenciones excluidas del denominador)
```
Aplica sobre los votos emitidos por los presentes que pueden votar.

#### Mayoría calificada 70%
```
Aprobada si: peso_votos_SI >= 70% del total de coeficientes activos del conjunto
Denominador: SUM(unidades.coeficiente) WHERE activo = true AND tenant_id = ?
             (TODOS los coeficientes del edificio, presentes o no, morosos o no)
```
El denominador es el edificio completo, no los presentes. Un 100% de aprobación entre los asistentes no es suficiente si esos asistentes no suman el 70% del edificio.

#### Unanimidad
```
Aprobada si: peso_votos_SI = total de coeficientes activos del conjunto (100%)
Denominador: mismo que calificada_70
```

#### Resultado persistido al cerrar
Cuando `estado` cambia a `'cerrada'`, el Job calcula y escribe `resultado` ('aprobada' o 'rechazada'). Mientras está abierta, `resultado` permanece `'pendiente'` y el broadcast muestra la tendencia en tiempo real.

#### Payload del broadcast `ResultadosVotacionActualizados`

```json
{
  "tipo_mayoria": "calificada_70",
  "umbral_requerido": 70.0,
  "total_denominador": 100.0,
  "porcentaje_si": 45.2,
  "aprobada": false,
  "votos_si":       { "count": 12, "peso": 45.2 },
  "votos_no":       { "count": 3,  "peso": 8.1  },
  "abstenciones":   { "count": 1,  "peso": 2.0  }
}
```

---

## 4. UI

### 4.1 Crear / editar reunión (`Reuniones/Create.jsx`, `Reuniones/Edit.jsx`)

Reemplazar el selector `tipo` único por dos selectores en la misma fila:

```
¿Qué tipo de cuerpo sesiona?    ¿Cómo fue convocada?
[ Asamblea ▾ ]                  [ Ordinaria ▾ ]
```

Opciones:
- `tipo_cuerpo`: Asamblea / Consejo de administración
- `tipo_convocatoria`: Ordinaria / Extraordinaria

Consejo disponible como opción (permite registrar la reunión) pero sin enforcement adicional en este sprint.

### 4.2 Crear votación — componente compartido

**Aplica en dos pantallas:**
- `Reuniones/Show` — estado `borrador`: admin pre-crea votaciones estándar de la agenda
- `Conducir` — estado `en_curso`: admin crea votaciones adicionales en vivo

El componente de creación es el mismo en ambos contextos. En `Show/borrador`, las votaciones creadas quedan en estado `'creada'`; en `Conducir`, el admin puede abrirlas inmediatamente o dejarlas en `'creada'` para abrir más tarde.

**Flujo del formulario:**

**Paso 1 — Tipo de decisión** (nuevo, va antes del texto de la pregunta):

```
Tipo de decisión
────────────────────────────────────────────────────
  Mayoría simple  (más de la mitad de votos presentes)
    ○ Aprobación del presupuesto anual
    ○ Elección del consejo de administración
    ○ Aprobación de la cuota de administración
    ○ Otra decisión ordinaria
    ○ ...

  Mayoría calificada — 70% del total del edificio
    ○ Reforma al reglamento de PH
    ○ Cambio de destinación de bienes comunes
    ○ ...

  Unanimidad
    ○ Extinción voluntaria del régimen de PH
────────────────────────────────────────────────────
```

Al seleccionar una opción **calificada o unanimidad**, aparece aviso contextual:

> ⚠️ **Esta decisión requiere el 70% del total de coeficientes del conjunto**, no el 70% de los presentes. Si los asistentes suman menos del 70% del edificio, la votación no podrá aprobarse aunque todos voten a favor.

El tipo de mayoría no es editable por el admin — viene fijado por el tipo de decisión.

**Paso 2 — Pregunta y opciones** (igual al flujo actual).

### 4.3 Panel de resultados en `Conducir`

El card de cada votación muestra el tipo de mayoría y el umbral correcto.

**Mayoría simple:**
```
APROBACIÓN DE PRESUPUESTO  ·  Mayoría simple
A favor    ███████████░  62%  de los votos emitidos
En contra  ████░░░░░░░  38%
✓ APROBADA
```

**Mayoría calificada 70%:**
```
REFORMA AL REGLAMENTO  ·  Mayoría calificada — 70% del edificio
A favor    ██████░░░░░  48.3%  del total del conjunto
Requerido  ███████████  70.0%
✗ NO APROBADA — faltan 21.7 puntos de coeficiente
```

El texto del denominador cambia visualmente: "de los votos emitidos" vs. "del total del conjunto". Esto evita que el admin malinterprete un 80% de aprobación entre presentes como suficiente para una decisión calificada.

### 4.4 Sala del copropietario — restricción de mora

Si `en_mora = true` y `restringir_voto_morosos = true`, el panel de votación es visible pero los botones están deshabilitados:

```
┌──────────────────────────────────────────────────────┐
│  VOTACIÓN ACTIVA                                     │
│  ¿Aprueba la reforma al reglamento de PH?            │
│                                                      │
│  [ Sí ]   [ No ]   [ Abstención ]   ← deshabilitados│
│                                                      │
│  ⚠ Tiene cuotas de administración en mora.          │
│    No puede votar en esta asamblea.                  │
│    Art. 38, Ley 675 de 2001.                         │
└──────────────────────────────────────────────────────┘
```

El copropietario puede ver los resultados si la votación no es secreta. No puede votar en ninguna votación de esa reunión.

### 4.5 Perfil del copropietario (`Copropietarios/Edit`)

Nuevo toggle en la sección de estado del copropietario:

```
☑  En mora  (3 o más cuotas de administración sin pagar)
   Al activar, este copropietario no podrá votar en ninguna asamblea.
```

### 4.6 Configuración del tenant (`Configuracion`)

Nuevo toggle en la sección de configuración de asambleas:

```
☑  Restringir votación a copropietarios en mora
   Recomendado · Art. 38, Ley 675 de 2001
   Si desactiva esta opción, el conjunto asume la responsabilidad
   legal de permitir votar a propietarios con cuotas en mora.
```

---

## 5. Archivos a crear o modificar

### Nuevos
| Archivo | Tipo |
|---------|------|
| `app/Models/TipoDecision.php` | Modelo |
| `database/migrations/XXXX_add_legal_compliance_fields.php` | Migración |
| `database/seeders/TiposDecisionSeeder.php` | Seeder |

### Modificados
| Archivo | Cambio |
|---------|--------|
| `app/Models/Reunion.php` | Reemplazar `tipo` por `tipo_cuerpo` + `tipo_convocatoria` |
| `app/Models/Votacion.php` | Agregar relación `tipoDecision()`, campo `resultado` |
| `app/Models/Copropietario.php` | Agregar cast `en_mora` |
| `app/Models/Tenant.php` | Agregar cast `restringir_voto_morosos` |
| `app/Services/VotoService.php` | Agregar check de mora (paso 0) |
| `app/Jobs/RecalcularResultadosVotacion.php` | Lógica de mayorías por tipo_mayoria |
| `app/Http/Controllers/Admin/ReunionController.php` | Validación tipo_cuerpo + tipo_convocatoria |
| `app/Http/Controllers/Admin/VotacionController.php` | Validación tipo_decision_id |
| `app/Http/Controllers/Admin/CopropietarioController.php` | Validación en_mora |
| `app/Http/Controllers/Admin/TenantSettingsController.php` | Validación restringir_voto_morosos |
| `resources/js/Pages/Admin/Reuniones/Create.jsx` | Dos selectores de tipo |
| `resources/js/Pages/Admin/Reuniones/Edit.jsx` | Dos selectores de tipo |
| `resources/js/Pages/Admin/Reuniones/Show.jsx` | Formulario crear votación con tipo_decision |
| `resources/js/Pages/Admin/Reuniones/Conducir.jsx` | Crear votación + panel resultados actualizado |
| `resources/js/Pages/Admin/Copropietarios/Edit.jsx` | Toggle en_mora |
| `resources/js/Pages/Admin/Configuracion.jsx` | Toggle restringir_voto_morosos |
| `resources/js/Pages/Copropietario/Sala/Show.jsx` | Panel de mora (componente `VotacionCard`) |
| `database/seeders/DatabaseSeeder.php` | Llamar TiposDecisionSeeder |

### Tests a crear o actualizar
| Archivo | Cobertura |
|---------|-----------|
| `tests/Feature/VotoServiceMoraTest.php` | Bloqueo de voto con mora activa/inactiva, con config activa/inactiva |
| `tests/Feature/RecalcularResultadosMayoriasTest.php` | Simple, calificada_70, unanimidad — aprobada y rechazada |
| `tests/Feature/TiposDecisionTest.php` | Seeder correcto, FK en votaciones |

---

## 6. Decisiones de diseño registradas

| Decisión | Alternativa descartada | Razón |
|----------|----------------------|-------|
| Catálogo en tabla DB (seeded) | Enum PHP / constantes | Las descripciones en español quedan en DB (editable sin deploy); extensible cuando la ley evolucione |
| `tipo_mayoria` fijado por tipo de decisión, no editable por admin | Admin elige libremente la mayoría | La ley no da opción; elimina riesgo de error humano en decisiones de alto impacto |
| `QuorumService` sin cambios para morosos | Excluir morosos del quórum | Art. 38 solo restringe el voto, no la presencia para validar quórum de deliberación |
| Denominador `calificada_70` = total del edificio, no solo presentes | Usar votos emitidos como denominador | Así lo establece la Ley 675; el 70% es sobre el universo total de coeficientes |
| Mora configurable por tenant (default true) | Mora forzada sin escape | Asambli no puede ser el juez de legalidad del conjunto; se activa con advertencia explícita |
| Componente crear-votación compartido entre Show/borrador y Conducir | Componentes separados | Permite pre-cargar la agenda estándar antes de la reunión sin duplicar lógica |

---

## 7. Out of scope — próximos sprints

- **Reuniones de consejo:** requiere módulo de membresía de consejeros. El campo `tipo_cuerpo = 'consejo'` ya existe en el schema para cuando se implemente.
- **Modalidad virtual / mixta:** requiere validaciones adicionales de Ley 2190/2022 (plataforma, identidad, acta especial).
- **Segunda citación:** quórum reducido cuando la primera citación no logra el mínimo.
- **Etapas de conjunto:** Art. 56, relevante para conjuntos grandes.
- **Orden del día:** campo en reunión, incluido en convocatoria y acta PDF.
- **Firma electrónica del acta:** presidente y secretario de la asamblea.
