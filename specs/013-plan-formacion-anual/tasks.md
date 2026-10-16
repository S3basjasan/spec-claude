---

description: "Tareas de la feature 013-plan-formacion-anual"
---

# Tasks: Plan de formación anual

**Input**: Documentos de diseño en `/specs/013-plan-formacion-anual/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/plan-api.md, contracts/plan-puertos-y-facades.md, quickstart.md. Requiere 000-login, 001/004 (competencias en `EstructuraFacade`), 005-catalogo-formativo (módulo `Desarrollo`, `DesarrolloFacade`, tabla `cursos`, `RequireRole`), 005-fichas-colaboradores (`PersonalFacade`, `colaborador_cuentas`) y 006-deteccion-necesidades-formativas (puerto `PlanDeFormacionInterface`, `GenerarPlanDesdeDnf`) ya implementadas. **Actividades Formativas (asistencia) y Reportes no existen**: la asistencia entra por un puerto con adaptador provisional (plan.md, research.md R-01).

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario (US1–US7 de spec.md) para implementarlas y probarlas de forma independiente. La feature **amplía** el módulo `Desarrollo` existente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1–US7)
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Desarrollo/…`. Abreviatura **`$D`** = `backend/app/Modules/Desarrollo`
- Frontend: `frontend/src/modules/desarrollo/plan-formacion/…` (reutiliza `frontend/src/shared/ui/…`). Abreviatura **`$F`** = `frontend/src/modules/desarrollo/plan-formacion`
- Pruebas backend: `backend/tests/{Unit,Feature}/Desarrollo/` (prefijo `Plan`); dobles en `backend/tests/Support/Desarrollo/`
- Pruebas de componentes: `*.dom.test.tsx` junto al código (con `// @vitest-environment jsdom`); E2E en `frontend/e2e/plan-formacion.spec.ts`
- Regla de módulos: `Desarrollo` solo usa `EstructuraFacade`, `PersonalFacade` y `SharedFacade` (nunca sus modelos ni tablas); `colaborador_id`, `competencia_id`, `aprobador_id`, `origen_dnf_id`, `tenant_id` y los ids de usuario son enteros **sin clave foránea** entre módulos. Entre tablas de `plan*` se usan FK **sin borrado en cascada** (`restrictOnDelete`). En frontend, `plan-formacion` **no importa nada** de otros módulos salvo `modules/auth/login` (rol) y `RequireRole`/`RequireAdmin` si su contrato público lo permite; si no, copiar el patrón de `dnf/components/RequireAdmin`
- Valores en base de datos (sin tildes; la interfaz muestra con tilde): `estado` del plan ∈ `Borrador` | `Revision` | `Aprobado` | `Ejecutando` | `Cerrado`; `tipo` ∈ `Presencial` | `Virtual` | `ELearning` | `OJT` | `Codesarrollo` | `Mentoring`; `tipo` del historial ∈ `Estado` | `Participante` | `Cancelacion` | `Aprobador`; estado de acción (calculado, no se guarda) ∈ `Programada` | `EnCurso` | `Finalizada` | `Cancelada`; roles: capacidad `admin` (Administrador) y rol `user` (Colaborador), excluyentes
- Reutilizar sin modificar: `Button`, `Badge`, `Alert`, `ConfirmDialog`, `Modal`, `Combobox`, `SelectField`, `SearchField`, `TextField`, `TextareaField`, `Stepper`, `Pagination`, `Breadcrumb`, `EmptyState`, `ErrorState`, `Skeleton` y `AppShell`. No crear componentes equivalentes
- Guía visual: `design/PlanAnualAdmin.dc.html` y `design/PlanAnualColaborador.dc.html` son **referencia, no código para copiar**; en estilos manda `design/DESIGN.md`. **Manda la spec sobre los tableros** (plan.md, tabla «Registro de desviaciones del diseño»): se omiten período inicio/fin, presupuesto, objetivo, precarga desde DNF, participantes por departamento, «Lugar o plataforma», avance por acción y los estados Completada/Inscrito/Por confirmar
- Insumo `plan-formacion.md` y plan.md: donde difieren, **prevalecen plan.md y research.md** (`tenant_id`, `nombre_normalizado`, nombre `Plan DNF {código DNF}`, tipo de historial `Aprobador`, `PATCH /api/planes/{id}/aprobador`)
- Lecciones previas: en E2E localizar campos con `getByRole('textbox', { name, exact: true })`; escribir archivos con acentos con la herramienta Write (los heredocs de Bash fallan); ver la memoria «Entorno local de pruebas» (OneDrive/Windows) al correr Pest, Vitest y Playwright

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Línea base verde y diseño registrado antes de tocar código

