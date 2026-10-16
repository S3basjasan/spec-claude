# Contrato: API de posiciones

Base: `/api/posiciones`. JSON. Todas las operaciones requieren `Authorization: Bearer <token>` (sin token: 401 `{"message":"No autenticado."}`). Crear, actualizar, suprimir y `opciones` requieren además la capacidad `admin` (sin ella: 403 `{"message":"No tienes permisos para realizar esta acción."}`). **No existe `DELETE`, ni reactivación, ni asignación de ocupante.** `estado`: `Ocupada` | `Vacante` | `Suprimida`. `modalidad`: `Nombramiento` | `Contrato` | `Encargo` | `Subrogacion`.

## Representaciones

**Posición (listado)**:

```json
{ "id": 7, "codigo": "POS-0007", "estado": "Vacante", "modalidad": "Contrato",
  "puesto": { "id": 2, "codigo": "PU-002", "nombre": "Jefe de talento humano" },
  "unidad": { "id": 4, "codigo": "UO-004", "nombre": "Selección y Desarrollo", "estado": "Activa" },
  "supervisor": { "id": 3, "codigo": "POS-0003", "nombre": "Jefe de talento humano" },
  "subordinadas_vigentes": 2 }
```

`supervisor` es `null` en la máxima autoridad.

**Posición (detalle)** añade `version`, `puesto.descripcion`, `unidad.tipo`, `ocupante` (`{id, nombre}` o `null`), `supervisor.ocupante`, `subordinadas` (`{id, codigo, puesto, estado}`; sin las Suprimidas) y, **solo con `admin`**, `auditoria`: `{created_by:{id,nombre}, created_at, updated_by|null, updated_at|null}`.

> **Revisión 2026-10-16.** (`q` por ocupante usa como máximo 200 usuarios coincidentes, ordenados por nombre.) Cambios sobre este contrato: el listado añade `ocupante` (`{id, nombre}` o `null`) a cada elemento y `q` busca también por nombre de la unidad y del ocupante; el detalle añade `ocupante` a cada subordinada; `POST`/`PUT` validan la jerarquía de unidades y la unidad raíz (422 con «La Gerencia General ya tiene su posición (máxima autoridad).», «Selecciona el supervisor.» o «El supervisor debe pertenecer a la misma unidad o a una unidad superior.»); `PUT` además rechaza una unidad que dejaría subordinadas en una unidad superior (422 con el motivo y la cantidad) y, en la máxima autoridad, cualquier cambio de unidad o supervisor; `GET /opciones` añade a las unidades `unidad_padre_id`, `superiores`, `habilitada` y `motivo_deshabilitada` (`inactiva` | `raiz_ocupada`), a las posiciones `unidad_organizacional_id` y `unidad`, y con `excluir` el campo `unidades_minimas`.

## GET /api/posiciones

Parámetros: `q` (código, nombre del puesto, o nombre del puesto o código del supervisor; sin distinguir mayúsculas ni tildes; máx. 50), `estado` (`Vigentes` | `Ocupada` | `Vacante` | `Suprimida` | `Todas`; por defecto `Vigentes`), `unidad_organizacional_id` (la unidad y todas sus dependientes), `page` y `per_page` (por defecto 10, máx. 50). Orden por código ascendente (numérico). **Sin `admin`**: `Ocupada` y `Vacante` se respetan; `Suprimida`, `Todas` y cualquier otro valor se tratan como `Vigentes`.

200 `{ "data": [<posición>…], "meta": { "current_page", "per_page", "total", "last_page", "from", "to" } }`; 422 con parámetros inválidos.

## GET /api/posiciones/{posicion}

Cualquier autenticado, también Suprimidas. 200 `{ "data": <detalle> }`; 404 `{ "message": "La posición no existe." }`.

## POST /api/posiciones (admin)

Body `{ "puesto_id", "unidad_organizacional_id", "posicion_jefe_id" | null, "modalidad" }`. Crea la posición `Vacante`, sin ocupante, con código `POS-####`, `version` 1 y `created_by`; registra el historial inicial. 201 `{ "data": <detalle> }`. 422 con errores por campo: puesto inexistente o inactivo; unidad inexistente o inactiva; supervisor inexistente o Suprimido; sin supervisor cuando ya existe otra posición vigente sin supervisor; modalidad inválida; `codigo`, `user_id` o `estado` en el body.

## PUT /api/posiciones/{posicion} (admin)

Body `{ "unidad_organizacional_id", "posicion_jefe_id" | null, "modalidad", "version" }`. Reemplaza esos datos; conserva código, puesto, ocupante, estado y `created_by`. Valida la unidad activa (solo si cambia), el supervisor (existente, no Suprimido, ni la propia posición ni una subordinada) y la máxima autoridad. 200 (misma `version` si no hubo cambios reales); 404; 409 `{ "message": "La posición cambió desde que la abriste. Recarga para ver la versión actual." }`; 422 con errores por campo, y si la posición está Suprimida (`"La posición está suprimida y no se puede editar."`); 422 si el body incluye `puesto_id`, `codigo`, `user_id` o `estado`.

## POST /api/posiciones/{posicion}/suprimir (admin)

Sin body. Pasa la posición a `Suprimida` en una sola operación atómica y registra el estado en el historial. 200 `{ "data": <detalle> }` (también si ya estaba Suprimida, sin cambios ni historial); 404; 422 si está Ocupada (`{ "message": "No se puede suprimir la posición: está ocupada.", "errors": {"estado": ["…"]}, "motivo": "ocupada" }`) o tiene subordinadas vigentes (`"motivo": "subordinadas"`, con `"subordinadas_vigentes": n` y un mensaje con la cantidad). No cambia `version`, `updated_by` ni `updated_at`.

## GET /api/posiciones/opciones (admin)

Parámetro opcional `excluir` (id de la posición que se edita). 200:

```json
{ "puestos": [ { "id": 1, "codigo": "PU-001", "nombre": "Analista de datos", "descripcion": "…" } ],
  "unidades": [ { "id": 1, "codigo": "UO-001", "nombre": "Gerencia General", "tipo": {"id":1,"nombre":"Gerencia General","nivel":1}, "nivel_arbol": 0, "estado": "Activa", "habilitada": true } ],
  "posiciones": [ { "id": 3, "codigo": "POS-0003", "puesto": "Jefe de talento humano", "ocupante": "Ana Administradora" } ],
  "hay_maxima_autoridad": true }
```

`habilitada` = unidad activa. `posiciones` son las no Suprimidas; con `excluir` se deja fuera esa posición y todas sus subordinadas. `hay_maxima_autoridad` indica si existe otra posición vigente sin supervisor distinta de la excluida. Se declara antes de `{posicion}`.

## Reglas transversales

- Errores y mensajes en español, sin detalles internos; `{posicion}` solo acepta valores numéricos.
- Los datos del puesto, la unidad, el supervisor y el ocupante son siempre los vigentes; este módulo no guarda copias.
- Cada creación, cambio y supresión escribe en `auditoria_cambios` en la misma transacción; no hay pantalla para consultar el historial en esta feature.
