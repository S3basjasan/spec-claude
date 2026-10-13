---

description: "Tareas de la feature 004-catalogo-puestos"
---

# Tasks: Catálogo maestro de puestos

**Input**: Documentos de diseño en `/specs/004-catalogo-puestos/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/puestos-api.md, contracts/estructura-facade.md. Requiere 000-login, 001-diccionario-competencias, 002 y 003 ya implementadas (competencias con `estado` y grados, `Combobox`, `Alert` neutro, `ConfirmDialog` y `Pagination` en `shared/ui`, helpers de prueba y patrones de 001–003).

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario para implementarlas y probarlas de forma independiente. La feature es **nueva** salvo los archivos compartidos indicados como "modificar".

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1–US5)
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Estructura/…` (Domain, Application, Infrastructure, Presentation)
- Frontend: `frontend/src/modules/estructura/puestos/…` (reutiliza `frontend/src/shared/ui/…`)
- Pruebas backend: `backend/tests/{Unit,Feature}/Estructura/Puestos/`
- Pruebas de componentes: `*.dom.test.tsx` junto al código (con `// @vitest-environment jsdom`); E2E en `frontend/e2e/puestos.spec.ts`
- Regla de módulos: `puestos` **no importa nada** de `frontend/src/modules/estructura/{competencias,comportamientos,preguntas}`; el rol llega solo por `frontend/src/modules/auth/login` (`index.ts`). Copiar (no importar) los patrones de 003.
- Valores en base de datos: `estado` de puesto ∈ `Activo` | `Inactivo`; `tipo` de función ∈ `Esencial` | `Complementaria`; `tipo` de requisito ∈ `Formacion` | `Experiencia` | `Capacitacion` (sin tildes; la interfaz muestra con tilde); `estado` de posición ∈ `Ocupada` | `Vacante` | `Suprimida`; `tipo` de competencia ∈ `Cardinal` | `Especifica`
- Reutilizar sin modificar: `CompetenciaAsociada`, `TipoCompetencia`, `EstadoCompetencia` y `GradoLetra` (backend); `Combobox`, `Alert`, `Badge`, `ConfirmDialog`, `Pagination`, `SearchField`, `SelectField`, `TextField`, `TextareaField`, `Breadcrumb`, `EmptyState`, `ErrorState` y `Skeleton` (frontend). Las excepciones de 001–003 tienen mensajes propios de competencias: puestos define las suyas (T013)
- Lecciones de 002 y 003: en E2E localizar los campos con `getByRole('textbox', { name, exact: true })` (el `getByLabel` también casa con el `aria-label` de otros botones); escribir los archivos con la herramienta Write (los heredocs de Bash fallan con acentos)
- Insumo técnico `plan-puestos.md` y plan.md: donde difieren, **prevalecen plan.md y research.md** (capacidad `admin` en lugar de permisos, grado de competencia inactiva bloqueado, auditoría por elemento, columnas `nombre_clave`, `nombre_busqueda` y `version`)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Línea base verde y diseño actualizado antes de tocar el frontend

