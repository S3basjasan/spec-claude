---

description: "Tareas de la feature 002-diccionario-comportamientos"
---

# Tasks: Diccionario de comportamientos

**Input**: Documentos de diseño en `/specs/002-diccionario-comportamientos/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/comportamientos-api.md, contracts/estructura-facade.md. Requiere 000-login y 001-diccionario-competencias ya implementadas (competencias con `estado`, grados y su Facade).

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario para implementarlas y probarlas de forma independiente. La feature es **nueva** (no existe código previo) salvo los archivos compartidos indicados como "modificar".

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1–US5)
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Estructura/…` (Domain, Application, Infrastructure, Presentation)
- Frontend: `frontend/src/modules/estructura/comportamientos/…` y `frontend/src/shared/ui/…`
- Pruebas backend: `backend/tests/{Unit,Feature}/Estructura/Comportamientos/`
- Pruebas de componentes: `*.dom.test.tsx` junto al código; E2E en `frontend/e2e/comportamientos.spec.ts`
- Regla de módulos: `comportamientos` **no importa nada** de `frontend/src/modules/estructura/competencias`; el rol llega solo por `frontend/src/modules/auth/login` (`index.ts`)
- Valores heredados de 001: `tipo` ∈ `Cardinal` | `Especifica`; `estado` ∈ `Activo` | `Inactivo` (siempre los de la competencia)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Línea base verde y diseño actualizado antes de tocar el frontend

