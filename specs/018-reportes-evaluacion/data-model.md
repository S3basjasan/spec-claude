# Data Model: Reportes de evaluación de resultados

Tres tablas nuevas, todas con `tenant_id` y referencias **lógicas** (sin FK) a Actividades, Plan de Formación y Gestión de Personal. Las métricas, los pendientes y las vistas consolidadas **no se persisten**.

## Tablas

### `encuestas_satisfaccion`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint, PK | |
| `tenant_id` | unsignedBigInteger | índice |
| `actividad_id` | unsignedBigInteger | referencia lógica a `actividades` |
| `colaborador_id` | unsignedBigInteger | referencia lógica a `colaboradores` |
| `calificacion` | unsignedTinyInteger | 1–5 |
| `comentario` | text, nullable | opcional; máximo 1 000 caracteres (validación; límite decidido por el sistema) |
| `created_at` | timestamp | fecha de registro; **sin** `updated_at` |

Restricción única: `(actividad_id, colaborador_id)`. Índice por `(tenant_id, actividad_id)`. Inmutable: el repositorio no ofrece actualizar ni eliminar.

### `seguimientos_impacto`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint, PK | |
| `tenant_id` | unsignedBigInteger | índice |
| `actividad_id` | unsignedBigInteger | referencia lógica |
| `colaborador_id` | unsignedBigInteger | referencia lógica |
| `hito` | unsignedSmallInteger | 30, 60 o 90 |
| `aplicacion` | unsignedTinyInteger | 1–5 |
| `apoyo` | unsignedTinyInteger | 1–5 |
| `comentario` | text, nullable | máximo 1 000 caracteres |
| `created_at` | timestamp | sin `updated_at` |

Restricción única: `(actividad_id, colaborador_id, hito)`. Índice por `(tenant_id, actividad_id)`. Inmutable.

### `impactos_accion`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint, PK | |
| `tenant_id` | unsignedBigInteger | índice |
| `plan_accion_id` | unsignedBigInteger, **único** | referencia lógica a `plan_acciones` |
| `indicador` | string(150) | indicador de negocio |
| `unidad` | string(50) | |
| `valor_antes` | decimal(14,4) | |
| `valor_despues` | decimal(14,4) | |
| `beneficio_estimado` | decimal(12,2) | ≥ 0, USD |
| `justificacion` | text | obligatoria; máximo 2 000 caracteres |
| `created_by` | unsignedBigInteger | usuario que la creó |
| `updated_by` | unsignedBigInteger | usuario de la última modificación (= `created_by` al crear) |
| `created_at`, `updated_at` | timestamps | |

Sin soft deletes ni DELETE. «Registrar impacto» hace *upsert* por `plan_accion_id` en una transacción.

## Datos derivados (no persistidos)

| Concepto | Regla |
|---|---|
| **Período** | `[fecha_desde, fecha_hasta]`; por defecto el año actual. Período anterior = igual duración inmediatamente previa. |
| **Pertenencia al período** | Acción o actividad: `fecha_fin`. Micro-contenido: fecha de finalización. Brecha cerrada: fecha de la DNF que la muestra cerrada. Brecha detectada: fecha de la primera DNF `ConBrecha`. |
| **Horas-persona de actividades** | `horas_de_la_acción × participantes con asistencia Presente` (del departamento filtrado). |
| **Horas de micro-learning** | Suma de `duracion_minutos / 60` de las completaciones del período de colaboradores del departamento filtrado. |
| **% Plan cumplido** | `acciones Finalizadas / acciones no canceladas × 100` de planes Ejecutando o Cerrado. |
| **Costo prorrateado** | `costo × asistentes(dep) / asistentes(total)`; igual para el beneficio estimado. |
| **Brecha** | Por (colaborador, competencia): se abre con `ConBrecha`, se cierra con `Cumple`/`Supera` en una DNF posterior; `SinEvaluacion` se ignora. |
| **Hito pendiente** | Colaborador con asistencia Presente en actividad Finalizada, sin respuesta, con `hoy ∈ [fecha_fin + h, fecha_fin + h + 15 días]`. |
| **Encuesta pendiente** | Colaborador con asistencia Presente en actividad Finalizada, sin respuesta, con `hoy ≤ fecha_fin + 30 días`. Sin fecha de activación: aplica a toda actividad Finalizada. |
| **Brechas cerradas / detectadas del período** | Cerradas por fecha de cierre; detectadas por fecha de primera aparición. Las cerradas pueden superar a las detectadas; se muestran por separado. |
| **Departamento actual** | Asignación laboral vigente; sin asignación vigente el colaborador es «Sin departamento» (gráfico) y queda fuera al filtrar por un departamento. |
| **Tasa de respuesta (hito)** | `respuestas del hito / colaboradores elegibles de la actividad` (elegibles = Presentes). |
| **Alerta de reingreso** | Algún par 30→60 o 60→90 con ≥ 3 respuestas en ambos y caída del promedio de aplicación ≥ 1. |
| **ROI de la acción** | `(beneficio_estimado − costo) / costo × 100`; nulo si el costo es 0. |
| **ROI estimado** | `(Σ beneficio − Σ costo) / Σ costo × 100` sobre las acciones con evaluación; nulo si no hay o el costo total es 0. |

## Relaciones lógicas

```text
actividades (017) ──< encuestas_satisfaccion >── colaboradores (GP)
actividades (017) ──< seguimientos_impacto  >── colaboradores (GP)
plan_acciones (013) ──1:0..1── impactos_accion
```

Las referencias se validan con los puertos del módulo (actividad existente y Finalizada, asistencia Presente, acción existente y Finalizada), nunca con FK.

## Transiciones y reglas de validación

- **Encuesta**: crear solo si la actividad está Finalizada, el colaborador estuvo Presente, está dentro de la ventana de 30 días y no existe una para ese par; calificación entera 1–5. No hay transición posterior.
- **Seguimiento**: crear solo si el hito es 30, 60 o 90, ya venció, no pasó su ventana de 15 días, el colaborador estuvo Presente en una actividad Finalizada y no hay respuesta para ese hito; `aplicacion` y `apoyo` enteros 1–5. No hay transición posterior.
- **Impacto**: crear o reemplazar solo si la acción existe y está Finalizada (aunque el plan esté Cerrado); `beneficio_estimado ≥ 0`; todos los campos obligatorios; `valor_antes` y `valor_despues` numéricos.

## Seeder

`ReportesSeeder` (registrado en `DatabaseSeeder` después de `ActividadesSeeder`, `MicroLearningSeeder` y `DnfSeeder`) crea, sobre las actividades Finalizadas del seeder de Actividades, con distintos departamentos y tipos: encuestas de satisfacción dentro de la ventana de 30 días; seguimientos de los tres hitos, incluido un caso de alerta de reingreso (≥ 3 respuestas por hito con caída ≥ 1 punto); y evaluaciones de impacto en algunas acciones, una de costo cero. Requiere que `DnfSeeder` incluya al menos un colaborador evaluado en dos DNF con una brecha cerrada; si no lo trae, el seeder de Reportes lo completa escribiendo la segunda DNF por repositorio. No depende de `IngresosSeeder`.
