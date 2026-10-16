# Contrato: Facade del módulo Estructura — posiciones

Amplía la Facade de 001–005 (`App\Modules\Estructura\Presentation\Facades\EstructuraFacade`). Sigue siendo la **única** puerta de los demás módulos: no acceden a clases internas ni a la tabla `posiciones`. Es de **solo lectura**: ningún módulo consumidor puede modificar posiciones.

## Consulta (incluye posiciones Suprimidas, para registros históricos)

| Método | Devuelve | Notas |
|---|---|---|
| `getPosicion(int $id): ?array` | `{id, codigo, estado, modalidad, puesto: {id, codigo, nombre}, unidad: {id, codigo, nombre, estado}, supervisor: {id, codigo, nombre} \| null, ocupante_id: int \| null, subordinadas_vigentes}` o `null` | No incluye auditoría |
| `getPosicionesPorIds(array $ids): array` | Lista de los anteriores para los ids que existen | Para mostrar registros históricos sin duplicarlos |
| `existePosicion(int $id): bool` | `true` si existe, también Suprimida | |
| `posicionEstaVigente(int $id): bool` | `true` si existe y es Ocupada o Vacante | Para el módulo de personal y otros consumidores |
| `getArbolDeSupervision(): array` | Todas las posiciones no Suprimidas con su `supervisor`, en orden de árbol | Para el organigrama y el perfilamiento |

## Contrato inverso con el módulo de gestión de personal (feature posterior)

El módulo de personal es el único que cambia el ocupante y el paso Ocupada ⇄ Vacante. MUST:

- Ocupar solo posiciones `Vacante` y liberar solo `Ocupada`, con una sentencia condicional que compruebe el estado en la misma operación (así no se cruza con **Suprimir**, que exige `Vacante`).
- Escribir `user_id` y `estado` juntos (Ocupada ⇔ `user_id` no nulo) y registrar `user_id` y `estado` en `auditoria_cambios` con `entidad = 'posicion'`.
- No ocupar posiciones Suprimidas.

## Reglas de uso para los módulos consumidores

- Guardan solo el `id` de la posición; código, puesto, unidad, supervisor y estado se leen siempre de este módulo (fuente única, FR-033).
- Las referencias a posiciones Suprimidas se conservan y siguen resolviéndose; como las posiciones nunca se eliminan, un `id` guardado es válido para siempre.
- Los cambios de unidad, supervisor o modalidad aplican a su ocupante actual: no hay versionado (FR-035).

## Garantías

- Solo arreglos con los campos indicados; nunca modelos ni entidades.
- No se expone la auditoría, los nombres de usuario ni el historial de cambios.
