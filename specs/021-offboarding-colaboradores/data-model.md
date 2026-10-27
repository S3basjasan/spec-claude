# Modelo de datos: Offboarding de colaboradores

Migraciones nuevas (`backend/database/migrations/`): `2026_10_26_000001_create_offboarding_expedientes_table.php`, `…000002_create_offboarding_tareas_entrevistas_tables.php`, `…000003_create_offboarding_eventos_table.php`. Raw SQL y triggers solo con driver sqlite, como 020. Ninguna toca datos ni semántica de GP-01…GP-07, 016 ni 020.

## `offboarding_expedientes`

| Campo | Reglas |
|---|---|
| id, tenant_id | tenant siempre del contexto autenticado |
| colaborador_id | FK `colaboradores` RESTRICT |
| vinculo_id | FK `vinculos_laborales` RESTRICT |
| motivo | `Renuncia`, `Terminacion`, `FinContrato`, `Jubilacion` (CHECK) |
| fecha_efectiva | DATE, día del tenant |
| estado | `Programada`, `Efectiva`, `Cancelada`, `IncidenciaDeAplicacion` (CHECK) |
| cierre_estado | `Pendiente`, `Cerrado`; `Cerrado` exige `estado = 'Efectiva'` (CHECK) |
| cierre_por / cierre_en | |
| cuenta_estado | `Pendiente`, `Coordinada`, `Incidencia` |
| politica_version_id (FK RESTRICT), politica_numero, politica_condiciones (JSON sellado), politica_hash | inmutables |
| formalizado_por, formalizado_en | inmutables |
| cancelacion_por / cancelacion_en / cancelacion_razon | |
| aplicada_en, aplicada_por, aplicada_por_sistema | |
| incidencia_codigo, incidencia_detalle, intentos_aplicacion | |
| version, operacion_id, operacion_hash, created_by, updated_by, timestamps | |

Índices: `UNIQUE (tenant_id, operacion_id)`; `UNIQUE (vinculo_id) WHERE estado <> 'Cancelada'`; `(tenant_id, estado, fecha_efectiva)`; `(tenant_id, colaborador_id)`. Triggers: columnas selladas inmutables y sin DELETE.

## `offboarding_tareas`
`id, tenant_id, expediente_id (RESTRICT), orden, tipo ('Plantilla'|'Entrevista'), nombre, descripcion, exige_evidencia, estado ('Pendiente'|'Completada'), completada_por, completada_en`. `UNIQUE (expediente_id, orden)`. Nombre, descripción, orden, tipo y evidencia inmutables (trigger). Sin DELETE.

`offboarding_documentos (expediente_id, tarea_id NULL, documento_id FK RESTRICT, tenant_id)`: respaldos del expediente (tarea nula) o de una tarea.

## `offboarding_entrevistas`
`id, tenant_id, expediente_id UNIQUE (RESTRICT), colaborador_id, cuestionario_version, estado ('Pendiente'|'EnCurso'|'Completada'|'NoRealizada'), presentada_en, finalizada_en, motivo_no_realizada ('Declinada'|'SinRespuesta'|'SinAcceso'), no_realizada_por (NULL si el propio colaborador declinó), no_realizada_en, version`. `Completada` exige `presentada_en`; `NoRealizada` exige motivo. Triggers: una entrevista terminal (`Completada`/`NoRealizada`) no cambia; sin DELETE.

## `offboarding_respuestas`
`id, tenant_id, entrevista_id (RESTRICT), pregunta_id, tipo ('valoracion'|'categoria'|'abierta'), dimension_o_categoria, valor INTEGER NULL (CHECK 1..5), texto TEXT NULL, cuestionario_version`; `UNIQUE (entrevista_id, pregunta_id)`. Solo existen filas de lo respondido. Triggers: tras `Completada` no se inserta, actualiza ni elimina.

## `offboarding_eventos` (append-only)
`id, tenant_id, expediente_id, evento, actor_tipo ('usuario'|'sistema'), actor_id, detalle JSON, operacion_id (único por tenant si no nulo), operacion_hash, created_at`. Eventos: `Formalizada`, `Cancelada`, `TareaCompletada`, `Aplicada`, `IncidenciaDeAplicacion`, `ReintentoDeAplicacion`, `CuentaCoordinada`, `CierreConfirmado`, `EntrevistaGuardada`, `EntrevistaCompletada`, `EntrevistaNoRealizada`, `ConsultaDeRespuestas`.

## Transiciones
Expediente: `Programada → Efectiva | IncidenciaDeAplicacion | Cancelada`; `IncidenciaDeAplicacion → Efectiva | Cancelada | IncidenciaDeAplicacion`; `Efectiva` terminal. Cierre: `Pendiente → Cerrado` (solo con `Efectiva`, tareas completas con evidencia). Entrevista: `Pendiente → EnCurso → Completada`; `Pendiente|EnCurso → NoRealizada`.
