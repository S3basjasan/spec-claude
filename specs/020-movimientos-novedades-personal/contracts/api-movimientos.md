# Contrato HTTP: movimientos y novedades de personal

**Feature**: 020-movimientos-novedades-personal · Prefijos bajo `/api/…`, JSON, autenticación Sanctum.

Convenciones heredadas del repositorio (no nuevas): respuestas `{ "data": … }`; errores `{ "message", "code", "errors"? }`; paginación `{ data, links, meta }` con `page` y `per_page` (máx. 100, por defecto 20); `Idempotency-Key` (UUID) obligatorio en todo `POST`; `version` (entero) obligatoria en cada cambio de estado de una actuación existente.

**Aislamiento uniforme**: un registro inexistente, de otro tenant o ajeno al Colaborador responde exactamente igual: `404 { "message": "Solicitud no encontrada.", "code": "SOLICITUD_NO_ENCONTRADA" }`. El tenant siempre sale de la cuenta autenticada; ningún campo del cuerpo ni de la URL lo selecciona.

## Rutas del Administrador — `/api/movimientos-personal` (`auth:sanctum` + `admin`)

| Método y ruta | Propósito | Éxito |
|---|---|---|
| `GET /` | Listado paginado del tenant. Filtros: `tipo`, `estado`, `grupo` (`pendientes`, `resueltas`, `programados`, `aplicados`, `incidencias`), `colaborador_id`, `desde`, `hasta` (periodo `AAAA-MM-DD`; coincide con el periodo de una novedad o la fecha efectiva de un movimiento) | 200 |
| `GET /resumen` | Conteos `{ pendientes, en_revision, programados, aplicados, incidencias }` | 200 |
| `GET /{id}` | Detalle completo (ver «Detalle») | 200 |
| `GET /contexto-de-movimiento?colaborador_id&q&page&per_page` | Situación actual del colaborador y destinos asignables | 200 |
| `GET /documentos?colaborador_id` | Documentos disponibles de la ficha que pueden referenciarse como respaldo | 200 |
| `POST /novedades` | Presentar Permiso, Licencia o Vacaciones en nombre de un colaborador | 201 (200 si es repetición idéntica) |
| `POST /ausencias` | Informar una ausencia ocurrida | 201 / 200 |
| `POST /movimientos` | Proponer Ascenso o Traslado | 201 / 200 |
| `POST /{id}/revision` | Pasar a «En revisión» | 200 |
| `POST /{id}/aprobacion` | Aprobar | 200 |
| `POST /{id}/rechazo` | Rechazar con razón | 200 |
| `POST /{id}/retiro` | Retirar una novedad que el mismo Administrador registró | 200 |
| `POST /{id}/revision-de-ausencia` | Pasar una ausencia a «Revisada» o «Justificada» | 200 |
| `POST /{id}/aplicacion` | Aplicar ahora un movimiento aprobado cuya fecha efectiva ya llegó, o reintentar uno con incidencia | 200 |

No existen `PUT`, `PATCH` ni `DELETE`: lo presentado, resuelto o aplicado no se edita ni se elimina (FR-028).

### Cuerpos de presentación

`POST /novedades`: `colaborador_id` (int, req.), `tipo` (`Permiso`\|`Licencia`\|`Vacaciones`), `fecha_inicio`, `fecha_fin` (`AAAA-MM-DD`), `motivo` (string ≤ 1000, opcional salvo política), `documento_ids` (lista de enteros, ≤ 10).

`POST /ausencias`: `colaborador_id`, `fecha_inicio`, `fecha_fin`, `motivo`, `documento_ids` (misma forma; sin anticipación).

`POST /movimientos`: `colaborador_id`, `tipo` (`Ascenso`\|`Traslado`), `posicion_destino_id`, `fecha_efectiva`, `motivo` (req., ≤ 1000), `documento_ids`.

### Cuerpos de resolución

| Ruta | Cuerpo |
|---|---|
| `revision` | `{ "version": n }` |
| `aprobacion` | `{ "version": n, "justificacion": "…", "reconoce_cruce": bool? }` — `justificacion` obligatoria (≤ 1000); `reconoce_cruce` obligatorio en `true` si el detalle muestra cruces |
| `rechazo` | `{ "version": n, "justificacion": "…" }` |
| `retiro` | `{ "version": n }` |
| `revision-de-ausencia` | `{ "version": n, "resultado": "Revisada"\|"Justificada", "observacion": "…" }` |
| `aplicacion` | `{ "version": n }` |

### Detalle (`GET /{id}`)

```jsonc
{ "data": {
  "id": 12, "tipo": "Ascenso", "categoria": "Movimiento", "estado": "Presentada", "version": 1,
  "colaborador": { "id": 3, "codigo": "EMP-003", "nombre": "…" },
  "presentada": { "en": "2026-10-21T15:04:05Z", "fecha": "2026-10-21", "por": { "id": 1, "nombre": "…" }, "en_nombre_de_colaborador": true },
  "motivo": "…",
  "periodo": null,                       // novedades: { "inicio": "…", "fin": "…", "ocurrida": false }
  "movimiento": {                        // solo movimientos
    "fecha_efectiva": "2026-12-01",
    "posicion_actual": { "id": 10, "puesto": "…", "unidad": "…" },
    "posicion_destino": { "id": 11, "puesto": "…", "unidad": "…", "asignable": true, "motivo": null },
    "aplicacion": { "estado": "Pendiente", "aplicada_en": null, "por_sistema": false, "incidencia": null }
  },
  "politica": { "version_id": 7, "numero": 2, "tipo": "Ascensos", "parametros": { }, "reglas_fijas": [ ] },
  "comprobaciones": [ { "clave": "evaluacion", "estado": "NoVerificable", "texto": "…" } ],
  "puede_aprobar": false, "motivo_no_aprobable": "EVALUACION_NO_VERIFICABLE",
  "cruces": [ { "id": 9, "tipo": "Vacaciones", "estado": "Aprobada", "inicio": "…", "fin": "…" } ],
  "documentos": [ { "id": 4, "disponible": true } ],
  "historial": [ { "evento": "Presentada", "estado_nuevo": "Presentada", "actor": { "tipo": "usuario", "id": 1, "nombre": "…" }, "justificacion": null, "en": "…" } ]
} }
```

