---

description: "Tareas de la feature 003-diccionario-preguntas"
---

# Tasks: Diccionario de preguntas

**Input**: Documentos de diseño en `/specs/003-diccionario-preguntas/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/preguntas-api.md, contracts/estructura-facade.md. Requiere 000-login, 001-diccionario-competencias y 002-diccionario-comportamientos ya implementadas (competencias con `estado`, `Combobox` y `Alert` neutro en `shared/ui`, helpers de prueba y patrones de 002).

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario para implementarlas y probarlas de forma independiente. La feature es **nueva** salvo los archivos compartidos indicados como "modificar".

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1–US5)
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Estructura/…` (Domain, Application, Infrastructure, Presentation)
- Frontend: `frontend/src/modules/estructura/preguntas/…` (reutiliza `frontend/src/shared/ui/…`)
- Pruebas backend: `backend/tests/{Unit,Feature}/Estructura/Preguntas/`
- Pruebas de componentes: `*.dom.test.tsx` junto al código (con `// @vitest-environment jsdom`); E2E en `frontend/e2e/preguntas.spec.ts`
- Regla de módulos: `preguntas` **no importa nada** de `frontend/src/modules/estructura/competencias` ni de `…/comportamientos`; el rol llega solo por `frontend/src/modules/auth/login` (`index.ts`). Copiar (no importar) los patrones de 002.
- Valores heredados de 001: `tipo` ∈ `Cardinal` | `Especifica`; `estado` ∈ `Activo` | `Inactivo` (siempre los de la competencia)
- Reutilizar de 002 sin modificarlos: `CompetenciaAsociada`, `CompetenciaNoDisponible`, `RegistroModificado`, `CompetenciaDisponibleDTO`, `CompetenciaDisponibleResource`, `Combobox`, `Alert` (tono neutro)
- Lecciones de 002: en E2E localizar los campos con `getByRole('textbox', { name, exact: true })` (el `getByLabel` también casa con el `aria-label` de otros botones); escribir los archivos con la herramienta Write (los heredocs de Bash fallan con acentos)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Línea base verde y diseño actualizado antes de tocar el frontend