- [X] T001 Verificar la línea base: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run` y `npx playwright test` en `frontend/` deben pasar antes de empezar (sin cambios de archivos). Anotar los fallos preexistentes de la memoria del proyecto para no atribuirlos a esta feature
- [X] T002 Actualizar `design/DESIGN.md` (Constitución III) **antes** de cualquier tarea de frontend: en §2 los colores por tipo de acción (Presencial, Virtual, E-learning, OJT, Codesarrollo, Mentoring) y por estado de plan/acción con sus variantes clara y oscura (tomarlos de `PlanAnualAdmin.dc.html`/`PlanAnualColaborador.dc.html`); en §4 los componentes **Tabs**, **Calendario mensual** (rejilla, chips por tipo, «Hoy», navegación de mes, vista por día bajo 640 px), **Chip de tipo**, **Tarjeta de acción**, **Barra de progreso del plan** y **Selector de participantes con capacidad usada**; en §5 los patrones «Planes de formación (Listado)», «Acciones: Calendario y Lista», «Asistente de 3 pasos» y «Mi plan»; en §9 las filas de desviación de plan.md (tabla «Registro de desviaciones del diseño»). Sin cambios si ya está completo

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Tablas, dominio con las reglas del plan, repositorio, puertos y adaptadores, y piezas de frontend que usan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend – datos

- [X] T003 Crear `backend/database/migrations/2026_10_18_000001_create_planes_table.php` según data-model.md: `id`; `tenant_id` entero sin signo con índice (sin FK); `numero` entero sin signo; `codigo` `string(12)` (`PLAN-0001`, derivado de `numero`); `nombre` `string(120)`; `nombre_normalizado` `string(120)`; `anio` `unsignedSmallInteger`; `descripcion` `text` anulable; `estado` `string(12)` por defecto `Borrador`; `aprobador_id` entero sin signo anulable (referencia lógica, sin FK); `origen_dnf_id` entero sin signo anulable (sin FK); `fecha_aprobacion` timestamp anulable; `created_by` y `updated_by` enteros sin signo (sin FK); timestamps. Únicos **`(tenant_id, numero)`** y **`(tenant_id, anio, nombre_normalizado)`**; índice `(tenant_id, anio, estado)`. Sin soft delete
- [X] T004 Crear `backend/database/migrations/2026_10_18_000002_create_plan_acciones_table.php`: `id`; `plan_id` FK a `planes.id` con `restrictOnDelete`; `curso_id` FK anulable a `cursos.id` con `restrictOnDelete` (mismo módulo); `nombre` `string(120)`; `tipo` `string(12)` **anulable** (`Presencial` | `Virtual` | `ELearning` | `OJT` | `Codesarrollo` | `Mentoring`); `fecha_inicio` y `fecha_fin` `date` **anulables** (dentro de `planes.anio`); `duracion_horas` `decimal(6,2)` anulable (> 0); `formador` `string(120)` anulable; `capacidad_maxima` entero sin signo anulable (> 0); `costo` `decimal(12,2)` anulable (≥ 0, USD); `cancelada` boolean por defecto false; `motivo_cancelacion` `string(500)` anulable (obligatorio si `cancelada`); `created_by`, `updated_by`, timestamps. Índices `(plan_id)` y `(fecha_inicio, fecha_fin)`
- [X] T005 [P] Crear `backend/database/migrations/2026_10_18_000003_create_plan_accion_competencias_table.php`: `id`; `plan_accion_id` FK a `plan_acciones.id` con `restrictOnDelete`; `competencia_id` entero sin signo (referencia lógica a Estructura, sin FK); timestamps. Restricción **única `(plan_accion_id, competencia_id)`**
- [X] T006 [P] Crear `backend/database/migrations/2026_10_18_000004_create_plan_accion_participantes_table.php`: `id`; `plan_accion_id` FK a `plan_acciones.id` con `restrictOnDelete`; `colaborador_id` entero sin signo con índice (referencia lógica a Gestión de Personal, sin FK); `created_by`; timestamps. Restricción **única `(plan_accion_id, colaborador_id)`**
- [X] T007 [P] Crear `backend/database/migrations/2026_10_18_000005_create_plan_historial_table.php` (solo se agrega): `id`; `plan_id` FK a `planes.id` con `restrictOnDelete`; `plan_accion_id` FK anulable a `plan_acciones.id` con `restrictOnDelete`; `tipo` `string(12)` (`Estado` | `Participante` | `Cancelacion` | `Aprobador`); `valor_anterior` y `valor_nuevo` `string(120)` anulables; `motivo` `string(500)` anulable; `user_id` entero sin signo (sin FK); solo `created_at` (sin `updated_at`)

### Backend – dominio

- [X] T008 [P] Crear los enums en `$D/Domain/ValueObjects/`: `EstadoPlan` (`Borrador`, `Revision`, `Aprobado`, `Ejecutando`, `Cerrado`; `etiqueta()` con tilde), `TipoAccion` (seis valores; `etiqueta()` «E-learning» para `ELearning`), `EstadoAccion` (`Programada`, `EnCurso`, `Finalizada`, `Cancelada`), `TipoHistorialPlan` (`Estado`, `Participante`, `Cancelacion`, `Aprobador`) y `CodigoPlan` (formatea `PLAN-` + `numero` con 4 dígitos)
- [X] T009 [P] Crear las excepciones finales (`RuntimeException`, mensajes en español sin ids internos, cada una con `code` estable) en `$D/Domain/Exceptions/`: `PlanNoEncontrado`, `AccionNoEncontrada`, `NombreDePlanDuplicado`, `TransicionInvalida`, `PlanIncompleto` (lleva `faltantes: list<{accion_id, campo}>`), `PlanNoEditable`, `MotivoRequerido`, `AccionesSinFinalizar`, `PlanCambioDeEstado`, `UsuarioNoEsAprobador`, `AprobadorInvalido`, `CupoExcedido`, `ParticipantesRepetidos`, `ColaboradorInvalido`, `FechasFueraDelAnio`, `AccionYaCancelada`, `CompetenciaInactiva` (reutilizar la existente si ya sirve)
- [X] T010 [P] Crear `$D/Domain/Services/NormalizadorDeNombre.php`: minúsculas, `trim` y colapso de espacios internos (research R-03; sin tocar acentos). Prueba en T016
- [X] T011 [P] Crear `$D/Domain/Services/CalculadorDeEstadoDeAccion.php` (research R-05): `calcular(bool $cancelada, ?DateTimeImmutable $inicio, ?DateTimeImmutable $fin, DateTimeImmutable $hoy): EstadoAccion` — `cancelada` → Cancelada; sin fechas o `hoy < inicio` → Programada; `inicio ≤ hoy ≤ fin` → EnCurso; `hoy > fin` → Finalizada
- [X] T012 [P] Crear `$D/Domain/Services/DetectorDeCruces.php` (research R-07): dado `list<{accion_id, plan_id, colaborador_id, inicio, fin}>` de acciones **no canceladas con fechas**, devuelve `list<{colaborador_id, accion_id, con_accion_id, con_plan_id}>` para cada par del mismo colaborador con `inicio_a ≤ fin_b y inicio_b ≤ fin_a` (sin duplicar el par en ambos sentidos: se reporta una vez por acción afectada)
- [X] T013 Crear `$D/Domain/Services/ReglasDePlan.php` (puro, sin Laravel; research R-08, data-model «Transiciones»): `transicion(EstadoPlan $actual, EstadoPlan $destino): bool` solo para Borrador→Revisión, Revisión→Aprobado, Revisión→Borrador, Aprobado→Ejecutando, Ejecutando→Cerrado; `faltantes(plan): list<{accion_id, campo}>` con los campos obligatorios al enviar a revisión (≥ 1 acción; por acción `tipo`, `fechas`, `duracion_horas`, `formador`, `capacidad_maxima`, `costo`, ≥ 1 `participantes`; plan: `aprobador`); `puedeCerrar(estadosDeAcciones)`; `permite(EstadoPlan, Operacion)` con `Operacion` ∈ editar, cambiarParticipantes, cancelarAccion, reasignarAprobador (Borrador: editar y cambiarParticipantes; Revisión: reasignarAprobador; Aprobado y Ejecutando: cambiarParticipantes y cancelarAccion; Cerrado: nada); `puede(plan, usuarioId, esAdmin): array` que calcula el objeto `puede` del contrato (aprobar y devolver solo si `usuarioId == aprobador_id`)
- [X] T014 Crear las entidades `$D/Domain/Entities/Plan.php` y `AccionFormativa.php` (agregado `Plan` con sus acciones, competencias y participantes; totales de costo y horas **excluyendo canceladas**, `cantidadAcciones` no canceladas) y la interfaz `$D/Domain/Repositories/PlanRepositoryInterface.php`: `siguienteNumero(tenantId)`, `crear(Plan)`, `guardar(Plan)` (reemplazo transaccional de acciones; borra explícitamente competencias y participantes de las acciones quitadas, sin cascade), `obtener(tenantId, id)`, `existeNombre(tenantId, anio, nombreNormalizado, ?excluirId)`, `listar(tenantId, FiltroPlanes)`, `listarAcciones(tenantId, FiltroAcciones)`, `cambiarEstadoSi(tenantId, id, estadoEsperado, nuevo, ?fechaAprobacion, userId): bool` (actualización condicionada), `reasignarAprobador`, `registrarHistorial(...)`, `historial(tenantId, planId, pagina, porPagina)`, `accionesDeColaborador(tenantId, colaboradorId)`, `accionesConParticipantes(tenantId, colaboradorIds)` (para cruces)

### Backend – infraestructura y puertos

- [X] T015 Crear los modelos Eloquent en `$D/Infrastructure/Persistence/Eloquent/Models/`: `PlanModel`, `PlanAccionModel`, `PlanAccionCompetenciaModel`, `PlanAccionParticipanteModel`, `PlanHistorialModel` (sin `UPDATED_AT`), con relaciones y `$fillable`; sin `SoftDeletes`
- [X] T016 [P] Crear `backend/tests/Unit/Desarrollo/ReglasDePlanTest.php`, `CalculadorDeEstadoDeAccionTest.php`, `DetectorDeCrucesTest.php` y `NormalizadorDeNombreTest.php`: todas las transiciones válidas e inválidas; `faltantes` con cada campo ausente; edición permitida por estado (tabla completa de R-08); `puede` (aprobar/devolver solo para el aprobador); estado de acción en los bordes (`hoy == inicio`, `hoy == fin`, sin fechas, cancelada con fechas pasadas); cruces con fechas que se tocan en un día, acciones canceladas ignoradas, colaboradores distintos sin cruce; normalización de « Plan  TÉCNICO »
- [X] T017 Crear `$D/Infrastructure/Persistence/Eloquent/Repositories/EloquentPlanRepository.php` implementando `PlanRepositoryInterface`: todas las consultas filtran por `tenant_id`; `crear` y `guardar` en transacción; `siguienteNumero` como `max(numero)+1` con un reintento ante colisión del índice único (research R-04); `cambiarEstadoSi` con `UPDATE … WHERE estado = :esperado`; sin `delete` de planes; borrado de acciones solo desde `guardar`; consultas por lote sin N+1. Cubrir con `backend/tests/Feature/Desarrollo/PlanRepositoryTest.php` (aislamiento por tenant, único de nombre normalizado, número secuencial, cambio condicionado que falla si el estado cambió, historial solo-agregar)
- [X] T018 [P] Crear los puertos en `$D/Application/Ports/`: `ColaboradoresVigentesInterface` (`buscar(tenantId, ?texto, pagina, porPagina)` → `{items: [{id, codigo, nombre, puesto}], total}`; `vigentesPorIds(tenantId, ids): list<int>`), `AprobadoresInterface` (`administradores(tenantId): list<{id, nombre}>`; `esAdministradorActivo(tenantId, userId): bool`), `AsistenciaInterface` (`presentesPorAccion(list<int> accionIds, int $colaboradorId): list<int>`) y `RelojInterface` (`hoy(int $tenantId): DateTimeImmutable`)
- [X] T019 Ampliar **solo con métodos aditivos de solo lectura** `backend/app/Modules/GestionPersonal/Presentation/Facades/PersonalFacade.php` y `…/Application/Services/PersonalService.php` con `buscarColaboradoresVigentes(int $tenantId, ?string $texto, int $pagina, int $porPagina): array` y `vigentesPorIds(int $tenantId, array $ids): array` (vínculo laboral vigente), y `backend/app/Modules/Shared/Presentation/Facades/SharedFacade.php` y `…/Application/Services/SharedService.php` con `getAdministradores(int $tenantId): array` (`[{id, nombre}]` de usuarios activos con capacidad `admin`); documentar cada uno con `@method`. Pruebas Pest en `backend/tests/Feature/GestionPersonal/PersonalFacadeVigentesTest.php` y `backend/tests/Feature/Shared/SharedFacadeAdministradoresTest.php` (aislamiento por tenant, sin vigentes, texto sin coincidencias)
- [X] T020 [P] Crear los adaptadores en `$D/Infrastructure/Adapters/`: `ColaboradoresVigentesDesdePersonal` (llama a `PersonalFacade`), `AprobadoresDesdeShared` (llama a `SharedFacade::getAdministradores`), `RelojDelSistema` (zona horaria del tenant con `SharedFacade::getContextoDeTenant`; UTC si no hay) y el provisional `AsistenciaSinDatos` (devuelve `[]`; comentario que indica que se sustituye al implementar Actividades Formativas)
- [X] T021 [P] Crear los dobles de prueba en `backend/tests/Support/Desarrollo/`: `ColaboradoresVigentesFalso`, `AprobadoresFalso`, `AsistenciaFalsa` (permite fijar presentes) y `RelojFijo`
- [X] T022 Registrar en `$D/Infrastructure/Providers/DesarrolloServiceProvider.php` los bindings de `PlanRepositoryInterface`, `ColaboradoresVigentesInterface`, `AprobadoresInterface`, `RelojInterface` y el provisional `AsistenciaInterface` → `AsistenciaSinDatos`; en el entorno `e2e` enlazar los adaptadores de prueba de T023
- [X] T023 [P] Crear los adaptadores de prueba solo para el entorno `e2e` en `$D/Infrastructure/Adapters/Pruebas/`: `ColaboradoresVigentesDePrueba` (lista fija con las fichas del seeder e2e), `AprobadoresDePrueba` (administradores del tenant) y `RelojDePrueba` (fecha fija por la variable de entorno del e2e). Nunca se enlazan fuera de `e2e`

### Backend – presentación base

- [X] T024 Crear el esqueleto de rutas en `$D/Routes/api.php` para `api/planes` y `api/plan-acciones` con `middleware('api')`: `mi-plan` con solo `auth:sanctum`; el resto con `['auth:sanctum', 'admin']`; **rutas fijas (`mi-plan`, `opciones`, `colaboradores`, `cursos`) antes de `/{id}`** y `whereNumber('id')`; **sin `DELETE`**. Las rutas se activan historia por historia (cada historia agrega las suyas)
- [X] T025 [P] Crear `$D/Application/DTO/` base: `PlanDTO`, `FilaPlanDTO`, `AccionDTO`, `FilaAccionDTO`, `AvisoCruceDTO`, `PuedeDTO`, `HistorialPlanDTO`, `MiPlanDTO`, `OpcionesPlanDTO` con la forma de contracts/plan-api.md (importes `float` con 2 decimales, fechas `Y-m-d`, `fecha_aprobacion`/`creado_en`/`modificado_en` en ISO 8601) *(Implementado como arreglos con la forma del contrato armados en `ArmadorDePlanes`, sin clases DTO.)*

### Frontend – base

- [X] T026 Crear `$F/text.ts` con **todo** el texto visible en español (títulos, pestañas, columnas, botones, estados de plan y de acción, tipos, mensajes de validación, vacíos, errores, confirmaciones, motivos, avisos de cruce, textos del colaborador) y `$F/fecha.ts` (formateo de rangos «2 – 27 feb 2026», hoy en la zona del navegador, y la función pura `detectarCrucesLocales(acciones)` con la misma regla de solapamiento que `DetectorDeCruces`: `inicio_a ≤ fin_b y inicio_b ≤ fin_a`, acciones sin fechas ignoradas) con `$F/fecha.test.ts`
- [X] T027 [P] Crear `frontend/src/shared/ui/Tabs.tsx` (`role="tablist"`, `aria-selected`, navegación con flechas, altura 44 px, indicador `--accent`) y `Tabs.dom.test.tsx`; exportar en el índice de `shared/ui` si existe
- [X] T028 [P] Crear `$F/components/BadgeEstadoPlan.tsx`, `BadgeEstadoAccion.tsx` y `ChipTipo.tsx` (envoltorios de `Badge` con los colores de DESIGN.md §2; el color nunca es el único indicador: siempre llevan texto) y `Badges.dom.test.tsx`
- [X] T029 [P] Crear `$F/api.ts` (tipos TypeScript estrictos de los objetos del contrato y funciones para cada endpoint sobre el cliente HTTP compartido; sin `any`), `$F/validation.ts` (esquemas Zod espejo de `GuardarPlanRequest`: reglas de Borrador, fechas dentro del año, fin ≥ inicio, horas > 0, capacidad entero > 0, costo ≥ 0) y `$F/validation.test.ts`
- [X] T030 [P] Crear `$F/nav.tsx` (ítem «Plan anual» para ambos roles, ruta `/plan-formacion`, icono propio en `shared/ui/icons`) y `$F/components/RequireAdmin.tsx` (copiar el patrón de `dnf/components/RequireAdmin`)

**Checkpoint**: Fundamentos listos; las historias pueden empezar

---

## Phase 3: User Story 1 - Armar un plan en Borrador con el asistente (Priority: P1) 🎯 MVP

**Goal**: El Administrador crea un plan con el asistente de 3 pasos, con acciones (opcionalmente desde un curso del catálogo), participantes y aprobador, y lo guarda como Borrador aun incompleto.

**Independent Test**: Crear un plan con dos acciones, asignar participantes y guardar; verificar código `PLAN-0001`, estado Borrador, validaciones (nombre duplicado, fechas, capacidad) y que se puede editar de nuevo.

### Tests for User Story 1

- [X] T031 [P] [US1] `backend/tests/Feature/Desarrollo/PlanCrearTest.php`: 201 con código secuencial; Borrador con acción solo con nombre y competencias; 422 sin nombre, sin año, nombre duplicado en el año (mayúsculas y espacios laterales distintos) y permitido en otro año; acción sin competencias; competencia inactiva o inexistente; fecha fuera del año y fin anterior al inicio; horas ≤ 0, capacidad ≤ 0, costo < 0; participantes repetidos, inexistentes o sin vínculo vigente; participantes sobre la capacidad; aprobador no administrador; 401 sin token y 403 como Colaborador
- [X] T032 [P] [US1] `backend/tests/Feature/Desarrollo/PlanActualizarTest.php`: `PUT` reemplaza datos, acciones, competencias y participantes; las acciones quitadas se eliminan sin dejar huérfanos; 422 `plan_no_editable` si el plan no está en Borrador; 404 de otro tenant; el nombre propio no cuenta como duplicado
- [X] T033 [P] [US1] `backend/tests/Feature/Desarrollo/PlanConsultarTest.php`: `GET /api/planes/{id}` devuelve acciones, competencias activas o no (conservadas), participantes, totales sin acciones canceladas, `avisos_cruce` con otro plan y `puede`; `GET /api/planes` con filtros `anio`, `estado`, `q`, paginación y sin acciones; `GET /api/planes/opciones`, `/colaboradores` y `/cursos` (solo cursos `Disponible`, tipo sugerido `Online→ELearning`, `Presencial→Presencial`, `Mixto→null`, horas = minutos/60); **401 sin token y 403 como Colaborador en `GET /api/planes`, `GET /api/planes/{id}` y los tres endpoints de apoyo**; el `POST`/`PUT` devuelven `avisos_cruce` contra acciones de otros planes
- [X] T034 [P] [US1] `$F/components/Paso1Datos.dom.test.tsx`, `Paso2Acciones.dom.test.tsx`, `Paso3Participantes.dom.test.tsx`, `FormularioAccion.dom.test.tsx` y `TablaPlanes.dom.test.tsx`: validación por campo, conservación de valores al fallar, bloqueo de envío pendiente, curso del catálogo que propone tipo/formador/competencias, capacidad llena que bloquea agregar, aviso de cruce junto al participante, estados loading/empty/error/success

### Implementation for User Story 1 (backend)

- [X] T035 [US1] Crear `$D/Application/UseCases/CrearPlan.php` (valida la existencia y actividad de competencias vía `CompetenciasInterface`, colaboradores vigentes vía `ColaboradoresVigentesInterface`, aprobador vía `AprobadoresInterface`, fechas dentro del año, cupo y repetidos; `nombre_normalizado` con `NormalizadorDeNombre`; `numero` y `codigo`; `created_by`/`updated_by`; todo en una transacción) y `ActualizarPlan.php` (solo Borrador con `ReglasDePlan::permite`; actualiza `updated_by`)
- [X] T036 [US1] Crear `$D/Application/UseCases/ObtenerPlan.php` (calcula estado de cada acción con `CalculadorDeEstadoDeAccion` y `RelojInterface`, totales, `faltantes` por acción, avisos de cruce con `DetectorDeCruces`, nombres de usuario y de referencia, `puede` con `ReglasDePlan::puede`) y `ListarPlanes.php` (filtros `anio`, `estado`, `q` por `nombre_normalizado`; paginación; `cantidad_acciones` y `costo_total` sin canceladas; sin cargar acciones completas)
- [X] T037 [P] [US1] Crear `$D/Application/UseCases/ObtenerOpcionesDePlan.php` (aprobadores, tipos, competencias **activas**), `BuscarColaboradoresParaPlan.php` y `ListarCursosParaPlan.php` (lee `cursos` Disponible a través del repositorio del catálogo del mismo módulo; añadir el método de lectura que falte en `CatalogoFormativoRepositoryInterface` y su implementación Eloquent, con prueba)
- [X] T038 [P] [US1] Crear los Requests en `$D/Presentation/Requests/`: `GuardarPlanRequest` (reglas de Borrador del contrato: `nombre` requerido máx. 120, `anio` entero requerido, `descripcion` máx. 2000, `aprobador_id` opcional, `acciones.*.nombre` requerido máx. 120, `acciones.*.competencia_ids` requerido con ≥ 1, `tipo` en la enumeración, fechas `date`, `duracion_horas` > 0, `capacidad_maxima` entero > 0, `costo` ≥ 0, `colaborador_ids` sin repetidos; mensajes en español) y `ListarPlanesRequest` (`anio`, `estado` en la enumeración, `q` máx. 120, `page`, `per_page` 1–100)
- [X] T039 [P] [US1] Crear los Resources en `$D/Presentation/Resources/`: `PlanResource`, `FilaPlanResource`, `AccionResource` con la forma exacta de contracts/plan-api.md
- [X] T040 [US1] Crear `$D/Presentation/Controllers/PlanController.php` (delgado: `store`, `update`, `show`, `index`, `opciones`, `colaboradores`, `cursos`; sin consultas a BD) y registrar las rutas `POST /`, `GET /`, `GET /{id}`, `PUT /{id}`, `GET /opciones`, `GET /colaboradores`, `GET /cursos` en `$D/Routes/api.php`; mapear las excepciones de dominio a 422/404 con `code`; ejecutar T031–T033 hasta que pasen

### Implementation for User Story 1 (frontend)

- [X] T041 [US1] Crear los hooks en `$F/hooks/`: `usePlanParams` (filtros y paginación en la URL), `usePlanes`, `usePlan`, `useGuardarPlan` (crear/actualizar; sin reintentos; invalida `['planes']`), `useOpcionesPlan`, `useBuscarColaboradores` y `useCursosParaPlan`
- [X] T042 [US1] Crear `$F/components/FormularioAccion.tsx` (nombre, selector «Partir de un curso del catálogo» con `Combobox`, tipo, **competencias objetivo en selección múltiple**, fechas, duración en horas, formador, capacidad máxima, costo USD; en Borrador solo nombre y competencias son obligatorias) y `Paso1Datos.tsx` (nombre, año, descripción)
- [X] T043 [US1] Crear `$F/components/SelectorParticipantes.tsx` (búsqueda por colaborador con `Combobox`, **indicador de capacidad usada** `n/capacidad`, bloqueo al llegar al máximo, sin repetidos), `AvisosCruce.tsx` (aviso informativo junto al participante: combina los cruces calculados al instante con `detectarCrucesLocales` —solo entre acciones del propio asistente— y los `avisos_cruce` que devuelve el servidor al guardar, que incluyen otros planes), `Paso2Acciones.tsx` (lista con agregar, editar y quitar con `ConfirmDialog`) y `Paso3Participantes.tsx` (participantes por acción y selector de aprobador)
- [X] T044 [US1] Crear `$F/components/AsistentePlan.tsx` con `Stepper` de 3 pasos, **«Guardar borrador» disponible en cualquier paso**, estado conservado entre pasos, errores 422 mapeados por campo (`acciones.2.fecha_fin`) o en un `Alert`, y envío bloqueado mientras la mutación está pendiente; al terminar guarda en Borrador (enviar a revisión es una acción aparte)
- [X] T045 [US1] Crear `$F/components/TablaPlanes.tsx` (patrón Listado: código, nombre, año, estado con `BadgeEstadoPlan`, cantidad de acciones, costo total, aprobador y columna de acciones con los botones que habilita `puede` — en esta historia solo «Editar»; «Ver detalle» se añade en T053 cuando exista la ruta `/plan-formacion/:id`), `$F/pages/PlanesPage.tsx` (tabla con loading/empty/error/success y botón «Nuevo plan») y `$F/pages/AsistentePlanPage.tsx` (crear y editar `/plan-formacion/:id/editar`)
- [X] T046 [US1] Crear `$F/pages/PlanFormacionPage.tsx` (despacha por rol: Administrador → `PlanesPage`; Colaborador → `MiPlanPage`, que se implementa en US5; mientras tanto muestra `EmptyState`) y registrar en `frontend/src/App.tsx` las rutas `/plan-formacion`, `/plan-formacion/nuevo` y `/plan-formacion/:id/editar` (estas dos con `RequireAdmin`) y `planFormacionNav` en `navItems`

**Checkpoint**: US1 completa y probada de forma independiente (MVP)

---

## Phase 4: User Story 2 - Enviar a revisión, aprobar o devolver (Priority: P1)

**Goal**: El Administrador envía el plan a revisión; solo el aprobador designado lo aprueba o lo devuelve a Borrador con motivo; cualquier Administrador puede reasignar el aprobador en Revisión.

**Independent Test**: Con un plan completo, enviarlo a revisión, devolverlo con motivo, reenviarlo y aprobarlo; verificar estados, 403 para otro administrador, reasignación del aprobador e historial.

### Tests for User Story 2

- [X] T047 [P] [US2] `backend/tests/Feature/Desarrollo/PlanEstadoRevisionTest.php`: enviar a revisión rechaza (422 `plan_incompleto` con `faltantes`) sin acciones, sin aprobador, con acción incompleta y con acción sin participantes; plan completo → Revisión y de solo lectura (`PUT` → 422); aprobar con un administrador distinto del aprobador → 403; devolver sin motivo → 422 `motivo_requerido`; devolver con motivo → Borrador con el motivo en el historial; aprobar → Aprobado con `fecha_aprobacion`; transiciones inválidas → 422 `transicion_invalida`; carrera → 422 `plan_cambio_de_estado`; cada cambio registra historial `Estado` con usuario, estados y motivo
- [X] T048 [P] [US2] `backend/tests/Feature/Desarrollo/PlanReasignarAprobadorTest.php`: cualquier Administrador reasigna en Revisión (200; estado y `fecha_aprobacion` intactos; historial `Aprobador` con anterior y nuevo); 422 en Borrador, Aprobado, Ejecutando y Cerrado; 422 si el nuevo no es administrador activo; el nuevo aprobador ya puede aprobar y el anterior ya no; 403 como Colaborador; 404 de otro tenant
- [X] T049 [P] [US2] `$F/components/AccionesPlan.dom.test.tsx`, `MotivoDialog.dom.test.tsx`, `ConfirmarTransicionDialog.dom.test.tsx` y `DetallePlan.dom.test.tsx`: botones según `puede` (aprobar y devolver solo para el aprobador), motivo obligatorio, confirmación accesible con foco atrapado y Escape, lista de faltantes al enviar, diálogo de reasignación del aprobador

### Implementation for User Story 2

- [X] T050 [US2] Crear `$D/Application/UseCases/CambiarEstadoDePlan.php` (enviar a revisión, aprobar, devolver; usa `ReglasDePlan::transicion` y `faltantes`; verifica con `AprobadoresInterface` que el usuario del token sea el aprobador designado en aprobar/devolver; `cambiarEstadoSi` para evitar carreras; fija `fecha_aprobacion`; registra historial `Estado` en la misma transacción) y `ReasignarAprobador.php` (solo Revisión, cualquier Administrador, valida `esAdministradorActivo`, historial `Aprobador`)
- [X] T051 [US2] Crear `CambiarEstadoRequest` (`estado` requerido en la enumeración, `motivo` opcional máx. 500) y `ReasignarAprobadorRequest` en `$D/Presentation/Requests/`; añadir `estado` y `aprobador` a `PlanController` y registrar `PATCH /{id}/estado` y `PATCH /{id}/aprobador`; responder 403 `usuario_no_es_aprobador`; ejecutar T047–T048 hasta que pasen
- [X] T052 [US2] Crear `$F/hooks/useCambiarEstado.ts` y `useReasignarAprobador.ts` (invalidan `['planes']`, el detalle y el historial; sin reintentos) y `$F/components/MotivoDialog.tsx` (motivo obligatorio, mensaje del servidor visible) y `ConfirmarTransicionDialog.tsx` (aprobar, iniciar ejecución y cerrar piden confirmación)
- [X] T053 [US2] Crear `$F/components/AccionesPlan.tsx` (botones por estado según `puede`: Editar y Enviar a revisión en Borrador; Aprobar y Devolver en Revisión solo para el aprobador; «Cambiar aprobador» en Revisión; **«Ver detalle» en todos los estados, que se incorpora aquí**) y usarlo en `TablaPlanes.tsx`; mostrar la lista de faltantes de un 422 `plan_incompleto` en un `Alert`
- [X] T054 [US2] Crear `$F/components/DetallePlan.tsx` (datos generales, estado, aprobador, acciones con costo y horas totales; el patrón Detalle de un registro) y `$F/pages/PlanDetallePage.tsx` en `/plan-formacion/:id` (con `RequireAdmin`; loading/error/404) con los botones de `AccionesPlan` y el diálogo de reasignar aprobador; registrar la ruta en `frontend/src/App.tsx`

**Checkpoint**: US1 y US2 funcionan de forma independiente

---

## Phase 5: User Story 3 - Ejecutar, ajustar y cerrar un plan aprobado (Priority: P1)

**Goal**: Con el plan Aprobado el administrador inicia la ejecución, cambia participantes (respetando capacidad), cancela acciones con motivo y cierra el plan cuando todas están Finalizadas o Canceladas.

**Independent Test**: Aprobar un plan, iniciar ejecución, cambiar participantes, cancelar una acción y cerrar cuando todas estén Finalizadas o Canceladas; verificar que no se puede editar nada más ni eliminar.

### Tests for User Story 3

- [X] T055 [P] [US3] `backend/tests/Feature/Desarrollo/PlanEjecucionTest.php`: Aprobado→Ejecutando; cerrar con acciones Programadas o En curso → 422 `acciones_sin_finalizar`; cerrar con todas Finalizadas o Canceladas → Cerrado (también con todas canceladas); Cerrado no admite ningún cambio ni retroceso; no existen rutas `DELETE` (405/404) para planes y acciones; en Aprobado y Ejecutando `PUT /api/planes/{id}` → 422 `plan_no_editable`
- [X] T056 [P] [US3] `backend/tests/Feature/Desarrollo/PlanParticipantesTest.php`: `PUT /api/plan-acciones/{id}/participantes` permitido en Borrador, Aprobado y Ejecutando y rechazado en Revisión y Cerrado (422 `plan_no_editable`); 422 `cupo_excedido`, `participantes_repetidos`, `colaborador_invalido`; los ya inscritos que perdieron el vínculo se **conservan** y siguen ocupando cupo, y se pueden quitar manualmente; desde la aprobación cada agregado y cada quitado genera un renglón `Participante` (en Borrador no hay historial); devuelve `avisos_cruce`; 422 `accion_cancelada`; 404 de otro tenant
- [X] T057 [P] [US3] `backend/tests/Feature/Desarrollo/PlanCancelarAccionTest.php`: `PATCH /api/plan-acciones/{id}/cancelar` solo en Aprobado y Ejecutando; 422 `motivo_requerido`, `accion_ya_cancelada` y `plan_no_editable`; la acción queda Cancelada con motivo, deja de contar en costo, horas y cantidad del plan y genera historial `Cancelacion`; 403 como Colaborador
- [X] T058 [P] [US3] `$F/components/CancelarAccionDialog.dom.test.tsx` y `EditorParticipantes.dom.test.tsx`: motivo obligatorio y texto «sus participantes dejarán de verla como pendiente»; capacidad respetada; botones ocultos cuando el estado del plan no lo permite

### Implementation for User Story 3

- [X] T059 [US3] Extender `CambiarEstadoDePlan` con Aprobado→Ejecutando y Ejecutando→Cerrado (`ReglasDePlan::puedeCerrar` con los estados calculados de las acciones y `RelojInterface`; error `AccionesSinFinalizar`; historial `Estado`)
- [X] T060 [US3] Crear `$D/Application/UseCases/CambiarParticipantes.php` (valida `ReglasDePlan::permite`, repetidos, cupo, y que **solo los agregados nuevos** tengan vínculo vigente con `vigentesPorIds`; reemplaza en una transacción; historial `Participante` por cada agregado o quitado solo si el plan es Aprobado o Ejecutando; devuelve los avisos de cruce) y `CancelarAccion.php` (solo Aprobado y Ejecutando; motivo obligatorio; historial `Cancelacion`)
- [X] T061 [US3] Crear `$D/Presentation/Controllers/PlanAccionController.php` (`participantes`, `cancelar`), `CambiarParticipantesRequest` (`colaborador_ids` array de enteros) y `CancelarAccionRequest` (`motivo` requerido máx. 500) y registrar `PUT /api/plan-acciones/{id}/participantes` y `PATCH /api/plan-acciones/{id}/cancelar` con `whereNumber`; ejecutar T055–T057 hasta que pasen
- [X] T062 [US3] Crear `$F/hooks/useCambiarParticipantes.ts` y `useCancelarAccion.ts` (invalidan detalle, historial, `['planes']` y `['plan-acciones']`), `$F/components/EditorParticipantes.tsx` (reutiliza `SelectorParticipantes` con capacidad; muestra los avisos de cruce devueltos) y `CancelarAccionDialog.tsx` (reutiliza `MotivoDialog`)
- [X] T063 [US3] Integrar en `DetallePlan.tsx`: botones «Iniciar ejecución» y «Cerrar plan» (con `ConfirmarTransicionDialog`), «Cambiar participantes» y «Cancelar acción» por acción según `puede`, y el badge adicional «Cancelada»; en `AccionesPlan.tsx` añadir «Iniciar ejecución» (Aprobado) y «Cerrar plan» (Ejecutando)

**Checkpoint**: Ciclo de vida completo; US1–US3 funcionan de forma independiente

---

## Phase 6: User Story 4 - Consultar planes, acciones y calendario (Priority: P2)

**Goal**: El administrador filtra planes y consulta las acciones del año en las pestañas «Calendario» y «Lista» con filtros comunes.

**Independent Test**: Con varios planes en distintos estados, verificar columnas, filtros, botones por estado y que el calendario muestre cada acción en los días que abarca.

### Tests for User Story 4

- [X] T064 [P] [US4] `backend/tests/Feature/Desarrollo/PlanAccionesListadoTest.php`: `GET /api/plan-acciones` con filtros `estado` (del plan), `tipo`, `competencia_id`, `fecha_desde`/`fecha_hasta` (solapamiento, una acción de varios días aparece en cualquier mes que toque), `plan_id` y `anio` (por defecto el año actual); `per_page` hasta 200; orden por `fecha_inicio`; `estado_accion` calculado; canceladas incluidas con `cancelada: true`; acciones incompletas sin fechas fuera de los rangos de fecha; 403 como Colaborador; aislamiento por tenant
- [X] T065 [P] [US4] `$F/components/CalendarioAcciones.dom.test.tsx`, `ListaAcciones.dom.test.tsx`, `FiltrosAcciones.dom.test.tsx` y `pages/AccionesPage.dom.test.tsx`: una acción de varios días pinta cada día con color y texto del tipo, clic abre el detalle, navegación de mes y «Hoy», teclado, aviso cuando hay más de 200, los filtros se conservan al cambiar de pestaña y se reflejan en la URL, canceladas con badge «Cancelada», estados loading/empty/error

### Implementation for User Story 4

- [X] T066 [US4] Crear `$D/Application/UseCases/ListarAccionesDePlanes.php` y `FiltroAcciones` (value object) usando `PlanRepositoryInterface::listarAcciones` (consulta por lote de competencias e inscritos, sin N+1), `ListarAccionesRequest` (`estado`, `tipo`, `competencia_id`, `fecha_desde`, `fecha_hasta` con `fecha_hasta ≥ fecha_desde`, `anio`, `plan_id`, `page`, `per_page` 1–200) y `FilaAccionResource`; añadir `index` a `PlanAccionController` y la ruta `GET /api/plan-acciones`; ejecutar T064 hasta que pase
- [X] T067 [US4] Crear `$F/hooks/useAcciones.ts` (caché `['plan-acciones', params]`; el calendario pide el rango visible con `per_page=200`) y `$F/components/FiltrosAcciones.tsx` (estado del plan, tipo, competencia, desde, hasta, «Limpiar filtros»; todo en la URL)
- [X] T068 [US4] Crear `$F/components/ListaAcciones.tsx` (tabla: plan, nombre, competencia objetivo, tipo, fechas, formador, inscritos/capacidad, costo y estado con badge del plan; acciones canceladas con el badge adicional «Cancelada»; `Pagination`) y `CalendarioAcciones.tsx` (vista mensual, cada acción en los días que abarca con `ChipTipo`, clic abre `DetalleAccionPanel`, bajo 640 px pasa a lista por día)
- [X] T069 [US4] Crear `$F/components/DetalleAccionPanel.tsx`, `$F/components/TabsPlan.tsx` (pestañas «Calendario» y «Lista» con `Tabs`, `?vista=`) y `$F/pages/AccionesPage.tsx` (por defecto el año actual); registrar `/plan-formacion/acciones` con `RequireAdmin` en `frontend/src/App.tsx` y enlazarlo desde `PlanesPage`
- [X] T070 [US4] Completar `PlanesPage.tsx` con los filtros por año, estado y búsqueda por nombre en la URL (reutilizando `SelectField` y `SearchField`) y el estado vacío «Ningún plan coincide»

**Checkpoint**: US4 funciona de forma independiente

---

## Phase 7: User Story 5 - Mis acciones formativas como colaborador (Priority: P2)

**Goal**: El colaborador ve cronológicamente sus acciones de planes Aprobado, Ejecutando o Cerrado, su «% del plan completado» y sus tres próximas actividades.

**Independent Test**: Con un colaborador inscrito en acciones de planes en distintos estados, verificar qué ve, el porcentaje, las próximas tres actividades y el mensaje sin acciones.

### Tests for User Story 5

- [X] T071 [P] [US5] `backend/tests/Feature/Desarrollo/PlanMiPlanTest.php`: solo acciones asignadas al colaborador del token en planes Aprobado, Ejecutando y Cerrado (nunca Borrador ni Revisión); orden cronológico; con 4 acciones asignadas no canceladas y 1 Finalizada con asistencia Presente → 25 %; Finalizada sin asistencia (adaptador provisional) no cuenta; canceladas visibles con su motivo y excluidas del porcentaje y de `proximas`; `proximas` con las tres de inicio ≥ hoy más cercanas; sin acciones → respuesta vacía con 0 %; usuario sin ficha → respuesta vacía; no filtra datos de otro colaborador ni de otro tenant; 401 sin token
- [X] T072 [P] [US5] `$F/components/TarjetaAccion.dom.test.tsx`, `ProgresoDelPlan.dom.test.tsx`, `ProximasActividades.dom.test.tsx` y `pages/MiPlanPage.dom.test.tsx`: tarjeta con nombre, competencia, fechas, formador, estado y «Ver detalle»; acción cancelada con motivo; barra `role="progressbar"` con `aria-valuenow`; mensaje informativo sin acciones; estados loading/error

### Implementation for User Story 5

- [X] T073 [US5] Crear `$D/Application/UseCases/ObtenerMiPlan.php` (usuario → colaborador con `CuentaColaboradorInterface`; acciones del colaborador por `accionesDeColaborador`; estado calculado con `RelojInterface`; `completada` = Finalizada y presente según `AsistenciaInterface`; porcentaje = completadas / asignadas no canceladas redondeado a entero; `proximas` ≤ 3 no canceladas con inicio ≥ hoy ordenadas por inicio) y `MiPlanResource`; añadir `miPlan` a `PlanController` y la ruta `GET /api/planes/mi-plan` (solo `auth:sanctum`, **declarada antes de `/{id}`**); ejecutar T071 hasta que pase
- [X] T074 [US5] Crear `$F/hooks/useMiPlan.ts`, `$F/components/TarjetaAccion.tsx` (con `BadgeEstadoAccion`, `ChipTipo` y motivo si está cancelada), `ProgresoDelPlan.tsx` («% del plan completado» con barra accesible), `ProximasActividades.tsx` y el panel de detalle de solo lectura (nombre, competencia, fechas, formador, duración; sin «Lugar o plataforma» ni avance por acción)
- [X] T075 [US5] Crear `$F/pages/MiPlanPage.tsx` (lista cronológica, progreso, próximas actividades y `EmptyState` «Aún no tienes acciones formativas asignadas») y sustituir el marcador de `PlanFormacionPage` (T046) por esta página para el rol Colaborador

**Checkpoint**: US5 funciona de forma independiente

---

## Phase 8: User Story 6 - Historial y detalle del plan (Priority: P2)

**Goal**: El administrador audita un plan: datos generales, origen con enlace a la DNF, totales e historial de cambios de estado y de cambios posteriores a la aprobación.

**Independent Test**: Realizar transiciones y cambios posteriores a la aprobación y verificar el historial y los datos de auditoría.

### Tests for User Story 6

- [X] T076 [P] [US6] `backend/tests/Feature/Desarrollo/PlanHistorialTest.php`: `GET /api/planes/{id}/historial` devuelve más reciente primero, paginado, con tipo, acción afectada, valores, motivo, usuario y fecha; contiene cambios de estado con motivo al devolver, participantes agregados o quitados y cancelaciones desde la aprobación, y reasignaciones de aprobador; **no** contiene cambios de participantes hechos en Borrador; 404 si el plan no existe o es de otro tenant; 403 como Colaborador; el plan expone creador, fecha de creación, último modificador y fecha
- [X] T077 [P] [US6] `$F/components/HistorialPlan.dom.test.tsx`: renderiza cada tipo de renglón con usuario, fecha y detalle; paginación; estados vacío, loading y error; `DetallePlan` muestra el origen con enlace a `/dnf/:id`

### Implementation for User Story 6

- [X] T078 [US6] Crear `$D/Application/UseCases/ListarHistorialDePlan.php`, `ListarHistorialPlanRequest` (`page`, `per_page` 1–100) y `HistorialPlanResource`; añadir `historial` a `PlanController` y la ruta `GET /api/planes/{id}/historial`; asegurarse de que `ObtenerPlan` entrega `creado_por`, `creado_en`, `modificado_por`, `modificado_en` y `origen_dnf` (código con `DnfRepositoryInterface`); ejecutar T076 hasta que pase
- [X] T079 [US6] Crear `$F/hooks/useHistorialPlan.ts` y `$F/components/HistorialPlan.tsx` (lista cronológica con badge por tipo y motivo) e integrarlo en `DetallePlan.tsx` junto con la auditoría (creado por/cuándo, modificado por/cuándo) y el origen con enlace a la DNF si existe

**Checkpoint**: US6 funciona de forma independiente

---

## Phase 9: User Story 7 - Recibir un borrador desde la DNF (Priority: P3)

**Goal**: La DNF solicita, por la Facade del módulo, un borrador con una acción por competencia con brecha y los colaboradores con brecha como participantes.

**Independent Test**: Generar una DNF con brechas de varios colaboradores y pulsar «Generar Plan de Formación»; verificar el plan en Borrador con acciones, participantes y origen.

### Tests for User Story 7

- [X] T080 [P] [US7] `backend/tests/Feature/Desarrollo/PlanDesdeDnfTest.php`: `DesarrolloFacade::crearBorradorDesdeDnf` crea un plan Borrador del año actual con nombre `Plan DNF {código DNF}`, `origen_dnf_id`, una acción por competencia con brecha (nombre de la competencia, esa competencia como objetivo, demás campos nulos) y los colaboradores con brecha como participantes, devuelve el id y registra `created_by`; competencia inactiva no bloquea; el borrador incompleto no se puede enviar a revisión (misma `faltantes`); sin brechas lanza excepción; `POST /api/dnf/{id}/plan` (006) ya devuelve el id real y `GET /api/planes/{id}` enlaza el origen; segundo intento → `PlanYaGenerado`
- [X] T081 [P] [US7] Localizar con `grep` los tests de 006 que dependen del adaptador provisional `PlanDeFormacionNoDisponible` o de `PlanNoDisponible` (el doble existente es `backend/tests/Support/Desarrollo/PlanDeFormacionFalso.php` y los Feature de DNF que lo usan) y actualizar solo los que prueben el adaptador real para esperar el borrador creado; los que usan el doble se dejan intactos

### Implementation for User Story 7

- [X] T082 [US7] Crear `$D/Application/UseCases/CrearBorradorDesdeDnf.php` (agrupa `brechas` por competencia; una acción por competencia con brecha; participantes únicos; transacción; nombre `Plan DNF {código}` obtenido con `DnfRepositoryInterface`; año actual con `RelojInterface`) y `$D/Infrastructure/Adapters/PlanDeFormacionDesdePlanes.php` (implementa `PlanDeFormacionInterface` de 006 delegando en el caso de uso)
- [X] T083 [US7] Cambiar en `DesarrolloServiceProvider.php` el binding de `PlanDeFormacionInterface` de `PlanDeFormacionNoDisponible` a `PlanDeFormacionDesdePlanes` (dejar `PlanDeFormacionDePrueba` en `e2e`; marcar el provisional como obsoleto o eliminarlo si ya no se usa) y exponer `crearBorradorDesdeDnf(int $tenantId, int $actorId, int $dnfId, array $brechas): int` en `DesarrolloService` y `DesarrolloFacade` (con `@method`)
- [X] T084 [US7] Verificar en el frontend que el botón de 006 «Generar Plan de Formación» enlaza ahora a `/plan-formacion/:id` (ajustar solo el destino del enlace en `frontend/src/modules/desarrollo/dnf/components/BotonGenerarPlan.tsx` y su prueba) y que `DetallePlan` muestra el origen

**Checkpoint**: Todas las historias funcionan de forma independiente

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Facades de salida, siembra, E2E, calidad y verificación final

- [X] T085 [P] Exponer en `DesarrolloService` y `DesarrolloFacade` (con `@method`) `getAccionesDePlanesEjecutando(int $tenantId): array` (acciones **no canceladas** de planes Ejecutando con sus participantes, para Actividades Formativas) y `getPlanesYAcciones(int $tenantId, array $filtros = []): array` (filtros `anio`, `estado`, `plan_id`; para Reportes), solo lectura, según contracts/plan-puertos-y-facades.md; prueba en `backend/tests/Feature/Desarrollo/PlanFacadeTest.php` (aislamiento por tenant, canceladas excluidas en la primera, totales en la segunda)
- [X] T086 [P] Crear `backend/database/seeders/PlanFormacionSeeder.php` según data-model.md «Seeder»: 5 planes (uno en cada estado) con acciones de los seis tipos, al menos una cancelada, acciones calculadas Programada, En curso y Finalizada, y el Borrador con acciones incompletas como las de la DNF; depende de los seeders de Estructura, Gestión de Personal y cursos; registrarlo en `DatabaseSeeder` y probar que es idempotente con `backend/tests/Feature/Desarrollo/PlanSeederTest.php`
- [X] T087 Crear `frontend/e2e/plan-formacion.spec.ts` (herramienta E2E existente, entorno `e2e`): el Administrador crea un plan con una acción y un participante, lo envía a revisión y el aprobador lo aprueba; el Colaborador inscrito ve su acción en «Mi plan de formación»; localizar campos con `getByRole('textbox', { name, exact: true })`
- [X] T088 [P] Revisar accesibilidad y responsive de todas las pantallas nuevas (WCAG 2.2 AA, foco visible, 44 px de objetivo táctil, calendario por teclado, temas claro y oscuro, 320–1440 px sin scroll horizontal) y registrar en `design/DESIGN.md` cualquier ajuste (Constitución III)
- [X] T089 [P] Ejecutar `vendor/bin/pint` y `vendor/bin/pest` en `backend/` y `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `npx playwright test` en `frontend/`; corregir fallos propios y dejar anotados los preexistentes de la línea base (T001)
- [X] T090 Recorrer los 10 escenarios de `specs/013-plan-formacion-anual/quickstart.md` en el entorno local y anotar el resultado; si algún contrato cambió durante la implementación, actualizar `contracts/plan-api.md` y `contracts/plan-puertos-y-facades.md`
- [X] T091 [P] Confirmar que la desviación de prefijo de rutas (`/api/planes`, `/api/plan-acciones`) sigue registrada en plan.md «Desviaciones» y que ninguna tarea la contradice; revisar que ningún `Desarrollo` acceda a tablas o modelos de otros módulos (`grep` de `DB::table('colaboradores'|'users'|…)`), que no exista ninguna ruta `DELETE` en `/api/planes` ni `/api/plan-acciones`, y que no se hayan añadido dependencias nuevas

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias.
- **Foundational (Phase 2)**: depende de Setup; **bloquea todas las historias**.
- **US1 (Phase 3)**: depende de Foundational. Es el MVP.
- **US2 (Phase 4)**: depende de Foundational y de `ObtenerPlan`/`PlanController` de US1 (T036, T040) y de `TablaPlanes` (T045).
- **US3 (Phase 5)**: depende de US2 (`CambiarEstadoDePlan`, `DetallePlan`).
- **US4 (Phase 6)**: depende de Foundational y de US1 (modelos y repositorio); puede ir en paralelo con US2/US3 tras US1.
- **US5 (Phase 7)**: depende de Foundational y US1; puede ir en paralelo con US2–US4 (el porcentaje se verifica plenamente con planes aprobados, que se crean por factories en las pruebas).
- **US6 (Phase 8)**: depende de US2 y US3 (que generan los renglones del historial) y de `DetallePlan`.
- **US7 (Phase 9)**: depende de Foundational y US1 (`Plan`, repositorio); independiente de US2–US6.
- **Polish (Phase 10)**: depende de las historias que se quieran entregar.

