# Data Model: Catálogo maestro de puestos

Las relaciones son internas del módulo `Estructura`: `Puesto 1—N Función`, `Puesto 1—N Requisito`,
`Puesto 1—N Competencia asignada N—1 Competencia (001)` y `Puesto 1—N Posición (005, solo lectura)`.
No se crean tablas ni columnas en `competencias` ni en `competencia_grados`. Los campos, longitudes y restricciones
siguen el insumo `plan-puestos.md`; las diferencias están marcadas con **(plan)** y justificadas en
[research.md](research.md) (D-02, D-03).

## Puesto (`puestos`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `codigo` | texto, máx. 20 | **Único**; lo genera el sistema como `PU-` + `id` con al menos 3 dígitos (`PU-014`, `PU-1000`); se asigna en la misma transacción que la inserción (anulable solo hasta ese momento); inmutable; nunca se reutiliza |
| `nombre` | texto, máx. 50 | Obligatorio, recortado; no vacío |
| `nombre_clave` **(plan)** | texto, máx. 50 | **Único**; nombre en minúsculas (Unicode) y recortado; impone el nombre único entre activos e inactivos |
| `nombre_busqueda` **(plan)** | texto, máx. 50 | Nombre sin tildes y en minúsculas; con índice; solo para buscar |
| `descripcion` | texto, máx. 200 | Obligatoria, recortada; síntesis que se muestra en el listado |
| `mision` | texto, máx. 200 | Obligatoria, recortada |
| `estado` | texto | `Activo` \| `Inactivo`; por defecto `Activo`; con índice |
| `version` **(plan)** | entero | Empieza en 1; +1 en cada edición **con cambios reales**; no cambia al activar o desactivar |
| `created_by` | entero anulable | Id del usuario que lo creó (sin clave foránea; anulable para datos sembrados) |
| `updated_by` | entero anulable | Id del usuario de la última edición con cambios; nulo hasta la primera |
| `created_at`, `updated_at` | marcas de tiempo | `updated_at` solo cambia con ediciones con cambios; no con el cambio de estado |

Sin soft delete. **No existe borrado de puestos.** Todas las claves foráneas hacia `puestos` son `restrictOnDelete`.

## Función (`puesto_funciones`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria (cambia cuando se reemplazan las filas del puesto) |
| `puesto_id` | FK a `puestos.id` | Obligatoria; sin borrado en cascada |
| `descripcion` | texto, máx. 500 | Obligatoria, recortada; distinta (sin distinguir mayúsculas ni espacios alrededor) de las otras funciones del puesto |
| `tipo` | texto | `Esencial` \| `Complementaria` |
| `orden` | entero ≥ 1 | Posición en la lista, desde 1 y sin saltos; lo define el usuario |

Restricción única en `(puesto_id, orden)`. Sin `created_by`, `updated_by` ni marcas de tiempo: la trazabilidad es la del
puesto y la de `auditoria_cambios`.

## Requisito (`puesto_requisitos`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `puesto_id` | FK a `puestos.id` | Obligatoria; sin borrado en cascada |
| `tipo` | texto | `Formacion` \| `Experiencia` \| `Capacitacion` (sin tildes en base de datos; la interfaz muestra con tilde) |
| `descripcion` | texto, máx. 500 | Obligatoria, recortada; distinta de los otros requisitos **del mismo tipo** del puesto (sin distinguir mayúsculas ni espacios alrededor) |

Sin columna de orden: se devuelven por tipo (Formación, Experiencia, Capacitación) y luego por `id`.

## Competencia asignada (`puesto_competencias`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `puesto_id` | FK a `puestos.id` | Obligatoria; sin borrado en cascada |
| `competencia_id` | FK a `competencias.id` | Obligatoria; sin borrado en cascada |
| `grado_requerido` | texto, 1 carácter | `A`, `B`, `C` o `D`; debe existir para esa competencia en `competencia_grados` (se valida en la aplicación; no hay clave foránea compuesta) |

Restricción única en `(puesto_id, competencia_id)`. Los datos de la competencia (código, nombre, tipo y estado) **no se
almacenan**: se leen de `competencias` en cada consulta.

## Posición (`posiciones`) — mínima, reservada para 005 **(plan)**

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `puesto_id` | FK a `puestos.id` | Obligatoria; sin borrado en cascada |
| `estado` | texto | `Ocupada` \| `Vacante` \| `Suprimida`; con índice |
| `created_at`, `updated_at` | marcas de tiempo | Automáticas |

Esta feature **solo lee** la tabla: cuenta las posiciones `Ocupada` y `Vacante` de un puesto para decidir si se puede
desactivar. La feature 005 es su dueña y la amplía con migraciones propias; no debe recrearla.

## Registro de cambios (`auditoria_cambios`)

Historial de solo inserción (sin `updated_at`, sin borrado en cascada ni acciones de modificación o borrado en la
aplicación).

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `entidad` | texto | `puesto` para esta feature (otras features usarán otros valores) |
| `entidad_id` | entero | Id del puesto; con índice junto con `entidad` |
| `campo` | texto | `codigo`, `nombre`, `descripcion`, `mision`, `estado`, `funciones`, `requisitos` o `competencias` |
| `valor_anterior` | texto anulable | Nulo en una alta o al crear |
| `valor_nuevo` | texto anulable | Nulo en una baja |
| `user_id` | entero anulable | Quien hizo el cambio (sin clave foránea) |
| `created_at` | marca de tiempo | Fecha del cambio |

