---

description: "Tareas de la feature 001-diccionario-competencias (plan del 2026-10-10: estado, auditoría y sin borrado)"
---

# Tasks: Diccionario de competencias

**Input**: Documentos de diseño en `/specs/001-diccionario-competencias/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/competencias-api.md, contracts/estructura-facade.md

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario. **Estas tareas parten de una implementación existente**
(plan del 2026-10-09: `DELETE`, código `COM-NNN`, `tipo` en minúsculas, sin estado ni auditoría). Cada tarea dice si
**modifica**, **crea** o **elimina** archivos; los cambios están resumidos en plan.md ("Cambios respecto de lo construido").

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1–US5)
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Estructura/…` (Domain, Application, Infrastructure, Presentation)
- Frontend: `frontend/src/modules/estructura/competencias/…` y `frontend/src/shared/ui/…`
- Pruebas backend: `backend/tests/{Unit,Feature}/Estructura/Competencias/`
- Pruebas de componentes: `*.dom.test.tsx` junto al código; E2E en `frontend/e2e/competencias.spec.ts`
- Valores nuevos: `tipo` ∈ `Cardinal` | `Especifica`; `estado` ∈ `Activo` | `Inactivo`; código `COMP-` + `id` con al menos 4 dígitos

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Línea base verde y diseño actualizado antes de tocar el frontend

