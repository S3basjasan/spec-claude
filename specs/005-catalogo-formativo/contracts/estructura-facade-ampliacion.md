# Contrato: ampliación de la Facade de Estructura (catálogo formativo)

Amplía `App\Modules\Estructura\Presentation\Facades\EstructuraFacade` (ver
`specs/001-diccionario-competencias/contracts/estructura-facade.md`). Sigue siendo la **única** puerta de los demás módulos: el
módulo `Desarrollo` no accede a las tablas `competencias`, `competencia_grados` ni a ninguna otra de Estructura.

## Método nuevo

| Método | Devuelve | Notas |
|---|---|---|
| `getTodasLasCompetencias(): array` | Lista de `{id, codigo, nombre, descripcion, tipo, estado}` de **todas** las competencias, activas e inactivas, ordenada por nombre | Sin grados (se piden con `getCompetencia` solo para el detalle). Necesario para la matriz del Administrador, que filtra por estado |

## Métodos existentes que usa el catálogo

| Método | Uso en el catálogo |
|---|---|
| `getCompetencia(int $id)` | Detalle: descripción, tipo, estado y grados A/B/C/D (solo lectura) |
| `getCompetenciasPorIds(array $ids)` | Resolver nombre y tipo de las competencias en recomendados e historial |
| `estaActiva(int $id)` | Validar que una **nueva** asociación solo se cree con competencias activas |
| `existeCompetencia(int $id)` | Responder 404 en detalle, guardado e historial |
| `getCompetenciasActivas()` | Base del cálculo de cobertura y de la vista del colaborador |

## Reglas

- Los datos de la competencia se leen siempre de la Facade; `Desarrollo` solo guarda su `id`.
- La ampliación es aditiva: no cambia la firma ni el comportamiento de ningún método existente.
- Se añade una prueba Pest que fija la forma del arreglo devuelto, incluyendo las inactivas.
