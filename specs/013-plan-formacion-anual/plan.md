# Implementation Plan: Plan de formación anual

**Branch**: `013-plan-formacion-anual` | **Date**: 2026-10-16 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification de `/specs/013-plan-formacion-anual/spec.md` y plan de partida `plan-formacion.md` (carpeta externa «specify y plan»). Referencia visual: `design/PlanAnualAdmin.dc.html` y `design/PlanAnualColaborador.dc.html`.

## Summary

El Plan de formación anual organiza las acciones formativas de un período con un ciclo de vida Borrador → Revisión → Aprobado → Ejecutando → Cerrado. Se implementa dentro del módulo `Desarrollo` (ya existente por 005 y 006) con Clean Architecture: un **servicio de dominio puro** concentra las reglas (transiciones permitidas, completitud para enviar a revisión, estado calculado de la acción, cruce de fechas, cupo), los casos de uso coordinan cada acción de negocio y los datos de otros módulos (competencias, colaboradores, usuarios aprobadores, asistencia) entran por **puertos propios** con adaptadores que llaman a las Facades. La persistencia es de solo-agregar para el historial y sin borrado físico de planes ni de acciones fuera de Borrador. El frontend añade `frontend/src/modules/desarrollo/plan-formacion/` con la vista del administrador (planes, acciones en Calendario/Lista, asistente de 3 pasos, detalle con historial) y la del colaborador, **siguiendo los tableros `design/PlanAnualAdmin.dc.html` y `design/PlanAnualColaborador.dc.html`**.

Esta feature **cierra el hueco que dejó 006**: reemplaza el adaptador provisional `PlanDeFormacionNoDisponible` por uno real que crea el borrador (FR-029), y expone en `DesarrolloFacade` las lecturas para Actividades Formativas y Reportes (FR-030).

Hallazgos que condicionan el alcance (detalle en [research.md](research.md)): (R-01) Gestión de Personal aún no publica búsqueda de colaboradores con vínculo vigente ni existe Actividades Formativas (asistencia): ambos entran por puertos con adaptadores provisionales y contratos de ampliación documentados; (R-02) los tableros traen campos y controles que la spec no pide (período inicio/fin, presupuesto, objetivo, precarga desde DNF, participantes por departamento, «Lugar o plataforma», % de avance por acción) y se omiten, registrándolo en `design/DESIGN.md` §9.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 13 (backend); TypeScript estricto, React, Vite, Tailwind CSS 4 (frontend).

**Primary Dependencies**: las ya instaladas (Sanctum, Scramble, Pest, Pint; React Router, TanStack Query, Axios, React Hook Form, Zod, Vitest, Testing Library). **Sin dependencias nuevas.** El calendario mensual se dibuja con componentes propios (rejilla CSS + `Date`), sin librería de calendario.

**Storage**: SQLite (predeterminado). Tablas nuevas `planes`, `plan_acciones`, `plan_accion_competencias`, `plan_accion_participantes`, `plan_historial`.

**Testing**: Pest (Unit del servicio de reglas y del calculador de estado; Feature de endpoints, transiciones, permisos, historial y creación desde DNF), Vitest + Testing Library (componentes y hooks) y un E2E del flujo principal del administrador (crear → enviar → aprobar) y del colaborador, con la herramienta E2E que ya use el repositorio (no se añade otra).

**Target Platform**: SPA web 320–1440 px, temas claro/oscuro, API REST bajo `/api/planes` y `/api/plan-acciones`.

**Project Type**: aplicación web (backend modular + frontend modular).

**Performance Goals**: SC-001 (plan de 5 acciones en menos de 10 min de uso) y SC-006 (filtrar en < 30 s) son de uso; técnicamente, listados paginados con consultas por lote (sin N+1) y detalle de plan de hasta 50 acciones con 100 participantes cada una en una sola respuesta.

**Constraints**: sin FK entre módulos; el controlador no consulta BD; ningún módulo toca tablas ajenas; sin `DELETE` HTTP; sin borrado físico de planes ni soft deletes; textos en español centralizados; sin `any`; toda consulta filtra por el `tenant_id` del token.

**Scale/Scope**: 5 pantallas (planes, acciones Calendario/Lista, asistente, detalle, mi plan), 14 endpoints (11 de negocio y 3 de apoyo: `opciones`, `colaboradores`, `cursos`), 5 tablas.

