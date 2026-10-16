# Contrato: API del catálogo formativo

Base: `/api/catalogo-formativo`. JSON. Todas las operaciones requieren `Authorization: Bearer <token>` (sin token: 401
`{"message":"No autenticado."}`). Las marcadas **admin** requieren además la capacidad `admin` (sin ella: 403
`{"message":"No tienes permisos para realizar esta acción."}`). **No existe `DELETE`** sobre ningún recurso: los vínculos se
quitan con `PUT` y las áreas formativas no se eliminan.

## Representaciones

**Recurso** (curso o micro-contenido):

```json
{ "id": 4, "nombre": "Comunicación efectiva", "tipo": "Online", "duracion_minutos": 90, "formador": "Ana Salas",
  "estado": "Disponible", "accion": "Iniciar", "url_destino": null }
```

`estado` (`Disponible` | `Archivado`), `accion` (`Iniciar` | `Ver`) y `url_destino` (nullable: sin valor, la interfaz muestra el
botón deshabilitado con "Próximamente"). Al colaborador solo se le devuelven recursos `Disponible`.

**Área formativa**: `{ "id": 2, "nombre": "Habilidades interpersonales" }`

**Fila (listado)**:

```json
{ "competencia_id": 2, "codigo": "COMP-0002", "nombre": "Trabajo en equipo", "tipo": "Cardinal", "estado": "Activo",
  "area_formativa": { "id": 2, "nombre": "Habilidades interpersonales" },
  "cursos_count": 3, "microcontenidos_count": 1, "cubierta": true, "nivel_actual": "B" }
```

`area_formativa` es `null` si no tiene. Los contadores y `cubierta` cuentan solo recursos `Disponible`. `nivel_actual`
(`A`–`D` o `null`) se incluye **solo para el colaborador**; el administrador no lo recibe.

**Detalle**:

```json
{ "competencia": { "id": 2, "codigo": "COMP-0002", "nombre": "Trabajo en equipo", "descripcion": "…", "tipo": "Cardinal", "estado": "Activo",
                   "grados": { "A": "…", "B": "…", "C": "…", "D": "…" } },
  "area_formativa": { "id": 2, "nombre": "Habilidades interpersonales" },
  "cursos": [ <recurso>… ], "microcontenidos": [ <recurso>… ],
  "version": 4, "ultima_configuracion": { "usuario": "Marta Ríos", "fecha": "2026-10-14T15:20:00Z" },
  "nivel_actual": "B", "nivel_requerido": "A" }
```

Para el **administrador**: incluye recursos `Archivado` señalados por `estado` (los contadores de la fila cuentan solo `Disponible`; el panel muestra además cuántos archivados hay), `version` y `ultima_configuracion` (nulos si
nunca se configuró; `version` 0), y no incluye `nivel_actual` ni `nivel_requerido`. Para el **colaborador**: solo recursos
`Disponible`, sin `version` ni `ultima_configuracion`; `nivel_actual` (o `null`) y, solo si tiene brecha activa, `nivel_requerido`.

## GET /api/catalogo-formativo

Parámetros: `q` (nombre, contiene, sin distinguir mayúsculas ni tildes, máx. 50), `tipo` (`Cardinal`|`Especifica`),
`area_formativa_id`, `cobertura` (`con`|`sin`: con/sin **cursos** disponibles, los micro-contenidos no cuentan; `sin_oferta`: ningún curso ni micro-contenido disponible, lo usa el enlace "Ver sin cobertura" del indicador), `estado` (`Activo`|`Inactivo`|`Todos`),
`page` (≥ 1), `per_page` (1–50, por defecto 10). Orden: nombre A–Z. Con `competencia` (id) devuelve solo esa fila (usado por la
entrada desde la DNF).

**Visibilidad por rol**: sin la capacidad `admin` solo se devuelven competencias `Activo` y `estado` se ignora. Con `admin`,
`estado` por defecto es `Activo`.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Siempre que haya sesión | `{ "data": [<fila>…], "links": {…}, "meta": { "current_page", "per_page", "total", "last_page", "from", "to" } }` |
| 422 | Parámetros inválidos | errores por campo |

## GET /api/catalogo-formativo/cobertura — admin

Cobertura sobre competencias `Activo` con ≥ 1 curso o micro-contenido `Disponible`.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Administrador | `{ "data": { "activas_totales": 10, "activas_cubiertas": 4, "porcentaje": 40 } }` (porcentaje 0 si no hay activas) |

## GET /api/catalogo-formativo/recomendados

Solo tiene sentido para el colaborador; el administrador recibe 200 con lista vacía. Devuelve las competencias `Activo` donde
el usuario autenticado tiene brecha activa y que cuentan con ≥ 1 recurso `Disponible`. Lista vacía si no hay brechas, no hay
DNF o el puerto falla.

```json
{ "data": [ { "competencia_id": 5, "nombre": "Análisis de datos", "tipo": "Especifica",
              "area_formativa": { "id": 3, "nombre": "Técnica y datos" },
              "nivel_actual": "C", "nivel_requerido": "B", "cursos_count": 2, "microcontenidos_count": 1 } ] }
```

## GET /api/catalogo-formativo/recursos-disponibles — admin