- [X] T001 Verificar la línea base: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run` y `npx playwright test` en `frontend/` deben pasar antes de empezar (sin cambios de archivos) — Línea base verde al iniciar (121 pruebas Pest tras el cambio; 99 Vitest; 19 E2E al terminar).
- [X] T002 [P] Modificar `design/DESIGN.md` **antes** de cualquier tarea de frontend (constitución III): §4 `Badge` con variante neutra para Inactivo (fondo `--border`, texto `--muted`, contraste ≥ 4.5:1 en ambos temas) y Activo con `--soft`/`--accent`; §5 patrón Listado con columnas Código, Nombre, Descripción, Tipo y Estado (badge), filtro de estado solo para el Administrador (Activas por defecto, Inactivas, Todas) y botones Editar y Activar/Desactivar (el texto alterna; desactivar confirma, activar no); §9 registrar la diferencia con `Competencias.dc.html` (sin Código ni Estado), y el texto de confirmación "no podrá asignarse en nuevos registros"

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Modelo de datos, dominio, repositorio y tipos compartidos que necesitan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend

- [X] T003 Modificar `backend/database/migrations/2026_10_09_230001_create_competencias_table.php`: `codigo` texto anulable con índice único (se asigna en la misma transacción de creación), `nombre` texto máximo 50, `nombre_clave` texto máximo 50 con índice único, `descripcion` texto máximo 500, `tipo` texto (`Cardinal` | `Especifica`), `estado` texto (`Activo` | `Inactivo`) por defecto `Activo` con índice, `version` entero sin signo por defecto 1, `created_by` y `updated_by` enteros sin signo anulables **sin clave foránea**, timestamps
- [X] T004 [P] Modificar `backend/database/migrations/2026_10_09_230002_create_competencia_grados_table.php`: `competencia_id` FK a `competencias.id` **sin borrado en cascada** (`restrictOnDelete`), `grado` (`A` | `B` | `C` | `D`), `descripcion` texto máximo 500 y restricción única en `(competencia_id, grado)`
- [X] T005 [P] Crear `backend/database/migrations/2026_10_10_000001_create_competencia_estado_historial_table.php`: `id`, `competencia_id` FK a `competencias.id` sin cascada, `estado_anterior` (`Activo` | `Inactivo`), `estado_nuevo` (`Activo` | `Inactivo`), `user_id` entero sin signo anulable sin clave foránea, `created_at`; **sin `updated_at`**
- [X] T006 [P] Modificar `backend/app/Modules/Estructura/Domain/ValueObjects/TipoCompetencia.php` (casos `Cardinal = 'Cardinal'` y `Especifica = 'Especifica'`, etiquetas "Cardinal" y "Específica"), crear `backend/app/Modules/Estructura/Domain/ValueObjects/EstadoCompetencia.php` (`Activo`, `Inactivo`) y crear `backend/app/Modules/Estructura/Domain/Exceptions/EstadoSinCambio.php` (mensaje "La competencia ya está en ese estado.")
- [X] T007 [P] Modificar la entidad `backend/app/Modules/Estructura/Domain/Entities/Competencia.php` añadiendo `estado` (`EstadoCompetencia`), `creadoPor` (`?int`) y `modificadoPor` (`?int`)
- [X] T008 Modificar `backend/app/Modules/Estructura/Domain/Repositories/CompetenciaRepositoryInterface.php`: `listar(?string $q, ?TipoCompetencia $tipo, ?EstadoCompetencia $estado, int $pagina, int $porPagina)` (estado nulo = todos), `crear(…, ?int $actorId)`, `actualizar(…, ?int $actorId)`, nuevo `cambiarEstado(int $id, EstadoCompetencia $nuevo, ?int $actorId): Competencia` (lanza `CompetenciaNoEncontrada` o `EstadoSinCambio`), añadir `obtenerActivas(): array`; **mantener `eliminar` hasta T053**
- [X] T009 [P] Modificar `CompetenciaModel` (fillable `estado`, `created_by`, `updated_by`) y crear `CompetenciaEstadoHistorialModel` (tabla `competencia_estado_historial`, `const UPDATED_AT = null`) en `backend/app/Modules/Estructura/Infrastructure/Persistence/Eloquent/Models/`
- [X] T010 Modificar `backend/app/Modules/Estructura/Infrastructure/Persistence/Eloquent/Repositories/EloquentCompetenciaRepository.php`: código `sprintf('COMP-%04d', $id)`; `crear` guarda `created_by`, `estado` `Activo` y los 4 grados en una transacción; `actualizar` guarda `updated_by`, incrementa `version` y **no toca `estado` ni `codigo`**; `listar` filtra por `estado` y por `nombre_clave` con `q` (escapa `%`, `_` y `\`); `cambiarEstado` hace `UPDATE … WHERE id = ? AND estado <> nuevo` e inserta el registro de historial en la misma transacción, sin cambiar `version` ni `updated_by`, y si no afecta filas lanza `CompetenciaNoEncontrada` o `EstadoSinCambio`; `obtenerActivas` ordenado por nombre — `cambiarEstado` usa `DB::table(...)` (no Eloquent) para no tocar `updated_at`; `crear`/`actualizar` registran `created_by`/`updated_by`.
- [X] T011 [P] Modificar `backend/database/factories/CompetenciaFactory.php` (tipos `Cardinal`/`Especifica`, estado `Activo`, código `COMP-NNNN`, estado `inactiva()`, `created_by` nulo), `backend/tests/Unit/Estructura/Competencias/RepositorioEnMemoria.php` (nuevas firmas, `cambiarEstado`) y el helper `datosCompetencia()` de `backend/tests/Pest.php` (`'tipo' => 'Cardinal'`)
- [X] T012 [P] Modificar `backend/tests/Feature/Estructura/Competencias/RepositorioCompetenciaTest.php` para el nuevo modelo: código `COMP-0001`/`COMP-0002`, `created_by`, `updated_by` tras actualizar, `cambiarEstado` con historial, `EstadoSinCambio`, nombre duplicado entre activas e inactivas, y que no existe borrado en cascada de los grados

### Frontend

- [X] T013 [P] Modificar `frontend/src/shared/ui/Badge.tsx` con la variante `neutral` para Inactivo según `design/DESIGN.md` (depende de T002)
- [X] T014 [P] Modificar `frontend/src/modules/estructura/competencias/api.ts`: `TipoCompetencia` = `'Cardinal' | 'Especifica'`, `EstadoCompetencia` = `'Activo' | 'Inactivo'`, `FiltroEstado` = `EstadoCompetencia | 'Todos'`, campo `estado` en `Competencia`, `estado?` en `FiltrosListado`, y nueva función `cambiarEstadoCompetencia(id, estado)` con `PATCH /api/competencias/{id}/estado`; **mantener `eliminarCompetencia` hasta T054**
- [X] T015 [P] Modificar `frontend/src/modules/estructura/competencias/text.ts`: columnas Código y Estado, etiquetas "Activo"/"Inactivo", filtro "Estado" (Activas, Inactivas, Todas), botones "Activar" y "Desactivar" (con nombre accesible por competencia), título "¿Desactivar la competencia «nombre»?" y descripción "La competencia no podrá asignarse en nuevos registros.", avisos de éxito "Competencia activada."/"Competencia desactivada." y "La competencia ya estaba en ese estado."
- [X] T016 [P] Modificar `frontend/src/test/competenciasFixtures.ts` (campo `estado`, `tipo` en `Cardinal`/`Especifica`, `pagina()` sin cambios)

**Checkpoint**: Modelo, repositorio y tipos listos; las historias pueden comenzar

---

## Phase 3: User Story 1 - Consultar el diccionario y sus grados (Priority: P1) 🎯 MVP

**Goal**: Cualquier persona autenticada ve el listado (Código, Nombre, Descripción, Tipo, Estado), busca, filtra y despliega grados; el Usuario solo ve activas y el Administrador puede filtrar por estado.

**Independent Test**: Como Usuario, el listado no incluye inactivas aunque se pida `estado=Todos`; como Administrador se alterna entre activas, inactivas y todas; ambos consultan por id una competencia inactiva y despliegan sus grados.

### Tests for User Story 1

> Escribirlas primero y comprobar que fallan

- [X] T017 [P] [US1] Modificar `backend/tests/Feature/Estructura/Competencias/ConsultarCompetenciasTest.php`: respuestas con `codigo` `COMP-NNNN` y `estado`; el Usuario recibe solo `Activo` aunque envíe `estado=Todos` o `estado=Inactivo`; el Administrador por defecto `Activo`, `estado=Inactivo` e `estado=Todos`; `estado` inválido → 422; `q` con acentos y `%`; tipos `Cardinal`/`Especifica`; el detalle de una inactiva responde 200 al Usuario y al Administrador
- [X] T018 [P] [US1] Modificar `backend/tests/Unit/Estructura/Competencias/ConsultarCompetenciasTest.php` para el filtro de estado y el tipo convertido
- [X] T019 [P] [US1] Modificar `backend/tests/Feature/Estructura/Competencias/EstructuraFacadeTest.php`: `estado` en todos los arreglos, `getCompetencia`/`getCompetenciasPorIds` incluyen inactivas, `estaActiva` (activa, inactiva, inexistente) y `getCompetenciasActivas` ordenadas por nombre y sin inactivas
- [X] T020 [P] [US1] Modificar `frontend/src/modules/estructura/competencias/pages/CompetenciasPage.dom.test.tsx`: columnas Código y Estado con badge, el Usuario no ve el filtro de estado, el Administrador sí, `estado` en la URL (`Inactivo`/`Todos`; `Activo` no se escribe), y la consulta solo envía `estado` si es Administrador

### Implementation for User Story 1

- [X] T021 [P] [US1] Modificar los DTO en `backend/app/Modules/Estructura/Application/DTO/`: `FiltroCompetenciasDTO` con `estado` (`?string`, nulo = todos) y `CompetenciaDTO` con `estado`
- [X] T022 [US1] Modificar `backend/app/Modules/Estructura/Application/UseCases/ListarCompetencias.php` para pasar el `EstadoCompetencia` (o nulo) al repositorio
- [X] T023 [P] [US1] Modificar `backend/app/Modules/Estructura/Presentation/Requests/ListarCompetenciasRequest.php`: `tipo` en `Cardinal,Especifica`, `estado` en `Activo,Inactivo,Todos` (nulable), y `filtro(bool $esAdministrador)` que resuelve el estado efectivo (no administrador → `Activo` siempre; administrador → el pedido o `Activo` por defecto; `Todos` → nulo), con mensajes en español
- [X] T024 [P] [US1] Modificar `CompetenciaResource` y `CompetenciaDetalleResource` en `backend/app/Modules/Estructura/Presentation/Resources/` para incluir `estado`
- [X] T025 [US1] Modificar `CompetenciaController@index` en `backend/app/Modules/Estructura/Presentation/Controllers/CompetenciaController.php` para calcular `$esAdministrador = $request->user()->tokenCan('admin')` y usar `$request->filtro($esAdministrador)`
- [X] T026 [P] [US1] Modificar `backend/database/seeders/CompetenciasSeeder.php`: siete competencias (Orientación a resultados, Trabajo en equipo, Comunicación efectiva y Liderazgo `Cardinal`; Pensamiento analítico y Gestión de proyectos `Especifica`; Adaptabilidad `Cardinal` e **Inactivo**), con 4 grados cada una, creadas por el repositorio con `created_by` = id de `admin@hrms.test` (o nulo) y el cambio a inactivo registrado en el historial — Creada por el repositorio; la inactiva (`Adaptabilidad`) registra su cambio en el historial.
- [X] T027 [US1] Modificar `backend/app/Modules/Estructura/Application/Services/EstructuraService.php` y `backend/app/Modules/Estructura/Presentation/Facades/EstructuraFacade.php`: `estado` en los arreglos, `estaActiva(int $id): bool` y `getCompetenciasActivas(): array` (según contracts/estructura-facade.md); **mantener `registrarVerificadorDeUso` hasta T053**
- [X] T028 [P] [US1] Modificar `frontend/src/modules/estructura/competencias/hooks/useListadoParams.ts` (parámetro `estado`: solo `Inactivo` o `Todos` se escriben en la URL, `Activo` es el valor por defecto) y `useCompetencias.ts` (clave `["competencias","lista",{q,tipo,estado,page}]`)
- [X] T029 [US1] Modificar `frontend/src/modules/estructura/competencias/components/CompetenciasTable.tsx`: columnas Código y Estado (`Badge` `Activo` / `neutral` `Inactivo`) y descripción recortada a 2 líneas, sin cambiar los botones todavía
- [X] T030 [US1] Modificar `frontend/src/modules/estructura/competencias/pages/CompetenciasPage.tsx`: `SelectField` "Estado" (Activas, Inactivas, Todas) visible solo para el Administrador, el estado vacío menciona las inactivas al Administrador, y la consulta envía `estado` solo si es Administrador
- [X] T031 [US1] Modificar el E2E de consulta en `frontend/e2e/competencias.spec.ts`: el Usuario ve 6 filas activas con Código y Estado y no ve `Adaptabilidad` ni el filtro de estado; el Administrador alterna Activas (6), Inactivas (1) y Todas (7); el Usuario consulta `Adaptabilidad` por la API

**Checkpoint**: US1 funcional: el diccionario se consulta con visibilidad por rol

---

## Phase 4: User Story 2 - Crear una competencia (Priority: P1)

**Goal**: El Administrador crea una competencia con exactamente cuatro grados; nace `Activo` con código `COMP-NNNN` y se registra quién la creó.

**Independent Test**: Crear una competencia válida y verla en el listado con `COMP-0008`, estado Activo y sus cuatro grados.

### Tests for User Story 2

- [X] T032 [P] [US2] Modificar `backend/tests/Feature/Estructura/Competencias/CrearCompetenciaTest.php`: `codigo` `COMP-0001`, `estado` `Activo`, `created_by` = id del Administrador, `tipo` `Cardinal`/`Especifica` (otro valor → 422), `grados` con una letra extra (`E`) o repetida → 422, nombre duplicado frente a una competencia **inactiva** → 422, `estado` y `codigo` enviados se ignoran
- [X] T033 [P] [US2] Modificar `backend/tests/Unit/Estructura/Competencias/CrearCompetenciaTest.php` para el `actorId` y el estado inicial
- [X] T034 [P] [US2] Modificar `frontend/src/modules/estructura/competencias/validation.test.ts` para `tipo` `Cardinal`/`Especifica`
- [X] T035 [P] [US2] Modificar `frontend/src/modules/estructura/competencias/pages/CompetenciaFormPage.dom.test.tsx`: carga útil con `tipo` `Cardinal` y opciones del selector `Cardinal`/`Especifica` (etiquetas "Cardinal" y "Específica")

### Implementation for User Story 2

- [X] T036 [P] [US2] Modificar `backend/app/Modules/Estructura/Presentation/Requests/GuardarCompetenciaRequest.php`: `tipo` en `Cardinal,Especifica`; `grados` requerido con **exactamente** las claves `A`, `B`, `C`, `D` (rechaza claves extra), cada una obligatoria, texto y máximo 500; `nombre` máximo 50; `descripcion` máximo 500; mensajes en español
- [X] T037 [US2] Modificar `backend/app/Modules/Estructura/Application/UseCases/CrearCompetencia.php` para recibir el `actorId`, y `CompetenciaController@store` para pasar `(int) $request->user()->getAuthIdentifier()`
- [X] T038 [P] [US2] Modificar `frontend/src/modules/estructura/competencias/validation.ts` (`z.enum(['Cardinal', 'Especifica'])`) y las opciones del selector de tipo en `frontend/src/modules/estructura/competencias/pages/CompetenciaFormPage.tsx`
- [X] T039 [US2] Modificar el E2E de creación en `frontend/e2e/competencias.spec.ts`: código `COMP-0008`, aviso "Competencia creada." y estado Activo en el listado

**Checkpoint**: US1 + US2: se puede consultar y alimentar el diccionario

---

## Phase 5: User Story 3 - Editar una competencia (Priority: P2)

**Goal**: El Administrador edita una competencia activa o inactiva sin cambiar su código ni su estado; se registra quién la modificó.

**Independent Test**: Editar una activa y una inactiva y comprobar que `codigo` y `estado` no cambian, `version` sube y `updated_by` es el editor.

### Tests for User Story 3

- [X] T040 [P] [US3] Modificar `backend/tests/Feature/Estructura/Competencias/ActualizarCompetenciaTest.php`: `PUT` sobre una **inactiva** → 200 y sigue `Inactivo`; el `estado` y el `codigo` enviados se ignoran; `updated_by` = id del editor y `created_by` intacto; `version` +1; `grados` con clave extra → 422; tipos `Cardinal`/`Especifica`
- [X] T041 [P] [US3] Modificar `backend/tests/Unit/Estructura/Competencias/ActualizarCompetenciaTest.php` para el `actorId` y que el estado no cambia
- [X] T042 [P] [US3] Modificar `frontend/src/modules/estructura/competencias/pages/CompetenciaFormPage.edit.dom.test.tsx` para una competencia inactiva (el formulario carga y guarda) y `tipo` `Cardinal`

### Implementation for User Story 3

- [X] T043 [US3] Modificar `backend/app/Modules/Estructura/Application/UseCases/ActualizarCompetencia.php` para recibir el `actorId`, y `CompetenciaController@update` para pasar el id del usuario autenticado
- [X] T044 [US3] Modificar el E2E de edición en `frontend/e2e/competencias.spec.ts`: editar una competencia inactiva desde el filtro "Inactivas", comprobar que sigue Inactivo y que la edición concurrente sigue mostrando el conflicto

**Checkpoint**: US1–US3 funcionando de forma independiente

---

## Phase 6: User Story 4 - Activar y desactivar una competencia (Priority: P2)

**Goal**: El Administrador desactiva (con confirmación) y activa (sin confirmación) competencias; no existe el borrado.

**Independent Test**: Desactivar una competencia (sale del listado por defecto y del listado del Usuario), volver a activarla y comprobar que no existe `DELETE` en ninguna capa.

### Tests for User Story 4

- [X] T045 [P] [US4] Crear `backend/tests/Feature/Estructura/Competencias/CambiarEstadoCompetenciaTest.php`: `PATCH /{id}/estado` `Inactivo` y luego `Activo` → 200 con el estado nuevo; 401 sin sesión; 403 al Usuario; 404 si no existe; 422 con `estado` ausente o inválido; 422 si ya está en ese estado (sin registro de historial); `version` y `updated_by` **no cambian**; tras desactivar, el Usuario ya no la ve en el listado pero sí por id; una edición con la versión previa al cambio de estado **no** da conflicto
- [X] T046 [P] [US4] Crear `backend/tests/Unit/Estructura/Competencias/CambiarEstadoCompetenciaTest.php` con el repositorio en memoria: cambia, propaga `EstadoSinCambio` y `CompetenciaNoEncontrada`
- [X] T047 [P] [US4] Crear `backend/tests/Feature/Estructura/Competencias/SinBorradoTest.php`: `DELETE /api/competencias/{id}` responde 404 o 405 incluso con el Administrador, la competencia permanece, y la tabla `competencia_grados` no tiene cascada (eliminar una competencia con SQL directo falla por la clave foránea)
- [X] T048 [P] [US4] Crear `frontend/src/modules/estructura/competencias/pages/CompetenciasPage.estado.dom.test.tsx` (sustituye a `CompetenciasPage.eliminar.dom.test.tsx`): el botón dice "Desactivar" en activas y "Activar" en inactivas; desactivar abre `ConfirmDialog` que nombra la competencia e informa "no podrá asignarse en nuevos registros"; cancelar no llama a la API; confirmar llama a `cambiarEstadoCompetencia(id, 'Inactivo')` y avisa; activar llama a la API **sin diálogo**; 422 "ya estaba en ese estado" refresca y avisa; 404 informa; botón bloqueado mientras guarda; el Usuario no ve el botón

### Implementation for User Story 4

- [X] T049 [US4] Crear el caso de uso `backend/app/Modules/Estructura/Application/UseCases/CambiarEstadoCompetencia.php` (`ejecutar(int $id, string $estado, ?int $actorId): CompetenciaDTO`)
- [X] T050 [P] [US4] Crear `backend/app/Modules/Estructura/Presentation/Requests/CambiarEstadoRequest.php` (`estado` obligatorio en `Activo,Inactivo`, mensajes en español)
- [X] T051 [US4] Añadir `CompetenciaController@cambiarEstado` (200 con el detalle; `EstadoSinCambio` → 422 con `errors.estado`; `CompetenciaNoEncontrada` → 404) y la ruta `PATCH /api/competencias/{id}/estado` con `auth:sanctum` y `admin` en `backend/app/Modules/Estructura/Presentation/Controllers/CompetenciaController.php` y `backend/app/Modules/Estructura/Routes/api.php`
- [X] T052 [US4] Eliminar `backend/app/Modules/Estructura/Application/UseCases/EliminarCompetencia.php`, `Application/Ports/VerificadoresDeUso.php`, `Domain/Contracts/VerificadorDeUsoInterface.php` y `Domain/Exceptions/CompetenciaEnUso.php`; quitar `destroy`, la ruta `DELETE` y la captura de `CompetenciaEnUso` del controlador y de `Routes/api.php`; quitar el registro de `VerificadoresDeUso` de `Infrastructure/Providers/EstructuraServiceProvider.php`; quitar `registrarVerificadorDeUso` de `EstructuraService` y `EstructuraFacade`; quitar `eliminar` de la interfaz y del repositorio (todo bajo `backend/app/Modules/Estructura/`) — Eliminados también el directorio vacío `Domain/Contracts` y `Application/Ports`; `EliminarCompetenciaTest` (Feature y Unit) y `VerificadorDeUsoTest` borrados.
- [X] T053 [US4] Eliminar las pruebas del borrado: `backend/tests/Feature/Estructura/Competencias/EliminarCompetenciaTest.php`, `backend/tests/Feature/Estructura/Competencias/VerificadorDeUsoTest.php` y `backend/tests/Unit/Estructura/Competencias/EliminarCompetenciaTest.php`, y quitar `eliminar` de `RepositorioEnMemoria.php`
- [X] T054 [P] [US4] Eliminar `frontend/src/modules/estructura/competencias/hooks/useEliminarCompetencia.ts` y `frontend/src/modules/estructura/competencias/pages/CompetenciasPage.eliminar.dom.test.tsx`; quitar `eliminarCompetencia` de `api.ts` y los textos `confirmDelete` de `text.ts`
- [X] T055 [P] [US4] Crear el hook `useCambiarEstadoCompetencia` (mutación sin reintentos; invalida `["competencias"]` sin esperar la recarga) en `frontend/src/modules/estructura/competencias/hooks/useCambiarEstadoCompetencia.ts`
- [X] T056 [US4] Modificar `frontend/src/modules/estructura/competencias/components/CompetenciasTable.tsx`: sustituir el botón Eliminar por Activar/Desactivar (texto según el estado, solo Administrador, `aria-label` con el nombre) y mantener Editar
- [X] T057 [US4] Modificar `frontend/src/modules/estructura/competencias/pages/CompetenciasPage.tsx`: `ConfirmDialog` solo para desactivar, activar directo, avisos de éxito, mapeo de 422 (ya en ese estado), 404 y errores de red, y retroceder una página si se vacía la actual
- [X] T058 [US4] Modificar el E2E de `frontend/e2e/competencias.spec.ts`: sustituir el flujo de eliminar por desactivar (cancelar no cambia; confirmar la saca del listado por defecto, aparece en "Inactivas" y el Usuario ya no la ve) y activar sin confirmación

**Checkpoint**: Las cuatro historias funcionan de forma independiente y no existe borrado

---

## Phase 7: User Story 5 - Trazabilidad de los cambios (Priority: P3)

**Goal**: Quién creó, quién editó por última vez y cada cambio de estado quedan registrados y el historial es inmutable.

**Independent Test**: Crear, editar, desactivar y activar con dos administradores distintos e inspeccionar `created_by`, `updated_by` y el historial.

### Tests for User Story 5

- [X] T059 [P] [US5] Crear `backend/tests/Feature/Estructura/Competencias/AuditoriaCompetenciaTest.php`: `created_by`/`created_at` al crear; `updated_by`/`updated_at` tras un `PUT` de otro administrador sin alterar `created_by`; cada `PATCH` añade una fila con `user_id`, `created_at`, `estado_anterior` y `estado_nuevo`; los registros previos se conservan; un `PATCH` repetido (422) no añade filas; la creación no añade fila; dos cambios concurrentes de estado dejan una sola fila
- [X] T060 [P] [US5] Crear `backend/tests/Unit/Estructura/Competencias/HistorialInmutableTest.php`: actualizar o eliminar un `CompetenciaEstadoHistorialModel` lanza `LogicException`, y no existe ruta ni caso de uso que lo haga

### Implementation for User Story 5

- [X] T061 [US5] Modificar `backend/app/Modules/Estructura/Infrastructure/Persistence/Eloquent/Models/CompetenciaEstadoHistorialModel.php` para que `updating` y `deleting` lancen `LogicException` ("El historial de estados no se puede modificar")

**Checkpoint**: Auditoría completa

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Calidad transversal y cierre

- [X] T062 [P] Auditar contraste y foco del badge y los botones nuevos en ambos temas (pares ≥ 4.5:1; Activo `--accent` sobre `--soft` y Inactivo `--muted` sobre `--border`) y reflejarlo en `frontend/e2e/competencias.spec.ts` (foco visible y tema oscuro) — Contraste calculado: Inactivo `--muted` sobre `--border` 4.69:1 (claro) y 5.47:1 (oscuro); Activo `--accent` sobre `--soft` 5.21:1 y 7.57:1.
- [X] T063 [P] Verificar sin scroll horizontal a 320 y 1440 px la tabla con Código y Estado, los grados desplegados, el formulario y el diálogo de desactivar en `frontend/e2e/competencias.spec.ts` — Tabla con `min-w-200` dentro de contenedor `relative overflow-x-auto`; verificado a 320 y 1440 px con filas expandidas y el diálogo.
- [X] T064 [P] Revisar seguridad: el rol se verifica en el backend en cada escritura y en el filtro de estado, un Usuario nunca recibe inactivas en el listado, `codigo`/`estado` se ignoran en `POST`/`PUT`, los errores no filtran detalles internos y están en español, y ningún módulo toca `competencia_estado_historial` — Revisado: rol verificado en el backend (listado, escrituras y estado), el Usuario nunca recibe inactivas, `codigo`/`estado` ignorados en `POST`/`PUT`, sin ruta ni caso de uso para el historial, errores en español sin detalles internos.
- [X] T065 Reiniciar la base de desarrollo con `php artisan migrate:fresh --seed` en `backend/` (las migraciones se modificaron en su sitio) y comprobar que hay 7 competencias, una inactiva con su historial — Base de desarrollo reiniciada: 7 competencias (`COMP-0007` inactiva) y 1 registro de historial.
- [X] T066 Ejecutar las puertas de calidad del backend: `vendor/bin/pint --test` y `vendor/bin/pest` en `backend/`
- [X] T067 Ejecutar las puertas de calidad del frontend: `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `npx playwright test` en `frontend/`
- [X] T068 Ejecutar la validación de `specs/001-diccionario-competencias/quickstart.md` de punta a punta — Pasos de la API 1–11 ejecutados contra un servidor real; la interfaz queda cubierta por el E2E.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias; T002 (diseño) precede a T013 y a todo el frontend
- **Foundational (Phase 2)**: depende de Setup; bloquea todas las historias. T008 depende de T006–T007; T010 de T003–T005, T008, T009; T012 de T010–T011
- **US1 (Phase 3)**: tras Foundational; MVP
- **US2 (Phase 4)**: tras Foundational; backend independiente; para verla en el listado usa US1
- **US3 (Phase 5)**: tras Foundational; reutiliza el formulario de US2 en el frontend
- **US4 (Phase 6)**: tras Foundational; T052–T054 (retirada del borrado) deben ir tras US1–US3 porque tocan archivos que esas historias modifican
- **US5 (Phase 7)**: tras Foundational; sus pruebas ejercitan US2–US4
- **Polish (Phase 8)**: tras las historias deseadas