### Within each story

Pruebas primero (deben fallar) → casos de uso y entidades → Requests/Resources/controlador y rutas → frontend (hooks, componentes, páginas, rutas).

### Parallel Opportunities

- Fundacional: T005, T006, T007 (migraciones), T008–T012 (dominio puro), T016, T018, T020, T021, T023, T025, T027–T030 son `[P]`.
- Tras US1: US4, US5 y US7 pueden avanzar en paralelo con US2→US3 (archivos distintos salvo `DetallePlan.tsx`, `PlanController.php` y `Routes/api.php`, que deben coordinarse).
- Dentro de cada historia, las tareas de pruebas `[P]` y los Requests/Resources `[P]` se hacen a la vez.

### Parallel Example: User Story 1

```text
Task: "T031 PlanCrearTest.php"          Task: "T032 PlanActualizarTest.php"
Task: "T033 PlanConsultarTest.php"      Task: "T034 pruebas de componentes del asistente"
Task: "T037 casos de uso de opciones"   Task: "T038 Requests"   Task: "T039 Resources"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Completar Setup y Foundational.
2. Completar US1 (crear y editar un Borrador con el asistente).
3. **Detenerse y validar** US1 de forma independiente (T031–T033 y el escenario 1 del quickstart).

### Incremental Delivery

1. Setup + Foundational → base lista.
2. US1 → MVP (crear Borrador).
3. US2 → aprobación; US3 → ejecución y cierre (ciclo de vida completo, las tres P1).
4. US4, US5 y US6 → consulta, colaborador y auditoría (P2).
5. US7 → integración con la DNF (P3); Polish → Facades de salida, seeder y E2E.

### Notes

- `[P]` = archivos distintos y sin dependencias pendientes.
- Cada historia debe poder completarse y probarse de forma independiente.
- Hacer commit tras cada tarea o grupo lógico; **no** hacer commit de `design/PlanAnual*.dc.html` salvo que el usuario lo pida.
- La asistencia es provisional (`AsistenciaSinDatos`): el progreso del colaborador será 0 % hasta implementar Actividades Formativas; es el comportamiento esperado (FR-024).
