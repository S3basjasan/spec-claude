# Contrato: Facade del módulo Estructura (diccionario de competencias)

Única puerta de los demás módulos hacia el diccionario. Clase:
`App\Modules\Estructura\Presentation\Facades\EstructuraFacade`. Es el **único** tipo de Estructura que otros
módulos pueden importar. Los módulos no acceden a clases internas ni a las tablas `competencias`,
`competencia_grados` ni `competencia_estado_historial`.

## Consulta (incluye competencias inactivas)

| Método | Devuelve | Notas |
|---|---|---|
| `getCompetencia(int $id): ?array` | `{id, codigo, nombre, descripcion, tipo, estado, grados: {A,B,C,D}}` o `null` | Los cuatro grados siempre presentes; devuelve también las inactivas |
| `getCompetenciasPorIds(array $ids): array` | Lista de las anteriores para los ids que existen | Para mostrar datos de registros históricos sin duplicarlos; incluye inactivas |
| `existeCompetencia(int $id): bool` | `true` si existe, activa o inactiva | Para comprobar referencias ya guardadas |

## Nuevas asignaciones (solo activas)

| Método | Devuelve | Notas |
|---|---|---|
| `estaActiva(int $id): bool` | `true` solo si existe y su estado es `Activo` | Un módulo MUST llamarla antes de guardar una **nueva** asignación (FR-019) |
| `getCompetenciasActivas(): array` | Lista de competencias `Activo` (mismos campos, ordenadas por nombre) | Para selectores de nuevas asignaciones |

## Reglas de uso para los módulos consumidores

- Los módulos guardan solo el `id` de la competencia; nombre, tipo, estado y grados se leen siempre del
  diccionario (fuente única, FR-018).
- Una **nueva** asignación MUST usar solo competencias activas; la Facade no la rechaza por sí misma, la regla
  la cumple cada módulo con `estaActiva`.
- Las referencias existentes a competencias que pasan a inactivas **se conservan** y se siguen resolviendo con
  `getCompetencia` / `getCompetenciasPorIds` (FR-019).
- Como las competencias nunca se eliminan, un `id` guardado sigue siendo válido para siempre; no hace falta
  declarar el uso ni registrar verificadores (se retira `registrarVerificadorDeUso`).

## Garantías

- La Facade nunca expone entidades ni modelos Eloquent, solo arreglos con los campos indicados.
- No expone datos de auditoría ni el historial de estado (fuera de alcance de esta feature).
