# Data Model: Diccionario de comportamientos

No se crean tablas ni columnas en `competencias` ni en `competencia_grados` (001). Las relaciones son internas del
módulo `Estructura`: `Competencia 1—4 CompetenciaGrado 1—(1..4) Comportamiento`, y `Competencia 1—0..1 Registro`.

## Registro de comportamientos (`competencia_comportamiento_registros`) — cabecera

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `competencia_id` | FK a `competencias.id` | Obligatoria; **única** (una competencia, un solo registro); **sin borrado en cascada**; inmutable tras crear |
| `version` | entero | Empieza en 1; +1 en cada edición **con cambios reales**; base del control de edición simultánea |
| `created_by` | entero anulable | Id del usuario que lo creó (sin clave foránea; anulable para datos sembrados) |
| `updated_by` | entero anulable | Id del usuario de la última edición con cambios reales; nulo hasta la primera |
| `created_at`, `updated_at` | marcas de tiempo | `created_at` al crear; `updated_at` solo cambia con ediciones con cambios reales |

Sin columna de estado ni soft delete. **No existe borrado de registros.**

## Comportamiento (`competencia_comportamientos`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `competencia_grado_id` | FK a `competencia_grados.id` | Obligatoria; **sin borrado en cascada** |
| `orden` | entero 1–4 | Posición dentro del grado |
| `descripcion` | texto, máximo 300 | Obligatoria; se guarda recortada; no vacía; distinta (sin distinguir mayúsculas) de las demás del mismo grado |
| `created_by` | entero anulable | Id del usuario que insertó la fila (se conserva al editar) |
| `updated_by` | entero anulable | Id del usuario que cambió el texto de la fila por última vez |
| `created_at`, `updated_at` | marcas de tiempo | Automáticas |

Restricción única en `(competencia_grado_id, orden)`: máximo 4 comportamientos por grado y sin posiciones
repetidas. El grado (A–D) y la competencia se obtienen a través de `competencia_grados`.

## Invariantes

- Si existe la fila de cabecera de una competencia, **cada uno de sus cuatro grados tiene entre 1 y 4
  comportamientos** (con `orden` consecutivo desde 1). Se garantiza escribiendo cabecera y filas en una sola
  transacción.
- La competencia asociada **debe estar `Activo`** al crear el registro; después puede pasar a `Inactivo` sin
  afectar al registro.
- El estado del registro no se guarda: es el `estado` actual de su competencia (join).

## Datos de la competencia (solo lectura, no almacenados)

`codigo`, `nombre`, `descripcion`, `tipo` y `estado` se leen siempre de `competencias`; este módulo nunca los
crea, edita, copia ni guarda versiones anteriores.

## Transiciones

- **Crear**: valida que la competencia exista, esté `Activo` y no tenga cabecera; inserta la cabecera
  (`version` 1, `created_by`) y las filas (`orden` por posición, `created_by`); todo en una transacción. Una
  violación del índice único de `competencia_id` se informa como "la competencia ya tiene registro".
- **Editar**: compara los comportamientos normalizados con los actuales.
  - Sin cambios → no se modifica nada (ni filas, ni cabecera, ni `version`, ni auditoría).
  - Con cambios y `version` coincidente → por grado actualiza las filas existentes por `orden` (conserva
    `created_by`, fija `updated_by` si el texto cambia), inserta las nuevas y elimina las sobrantes;
    incrementa `version` y fija `updated_by` y `updated_at` de la cabecera.
  - `version` distinta → conflicto.
- **Cambio de estado de la competencia** (en 001): no escribe nada aquí; el registro lo refleja por el join.

## Reglas de visibilidad

- Administrador: lista `Activo` por defecto, o `Inactivo`, o `Todos`.
- Usuario: el listado solo contiene registros de competencias `Activo`, pase lo que pase en la petición.
- Cualquier persona autenticada puede consultar por `competencia_id` un registro de competencia activa o inactiva.
- Los demás módulos asignan solo comportamientos de competencias `Activo` y conservan sus referencias a las
  de competencias `Inactivo`.

## Datos iniciales (`ComportamientosSeeder`, idempotente por competencia; depende de `CompetenciasSeeder`)

Creados por el repositorio con `created_by` = id de `admin@hrms.test` (o nulo):

| Competencia | Estado | Comportamientos por grado (A/B/C/D) |
|---|---|---|
| COMP-0001 Orientación a resultados | Activo | 4 / 3 / 2 / 1 |
| COMP-0002 Trabajo en equipo | Activo | 2 / 2 / 2 / 2 |
| COMP-0004 Liderazgo | Activo | 1 / 4 / 3 / 2 |
| COMP-0006 Gestión de proyectos | Activo | 3 / 1 / 4 / 2 |
| COMP-0007 Adaptabilidad | **Inactivo** | 2 / 3 / 1 / 4 |
| COMP-0003 Comunicación efectiva | Activo | **sin registro** (disponible para crear) |
| COMP-0005 Pensamiento analítico | Activo | **sin registro** (disponible para crear) |

Los textos son conductas de ejemplo, sin datos personales reales.
