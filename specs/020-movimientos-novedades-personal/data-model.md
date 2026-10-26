# Modelo de datos: Registro de movimientos y novedades de personal

**Feature**: 020-movimientos-novedades-personal

Motor: SQLite (como GP-02 y 016). Los índices parciales y los triggers van en SQL crudo; los triggers se crean solo con `DB::getDriverName() === 'sqlite'`. Toda tabla lleva `tenant_id` y toda consulta lo filtra. Ninguna migración modifica datos ni semántica de GP-01…GP-07: solo se **agrega** una columna nullable a `asignaciones_laborales`.

Migraciones nuevas (`backend/database/migrations/`):

| Archivo | Contenido |
|---|---|
| `2026_10_24_000001_create_actuaciones_personal_table.php` | tabla común, índices y triggers de coherencia |
| `2026_10_24_000002_create_movimientos_novedades_detalle_tables.php` | `movimientos_personal`, `novedades_personal`, `actuacion_documentos` |
| `2026_10_24_000003_create_actuacion_transiciones_table.php` | registro append-only y triggers |
| `2026_10_24_000004_add_actuacion_id_to_asignaciones_laborales.php` | columna nullable + índice único parcial |

`down()` de cada una elimina solo lo que creó; la columna agregada se retira con `ALTER TABLE … DROP COLUMN` (SQLite ≥ 3.35), tras eliminar su índice. Es reversible sin tocar filas históricas porque la columna es nula en todas ellas.

## `actuaciones_personal` (estado actual y campos comunes)

| Campo | Tipo | Reglas |
|---|---|---|
| id | integer PK | |
| tenant_id | integer NOT NULL | siempre del contexto autenticado |
| colaborador_id | FK `colaboradores` ON DELETE RESTRICT | la ficha de la persona afectada |
| vinculo_id | FK `vinculos_laborales` ON DELETE RESTRICT, NOT NULL | vínculo vigente al presentar |
| tipo | varchar(12) NOT NULL | `Ascenso`, `Traslado`, `Permiso`, `Licencia`, `Vacaciones`, `Ausencia` (CHECK) |
| categoria | varchar(10) NOT NULL | `Movimiento` \| `Novedad` (CHECK coherente con `tipo`) |
| estado | varchar(24) NOT NULL | ver máquina de estados (CHECK) |
| motivo | varchar(1000) NULL | justificación; **obligatoria en movimientos** (trigger), condicional en novedades según política (la valida el dominio) |
| presentada_por | integer NOT NULL | cuenta que presenta o registra |
| en_nombre_de_colaborador | boolean NOT NULL | verdadero si la cuenta que registra no es la de la ficha afectada |
| presentada_en | timestamp NOT NULL | instante UTC de presentación |
| fecha_presentacion | date NOT NULL | día en la zona horaria del tenant (base de la anticipación) |
| politica_version_id | FK `politica_versiones` ON DELETE RESTRICT, NOT NULL | impide eliminación en cascada |
| politica_numero | integer NOT NULL | número de la versión conservada |
| politica_condiciones | text NOT NULL | JSON sellado copiado de la versión (copia de los parámetros) |
| politica_hash | char(64) NOT NULL | hash de la versión |
| version | integer NOT NULL default 1 | control optimista |
| operacion_id | char(36) NOT NULL | clave de presentación |
| operacion_hash | char(64) NOT NULL | hash del contenido presentado |
| created_by, updated_by | integer NOT NULL | |
| created_at, updated_at | timestamp | |

Índices: `UNIQUE (tenant_id, operacion_id)`; `INDEX (tenant_id, estado, tipo, presentada_en DESC)` para el listado y el resumen; `INDEX (tenant_id, colaborador_id, presentada_en DESC)` para «mis solicitudes» y la detección de cruces; **`UNIQUE (colaborador_id) WHERE categoria = 'Movimiento' AND estado IN ('Aprobada','Programada','IncidenciaDeAplicacion')`** (a lo sumo un movimiento aprobado pendiente por persona: protege contra dos movimientos incompatibles concurrentes).

Triggers (SQLite): `politica_*`, `tipo`, `categoria`, `colaborador_id`, `tenant_id`, `presentada_*`, `fecha_presentacion`, `operacion_*` son **inmutables** (`BEFORE UPDATE` aborta si cambian); `DELETE` está prohibido; un movimiento exige `motivo` no vacío.

### Máquina de estados

