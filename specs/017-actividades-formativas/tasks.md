---

description: "Tareas de la feature 017-actividades-formativas"
---

# Tasks: Actividades formativas

**Input**: Documentos de diseño en `/specs/017-actividades-formativas/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/actividades-api.md, contracts/actividades-puertos-y-facades.md, quickstart.md. Requiere 000-login, 001/004 (competencias en `EstructuraFacade`), 005-unidad-organizacional (unidades = departamentos), 005-fichas-colaboradores (`PersonalFacade`, `colaborador_cuentas`) y 013-plan-formacion-anual (`planes`, `plan_acciones`, `plan_accion_participantes`, `AsistenciaInterface`, `CambiarParticipantes`, `ObtenerMiPlan`) ya implementadas. **Reportes no existe**: solo se publican los métodos de Facade que consumirá.

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario (US1–US6 de spec.md) para implementarlas y probarlas de forma independiente. La feature añade tablas propias al módulo `Desarrollo` y **no modifica** ninguna tabla existente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1–US6)
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Desarrollo/…`. Abreviatura **`$D`** = `backend/app/Modules/Desarrollo`
- Frontend: `frontend/src/modules/desarrollo/actividades/…` (reutiliza `frontend/src/shared/ui/…`). Abreviatura **`$F`** = `frontend/src/modules/desarrollo/actividades`
- Pruebas backend: `backend/tests/{Unit,Feature}/Desarrollo/` (prefijo `Actividad`); pruebas de componentes: `*.dom.test.tsx` junto al código (con `// @vitest-environment jsdom`); E2E en `frontend/e2e/actividades.spec.ts`
- Regla de módulos: Actividades **no lee `plan_acciones` ni `plan_accion_participantes`**; usa el puerto `AccionesDePlanInterface`. Personal y Estructura solo por puertos cuyos adaptadores llaman a las Facades. `colaborador_id`, `departamento_id`, `tenant_id` y los ids de usuario son enteros **sin clave foránea** entre módulos; entre tablas de la feature se usan FK **sin borrado en cascada** (`restrictOnDelete`)
- Valores en base de datos (sin tildes; la interfaz muestra con tilde): `tipo` de acción ∈ `Presencial` | `Virtual` | `ELearning` | `OJT` | `Codesarrollo` | `Mentoring`; estado calculado ∈ `Programada` | `EnCurso` | `Finalizada` | `Cancelada` (enum `EstadoAccion` existente); asistencia ∈ `Pendiente` | `Presente` | `Ausente` | `Justificado` (Pendiente = sin fila); roles: capacidad `admin` (Administrador) y rol `user` (Colaborador), excluyentes
- Reutilizar sin modificar: `Button`, `Badge`, `Alert`, `ConfirmDialog`, `Modal`, `Combobox`, `SelectField`, `SearchField`, `TextField`, `TextareaField`, `Tabs`, `Pagination`, `Breadcrumb`, `EmptyState`, `ErrorState`, `Skeleton` y `AppShell`. No crear componentes equivalentes
- En frontend, `actividades` **no importa nada** de `plan-formacion`, `micro-learning` ni `catalogo-formativo`, salvo `modules/auth/login` (rol); copiar el patrón de `RequireRole` de micro-learning en lugar de importarlo
- Guía visual: `design/ActividadesAdmin.dc.html`, `design/ActividadesAsistencia.dc.html`, `design/ActividadesCalificaciones.dc.html` y `design/ActividadesColaborador.dc.html` son **referencia primordial, no código para copiar**; en estilos manda `design/DESIGN.md`. **Manda la spec sobre los tableros** (plan.md, tabla «Diferencias entre tableros y spec»): el modal crea desde una acción del plan, sin botón «Cancelar» por fila, sin «Ver como colaborador», sin panel «Fundamento», calificación de 1 en 1, el colaborador no ve observaciones
- Insumo `plan-actividades.md` y plan.md: donde difieren, **prevalecen plan.md, research.md y data-model.md** (`tenant_id` y `version` añadidos, `Pendiente` borra la fila, datos de la acción siempre del plan vigente)
- Lecciones previas: en E2E localizar campos con `getByRole('textbox', { name, exact: true })`; escribir archivos con acentos con la herramienta Write (los heredocs de Bash fallan; tampoco hay Python en el entorno); ver la memoria «Entorno local de pruebas» (OneDrive/Windows) al correr Pest, Vitest y Playwright

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Línea base verde y diseño registrado antes de tocar código

