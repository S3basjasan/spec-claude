# Puertos y Facades de la feature 018

## Facade propia

**Ninguna.** Reportes no expone datos a otros módulos (FR-042); `DesarrolloFacade` no se amplía.

## Puertos nuevos de `Desarrollo` (Application/Ports)

| Puerto | Responsabilidad | Adaptador | Fuente |
|---|---|---|---|
| `FuenteDePlanesInterface` | `acciones(tenantId, filtros): list<{accion_id, plan_id, plan_codigo, plan_estado, nombre, tipo, estado_accion, fecha_inicio, fecha_fin, horas, costo, cancelada, competencia_ids, participante_ids}>` y `planes(tenantId): list<{id, codigo, nombre}>` | `PlanesParaReportes` | `PlanRepositoryInterface::planesConAcciones` (ampliado) + `CalculadorDeEstadoDeAccion` + `RelojInterface` |
| `FuenteDeActividadesInterface` | `actividades(tenantId, filtros)`, `asistencias(tenantId, filtros)`, `actividadesDeColaborador(tenantId, colaboradorId)` | `ActividadesParaReportes` | `ActividadRepositoryInterface` (sin cambios) |
| `FuenteDeMicrolearningInterface` | `completaciones(tenantId, filtros)`, `certificadosDe(tenantId, colaboradorId)` | `MicrolearningParaReportes` | `ProgresoMicroRepositoryInterface::completaciones/progresosDe` (sin cambios) |
| `FuenteDeDnfInterface` | `evaluaciones(tenantId, filtros): list<{dnf_id, fecha, colaborador_id, competencia_id, estado, nivel_requerido, nivel_actual}>` ordenadas por fecha y id | `DnfParaReportes` | `DnfRepositoryInterface::evaluacionesDeCompetencias` (nuevo) |
| `DepartamentoDeColaboradorInterface` | `actuales(tenantId, colaboradorIds, fecha): array<int, int\|null>` (colaborador → unidad actual) | `DepartamentoDeColaboradorDesdePersonal` | `PersonalFacade::getDepartamentoActual` (nuevo) |
| `GeneradorDeExcelInterface` | `generar(libro): string` (bytes del `.xlsx`; hojas con filas de celdas) | `ExcelConZip` | `ZipArchive` |
| `GeneradorDeReportePdfInterface` | `generar(datos): string` (bytes del PDF, sin gráficos) | `ReportePdfDompdf` | `dompdf/dompdf` |

Puertos existentes que se reutilizan sin cambios: `DepartamentosInterface` (nombres de departamentos), `NombresDeReferenciaInterface` (nombres de colaboradores y competencias), `CompetenciasInterface`, `CuentaColaboradorInterface::cuentaDe(usuarioId)`, `NombresDeUsuarioInterface`, `AccionesDePlanInterface::deColaborador` (próximas acciones), `RelojInterface::hoy`.

## Cambios en código existente (todos aditivos y de solo lectura)

| Elemento | Cambio |
|---|---|
| `PlanRepositoryInterface::planesConAcciones` | Cada acción de la respuesta añade `nombre`, `competencia_ids` y `participante_ids`. Los campos actuales no cambian. |
| `DnfRepositoryInterface` | Método nuevo `evaluacionesDeCompetencias(int $tenantId, array $filtros): array` (`fecha_desde`, `fecha_hasta`, `colaborador_id`, `colaborador_ids`). |
| `PersonalFacade` (Gestión de Personal) | Método nuevo `getDepartamentoActual(int $tenantId, array $colaboradorIds, string $fecha): array` → `colaborador_id => unidad_id\|null`, a partir de la asignación laboral vigente y `EstructuraFacade::getPosicionesDeGestionPorIds (mediante el puerto LectorEstructuraPort de Gestión de Personal)`. Lanza `InformacionNoDisponible` si la lectura falla (como `getDesarrollo`). |
| `DesarrolloServiceProvider` | Bindings de los puertos nuevos. |

## Repositorio propio (Domain/Repositories)

`ReportesRepositoryInterface`:

- `registrarEncuesta(Encuesta): Encuesta` y `encuestasDe(tenantId, actividadIds): list`, `encuestaDe(actividadId, colaboradorId): ?Encuesta`, `comentarios(tenantId, actividadId, página)`.
- `registrarSeguimiento(Seguimiento): Seguimiento`, `seguimientosDe(tenantId, actividadIds): list` y `seguimientoDe(actividadId, colaboradorId, hito): ?Seguimiento`.
- `impactoDe(tenantId, planAccionId): ?Impacto`, `impactosDe(tenantId, planAccionIds): list` y `guardarImpacto(Impacto, usuarioId): Impacto` (*upsert* transaccional).

No existen métodos para actualizar ni eliminar encuestas o seguimientos.

## Dominio (Domain/Services, sin Laravel)

| Servicio | Responsabilidad |
|---|---|
| `CalculadorDePeriodo` | Año actual por defecto y período anterior equivalente. |
| `ProrrateadorPorDepartamento` | Proporción de asistentes del departamento para costo y beneficio. |
| `CalculadorDeIndicadores` | % Plan cumplido, Total horas, satisfacción, ROI estimado, indicadores de gestión y variaciones. |
| `CalculadorDeBrechasCerradas` | Detectadas, cerradas y activas al cierre de mes (R4). |
| `CalculadorDeSeguimiento` | Tasa de respuesta, promedios y alerta de reingreso (R11). |
| `CalculadorDeRoi` | ROI de una acción y ROI agregado. |
| `ReglasDeEncuesta`, `ReglasDeSeguimiento`, `ReglasDeImpacto` | Elegibilidad, ventanas de 30 y 15 días, y validación de campos. |

## Cambios en otros módulos

Solo `GestionPersonal`: el método aditivo `getDepartamentoActual` de `PersonalFacade` (con su prueba). No se modifican Estructura, DNF (esquema), Shared ni los contratos publicados de `DesarrolloFacade`.
