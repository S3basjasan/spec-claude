# Contrato: API de puestos

Base: `/api/puestos`. JSON. Todas las operaciones requieren `Authorization: Bearer <token>` (sin token: 401
`{"message":"No autenticado."}`). Crear, actualizar, cambiar estado y `competencias-disponibles` requieren además la
capacidad `admin` (sin ella: 403 `{"message":"No tienes permisos para realizar esta acción."}`). **No existe `DELETE`**:
los puestos nunca se eliminan.

## Representaciones

**Puesto (listado)** — no incluye misión, funciones, requisitos ni competencias:

```json
{ "id": 1, "codigo": "PU-001", "nombre": "Analista de datos", "descripcion": "Convierte datos en reportes para decidir mejor.", "estado": "Activo" }
```

**Puesto (detalle)** añade `mision`, `version` y las tres listas. `funciones` va por `orden` (desde 1); `requisitos`, por
tipo (`Formacion`, `Experiencia`, `Capacitacion`) y luego por creación; `competencias`, por nombre. Los datos de cada
competencia (`codigo`, `nombre`, `tipo`, `estado`) son los actuales del diccionario y no se almacenan en el puesto:

```json
{ "id": 1, "codigo": "PU-001", "nombre": "Analista de datos", "descripcion": "…", "mision": "…", "estado": "Inactivo", "version": 3,
  "funciones": [ { "orden": 1, "descripcion": "Recopilar y consolidar datos.", "tipo": "Esencial" },
                 { "orden": 2, "descripcion": "Documentar indicadores.", "tipo": "Complementaria" } ],
  "requisitos": [ { "tipo": "Formacion", "descripcion": "Título de tercer nivel afín." },
                  { "tipo": "Experiencia", "descripcion": "2 años en análisis de datos." } ],
  "competencias": [ { "competencia_id": 2, "codigo": "COMP-0002", "nombre": "Trabajo en equipo", "tipo": "Cardinal", "estado": "Activo", "grado_requerido": "B" },
                    { "competencia_id": 7, "codigo": "COMP-0007", "nombre": "Adaptabilidad", "tipo": "Especifica", "estado": "Inactivo", "grado_requerido": "C" } ] }
```

**Competencia disponible** (para el selector): `{ "competencia_id", "codigo", "nombre", "tipo", "grados": [ { "grado": "A", "descripcion": "…" }, …cuatro ] }`.

## Petición de crear/actualizar

```json
{ "nombre": "…", "descripcion": "…", "mision": "…",
  "funciones": [ { "descripcion": "…", "tipo": "Esencial" } ],
  "requisitos": [ { "tipo": "Formacion", "descripcion": "…" } ],
  "competencias": [ { "competencia_id": 2, "grado_requerido": "B" } ],
  "version": 3 }
```

- `nombre` (obligatorio, ≤ 50), `descripcion` (obligatoria, ≤ 200) y `mision` (obligatoria, ≤ 200): se recortan; un texto
  de solo espacios cuenta como vacío. El `nombre` debe ser único entre todos los puestos (activos e inactivos), sin
  distinguir mayúsculas ni espacios alrededor.
- `funciones`: de 1 a 20 elementos, con al menos una de `tipo` `Esencial`. Cada una: `descripcion` obligatoria (≤ 500,
  recortada, distinta —sin distinguir mayúsculas ni espacios alrededor— de las otras) y `tipo` (`Esencial` o
  `Complementaria`). El `orden` se asigna según la posición en el arreglo, desde 1.
- `requisitos`: de 1 a 20 elementos, con al menos uno de `Formacion` y uno de `Experiencia` (`Capacitacion` es
  opcional). Cada uno: `tipo` y `descripcion` obligatoria (≤ 500, recortada, distinta de las de los otros requisitos
  **del mismo tipo**).
- `competencias`: de 1 a 20 elementos, sin `competencia_id` repetido. Cada una: `competencia_id` existente y `Activo`
  (o ya asignada al puesto, solo en `PUT`) y `grado_requerido` (`A`–`D`) que exista para esa competencia. Una
  competencia ya asignada que está `Inactivo` solo se acepta **con el mismo grado** que tenía.
- `version`: solo en `PUT`, obligatorio; la del detalle con el que se abrió el formulario.
- `codigo`: solo en `PUT`, opcional; si se envía y no coincide con el del puesto → 422. El código nunca cambia.

## POST /api/puestos

Crea el puesto con estado `Activo` y `version` 1, sus funciones, requisitos y competencias en una transacción, genera el
código `PU-###`, registra `created_by` y escribe en el historial una fila por cada dato y elemento (valor anterior nulo).

