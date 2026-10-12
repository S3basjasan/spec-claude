# Contrato: Facade del módulo Estructura — preguntas

Amplía la Facade de 001 y 002 (`App\Modules\Estructura\Presentation\Facades\EstructuraFacade`, ver
`specs/001-diccionario-competencias/contracts/estructura-facade.md` y
`specs/002-diccionario-comportamientos/contracts/estructura-facade.md`). Sigue siendo la **única** puerta de los demás
módulos: no acceden a clases internas ni a las tablas `competencia_pregunta_registros` ni `competencia_preguntas`.

## Consulta (incluye competencias inactivas, para registros históricos)

| Método | Devuelve | Notas |
|---|---|---|
| `getPreguntas(int $competenciaId): ?array` | `{competencia_id, codigo, nombre, descripcion, tipo, estado, preguntas: [«1», «2», «3», «4»]}` o `null` | `null` si la competencia no tiene registro; siempre 4 textos en orden; datos de competencia actuales |
| `getPreguntasPorCompetencias(array $competenciaIds): array` | Lista de las anteriores para las competencias que tienen registro | Para mostrar datos de registros históricos sin duplicarlos |

## Nuevas asignaciones (solo competencias activas)

| Método | Devuelve | Notas |
|---|---|---|
| `tienePreguntasActivas(int $competenciaId): bool` | `true` solo si la competencia existe, está `Activo` y tiene registro | Un módulo MUST llamarla antes de guardar una **nueva** asignación (FR-020) |
| `getPreguntasActivas(): array` | Lista de registros (mismos campos) de competencias `Activo`, ordenada por nombre | Para selectores de nuevas asignaciones |

## Reglas de uso para los módulos consumidores

- Guardan solo el `competencia_id`; nombre, tipo, estado y preguntas se leen siempre del diccionario (fuente única,
  FR-019).
- Una **nueva** asignación MUST usar solo preguntas de competencias activas (`tienePreguntasActivas`).
- Las referencias existentes a preguntas de competencias que pasan a inactivas **se conservan** y siguen resolviéndose
  con `getPreguntas` / `getPreguntasPorCompetencias`.
- Como ni las competencias ni los registros se eliminan, un `competencia_id` guardado sigue siendo válido para siempre.

## Garantías

- Solo arreglos con los campos indicados; nunca modelos ni entidades.
- No se expone la auditoría ni el historial de estado (este último vive en 001).