- [X] T001 Verificar la línea base: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run` y `npx playwright test` en `frontend/` deben pasar antes de empezar (sin cambios de archivos)
- [X] T002 [P] Modificar `design/DESIGN.md` **antes** de cualquier tarea de frontend (constitución III): §4 componentes nuevos **Combobox** (campo de texto que filtra una lista de opciones al escribir, `role="combobox"` con `listbox`, navegación con flechas, Enter selecciona, Escape cierra, estado "sin resultados") y **Lista de campos** (campos de texto repetibles con "Agregar" hasta el máximo y "Quitar" hasta el mínimo, contador de caracteres por campo, mínimo 1 y máximo 4); §5 patrón Listado ampliado con filas desplegables de **viñetas por grado** y el **aviso "La competencia asociada está inactiva"** (`Alert` neutro); §9 diferencias con los mockups (columnas Código y Estado, ausencia de activar/desactivar/eliminar en este recurso)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Modelo de datos, dominio, repositorio y componentes compartidos que necesitan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend

- [X] T003 Crear `backend/database/migrations/2026_10_11_000001_create_competencia_comportamiento_registros_table.php`: `id`, `competencia_id` FK a `competencias.id` **única** y **sin borrado en cascada** (`restrictOnDelete`), `version` entero sin signo por defecto 1, `created_by` y `updated_by` enteros sin signo anulables **sin clave foránea**, timestamps
- [X] T004 Crear `backend/database/migrations/2026_10_11_000002_create_competencia_comportamientos_table.php`: `id`, `competencia_grado_id` FK a `competencia_grados.id` **sin borrado en cascada** (`restrictOnDelete`), `orden` entero 1–4, `descripcion` texto máximo 300, `created_by` y `updated_by` enteros sin signo anulables sin clave foránea, timestamps, y restricción única en `(competencia_grado_id, orden)`
- [X] T005 [P] Crear las excepciones `RegistroNoEncontrado` ("El registro de comportamientos no existe."), `CompetenciaNoDisponible` ("La competencia no existe o no está activa."), `CompetenciaYaTieneRegistro` ("La competencia ya tiene un registro de comportamientos.") y `RegistroModificado` ("El registro cambió desde que lo abriste. Recarga para ver la versión actual.") en `backend/app/Modules/Estructura/Domain/Exceptions/`, y los value objects `CompetenciaAsociada` (id, codigo, nombre, descripcion, `TipoCompetencia`, `EstadoCompetencia`; solo lectura) y `PaginaRegistrosComportamientos` en `backend/app/Modules/Estructura/Domain/ValueObjects/`
- [X] T006 [P] Crear la entidad `backend/app/Modules/Estructura/Domain/Entities/RegistroComportamientos.php` (`CompetenciaAsociada`, `version`, `creadoPor`, `modificadoPor` y `comportamientos` como arreglo `A`–`D` de listas de textos)
- [X] T007 Crear `backend/app/Modules/Estructura/Domain/Repositories/ComportamientoRepositoryInterface.php`: `listar(?string $q, ?TipoCompetencia $tipo, ?EstadoCompetencia $estado, int $pagina, int $porPagina)`, `obtener(int $competenciaId): ?RegistroComportamientos`, `obtenerPorCompetencias(array $ids)`, `obtenerActivos()`, `disponibles()` (competencias `Activo` sin registro, orden por nombre), `crear(int $competenciaId, array $comportamientos, ?int $actorId)` y `actualizar(int $competenciaId, int $versionEsperada, array $comportamientos, ?int $actorId)`
- [X] T008 [P] Crear `RegistroComportamientosModel` (tabla de T003) y `ComportamientoModel` (tabla de T004) en `backend/app/Modules/Estructura/Infrastructure/Persistence/Eloquent/Models/`, y modificar `CompetenciaGradoModel.php` (+ `comportamientos()` ordenada por `orden`) y `CompetenciaModel.php` (+ `registroComportamientos()`); **sin columnas nuevas** en `competencias` ni `competencia_grados`
- [X] T009 Crear `backend/app/Modules/Estructura/Infrastructure/Persistence/Eloquent/Repositories/EloquentComportamientoRepository.php` con las **lecturas**: `listar`, `obtener`, `obtenerPorCompetencias`, `obtenerActivos` y `disponibles`, todas con `join` a `competencias` (código, nombre, descripción, tipo y estado en vivo; `q` busca por `nombre_clave` de la competencia y escapa `%`, `_` y `\`; `disponibles` = competencias `Activo` sin cabecera, ordenadas por nombre)
- [X] T009b Añadir al mismo repositorio las **escrituras**: `crear` en una transacción (valida que la competencia exista y esté `Activo`, inserta la cabecera con `version` 1 y `created_by`, e inserta las filas con `orden` por posición; la violación del índice único de `competencia_id` → `CompetenciaYaTieneRegistro`) y `actualizar` en una transacción (compara los textos recortados con los actuales y, si son idénticos, devuelve el registro **sin escribir nada**; si hay cambios y `version` coincide —`UPDATE … WHERE competencia_id = ? AND version = ?`, si no `RegistroModificado`— actualiza por `orden` conservando `created_by`, inserta nuevas, elimina las sobrantes, incrementa `version` y fija `updated_by`)
- [X] T010 Registrar el binding `ComportamientoRepositoryInterface` → `EloquentComportamientoRepository` en `backend/app/Modules/Estructura/Infrastructure/Providers/EstructuraServiceProvider.php`
- [X] T011 [P] Crear `backend/database/factories/RegistroComportamientosFactory.php` (cabecera + 4 grados con 1–4 comportamientos), el doble `backend/tests/Unit/Estructura/Comportamientos/RepositorioComportamientosEnMemoria.php` y los helpers `datosComportamientos()` y `registroDe()` en `backend/tests/Pest.php`
- [X] T012 [P] Crear `backend/tests/Feature/Estructura/Comportamientos/RepositorioComportamientosTest.php`: crear (cabecera `version` 1, `created_by`, orden por posición), una competencia un solo registro (índice único), competencia inactiva o inexistente rechazada, editar con cambios (versión +1, `created_by` de las filas conservado, filas sobrantes eliminadas), editar sin cambios (nada cambia), `RegistroModificado`, y que las claves foráneas no tienen cascada (no se puede borrar una competencia con registro); y que un segundo insert de cabecera para la misma `competencia_id` falla por el índice único (la garantía real ante creaciones simultáneas)

### Frontend

- [X] T013 [P] Crear `Combobox` en `frontend/src/shared/ui/Combobox.tsx` según `design/DESIGN.md` (depende de T002): props `id`, `label`, `options` (`{value,label,description?}`), `value`, `onChange`, `error`, `placeholder`, `emptyText`; filtra por texto sin distinguir mayúsculas, `aria-expanded`/`aria-activedescendant`, Enter, flechas y Escape, sin importar módulos
- [X] T014 [P] Crear `ListaDeCampos` en `frontend/src/shared/ui/ListaDeCampos.tsx` según `design/DESIGN.md`: campos de texto repetibles con `min` 1 y `max` 4, botones "Agregar comportamiento" (deshabilitado en el máximo) y "Quitar" (deshabilitado en el mínimo, con `aria-label` que indica la posición), contador `n/300` y errores por campo
- [X] T015 [P] Añadir `IconList` (SVG propio, trazo 1.75, `aria-hidden`) a `frontend/src/shared/ui/icons.tsx`
- [X] T016 [P] Crear `frontend/src/modules/estructura/comportamientos/api.ts`: tipos propios (`TipoCompetencia`, `EstadoCompetencia`, `FiltroEstado`, `LetraGrado`, `RegistroResumen`, `RegistroDetalle` con `comportamientos: Record<LetraGrado, string[]>`, `CompetenciaDisponible`) y funciones `listarComportamientos`, `obtenerComportamientos`, `listarCompetenciasDisponibles`, `crearComportamientos` y `actualizarComportamientos(competenciaId, datos, version)` sobre el cliente `http` compartido
- [X] T017 [P] Crear `frontend/src/modules/estructura/comportamientos/text.ts` con todos los textos en español ("Comportamientos", "Visualizar comportamientos", "Ocultar comportamientos", "Nuevo registro", "Editar", columnas, estados, filtros, avisos "La competencia asociada está inactiva", mensajes de validación y de conflicto, "No hay competencias disponibles")
- [X] T018 [P] Crear `frontend/src/test/comportamientosFixtures.ts` (`registro()`, `detalle()`, `pagina()`, `competenciaDisponible()`)

**Checkpoint**: Base lista; las historias pueden comenzar

---

## Phase 3: User Story 1 - Consultar los comportamientos (Priority: P1) 🎯 MVP

**Goal**: Cualquier persona autenticada ve el listado (Código, Nombre, Descripción, Tipo, Estado de la competencia), busca, filtra y despliega los grados con viñetas; el Usuario solo ve los de competencias activas y el Administrador puede filtrar por estado.

**Independent Test**: Como Usuario, el listado no incluye registros de competencias inactivas aunque se pida `estado=Todos`; como Administrador se alterna entre activos, inactivos y todos; ambos consultan por id el registro de una competencia inactiva y despliegan sus comportamientos.

### Tests for User Story 1

> Escribirlas primero y comprobar que fallan

- [X] T019 [P] [US1] Crear `backend/tests/Feature/Estructura/Comportamientos/ConsultarComportamientosTest.php`: 401 sin sesión; el Usuario recibe solo `Activo` aunque envíe `estado=Todos` o `Inactivo`; el Administrador por defecto `Activo`, `Inactivo` y `Todos`; `estado`, `tipo` o `per_page` inválidos → 422; `q` por nombre de la competencia (acentos y `%`); orden por nombre de la competencia; paginación (10 por defecto, máximo 50); el listado no incluye comportamientos; el detalle trae las cuatro claves `A`–`D` en orden y `version`; el detalle de una competencia inactiva responde 200 a ambos roles; competencia sin registro → 404; el nombre de la competencia cambiado en 001 se refleja de inmediato
- [X] T020 [P] [US1] Crear `backend/tests/Unit/Estructura/Comportamientos/ConsultarComportamientosTest.php` para `ListarComportamientos` y `ObtenerComportamientos` con el doble en memoria
- [X] T021 [P] [US1] Crear `backend/tests/Feature/Estructura/Comportamientos/EstructuraFacadeComportamientosTest.php`: `getComportamientos` (incluye inactivas, `null` sin registro, solo arreglos), `getComportamientosPorCompetencias`, `tieneComportamientosActivos` (activa con registro, inactiva, sin registro) y `getComportamientosActivos` ordenados por nombre
- [X] T022 [P] [US1] Crear `frontend/src/modules/estructura/comportamientos/pages/ComportamientosPage.dom.test.tsx`: estados cargando, vacío, sin resultados, error con "Reintentar" y éxito; columnas Código, Nombre, Descripción, Tipo y Estado (badge Activo/Inactivo); el Usuario no ve filtro de estado, "Nuevo registro" ni Editar; el Administrador sí; búsqueda, tipo y estado en la URL (`Activo` no se escribe); el Usuario no envía `estado`; no existe ningún botón Activar, Desactivar ni Eliminar
- [X] T023 [P] [US1] Crear `frontend/src/modules/estructura/comportamientos/components/ComportamientosPanel.dom.test.tsx`: cuatro grados A–D con sus comportamientos como viñetas (`<ul>/<li>`), estado de carga y de error con reintento, 404 "ya no existe", y el aviso "La competencia asociada está inactiva" cuando corresponde

### Implementation for User Story 1

- [X] T024 [P] [US1] Crear los DTO `RegistroComportamientosDTO`, `ListadoComportamientosDTO` y `FiltroComportamientosDTO` (con `estado` nulo = todos) en `backend/app/Modules/Estructura/Application/DTO/`
- [X] T025 [US1] Crear los casos de uso `ListarComportamientos` y `ObtenerComportamientos` en `backend/app/Modules/Estructura/Application/UseCases/`
- [X] T026 [P] [US1] Crear `ListarComportamientosRequest` (`q` máximo 50, `tipo` en `Cardinal,Especifica`, `estado` en `Activo,Inactivo,Todos`, `page` ≥ 1, `per_page` entre 1 y 50; `filtro(bool $esAdministrador)` resuelve el estado efectivo: no administrador → `Activo` siempre; administrador → el pedido o `Activo`; `Todos` → nulo; mensajes en español) en `backend/app/Modules/Estructura/Presentation/Requests/ListarComportamientosRequest.php`
- [X] T027 [P] [US1] Crear `RegistroComportamientosResource` (competencia_id, codigo, nombre, descripcion, tipo, estado) y `RegistroComportamientosDetalleResource` (añade `version` y `comportamientos` con las claves `A`–`D`) en `backend/app/Modules/Estructura/Presentation/Resources/`
- [X] T028 [US1] Crear `ComportamientoController` con `index` (`$request->user()->tokenCan('admin')`) y `show` (404 "El registro de comportamientos no existe.") en `backend/app/Modules/Estructura/Presentation/Controllers/ComportamientoController.php`, y registrar `GET /api/comportamientos` y `GET /api/comportamientos/{competencia_id}` con `auth:sanctum` (sin `DELETE` ni ruta de estado) en `backend/app/Modules/Estructura/Routes/api.php`
- [X] T029 [US1] Modificar `backend/app/Modules/Estructura/Application/Services/EstructuraService.php` (inyectar `ComportamientoRepositoryInterface`) y `backend/app/Modules/Estructura/Presentation/Facades/EstructuraFacade.php` con `getComportamientos`, `getComportamientosPorCompetencias`, `tieneComportamientosActivos` y `getComportamientosActivos` según contracts/estructura-facade.md
- [X] T030 [P] [US1] Crear `backend/database/seeders/ComportamientosSeeder.php` (idempotente por competencia; usa el repositorio con `created_by` = id de `admin@hrms.test` o nulo): registros para `COMP-0001` (4/3/2/1 comportamientos en A/B/C/D), `COMP-0002` (2/2/2/2), `COMP-0004` (1/4/3/2), `COMP-0006` (3/1/4/2) y `COMP-0007` inactiva (2/3/1/4); `COMP-0003` y `COMP-0005` quedan sin registro; llamarlo desde `backend/database/seeders/DatabaseSeeder.php` después de `CompetenciasSeeder`
- [X] T031 [P] [US1] Crear los hooks `useListadoParams` (q, tipo, estado y page en `useSearchParams`, retardo de 300 ms al escribir, `Activo` no se escribe), `useComportamientos` (clave `["comportamientos","lista",{q,tipo,estado,page}]`) y `useComportamiento` (clave `["comportamientos","detalle",id]`, con `siempreFresca`) en `frontend/src/modules/estructura/comportamientos/hooks/`
- [X] T032 [US1] Crear `ComportamientosPanel` (carga el detalle solo al desplegar; cuatro grados con `<ul>`; aviso neutro si la competencia está inactiva) en `frontend/src/modules/estructura/comportamientos/components/ComportamientosPanel.tsx`
- [X] T033 [US1] Crear `ComportamientosTable` (columnas Código, Nombre, Descripción recortada a 2 líneas, Tipo y Estado con `Badge` `Activo` / `neutral` `Inactivo`; botón "Visualizar comportamientos" con `aria-expanded`; contenedor `relative overflow-x-auto`) en `frontend/src/modules/estructura/comportamientos/components/ComportamientosTable.tsx`
- [X] T034 [US1] Crear `ComportamientosPage` según `design/Competencias.dc.html` y `design/Estados.dc.html` (búsqueda por nombre de la competencia, filtro de tipo, filtro de estado solo Administrador, tabla, `Pagination`, estados cargando/vacío/error/éxito) en `frontend/src/modules/estructura/comportamientos/pages/ComportamientosPage.tsx`
- [X] T035 [US1] Crear `frontend/src/modules/estructura/comportamientos/nav.tsx` (ítem "Comportamientos" con `IconList`) y registrar la ruta `/comportamientos` y el ítem en `frontend/src/App.tsx`
- [X] T036 [US1] Crear el E2E de consulta en `frontend/e2e/comportamientos.spec.ts`: el Usuario ve 4 registros activos con Código y Estado, sin `Adaptabilidad`, sin filtro de estado ni botones de gestión; busca, filtra y recarga conservando la URL; despliega "Visualizar comportamientos" y ve A–D con viñetas; el Administrador alterna Activos (4), Inactivos (1) y Todos (5) usando solo las filas de los registros sembrados

**Checkpoint**: US1 funcional: el diccionario se consulta con visibilidad por rol

---

## Phase 4: User Story 2 - Crear un registro de comportamientos (Priority: P1)

**Goal**: El Administrador elige una competencia activa sin registro (con búsqueda) y registra 1–4 comportamientos por grado.

**Independent Test**: Crear el registro de `COMP-0003` y verlo en el listado con sus cuatro grados; con datos inválidos ver errores por campo.

### Tests for User Story 2

- [X] T037 [P] [US2] Crear `backend/tests/Feature/Estructura/Comportamientos/CrearComportamientosTest.php`: 201 con `version` 1 y `created_by`; 401 y 403 al Usuario; `competencia_id` ausente, inexistente, inactiva o con registro → 422 con mensaje propio; grados faltantes, clave extra o repetida → 422; menos de 1 o más de 4 textos → 422; texto vacío, de solo espacios, de 301 caracteres (300 sí) o repetido en el grado (sin distinguir mayúsculas) → 422 con error en `comportamientos.A.2`; dos creaciones de la misma competencia dejan un solo registro; el texto se guarda recortado y el orden es la posición
- [X] T038 [P] [US2] Crear `backend/tests/Feature/Estructura/Comportamientos/CompetenciasDisponiblesTest.php`: 401, 403 al Usuario, 200 al Administrador con solo competencias activas sin registro ordenadas por nombre (sin `Adaptabilidad` ni las que ya tienen), y que una competencia con registro desaparece de la lista
- [X] T039 [P] [US2] Crear `backend/tests/Unit/Estructura/Comportamientos/CrearComportamientosTest.php` para `CrearComportamientos` y `ListarCompetenciasDisponibles` con el doble en memoria
- [X] T040 [P] [US2] Crear `frontend/src/shared/ui/Combobox.dom.test.tsx` y `frontend/src/shared/ui/ListaDeCampos.dom.test.tsx`: filtrar al escribir, flechas/Enter/Escape, "sin resultados", `aria-*`; agregar hasta 4, quitar hasta 1, contador, errores por campo
- [X] T041 [P] [US2] Crear `frontend/src/modules/estructura/comportamientos/validation.test.ts` para el esquema Zod: competencia obligatoria al crear; por grado entre 1 y 4 textos; cada texto obligatorio, recortado, máximo 300 caracteres y distinto de los demás del grado (sin distinguir mayúsculas)
- [X] T042 [P] [US2] Crear `frontend/src/modules/estructura/comportamientos/pages/ComportamientosFormPage.dom.test.tsx` (crear): selector con búsqueda solo de disponibles, al elegir se ven nombre, descripción y tipo en solo lectura, no deja pasar de 4 ni bajar de 1, errores por campo con aviso general, valores conservados, botón bloqueado al guardar, mapeo de 422 (`competencia_id`, `comportamientos.B.1`), "No hay competencias disponibles", y `RequireAdmin` (Usuario sin permisos)

### Implementation for User Story 2

- [X] T043 [P] [US2] Crear `GuardarComportamientosRequest` en `backend/app/Modules/Estructura/Presentation/Requests/GuardarComportamientosRequest.php`: `competencia_id` obligatorio en `POST` (entero, existe, `Activo`, sin registro; mensajes distintos); `comportamientos` con `array:A,B,C,D`; cada grado `array` con `min:1` y `max:4`; cada texto `string`, obligatorio, recortado, `max:300` y único dentro del grado sin distinguir mayúsculas; `version` obligatorio solo en `PUT`; mensajes en español
- [X] T044 [US2] Crear `CrearComportamientos` y `ListarCompetenciasDisponibles` en `backend/app/Modules/Estructura/Application/UseCases/` y el DTO `CompetenciaDisponibleDTO` en `backend/app/Modules/Estructura/Application/DTO/`
- [X] T045 [US2] Añadir `ComportamientoController@store` (201; excepciones de dominio → 422 con `errors`) y `@disponibles`, el `CompetenciaDisponibleResource` en `backend/app/Modules/Estructura/Presentation/Resources/`, y registrar `GET /api/comportamientos/competencias-disponibles` **antes** de `/{competencia_id}` y `POST /api/comportamientos` con `auth:sanctum` y `admin` en `backend/app/Modules/Estructura/Routes/api.php`
- [X] T046 [P] [US2] Crear `frontend/src/modules/estructura/comportamientos/validation.ts` (esquema Zod de T041) y `RequireAdmin` propio en `frontend/src/modules/estructura/comportamientos/components/RequireAdmin.tsx` (rol solo del contrato `modules/auth/login`; sin importar de `competencias`)
- [X] T047 [P] [US2] Crear los hooks `useCompetenciasDisponibles` y `useGuardarComportamientos` (crear; mutación sin reintentos; invalida `["comportamientos"]` sin esperar la recarga) en `frontend/src/modules/estructura/comportamientos/hooks/`
- [X] T048 [P] [US2] Crear `CompetenciaResumen` (nombre, descripción y tipo en solo lectura, más estado cuando se conoce) en `frontend/src/modules/estructura/comportamientos/components/CompetenciaResumen.tsx`
- [X] T049 [US2] Crear `ComportamientosFormPage` en modo crear según `design/Formulario.dc.html` (Breadcrumb, `Combobox` de competencias disponibles, `CompetenciaResumen`, un `fieldset` por grado con `ListaDeCampos`, Alert general "Revisa los campos marcados antes de guardar.", Cancelar y "Guardar") en `frontend/src/modules/estructura/comportamientos/pages/ComportamientosFormPage.tsx`, registrar `/comportamientos/nuevo` bajo `RequireAdmin` en `frontend/src/App.tsx` y mostrar "Nuevo registro" solo al Administrador en `ComportamientosPage.tsx`
- [X] T050 [US2] Añadir el E2E de creación en `frontend/e2e/comportamientos.spec.ts`: el Administrador crea el registro de `COMP-0003` (errores por campo con el formulario incompleto, no pasa de 4 ni baja de 1; en el selector aparecen `Comunicación efectiva` y `Pensamiento analítico` y no aparecen las que ya tienen registro ni `Adaptabilidad`; no se comprueba el número exacto de disponibles, porque `competencias.spec.ts` puede haber añadido otras activas) y lo ve en el listado; el Usuario no ve el botón ni accede a `/comportamientos/nuevo`

**Checkpoint**: US1 + US2: se puede consultar y alimentar el diccionario

---

## Phase 5: User Story 3 - Editar los comportamientos (Priority: P2)

**Goal**: El Administrador modifica los comportamientos de un registro (de competencia activa o inactiva) con la competencia en solo lectura; guardar sin cambios no modifica nada.

**Independent Test**: Editar un registro (cambiar, añadir y quitar), comprobar los cambios y la auditoría; guardar sin cambios y comprobar que `version` y auditoría no cambian; repetir con la competencia inactiva.

### Tests for User Story 3

- [X] T051 [P] [US3] Crear `backend/tests/Feature/Estructura/Comportamientos/ActualizarComportamientosTest.php`: 200 con cambios (`version` +1, `updated_by` = editor, `created_by` intacto, filas cambiadas/añadidas/quitadas); editar con la competencia inactiva → 200 y sigue `Inactivo`; sin cambios reales → 200, misma `version`, sin tocar `updated_at` ni `updated_by`; `version` desactualizada → 409; `version` ausente → 422; `competencia_id` distinto → 422; sin registro → 404; 401 y 403 al Usuario; las mismas reglas de validación que crear
- [X] T052 [P] [US3] Crear `backend/tests/Unit/Estructura/Comportamientos/ActualizarComportamientosTest.php` para `ActualizarComportamientos` con el doble en memoria
- [X] T053 [P] [US3] Crear `frontend/src/modules/estructura/comportamientos/pages/ComportamientosFormPage.edit.dom.test.tsx`: estado cargando, competencia en solo lectura (sin selector), comportamientos actuales por grado, aviso "La competencia asociada está inactiva", envío con `version`, alerta de conflicto 409 con "Recargar", 404 "ya no existe" y guardar sin cambios vuelve al listado sin error

### Implementation for User Story 3

- [X] T054 [US3] Crear `ActualizarComportamientos` en `backend/app/Modules/Estructura/Application/UseCases/ActualizarComportamientos.php`
- [X] T055 [US3] Añadir `ComportamientoController@update` (200; `RegistroModificado` → 409; `RegistroNoEncontrado` → 404; `competencia_id` distinto → 422) y la ruta `PUT /api/comportamientos/{competencia_id}` con `auth:sanctum` y `admin` en `backend/app/Modules/Estructura/Presentation/Controllers/ComportamientoController.php` y `backend/app/Modules/Estructura/Routes/api.php`
- [X] T056 [US3] Extender `useGuardarComportamientos` con la actualización (envía `version`; expone el conflicto) en `frontend/src/modules/estructura/comportamientos/hooks/useGuardarComportamientos.ts`
- [X] T057 [US3] Añadir el modo editar a `ComportamientosFormPage` (lectura fresca con esqueleto, `CompetenciaResumen` sin selector, comportamientos actuales, alerta de conflicto con "Recargar" y 404), registrar `/comportamientos/:competenciaId/editar` bajo `RequireAdmin` en `frontend/src/App.tsx` y mostrar el botón Editar solo al Administrador en `ComportamientosTable.tsx` y mostrar el aviso "La competencia asociada está inactiva" en el formulario cuando la competencia está inactiva (`Alert` neutro)
- [X] T058 [US3] Añadir el E2E de edición en `frontend/e2e/comportamientos.spec.ts`: editar `COMP-0001` (cambiar, añadir y quitar comportamientos), editar el de `COMP-0007` inactiva, guardar sin cambios sin error y el conflicto con una edición concurrente simulada por la API

**Checkpoint**: US1–US3 funcionando de forma independiente

---

## Phase 6: User Story 4 - El estado sigue al de la competencia (Priority: P2)

**Goal**: Desactivar o reactivar la competencia en 001 cambia el registro automáticamente; este diccionario no ofrece activar, desactivar ni eliminar.

**Independent Test**: Desactivar una competencia con registro desde 001 y comprobar que su registro aparece inactivo (con indicación visual) y desaparece del listado del Usuario; reactivarla y comprobar que vuelve.

### Tests for User Story 4

- [X] T059 [P] [US4] Crear `backend/tests/Feature/Estructura/Comportamientos/EstadoDerivadoTest.php`: desactivar la competencia con `PATCH /api/competencias/{id}/estado` hace que su registro salga del listado del Usuario y del listado por defecto del Administrador, aparezca con `estado` `Inactivo` en `?estado=Inactivo` y siga consultable por id para ambos; reactivarla lo devuelve; editar el registro de una competencia inactiva no cambia su estado; ni la tabla ni el modelo del registro tienen columna `estado`
- [X] T060 [P] [US4] Crear `backend/tests/Feature/Estructura/Comportamientos/SinGestionDeEstadoTest.php`: no existe ninguna ruta `DELETE` ni de estado bajo `api/comportamientos` (`DELETE` y `PATCH` responden 404/405 incluso al Administrador) y no se elimina ningún registro
- [X] T061 [P] [US4] Crear `frontend/src/modules/estructura/comportamientos/components/ComportamientosTable.dom.test.tsx`: la fila de una competencia inactiva muestra el badge neutro "Inactivo" y, al desplegarla, el aviso "La competencia asociada está inactiva"; no hay botones Activar, Desactivar ni Eliminar para ningún rol

### Implementation for User Story 4

- [X] T062 [US4] Comprobar que ningún componente de `frontend/src/modules/estructura/comportamientos/` ofrece activar, desactivar ni eliminar y que el aviso de competencia inactiva aparece en tabla, panel y formulario (ajustes solo si faltan)
- [X] T063 [US4] Añadir el E2E de estado derivado en `frontend/e2e/comportamientos.spec.ts`: el Administrador desactiva `Trabajo en equipo` desde `/competencias` (001), el registro aparece inactivo en `/comportamientos?estado=Inactivo` y desaparece del listado del Usuario; al reactivarla vuelve a verse en ambos; el test deja `Trabajo en equipo` activa al terminar aunque falle una comprobación intermedia (con `try/finally`)

**Checkpoint**: Cuatro historias funcionando; el estado nunca se gestiona aquí

---

## Phase 7: User Story 5 - Trazabilidad de los cambios (Priority: P3)

**Goal**: Quién creó, quién editó por última vez y cuándo quedan registrados; los cambios de estado no se registran aquí.

**Independent Test**: Crear y editar con dos administradores distintos e inspeccionar `created_by`, `updated_by`, `version` y las marcas de tiempo; desactivar la competencia y comprobar que no se añade nada en este diccionario.

### Tests for User Story 5

- [X] T064 [P] [US5] Crear `backend/tests/Feature/Estructura/Comportamientos/AuditoriaComportamientosTest.php`: ciclo completo con dos administradores (`created_by` del alta, `updated_by` tras otra edición sin alterar `created_by`, `version` y marcas de tiempo); una edición que solo quita un comportamiento actualiza igualmente `updated_by`; una edición sin cambios no modifica `updated_by`, `updated_at` ni `version`; las filas conservan su `created_by`
- [X] T065 [P] [US5] Crear `backend/tests/Unit/Estructura/Comportamientos/SinHistorialPropioTest.php`: desactivar y reactivar la competencia no escribe ninguna fila en `competencia_comportamiento_registros` ni `competencia_comportamientos`, y no existe tabla ni ruta de historial de comportamientos

**Checkpoint**: Auditoría completa

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Calidad transversal y cierre

- [X] T066 [P] Auditar contraste y foco de `Combobox`, `ListaDeCampos`, viñetas y avisos en ambos temas (pares ≥ 4.5:1, foco visible, orden de tabulación del formulario) y reflejarlo en `frontend/e2e/comportamientos.spec.ts` (foco visible y tema oscuro)
- [X] T067 [P] Verificar sin scroll horizontal a 320 y 1440 px el listado, los comportamientos desplegados y el formulario con los cuatro grados llenos (16 campos) en `frontend/e2e/comportamientos.spec.ts`
- [X] T068 [P] Revisar seguridad: el rol se verifica en el backend en el listado, las escrituras y `competencias-disponibles`; un Usuario nunca recibe registros de competencias inactivas; `competencia_id` distinto en `PUT` se rechaza; los errores no filtran detalles internos y están en español; ningún módulo toca las tablas de comportamientos
- [X] T069 [P] Revisar que `design/DESIGN.md` refleja `Combobox`, `ListaDeCampos` y el aviso de competencia inactiva tal como quedaron implementados
- [X] T070 Reiniciar la base de desarrollo con `php artisan migrate:fresh --seed` en `backend/` y comprobar 5 registros (uno de `COMP-0007` inactiva) y 2 competencias activas disponibles
- [X] T071 Ejecutar las puertas de calidad del backend: `vendor/bin/pint --test` y `vendor/bin/pest` en `backend/`
- [X] T072 Ejecutar las puertas de calidad del frontend: `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `npx playwright test` en `frontend/`
- [X] T073 Ejecutar la validación de `specs/002-diccionario-comportamientos/quickstart.md` de punta a punta

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias; T002 (diseño) precede a T013–T015 y a todo el frontend
- **Foundational (Phase 2)**: depende de Setup; bloquea todas las historias. T007 depende de T005–T006; T009 de T003, T004, T007 y T008; T009b de T009; T010 de T009 y T009b; T012 de T009, T009b, T010 y T011
- **US1 (Phase 3)**: tras Foundational; MVP
- **US2 (Phase 4)**: tras Foundational; el backend es independiente; el frontend se integra con la página de US1
- **US3 (Phase 5)**: tras Foundational; reutiliza `useGuardarComportamientos` y `ComportamientosFormPage` de US2
- **US4 (Phase 6)**: tras US1 (y US3 para la prueba de edición con competencia inactiva); no añade endpoints
- **US5 (Phase 7)**: sus pruebas ejercitan crear (US2) y editar (US3)
- **Polish (Phase 8)**: tras las historias deseadas

