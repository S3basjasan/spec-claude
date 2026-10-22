# Contrato HTTP: Políticas operativas de personal

Prefijo `/api/politicas-personal` (excepción por precedente, ver plan). Las rutas de administración van bajo `auth:sanctum` + `admin`; `…/aplicable` va solo bajo `auth:sanctum`. **No existe ningún `DELETE`, ni `PUT`/`PATCH` sobre versiones publicadas.** Instantes ISO 8601 UTC; ids enteros; `{tipo}` ∈ `permisos|ausencias|licencias|vacaciones|ascensos|traslados|offboarding`. Errores: `{message, code, errors?}`. El tenant y el actor salen siempre de la sesión.

## Representaciones

`politica_resumen`:

```json
{ "tipo": "permisos", "nombre": "Permisos", "categoria": "Novedad", "situacion": "Vigente",
  "disponible": true, "version_vigente": { "numero": 2, "inicio_vigencia": "2026-11-01T13:00:00Z" },
  "version_programada": null, "tiene_borrador": false }
```

`situacion`: `SinConfigurar` (sin versión vigente; **nunca** «sin restricciones»), `Vigente`, `NoAdmiteSolicitudes` (vigente con `disponible=false`). `disponible` es `null` cuando no aplica (Offboarding o sin configurar).

`version`:

```json
{ "id": 12, "numero": 2, "estado": "Vigente", "descripcion": "…",
  "parametros": { "disponible": true, "justificacion_obligatoria": true, "documento_obligatorio": false, "anticipacion_dias": 2 },
  "inicio_vigencia": "2026-11-01T13:00:00Z", "inicio_vigencia_local": "2026-11-01T08:00",
  "motivo_cambio": "…", "revision": { "revisor": "…", "observaciones": "…", "revision_en": "…" },
  "publicada_por": 1, "publicada_en": "…", "retiro": null,
  "base_version_id": 9, "version": 1, "created_by": 1, "created_at": "…", "updated_by": 1, "updated_at": "…" }
```

Un borrador: `numero: null`, `estado: "Borrador"`, sin `inicio_vigencia`, `motivo_cambio`, `revision` ni `publicada_*`; con `version` para el control de edición. Un retiro: `retiro: { "por": 1, "en": "…", "motivo": "…" }`.

`definicion`: ver [catalogo-de-parametros.md](catalogo-de-parametros.md): `{ categoria, parametros: [{clave, tipo, etiqueta, ayuda, minimo?, maximo?, obligatorio}], reglas_fijas: [{clave, texto}], limites }`.

## Endpoints de administración

| Método y ruta | Entrada | Respuestas |
|---|---|---|
| `GET /api/politicas-personal` | `tipo`, `situacion` (`SinConfigurar` \| `Vigente` \| `NoAdmiteSolicitudes` \| `ConProgramada`) | 200 `{data: [politica_resumen]}` (siempre los siete tipos que cumplan el filtro; sin paginación) |
| `GET /api/politicas-personal/{tipo}` | — | 200 `{data: {resumen, definicion, vigente, programada, borrador, versiones: [version…]}}`; versiones publicadas de más reciente a más antigua (por `numero`); 404 |
| `GET /api/politicas-personal/{tipo}/versiones/{version}` | — | 200 `{data: version}`; 404 |
| `GET /api/politicas-personal/{tipo}/versiones/{version}/comparacion` | `con` = `vigente` (por defecto) | 200 `{data: {contra: version\|null, diferencias: [{clave, etiqueta, anterior, nuevo}]}}`; compara contra la vigente **real**; 404 |
| `POST /api/politicas-personal/{tipo}/borrador` | `base_version_id` opcional (versión publicada del mismo tipo) | 201 `{data: version}`; 409 `BORRADOR_EXISTENTE` (con `errors.borrador_id`); 404; 422; 503 `ESCRITURA_DESHABILITADA` |
| `PATCH /api/politicas-personal/{tipo}/borrador/{version}` | `descripcion`, `parametros`, `version` (control de edición) | 200 `{data: version}`; 409 `BORRADOR_MODIFICADO`; 404; 422 (errores por campo); 503 |
| `POST /api/politicas-personal/{tipo}/borrador/{version}/descarte` | `version` | 200 `{data: null}`; 409 `BORRADOR_MODIFICADO`; 404; 503 |
| `POST /api/politicas-personal/{tipo}/borrador/{version}/previsualizacion` | `inicio` (opcional, ver abajo) | 200 `{data: {condiciones, diferencias, inicio_vigencia, inicio_vigencia_local, efecto, errores}}`; no escribe nada; `errores` lista las contradicciones y campos incompletos; 404 |
| `POST /api/politicas-personal/{tipo}/borrador/{version}/publicacion` | cabecera `Idempotency-Key` (UUID); cuerpo: `version`, `motivo`, `revision: {revisor, observaciones?}`, `inicio` | 201 `{data: version}`; 200 en repetición válida; 409 (`BORRADOR_MODIFICADO`, `PROGRAMADA_EXISTENTE`, `CLAVE_DE_OPERACION_REUTILIZADA`); 422 (`VALIDACION`, `INICIO_EN_EL_PASADO`, `CLAVE_DE_OPERACION_REQUERIDA`); 404; 503 |
| `POST /api/politicas-personal/{tipo}/versiones/{version}/retiro` | cabecera `Idempotency-Key` (UUID); cuerpo: `justificacion` | 200 `{data: version}` (también en repetición válida); 409 (`VIGENCIA_INICIADA` si la versión ya comenzó —vigente o sustituida—, `VERSION_NO_RETIRABLE` si es un borrador o ya está retirada, `CLAVE_DE_OPERACION_REUTILIZADA`); 422; 404; 503 |

