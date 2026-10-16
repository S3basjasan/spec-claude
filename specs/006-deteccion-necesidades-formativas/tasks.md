---

description: "Tareas de la feature 006-deteccion-necesidades-formativas"
---

# Tasks: Detección de necesidades formativas (DNF)

**Input**: Documentos de diseño en `/specs/006-deteccion-necesidades-formativas/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/dnf-api.md, contracts/dnf-puertos-y-facades.md, quickstart.md. Requiere 000-login, 001/004 (competencias y puestos en `EstructuraFacade`), 005-catalogo-formativo (módulo `Desarrollo`, `DesarrolloFacade`, `BrechasActivasInterface`, `RequireRole`, `/formacion`) y 005-fichas-colaboradores (`PersonalFacade`, `colaborador_cuentas`) ya implementadas. **Gestión de Personal GP-02 (puesto, vínculo, nivel actual) y Plan de Formación no existen**: entran por puertos con adaptadores provisionales (plan.md, research.md R-01).

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario (US1–US6 de spec.md) para implementarlas y probarlas de forma independiente. La feature **amplía** el módulo `Desarrollo` existente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1–US6)
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Desarrollo/…` (Domain, Application, Infrastructure, Presentation, Routes). Abreviatura **`$D`** = `backend/app/Modules/Desarrollo`
- Frontend: `frontend/src/modules/desarrollo/dnf/…` (reutiliza `frontend/src/shared/ui/…`). Abreviatura **`$F`** = `frontend/src/modules/desarrollo/dnf`
- Pruebas backend: `backend/tests/{Unit,Feature}/Desarrollo/` (prefijo `Dnf`); dobles en `backend/tests/Support/Desarrollo/`
- Pruebas de componentes: `*.dom.test.tsx` junto al código (con `// @vitest-environment jsdom`); E2E en `frontend/e2e/dnf.spec.ts`
- Regla de módulos: `Desarrollo` solo usa `EstructuraFacade` y `PersonalFacade` (nunca sus modelos ni tablas); `colaborador_id`, `puesto_id`, `competencia_id`, `departamento_id`, `tenant_id` y los ids de usuario son enteros **sin clave foránea** entre módulos. En frontend, `dnf` **no importa nada** de otros módulos salvo `modules/auth/login` (rol) y `modules/desarrollo/catalogo-formativo/components/RequireRole` si su contrato público lo permite; si no, copiar el patrón
- Valores en base de datos (sin tildes; la interfaz muestra con tilde): `alcance` ∈ `Individual` | `Departamento`; `prioridad` ∈ `Critica` | `Alta` | `Media` | `Baja`; `estado` ∈ `ConBrecha` | `Cumple` | `Supera` | `SinEvaluacion`; `tipo_competencia` ∈ `Cardinal` | `Especifica`; niveles `A`–`D`; roles: capacidad `admin` (Administrador) y rol `user` (Colaborador), excluyentes
- Reutilizar sin modificar: `Button`, `Badge`, `Alert`, `ConfirmDialog`, `Modal`, `Combobox`, `SelectField`, `SearchField`, `Pagination`, `Breadcrumb`, `EmptyState`, `ErrorState`, `Skeleton` y `AppShell`. No crear componentes equivalentes
- Guía visual: `design/DNFAdmin.dc.html` y `design/DNFColaborador.dc.html` son **referencia, no código para copiar**; en estilos manda `design/DESIGN.md` (§2 colores semánticos, §4 «Componentes de la DNF», §5 puntos 13–15, §9 diferencias). **La prioridad la calcula el backend por grados y tipo (FR-010), no por porcentaje como hacen los tableros**
- Insumo `plan-dnf.md` y plan.md: donde difieren, **prevalecen plan.md y research.md** (`tenant_id`, `plan_generado_por/en`, ruta `/formacion?competencia=:id`, `GET /api/dnf/opciones`)
- Lecciones previas: en E2E localizar campos con `getByRole('textbox', { name, exact: true })`; escribir archivos con acentos con la herramienta Write (los heredocs de Bash fallan); ver la memoria «Entorno local de pruebas» (OneDrive/Windows) al correr Pest, Vitest y Playwright

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Línea base verde antes de tocar nada

