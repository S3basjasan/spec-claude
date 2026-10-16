# Contrato: contratos entre módulos de GP-02

Ningún módulo lee clases ni tablas internas de otro; la colaboración usa Facades y puertos. Las lecturas devuelven
arreglos (los modelos no salen del módulo).

## `EstructuraFacade` (nuevos métodos, módulo Estructura)

| Método | Descripción |
|---|---|
| `getPosicion(int $id): ?array` | `{id, tenant_id, estado, puesto_id, puesto, puesto_activo, unidad_id, unidad, unidad_activa, jefatura}`; `jefatura` es `null` hasta que Estructura la publique |
| `getPosicionesPorIds(array $ids): array` | Lo mismo para varias, incluidas las suprimidas (histórico) |
| `posicionesAsignables(int $tenantId, ?string $texto, int $pagina, int $porPagina): array` | `{items: [{id, puesto, unidad, jefatura}], total}`: del tenant, no suprimidas, con puesto activo y unidad activa; búsqueda por puesto o unidad sin tildes; **sin** filtrar por ocupación |
| `posicionEsAsignable(int $id, int $tenantId): array` | `{asignable: bool, motivo: null\|'inexistente'\|'suprimida'\|'puesto_inactivo'\|'unidad_inactiva'\|'otro_tenant'}`; se evalúa en la conexión y transacción del llamador y **no** abre otra |
| `getPosicionesPorPuesto(string $texto): array` | ids de posición cuyo puesto contiene el texto (normalizado) |
| `getPosicionesPorUnidad(int $unidadId): array` | ids de posición de la unidad |
| `getUnidadesActivas()` | Ya existe (005); se usa para el filtro de unidad de GP-01 |

## `PersonalFacade` (nuevo método, módulo GestionPersonal)

| Método | Descripción |
|---|---|
| `posicionesConAsignacionVigenteOFutura(array $posicionIds, string $fecha): array` | ids, entre los dados, con una asignación que no terminó efectivamente en o antes de `fecha` (fin nulo o `fin > fecha`) |

Lo consumen los casos de uso `CambiarEstadoPuesto` y `CambiarEstadoUnidad` mediante el puerto
`OcupacionDePosicionesPort` de Estructura (adaptador `OcupacionPorPersonalFacade`).

## Puertos de GP-01 que GP-02 implementa

| Puerto | Adaptador nuevo | Cambios |
|---|---|---|
| `LectorLaboralPort` | `EloquentLectorLaboral` | Implementa los cinco métodos y añade `estadosPresentes(int $tenantId, string $fecha): Lectura` |
| `LectorEstructuraPort` | `EstructuraFacadeLector` | `posiciones`, `posicionesPorPuesto`, `posicionesPorUnidad` y `unidades` sobre `EstructuraFacade` |
| `LectorDeCapacidadesPort` | `LectorDeCapacidadesGp02` | `registrar_ingreso` → `true`; el resto, `false` |
| `PosicionesAsignablesPort` (nuevo) | `PosicionesAsignablesEstructura` | `listar(tenantId, texto, pagina, porPagina)` y `verificar(posicionId, tenantId)` sobre `EstructuraFacade` |

Las lecturas de un origen caído devuelven `Lectura::noDisponible()`; nunca valores inventados.

## Cambios de comportamiento en funcionalidades existentes

| Funcionalidad | Cambio | Prueba que lo cubre |
|---|---|---|
| 004 Puestos | Desactivar rechaza también si una posición del puesto tiene asignación vigente o futura; el conteo del mensaje las suma | `CambiarEstadoPuestoOcupacionTest` |
| 005 Unidades | Igual para la unidad y su rama; el mensaje indica unidades y cantidad | `CambiarEstadoUnidadOcupacionTest` |
| 005 Fichas (GP-01) | Estados `Activo` y `Desvinculado`; `SinVinculoVigente` excluye desvinculados; `laboral.proximo_ingreso` | `ListarYConsultarTest` ampliada |
| Estructura (`posiciones`) | Columna `tenant_id` con valor por defecto 1 | `PosicionesAsignablesTest` |

## Orden de bloqueo

Todo escritor laboral toma, en este orden: (1) la fila de la ficha, (2) las posiciones por `id` ascendente, (3) el
vínculo. Movimientos y salidas futuros deben respetarlo.
