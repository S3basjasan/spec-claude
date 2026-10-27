# Investigación: Offboarding de colaboradores

**Feature**: 021-offboarding-colaboradores · **Fecha**: 2026-10-26

Decisiones tomadas inspeccionando el repositorio real. Sin dependencias nuevas. El motor es SQLite con aislamiento por `tenant_id` (no PostgreSQL por esquema), como GP-02, 016 y 020.

## Hallazgos que condicionan el diseño

| Hallazgo | Consecuencia |
|---|---|
| `PoliticasService::seleccionarParaPresentar(tenant, 'Offboarding', instante)` exige transacción y devuelve `condiciones.parametros.tareas` con `orden`, `nombre`, `descripcion`, `exige_evidencia` ya canónicos. | La formalización toma el bloqueo de la ficha y fija versión y plantilla en la misma transacción (igual que la 020). |
| `VinculoLaboralRepositoryInterface::ejecutarBajoBloqueo` serializa por ficha; hay triggers que prohíben acortar un vínculo sobre una asignación abierta (`VINCULO_ACORTADO_SOBRE_ASIGNACION`) y superposiciones. | La salida cierra primero la asignación abierta y luego el vínculo, en una transacción, con `fecha_fin = fecha_salida = fecha efectiva` (fin excluido). |
| La feature 020 ya aplica movimientos con proceso programado, identidad de sistema, incidencia en transacción aparte y reintento manual. | Se reutiliza el patrón: `AplicarSalida`, comando `offboarding:aplicar-salidas`, mismas reglas de idempotencia. |
| No existe componente de Identidad y Acceso con contrato de deshabilitación ni de permisos finos. | Dos puertos sin adaptador real: `CoordinadorDeCuentaPort` (queda «pendiente», nunca «coordinada») y `PermisoDeEntrevistasPort` (nadie lo posee hasta que exista el contrato). Las pruebas los sustituyen. |
| El Colaborador se identifica por `colaborador_cuentas` y se autentica con Sanctum (capacidad `user`). | Rutas del Colaborador solo con `auth:sanctum` y asociación cuenta–ficha; sin crear acceso a desvinculados. |
| `TipoDeHecho` y `ConsultarTrayectoria`/`EloquentLectorLaboral` ya publican hechos de movimientos. | Se agrega el hecho `Salida` leído del expediente aplicado. |

## Decisiones

- **D-01 Expediente + tareas + entrevista como tablas propias**: `offboarding_expedientes`, `offboarding_tareas`, `offboarding_entrevistas`, `offboarding_respuestas`, `offboarding_documentos`, `offboarding_eventos` (append-only). El expediente es el candado lógico (junto con el bloqueo de ficha) y lleva versión optimista.
- **D-02 Una salida viva por vínculo**: índice único parcial `(vinculo_id) WHERE estado <> 'Cancelada'`.
- **D-03 La entrevista es una tarea del sistema**: al formalizar se agrega la tarea `Entrevista` (tipo `Entrevista`, siempre obligatoria, ordenada al final) y su entrevista `Pendiente`. La tarea queda `Completada` cuando la entrevista se completa o se registra «no realizada» con motivo; el cierre exige todas las tareas completadas.
- **D-04 Respuestas**: solo se guardan las respondidas; lo no respondido se deriva comparando con el cuestionario versionado (ausente ≠ 0). Guardar avance reemplaza el conjunto mientras la entrevista está `EnCurso` (control optimista); al completar, triggers impiden modificar o borrar respuestas y la entrevista.
- **D-05 Cuestionario v1 en código** (PHP puro): dimensiones, escala, categorías y preguntas abiertas, con identificadores estables (`DIM-…`, `CAT-…`, `ABI-…`).
- **D-06 Representación estructurada** (`RepresentacionParaAnalisis`): contexto (referencia técnica = hash HMAC del id de entrevista, sin datos identificativos), cuantitativo, cualitativo, control y procedencia (`declarado_por_colaborador`); sin llamadas externas. El contrato de resultados futuros se documenta en `contracts/analisis-futuro.md`.
- **D-07 Agregados**: promedios y frecuencias deterministas solo si hay al menos 5 entrevistas completadas en el grupo; si no, `oculto = true`.
- **D-08 Cuenta**: tras aplicar la salida se invoca `CoordinadorDeCuentaPort`; su resultado actualiza `cuenta_estado` (`Pendiente`, `Coordinada`, `Incidencia`) sin revertir la salida; el adaptador por defecto devuelve `Pendiente`.
- **D-09 Permiso de entrevistas**: `PermisoDeEntrevistasPort::puede(userId)`; adaptador por defecto niega. Cada consulta de respuestas registra un evento `ConsultaDeRespuestas`.
- **D-10 Cancelación** solo en estado `Programada` o `Incidencia`, con razón; terminal.
- **D-11 Visibilidad del Colaborador**: ve su expediente (no cancelado) con motivo, fecha efectiva, estado y tareas (nombre, descripción, estado); no ve respaldos, historial ni incidencias.
- **D-12 Frontend**: módulo `gestionPersonal/offboarding`, rutas `/offboarding`, `/offboarding/formalizar`, `/offboarding/:id` (Administrador) y `/mi-desvinculacion`, `/mi-desvinculacion/entrevista` (Colaborador).

## Compuertas abiertas

| # | Compuerta | Efecto mientras esté abierta |
|---|---|---|
| P-1 | Contrato con Identidad y Acceso (tratamiento de cuenta) | `cuenta_estado` queda `Pendiente`; nunca «deshabilitada». |
| P-2 | Permiso «consultar entrevistas de salida» | Nadie consulta respuestas ni la representación estructurada; sí el estado de participación. |
| P-3 | Mínimo de agregados (5 por defecto) | Configurable por `config('hrms.offboarding.minimo_agregados')`. |
| P-4 | Corrección de una salida ya efectiva | No existe edición ni reversión. |