- [X] T001 Verificar la línea base: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run` y `npx playwright test` en `frontend/` deben pasar antes de empezar (sin cambios de archivos)
- [X] T002 Verificar que `design/DESIGN.md` ya contiene los tokens `--warn/--warnsoft/--info/--infosoft/--neutral/--neutralsoft` (§2), el bloque «Componentes de la DNF» (§4), los patrones 13–15 (§5) y las filas de `DNF*.dc.html` en §9 (Constitución III); si falta algo, añadirlo **antes** de cualquier tarea de frontend (sin cambios si está completo)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Tablas, dominio con el cálculo de la brecha, repositorio append-only, puertos y adaptadores, siembra y piezas de frontend que usan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend

- [X] T003 Crear `backend/database/migrations/2026_10_16_000001_create_dnf_table.php`: `id`; `tenant_id` entero sin signo con índice (sin FK); `codigo` `string(12)` (`DNF-NNNN`); `alcance` `string(12)` (`Individual` | `Departamento`); `departamento_id` entero sin signo **anulable** (referencia lógica a Estructura, sin FK; obligatorio si `Departamento`, nulo si `Individual`); `competencias_omitidas` entero sin signo por defecto 0; `plan_formacion_id` entero sin signo **anulable y único** (referencia lógica al plan, sin FK); `plan_generado_por` entero sin signo anulable; `plan_generado_en` timestamp anulable; `created_by` entero sin signo (sin FK); timestamps (`created_at` = fecha de generación). Índice **único `(tenant_id, codigo)`** e índice `(tenant_id, created_at)`. Sin soft delete
- [X] T004 Crear `backend/database/migrations/2026_10_16_000002_create_dnf_colaboradores_table.php`: `id`; `dnf_id` FK a `dnf.id` **sin borrado en cascada** (`restrictOnDelete`); `colaborador_id` entero sin signo con índice (referencia lógica a Gestión de Personal, sin FK); `puesto_id` entero sin signo (copia del momento, sin FK); timestamps. Restricción **única en `(dnf_id, colaborador_id)`**
- [X] T005 Crear `backend/database/migrations/2026_10_16_000003_create_dnf_detalles_table.php`: `id`; `dnf_colaborador_id` FK a `dnf_colaboradores.id` **sin borrado en cascada** (`restrictOnDelete`); `competencia_id` entero sin signo (sin FK); `tipo_competencia` `string(12)` (`Cardinal` | `Especifica`, copia del momento); `nivel_requerido` `char(1)` (`A`–`D`); `nivel_actual` `char(1)` **anulable** (nulo ⇒ `SinEvaluacion`); `diferencia` `smallint` **anulable** (actual − requerido en grados); `brecha_porcentaje` `decimal(5,2)` **anulable** (≥ 0; 0 si no hay brecha); `prioridad` `string(7)` **anulable** (`Critica` | `Alta` | `Media` | `Baja`; solo si `ConBrecha`); `estado` `string(14)` (`ConBrecha` | `Cumple` | `Supera` | `SinEvaluacion`). Restricción **única en `(dnf_colaborador_id, competencia_id)`**. Sin timestamps de actualización necesarios (inmutable)
- [X] T006 [P] Crear los enums y value objects de dominio en `$D/Domain/ValueObjects/`: `PrioridadBrecha` (`Critica`, `Alta`, `Media`, `Baja`; método `orden(): int` 4..1 y `etiqueta()` con tilde), `EstadoBrecha` (`ConBrecha`, `Cumple`, `Supera`, `SinEvaluacion`), `AlcanceDnf` (`Individual`, `Departamento`), `TipoCompetenciaDnf` (`Cardinal`, `Especifica`). Reutilizar `NivelGrado` existente (añadir `valorNumerico()`: D=1, C=2, B=3, A=4 **solo aquí**, único lugar con la escala)
- [X] T007 [P] Crear las excepciones finales (`RuntimeException`, mensajes en español sin ids internos) en `$D/Domain/Exceptions/`: `DnfNoEncontrada` ("La DNF no existe."), `SinColaboradoresEvaluables` ("No hay colaboradores evaluables."), `ColaboradorNoEvaluable` (recibe el motivo `SinPuesto|SinPerfil|SinVinculoVigente`), `DnfSinBrechas` ("La DNF no tiene brechas."), `PlanYaGenerado` ("La DNF ya generó un plan."), `PlanNoDisponible` ("Plan de Formación no disponible.")
- [X] T008 Crear el servicio de dominio puro `$D/Domain/Services/CalculadorDeBrecha.php` (sin Laravel) según research.md R-02: `calcular(NivelGrado $requerido, ?NivelGrado $actual, TipoCompetenciaDnf $tipo): Brecha` con `diferencia = actual − requerido`; `brecha% = (requerido − actual) / requerido × 100` solo si `actual < requerido`, redondeada a 2 decimales (si no, 0); sin `actual` ⇒ `SinEvaluacion` con `diferencia`, `brecha%` y `prioridad` nulos; prioridad solo con brecha: −3 Crítica, −2 Alta, −1 Media si Cardinal / Baja si Especifica; estado `<0` ConBrecha, `0` Cumple, `>0` Supera. Cumplir los invariantes de data-model.md (1–4)
- [X] T009 [P] Crear `backend/tests/Unit/Desarrollo/CalculadorDeBrechaTest.php` (SC-003): las **16 combinaciones** requerido × actual, cada una con Cardinal y Especifica (valores exactos de diferencia, porcentaje, prioridad y estado; p. ej. C vs A → −2, 50.00, Alta; D vs A → −3, 75.00, Crítica; D vs C Cardinal → −1, 50.00, Media; D vs C Especifica → Baja; B vs A → −1, 25.00; requerido D nunca da brecha); actual nulo → `SinEvaluacion` con campos nulos; cumple y supera → 0 %, sin prioridad
- [X] T010 [P] Crear las entidades de solo lectura en `$D/Domain/Entities/`: `Dnf` (id, tenantId, código, alcance, departamentoId, competenciasOmitidas, planFormacionId, planGeneradoPor, planGeneradoEn, creadoPor, creadoEn, `ResultadoColaborador[]`; métodos derivados `cantidadBrechas()`, `prioridadMasAlta()`, `sinEvaluacion()`, `tienePlan()`), `ResultadoColaborador` (colaboradorId, puestoId, `Brecha[]`, derivados por colaborador) y `Brecha` (competenciaId, tipo, nivelRequerido, nivelActual?, diferencia?, brechaPorcentaje?, prioridad?, estado). Invariantes de data-model.md validados en el constructor
- [X] T011 Crear `$D/Domain/Repositories/DnfRepositoryInterface.php`: **solo** `guardar(Dnf $dnf): Dnf` (inserta las tres tablas en una transacción, asigna código), `siguienteCodigo(int $tenantId): string` (no público si el repositorio lo hace dentro de `guardar`), `obtener(int $tenantId, int $id, ?int $colaboradorId = null): ?Dnf`, `listar(int $tenantId, FiltroDnf $filtro): ListadoDnf`, `masRecienteDeColaborador(int $tenantId, int $colaboradorId): ?array` (DNF y su `ResultadoColaborador`), `asignarPlan(int $tenantId, int $dnfId, int $planId, int $actorId, DateTimeImmutable $momento): bool` (`UPDATE … WHERE plan_formacion_id IS NULL`, devuelve si afectó una fila). **Sin** actualizar ni eliminar nada más
- [X] T012 [P] Crear los modelos Eloquent en `$D/Infrastructure/Persistence/Eloquent/Models/`: `DnfModel`, `DnfColaboradorModel`, `DnfDetalleModel` (relaciones; casts a enums). Los tres rechazan `updating` y `deleting` lanzando `LogicException` («Una DNF es inmutable»), con la **única** excepción de `DnfModel` al completar `plan_formacion_id`, `plan_generado_por`, `plan_generado_en` desde nulo (research.md R-03)
- [X] T013 Crear `$D/Infrastructure/Persistence/Eloquent/Repositories/EloquentDnfRepository.php` implementando T011: `guardar` en `DB::transaction` con código `DNF-` + número de 4 dígitos = `max+1` por tenant y reintento acotado (3) ante colisión del índice único; inserciones de `dnf_detalles` por lotes; `listar` con filtros `fecha_desde/hasta`, `colaborador_id` (une `dnf_colaboradores`, incluye masivas), `departamento_id`, `prioridad` (coincide con la **prioridad más alta** de la DNF) y paginación, ordenado por `created_at` desc, calculando cantidad de brechas y prioridad más alta con agregados (sin N+1); `masRecienteDeColaborador` ordena por `created_at` e `id` descendentes; todo filtra por `tenant_id`
- [X] T014 [P] Crear `backend/tests/Feature/Desarrollo/DnfRepositorioTest.php`: código `DNF-0001`, `DNF-0002` por tenant (el tenant 2 también empieza en `DNF-0001`); guardar y leer una DNF con resultados y detalles; `UPDATE`/`DELETE` sobre las tres tablas lanzan error; `asignarPlan` solo funciona una vez (segunda llamada devuelve `false`); filtros del listado (fechas, colaborador en masiva, departamento, prioridad más alta) y aislamiento por tenant. Añadir el caso de colisión: si el código calculado ya existe (inserción concurrente simulada), `guardar` reintenta y termina con un código único sin error
- [X] T015 [P] Crear los puertos en `$D/Application/Ports/`: `PerfilesDePuestoInterface` (`perfilDe(int $puestoId): ?array`), `ColaboradoresEvaluablesInterface` (`evaluablesPorIds(int $tenantId, array $ids): array`, `evaluablesDeUnidad(int $tenantId, int $unidadId): array`, devolviendo también `excluidos[]`), `NivelesActualesInterface` (`nivelesDe(int $tenantId, array $colaboradorIds): array`), `CuentaColaboradorInterface` (`cuentaDe(int $usuarioId): ?array` → `{tenant_id, colaborador_id}`), `PlanDeFormacionInterface` (`crearBorrador(int $tenantId, int $actorId, int $dnfId, array $brechas): int`) y `NombresDeReferenciaInterface` (`colaboradores`, `puestos`, `competencias`, `unidades` por lote de ids → arreglos id ⇒ nombre/datos), con las formas de contracts/dnf-puertos-y-facades.md
- [X] T016 Añadir a `backend/app/Modules/GestionPersonal/Application/Services/PersonalService.php` y a `backend/app/Modules/GestionPersonal/Presentation/Facades/PersonalFacade.php` el método de solo lectura `getCuentaDeUsuario(int $usuarioId): ?array` que devuelve `{tenant_id, colaborador_id}` resuelto desde `colaborador_cuentas` por `user_id` (nulo si el usuario no tiene ficha asociada); documentarlo en el docblock de la Facade. Cambio **aditivo**, sin alterar los métodos existentes ni depender de GP-02. Prueba en `backend/tests/Feature/GestionPersonal/PersonalFacadeCuentaDeUsuarioTest.php` (con ficha, sin ficha, tenant correcto en el resultado)
- [X] T017 [P] Crear los adaptadores en `$D/Infrastructure/Adapters/`: `PerfilesDePuestoDesdeEstructura` (llama a `EstructuraFacade::getPuesto`; devuelve `competencias[]` con `competencia_id`, `codigo`, `nombre`, `tipo`, `activa` (= `estado` activo) y `nivel_requerido` (= `grado_requerido`)), `CuentaColaboradorDesdePersonal` (llama a `PersonalFacade::getCuentaDeUsuario` de T016), `NombresDeReferenciaDesdeFacades` (usa `PersonalFacade::getColaboradoresPorIds`, `EstructuraFacade::getPuestosPorIds`, `getCompetenciasPorIds` —incluye inactivas— y `getUnidadesPorIds`; nombre ausente ⇒ valor «No disponible»), y los **provisionales** `ColaboradoresEvaluablesSinDatos` (devuelve vacío; en `evaluablesDeUnidad` documentar que el definitivo obtendrá las subunidades con `EstructuraFacade::getArbolDeUnidades()`), `NivelesActualesSinDatos` (`[]`) y `PlanDeFormacionNoDisponible` (lanza `PlanNoDisponible`), cada uno con el comentario de sustitución por la Facade de GP-02 / Plan de Formación
- [X] T018 Registrar en `$D/Infrastructure/Providers/DesarrolloServiceProvider.php` los bindings de `DnfRepositoryInterface` y de los puertos de T015 con sus adaptadores de T017 (los provisionales marcados con comentario `// PROVISIONAL`). Crear además, en `$D/Infrastructure/Adapters/Pruebas/`, los adaptadores **solo para el entorno e2e** (`ColaboradoresEvaluablesDePrueba`, `NivelesActualesDePrueba`, `PlanDeFormacionDePrueba`, con datos fijos de `PersonalSeeder`/`PuestosSeeder`/`CompetenciasSeeder`) y enlazarlos en el provider **únicamente** si `app()->environment('e2e')`; en cualquier otro entorno nunca se enlazan (prueba en `backend/tests/Feature/Desarrollo/DnfAdaptadoresPorEntornoTest.php`). **No** cambiar aún el binding de `BrechasActivasInterface` (lo hace T043)
- [X] T019 [P] Crear los dobles de prueba en `backend/tests/Support/Desarrollo/`: `ColaboradoresEvaluablesFalsos` (acepta colaboradores con puesto/unidad/excluidos y registra consultas), `NivelesActualesFalsos` (mapa colaborador → competencia → nivel), `PlanDeFormacionFalso` (devuelve un id o simula fallo) y `PerfilesDePuestoFalsos`; y el helper `backend/tests/Support/Desarrollo/DnfHelpers.php` con `dnfDeEjemplo(array $override)` que guarda una DNF con sus detalles por el repositorio, `bindDnfFalsos(...)`, y reutiliza `actuarComoAdminDeTenant`/`actuarComoUsuarioDeTenant` de `backend/tests/GestionPersonalHelpers.php`
- [X] T020 Crear `backend/database/seeders/DnfSeeder.php` (idempotente; usa `updateOrCreate`/comprobación por `(tenant_id, codigo)`) que inserta **directamente en las tres tablas** (research.md R-01; no depende de GP-02) dos DNF del tenant 1: (1) **Individual** de la ficha «Ana Núñez Paredes» con competencias sembradas por `CompetenciasSeeder` que cubran las cuatro prioridades, al menos un `Cumple`, un `Supera` y un `SinEvaluacion`; (2) **Departamento** (una unidad sembrada por `UnidadesSeeder`) con ≥ 3 colaboradores de `PersonalSeeder` (Ana, Luis, María), mezcla de brechas, un colaborador sin brechas y un `SinEvaluacion`; `puesto_id` de un puesto de `PuestosSeeder`. Registrarlo en `backend/database/seeders/DatabaseSeeder.php` **después de `PersonalSeeder`**. Prueba en `backend/tests/Feature/Desarrollo/DnfSeederTest.php` (idempotencia, las cuatro prioridades presentes, `created_by` válido)