## Constitution Check

| Principio | Cumplimiento | Estado |
|---|---|---|
| I. Backend modular / Clean Architecture | Todo en `Modules/Desarrollo`. Reglas en `Domain/Services` (`ReglasDePlan`, `CalculadorDeEstadoDeAccion`, `DetectorDeCruces`), casos de uso explícitos, repositorio por interfaz, controladores delgados, Requests para validar, Resources para responder. Competencias, colaboradores, usuarios y asistencia solo por Facades de otros módulos tras puertos. Cursos del catálogo se leen dentro del mismo módulo por su repositorio. Admin con `auth:sanctum` + middleware `admin`. **Desviación registrada**: las rutas son `/api/planes` y `/api/plan-acciones` y no `/api/desarrollo/…` (ver «Desviaciones»). | Pasa con desviación registrada |
| II. Frontend modular, accesible, tipado | `frontend/src/modules/desarrollo/plan-formacion/` con `api.ts`, `hooks/`, `components/`, `pages/`, `text.ts`, `validation.ts`. Axios solo en el cliente compartido; escrituras sin reintento; filtros, pestaña activa y paginación en la URL; loading/empty/error/success en toda vista; confirmaciones accesibles; WCAG 2.2 AA (el calendario es navegable por teclado y cada acción tiene texto, el color nunca es el único indicador); 320–1440 px sin scroll horizontal (el calendario cambia a lista por día bajo 640 px). | Pasa |
| III. Diseño como fuente visual | Se usan `design/PlanAnualAdmin.dc.html` y `design/PlanAnualColaborador.dc.html`. Hay ampliaciones y omisiones que se **registran primero en `design/DESIGN.md`** (tabla en «Diseño de frontend»). | Pasa con tarea previa |
| IV. Calidad, seguridad, dependencias | Pruebas por nivel; sin dependencias nuevas; entradas validadas; 401/403/404/422 seguros; auditoría de autor y fecha; el colaborador solo ve lo propio con el id tomado del token; aprobar/devolver validan que el usuario del token sea el aprobador designado. | Pasa |

Re-evaluación tras el diseño (Fase 1): sin violaciones nuevas. Las desviaciones respecto a `plan-formacion.md` están justificadas en la sección siguiente; ninguna es una excepción a la constitución.

### Desviaciones respecto a `plan-formacion.md`

| Punto | Documento de partida | Este plan | Motivo |
|---|---|---|---|
| Prefijo de rutas (**desviación de la Constitución I**, que pide `/api/<modulo>/`) | `/api/planes` y `/api/plan-acciones` | Se mantienen | Es el contrato del documento de partida y coincide con lo ya publicado por el módulo `Desarrollo` (`/api/catalogo-formativo` en 005 y `/api/dnf` en 006): las rutas hablan del recurso, no del módulo. Mantenerlas evita un tercer criterio dentro del mismo módulo. Registrada aquí conforme a «Las desviaciones MUST registrarse en la spec o el plan». |
| Multi-tenant | Las tablas no tienen tenant | `planes.tenant_id` obligatorio; el código PLAN-NNNN y la unicidad de nombre por año son **por tenant**; toda consulta filtra por el tenant del token. Las tablas hijas se alcanzan solo a través del plan | Usuarios, colaboradores y `dnf` ya llevan `tenant_id`; sin él se filtrarían datos entre tenants (Constitución IV). |
| Unicidad de nombre | «nombre (normalizado)» | Columna `nombre_normalizado` (minúsculas, sin espacios laterales ni dobles) con único `(tenant_id, anio, nombre_normalizado)`; `nombre` conserva lo que escribió la persona | FR-003: la unicidad no distingue mayúsculas ni espacios laterales. |
| Nombre del borrador desde la DNF | «Plan DNF-000X» | `Plan DNF {código de la DNF}` (p. ej. «Plan DNF DNF-0001») | Aclaración de la spec (sesión 2026-10-16). |
| Estados | `Revision` | Se guarda sin tilde y se muestra «Revisión» | Convención de los demás valores guardados del módulo. |
| Historial | `tipo`: Estado, Participante, Cancelacion | Se añade `Aprobador` | FR-016a: la reasignación del aprobador en Revisión debe quedar registrada. |
| Endpoints | 9 operaciones | Se añaden `PATCH /api/planes/{id}/aprobador` (FR-016a) y `GET /api/planes/opciones` (aprobadores y colaboradores para el asistente) | La spec exige reasignar y el asistente necesita sus selectores. |
| Cambio de participantes en Borrador | Permitido por `PUT` de participantes | Se mantiene, pero en Borrador no genera historial | FR-027: el historial de participantes comienza al aprobar. |
| Cambio en otros módulos | — | Métodos **aditivos y de solo lectura**: `PersonalFacade::buscarColaboradoresVigentes` y `vigentesPorIds`; `SharedFacade::getAdministradores` | Ver R-01 y [contracts/plan-puertos-y-facades.md](contracts/plan-puertos-y-facades.md). |