- [X] T001 Verificar la línea base: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run` y `npx playwright test` en `frontend/` deben pasar antes de empezar (sin cambios de archivos)
- [X] T002 [P] Modificar `design/DESIGN.md` **antes** de cualquier tarea de frontend (constitución III): §4 componentes del formulario de puesto (fila repetible de **tipo y texto** con Quitar, **Subir/Bajar** con anuncio accesible de la nueva posición, fila de competencia con selector, datos de solo lectura y grado, o **grado bloqueado** con aviso si la competencia está inactiva); §5 Listado: la **fila entera** es el botón desplegable (`h2` con botón `aria-expanded` y panel `role="region"`), solo una abierta a la vez, detalle con tres bloques (misión; funciones numeradas con su tipo; requisitos agrupados por tipo; tabla de competencias con tipo y grado), filtro de estado solo Administrador, paginación, botones Editar y Activar/Desactivar dentro del detalle, `ConfirmDialog` de desactivación con el motivo de rechazo; §5 Formulario: cuatro secciones en `fieldset`; §9 diferencias con `design/Puestos.dc.html` ("Clasificación" se muestra como "Tipo"; "Conocimientos" es "Capacitación"; la competencia "Gerencial" no existe en el modelo, solo Cardinal y Específica; las funciones muestran su tipo; los requisitos se agrupan por tipo; el menú del tablero trae Organigrama y Perfilamiento, que pertenecen a otras features y no se implementan aquí). No tocar `design/Puestos.dc.html`. Referenciar (no repetir) `Combobox` y `Alert – neutro` de 002
- [X] T003 [P] Crear `design/PuestosFormulario.dc.html` (tablero del formulario crear/editar de puesto con las cuatro secciones, filas de funciones con Subir/Bajar, requisitos, competencias con grado bloqueado y errores por campo; mismos tokens que `design/Formulario.dc.html`, tema claro y oscuro) y añadir su entrada en `design/canvas.json` (después de `Puestos.dc.html` en `order` y en `boards`, interactivo, 1440 de ancho)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Modelo de datos, dominio, auditoría, repositorio de lectura y piezas de frontend que necesitan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend

- [X] T004 [P] Crear `backend/config/estructura.php` con `'puestos' => ['max_elementos_por_lista' => (int) env('PUESTOS_MAX_ELEMENTOS_POR_LISTA', 20)]` (límite configurable de funciones, requisitos y competencias por puesto; 20 por defecto)
- [X] T005 Crear `backend/database/migrations/2026_10_13_000001_create_puestos_table.php`: `id`; `codigo` `string(20)` **anulable y único** (se asigna `PU-` + `id` con al menos 3 dígitos en la misma transacción); `nombre` `string(50)` con `collation('nocase')`; `nombre_clave` `string(50)` **único** (nombre en minúsculas Unicode y recortado); `nombre_busqueda` `string(50)` **con índice** (nombre sin tildes y en minúsculas); `descripcion` `string(200)`; `mision` `string(200)`; `estado` `string(10)` por defecto `Activo` **con índice** (`Activo` | `Inactivo`); `version` entero sin signo por defecto 1; `created_by` y `updated_by` enteros sin signo anulables **sin clave foránea**; timestamps. Sin soft delete
- [X] T006 Crear `backend/database/migrations/2026_10_13_000002_create_puesto_funciones_table.php`: `id`; `puesto_id` FK a `puestos.id` **sin borrado en cascada** (`restrictOnDelete`); `descripcion` `string(500)`; `tipo` `string(15)` (`Esencial` | `Complementaria`); `orden` entero sin signo (1 a n); restricción **única en `(puesto_id, orden)`**; sin `created_by`, `updated_by` ni timestamps
- [X] T007 Crear `backend/database/migrations/2026_10_13_000003_create_puesto_requisitos_table.php`: `id`; `puesto_id` FK a `puestos.id` **sin borrado en cascada** (`restrictOnDelete`); `tipo` `string(15)` (`Formacion` | `Experiencia` | `Capacitacion`, sin tildes); `descripcion` `string(500)`; sin columna de orden, sin `created_by`, `updated_by` ni timestamps
- [X] T008 Crear `backend/database/migrations/2026_10_13_000004_create_puesto_competencias_table.php`: `id`; `puesto_id` FK a `puestos.id` **sin borrado en cascada** (`restrictOnDelete`); `competencia_id` FK a `competencias.id` **sin borrado en cascada** (`restrictOnDelete`); `grado_requerido` `string(1)` (`A`–`D`; que exista para esa competencia en `competencia_grados` se valida en la aplicación, sin FK compuesta); restricción **única en `(puesto_id, competencia_id)`**; sin `created_by`, `updated_by` ni timestamps
- [X] T009 Crear `backend/database/migrations/2026_10_13_000005_create_posiciones_table.php`: tabla **mínima** reservada para la feature 005: `id`; `puesto_id` FK a `puestos.id` **sin borrado en cascada** (`restrictOnDelete`); `estado` `string(10)` **con índice** (`Ocupada` | `Vacante` | `Suprimida`); timestamps. Comentario en la migración: "005 amplía esta tabla con migraciones propias; no recrearla"
- [X] T010 [P] Crear `backend/database/migrations/2026_10_13_000006_create_auditoria_cambios_table.php`: historial **de solo inserción**: `id`; `entidad` `string(30)` (`puesto` en esta feature); `entidad_id` entero sin signo, **índice compuesto `(entidad, entidad_id)`**; `campo` `string(30)`; `valor_anterior` `text` anulable; `valor_nuevo` `text` anulable; `user_id` entero sin signo anulable **sin clave foránea**; solo `created_at` (sin `updated_at`)
- [X] T011 [P] Crear los enums de dominio `EstadoPuesto` (`Activo`, `Inactivo`; método `opuesto()`), `TipoFuncion` (`Esencial`, `Complementaria`), `TipoRequisito` (`Formacion`, `Experiencia`, `Capacitacion`; método `etiqueta()` con tilde: "Formación", "Experiencia", "Capacitación"; método `orden()` 1–3) y `EstadoPosicion` (`Ocupada`, `Vacante`, `Suprimida`; método `enUso()` verdadero para `Ocupada` y `Vacante`) en `backend/app/Modules/Estructura/Domain/ValueObjects/`
- [X] T012 [P] Crear los value objects `FuncionPuesto` (`orden`, `descripcion`, `TipoFuncion`), `RequisitoPuesto` (`TipoRequisito`, `descripcion`), `CompetenciaConGrados` (`CompetenciaAsociada` de 002 + `grados` como mapa letra → descripción), `CompetenciaDePuesto` (`CompetenciaAsociada` + `gradoRequerido`), `CambioAuditado` (`campo`, `valorAnterior` y `valorNuevo` anulables), `AsignacionDeCompetencia` (`competenciaId`, `grado`), `DatosPuesto` (`nombre`, `descripcion`, `mision` y las listas `funciones` como `FuncionPuesto` con `orden` = posición + 1, `requisitos` como `RequisitoPuesto` y `competencias` como `AsignacionDeCompetencia`: es la entrada de los casos de uso y del repositorio, de modo que Domain y Application no dependen de ningún DTO ni Request), `InstantaneaDePuesto` (`codigo`, `nombre`, `descripcion`, `mision`, `estado`, `funciones`, `requisitos` y `competencias` como `CompetenciaDePuesto` con código, nombre y grado: el estado que compara el servicio de auditoría) y `PaginaPuestos` (items, total) en `backend/app/Modules/Estructura/Domain/ValueObjects/`
- [X] T013 [P] Crear las excepciones finales (`RuntimeException`) en `backend/app/Modules/Estructura/Domain/Exceptions/`: `PuestoNoEncontrado` ("El puesto no existe."), `PuestoModificado` ("El puesto cambió desde que lo abriste. Recarga para ver la versión actual."), `NombreDePuestoDuplicado` ("Ya existe un puesto con ese nombre."), `PuestoConPosicionesEnUso` (recibe la cantidad; mensaje "No se puede desactivar el puesto: tiene N posiciones ocupadas o vacantes." con singular para 1; expone `cantidad`) y `CompetenciasDePuestoInvalidas` (recibe un mapa clave de error → mensaje, p. ej. `competencias.2.grado_requerido`)
- [X] T014 Crear la entidad `backend/app/Modules/Estructura/Domain/Entities/Puesto.php` (`id`, `codigo`, `nombre`, `descripcion`, `mision`, `EstadoPuesto`, `version`, `creadoPor`, `modificadoPor` anulables, y las listas `funciones`, `requisitos` y `competencias`; en el listado las listas van vacías)
- [X] T015 Crear el servicio de dominio puro `backend/app/Modules/Estructura/Domain/Services/ComparadorDeCambiosDePuesto.php` (sin Laravel ni Eloquent): `comparar(?InstantaneaDePuesto $anterior, InstantaneaDePuesto $nuevo): array<CambioAuditado>` (el repositorio construye ambas instantáneas; `$anterior` nulo = creación) según research.md D-07 y data-model.md ("Qué se registra y cómo"): una fila por campo cambiado (`codigo`, `nombre`, `descripcion`, `mision`, `estado`); una fila por **elemento** en `funciones` (clave = descripción normalizada en minúsculas y recortada; valor `«Tipo»: «descripción»`; mismo texto con otro tipo = una modificación), `requisitos` (clave = tipo + descripción normalizada; valor `«Tipo»: «descripción»`; cualquier cambio = baja + alta) y `competencias` (clave = `competencia_id`; valor `«código» «nombre»: grado «X»`; otro grado = una modificación); con anterior vacío (creación) todo es alta con valor anterior nulo; **el orden de las funciones y la numeración derivada se ignoran** (reordenar no genera filas); sin diferencias devuelve lista vacía
- [X] T016 [P] Crear `backend/tests/Unit/Estructura/Puestos/ComparadorDeCambiosDePuestoTest.php`: creación con todas las filas y valor anterior nulo (incluidos `codigo` y `estado` `Activo`); cambio de nombre, descripción y misión; alta, baja y modificación de tipo de una función; cambiar el texto de una función = baja + alta; reordenar funciones o quitar una y renumerar las demás → solo la fila de la baja; requisitos (cambio de texto = baja + alta; mismo texto en tipos distintos son elementos distintos); competencias (alta, baja, cambio de grado); sin diferencias → lista vacía; comparación sin distinguir mayúsculas ni espacios alrededor en las claves
- [X] T017 Crear `backend/app/Modules/Estructura/Domain/Repositories/PuestoRepositoryInterface.php`: `listar(?string $q, ?EstadoPuesto $estado, int $pagina, int $porPagina): PaginaPuestos`, `obtener(int $id): ?Puesto`, `obtenerPorIds(array $ids): array`, `obtenerActivos(): array`, `competenciasDisponibles(): array` (competencias `Activo` como `CompetenciaConGrados`, por nombre), `competenciasPorIds(array $ids): array` (mapa id → `CompetenciaConGrados`, incluidas las inactivas, para validar), `crear(DatosPuesto $datos, ?int $actorId): Puesto`, `actualizar(int $id, int $versionEsperada, DatosPuesto $datos, ?int $actorId): Puesto` y `cambiarEstado(int $id, EstadoPuesto $nuevo, ?int $actorId): Puesto`
- [X] T018 [P] Crear los modelos Eloquent `PuestoModel` (tabla de T005; relaciones `funciones()` ordenadas por `orden`, `requisitos()`, `competenciasAsignadas()` y `posiciones()`; helpers estáticos `claveDeNombre()` —minúsculas Unicode y recortado, como `CompetenciaModel::claveDeNombre`— y `busquedaDeTexto()` —sin tildes con `Str::ascii`, en minúsculas y recortado—), `PuestoFuncionModel`, `PuestoRequisitoModel`, `PuestoCompetenciaModel` (relación `competencia()`), `PosicionModel` y `AuditoriaCambioModel` (`UPDATED_AT = null`) en `backend/app/Modules/Estructura/Infrastructure/Persistence/Eloquent/Models/`, y modificar `CompetenciaModel.php` (+ `puestoCompetencias()` hasMany); **sin columnas nuevas** en `competencias` ni `competencia_grados`
- [X] T019 Crear `backend/app/Modules/Estructura/Infrastructure/Persistence/Eloquent/Repositories/EloquentPuestoRepository.php` con las **lecturas**: `listar` (filtra `q` por `codigo` en minúsculas **o** por `nombre_busqueda` con `LIKE` escapando `%`, `_` y `\` y normalizando el texto buscado con `PuestoModel::busquedaDeTexto`; `estado` opcional; orden `nombre` ascendente; sin cargar listas hijas), `obtener` (carga funciones por `orden`, requisitos por tipo y luego `id`, y competencias por nombre con `join` en vivo a `competencias`), `obtenerPorIds`, `obtenerActivos` (por nombre), `competenciasDisponibles` (activas con sus cuatro grados), `competenciasPorIds`, el método privado `registrarCambios(int $puestoId, array $cambios, ?int $actorId)` que inserta en `auditoria_cambios` con `entidad = 'puesto'`, y el método privado `instantanea(int $puestoId): InstantaneaDePuesto` (estado actual con código y nombre de cada competencia leídos en vivo, para comparar en la auditoría)
- [X] T020 Registrar el binding `PuestoRepositoryInterface` → `EloquentPuestoRepository` en `backend/app/Modules/Estructura/Infrastructure/Providers/EstructuraServiceProvider.php`
- [X] T021 [P] Crear `backend/database/factories/PuestoFactory.php` (puesto con funciones —una de ellas `Esencial`—, requisitos de `Formacion` y `Experiencia` y una competencia activa; estados `inactivo()` y `conPosiciones(ocupadas, vacantes, suprimidas)`), el doble `backend/tests/Unit/Estructura/Puestos/RepositorioPuestosEnMemoria.php` y los helpers `datosPuesto()`, `puestoDe()` y `posicionesDe()` en `backend/tests/Pest.php`
- [X] T022 [P] Crear `backend/tests/Feature/Estructura/Puestos/RepositorioPuestosTest.php` (lecturas y restricciones de base de datos): `codigo` único; `nombre_clave` único (otra caja o espacios alrededor chocan); `(puesto_id, orden)` único; `(puesto_id, competencia_id)` único; las FK hacia `puestos` y `competencias` son `restrictOnDelete` (no se borra un puesto con hijas ni una competencia asignada); `listar` por nombre, con `q` por código y por nombre sin tildes ni mayúsculas, `%` y `_` literales, y por estado; `obtener` devuelve funciones por `orden`, requisitos por tipo y competencias con datos en vivo; `competenciasDisponibles` solo activas con cuatro grados; `competenciasPorIds` incluye inactivas

### Frontend

- [X] T023 [P] Crear `frontend/src/modules/estructura/puestos/api.ts`: tipos propios (`EstadoPuesto`, `FiltroEstado`, `TipoFuncion`, `TipoRequisito`, `TipoCompetencia`, `PuestoResumen`, `PuestoDetalle` con `funciones`, `requisitos` y `competencias`, `CompetenciaDisponible` con `grados`, `PaginaPuestos`, `DatosPuesto`) y funciones `listarPuestos`, `obtenerPuesto`, `listarCompetenciasDisponibles`, `crearPuesto(datos)`, `actualizarPuesto(id, datos, version)` y `cambiarEstadoPuesto(id, estado)` sobre el cliente `http` compartido
- [X] T024 [P] Crear `frontend/src/modules/estructura/puestos/text.ts` con todos los textos en español ("Puestos", "Nuevo puesto", "Editar puesto", "Guardar puesto", "Buscar por código o nombre", "Ningún puesto coincide con la búsqueda.", columnas, estados, filtro de estado, "Misión", "Funciones", "Requisitos", "Competencias", "Grado requerido", tipos con tilde, "Esencial"/"Complementaria", "Agregar función/requisito/competencia", "Quitar", "Subir función N"/"Bajar función N", "El código se asigna al guardar", aviso de competencia inactiva y de grado bloqueado, diálogo de desactivación, "Activar"/"Desactivar", mensajes de validación, conflicto y "ya no existe", "No hay competencias disponibles", máximo de 20, y el vacío del Administrador con el filtro por defecto: "No hay puestos activos. Cambia el filtro para ver también los inactivos.")
- [X] T025 [P] Crear `frontend/src/test/puestosFixtures.ts` (`puesto()`, `detalle()`, `pagina()`, `competenciaDisponible()`, `funcion()`, `requisito()`, `competenciaAsignada()`)
- [X] T026 [P] Añadir `IconFileText` (SVG propio, trazo 1.75, `aria-hidden`, el documento del menú de `design/Puestos.dc.html`) a `frontend/src/shared/ui/icons.tsx`

**Checkpoint**: Base lista; las historias pueden empezar

---

## Phase 3: User Story 1 - Consultar el catálogo y el detalle de un puesto (Priority: P1) 🎯 MVP

**Goal**: Cualquier persona autenticada ve el listado (activos por defecto), busca por código o nombre y despliega el detalle completo de una fila; el Administrador filtra por estado.

**Independent Test**: Con la base sembrada, entrar como Usuario y como Administrador, buscar, desplegar un puesto y comprobar lo que ve cada rol.

### Tests for User Story 1

- [X] T027 [P] [US1] Crear `backend/tests/Feature/Estructura/Puestos/ConsultarPuestosTest.php`: 401 sin sesión; el Usuario recibe solo `Activo` aunque envíe `estado=Todos` o `Inactivo`; el Administrador por defecto `Activo`, `Inactivo` y `Todos`; `estado` o `per_page` inválidos → 422; `q` por código (`pu-00`) y por nombre sin distinguir tildes ni mayúsculas (`gestion` encuentra un puesto llamado «Ejecutivo de gestión comercial»), con `%` literal; orden por nombre; paginación (10 por defecto, máximo 50); el listado trae solo `id`, `codigo`, `nombre`, `descripcion` y `estado`; el detalle trae `mision`, `version`, funciones por `orden`, requisitos por tipo y competencias con `codigo`, `nombre`, `tipo`, `estado` y `grado_requerido`; el detalle de un puesto inactivo responde 200 a ambos roles; id inexistente → 404; el nombre de una competencia cambiado en 001 se refleja de inmediato; desactivar en 001 una competencia asignada no cambia el estado del puesto (sigue `Activo`) y el detalle la muestra `Inactivo` con su grado
- [X] T028 [P] [US1] Crear `backend/tests/Unit/Estructura/Puestos/ConsultarPuestosTest.php` para `ListarPuestos` y `ObtenerPuesto` con el doble en memoria
- [X] T029 [P] [US1] Crear `backend/tests/Feature/Estructura/Puestos/EstructuraFacadePuestosTest.php`: `getPuesto` (incluye inactivos, `null` si no existe, solo arreglos con los campos del contrato), `getPuestosPorIds`, `existePuesto`, `puestoEstaActivo` (activo, inactivo, inexistente) y `getPuestosActivos` ordenados por nombre con `{id, codigo, nombre, descripcion}`
- [X] T030 [P] [US1] Crear `frontend/src/modules/estructura/puestos/pages/PuestosPage.dom.test.tsx`: estados cargando, vacío, sin resultados ("Ningún puesto coincide con la búsqueda."), error con "Reintentar" y éxito; columnas Código, Nombre, Descripción y Estado (badge Activo/Inactivo); el Usuario no ve filtro de estado, "Nuevo puesto" ni botones de gestión y no envía `estado`; el Administrador sí ve el filtro y, con todos los puestos inactivos y el filtro por defecto, el vacío le sugiere ver también los inactivos (al Usuario no); búsqueda, estado y página en la URL (`Activo` no se escribe); no existe ningún botón Eliminar
- [X] T031 [P] [US1] Crear `frontend/src/modules/estructura/puestos/components/PuestoDetalle.dom.test.tsx`: el detalle se pide solo al desplegar; muestra misión, funciones como lista numerada con su tipo, requisitos agrupados por tipo (Formación, Experiencia, Capacitación) y tabla de competencias con competencia, tipo y grado requerido; la competencia inactiva se señala; solo una fila abierta a la vez; estados de carga y de error con reintento y 404 "ya no existe"

### Implementation for User Story 1

- [X] T032 [P] [US1] Crear los DTO `PuestoDTO`, `ListadoPuestosDTO` y `FiltroPuestosDTO` (con `estado` nulo = todos) en `backend/app/Modules/Estructura/Application/DTO/`
- [X] T033 [US1] Crear los casos de uso `ListarPuestos` y `ObtenerPuesto` en `backend/app/Modules/Estructura/Application/UseCases/`
- [X] T034 [P] [US1] Crear `ListarPuestosRequest` (`q` máximo 50, `estado` en `Activo,Inactivo,Todos`, `page` ≥ 1, `per_page` entre 1 y 50; `filtro(bool $esAdministrador)` resuelve el estado efectivo: no administrador → `Activo` siempre; administrador → el pedido o `Activo`; `Todos` → nulo; mensajes en español) en `backend/app/Modules/Estructura/Presentation/Requests/ListarPuestosRequest.php`
- [X] T035 [P] [US1] Crear `PuestoResource` (id, codigo, nombre, descripcion, estado) y `PuestoDetalleResource` (añade `mision`, `version`, `funciones`, `requisitos` y `competencias` según contracts/puestos-api.md) en `backend/app/Modules/Estructura/Presentation/Resources/`
- [X] T036 [US1] Crear `PuestoController` con `index` (`$request->user()->tokenCan('admin')`) y `show` (404 "El puesto no existe.") en `backend/app/Modules/Estructura/Presentation/Controllers/PuestoController.php`, y registrar `GET /api/puestos` y `GET /api/puestos/{puestoId}` (`whereNumber`) con `auth:sanctum` (sin `DELETE`) en `backend/app/Modules/Estructura/Routes/api.php`
- [X] T037 [US1] Modificar `backend/app/Modules/Estructura/Application/Services/EstructuraService.php` (inyectar `PuestoRepositoryInterface`) y `backend/app/Modules/Estructura/Presentation/Facades/EstructuraFacade.php` con `getPuesto`, `getPuestosPorIds`, `existePuesto`, `puestoEstaActivo` y `getPuestosActivos` según contracts/estructura-facade.md (actualizar los docblocks)
- [X] T038 [P] [US1] Crear `backend/database/seeders/PuestosSeeder.php` (idempotente por `nombre_clave`; escribe por Eloquent con `created_by` = id de `admin@hrms.test` o nulo y asigna `codigo` = `sprintf('PU-%03d', id)`; competencias por su `codigo` `COMP-000N`) con los seis puestos de data-model.md: **Analista de datos** (Activo, sin posiciones), **Jefe de talento humano** (Activo, 2 posiciones `Ocupada` y 1 `Vacante`), **Desarrollador de software** (Activo, 2 `Suprimida`), **Ejecutivo de gestión comercial** (Activo, con `COMP-0007` inactiva asignada; es el único nombre sembrado con tilde, para probar la búsqueda sin tildes), **Asistente contable** (Inactivo, 1 `Suprimida`) y **Coordinador de operaciones** (Inactivo, sin posiciones); cada uno con descripción ≤ 200, misión ≤ 200, funciones `Esencial` y `Complementaria`, requisitos de `Formacion` y `Experiencia` (algunos `Capacitacion`) y competencias con grados válidos; llamarlo desde `backend/database/seeders/DatabaseSeeder.php` después de `PreguntasSeeder`
- [X] T039 [P] [US1] Crear `useListadoParams.ts` (copia de 003: q, estado y page en `useSearchParams`, `Activo` no se escribe, retardo de 300 ms al escribir y vuelta a la página 1 al cambiar filtros) y `usePuestos.ts`, que exporta `puestosKey`, `usePuestos` (clave `["puestos","lista",{q,estado,page}]`), `usePuesto` (clave `["puestos","detalle",id]`, con `siempreFresca`) y `useCompetenciasDisponibles` (clave `["puestos","competencias-disponibles"]`, `refetchOnMount: 'always'`), en `frontend/src/modules/estructura/puestos/hooks/`
- [X] T040 [US1] Crear `PuestoDetalle` (carga el detalle solo al desplegar; secciones Misión, Funciones con `<ol>` y tipo de cada una, Requisitos agrupados por tipo, y tabla de Competencias con competencia, tipo y grado requerido en insignia; indicador de competencia inactiva y de puesto inactivo; estados de carga y error) en `frontend/src/modules/estructura/puestos/components/PuestoDetalle.tsx`
- [X] T041 [US1] Crear `PuestoFila` (botón de toda la fila con `aria-expanded` y `aria-controls`, código, nombre, descripción y `Badge` `Activo` / `neutral` `Inactivo`, chevron) y `PuestosList` (cabecera de columnas, una sola fila abierta a la vez, mensaje "Ningún puesto coincide con la búsqueda.") en `frontend/src/modules/estructura/puestos/components/`
- [X] T042 [US1] Crear `PuestosPage` según `design/Puestos.dc.html` y `design/Estados.dc.html` (encabezado, búsqueda por código o nombre, filtro de estado solo Administrador, `PuestosList`, `Pagination`, estados cargando/vacío/error/éxito) en `frontend/src/modules/estructura/puestos/pages/PuestosPage.tsx`
- [X] T043 [US1] Crear `frontend/src/modules/estructura/puestos/nav.tsx` (ítem "Puestos" con `IconFileText`) y registrar la ruta `/puestos` y el ítem en `frontend/src/App.tsx` (después de `preguntasNav`)
- [X] T044 [US1] Crear el E2E de consulta en `frontend/e2e/puestos.spec.ts`: el Usuario ve los 4 puestos activos, sin filtro de estado ni botones de gestión; busca por código y por nombre sin tildes (`gestion` encuentra «Ejecutivo de gestión comercial»); despliega un puesto y ve misión, funciones numeradas, requisitos por tipo y competencias; abrir otro cierra el primero; el Administrador alterna Activos (4), Inactivos (2) y Todos (6) usando solo las filas sembradas; el Usuario no puede escribir por la API (403)

**Checkpoint**: MVP consultable

---

## Phase 4: User Story 2 - Crear un puesto (Priority: P1)

**Goal**: El Administrador crea un puesto con sus datos, funciones, requisitos y competencias; el sistema asigna el código y lo crea Activo.

**Independent Test**: Crear un puesto válido y verlo en el listado con su código, estado Activo y todo su detalle.

### Tests for User Story 2

- [X] T045 [P] [US2] Crear `backend/tests/Feature/Estructura/Puestos/CrearPuestoTest.php`: 201 con `codigo` `PU-###` (con un puesto previo de `id` 999, el siguiente se crea como `PU-1000`), `estado` `Activo`, `version` 1, `created_by`, `orden` de funciones 1..n por posición y textos recortados; 401 y 403 al Usuario; `nombre` (50 sí, 51 no), `descripcion` (200 sí, 201 no) y `mision` (200 sí, 201 no) obligatorios y de solo espacios → 422 por campo; nombre repetido (otra caja, espacios alrededor o igual al de un puesto inactivo) → 422 en `nombre`; sin funciones o sin ninguna `Esencial` → 422 en `funciones`; función repetida → 422 en `funciones.N.descripcion`; descripción de 501 caracteres → 422; sin requisito de `Formacion` o de `Experiencia` → 422 en `requisitos`; requisito repetido del mismo tipo → 422, el mismo texto en tipos distintos → 201; sin competencias, competencia repetida, inexistente o inactiva, grado que no existe para la competencia → 422 en `competencias.N.competencia_id` o `.grado_requerido`; más de 20 funciones, requisitos o competencias → 422; dos creaciones con el mismo nombre dejan un solo puesto
- [X] T046 [P] [US2] Crear `backend/tests/Feature/Estructura/Puestos/CompetenciasDisponiblesPuestosTest.php`: 401, 403 al Usuario, 200 al Administrador con solo competencias activas con sus cuatro grados ordenadas por nombre (sin `Adaptabilidad`), y que la ruta no se interprete como id
- [X] T047 [P] [US2] Crear `backend/tests/Unit/Estructura/Puestos/ReglasDeCompetenciasDePuestoTest.php` (casos de creación): competencia inexistente, inactiva o con grado inexistente → errores por fila con la clave `competencias.N.competencia_id` o `.grado_requerido` y mensajes en español; varias filas inválidas devuelven todos los errores; competencias válidas pasan
- [X] T048 [P] [US2] Crear `backend/tests/Unit/Estructura/Puestos/CrearPuestoTest.php` para `CrearPuesto` y `ListarCompetenciasParaPuestos` con el doble en memoria
- [X] T049 [P] [US2] Crear `frontend/src/modules/estructura/puestos/validation.test.ts` para el esquema Zod: nombre ≤ 50, descripción ≤ 200 y misión ≤ 200 obligatorios y recortados; funciones de 1 a 20 con ≥ 1 `Esencial` y sin descripciones repetidas (sin distinguir mayúsculas ni espacios alrededor); requisitos de 1 a 20 con ≥ 1 de Formación y ≥ 1 de Experiencia y sin repetir descripción dentro de un tipo; competencias de 1 a 20, sin repetir y con grado obligatorio; descripciones ≤ 500
- [X] T050 [P] [US2] Crear `frontend/src/modules/estructura/puestos/pages/PuestoFormPage.dom.test.tsx` (crear): cuatro secciones, código "se asigna al guardar", contadores `n/máximo`, errores por campo con aviso general "Revisa los campos marcados antes de guardar.", valores conservados al corregir, botón bloqueado mientras guarda, mapeo de 422 (`nombre`, `funciones.1.descripcion`, `competencias.0.grado_requerido`), "No hay competencias disponibles", y `RequireAdmin` (Usuario sin permisos)
- [X] T051 [P] [US2] Crear `frontend/src/modules/estructura/puestos/components/FieldsetsDePuesto.dom.test.tsx`: `FuncionesFieldset` (agregar, quitar, Subir/Bajar con `aria-label` "Subir función N"/"Bajar función N", deshabilitados en los extremos, anuncio `aria-live` de la nueva posición, numeración desde 1, Agregar deshabilitado al llegar a 20), `RequisitosFieldset` (agregar y quitar) y `CompetenciasFieldset` (el selector solo ofrece activas aún no elegidas, el grado solo ofrece los de la competencia elegida y se reinicia al cambiarla, datos de solo lectura de la competencia)

