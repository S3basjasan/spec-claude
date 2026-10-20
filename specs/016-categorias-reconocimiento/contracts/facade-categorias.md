# Contrato público: Facade de categorías (`CategoriasFacade`)

Único punto de acceso de Desafíos, Hitos y Ranking al catálogo (FR-036, FR-038). Sin endpoint HTTP. Se registra como `compensaciones-categorias-facade` en `CompensacionesServiceProvider`.

## Métodos

| Método | Devuelve |
|---|---|
| `listarSeleccionables(): array` | Categorías `Activo` cuya competencia está activa, ordenadas por código: `{id, codigo, nombre, icono, competencia_id, puntuacion_base}` |
| `obtener(int $id): ?array` | Categoría en cualquier estado, o `null`: `{id, codigo, nombre, descripcion, icono, competencia_id, competencia_activa, estado, seleccionable, puntuacion_base, indicadores: [{orden, texto}]}` |
| `obtenerPorIds(array $ids): array` | Las categorías existentes entre los ids, en cualquier estado (para historiales) |
| `esSeleccionable(int $id): bool` | `true` solo si existe, está `Activo` y su competencia está activa |

## Reglas

- Solo lectura: no modifica el catálogo.
- Hitos y desafíos MUST validar `esSeleccionable` al registrar un nuevo uso y rechazar (422) en caso contrario; los registros históricos se resuelven con `obtener`/`obtenerPorIds`.
- El hito copia `puntuacion_base` al otorgarse; este módulo no guarda esa copia (FR-037).
- Contrato hacia Estructura (`CompetenciasPort`): `activas()`, `porId(int): ?array{id,codigo,nombre,tipo,estado}`, `porIds(array)`, `idsPorNombre(string)` (resuelto sobre `getTodasLasCompetencias()`).
