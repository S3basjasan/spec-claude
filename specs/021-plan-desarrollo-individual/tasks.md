---

description: "Tareas de la feature 021-plan-desarrollo-individual"
---

# Tasks: Plan de desarrollo individual (PDI) y compromisos de mejora

**Input**: Documentos de diseño en `/specs/021-plan-desarrollo-individual/`

**Prerequisites**: plan.md, spec.md (con las aclaraciones de la sesión 2026-10-22), research.md, data-model.md, contracts/api.md, contracts/facade.md, contracts/ui.md, quickstart.md. Requiere las features 000-login, 001/004 (puestos y competencias en `EstructuraFacade`), 005-unidad-organizacional (departamentos), 005-fichas-colaboradores y 006-ingresos-reingresos (`PersonalFacade`, vínculo vigente), 006-deteccion-necesidades-formativas (DNF), 013-plan-formacion-anual, 017-actividades-formativas, 019-rutas-carrera y 020-adecuacion-puesto-futuro, ya implementadas.

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario (US1–US8 de spec.md) para implementarlas y probarlas de forma independiente. La feature añade cinco tablas propias al módulo `Desarrollo`, **amplía `DesarrolloFacade`** con métodos de solo lectura y no toca tablas de otros módulos. Las fases siguen el orden de prioridad (P1: US1, US2, US3, US6; P2: US4, US5, US7; P3: US8).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1–US8)
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Desarrollo/…`. Abreviatura **`$D`** = `backend/app/Modules/Desarrollo`
- Frontend: `frontend/src/modules/desarrollo/pdi/…` (reutiliza `frontend/src/shared/ui/…`). Abreviatura **`$F`** = `frontend/src/modules/desarrollo/pdi`
- Pruebas backend: `backend/tests/Unit/Desarrollo/Pdi/` y `backend/tests/Feature/Desarrollo/Pdi/`; pruebas de componentes: `*.dom.test.tsx` junto al código (con `// @vitest-environment jsdom`); fixtures en `frontend/src/test/pdiFixtures.ts`; E2E en `frontend/e2e/pdi.spec.ts`
- Regla de módulos: el PDI **no lee tablas de Estructura, Personal, DNF, Adecuación, Plan de Formación ni Actividades**; usa puertos de `Desarrollo` cuyos adaptadores llaman a `EstructuraFacade`, `PersonalFacade` y `DesarrolloFacade`. `colaborador_id`, `puesto_objetivo_id`, `elemento_id`, `origen_dnf_id`, `origen_adecuacion_id`, `plan_accion_id` y los ids de usuario son enteros **sin clave foránea** hacia otros módulos; las FK internas (`pdi_id`, `compromiso_id`) son `restrictOnDelete`
- Valores en base de datos (sin tildes; la interfaz muestra con tilde y espacios): `tipo` ∈ `Mejora` | `Carrera` | `Talento`; `estado` ∈ `Borrador` | `Acordado` | `Cerrado`; `dimension` ∈ `Competencia` | `Conocimiento` | `Experiencia` | `Meta`; acción `tipo` ∈ `Curso` | `Mentoring` | `Coaching` | `Autodesarrollo` | `Codesarrollo` | `NuevaResponsabilidad` | `Otra`; acción `estado` ∈ `Pendiente` | `EnCurso` | `Cumplida` | `Cancelada`; hito `estado` ∈ `Pendiente` | `Realizado`; `resultado` del plan ∈ `BrechaCerrada` | `Parcial` | `NoCerrada`; `resultado` del compromiso ∈ `Cerrada` | `Parcial` | `NoCerrada`
- Permisos: catálogo en código (`ClaveDePermisoPdi`, mismo patrón que `ClaveDePermisoAdecuacion`) con `desarrollo.pdi.gestionar`, `.acordar`, `.seguimiento`, `.vincular`, `.cerrar`, `.gantt`, `.ver`, `.ver_propio` y `.aceptar_propio`; `admin` recibe las siete de gestión, `user` solo las dos terminadas en `_propio`; un único middleware `permiso:<clave>` (que además acepta `permiso:a|b`) y `GET /api/pdi/permisos` para el frontend. Los controladores no consultan el rol
- Reutilizar sin modificar: `Button`, `Badge`, `Alert`, `ConfirmDialog`, `Modal`, `SelectField`, `SearchField`, `TextField`, `TextareaField`, `Tabs`, `Pagination`, `DataTable`, `EmptyState`, `ErrorState`, `Skeleton` y `AppShell`. No crear componentes equivalentes
- En frontend, `pdi` **no importa nada** de `adecuacion`, `actividades`, `reportes`, `plan-formacion`, `micro-learning`, `dnf`, `rutas` ni `catalogo-formativo`, salvo `modules/auth/login` (rol); copiar el patrón de `RequirePermiso` de `adecuacion` en lugar de importarlo. Los enlaces a Adecuación y a Actividades son solo URLs
- Guía visual **exacta**: `design/PDIAdmin.dc.html`, `PDIAsistente.dc.html`, `PDIDetalle.dc.html`, `PDISeguimiento.dc.html` (mismo prototipo del administrador) y `PDIColaborador.dc.html`; el detalle por pantalla está en `contracts/ui.md`. Manda la spec en reglas y datos; los tableros, en aspecto y estructura (plan.md, tabla «Diferencias entre tableros y spec»)
- Aclaraciones de la spec que hay que respetar en todo el código: (1) el colaborador solo ve y responde un Borrador **después** del acuerdo del responsable; (2) el responsable puede cambiarse en un plan Acordado **sin** borrar acuerdos; (3) al cerrar se **congela** el estado de las acciones vinculadas (`estado_congelado`)
- Concurrencia: todos los comandos de plan reciben `version` y devuelven 409 si cambió
- Lecciones previas: en E2E localizar campos con `getByRole('textbox', { name, exact: true })`; escribir archivos con acentos con la herramienta Write (los heredocs de Bash fallan; tampoco hay Python); ver la memoria «Entorno local de pruebas» (OneDrive/Windows) al correr Pest, Vitest y Playwright; no asumir `IngresosSeeder` en los E2E
- Sin dependencias nuevas

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Línea base verde y configuración antes de tocar código

