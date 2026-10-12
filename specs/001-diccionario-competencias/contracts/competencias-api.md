# Contrato: API de competencias

Base: `/api/competencias`. JSON. Todas las operaciones requieren `Authorization: Bearer <token>` (sin token:
401 `{"message":"No autenticado."}`). Crear, actualizar y cambiar estado requieren además la capacidad
`admin` (sin ella: 403 `{"message":"No tienes permisos para realizar esta acción."}`). **No existe `DELETE`**:
una competencia nunca se elimina.

## Representaciones

**Competencia (listado)**

```json
{ "id": 1, "codigo": "COMP-0001", "nombre": "Liderazgo", "descripcion": "…", "tipo": "Cardinal", "estado": "Activo" }
```

**Competencia (detalle)** añade `version` y los grados, siempre cuatro y en orden A–D:

```json
{ "id": 1, "codigo": "COMP-0001", "nombre": "Liderazgo", "descripcion": "…", "tipo": "Cardinal",
  "estado": "Activo", "version": 3,
  "grados": [ { "grado": "A", "descripcion": "…" }, { "grado": "B", "descripcion": "…" },
              { "grado": "C", "descripcion": "…" }, { "grado": "D", "descripcion": "…" } ] }
```

## Petición de crear/actualizar

```json
{ "nombre": "Liderazgo", "descripcion": "…", "tipo": "Cardinal",
  "grados": { "A": "…", "B": "…", "C": "…", "D": "…" }, "version": 3 }
```

- `nombre`: obligatorio, texto recortado, máximo 50, único entre activas e inactivas sin distinguir
  mayúsculas ni espacios alrededor.
- `descripcion`: obligatoria, máximo 500.
- `tipo`: obligatorio, `Cardinal` o `Especifica`.
- `grados.A` … `grados.D`: exactamente esas cuatro letras, sin repetir ni omitir, cada una obligatoria y de
  máximo 500.
- `version`: solo en `PUT`, obligatorio; la del detalle con el que se abrió el formulario.
- `codigo`, `estado` y cualquier otro campo se ignoran: el código no se modifica y el estado solo cambia con
  `PATCH /{id}/estado`.

## POST /api/competencias

Crea la competencia con `estado` `Activo` y `version` 1; registra `created_by`.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 201 | Creada | `{ "data": <detalle> }` |
| 422 | Validación o nombre ya existente | `{ "message": "…", "errors": { "nombre": ["…"], "grados.B": ["…"] } }` |

## GET /api/competencias

Parámetros: `q` (nombre, contiene, sin distinguir mayúsculas, máximo 50), `tipo` (`Cardinal`|`Especifica`),
`estado` (`Activo`|`Inactivo`|`Todos`), `page` (≥ 1), `per_page` (1–50, por defecto 10). Orden: nombre A–Z.
No incluye grados.

**Visibilidad por rol**: si el token no tiene la capacidad `admin`, el resultado contiene solo competencias
`Activo` y el parámetro `estado` se ignora. Con `admin`, `estado` por defecto es `Activo`.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Siempre que haya sesión | `{ "data": [<listado>…], "links": {…}, "meta": { "current_page", "per_page", "total", "last_page", "from", "to" } }` |
| 422 | `tipo`, `estado` o `per_page` inválidos | errores por campo |

## GET /api/competencias/{id}

Disponible para cualquier persona autenticada, también para competencias `Inactivo` (registros históricos).

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Existe | `{ "data": <detalle> }` |
| 404 | No existe | `{ "message": "La competencia no existe." }` |

## PUT /api/competencias/{id}

Reemplaza nombre, descripción, tipo y grados **en una transacción**; funciona con competencias activas e
inactivas; no cambia `codigo` ni `estado`; incrementa `version` y registra `updated_by`.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Actualizada | `{ "data": <detalle> }` con `version` nueva |
| 404 | No existe | `{ "message": "La competencia no existe." }` |
| 409 | `version` distinta de la actual | `{ "message": "La competencia cambió desde que la abriste. Recarga para ver la versión actual." }` |
| 422 | Validación o nombre de otra competencia | errores por campo |

## PATCH /api/competencias/{id}/estado

```json
{ "estado": "Inactivo" }
```

- `estado`: obligatorio, `Activo` o `Inactivo`.
- Si cambia, registra en el historial (competencia, estado anterior, estado nuevo, usuario, fecha) en la misma
  transacción. **No** cambia `version` ni `updated_by`.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Estado cambiado | `{ "data": <detalle> }` con el estado nuevo |
| 404 | No existe | `{ "message": "La competencia no existe." }` |
| 422 | `estado` ausente o inválido | `{ "message": "…", "errors": { "estado": ["…"] } }` |
| 422 | La competencia ya está en el estado pedido | `{ "message": "La competencia ya está en ese estado.", "errors": { "estado": ["La competencia ya está en ese estado."] } }`; no se escribe historial |

## Reglas transversales

- Errores y mensajes en español; nunca se revelan detalles internos.
- `tipo` es `Cardinal` o `Especifica` y `estado` es `Activo` o `Inactivo`; la interfaz muestra "Específica".
- Un cambio concurrente de estado entre dos administradores produce un solo registro de historial; el segundo
  recibe 422 "ya está en ese estado".
