# Data Model: Diccionario de preguntas

No se crean tablas ni columnas en `competencias` ni en `competencia_grados` (001). Las relaciones son internas del
módulo `Estructura`: `Competencia 1—0..1 Registro 1—4 Pregunta`.

## Registro de preguntas (`competencia_pregunta_registros`) — cabecera

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `competencia_id` | FK a `competencias.id` | Obligatoria; **única** (una competencia, un solo registro); **sin borrado en cascada**; inmutable tras crear |
| `version` | entero | Empieza en 1; +1 en cada edición **con cambios reales**; base del control de edición simultánea |
| `created_by` | entero anulable | Id del usuario que lo creó (sin clave foránea; anulable para datos sembrados) |
| `updated_by` | entero anulable | Id del usuario de la última edición con cambios reales; nulo hasta la primera |
| `created_at`, `updated_at` | marcas de tiempo | `created_at` al crear; `updated_at` solo cambia con ediciones con cambios reales |

Sin columna de estado ni soft delete. **No existe borrado de registros.**

## Pregunta (`competencia_preguntas`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `registro_id` | FK a `competencia_pregunta_registros.id` | Obligatoria; **sin borrado en cascada** |
| `orden` | entero 1–4 | Número de la pregunta |
| `pregunta` | texto, máximo 500 | Obligatoria; se guarda recortada; no vacía; distinta (sin distinguir mayúsculas) de las otras tres del registro |
| `created_by` | entero anulable | Id del usuario que insertó la fila (se conserva al editar) |
| `updated_by` | entero anulable | Id del usuario que cambió el texto de la fila por última vez |
| `created_at`, `updated_at` | marcas de tiempo | Automáticas |

Restricción única en `(registro_id, orden)`: máximo 4 preguntas por registro y sin números repetidos. La competencia
se obtiene a través de la cabecera.

## Invariantes

- Si existe la cabecera de una competencia, tiene **exactamente 4 preguntas** con `orden` 1, 2, 3 y 4. Se garantiza
  escribiendo cabecera y filas en una sola transacción; la edición solo actualiza las 4 filas (nunca inserta ni
  elimina).
- La competencia asociada **debe estar `Activo`** al crear el registro; después puede pasar a `Inactivo` sin afectar
  al registro.
- El estado del registro no se guarda: es el `estado` actual de su competencia (join).

## Datos de la competencia (solo lectura, no almacenados)

`codigo`, `nombre`, `descripcion`, `tipo` y `estado` se leen siempre de `competencias`; este módulo nunca los crea,
edita, copia ni guarda versiones anteriores.

## Transiciones

- **Crear**: valida que la competencia exista, esté `Activo` y no tenga cabecera; inserta la cabecera (`version` 1,
  `created_by`) y las 4 filas (`orden` por posición, `created_by`); todo en una transacción. Una violación del índice
  único de `competencia_id` se informa como "la competencia ya tiene registro".
- **Editar**: compara los cuatro textos normalizados con los actuales.
  - Sin cambios → no se modifica nada (ni filas, ni cabecera, ni `version`, ni auditoría).
  - Con cambios y `version` coincidente → actualiza por `orden` las filas cuyo texto cambió (conserva `created_by`,
    fija `updated_by`), incrementa `version` y fija `updated_by` y `updated_at` de la cabecera.
  - `version` distinta → conflicto.
- **Cambio de estado de la competencia** (en 001): no escribe nada aquí; el registro lo refleja por el join.

## Reglas de visibilidad

- Administrador: lista `Activo` por defecto, o `Inactivo`, o `Todos`.
- Usuario: el listado solo contiene registros de competencias `Activo`, pase lo que pase en la petición.
- Cualquier persona autenticada puede consultar por `competencia_id` un registro de competencia activa o inactiva.
- Los demás módulos asignan solo preguntas de competencias `Activo` y conservan sus referencias a las de competencias
  `Inactivo`.

## Datos iniciales (`PreguntasSeeder`, idempotente por competencia; depende de `CompetenciasSeeder`)

Creados por el repositorio con `created_by` = id de `admin@hrms.test` (o nulo); cuatro preguntas cada uno:

| Competencia | Estado | Registro |
|---|---|---|
| COMP-0001 Orientación a resultados | Activo | 4 preguntas |
| COMP-0002 Trabajo en equipo | Activo | 4 preguntas |
| COMP-0004 Liderazgo | Activo | 4 preguntas |
| COMP-0006 Gestión de proyectos | Activo | 4 preguntas |
| COMP-0007 Adaptabilidad | **Inactivo** | 4 preguntas |
| COMP-0003 Comunicación efectiva | Activo | **sin registro** (disponible para crear) |
| COMP-0005 Pensamiento analítico | Activo | **sin registro** (disponible para crear) |

Los textos son preguntas de ejemplo, sin datos personales reales. Como en 002, para la competencia inactiva el
seeder la reactiva un instante (sin tocar `updated_at`) para poder crear el registro y la restaura.