- [X] T001 Verificar la línea base: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run --testTimeout=30000` y `npx playwright test` en `frontend/` (sin cambios de archivos). Anotar los fallos preexistentes de la memoria del proyecto (seeders de 017, dompdf) para no atribuirlos a esta feature
- [X] T002 Añadir en `design/DESIGN.md` la sección «PDI (feature 021)» (patrones de lista, asistente, detalle, Gantt y «Mi plan de desarrollo») y las filas de «Diferencias conocidas entre mockups y specs» de la tabla de plan.md (jefatura→responsable, firma→acuerdo/aceptación, sin «En seguimiento», varias acciones por compromiso, cierre por modal, persona a cargo en texto, sin duración en meses, filtros añadidos). Constitución III: documentar antes de implementar frontend
- [X] T003 Añadir en `backend/config/desarrollo.php` la clave `pdi` con `conocimientos_con_grado` (`(bool) env('DESARROLLO_PDI_CONOCIMIENTOS_CON_GRADO', false)`), con comentario (la rama `true` no se implementa; con `false` la dimensión Conocimiento se **desactiva**: la precarga no genera conocimientos, `GET /api/pdi/opciones` no la lista en `dimensiones_disponibles` y crear/editar un compromiso de Conocimiento responde 422), y documentarla en `backend/.env.example`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Tablas, modelos, dominio base, repositorio, autorización por permiso y piezas de frontend que usan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend – datos

- [X] T004 Crear `backend/database/migrations/2026_10_25_000001_create_pdis_table.php` según data-model.md: `id`; `tenant_id` entero sin signo con índice; `codigo` `string(12)` (`PDI-0001`); `colaborador_id` entero sin signo (sin FK); `tipo` `string(8)` ∈ Mejora | Carrera | Talento; `objetivo` `string(500)`; `puesto_objetivo_id` entero sin signo **anulable**; `origen_dnf_id` y `origen_adecuacion_id` enteros sin signo **anulables**; `responsable_id` entero sin signo; `fecha_inicio` y `fecha_fin` `date`; `estado` `string(8)` por defecto `Borrador`; `version` entero sin signo por defecto 1; `acuerdo_responsable_at` datetime anulable; `acuerdo_responsable_by` entero sin signo anulable; `aceptacion_colaborador_at` datetime anulable; `comentario_colaborador` `string(1000)` anulable; `resultado` `string(13)` anulable; `conclusion` `string(1000)` anulable; `cerrado_at` datetime anulable; `cerrado_by` entero sin signo anulable; `created_by` y `updated_by` enteros sin signo; `timestamps`. Índices: único `(tenant_id, codigo)`; `(tenant_id, colaborador_id, estado)`; `(tenant_id, estado, fecha_fin)`; `(tenant_id, responsable_id)`. Crear además con `DB::statement` el **índice único parcial** `pdis_abierto_unico` sobre `(tenant_id, colaborador_id, tipo) WHERE estado <> 'Cerrado'` y eliminarlo en `down()` (research R8). Sin soft deletes ni cascada
- [X] T005 [P] Crear `backend/database/migrations/2026_10_25_000002_create_pdi_compromisos_table.php`: `id`; `tenant_id` con índice; `pdi_id` FK a `pdis` `restrictOnDelete`; `orden` entero pequeño sin signo; `dimension` `string(12)` ∈ Competencia | Conocimiento | Experiencia | Meta; `tipo_elemento` `string(12)` **anulable** ∈ Competencia | Conocimiento (solo en esas dimensiones); `elemento_id` entero sin signo **anulable** (sin FK); `descripcion` `string(500)` anulable; `nivel_actual` y `nivel_objetivo` `char(1)` **anulables** ∈ A|B|C|D; `indicador` `string(500)` anulable; `fecha_inicio` y `fecha_fin` `date`; `resultado` `string(10)` anulable ∈ Cerrada | Parcial | NoCerrada; `timestamps`. Índice `(pdi_id, orden)`
- [X] T006 [P] Crear `backend/database/migrations/2026_10_25_000003_create_pdi_acciones_table.php`: `id`; `tenant_id` con índice; `compromiso_id` FK a `pdi_compromisos` `restrictOnDelete`; `orden` entero pequeño sin signo; `tipo` `string(20)` ∈ Curso | Mentoring | Coaching | Autodesarrollo | Codesarrollo | NuevaResponsabilidad | Otra; `descripcion` `string(500)`; `persona_cargo` `string(150)` anulable; `fecha_inicio` y `fecha_fin` `date`; `plan_accion_id` entero sin signo **anulable**, **no único** y sin FK; `estado` `string(10)` por defecto `Pendiente` ∈ Pendiente | EnCurso | Cumplida | Cancelada; `estado_congelado` booleano por defecto `false`; `created_by` y `updated_by` enteros sin signo; `timestamps`. Índices `(compromiso_id, orden)` y `(tenant_id, plan_accion_id)`
- [X] T007 [P] Crear `backend/database/migrations/2026_10_25_000004_create_pdi_hitos_table.php`: `id`; `tenant_id` con índice; `pdi_id` FK a `pdis` `restrictOnDelete`; `fecha` `date`; `descripcion` `string(300)`; `estado` `string(9)` por defecto `Pendiente` ∈ Pendiente | Realizado; `observaciones` `string(500)` anulable; `registrado_by` entero sin signo anulable; `registrado_at` datetime anulable; `timestamps`. Índice `(pdi_id, fecha)`
- [X] T008 [P] Crear `backend/database/migrations/2026_10_25_000005_create_pdi_historial_table.php`: `id`; `tenant_id` con índice; `pdi_id` FK a `pdis` `restrictOnDelete`; `tipo` `string(12)` ∈ Creacion | Edicion | Estado | Acuerdo | Aceptacion | Devolucion | Responsable | Vinculo | Avance | Hito | Cierre; `referencia_id` entero sin signo anulable; `valor_anterior` y `valor_nuevo` `string(255)` anulables; `motivo` `string(1000)` anulable; `user_id` entero sin signo; `created_at` (sin `updated_at`). Índice `(pdi_id, created_at)`
- [X] T009 [P] Crear los modelos Eloquent en `$D/Infrastructure/Persistence/Eloquent/Models/`: `PdiModel`, `PdiCompromisoModel`, `PdiAccionModel`, `PdiHitoModel` y `PdiHistorialModel` (casts de fechas y booleanos, relaciones internas). `PdiHistorialModel` **bloquea `updating` y `deleting`** (solo inserciones). Ninguno usa `SoftDeletes`

### Backend – dominio

- [X] T010 [P] Crear los objetos de valor en `$D/Domain/ValueObjects/`: `TipoDePdi`, `EstadoDePdi`, `DimensionDeCompromiso`, `ResultadoDeCompromiso`, `ResultadoDePdi`, `TipoDeAccionPdi` (con `esVinculable()` verdadero solo en Curso, Mentoring y Codesarrollo), `EstadoDeAccionPdi`, `EstadoDeHito`, `TipoDeHistorialPdi` (enums con etiqueta en español), `CodigoDePdi` (`PDI-` + número de 4 dígitos con relleno; construye desde número y valida el formato) y `PeriodoDePdi` (inicio y fin; `fin ≥ inicio`; `contiene(fecha)`)
- [X] T011 [P] Crear las excepciones en `$D/Domain/Exceptions/`: `PlanDuplicadoPorTipo` (lleva el código del plan abierto), `PlanNoEditable`, `PlanIncompleto` (lleva la lista de faltantes: `sin_compromisos`, `compromiso_sin_acciones`, `sin_responsable`, `sin_periodo`, `responsable_sin_permiso`), `AcuerdoNoVigente`, `AccionesAbiertas` (lleva la cantidad), `ResultadosIncompletos`, `VinculoInvalido`, `TransicionInvalida` y `VersionDesfasada` (lleva la versión actual)
- [X] T012 [P] Crear las entidades en `$D/Domain/Entities/`: `Pdi` (raíz; expone compromisos, hitos y estado; `visibleParaColaborador()` = Cerrado, Acordado, o Borrador con `acuerdo_responsable_at` no nulo), `CompromisoDePdi`, `AccionDePdi` y `HitoDePdi`. Invariantes: `puesto_objetivo_id` obligatorio en Carrera y Talento y nulo en Mejora; tipo inmutable; periodo válido
- [X] T013 [P] Crear `$D/Domain/Services/ReglasDeTransicionDePdi.php`: permite/rechaza editar (solo Borrador), confirmar acuerdo (Borrador completo), aceptar y devolver-colaborador (Borrador con acuerdo vigente), devolver a Borrador (solo Acordado, con motivo), cambiar responsable (Borrador y Acordado, nunca Cerrado), avance y vinculación (Borrador/Acordado según operación) y cerrar (solo Acordado); expone `borraAcuerdos(operacion)`. Y `ReglasDeCompletitudDePdi.php` (FR-026): al menos un compromiso, cada compromiso con al menos una acción, responsable y periodo definidos; devuelve la lista de faltantes
- [X] T014 [P] Crear `$D/Domain/Services/CalculadorDeAvance.php` (FR-037: Cumplidas ÷ no Canceladas × 100 redondeado a entero; `null` si no hay acciones no Canceladas) y `HitosVencidos.php` (un hito `Pendiente` con `fecha < hoy` es vencido; cuenta por plan)
- [X] T015 [P] Pruebas Pest unitarias de dominio en `backend/tests/Unit/Desarrollo/Pdi/`: `ReglasDeTransicionDePdiTest.php` (cada transición permitida y rechazada, incluido Cerrado inmutable), `ReglasDeCompletitudDePdiTest.php`, `CalculadorDeAvanceTest.php` (ejemplo 2 de 3 = 67 %; todas Canceladas ⇒ `null`), `HitosVencidosTest.php`, `CodigoDePdiTest.php` y `PdiVisibilidadTest.php` (Borrador sin acuerdo no visible; con acuerdo, sí)

### Backend – repositorio, puertos y autorización

- [X] T016 Crear `$D/Domain/Repositories/RepositorioDePdiInterface.php` con: `transaccion`, `crear(Pdi): Pdi` (genera `CodigoDePdi` siguiente por tenant dentro de la transacción con reintento acotado ante colisión), `obtener(tenantId, id): ?Pdi` (con hijos), `actualizarCabecera`, `reemplazarEstructura(Pdi)` (borrado explícito de hijos del Borrador y reinserción; único lugar con borrado físico, research R10), `existePlanAbierto(tenantId, colaboradorId, tipo): ?Pdi`, `registrarHistorial(...)`, `listarHistorial(...)`; las lecturas de listas, Gantt y «sin plan» se añaden en sus historias. Implementarlo en `$D/Infrastructure/Persistence/Eloquent/EloquentPdiRepository.php`
- [X] T017 [P] Pruebas Pest de repositorio en `backend/tests/Feature/Desarrollo/Pdi/PdiRepositorioTest.php`: código `PDI-0001` y siguiente por tenant (cada tenant empieza en 1), único parcial (segundo plan abierto del mismo colaborador y tipo falla en BD; tras Cerrar el primero se permite), reemplazo de estructura en Borrador, historial inmutable (no actualiza ni borra), aislamiento por tenant
- [X] T018 Crear `$D/Domain/ValueObjects/ClaveDePermisoPdi.php` (enum con las nueve claves; `deAdministrador()` = gestionar, acordar, seguimiento, vincular, cerrar, gantt y ver; `deColaborador()` = ver_propio y aceptar_propio) y `$D/Application/Services/AutorizadorDePdi.php` (mismo patrón que `AutorizadorDeAdecuacion`)
- [X] T019 Modificar `$D/Presentation/Middleware/EnsurePermiso.php`: inyectar `AutorizadorDePdi`, resolver también las claves de `ClaveDePermisoPdi` y aceptar varias claves separadas por `|` (basta una). Prueba Pest en `backend/tests/Feature/Desarrollo/Pdi/PdiPermisosTest.php`: administrador con las siete de gestión y sin `_propio`; colaborador solo con `_propio`; 401 sin sesión; 403 con clave ajena; `permiso:a|b` deja pasar con cualquiera; las rutas de Rutas y Adecuación siguen intactas
- [X] T020 Registrar en `$D/Infrastructure/Providers/DesarrolloServiceProvider.php` los bindings nuevos (repositorio, autorizador) y crear en `$D/Routes/api.php` el grupo `Route::prefix('api/pdi')->middleware(['api','auth:sanctum'])` con `GET /permisos` (sin permiso adicional) y la convención de orden: rutas fijas (`permisos`, `opciones`, `acciones-vinculables`, `precarga`, `mi-plan`, `gantt`, `sin-plan`) **antes** de `/{id}` (`whereNumber('id')`); más los grupos vacíos `api/pdi-acciones` y `api/pdi-hitos`. Crear `PdiController::permisos` y prueba en `PdiPermisosTest.php` de `GET /api/pdi/permisos`

### Frontend – base compartida de la feature

- [X] T021 [P] Crear `$F/api.ts` (tipos `ClavePermiso`, `Pdi`, `PdiResumen`, `Compromiso`, `Accion`, `Hito`, `HistorialItem`, `Precarga`, `QueSigue`, `GanttPlan`, etiquetas de error 409/422 y funciones de cliente por endpoint de contracts/api.md usando el cliente HTTP compartido) y `$F/permisos.ts` (`PERMISOS`, `tienePermiso`, `CLAVES_DE_GESTION`, `tieneAlgunaDeGestion`)
- [X] T022 [P] Crear `$F/text.ts` (todo el texto visible en español: títulos, etiquetas de tipos, estados, dimensiones, acciones, resultados, avisos, mensajes de error, estados vacío/error, modales) y `$F/estilosEstado.ts` (colores semánticos de badges por estado del plan, tipo, estado de acción y resultado, con los tokens del design system)
- [X] T023 [P] Crear `$F/hooks/claves.ts` (claves de caché `pdi/permisos`, `pdi/opciones`, `pdi/precarga`, `pdi/lista?filtros`, `pdi/{id}`, `pdi/{id}/historial`, `pdi/gantt?filtros`, `pdi/sin-plan?filtros`, `pdi/mi-plan`, `pdi/acciones-vinculables?colaborador`) y `$F/hooks/usePermisosPdi.ts`
- [X] T024 [P] Crear `$F/components/RequirePermiso.tsx` (patrón de `adecuacion`, con `SinAccesoPdi`) y `$F/components/Etiquetas.tsx` con `BadgeDeEstadoDePdi`, `BadgeDeTipoDePdi`, `BadgeDeEstadoDeAccion` y `BadgeDeResultado`. Prueba `$F/components/Etiquetas.dom.test.tsx`
- [X] T025 [P] Crear `$F/nav.tsx` (`pdiNav`, ítem «PDI» visible con cualquier clave `desarrollo.pdi.*`; para el colaborador se rotula «Mi plan de desarrollo») y registrarlo en `frontend/src/App.tsx` en el grupo «Talento y desarrollo» entre `adecuacionNav` y `microLearningNav`. Crear `frontend/src/test/pdiFixtures.ts` con planes de ejemplo (Borrador sin acuerdo, Borrador con acuerdo, Acordado con acción vinculada y hito vencido, Cerrado)

**Checkpoint**: Base lista — las historias pueden empezar

---

## Phase 3: User Story 1 - Crear un plan en Borrador con compromisos, acciones e hitos (Priority: P1) 🎯 MVP

**Goal**: El administrador crea un plan de cualquier tipo, con precarga desde la última DNF o adecuación, y lo guarda en Borrador con código `PDI-000N`; puede editarlo mientras esté en Borrador.

**Independent Test**: Con un colaborador con una DNF con brecha de competencia X (C → B), crear un plan de Mejora precargado, agregar una acción de tipo Curso, un hito, fijar el periodo y guardar: código `PDI-0001`, estado Borrador, niveles C y B precargados, responsable igual a quien lo creó, y registro de quién lo creó y cuándo.

### Tests for User Story 1

- [X] T026 [P] [US1] Pruebas Pest unitarias en `backend/tests/Unit/Desarrollo/Pdi/ReglasDeCompromisoTest.php`: elemento obligatorio y activo en Competencia/Conocimiento; descripción obligatoria en Experiencia/Meta; niveles A–D distintos entre sí; fechas dentro del periodo; elemento no repetido en el plan; acción con fechas dentro del periodo; vínculo solo en Curso, Mentoring y Codesarrollo; hito dentro del periodo; dimensión Conocimiento rechazada cuando no está disponible
- [X] T027 [P] [US1] Pruebas Pest de feature en `backend/tests/Feature/Desarrollo/Pdi/PdiCrearTest.php`: 201 con código `PDI-0001` y `created_by`/fecha; responsable por defecto; Carrera/Talento sin puesto objetivo ⇒ 422 y Mejora con puesto objetivo ⇒ 422; segundo plan no Cerrado del mismo tipo ⇒ 422 con el código del existente, tras cerrar el primero se permite; elementos inactivos o repetidos ⇒ 422; responsable que no es administrador activo ⇒ 422; fechas fuera del periodo o invertidas ⇒ 422; colaborador sin vínculo vigente ⇒ 422; compromiso de dimensión Conocimiento con la bandera en `false` ⇒ 422; `plan_accion_id` de una acción de tipo no vinculable (Coaching, Autodesarrollo, NuevaResponsabilidad, Otra) o que no está en la lista de vinculables ⇒ 422; se puede guardar sin compromisos ni acciones; colaborador ⇒ 403; sin sesión ⇒ 401; no existe `DELETE`
- [X] T028 [P] [US1] Pruebas Pest de feature en `backend/tests/Feature/Desarrollo/Pdi/PdiPrecargaTest.php`: precarga de Mejora desde la DNF más reciente (niveles, `origen`), de Carrera/Talento desde la última adecuación (brecha de Experiencia como compromiso con descripción y sin niveles), elementos inactivos omitidos con aviso, lista vacía sin DNF/adecuación, 422 sin puesto objetivo en Carrera/Talento, con la bandera `desarrollo.pdi.conocimientos_con_grado = false` no se generan conocimientos y `GET /opciones` devuelve `dimensiones_disponibles` sin Conocimiento
- [X] T029 [P] [US1] Pruebas Pest de feature en `backend/tests/Feature/Desarrollo/Pdi/PdiEditarTest.php`: `PUT` en Borrador reemplaza datos, compromisos, acciones e hitos y **borra los acuerdos**, incrementa `version`; conserva un `plan_accion_id` vinculable reenviado y rechaza uno no vinculable con 422; `tipo` no modificable; en Acordado o Cerrado ⇒ 422; `version` desfasada ⇒ 409; 404 si no existe
- [X] T030 [P] [US1] Prueba de Facade en `backend/tests/Feature/Desarrollo/Pdi/PdiBrechasFacadeTest.php`: `ultimaDnfConBrechas` devuelve `dnf_id`, fecha y brechas sin alterar `brechasActivas`; `ultimaAdecuacion` incluye `nivel_actual` y `nivel_objetivo` por brecha y años en Experiencia, sin romper el contrato existente de 020
- [X] T031 [P] [US1] Pruebas de componentes en `$F/components/*.dom.test.tsx` y `$F/validation.test.ts`: `PasosDelAsistente`, `SelectorDeColaboradorYTipo` (aviso y bloqueo ante plan abierto), `TarjetaDeCompromiso` (campos según dimensión), `CalendarioDeHitos`, y el esquema Zod (puesto objetivo obligatorio en Carrera/Talento, fechas dentro del periodo, elemento/descripción según dimensión, niveles distintos)

### Implementation for User Story 1 – backend

- [X] T032 [US1] Ampliar `DesarrolloFacade` y `DesarrolloService` con `ultimaDnfConBrechas(tenantId, colaboradorId)` (caso de uso nuevo sobre el repositorio de DNF existente) y extender el resultado de `ultimaAdecuacion` con `nivel_actual`, `nivel_objetivo` y años (solo campos nuevos, contrato previo intacto); actualizar el docblock de la Facade (contracts/facade.md)
- [X] T033 [P] [US1] Crear el puerto `$D/Application/Ports/BrechasParaPdiInterface.php` y su adaptador `$D/Infrastructure/Adapters/BrechasParaPdiDesdeDnfYAdecuacion.php` (usa los casos de uso propios del módulo; omite elementos inactivos con `CompetenciasInterface::estaActiva`); binding en el proveedor
- [X] T034 [P] [US1] Crear los DTO en `$D/Application/DTO/`: `PdiDTO`, `PdiResumenDTO`, `CompromisoDTO`, `AccionDTO`, `HitoDTO`, `PrecargaDTO`, `OpcionesDePdiDTO`
- [X] T035 [US1] Crear `$D/Domain/Services/ReglasDeCompromiso.php` (validaciones de T026) y los casos de uso `$D/Application/UseCases/PrecargarPdi.php` (R5; sin guardar), `ObtenerOpcionesDePdi.php` (colaboradores vigentes, puestos activos, departamentos, administradores, `dimensiones_disponibles` según la bandera de conocimientos; con `colaborador_id`: puesto objetivo sugerido por `inscripcionesActivas` y planes abiertos por tipo), `CrearPdi.php` (transacción: valida, **vínculo vigente del colaborador con `ColaboradoresVigentesInterface::vigentesPorIds` (422 si no)**, unicidad, dimensión disponible, responsable con `AprobadoresInterface::esAdministradorActivo`, **cada `plan_accion_id` con el puerto `ValidadorDeVinculoInterface` (tipo vinculable y presente en la lista de vinculables; mientras no exista T064 su adaptador provisional rechaza cualquier `plan_accion_id`)**, crea en Borrador, `created_by`, historial `Creacion`) y `ActualizarPdi.php` (mismas validaciones; solo Borrador; control de `version`; reemplaza estructura; borra `acuerdo_*` y `aceptacion_*`; historial `Edicion`)
- [X] T036 [US1] Crear `$D/Application/Services/ArmadorDeDetalleDePdi.php` (compone el detalle con nombres en lote vía puertos existentes, `avance`, `hitos_vencidos` y `acciones_permitidas` por estado y permisos; para acciones sin vínculo usa el estado guardado — la derivación de vinculadas se añade en US4) y `ObtenerPdi.php` (devuelve 404 si no existe, es de otro tenant, es de otra persona con `ver_propio` o es un Borrador sin acuerdo visto por su colaborador)
- [X] T037 [US1] Crear en `$D/Presentation/`: `Requests/CrearPdiRequest.php`, `ActualizarPdiRequest.php`, `PrecargaRequest.php`; `Resources/PdiResource.php`, `PdiResumenResource.php`; `Controllers/PdiController.php` con `opciones`, `precarga`, `store`, `show`, `update` (delgado); rutas en `Routes/api.php`: `GET /opciones` y `GET /precarga` (`permiso:desarrollo.pdi.gestionar`), `POST /` (`gestionar`), `GET /{id}` (`permiso:desarrollo.pdi.ver|desarrollo.pdi.ver_propio`), `PUT /{id}` (`gestionar`). Mapear `PlanDuplicadoPorTipo`, `PlanNoEditable` y validaciones a 422 y `VersionDesfasada` a 409 con mensajes seguros

### Implementation for User Story 1 – frontend

- [X] T038 [P] [US1] Crear `$F/validation.ts` (esquemas Zod del plan, compromiso, acción e hito según plan.md «Validación») y los hooks `$F/hooks/useOpciones.ts`, `usePrecarga.ts`, `usePdi.ts` y `useMutacionesPdi.ts` (crear y actualizar, sin reintento; invalidan las claves de T023)
- [X] T039 [P] [US1] Crear los componentes del asistente en `$F/components/`: `PasosDelAsistente.tsx`, `SelectorDeColaboradorYTipo.tsx` (con origen, brechas detectadas, vista previa de la precarga y aviso/bloqueo de plan abierto con enlace), `TarjetaDeCompromiso.tsx` (dimensión, elemento o descripción, niveles, indicador, fechas, **Quitar**), `FilaDeAccion.tsx` (tipo, descripción, persona a cargo, fechas; el botón de vincular se añade en US4), `CalendarioDeHitos.tsx`
- [X] T040 [US1] Crear `$F/pages/AsistenteDePdiPage.tsx` (tres pasos con `?paso=` y `?colaborador=&tipo=&puesto_objetivo=`; paso 3 con responsable por defecto = usuario actual, periodo e hitos; botones «Crear PDI (Borrador)»; estados de validación por campo, conflicto 409 y error con «Reintentar») y la edición `/pdi/:id/editar` reutilizando la misma página con datos cargados; botón «Volver a precargar brechas»
- [X] T041 [US1] Crear `$F/pages/DetalleDePdiPage.tsx` básico (encabezado, barra de avance, pestañas Resumen y Compromisos y acciones en solo lectura; las demás se completan en sus historias) y registrar en `frontend/src/App.tsx` las rutas `/pdi/nuevo` (`RequirePermiso gestionar`), `/pdi/:id` y `/pdi/:id/editar`. Pruebas de página `$F/pages/AsistenteDePdiPage.dom.test.tsx`

**Checkpoint**: US1 funcional y comprobable — se crean, precargan y editan planes en Borrador

---

## Phase 4: User Story 2 - Acordar el plan: confirmación del responsable y aceptación del colaborador (Priority: P1)

**Goal**: El responsable confirma el acuerdo; solo entonces el colaborador ve el plan y lo acepta (pasa a Acordado) o lo devuelve con comentario. El administrador puede devolver un Acordado a Borrador y cambiar el responsable.

**Independent Test**: Con un Borrador con un compromiso y una acción: confirmar el acuerdo (sigue Borrador, ahora visible al colaborador), aceptar como colaborador (pasa a Acordado) y verificar fechas y usuario; repetir devolviendo con comentario (sigue Borrador, sin acuerdos, comentario guardado y plan no visible).

### Tests for User Story 2

- [X] T042 [P] [US2] Pruebas Pest de feature en `backend/tests/Feature/Desarrollo/Pdi/PdiAcuerdoTest.php`: confirmar acuerdo con plan incompleto ⇒ 422 con `errors.completitud`; acuerdo válido registra usuario y fecha y **no** cambia el estado; repetir el acuerdo ⇒ 422; `aceptar` sin acuerdo ⇒ 404 (el Borrador no es visible para el colaborador); `aceptar` con acuerdo ⇒ Acordado; `aceptar` de otro colaborador ⇒ 403; Borrador sin acuerdo ⇒ 404 para su colaborador; `version` desfasada ⇒ 409 (edición previa borró el acuerdo)
- [X] T043 [P] [US2] Pruebas Pest de feature en `backend/tests/Feature/Desarrollo/Pdi/PdiDevolverTest.php`: `devolver-colaborador` exige comentario (1–1000) y acuerdo vigente, guarda el comentario, borra acuerdos y deja de ser visible; `devolver` (admin) exige motivo y estado Acordado, borra acuerdos, historial con motivo; ambos ⇒ 422 en estados incorrectos
- [X] T044 [P] [US2] Pruebas Pest de feature en `backend/tests/Feature/Desarrollo/Pdi/PdiResponsableTest.php`: cambiar responsable en Acordado conserva acuerdos y estado, deja historial `Responsable` con anterior y nuevo; en Borrador borra acuerdos; destino sin rol de administrador ⇒ 422; en Cerrado ⇒ 422; responsable sin permiso se marca `sin_permiso` en la lectura
- [X] T045 [P] [US2] Pruebas de componentes en `$F/components/PanelDeAcuerdo.dom.test.tsx` y `ModalDeMotivo.dom.test.tsx`: botones según estado y permisos (Confirmar acuerdo, Devolver a Borrador, Cambiar responsable), motivo obligatorio, foco atrapado y restaurado

### Implementation for User Story 2 – backend

- [X] T046 [US2] Crear los casos de uso en `$D/Application/UseCases/`: `ConfirmarAcuerdoDePdi.php` (valida con `ReglasDeCompletitudDePdi`, registra `acuerdo_responsable_*`, historial `Acuerdo`), `AceptarPdi.php` (colaborador dueño vía `CuentaColaboradorInterface`; exige acuerdo vigente; fija `aceptacion_colaborador_at` y pasa a Acordado; historial `Aceptacion` y `Estado`), `DevolverPdiDelColaborador.php`, `DevolverPdiABorrador.php` y `CambiarResponsableDePdi.php` (R1; valida administrador activo). Todos controlan `version` y registran historial con usuario, valor anterior y nuevo
- [X] T047 [US2] Crear `Requests/AcuerdoRequest.php`, `ComentarioRequest.php`, `MotivoRequest.php`, `ResponsableRequest.php` y las acciones en `PdiController.php` y rutas: `POST /{id}/acuerdo` (`permiso:desarrollo.pdi.acordar`), `POST /{id}/aceptar` y `POST /{id}/devolver-colaborador` (`desarrollo.pdi.aceptar_propio`), `POST /{id}/devolver` y `PATCH /{id}/responsable` (`desarrollo.pdi.gestionar`). Mapear `PlanIncompleto`, `AcuerdoNoVigente`, `TransicionInvalida` a 422 y `VersionDesfasada` a 409

### Implementation for User Story 2 – frontend

- [X] T048 [P] [US2] Crear `$F/components/PanelDeAcuerdo.tsx` (acuerdo del responsable y aceptación del colaborador con fecha y usuario, comentario del colaborador, texto guía) y `$F/components/ModalDeMotivo.tsx` (motivo obligatorio, usado en «Devolver a Borrador»), y ampliar `useMutacionesPdi.ts` con acuerdo, devolver y cambiar responsable
- [X] T049 [US2] Completar `DetalleDePdiPage.tsx`: pestaña Resumen con botones **Confirmar acuerdo** (Borrador completo y sin acuerdo), **Editar** (Borrador), **Devolver a Borrador** (Acordado), **Cambiar responsable** (Borrador y Acordado; selector de administradores), mensajes de completitud faltante, indicador de «responsable sin permiso» y conflicto 409 con aviso de recarga. Prueba en `$F/pages/DetalleDePdiPage.dom.test.tsx`

**Checkpoint**: US2 funcional — el flujo de doble confirmación y las devoluciones funcionan por API y desde el detalle del administrador

---

## Phase 5: User Story 3 - Dar seguimiento a un plan Acordado (Priority: P1)

**Goal**: En un plan Acordado el administrador registra avances de acciones sin vínculo y de hitos; el avance y los hitos vencidos se calculan.

**Independent Test**: Plan Acordado con 4 acciones (una Cancelada, dos Cumplidas, una Pendiente) y un hito Pendiente con fecha anterior a hoy: avance 67 %, hito vencido, y no se pueden editar fechas ni agregar compromisos.

### Tests for User Story 3

- [X] T050 [P] [US3] Pruebas Pest de feature en `backend/tests/Feature/Desarrollo/Pdi/PdiSeguimientoTest.php`: `PATCH /api/pdi-acciones/{id}/avance` en Acordado cambia el estado e historial `Avance` con anterior y nuevo; acción vinculada ⇒ 422; plan no Acordado ⇒ 422; `PATCH /api/pdi-hitos/{id}` marca Realizado con observaciones e historial `Hito`; `PUT /api/pdi/{id}` en Acordado ⇒ 422 (estructura no editable); avance = Cumplidas ÷ no Canceladas; hito Pendiente con fecha pasada ⇒ `vencido`; colaborador ⇒ 403
- [X] T051 [P] [US3] Pruebas de componentes en `$F/components/FilaDeAccion.dom.test.tsx` y `$F/components/HitosDelPlan.dom.test.tsx`: selector de estado visible solo en acciones sin vínculo de un plan Acordado, estado en solo lectura en vinculadas y en Borrador/Cerrado, marca «Vencido»

### Implementation for User Story 3

- [X] T052 [US3] Crear los casos de uso `$D/Application/UseCases/RegistrarAvanceDeAccion.php` y `RegistrarAvanceDeHito.php` (solo Acordado; control de `version`; `registrado_by/at`; historial), `Requests/AvanceRequest.php`, `HitoRequest.php`, `Controllers/PdiAccionesController.php` (`avance`) y `PdiHitosController.php` (`update`) y rutas `PATCH /api/pdi-acciones/{id}/avance` y `PATCH /api/pdi-hitos/{id}` (`permiso:desarrollo.pdi.seguimiento`); asegurar que `ArmadorDeDetalleDePdi` expone `avance`, `vencido` y `hitos_vencidos`
- [X] T053 [P] [US3] Completar en el frontend: `$F/components/FilaDeAccion.tsx` (selector Pendiente/En curso/Cumplida/Cancelada en acciones sin vínculo de un Acordado), `$F/components/HitosDelPlan.tsx` (selector Pendiente/Realizado, observaciones, marca **Vencido**), la pestaña Hitos y la barra de avance de `DetalleDePdiPage.tsx`, y mutaciones en `useMutacionesPdi.ts` (avance de acción y de hito)

**Checkpoint**: US3 funcional — el seguimiento registra avances auditados y calcula avance y vencidos

---

## Phase 6: User Story 6 - Consultar «Mi plan de desarrollo» como colaborador (Priority: P1)

**Goal**: El colaborador ve solo sus planes con acuerdo del responsable, acepta o devuelve con comentario y ve «Qué sigue».

**Independent Test**: Colaborador con un plan Borrador (con acuerdo) y otro Acordado: tarjetas, solo el Borrador ofrece aceptar o devolver, «Qué sigue» ordena por fecha, una acción vinculada muestra el enlace, y no puede ver el plan de otra persona ni un Borrador sin acuerdo.

### Tests for User Story 6

- [X] T054 [P] [US6] Pruebas Pest de feature en `backend/tests/Feature/Desarrollo/Pdi/PdiMiPlanTest.php`: devuelve solo planes propios con acuerdo del responsable (Borrador con acuerdo, Acordado, Cerrado) y excluye el Borrador sin acuerdo; `que_sigue` solo de planes Acordado, acciones Pendiente/EnCurso e hitos Pendiente por fecha ascendente con vencidos primero; respuesta vacía sin planes; `GET /{id}` de otra persona ⇒ 404; administrador ⇒ 403 en `mi-plan`
- [X] T055 [P] [US6] Pruebas de componentes en `$F/components/TarjetaDePlan.dom.test.tsx`, `QueSigue.dom.test.tsx` y `$F/pages/MiPlanDeDesarrolloPage.dom.test.tsx`: tarjetas con tipo/estado/avance/periodo, «Aceptar plan» (con casilla de lectura) y «Devolver con comentario» solo en Borrador con acuerdo, comentario obligatorio, estado vacío, loading, error con «Reintentar»

### Implementation for User Story 6

- [X] T056 [US6] Crear `$D/Application/UseCases/ObtenerMiPlan.php` (planes visibles del colaborador del usuario vía `CuentaColaboradorInterface`; compone `que_sigue`), `Resources/MiPlanResource.php`, `Controllers/MiPlanPdiController.php` y la ruta `GET /api/pdi/mi-plan` (`permiso:desarrollo.pdi.ver_propio`) declarada antes de `/{id}`
- [X] T057 [P] [US6] Crear en `$F/components/`: `TarjetaDePlan.tsx`, `QueSigue.tsx` (vencidos destacados; el enlace «Ver actividad» de las acciones vinculadas se activa en US4, T065/T067) y la vista de detalle del colaborador en solo lectura (compromisos, acciones, hitos, panel de acuerdo con casilla «Revisé los compromisos, acciones y fechas de este plan y acepto cumplirlos»); hooks `useMiPlan.ts` y mutaciones `aceptar` y `devolverColaborador`
- [X] T058 [US6] Crear `$F/pages/MiPlanDeDesarrolloPage.tsx` (selector entre planes con `?plan=`, estado vacío «Aún no tienes un plan de desarrollo») y `$F/pages/EntradaPdiPage.tsx` (resuelve `/pdi`: con alguna clave de gestión muestra la lista del administrador —se completa en US7—; con solo `ver_propio`, «Mi plan de desarrollo»); registrar `/pdi` en `frontend/src/App.tsx`
- [X] T059 [US6] E2E del flujo principal en `frontend/e2e/pdi.spec.ts`: el administrador crea un plan de Mejora con una acción y un hito, intenta que el colaborador lo vea y comprueba que aún no aparece, confirma el acuerdo, el colaborador lo ve en «Mi plan de desarrollo», lo acepta y el administrador verifica el estado Acordado. Preparar los datos sin depender de `IngresosSeeder` (ver memoria «Entorno local de pruebas»)

**Checkpoint**: US1 + US2 + US3 + US6 cubren el flujo completo de punta a punta (MVP)

---

## Phase 7: User Story 4 - Vincular acciones al Plan de Formación (Priority: P2)

**Goal**: En acciones Curso, Mentoring y Codesarrollo, el administrador vincula una acción formativa; el estado pasa a derivarse (solo lectura) y puede desvincularse.

**Independent Test**: Con una acción formativa Finalizada con asistencia Presente del colaborador, vincularla a una acción Curso: estado Cumplida de solo lectura y en el historial; desvincular devuelve el estado manual.

### Tests for User Story 4

- [X] T060 [P] [US4] Pruebas Pest unitarias en `backend/tests/Unit/Desarrollo/Pdi/DerivadorDeEstadoDeAccionTest.php` (R3): acción del plan cancelada o actividad `Cancelada` ⇒ Cancelada; actividad `Finalizada` con asistencia `Presente` ⇒ Cumplida; `Justificado` y `Ausente` no cumplen; en otro caso EnCurso si hoy está dentro de las fechas de la acción del PDI y Pendiente si no
- [X] T061 [P] [US4] Pruebas Pest de feature en `backend/tests/Feature/Desarrollo/Pdi/PdiVinculoTest.php`: el selector solo lista acciones de planes Aprobado o Ejecutando donde el colaborador es participante y sin canceladas; vincular Coaching/Autodesarrollo/NuevaResponsabilidad/Otra ⇒ 422; acción del plan que no está en la lista ⇒ 422; vincular en Borrador borra acuerdos y en Acordado los conserva; en Cerrado ⇒ 422; desvincular devuelve el estado manual; historial `Vinculo`; el Plan de Formación y las Actividades no se modifican; participante que sale ⇒ `requiere_revision`; sin N+1 en el detalle; `GET /api/pdi/mi-plan` expone `actividad_id` en las acciones vinculadas y en `que_sigue`
- [X] T062 [P] [US4] Prueba de Facade en `backend/tests/Feature/Desarrollo/Pdi/PdiAccionesVinculablesFacadeTest.php`: `getAccionesVinculablesDeColaborador` para planes Aprobado/Ejecutando y exclusión de canceladas, sin alterar los métodos existentes
- [X] T063 [P] [US4] Pruebas de componentes en `$F/components/SelectorDeAccionVinculable.dom.test.tsx`: lista, búsqueda, selección, «Desvincular», «Requiere revisión», botón visible solo en Curso/Mentoring/Codesarrollo

### Implementation for User Story 4

- [X] T064 [US4] Crear los puertos `$D/Application/Ports/AccionesVinculablesInterface.php` y `EstadoFormativoDeAccionInterface.php` y sus adaptadores `$D/Infrastructure/Adapters/AccionesVinculablesDesdePlanes.php` y `EstadoFormativoDesdeActividades.php`; añadir `accionesVinculablesDeColaborador` a `PlanRepositoryInterface` y su implementación Eloquent existente (consulta propia de planes, solo lectura) y exponerla como `DesarrolloFacade::getAccionesVinculablesDeColaborador` (contracts/facade.md); bindings en el proveedor
- [X] T065 [US4] Crear `$D/Domain/Services/DerivadorDeEstadoDeAccion.php` (T060) y `$D/Application/Services/ResolvedorDeEstadoDeAcciones.php` (una sola llamada a `getAsistenciaDeColaborador` y una a `porIds` por plan; marca `estado_derivado` y `requiere_revision`) e integrarlo en `ArmadorDeDetalleDePdi` y en `ObtenerMiPlan` (plan no Cerrado ⇒ derivado; `estado_congelado` ⇒ guardado; se incluye `actividad_id` en las acciones vinculadas y en `que_sigue`)
- [X] T066 [US4] Crear `VincularAccionDePdi.php` y `ListarAccionesVinculables.php` en `$D/Application/UseCases/` (revalida contra la lista de la Facade, tipo vinculable, estado del plan, historial `Vinculo` con anterior y nuevo), `Requests/VinculoRequest.php`, `PdiAccionesController::vincular`, la implementación real de `ValidadorDeVinculoInterface` (`$D/Infrastructure/Adapters/ValidadorDeVinculoDesdePlanes.php`, que sustituye al adaptador provisional de T035 y reutiliza `AccionesVinculablesInterface`) y rutas `PUT /api/pdi-acciones/{id}/vinculo` y `GET /api/pdi/acciones-vinculables` (`permiso:desarrollo.pdi.vincular`)
- [X] T067 [P] [US4] Crear `$F/components/SelectorDeAccionVinculable.tsx` (modal) y el botón **Vincular a Plan de Formación** / **Desvincular** en `FilaDeAccion.tsx` (en el asistente y en el detalle), hooks `useAccionesVinculables.ts` y mutación de vínculo; activar en `QueSigue.tsx` y en el detalle del colaborador el enlace «Ver actividad» a `/actividades` con `actividad_id` (solo URL, sin importar otros módulos). La sugerencia de micro-contenidos (FR-042) queda fuera de alcance

**Checkpoint**: US4 funcional — el cumplimiento de las acciones formativas se refleja sin captura doble

---

## Phase 8: User Story 5 - Cerrar un plan con su resultado (Priority: P2)

**Goal**: Con todas las acciones Cumplidas o Canceladas, el administrador cierra el plan con un resultado por compromiso; el resultado del plan se calcula, el estado de las acciones vinculadas se congela y el plan queda en solo lectura.

**Independent Test**: Plan Acordado con dos compromisos y acciones cerradas: Cerrada + Parcial ⇒ Parcial; ambos Cerrada ⇒ BrechaCerrada; ambos NoCerrada ⇒ NoCerrada; el plan queda en solo lectura y el estado de las acciones vinculadas no cambia si cambia el Plan de Formación.

### Tests for User Story 5

- [X] T068 [P] [US5] Pruebas Pest unitarias en `backend/tests/Unit/Desarrollo/Pdi/CalculadorDeResultadoDePdiTest.php`: todos Cerrada ⇒ BrechaCerrada; ninguno Cerrada ⇒ NoCerrada; mixto (incluye Parcial) ⇒ Parcial
- [X] T069 [P] [US5] Pruebas Pest de feature en `backend/tests/Feature/Desarrollo/Pdi/PdiCerrarTest.php`: con acciones Pendiente/EnCurso ⇒ 422 con `acciones_abiertas`; falta resultado en algún compromiso ⇒ 422; cierre válido guarda resultados, resultado del plan, `cerrado_at/by` e historial `Cierre`; **congela** `estado` y `estado_congelado` de las acciones vinculadas y un cambio posterior del Plan de Formación o de la asistencia no los modifica; Carrera/Talento devuelven `ofrecer_adecuacion` y Mejora no; plan Cerrado: `PUT`, avance, hito, vínculo, devolver y responsable ⇒ 422; no existe `DELETE`; permite crear un nuevo plan del mismo tipo; cierre con todas las acciones Canceladas ⇒ permitido y avance «no calculable»; un plan abierto de un colaborador que dejó de estar vigente se puede consultar y cerrar
- [X] T070 [P] [US5] Pruebas de componentes en `$F/components/ModalDeCierre.dom.test.tsx`: resultado obligatorio por compromiso, conclusión opcional, lista de acciones abiertas que bloquea, resultado calculado mostrado, enlace «Generar nueva adecuación» solo en Carrera/Talento, plan Cerrado en solo lectura

### Implementation for User Story 5

- [X] T071 [US5] Crear `$D/Domain/Services/CalculadorDeResultadoDePdi.php` y `$D/Application/UseCases/CerrarPdi.php` (transacción: valida acciones y resultados, resuelve y persiste el estado final de cada acción vinculada con `estado_congelado = true`, guarda resultados, calcula el del plan, historial `Cierre` y `Estado`), `Requests/CerrarPdiRequest.php`, `PdiController::cerrar` y la ruta `POST /api/pdi/{id}/cerrar` (`permiso:desarrollo.pdi.cerrar`) con `ofrecer_adecuacion: {colaborador_id, puesto_objetivo_id}` en Carrera y Talento
- [X] T072 [P] [US5] Crear `$F/components/ModalDeCierre.tsx`, mutación `cerrar` y, en `DetalleDePdiPage.tsx`, el botón **Cerrar**, el panel «Resultado del cierre» y el enlace «Generar nueva adecuación» (navega a `/adecuacion?colaborador=…&puesto=…`); plan Cerrado en solo lectura en todas las pestañas
- [X] T073 [US5] Enlace con Adecuación (R13): leer `?colaborador=` y `?puesto=` en `frontend/src/modules/desarrollo/adecuacion/pages/EntradaAdecuacionPage.tsx` y en el asistente de generación para preseleccionar colaborador y puesto objetivo (cambio aditivo; si ya los lee, solo se añade la prueba) y su prueba en `frontend/src/modules/desarrollo/adecuacion/pages/EntradaAdecuacionPage.dom.test.tsx`

**Checkpoint**: US5 funcional — los planes se cierran con resultado calculado y quedan inmutables

---

## Phase 9: User Story 7 - Consultar y filtrar los planes, y ver quién tiene brechas sin plan (Priority: P2)

**Goal**: Lista del administrador con filtros, KPIs, indicador de hitos vencidos, bloque «Personas con brecha y sin PDI», historial del plan y Facade para otros módulos.

**Independent Test**: Con planes de varios tipos, estados, departamentos y responsables, cada filtro reduce la lista como se espera; un colaborador con brecha activa y sin plan aparece en el indicador y desaparece al crearle el plan; el historial lista todos los eventos.

### Tests for User Story 7

- [X] T074 [P] [US7] Pruebas Pest de feature en `backend/tests/Feature/Desarrollo/Pdi/PdiListarTest.php`: orden más reciente primero con paginación, filtros `tipo`, `estado`, `departamento_id`, `responsable_id`, `fecha_desde/hasta` (sobre `fecha_fin`) solos y combinados, `kpis`, indicador de hitos vencidos, sin N+1 (nombres y departamentos en lote), colaborador ⇒ 403, aislamiento por tenant
- [X] T075 [P] [US7] Pruebas Pest de feature en `backend/tests/Feature/Desarrollo/Pdi/PdiSinPlanTest.php`: colaborador con brecha activa de DNF y sin plan Mejora abierto aparece con `origen: dnf`; con adecuación vigente con brechas y sin plan Carrera/Talento para ese puesto aparece con `origen: adecuacion`; deja de aparecer al crearle el plan; filtro por departamento y paginación (R6)
- [X] T076 [P] [US7] Pruebas Pest de feature en `backend/tests/Feature/Desarrollo/Pdi/PdiHistorialTest.php`: lista todos los eventos (creación, estado, acuerdo, aceptación, devolución con motivo/comentario, responsable, vínculo, avance, hito, cierre) con usuario, fecha, valor anterior y nuevo; paginación; el colaborador solo ve el de sus planes visibles; es de solo lectura
- [X] T077 [P] [US7] Pruebas Pest de Facade en `backend/tests/Feature/Desarrollo/Pdi/PdiFacadeTest.php`: `planesActivos`, `pdiPorFiltros` y `existePlanAbierto` según contracts/facade.md, sin revelar compromisos; planes Borrador sin acuerdo excluidos de `planesActivos`
- [X] T078 [P] [US7] Pruebas de componentes en `$F/components/TablaDePdi.dom.test.tsx`, `KpisDePdi.dom.test.tsx`, `BloqueSinPlan.dom.test.tsx` y `$F/pages/ListaDePdiPage.dom.test.tsx`: filtros en la URL, «Limpiar filtros», indicador de hitos vencidos, vacío, loading, error con «Reintentar», «Crear PDI» con la precarga en la URL, tabla que pasa a tarjetas en móvil

### Implementation for User Story 7

- [X] T079 [US7] Añadir a `RepositorioDePdiInterface` y `EloquentPdiRepository` los métodos de lectura `listar(filtro)`, `kpis` y `planesDeColaboradores`; crear `ListarPdi.php`, `ListarColaboradoresSinPlan.php` (R6; tope de 200 candidatos, lotes por página) y `ListarHistorialDePdi.php` en `$D/Application/UseCases/`, `Requests/ListarPdiRequest.php`, `Resources/HistorialResource.php` y rutas `GET /api/pdi` (`permiso:desarrollo.pdi.ver`), `GET /api/pdi/sin-plan` (`desarrollo.pdi.gestionar`) y `GET /api/pdi/{id}/historial` (`desarrollo.pdi.ver|desarrollo.pdi.ver_propio`)
- [X] T080 [US7] Crear `$D/Application/UseCases/ConsultarPdiParaOtrosModulos.php` y exponer en `DesarrolloFacade`/`DesarrolloService` `planesActivos`, `pdiPorFiltros` y `existePlanAbierto` (contracts/facade.md) con docblocks
- [X] T081 [P] [US7] Crear en `$F/components/`: `TablaDePdi.tsx`, `KpisDePdi.tsx`, `BloqueSinPlan.tsx`, `BarraDeFiltrosPdi.tsx`, y la pestaña Historial (tabla de solo lectura con paginación); hooks `useListaPdi.ts`, `useSinPlan.ts`, `useHistorialPdi.ts` (filtros y página en la URL)
- [X] T082 [US7] Crear `$F/pages/ListaDePdiPage.tsx` (pestañas **Lista de PDI** y **Seguimiento (Gantt)** con `?pestana=`; botón **Nuevo PDI**), conectarla en `EntradaPdiPage.tsx` y completar la pestaña Historial de `DetalleDePdiPage.tsx`
- [X] T083 [US7] Integración con Adecuación (FR-044): en `frontend/src/modules/desarrollo/adecuacion/components/AccionesDeAdecuacion.tsx` habilitar «Crear plan de desarrollo» e «Incluir en Programa de Talentos» (deshabilitados sin brechas o con un plan abierto del tipo, con su motivo) y enlazar a `/pdi/nuevo?colaborador=&tipo=&puesto_objetivo=` (tipos Carrera y Talento); en el backend de 020, exponer `plan_abierto: {id, codigo}|null` (por colaborador y tipo sugerido: Carrera o Talento) en la respuesta de `$D/Application/UseCases/ObtenerOpcionesDeAdecuacion.php` y su recurso HTTP, usando `DesarrolloFacade::existePlanAbierto` de T080 sin cambiar los campos existentes. Actualizar las pruebas existentes de `AccionesDeAdecuacion.dom.test.tsx` y añadir una prueba Pest en `backend/tests/Feature/Desarrollo/Pdi/PdiAdecuacionIntegracionTest.php`

**Checkpoint**: US7 funcional — la gestión del conjunto de planes está completa

---

## Phase 10: User Story 8 - Ver la carta Gantt consolidada (Priority: P3)

**Goal**: Vista consolidada de las acciones de los planes Acordado con filtros por tipo, responsable y departamento.

**Independent Test**: Con acciones de planes Acordado de dos tipos y departamentos y planes Borrador y Cerrados: solo aparecen acciones de planes Acordado, ubicadas por sus fechas; cada filtro reduce la vista; sin planes Acordado, estado vacío.

### Tests for User Story 8

- [X] T084 [P] [US8] Pruebas Pest de feature en `backend/tests/Feature/Desarrollo/Pdi/PdiGanttTest.php`: solo planes Acordado, estado de acción resuelto (derivado en vinculadas), filtros `tipo`, `responsable_id`, `departamento_id`, paginación por plan, `hoy`, sin N+1, `permiso:desarrollo.pdi.gantt` (403 sin él)
- [X] T085 [P] [US8] Pruebas de componentes en `$F/components/LineaDeTiempoGantt.dom.test.tsx` y `$F/pages/SeguimientoGanttPage.dom.test.tsx`: leyenda Cumplida/En curso/Pendiente/Hito, marcador de hoy, alternativa tabular accesible, filtros en la URL, vacío «No hay PDI Acordados con estos filtros»

### Implementation for User Story 8

- [X] T086 [US8] Añadir `ganttDePlanesAcordados` al repositorio, crear `ObtenerGanttDePdi.php`, `Resources/GanttResource.php` y la ruta `GET /api/pdi/gantt` (`permiso:desarrollo.pdi.gantt`) declarada antes de `/{id}`
- [X] T087 [P] [US8] Crear `$F/components/LineaDeTiempoGantt.tsx` (franjas por acción con estado, hitos y marcador de hoy; scroll contenido dentro del contenedor y alternativa tabular), `BarraDeFiltrosGantt.tsx`, `useGantt.ts` y `$F/pages/SeguimientoGanttPage.tsx`; conectar la pestaña en `ListaDePdiPage.tsx`; clic en una fila abre `/pdi/:id`

**Checkpoint**: US8 funcional — todas las historias completas

---

## Phase 11: Polish & Cross-Cutting Concerns

- [X] T088 [P] Crear `backend/database/seeders/PdisSeeder.php` según data-model.md (cinco planes: Mejora Borrador sin acuerdo; Carrera Borrador con acuerdo del responsable; Talento Acordado con acción vinculada a una acción del seeder del Plan de Formación y un hito vencido; Mejora Cerrado «BrechaCerrada»; Carrera Cerrado «Parcial»; compromisos de las cuatro dimensiones y acciones de los siete tipos; nombres ficticios) y registrarlo en `DatabaseSeeder` después de los seeders de Personal, Estructura, Plan de Formación, Actividades, DNF y Adecuación. Prueba de humo en `backend/tests/Feature/Desarrollo/Pdi/PdisSeederTest.php`
- [X] T089 [P] Revisión de privacidad y errores: pruebas Pest en `backend/tests/Feature/Desarrollo/Pdi/PdiPrivacidadTest.php` — el colaborador nunca recibe planes ajenos, Borradores sin acuerdo ni datos de gestión; los errores no revelan trazas ni datos de otros módulos; 503/falla de fuente degrada con mensaje seguro
- [X] T090 [P] Accesibilidad y responsive: foco visible y orden de tabulación en pasos, pestañas, modales y tablas; `aria-current` en pasos y navegación; sin scroll horizontal entre 320 y 1440 px; temas claro y oscuro; objetivos táctiles ≥ 44 px; revisar contraste de badges
- [X] T091 [P] Completar E2E en `frontend/e2e/pdi.spec.ts`: devolver con comentario (el plan desaparece de «Mi plan»), cierre con resultado y `Generar nueva adecuación`, vinculación de una acción formativa (si el entorno de datos lo permite), y la lista con filtros
- [X] T092 Ejecutar la puerta de calidad de backend: `vendor/bin/pest`, `vendor/bin/pint --test` y el análisis estático disponible en `backend/`; corregir hallazgos sin tocar código ajeno
- [X] T093 Ejecutar la puerta de calidad de frontend: `npm run lint`, `npx tsc -b`, `npx vitest run --testTimeout=30000 --maxWorkers=3`, `npm run build` y `npx playwright test pdi`; confirmar que no hay `any`, ni texto visible fuera de `text.ts`, ni Axios directo en páginas, ni dependencias nuevas
- [X] T094 Recorrer los 20 escenarios de `quickstart.md` y verificar que la implementación coincide con `design/PDI*.dc.html` salvo las diferencias registradas en plan.md y `design/DESIGN.md`; anotar en `specs/021-plan-desarrollo-individual/quickstart.md` los resultados y cualquier desviación

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias.
- **Foundational (Phase 2)**: depende de Setup; **bloquea** todas las historias.
- **US1 (Phase 3)**: tras Foundational. Es la base de datos de las demás (crear y leer planes).
- **US2 (Phase 4)**: depende de US1 (necesita planes y el detalle).
- **US3 (Phase 5)**: depende de US2 (necesita planes Acordados) y de US1.
- **US6 (Phase 6)**: depende de US2 (acuerdo/aceptación) y comparte el detalle de US1; puede avanzar en paralelo con US3. Funciona sin vínculos: el enlace «Ver actividad» y `actividad_id` se activan con US4 (T065/T067).
- **US4 (Phase 7)**: depende de US1 (acciones) y se integra con el detalle de US3; sin dependencia de US6.
- **US5 (Phase 8)**: depende de US3 y, para el congelamiento, de US4 (`ResolvedorDeEstadoDeAcciones`).
- **US7 (Phase 9)**: depende de US1 y US2; usa `ObtenerMiPlan`/Facade de US6 solo para `planesActivos`.
- **US8 (Phase 10)**: depende de US3 y US4 (estado resuelto de acciones).
- **Polish (Phase 11)**: depende de las historias que se quieran entregar.

### Within Each Story

- Pruebas antes de la implementación (deben fallar primero).
- Dominio → casos de uso → controlador/rutas → frontend (hooks → componentes → página).
- El historial se registra en el mismo caso de uso que cambia el estado.

### Parallel Opportunities

- Setup: T002 y T003 en paralelo.
- Foundational: migraciones T005–T008 en paralelo tras T004; T009–T015 en paralelo; frontend T021–T025 en paralelo con el backend.
- Dentro de cada historia: todas las tareas de prueba [P]; DTO/puertos [P]; componentes de frontend [P].
- US3 y US6 pueden desarrollarse en paralelo una vez terminada US2; US4 puede empezar tras US1.

### Parallel Example: User Story 1

```text
Tests:  T026 ReglasDeCompromisoTest | T027 PdiCrearTest | T028 PdiPrecargaTest | T029 PdiEditarTest | T030 PdiBrechasFacadeTest | T031 componentes del asistente
Código: T033 puerto+adaptador de brechas | T034 DTO | T038 validation+hooks | T039 componentes del asistente
Luego:  T032 → T035 → T036 → T037 (backend en orden) · T040 → T041 (frontend en orden)
```

---

## Implementation Strategy

### MVP First (US1 + US2 + US3 + US6)

1. Completar Setup y Foundational.
2. Entregar US1 (crear en Borrador), US2 (doble confirmación), US6 (colaborador) y US3 (seguimiento): el ciclo de acuerdo y seguimiento completo sin vínculos ni cierre.
3. **Detenerse y validar** con el E2E de T059 y los escenarios 1–10 de `quickstart.md`.

### Incremental Delivery

1. + US4: el cumplimiento se alimenta del Plan de Formación (escenario 11).
2. + US5: cierre con resultado y congelamiento (escenarios 12–13).
3. + US7: lista, indicador de brechas sin plan, historial e integración con Adecuación (escenarios 15, 18, 19).
4. + US8: carta Gantt (escenario 14).
5. Polish: seeder, accesibilidad, E2E completo y puertas de calidad.

### Notas

- Las dos desviaciones constitucionales (rutas `/api/pdi` y autorización por clave de permiso) están **aprobadas** (plan.md, 2026-10-22); no repetir la consulta.
- No añadir dependencias nuevas ni modificar tablas de otros módulos; los únicos cambios fuera de `pdi` son aditivos: `DesarrolloFacade`/`DesarrolloService`, `PlanRepositoryInterface` (lectura), `EnsurePermiso`, `config/desarrollo.php`, `App.tsx` y los botones/lectura de URL de Adecuación (FR-044).
- Cada tarea modifica o crea archivos concretos; no mezclar historias en un mismo commit si se puede evitar.