- [X] T001 Verificar la línea base: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run` y `npx playwright test` en `frontend/` deben pasar antes de empezar (sin cambios de archivos)
- [X] T002 [P] Modificar `design/DESIGN.md` **antes** de cualquier tarea de frontend (constitución III): §5 patrón Listado: la fila desplegable puede mostrar una **lista numerada** (`<ol>`) de textos (p. ej. las cuatro preguntas de una competencia), con el aviso neutro "La competencia asociada está inactiva" cuando corresponda; el Formulario puede usar **campos de texto fijos numerados** con contador `n/máximo` cuando la cantidad no es variable (sin Agregar/Quitar); §9 diferencias con los mockups (columnas Código y Estado, ausencia de activar/desactivar/eliminar en este recurso, campos numerados fijos). No repetir `Combobox`, `Lista de campos` ni `Alert – neutro`, que ya están documentados en 002; solo referenciarlos

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Modelo de datos, dominio, repositorio y piezas de frontend que necesitan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend

- [X] T003 Crear `backend/database/migrations/2026_10_11_000003_create_competencia_pregunta_registros_table.php`: `id`, `competencia_id` FK a `competencias.id` **única** y **sin borrado en cascada** (`restrictOnDelete`), `version` entero sin signo por defecto 1, `created_by` y `updated_by` enteros sin signo anulables **sin clave foránea**, timestamps
- [X] T004 Crear `backend/database/migrations/2026_10_11_000004_create_competencia_preguntas_table.php`: `id`, `registro_id` FK a `competencia_pregunta_registros.id` **sin borrado en cascada** (`restrictOnDelete`), `orden` entero 1–4, `pregunta` texto máximo 500 (`string(500)`), `created_by` y `updated_by` enteros sin signo anulables sin clave foránea, timestamps, y restricción única en `(registro_id, orden)`
- [X] T005 [P] Crear las excepciones `PreguntasNoEncontradas` ("El registro de preguntas no existe.") y `CompetenciaYaTienePreguntas` ("La competencia ya tiene un registro de preguntas.") en `backend/app/Modules/Estructura/Domain/Exceptions/` (finales, `RuntimeException`, como las de 002) y el value object `PaginaRegistrosPreguntas` (items, total) en `backend/app/Modules/Estructura/Domain/ValueObjects/`
- [X] T006 [P] Crear la entidad `backend/app/Modules/Estructura/Domain/Entities/RegistroPreguntas.php` (`CompetenciaAsociada` de 002, `version`, `creadoPor`, `modificadoPor` y `preguntas` como lista de exactamente 4 textos; métodos `estado()` y `competenciaActiva()`)
- [X] T007 Crear `backend/app/Modules/Estructura/Domain/Repositories/PreguntaRepositoryInterface.php`: `listar(?string $q, ?TipoCompetencia $tipo, ?EstadoCompetencia $estado, int $pagina, int $porPagina)`, `obtener(int $competenciaId): ?RegistroPreguntas`, `obtenerPorCompetencias(array $ids)`, `obtenerActivos()`, `disponibles()` (competencias `Activo` sin registro, orden por nombre, como `CompetenciaAsociada`), `crear(int $competenciaId, array $preguntas, ?int $actorId)` y `actualizar(int $competenciaId, int $versionEsperada, array $preguntas, ?int $actorId)`
- [X] T008 [P] Crear `RegistroPreguntasModel` (tabla de T003) y `PreguntaModel` (tabla de T004) en `backend/app/Modules/Estructura/Infrastructure/Persistence/Eloquent/Models/`, y modificar `CompetenciaModel.php` (+ `registroPreguntas()` hasOne); **sin columnas nuevas** en `competencias` ni `competencia_grados`
- [X] T009 Crear `backend/app/Modules/Estructura/Infrastructure/Persistence/Eloquent/Repositories/EloquentPreguntaRepository.php` con las **lecturas**: `listar`, `obtener`, `obtenerPorCompetencias`, `obtenerActivos` y `disponibles`, todas con `join` a `competencias` (código, nombre, descripción, tipo y estado en vivo; `q` busca por `nombre_clave` de la competencia y escapa `%`, `_` y `\`; `disponibles` = competencias `Activo` sin cabecera, ordenadas por nombre); las preguntas se devuelven ordenadas por `orden`
- [X] T009b Añadir al mismo repositorio las **escrituras**: `crear` en una transacción (valida que la competencia exista y esté `Activo` → si no `CompetenciaNoDisponible`; inserta la cabecera con `version` 1 y `created_by`, e inserta las 4 filas con `orden` 1–4 por posición; la violación del índice único de `competencia_id` → `CompetenciaYaTienePreguntas`) y `actualizar` en una transacción (compara los 4 textos recortados con los actuales y, si son idénticos, devuelve el registro **sin escribir nada**; si hay cambios y `version` coincide —`UPDATE … WHERE competencia_id = ? AND version = ?`, si no `RegistroModificado`— actualiza **por `orden`** solo las filas cuyo texto cambió conservando `created_by` y fijando `updated_by`, incrementa `version` y fija `updated_by` de la cabecera; **no inserta ni elimina filas**; sin registro → `PreguntasNoEncontradas`)
- [X] T010 Registrar el binding `PreguntaRepositoryInterface` → `EloquentPreguntaRepository` en `backend/app/Modules/Estructura/Infrastructure/Providers/EstructuraServiceProvider.php`
- [X] T011 [P] Crear `backend/database/factories/RegistroPreguntasFactory.php` (cabecera + 4 preguntas con `conPreguntas()`), el doble `backend/tests/Unit/Estructura/Preguntas/RepositorioPreguntasEnMemoria.php` y los helpers `datosPreguntas()` y `registroPreguntasDe()` en `backend/tests/Pest.php`
- [X] T012 [P] Crear `backend/tests/Feature/Estructura/Preguntas/RepositorioPreguntasTest.php`: crear (cabecera `version` 1, `created_by`, `orden` 1–4 por posición, 4 filas), una competencia un solo registro (índice único; un segundo insert de cabecera para la misma `competencia_id` falla), competencia inactiva o inexistente rechazada sin dejar filas, `orden` único por registro, sin cascada (no se puede borrar una competencia con registro), editar con cambios (versión +1, `created_by` de las filas conservado, solo cambian las filas con texto distinto, siguen 4 filas), editar sin cambios (nada cambia, ni con espacios alrededor), `RegistroModificado` y `PreguntasNoEncontradas`, estado y datos de la competencia leídos en vivo, `disponibles()`. Esta prueba es la evidencia de SC-005: la unicidad por competencia la garantiza el índice único, no una carrera reproducible

### Frontend

- [X] T013 [P] Crear `frontend/src/modules/estructura/preguntas/api.ts`: tipos propios (`TipoCompetencia`, `EstadoCompetencia`, `FiltroEstado`, `RegistroResumen`, `RegistroDetalle` con `preguntas: string[]`, `CompetenciaDisponible`, `PaginaRegistros`) y funciones `listarPreguntas`, `obtenerPreguntas`, `listarCompetenciasDisponibles`, `crearPreguntas(competenciaId, preguntas)` y `actualizarPreguntas(competenciaId, preguntas, version)` sobre el cliente `http` compartido
- [X] T014 [P] Crear `frontend/src/modules/estructura/preguntas/text.ts` con todos los textos en español ("Preguntas", "Visualizar preguntas", "Ocultar preguntas", "Nuevo registro", "Editar", columnas, estados, filtros, aviso "La competencia asociada está inactiva", "Pregunta 1"…"Pregunta 4", mensajes de validación y de conflicto, "No hay competencias disponibles")
- [X] T015 [P] Crear `frontend/src/test/preguntasFixtures.ts` (`registro()`, `detalle()`, `pagina()`, `competenciaDisponible()`)
- [X] T016 [P] Añadir `IconHelp` (SVG propio, trazo 1.75, `aria-hidden`) a `frontend/src/shared/ui/icons.tsx` para el ítem de menú

**Checkpoint**: Base lista; las historias pueden empezar

---

## Phase 3: User Story 1 - Consultar las preguntas (Priority: P1) 🎯 MVP

**Goal**: Cualquier persona autenticada ve el listado (activos por defecto), busca, filtra y despliega las cuatro preguntas numeradas; el Administrador filtra por estado.

**Independent Test**: Con la base sembrada, entrar como Usuario y como Administrador, buscar, filtrar y desplegar; comprobar lo que ve cada rol.

### Tests for User Story 1

- [X] T017 [P] [US1] Crear `backend/tests/Feature/Estructura/Preguntas/ConsultarPreguntasTest.php`: 401 sin sesión; el Usuario recibe solo `Activo` aunque envíe `estado=Todos` o `Inactivo`; el Administrador por defecto `Activo`, `Inactivo` y `Todos`; `estado`, `tipo` o `per_page` inválidos → 422; `q` por nombre de la competencia (acentos y `%`); orden por nombre de la competencia; paginación (10 por defecto, máximo 50); el listado no incluye preguntas; el detalle trae `preguntas` con exactamente 4 textos en orden y `version`; el detalle de una competencia inactiva responde 200 a ambos roles; competencia sin registro → 404; el nombre de la competencia cambiado en 001 se refleja de inmediato
- [X] T018 [P] [US1] Crear `backend/tests/Unit/Estructura/Preguntas/ConsultarPreguntasTest.php` para `ListarPreguntas` y `ObtenerPreguntas` con el doble en memoria
- [X] T019 [P] [US1] Crear `backend/tests/Feature/Estructura/Preguntas/EstructuraFacadePreguntasTest.php`: `getPreguntas` (incluye inactivas, `null` sin registro, solo arreglos, 4 textos), `getPreguntasPorCompetencias`, `tienePreguntasActivas` (activa con registro, inactiva, sin registro) y `getPreguntasActivas` ordenadas por nombre
- [X] T020 [P] [US1] Crear `frontend/src/modules/estructura/preguntas/pages/PreguntasPage.dom.test.tsx`: estados cargando, vacío, sin resultados, error con "Reintentar" y éxito; columnas Código, Nombre, Descripción, Tipo y Estado (badge Activo/Inactivo); el Usuario no ve filtro de estado, "Nuevo registro" ni Editar; el Administrador sí; búsqueda, tipo y estado en la URL (`Activo` no se escribe); el Usuario no envía `estado`; no existe ningún botón Activar, Desactivar ni Eliminar
- [X] T021 [P] [US1] Crear `frontend/src/modules/estructura/preguntas/components/PreguntasPanel.dom.test.tsx`: las cuatro preguntas numeradas del 1 al 4 en una lista ordenada (`<ol>/<li>`), estado de carga y de error con reintento, 404 "ya no existe", y el aviso "La competencia asociada está inactiva" cuando corresponde

### Implementation for User Story 1

- [X] T022 [P] [US1] Crear los DTO `RegistroPreguntasDTO`, `ListadoPreguntasDTO` y `FiltroPreguntasDTO` (con `estado` nulo = todos) en `backend/app/Modules/Estructura/Application/DTO/`
- [X] T023 [US1] Crear los casos de uso `ListarPreguntas` y `ObtenerPreguntas` en `backend/app/Modules/Estructura/Application/UseCases/`
- [X] T024 [P] [US1] Crear `ListarPreguntasRequest` (`q` máximo 50, `tipo` en `Cardinal,Especifica`, `estado` en `Activo,Inactivo,Todos`, `page` ≥ 1, `per_page` entre 1 y 50; `filtro(bool $esAdministrador)` resuelve el estado efectivo: no administrador → `Activo` siempre; administrador → el pedido o `Activo`; `Todos` → nulo; mensajes en español) en `backend/app/Modules/Estructura/Presentation/Requests/ListarPreguntasRequest.php`
- [X] T025 [P] [US1] Crear `RegistroPreguntasResource` (competencia_id, codigo, nombre, descripcion, tipo, estado) y `RegistroPreguntasDetalleResource` (añade `version` y `preguntas` como lista de 4 textos) en `backend/app/Modules/Estructura/Presentation/Resources/`
- [X] T026 [US1] Crear `PreguntaController` con `index` (`$request->user()->tokenCan('admin')`) y `show` (404 "El registro de preguntas no existe.") en `backend/app/Modules/Estructura/Presentation/Controllers/PreguntaController.php`, y registrar `GET /api/preguntas` y `GET /api/preguntas/{competenciaId}` con `auth:sanctum` (sin `DELETE` ni ruta de estado) en `backend/app/Modules/Estructura/Routes/api.php`
- [X] T027 [US1] Modificar `backend/app/Modules/Estructura/Application/Services/EstructuraService.php` (inyectar `PreguntaRepositoryInterface`) y `backend/app/Modules/Estructura/Presentation/Facades/EstructuraFacade.php` con `getPreguntas`, `getPreguntasPorCompetencias`, `tienePreguntasActivas` y `getPreguntasActivas` según contracts/estructura-facade.md (actualizar los docblocks)
- [X] T028 [P] [US1] Crear `backend/database/seeders/PreguntasSeeder.php` (idempotente por competencia; usa el repositorio con `created_by` = id de `admin@hrms.test` o nulo; para la inactiva la reactiva un instante con `DB::table` sin tocar `updated_at` y la restaura en `finally`, como `ComportamientosSeeder`): registros de cuatro preguntas para `COMP-0001`, `COMP-0002`, `COMP-0004`, `COMP-0006` y `COMP-0007` (inactiva); `COMP-0003` y `COMP-0005` quedan sin registro; llamarlo desde `backend/database/seeders/DatabaseSeeder.php` después de `ComportamientosSeeder`
- [X] T029 [P] [US1] Crear `useListadoParams.ts` (copia de 002: q, tipo, estado y page en `useSearchParams`, `Activo` no se escribe) y `usePreguntas.ts`, que exporta `preguntasKey`, `usePreguntas` (clave `["preguntas","lista",{q,tipo,estado,page}]`), `usePregunta` (clave `["preguntas","detalle",id]`, con `siempreFresca`) y `useCompetenciasDisponibles` (clave `["preguntas","disponibles"]`, `refetchOnMount: 'always'`), en `frontend/src/modules/estructura/preguntas/hooks/`
- [X] T030 [US1] Crear `PreguntasPanel` (carga el detalle solo al desplegar; `<ol>` con las cuatro preguntas numeradas; aviso neutro si la competencia está inactiva) en `frontend/src/modules/estructura/preguntas/components/PreguntasPanel.tsx`
- [X] T031 [US1] Crear `PreguntasTable` (columnas Código, Nombre, Descripción recortada a 2 líneas, Tipo y Estado con `Badge` `Activo` / `neutral` `Inactivo`; botón "Visualizar preguntas" con `aria-expanded`; contenedor `relative overflow-x-auto`) en `frontend/src/modules/estructura/preguntas/components/PreguntasTable.tsx`
- [X] T032 [US1] Crear `PreguntasPage` según `design/Competencias.dc.html` y `design/Estados.dc.html` (búsqueda por nombre de la competencia, filtro de tipo, filtro de estado solo Administrador, tabla, `Pagination`, estados cargando/vacío/error/éxito) en `frontend/src/modules/estructura/preguntas/pages/PreguntasPage.tsx`
- [X] T033 [US1] Crear `frontend/src/modules/estructura/preguntas/nav.tsx` (ítem "Preguntas" con `IconHelp`) y registrar la ruta `/preguntas` y el ítem en `frontend/src/App.tsx`
- [X] T034 [US1] Crear el E2E de consulta en `frontend/e2e/preguntas.spec.ts`: el Usuario ve 4 registros activos con Código y Estado, sin `Adaptabilidad`, sin filtro de estado ni botones de gestión; busca, filtra y recarga conservando la URL; despliega "Visualizar preguntas" y ve las 4 numeradas; el Administrador alterna Activos (4), Inactivos (1) y Todos (5) usando solo las filas de los registros sembrados; el Usuario no puede escribir por la API (403) ni ver disponibles

**Checkpoint**: MVP consultable

---

## Phase 4: User Story 2 - Crear un registro de preguntas (Priority: P1)

**Goal**: El Administrador crea el registro de una competencia activa sin registro con sus cuatro preguntas.

**Independent Test**: Crear el registro de `COMP-0003` y verlo en el listado.

### Tests for User Story 2

- [X] T035 [P] [US2] Crear `backend/tests/Feature/Estructura/Preguntas/CrearPreguntasTest.php`: 201 con `version` 1 y `created_by`; 401 y 403 al Usuario; `competencia_id` ausente, inexistente, inactiva o con registro → 422 con mensaje propio; menos o más de 4 textos → 422; texto vacío, de solo espacios, de 501 caracteres (500 sí) o repetido en el registro (sin distinguir mayúsculas ni espacios alrededor) → 422 con error en `preguntas.2`; dos creaciones de la misma competencia dejan un solo registro; el texto se guarda recortado y el orden es la posición
- [X] T036 [P] [US2] Crear `backend/tests/Feature/Estructura/Preguntas/CompetenciasDisponiblesPreguntasTest.php`: 401, 403 al Usuario, 200 al Administrador con solo competencias activas sin registro ordenadas por nombre (sin `Adaptabilidad` ni las que ya tienen), y que una competencia con registro desaparece de la lista
- [X] T037 [P] [US2] Crear `backend/tests/Unit/Estructura/Preguntas/CrearPreguntasTest.php` para `CrearPreguntas` y `ListarCompetenciasDisponiblesPreguntas` con el doble en memoria
- [X] T038 [P] [US2] Crear `frontend/src/modules/estructura/preguntas/validation.test.ts` para el esquema Zod: competencia obligatoria al crear; exactamente 4 preguntas; cada texto obligatorio, recortado, máximo 500 caracteres y distinto de los demás (sin distinguir mayúsculas)
- [X] T039 [P] [US2] Crear `frontend/src/modules/estructura/preguntas/pages/PreguntasFormPage.dom.test.tsx` (crear): selector con búsqueda solo de disponibles, al elegir se ven nombre, descripción y tipo en solo lectura, cuatro campos numerados fijos sin botones de agregar ni quitar, contador `n/500`, errores por campo con aviso general, pregunta repetida, valores conservados, botón bloqueado al guardar, mapeo de 422 (`competencia_id`, `preguntas.1`), "No hay competencias disponibles", y `RequireAdmin` (Usuario sin permisos); el 422 por competencia no disponible (`competencia_id`: «La competencia no existe o no está activa.») se muestra en el selector, conserva las preguntas escritas y vuelve a pedir `competencias-disponibles`

### Implementation for User Story 2

- [X] T040 [P] [US2] Crear `GuardarPreguntasRequest` en `backend/app/Modules/Estructura/Presentation/Requests/GuardarPreguntasRequest.php`: `competencia_id` obligatorio en `POST` (entero, existe, `Activo`, sin registro; mensajes distintos); `preguntas` `array` de **exactamente 4** (`size:4`); cada texto `string`, obligatorio, recortado, `max:500` y único dentro del registro sin distinguir mayúsculas ni espacios alrededor (`withValidator`); `version` obligatorio solo en `PUT`; mensajes en español
- [X] T041 [US2] Crear `CrearPreguntas` y `ListarCompetenciasDisponiblesPreguntas` en `backend/app/Modules/Estructura/Application/UseCases/` (reutilizar `CompetenciaDisponibleDTO` de 002)
- [X] T042 [US2] Añadir `PreguntaController@store` (201; `CompetenciaNoDisponible` y `CompetenciaYaTienePreguntas` → 422 con `errors.competencia_id`) y `@disponibles` (reutilizar `CompetenciaDisponibleResource` de 002), y registrar `GET /api/preguntas/competencias-disponibles` **antes** de `/{competenciaId}` y `POST /api/preguntas` con `auth:sanctum` y `admin` en `backend/app/Modules/Estructura/Routes/api.php`
- [X] T043 [P] [US2] Crear `frontend/src/modules/estructura/preguntas/validation.ts` (esquema Zod de T038) y `RequireAdmin` propio en `frontend/src/modules/estructura/preguntas/components/RequireAdmin.tsx` (rol solo del contrato `modules/auth/login`)
- [X] T044 [P] [US2] Crear el hook `useGuardarPreguntas` (crear; mutación sin reintentos; invalida `["preguntas"]` sin esperar la recarga) en `frontend/src/modules/estructura/preguntas/hooks/useGuardarPreguntas.ts`
- [X] T045 [P] [US2] Crear `CompetenciaResumen` (nombre, descripción y tipo en solo lectura, más código y estado cuando se conocen) en `frontend/src/modules/estructura/preguntas/components/CompetenciaResumen.tsx`
- [X] T046 [US2] Crear `PreguntasFormPage` en modo crear según `design/Formulario.dc.html` (Breadcrumb, `Combobox` de competencias disponibles, `CompetenciaResumen`, cuatro `TextField` numerados "Pregunta 1"…"Pregunta 4" con contador `n/500`, Alert general "Revisa los campos marcados antes de guardar.", Cancelar y "Guardar") en `frontend/src/modules/estructura/preguntas/pages/PreguntasFormPage.tsx` (ante el 422 de `competencia_id` invalidar `["preguntas","disponibles"]` para refrescar la lista), registrar `/preguntas/nuevo` bajo `RequireAdmin` en `frontend/src/App.tsx` y mostrar "Nuevo registro" solo al Administrador en `PreguntasPage.tsx`
- [X] T047 [US2] Añadir el E2E de creación en `frontend/e2e/preguntas.spec.ts`: el Administrador crea el registro de `COMP-0003` (errores por campo con el formulario incompleto y con una pregunta repetida; en el selector aparecen `Comunicación efectiva` y `Pensamiento analítico` y no aparecen las que ya tienen registro ni `Adaptabilidad`; no se comprueba el número exacto de disponibles, porque `competencias.spec.ts` puede haber añadido otras activas) y lo ve en el listado; el Usuario no ve el botón ni accede a `/preguntas/nuevo`

**Checkpoint**: Consulta y creación funcionan

---

## Phase 5: User Story 3 - Editar las preguntas (Priority: P2)

**Goal**: El Administrador modifica las preguntas de un registro (también de competencia inactiva) con la competencia en solo lectura.

**Independent Test**: Editar `COMP-0001` y `COMP-0007`, guardar, ver los cambios y la auditoría.

### Tests for User Story 3

- [X] T048 [P] [US3] Crear `backend/tests/Feature/Estructura/Preguntas/ActualizarPreguntasTest.php`: 200 con cambios (`version` +1, `updated_by` = editor, `created_by` intacto, siguen 4 filas); editar con la competencia inactiva → 200 y sigue `Inactivo`; sin cambios reales → 200, misma `version`, sin tocar `updated_at` ni `updated_by`; `version` desactualizada → 409; `version` ausente → 422; `competencia_id` distinto → 422; sin registro → 404; 401 y 403 al Usuario; las mismas reglas de validación que crear
- [X] T049 [P] [US3] Crear `backend/tests/Unit/Estructura/Preguntas/ActualizarPreguntasTest.php` para `ActualizarPreguntas` con el doble en memoria
- [X] T050 [P] [US3] Crear `frontend/src/modules/estructura/preguntas/pages/PreguntasFormPage.edit.dom.test.tsx`: estado cargando, competencia en solo lectura (sin selector), preguntas actuales en sus cuatro campos, aviso "La competencia asociada está inactiva", envío con `version`, alerta de conflicto 409 con "Recargar", 404 "ya no existe" y guardar sin cambios vuelve al listado sin error

### Implementation for User Story 3

- [X] T051 [US3] Crear `ActualizarPreguntas` en `backend/app/Modules/Estructura/Application/UseCases/ActualizarPreguntas.php` (`ejecutar(int $competenciaId, int $versionEsperada, array $preguntas, ?int $actorId = null)`, recorta los textos)
- [X] T052 [US3] Añadir `PreguntaController@update` (200; `RegistroModificado` → 409; `PreguntasNoEncontradas` → 404; `competencia_id` distinto → 422) y la ruta `PUT /api/preguntas/{competenciaId}` con `auth:sanctum` y `admin` en `backend/app/Modules/Estructura/Presentation/Controllers/PreguntaController.php` y `backend/app/Modules/Estructura/Routes/api.php`
- [X] T053 [US3] Extender `useGuardarPreguntas` con la actualización (envía `version`; expone el conflicto) en `frontend/src/modules/estructura/preguntas/hooks/useGuardarPreguntas.ts`
- [X] T054 [US3] Añadir el modo editar a `PreguntasFormPage` (lectura fresca con esqueleto, `CompetenciaResumen` sin selector, preguntas actuales, alerta de conflicto con "Recargar" y 404), registrar `/preguntas/:competenciaId/editar` bajo `RequireAdmin` en `frontend/src/App.tsx`, mostrar el botón Editar solo al Administrador en `PreguntasTable.tsx` y mostrar el aviso "La competencia asociada está inactiva" en el formulario cuando corresponda (`Alert` neutro)
- [X] T055 [US3] Añadir el E2E de edición en `frontend/e2e/preguntas.spec.ts`: editar `COMP-0001` (cambiar preguntas), editar el de `COMP-0007` inactiva con su aviso, guardar sin cambios sin error y el conflicto con una edición concurrente simulada por la API

**Checkpoint**: Crear y editar funcionan

---

## Phase 6: User Story 4 - El estado sigue al de la competencia (Priority: P2)

**Goal**: Desactivar o reactivar la competencia en 001 cambia el registro sin acciones propias; no hay activar, desactivar ni eliminar.

**Independent Test**: Desactivar una competencia con registro, ver el registro inactivo solo para el Administrador, reactivarla.

### Tests for User Story 4

- [X] T056 [P] [US4] Crear `backend/tests/Feature/Estructura/Preguntas/EstadoDerivadoTest.php`: desactivar la competencia con `PATCH /api/competencias/{id}/estado` hace que su registro salga del listado del Usuario y del listado por defecto del Administrador, aparezca con `estado` `Inactivo` en `?estado=Inactivo` y siga consultable por id para ambos; reactivarla lo devuelve; editar el registro de una competencia inactiva no cambia su estado; ni la tabla ni el modelo del registro tienen columna `estado`
- [X] T057 [P] [US4] Crear `backend/tests/Feature/Estructura/Preguntas/SinGestionDeEstadoTest.php`: bajo `api/preguntas` solo existen 5 rutas (`GET`, `POST`, `PUT`); ninguna `DELETE` ni de estado (`DELETE` y `PATCH` responden 404/405 incluso al Administrador) y no se elimina ningún registro
- [X] T058 [P] [US4] Crear `frontend/src/modules/estructura/preguntas/components/PreguntasTable.dom.test.tsx`: la fila de una competencia inactiva muestra el badge neutro "Inactivo" y, al desplegarla, el aviso "La competencia asociada está inactiva"; solo el Administrador ve Editar; no hay botones Activar, Desactivar ni Eliminar para ningún rol

### Implementation for User Story 4

- [X] T059 [US4] Comprobar que ningún componente de `frontend/src/modules/estructura/preguntas/` ofrece activar, desactivar ni eliminar y que el aviso de competencia inactiva aparece en tabla, panel y formulario (ajustes solo si faltan)
- [X] T060 [US4] Añadir el E2E de estado derivado en `frontend/e2e/preguntas.spec.ts`: el Administrador desactiva `Trabajo en equipo` desde `/competencias` (001), el registro aparece inactivo en `/preguntas?estado=Inactivo` y desaparece del listado del Usuario; al reactivarla vuelve a verse en ambos; el test deja `Trabajo en equipo` activa al terminar aunque falle una comprobación intermedia (con `try/finally`)

**Checkpoint**: Estado derivado verificado

---

## Phase 7: User Story 5 - Trazabilidad de los cambios (Priority: P3)

**Goal**: Queda registrado quién creó y quién modificó cada registro; el estado solo se audita en 001.

**Independent Test**: Crear y editar con dos administradores e inspeccionar `created_by`/`updated_by`.

- [X] T061 [P] [US5] Crear `backend/tests/Feature/Estructura/Preguntas/AuditoriaPreguntasTest.php`: ciclo completo con dos administradores (`created_by` del alta, `updated_by` tras otra edición sin alterar `created_by`, `version` y marcas de tiempo); una edición sin cambios no modifica `updated_by`, `updated_at` ni `version`; las filas conservan su `created_by`
- [X] T062 [P] [US5] Crear `backend/tests/Unit/Estructura/Preguntas/SinHistorialPropioTest.php`: desactivar y reactivar la competencia no escribe ninguna fila en `competencia_pregunta_registros` ni `competencia_preguntas`, y no existe tabla ni ruta de historial de preguntas

**Checkpoint**: Auditoría completa

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Calidad transversal y cierre

- [X] T063 [P] Auditar contraste y foco del selector, los campos numerados, la lista numerada y los avisos en ambos temas (pares ≥ 4.5:1, foco visible, orden de tabulación del formulario) y reflejarlo en `frontend/e2e/preguntas.spec.ts` (foco visible y tema oscuro)
- [X] T064 [P] Verificar sin scroll horizontal a 320 y 1440 px el listado, las preguntas desplegadas y el formulario con los cuatro campos llenos de 500 caracteres en `frontend/e2e/preguntas.spec.ts`
- [X] T065 [P] Revisar seguridad: el rol se verifica en el backend en el listado, las escrituras y `competencias-disponibles`; un Usuario nunca recibe registros de competencias inactivas; `competencia_id` distinto en `PUT` se rechaza; los errores no filtran detalles internos y están en español; ningún módulo toca las tablas de preguntas
- [X] T066 [P] Revisar que `design/DESIGN.md` refleja la lista numerada, los campos numerados fijos y el aviso de competencia inactiva tal como quedaron implementados
- [X] T067 Reiniciar la base de desarrollo con `php artisan migrate:fresh --seed` en `backend/` y comprobar 5 registros de preguntas (uno de `COMP-0007` inactiva, 20 filas) y 2 competencias activas disponibles
- [X] T068 Ejecutar las puertas de calidad del backend: `vendor/bin/pint --test` y `vendor/bin/pest` en `backend/`
- [X] T069 Ejecutar las puertas de calidad del frontend: `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `npx playwright test` en `frontend/`
- [X] T070 Ejecutar la validación de `specs/003-diccionario-preguntas/quickstart.md` de punta a punta

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias; T002 (diseño) precede a todo el frontend (T013–T016 en adelante)
- **Foundational (Phase 2)**: depende de Setup; bloquea todas las historias. T007 depende de T005–T006; T009 de T003, T004, T007 y T008; T009b de T009; T010 de T009 y T009b; T012 de T009, T009b, T010 y T011
- **US1 (Phase 3)**: tras Foundational; MVP
- **US2 (Phase 4)**: tras Foundational; el backend es independiente; el frontend se integra con la página de US1
- **US3 (Phase 5)**: tras Foundational; reutiliza `useGuardarPreguntas` y `PreguntasFormPage` de US2
- **US4 (Phase 6)**: tras US1 (y US3 para la prueba de edición con competencia inactiva); no añade endpoints
- **US5 (Phase 7)**: sus pruebas ejercitan crear (US2) y editar (US3)
- **Polish (Phase 8)**: tras las historias deseadas