## Project Structure

### Documentation (this feature)

```text
specs/013-plan-formacion-anual/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── plan-api.md
│   └── plan-puertos-y-facades.md
├── checklists/requirements.md
└── tasks.md            # lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Desarrollo/
│   ├── Domain/
│   │   ├── ValueObjects/    # EstadoPlan, TipoAccion, EstadoAccion, TipoHistorialPlan, CodigoPlan
│   │   ├── Services/        # ReglasDePlan (transiciones y completitud), CalculadorDeEstadoDeAccion, DetectorDeCruces, NormalizadorDeNombre
│   │   ├── Entities/        # Plan, AccionFormativa (agregado Plan)
│   │   ├── Repositories/    # PlanRepositoryInterface
│   │   └── Exceptions/      # PlanNoEncontrado, AccionNoEncontrada, TransicionInvalida, PlanIncompleto, PlanNoEditable,
│   │                        # NombreDePlanDuplicado, CupoExcedido, FechasFueraDelAnio, UsuarioNoEsAprobador, AccionYaCancelada, ...
│   ├── Application/
│   │   ├── Ports/           # CompetenciasInterface (existe), CuentaColaboradorInterface (existe), NombresDeUsuarioInterface (existe),
│   │   │                    # NombresDeReferenciaInterface (existe), ColaboradoresVigentesInterface, AprobadoresInterface,
│   │   │                    # AsistenciaInterface, RelojInterface
│   │   ├── DTO/             # PlanDTO, FilaPlanDTO, AccionDTO, FilaAccionDTO, HistorialPlanDTO, MiPlanDTO, AvisoCruceDTO, OpcionesPlanDTO
│   │   └── UseCases/        # CrearPlan, ActualizarPlan, ListarPlanes, ObtenerPlan, CambiarEstadoDePlan, ReasignarAprobador,
│   │                        # ListarAccionesDePlanes, CambiarParticipantes, CancelarAccion, ListarHistorialDePlan,
│   │                        # ObtenerMiPlan, ObtenerOpcionesDePlan, CrearBorradorDesdeDnf
│   ├── Infrastructure/
│   │   ├── Adapters/        # ColaboradoresVigentesDesdePersonal, AprobadoresDesdeShared, RelojDelSistema (definitivos);
│   │   │                    # AsistenciaSinDatos (provisional); PlanDeFormacionDesdePlanes (reemplaza PlanDeFormacionNoDisponible);
│   │   │                    # Pruebas/ (adaptadores solo para e2e)
│   │   ├── Persistence/Eloquent/{Models,Repositories}/   # PlanModel, PlanAccionModel, PlanAccionCompetenciaModel,
│   │   │                                                 # PlanAccionParticipanteModel, PlanHistorialModel, EloquentPlanRepository
│   │   └── Providers/DesarrolloServiceProvider.php       # nuevos bindings
│   ├── Presentation/
│   │   ├── Controllers/{PlanController,PlanAccionController}.php
│   │   ├── Requests/{GuardarPlanRequest,CambiarEstadoRequest,ReasignarAprobadorRequest,ListarPlanesRequest,
│   │   │             ListarAccionesRequest,CambiarParticipantesRequest,CancelarAccionRequest,ListarHistorialPlanRequest}.php
│   │   ├── Resources/       # PlanResource, FilaPlanResource, AccionResource, FilaAccionResource, HistorialPlanResource, MiPlanResource
│   │   └── Facades/DesarrolloFacade.php   # + crearBorradorDesdeDnf, getAccionesDePlanesEjecutando, getPlanesYAcciones
│   └── Routes/api.php                      # rutas /api/planes y /api/plan-acciones
├── app/Modules/GestionPersonal/   # solo PersonalFacade/PersonalService: métodos aditivos de lectura
├── app/Modules/Shared/            # solo SharedFacade/SharedService: getAdministradores
├── database/migrations/2026_10_18_00000x_create_plan*.php
├── database/seeders/PlanFormacionSeeder.php
└── tests/{Unit,Feature}/Desarrollo/Plan*

frontend/src/modules/desarrollo/plan-formacion/
├── api.ts  text.ts  validation.ts  nav.tsx  fecha.ts
├── hooks/        # usePlanes, usePlan, useGuardarPlan, useCambiarEstado, useReasignarAprobador, useAcciones, useCambiarParticipantes,
│                 # useCancelarAccion, useHistorialPlan, useMiPlan, useOpcionesPlan, usePlanParams
├── components/   # TabsPlan, TablaPlanes, AccionesPlan (botones por estado), CalendarioAcciones, ListaAcciones, FiltrosAcciones,
│                 # BadgeEstadoPlan, BadgeEstadoAccion, ChipTipo, AsistentePlan (Paso1Datos, Paso2Acciones, Paso3Participantes),
│                 # FormularioAccion, SelectorParticipantes, AvisosCruce, DetallePlan, HistorialPlan, MotivoDialog, ConfirmarTransicionDialog,
│                 # DetalleAccionPanel, TarjetaAccion, ProgresoDelPlan, ProximasActividades
└── pages/        # PlanFormacionPage (despacha por rol), PlanesPage, AccionesPage, AsistentePlanPage, PlanDetallePage, MiPlanPage
```