| Categoría | Estados | Transiciones válidas |
|---|---|---|
| Novedad (Permiso, Licencia, Vacaciones) | `Presentada`, `EnRevision`, `Aprobada`, `Rechazada`, `Retirada` | Presentada→EnRevision; Presentada\|EnRevision→Aprobada\|Rechazada\|Retirada |
| Ausencia | `Informada`, `Revisada`, `Justificada` | Informada→Revisada\|Justificada |
| Movimiento | `Presentada`, `EnRevision`, `Rechazada`, `Aprobada`, `Programada`, `Aplicada`, `IncidenciaDeAplicacion` | Presentada→EnRevision; Presentada\|EnRevision→Rechazada; Presentada\|EnRevision→Aprobada (fecha efectiva ≤ hoy) \| Programada (futura); Aprobada\|Programada→Aplicada \| IncidenciaDeAplicacion; IncidenciaDeAplicacion→Aplicada (reintento) |

Estados terminales: `Rechazada`, `Retirada`, `Aplicada`, `Revisada`, `Justificada`. No hay transición de salida desde ellos ni eliminación.

## `movimientos_personal` (detalle 1:1 de Ascenso/Traslado)

| Campo | Tipo | Reglas |
|---|---|---|
| actuacion_id | PK, FK `actuaciones_personal` RESTRICT | |
| tenant_id | integer NOT NULL | |
| asignacion_origen_id | FK `asignaciones_laborales` RESTRICT NOT NULL | asignación vigente al presentar |
| posicion_origen_id | integer NOT NULL | posición de esa asignación (sin FK: la valida Estructura) |
| posicion_destino_id | integer NOT NULL | posición destino (sin FK); distinta de la de origen |
| fecha_efectiva | date NOT NULL | propuesta; jamás se sustituye por la actual |
| evaluacion_estado | varchar(14) NULL | `Valida` \| `NoDisponible` \| `NoVerificable` en la última consulta |
| evaluacion_contexto | text NULL | JSON verificable devuelto por el evaluador (colaborador, puesto, requisitos, momento, resultado); nunca un porcentaje inventado |
| evaluacion_consultada_en | timestamp NULL | |
| aprobada_por / aprobada_en | integer / timestamp NULL | derivable de la transición; se copian para consulta |
| aplicacion_estado | varchar(10) NOT NULL default `Pendiente` | `Pendiente` \| `Aplicada` \| `Incidencia` |
| aplicada_en | timestamp NULL | |
| aplicada_por | integer NULL | cuenta del Administrador en un reintento manual; nulo en la aplicación del sistema |
| aplicada_por_sistema | boolean NOT NULL default 0 | identidad de sistema |
| asignacion_nueva_id | FK `asignaciones_laborales` RESTRICT NULL | solo cuando `Aplicada` |
| incidencia_codigo | varchar(40) NULL | uno de: `DESTINO_NO_ASIGNABLE`, `DESTINO_OCUPADO`, `VINCULO_NO_VIGENTE`, `ORIGEN_CAMBIO`, `ESTRUCTURA_NO_VERIFICABLE`, `INCONSISTENCIA` |
| incidencia_detalle | varchar(300) NULL | texto seguro, sin identificadores de otros tenants |
| intentos_aplicacion | integer NOT NULL default 0 | |

Índices: `INDEX (tenant_id, aplicacion_estado, fecha_efectiva)` para el procesador; `INDEX (posicion_destino_id)`.

Trigger: `aplicacion_estado = 'Aplicada'` ⇔ `asignacion_nueva_id` y `aplicada_en` no nulos; `Incidencia` ⇔ `incidencia_codigo` no nulo.

## `novedades_personal` (detalle 1:1 de Permiso/Licencia/Vacaciones/Ausencia)

| Campo | Tipo | Reglas |
|---|---|---|
| actuacion_id | PK, FK RESTRICT | |
| tenant_id | integer NOT NULL | |
| fecha_inicio | date NOT NULL | primer día (inclusivo) |
| fecha_fin | date NOT NULL | último día (inclusivo); `CHECK (fecha_fin >= fecha_inicio)` |
| ocurrida | boolean NOT NULL | verdadero solo para Ausencia (hecho informado) |
| cruce_reconocido_por | integer NULL | quién reconoció el cruce al aprobar |
| cruce_reconocido_en | timestamp NULL | |
| revision_observacion | varchar(500) NULL | de la revisión/justificación de una ausencia |