### Frontend

- [X] T021 [P] Crear `$F/api.ts` con los tipos (`Alcance`, `Prioridad`, `EstadoBrecha`, `Nivel`, `Brecha`, `ResultadoColaborador`, `Dnf`, `FilaHistorial`, `MiDiagnostico`, `AvisosDnf`, `OpcionesDnf`, `PlanGenerado`) y las funciones HTTP sobre `shared/api/httpClient` (`generarDnf`, `listarDnf`, `obtenerDnf`, `miDiagnostico`, `generarPlan`, `obtenerOpciones`) según contracts/dnf-api.md. Sin `any`
- [X] T022 [P] Crear `$F/text.ts` con **todos** los textos visibles en español (títulos, etiquetas, avisos, estados vacío/error, motivos del botón de plan, «No disponible», nombres de prioridades y estados con tilde, textos de los tableros que la spec mantiene) y `$F/nav.tsx` con el ítem lateral «DNF» (ruta `/dnf`, icono de `shared/ui/icons`; añadir el icono si no existe)
- [X] T023 [P] Crear `$F/validation.ts` (Zod): esquema del paso 1 con alcance obligatorio, `colaborador_id` obligatorio si `Individual` y prohibido si `Departamento` y viceversa, más el esquema de filtros del historial (`fecha_desde ≤ fecha_hasta`, `per_page` 1–100). Prueba en `$F/validation.test.ts`
- [X] T024 [P] Crear `$F/components/BadgePrioridad.tsx` y `$F/components/BadgeEstado.tsx` (envoltorios de `Badge` con los tokens semánticos de `design/DESIGN.md` §4: Crítica `--danger`/`--dangersoft`, Alta `--warn`/`--warnsoft`, Media `--info`/`--infosoft`, Baja `--neutral`/`--neutralsoft`; estados Con brecha, Cumple, Supera, Sin evaluación; **siempre con texto**, sin prioridad muestra «—») con pruebas `*.dom.test.tsx`
- [X] T025 Crear `$F/pages/DnfPage.tsx` que despacha por rol con `RequireRole` (Administrador → `DnfAdminPage`, Colaborador → `MiDiagnosticoPage`; mismo patrón que `CatalogoPage`), crear también **esqueletos mínimos** (`export function … () { return null }`, sin texto) de `DnfAdminPage`, `MiDiagnosticoPage`, `DnfHistorialPage` y `DnfDetallePage` en `$F/pages/` para que el build no se rompa, y registrar en `frontend/src/App.tsx` las rutas `dnf` (todos), `dnf/historial` y `dnf/:id` (envueltas en el guard de administrador existente) y el ítem `dnfNav` en `navItems`. Cada historia sustituye su esqueleto por la página real