### User Story Dependencies

- **US1 (P1)**: sin dependencias de otras historias
- **US2 (P1)**: backend independiente; el frontend usa la página y las rutas de US1
- **US3 (P2)**: backend independiente de US2 salvo la validación compartida (`GuardarComportamientosRequest`, T043); el frontend depende del formulario de US2
- **US4 (P2)**: depende de US1 (listado y detalle) y de la API de 001
- **US5 (P3)**: depende de US2 y US3

### Within Each User Story

- Las pruebas se escriben primero y deben fallar
- Dominio → casos de uso → controlador y rutas; en frontend: hooks → componentes → páginas → rutas
- Los archivos compartidos (`ComportamientoController.php`, `Routes/api.php`, `ComportamientosTable.tsx`, `ComportamientosPage.tsx`, `ComportamientosFormPage.tsx`, `App.tsx`, `comportamientos.spec.ts`) se editan en secuencia entre historias, no en paralelo

### Parallel Opportunities

- Phase 2: T005, T006 y T008 en paralelo; T011–T012 y T013–T018 en paralelo cuando sus dependencias estén hechas
- Cada historia: todas sus pruebas [P] a la vez; DTO/Request/Resource y hooks en paralelo
- Backend y frontend de una historia pueden avanzar en paralelo tras Foundational

