# Contrato: Facade del módulo Estructura — unidades y tipos de unidad

Amplía la Facade de 001–004 (`App\Modules\Estructura\Presentation\Facades\EstructuraFacade`). Sigue siendo la **única** puerta de los demás módulos: no acceden a clases internas ni a las tablas `unidades_organizacionales` ni `tipos_unidad`. Es de **solo lectura**: ningún módulo consumidor puede modificar unidades ni tipos.

## Consulta de unidades (incluye inactivas, para registros históricos)

| Método | Devuelve | Notas |
|---|---|---|
| `getUnidad(int $id): ?array` | `{id, codigo, nombre, tipo: {id, nombre, nivel}, unidad_padre: {id, codigo, nombre} \| null, nivel_arbol, estado, fecha_creacion, fecha_supresion, dependientes_activas, posiciones_vigentes, admite_hijas, sigue_cadena}` o `null` | No incluye auditoría |
| `getUnidadesPorIds(array $ids): array` | Lista de los anteriores para los ids que existen | Para mostrar registros históricos sin duplicarlos |
| `existeUnidad(int $id): bool` | `true` si existe, activa o inactiva | |
| `getArbolDeUnidades(): array` | Todas las unidades en orden jerárquico, con los mismos campos | Para el organigrama |

## Consulta de la cadena de tipos (nuevo)

| Método | Devuelve | Notas |
|---|---|---|
| `getCadenaDeTipos(): array` | Lista `{id, nombre, nivel, es_raiz}` ordenada por nivel | La cadena vigente |
| `getTipoDeUnidad(int $id): ?array` | `{id, nombre, nivel, es_raiz}` o `null` | |

## Nuevas asignaciones (solo unidades activas)

| Método | Devuelve | Notas |
|---|---|---|
| `unidadEstaActiva(int $id): bool` | `true` solo si existe y está `Activa` | Un módulo MUST llamarla antes de asignar una **nueva** posición (FR-054) |
| `getUnidadesActivas(): array` | Lista `{id, codigo, nombre, tipo, nivel_arbol}` de unidades `Activa`, en orden jerárquico | Para el selector de unidad al crear posiciones |

## Reglas de uso para los módulos consumidores

- Guardan solo el `id` de la unidad; código, nombre, tipo, padre y estado se leen siempre de este módulo (FR-053).
- Una **nueva** posición MUST asignarse solo a una unidad activa. La Facade no la rechaza por sí misma.
- Las referencias a unidades que pasan a inactivas **se conservan** y se resuelven con `getUnidad` / `getUnidadesPorIds`; como nunca se eliminan, un `id` guardado es válido para siempre.
- Los cambios de nombre, de padre o del nombre del tipo aplican a todas las posiciones: no hay versionado (FR-055). El nivel de un tipo puede cambiar, pero el tipo de una unidad no.

## Contrato inverso con posiciones (feature posterior)

Esta feature **lee** `posiciones` para contar las `Ocupada` y `Vacante` de cada unidad y de su rama. A cambio, la feature de posiciones MUST:

- Mantener `unidad_organizacional_id` (FK a `unidades_organizacionales.id`, sin borrado en cascada; hoy anulable, pasará a obligatoria) y los estados `Ocupada` | `Vacante` | `Suprimida` con esos valores exactos.
- Exigir, **en la misma operación** que inserta o reasigna una posición, que su unidad esté `Activa`, para no abrir una ventana de carrera con la desactivación en cascada.
- Seguir considerando válida una posición cuya unidad se desactiva después (las `Suprimida` conservan su unidad como dato histórico).

## Garantías

- Solo arreglos con los campos indicados; nunca modelos ni entidades.
- No se expone la auditoría, los nombres de usuario ni el historial de cambios.