**Checkpoint**: Dominio, persistencia, puertos, siembra, tipos y navegación listos; las historias pueden comenzar

---

## Phase 3: User Story 1 - Generar una DNF individual (Priority: P1) 🎯 MVP

**Goal**: El administrador elige un colaborador y obtiene su diagnóstico guardado (código, tabla con brecha en grados y %, prioridad y estado)

**Independent Test**: Con un colaborador evaluable (doble de puertos), `POST /api/dnf` crea `DNF-0001` y la pantalla muestra C vs A → −2, 50 %, Alta, Con brecha; las competencias inactivas se omiten con aviso; un perfil modificado después no altera la DNF

### Tests for User Story 1

- [X] T026 [P] [US1] Crear `backend/tests/Feature/Desarrollo/DnfGenerarIndividualTest.php`: 201 con la forma de contracts/dnf-api.md; valores calculados (C vs A → −2, 50.00, Alta, ConBrecha); Cumple/Supera con 0 % y sin prioridad; competencia sin nivel actual → `SinEvaluacion` con campos nulos; competencias inactivas omitidas y contadas en `competencias_omitidas`; cambiar después el perfil o el nivel (dobles) no altera la DNF guardada (SC-004); colaborador sin puesto/perfil/vínculo vigente → 422 con motivo; sin colaboradores evaluables → 422 «No hay colaboradores evaluables.»; 422 por validación (`alcance` ausente o inválido, `colaborador_id` ausente, `departamento_id` con `Individual`, colaborador o departamento que no existe en el tenant del administrador → 422); 401 sin token y 403 para el colaborador; PUT/PATCH/DELETE sobre `/api/dnf` y `/api/dnf/{id}` → 405; dos generaciones consecutivas del mismo colaborador se permiten sin advertencia (FR-014a); autoría `created_by` y fecha; la generación individual se completa en menos de 10 s con datos de prueba (SC-001)
- [X] T027 [P] [US1] Crear `backend/tests/Feature/Desarrollo/DnfOpcionesTest.php`: `GET /api/dnf/opciones` devuelve colaboradores y departamentos evaluables del tenant (doble), excluye los sin vínculo vigente (FR-002a), 401/403
- [X] T028 [P] [US1] Crear `$F/components/TablaDiagnostico.dom.test.tsx`, `$F/components/PasoSeleccion.dom.test.tsx` y `$F/hooks/useGenerarDnf.test.tsx`: tabla ordenada de mayor a menor prioridad por defecto, columnas competencia/requerido/actual/brecha (grados)/brecha (%)/prioridad/estado, «Sin evaluación» con «—»; paso 1 con errores por campo, envío bloqueado mientras la mutación está pendiente y mapeo de 422 al campo; la mutación **no se reintenta**

### Implementation for User Story 1

- [X] T029 [P] [US1] Crear los DTO en `$D/Application/DTO/`: `DnfDTO`, `ResultadoColaboradorDTO`, `BrechaDTO`, `AvisosDTO`, `OpcionesDnfDTO`, con las formas de contracts/dnf-api.md (incluye cantidad de brechas, prioridad más alta, sin evaluación)
- [X] T030 [US1] Crear el caso de uso `$D/Application/UseCases/GenerarDnf.php` **solo con el alcance Individual** (la masiva llega en US3): valida que el colaborador sea evaluable (`ColaboradoresEvaluablesInterface`), obtiene el perfil (`PerfilesDePuestoInterface`), omite competencias inactivas y las cuenta, obtiene niveles (`NivelesActualesInterface`), calcula con `CalculadorDeBrecha`, guarda con `DnfRepositoryInterface::guardar` en una transacción y devuelve `DnfDTO` con avisos; lanza `ColaboradorNoEvaluable`/`SinColaboradoresEvaluables`; si un puerto lanza, registrar sin datos personales y tratar como «sin datos»
- [X] T031 [US1] Crear el caso de uso `$D/Application/UseCases/ObtenerOpcionesDnf.php`: colaboradores evaluables y departamentos con `colaboradores_evaluables` (por ahora solo con los datos de los puertos; la cuenta de subunidades llega con US3)
- [X] T032 [P] [US1] Crear `$D/Presentation/Requests/GenerarDnfRequest.php` (reglas de T026/T023: `alcance` ∈ enumeración; `colaborador_id` requerido si Individual y prohibido si Departamento; `departamento_id` a la inversa; enteros positivos) y `$D/Presentation/Resources/DnfResource.php`, `ResultadoColaboradorResource.php`, `BrechaResource.php` (campos `competencia_activa`, nombres resueltos por `NombresDeReferenciaInterface`, mensajes seguros)
- [X] T033 [US1] Crear `$D/Presentation/Controllers/DnfController.php` (delgado, sin consultas de BD) con `store` y `opciones`, y registrar en `$D/Routes/api.php` el grupo `Route::prefix('api/dnf')->middleware(['api','auth:sanctum','admin'])` con `POST /` (`dnf.store`) y `GET /opciones` (`dnf.opciones`) **antes** de cualquier `/{id}`; sin rutas PUT/PATCH/DELETE. Mapear `SinColaboradoresEvaluables`/`ColaboradorNoEvaluable` a 422 con mensaje en español y cabecera `Location`
- [X] T034 [P] [US1] Crear `$F/hooks/useGenerarDnf.ts` y `$F/hooks/useOpcionesDnf.ts` (TanStack Query; mutación sin reintentos; invalida `['dnf','lista']` al crear)
- [X] T035 [P] [US1] Crear `shared/ui/Stepper.tsx` (pasos numerados con `aria-current="step"`, paso no alcanzable `disabled`; ver `design/DESIGN.md` §4) con prueba `shared/ui/Stepper.dom.test.tsx`, y los componentes `$F/components/PasoSeleccion.tsx` (radiogroup de alcance «Un colaborador/Por departamento», `Combobox` de colaborador con búsqueda y vista previa puesto/departamento/n.º de competencias; el selector de departamento queda para US3), `$F/components/TablaDiagnostico.tsx` (`DataTable` del diseño con minibarra de brecha), `$F/components/KpiDiagnostico.tsx` (`StatCard`) y `$F/components/AvisosDnf.tsx` (`Alert` neutro de competencias omitidas y sin evaluación)
- [X] T036 [US1] Crear `$F/pages/DnfAdminPage.tsx` (asistente de 2 pasos según `design/DESIGN.md` §5 punto 13 y `DNFAdmin.dc.html`): paso 1 → «Detectar necesidades» → paso 2 con título, fecha, KPI, avisos y tabla; estados cargando (Skeleton), error con Reintentar y sin colaboradores evaluables (EmptyState); enlace «Cambiar selección». Sin botones de editar/eliminar
- [X] T037 [US1] Crear `frontend/e2e/dnf.spec.ts` (patrón de `catalogo-formativo.spec.ts`) y configurar el entorno e2e (según `frontend/e2e/README.md` y `playwright.config.ts`) para arrancar el backend con `APP_ENV=e2e`, de modo que se usen los adaptadores de T018. Flujo principal del administrador: abrir `/dnf`, elegir un colaborador, «Detectar necesidades», ver la tabla con valores calculados (p. ej. C vs A → −2, 50 %, Alta, Con brecha) y la ausencia de botones de editar/eliminar; incluir un caso con un colaborador no evaluable que muestre el mensaje de error «No hay colaboradores evaluables.»