| Código | Cuándo | Cuerpo |
|---|---|---|
| 201 | Creado | `{ "data": <detalle> }` |
| 422 | Validación | `{ "message": "…", "errors": { "nombre": ["…"], "funciones": ["…"], "funciones.2.descripcion": ["…"], "requisitos": ["…"], "competencias.0.competencia_id": ["…"], "competencias.0.grado_requerido": ["…"] } }` |

## GET /api/puestos

Parámetros: `q` (código o nombre, contiene, sin distinguir mayúsculas ni tildes, máximo 50), `estado`
(`Activo`|`Inactivo`|`Todos`), `page` (≥ 1), `per_page` (1–50, por defecto 10). Orden: nombre A–Z.

**Visibilidad por rol**: si el token no tiene la capacidad `admin`, el resultado contiene solo puestos `Activo` y
`estado` se ignora. Con `admin`, `estado` por defecto es `Activo`.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Siempre que haya sesión | `{ "data": [<listado>…], "links": {…}, "meta": { "current_page", "per_page", "total", "last_page", "from", "to" } }` |
| 422 | `estado` o `per_page` inválidos | errores por campo |

## GET /api/puestos/competencias-disponibles

Solo Administrador. Competencias `Activo` con sus cuatro grados, ordenadas por nombre (sin paginar). No excluye las ya
asignadas a un puesto: el formulario las filtra según las filas que tenga.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Administrador | `{ "data": [ <competencia disponible>… ] }` |

Se declara antes de `/{puestoId}` y este se restringe a números: la palabra `competencias-disponibles` nunca se
interpreta como id.

## GET /api/puestos/{puestoId}

Cualquier persona autenticada, también si el puesto está `Inactivo` (consulta desde registros históricos).

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | El puesto existe | `{ "data": <detalle> }` |
| 404 | No existe | `{ "message": "El puesto no existe." }` |

## PUT /api/puestos/{puestoId}

Reemplaza los datos y los conjuntos de funciones, requisitos y competencias en una transacción; funciona con el puesto
activo o inactivo; conserva `codigo` y `created_by`; no cambia el estado. Solo incrementa `version` y registra
`updated_by` si hay **cambios reales** (un cambio que solo reordena funciones lo es, pero no escribe en el historial).
Guardar sin cambios reales no modifica nada.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Actualizado, o sin cambios reales (nada se modifica) | `{ "data": <detalle> }` (misma `version` si no hubo cambios) |
| 404 | No existe | `{ "message": "El puesto no existe." }` |
| 409 | `version` distinta de la actual | `{ "message": "El puesto cambió desde que lo abriste. Recarga para ver la versión actual." }` |
| 422 | Validación, `codigo` distinto del del puesto, o competencia inactiva nueva o con otro grado | errores por campo |

## PATCH /api/puestos/{puestoId}/estado

Cuerpo: `{ "estado": "Activo" | "Inactivo" }`. Activar no tiene restricciones. Desactivar solo procede si el puesto no
tiene posiciones `Ocupada` ni `Vacante`; la comprobación y el cambio se hacen en una sola operación atómica. Las
`Suprimida` no lo impiden. No cambia `version`, `updated_by` ni `updated_at`; escribe una fila `estado` en el historial
solo si el estado cambió.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Cambiado, o el puesto ya estaba en ese estado (sin cambios ni historial) | `{ "data": <detalle> }` |
| 404 | No existe | `{ "message": "El puesto no existe." }` |
| 422 | `estado` inválido; o desactivar con posiciones `Ocupada` o `Vacante` | `{ "message": "No se puede desactivar el puesto: tiene 3 posiciones ocupadas o vacantes.", "errors": { "estado": ["…"] }, "posiciones_en_uso": 3 }` |

`posiciones_en_uso` es la suma de las posiciones `Ocupada` y `Vacante`; el puesto sigue `Activo`.

## Reglas transversales

- Errores y mensajes en español; nunca se revelan detalles internos.
- `Especifica` (sin tilde) es el valor de `tipo` de competencia; `Formacion` y `Capacitacion`, los de requisito. La
  interfaz muestra "Específica", "Formación" y "Capacitación".
- Los datos de la competencia son siempre los actuales del diccionario (001); si cambia su nombre o su estado, el puesto
  lo refleja al instante.
- Cada cambio de datos, estado, función, requisito o competencia, y la creación, escribe filas en `auditoria_cambios`
  (ver [data-model.md](../data-model.md)); el contrato no expone una consulta del historial (fuera de alcance).
- Un `Usuario` nunca obtiene puestos `Inactivo` en el listado, aunque envíe `estado=Inactivo` o `estado=Todos`.