### User Story Dependencies

- **US1 (P1)**: sin dependencias de otras historias
- **US2 (P1)**: backend independiente; el frontend usa la página y las rutas de US1
- **US3 (P2)**: backend independiente de US2 salvo la validación compartida (`GuardarPreguntasRequest`, T040); el frontend depende del formulario de US2
- **US4 (P2)**: depende de US1 y de la API de 001
- **US5 (P3)**: depende de US2 y US3

### Within Each User Story

- Las pruebas se escriben primero y deben fallar
- Dominio → casos de uso → controlador y rutas; en frontend: hooks → componentes → páginas → rutas
- Los archivos compartidos (`PreguntaController.php`, `Routes/api.php`, `PreguntasTable.tsx`, `PreguntasPage.tsx`, `PreguntasFormPage.tsx`, `useGuardarPreguntas.ts`, `App.tsx`, `preguntas.spec.ts`) se editan en secuencia entre historias, no en paralelo
- Los E2E de `preguntas.spec.ts` dependen del orden (serial): la creación de `COMP-0003` precede a las comprobaciones de conteo posteriores

### Parallel Opportunities

- Phase 2: T005, T006 y T008 en paralelo; T011–T012 y T013–T016 en paralelo cuando sus dependencias estén hechas
- Cada historia: todas sus pruebas [P] a la vez; DTO/Request/Resource y hooks en paralelo
- Backend y frontend de una historia pueden avanzar en paralelo tras Foundational