Índice: `INDEX (tenant_id, fecha_inicio, fecha_fin)`.

## `actuacion_documentos` (respaldos referenciados)

`actuacion_id` FK RESTRICT, `documento_id` FK `documentos_colaborador` RESTRICT, `tenant_id`, `created_at`; `PRIMARY KEY (actuacion_id, documento_id)`; `INDEX (documento_id)`. Solo referencias: el contenido y los permisos pertenecen a la gestión documental (feature 014). No se guarda ni se registra el título ni el contenido en trazas.

## `actuacion_transiciones` (registro append-only: antecedentes administrativos)

| Campo | Tipo | Reglas |
|---|---|---|
| id | PK | |
| tenant_id | integer NOT NULL | |
| actuacion_id | FK RESTRICT | |
| evento | varchar(30) NOT NULL | `Presentada`, `PasoARevision`, `Aprobada`, `Rechazada`, `Retirada`, `Programada`, `Aplicada`, `IncidenciaDeAplicacion`, `ReintentoDeAplicacion`, `Revisada`, `Justificada` |
| estado_anterior / estado_nuevo | varchar(24) | `estado_anterior` nulo solo en `Presentada` |
| actor_tipo | varchar(8) NOT NULL | `usuario` \| `sistema` |
| actor_id | integer NULL | nulo si `sistema` |
| justificacion | varchar(1000) NULL | obligatoria en `Aprobada`, `Rechazada`, `Retirada` (motivo breve) y `IncidenciaDeAplicacion` |
| detalle | text NULL | JSON seguro: cruces reconocidos, resultado de evaluación, código de incidencia |
| operacion_id | char(36) NULL | clave de la operación (nula para el sistema) |
| operacion_hash | char(64) NULL | |
| created_at | timestamp NOT NULL | |

Índices: `INDEX (actuacion_id, id)`; `UNIQUE (tenant_id, operacion_id) WHERE operacion_id IS NOT NULL`. Triggers: `BEFORE UPDATE` y `BEFORE DELETE` abortan (append-only).

## Cambio en `asignaciones_laborales` (hecho laboral derivado)

`actuacion_id INTEGER NULL REFERENCES actuaciones_personal (id) ON DELETE RESTRICT`; `CREATE UNIQUE INDEX asignaciones_una_por_actuacion ON asignaciones_laborales (actuacion_id) WHERE actuacion_id IS NOT NULL`. Una actuación genera a lo sumo una asignación: garantía física de que reintentos y concurrencia no duplican el hecho. Las filas existentes quedan con `NULL`.

## Relaciones

```text
colaboradores 1─* vinculos_laborales 1─* asignaciones_laborales *─0..1 actuaciones_personal
actuaciones_personal 1─0..1 movimientos_personal (asignacion_origen_id, asignacion_nueva_id → asignaciones_laborales)
actuaciones_personal 1─0..1 novedades_personal
actuaciones_personal 1─* actuacion_documentos *─1 documentos_colaborador
actuaciones_personal 1─* actuacion_transiciones
actuaciones_personal *─1 politica_versiones
```

## Reglas de validación del dominio (resumen; el detalle está en `contracts/api-movimientos.md`)

- Tipos y estados solo del catálogo; el tenant no los amplía.
- Novedades Permiso/Licencia/Vacaciones: `fecha_inicio ≥ hoy + anticipacion_dias` (hoy en la zona del tenant, instante de presentación), nunca pasada; `fecha_fin ≥ fecha_inicio`; ambas en `[1900-01-01, hoy + 10 años]`; justificación y documento según la política conservada.
- Ausencia: `fecha_fin ≤ hoy`; sin anticipación; justificación/documento según la política.
- Movimiento: `fecha_efectiva ≥ hoy + anticipacion_dias`; justificación siempre; documento adicional si la política lo exige; destino asignable según Estructura y distinto de la posición actual; la asignación de origen es la vigente de un vínculo vigente.
- Documentos: existen en el tenant, pertenecen a la ficha afectada, están `Disponible` y son accesibles para quien presenta; uno no accesible no cuenta como respaldo.
- Resolución: estado resoluble, `version` actual, política conservada (no se sustituye), condiciones actuales (vínculo, destino) y, en movimientos, evaluación `Valida` verificada por `VerificadorDeEvaluacion`.
- Cruce: en novedades de ausencia no aplica; en las demás, aprobar con cruces exige reconocimiento.