`inicio` (en previsualización y publicación): `{ "modo": "inmediato" }` o `{ "modo": "programado", "fecha_hora_local": "AAAA-MM-DDTHH:mm" }`. La hora local se interpreta en la zona de la organización; si no existe por cambio de hora se usa el primer instante válido posterior; si se repite, la primera ocurrencia. La respuesta devuelve `inicio_vigencia` (UTC) e `inicio_vigencia_local`.

`efecto` (previsualización): `{ "momento": "…", "aplica_a": "nuevas solicitudes", "solicitudes_anteriores": "conservan sus condiciones", "sustituye": {numero} | null }`.

## Endpoint de consulta (cualquier usuario autenticado)

| Método y ruta | Entrada | Respuestas |
|---|---|---|
| `GET /api/politicas-personal/{tipo}/aplicable` | — | 200 `{data: politica_aplicable}`; 404 tipo desconocido; 503 `POLITICA_NO_DISPONIBLE` |

```json
{ "tipo": "permisos", "situacion": "Vigente", "numero": 2, "inicio_vigencia": "2026-11-01T13:00:00Z",
  "parametros": { "…": "…" }, "reglas_fijas": [ { "clave": "…", "texto": "…" } ] }
```

`situacion`: `SinConfigurar` (sin `numero` ni `parametros`), `NoAdmiteSolicitudes` (con `numero` y `parametros`) o `Vigente`. Nunca incluye descripción interna, motivo, revisión, responsables, versiones, borradores ni programadas. Es una consulta informativa: no reserva versión (la reserva ocurre al presentar, ver [consumo-de-politicas.md](consumo-de-politicas.md)).

## Reglas de respuesta

- **401** sin sesión. **403** al Colaborador en cualquier ruta de administración (middleware `admin`), sin revelar si existe el recurso.
- **404** `POLITICA_NO_ENCONTRADA` idéntico para tipo desconocido, versión inexistente, versión de otro tipo o de otro tenant, y borrador descartado en las rutas de consulta. En las rutas de borrador, una versión que existe pero ya no es un borrador activo (descartada o publicada) responde **409** `BORRADOR_MODIFICADO`.
- **409**: `BORRADOR_EXISTENTE` (con `errors.borrador_id`), `BORRADOR_MODIFICADO` (versión obsoleta, descartado o ya publicado; no se aplica nada), `PROGRAMADA_EXISTENTE` (retirarla primero; también para publicación inmediata), `VIGENCIA_INICIADA` (retiro de una versión que ya empezó), `VERSION_NO_RETIRABLE` (no es programada o ya está retirada), `CLAVE_DE_OPERACION_REUTILIZADA`.
- **422** `VALIDACION`: `errors` es un objeto de claves planas con puntos (`descripcion`, `parametros.<clave>`, `parametros.tareas.<n>.nombre`, `motivo`, `revision.revisor`, `revision.observaciones`, `justificacion`, `inicio.fecha_hora_local`), cada una con una lista de mensajes en español. Un parámetro fuera del catálogo dice «Parámetro no admitido para este tipo de política.»; uno que intenta fijar una regla fija dice «Regla fija, no configurable: …» con el texto de la regla; Ausencias dice «Este tipo de política no usa anticipación mínima.». Además `INICIO_EN_EL_PASADO` y `CLAVE_DE_OPERACION_REQUERIDA` como códigos propios.
- **503** `ESCRITURA_DESHABILITADA` (`hrms.gestion_personal.politicas_habilitado`) o `POLITICA_NO_DISPONIBLE` (consulta aplicable). Una falla jamás se responde como «sin requisitos».
- La publicación y el retiro son idempotentes por `Idempotency-Key` + hash del contenido: mismo par, mismo resultado (200); misma clave con otro contenido, 409.
- Los campos desconocidos del cuerpo (fuera de `parametros`) se ignoran; dentro de `parametros` se rechazan.
- Ninguna operación elimina versiones; ninguna modifica una versión publicada (salvo registrar su retiro antes de su inicio).
- El estado de cada versión (`estado`) se calcula en cada respuesta con el reloj del servidor.