### Implementation for User Story 2

- [X] T052 [P] [US2] Crear `GuardarPuestoRequest` en `backend/app/Modules/Estructura/Presentation/Requests/GuardarPuestoRequest.php` (crear y actualizar): `nombre` obligatorio `max:50`, `descripcion` obligatoria `max:200`, `mision` obligatoria `max:200`; `funciones` arreglo de `min:1` y `max` desde `config('estructura.puestos.max_elementos_por_lista')`, cada una con `descripcion` obligatoria `max:500` y `tipo` en `Esencial,Complementaria`, con al menos una `Esencial` y sin descripciones repetidas (sin distinguir mayúsculas ni espacios alrededor, en `withValidator`); `requisitos` análogo con `tipo` en `Formacion,Experiencia,Capacitacion`, al menos uno de `Formacion` y uno de `Experiencia`, y sin repetir descripción dentro del mismo tipo; `competencias` `min:1`, mismo máximo, cada una con `competencia_id` entero y `grado_requerido` en `A,B,C,D`, sin `competencia_id` repetido; `version` obligatoria solo en `PUT`; `codigo` opcional solo en `PUT`; mensajes en español; método `datos(): DatosPuesto` (value object de dominio de T012) con textos recortados y el orden recibido
- [X] T053 [P] [US2] Crear `CompetenciaParaPuestoDTO` en `backend/app/Modules/Estructura/Application/DTO/` y la regla de dominio pura `backend/app/Modules/Estructura/Domain/Services/ReglasDeCompetenciasDePuesto.php`: `validar(array $solicitadas, array $existentes, array $asignadasActuales): void` lanza `CompetenciasDePuestoInvalidas` con una clave por fila (`competencias.N.competencia_id`: "La competencia no existe o no está activa."; `competencias.N.grado_requerido`: "Ese grado no existe para la competencia." y, para una inactiva ya asignada con otro grado, "La competencia está inactiva: su grado no se puede cambiar. Quítala o déjala como estaba."); una competencia inactiva solo se acepta si ya estaba asignada y con el mismo grado
- [X] T054 [US2] Crear los casos de uso `CrearPuesto` (obtiene `competenciasPorIds`, aplica `ReglasDeCompetenciasDePuesto`, llama a `crear` con el `DatosPuesto` recibido) y `ListarCompetenciasParaPuestos` en `backend/app/Modules/Estructura/Application/UseCases/`
- [X] T055 [US2] Añadir `crear` a `EloquentPuestoRepository.php` en una transacción: inserta el puesto con `estado` `Activo`, `version` 1, `created_by`, `nombre_clave` y `nombre_busqueda` y `codigo` nulo, asigna `codigo = sprintf('PU-%03d', $id)` en la misma transacción, inserta funciones (`orden` = posición + 1), requisitos y competencias, y escribe en `auditoria_cambios` las filas de creación: construye la `InstantaneaDePuesto` nueva (con código y nombre en vivo de cada competencia) y la compara con `ComparadorDeCambiosDePuesto` contra `null` (valor anterior nulo); la violación del índice único de `nombre_clave` → `NombreDePuestoDuplicado`
- [X] T056 [P] [US2] Crear `CompetenciaParaPuestoResource` (`competencia_id`, `codigo`, `nombre`, `tipo`, `grados` con `{grado, descripcion}`) en `backend/app/Modules/Estructura/Presentation/Resources/`
- [X] T057 [US2] Añadir `PuestoController@store` (201; `NombreDePuestoDuplicado` → 422 con `errors.nombre`; `CompetenciasDePuestoInvalidas` → 422 con sus claves) y `@disponibles`, y registrar `GET /api/puestos/competencias-disponibles` **antes** de `/{puestoId}` y `POST /api/puestos` con `auth:sanctum` y `admin` en `backend/app/Modules/Estructura/Presentation/Controllers/PuestoController.php` y `backend/app/Modules/Estructura/Routes/api.php`
- [X] T058 [P] [US2] Crear `frontend/src/modules/estructura/puestos/validation.ts` (esquema Zod de T049) y `RequireAdmin` propio en `frontend/src/modules/estructura/puestos/components/RequireAdmin.tsx` (rol solo del contrato `modules/auth/login`)
- [X] T059 [P] [US2] Crear el hook `useGuardarPuesto` (crear; mutación sin reintentos; invalida `["puestos"]` sin esperar la recarga) en `frontend/src/modules/estructura/puestos/hooks/useGuardarPuesto.ts`
- [X] T060 [P] [US2] Crear `FilaTipoTexto` (selector de tipo, texto con contador `n/500`, botón Quitar con `aria-label`, error por campo; botones opcionales Subir/Bajar) en `frontend/src/modules/estructura/puestos/components/FilaTipoTexto.tsx`
- [X] T061 [US2] Crear `FuncionesFieldset` (`useFieldArray`, numeración desde 1, Agregar deshabilitado en 20, Subir/Bajar con anuncio `aria-live`) y `RequisitosFieldset` (agregar y quitar, mismo máximo) en `frontend/src/modules/estructura/puestos/components/`
- [X] T062 [US2] Crear `CompetenciasFieldset` (`Combobox` de competencias activas aún no elegidas, datos de solo lectura —código, tipo y estado—, `SelectField` de grados de la competencia elegida que se reinicia al cambiarla, Quitar, Agregar deshabilitado en 20) en `frontend/src/modules/estructura/puestos/components/CompetenciasFieldset.tsx`
- [X] T063 [US2] Crear `PuestoFormPage` en modo crear según `design/PuestosFormulario.dc.html` y `design/Formulario.dc.html` (Breadcrumb, cuatro secciones en `fieldset`, código "se asigna al guardar", Alert general "Revisa los campos marcados antes de guardar.", Cancelar y "Guardar puesto"; ante el 422 de competencia no disponible invalidar `["puestos","competencias-disponibles"]`) en `frontend/src/modules/estructura/puestos/pages/PuestoFormPage.tsx`, registrar `/puestos/nuevo` bajo `RequireAdmin` en `frontend/src/App.tsx` y mostrar "Nuevo puesto" solo al Administrador en `PuestosPage.tsx`
- [X] T064 [US2] Añadir el E2E de creación en `frontend/e2e/puestos.spec.ts`: el Administrador crea un puesto completo (errores por campo con el formulario incompleto, con una función repetida y con ninguna función Esencial; reordena funciones con Subir/Bajar; el selector no ofrece `Adaptabilidad`; no se comprueba el número exacto de competencias disponibles porque `competencias.spec.ts` puede haber añadido otras) y lo ve en el listado con código `PU-###` y estado Activo; el Usuario no ve el botón ni accede a `/puestos/nuevo`

