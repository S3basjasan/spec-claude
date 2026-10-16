# Data Model: Plan de formación anual

Módulo `Desarrollo`. Las referencias a otros módulos (usuarios, colaboradores, competencias, DNF) son **lógicas, sin clave foránea**. Entre tablas del módulo se usan FK **sin cascade delete**.

## Tablas

### `planes`
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint, índice | referencia lógica |
| numero | unsigned int | secuencial por tenant; único `(tenant_id, numero)` |
| codigo | string(12) | `PLAN-0001`, derivado de `numero` |
| nombre | string(120) | tal como lo escribió la persona (sin espacios laterales) |
| nombre_normalizado | string(120) | minúsculas, espacios colapsados; único `(tenant_id, anio, nombre_normalizado)` |
| anio | unsigned smallint | obligatorio |
| descripcion | text nullable | |
| estado | string(12) | `Borrador` (default) \| `Revision` \| `Aprobado` \| `Ejecutando` \| `Cerrado`; índice `(tenant_id, anio, estado)` |
| aprobador_id | bigint nullable | usuario con rol Administrador |
| origen_dnf_id | bigint nullable | DNF de origen |
| fecha_aprobacion | timestamp nullable | la fija el sistema al aprobar |
| created_by, updated_by | bigint | auditoría (FR-026) |
| created_at, updated_at | timestamps | |

### `plan_acciones`
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| plan_id | FK → planes | sin cascade |
| curso_id | FK nullable → cursos | mismo módulo; referencia de punto de partida |
| nombre | string(120) | obligatorio |
| tipo | string(12) nullable | `Presencial` \| `Virtual` \| `ELearning` \| `OJT` \| `Codesarrollo` \| `Mentoring` |
| fecha_inicio, fecha_fin | date nullable | dentro de `planes.anio` |
| duracion_horas | decimal(6,2) nullable | > 0 |
| formador | string(120) nullable | |
| capacidad_maxima | unsigned int nullable | > 0 |
| costo | decimal(12,2) nullable | ≥ 0, USD |
| cancelada | boolean default false | |
| motivo_cancelacion | string(500) nullable | obligatorio si `cancelada` |
| created_by, updated_by, timestamps | | |

Índices: `(plan_id)`, `(fecha_inicio, fecha_fin)`.

### `plan_accion_competencias`
`id`, `plan_accion_id` (FK, sin cascade), `competencia_id` (referencia lógica). Único `(plan_accion_id, competencia_id)`.

### `plan_accion_participantes`
`id`, `plan_accion_id` (FK, sin cascade), `colaborador_id` (referencia lógica), `created_by`, timestamps. Único `(plan_accion_id, colaborador_id)`. Índice `(colaborador_id)` para "Mi plan" y cruces.

### `plan_historial` (solo se agrega)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| plan_id | FK → planes | |
| plan_accion_id | FK nullable → plan_acciones | |
| tipo | string(12) | `Estado` \| `Participante` \| `Cancelacion` \| `Aprobador` |
| valor_anterior, valor_nuevo | string(120) nullable | estado, o `colaborador_id`/nombre, o `aprobador_id` |
| motivo | string(500) nullable | obligatorio al devolver a Borrador y al cancelar |
| user_id | bigint | quien actuó |
| created_at | timestamp | |

Sin `updated_at`: nunca se modifica.

## Reglas de validación

- **Plan**: nombre y año obligatorios; nombre único por `(tenant, año)` normalizado; aprobador (si se da) debe ser Administrador activo del tenant.
- **Acción en Borrador**: solo `nombre` y ≥ 1 competencia obligatorios; todas las competencias deben existir y estar **activas** al asociarlas por primera vez (las ya asociadas que luego se desactivan se conservan y se muestran).
- **Fechas**: `fecha_fin ≥ fecha_inicio` y ambas dentro de `planes.anio` (cuando están presentes).
- **Participantes**: sin repetir en la acción; cantidad ≤ `capacidad_maxima` cuando hay capacidad definida; solo colaboradores con vínculo vigente al **agregarlos** (los ya inscritos que pierden el vínculo se conservan).
- **Completitud para enviar a revisión** (`ReglasDePlan::faltantes`): ≥ 1 acción; todas con tipo, fechas, horas, formador, capacidad, costo (acciones **canceladas** no existen aún en Borrador/Revisión); ≥ 1 participante por acción; aprobador designado.

## Transiciones del plan

| De → A | Quién | Condición | Historial |
|---|---|---|---|
| Borrador → Revisión | Administrador | plan completo | Estado |
| Revisión → Aprobado | solo aprobador designado | — ; fija `fecha_aprobacion` | Estado |
| Revisión → Borrador | solo aprobador designado | motivo obligatorio | Estado + motivo |
| Aprobado → Ejecutando | Administrador | — | Estado |
| Ejecutando → Cerrado | Administrador | todas las acciones Finalizadas o Canceladas | Estado |

Cualquier otra transición → 422. Revisión permite además `reasignarAprobador` (historial `Aprobador`).

## Estado calculado de la acción

`cancelada` → **Cancelada**; sin fechas o `hoy < inicio` → **Programada**; `inicio ≤ hoy ≤ fin` → **En curso**; `hoy > fin` → **Finalizada**. Nunca se guarda.

## Totales

- Costo total del plan = suma de `costo` de las acciones no canceladas.
- Horas totales = suma de `duracion_horas` de las acciones no canceladas.
- Cantidad de acciones en la tabla de planes = acciones no canceladas.

## Eliminación

Sin `DELETE` HTTP, sin soft deletes y sin borrado físico de planes. En un plan **Borrador**, `PUT` puede quitar acciones; sus competencias y participantes se borran explícitamente dentro de la transacción (sin cascade). Desde Revisión las acciones solo se cancelan.

## Seeder (`PlanFormacionSeeder`)

5 planes (uno por estado) con acciones de los seis tipos, al menos una cancelada y acciones calculadas Programada, En curso y Finalizada; el Borrador incluye acciones incompletas como las que crea la DNF. Depende de los seeders de Estructura, Gestión de Personal y cursos; colaboradores sin vínculo vigente se omiten.

## Cruces de fechas

Calculados al leer (no se guardan): por colaborador, intervalos de acciones no canceladas del tenant que se solapan (`inicio_a ≤ fin_b y inicio_b ≤ fin_a`).