**Structure Decision**: aplicación web con módulos espejo `Desarrollo` en backend y frontend, igual que 005 y 006. La funcionalidad se aísla en la subcarpeta `plan-formacion/` del frontend (como `catalogo-formativo/` y `dnf/`). Los componentes que ya existen en `shared/ui` (`Stepper`, `Badge`, `Modal`, `ConfirmDialog`, `Combobox`, `SelectField`, `SearchField`, `Pagination`, `Skeleton`, `EmptyState`, `ErrorState`, `Alert`, `TextField`, `TextareaField`, `Breadcrumb`) se reutilizan.

## Complexity Tracking

Sin violaciones de la constitución que justificar. Riesgo de alcance registrado, no de complejidad: ver R-01 en [research.md](research.md).

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/plan-formacion` | Admin: Planes de formación (tabla) · Colaborador: Mi plan de formación (se despacha por rol con `RequireRole`, como `/formacion` y `/dnf`) | Administrador y Colaborador |
| `/plan-formacion/acciones` | Acciones formativas: pestañas Calendario y Lista (`?vista=calendario\|lista`) | Administrador |
| `/plan-formacion/nuevo` | Asistente de creación (3 pasos) | Administrador |
| `/plan-formacion/:id/editar` | Asistente de edición (solo Borrador) | Administrador |
| `/plan-formacion/:id` | Detalle del plan con historial; desde aquí se cambian participantes y se cancelan acciones | Administrador |

Navegación lateral: una entrada «Plan anual» (como en los tableros) visible para ambos roles. Los tableros traen un botón «Ver como colaborador/administrador», el menú con Empleados y Ajustes y «Mi perfil»: son **controles de prototipo** y no se implementan (cada persona tiene un solo rol).

### Componentes
- **Nuevos** (no existe equivalente en `shared/ui`): `Tabs` (pestañas accesibles `role="tablist"`; nace en `shared/ui` porque Catálogo/otros pueden reutilizarlo), `BadgeEstadoPlan`, `BadgeEstadoAccion` y `ChipTipo` (envoltorios de `Badge` con los colores del tablero), `CalendarioAcciones` (rejilla mensual con navegación mes anterior/siguiente y «Hoy»), `ProgresoDelPlan` (barra `role="progressbar"`, reutiliza la del indicador de cobertura), `TarjetaAccion`, `ProximasActividades`, `SelectorParticipantes` con indicador de capacidad usada, `AvisosCruce`.
- **Del design system**: `AppShell`, `Button`, `Alert`, `Badge`, `Stepper`, `Combobox`, `SelectField`, `SearchField`, `TextField`, `TextareaField`, `Modal`/`ConfirmDialog` (confirmaciones y motivo), `Pagination`, `Skeleton`, `EmptyState`, `ErrorState`, `Breadcrumb`, y el patrón Listado para la tabla de planes y la lista de acciones.

**Registro de desviaciones del diseño (Constitución III) — a incorporar en `design/DESIGN.md` como primera tarea (§2 tokens de tipo de acción, §4 componentes, §5 patrones nuevos, §9 diferencias):**

| Tablero | Qué no cubre el sistema de diseño o la spec | Resolución |
|---|---|---|
| `PlanAnualAdmin.dc.html` | Pestañas, calendario mensual con chips por tipo, chips de filtro («pill»), tarjetas KPI de avance del plan y contadores por estado | Añadir al DESIGN.md (Tabs, Calendario mensual, Chip de tipo). Las tarjetas KPI se reducen a las que la spec respalda: cantidad de acciones, costo total y horas totales (FR-021); se omiten las demás cifras de ejemplo. |
| `PlanAnualAdmin.dc.html` | Paso 1 pide «Inicio del período», «Fin del período», «Presupuesto estimado (USD)» y «Objetivo del plan» | **Manda la spec** (FR-002): solo nombre, año y descripción. Se omiten esos cuatro campos; el período es el año. Registrado en §9. |
| `PlanAnualAdmin.dc.html` | Paso 1 ofrece «Precargar acciones desde el último DNF» | Se omite: el borrador desde la DNF lo crea la DNF mediante la Facade (FR-029), no el asistente. Registrado en §9. |
| `PlanAnualAdmin.dc.html` | Paso 3 asigna «Participantes por departamento» (conteo por departamento) | **Manda la spec** (FR-012, FR-014, historia 1): participantes por acción, con búsqueda por colaborador, indicador de capacidad usada y avisos de cruce junto al participante. Registrado en §9. |
| `PlanAnualAdmin.dc.html` | Paso 2 trae «Competencia objetivo» como selección única, sin curso del catálogo, sin duración en horas | La spec pide una o más competencias, curso opcional que propone tipo/formador/competencias y duración en horas (FR-008–FR-010): el formulario de acción usa selección múltiple (Combobox) y un selector «Partir de un curso del catálogo». |
| `PlanAnualAdmin.dc.html` | Estados de plan y de acción de ejemplo | Los estados son los de la spec: plan (Borrador, Revisión, Aprobado, Ejecutando, Cerrado) y acción (Programada, En curso, Finalizada, Cancelada). La acción cancelada lleva el badge adicional «Cancelada» (spec 4.1). |
| `PlanAnualAdmin.dc.html` | Los botones por fila de la tabla de planes y el detalle solo existen para el panel de la acción | Se define el detalle del plan (patrón Detalle de un registro, `ColaboradorFicha.dc.html`) con el historial como lista cronológica. |
| `PlanAnualColaborador.dc.html` | Estados de la tarjeta «Completada», «Inscrito», «Por confirmar» y «Tu avance %» por acción | **Manda la spec** (FR-013, FR-022): se muestra el estado de la acción (Programada, En curso, Finalizada, Cancelada). El avance por acción se omite; solo el «% del plan completado» general (FR-023). |
| `PlanAnualColaborador.dc.html` | El detalle muestra «Lugar o plataforma» | No existe en la spec: se omite. Registrado en §9. |
| `PlanAnualColaborador.dc.html` | La lista no muestra el motivo de cancelación | La spec aclarada (FR-022) lo exige: la tarjeta «Cancelada» muestra el motivo. |
| Ambos | Menú lateral del tablero (Empleados, Ajustes, Mi perfil, Perfilamiento) | Se usa el `AppShell` real con la navegación declarativa existente. |

### Datos y estado
- Planes (admin): `GET /api/planes` con `anio, estado, q, page, per_page` **en la URL**; caché `['planes','lista', params]`, invalidada tras crear, actualizar y cambiar estado o aprobador.
- Acciones (admin): `GET /api/plan-acciones` con `estado, tipo, competencia_id, fecha_desde, fecha_hasta, anio, plan_id, page, per_page` en la URL; la pestaña (`vista`) y el mes visible del calendario también. Lista y calendario comparten filtros y caché base `['plan-acciones', params]`; el calendario pide el rango del mes visible con `per_page` máximo (R-06).
- Detalle: `GET /api/planes/{id}` (acciones, totales y avisos) y `GET /api/planes/{id}/historial` paginado. Tras cambiar participantes, cancelar o cambiar estado se invalidan detalle, historial, lista de planes y acciones.
- Asistente: `POST /api/planes` (crear) o `PUT /api/planes/{id}` (Borrador); selectores en `GET /api/planes/opciones` (aprobadores, tipos) más búsqueda de colaboradores `GET /api/planes/colaboradores?q=` y de cursos `GET /api/planes/cursos?q=`. «Guardar borrador» está disponible en los 3 pasos y envía el estado completo del asistente.
- Colaborador: `GET /api/planes/mi-plan`; «Ver detalle» abre un panel con los datos ya cargados (sin nueva petición).
- Mutaciones sin reintentos; los 422 mantienen los valores del formulario.

### Validación
- Paso 1: nombre y año obligatorios (Zod espejo de `GuardarPlanRequest`); nombre único por año se confirma con el 422 del servidor.
- Paso 2 (acción en Borrador): solo nombre y competencias obligatorios; fechas dentro del año, fin ≥ inicio, horas > 0, capacidad entero > 0, costo ≥ 0.
- Paso 3: sin repetidos y sin superar capacidad (el indicador bloquea agregar al llegar al máximo); los avisos de cruce son informativos. **Avisos de cruce en el asistente**: mientras el plan no está guardado, el cliente calcula al instante los cruces **entre las acciones del propio asistente** (misma regla de solapamiento que `DetectorDeCruces`, función pura en `fecha.ts`); los cruces con acciones de **otros planes** los devuelve el servidor en la respuesta de «Guardar borrador» (`avisos_cruce` del `POST`/`PUT`) y se muestran junto al participante. No hay endpoint de vista previa.
- Errores 422 se mapean por campo (`acciones.2.fecha_fin`) o, si son de negocio (plan incompleto), a un `Alert` con la lista de faltantes sobre el botón. Se bloquea el envío mientras la mutación está pendiente.

## Decisiones cerradas

| Tema | Decisión |
|---|---|
| Hoy y zona horaria | El estado de la acción se calcula con la fecha de «hoy» en la zona horaria del tenant (`SharedFacade::getContextoDeTenant`), tras un puerto `RelojInterface::hoy(usuarioId)` (la zona sale del tenant del usuario) para poder fijar la fecha en pruebas. |
| Concurrencia | Los cambios de estado usan actualización condicionada (`WHERE estado = anterior`); si afecta 0 filas se responde 422 «el plan cambió de estado». Los `PUT` no usan versión (el último guardado gana), igual que 005. |
| Participantes sin vínculo vigente | La inscripción se conserva y ocupa cupo; el administrador la quita manualmente (aclaración 3). El buscador del paso 3 solo ofrece colaboradores con vínculo vigente. |
| Aprobador | Cualquier usuario con rol Administrador del tenant; puede ser quien crea/envía (aclaración 2). La reasignación en Revisión la hace cualquier Administrador (FR-016a) y se registra con tipo `Aprobador`. |
| Cancelada vs. totales | Costo y horas totales del plan excluyen acciones canceladas. |
| Progreso del colaborador | `completadas / asignadas no canceladas`, con «completada» = Finalizada + asistencia Presente. Hasta que exista Actividades Formativas, `AsistenciaSinDatos` devuelve ninguna asistencia (FR-024). |
| Facade de salida | `crearBorradorDesdeDnf`, `getAccionesDePlanesEjecutando` y `getPlanesYAcciones` en `DesarrolloFacade`; el adaptador `PlanDeFormacionDesdePlanes` (puerto de 006) llama al caso de uso `CrearBorradorDesdeDnf`. |
| E2E | Adaptadores de prueba (`Infrastructure/Adapters/Pruebas/`) se registran **solo** cuando `app()->environment('e2e')`: colaboradores vigentes, aprobadores y reloj fijo; nunca en producción ni desarrollo. |
