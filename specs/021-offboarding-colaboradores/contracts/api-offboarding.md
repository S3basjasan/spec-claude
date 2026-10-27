# Contrato HTTP: offboarding

Convenciones heredadas: `{ data }`, errores `{ message, code, errors? }`, paginación `{ data, links, meta }`, `Idempotency-Key` (UUID) en todo `POST`/`PUT`, `version` en cambios de expedientes existentes. Un registro inexistente, ajeno o de otro tenant responde `404 { code: "EXPEDIENTE_NO_ENCONTRADO" }` idéntico. El tenant sale siempre de la cuenta.

## Administrador — `/api/offboarding` (`auth:sanctum` + `admin`)

| Ruta | Propósito |
|---|---|
| `GET /` | Listado paginado. Filtros: `estado`, `cierre`, `motivo`, `entrevista`, `desde`, `hasta`, `colaborador_id`, `page`, `per_page` |
| `GET /resumen` | Conteos: programadas, efectivas, con incidencia, cierre pendiente, entrevistas completadas/no realizadas/pendientes |
| `GET /contexto?colaborador_id` | Vínculo vigente, motivos, política (situación y plantilla) |
| `GET /documentos?colaborador_id` | Respaldos referenciables |
| `GET /agregados` | Promedios y frecuencias deterministas; `oculto: true` si el grupo es menor al mínimo |
| `POST /` | Formalizar: `colaborador_id`, `motivo`, `fecha_efectiva`, `documento_ids` |
| `GET /{id}` | Detalle |
| `POST /{id}/cancelacion` | `version`, `razon` |
| `POST /{id}/tareas/{tarea}/completar` | `version`, `documento_ids` |
| `POST /{id}/aplicacion` | Reintento manual (`version`) |
| `POST /{id}/cierre` | Confirmar cierre administrativo (`version`) |
| `POST /{id}/entrevista/no-realizada` | `version`, `motivo` (`Declinada`, `SinRespuesta`, `SinAcceso`) |
| `GET /{id}/entrevista/respuestas` | Respuestas originales; exige permiso de entrevistas; registra la consulta |
| `GET /{id}/entrevista/estructura` | Representación estructurada; mismo permiso |

No existe `PUT`, `PATCH` ni `DELETE`.

## Colaborador — `/api/mi-desvinculacion` (`auth:sanctum`)

| Ruta | Propósito |
|---|---|
| `GET /` | Su desvinculación comunicada (motivo, fecha efectiva, estado, tareas) |
| `GET /entrevista` | Cuestionario, estado y sus respuestas |
| `PUT /entrevista` | Guardar avance (`version`, `valoraciones`, `categorias`, `abiertas`) → `EnCurso` |
| `POST /entrevista/confirmacion` | Completar (`version`) |
| `POST /entrevista/declinacion` | Declinar (`version`) → `NoRealizada` |

## Códigos de error principales

`422`: `VALIDACION`, `POLITICA_SIN_CONFIGURAR`, `SIN_VINCULO_VIGENTE`, `FECHA_EFECTIVA_INVALIDA`, `SALIDA_YA_FORMALIZADA` (409), `EVIDENCIA_REQUERIDA`, `CIERRE_NO_PROCEDE`, `ENTREVISTA_SIN_RESOLVER`; `403 SIN_PERMISO_DE_ENTREVISTAS`; `404 SIN_FICHA_ASOCIADA`; `409`: `EXPEDIENTE_MODIFICADO`, `ESTADO_NO_PERMITIDO`, `ENTREVISTA_CERRADA`, `APLICACION_NO_PROCEDE`, `INCIDENCIA_DE_APLICACION`, `CLAVE_DE_OPERACION_REUTILIZADA`; `503`: `POLITICA_NO_DISPONIBLE`.
