# Contrato: Facade del módulo Estructura — puestos

Amplía la Facade de 001, 002 y 003 (`App\Modules\Estructura\Presentation\Facades\EstructuraFacade`, ver
`specs/001-diccionario-competencias/contracts/estructura-facade.md`). Sigue siendo la **única** puerta de los demás
módulos: no acceden a clases internas ni a las tablas `puestos`, `puesto_funciones`, `puesto_requisitos`,
`puesto_competencias` ni `auditoria_cambios`. Es de **solo lectura**: ningún módulo consumidor puede modificar puestos.

## Consulta (incluye puestos inactivos, para registros históricos)

| Método | Devuelve | Notas |
|---|---|---|
| `getPuesto(int $id): ?array` | `{id, codigo, nombre, descripcion, mision, estado, funciones: [{orden, descripcion, tipo}], requisitos: [{tipo, descripcion}], competencias: [{competencia_id, codigo, nombre, tipo, estado, grado_requerido}]}` o `null` | `null` si no existe; datos de competencia actuales; incluye puestos `Inactivo` |
| `getPuestosPorIds(array $ids): array` | Lista de los anteriores para los ids que existen | Para mostrar datos de registros históricos sin duplicarlos |
| `existePuesto(int $id): bool` | `true` si existe, activo o inactivo | Para comprobar referencias ya guardadas |

## Nuevas posiciones (solo puestos activos)

| Método | Devuelve | Notas |
|---|---|---|
| `puestoEstaActivo(int $id): bool` | `true` solo si el puesto existe y su estado es `Activo` | Un módulo MUST llamarla antes de crear una **nueva** posición (FR-031) |
| `getPuestosActivos(): array` | Lista `{id, codigo, nombre, descripcion}` de puestos `Activo`, ordenada por nombre | Para el selector de puesto al crear posiciones |

## Reglas de uso para los módulos consumidores

- Guardan solo el `id` del puesto; código, nombre, misión, funciones, requisitos y competencias se leen siempre del
  catálogo (fuente única, FR-030).
- Una **nueva** posición MUST usar solo puestos activos (`puestoEstaActivo`). La Facade no la rechaza por sí misma.
- Las referencias existentes a puestos que pasan a inactivos **se conservan** y siguen resolviéndose con `getPuesto` /
  `getPuestosPorIds` (FR-031).
- Como los puestos nunca se eliminan, un `id` guardado sigue siendo válido para siempre.
- Los cambios en funciones, requisitos o competencias de un puesto aplican a todas sus posiciones: no hay versionado
  (FR-033).

## Contrato inverso con posiciones (feature 005)

Esta feature **lee** `posiciones` solo para contar las que impiden desactivar un puesto. A cambio, la feature 005 MUST:

- Mantener en `posiciones` las columnas `puesto_id` (FK a `puestos.id`, sin borrado en cascada) y `estado`
  (`Ocupada` | `Vacante` | `Suprimida`), con esos valores exactos. La tabla mínima creada aquí es el punto de partida;
  005 la amplía con migraciones propias y no la recrea.
- Exigir, **en la misma operación** que inserta una posición nueva, que su puesto esté `Activo`. Así, comprobar
  "sin posiciones en uso" y desactivar (una sola sentencia atómica) no deja una ventana de carrera.
- Seguir considerando válida una posición cuyo puesto se desactiva después de crearla (se conserva y es consultable).

## Garantías

- Solo arreglos con los campos indicados; nunca modelos ni entidades.
- No se expone la auditoría ni el historial de cambios (la consulta del historial es una feature posterior).
