# Puertos y Facades de la feature 017

## Facade propia: `DesarrolloFacade` (ampliación aditiva, solo lectura)

Ningún consumidor externo puede escribir actividades ni asistencia. Ninguna respuesta incluye `observaciones`.

| Método | Consumidor | Contrato |
|---|---|---|
| `getAsistenciaDeColaborador(int $tenantId, int $colaboradorId, array $accionIds): array` | Plan de Formación (013) / externos | `[{accion_id, actividad_id, estado, asistencia, calificacion}]` de las acciones indicadas que tienen actividad y registro del colaborador; la asistencia sin registro se reporta `Pendiente`. |
| `tieneAsistenciaRegistrada(int $tenantId, int $accionId, int $colaboradorId): bool` | Plan de Formación (013) / externos | `true` si existe fila en `actividad_asistencias` del colaborador para la actividad de esa acción. |
| `getActividades(int $tenantId, array $filtros = []): array` | Reportes (6) | Filtros `anio`, `plan_id`, `tipo`, `estado`, `departamento_id`, `competencia_id`, `fecha_desde`, `fecha_hasta`. Devuelve `[{actividad_id, codigo, accion_id, plan_id, nombre, tipo, estado, fecha_inicio, fecha_fin, horas, departamento_id, inscritos, calificados, promedio}]`. |
| `getAsistencias(int $tenantId, array $filtros = []): array` | Reportes (6) | Mismos filtros más `colaborador_id`, `actividad_id`. Devuelve `[{actividad_id, accion_id, plan_id, colaborador_id, asistencia, calificacion, horas, fecha_inicio, fecha_fin}]`; solo participantes con registro (los Pendientes no se materializan). Las horas son las de la acción. |

`getAccionesDePlanesEjecutando` (feature 013) sigue vigente y no cambia.

## Puertos nuevos de `Desarrollo` (Application/Ports)

| Puerto | Responsabilidad | Adaptador | Estado |
|---|---|---|---|
| `AccionesDePlanInterface` | `porIds(tenantId, accionIds)`, `elegibles(tenantId, idsConActividad, planId, q, pagina, porPagina)`, `obtener(tenantId, accionId)`, `deColaborador(tenantId, colaboradorId)`, `planes(tenantId)`: datos de la acción y de su plan, siempre vigentes (R1) | `AccionesDesdePlan` → `PlanRepositoryInterface` | Definitivo |
| `DepartamentosInterface` | `activos(): [{id, nombre}]`, `porIds(ids): id => nombre`, `estaActivo(id): bool` | `DepartamentosDesdeEstructura` → `EstructuraFacade::getUnidadesActivas/getUnidadesPorIds` | Definitivo |
| `AsistenciaRegistradaInterface` | `tieneAsistencia(tenantId, accionId, colaboradorId): bool` (para impedir quitar participantes) | `AsistenciaRegistradaDesdeActividades` → `ActividadRepositoryInterface` | Definitivo |

## Puertos existentes modificados

| Puerto | Cambio |
|---|---|
| `AsistenciaInterface::presentesPorAccion` | Sin cambio de firma. El *binding* pasa de `AsistenciaSinDatos` (se elimina) a `AsistenciaDesdeActividades`: devuelve las acciones cuya actividad está **Finalizada** y cuyo colaborador tiene asistencia **Presente**. |
| `CambiarParticipantes` (caso de uso) | Recibe `AsistenciaRegistradaInterface`; si entre los participantes quitados hay alguno con asistencia, lanza `ParticipanteConAsistencia` (422, `code: "participante_con_asistencia"`) y no cambia nada. |
| `PlanRepositoryInterface` | Métodos de lectura nuevos para el adaptador: `accionesPorIds(tenantId, ids)` (con plan, competencias y participantes; devuelve el objeto de valor de dominio `AccionDeActividad`) y `accionesElegiblesParaActividad(tenantId, idsConActividad, planId, q, page, perPage)`, `idsDeAccionesDeColaboradorEnEjecucion(tenantId, colaboradorId)` y `planesParaActividades(tenantId)`. El segundo recibe los ids ya con actividad por parámetro para no leer tablas de Actividades. |

Puertos ya existentes que se reutilizan sin cambios: `NombresDeReferenciaInterface` (nombres de colaboradores y competencias),
`CompetenciasInterface`, `CuentaColaboradorInterface::cuentaDe(usuarioId)` (para `mis-actividades`), `NombresDeUsuarioInterface`
(auditoría e historial) y `RelojInterface::hoy` (fecha de referencia del estado).

## Repositorio (Domain/Repositories)

`ActividadRepositoryInterface`: `crear` (asigna `numero`/`codigo` en transacción), `actualizar`, `obtener`, `obtenerPorAccion`,
`listarDelTenant`, `registrosDe(actividadId)`, `guardarAsistencia(actividad, cambios, historial, versionEsperada)` (atómico, devuelve
nueva versión o lanza el conflicto), `historial(actividadId, página)`, `agregadosPorActividad(filtros)`,
`agregadosPorColaborador(filtros)`, `registradosPorActividades(ids)`, `actividadesDeColaborador(tenantId, colaboradorId)`.

## Cambios en otros módulos

Ninguno: no se modifican Estructura, Gestión de Personal, DNF ni Shared. Reportes (aún no existe) consumirá `getActividades` y
`getAsistencias` cuando se implemente.