**Checkpoint**: Consulta y creación funcionan

---

## Phase 5: User Story 3 - Editar un puesto (Priority: P2)

**Goal**: El Administrador modifica datos, funciones, requisitos y competencias de un puesto (activo o inactivo); el código y el estado no cambian por esta vía.

**Independent Test**: Editar un puesto activo y uno inactivo, guardar y comprobar los cambios, que el código y el estado son los mismos y quién lo modificó.

### Tests for User Story 3

- [X] T065 [P] [US3] Crear `backend/tests/Feature/Estructura/Puestos/ActualizarPuestoTest.php`: 200 con cambios (`version` +1, `updated_by` = editor, `created_by`, `codigo` y `estado` intactos, filas hijas reemplazadas con `orden` 1..n); editar un puesto inactivo → 200 y sigue `Inactivo`; sin cambios reales → 200, misma `version`, sin tocar `updated_at` ni `updated_by` (ni con espacios alrededor); solo reordenar funciones → 200, `version` +1, nuevo orden, `updated_by` actualizado; `version` desactualizada → 409; `version` ausente → 422; `codigo` distinto → 422; sin puesto → 404; 401 y 403 al Usuario; mantener su propio nombre (también con otra caja) → 200 y nombre de **otro** puesto → 422 en `nombre`; competencia inactiva ya asignada: mantenerla con el mismo grado → 200, con otro grado → 422 en `competencias.N.grado_requerido`, quitarla → 200 y no se puede volver a agregar; agregar una competencia inactiva nueva → 422; las mismas reglas de validación que crear
- [X] T066 [P] [US3] Crear `backend/tests/Unit/Estructura/Puestos/ActualizarPuestoTest.php` para `ActualizarPuesto` con el doble en memoria, y ampliar `ReglasDeCompetenciasDePuestoTest.php` con los casos de actualización (inactiva ya asignada con el mismo grado pasa; con otro grado falla; inactiva no asignada falla)
- [X] T067 [P] [US3] Crear `frontend/src/modules/estructura/puestos/pages/PuestoFormPage.edit.dom.test.tsx`: estado cargando, código en solo lectura, valores actuales en las cuatro secciones, competencia inactiva con indicador y **grado bloqueado** (solo Quitar), envío con `version`, alerta de conflicto 409 con "Recargar", 404 "ya no existe", guardar sin cambios vuelve al listado sin error, y el botón Guardar se bloquea mientras la petición está en curso (sin envíos duplicados)

