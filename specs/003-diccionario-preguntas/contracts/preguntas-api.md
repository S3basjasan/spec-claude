# Contrato: API de preguntas

Base: `/api/preguntas`. JSON. Todas las operaciones requieren `Authorization: Bearer <token>` (sin token: 401
`{"message":"No autenticado."}`). Crear, actualizar y `competencias-disponibles` requieren además la capacidad
`admin` (sin ella: 403 `{"message":"No tienes permisos para realizar esta acción."}`). **No existe `DELETE`** ni
operación de cambio de estado: el estado lo gestiona 001-diccionario-competencias.

## Representaciones

**Registro (listado)** — datos de la competencia leídos en vivo; no incluye preguntas:

```json
{ "competencia_id": 1, "codigo": "COMP-0001", "nombre": "Orientación a resultados", "descripcion": "…", "tipo": "Cardinal", "estado": "Activo" }
```

**Registro (detalle)** añade `version` y `preguntas`: siempre exactamente 4 textos, en orden (posición 0 = pregunta 1):

```json
{ "competencia_id": 1, "codigo": "COMP-0001", "nombre": "…", "descripcion": "…", "tipo": "Cardinal", "estado": "Inactivo",
  "version": 3, "preguntas": ["…", "…", "…", "…"] }
```

**Competencia disponible**: `{ "competencia_id", "codigo", "nombre", "descripcion", "tipo" }`.

## Petición de crear/actualizar

```json
{ "competencia_id": 3, "preguntas": ["…", "…", "…", "…"], "version": 3 }
```

- `competencia_id`: obligatorio en `POST`; debe existir, estar `Activo` y no tener registro. En `PUT`, opcional; si se
  envía y no coincide con la ruta → 422.
- `preguntas`: arreglo de **exactamente 4** textos; cada texto obligatorio, se recorta, máximo **500** caracteres y
  distinto (sin distinguir mayúsculas ni espacios alrededor) de los otros tres. El número de cada pregunta es su
  posición (1–4).
- `version`: solo en `PUT`, obligatorio; la del detalle con el que se abrió el formulario.

## POST /api/preguntas

Crea el registro (`version` 1) y registra `created_by`.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 201 | Creado | `{ "data": <detalle> }` |
| 422 | Validación: competencia ausente, inexistente, inactiva o con registro; menos o más de 4 textos; texto vacío, largo o repetido | `{ "message": "…", "errors": { "competencia_id": ["…"], "preguntas": ["…"], "preguntas.2": ["…"] } }` |

## GET /api/preguntas

Parámetros: `q` (nombre de la competencia, contiene, sin distinguir mayúsculas ni acentos de caja, máximo 50), `tipo`
(`Cardinal`|`Especifica`), `estado` (`Activo`|`Inactivo`|`Todos`), `page` (≥ 1), `per_page` (1–50, por defecto 10).
Orden: nombre de la competencia A–Z.

**Visibilidad por rol**: si el token no tiene la capacidad `admin`, el resultado contiene solo registros de
competencias `Activo` y `estado` se ignora. Con `admin`, `estado` por defecto es `Activo`.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Siempre que haya sesión | `{ "data": [<listado>…], "links": {…}, "meta": { "current_page", "per_page", "total", "last_page", "from", "to" } }` |
| 422 | `tipo`, `estado` o `per_page` inválidos | errores por campo |

## GET /api/preguntas/competencias-disponibles

Solo Administrador. Competencias `Activo` que no tienen registro, ordenadas por nombre (sin paginar).

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Administrador | `{ "data": [ <competencia disponible>… ] }` |

Se declara antes de `/{competencia_id}`: la palabra `competencias-disponibles` nunca se interpreta como id.

## GET /api/preguntas/{competencia_id}

Cualquier persona autenticada, también si la competencia está `Inactivo` (consulta desde registros históricos).

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | La competencia tiene registro | `{ "data": <detalle> }` |
| 404 | No hay registro para esa competencia | `{ "message": "El registro de preguntas no existe." }` |

## PUT /api/preguntas/{competencia_id}

Reemplaza los textos de las 4 preguntas en una transacción (nunca inserta ni elimina filas); funciona con la
competencia activa o inactiva; no cambia la competencia; solo incrementa `version` y registra `updated_by` si hay
**cambios reales**.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Actualizado, o sin cambios reales (nada se modifica) | `{ "data": <detalle> }` (misma `version` si no hubo cambios) |
| 404 | No hay registro | `{ "message": "El registro de preguntas no existe." }` |
| 409 | `version` distinta de la actual | `{ "message": "El registro cambió desde que lo abriste. Recarga para ver la versión actual." }` |
| 422 | Validación, o `competencia_id` distinto del de la ruta | errores por campo |

## Reglas transversales

- Errores y mensajes en español; nunca se revelan detalles internos.
- `tipo` es `Cardinal` o `Especifica` y `estado` es `Activo` o `Inactivo` (los de la competencia); la interfaz muestra
  "Específica".
- Los datos de la competencia siempre son los actuales de 001; si cambia su nombre, el listado lo refleja al instante.