**Checkpoint**: US1 funcional y comprobada con dobles de los puertos; el cálculo y la inmutabilidad están cubiertos

---

## Phase 4: User Story 2 - Consultar mi diagnóstico como colaborador (Priority: P1)

**Goal**: El colaborador ve sus brechas de su última DNF, las tres prioritarias y llega a los cursos sugeridos

**Independent Test**: Con la DNF sembrada de Ana, ingresar como `usuario@hrms.test` y ver tarjetas con «Nivel actual: C · Requerido: A · Diferencia: −2», tres prioritarias ordenadas y «Ver cursos sugeridos» hacia `/formacion?competencia=:id`

### Tests for User Story 2

- [X] T038 [P] [US2] Crear `backend/tests/Feature/Desarrollo/DnfMiDiagnosticoTest.php`: forma de contracts/dnf-api.md; orden por prioridad y, a igual prioridad, mayor diferencia (empate final por nombre); `prioritarias` máximo 3; `cumplen` y `resumen`; `tiene_oferta` por competencia (usa `DesarrolloFacade::competenciaTieneOferta`); la DNF más reciente (individual o masiva) manda sobre las anteriores (FR-014/FR-014a); sin DNF → `{"data": null}` con 200; usuario sin ficha asociada → `{"data": null}`; DNF sin brechas → listas vacías; el id del colaborador sale del token (no acepta parámetros) y un colaborador no ve el diagnóstico de otro; 401 sin token; el administrador también recibe `{"data": null}` salvo que tenga ficha
- [X] T039 [P] [US2] Crear `backend/tests/Feature/Desarrollo/DnfBrechasActivasFacadeTest.php`: `DesarrolloFacade::brechasActivas(tenantId, colaboradorId)` devuelve las `ConBrecha` de la DNF más reciente con `competencia_id`, `nivel_requerido`, `nivel_actual`, `diferencia`, `prioridad`; `[]` si no hay DNF; solo arreglos (sin modelos); y el caso de `ListarRecomendados` de 005 con el adaptador real devuelve ahora recomendados para el usuario sembrado
- [X] T040 [P] [US2] Crear `$F/components/TarjetaBrecha.dom.test.tsx`, `$F/components/BrechasPrioritarias.dom.test.tsx` y `$F/pages/MiDiagnosticoPage.dom.test.tsx`: texto «Nivel actual: C · Requerido: A · Diferencia: −2 (50 % de brecha)», `aria-label` resumen, barras A–D con letra visible, botón «Ver cursos sugeridos» que navega a `/formacion?competencia=:id` y queda deshabilitado con «Aún no hay cursos asociados en el catálogo.» sin oferta; estados sin DNF, sin brechas, cargando y error con Reintentar; sin datos del prototipo (sin «Ver como administrador»)

### Implementation for User Story 2

- [X] T041 [US2] Crear `$D/Application/UseCases/ObtenerMiDiagnostico.php`: obtiene `{tenant_id, colaborador_id}` con `CuentaColaboradorInterface::cuentaDe($usuarioId)` (id de usuario tomado del token), consulta `DnfRepositoryInterface::masRecienteDeColaborador($tenantId, $colaboradorId)`, ordena (prioridad, mayor |diferencia|, nombre de competencia), toma las 3 prioritarias, calcula `resumen` y `tiene_oferta` por `DesarrolloFacade`; devuelve `MiDiagnosticoDTO` (crear en `$D/Application/DTO/MiDiagnosticoDTO.php`); `null` si no hay ficha o DNF
- [X] T042 [US2] Añadir `miDiagnostico` a `DnfController` y la ruta `GET /api/dnf/mi-diagnostico` (`dnf.mi-diagnostico`) en `$D/Routes/api.php` con solo `auth:sanctum` (sin `admin`), declarada **antes** de `/{id}`; el usuario sale de `$request->user()`; `MiDiagnosticoResource`
- [X] T043 [US2] Crear el caso de uso `$D/Application/UseCases/ObtenerBrechasActivas.php` (DNF más reciente → detalles `ConBrecha`), añadir `brechasActivas(int $tenantId, int $colaboradorId)` (tenant **explícito**, decisión cerrada en plan.md) a `$D/Application/Services/DesarrolloService.php` y a `$D/Presentation/Facades/DesarrolloFacade.php` (docblock `@method`), y crear el adaptador definitivo `$D/Infrastructure/Adapters/BrechasActivasDesdeDnf.php` (obtiene `{tenant_id, colaborador_id}` con `CuentaColaboradorInterface::cuentaDe($usuarioId)` y devuelve `[ {competencia_id, nivel_requerido} ]`; `[]` si no hay ficha). **Cambiar el binding** de `BrechasActivasInterface` a `BrechasActivasDesdeDnf` en `DesarrolloServiceProvider` y eliminar `BrechasActivasSinDatos` si ya nadie lo usa (las pruebas de 005 siguen usando `BrechasActivasFalsas`)
- [X] T044 [P] [US2] Crear `$F/hooks/useMiDiagnostico.ts`, `$F/components/TarjetaBrecha.tsx` (Card con h3, `BadgePrioridad`, dos barras horizontales A–D «Requerido» y «Actual», línea de diferencia y botón «Ver cursos sugeridos»), `$F/components/BrechasPrioritarias.tsx` (hasta 3 tarjetas numeradas, «Otras brechas detectadas» y bloque plegable «Competencias que ya cumples (N)») y `$F/components/LeyendaBarras.tsx` («Cómo leer las barras»; A es el nivel más alto · Diferencia negativa = te falta)
- [X] T045 [US2] Crear `$F/pages/MiDiagnosticoPage.tsx` (según `design/DESIGN.md` §5 punto 15 y `DNFColaborador.dc.html`): encabezado con el puesto, KPI, brechas prioritarias, otras brechas y cumplidas; estados sin DNF/sin brechas (EmptyState informativo), cargando, error con Reintentar
- [X] T046 [US2] Ampliar `frontend/e2e/dnf.spec.ts` con el flujo del colaborador sobre la DNF generada o sembrada (entorno e2e) (`usuario@hrms.test`): ve las tarjetas, las tres prioritarias en orden y «Ver cursos sugeridos» lleva a `/formacion?competencia=…`; colaborador sin ficha (`sinficha@hrms.test`) ve el mensaje informativo; el colaborador no ve el menú ni las rutas de historial