### Implementation for User Story 3

- [X] T068 [US3] Crear `ActualizarPuesto` en `backend/app/Modules/Estructura/Application/UseCases/ActualizarPuesto.php` (`ejecutar(int $id, int $versionEsperada, DatosPuesto $datos, ?int $actorId = null)`; obtiene el puesto actual —`PuestoNoEncontrado` si no existe—, aplica `ReglasDeCompetenciasDePuesto` con sus competencias asignadas y llama a `actualizar`)
- [X] T069 [US3] Añadir `actualizar` a `EloquentPuestoRepository.php` en una transacción (research.md D-10 y D-11): si el contenido normalizado coincide **exactamente** con el actual (datos, funciones en el mismo orden, requisitos y competencias) devuelve el puesto sin escribir nada; si no, `UPDATE … WHERE id = ? AND version = ?` que incrementa `version`, fija `updated_by`/`updated_at` y actualiza `nombre_clave` y `nombre_busqueda` (0 filas → `PuestoModificado`; nombre de otro puesto → `NombreDePuestoDuplicado`), reemplaza las filas hijas (borra y reinserta; `orden` desde 1), y escribe en `auditoria_cambios` solo lo que devuelve `ComparadorDeCambiosDePuesto` al comparar la `instantanea()` previa con la nueva (un cambio que solo reordena funciones no escribe filas); conserva `codigo` y `created_by`; no toca `estado`
- [X] T070 [US3] Añadir `PuestoController@update` (200; `PuestoModificado` → 409; `PuestoNoEncontrado` → 404; `codigo` enviado y distinto → 422 con `errors.codigo`; `NombreDePuestoDuplicado` y `CompetenciasDePuestoInvalidas` → 422) y la ruta `PUT /api/puestos/{puestoId}` con `auth:sanctum` y `admin` en `backend/app/Modules/Estructura/Presentation/Controllers/PuestoController.php` y `backend/app/Modules/Estructura/Routes/api.php`
- [X] T071 [US3] Extender `useGuardarPuesto` con la actualización (envía `version`; expone el conflicto 409 y el 404) en `frontend/src/modules/estructura/puestos/hooks/useGuardarPuesto.ts`
- [X] T072 [US3] Añadir el modo editar a `PuestoFormPage` (lectura fresca con esqueleto, código en solo lectura, valores actuales, alerta de conflicto con "Recargar" y 404); en `CompetenciasFieldset.tsx` mostrar las competencias inactivas ya asignadas con indicador, grado bloqueado y solo Quitar; registrar `/puestos/:puestoId/editar` bajo `RequireAdmin` en `frontend/src/App.tsx`; mostrar el botón Editar solo al Administrador dentro de `PuestoDetalle.tsx`
- [X] T073 [US3] Añadir el E2E de edición en `frontend/e2e/puestos.spec.ts`: editar un puesto activo (cambiar nombre, reordenar funciones, agregar un requisito, quitar una competencia), editar el puesto inactivo, el puesto con competencia inactiva (grado bloqueado), guardar sin cambios sin error y el conflicto con una edición concurrente simulada por la API

