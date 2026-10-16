# Contrato HTTP: Desarrollo profesional

Prefijos de GP-01 (`/api/colaboradores`, `/api/mi-ficha`). Las rutas de administración van bajo `auth:sanctum` + `admin`; la propia, solo `auth:sanctum`. **No existe ningún `DELETE`.** Fechas `YYYY-MM-DD`; instantes ISO 8601 UTC; ids enteros. Errores en el formato actual: `{message, code, errors?}`.

## Representación `desarrollo`

```json
{
  "data": {
    "colaborador_id": 12,
    "hoy": "2026-10-15",
    "formaciones": [{
      "id": 1, "nivel": "Tercer nivel", "titulo_programa": "Ingeniería de Sistemas", "institucion": "EPN",
      "estado": "Completada", "fecha_inicio": "2015-04-01", "fecha_finalizacion": null, "observaciones": null,
      "version": 1, "created_at": "…", "updated_at": "…"
    }],
    "experiencias": [{
      "id": 3, "organizacion": "ACME", "cargo": "Analista", "fecha_inicio": "2019-01-07",
      "fecha_finalizacion": null, "funciones": null, "observaciones": null, "version": 1, "created_at": "…", "updated_at": "…"
    }],
    "movilidad": { "estado": "NoInformada", "observaciones": null, "actualizada_at": null, "version": 1 },
    "acciones": { "editar": true }
  }
}
```

Orden: `formaciones` por inicio desc (sin fecha al final) e id desc; `experiencias` por inicio desc e id desc. `estado` de formación: `EnCurso|Completada|Incompleta`; de movilidad: `Disponible|NoDisponible|NoInformada`. `hoy` es la fecha del tenant, para validar en el cliente. `acciones.editar` es `false` en la vista propia.

## Endpoints

| Método y ruta | Quién | Cuerpo | Respuestas |
|---|---|---|---|
| `GET /api/colaboradores/{colaboradorId}/desarrollo` | Admin | — | 200 `desarrollo`; 404 `FICHA_NO_ENCONTRADA` |
| `GET /api/mi-ficha/desarrollo` | Autenticado con ficha asociada y permiso de ficha | — | 200 `desarrollo` (`acciones.editar=false`); 403 `CUENTA_SIN_FICHA` / `SIN_PERMISO_FICHA` |
| `POST /api/colaboradores/{colaboradorId}/formaciones` | Admin | `nivel, titulo_programa, institucion, estado` (+ `fecha_inicio, fecha_finalizacion, observaciones`) | 201 fila creada; 422; 404; 503 `ESCRITURA_DESHABILITADA` |
| `PATCH /api/colaboradores/{colaboradorId}/formaciones/{formacionId}` | Admin | campos editables (omitido = sin cambio, `null` vacía opcionales) + `version` | 200 fila (sin cambios reales ⇒ 200 sin incrementar versión); 409 `REGISTRO_MODIFICADO`; 422; 404 `REGISTRO_NO_ENCONTRADO` |
| `POST /api/colaboradores/{colaboradorId}/experiencias` | Admin | `organizacion, cargo, fecha_inicio` (+ `fecha_finalizacion, funciones, observaciones`) | 201; 422; 404; 503 |
| `PATCH /api/colaboradores/{colaboradorId}/experiencias/{experienciaId}` | Admin | campos + `version` | 200 / 409 / 422 / 404 |
| `PATCH /api/colaboradores/{colaboradorId}/movilidad` | Admin | `estado`, `observaciones`, `version` (de movilidad) | 200 bloque `movilidad`; 409 `REGISTRO_MODIFICADO`; 422 |

Las respuestas de escritura devuelven únicamente la fila (o el bloque) guardada, no la colección completa; el cliente invalida la consulta de desarrollo.

## Reglas de respuesta

- **401** sin sesión. **403** a un Colaborador en cualquier ruta de administración (middleware `admin`), sin revelar si el recurso existe.
- **404** idéntico para ficha inexistente, de otro tenant o fila de otra ficha (`FICHA_NO_ENCONTRADA` / `REGISTRO_NO_ENCONTRADO`), sin distinguir la causa.
- **422** `VALIDACION` con `errors` por campo (`nivel`, `fecha_finalizacion`, …); mensajes en español sin datos internos. Reglas: obligatorios, longitudes, fechas reales, inicio no futuro, finalización no futura en experiencia, `fin >= inicio`.
- **409** versión desactualizada; no se escribe nada.
- **503** `ESCRITURA_DESHABILITADA` o `INFORMACION_NO_DISPONIBLE`.
- Los POST no usan `Idempotency-Key`; no hay deduplicación (FR-006).
- Intentar enviar `colaborador_id`, `tenant_id`, `created_by` o `version` de otra fila en el cuerpo se ignora/rechaza: el destino lo fija siempre la ruta.

## Cambios aditivos en GP-01

`GET /api/colaboradores/{id}` y `/api/mi-ficha` devuelven `capacidades.desarrollo = true` (sin otros cambios de contrato).
