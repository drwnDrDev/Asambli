# Diagrama ER — ASAMBLI

```mermaid
erDiagram

    tenants {
        bigint id PK
        string nombre
        string nit UK
        string direccion
        string ciudad
        string logo_url
        tinyint max_poderes_por_delegado "DEFAULT 2"
        boolean activo "DEFAULT true"
        timestamp created_at
        timestamp updated_at
    }

    users {
        bigint id PK
        bigint tenant_id FK "nullable → tenants.id"
        string name
        string email UK
        string rol "super_admin | administrador | copropietario"
        timestamp email_verified_at
        string password
        string remember_token
        timestamp created_at
        timestamp updated_at
    }

    unidades {
        bigint id PK
        bigint tenant_id FK "→ tenants.id CASCADE"
        string numero "UNIQUE con tenant_id"
        string tipo "apartamento | local | parqueadero | otro"
        decimal coeficiente "8,5"
        string torre
        string piso
        boolean activo "DEFAULT true"
        timestamp created_at
        timestamp updated_at
    }

    copropietarios {
        bigint id PK
        bigint tenant_id FK "→ tenants.id CASCADE"
        bigint user_id FK "→ users.id CASCADE"
        bigint unidad_id FK "→ unidades.id CASCADE"
        boolean es_residente "DEFAULT true"
        string telefono
        boolean activo "DEFAULT true"
        timestamp created_at
        timestamp updated_at
    }

    reuniones {
        bigint id PK
        bigint tenant_id FK "→ tenants.id CASCADE"
        string titulo
        string tipo "asamblea | consejo | extraordinaria"
        string tipo_voto_peso "coeficiente | unidad"
        decimal quorum_requerido "5,2 DEFAULT 50.00"
        string estado "borrador | convocada | en_curso | finalizada"
        timestamp fecha_programada
        timestamp fecha_inicio
        timestamp fecha_fin
        timestamp convocatoria_enviada_at
        bigint creado_por FK "→ users.id"
        timestamp created_at
        timestamp updated_at
    }

    votaciones {
        bigint id PK
        bigint tenant_id FK "→ tenants.id CASCADE"
        bigint reunion_id FK "→ reuniones.id CASCADE"
        string titulo
        text descripcion
        string tipo "si_no | si_no_abstencion | opcion_multiple"
        boolean es_secreta "DEFAULT true"
        string estado "creada | abierta | cerrada | pausada"
        timestamp abierta_at
        timestamp cerrada_at
        bigint creada_por FK "→ users.id"
        timestamp created_at
        timestamp updated_at
    }

    opciones_votacion {
        bigint id PK
        bigint votacion_id FK "→ votaciones.id CASCADE"
        string texto
        tinyint orden "DEFAULT 0"
        timestamp created_at
        timestamp updated_at
    }

    votos {
        bigint id PK
        bigint tenant_id FK "→ tenants.id CASCADE"
        bigint votacion_id FK "→ votaciones.id CASCADE"
        bigint copropietario_id FK "→ copropietarios.id CASCADE"
        bigint en_nombre_de FK "nullable → copropietarios.id"
        bigint opcion_id FK "→ opciones_votacion.id"
        decimal peso "8,5 DEFAULT 1.00000"
        string ip_address
        string user_agent
        string hash_verificacion "SHA-256, 64 chars"
        timestamp created_at "sin updated_at — inmutable"
    }

    asistencias {
        bigint id PK
        bigint reunion_id FK "→ reuniones.id CASCADE"
        bigint copropietario_id FK "→ copropietarios.id CASCADE"
        boolean confirmada_por_admin "DEFAULT false"
        timestamp hora_confirmacion
        json vota_por_poderes "IDs de poderdantes"
        timestamp created_at
        timestamp updated_at
    }

    poderes {
        bigint id PK
        bigint tenant_id FK "→ tenants.id CASCADE"
        bigint reunion_id FK "→ reuniones.id CASCADE"
        bigint apoderado_id FK "→ copropietarios.id CASCADE"
        bigint poderdante_id FK "→ copropietarios.id CASCADE"
        string documento_url
        bigint registrado_por FK "→ users.id"
        timestamp created_at
        timestamp updated_at
    }

    reunion_logs {
        bigint id PK
        bigint reunion_id FK "→ reuniones.id CASCADE"
        bigint user_id FK "nullable → users.id"
        string accion
        json metadata
        timestamp created_at "sin updated_at — append-only"
    }

    magic_links {
        bigint id PK
        bigint user_id FK "→ users.id CASCADE"
        bigint reunion_id "sin FK constraint"
        string token UK "64 chars"
        timestamp expires_at
        timestamp used_at
        timestamp created_at
        timestamp updated_at
    }

    %% Relaciones

    tenants ||--o{ users : "tiene"
    tenants ||--o{ unidades : "tiene"
    tenants ||--o{ copropietarios : "tiene"
    tenants ||--o{ reuniones : "tiene"
    tenants ||--o{ votaciones : "tiene"
    tenants ||--o{ votos : "tiene"
    tenants ||--o{ poderes : "tiene"

    users ||--o{ copropietarios : "es"
    users ||--o{ magic_links : "tiene"
    users ||--o{ reunion_logs : "registra"
    users ||--o{ reuniones : "crea (creado_por)"
    users ||--o{ votaciones : "crea (creada_por)"
    users ||--o{ poderes : "registra"

    unidades ||--o{ copropietarios : "pertenece a"

    copropietarios ||--o{ asistencias : "asiste"
    copropietarios ||--o{ votos : "vota"
    copropietarios |o--o{ votos : "representado en (en_nombre_de)"
    copropietarios ||--o{ poderes : "es apoderado"
    copropietarios ||--o{ poderes : "es poderdante"

    reuniones ||--o{ votaciones : "contiene"
    reuniones ||--o{ asistencias : "registra"
    reuniones ||--o{ poderes : "aplica"
    reuniones ||--o{ reunion_logs : "tiene"

    votaciones ||--o{ opciones_votacion : "tiene"
    votaciones ||--o{ votos : "recibe"

    opciones_votacion ||--o{ votos : "elegida en"
```

## Notas

| Tabla | Restricciones especiales |
|-------|--------------------------|
| `unidades` | UNIQUE `(tenant_id, numero)` |
| `copropietarios` | UNIQUE `(tenant_id, user_id)` |
| `asistencias` | UNIQUE `(reunion_id, copropietario_id)` |
| `votos` | UNIQUE `(votacion_id, copropietario_id, en_nombre_de)` |
| `poderes` | UNIQUE `(reunion_id, poderdante_id)` |
| `votos` | Sin `updated_at` — votos inmutables |
| `reunion_logs` | Sin `updated_at` — log append-only |
| `users` | `tenant_id` nullable para `super_admin` |
| `tenants` | `max_poderes_por_delegado` aplica restricción de negocio en `Poder::booted()` |