**Checkpoint**: Crear y editar funcionan

---

## Phase 6: User Story 4 - Activar y desactivar un puesto (Priority: P2)

**Goal**: El Administrador desactiva un puesto sin posiciones Ocupadas ni Vacantes (con confirmación) o reactiva uno inactivo; nunca se elimina.

**Independent Test**: Desactivar un puesto sin posiciones en uso, intentar desactivar uno con posiciones Ocupadas o Vacantes y volver a activar el primero.

### Tests for User Story 4

- [X] T074 [P] [US4] Crear `backend/tests/Feature/Estructura/Puestos/CambiarEstadoPuestoTest.php`: 200 al desactivar un puesto sin posiciones y al reactivar sin restricciones; puesto con posiciones `Ocupada` o `Vacante` → 422 con `posiciones_en_uso` igual a la suma y `errors.estado`, y sigue `Activo`; solo `Suprimida` → 200; mismo estado → 200 sin cambios; `estado` ausente o inválido → 422; 404; 401; 403 al Usuario; el cambio de estado no altera `version`, `updated_by` ni `updated_at`; editar sigue siendo posible tras desactivar; el detalle y el listado reflejan el estado (el Usuario deja de verlo en el listado pero lo consulta por id)
- [X] T075 [P] [US4] Crear `backend/tests/Feature/Estructura/Puestos/RepositorioEstadoPuestoTest.php`: la desactivación es una sola sentencia condicional (comprobación y cambio atómicos): con una posición `Vacante` insertada antes, el puesto no se desactiva; con solo `Suprimida` se desactiva; contar posiciones suma `Ocupada` y `Vacante` y excluye `Suprimida`; dos desactivaciones seguidas dejan una sola fila `estado` en `auditoria_cambios`; reactivar escribe `Inactivo` → `Activo`
- [X] T076 [P] [US4] Crear `backend/tests/Unit/Estructura/Puestos/CambiarEstadoPuestoTest.php` para `CambiarEstadoPuesto` con el doble en memoria
- [X] T077 [P] [US4] Crear `frontend/src/modules/estructura/puestos/components/PuestoEstado.dom.test.tsx`: el botón alterna su texto (Desactivar si activo, Activar si inactivo) y solo lo ve el Administrador; Desactivar abre `ConfirmDialog` que nombra el puesto y Cancelar no cambia nada; el 422 por posiciones en uso muestra el mensaje con la cantidad dentro del diálogo y el puesto sigue Activo; Activar actúa sin confirmación; mientras la petición de estado está en curso el botón y la confirmación quedan bloqueados (sin envíos duplicados); no hay botón Eliminar para ningún rol