**Checkpoint**: US1 y US2 (P1) completas — MVP entregable

---

## Phase 5: User Story 3 - Generar una DNF masiva por departamento (Priority: P2)

**Goal**: El administrador evalúa un departamento (con sus subunidades) tras confirmar la cantidad, con resumen por colaborador

**Independent Test**: Con un departamento de colaboradores completos e incompletos (doble de puertos), confirmar «Se evaluarán N colaboradores», ver el resumen y abrir la tabla de uno; los excluidos salen en el aviso

### Tests for User Story 3

- [X] T047 [P] [US3] Crear `backend/tests/Feature/Desarrollo/DnfGenerarMasivaTest.php`: un resultado por colaborador evaluable; incluye colaboradores de **subunidades** (FR-002); excluye con motivo `SinPuesto|SinPerfil|SinVinculoVigente` (FR-002a) y los devuelve en `avisos.excluidos`; departamento inexistente o de otro tenant → 422/404; ningún evaluable → 422; una sola transacción (si un cálculo falla no queda nada guardado); la perfil de cada puesto se consulta una vez por puesto distinto; 100 colaboradores se generan en menos de 60 s (SC-002); `competencias_omitidas` acumula las de la DNF
- [X] T048 [P] [US3] Crear `$F/components/ResumenPorColaborador.dom.test.tsx` y `$F/components/ConfirmarMasivaDialog.dom.test.tsx`: el diálogo dice «Se evaluarán N colaboradores», botón primario (no de peligro), Escape/Cancelar cierran sin mutar; el resumen muestra colaborador, puesto, cantidad de brechas y prioridad más alta, y cada fila abre la tabla del colaborador con «Volver al resumen»
- [X] T059 [P] [US3] Crear `backend/tests/Feature/Desarrollo/DnfObtenerTest.php` (se escribe en US3 junto con T050): `GET /api/dnf/{id}` con autor (`generado_por`) y fecha; `?colaborador_id=` filtra en una masiva (404 si no pertenece); individual incluye `brechas`; masiva sin filtro solo el resumen; DNF de otro tenant o inexistente → 404; id no numérico no colisiona con `opciones`/`mi-diagnostico`; 401/403

### Implementation for User Story 3

- [X] T049 [US3] Ampliar `$D/Application/UseCases/GenerarDnf.php` con el alcance `Departamento`: obtiene evaluables y excluidos con `ColaboradoresEvaluablesInterface::evaluablesDeUnidad` (que incluye subunidades), cachea el perfil por puesto durante la petición, pide los niveles por lote (`NivelesActualesInterface`), calcula todo y guarda en una transacción; `SinColaboradoresEvaluables` si no queda ninguno; el resultado de cada colaborador sin `brechas` detalladas en la respuesta masiva (solo resumen)
- [X] T050 [US3] Ampliar `$D/Application/UseCases/ObtenerOpcionesDnf.php` y el adaptador para devolver `colaboradores_evaluables` por departamento (incluye subunidades), usado por la confirmación; actualizar `$D/Presentation/Resources/DnfResource.php` para la forma masiva (`colaboradores` con resumen) y `avisos.excluidos`. Crear aquí también, porque la respuesta `201` de una masiva no incluye las brechas de cada colaborador: `$D/Application/UseCases/ObtenerDnf.php` (resuelve nombres por lote con `NombresDeReferenciaInterface`, autor con `NombresDeUsuarioInterface`, acepta `colaborador_id` opcional; lanza `DnfNoEncontrada`), la acción `show` de `DnfController` y la ruta `GET /api/dnf/{id}` (`dnf.show`, `whereNumber('id')`, **después** de las rutas fijas `opciones` y `mi-diagnostico`; `DnfNoEncontrada` → 404). Su prueba es T059 (que se escribe y ejecuta en esta historia)
- [X] T051 [P] [US3] Ampliar `$F/components/PasoSeleccion.tsx` con el `SelectField` de departamento (mostrando `colaboradores_evaluables`) y crear `$F/components/ConfirmarMasivaDialog.tsx` (sobre `ConfirmDialog` no destructivo) y `$F/components/ResumenPorColaborador.tsx` (`DataTable` con botón por fila y enlace «Volver al resumen»)
- [X] T052 [US3] Ampliar `$F/pages/DnfAdminPage.tsx`: alcance Departamento → confirmación → resumen por colaborador → tabla del colaborador elegido (con `GET /api/dnf/{id}?colaborador_id=`, creado en T050); avisos de excluidos con nombre y motivo; si se excluyó a todos, EmptyState. Añadir al E2E (`frontend/e2e/dnf.spec.ts`) la generación masiva con los adaptadores de prueba: modal «Se evaluarán N colaboradores», resumen por colaborador y apertura de la tabla de uno

**Checkpoint**: US1–US3 funcionales de forma independiente

---

## Phase 6: User Story 4 - Revisar, filtrar y visualizar el diagnóstico (Priority: P2)

**Goal**: Ordenar y filtrar la tabla, ver el gráfico perfil ideal vs actual y recibir avisos informativos

**Independent Test**: Abrir una DNF con prioridades y estados mixtos: la tabla va de mayor a menor prioridad, los filtros acotan filas y el gráfico compara requerido contra actual del colaborador elegido

### Tests for User Story 4

- [X] T053 [P] [US4] Crear `$F/components/FiltrosDiagnostico.dom.test.tsx` y `$F/components/GraficoPerfil.dom.test.tsx`: filtros por prioridad y estado y casilla «Mostrar solo competencias con brecha» acotan la tabla sin recargar; gráfico con `role="img"` y `aria-label` que resume los datos, eje A–D, leyenda visible, requerido y actual por competencia, y los competencias «Sin evaluación» sin barra de actual; sin color como único canal (marcas con texto)
- [X] T054 [P] [US4] Crear `$F/orden.test.ts`: la función de orden de filas (prioridad Crítica > Alta > Media > Baja > sin prioridad, luego mayor |diferencia|, luego nombre) y la de filtros, con casos de empate

### Implementation for User Story 4

- [X] T055 [P] [US4] Crear `$F/orden.ts` (orden y filtros puros, tipados) y `$F/components/FiltrosDiagnostico.tsx` (controlado: recibe los valores y los cambia quien lo usa, para poder enlazarlos a la URL) (`SelectField` de prioridad y estado + casilla «solo con brecha»)
- [X] T056 [P] [US4] Crear `$F/components/GraficoPerfil.tsx`: SVG propio sin librerías (research.md R-09): barras del nivel actual (`--accent`), marcador/línea del requerido (`--text`), tramo de brecha con borde discontinuo (`--warn` sobre `--warnsoft`), eje A–D, leyenda; barras horizontales en pantallas estrechas (320 px sin scroll horizontal); equivalente accesible = la tabla
- [X] T057 [US4] Integrar filtros y gráfico en `$F/pages/DnfAdminPage.tsx` (y reutilizable por el detalle de US5): el gráfico sigue al colaborador seleccionado; los avisos de US1/US3 se muestran sin duplicarse. En el **detalle** (`/dnf/:id`) los filtros de prioridad, estado y «solo con brecha» y el colaborador seleccionado se persisten en la URL (constitución II); en el paso 2 del asistente (pantalla transitoria) son estado local