---

## Parallel Example: User Story 2

```bash
# Pruebas de US2 en paralelo:
Task: "Feature CrearComportamientosTest en backend/tests/Feature/Estructura/Comportamientos/"
Task: "Feature CompetenciasDisponiblesTest en backend/tests/Feature/Estructura/Comportamientos/"
Task: "dom Combobox y ListaDeCampos en frontend/src/shared/ui/"
Task: "dom ComportamientosFormPage (crear) en frontend/src/modules/estructura/comportamientos/pages/"

# Piezas independientes de US2:
Task: "GuardarComportamientosRequest en backend/app/Modules/Estructura/Presentation/Requests/"
Task: "validation.ts y RequireAdmin en frontend/src/modules/estructura/comportamientos/"
Task: "Hooks useCompetenciasDisponibles y useGuardarComportamientos"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Completar Phase 1 y Phase 2 (incluidos el diseño y las migraciones)
2. Completar US1 (Phase 3)
3. **Detenerse y validar**: el Usuario solo ve registros de competencias activas y el Administrador alterna por estado
4. Añadir US2 para poder alimentar el diccionario (también P1)

### Incremental Delivery

1. Setup + Foundational → modelo, repositorio y componentes listos
2. US1 → validar; US2 → validar (MVP completo: se consulta y se crea)
3. US3 → edición con control de versión y sin cambios reales; US4 → estado derivado de 001
4. US5 → auditoría verificada; Polish: accesibilidad, responsive, seguridad y puertas de calidad

---

## Notes

- [P] = archivos distintos, sin dependencias
- La etiqueta [Story] mapea cada tarea a su historia
- Sin dependencias nuevas: Combobox, ListaDeCampos e `IconList` son componentes propios
- Los datos y el estado de la competencia nunca se copian: siempre `join` a `competencias`
- Confirmar que las pruebas fallan antes de implementar
- Hacer commit tras cada tarea o grupo lógico
- Los dos specs E2E (`competencias.spec.ts` y `comportamientos.spec.ts`) comparten base de datos y corren en orden alfabético con un solo worker; ninguno debe depender de datos creados por el otro ni dejarlos cambiados
