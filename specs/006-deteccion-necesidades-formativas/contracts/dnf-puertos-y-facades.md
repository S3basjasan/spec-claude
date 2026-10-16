# Contrato: puertos de entrada, ampliaciones de Facade y Facade de la DNF

Sigue el patrón de [`005-catalogo-formativo/contracts/desarrollo-puertos.md`](../../005-catalogo-formativo/contracts/desarrollo-puertos.md). Los casos de uso solo dependen de los puertos; ningún módulo accede a tablas ajenas.

## Puertos (Desarrollo/Application/Ports)

| Puerto | Método | Devuelve | Adaptador | Estado |
|---|---|---|---|---|
| `PerfilesDePuestoInterface` | `perfilDe(int $puestoId): ?array` | `{puesto:{id,codigo,nombre}, competencias:[{competencia_id,codigo,nombre,tipo,activa,nivel_requerido}]}` o null | `PerfilesDePuestoDesdeEstructura` → `EstructuraFacade::getPuesto` (campos `competencias[].grado_requerido`, `tipo`, `estado` ya existen) | **Definitivo** |
| `ColaboradoresEvaluablesInterface` | `evaluablesPorIds(int $tenantId, array $ids): array` · `evaluablesDeUnidad(int $tenantId, int $unidadId): array` | `[{colaborador_id, nombre, puesto_id, unidad_id}]`; solo con vínculo vigente y puesto. Para unidad: incluye **subunidades** (el adaptador obtiene los ids de subunidades con `EstructuraFacade::getArbolDeUnidades()`) y devuelve también `excluidos[{colaborador_id,nombre,motivo}]` | `ColaboradoresEvaluablesSinDatos` → vacío | **Provisional** hasta GP-02 |
| `CuentaColaboradorInterface` | `cuentaDe(int $usuarioId): ?array` | `{tenant_id, colaborador_id}` de la ficha asociada a la cuenta, o null si no hay ficha | `CuentaColaboradorDesdePersonal` → `PersonalFacade::getCuentaDeUsuario` (**se añade en esta feature**, ver abajo) | **Definitivo** |
| `NivelesActualesInterface` | `nivelesDe(int $tenantId, array $colaboradorIds): array` | `[colaborador_id => [competencia_id => 'A'..'D']]`; solo competencias con nivel registrado | `NivelesActualesSinDatos` → `[]` | **Provisional** hasta GP-02 |
| `PlanDeFormacionInterface` | `crearBorrador(int $tenantId, int $actorId, int $dnfId, array $brechas): int` | id del borrador | `PlanDeFormacionNoDisponible` → lanza `PlanNoDisponible` (503) | **Provisional** hasta la feature Plan de Formación |
| `BrechasActivasInterface` (existente en 005) | `brechasDe(int $usuarioId): array` | `[{competencia_id, nivel_requerido}]` | **Se reemplaza** `BrechasActivasSinDatos` por `BrechasActivasDesdeDnf` (traduce usuario → `{tenant_id, colaborador_id}` con `CuentaColaboradorInterface::cuentaDe` y llama al caso de uso de brechas activas) | **Definitivo** (cambia el binding en `DesarrolloServiceProvider`) |

Reglas:
- Todo caso de uso recibe `tenant_id` y `usuarioId` del usuario autenticado, nunca de la petición, salvo `colaborador_id`/`departamento_id` validados contra el tenant.
- Si un puerto lanza o devuelve vacío, `GenerarDnf` lo trata como «sin datos» (colaborador excluido, o 422 si no queda ninguno); se registra el fallo sin datos personales.
- Cuando existan GP-02 y Plan de Formación se escriben los adaptadores definitivos y se cambian los bindings; ningún caso de uso, recurso ni pantalla cambia.

## Ampliaciones de Facade de otros módulos

| Módulo | Método de Facade requerido | Contenido |
|---|---|---|
| Gestión de Personal (GP-02) | `getColaboradoresEvaluables(int $tenantId, array $unidadIds = null, array $colaboradorIds = null): array` | `{colaborador_id, nombre, puesto_id, unidad_id}` de quienes tienen vínculo vigente; más el motivo de exclusión (`SinPuesto`, `SinVinculoVigente`) |
| Gestión de Personal | `getNivelesActuales(int $tenantId, array $colaboradorIds): array` | `colaborador_id => competencia_id => nivel` |
| Gestión de Personal — **se implementa en esta feature (tarea T016, aditiva y de solo lectura)** | `getCuentaDeUsuario(int $usuarioId): ?array` | `{tenant_id, colaborador_id}` resuelto desde `colaborador_cuentas`; null si el usuario no tiene ficha asociada |
| Plan de Formación | `crearBorradorDesdeDnf(int $tenantId, int $actorId, int $dnfId, array $brechas): int` | Un borrador por DNF (las brechas incluyen colaborador, competencia, grados) |

Si GP decide una forma distinta, solo cambia el adaptador.

## Facade del módulo Desarrollo (ampliación de `DesarrolloFacade`)

| Método | Devuelve | Notas |
|---|---|---|
| `brechasActivas(int $tenantId, int $colaboradorId): array` | `[{competencia_id, nivel_requerido, nivel_actual, diferencia, prioridad}]` | Detalles `ConBrecha` de la DNF más reciente del colaborador (R-06). `[]` si no hay DNF. Solo lectura; devuelve arreglos, no modelos. Consumida por el Catálogo Formativo y, para indicadores, por Reportes. |
| `getDnfRegistradas(int $tenantId, array $filtros = []): array` | Cabeceras de DNF `{id, codigo, fecha, alcance, colaborador_id, departamento_id, colaboradores, cantidad_brechas, prioridad_mas_alta}`; filtros opcionales `fecha_desde`, `fecha_hasta`, `colaborador_id`, `departamento_id`, `prioridad` | Insumo del indicador «brechas cerradas» de Reportes (FR-032); recorre todas las páginas; sin editar. |

El `DesarrolloService` expone ambos. `brechasActivas` recibe el `tenantId` de forma **explícita** (decisión cerrada: la Facade no depende del contexto de la petición, así Reportes puede invocarla fuera de ella); `BrechasActivasDesdeDnf` lo obtiene de `CuentaColaboradorInterface::cuentaDe`.

## Garantías
- La Facade nunca expone entidades ni modelos Eloquent.
- La DNF no modifica competencias, perfiles ni niveles.