**Checkpoint**: US1–US4 funcionales

---

## Phase 7: User Story 5 - Historial y detalle de DNF (Priority: P2)

**Goal**: Consultar, filtrar y abrir cualquier DNF en solo lectura, con autor y fecha

**Independent Test**: Con las DNF sembradas, filtrar por fechas/colaborador/departamento/prioridad en el historial y abrir un detalle de solo lectura sin editar/eliminar

### Tests for User Story 5

- [X] T058 [P] [US5] Crear `backend/tests/Feature/Desarrollo/DnfListarTest.php`: forma de contracts/dnf-api.md (`cantidad_brechas`, `prioridad_mas_alta`, `tiene_plan`, sin detalle de brechas); filtros `fecha_desde/hasta` (422 si `desde > hasta`), `colaborador_id` (incluye masivas), `departamento_id`, `prioridad` (la más alta de la DNF), `page`/`per_page` (1–100); orden por fecha desc; aislamiento por tenant; 401/403
- [X] T060 [P] [US5] Crear `backend/tests/Feature/Desarrollo/DnfFacadeRegistradasTest.php` para `DesarrolloFacade::getDnfRegistradas(array $filtros)` (cabeceras sin detalle; FR-032) y `$F/pages/DnfHistorialPage.dom.test.tsx` + `$F/pages/DnfDetallePage.dom.test.tsx`: filtros persistidos en la URL, paginación, estados cargando/vacío/error, fila abre el detalle, detalle solo lectura con código, fecha, autor y aviso de omitidas, sin botones editar/eliminar

### Implementation for User Story 5

- [X] T061 [P] [US5] Crear `$D/Application/DTO/FiltroDnf.php`, `ListadoDnf.php` y `FilaHistorialDnfDTO.php`, y el caso de uso `$D/Application/UseCases/ListarDnf.php` (`ObtenerDnf` ya existe desde T050)
- [X] T062 [US5] Añadir la acción `index` a `DnfController` con `$D/Presentation/Requests/ListarDnfRequest.php` (fechas `Y-m-d`, `desde ≤ hasta`, `prioridad` ∈ enumeración, `page`, `per_page` 1–100 por defecto 15) y la ruta `GET /api/dnf` (`dnf.index`) en `$D/Routes/api.php`, declarada junto a las demás rutas fijas
- [X] T063 [US5] Añadir `getDnfRegistradas(array $filtros): array` a `DesarrolloService` y `DesarrolloFacade` (cabeceras: id, código, fecha, alcance, colaboradores, cantidad de brechas; sin modificar nada) para el indicador de Reportes
- [X] T064 [P] [US5] Crear `$F/hooks/useHistorialDnf.ts`, `$F/hooks/useDetalleDnf.ts` y `$F/hooks/useDnfParams.ts` (filtros y paginación en la URL; clave de caché `['dnf','lista', params]`)
- [X] T065 [US5] Crear `$F/pages/DnfHistorialPage.tsx` (patrón Listado, `design/DESIGN.md` §5 punto 14: filtros fecha desde/hasta, `Combobox` de colaborador, `SelectField` de departamento y prioridad; `DataTable` con código, fecha, alcance, colaborador o departamento, cantidad de brechas, `BadgePrioridad` y «Ver detalle»; `Pagination`) y `$F/pages/DnfDetallePage.tsx` (paso 2 en solo lectura reutilizando tabla, gráfico, filtros y avisos; autor y fecha; `Breadcrumb` hacia el historial; 404 con enlace al historial); enlace «Ver historial» desde `DnfAdminPage`. Los filtros del historial y los del detalle (T057) viven en la URL (`useDnfParams`)
- [X] T066 [US5] Ampliar `frontend/e2e/dnf.spec.ts` con el flujo del administrador sobre las DNF sembradas: abrir el historial, filtrar por prioridad y abrir el detalle en solo lectura (sin botones de editar/eliminar; el colaborador recibe acceso denegado a `/dnf/historial`)

**Checkpoint**: US1–US5 funcionales

---

## Phase 8: User Story 6 - Generar borrador de Plan de Formación desde una DNF (Priority: P3)

**Goal**: Solicitar al Plan de Formación un único borrador por DNF con las brechas detectadas

**Independent Test**: Con el doble de `PlanDeFormacionInterface`, una DNF con brechas genera el borrador, queda registrado quién y cuándo, el botón se deshabilita con el enlace; sin brechas o con plan ya generado, 422

### Tests for User Story 6

- [X] T067 [P] [US6] Crear `backend/tests/Feature/Desarrollo/DnfGenerarPlanTest.php`: 201 con `plan_formacion_id`, `generado_por`, `generado_en`; se envían al puerto **todas** las brechas `ConBrecha` de la DNF (un solo borrador también en masivas); DNF sin brechas → 422 «La DNF no tiene brechas.»; segunda llamada → 422 «La DNF ya generó un plan.» (también ante dos peticiones concurrentes simuladas: solo una gana); puerto no disponible → 503 seguro y la DNF **no** queda con plan (se puede reintentar); 404 inexistente u otro tenant; 401/403; el resto de la DNF permanece inmutable
- [X] T068 [P] [US6] Crear `$F/components/BotonGenerarPlan.dom.test.tsx` y `$F/hooks/useGenerarPlan.test.tsx`: visible solo para el administrador; deshabilitado con el motivo visible (`aria-describedby`) si no hay brechas o ya generó plan, mostrando «Ver plan»; la mutación no se reintenta; en caso de 503 muestra el mensaje y permite reintentar; invalida detalle y lista tras el éxito

### Implementation for User Story 6

- [X] T069 [US6] Crear `$D/Application/UseCases/GenerarPlanDesdeDnf.php`: carga la DNF del tenant, exige brechas y ausencia de plan, llama a `PlanDeFormacionInterface::crearBorrador` con las brechas de toda la DNF, y luego `DnfRepositoryInterface::asignarPlan` (si devuelve `false` ⇒ `PlanYaGenerado`); si el puerto lanza ⇒ `PlanNoDisponible` sin marcar el plan
- [X] T070 [US6] Añadir `plan` a `DnfController` y la ruta `POST /api/dnf/{id}/plan` (`dnf.plan`, `whereNumber('id')`, administrador) en `$D/Routes/api.php`; mapear `DnfSinBrechas`/`PlanYaGenerado` a 422, `PlanNoDisponible` a 503 con mensaje seguro y `DnfNoEncontrada` a 404
- [X] T071 [P] [US6] Crear `$F/hooks/useGenerarPlan.ts` y `$F/components/BotonGenerarPlan.tsx` (botón primario «Generar Plan de Formación»; deshabilitado con motivo; tras generar, enlace «Ver plan»)
- [X] T072 [US6] Integrar `BotonGenerarPlan` en `$F/pages/DnfAdminPage.tsx` (paso 2) y en `$F/pages/DnfDetallePage.tsx`; en el detalle mostrar quién y cuándo generó el plan
- [X] T073 [US6] Ampliar `frontend/e2e/dnf.spec.ts` con el botón de plan sobre una DNF sembrada: deshabilitado/“Ver plan” según el caso y mensaje «Plan de Formación no disponible» en el entorno e2e (adaptador `PlanDeFormacionDePrueba`) y comprobar que generar el borrador toma como máximo 3 clics desde la DNF (SC-007)

