# Data Model: Actividades formativas

Tablas nuevas en SQLite (módulo `Desarrollo`). Convenciones del módulo: sin `cascade`, sin soft deletes, referencias lógicas sin FK
entre módulos, `created_by`/`updated_by` como ids de usuario. **No se modifica ninguna tabla existente.**

## actividades

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, índice | tomado del plan de la acción al crear; sin FK |
| numero | unsignedInteger | secuencial por tenant; único con `tenant_id` |
| codigo | string(12) | `ACT-0001`; único con `tenant_id`; no editable |
| plan_accion_id | foreignId → `plan_acciones`, `restrictOnDelete`, **único** | una acción tiene como máximo una actividad |
| lugar_enlace | string(500) | obligatorio; URL para Virtual/E-learning, texto libre en los demás |
| departamento_id | unsignedBigInteger nullable, índice | referencia lógica a una unidad de Estructura |
| version | unsignedInteger default 1 | control optimista del registro de asistencia (R4) |
| created_by, updated_by | unsignedBigInteger | auditoría (FR-032) |
| created_at, updated_at | timestamps | |

Índices: `unique(tenant_id, numero)`, `unique(tenant_id, codigo)`, `unique(plan_accion_id)`, `index(tenant_id, departamento_id)`.

No se guardan: nombre, tipo, fechas, formador, capacidad, competencias, inscritos, plan, estado ni «Asistencia pendiente» (se leen
o calculan del plan vigente, R1).

## actividad_asistencias

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| actividad_id | foreignId → `actividades`, `restrictOnDelete` | |
| colaborador_id | unsignedBigInteger, índice | referencia lógica a Gestión de Personal |
| asistencia | string(12) | `Presente` \| `Ausente` \| `Justificado` (Pendiente = sin fila) |
| calificacion | unsignedTinyInteger nullable | 1–5; solo si `asistencia = Presente` |
| observaciones | text nullable | interno; solo con asistencia registrada; máx. 1 000 caracteres |
| registrado_by | unsignedBigInteger | último usuario que cambió la fila |
| created_at, updated_at | timestamps | |

Restricciones: `unique(actividad_id, colaborador_id)`. La regla «calificación solo con Presente» y el rango 1–5 se validan en la
aplicación (`ValidadorDeRegistrosDeAsistencia`) y se refuerzan con `CHECK` en la migración cuando el motor lo admite.

## actividad_asistencia_historial

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| actividad_id | foreignId → `actividades`, `restrictOnDelete`, índice | |
| colaborador_id | unsignedBigInteger | |
| asistencia_anterior, asistencia_nueva | string(12) nullable | `null` = Pendiente / sin registro |
| calificacion_anterior, calificacion_nueva | unsignedTinyInteger nullable | |
| user_id | unsignedBigInteger | quien hizo el cambio |
| created_at | timestamp | solo inserción: no se actualiza ni borra |

Índice: `index(actividad_id, created_at)`.

## Entidades y objetos de valor de dominio

- **Actividad** (lectura combinada): `id`, `codigo`, `accionId`, `lugarEnlace`, `departamentoId`, `version`, auditoría. El
  estado y la marca de asistencia pendiente los calcula `ReglasDeActividad` con los datos de la acción.
- **AsistenciaParticipante** (enum): `Pendiente`, `Presente`, `Ausente`, `Justificado`.
- **EstadoAccion** (existente, reutilizado): `Programada`, `EnCurso`, `Finalizada`, `Cancelada`.
- **CodigoActividad**: `desde(int $numero): string` → `ACT-0001`.
- **FiltroActividades** / **FiltroCalificaciones**: filtros inmutables (tipo, estado, departamento, competencia, plan, fechas, búsqueda,
  agrupación, colaborador).

## Reglas de validación

| Regla | Origen | Dónde se aplica |
|---|---|---|
| Código único ACT-NNNN secuencial no editable | FR-001 | caso de uso + índice único |
| Una acción, una actividad | FR-002 | índice único + validación previa (422) |
| Solo acciones no canceladas de planes Ejecutando | FR-003 | `CrearActividad` |
| Lugar o enlace obligatorio; URL para Virtual/E-learning | FR-004 | `ReglasDeActividad` |
| Departamento existente y activo (opcional) | FR-005 | `DepartamentosInterface` |
| Editar solo lugar/enlace y departamento, plan ≠ Cerrado | FR-010/FR-011 | `ActualizarActividad` |
| Registrar solo con actividad En curso o Finalizada y plan ≠ Cerrado | FR-014 | `ReglasDeActividad::puedeRegistrar` |
| Solo inscritos actuales; sin repetidos | FR-012 | `ValidadorDeRegistrosDeAsistencia` |
| Calificación entera 1–5, solo con Presente | FR-015 | `ValidadorDeRegistrosDeAsistencia` |
| Pendiente elimina calificación y observaciones | FR-016/FR-017 | `RegistrarAsistencia` |
| Observaciones solo con asistencia registrada | FR-017 (aclaración 1) | `ValidadorDeRegistrosDeAsistencia` |
| Guardado atómico + historial por cambio de asistencia o calificación | FR-019/FR-033 | `RegistrarAsistencia` (transacción) |
| Control de versión | caso límite de concurrencia | `RegistrarAsistencia` (409) |
| No quitar de la acción a quien tiene asistencia | FR-035 | `CambiarParticipantes` vía `AsistenciaRegistradaInterface` |

## Transiciones y estado derivado

El estado de la actividad **no es un campo**: `calcular(cancelada, inicio, fin, hoy)` del `CalculadorDeEstadoDeAccion` existente
(Cancelada > Programada > En curso > Finalizada). La marca «Asistencia pendiente» = Finalizada ∧ (inscritos actuales − registros de
inscritos actuales) > 0. Las actividades Canceladas conservan sus registros y no aceptan nuevos.

```text
fila de asistencia:   (sin fila = Pendiente) ──Presente/Ausente/Justificado──▶ fila
                                   ▲                                           │
                                   └────────────── Pendiente (borra fila) ◀────┘
Presente ──cambia a Ausente/Justificado/Pendiente──▶ calificacion = null (+ historial)
```

## Relaciones con otros módulos (lógicas)

- `actividades.plan_accion_id` → `plan_acciones.id` (misma BD, FK con `restrict`; el acceso lo hace el puerto).
- `actividades.departamento_id` → unidad de Estructura (sin FK).
- `actividad_asistencias.colaborador_id` / `actividad_asistencia_historial.colaborador_id` → colaborador de Gestión de Personal (sin FK).
- `user_id`/`created_by`/`updated_by`/`registrado_by` → usuarios de Shared; los nombres se resuelven con `NombresDeUsuarioInterface`.

## Datos de prueba

`ActividadesSeeder`: ver R13 de [research.md](research.md). 6 actividades, un colaborador de prueba con asistencias en las cuatro
situaciones, calificaciones de 1 a 5 y al menos una actividad Finalizada con asistencia pendiente.
