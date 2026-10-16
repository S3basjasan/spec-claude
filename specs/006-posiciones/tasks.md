---

description: "Tareas de la feature 006-posiciones"
---

# Tasks: Posiciones organizacionales

**Input**: Documentos de diseño en `/specs/006-posiciones/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/posiciones-api.md, contracts/estructura-facade.md. Requiere 000-login a 005-unidad-organizacional ya implementadas (tabla `posiciones` mínima de 004 con `unidad_organizacional_id` de 005, `PosicionModel`, `EstadoPosicion`, `AuditoriaCambioModel`, `NombresDeUsuariosInterface` y su adaptador, `ArbolDeUnidades`, `NormalizadorDeTexto`, `Modal`, `ConfirmDialog`, `Pagination`).

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario (US1–US5 de spec.md). Se **reutiliza** el código de 004 (listado con filas desplegables, paginación) y de 005 (modales, árbol, auditoría) como patrón; se copian los patrones, no se importan módulos de otra funcionalidad del frontend.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Estructura/…` (Domain, Application, Infrastructure, Presentation)
- Frontend: `frontend/src/modules/estructura/posiciones/…` (reutiliza `frontend/src/shared/ui/…`)
- Pruebas backend: `backend/tests/{Unit,Feature}/Estructura/Posiciones/`
- Pruebas de componentes: `*.dom.test.tsx` junto al código (con `// @vitest-environment jsdom`); E2E en `frontend/e2e/posiciones.spec.ts`
- Regla de módulos: `posiciones` **no importa nada** de `frontend/src/modules/estructura/{competencias,comportamientos,preguntas,puestos,unidades}`; el rol llega solo por `frontend/src/modules/auth/login`.
- Valores: `estado` de posición ∈ `Ocupada` | `Vacante` | `Suprimida` (enum existente `EstadoPosicion`); `modalidad` ∈ `Nombramiento` | `Contrato` | `Encargo` | `Subrogacion` (sin tilde en la base; la interfaz muestra "Subrogación"); auditoría `entidad = 'posicion'`.
- Lecciones: escribir archivos con acentos con las herramientas Write/Edit (no heredocs con comillas complejas); en E2E localizar campos con `getByRole(…, { name, exact: true })`; los modales de `shared/ui/Modal` ya manejan foco y Escape; el servidor de desarrollo (`artisan serve`) es de un solo hilo: dar tiempo a las esperas largas.
- **Revisión 2026-10-16**: las tareas de las Fases 1 a 8 describen la primera versión (ya implementada, salvo las pruebas); la **Fase 9** las corrige con la jerarquía de unidades, la unidad raíz con una sola posición y los demás cambios del spec revisado. Donde una tarea anterior diga «una sola posición vigente sin supervisor» o «supervisor sin la propia posición ni sus subordinadas», rige además la Fase 9.
- Donde difieren, **prevalecen plan.md y spec.md** sobre el insumo `plan-posiciones.md` (capacidad `admin` en lugar de permisos, `version`, auditoría solo para el Administrador, filtro "Vigentes").

---

## Phase 1: Setup (línea base y diseño)

**Purpose**: Línea base verde y diseño creado antes de tocar el frontend