### User Story Dependencies

- **US1 (P1)**: sin dependencias de otras historias
- **US2 (P1)**: backend independiente; el frontend se integra con la página de US1
- **US3 (P2)**: backend independiente; el frontend depende del formulario de US2
- **US4 (P2)**: backend independiente; el botón y el diálogo viven en la tabla y la página de US1
- **US5 (P3)**: depende de que existan crear, editar y cambiar estado (US2–US4)

### Within Each User Story

- Las pruebas se escriben primero y deben fallar
- Dominio → casos de uso → controlador y rutas; en frontend: hooks → componentes → páginas
- Los archivos compartidos (`CompetenciaController.php`, `Routes/api.php`, `CompetenciasTable.tsx`, `CompetenciasPage.tsx`, `competencias.spec.ts`) se editan en secuencia entre historias, no en paralelo

### Parallel Opportunities

- Phase 2: T004, T005, T006, T007 y T009 en paralelo; T011–T016 en paralelo cuando sus dependencias estén hechas
- Cada historia: todas sus pruebas [P] a la vez; DTO/Request/Resource y hooks en paralelo
- Backend y frontend de una historia pueden avanzar en paralelo tras Foundational

---

## Parallel Example: User Story 4

```bash
# Pruebas de US4 en paralelo:
Task: "Feature CambiarEstadoCompetenciaTest en backend/tests/Feature/Estructura/Competencias/"
Task: "Unit CambiarEstadoCompetenciaTest en backend/tests/Unit/Estructura/Competencias/"
Task: "Feature SinBorradoTest en backend/tests/Feature/Estructura/Competencias/"
Task: "dom CompetenciasPage.estado.dom.test.tsx en frontend/src/modules/estructura/competencias/pages/"

# Piezas independientes de US4:
Task: "CambiarEstadoRequest en backend/app/Modules/Estructura/Presentation/Requests/"
Task: "Hook useCambiarEstadoCompetencia en frontend/src/modules/estructura/competencias/hooks/"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Completar Phase 1 y Phase 2 (incluido el diseño y las migraciones)
2. Completar US1 (Phase 3)
3. **Detenerse y validar**: el Usuario solo ve activas y el Administrador alterna por estado
4. Añadir US2 para poder alimentar el diccionario (también P1)

### Incremental Delivery

1. Setup + Foundational → modelo nuevo listo (las pruebas heredadas se ajustan en T011–T012)
2. US1 → validar; US2 → validar (MVP completo: se consulta y se crea)
3. US3 → edición de activas e inactivas; US4 → activar/desactivar y retirada del borrado
4. US5 → auditoría verificada; Polish: accesibilidad, responsive, seguridad y puertas de calidad

---

## Notes

- [P] = archivos distintos, sin dependencias
- La etiqueta [Story] mapea cada tarea a su historia
- Sin dependencias nuevas
- Tras la Phase 2 y hasta completar cada historia, algunas pruebas heredadas fallarán: cada historia las actualiza (T017–T020, T032–T035, T040–T042)
- Confirmar que las pruebas fallan antes de implementar
- Hacer commit tras cada tarea o grupo lógico
