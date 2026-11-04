# Data Model: Ajustes técnicos del modelo de categorías (01b.5)

Fuente: `modelo-de-datos.dbml` (tabla `categorias_reconocimiento`). `unidades_ids` pertenece a 01b.4 y **no** se agrega aquí.

## Antes (inc-01) → Después (01b.5)

### `categorias_reconocimiento`

| Columna | Antes | Después |
|---|---|---|
| `id`, `codigo`, `nombre`, `nombre_normalizado`, `descripcion`, `puntuacion_base`, `icono`, `estado`, `created_by`, `updated_by`, timestamps(6) | sin cambios | sin cambios |
| `competencia_id` | `bigint`, FK `constrained('competencias') restrictOnDelete` | `bigint` sin FK (relación lógica; se valida por `CompetenciasPort` → `EstructuraFacade`) |
| `indicadores` | no existe | `json`, lista ordenada de 1 a 5 textos de 5 a 250 caracteres |

Índices y unicidad se mantienen (`codigo`, `nombre_normalizado`, índice de `estado`). Se pierde el índice único de la tabla eliminada `(categoria, texto_normalizado)`: la unicidad de indicadores pasa a garantizarse solo en Dominio y en la validación de entrada (ver reglas).

### `indicadores_reconocimiento`

Se elimina. En `down()` se recrea con su esquema original: `id`, `categoria_reconocimiento_id` (FK restrict), `orden` (1..5), `texto` (250), `texto_normalizado` (250), timestamps, únicos `(categoria, orden)` y `(categoria, texto_normalizado)`.

## Formato de `indicadores`

```json
["Comparte información con su equipo", "Ayuda a quien lo necesita"]
```

- El orden es la posición en la lista; la API sigue devolviendo `orden` 1..n derivado.
- Es el mismo formato que el valor de auditoría del campo `indicadores` (`Categoria::indicadoresComoJson()`, JSON sin escapar Unicode), por lo que CA-07 no cambia.
- Lectura: cast `array` del modelo. Escritura en `UPDATE` condicional: `json_encode(..., JSON_UNESCAPED_UNICODE)`.

## Reglas de validación (sin cambios)

| Regla | Dónde se aplica |
|---|---|
| 1 a 5 indicadores | `Categoria::indicadoresDesde` (Dominio) y `GuardarCategoriaRequest` |
| 5 a 250 caracteres, sin HTML | `Indicador` (Dominio) y `GuardarCategoriaRequest` |
| Sin repetidos, sin distinguir mayúsculas ni tildes | `Categoria::indicadoresDesde` con `Normalizador` y `GuardarCategoriaRequest` |
| Competencia existente | `CompetenciasPort` (Facade de Estructura) |

## Transiciones y migración de datos

`up()`: por cada categoría, `indicadores = textos ordenados por orden` (o `[]`). `down()`: por cada categoría, una fila legacy por elemento (`orden = posición + 1`, `texto_normalizado = Normalizador::normalizar(texto)`), luego quita la columna y restaura la FK de `competencia_id` (aborta sin cambios si hay competencias inexistentes).

## Entidades de dominio

Sin cambios: `Categoria` (con `array<Indicador>`), `Indicador` (`texto`, `orden`, `normalizado()`), `Normalizador`. Solo cambia cómo la infraestructura las lee y las guarda.