- [ ] T001 Registrar la línea base (sin cambios de archivos): ejecutar `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`, y `npm run lint`, `npx tsc -b` y `npx vitest run` en `frontend/`, y anotar los fallos existentes. Las pruebas obsoletas de 005 (tarea 005-T074, pendiente de aprobación) pueden fallar y no son de esta feature: no se tocan aquí
- [X] T002 [P] Crear `design/Posiciones.dc.html` (tablero nuevo, sigue el formato de `design/Puestos.dc.html` y `design/Unidades.dc.html`): listado con columnas código, puesto, unidad, supervisor (puesto y debajo el código; "Sin supervisor"), estado (badges Ocupada normal, Vacante ámbar, Suprimida neutra) y modalidad; búsqueda; filtros de estado ("Vigentes", Ocupada, Vacante, Suprimida y Todas solo Administrador) y de unidad en árbol con sangría; "Limpiar filtros"; paginador; filas desplegables con Ocupante, Supervisor, Supervisa a, Puesto, Unidad y Auditoría; botones Editar y Suprimir (deshabilitado con motivo); modales Nueva posición y Editar posición; diálogo de supresión; mensaje de confirmación. Registrarlo en `design/canvas.json` (después de `Unidades.dc.html`, interactivo, 1440 de ancho)
- [X] T003 [P] Modificar `design/DESIGN.md` **antes** de cualquier tarea de frontend (constitución III): badge **Vacante** (ámbar, con `--warning`; siempre con texto), filtro de unidad en árbol (patrón Listado, punto de filtros) y fila desplegable de posiciones (patrón Listado, punto 10, con secciones Ocupante, Supervisor, Supervisa a, Puesto, Unidad y Auditoría); §9 diferencias con el tablero (modalidades Híbrida/Presencial/Remota → Nombramiento/Contrato/Encargo/Subrogación; unidad propia de la posición; supervisor como posición; código POS-####; posiciones sobre puestos inactivos no permitidas)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Modelo de datos, dominio, lecturas y piezas de frontend que necesitan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend

- [X] T004 Crear `backend/database/migrations/2026_10_16_000001_complete_posiciones_table.php` (research D-02 y D-04): añade a `posiciones` `codigo` `string(20)` **anulable y único**; `user_id` FK a `users` (`restrictOnDelete`, anulable, con índice); `posicion_jefe_id` FK a `posiciones` (`restrictOnDelete`, anulable, con índice); `modalidad` `string(30)` con valor por defecto `Nombramiento` (para las filas ya sembradas); `version` entero por defecto 1; `created_by` y `updated_by` `unsignedBigInteger` anulables sin FK; y, con `DB::statement`, el índice único parcial `posiciones_una_maxima_autoridad` (`create unique index … on posiciones ((1)) where posicion_jefe_id is null and estado <> 'Suprimida'`). `down()` revierte
- [X] T005 [P] Crear en `backend/app/Modules/Estructura/Domain/ValueObjects/` el enum `Modalidad` (`Nombramiento`, `Contrato`, `Encargo`, `Subrogacion`; método `etiqueta()` con tilde) y los value objects `DatosPosicion` (`puestoId` nulo al editar, `unidadId`, `supervisorId` nulo en la máxima autoridad, `Modalidad`), `NodoDePosicion` (`id`, `codigo`, `supervisorId`, `estado`), `InstantaneaDePosicion` (`puestoId`, `unidadId`, `supervisorId`, `modalidad`, `estado`), `FiltroDePosiciones`, `PaginaDePosiciones` y `OpcionesDePosicion`; reutilizar `EstadoPosicion` y `CambioAuditado` sin modificar
- [X] T006 [P] Crear las excepciones finales (`RuntimeException`) en `Domain/Exceptions/`: `PosicionNoEncontrada` ("La posición no existe."), `PosicionModificada` ("La posición cambió desde que la abriste. Recarga para ver la versión actual."), `PosicionInvalida` (errores por campo, con constructores `puestoInexistente`, `puestoInactivo`, `unidadInexistente`, `unidadInactiva`, `supervisorInexistente`, `supervisorSuprimido`, `supervisorEsLaMisma`, `supervisorEsSubordinada`, `maximaAutoridadYaExiste`, `suprimida` ("La posición está suprimida y no se puede editar.")) y `PosicionNoSuprimible` (motivo `ocupada` o `subordinadas`, con la cantidad y el mensaje de contrato)
- [X] T007 Crear la entidad `Domain/Entities/Posicion.php` (`id`, `codigo`, `estado`, `modalidad`, referencias de puesto, unidad y supervisor con su nombre o código, `ocupanteId`, `subordinadasVigentes`, `version`, y para el detalle descripción del puesto, tipo de unidad, `subordinadas`, `creadoPor`, `creadoEn`, `modificadoPor`, `modificadoEn`)
- [X] T008 Crear el servicio de dominio puro `Domain/Services/ArbolDeSupervision.php` (sin Laravel) según research D-07: a partir de `NodoDePosicion` de las posiciones **no Suprimidas** devuelve `subordinadasDe(id)` (directas e indirectas), `subordinadasDirectas(id)`, `esSubordinada(candidata, de)` y detecta ciclos sin repetir nodos visitados
- [X] T009 [P] Crear `Domain/Services/ComparadorDeCambiosDePosicion.php` (puro) según research D-10: `comparar(?InstantaneaDePosicion $anterior, InstantaneaDePosicion $nuevo)` devuelve una fila por campo cambiado (`puesto_id` solo al crear, `unidad_organizacional_id`, `posicion_jefe_id`, `modalidad`, `estado`) con ids como texto; en la creación, una por dato con valor y anterior nulo
- [X] T010 [P] Crear `Domain/Repositories/PosicionRepositoryInterface.php`: `listar(FiltroDePosiciones): PaginaDePosiciones`, `obtener(int): ?Posicion`, `obtenerPorIds(array)`, `opciones(?int $excluir): OpcionesDePosicion`, `arbolDeSupervision(): array`, `crear(DatosPosicion, ?int $actor): Posicion`, `actualizar(int $id, int $versionEsperada, DatosPosicion, ?int $actor): Posicion`, `suprimir(int $id, ?int $actor): Posicion`, con las excepciones que lanzan
- [X] T011 [P] Modificar `Infrastructure/Persistence/Eloquent/Models/PosicionModel.php`: campos asignables (`puesto_id`, `unidad_organizacional_id`, `posicion_jefe_id`, `estado`, `modalidad`, `version`, `created_by`, `updated_by`; **no** `codigo` ni `user_id`), relaciones `puesto()`, `unidad()`, `supervisor()`, `subordinadas()` y helper `codigoDeId(int)` con `POS-%04d`; sin ocupante asignable desde este módulo
- [X] T012 Crear `Infrastructure/Persistence/Eloquent/Repositories/EloquentPosicionRepository.php` con las **lecturas** (research D-12): `listar` (orden numérico por código; `estado` Vigentes/Ocupada/Vacante/Suprimida/Todas; `unidad_organizacional_id` expandido con `ArbolDeUnidades`; `q` normalizada con `NormalizadorDeTexto` sobre `codigo`, `puestos.nombre_busqueda` y el puesto o código del supervisor; `subordinadas_vigentes` con una consulta agrupada; sin consultas por fila; paginación `page`/`per_page` por defecto 10, máximo 50), `obtener` (detalle con subordinadas sin las Suprimidas, ocupante y auditoría), `obtenerPorIds`, `opciones` (puestos activos, unidades en orden de árbol con `habilitada`, posiciones no Suprimidas candidatas con el nombre del ocupante y `hay_maxima_autoridad`; con `excluir`, sin la posición ni sus subordinadas) y `arbolDeSupervision`; registrar el binding en `EstructuraServiceProvider.php`
- [X] T013 [P] Crear `backend/database/factories/PosicionFactory.php` (posición Vacante válida: puesto activo y unidad activa creados con las fábricas existentes, `modalidad` `Contrato`, `codigo` asignado a `POS-%04d` del `id` en `afterCreating`, estados `ocupada(UserModel)`, `suprimida()`, `conSupervisor(PosicionModel)` y `maximaAutoridad()`) y añadir helpers en `backend/tests/Pest.php` (`posicionDe()`, `estructuraDeEjemplo()`)
- [X] T014 [P] Crear `backend/database/seeders/PosicionesSeeder.php` según research D-17 (idempotente por código) y llamarlo desde `DatabaseSeeder.php` después de `UnidadesSeeder`: completa las seis filas ya sembradas (código `POS-####` del id, modalidad, supervisor; las dos Ocupadas con `admin@hrms.test` y `usuario@hrms.test` en `user_id`) y añade posiciones Vacantes hasta reproducir la estructura de las 12 unidades (máxima autoridad en UO-001, gerentes que reportan a ella, áreas, departamentos y secciones bajo su jefatura); cumplir: Ocupada en UO-012; ninguna Ocupada ni Vacante en UO-005, UO-009 ni UO-008 (solo Suprimidas); UO-011 sin posiciones; las cuatro modalidades; una Vacante sin subordinadas, una Ocupada y una Vacante con subordinadas vigentes; una Suprimida sin ocupante ni subordinadas; Ocupadas y Vacantes solo con puestos y unidades activos
- [ ] T015 [P] Crear `backend/tests/Unit/Estructura/Posiciones/ArbolDeSupervisionTest.php` y `ComparadorDeCambiosDePosicionTest.php`: subordinadas directas e indirectas, candidata subordinada, datos con ciclo sin bucle infinito, las Suprimidas excluidas; creación con cuatro o cinco filas con anterior nulo, un campo cambiado, sin cambios sin filas, `puesto_id` solo al crear
- [ ] T016 [P] Crear `backend/tests/Feature/Estructura/Posiciones/RepositorioPosicionesTest.php` (lecturas y restricciones): `codigo` único; el índice parcial impide dos vigentes sin supervisor pero admite una Suprimida sin supervisor; orden numérico (`POS-0009` antes de `POS-0010`); filtro de unidad con su rama; búsqueda sin tildes por código, puesto y supervisor; volumen de 2000 posiciones paginado (SC-003); `restrictOnDelete` en las FK

### Frontend

- [X] T017 [P] Crear `frontend/src/modules/estructura/posiciones/api.ts`: tipos propios (`EstadoPosicion`, `Modalidad`, `FiltroEstado` = `Vigentes` | `Ocupada` | `Vacante` | `Suprimida` | `Todas`, `PosicionResumen` con `subordinadas_vigentes`, `PosicionDetalle` con `version`, `ocupante`, `subordinadas` y `auditoria` opcional, `OpcionesDePosicion`, `DatosPosicion`) y funciones `listarPosiciones(filtros)`, `obtenerPosicion`, `obtenerOpciones(excluir?)`, `crearPosicion`, `actualizarPosicion(id, datos, version)` y `suprimirPosicion(id)` sobre el cliente `http` compartido, más `listarUnidadesParaFiltro()` (lectura de `GET /api/unidades` para el filtro de unidad, sin importar nada del módulo de unidades)
- [X] T018 [P] Crear `frontend/src/modules/estructura/posiciones/text.ts` con todos los textos en español (títulos, columnas, estados Ocupada / Vacante / Suprimida, modalidades con tilde, filtros "Vigentes", "Limpiar filtros", "Ninguna posición coincide con los filtros.", "Sin supervisor", "Sin ocupante", "Sin supervisor (máxima autoridad)", "La unidad está inactiva", mensajes de validación "Selecciona el puesto." etc., avisos "Posición creada.", "Posición actualizada.", "Posición suprimida.", conflicto, motivos de Suprimir deshabilitado con cantidades)
- [ ] T019 [P] Crear `frontend/src/test/posicionesFixtures.ts` (`posicion()`, `detalle()`, `pagina()`, `opciones()` con una máxima autoridad, una Ocupada, una Vacante con subordinadas y una Suprimida)
- [X] T020 [P] Modificar `frontend/src/shared/ui/Badge.tsx`: añadir la variante `warning` (fondo `--soft`/ámbar con texto `--warning`, contraste ≥ 4.5:1 en ambos temas) usada por el estado Vacante; si falta, añadir `IconUsers` en `frontend/src/shared/ui/icons.tsx` para el ítem "Posiciones" de la navegación
- [ ] T021 [P] Crear `backend/tests/Support/InMemoryPosicionRepository.php`: doble en memoria que implementa `PosicionRepositoryInterface` (con las mismas excepciones que el repositorio real) para las pruebas unitarias de los casos de uso (T034, T043, T052)

**Checkpoint**: Base lista

---

## Phase 3: User Story 1 - Consultar las posiciones y su detalle (Priority: P1) 🎯 MVP

**Goal**: Listar, buscar, filtrar, paginar y desplegar el detalle según el rol.

**Independent Test**: Con posiciones Ocupadas, Vacantes y Suprimidas en varias unidades, entrar como Usuario y como Administrador, buscar, filtrar, paginar y desplegar.

### Tests for User Story 1

- [ ] T022 [P] [US1] Crear `backend/tests/Feature/Estructura/Posiciones/ConsultarPosicionesTest.php`: 401; el Usuario solo recibe Ocupadas y Vacantes y `Suprimida`/`Todas` se tratan como `Vigentes`; el Administrador con `Todas`; orden numérico, 10 por página y `meta`; búsqueda por código, puesto y supervisor sin tildes; filtro de unidad con dependientes; `subordinadas_vigentes`; detalle de una Suprimida para ambos roles; `auditoria` solo para el Administrador; ocupante y supervisor con ocupante; 404; un Usuario no administrador puede leer `GET /api/unidades` para el filtro de unidad (si no pudiera, replantear la fuente del filtro antes de implementar T029)
- [ ] T023 [P] [US1] Crear `backend/tests/Feature/Estructura/Posiciones/FacadePosicionesTest.php` (contrato de contracts/estructura-facade.md): `getPosicion` devuelve solo arreglos con los campos del contrato, también para una Suprimida, y sin auditoría; `getPosicionesPorIds` ignora ids inexistentes; `existePosicion` es verdadero también para Suprimidas; `posicionEstaVigente` es verdadero solo para Ocupada y Vacante; `getArbolDeSupervision` excluye las Suprimidas y respeta el orden de árbol
- [ ] T024 [P] [US1] Crear `frontend/src/modules/estructura/posiciones/pages/PosicionesPage.dom.test.tsx`: estados cargando, vacío, "Ninguna posición coincide con los filtros." y error con "Reintentar"; columnas y "Sin supervisor"; badges; el Usuario no ve Suprimida ni "Todas", ni "Nueva posición" ni acciones; el Administrador sí; búsqueda, estado, unidad y página en la URL (`Vigentes` no se escribe); el Usuario no envía `estado`; paginador; "Limpiar filtros"
- [ ] T025 [P] [US1] Crear `components/PosicionFila.dom.test.tsx` y `PosicionDetalle.dom.test.tsx`: una sola fila abierta (`aria-expanded`/`aria-controls`), detalle cargado al desplegar con Ocupante ("Sin ocupante"), Supervisor, Supervisa a, Puesto, Unidad (nombre, tipo, estado), auditoría solo si llega; sin Editar ni Suprimir para el Usuario ni en Suprimidas

### Implementation for User Story 1

- [X] T026 [US1] Crear `Application/DTO/{PosicionDTO,PosicionDetalleDTO,ListadoPosicionesDTO,FiltroPosicionesDTO}.php`, los casos de uso `ListarPosiciones` y `ObtenerPosicion` (el detalle incluye la auditoría y los nombres vía `NombresDeUsuariosInterface` solo cuando quien consulta es administrador; el nombre del ocupante siempre), `Presentation/Requests/ListarPosicionesRequest.php` (valida `q` ≤ 50, `estado`, `unidad_organizacional_id`, `page`, `per_page`; resuelve el estado efectivo según el rol), `Presentation/Resources/{PosicionResource,PosicionDetalleResource}.php`, `PosicionController@index` y `@show` y las rutas `GET /api/posiciones` y `GET /api/posiciones/{posicionId}` (`auth:sanctum`, `whereNumber`) en `Routes/api.php`
- [X] T027 [US1] Ampliar `EstructuraService` y `EstructuraFacade` con `getPosicion`, `getPosicionesPorIds`, `existePosicion`, `posicionEstaVigente` y `getArbolDeSupervision` según contracts/estructura-facade.md (solo arreglos, docblocks `@method`)
- [X] T028 [P] [US1] Crear los hooks `hooks/useListadoParams.ts` (q con retardo de 300 ms, estado, unidad y página en `useSearchParams`; `Vigentes` no se escribe) y `hooks/usePosiciones.ts` (`posicionesKey`, lista con `keepPreviousData`, detalle y opciones)
- [X] T029 (reemplazada en parte por T081) [P] [US1] Crear `components/{PosicionFila,PosicionDetalle,PosicionesList,FiltroDeUnidad,RequireAdmin}.tsx`: fila desplegable como `puestos/components/PuestoFila.tsx` (copiar el patrón), columna supervisor con puesto y código debajo, `Badge` por estado (Ocupada normal, Vacante `warning`, Suprimida neutra), `FiltroDeUnidad` como `SelectField` con las unidades de `GET /api/unidades` con sangría por `nivel_arbol`, `RequireAdmin` con el contrato `modules/auth/login`
- [X] T030 [US1] Crear `pages/PosicionesPage.tsx` según `design/Posiciones.dc.html` (encabezado, búsqueda, filtros de estado y de unidad, "Limpiar filtros", lista, `Pagination` de `shared/ui`, resumen, mensaje de confirmación `aria-live`, estados cargando / vacío / error) crear `frontend/src/modules/estructura/posiciones/nav.tsx` (exporta el ítem "Posiciones") y registrar en `frontend/src/App.tsx` la ruta `/posiciones` y ese ítem (`App.tsx` solo lo importa)
- [ ] T031 [US1] Crear el E2E de consulta en `frontend/e2e/posiciones.spec.ts`: el Usuario ve solo Ocupadas y Vacantes (10 por página), busca por código y por puesto sin tildes, filtra por unidad con su rama, despliega una fila y ve el detalle sin auditoría ni acciones, y consulta por su dirección una Suprimida; `estado=Todas` no le devuelve Suprimidas; el Administrador alterna Vigentes, Ocupada, Vacante, Suprimida y Todas y ve la auditoría; `DELETE` responde 404/405

**Checkpoint**: MVP de consulta

---

## Phase 4: User Story 2 - Crear una posición (Priority: P1)

**Goal**: Crear una posición Vacante con puesto y unidad activos, supervisor y modalidad.

### Tests for User Story 2

- [ ] T032 [P] [US2] Crear `backend/tests/Feature/Estructura/Posiciones/CrearPosicionTest.php`: 201 `Vacante`, `user_id` nulo, código `POS-####` del id, `version` 1; puesto o unidad inactivos o inexistentes, supervisor Suprimido o inexistente, modalidad inválida → 422 por campo; `estado`, `user_id`, `codigo` en el body → 422; sin supervisor con otra máxima autoridad → 422 y la primera máxima autoridad sí se crea; misma unidad y puesto repetidos aceptados; unidad raíz y del último nivel válidas; 401/403
- [ ] T033 [P] [US2] Crear `OpcionesDePosicionTest.php`: puestos activos, unidades con `habilitada`, posiciones no Suprimidas con ocupante, `hay_maxima_autoridad`; 401/403
- [ ] T034 [P] [US2] Crear `backend/tests/Unit/Estructura/Posiciones/CrearPosicionTest.php` para `CrearPosicion` y `ListarOpcionesDePosicion` con un doble en memoria del repositorio
- [ ] T035 [P] [US2] Crear `frontend/src/modules/estructura/posiciones/validation.test.ts` y `components/PosicionModal.crear.dom.test.tsx`: validación de los cuatro campos, "Sin supervisor (máxima autoridad)" deshabilitada si `hay_maxima_autoridad`, unidades inactivas deshabilitadas con "La unidad está inactiva", errores 422 junto al campo, botón bloqueado al guardar, el Usuario no ve el botón

### Implementation for User Story 2

- [X] T036 [US2] Crear `Presentation/Requests/GuardarPosicionRequest.php` (`puesto_id` obligatorio solo al crear y prohibido al editar; `unidad_organizacional_id` y `modalidad` obligatorios; `posicion_jefe_id` nulo; `version` obligatoria en `PUT`; `codigo`, `user_id` y `estado` prohibidos; mensajes en español) y `OpcionesDePosicionRequest.php` (`excluir`)
- [X] T037 (reemplazada en parte por T076) [US2] Añadir a `EloquentPosicionRepository` la escritura `crear` (research D-05, D-06 y D-09): transacción cuyo primer enunciado inserta la fila `Vacante` con `codigo` nulo y `version` 1; con el candado tomado valida puesto existente y activo, unidad existente y activa, supervisor existente y no Suprimido y máxima autoridad única (violación del índice → `PosicionInvalida`); asigna `POS-%04d` y registra el historial inicial con `ComparadorDeCambiosDePosicion`
- [X] T038 [US2] Crear los casos de uso `CrearPosicion` y `ListarOpcionesDePosicion`, `OpcionesDePosicionDTO`, `OpcionesDePosicionResource`, `PosicionController@store` (201; `PosicionInvalida` → 422 con `errors` por campo) y `@opciones` y las rutas `POST /api/posiciones` y `GET /api/posiciones/opciones` (esta **antes** de `{posicionId}`) con `auth:sanctum` + `admin`
- [X] T039 [P] [US2] Crear `validation.ts` (esquema Zod de los cuatro campos con los mensajes de T018) y `hooks/useGuardarPosicion.ts` (crear; mutación sin reintentos; invalida `["posiciones"]` sin esperar la recarga)
- [X] T040 (reemplazada en parte por T083) [US2] Crear `components/PosicionModal.tsx` (modo crear; usa `shared/ui/Modal`; selectores de puesto, unidad con sangría, supervisor y modalidad; opciones de `GET /api/posiciones/opciones` siempre frescas) e integrarlo en `PosicionesPage.tsx` ("Nueva posición" solo Administrador, mensaje "Posición creada.")
- [ ] T041 [US2] Añadir al E2E la creación: el Administrador abre "Nueva posición", ve errores por campo, ve las unidades inactivas deshabilitadas y "Sin supervisor" deshabilitada, crea una posición bajo un supervisor y la ve Vacante con su código; el Usuario recibe 403 en `POST` y `opciones`

---

## Phase 5: User Story 3 - Editar unidad, supervisor y modalidad (Priority: P2)

### Tests for User Story 3

- [ ] T042 [P] [US3] Crear `ActualizarPosicionTest.php` y `ReglasDeSupervisionTest.php` en `backend/tests/Feature/Estructura/Posiciones/`: 200 con cambios (`version` +1, `updated_by`, conserva código, puesto, ocupante y estado); sin cambios no escribe; supervisor propia, subordinada directa o indirecta, Suprimida → 422; dejar sin supervisor con otra máxima autoridad → 422; la máxima autoridad se puede conservar y reasignar; unidad inactiva → 422; Ocupada editable; Suprimida → 422; `puesto_id`, `codigo`, `user_id`, `estado` en el body → 422; 409 por versión; dos cambios que formarían un ciclo, el segundo se rechaza; 401/403
- [ ] T043 [P] [US3] Crear `backend/tests/Unit/Estructura/Posiciones/ActualizarPosicionTest.php` para `ActualizarPosicion` con el doble en memoria
- [ ] T044 [P] [US3] Crear `components/PosicionModal.editar.dom.test.tsx`: puesto, código, ocupante, estado y auditoría en solo lectura, opciones con `excluir` = id, supervisor sin la propia posición ni sus subordinadas, envío con `version`, conflicto 409 con "Recargar", 404 cierra el modal con aviso, guardar sin cambios sin error, versión congelada al abrir

### Implementation for User Story 3

- [X] T045 (reemplazada en parte por T077) [US3] Añadir a `EloquentPosicionRepository` la escritura `actualizar` (research D-06): sin cambios reales no escribe; el primer enunciado sube `version` con `WHERE version = ? AND estado <> 'Suprimida'` (0 filas → `PosicionModificada` o `PosicionInvalida::suprimida`); valida unidad activa solo si cambia, supervisor (existente, no Suprimido, ni ella ni subordinada según `ArbolDeSupervision` vigente) y máxima autoridad; registra solo los campos cambiados
- [X] T046 [US3] Crear el caso de uso `ActualizarPosicion` y `PosicionController@update` (200; `PosicionModificada` → 409; `PosicionNoEncontrada` → 404; `PosicionInvalida` → 422) y la ruta `PUT /api/posiciones/{posicionId}` con `admin`
- [X] T047 [P] [US3] Extender `hooks/useGuardarPosicion.ts` con la actualización (envía `version`; invalida también tras 404 o 409) y crear la lectura fresca del detalle para editar
- [X] T048 [US3] Añadir el modo editar a `PosicionModal.tsx` e integrar el botón Editar del detalle (solo Administrador y solo posiciones no Suprimidas) en `PosicionDetalle.tsx` y `PosicionesPage.tsx`; mensaje "Posición actualizada."
- [ ] T049 [US3] Añadir al E2E la edición: cambiar unidad y modalidad, mover bajo otro supervisor (sus subordinadas siguen), selector de supervisor sin la propia ni sus subordinadas, editar una Ocupada, guardar sin cambios sin error, conflicto simulado por la API (restablecer el dato al terminar); 403 al Usuario en `PUT`

---

## Phase 6: User Story 4 - Suprimir una posición (Priority: P2)

### Tests for User Story 4

- [ ] T050 [P] [US4] Crear `SuprimirPosicionTest.php`: Vacante sin subordinadas → 200 `Suprimida`; Ocupada → 422 `ocupada`; con subordinadas vigentes → 422 `subordinadas` con la cantidad; subordinadas Suprimidas no lo impiden; ya Suprimida → 200 sin cambios ni historial; no cambia `version` ni `updated_by`; 404; 403; una Suprimida sigue consultable con puesto, unidad y supervisor aunque luego se desactive su puesto o su unidad
- [ ] T051 [P] [US4] Crear `SupresionAtomicaTest.php` y `ReglasConPuestosYUnidadesTest.php`: un fallo al escribir el historial revierte la supresión; no se puede desactivar un puesto (004) ni una unidad (005, también en cascada) con posiciones Ocupadas o Vacantes, y sí cuando solo quedan Suprimidas; crear una posición bajo una unidad o puesto ya inactivos se rechaza; crear una subordinada bajo una posición ya Suprimida se rechaza; el orden de llegada en ambos sentidos entre desactivar el puesto o la unidad y crear la posición (la desactivación llegó primero → la creación se rechaza; la posición llegó primero → la desactivación se rechaza), simulado con transacciones consecutivas (SQLite serializa las escrituras, así que se prueba el orden y no la concurrencia real); añadir `SinEliminarPosicionesTest.php` (6 rutas bajo `api/posiciones`, `DELETE` 404/405, sin ruta de reactivar ni de ocupante)
- [ ] T052 [P] [US4] Crear `backend/tests/Unit/Estructura/Posiciones/SuprimirPosicionTest.php` y `frontend/.../components/SuprimirDialog.dom.test.tsx`: Suprimir deshabilitado con descripción accesible del motivo (Ocupada o con N subordinadas), confirmación que nombra la posición, Cancelar y Escape no cambian nada, 422 en el diálogo, éxito con aviso, el Usuario no ve el botón

### Implementation for User Story 4

- [X] T053 [US4] Añadir a `EloquentPosicionRepository` la escritura `suprimir` (research D-08): transacción con una sola sentencia condicional (`estado = 'Vacante'` y `NOT EXISTS` subordinadas con `estado <> 'Suprimida'`); si no afecta filas distingue ya Suprimida (200), Ocupada o subordinadas (con la cantidad) y lanza `PosicionNoSuprimible`; registra el estado en el historial; no toca `version`, `updated_by` ni `updated_at`
- [X] T054 [US4] Crear el caso de uso `SuprimirPosicion` y `PosicionController@suprimir` (200; `PosicionNoSuprimible` → 422 con `motivo` y `subordinadas_vigentes`) y la ruta `POST /api/posiciones/{posicionId}/suprimir` con `admin`
- [X] T055 [P] [US4] Crear `hooks/useSuprimirPosicion.ts` (sin reintentos; invalida `["posiciones"]` también tras 404 o 422) y `components/SuprimirDialog.tsx` (usa `ConfirmDialog` de `shared/ui`; muestra el mensaje del servidor si responde 422)
- [X] T056 [US4] Añadir a `PosicionDetalle.tsx` y `PosicionesPage.tsx` el botón Suprimir (solo Administrador y no Suprimidas; deshabilitado con descripción accesible si el estado es Ocupada o `subordinadas_vigentes` > 0) y el aviso "Posición suprimida."
- [ ] T057 [US4] Añadir al E2E la supresión: Suprimir deshabilitado en una Ocupada y en una Vacante con subordinadas, suprimir una Vacante hoja con confirmación (sale del listado por defecto y aparece con el filtro Suprimida), la Suprimida sigue consultable sin Editar ni Suprimir, y 403 al Usuario en `POST …/suprimir`

---

## Phase 7: User Story 5 - Trazabilidad de los cambios (Priority: P3)

- [ ] T058 [P] [US5] Crear `AuditoriaPosicionesTest.php`: ciclo con dos administradores (`created_by` del alta; `updated_by` y `version` tras una edición sin alterar `created_by`); al crear, una fila por dato inicial con valor (`puesto_id`, `unidad_organizacional_id`, `posicion_jefe_id` si tiene, `modalidad`, `estado`) con anterior nulo; al editar, una fila por campo cambiado con los ids anterior y nuevo; cambiar el supervisor de una posición con subordinadas registra solo esa posición; suprimir escribe `estado` (`Vacante` → `Suprimida`) con el usuario y sin tocar `updated_by`; editar sin cambios no añade filas; los registros con `entidad = 'posicion'` no se pueden modificar ni borrar y no se mezclan con `puesto` ni `unidad`; `user_id` de la fila es quien hizo el cambio, no el ocupante
- [ ] T059 [P] [US5] Crear `EscrituraAtomicaTest.php`: si falla la escritura del historial (excepción simulada en `AuditoriaCambioModel::creating`, limpiada con `Event::forget`) se revierte el cambio de la posición en `crear` y `actualizar`; una validación que falla después de subir `version` revierte también ese aumento
- [X] T060 [US5] Verificar que `crear`, `actualizar` y `suprimir` escriben el historial en la misma transacción con `ComparadorDeCambiosDePosicion` y que la auditoría solo se devuelve al Administrador (`PosicionDetalleResource`); completar lo que falte

---

## Phase 8: Polish & Cross-Cutting Concerns

- [ ] T061 [P] Auditar contraste y foco del listado, los badges (incluido el ámbar de Vacante), la fila desplegada, los modales y el diálogo en ambos temas (pares ≥ 4.5:1, foco visible, orden de tabulación, el foco vuelve al botón que abrió el modal o el diálogo) y reflejarlo en `frontend/e2e/posiciones.spec.ts`
- [ ] T062 [P] Verificar sin scroll horizontal de la página a 320 y 1440 px el listado (la tabla se desplaza dentro de su contenedor), la fila desplegada y los modales, en el E2E
- [ ] T063 [P] Revisar seguridad: el rol se verifica en el backend en el listado, las escrituras y `opciones`; un Usuario nunca recibe Suprimidas en el listado ni la auditoría; `estado`, `user_id`, `codigo` y `puesto_id` (al editar) del cuerpo se rechazan; la estructura (ciclos, máxima autoridad, supervisor Suprimido) no puede quedar inválida con peticiones simultáneas; errores en español sin detalles internos; este módulo nunca escribe `user_id` ni el paso Ocupada ⇄ Vacante; ningún módulo toca la tabla `posiciones`
- [X] T064 [P] Revisar que `design/Posiciones.dc.html` y `design/DESIGN.md` reflejan lo implementado
- [X] T065 Reiniciar la base de desarrollo con `php artisan migrate:fresh --seed` en `backend/` y comprobar posiciones con `POS-####`, una sola máxima autoridad vigente, una Ocupada en UO-012, ninguna Ocupada ni Vacante en UO-005, UO-009 ni UO-008, una Suprimida sin ocupante ni subordinadas y las cuatro modalidades
- [ ] T066 Ejecutar las puertas de calidad del backend: `vendor/bin/pint --test` y `vendor/bin/pest` en `backend/` : deben pasar todas las pruebas de `Posiciones/` y de 004, y las de 005 que sembraban `posiciones` (ajustarlas solo si dependen de columnas que cambian); los únicos fallos admitidos son los ya registrados en T001 por las pruebas obsoletas de 005
- [ ] T067 Ejecutar las puertas de calidad del frontend: `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `./node_modules/.bin/playwright test` en `frontend/`: deben pasar `posiciones.spec.ts`, las pruebas de `posiciones/` y las demás de 004; los únicos fallos admitidos son los registrados en T001 por 005 (`unidades.spec.ts` y pruebas obsoletas de unidades)
- [ ] T068 Ejecutar la validación de `specs/006-posiciones/quickstart.md` de punta a punta

---

---

## Phase 9: Revisión 2026-10-16 (jerarquía de unidades y unidad raíz)

**Purpose**: Ajustes al código ya implementado (Fases 1 a 8) por la actualización del spec y del insumo: regla de jerarquía de unidades, unidad raíz con una sola posición («sin supervisor ⇔ raíz»), unidades mínimas al editar, columna Ocupante, búsqueda por unidad y ocupante, contador, selectores agrupados y reubicación de unidades (FR-038). Razonado en [research.md](research.md) (D-19 a D-26). Las tareas de esta fase van **antes** de las puertas de calidad T066 a T068, que deben ejecutarse de nuevo al terminar.

### Diseño (primero, constitución III)

- [ ] T069 [P] (el usuario reemplazó el tablero por el suyo, que ya trae columnas, búsqueda y badges; no se sobrescribe: faltan en él filtros, filas desplegables y modales, documentados en DESIGN.md) Actualizar `design/Posiciones.dc.html`: columnas **Reporta a** (código y, debajo, puesto; «—» y «Máxima autoridad») y **Ocupante** («Sin ocupante» atenuado y en cursiva), etiqueta «Buscar» con marcador «Código, puesto, unidad u ocupante», contador «N posiciones» bajo la búsqueda, badge Suprimida de peligro, detalle con «Máxima autoridad» y «Supervisa a» con ocupante, modal con unidad raíz deshabilitada («La Gerencia General ya tiene su posición (máxima autoridad)»), supervisor deshabilitado hasta elegir la unidad y agrupado por unidad, fijo en «Sin supervisor (máxima autoridad)» en la raíz y, al editar, unidades deshabilitadas («Tiene subordinadas en unidades superiores a esta»)
- [X] T070 [P] Actualizar `design/DESIGN.md`: §4 Badge con la variante de peligro para Suprimida; §5 Listado, punto 11 (columnas, contador, selector de supervisor agrupado); §9 diferencias con el tablero

### Backend

- [X] T071 [P] [US2] (también lo usan US1 y US3) Crear `backend/app/Modules/Estructura/Domain/Services/JerarquiaDeUnidades.php` (puro, sobre `ArbolDeUnidades`): `raiz()`, `superioresDe(int $unidad)` (de la padre a la raíz) y `esIgualOSuperior(int $candidata, int $unidad)`
- [X] T072 [P] [US1] Búsqueda por ocupante sin leer `users` (máximo 200 ids, ordenados por nombre, D-23): añadir `buscarIdsDeUsuariosPorNombre(string $texto)` a `SharedFacade` y su servicio en `backend/app/Modules/Shared/`, `idsPorNombre(string $texto): array` al puerto `Application/Ports/NombresDeUsuariosInterface.php` y su implementación en `Infrastructure/Adapters/SharedNombresDeUsuarios.php`
- [X] T073 [US2] Ampliar `Domain/Exceptions/PosicionInvalida.php` con `raizOcupada()` («La Gerencia General ya tiene su posición (máxima autoridad).»), `supervisorObligatorio()` («Selecciona el supervisor.»), `supervisorEnRaiz()` (la máxima autoridad no tiene supervisor), `supervisorFueraDeJerarquia()` («El supervisor debe pertenecer a la misma unidad o a una unidad superior.»), `unidadPorDebajoDeSubordinadas(int $cantidad)` y `maximaAutoridadSoloModalidad()`; `maximaAutoridadYaExiste()` pasa a decir lo mismo que `raizOcupada()`
- [X] T074 [US1] En `EloquentPosicionRepository`: el listado devuelve el ocupante de cada fila (`user_id`) y filtra `q` también por nombre de la unidad y por ocupante (`idsPorNombre`); `obtener` añade el ocupante de cada subordinada; `PosicionDTO`/`PosicionDetalleDTO` y los recursos exponen `ocupante` (nombres en una sola llamada por página, D-24)
- [X] T075 [US2] En `EloquentPosicionRepository::opciones`: las unidades añaden `unidad_padre_id`, `superiores`, `habilitada` (falsa si está inactiva, o es la raíz y ya tiene una posición vigente distinta de la excluida) y `motivo_deshabilitada` (`inactiva` | `raiz_ocupada`); las posiciones candidatas añaden `unidad_organizacional_id` y `unidad`; con `excluir` se añade `unidades_minimas` (D-22); actualizar `OpcionesDePosicion`, `OpcionesDePosicionDTO` y `OpcionesDePosicionResource`
- [X] T076 [US2] (misma regla en US3, T077) En `EloquentPosicionRepository::crear`, después del primer enunciado de escritura: si la unidad es la raíz, el supervisor debe ser nulo y no puede haber otra posición vigente en ella; si no, el supervisor es obligatorio y su unidad debe ser la misma o una superior (`JerarquiaDeUnidades`); mantener la captura de la violación del índice parcial
- [X] T077 [US3] En `EloquentPosicionRepository::actualizar`: la máxima autoridad (sin supervisor) solo admite cambiar la modalidad; la unidad nueva debe ser la misma o superior a la de cada subordinada directa vigente (422 con la cantidad que lo impide); la unidad y el supervisor nuevos cumplen las reglas de crear (raíz, jerarquía); mover una posición a la raíz ocupada se rechaza
- [X] T078 [US3] Reubicación en el módulo de unidades (FR-038): en `EloquentUnidadRepository::actualizar` rechazar con `UnidadInvalida` (campo `unidad_padre_id`, mensaje con la cantidad de posiciones afectadas) si, en el árbol resultante, alguna posición vigente de la rama movida o cuyo supervisor esté en ella deja de tener a su supervisora en la misma unidad o en una superior; añadir la fábrica en `Domain/Exceptions/UnidadInvalida.php`
- [X] T079 [P] [US1] Ajustar `backend/database/seeders/PosicionesSeeder.php`: añadir una Vacante de «Desarrollador de software» en Desarrollo Web (UO-012) que reporta a la Ocupada de esa misma unidad (supervisión dentro de una misma unidad); comprobar que todas las demás cumplen la jerarquía

### Frontend

- [X] T080 [P] [US1] `frontend/src/shared/ui/Badge.tsx`: añadir la variante `danger` (`bg-dangersoft text-danger`) y usarla para Suprimida; actualizar `posiciones/api.ts` (`ocupante` en el resumen, `ocupante` en las subordinadas, campos nuevos de las opciones) y `posiciones/text.ts` (etiqueta «Buscar», marcador «Código, puesto, unidad u ocupante», «Reporta a», «Ocupante», «Máxima autoridad», contador «N posiciones», mensajes del selector de supervisor)
- [X] T081 [US1] `PosicionFila.tsx`, `PosicionesList.tsx` y `PosicionesPage.tsx`: columnas Reporta a y Ocupante, Suprimida con la variante de peligro, contador `aria-live` bajo la búsqueda
- [X] T082 [US1] `PosicionDetalle.tsx`: supervisor con «Máxima autoridad» si no tiene y «Supervisa a» con código, puesto, ocupante y estado
- [X] T083 [US2] `PosicionModal.tsx` y `validation.ts`: el supervisor se habilita al elegir la unidad, ofrece solo candidatos de esa unidad o de sus superiores agrupados con `optgroup` desde la elegida hasta la raíz («código · puesto · ocupante»), queda fijo en «Sin supervisor (máxima autoridad)» si la unidad es la raíz, se vacía si deja de ser válido, muestra «No hay posiciones en esta unidad ni en sus unidades superiores» si no hay candidatos y deshabilita las unidades según `habilitada`/`motivo_deshabilitada`
- [X] T084 [US3] `PosicionModal.tsx` (edición): deshabilitar las unidades que no sean iguales ni superiores a todas las `unidades_minimas` («Tiene subordinadas en unidades superiores a esta») y mostrar la unidad y el supervisor en solo lectura en la máxima autoridad

### Pruebas (pendientes, como el resto)

- [ ] T085 [P] [US2] Crear `backend/tests/Unit/Estructura/Posiciones/JerarquiaDeUnidadesTest.php` y `backend/tests/Feature/Estructura/Posiciones/JerarquiaDePosicionesTest.php`: supervisor en la misma unidad, en una superior no inmediata y en la raíz se acepta; en una unidad dependiente o en otra rama se rechaza; la raíz solo admite una posición vigente y sin supervisor, y queda libre al suprimirla; una posición de otra unidad sin supervisor se rechaza; la unidad nueva por debajo de la de una subordinada se rechaza con la cantidad; la máxima autoridad solo cambia de modalidad
- [ ] T086 [P] [US1] Crear `backend/tests/Feature/Estructura/Posiciones/ListadoConOcupanteTest.php` (la prueba del método de `Shared` está en T090) y ampliar `OpcionesDePosicionTest`: `ocupante` en el listado y en las subordinadas, búsqueda por unidad y por nombre del ocupante, `superiores`, `habilitada`/`motivo_deshabilitada`, `unidades_minimas` y las posiciones candidatas con su unidad
- [ ] T087 [P] [US3] Crear `backend/tests/Feature/Estructura/Unidades/ReubicarConPosicionesTest.php`: reubicar una unidad se rechaza si deja una posición reportando a otra de una unidad inferior o de otra rama, y se acepta si no afecta a ninguna
- [ ] T088 [P] [US2] Ampliar `frontend/.../components/PosicionModal.crear.dom.test.tsx`, `PosicionModal.editar.dom.test.tsx` y `PosicionesPage.dom.test.tsx`: supervisor deshabilitado hasta elegir la unidad, grupos por unidad, fijo en la raíz, vaciado al cambiar la unidad, unidades deshabilitadas, columnas Reporta a y Ocupante, contador y badge Suprimida
- [ ] T089 [US2] Ampliar `frontend/e2e/posiciones.spec.ts`: crear la máxima autoridad (raíz fija y sin supervisor), crear una posición cuyo supervisor es de una unidad superior, comprobar que la raíz ya no se puede elegir, y editar una posición con subordinadas viendo la unidad deshabilitada
- [ ] T090 [P] [US1] Crear `backend/tests/Feature/Shared/BuscarUsuariosPorNombreTest.php`: `SharedFacade::buscarIdsDeUsuariosPorNombre` encuentra sin distinguir mayúsculas ni tildes, devuelve vacío sin coincidencias y no pasa de 200 ids (ordenados por nombre); y `idsPorNombre` del puerto de `Estructura` devuelve lo mismo

---

## Dependencies & Execution Order

- **Fase 1** primero; T002 y T003 antes de cualquier tarea de frontend (constitución III).
- **Fase 2** bloquea todas las historias. Dentro de ella: T004 → T011/T012/T014; T005–T007 → T008–T012; T012 → T013–T016; T010 → T021 (el doble en memoria lo usan T034, T043 y T052).
- **Deuda de 005**: la feature no se da por completa del todo hasta resolver 005-T074 (pruebas obsoletas de unidades, requiere aprobación); hasta entonces T066 y T067 admiten solo los fallos registrados en T001.
- **Fase 9** (revisión 2026-10-16) va después de las Fases 1 a 8 ya implementadas y antes de T066 a T068: T069 y T070 antes de T080 a T084; T071 antes de T076, T077 y T078; T073 antes de T076 y T077; T072 antes de T074; T075 antes de T083 y T084; T078 afecta a 005, así que sus pruebas (T087) viven en `Unidades/`.
- **US1 y US2 (P1)** dependen solo de la fase 2 (US2 usa el listado de US1 para verse en pantalla); US3 y US4 dependen de US1 y US2; US5 de las anteriores.
- Dentro de cada historia: pruebas → backend → frontend → E2E. Tareas que tocan el mismo archivo (`EloquentPosicionRepository.php`, `PosicionController.php`, `Routes/api.php`, `PosicionesPage.tsx`, `PosicionModal.tsx`, `posiciones.spec.ts`) van en secuencia.

### Parallel example: User Story 2

```text
T032, T033, T034, T035 (pruebas, archivos distintos)  →  T036 → T037 → T038  ‖  T039 → T040 → T041
```

## Implementation Strategy

1. **MVP**: Fases 1–2, US1 (consulta) y US2 (crear).
2. **Entrega incremental**: US3 (editar), US4 (suprimir), US5 (auditoría) y pulido.
3. Cada historia termina con su E2E antes de pasar a la siguiente.

### Notas

- Ocupar y liberar (usuario y paso Ocupada ⇄ Vacante) no se implementa: lo hará el módulo de gestión de personal; esta feature solo deja el contrato (contracts/estructura-facade.md) y respeta la coherencia estado y ocupante al leer.
- Los puntos por confirmar de spec.md (quién ocupa, una sola máxima autoridad, puesto fijo) se resuelven con el valor por defecto del documento; si negocio los cambia, hay que revisar US2, US3 y el índice parcial de T004.

## Estado de la implementación (2026-10-15)

Implementado **sin pruebas**, por indicación expresa del usuario ("Quiero implementar sin pruebas"). Quedan marcadas `[X]` las tareas de código, diseño y sembrado; quedan pendientes todas las tareas de prueba (Pest, Vitest y E2E), las auditorías manuales (T061–T063), la validación del quickstart (T068) y las puertas de calidad que ejecutan pruebas (T066, T067).

Comprobaciones estáticas y de humo hechas: `pint` sin cambios pendientes, `tsc -b` y `eslint` sin errores en el código nuevo, `migrate:fresh --seed` correcto (10 posiciones con una sola máxima autoridad) y una petición de humo por el kernel a los seis endpoints.

Puntos a tener presente:
- El índice parcial `posiciones_una_maxima_autoridad` rechaza dos posiciones vigentes sin supervisor. Las pruebas existentes de 004 y 005 que crean varias posiciones Ocupadas o Vacantes sin `posicion_jefe_id` fallarán hasta que se ajusten (T066); `PuestosSeeder` ya cuelga las vigentes de la primera.
- `design/Posiciones.dc.html`, `DESIGN.md` y `canvas.json` están actualizados (token `--warningsoft`, Badge de advertencia, patrón de listado de estructura de reporte).
- `PosicionFactory` existe, pero no se creó `InMemoryPosicionRepository` (T021) ni `posicionesFixtures.ts` (T019) por ser infraestructura de pruebas.

### Estado de la Fase 9 (2026-10-16)

Implementada **sin pruebas**, por indicación expresa del usuario ("No quiero que hagas nada de pruebas"). Hechas: T070 a T084 y la reimplementación de T029, T037, T040 y T045 (reabiertas). Pendientes: T069 (el tablero `design/Posiciones.dc.html` es ahora el del usuario y no se sobrescribió; le faltan filtros, filas desplegables y modales, que están descritos en `DESIGN.md`), las pruebas T085 a T090 y las puertas de calidad T066 a T068.

Comprobaciones hechas: `pint`, `tsc -b` y `eslint` limpios en el código nuevo, `migrate:fresh --seed` correcto y peticiones de humo a la API (raíz ocupada, supervisor obligatorio, supervisor fuera de jerarquía, máxima autoridad solo modalidad, búsqueda por unidad y ocupante, y reubicación de una unidad rechazada con 422). Se añadió `idsPorNombre` al doble `tests/Unit/Estructura/Unidades/NombresDeUsuariosEnMemoria.php` solo para que siga cumpliendo el puerto.