Parámetros: `tipo` (`curso`|`microcontenido`, obligatorio), `q` (nombre, máx. 50), `competencia_id` (opcional: excluye los ya
vinculados a esa competencia). Devuelve solo recursos `Disponible`, ordenados por nombre, sin paginar, con
`competencias_count` (en cuántas otras competencias se usa).

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Administrador | `{ "data": [ <recurso> + "competencias_count"… ] }` (vacío si el catálogo de micro-contenidos aún no tiene registros) |
| 422 | `tipo` ausente o inválido | errores por campo |

## GET /api/catalogo-formativo/areas

Cualquier persona autenticada. Lista todas las áreas formativas ordenadas por nombre (para filtros y selectores).
`{ "data": [<área>…] }`.

## POST /api/catalogo-formativo/areas — admin

Cuerpo: `{ "nombre": "…" }`. `nombre` obligatorio (≤ 60, recortado; solo espacios cuenta como vacío), único sin distinguir
mayúsculas ni espacios al inicio o al final.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 201 | Creada | `{ "data": <área> }` |
| 422 | Vacío, demasiado largo o duplicado | `{ "message": "…", "errors": { "nombre": ["…"] } }` |

## PATCH /api/catalogo-formativo/areas/{areaId} — admin

Cuerpo: `{ "nombre": "…" }`, mismas reglas (el área puede conservar su propio nombre con otra capitalización). Si el nombre
cambia escribe una fila de historial (`AreaFormativa` / `Renombrado`, nombre anterior y nuevo, usuario, fecha). Sin cambio real
no escribe nada.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Renombrada, o sin cambios | `{ "data": <área> }` |
| 404 | No existe | `{ "message": "El área formativa no existe." }` |
| 422 | Validación o duplicado | errores por campo |

## GET /api/catalogo-formativo/{competenciaId}

Cualquier persona autenticada. Para el colaborador, 404 si la competencia está inactiva.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | La competencia existe (y es visible para el rol) | `{ "data": <detalle> }` |
| 404 | No existe, o inactiva para el colaborador | `{ "message": "La competencia no existe." }` |

## PUT /api/catalogo-formativo/{competenciaId} — admin

```json
{ "area_formativa_id": 2, "curso_ids": [4, 7], "microcontenido_ids": [1], "version": 4 }
```

Reemplaza la configuración formativa de la competencia **en una transacción**: área, conjunto de cursos y conjunto de
micro-contenidos. `area_formativa_id` puede ser `null` (sin área). `curso_ids` y `microcontenido_ids` sin repetidos. `version`
obligatoria: la del detalle con el que se abrió el panel (0 si nunca se configuró). Escribe el historial de cada vínculo
agregado o quitado y del cambio de área. Sin diferencias reales no modifica nada, no escribe historial y no sube la versión.

Reglas: los ids deben existir; **los recursos nuevos** deben estar `Disponible`; los ya vinculados que se archivaron pueden
conservarse o quitarse; si la competencia está inactiva **no se admiten recursos nuevos** (sí quitar y cambiar el área).

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Guardado, o sin cambios | `{ "data": <detalle> }` (misma `version` si no hubo cambios) |
| 404 | La competencia no existe | `{ "message": "La competencia no existe." }` |
| 409 | `version` distinta de la actual | `{ "message": "La configuración cambió desde que la abriste. Recarga para ver la versión actual." }` |
| 422 | Ids repetidos o inexistentes, recurso nuevo archivado, área inexistente, o recursos nuevos en competencia inactiva | `{ "message": "…", "errors": { "curso_ids.1": ["…"], "area_formativa_id": ["…"], "competencia": ["La competencia está inactiva: no se pueden asociar recursos nuevos."] } }` |

## GET /api/catalogo-formativo/{competenciaId}/historial — admin

Parámetros: `page`, `per_page` (1–50, por defecto 10). Orden: más reciente primero. Incluye los cambios de vínculos y de área de
la competencia y los renombrados de las áreas que ha tenido asignadas.

```json
{ "data": [ { "fecha": "2026-10-14T15:20:00Z", "usuario": "Marta Ríos", "tipo_cambio": "Curso", "accion": "Asociado",
              "recurso": "Comunicación efectiva", "valor_anterior": null, "valor_nuevo": "Comunicación efectiva" },
            { "fecha": "…", "usuario": "…", "tipo_cambio": "AreaFormativa", "accion": "Cambiado",
              "recurso": null, "valor_anterior": "Técnica y datos", "valor_nuevo": "Desempeño y resultados" } ],
  "links": {…}, "meta": {…} }
```

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Administrador | como arriba |
| 404 | La competencia no existe | `{ "message": "La competencia no existe." }` |

## Orden de rutas

`/cobertura`, `/recomendados`, `/recursos-disponibles` y `/areas` se declaran antes de `/{competenciaId}`, que se restringe a
números, para que esas palabras nunca se interpreten como id.

## Reglas transversales

- Errores y mensajes en español; nunca se revelan detalles internos.
- `Especifica` (sin tilde) es el valor de `tipo` de competencia; la interfaz muestra "Específica".
- Los datos de la competencia son siempre los actuales del diccionario (Estructura): si cambia su nombre, descripción, grados
  o estado, el catálogo lo refleja al instante.
- Un colaborador nunca recibe recursos archivados, competencias inactivas, cobertura, historial, `version` ni el nivel de otra
  persona, aunque envíe parámetros como `estado=Todos`.
- La degradación de Gestión de Personal, DNF o micro-contenidos no produce error: `nivel_actual` queda `null`, `recomendados`
  queda vacío y `microcontenidos` queda `[]`.
