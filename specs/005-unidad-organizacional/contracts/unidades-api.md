# Contrato: API de unidades organizacionales y tipos de unidad

Bases: `/api/unidades` y `/api/tipos-unidad`. JSON. Todas las operaciones requieren `Authorization: Bearer <token>` (sin token: 401 `{"message":"No autenticado."}`). Las escrituras, `opciones` e `impacto-desactivacion` requieren además la capacidad `admin` (sin ella: 403 `{"message":"No tienes permisos para realizar esta acción."}`). **No existe `DELETE`**. `estado` de unidad: `Activa` | `Inactiva` (filtro: también `Todas`).

## Representaciones

**Unidad (listado)**, en orden jerárquico:

```json
{ "id": 7, "codigo": "UO-007", "nombre": "Reclutamiento",
  "tipo": { "id": 4, "nombre": "Departamento", "nivel": 4 },
  "unidad_padre": { "id": 4, "codigo": "UO-004", "nombre": "Selección y Desarrollo", "estado": "Activa" },
  "nivel_arbol": 3, "estado": "Activa",
  "tiene_dependientes": true, "dependientes_activas": 1, "posiciones_vigentes": 0,
  "admite_hijas": true, "sigue_cadena": true }
```

`unidad_padre` es `null` en la raíz (`nivel_arbol` 0). `dependientes_activas` cuenta la rama activa (directas e indirectas); `posiciones_vigentes`, las `Ocupada` y `Vacante` de la propia unidad. `sigue_cadena` es falso si el nivel del tipo no es el de la padre + 1.

**Unidad (detalle)** añade `version`, `fecha_creacion`, `fecha_supresion` (o `null`), `dependientes` (hijas directas: id, código, nombre, estado) y, **solo con `admin`**, `auditoria`: `{created_by:{id,nombre}, created_at, updated_by|null, updated_at|null}`.

**Tipo**: `{ "id": 2, "nombre": "Gerencia", "nivel": 2, "es_raiz": false, "cantidad_unidades": 2 }`.

## Unidades

### GET /api/unidades
Parámetros: `q` (código, nombre o nombre del tipo; sin distinguir mayúsculas ni tildes; máx. 50) y `estado`. Sin paginación. Orden y `nivel_arbol` sobre el árbol completo antes de filtrar. Con `q`, o con `estado` distinto de `Activa`, la respuesta incluye las coincidencias **y todas sus ancestras**. Sin `admin`: solo `Activa` y `estado` se ignora. 200 `{ "data": [...], "meta": { "total": n } }`; 422 con `estado` o `q` inválidos.

### GET /api/unidades/{unidad}
Cualquier autenticado, también inactivas. 200 `{ "data": <detalle> }` (con `auditoria` solo si `admin`); 404 `{ "message": "La unidad no existe." }`.

### POST /api/unidades
Body: `{ "nombre", "unidad_padre_id" }` (ambos obligatorios). El backend calcula `tipo_unidad_id` (nivel de la padre + 1), crea la unidad `Activa` con la fecha del día, `version` 1 y código `UO-###`, y registra el historial. 201 `{ "data": <detalle> }`. 422: nombre vacío o de más de 150; padre inexistente, inactiva o de un tipo del último nivel ("Este tipo no admite subunidades."); `tipo_unidad_id`, `codigo`, `estado` o fechas en el body.

### PUT /api/unidades/{unidad}
Body: `{ "nombre", "unidad_padre_id", "version" }`. Reemplaza el nombre y, si cambia la padre, reubica la unidad con su rama. Funciona con unidades inactivas; conserva código, tipo, estado, fechas y `created_by`. La raíz debe seguir con `unidad_padre_id` nulo. Si la padre cambia: existe, activa, de tipo del nivel inmediatamente superior, ni la propia unidad ni una dependiente. Si no cambia, se conserva aunque no siga la cadena. 200 (misma `version` si no hubo cambios reales); 404; 409 `{ "message": "La unidad cambió desde que la abriste. Recarga para ver la versión actual." }`; 422 con errores por campo.

### GET /api/unidades/opciones (admin)
Parámetro opcional `reubicar` (id de la unidad). 200:

