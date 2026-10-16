# Modelo de datos: Detección de necesidades formativas (DNF)

Referencias a otros módulos (`tenant_id`, `colaborador_id`, `puesto_id`, `competencia_id`, `departamento_id`, `plan_formacion_id`, usuarios) son **lógicas, sin FK** entre módulos. Las FK internas son sin `ON DELETE CASCADE`.

## Tabla `dnf`

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| tenant_id | bigint | Obligatorio; índice |
| codigo | string(12) | `DNF-NNNN`; único con `tenant_id`; inmutable (FR-003) |
| alcance | string | `Individual` \| `Departamento` |
| departamento_id | bigint null | Obligatorio si `Departamento`; null si `Individual` (FR-004) |
| competencias_omitidas | unsigned int | Default 0; competencias inactivas omitidas en toda la DNF |
| plan_formacion_id | bigint null | Único (cuando no es null); lo completa `GenerarPlanDesdeDnf` |
| plan_generado_por | bigint null | Usuario que generó el borrador (FR-030) |
| plan_generado_en | timestamp null | |
| created_by | bigint | Usuario que generó la DNF |
| created_at, updated_at | timestamps | `created_at` = fecha de generación |

Índices: `(tenant_id, codigo)` único; `(tenant_id, created_at)`; `plan_formacion_id` único.

## Tabla `dnf_colaboradores`

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| dnf_id | FK → dnf | Sin cascada |
| colaborador_id | bigint | Referencia lógica a Gestión de Personal |
| puesto_id | bigint | Copia del momento |
| timestamps | | |

Único `(dnf_id, colaborador_id)`. Índice `colaborador_id` (brecha activa y filtro del historial).

## Tabla `dnf_detalles`

| Columna | Tipo | Reglas |
|---|---|---|
| id | bigint PK | |
| dnf_colaborador_id | FK → dnf_colaboradores | Sin cascada |
| competencia_id | bigint | Referencia lógica a Estructura |
| tipo_competencia | string | `Cardinal` \| `Especifica` (copia del momento) |
| nivel_requerido | char(1) | `A`–`D` |
| nivel_actual | char(1) null | null ⇒ `SinEvaluacion` |
| diferencia | smallint null | `actual − requerido` en grados; null si Sin evaluación |
| brecha_porcentaje | decimal(5,2) null | ≥ 0; 0 si no hay brecha; null si Sin evaluación |
| prioridad | string null | `Critica` \| `Alta` \| `Media` \| `Baja`; solo si `ConBrecha` |
| estado | string | `ConBrecha` \| `Cumple` \| `Supera` \| `SinEvaluacion` |

Único `(dnf_colaborador_id, competencia_id)`.

### Invariantes (validadas en el dominio y reforzadas por pruebas)

1. `estado = SinEvaluacion` ⇔ `nivel_actual`, `diferencia`, `brecha_porcentaje`, `prioridad` son null.
2. `prioridad` no null ⇔ `estado = ConBrecha`.
3. `estado = ConBrecha` ⇔ `diferencia < 0` ⇒ `brecha_porcentaje > 0`; `Cumple` ⇒ `diferencia = 0`; `Supera` ⇒ `diferencia > 0`; en ambos `brecha_porcentaje = 0`.
4. `prioridad`: `diferencia = −3` Crítica, `−2` Alta, `−1` Media si Cardinal / Baja si Especifica.
5. Una DNF tiene ≥ 1 `dnf_colaboradores`. `Individual` tiene exactamente 1 y `departamento_id` null; `Departamento` exige `departamento_id`.
6. Ninguna fila de las tres tablas se modifica ni elimina tras crearse; única excepción: `dnf.plan_formacion_id`, `plan_generado_por`, `plan_generado_en` se completan **una sola vez**.

## Valores derivados (no se guardan)

- **Cantidad de brechas** de una DNF/colaborador = detalles con `estado = ConBrecha`.
- **Prioridad más alta** = máxima por orden Crítica > Alta > Media > Baja; «—» si no hay brechas.
- **Sin evaluación** = detalles con `estado = SinEvaluacion`.
- **Brecha activa** de un colaborador = detalles `ConBrecha` de su DNF más reciente (R-06).

## Transiciones de estado

Una DNF no tiene estados. Su único cambio posible es `sin plan → con plan` (irreversible).

## Entidades de dominio (Desarrollo/Domain)

- `Dnf` (raíz): código, alcance, departamento, omitidas, plan, autor; contiene `ResultadoColaborador[]`.
- `ResultadoColaborador`: colaborador, puesto, `Brecha[]`.
- `Brecha`: competencia, tipo, nivel requerido/actual, diferencia, porcentaje, prioridad, estado.
- Value objects: `NivelGrado` (existente), `PrioridadBrecha` (con orden), `EstadoBrecha`, `AlcanceDnf`, `TipoCompetencia`.

## Seeder `DnfSeeder`

Inserta directamente en las tres tablas (no depende de los seeders de GP, que aún no existen; ver R-01) dos DNF del tenant de demostración:
1. **Individual** con competencias que cubren las cuatro prioridades, al menos un `Cumple`, un `Supera` y un `SinEvaluacion`.
2. **Departamento** con ≥ 3 colaboradores, mezcla de brechas, un colaborador sin brechas y un `SinEvaluacion`.
Los `competencia_id` se toman de las competencias sembradas por Estructura; los `colaborador_id`, de las fichas sembradas por GP si existen, o ids fijos documentados si no. Debe ser idempotente.