- [X] T001 Verificar la línea base: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run` y `npx playwright test` en `frontend/` deben pasar antes de empezar (sin cambios de archivos). Anotar los fallos preexistentes de la memoria del proyecto (features 006/013/016) para no atribuirlos a esta feature
- [X] T002 Actualizar `design/DESIGN.md` (Constitución III) **antes** de cualquier tarea de frontend: en §2 los colores por estado de actividad (Programada `--info`/`--infosoft`, En curso `--accent`/`--soft`, Finalizada `--neutral`/`--neutralsoft`, Cancelada `--danger`/`--dangersoft`), por asistencia (Pendiente `--neutral`, Presente `--accent`, Ausente `--danger`, Justificado `--warn`) y el badge «Asistencia pendiente» (`--warn`/`--warnsoft`); en §4 los componentes **Control de asistencia (Presente / Ausente / Justificado)**, **Campo de calificación 1–5**, **Resumen de asistencia** y **Tarjeta de actividad**; en §5 los patrones «Lista de actividades con modal de creación», «Registro de asistencia», «Calificaciones por actividad / participante» y «Mis actividades»; las filas de la tabla «Diferencias entre tableros y spec» de plan.md. Sin cambios si ya está completo

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Tablas, modelos, dominio base, puertos con el Plan, repositorio base, rutas protegidas y piezas de frontend que usan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend – datos

- [X] T003 Crear `backend/database/migrations/2026_10_21_000001_create_actividades_table.php` según data-model.md: `id`; `tenant_id` entero sin signo con índice (sin FK); `numero` entero sin signo; `codigo` `string(12)` (`ACT-0001`); `plan_accion_id` FK a `plan_acciones.id` con `restrictOnDelete` y **único**; `lugar_enlace` `string(500)` obligatorio; `departamento_id` entero sin signo anulable con índice (sin FK); `version` entero sin signo por defecto `1`; `created_by` y `updated_by` enteros sin signo; timestamps. Únicas `(tenant_id, numero)` y `(tenant_id, codigo)`; índice `(tenant_id, departamento_id)`
- [X] T004 [P] Crear `backend/database/migrations/2026_10_21_000002_create_actividad_asistencias_table.php`: `id`; `actividad_id` FK a `actividades.id` con `restrictOnDelete`; `colaborador_id` entero sin signo con índice (sin FK); `asistencia` `string(12)` ∈ `Presente` | `Ausente` | `Justificado` (Pendiente = sin fila); `calificacion` entero pequeño sin signo anulable entre 1 y 5, solo si `asistencia = Presente` (validado en la aplicación y con `CHECK` si el motor lo admite); `observaciones` texto anulable, máx. 1 000 caracteres, solo con asistencia registrada; `registrado_by` entero sin signo; timestamps. Única `(actividad_id, colaborador_id)`
- [X] T005 [P] Crear `backend/database/migrations/2026_10_21_000003_create_actividad_asistencia_historial_table.php` (solo se agrega): `id`; `actividad_id` FK a `actividades.id` con `restrictOnDelete`; `colaborador_id` entero sin signo; `asistencia_anterior` y `asistencia_nueva` `string(12)` anulables (nulo = Pendiente); `calificacion_anterior` y `calificacion_nueva` entero pequeño sin signo anulables; `user_id` entero sin signo; solo `created_at`. Índice `(actividad_id, created_at)`
- [X] T006 [P] Crear los modelos Eloquent `$D/Infrastructure/Persistence/Eloquent/Models/ActividadModel.php` (tabla `actividades`, relaciones `asistencias()` y `historial()`), `ActividadAsistenciaModel.php` y `ActividadHistorialModel.php` siguiendo la convención de `PlanAccionModel` / `MicroHistorialModel` (sin soft deletes; el historial sin `updated_at`)
- [X] T007 [P] Escribir `backend/tests/Feature/Desarrollo/ActividadMigracionTest.php`: las tres tablas existen con sus columnas; no se puede insertar una segunda actividad para la misma `plan_accion_id`; `(actividad_id, colaborador_id)` es único; borrar una actividad con registros falla por `restrictOnDelete`; `version` empieza en 1

### Backend – dominio y puertos

- [X] T008 [P] Crear los objetos de valor en `$D/Domain/ValueObjects/`: `CodigoActividad` (`desde(int $numero): string` → `ACT-0001`, igual que `CodigoPlan`), `AsistenciaParticipante` (enum `Pendiente`, `Presente`, `Ausente`, `Justificado`), `FiltroActividades` (q, tipo, estado, departamentoId, competenciaId, planId, fechaDesde, fechaHasta, página y tamaño; inmutable) y `FiltroCalificaciones` (agrupar `actividad`|`participante`, fechas, tipo, departamentoId, planId, colaboradorId, página y tamaño)
- [X] T009 [P] Crear las excepciones de dominio en `$D/Domain/Exceptions/`: `ActividadNoEncontrada`, `AccionNoEncontradaParaActividad`, `AccionNoElegible` (`codigo: accion_no_elegible`), `LugarInvalido`, `DepartamentoInvalido`, `PlanCerradoParaActividad` (`codigo: plan_cerrado`), `AsistenciaNoRegistrable` (`codigo: asistencia_no_registrable`), `RegistroDeAsistenciaInvalido` (con errores por campo), `VersionDesactualizada` (`codigo: version_desactualizada`, HTTP 409) y `ParticipanteConAsistencia` (`codigo: participante_con_asistencia`). Mensajes en español, seguros y sin datos sensibles
- [X] T010 Crear `$D/Domain/Services/ReglasDeActividad.php` (sin Laravel) con: `estado(AccionDeActividad, DateTimeImmutable $hoy): EstadoAccion` reutilizando `CalculadorDeEstadoDeAccion`; `validarLugar(TipoAccion, string): void` (obligatorio; máx. 500 caracteres; `Virtual` y `ELearning` exigen URL absoluta `http`/`https` sin espacios, los demás texto libre); `puedeEditar(planEstado)` (plan ≠ `Cerrado`); `puedeRegistrar(estadoActividad, planEstado)`; `motivoBloqueo(...)` ∈ `null | actividad_programada | actividad_cancelada | plan_cerrado`; `asistenciaPendiente(estado, inscritos, registradosDeInscritos): bool` (Finalizada y pendientes > 0). Crear también el objeto de valor de lectura `$D/Domain/ValueObjects/AccionDeActividad.php` (en **Dominio**, sin dependencias de Laravel ni de Application, para que `ReglasDeActividad` lo use sin invertir las dependencias —Constitución I—; el puerto de aplicación lo devuelve) con (accion_id, plan_id, plan_codigo, plan_nombre, plan_estado, nombre, tipo, fecha_inicio, fecha_fin, duracion_horas, formador, capacidad_maxima, cancelada, competencia_ids, participantes)
- [X] T011 [P] Escribir `backend/tests/Unit/Desarrollo/ReglasDeActividadTest.php` (Pest, sin base de datos): estados Programada/En curso/Finalizada/Cancelada con fechas límite (hoy = inicio y hoy = fin); `validarLugar` para los seis tipos (URL inválida en Virtual y E-learning, texto libre aceptado en los demás, vacío y > 500 rechazados); `puedeEditar`/`puedeRegistrar` y `motivoBloqueo` en cada combinación estado × estado del plan; `asistenciaPendiente` solo en Finalizada con pendientes
- [X] T012 [P] Crear los puertos en `$D/Application/Ports/`: `AccionesDePlanInterface` (`porIds(tenantId, accionIds): array<int, AccionDeActividad>`, `elegibles(tenantId, accionIdsConActividad, planId?, q?, page, perPage)`, `obtener(tenantId, accionId): ?AccionDeActividad`), `DepartamentosInterface` (`activos(): list<{id, nombre}>`, `porIds(ids): array<int,string>`, `estaActivo(id): bool`) y `AsistenciaRegistradaInterface` (`tieneAsistencia(accionId, colaboradorId): bool`), con docblocks como `AsistenciaInterface`
- [X] T013 Ampliar `$D/Domain/Repositories/PlanRepositoryInterface.php` e implementar en `$D/Infrastructure/Persistence/Eloquent/Repositories/EloquentPlanRepository.php` dos métodos de solo lectura: `accionesPorIds(int $tenantId, array $ids)` (acción + plan + competencias + participantes, incluyendo canceladas y planes de cualquier estado) y `accionesElegiblesParaActividad(int $tenantId, array $idsConActividad, ?int $planId, ?string $q, int $page, int $perPage)` (no canceladas, plan `Ejecutando`, `id` fuera de `$idsConActividad`, búsqueda por nombre sin distinguir mayúsculas). No toca tablas de Actividades
- [X] T014 [P] Crear los adaptadores en `$D/Infrastructure/Adapters/`: `AccionesDesdePlan` (implementa `AccionesDePlanInterface` sobre `PlanRepositoryInterface`; mapea al objeto de valor de dominio `AccionDeActividad`) y `DepartamentosDesdeEstructura` (implementa `DepartamentosInterface` con `EstructuraFacade::getUnidadesActivas()` y `getUnidadesPorIds()`)
- [X] T015 Crear `$D/Domain/Repositories/ActividadRepositoryInterface.php` con las operaciones de contracts/actividades-puertos-y-facades.md (`crear` asignando `numero`/`codigo` en transacción, `actualizar`, `obtener`, `obtenerPorAccion`, `listarDelTenant`, `idsDeAccionesConActividad`, `registrosDe`, `registradosPorActividades`, `guardarAsistencia` atómico con `versionEsperada`, `historial`, `agregadosPorActividad`, `agregadosPorColaborador`, `actividadesDeColaborador`, `tieneAsistencia`) y una entidad de lectura `$D/Domain/Entities/Actividad.php`
- [X] T016 Implementar en `$D/Infrastructure/Persistence/Eloquent/Repositories/EloquentActividadRepository.php` las operaciones de base: `crear` (calcula `numero` = máximo del tenant + 1 y `codigo` con `CodigoActividad` dentro de una transacción; reintenta ante colisión de la única), `obtener`, `obtenerPorAccion`, `listarDelTenant`, `idsDeAccionesConActividad`, `registrosDe`. Las demás se implementan en su historia
- [X] T017 Crear `$D/Application/Services/ArmadorDeActividades.php`: arma el «resumen» y el «detalle» de contracts/actividades-api.md a partir de la actividad, la `AccionDeActividad` (puerto), `DepartamentosInterface`, `NombresDeReferenciaInterface` (colaboradores y competencias), `NombresDeUsuarioInterface` (auditoría) y `RelojInterface::hoy`; calcula estado, `puede_editar`, `puede_registrar_asistencia`, `motivo_bloqueo`, `resumen_asistencia`, participantes (inscritos actuales + registro o `Pendiente`) y `asistencia_pendiente`. Nunca incluye observaciones en la variante del colaborador
- [X] T018 Crear el trait `$D/Presentation/Controllers/RespondeErroresDeActividad.php` (siguiendo `RespondeErroresDePlan`): 404 para `ActividadNoEncontrada`/`AccionNoEncontradaParaActividad`, 409 para `VersionDesactualizada`, 422 con `{message, codigo}` o `{message, errors}` para el resto
- [X] T019 Registrar en `$D/Infrastructure/Providers/DesarrolloServiceProvider.php` los *bindings* nuevos (`ActividadRepositoryInterface`→`EloquentActividadRepository`, `AccionesDePlanInterface`→`AccionesDesdePlan`, `DepartamentosInterface`→`DepartamentosDesdeEstructura`) y añadir en `$D/Routes/api.php` el grupo `Route::prefix('api/actividades')->middleware('api')` con las rutas fijas antes de `/{id}` y los middlewares: `auth:sanctum` en todas, `admin` en todas salvo `mis-actividades` (los controladores se crean en cada historia; registrar las rutas junto a su controlador)

### Frontend – base común

- [X] T020 [P] Crear `$F/text.ts` con **todo** el texto visible en español (navegación «Actividades», títulos, columnas, filtros, estados, asistencia, mensajes de error/éxito, confirmaciones, explicaciones de bloqueo `actividad_programada`/`actividad_cancelada`/`plan_cerrado`, vacíos, texto del colaborador) y `$F/estilosEstado.ts` con las variantes de `Badge` por estado de actividad y por asistencia
- [X] T021 [P] Crear `$F/api.ts` con los tipos TypeScript estrictos (sin `any`) de contracts/actividades-api.md y las funciones de acceso usando el cliente HTTP compartido de `frontend/src/shared/api`; las escrituras (`POST`, `PUT`) sin reintentos. Crear `$F/hooks/claves.ts` con las claves de TanStack Query
- [X] T022 [P] Crear `$F/components/RequireRole.tsx` (patrón de micro-learning: elige la vista por `useCurrentUser().data.role`, `admin` o `user`; muestra `role="status"` mientras carga y un `role="alert"` sin permisos) y `$F/nav.tsx` con el ítem «Actividades» (icono de `shared/ui/icons`)
- [X] T023 [P] Crear `$F/components/BadgeEstadoActividad.tsx` y `$F/components/BadgeAsistencia.tsx` (sobre `shared/ui/Badge`, usando `estilosEstado.ts`; incluir el badge «Asistencia pendiente» como variante) con `*.dom.test.tsx` que verifican el texto y que el color no es el único indicador
- [X] T024 Registrar en `frontend/src/App.tsx` el ítem `actividadesNav` en `navItems` (después de `planFormacionNav` y `microLearningNav`) y las rutas `actividades` (página raíz por rol) y `actividades/:id/asistencia` (solo administrador), con páginas esqueleto que se completan en cada historia

**Checkpoint**: tablas, puertos, repositorio base, rutas protegidas y piezas comunes listas; las historias pueden empezar

---

## Phase 3: User Story 1 - Crear una actividad desde una acción del plan (Priority: P1) 🎯 MVP

**Goal**: el administrador crea una actividad desde una acción elegible, con lugar o enlace y departamento opcional, y la ve en la lista.

**Independent Test**: con un plan Ejecutando con una acción sin actividad, crear la actividad con un lugar, verificar el código ACT-NNNN, los datos heredados y que la acción deja de aparecer en el selector; una acción Virtual con un texto que no es URL es rechazada.

### Tests (US1)

- [X] T025 [P] [US1] Escribir `backend/tests/Feature/Desarrollo/ActividadCrearTest.php`: 201 con código `ACT-0001` y datos heredados; el siguiente es `ACT-0002`; 422 con `lugar_enlace` vacío, > 500 caracteres o, en Virtual/E-learning, sin URL `http(s)`; 422 con departamento inexistente o inactivo; 422 `accion_no_elegible` con acción cancelada, plan que no está Ejecutando o acción que ya tiene actividad; 404 con acción inexistente; 403 para Colaborador y 401 sin sesión; queda registrado `created_by`/`updated_by`
- [X] T026 [P] [US1] Escribir `backend/tests/Feature/Desarrollo/ActividadAccionesDisponiblesTest.php` y `ActividadDepartamentosTest.php`: solo acciones no canceladas de planes Ejecutando sin actividad; filtros `plan_id` y `q`; paginación; una acción deja de aparecer al crearle actividad; `departamentos` devuelve solo unidades activas; 403 para Colaborador
- [X] T027 [P] [US1] Escribir `backend/tests/Feature/Desarrollo/ActividadListarBasicoTest.php` y `ActividadObtenerTest.php`: la lista devuelve las columnas del contrato con estado calculado y el año actual por defecto; el detalle incluye datos de la acción, competencias, participantes con `Pendiente` por defecto y `puede_editar`/`puede_registrar_asistencia`; una actividad cuyo departamento pasó a inactivo conserva y muestra su nombre, y un colaborador cuyo nombre no se resuelve se muestra como «No disponible» sin romper la respuesta; los datos heredados reflejan el plan vigente (si cambia la fecha en el plan, cambia el estado); 404 inexistente; el detalle del administrador incluye `observaciones`

### Implementation (US1)

- [X] T028 [US1] Crear los casos de uso `$D/Application/UseCases/ListarAccionesDisponibles.php`, `ListarDepartamentosParaActividad.php`, `CrearActividad.php` y `ObtenerActividad.php`. `CrearActividad` revalida en la transacción: acción existente (404), no cancelada, plan `Ejecutando`, sin actividad previa (`AccionNoElegible`), `ReglasDeActividad::validarLugar` según el tipo y `DepartamentosInterface::estaActivo`; guarda `tenant_id` del plan, `created_by` = `updated_by` = usuario autenticado
- [X] T029 [US1] Crear `$D/Application/UseCases/ListarActividades.php` en su versión base: actividades del tenant + datos de la acción por el puerto (`porIds` por lote), estado calculado y paginación; sin otros filtros que el año actual (`fecha_desde`/`fecha_hasta` ausentes ⇒ acciones cuyas fechas se superponen con el año actual). Orden: fecha de inicio descendente, luego código
- [X] T030 [P] [US1] Crear `$D/Presentation/Requests/GuardarActividadRequest.php` (`plan_accion_id` entero requerido solo al crear; `lugar_enlace` string requerido máx. 500; `departamento_id` entero anulable), `ListarActividadesRequest.php` y `AccionesDisponiblesRequest.php` (`plan_id`, `q`, `page`, `per_page` máx. 50); mensajes en español
- [X] T031 [US1] Crear `$D/Presentation/Controllers/ActividadController.php` (delgado, sin consultas): `accionesDisponibles`, `departamentos`, `index`, `store`, `show`; registrar sus rutas en `$D/Routes/api.php` (`GET acciones-disponibles` y `GET departamentos` antes de `GET /{id}`; `GET /`, `POST /`, `GET /{id}` con `whereNumber('id')`)

### Frontend (US1)

- [X] T032 [P] [US1] Crear `$F/validation.ts` (Zod): `lugar_enlace` obligatorio, máx. 500 y, para tipo `Virtual`/`ELearning`, URL `http(s)` válida; `departamento_id` opcional; mensajes desde `text.ts`. Con `validation.test.ts`
- [X] T033 [P] [US1] Crear los hooks `$F/hooks/useActividades.ts`, `useActividad.ts`, `useAccionesDisponibles.ts`, `useDepartamentos.ts` y `useGuardarActividad.ts` (mutación de crear sin reintentos, invalida lista y acciones disponibles)
- [X] T034 [P] [US1] Crear `$F/components/SelectorAccion.tsx` (sobre `Combobox` y `SelectField` compartidos: búsqueda por nombre, filtro por plan, estado vacío «No hay acciones disponibles», loading y error con reintento) y `$F/components/DatosHeredados.tsx` (nombre, tipo, competencias, fechas, formador, capacidad e inscritos en solo lectura con `ListaDeCampos`)
- [X] T035 [US1] Crear `$F/components/FormularioActividadModal.tsx` (patrón «Formulario en modal», `Modal` de 860 px como `design/ActividadesAdmin.dc.html`): selector de acción → datos heredados → campos «Lugar o enlace» (etiqueta según el tipo elegido) y «Departamento»; React Hook Form + Zod; errores por campo desde el 422; conserva valores; bloquea el envío pendiente; al crear cierra el modal y muestra el aviso «Se creó la actividad ACT-NNNN»
- [X] T036 [P] [US1] Crear `$F/components/TablaActividades.tsx` (columnas: código, nombre, tipo, fechas, lugar o enlace, formador, departamento, capacidad, inscritos, estado y acciones «Asistencia» y «Editar»; chip de tipo con los colores de los tableros; `EmptyState`, `Skeleton`, `ErrorState`) y `$F/components/VistaAdministrador.tsx` (encabezado, botón «+ Nueva Actividad», `Tabs` Actividades/Calificaciones con `?vista=` en la URL, tabla). El botón de crear y las acciones solo existen en esta vista
- [X] T037 [US1] Completar `$F/pages/ActividadesPage.tsx`: `RequireRole` con `admin={<VistaAdministrador />}` y `colaborador={<MisActividadesPage />}` (este último se completa en US3)
- [X] T038 [P] [US1] Escribir `$F/components/FormularioActividadModal.dom.test.tsx` y `TablaActividades.dom.test.tsx` (Vitest + Testing Library): el selector lista y filtra acciones; elegir una muestra los datos heredados sin campos editables; la validación de URL por tipo muestra el error por campo; los estados loading/empty/error/success de la tabla; no existe botón de eliminar ni de cancelar

**Checkpoint**: el administrador crea actividades y las ve en la lista (MVP navegable)

---

## Phase 4: User Story 2 - Registrar asistencia y calificaciones (Priority: P1)

**Goal**: el administrador registra asistencia, calificación 1–5 y observaciones de los inscritos de una actividad En curso o Finalizada, con guardado atómico, confirmación e historial.

**Independent Test**: con una actividad En curso y tres inscritos, marcar uno Presente con calificación 4, otro Ausente y otro Justificado; guardar y verificar que persisten, que la calificación solo existe en el Presente y que cada cambio queda en el historial.

### Tests (US2)

- [X] T039 [P] [US2] Escribir `backend/tests/Unit/Desarrollo/ValidadorDeRegistrosDeAsistenciaTest.php` (Pest, sin Laravel): asistencia fuera de `Pendiente|Presente|Ausente|Justificado`; calificación no entera, 0, 6 o decimal; calificación con Ausente, Justificado o Pendiente; observaciones o calificación con `Pendiente`; observaciones > 1 000 caracteres; colaborador repetido; colaborador no inscrito; `registros` vacío
- [X] T040 [P] [US2] Escribir `backend/tests/Feature/Desarrollo/ActividadAsistenciaTest.php`: guardado exitoso devuelve el detalle con `version` + 1; **todo o nada** (un registro inválido no guarda ninguno); cambiar Presente→Ausente/Justificado/Pendiente elimina la calificación y escribe historial con valor anterior y nuevo (incluida la eliminación automática); `Pendiente` borra la fila y sus observaciones; solo cambios de observaciones no generan historial; solo se escriben los registros que difieren del actual; 422 `asistencia_no_registrable` con actividad Programada o Cancelada (y los registros existentes de una Cancelada se conservan); 422 `plan_cerrado`; 409 `version_desactualizada` si la versión no coincide y no se guarda nada; 403 para Colaborador; un inscrito posterior aparece como Pendiente; un colaborador retirado del plan sin registro deja de listarse y de contar como pendiente
- [X] T041 [P] [US2] Añadir a `backend/tests/Feature/Desarrollo/ActividadAsistenciaTest.php` el caso de «Marcar todos presentes» (operación solo de cliente, sin endpoint propio): un único `PUT /asistencia` que marca Presente a todos los Pendientes deja intactos los registros previos de Ausente y Justificado que el cliente no envía, y escribe historial solo para los Pendientes que cambian

### Implementation (US2)

- [X] T042 [US2] Crear `$D/Domain/Services/ValidadorDeRegistrosDeAsistencia.php` (sin Laravel) con las reglas de T039, devolviendo errores por campo (`registros.N.calificacion`, etc.) que `RegistroDeAsistenciaInvalido` transporta al 422
- [X] T043 [US2] Implementar en `EloquentActividadRepository` `guardarAsistencia(actividad, cambios, historial, versionEsperada)`: transacción; compara `version` con `lockForUpdate`-equivalente (en SQLite, `update ... where version = ?` y verificar filas afectadas) y lanza `VersionDesactualizada`; inserta/actualiza/borra las filas de `actividad_asistencias`; inserta el historial; incrementa `version`, `updated_by` y `updated_at`; la lectura paginada del historial se implementa en T073 (US6); las pruebas de esta historia leen `actividad_asistencia_historial` directamente
- [X] T044 [US2] Crear `$D/Application/UseCases/RegistrarAsistencia.php`: obtiene actividad y acción (404), aplica `puedeRegistrar` (`AsistenciaNoRegistrable`/`PlanCerradoParaActividad`), valida con `ValidadorDeRegistrosDeAsistencia` contra los inscritos actuales, calcula solo los cambios reales respecto de los registros existentes, genera una fila de historial por cada cambio de `asistencia` o `calificacion` (incluida la eliminación automática de la calificación), delega en `guardarAsistencia` y devuelve el detalle armado
- [X] T045 [P] [US2] Crear `$D/Presentation/Requests/RegistrarAsistenciaRequest.php` (`version` entero requerido; `registros` arreglo requerido no vacío; por registro `colaborador_id` entero, `asistencia` en `Pendiente,Presente,Ausente,Justificado`, `calificacion` entero anulable, `observaciones` string anulable máx. 1 000) y `$D/Presentation/Controllers/ActividadAsistenciaController.php` con `update`; registrar `PUT /{id}/asistencia` con `whereNumber('id')`

### Frontend (US2)

- [X] T046 [P] [US2] Crear `$F/hooks/useRegistrarAsistencia.ts` (mutación sin reintentos; en 409 expone el mensaje de recarga y no pierde los valores del formulario; al éxito actualiza el detalle y la lista) y `$F/registros.ts` con la lógica pura del borrador: estado inicial desde el detalle, `marcarTodosPresentes` (solo los `Pendiente`; no cambia a quienes ya tienen un registro), alternar la opción seleccionada para volver a Pendiente (escenario 10 de US2), limpiar calificación y observaciones al pasar a no Presente / Pendiente, calcular cuáles registros previos se modifican (para la confirmación) y generar el cuerpo del `PUT` solo con lo que cambió. Con `registros.test.ts`
- [X] T047 [P] [US2] Crear `$F/components/ResumenAsistencia.tsx` (chips Presentes / Ausentes / Justificados / Sin registrar con `role="status"` y `aria-live="polite"`, como el tablero), `$F/components/TablaAsistencia.tsx` (columnas #, participante, control de tres opciones Presente / Ausente / Justificado accesible por teclado, calificación 1–5 con paso 1 deshabilitada si no es Presente, observaciones deshabilitadas si es Pendiente; botón «Marcar todos presentes») y `$F/components/ConfirmarCambioDialog.tsx` (sobre `ConfirmDialog`: lista los participantes con registro previo que cambian)
- [X] T048 [US2] Crear `$F/pages/AsistenciaPage.tsx` en `/actividades/:id/asistencia` (solo administrador): encabezado con datos de la actividad y `BadgeEstadoActividad`, `ResumenAsistencia`, `TablaAsistencia`, botón «Guardar» que abre `ConfirmarCambioDialog` solo si se modifica un registro previo, aviso de éxito con `role="status"`, vista deshabilitada con la explicación de `motivo_bloqueo` (FR-021), loading/empty/error, advertencia al salir con cambios sin guardar, y manejo del 409
- [X] T049 [P] [US2] Escribir `$F/components/TablaAsistencia.dom.test.tsx` y `$F/pages/AsistenciaPage.dom.test.tsx`: calificación y observaciones deshabilitadas en Pendiente y habilitadas al marcar Presente; «Marcar todos presentes» solo cambia Pendientes; pulsar de nuevo la opción seleccionada vuelve a Pendiente y limpia calificación y observaciones; cambiar un registro previo abre la confirmación; la vista queda deshabilitada con la explicación correcta para Programada, Cancelada y plan Cerrado; el error 409 muestra el mensaje y conserva los valores

**Checkpoint**: el administrador registra y corrige asistencia; el historial se alimenta

---

## Phase 5: User Story 3 - Consultar actividades como colaborador (Priority: P1)

**Goal**: el colaborador ve sus actividades (próximas e historial) con su asistencia y calificación, y el Plan usa la asistencia para el progreso y para impedir quitar participantes con registro.

**Independent Test**: con un colaborador inscrito en una actividad Programada y otra Finalizada con asistencia Presente y calificación 4, verificar que aparecen en las secciones correctas, sin observaciones; que su progreso en el Plan cuenta la Finalizada; y que quitarlo de esa acción es rechazado.

### Tests (US3)

- [X] T050 [P] [US3] Escribir `backend/tests/Feature/Desarrollo/ActividadMisActividadesTest.php`: solo actividades del usuario del token en planes Ejecutando o Cerrado y solo de acciones con actividad; `proximas` (Programada/En curso, inicio ascendente) e `historial` (Finalizada/Cancelada, de la más reciente a la más antigua); asistencia `Pendiente` visible tal cual y sin calificación (aclaración 3); **jamás** aparece la clave `observaciones`; `resumen` correcto (inscritas, finalizadas, % de asistencia, promedio); respuesta vacía sin actividades o sin ficha; actividades de otros colaboradores y de planes en otros estados no aparecen; 401 sin sesión
- [X] T051 [P] [US3] Escribir `backend/tests/Feature/Desarrollo/ActividadIntegracionPlanTest.php`: `ObtenerMiPlan` cuenta como completada solo la acción cuya actividad está Finalizada y el colaborador está Presente (Ausente, Justificado, Pendiente, actividad En curso o acción sin actividad no cuentan); `PUT /api/plan-acciones/{id}/participantes` devuelve 422 `participante_con_asistencia` si se quita a alguien con asistencia registrada y no cambia nada; quitar a alguien sin registro sigue funcionando; agregar sigue funcionando

### Implementation (US3)

- [X] T052 [US3] Crear `$D/Infrastructure/Adapters/AsistenciaDesdeActividades.php` (implementa `AsistenciaInterface::presentesPorAccion`: acciones con actividad Finalizada y fila `Presente` del colaborador, usando `ActividadRepositoryInterface` y el puerto de acciones) y `AsistenciaRegistradaDesdeActividades.php` (implementa `AsistenciaRegistradaInterface`). En `DesarrolloServiceProvider.php` cambiar el *binding* de `AsistenciaInterface` a `AsistenciaDesdeActividades`, registrar `AsistenciaRegistradaInterface` y **eliminar** `$D/Infrastructure/Adapters/AsistenciaSinDatos.php` (y su comentario PROVISIONAL)
- [X] T053 [US3] Modificar `$D/Application/UseCases/CambiarParticipantes.php`: inyectar `AsistenciaRegistradaInterface` y, si entre `$quitados` hay un colaborador con asistencia registrada en la acción, lanzar `ParticipanteConAsistencia` antes de la transacción; mapear la excepción a 422 en el trait de respuestas de plan (`RespondeErroresDePlan`) con `codigo: participante_con_asistencia`. Actualizar los dobles de prueba de plan si el constructor cambia
- [X] T054 [US3] Crear `$D/Application/UseCases/ObtenerMisActividades.php`: `CuentaColaboradorInterface::cuentaDe(usuarioId)` (sin ficha ⇒ vacío), actividades del colaborador de planes Ejecutando o Cerrado, asistencia y calificación de su fila (o `Pendiente`), `proximas`/`historial` con el orden del contrato, `resumen` derivado (FR-037: inscritas, finalizadas, % de asistencia = Presentes sobre registros de asistencia, calificación promedio; `null` sin datos); **sin observaciones en ningún campo**. Crear `$D/Presentation/Controllers/MisActividadesController.php` y registrar `GET /mis-actividades` (solo `auth:sanctum`, sin `admin`, antes de `/{id}`)

### Frontend (US3)

- [X] T055 [P] [US3] Crear `$F/hooks/useMisActividades.ts` y `$F/components/TarjetaActividad.tsx` (nombre, tipo, fecha, lugar o enlace —enlace clicable en Virtual/E-learning—, formador, `BadgeEstadoActividad`, `BadgeAsistencia` y calificación; sin observaciones; el estado «Pendiente» tal cual) y `$F/components/SeccionesColaborador.tsx` («Próximas actividades» con aviso de «cuándo» como el tablero e «Historial»)
- [X] T056 [US3] Crear `$F/pages/MisActividadesPage.tsx` según `design/ActividadesColaborador.dc.html`: encabezado «Mis actividades formativas», cuatro tarjetas-resumen derivadas (FR-037: inscritas, finalizadas, asistencia, calificación promedio) con «—» sin datos, secciones, mensaje informativo si no tiene actividades, loading y error con reintento. Conectarla en `ActividadesPage.tsx`
- [X] T057 [P] [US3] Escribir `$F/pages/MisActividadesPage.dom.test.tsx` y `$F/components/TarjetaActividad.dom.test.tsx`: secciones y orden; resumen con «—» cuando no hay datos (FR-037); «Pendiente» sin calificación; no se renderiza ningún texto de observaciones aunque el objeto lo trajera; mensaje sin actividades; estados loading/error

**Checkpoint**: P1 completo — crear, registrar y consultar; el Plan ya usa la asistencia real

---

## Phase 6: User Story 4 - Listar y filtrar actividades (Priority: P2)

**Goal**: la lista del administrador permite búsqueda y filtros combinados, año actual por defecto y el badge «Asistencia pendiente».

**Independent Test**: con actividades de distintos tipos, estados y departamentos, aplicar filtros combinados y verificar la tabla, la URL y el badge solo en Finalizadas con pendientes.

### Tests (US4)

- [X] T058 [P] [US4] Ampliar `backend/tests/Feature/Desarrollo/ActividadListarTest.php`: filtros `q`, `tipo`, `estado` (calculado), `departamento_id`, `competencia_id`, `plan_id`, `fecha_desde`/`fecha_hasta`, combinados; sin fechas ⇒ año actual (una actividad de un plan de otro año no aparece); `asistencia_pendiente` verdadero solo en Finalizada con inscritos sin registro y falso con todos registrados o si no está Finalizada; un inscrito retirado sin registro no cuenta; paginación y metadatos; 422 con filtro inválido; 403 Colaborador
- [X] T059 [P] [US4] Ampliar la prueba de rendimiento razonable en el mismo archivo: el número de consultas de la lista no crece al pasar de 3 a 12 actividades (sin N+1); el objetivo de 1 000 actividades en menos de 2 s se valida con el diseño por lotes, no con una prueba de carga

### Implementation (US4)

- [X] T060 [US4] Ampliar `$D/Application/UseCases/ListarActividades.php`: aplicar en memoria, tras leer las acciones por lote, los filtros de nombre, tipo, estado calculado, competencia, plan y rango de fechas (superposición), más el filtro de departamento; calcular `asistencia_pendiente` con `AccionesDePlanInterface` (inscritos actuales) y `registradosPorActividades` (implementar en `EloquentActividadRepository`) y paginar al final. Ampliar `ListarActividadesRequest.php` con la validación de cada filtro (`tipo` y `estado` en sus listas, fechas `Y-m-d`, `fecha_hasta` ≥ `fecha_desde`)

### Frontend (US4)

- [X] T061 [P] [US4] Crear `$F/hooks/useActividadesParams.ts` (búsqueda, filtros y página persistidos en la URL; la UI **siempre envía un rango explícito**, por defecto del 1 de enero al 31 de diciembre del año actual; para ver otros años se cambian las fechas; «Limpiar filtros» restablece el año actual) y `$F/components/FiltrosActividades.tsx` (`SearchField` por nombre; `SelectField` de tipo con los seis tipos incluido E-learning, estado, departamento, plan; competencia; rango de fechas; contador «Mostrando N de M» con `role="status"`)
- [X] T062 [US4] Integrar filtros, paginación (`Pagination`) y el badge «Asistencia pendiente» en `$F/components/VistaAdministrador.tsx` y `TablaActividades.tsx`; estado vacío «Ninguna actividad coincide con los filtros»
- [X] T063 [P] [US4] Escribir `$F/components/FiltrosActividades.dom.test.tsx` y `$F/hooks/useActividadesParams.test.ts`: los filtros se reflejan en la URL y se restauran desde ella; el año actual es el valor por defecto; limpiar filtros; el badge aparece solo cuando `asistencia_pendiente` es verdadero

**Checkpoint**: la lista es completa y navegable por URL

---

## Phase 7: User Story 5 - Consultar calificaciones agregadas (Priority: P2)

**Goal**: dos pestañas, «Por actividad» y «Por participante», con promedios y filtros.

**Independent Test**: con dos actividades en las que un colaborador obtuvo 4 y 5, su promedio es 4,5 y el detalle lista ambas; una actividad sin notas muestra promedio sin valor; un Presente sin calificación no aparece en «Por participante».

### Tests (US5)

- [X] T064 [P] [US5] Escribir `backend/tests/Unit/Desarrollo/CalculadorDePromediosTest.php` (Pest): promedio de 3, 4 y 5 = 4,0; sin notas = `null`; redondeo a un decimal con redondeo estándar, mitad hacia arriba, como `round()` de PHP (4,25 → 4,3; 4,24 → 4,2); considera solo calificaciones asignadas
- [X] T065 [P] [US5] Escribir `backend/tests/Feature/Desarrollo/ActividadCalificacionesTest.php`: `agrupar=actividad` (calificados y promedio, `null` sin notas, incluye actividades sin notas); `agrupar=participante` (solo colaboradores con al menos una calificación, promedio sobre las actividades filtradas, cambia al cambiar el filtro de fechas/tipo/departamento/plan); `colaborador_id` devuelve el detalle por actividad con asistencia y calificación; 422 sin `agrupar` o inválido; paginación; 403 Colaborador; jamás incluye observaciones

### Implementation (US5)

- [X] T066 [US5] Crear `$D/Domain/Services/CalculadorDePromedios.php` (promedio con un decimal; `null` sin datos) e implementar en `EloquentActividadRepository` `agregadosPorActividad(filtros)` y `agregadosPorColaborador(filtros)` con agregación SQL (`COUNT` de `calificacion IS NOT NULL`, `SUM`) restringida al tenant
- [X] T067 [US5] Crear `$D/Application/UseCases/ListarCalificaciones.php` (une los agregados con los datos de la acción por el puerto y aplica fechas, tipo, departamento y plan; nombres de colaboradores por `NombresDeReferenciaInterface`; detalle por colaborador), `$D/Presentation/Requests/ListarCalificacionesRequest.php` y la acción `calificaciones` en `ActividadController.php`; registrar `GET /calificaciones` antes de `/{id}`

### Frontend (US5)

- [X] T068 [P] [US5] Crear `$F/hooks/useCalificaciones.ts` y `$F/hooks/useCalificacionesParams.ts` (los filtros de rango de fechas, tipo, departamento y plan, el participante seleccionado y la página se persisten en la URL junto con `?vista=calificaciones` —Constitución II—; con `useCalificacionesParams.test.ts`) y `$F/components/TablaCalificaciones.tsx` (Por actividad: nombre, tipo, fechas, calificados, promedio; Por participante: nombre, actividades calificadas, promedio; promedio sin valor «—») y `$F/components/DetalleParticipante.tsx` (detalle por actividad al elegir un participante), con filtros de rango de fechas, tipo, departamento y plan
- [X] T069 [US5] Integrar la pestaña «Calificaciones» (`?vista=calificaciones`, `Tabs` compartido, filtros y participante seleccionado leídos y escritos en la URL mediante `useCalificacionesParams`) en `VistaAdministrador.tsx` según `design/ActividadesCalificaciones.dc.html`; loading, vacío («Aún no hay calificaciones registradas para este filtro»), error con reintento
- [X] T070 [P] [US5] Escribir `$F/components/TablaCalificaciones.dom.test.tsx`: ambas pestañas, promedio sin valor, selección de participante y su detalle, estados de datos

**Checkpoint**: las calificaciones agregadas están disponibles

---

## Phase 8: User Story 6 - Editar actividad y consultar historial (Priority: P3)

**Goal**: editar lugar o enlace y departamento mientras el plan no esté Cerrado, y consultar el historial de registros.

**Independent Test**: editar el lugar y verificar la auditoría de modificación; cambiar una asistencia y verificar el historial con valor anterior y nuevo; con el plan Cerrado, todo queda en solo lectura.

### Tests (US6)

- [X] T071 [P] [US6] Escribir `backend/tests/Feature/Desarrollo/ActividadActualizarTest.php`: 200 reemplaza lugar y departamento (incluido `null`); `updated_by`/`updated_at` cambian y `created_by` no; la acción, el código y los demás campos no se modifican aunque lleguen en el cuerpo; mismas validaciones de URL por tipo; 422 `plan_cerrado`; 404; una actividad Cancelada sí puede editarse mientras el plan no esté Cerrado; 403 Colaborador
- [X] T072 [P] [US6] Escribir `backend/tests/Feature/Desarrollo/ActividadHistorialTest.php`: lista del más reciente al más antiguo con usuario, fecha, participante, anterior y nuevo; incluye la eliminación automática de calificación; paginación; 404; 403 Colaborador; el historial no se puede modificar ni borrar (no existe ruta)

### Implementation (US6)

- [X] T073 [US6] Crear `$D/Application/UseCases/ActualizarActividad.php` (valida `puedeEditar`, lugar según el tipo y departamento activo; actualiza solo `lugar_enlace` y `departamento_id`, `updated_by`) y `$D/Application/UseCases/ListarHistorialDeActividad.php` (nombres de usuario por `NombresDeUsuarioInterface`, nombres de colaboradores por `NombresDeReferenciaInterface`); implementar `historial` paginado en `EloquentActividadRepository`; añadir `update` e `historial` a `ActividadController.php` y registrar `PUT /{id}` y `GET /{id}/historial`

### Frontend (US6)

- [X] T074 [P] [US6] Crear `$F/hooks/useHistorialActividad.ts` y `$F/components/HistorialActividad.tsx` (tabla paginada con usuario, fecha, participante, anterior y nuevo; vacío y error) y mostrarlo desde `AsistenciaPage.tsx` (panel o `Modal` «Historial de registros»)
- [X] T075 [US6] Habilitar la edición en `FormularioActividadModal.tsx` (acción «Editar» de la tabla): acción del plan y código fijos, mismos campos y validaciones, solo lectura con aviso si `puede_editar` es falso; invalida lista y detalle. Ampliar `FormularioActividadModal.dom.test.tsx` y `HistorialActividad.dom.test.tsx`

**Checkpoint**: todas las historias funcionales completas

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Facade para Reportes, datos de prueba, E2E, regresión y puertas de calidad

- [X] T076 [P] Añadir a `$D/Application/Services/DesarrolloService.php` y a los `@method` de `$D/Presentation/Facades/DesarrolloFacade.php` los métodos `getAsistenciaDeColaborador`, `tieneAsistenciaRegistrada`, `getActividades` y `getAsistencias` con los contratos de contracts/actividades-puertos-y-facades.md (solo lectura; sin observaciones; horas de la acción)
- [X] T077 [P] Escribir `backend/tests/Feature/Desarrollo/ActividadFacadeTest.php`: forma de cada método, filtros (`anio`, `plan_id`, `tipo`, `estado`, `departamento_id`, `competencia_id`, fechas, `colaborador_id`, `actividad_id`), aislamiento por `tenant_id`, ausencia de `observaciones`, y que los Pendientes no se materializan en `getAsistencias`
- [X] T078 Crear `backend/database/seeders/ActividadesSeeder.php` y registrarlo en `backend/database/seeders/DatabaseSeeder.php` **después** de `PlanFormacionSeeder` (y de `PersonalSeeder`) siguiendo R13 de research.md: sin modificar `PlanFormacionSeeder`, añadir acciones propias marcadas con el prefijo `[Act]` al plan Ejecutando existente para cubrir los seis tipos y los cuatro estados, crear 6 actividades (Presencial en el plan Cerrado Finalizada; Codesarrollo En curso; Mentoring Finalizada con asistencia pendiente; OJT Programada; Virtual En curso; E-learning cancelada tras crearla), con registros Pendiente, Presente, Ausente y Justificado, calificaciones de 1 a 5 e historial coherente para `usuario@hrms.test`. Escribir `ActividadesSeederTest.php`
- [X] T079 Escribir `frontend/e2e/actividades.spec.ts` (Playwright, datos de `backend/.env.e2e`; pruebas seriales; documentar al inicio los datos sembrados como en `micro-learning.spec.ts`): el administrador crea una actividad desde una acción disponible, registra asistencia con «Marcar todos presentes», califica y guarda con confirmación, ve el historial, y consulta la pestaña Calificaciones; la colaboradora `usuario@hrms.test` ve sus actividades en «Próximas actividades» e «Historial» con asistencia y calificación y sin observaciones
- [X] T080 [P] Revisar responsive (320, 768 y 1440 px sin scroll horizontal), tema claro y oscuro, foco en modales, navegación por teclado del control de asistencia y `role="status"`/`aria-live` al guardar, contra `design/ActividadesAdmin.dc.html`, `ActividadesAsistencia.dc.html`, `ActividadesCalificaciones.dc.html` y `ActividadesColaborador.dc.html`; corregir las desviaciones o registrarlas en plan.md
- [X] T081 Ejecutar las puertas de calidad: en `backend/` `vendor/bin/pest` (completo, incluyendo Plan, micro-learning y DNF) y `vendor/bin/pint --test`; en `frontend/` `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `npx playwright test` (completo, porque `actividades`, `plan-formacion` y `micro-learning` comparten la base E2E). Comparar con los fallos preexistentes anotados en T001; no debe haber regresiones atribuibles a esta feature
- [X] T082 Recorrer los escenarios de `specs/017-actividades-formativas/quickstart.md`, comprobar los criterios SC-001 a SC-009 de spec.md y marcar como hecho en este archivo; actualizar `design/DESIGN.md` si la revisión de T080 cambió algo visual

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Fase 1)**: sin dependencias
- **Foundational (Fase 2)**: depende de Setup; **bloquea todas las historias**
- **US1 (P1)**: depende de Foundational. Es el MVP: crear y listar
- **US2 (P1)**: depende de Foundational y de US1 (necesita una actividad y su detalle para registrar)
- **US3 (P1)**: depende de Foundational; el backend del colaborador y la integración con el Plan pueden empezar en paralelo a US1/US2 (las pruebas siembran actividades y registros con *factories*); la verificación de extremo a extremo necesita US1 y US2
- **US4 (P2)**: depende de US1 (amplía la lista)
- **US5 (P2)**: depende de Foundational y de US2 (necesita calificaciones registradas para probar de extremo a extremo; el backend se prueba con datos sembrados)
- **US6 (P3)**: depende de US1 (editar) y US2 (historial)
- **Polish (Fase 9)**: depende de las historias que se entreguen; la Facade (T076) puede hacerse tras Foundational

