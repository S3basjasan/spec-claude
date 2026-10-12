# Data Model: Diccionario de competencias

## Competencia (`competencias`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `codigo` | texto | Único, formato `COMP-` + `id` con al menos 4 dígitos (`COMP-0001`); anulable solo mientras se crea la fila (se asigna en la misma transacción); nunca se modifica |
| `nombre` | texto, máximo 50 | Obligatorio; se guarda recortado; se muestra tal como se escribió |
| `nombre_clave` | texto, máximo 50 | Nombre recortado y en minúsculas (Unicode); índice único: nombre único entre activas e inactivas sin distinguir mayúsculas ni espacios alrededor |
| `descripcion` | texto, máximo 500 | Obligatoria; no puede ser solo espacios |
| `tipo` | `Cardinal` \| `Especifica` | Obligatorio |
| `estado` | `Activo` \| `Inactivo` | Obligatorio; por defecto `Activo`; solo cambia con `PATCH /{id}/estado` |
| `version` | entero | Empieza en 1; +1 en cada `PUT`; **no** cambia al cambiar de estado |
| `created_by` | entero anulable | Id del usuario que la creó (sin clave foránea; anulable para datos sembrados) |
| `updated_by` | entero anulable | Id del usuario que hizo la última edición (`PUT`); nulo hasta la primera edición |
| `created_at`, `updated_at` | marcas de tiempo | Automáticas |

Relaciones: una Competencia tiene exactamente cuatro Grados y cero o más registros de historial de estado.
**No existe borrado** de competencias, y las claves foráneas hijas no tienen cascada.

## Grado de desarrollo (`competencia_grados`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `competencia_id` | FK a `competencias.id` | Obligatoria; **sin borrado en cascada** |
| `grado` | `A` \| `B` \| `C` \| `D` | Obligatorio |
| `descripcion` | texto, máximo 500 | Obligatoria; no puede ser solo espacios |

Restricción única en `(competencia_id, grado)`: un solo grado de cada letra por competencia. Invariante: toda
competencia tiene siempre sus cuatro grados (se crean y se reemplazan juntos en una transacción).

## Historial de estado (`competencia_estado_historial`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `competencia_id` | FK a `competencias.id` | Obligatoria; sin borrado en cascada |
| `estado_anterior` | `Activo` \| `Inactivo` | Obligatorio |
| `estado_nuevo` | `Activo` \| `Inactivo` | Obligatorio; distinto de `estado_anterior` |
| `user_id` | entero anulable | Id del usuario que hizo el cambio (sin clave foránea; anulable para datos sembrados) |
| `created_at` | marca de tiempo | Fecha del cambio; **sin `updated_at`** |

De solo inserción: la aplicación no ofrece edición ni borrado de sus registros. Un cambio de estado crea un
registro; el alta de la competencia no.

## Transiciones

- **Crear**: inserta competencia (`estado` `Activo`, `version` 1, `created_by`) y 4 grados; asigna `codigo`;
  todo en una transacción. No escribe historial.
- **Actualizar**: exige `version` actual; reemplaza nombre, descripción, tipo y los 4 grados; incrementa
  `version`; guarda `updated_by`; `codigo` y `estado` intactos. Si la versión no coincide → conflicto.
- **Cambiar estado**: si el estado pedido es distinto del actual, actualiza `estado` y, en la misma
  transacción, inserta el registro de historial; `version` y `updated_by` no cambian. Si ya es el estado
  pedido → rechazo sin escritura. Admite `Activo → Inactivo` e `Inactivo → Activo`.

## Reglas de visibilidad

- Administrador: lista `Activo` por defecto, o `Inactivo`, o `Todos`.
- Usuario: el listado solo contiene `Activo`, pase lo que pase en la petición.
- Cualquier persona autenticada puede consultar por id una competencia activa o inactiva.
- Los demás módulos asignan solo competencias `Activo` y conservan sus referencias a las `Inactivo`.

## Datos iniciales (`CompetenciasSeeder`, idempotente por nombre)

Siete competencias con cuatro grados cada una, creadas por el repositorio (código `COMP-0001`…`COMP-0007`):

| Nombre | Tipo | Estado |
|---|---|---|
| Orientación a resultados | Cardinal | Activo |
| Trabajo en equipo | Cardinal | Activo |
| Comunicación efectiva | Cardinal | Activo |
| Liderazgo | Cardinal | Activo |
| Pensamiento analítico | Especifica | Activo |
| Gestión de proyectos | Especifica | Activo |
| Adaptabilidad | Cardinal | Inactivo (con su registro de historial) |

`created_by` y `user_id` toman el id del administrador de prueba (`admin@hrms.test`) si existe, o `null`.
Sin datos personales reales.
