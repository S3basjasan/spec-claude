# Contrato de Facades: Plan de desarrollo individual (PDI)

La colaboración entre módulos es solo por Facades y puertos de aplicación. Esta feature **expone** métodos nuevos en `DesarrolloFacade` y **consume** métodos existentes; los cambios en Facades de otros módulos son aditivos y de solo lectura.

## Métodos que expone `DesarrolloFacade` (nuevos)

```php
/** Planes no Cerrados del colaborador con avance. Usado por Reportes («Mi plan de desarrollo»). */
planesActivos(int $tenantId, int $colaboradorId): array
// [{pdi_id, codigo, tipo, estado, objetivo, fecha_inicio, fecha_fin, avance, hitos_vencidos}]
// Solo planes visibles para el colaborador (Borrador con acuerdo del responsable, o Acordado).

/** Cabeceras de planes por filtros. Usado por Reportes (indicadores de cierre de brechas). */
pdiPorFiltros(int $tenantId, array $filtros = []): array
// filtros: tipo, estado, resultado, fecha_desde, fecha_hasta (sobre fecha_fin), departamento_id, colaborador_id
// [{pdi_id, codigo, colaborador_id, tipo, estado, resultado, fecha_inicio, fecha_fin, cerrado_at,
//   compromisos: n, compromisos_cerrados: n}]

/** Indica si el colaborador tiene un plan no Cerrado de ese tipo. Usado por Adecuación (habilita sus botones). */
existePlanAbierto(int $tenantId, int $colaboradorId, string $tipo): bool

/** Acciones del Plan de Formación vinculables a una acción del PDI. Solo lectura. */
getAccionesVinculablesDeColaborador(int $tenantId, int $colaboradorId): array
// Acciones no canceladas de planes Aprobado o Ejecutando donde el colaborador es participante:
// [{accion_id, plan_id, plan_codigo, plan_nombre, plan_estado, nombre, tipo, fecha_inicio, fecha_fin, formador}]
```

Los nombres siguen la convención de la Facade actual (`get…` para lecturas de otros módulos; los métodos de consulta propios mantienen los nombres de `plan-pdi.md`). `existePlanAbierto` y `planesActivos` no revelan contenido de compromisos.

## Cambios aditivos en `DesarrolloFacade`

| Método existente | Cambio | Razón |
|---|---|---|
| `brechasActivas(tenantId, colaboradorId)` | Se añade un método hermano `ultimaDnfConBrechas(tenantId, colaboradorId): ?array` que devuelve `{dnf_id, fecha, brechas: [...]}` | Guardar `origen_dnf_id` (R5). `brechasActivas` no cambia. |
| `ultimaAdecuacion(tenantId, colaboradorId, puestoObjetivoId)` | Cada brecha devuelta incluye además `nivel_actual` y `nivel_objetivo` (A–D) y `valor_anios`/`requerido_anios` en Experiencia | Precargar niveles sin leer tablas de Adecuación. El resto del contrato no cambia. |
| `getAsistenciaDeColaborador(...)` | Sin cambios | Se usa tal cual para derivar `Cumplida` (R3). |
| `inscripcionesActivas(...)`, `obtenerNivel(...)` | Sin cambios | Puesto objetivo por defecto en Carrera. |

## Puertos de aplicación del módulo `Desarrollo` (nuevos o ampliados)

| Puerto | Responsabilidad | Adaptador |
|---|---|---|
| `AccionesVinculablesInterface` (nuevo) | Acciones vinculables de un colaborador y lectura por ids con participantes | `AccionesVinculablesDesdePlanes` (usa `PlanRepositoryInterface`, método nuevo `accionesVinculablesDeColaborador`) |
| `ValidadorDeVinculoInterface` (nuevo) | Valida un `plan_accion_id` (tipo vinculable y presente en las acciones vinculables del colaborador); lo usan crear, editar y vincular | Provisional (rechaza todo) en T035; real `ValidadorDeVinculoDesdePlanes` en T066 |
| `EstadoFormativoDeAccionInterface` (nuevo) | Estado de actividad y asistencia por colaborador y acción | `EstadoFormativoDesdeActividades` (usa `getAsistenciaDeColaborador`) |
| `BrechasParaPdiInterface` (nuevo) | Última DNF con brechas y última adecuación con sus brechas | `BrechasParaPdiDesdeDnfYAdecuacion` (casos de uso propios del módulo) |
| `AprobadoresInterface` (existente) | `administradores` y `esAdministradorActivo` | `AprobadoresDesdeShared` |
| `CuentaColaboradorInterface` (existente) | Colaborador del usuario autenticado | `CuentaColaboradorDesdePersonal` |
| `ColaboradoresVigentesInterface`, `DepartamentoDeColaboradorInterface`, `NombreDeColaboradorInterface` (existentes) | Colaboradores vigentes, departamento y nombres en lote | adaptadores existentes |
| `CompetenciasInterface` (existente) | Elementos activos y nombres | `CompetenciasDesdeEstructura` |
| `PuestosParaRutasInterface` / `PerfilesDePuestoInterface` (existentes) | Puestos activos y nombres | adaptadores existentes |
| `RelojInterface` (existente) | «Hoy» para hitos vencidos y estados derivados | `RelojDelSistema` |
| `RepositorioDePdiInterface` (nuevo, en `Domain/Repositories`) | Persistencia de planes con hijos, historial, lecturas y unicidad | `EloquentPdiRepository` |

## Facades de otros módulos

- **Gestión de Personal (`PersonalFacade`)**: sin cambios; todo se consume por los puertos existentes (colaboradores, departamento, nombres).
- **Estructura Empresarial (`EstructuraFacade`)**: sin cambios; puestos y competencias activos por los puertos existentes. Conocimientos: bandera `desarrollo.pdi.conocimientos_con_grado` (por defecto `false`), igual que 019 y 020; con `false` la dimensión Conocimiento se desactiva en los compromisos.

## Reglas de aislamiento
- Ninguna consulta toca tablas de otro módulo ni de otra feature por SQL; el repositorio del PDI solo lee/escribe `pdis*`.
- `pdi_acciones.plan_accion_id` es referencia lógica validada con la Facade; no hay FK física.
- El Plan de Formación y las Actividades **nunca se modifican** desde esta feature.