```json
{ "tipos": [ { "id": 1, "nombre": "Gerencia General", "nivel": 1 } ],
  "unidades": [ { "id": 1, "codigo": "UO-001", "nombre": "Gerencia General", "tipo": { "id": 1, "nombre": "Gerencia General", "nivel": 1 },
                  "nivel_arbol": 0, "estado": "Activa", "admite_hijas": true, "habilitada": true } ] }
```

`habilitada` = activa y que admite hijas. Con `reubicar`, `unidades` contiene solo las activas del tipo inmediatamente superior al de esa unidad, sin la propia unidad ni sus dependientes.

### GET /api/unidades/{unidad}/impacto-desactivacion (admin)
200 `{ "data": { "procede": bool, "dependientes_activas": [ {id, codigo, nombre, tipo} ], "unidades_con_posiciones": [ {id, codigo, nombre, cantidad} ] } }`. `procede` es falso si es la raíz o si hay posiciones. 404.

### PATCH /api/unidades/{unidad}/estado (admin)
Body `{ "estado": "Activa" | "Inactiva" }`. Si ya tiene ese estado: 200 sin cambios ni historial.
- **Inactiva**: desactiva en cascada la unidad y todas sus dependientes activas, en una sola operación, con `fecha_supresion` del día. 422 si es la raíz o si la unidad o alguna dependiente activa tiene posiciones `Ocupada` o `Vacante`: `{ "message": "…", "errors": {"estado": ["…"]}, "unidades_con_posiciones": [ {id, codigo, nombre, cantidad} ] }`; ninguna unidad cambia.
- **Activa**: solo esa unidad, y solo si su padre está activa (422 con `"motivo": "padre_inactivo"`); borra `fecha_supresion`; no reactiva dependientes.

No cambia `version`, `updated_by` ni `updated_at`. 200 `{ "data": <detalle> }`; 404.

## Tipos de unidad

### GET /api/tipos-unidad
Cualquier autenticado. 200 `{ "data": [<tipo>…], "meta": { "version": "<token>" } }` ordenados por nivel; el token es el SHA-1 de `id:nombre:nivel` de los tipos ordenados por nivel.

### POST /api/tipos-unidad (admin)
Body `{ "nombre" }`. Crea el tipo con nivel = último + 1. 201 `{ "data": <tipo> }`; 422 si el nombre está vacío, supera 50 o ya existe sin distinguir mayúsculas ni tildes.

### PUT /api/tipos-unidad (admin)
Body `{ "version": "<token>", "tipos": [ { "id", "nombre" } ] }`: la lista completa en el nuevo orden. Aplica renombres y reordenamiento en una sola transacción; el nivel de cada tipo es su posición (1..N).
- 200 `{ "data": [<tipo>…], "meta": { "version": "<token nuevo>" } }`; sin cambios: 200 sin escribir.
- 409 `{ "message": "La cadena de tipos cambió desde que la abriste. Recarga para ver la versión actual." }` si el token no coincide.
- 422 por validación: `errors` con claves `tipos.{índice}.nombre` (vacío, > 50 o repetido), ids que no son exactamente los existentes, o el primero no es el tipo raíz actual.
- 422 por conflicto: `{ "message": "…", "unidades_invalidas": [ { "id", "codigo", "nombre", "tipo": {nombre, nivel}, "unidad_padre": { "id", "codigo", "nombre", "tipo": {nombre, nivel} } } ] }` con los tipos y niveles **nuevos**, cuando alguna unidad quedaría bajo una padre de nivel igual o más profundo. No se aplica ningún cambio.

## Reglas transversales

- Errores y mensajes en español, sin detalles internos. `{unidad}` solo acepta valores numéricos; `opciones` e `impacto-desactivacion` se declaran antes de `{unidad}`.
- Los datos del tipo y de la padre son siempre los vigentes.
- Cada creación, cambio de datos, reubicación, cambio de estado (también en cascada) y cambio de la cadena escribe en `auditoria_cambios` en la misma transacción; la consulta del historial no tiene pantalla en esta feature.
