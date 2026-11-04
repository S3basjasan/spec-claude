---

description: "Tareas de 01b.4 · Categorías por unidad y visibles por rol"
---

# Tasks: Categorías por unidad y visibles por rol (01b.4)

**Input**: Documentos de diseño en `/specs/026-01b.4-categorias-visibles-por-rol/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/categorias-unidades-api.md, quickstart.md

**Tests**: Se incluyen: la constitución exige cobertura automatizada (Pest, Vitest y un E2E del flujo principal) y el plan de origen enumera las pruebas. En Pest los cuatro puertos se reemplazan por implementaciones falsas; ninguna prueba depende de `config/compensaciones_prueba.php`.

**Organization**: Tareas agrupadas por historia de usuario. La Phase 2 deja la columna, el dominio, los puertos y el contrato de guardado; cada historia añade su parte visible o verificable.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia a la que pertenece (US1…US6)

## Path Conventions

- Backend: `backend/app/Modules/Compensaciones/`, pruebas en `backend/tests/Feature/Compensaciones/` y `backend/tests/Unit/Compensaciones/`
- Frontend: `frontend/src/modules/compensaciones/categorias/`, E2E en `frontend/e2e/`
- Diseño: `design/`
- Rutas HTTP bajo `/api/compensaciones/`
- Mensajes exactos 422 (campo `unidades_ids`): «Elige al menos una unidad.» y «La unidad seleccionada ya no está activa.»
- Asignación directa: sin herencia por jerarquía de unidades (FR-017)

---

## Phase 1: Setup

**Purpose**: Línea base y diseño previo a la interfaz

- [X] T001 Ejecutar la suite actual `cd backend && ./vendor/bin/pest tests/Feature/Compensaciones tests/Unit/Compensaciones` y `cd frontend && npx vitest run src/modules/compensaciones`, y anotar que todo pasa (línea base)
- [X] T002 [P] Actualizar el diseño antes de implementar la interfaz (Constitución III): añadir el selector de unidades («Todas las unidades» + una casilla por unidad + texto de alcance + error «Elige al menos una unidad») al formulario en `design/CategoriasReconocimiento.dc.html`, la columna «Unidades» al listado, y una sección «Categorías por unidad y rol» en `design/DESIGN.md` (estados: vacío del colaborador, aviso «Sin unidades asignadas», banner por rol; claro/oscuro y responsive). `design/CategoriasPorRol.dc.html` ya existe como referencia

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Columna, objeto de valor, puertos con sus adaptadores y fakes, persistencia y contrato de guardado. Ninguna historia puede empezar antes.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T003 Crear la migración `backend/database/migrations/2026_11_04_000001_add_unidades_ids_to_categorias_reconocimiento.php`: agrega `unidades_ids` JSON nullable a `categorias_reconocimiento` (lista de enteros positivos distintos y ordenados, mínimo 1 al guardar; relación lógica con `unidades_organizacionales`, sin FK; no lee datos de otros módulos); `down()` la elimina
- [X] T004 [P] Crear `backend/app/Modules/Compensaciones/Domain/ValueObjects/UnidadesAsignadas.php`: lista de ≥ 1 enteros positivos distintos, ordenados; `ids()`, `contiene(int)`, `comoJson()` (p. ej. `"[1,2,5]"`); vacía o con no enteros lanza `DatosDeCategoriaInvalidos('unidades_ids', 'Elige al menos una unidad.')`
- [X] T005 [P] Crear `backend/tests/Unit/Compensaciones/UnidadesAsignadasTest.php`: ordena y deduplica, rechaza vacío / cero / negativo / texto, `contiene`, `comoJson`
- [X] T006 Modificar `backend/app/Modules/Compensaciones/Domain/Entities/Categoria.php`: nueva propiedad `?UnidadesAsignadas $unidades` (nulo solo en datos previos al comando inicial); `propuesta(...)` la recibe como parámetro **opcional** (`?UnidadesAsignadas $unidades = null`) hasta que T018 la haga obligatoria al guardar, para no romper `CrearCategoria`/`ActualizarCategoria` entre fases; `diferenciasCon()` agrega el par `unidades` (JSON de ids ordenados, anterior → nuevo; sin cambio no genera fila); agregar el método `asignadaA(int $unidadId): bool`. Ajustar `backend/app/Modules/Compensaciones/Domain/ValueObjects/FiltroCategorias.php` con `?int $unidadId = null`
- [X] T007 Modificar `backend/app/Modules/Compensaciones/Infrastructure/Persistence/Eloquent/Models/CategoriaReconocimientoModel.php` (cast `unidades_ids` a array) y `backend/app/Modules/Compensaciones/Infrastructure/Persistence/Eloquent/Repositories/EloquentCategoriaRepository.php`: leer y escribir `unidades_ids` al crear y en el `UPDATE` condicional por versión; `buscar()` aplica `whereJsonContains('unidades_ids', $filtro->unidadId)` cuando el filtro trae unidad; `activas()` y `porIds()` devuelven la categoría con sus unidades
- [X] T008 [P] Crear los cuatro puertos en `backend/app/Modules/Compensaciones/Application/Ports/`: `EmpresaActualPort` (`tenantId()`), `ColaboradoresPort` (`cuentaDeUsuario(userId)`, `nombres(ids)`, `vigentes(ids, fecha)`, `unidadActual(ids, fecha)`), `UnidadesPort` (`activas()`, `porIds(ids)` indexado por id, con `estado`), `EquipoPort` (`esLider(colaboradorId)`, `equipoDe(liderId)`, `liderDe(colaboradorId)`, `usuariosDe(colaboradorIds)`)
- [X] T009 [P] Crear `backend/app/Modules/Compensaciones/Infrastructure/Adapters/UnidadesDesdeEstructura.php` (solo `EstructuraFacade::getUnidadesActivas` y `getUnidadesPorIds`; sin leer tablas de Estructura) y `ColaboradoresDesdePersonal.php` (`PersonalFacade::getCuentaDeUsuario`, `getColaboradoresPorIds`, `vigentesPorIds`, `getDepartamentoActual`, con el tenant de `EmpresaActualPort`)
- [X] T010 [P] Crear `backend/app/Modules/Compensaciones/Infrastructure/Adapters/EmpresaActualPorSesionOConfiguracion.php` (con sesión: `SharedFacade::getContextoDeTenant(userId)`; sin sesión: `config('compensaciones_prueba.tenant_id')`; PROVISIONAL en la parte sin sesión) y `EquipoDesdeConfiguracion.php` (PROVISIONAL; lee `equipos` y `usuarios` de la configuración). Crear `backend/config/compensaciones_prueba.php` con `tenant_id`, `equipos` (líder → colaboradores) y `usuarios` (colaborador → usuario) con ids que coinciden con `PersonalSeeder`, y un comentario que diga que es solo para el repositorio de prueba y no se usa en el proyecto real
- [X] T011 Enlazar los cuatro puertos a sus adaptadores en `backend/app/Modules/Compensaciones/Infrastructure/Providers/CompensacionesServiceProvider.php` (el cambio futuro a `EquipoDesdePersonal` debe tocar solo este enlace)
- [X] T012 [P] Crear los falsos de prueba en `backend/tests/Support/Compensaciones/` (`ColaboradoresFalso`, `UnidadesFalso`, `EquipoFalso`, `EmpresaActualFalsa`) y un ayudante para enlazarlos en el contenedor de Pest; ninguna prueba debe depender de `compensaciones_prueba.php`
- [X] T013 Modificar `backend/app/Modules/Compensaciones/Application/DTOs/DatosCategoriaDTO.php` (agrega `?array $unidadesIds` y `bool $todasLasUnidades`) y `backend/app/Modules/Compensaciones/Presentation/Requests/GuardarCategoriaRequest.php`: `unidades_ids` (array de enteros, `distinct`) obligatorio salvo `todas_las_unidades` verdadero (la exigencia se aplica en el request; el guardado efectivo llega con T018); si llegan ambos gana `todas_las_unidades` (ver `contracts/categorias-unidades-api.md`); `datos()` lo pasa al DTO
- [X] T014 Ajustar las pruebas existentes que crean o editan categorías porque el campo ahora es obligatorio en el request (deben quedar verdes al cerrar la fase; mientras T018 no esté, las pruebas envían `todas_las_unidades: true` y el caso de uso lo ignora temporalmente): `backend/tests/Feature/Compensaciones/CrearCategoriaTest.php`, `ActualizarCategoriaTest.php`, `CambiarEstadoCategoriaTest.php`, `ListarCategoriasTest.php`, `ObtenerCategoriaTest.php`, `CategoriasFacadeTest.php`, `RendimientoListadoTest.php`, `EsquemaCategoriasTest.php` y los de `Puntaje*Test.php` (por defecto, todas las unidades activas del falso). Verificar que pasan

**Checkpoint**: la columna existe, los puertos están enlazados y las pruebas existentes siguen verdes.

---

## Phase 3: User Story 1 - Asignar categorías a unidades (Priority: P1) 🎯 MVP

**Goal**: El Gestor crea y edita categorías eligiendo una o varias unidades activas o «Todas las unidades»; sin unidades no se guarda; el cambio queda auditado (FR-001, FR-002, FR-003, FR-009, FR-011, FR-014).

**Independent Test**: Crear una categoría con dos unidades y comprobar que queda con esas dos; editarla con «Todas» y comprobar que quedan las activas de ese momento; intentar guardar sin unidades; revisar la auditoría.

### Tests for User Story 1

- [X] T015 [P] [US1] Crear `backend/tests/Feature/Compensaciones/UnidadesDeCategoriaTest.php`: crear con unidades válidas guarda ids ordenados; vacío / ausente → 422 «Elige al menos una unidad.» en `unidades_ids`; unidad inexistente o inactiva → 422 «La unidad seleccionada ya no está activa.»; `todas_las_unidades: true` guarda las activas del momento y una unidad creada después no se agrega sola (FR-011); editar reemplaza la lista; guardar sin cambiar unidades no agrega fila de auditoría; cambiar unidades registra el campo `unidades` con anterior y nuevo; 409 por versión antes que 422; cambiar unidades no altera reconocimientos ni desafíos
- [X] T016 [P] [US1] Crear `backend/tests/Feature/Compensaciones/UnidadesDisponiblesTest.php`: `GET /api/compensaciones/unidades-disponibles` devuelve las activas para `admin`; 403 para no `admin`; 401 sin sesión
- [X] T017 [P] [US1] Crear `frontend/src/modules/compensaciones/categorias/components/SelectorDeUnidades.dom.test.tsx`: marcar «Todas» marca todas; quitar una desmarca «Todas» y conserva el resto; sin selección muestra «Elige al menos una unidad» y el texto de alcance «Nadie la verá»; accesible por teclado; y ampliar `frontend/src/modules/compensaciones/categorias/validation.test.ts` con la regla de al menos una unidad

### Implementation for User Story 1

- [X] T018 [US1] Modificar `backend/app/Modules/Compensaciones/Application/UseCases/CrearCategoria.php` y `ActualizarCategoria.php`: inyectar `UnidadesPort`; resolver `todas_las_unidades` con `activas()`; validar que cada id exista y esté activo con `porIds()` (422 con los mensajes exactos); construir `UnidadesAsignadas` y pasarlo a la propuesta (desde aquí el guardado exige unidades); en `ActualizarCategoria` la verificación de versión sigue antes que la de unidades
- [X] T019 [US1] Modificar `backend/app/Modules/Compensaciones/Application/Services/AuditoriaDeCategoria.php`: `alCrear` registra `unidades` con anterior nulo; `alModificar` ya la toma de `diferenciasCon`
- [X] T020 [US1] Modificar `backend/app/Modules/Compensaciones/Application/DTOs/CategoriaDTO.php` y `Services/ArmadorDeCategoriaDTO.php`: resolver las unidades `{id, nombre, activa}` con **una** llamada a `UnidadesPort::porIds` por lote, y calcular `aplicaATodas` (con una lectura a `activas()`) y `sinUnidadesActivas`; `backend/app/Modules/Compensaciones/Presentation/Resources/CategoriaResource.php` expone `unidades`, `aplica_a_todas` y `sin_unidades_activas` solo cuando el contexto lo permite (por ahora, sólo `admin`; US5 agrega al líder)
- [X] T021 [P] [US1] Crear `backend/app/Modules/Compensaciones/Application/UseCases/ListarUnidadesDisponibles.php` y `backend/app/Modules/Compensaciones/Presentation/Controllers/UnidadesController.php`; registrar `GET /unidades-disponibles` en `backend/app/Modules/Compensaciones/Routes/api.php` dentro del grupo `['auth:sanctum', 'admin']`
- [X] T022 [P] [US1] Frontend `frontend/src/modules/compensaciones/categorias/`: tipos y body en `api.ts` (`unidades_ids`, `todas_las_unidades`, `obtenerUnidadesDisponibles()`), `hooks/useUnidadesDisponibles.ts` (se refresca al abrir el formulario), esquema Zod en `validation.ts` (al menos una unidad) y textos en `text.ts` («Todas las unidades», «Elige al menos una unidad.», «La unidad seleccionada ya no está activa.», alcance «La verán N unidades» / «Nadie la verá»)
- [X] T023 [US1] Crear `frontend/src/modules/compensaciones/categorias/components/SelectorDeUnidades.tsx` (casilla «Todas las unidades», una casilla por unidad activa, texto de alcance, error junto al campo, etiquetas y foco accesibles) e integrarlo en `components/CategoriaFormModal.tsx` (crear y editar; mapea el 422 de `unidades_ids` al campo; conserva lo escrito; bloquea envíos pendientes). Ampliar `CategoriaFormModal.dom.test.tsx`
- [X] T024 [US1] Actualizar `frontend/src/modules/compensaciones/categorias/components/CategoriasTabla.tsx` y `DetalleCategoria.tsx`: columna «Unidades» (nombres o «Todas las unidades») y lista de unidades en el detalle para el Gestor; aviso «Sin unidades asignadas» / «sin unidades activas» cuando corresponda; ampliar `CategoriasTabla.dom.test.tsx`

**Checkpoint**: El Gestor asigna unidades desde el formulario y ve la columna; la auditoría registra los cambios.

---

## Phase 4: User Story 2 - El colaborador ve solo las categorías de su unidad (Priority: P1)

**Goal**: El colaborador ve únicamente las categorías activas asignadas a su unidad actual; las demás responden 404 por detalle; sin ficha o sin unidad, lista vacía (FR-006, FR-015, FR-017).

**Independent Test**: Con categorías en distintas unidades, el colaborador de A solo ve las activas de A; el detalle de una de B responde 404; sin controles de gestión ni columna de unidades.

### Tests for User Story 2

- [X] T025 [P] [US2] Crear `backend/tests/Feature/Compensaciones/VisibilidadPorRolTest.php` (parte colaborador): solo activas de su unidad; inactivas ocultas; sin ficha o sin unidad → `data: []`; unidad del colaborador inactiva → `data: []` y al reactivarla vuelve a ver las suyas (US2 AC7); el cambio de unidad se refleja en la siguiente consulta; detalle fuera de su unidad → 404 igual que inexistente; la respuesta no incluye `unidades`; no hay herencia desde ni hacia unidades padre/hijas (FR-017); `admin` sigue viendo todas
- [X] T026 [P] [US2] Ampliar `frontend/src/modules/compensaciones/categorias/pages/CategoriasReconocimientoPage.dom.test.tsx`: colaborador sin categorías ve «Todavía no tienes categorías asignadas»; no ve acciones de gestión ni columna de unidades; muestra el banner del rol

### Implementation for User Story 2

- [X] T027 [US2] Crear `backend/app/Modules/Compensaciones/Application/Services/VisibilidadDeCategorias.php`: dado el usuario devuelve el alcance `Gestor` (permiso `categorias.gestionar` vía `PoliticaDeAcceso`), `Unidad(id)` (con `ColaboradoresPort::cuentaDeUsuario` + `unidadActual` en la fecha de hoy en `config('compensaciones.zona_horaria')`) solo si `UnidadesPort::porIds` la devuelve activa, o `Ninguno` (sin ficha, sin unidad o con la unidad inactiva). Si Estructura/Personal no están disponibles, propagar un error general seguro
- [X] T028 [US2] Modificar `backend/app/Modules/Compensaciones/Application/UseCases/ListarCategorias.php` (aplica el alcance: Gestor como hoy; `Unidad` → estado Activo + `FiltroCategorias::unidadId`; `Ninguno` → página vacía) y `ObtenerCategoria.php` (fuera del alcance → `CategoriaNoEncontrada`); `Presentation/Controllers/CategoriaController.php` pasa el usuario autenticado; `CategoriaResource` no expone unidades al colaborador
- [X] T029 [US2] Frontend: `pages/CategoriasReconocimientoPage.tsx` con banner por rol («Ves las categorías asignadas a tu unidad…») y estado vacío del colaborador; `text.ts` con los textos; oculta controles de gestión (el servidor mantiene la autoridad)

**Checkpoint**: Gestor y colaborador funcionan de punta a punta.

---

## Phase 5: User Story 3 - Consulta disponible de categorías de la unidad de la persona (Priority: P1)

**Goal**: Existe la consulta única que desafíos (02.3) y reconocimientos (03.1) usarán para aceptar solo categorías activas de la unidad de la persona (FR-007, FR-008). El 422 y la lista vacía con mensaje los implementan esas funciones; aquí se entrega y prueba la consulta.

**Independent Test**: Con una categoría solo en Ventas, `seleccionablesParaUnidad(Operaciones)` no la incluye y `seleccionablesParaUnidad(Ventas)` sí.

### Tests for User Story 3

- [X] T030 [P] [US3] Crear `backend/tests/Feature/Compensaciones/SeleccionablesParaUnidadTest.php`: devuelve solo activas, con competencia activa y asignadas a la unidad (mismo formato de `listarSeleccionables()`); excluye inactivas, de otra unidad, con competencia inactiva y sin unidades; sin herencia por jerarquía; el resultado refleja de inmediato un cambio de asignación o de estado; la persona que cambió de unidad se consulta con su unidad actual

### Implementation for User Story 3

- [X] T031 [US3] Añadir `seleccionablesParaUnidad(int $unidadId): array` a `backend/app/Modules/Compensaciones/Application/Services/CategoriasService.php` y su `@method` en `Presentation/Facades/CategoriasFacade.php`: categorías activas, con competencia activa, que contienen la unidad; `[]` si la unidad no existe o está inactiva (`UnidadesPort::porIds`). Conservar `listarSeleccionables()` sin cambios
- [X] T032 [US3] En `CategoriasService::aArreglo` (y por tanto en `obtener`/`obtenerPorIds`) incluir `unidades_ids`; sin filtrar por unidad para resolver registros históricos. Documentar en el docblock de la Facade que desafíos (02.3) y reconocimientos (03.1) deben rechazar con 422 toda categoría ausente de `seleccionablesParaUnidad` para la unidad de la persona

**Checkpoint**: El contrato de selección está probado y listo para consumir.

---

## Phase 6: User Story 4 - Consulta de categorías seleccionables por unidad (Priority: P2)

**Goal**: La consulta es robusta ante unidades inactivas o inexistentes y sus resultados no filtran datos de otras unidades (spec US4).

**Independent Test**: Consultar una unidad inexistente, otra inactiva y otra válida y comprobar `[]`, `[]` y la lista correcta.

- [X] T033 [P] [US4] Ampliar `backend/tests/Feature/Compensaciones/SeleccionablesParaUnidadTest.php`: unidad inexistente → `[]`; unidad inactiva → `[]`; al reactivar la unidad su asignación previa vuelve a aplicar; ninguna categoría de otra unidad aparece; ampliar `CategoriasFacadeTest.php` para `obtener` y `obtenerPorIds` con `unidades_ids`
- [X] T034 [US4] Ajustar `CategoriasService::seleccionablesParaUnidad` si alguna prueba de T033 falla (p. ej. estado de la unidad leído por `UnidadesPort::porIds` en vez de `activas()`); sin tocar el contrato público

**Checkpoint**: la consulta cumple los escenarios de US4. Es un contrato interno sin usuario (ver `research.md` §8).

---

## Phase 7: User Story 5 - Líder y Gerencia consultan todas las categorías activas (Priority: P2)

**Goal**: El líder consulta todas las categorías activas con sus unidades, sin gestionar; Gerencia es el Administrador en el repo de prueba y ve y gestiona todo (limitación aceptada; FR-004, FR-005, FR-015; SC-007 se verifica con el líder).

**Independent Test**: El líder ve todas las activas con «Aplica a: …» y sin acciones; los intentos directos de crear/editar/activar responden 403.

### Tests for User Story 5

- [X] T035 [P] [US5] Ampliar `backend/tests/Feature/Compensaciones/VisibilidadPorRolTest.php` (parte líder y administrador): líder (`EquipoFalso::esLider`) ve todas las activas con `unidades` y `aplica_a_todas`, nunca inactivas aunque pida `estado=Todos`; `POST`/`PUT`/`PATCH` directos del líder → 403; `GET /unidades-disponibles` del líder → 403; `admin` ve todas, activas e inactivas, con unidades
- [X] T036 [P] [US5] Ampliar `frontend/src/modules/compensaciones/categorias/components/CategoriasTabla.dom.test.tsx` y `CategoriasReconocimientoPage.dom.test.tsx`: líder ve «Aplica a: …» como texto y ningún control de edición; banner de líder

### Implementation for User Story 5

- [X] T037 [US5] Añadir el alcance `Lider` a `VisibilidadDeCategorias` (según `EquipoPort::esLider(colaboradorId)` del usuario) con prioridad: Gestor > Líder > Unidad > Ninguno; `ListarCategorias` aplica «todas las activas, sin filtro de unidad» y ignora el filtro `estado`; `ObtenerCategoria` permite cualquier activa; `CategoriaResource` expone `unidades` también al líder
- [X] T038 [US5] Frontend: `CategoriasTabla.tsx` / `DetalleCategoria.tsx` muestran «Aplica a: {unidades}» como texto al líder (sin acciones); banner del líder en `CategoriasReconocimientoPage.tsx` («Consultas todas las categorías activas…»); textos en `text.ts`

**Checkpoint**: Los tres roles ven lo que les corresponde.

---

## Phase 8: User Story 6 - Las categorías existentes siguen funcionando (Priority: P2)

**Goal**: Las categorías previas quedan asignadas a todas las unidades activas; los registros ya hechos no cambian (FR-010, FR-012).

**Independent Test**: Con categorías sin unidades, ejecutar el comando dos veces: la primera las asigna, la segunda no cambia nada; los reconocimientos y desafíos previos quedan intactos.

### Tests for User Story 6

- [X] T039 [P] [US6] Crear `backend/tests/Feature/Compensaciones/AsignarUnidadesInicialesTest.php`: asigna todas las unidades activas a las categorías sin unidades; no toca las que ya tienen; idempotente al ejecutarse dos veces; registra una fila de auditoría `unidades` con actor nulo; una categoría sin unidades solo la ve el `admin` (con `unidades: []` y aviso) y no aparece en `seleccionablesParaUnidad` ni para líder ni colaborador

### Implementation for User Story 6

- [X] T040 [US6] Crear `backend/app/Modules/Compensaciones/Infrastructure/Console/AsignarUnidadesIniciales.php` (`compensaciones:asignar-unidades-iniciales`; usa `UnidadesPort::activas()` y `AuditoriaDeCategoria`; informa cuántas categorías actualizó) y registrarlo en `CompensacionesServiceProvider.php`; las categorías sin unidades (datos previos) se muestran al Gestor con `sin_unidades_activas: true` y `VisibilidadDeCategorias` y `seleccionablesParaUnidad` las excluyen para líder y colaborador (el paso del comando ya figura en «Preparar los datos» del `quickstart.md`)

**Checkpoint**: Las seis historias son verificables.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: E2E del flujo principal, calidad y verificación contra la spec

- [X] T041 Ampliar `frontend/e2e/categorias-reconocimiento.spec.ts`: el Gestor asigna una categoría a la unidad A; un colaborador de la unidad B no la ve y uno de A sí; sin unidades el formulario muestra «Elige al menos una unidad»
- [X] T042 Ejecutar la calidad del backend: `cd backend && ./vendor/bin/pint` y el análisis estático disponible, y `./vendor/bin/pest` completo (incluye `RendimientoListadoTest`: una llamada a `UnidadesPort::porIds` por página) — Resultado: Pint pasa y Pest de Compensaciones pasa (239+). No hay herramienta de análisis estático instalada. La suite completa del backend tiene 47 fallos en Estructura (unidades) y Gestión de Personal (exportación de ficha), módulos que esta función no toca.
- [X] T043 Ejecutar la calidad del frontend: `cd frontend && npm run lint && npm run typecheck && npm test && npm run build`, y el E2E `npm run e2e -- categorias-reconocimiento` — Resultado: ESLint, TypeScript y Vitest pasan en el alcance de Compensaciones, `vite build` compila y el E2E de categorías pasa (6/6). El proyecto completo conserva fallos previos ajenos (micro-learning, AppShell, desarrollo y estructura/unidades) que impiden `npm run build` y `npm run lint` globales.
- [ ] T044 Recorrer `specs/026-01b.4-categorias-visibles-por-rol/quickstart.md` de punta a punta; comprobar a mano claro/oscuro, 320–1440 px sin scroll horizontal y que asignar unidades a una categoría (elegir, ver el alcance y guardar) toma menos de 1 minuto (SC-004); confirmar que `design/` quedó actualizado y registrar en `plan.md` el resultado de las dos desviaciones abiertas (panel del mockup y conteo de personas por unidad)
- [X] T045 Verificar contra la spec: cada FR-001…FR-017 y SC-001…SC-007 tiene prueba o verificación manual, y anotar en `plan.md` las salvedades que sigan abiertas (conteo de personas por unidad, Gerencia solo consulta) — Resultado: FR-001 a FR-017 cubiertos por pruebas; FR-012 (reconocimientos y desafíos ya registrados) no es comprobable todavía porque esas tablas aún no existen; FR-005 y SC-007 se verifican con el líder (Gerencia = Administrador en el repo de prueba). Pendiente T044: recorrido manual del quickstart y tema oscuro.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias; T002 (diseño) debe terminar antes de T023, T024, T029 y T038.
- **Foundational (Phase 2)**: depende de T001; bloquea todas las historias. Dentro: T003 → T006/T007; T008 → T009/T010 → T011; T012 tras T008; T013 tras T004 y T006; T014 al final de la fase.
- **US1 (Phase 3)**: depende de la Phase 2. Es el MVP.
- **US2 (Phase 4)**: depende de la Phase 2 y de T020 (resource con unidades). Puede avanzar en paralelo con US1 en backend una vez listo T020.
- **US3 (Phase 5)**: depende de la Phase 2; independiente de US1 para el backend, pero sus pruebas necesitan categorías con unidades (T013/T018).
- **US4 (Phase 6)**: depende de US3.
- **US5 (Phase 7)**: depende de US2 (`VisibilidadDeCategorias`) y de T020.
- **US6 (Phase 8)**: depende de la Phase 2 (T007, T011) y de T019 (auditoría); T040 depende además de T027 y T031.
- **Polish (Phase 9)**: depende de todas las historias.

### Within Each User Story

- Pruebas primero (deben fallar), luego casos de uso / servicios, luego controlador y frontend.
- Backend antes que frontend dentro de cada historia (el frontend consume el contrato).

### Parallel Opportunities

- T002 junto con T001.
- En la Phase 2: T004, T005, T008, T009, T010 y T012 en paralelo tras sus prerrequisitos.
- En US1: T015, T016 y T017 en paralelo; T021 y T022 en paralelo con T018–T020.
- US3 y US6 pueden avanzar en paralelo con US1 una vez cerrada la Phase 2.

---

## Parallel Example: User Story 1

```text
Task: "Crear UnidadesDeCategoriaTest.php"                       (T015)
Task: "Crear UnidadesDisponiblesTest.php"                       (T016)
Task: "Crear SelectorDeUnidades.dom.test.tsx"                   (T017)
Task: "Crear ListarUnidadesDisponibles + UnidadesController"    (T021)
Task: "Frontend api.ts / hook / validation / text"              (T022)
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Completar Setup y Foundational.
2. Completar US1: el Gestor asigna unidades y se audita.
3. **Validar**: crear con dos unidades, con «Todas», y sin unidades.

### Incremental Delivery

1. Fundación → US1 (asignar) → US2 (colaborador) → US3 (contrato de selección) → desplegar y demostrar.
2. US5 (líder/Gerencia) y US6 (categorías previas) se agregan sin romper lo anterior.
3. US4 refuerza la consulta; Polish cierra con E2E y calidad.

> **Antes de publicar**: ejecutar una vez `php artisan compensaciones:asignar-unidades-iniciales` después de migrar, para que las categorías existentes no queden invisibles para líder y colaborador (T040).

---

## Notes

- Total: 45 tareas. Setup 2 · Foundational 12 · US1 10 · US2 5 · US3 3 · US4 2 · US5 4 · US6 2 · Polish 5.
- Decisiones aceptadas en `plan.md`: selector dentro del formulario (no panel lateral), sin conteo de personas por unidad, Gerencia = Administrador y el 422 de reconocer y asignar desafíos a cargo de 02.3 y 03.1.
- `EquipoPort` y la parte sin sesión de `EmpresaActualPort` son provisionales: al publicarse los métodos de Gestión de Personal, se cambia solo el enlace en `CompensacionesServiceProvider.php`.
- No se añaden dependencias.