---

## Parallel Example: User Story 2

```bash
# Pruebas de US2 en paralelo:
Task: "Feature CrearPreguntasTest en backend/tests/Feature/Estructura/Preguntas/"
Task: "Feature CompetenciasDisponiblesPreguntasTest en backend/tests/Feature/Estructura/Preguntas/"
Task: "validation.test.ts en frontend/src/modules/estructura/preguntas/"
Task: "dom PreguntasFormPage (crear) en frontend/src/modules/estructura/preguntas/pages/"

# Piezas independientes de US2:
Task: "GuardarPreguntasRequest"
Task: "validation.ts y RequireAdmin"
Task: "useGuardarPreguntas y CompetenciaResumen"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 (línea base y diseño) y Phase 2 (modelo, repositorio y piezas de frontend)
2. Phase 3 (US1): el diccionario es consultable con los datos sembrados
3. **Parar y validar**: Usuario y Administrador consultan, buscan, filtran y despliegan

### Incremental Delivery

1. US1 → consulta; 2. US2 → crear; 3. US3 → editar; 4. US4 → estado derivado verificado; 5. US5 → auditoría; 6. Polish
2. Cada historia se prueba por separado antes de pasar a la siguiente; al terminar cada tarea marcarla `[X]`

### Notas

- Escribir las pruebas primero y verlas fallar; no marcar una tarea sin que su prueba pase
- No añadir dependencias nuevas ni tocar el código de 001 y 002 más allá de los archivos compartidos indicados (rutas, servicio y Facade, `App.tsx`, `icons.tsx`, `DatabaseSeeder`, `Pest.php`, `CompetenciaModel`, `EstructuraServiceProvider`)

### Desviaciones respecto a lo planeado (registradas al implementar)

- Las pruebas de T056, T057 y T061 (estado derivado, sin gestión de estado y auditoría) quedaron en un solo archivo: `backend/tests/Feature/Estructura/Preguntas/EstadoYAuditoriaPreguntasTest.php`.
- Las pruebas unitarias de T018, T037, T049 y T062 quedaron en `backend/tests/Unit/Estructura/Preguntas/CasosDeUsoPreguntasTest.php`.
- Las pruebas de `PreguntasTable` (T058) incluyen las comprobaciones de T059.