### Qué se registra y cómo

| Situación | Filas escritas |
|---|---|
| Crear un puesto | `codigo`, `nombre`, `descripcion`, `mision` y `estado` (`Activo`), y una fila por cada función, requisito y competencia, todas con valor anterior nulo |
| Cambiar nombre, descripción o misión | Una fila por campo cambiado, con el valor anterior y el nuevo |
| Activar o desactivar | Una fila `estado` (`Activo` ↔ `Inactivo`) solo si el estado cambió realmente |
| Agregar una función, requisito o competencia | Fila con valor anterior nulo y el elemento en `valor_nuevo` |
| Quitar una función, requisito o competencia | Fila con el elemento en `valor_anterior` y valor nuevo nulo |
| Cambiar el tipo de una función | Una fila `funciones`: anterior `Esencial: …`, nuevo `Complementaria: …` |
| Cambiar el texto de una función o de un requisito | Dos filas: la baja del texto anterior y el alta del nuevo |
| Cambiar el grado de una competencia (activa) | Una fila `competencias`: anterior `«código» «nombre»: grado B`, nuevo `…: grado A` |
| Solo reordenar funciones, o cambios de numeración derivados | **Ninguna** (el orden no se audita) |
| Guardar sin cambios reales | **Ninguna** (tampoco cambian `version`, `updated_by` ni `updated_at`) |

Formato de los valores de lista: función y requisito, `«Tipo»: «descripción»` (con el tipo en su forma de pantalla:
"Formación", "Capacitación"); competencia, `«código» «nombre»: grado «X»`.

## Invariantes

- Todo puesto tiene al menos una función (con al menos una `Esencial`), al menos un requisito de `Formacion` y uno de
  `Experiencia`, y al menos una competencia; cada lista tiene como máximo 20 elementos. Se garantiza escribiendo el
  puesto, sus filas hijas y el historial en **una sola transacción**.
- `orden` de las funciones es 1, 2, …, n sin saltos ni repeticiones.
- Una competencia aparece como máximo una vez por puesto.
- Al **agregar** una competencia, debe estar `Activo`. Después puede pasar a `Inactivo` sin afectar al puesto: la
  asignación se conserva con su grado, que ya no puede cambiarse (solo mantenerse o quitarse la competencia).
- El nombre es único entre todos los puestos (activos e inactivos) según `nombre_clave`.
- Un puesto con posiciones `Ocupada` o `Vacante` no puede pasar a `Inactivo`; las `Suprimida` no lo impiden. Un puesto
  `Inactivo` conserva sus posiciones y sigue siendo consultable y editable.
- Los puestos no tienen versionado: editar un puesto cambia lo que ven todas sus posiciones.

## Transiciones de estado

| De → A | Condición | Efecto |
|---|---|---|
| (nuevo) → `Activo` | Siempre | Código asignado; historial de creación |
| `Activo` → `Inactivo` | Sin posiciones `Ocupada` ni `Vacante` (comprobado en la misma sentencia que el cambio) | Fila `estado` en el historial; no cambia `version` |
| `Inactivo` → `Activo` | Sin restricciones | Fila `estado` en el historial; no cambia `version` |
| Mismo estado → mismo estado | — | 200 sin cambios y sin historial |

## Datos de la competencia (solo lectura, no almacenados)

`codigo`, `nombre`, `tipo` (`Cardinal` o `Especifica`) y `estado` se leen siempre de `competencias`, y los grados
válidos de `competencia_grados`; este módulo nunca los crea, edita ni copia. Si cambia el nombre de una competencia, el
puesto lo muestra al instante; el historial conserva el texto del nombre que tenía al registrarse el cambio.

## Datos sembrados (`PuestosSeeder`)

Depende de `CompetenciasSeeder` (siete competencias; `COMP-0007` Adaptabilidad inactiva) y escribe por Eloquent. Los
códigos los asigna el `id` en orden de inserción.

| Puesto | Estado | Particularidad |
|---|---|---|
| Analista de datos | Activo | Sin posiciones (se puede desactivar) |
| Jefe de talento humano | Activo | Posiciones Ocupadas y Vacantes (la desactivación se rechaza con la cantidad) |
| Desarrollador de software | Activo | Solo posiciones Suprimidas (se puede desactivar) |
| Ejecutivo de gestión comercial | Activo | Con una competencia inactiva asignada (`COMP-0007`); único nombre sembrado con tilde, para probar la búsqueda sin tildes |
| Asistente contable | Inactivo | Con posiciones Suprimidas |
| Coordinador de operaciones | Inactivo | Sin posiciones; sirve para probar la reactivación |

Cada puesto lleva descripción, misión, funciones Esenciales y Complementarias, requisitos de Formación y Experiencia
(algunos de Capacitación) y competencias activas con grados válidos, salvo la indicada. Los usuarios son los de 000:
`admin@hrms.test` y `usuario@hrms.test`.
