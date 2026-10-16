# Puertos y Facades de la feature 013

## Facade propia: `DesarrolloFacade` (ampliación)

Presentación pública del módulo `Desarrollo`. Los demás módulos solo conocen estos métodos.

| Método | Consumidor | Contrato |
|---|---|---|
| `crearBorradorDesdeDnf(int $tenantId, int $actorId, int $dnfId, array $brechas): int` | DNF (006, vía el adaptador `PlanDeFormacionDesdePlanes`) | `$brechas` = `[{colaborador_id, competencia_id, nivel_requerido, nivel_actual, diferencia, prioridad}]` (la forma que ya envía `GenerarPlanDesdeDnf`). Crea un plan **Borrador** del año actual, nombre `Plan DNF {código DNF}`, `origen_dnf_id`, una acción por competencia con brecha (nombre de la competencia como nombre, esa competencia como objetivo, demás datos nulos) y los colaboradores con brecha como participantes. Devuelve el id del plan. Lanza excepción si no hay brechas. |
| `getAccionesDePlanesEjecutando(int $tenantId): array` | Actividades Formativas (pendiente) | `[{accion_id, plan_id, nombre, tipo, fecha_inicio, fecha_fin, participantes: [colaborador_id]}]` de acciones **no canceladas** de planes en estado Ejecutando. Solo lectura. |
| `getPlanesYAcciones(int $tenantId, array $filtros = []): array` | Reportes (pendiente) | Filtros `anio`, `estado`, `plan_id`. Devuelve planes `{id, codigo, anio, estado, costo_total, horas_totales}` con acciones `{id, tipo, horas, costo, estado_accion, inscritos}`. Solo lectura. |

## Puertos nuevos de `Desarrollo` (Application/Ports)

| Puerto | Responsabilidad | Adaptador | Estado |
|---|---|---|---|
| `ColaboradoresVigentesInterface` | `buscar(tenantId, ?texto, pagina, porPagina, fecha)`: colaboradores con vínculo vigente `{id, codigo, nombre}` en la fecha; `vigentesPorIds(tenantId, ids, fecha)`: subconjunto con vínculo vigente | `ColaboradoresVigentesDesdePersonal` → `PersonalFacade::buscarColaboradoresVigentes` y `vigentesPorIds` | Definitivo; requiere ampliación **aditiva** de GP |
| `AprobadoresInterface` | `administradores(tenantId)`: `[{id, nombre}]`; `esAdministradorActivo(tenantId, userId)` | `AprobadoresDesdeShared` → `SharedFacade::getAdministradores(tenantId)` | Definitivo; ampliación **aditiva** de Shared |
| `AsistenciaInterface` | `presentesPorAccion(accionIds, colaboradorId)`: ids de acciones con asistencia Presente | `AsistenciaSinDatos` (devuelve vacío) | **Provisional**: Actividades Formativas aún no existe; al implementarla se cambia el binding |
| `RelojInterface` | `hoy(usuarioId)`: fecha actual en la zona horaria del tenant del usuario | `RelojDelSistema` → `SharedFacade::getContextoDeTenant` | Definitivo |

Puertos ya existentes que se reutilizan: `CompetenciasInterface` (activas y por ids, vía `EstructuraFacade`), `CuentaColaboradorInterface` (usuario → colaborador), `NombresDeUsuarioInterface`, `NombresDeReferenciaInterface` y el puerto de 006 `PlanDeFormacionInterface`, cuyo binding `PlanDeFormacionNoDisponible` se **reemplaza** por `PlanDeFormacionDesdePlanes` (el adaptador de prueba `PlanDeFormacionDePrueba` de 006 se eliminó: el E2E usa el real). En el entorno `e2e` solo `ColaboradoresVigentesDePrueba` sustituye a `ColaboradoresVigentesDesdePersonal` (las tres fichas sembradas); los aprobadores y el reloj usan los adaptadores reales.

## Ampliaciones aditivas en otros módulos

Solo lectura, sin cambiar firmas existentes:

- `PersonalFacade::buscarColaboradoresVigentes(int $tenantId, ?string $texto, int $pagina, int $porPagina, string $fecha): array` → `{items: [{id, codigo, nombre}], total}`.
- `PersonalFacade::vigentesPorIds(int $tenantId, array $ids, string $fecha): array` → ids con vínculo vigente.
- `SharedFacade::getAdministradores(int $tenantId): array` → `[{id, nombre}]` de usuarios activos con capacidad `admin`.

Cada ampliación se cubre con una prueba Pest en su módulo y se documenta en su `@method`.

## Cambio en 006

`PlanDeFormacionDesdePlanes::crearBorrador()` delega en el caso de uso `CrearBorradorDesdeDnf`. `GenerarPlanDesdeDnf` y el contrato de 006 no cambian; `DnfAdaptadoresPorEntornoTest` ahora espera el adaptador real en todos los entornos; los demás tests de 006 usan el doble `PlanDeFormacionFalso` y no cambian. El adaptador provisional `PlanDeFormacionNoDisponible` se eliminó.
