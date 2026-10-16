# Contrato: API de colaboradores

Base: `/api/colaboradores` y `/api/mi-ficha`. JSON. Todas las operaciones requieren `Authorization: Bearer <token>` (sin
token: 401 `{"message":"No autenticado."}`). Las de `/api/colaboradores*` requieren además la capacidad `admin` (sin
ella: 403 `{"message":"No tienes permisos para realizar esta acción."}`, igual para toda ficha, exista o no). El tenant
sale siempre de la cuenta autenticada. **No existe `DELETE`**: las fichas nunca se eliminan ni se desactivan.

**Errores con código**: `{ "message": "…", "code": "<CODIGO>", "errors": { "<campo>": ["…"] } }`; `errors` solo en 422.
Ficha inexistente, de otro tenant o ajena: 404 `{"message":"La ficha no existe.","code":"FICHA_NO_ENCONTRADA"}`
(idéntico en los tres casos).

## Representaciones

**Colaborador (listado)**:

```json
{ "id": 1, "codigo": "EMP-001", "nombres": "Ana", "apellidos": "Núñez Paredes",
  "estado": "SinVinculoVigente",
  "puesto": null, "unidad": null, "laboral_disponible": true }
```

`puesto` y `unidad` son `null` cuando no hay asignación (la interfaz muestra «No registrado»). Si
`laboral_disponible` es `false`, la fuente falló y la interfaz muestra «Información no disponible».

**Colaborador (detalle)** — `GET /api/colaboradores/{id}` y `GET /api/mi-ficha`:

```json
{ "data": {
  "id": 1, "codigo": "EMP-001", "version": 3,
  "tipo_identificacion": "Cedula", "numero_identificacion": "0102030405",
  "nombres": "Ana", "apellidos": "Núñez Paredes",
  "fecha_nacimiento": "1990-04-12", "nacionalidad": "Ecuatoriana", "genero": null, "estado_civil": "Soltera",
  "numero_hijos": 0,
  "direccion": null, "telefono": "+593 2 555 0101", "correo_contacto": "ana@example.test",
  "emergencia_nombre": "Rosa Núñez", "emergencia_relacion": "Madre", "emergencia_telefono": "0999999999",
  "created_at": "2026-10-15T10:00:00Z", "updated_at": "2026-10-15T10:00:00Z",
  "laboral": {
    "disponibilidad": "disponible",
    "estado": "SinVinculoVigente", "puesto": null, "unidad": null
  },
  "trayectoria": { "disponibilidad": "disponible", "hechos": [] },
  "capacidades": { "desarrollo": false, "documentos": false, "registrar_ingreso": false, "exportar": false },
  "acciones": { "editar": true }
} }
```

- `laboral.disponibilidad` y `trayectoria.disponibilidad`: `disponible` o `no_disponible`. En `no_disponible`, el resto
  del bloque va en `null`/vacío y no equivale a ausencia confirmada.
- `trayectoria.hechos[]`: `{ "fecha_efectiva": "2026-11-01", "tipo": "Contratacion", "programado": true, "detalle": "…" }`,
  por `fecha_efectiva` descendente; `programado` es `true` si la fecha es posterior a hoy en la zona del tenant.
- `capacidades.*`: ya combinan instalación de la funcionalidad y permiso; hoy siempre `false`.
- `acciones.editar`: `true` solo con la capacidad `admin`; en `mi-ficha` siempre `false`.
- Ningún campo de adecuación, potencial, criticidad, salario, nómina, cuentas bancarias, idiomas, periodo de prueba ni
  suspensión.

## Campos editables

`tipo_identificacion` (`Cedula`|`Pasaporte`|`Otro`), `numero_identificacion` (≤ 50), `nombres` (1–150), `apellidos`
(1–150), `fecha_nacimiento`, `nacionalidad` (≤ 100), `genero` (≤ 80), `estado_civil` (≤ 80), `numero_hijos` (entero ≥ 0),
`direccion` (≤ 500), `telefono` (≤ 30, `^\+?[0-9 ()\-]+$`), `correo_contacto` (≤ 254, formato válido),
`emergencia_nombre` (≤ 150), `emergencia_relacion` (≤ 100), `emergencia_telefono` (≤ 30, mismo patrón).

- Texto: se recorta; solo espacios cuenta como vacío. Opcionales vacíos se guardan como `null`.
- `fecha_nacimiento`: `YYYY-MM-DD`, no posterior a hoy en la zona horaria del tenant (hoy es válido).
- Contacto de emergencia: o los tres campos vacíos, o `emergencia_nombre` y `emergencia_telefono` llenos.
- **No asignables** (422 `CAMPO_NO_PERMITIDO`, un error por campo): `id`, `codigo`, `tenant_id`,
  `identificacion_clave`, `*_busqueda`, `version` (en `POST`), `created_*`, `updated_*`, `operacion_*`, `estado`,
  `puesto`, `unidad`, `estado_laboral`, `usuario_id` y cualquier otro campo no declarado.

## POST /api/colaboradores