### Within Each User Story

- Pruebas primero (deben fallar), luego dominio → repositorio → casos de uso → request/controlador/rutas → hooks → componentes → página
- Backend y frontend de una historia pueden avanzar en paralelo una vez fijado el contrato

### Parallel Opportunities

- Foundational: T004, T005, T006, T007 (migraciones y modelos) en paralelo tras T003; T008, T009, T011, T012 en paralelo; T014 tras T012–T013; T020–T023 en paralelo
- US1: T025–T027 (pruebas) y T032–T034 en paralelo
- US2: T039, T040 y T046, T047 en paralelo
- US3: T050, T051 y T055 en paralelo; US3 backend (T052–T054) en paralelo con US2 frontend
- US4 y US5 pueden ejecutarse en paralelo entre sí

---

## Implementation Strategy

### MVP First

1. Fases 1 y 2 (Setup y Foundational).
2. US1 (crear y listar) → **validar**: la actividad se crea y aparece en la lista.
3. US2 (registrar asistencia) y US3 (colaborador + integración con el Plan) → **P1 completo**: se puede demostrar el valor central.

### Incremental Delivery

1. + US4 (filtros y badge) → lista completa.
2. + US5 (calificaciones) → análisis.
3. + US6 (edición e historial) → trazabilidad.
4. Polish: Facade, seeder, E2E y puertas de calidad.

