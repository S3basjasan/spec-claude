# Contrato: Facade del módulo Estructura — comportamientos

Amplía la Facade de 001 (`App\Modules\Estructura\Presentation\Facades\EstructuraFacade`, ver
`specs/001-diccionario-competencias/contracts/estructura-facade.md`). Sigue siendo la **única** puerta de los
demás módulos: no acceden a clases internas ni a las tablas `competencia_comportamiento_registros` ni
`competencia_comportamientos`.

## Consulta (incluye competencias inactivas, para registros históricos)

| Método | Devuelve | Notas |
|---|---|---|
| `getComportamientos(int $competenciaId): ?array` | `{competencia_id, codigo, nombre, descripcion, tipo, estado, comportamientos: {A:[…], B:[…], C:[…], D:[…]}}` o `null` | `null` si la competencia no tiene registro; los cuatro grados siempre presentes; datos de competencia actuales |
| `getComportamientosPorCompetencias(array $competenciaIds): array` | Lista de las anteriores para las competencias que tienen registro | Para mostrar datos de registros históricos sin duplicarlos |

## Nuevas asignaciones (solo competencias activas)

| Método | Devuelve | Notas |
|---|---|---|
| `tieneComportamientosActivos(int $competenciaId): bool` | `true` solo si la competencia existe, está `Activo` y tiene registro | Un módulo MUST llamarla antes de guardar una **nueva** asignación (FR-020) |
| `getComportamientosActivos(): array` | Lista de registros (mismos campos) de competencias `Activo`, ordenada por nombre | Para selectores de nuevas asignaciones |

## Reglas de uso para los módulos consumidores

- Guardan solo el `competencia_id` (la competencia identifica su registro); nombre, tipo, estado y
  comportamientos se leen siempre del diccionario (fuente única, FR-019).
- Una **nueva** asignación MUST usar solo comportamientos de competencias activas; la regla la cumple cada módulo
  con `tieneComportamientosActivos`.
- Las referencias existentes a comportamientos de competencias que pasan a inactivas **se conservan** y siguen
  resolviéndose con `getComportamientos` / `getComportamientosPorCompetencias`.
- Como ni las competencias ni los registros se eliminan, un `competencia_id` guardado sigue siendo válido para
  siempre.

## Garantías

- Solo arreglos con los campos indicados; nunca modelos ni entidades.
- No se expone la auditoría ni el historial de estado (este último vive en 001).