Cabecera obligatoria `Idempotency-Key: <uuid>`. Crea la ficha con `version` 1 y código `EMP-###`, `created_by` del
actor; no crea cuenta, vínculo, documentos ni desarrollo.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 201 | Primera creación | `{ "data": <detalle> }` |
| 200 | Repetición con la misma clave, mismo cuerpo y mismo actor | `{ "data": <detalle> }` de la ficha ya creada |
| 422 `CLAVE_DE_OPERACION_REQUERIDA` | Falta o no es UUID | `message`, `code` |
| 422 `CLAVE_DE_OPERACION_REUTILIZADA` | Misma clave con otro cuerpo u otro actor | `message`, `code` |
| 422 | Validación | `errors` por campo (`numero_identificacion`, `emergencia_nombre`, …) |
| 422 `IDENTIFICACION_DUPLICADA` | Tipo y número ya usados en el tenant | `errors.numero_identificacion` y `ficha_existente: { "id": 12, "codigo": "EMP-001" }` |
| 503 `ESCRITURA_DESHABILITADA` | Bandera de rollback desactivada | `message`, `code` |

## GET /api/colaboradores

Parámetros: `q` (≤ 50; nombres, apellidos, código o puesto; contiene; sin distinguir mayúsculas ni tildes), `estado`
(`Todos` por defecto, o un valor de `estado` ofrecido por `/filtros`), `unidad_id`, `page` (≥ 1), `per_page` (1–50, por
defecto 20). Orden: apellidos, nombres y código ascendentes. Todos los filtros se combinan con AND; la búsqueda por
código, nombre y puesto es un OR interno.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Siempre | `{ "data": [<listado>…], "links": {…}, "meta": { "current_page", "per_page", "total", "last_page", "from", "to" } }` |
| 422 | `estado`, `unidad_id`, `per_page` o `page` inválidos, o `unidad_id` con Estructura no instalada | errores por campo |
| 503 `INFORMACION_NO_DISPONIBLE` | Un lector necesario para el filtro activo falló | `message`, `code` |

## GET /api/colaboradores/filtros

Antes de `/{id}` en las rutas. Respuesta 200:

```json
{ "data": { "estados": [ { "valor": "Todos" }, { "valor": "SinVinculoVigente" } ],
            "unidades": [] } }
```

`estados` contiene `Todos` y solo los estados que tiene al menos una ficha del tenant; `unidades`,
`{ "id", "nombre" }` de las que Estructura publique (vacío si no está instalada). Si el lector falla:
`unidades` omitido con `"unidades_disponibles": false`.

## GET /api/colaboradores/{id}

200 con `{ "data": <detalle> }`; 404 `FICHA_NO_ENCONTRADA`. Los bloques `laboral` y `trayectoria` fallan de forma
independiente sin impedir el 200.

## PATCH /api/colaboradores/{id}

Cuerpo: solo los campos editables que cambian, más `version` obligatoria (la del detalle abierto). Un campo omitido no
cambia; `null` vacía un opcional; los obligatorios nunca pueden ser `null`. Si no hay cambios reales y la versión
coincide: 200 sin escribir.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Actualizada o sin cambios | `{ "data": <detalle> }` (con `version` nueva si hubo cambios) |
| 404 `FICHA_NO_ENCONTRADA` | Inexistente, de otro tenant | — |
| 409 `FICHA_MODIFICADA` | `version` obsoleta | `message` («La ficha cambió. Actualiza la información.») |
| 422 | Validación del estado resultante | `errors` por campo |
| 422 `IDENTIFICACION_DUPLICADA` | La nueva identificación la usa otra ficha | `errors.numero_identificacion`, `ficha_existente` |
| 503 `ESCRITURA_DESHABILITADA` | Bandera de rollback desactivada | — |

Conservar la propia identificación o cambiar solo sus mayúsculas no es duplicado. El código, el `id` y las
asociaciones no cambian.

## GET /api/mi-ficha

Solo `auth:sanctum`. Devuelve la ficha asociada a la cuenta, en el mismo tenant, si hay permiso de consulta de ficha.
Sin parámetros: no admite elegir otra ficha.

| Código | Cuándo | Cuerpo |
|---|---|---|
| 200 | Asociación válida y `puede_consultar_ficha` | `{ "data": <detalle> }` con `acciones.editar=false` |
| 403 `CUENTA_SIN_FICHA` | La cuenta no tiene ficha asociada | `message`: «Tu cuenta no tiene una ficha asociada.» |
| 403 `SIN_PERMISO_FICHA` | Hay asociación pero no permiso | `message`: «No tienes permiso para consultar tu ficha.» |

`capacidades.documentos` es `true` solo si la funcionalidad está instalada **y** `puede_consultar_documentos`.

## Trazabilidad

| Criterio | Cubierto por |
|---|---|
| GP01-CA01, RN02, RN04 | `POST` 201 y 200 por reintento; código `EMP-1000` |
| GP01-CA02, RN01, RN03 | `POST` y `PATCH` 422 `IDENTIFICACION_DUPLICADA`; mismo número en otro tenant → 201 |
| GP01-CA03, RN05 | `PATCH` conserva `id`/`codigo`; 422 sin cambios |
| GP01-CA04, RC-01–04 | 401, 403, 404 uniforme, `mi-ficha`; `tenant_id` en cuerpo → 422 |
| GP01-CA05, RN08 | `null` → «No registrado»; `noInstalado` vs `no_disponible` |
| GP01-CA06, RC-10 | `PATCH` 409 `FICHA_MODIFICADA` |