### Implementation for User Story 4

- [X] T078 [P] [US4] Crear `CambiarEstadoPuestoRequest` (`estado` obligatorio en `Activo,Inactivo`; mensajes en español; método `estado()`) en `backend/app/Modules/Estructura/Presentation/Requests/CambiarEstadoPuestoRequest.php` y el caso de uso `CambiarEstadoPuesto` en `backend/app/Modules/Estructura/Application/UseCases/CambiarEstadoPuesto.php`
- [X] T079 [US4] Añadir `cambiarEstado` a `EloquentPuestoRepository.php` en una transacción (research.md D-09): desactivar = `UPDATE puestos SET estado = 'Inactivo' WHERE id = ? AND estado = 'Activo' AND NOT EXISTS (posición `Ocupada` o `Vacante` del puesto)`; activar = `UPDATE … WHERE id = ? AND estado = 'Inactivo'`; sin tocar `version`, `updated_by` ni `updated_at`; si afecta una fila escribe la fila `estado` en `auditoria_cambios`; si no afecta ninguna distingue: no existe → `PuestoNoEncontrado`, ya estaba en ese estado → devuelve el puesto sin historial, posiciones en uso → `PuestoConPosicionesEnUso` con la suma de `Ocupada` y `Vacante`
- [X] T080 [US4] Añadir `PuestoController@estado` (200 con el detalle; `PuestoConPosicionesEnUso` → 422 con `message`, `errors.estado` y `posiciones_en_uso`; `PuestoNoEncontrado` → 404) y la ruta `PATCH /api/puestos/{puestoId}/estado` con `auth:sanctum` y `admin` en `backend/app/Modules/Estructura/Presentation/Controllers/PuestoController.php` y `backend/app/Modules/Estructura/Routes/api.php`
- [X] T081 [P] [US4] Crear el hook `useCambiarEstadoPuesto` (mutación sin reintentos; invalida `["puestos"]`; expone el 422 con `posiciones_en_uso`) en `frontend/src/modules/estructura/puestos/hooks/useCambiarEstadoPuesto.ts`
- [X] T082 [US4] Añadir a `PuestoDetalle.tsx` el botón Activar/Desactivar (solo Administrador; alterna su texto) con `ConfirmDialog` al desactivar (título como pregunta con el nombre del puesto entre comillas; si el backend rechaza, el diálogo muestra el mensaje con la cantidad de posiciones y el puesto sigue Activo) y el aviso de estado Inactivo del puesto
- [X] T083 [US4] Añadir el E2E de estado en `frontend/e2e/puestos.spec.ts`: el Administrador desactiva `Analista de datos` (confirmación nombra el puesto; desaparece del listado por defecto y del listado del Usuario); intenta desactivar `Jefe de talento humano` y ve el rechazo con la cantidad de posiciones y el puesto sigue Activo; desactiva `Desarrollador de software` (solo Suprimidas); reactiva uno inactivo sin confirmación; el test deja los puestos en su estado inicial al terminar aunque falle una comprobación intermedia (`try/finally`)

**Checkpoint**: Ciclo de vida completo

---

## Phase 7: User Story 5 - Trazabilidad de los cambios (Priority: P3)

**Goal**: Queda registrado quién creó y modificó cada puesto y cada cambio con campo, valor anterior, valor nuevo, usuario y fecha; el historial no se puede modificar ni borrar.

**Independent Test**: Crear, editar, reordenar, desactivar y activar con distintos administradores e inspeccionar `puestos` y `auditoria_cambios`.

- [X] T084 [P] [US5] Crear `backend/tests/Feature/Estructura/Puestos/AuditoriaPuestosTest.php`: ciclo completo con dos administradores (`created_by` del alta, `updated_by` tras otra edición sin alterar `created_by`, `version` y marcas de tiempo); la creación escribe una fila por `codigo`, `nombre`, `descripcion`, `mision`, `estado` y por cada función, requisito y competencia con valor anterior nulo; editar nombre escribe una fila con anterior y nuevo y `user_id`; agregar una función, quitar un requisito y cambiar el grado de una competencia escriben una fila por elemento; cambiar el texto de una función escribe baja + alta; cambiar solo el tipo de una función escribe una modificación; reordenar funciones **no** escribe filas pero sí actualiza `updated_by`; guardar sin cambios no escribe filas ni toca `updated_by`; activar y desactivar escriben una fila `estado` cada una; todas las filas llevan `entidad = 'puesto'`, `entidad_id` y fecha
- [X] T085 [P] [US5] Crear `backend/tests/Feature/Estructura/Puestos/HistorialInmutablePuestosTest.php`: no existe ninguna ruta que consulte, modifique ni borre el historial (bajo `api/puestos` solo hay `GET` listado, `GET` detalle, `GET` competencias-disponibles, `POST`, `PUT` y `PATCH` de estado; `DELETE` responde 404/405 incluso al Administrador); intentar actualizar o borrar un `AuditoriaCambioModel` existente lanza excepción; ningún puesto se elimina
- [X] T086 [US5] Proteger `AuditoriaCambioModel` en `backend/app/Modules/Estructura/Infrastructure/Persistence/Eloquent/Models/AuditoriaCambioModel.php` para que las filas existentes no puedan actualizarse ni borrarse desde la aplicación (eventos `updating` y `deleting` lanzan `LogicException`; solo inserción)

**Checkpoint**: Auditoría completa

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Calidad transversal y cierre

