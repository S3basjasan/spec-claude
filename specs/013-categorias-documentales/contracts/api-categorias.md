# Contrato HTTP: Categorías documentales

Prefijo `/api/categorias-documentales` (excepción por precedente de `/api/colaboradores`). Todas las rutas van bajo `auth:sanctum` + `admin`. **No existe ningún `DELETE`.** Instantes ISO 8601 UTC; ids enteros. Errores en el formato actual: `{message, code, errors?}`. El tenant se toma siempre de la sesión; ningún cuerpo ni filtro lo acepta.

## Representación `categoria`

```json
{
  "data": {
    "id": 4,
    "nombre": "Contratos",
    "estado": "Activa",
    "version": 1,
    "created_by": 1,
    "updated_by": 1,
    "created_at": "2026-10-16T14:00:00Z",
    "updated_at": "2026-10-16T14:00:00Z"
  }
}
```

El listado devuelve la misma forma por elemento (con `created_by`/`updated_by` omitidos) dentro de `{data: [...], meta: {current_page, per_page, total, last_page, from, to}}`.

## Endpoints

| Método y ruta | Cuerpo / parámetros | Respuestas |
|---|---|---|
| `POST /api/categorias-documentales` | `nombre` | 201 `categoria` (estado `Activa`); 422 `VALIDACION`; 503 `ESCRITURA_DESHABILITADA` |
| `GET /api/categorias-documentales` | `q` (≤ 50), `estado` = `Activa` \| `Inactiva` \| `Todas` (por defecto `Activa`), `page` ≥ 1, `per_page` 1–100 (por defecto 20) | 200 paginado, orden por `nombre_busqueda` e `id`; 422 por parámetros inválidos |
| `GET /api/categorias-documentales/{categoria}` | — | 200 `categoria`; 404 `CATEGORIA_NO_ENCONTRADA` |
| `PATCH /api/categorias-documentales/{categoria}` | `nombre`, `version` | 200 `categoria` (sin cambio real ⇒ 200 sin escribir); 409 `CATEGORIA_MODIFICADA`; 422; 404; 503 |
| `PATCH /api/categorias-documentales/{categoria}/estado` | `estado` (`Activa` \| `Inactiva`), `version` | 200 `categoria` (mismo estado con versión vigente ⇒ 200 sin escribir); 409; 422; 404; 503 |

`{categoria}` solo admite dígitos.

## Reglas de respuesta

- **401** sin sesión. **403** al Colaborador en cualquier ruta (middleware `admin`), sin revelar si el recurso existe; un permiso ausente equivale a denegado.
- **404** idéntico para categoría inexistente o de otro tenant (`CATEGORIA_NO_ENCONTRADA`), sin distinguir la causa.
- **422** `VALIDACION` con `errors.nombre` (obligatorio, más de 100 caracteres tras recortar, o `Ya existe una categoría con ese nombre.`) o `errors.estado`; también para `version` ausente o no entera. Una violación de la restricción única por dos creaciones simultáneas devuelve el mismo 422 de `nombre`.
- **409** versión obsoleta; no se escribe nada. Nunca se acepta una versión vieja aunque el resultado visual parezca equivalente.
- **503** `ESCRITURA_DESHABILITADA` (configuración `hrms.gestion_personal.escritura_habilitada`).
- Ninguna operación consulta ni escribe documentos. Los campos desconocidos del cuerpo (`tenant_id`, `created_by`, `id`…) se ignoran; el destino lo fija la ruta y la sesión.

## Reglas de unicidad y búsqueda

- Unicidad por `(tenant, nombre_clave)`: NFC, recorte exterior y minúsculas; «Educación» y «educación» colisionan, «Educacion» no. Las categorías inactivas cuentan.
- Búsqueda `q`: parcial sobre el nombre sin tildes y en minúsculas; con los caracteres `%` y `_` tratados literalmente.