**Checkpoint**: Todas las historias funcionales de forma independiente

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Calidad transversal y cierre

- [X] T074 [P] Revisar `contracts/dnf-puertos-y-facades.md` y `contracts/dnf-api.md` contra lo implementado (`CuentaColaboradorInterface` y `PersonalFacade::getCuentaDeUsuario` ya documentados como definitivos; `brechasActivas(tenantId, colaboradorId)`) y corregir cualquier diferencia que aparezca durante la implementación
- [X] T075 [P] Documentar los cinco endpoints con los atributos/docblocks que use Scramble en `DnfController` y comprobar `php artisan scramble:export` (o la ruta de docs existente) sin errores
- [X] T076 [P] Revisión de accesibilidad y responsive de las tres pantallas: WCAG 2.2 AA (foco visible, `aria-current`, `role="img"` del gráfico con resumen, texto además de color), temas claro/oscuro persistidos y 320–1440 px sin scroll horizontal; cubrirlo con pruebas de componentes donde falte
- [X] T077 [P] Verificar que ningún texto visible queda fuera de `$F/text.ts`, que no hay `any` ni imports entre módulos de negocio y que las páginas no llaman Axios directamente (`grep` en `frontend/src/modules/desarrollo/dnf`)
- [X] T078 Ejecutar el recorrido de `quickstart.md` (escenarios 1–12 con dobles o con las DNF sembradas) y registrar en él cualquier desviación observada
- [ ] T079 Ejecutar todas las puertas de calidad: `vendor/bin/pest`, `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `npx playwright test` en `frontend/`; corregir lo que falle y confirmar que el módulo `Desarrollo` conserva verdes las pruebas de 005
  - **Estado (2026-10-16)**: lo de esta feature pasa: Pest `Desarrollo`+`GestionPersonal`+`Shared` 330/330, Pint, ESLint completo, `vite build`, Vitest de `dnf` 101/101 y Playwright `dnf.spec.ts` 7/7. **Quedan fallos preexistentes ajenos a la DNF** (verificados en la línea base antes de tocar código): 48 pruebas Pest de `Estructura/Unidades`, 54 pruebas Vitest de `estructura/unidades` más `CatalogoAdminPage.dom.test.tsx` (aviso «solo lectura» comentado en el commit f1903c1), 18 errores de `tsc -b` en pruebas de `estructura/unidades` (por eso `npm run build` falla en `tsc -b`) y el E2E `catalogo-formativo.spec.ts` desde la prueba del administrador por esa misma causa. Hasta corregirlos, esta tarea queda abierta.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias.
- **Foundational (Phase 2)**: depende de Setup; **bloquea todas las historias**.
- **User Stories (Phase 3–8)**: dependen de Foundational. US1 y US2 (P1) pueden ir en paralelo tras Foundational; US2 solo necesita DNF guardadas (siembra T020 o `dnfDeEjemplo`), no la generación de US1.
- **Polish (Phase 9)**: depende de las historias que se entreguen.

### User Story Dependencies

- **US1 (P1)**: tras Foundational; define `GenerarDnf`, `DnfController`, `DnfResource` y la tabla de diagnóstico.
- **US2 (P1)**: tras Foundational; reutiliza `BadgePrioridad`. Independiente de US1 en backend; comparte `DnfController` (T042 después de T033 si se hacen en el mismo archivo).
- **US3 (P2)**: depende de US1 (amplía `GenerarDnf`, `PasoSeleccion`, `DnfAdminPage`) y construye `ObtenerDnf`/`show`, que US5 reutiliza.
- **US4 (P2)**: depende de US1 (tabla y página); su integración con el detalle espera a US5.
- **US5 (P2)**: depende de US3 (reutiliza `ObtenerDnf` y `GET /api/dnf/{id}` creados allí, T050/T059) y de Foundational; la página de detalle reutiliza componentes de US1/US4.
- **US6 (P3)**: depende de US1 y US5 (botón en el asistente y en el detalle) y de `DnfRepositoryInterface::asignarPlan` (T013).

### Within Each User Story

- Pruebas primero y fallando → DTO/casos de uso → controlador y rutas → hooks y componentes → página → E2E.
- Rutas fijas (`opciones`, `mi-diagnostico`) **antes** de `/{id}` en `Routes/api.php`.

### Parallel Opportunities

- Foundational: T006, T007, T009, T010, T012, T014, T015, T017, T019, T021–T024 son [P] (archivos distintos); migraciones T003→T004→T005 en orden por las FK.
- Tras Foundational, un desarrollador puede tomar US1 y otro US2/US5.
- Las pruebas [P] de cada historia se escriben a la vez; en frontend, hooks y componentes de una misma historia son [P] entre sí.

---

## Parallel Example: User Story 1

```text
# Pruebas de US1 a la vez:
T026 backend/tests/Feature/Desarrollo/DnfGenerarIndividualTest.php
T027 backend/tests/Feature/Desarrollo/DnfOpcionesTest.php
T028 $F/components/*.dom.test.tsx + hooks

# Implementación en paralelo (archivos distintos):
T029 DTO · T032 Request/Resources · T034 hooks · T035 Stepper y componentes
```

---

## Implementation Strategy

### MVP First (US1 + US2, ambas P1)

1. Completar Setup y Foundational (cálculo, persistencia inmutable, puertos, siembra).
2. US1: generar la DNF individual (probada con dobles de los puertos).
3. US2: el colaborador consulta su diagnóstico (sembrado) y llega al catálogo.
4. **PARAR y validar** con el quickstart (escenarios 1–3, 9–11).

### Incremental Delivery

US3 (masiva) → US4 (filtros y gráfico) → US5 (historial y detalle) → US6 (plan). Cada una se demuestra por separado sin romper las anteriores.

### Nota de alcance

Mientras GP-02 y Plan de Formación no existan, en un entorno real `POST /api/dnf` responde 422 («No hay colaboradores evaluables.») y `POST /api/dnf/{id}/plan` 503 en un entorno real; las pruebas usan los dobles de T019 y la demostración usa las DNF de `DnfSeeder`. Al llegar esas features solo se escriben los adaptadores definitivos y se cambian los bindings de `DesarrolloServiceProvider`.

En el entorno `e2e` (`APP_ENV=e2e`) el provider enlaza los adaptadores de `Infrastructure/Adapters/Pruebas/` (T018), por lo que los E2E generan DNF y borradores de plan reales sin GP-02 ni Plan de Formación; esos adaptadores nunca se enlazan fuera de ese entorno.