`comprobaciones[].estado` distingue tres resultados separados: `Cumple`, `NoCumple`, `NoVerificable`. Un error de política, evaluación, estructura o documentos es `NoVerificable` (nunca «cumple», «saldo disponible» ni «aplicado»). `puede_aprobar` es falso mientras alguna comprobación obligatoria no sea `Cumple`; el servidor repite la verificación al aprobar.

## Rutas del Colaborador — `/api/mis-solicitudes` (`auth:sanctum`)

La ficha se resuelve por la asociación cuenta–ficha del tenant del usuario autenticado; sin asociación responde `404 { "code": "SIN_FICHA_ASOCIADA" }`.

| Método y ruta | Propósito |
|---|---|
| `GET /` | Solicitudes propias. Filtros: `tipo`, `estado`. Incluye novedades (todos los estados), ausencias propias ya revisadas o justificadas y movimientos propios aprobados, programados o aplicados. |
| `GET /{id}` | Detalle comunicable de una solicitud propia |
| `GET /documentos` | Documentos propios que pueden referenciarse (visibles para el Colaborador y con permiso documental) |
| `POST /novedades` | Presentar un Permiso, Licencia o Vacaciones propia (mismo cuerpo que el del Administrador, **sin** `colaborador_id`) |
| `POST /{id}/retiro` | Retirar una novedad propia en «Presentada» o «En revisión» (`{ "version": n }`) |

Forma comunicable (distinta de la del Administrador; **no contiene** los campos restringidos): novedades → `id, tipo, estado, periodo, motivo, resultado: { sentido, justificacion, resuelta_en }?, politica: { numero, requisitos }, version`; movimientos → `id, tipo, estado_visible, fecha_efectiva`. Para el Colaborador `IncidenciaDeAplicacion` se muestra como `Aprobada` («pendiente de aplicar») y nunca se revela la incidencia ni se comunica como aplicada.

## Códigos de error

| HTTP | `code` | Cuándo |
|---|---|---|
| 401 / 403 | — | sin sesión / sin capacidad `admin` (rutas del Administrador) |
| 404 | `SOLICITUD_NO_ENCONTRADA` | inexistente, ajena o de otro tenant |
| 404 | `FICHA_NO_ENCONTRADA` | ficha inexistente o de otro tenant al presentar |
| 404 | `SIN_FICHA_ASOCIADA` | cuenta de Colaborador sin ficha asociada |
| 422 | `VALIDACION` | campos inválidos, por campo en `errors` |
| 422 | `ANTICIPACION_INSUFICIENTE` | no cumple la anticipación en días calendario |
| 422 | `FECHA_PASADA` | novedad/movimiento con inicio anterior a hoy |
| 422 | `AUSENCIA_FUTURA` | ausencia con fecha posterior a hoy |
| 422 | `JUSTIFICACION_REQUERIDA` | falta la justificación exigida |
| 422 | `DOCUMENTO_REQUERIDO` | falta el respaldo exigido o no está accesible |
| 422 | `SIN_VINCULO_VIGENTE` | ficha sin vínculo laboral vigente |
| 422 | `POSICION_NO_DISPONIBLE` | destino inexistente, suprimido, de otro tenant, igual al actual u ocupado |
| 422 | `POLITICA_SIN_CONFIGURAR` | el tipo no tiene versión vigente |
| 422 | `POLITICA_NO_ADMITE_SOLICITUDES` | la versión vigente tiene `disponible = false` |
| 422 | `CRUCE_SIN_RECONOCER` | aprobar una novedad con cruces sin `reconoce_cruce` |
| 422 | `EVALUACION_NO_VERIFICABLE` | aprobar un movimiento sin evaluación válida frente al destino |
| 422 | `CLAVE_DE_OPERACION_REQUERIDA` | falta o no es UUID la `Idempotency-Key` |
| 409 | `CLAVE_DE_OPERACION_REUTILIZADA` | misma clave con otro contenido u otro actor |
| 409 | `SOLICITUD_MODIFICADA` | `version` obsoleta o transición ya realizada por otro |
| 409 | `ESTADO_NO_RESOLUBLE` | transición no permitida desde el estado actual |
| 409 | `MOVIMIENTO_INCOMPATIBLE` | el colaborador ya tiene otro movimiento aprobado pendiente |
| 409 | `APLICACION_NO_PROCEDE` | aplicar con fecha efectiva futura o fuera de estado |
| 409 | `INCIDENCIA_DE_APLICACION` | el reintento sigue sin cumplir condiciones (con `incidencia`) |
| 503 | `POLITICA_NO_DISPONIBLE` | falló la consulta de política: se distingue de las dos causas anteriores |
| 503 | `ESTRUCTURA_NO_VERIFICABLE` | falló Estructura o la ficha está bloqueada tras los reintentos acotados |

Los mensajes son seguros: no incluyen identificadores de otro tenant, rutas internas, contenido ni títulos de documentos, ni trazas.