- [X] T087 [P] Auditar contraste y foco de la fila desplegable, las filas repetibles, Subir/Bajar, el selector, el grado bloqueado, las insignias y los avisos en ambos temas (pares ≥ 4.5:1, foco visible, orden de tabulación del formulario) y reflejarlo en `frontend/e2e/puestos.spec.ts` (foco visible y tema oscuro)
- [X] T088 [P] Verificar sin scroll horizontal a 320 y 1440 px el listado, el detalle desplegado (incluida la tabla de competencias) y el formulario con listas de 20 elementos y textos de 500 caracteres en `frontend/e2e/puestos.spec.ts`
- [X] T089 [P] Revisar seguridad en `backend/app/Modules/Estructura/Presentation/Controllers/PuestoController.php`, `backend/app/Modules/Estructura/Presentation/Requests/` y `backend/app/Modules/Estructura/Routes/api.php`: el rol se verifica en el backend en el listado, las escrituras, el cambio de estado y `competencias-disponibles`; un Usuario nunca recibe puestos inactivos en el listado; `codigo` distinto en `PUT` se rechaza; los errores no filtran detalles internos y están en español; ningún módulo toca las tablas de puestos ni `auditoria_cambios`; el contrato con `posiciones` coincide con contracts/estructura-facade.md
- [X] T090 [P] Revisar que `design/DESIGN.md` y `design/PuestosFormulario.dc.html` reflejan la fila desplegable, las filas repetibles, la reordenación y el grado bloqueado tal como quedaron implementados
- [X] T091 Reiniciar la base de desarrollo con `php artisan migrate:fresh --seed` en `backend/` y comprobar 6 puestos (2 inactivos), 3 posiciones en uso (2 `Ocupada` + 1 `Vacante`) del puesto `PU-002`, posiciones `Suprimida` en `PU-003` y `PU-005`, y la competencia inactiva `COMP-0007` asignada a `PU-004`
- [X] T092 Ejecutar las puertas de calidad del backend: `vendor/bin/pint --test` y `vendor/bin/pest` en `backend/` (no hay análisis estático instalado y no se añade)
- [X] T093 Ejecutar las puertas de calidad del frontend: `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `npx playwright test` en `frontend/`
- [X] T094 Ejecutar la validación de `specs/004-catalogo-puestos/quickstart.md` de punta a punta

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias; T002 y T003 (diseño) preceden a todo el frontend (T023 en adelante)
- **Foundational (Phase 2)**: depende de Setup; bloquea todas las historias. T006–T009 dependen de T005 (FK a `puestos`) y T008 además de la tabla `competencias` ya existente; T014 de T011–T012; T015 de T012 y T014; T016 de T015; T017 de T014; T018 de T005–T010; T019 de T015, T017 y T018; T020 de T019; T022 de T018–T021
- **US1 (Phase 3)**: tras Foundational; MVP
- **US2 (Phase 4)**: tras Foundational; el backend es independiente; el frontend se integra con la página de US1
- **US3 (Phase 5)**: tras Foundational; reutiliza `GuardarPuestoRequest`, `ReglasDeCompetenciasDePuesto`, `useGuardarPuesto` y `PuestoFormPage` de US2
- **US4 (Phase 6)**: tras US1 (el botón vive en `PuestoDetalle`); no depende de US2 ni US3 en el backend
- **US5 (Phase 7)**: sus pruebas ejercitan crear (US2), editar (US3) y estado (US4); la escritura del historial ya está en esas historias
- **Polish (Phase 8)**: tras las historias deseadas

### User Story Dependencies

- **US1 (P1)**: sin dependencias de otras historias
- **US2 (P1)**: backend independiente; el frontend usa la página y las rutas de US1
- **US3 (P2)**: depende de las reglas y la request de US2 (T052–T053) y del formulario de US2 para el frontend
- **US4 (P2)**: depende de US1 (frontend); el backend solo de Foundational
- **US5 (P3)**: depende de US2, US3 y US4

### Within Each User Story

- Las pruebas se escriben primero y deben fallar
- Dominio → casos de uso → repositorio → controlador y rutas; en frontend: hooks → componentes → páginas → rutas
- Los archivos compartidos (`PuestoController.php`, `Routes/api.php`, `EloquentPuestoRepository.php`, `PuestoDetalle.tsx`, `PuestosPage.tsx`, `PuestoFormPage.tsx`, `CompetenciasFieldset.tsx`, `useGuardarPuesto.ts`, `App.tsx`, `puestos.spec.ts`) se editan en secuencia entre historias, no en paralelo
- Los E2E de `puestos.spec.ts` dependen del orden (serial): la creación precede a las comprobaciones de conteo posteriores y cada test restablece lo que cambia

### Parallel Opportunities

- Phase 2: T004, T010, T011, T012 y T013 en paralelo; T016, T021 y T022 cuando sus dependencias estén hechas; T023–T026 (frontend) en paralelo con todo el backend
- Cada historia: todas sus pruebas [P] a la vez; DTO, Request y Resource, y hooks en paralelo
- Backend y frontend de una historia pueden avanzar en paralelo tras Foundational

---

## Parallel Example: User Story 2

```bash
# Pruebas de US2 en paralelo:
Task: "Feature CrearPuestoTest en backend/tests/Feature/Estructura/Puestos/"
Task: "Feature CompetenciasDisponiblesPuestosTest en backend/tests/Feature/Estructura/Puestos/"
Task: "Unit ReglasDeCompetenciasDePuestoTest en backend/tests/Unit/Estructura/Puestos/"
Task: "validation.test.ts en frontend/src/modules/estructura/puestos/"
Task: "dom FieldsetsDePuesto en frontend/src/modules/estructura/puestos/components/"

# Piezas independientes de US2:
Task: "GuardarPuestoRequest"
Task: "CompetenciaParaPuestoDTO y ReglasDeCompetenciasDePuesto"
Task: "validation.ts y RequireAdmin"
Task: "useGuardarPuesto y FilaTipoTexto"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 (línea base y diseño) y Phase 2 (modelo, auditoría, repositorio de lectura y piezas de frontend)
2. Phase 3 (US1): el catálogo es consultable con los datos sembrados
3. **Parar y validar**: Usuario y Administrador consultan, buscan, filtran y despliegan el detalle

### Incremental Delivery

1. US1 → consulta; 2. US2 → crear; 3. US3 → editar; 4. US4 → activar y desactivar; 5. US5 → auditoría verificada; 6. Polish
2. Cada historia se prueba por separado antes de pasar a la siguiente; al terminar cada tarea marcarla `[X]`

### Notas

- Escribir las pruebas primero y verlas fallar; no marcar una tarea sin que su prueba pase
- No añadir dependencias nuevas ni tocar el código de 001–003 más allá de los archivos compartidos indicados (rutas, servicio y Facade, `App.tsx`, `icons.tsx`, `DatabaseSeeder`, `Pest.php`, `CompetenciaModel`, `EstructuraServiceProvider`)
- Riesgo registrado (research.md D-02): el DBML real no está en el repositorio; si aparece y difiere de las migraciones T005–T010, alinearlas antes de implementar
- La tabla `posiciones` (T009) es mínima y pertenece a la feature 005: no añadir columnas ni lógica de posiciones aquí

### Desviaciones respecto a lo planeado (registradas al implementar)

- `EloquentPuestoRepository.php` se escribió completo de una vez (lecturas, `crear`, `actualizar` y `cambiarEstado`) para no dejar la clase a medias; T055, T069 y T079 se dieron por hechas cuando pasaron sus pruebas de historia.
- La protección del historial (T086: `updating` y `deleting` lanzan `LogicException`) se implementó junto con el modelo en T018, igual que `CompetenciaEstadoHistorialModel`; T085 la verifica.
- Los helpers compartidos `datosDePuesto()` (value object de dominio) y `cuerpoDelPuesto()` (cuerpo de un PUT) viven en `backend/tests/Pest.php`, no en un archivo de prueba, para que cada archivo se pueda ejecutar solo.
- Se añadió la prop opcional `hideLabel` a `shared/ui/TextField.tsx` (como ya tiene `SelectField`) y el icono `IconChevronUp` (Subir) a `icons.tsx`.
- Las comprobaciones de 403 del Usuario sobre las escrituras (T044) se añadieron a medida que existieron las rutas: `POST` y `competencias-disponibles` (US2), `PUT` (US3). El 403 de `PATCH …/estado` lo cubren `CambiarEstadoPuestoTest` y la validación del quickstart.
- El E2E de foco (T087) usa el teclado (Tab): un `.focus()` programático tras un clic no activa `:focus-visible` en los botones, así que el anillo de 2px solo aparece al navegar con teclado, como se espera.
- U4 (comparar requisitos y competencias como conjuntos al decidir si hay cambios reales) quedó implementado en `EloquentPuestoRepository::sinCambios()`; G4 (el Usuario no ve Editar ni Activar/Desactivar) está en `PuestoDetalle.dom.test.tsx`.
- Validación de punta a punta (T094): los pasos de API del quickstart se ejecutaron con un script contra un servidor local (59 comprobaciones, todas correctas); los pasos de interfaz están cubiertos por `frontend/e2e/puestos.spec.ts`.
