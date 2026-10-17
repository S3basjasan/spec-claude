# Contrato HTTP: categorías de reconocimiento

Prefijo `/api/compensaciones`. Todos los endpoints exigen `auth:sanctum` (sin sesión → 401). Los de escritura y `competencias-disponibles` exigen además la capacidad `admin` (sin ella → 403). No existe `DELETE`. Los mensajes de error no revelan información sensible.

## Representación `Categoria`

```json
{
  "id": 1,
  "codigo": "CAT-001",
  "nombre": "Trabajo en equipo",
  "descripcion": "Colabora de forma ...",
  "competencia": { "id": 3, "codigo": "COM-003", "nombre": "Colaboración", "estado": "Activo" },
  "competencia_activa": true,
  "seleccionable": true,
  "puntuacion_base": 100,
  "icono": "equipo",
  "estado": "Activo",
  "indicadores": [ { "orden": 1, "texto": "Comparte información ..." } ],
  "updated_at": "2026-10-16T14:03:22.123456Z",
  "created_by": 1, "updated_by": 1, "created_at": "...Z"
}
```

`created_by`, `updated_by` y `created_at` solo se incluyen para `admin`. En el listado, `indicadores` se omite.

## GET `/categorias`

Query: `q` (≤ 100; nombre de categoría o de competencia, sin distinguir mayúsculas ni tildes), `estado` (`Activo` | `Inactivo` | `Todos`, defecto `Activo`), `competencia_id`, `page`. 15 por página fijos; orden por `codigo` ascendente; página fuera de rango devuelve la última. Quien no es `admin` siempre recibe solo `Activo` (el filtro `estado` se ignora).
- 200 `{ data: [Categoria], meta: { page, per_page: 15, total, last_page } }`.
- 422 parámetro inválido.

## GET `/categorias/{categoria}`

200 con indicadores ordenados. Devuelve también categorías `Inactivo`. 404 si no existe.

## POST `/categorias` (admin)

Body: `nombre`, `descripcion`, `competencia_id`, `puntuacion_base`, `icono`, `indicadores` (lista de 1–5 textos).
Reglas: ver [data-model.md](../data-model.md) y spec FR-005…FR-016. `competencia_id` debe existir y estar activa. Cualquier clave `codigo`, `estado`, `orden`, `created_by`, `updated_by`, `created_at`, `id` → 422.
- 201 `Categoria` (Activa, con código asignado). 422 por validación. Auditoría en la misma transacción.

## PUT `/categorias/{categoria}` (admin)

Body igual al de crear más `updated_at` (obligatorio, el valor leído). La competencia solo se exige activa si cambia.
- 200 `Categoria` (reemplaza indicadores; sin cambios reales → 200 sin tocar `updated_at` ni auditoría).
- 404; 422; 409 `{ "message": "La categoría fue modificada por otro usuario. Recarga para ver los cambios." }` si `updated_at` no coincide (no se aplica nada).

## PATCH `/categorias/{categoria}/estado` (admin)

Body: `estado` (`Activo` | `Inactivo`), `updated_at`.
- 200 `Categoria`; si ya tiene ese estado y el token está vigente, sin cambios ni auditoría. Se puede activar una categoría con competencia inactiva.
- 404; 422; 409 igual que PUT.

## GET `/competencias-disponibles` (admin)

200 `{ data: [ { id, codigo, nombre, tipo } ] }` de competencias activas, ordenadas por nombre.

## GET `/competencias-filtro`

Opciones del filtro de competencia del listado. `auth:sanctum`, sin exigir `admin`. `admin` recibe competencias activas e inactivas; los demás, solo activas.
- 200 `{ data: [ { id, codigo, nombre, tipo, estado } ] }` ordenadas por nombre.

## Resumen de códigos

| Situación | Código |
|---|---|
| Sin sesión | 401 |
| Sin permiso | 403 |
| No existe | 404 |
| Datos inválidos, nombre repetido, competencia inactiva, campos prohibidos | 422 |
| `updated_at` desactualizado | 409 |
