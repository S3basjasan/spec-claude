# Contrato: categorías por unidad (01b.4)

Base: `/api/compensaciones`. Lectura: sesión Sanctum (401). Gestión: capacidad `admin` / permiso `categorias.gestionar` (403). Errores de validación: `422 {message, errors: {campo: [mensaje]}}`.

## POST `/categorias` · PUT `/categorias/{id}` (admin)

Body (además de los campos existentes):

| Campo | Tipo | Regla |
|---|---|---|
| `unidades_ids` | `int[]` | Distintos, existentes y activos. Obligatorio salvo que `todas_las_unidades` sea `true` |
| `todas_las_unidades` | `bool` | Si es `true`, se resuelve a las unidades activas del momento y se ignora `unidades_ids` |

Errores 422 (campo `unidades_ids`):
- Ausente, vacío o con valores no enteros: «Elige al menos una unidad.»
- Alguna unidad no existe o está inactiva: «La unidad seleccionada ya no está activa.»

`PUT` sigue exigiendo la versión leída (409 si cambió). Si las unidades no cambian no se audita.

## GET `/categorias` (autenticado)

Parámetros existentes (`q`, `estado`, `competencia_id`, `page`). El alcance lo decide el servidor:

| Quien consulta | Resultado |
|---|---|
| `admin` (Gestor; Gerencia en el repo de prueba) | Todas (activas e inactivas según `estado`), con `unidades` |
| Líder (`EquipoPort::esLider`) | Todas las activas, con `unidades`; el filtro `estado` se ignora |
| Otros con ficha y unidad | Solo activas asignadas a su unidad actual, **sin** `unidades` |
| Sin ficha o sin unidad | `data: []` |

Cada ítem agrega (cuando corresponde):

```json
{
  "unidades": [{ "id": 3, "nombre": "Operaciones", "activa": true }],
  "aplica_a_todas": false,
  "sin_unidades_activas": false
}
```

`sin_unidades_activas` solo se informa al Gestor. Una categoría sin unidades (datos previos) lo hace `true`, con `unidades: []`.

## GET `/categorias/{id}` (autenticado)

Mismo alcance. Una categoría fuera del alcance de quien consulta responde `404` (igual que una inexistente). El `admin` ve cualquiera.

## GET `/unidades-disponibles` (admin)

Unidades activas para el selector, en orden jerárquico.

```json
{ "data": [{ "id": 3, "codigo": "UO-003", "nombre": "Operaciones", "nivel": 2 }] }
```

403 para quien no es `admin`.

## Contrato interno: `CategoriasFacade::seleccionablesParaUnidad(int $unidadId): array`

Devuelve las categorías **activas**, con competencia activa y asignadas a esa unidad, con la forma de `listarSeleccionables()`:

```php
[['id' => 1, 'codigo' => 'CAT-001', 'nombre' => '…', 'icono' => '…', 'competencia_id' => 7, 'puntuacion_base' => 50]]
```

- Unidad inexistente o inactiva → `[]`.
- Categoría desactivada, de otra unidad, con competencia inactiva o sin unidades → no aparece.
- Asignación directa: no hay herencia por jerarquía de unidades.
- Las funciones de desafíos (02.3) y reconocimientos (03.1) rechazan con 422 cualquier categoría que no esté en esta lista para la unidad de la persona.

`obtener(id)` y `obtenerPorIds(ids)` agregan `unidades_ids` y siguen sin filtrar por unidad (resuelven registros históricos).

## Auditoría

Campo `unidades`: anterior y nuevo como JSON de ids ordenados (`"[1,2,5]"`), con nulo anterior al crear.