## Notes

- Cada tarea cita su archivo; los valores restringidos del modelo (longitudes, enumeraciones, rangos) están copiados de data-model.md y no se deciden al implementar
- Commit por tarea o por grupo lógico; detenerse en cada *checkpoint* para validar la historia por separado
- «Marcar todos presentes» es una operación **solo de cliente** (no hay endpoint): el servidor solo ve el `PUT /asistencia` resultante

## Notas de ejecución (2026-10-17)

- **Resultado**: 82/82 tareas. Backend: 1 535 pruebas Pest (186 nuevas), 111 rojos que son **exactamente los de la línea base** (0 nuevos). Frontend: Vitest 943 pasan y 54 rojos de la línea base (`estructura/unidades` y 1 en `CatalogoAdminPage`); ESLint limpio; `tsc -b` con los 18 errores de la línea base en `estructura/unidades` y ninguno de esta feature. E2E: `actividades` 5/5, `plan-formacion` 4/4 y `micro-learning` 3/4.
- **Ajustes frente al diseño** (todo registrado en plan.md, contratos y `design/DESIGN.md`): el código de error de negocio es `code` (como en el Plan) y las respuestas de éxito van en `{ data }`; endpoint nuevo `GET /api/actividades/opciones` para los filtros; `presentes` en el resumen del colaborador; el adaptador de asistencia solo devuelve acciones con «Presente» y el Plan aplica «Finalizada»; `PlanSeederTest` ahora excluye las acciones `[Act]` que añade `ActividadesSeeder`.
- **Fallos preexistentes que condicionan la validación local** (no son de esta feature): `IngresosSeeder` falla por el índice `posiciones_una_maxima_autoridad` (cambio sin confirmar en `2026_10_16_000001_complete_posiciones_table.php`), así que `migrate:fresh --seed` y los E2E estándar no arrancan; los E2E de esta feature se ejecutaron sembrando sin `IngresosSeeder` con una configuración temporal de Playwright que no se versionó. `dompdf/dompdf` no está instalado en `vendor/`, por lo que el E2E del certificado de micro-learning devuelve 503.
