---

description: "Tareas de la feature 005-unidad-organizacional (revisión 2026-10-15)"
---

# Tasks: Unidades organizacionales

**Input**: Documentos de diseño en `/specs/005-unidad-organizacional/`

**Prerequisites**: spec.md y plan.md (revisión 2026-10-15). Requiere 000-login a 004-catalogo-puestos ya implementadas (tablas `auditoria_cambios` y `posiciones`, `AuditoriaCambioModel`, `PosicionModel`, `PuestosSeeder`).

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Estado de partida**: la implementación de la **versión anterior** de la feature ya existe en el repositorio (árbol con formulario en línea, tipo elegido a mano, desactivación sin cascada, catálogo de tipos de solo lectura). Esta revisión **no parte de cero**: las tareas indican qué archivo se **modifica**, cuál se **crea** y cuál se **elimina**. Lo no mencionado se conserva tal cual (repositorio de lecturas, `ArbolDeUnidades`, `ComparadorDeCambiosDeUnidad`, puerto de nombres de usuario, control de versión de la unidad, `RequireAdmin`, `useListadoParams`).

**Organization**: Tareas agrupadas por historia de usuario (US1–US6 de spec.md).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Estructura/…` (Domain, Application, Infrastructure, Presentation)
- Frontend: `frontend/src/modules/estructura/unidades/…` (reutiliza `frontend/src/shared/ui/…`)
- Pruebas backend: `backend/tests/{Unit,Feature}/Estructura/{Unidades,TiposUnidad}/`
- Pruebas de componentes: `*.dom.test.tsx` junto al código (con `// @vitest-environment jsdom`); E2E en `frontend/e2e/unidades.spec.ts`
- Regla de módulos: `unidades` **no importa nada** de `frontend/src/modules/estructura/{competencias,comportamientos,preguntas,puestos}`; el rol llega solo por `frontend/src/modules/auth/login`.
- Valores: `estado` de unidad ∈ `Activa` | `Inactiva`; `estado` de posición ∈ `Ocupada` | `Vacante` | `Suprimida`; auditoría `entidad` ∈ `unidad` | `tipo_unidad`.
- Lecciones: escribir archivos con acentos o barras con las herramientas Write/Edit (no heredocs); en E2E localizar campos con `getByRole('textbox', { name, exact: true })`; los comandos de la shell en Windows usan `./node_modules/.bin/playwright`; la shell de la sesión no ejecuta pruebas salvo que se pida.
- Donde difieren, **prevalecen plan.md y spec.md** sobre el insumo `plan-unidades.md` (capacidad `admin` en lugar de permisos, `version`, token de cadena, auditoría solo para el Administrador).

---

## Phase 1: Setup (documentación y diseño)

**Purpose**: Alinear los artefactos y el diseño con la spec revisada antes de tocar código

- [ ] T001 Verificar la línea base antes de empezar: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b` y `npx vitest run` en `frontend/` (sin cambios de archivos)
- [X] T002 [P] Reescribir `specs/005-unidad-organizacional/research.md` y `data-model.md` según plan.md: `tipos_unidad` con `nivel`, `created_by` y `updated_by`; tipo calculado; raíz sembrada; cascada y su transacción (D-cascada); reordenamiento en dos pasos; token de cadena; auditoría de tipos; decisiones descartadas del plan anterior (formulario en línea, `opciones?excluir`, reactivación de raíz)
- [X] T003 [P] Reescribir `specs/005-unidad-organizacional/contracts/unidades-api.md` y `estructura-facade.md` con los 10 endpoints del plan (incluidos `/api/tipos-unidad`, `impacto-desactivacion` y `opciones?reubicar`), sus códigos 200/201/404/409/422, el contenido de `unidades_invalidas` y la ampliación de la Facade con la cadena de tipos; actualizar `quickstart.md` El token de cadena es el SHA-1 de `id:nombre:nivel` de los tipos ordenados por nivel y viaja como `version` en `GET /api/tipos-unidad`.
- [X] T004 [P] Modificar `design/DESIGN.md` **antes** de cualquier tarea de frontend: §4 nuevo componente **Modal** (cabecera, cuerpo, pie, foco, Escape) y **Chip de nivel**; §5 reemplazar "Formulario en línea" por **Formulario en modal**; actualizar **Listado en árbol** (expandir / contraer con `aria-expanded`, "Expandir todo / Contraer todo", búsqueda con ancestras, sin columna Unidad padre, resumen "N unidades · M activas", advertencia "No sigue la cadena"); nuevo patrón **Editar tipos** (lista con asa y flechas, candado en la raíz, vista previa y modal de bloqueo); diálogo de desactivación en cascada con lista y bloqueo; §9 diferencias con el tablero
- [X] T005 [P] (el tablero ya incluía todos estos elementos, entregado el 2026-10-15; solo se verificó) Actualizar `design/Unidades.dc.html` y su entrada en `design/canvas.json`: cadena de tipos, árbol expandible, filtro de estado y acciones solo para el Administrador, modales Crear / Editar (tipo de solo lectura; padre limitada al tipo superior; información de solo lectura), Nuevo tipo, Editar tipos con vista previa, bloqueo de reordenamiento y desactivación en cascada (con y sin dependientes, con bloqueo), textos "Reactivar", "La unidad raíz no se puede desactivar" y "Reactiva primero la unidad padre"; sin validación de nombre duplicado

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Modelo de datos, dominio de la cadena y piezas de frontend que necesitan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend

- [X] T006 Editar `backend/database/migrations/2026_10_14_000001_create_tipos_unidad_table.php`: añadir `nivel` (`tinyInteger`, **único**), `created_by` y `updated_by` (`unsignedBigInteger` anulables, sin FK a `users`); conservar `nombre` `string(50)` único
- [X] T007 [P] Crear en `backend/app/Modules/Estructura/Domain/` el servicio puro `Services/CadenaDeTipos.php` (sin Laravel): a partir de la lista de tipos (id, nombre, nivel) y de las unidades (id, código, nombre, tipo, padre) calcula la cadena resultante de un borrador (tipo raíz primero, niveles 1..N), los tipos que cambian de nombre o de nivel, y las **unidades en conflicto** (padre con nivel resultante ≥ al de la unidad) con sus tipos y niveles nuevos y los de su padre; `tipoDeNivel(nivel)`, `admiteHijas(tipo)` y `sigueCadena(unidad)`; y los value objects `BorradorDeTipo`, `UnidadAfectadaPorCadena` y `ImpactoDeDesactivacion`
- [X] T008 [P] Crear `Domain/Services/ComparadorDeCambiosDeTipo.php` (puro): filas `nombre` y `nivel` entre dos estados; en la creación, valor anterior nulo; ids como texto
- [X] T009 [P] Crear las excepciones en `Domain/Exceptions/`: `CadenaModificada` ("La cadena de tipos cambió desde que la abriste. Recarga para ver la versión actual."), `CadenaInvalida` (errores por índice y `unidadesInvalidas`) y `DesactivacionBloqueada` (unidades y cantidades); ajustar `UnidadInvalida` ("Este tipo no admite subunidades.", raíz no reubicable, raíz no desactivable) y retirar `UnidadNoReactivable::RAIZ_ACTIVA_EXISTENTE`
- [X] T010 Modificar `Domain/ValueObjects/TipoDeUnidad.php` (+ `nivel`) y la entidad `Domain/Entities/Unidad.php` (+ `tieneDependientes`, `admiteHijas`, `sigueCadena`); crear `Domain/Entities/TipoUnidad.php`
- [X] T011 Modificar `Domain/Services/ArbolDeUnidades.php`: añadir `rama(id)` (la unidad y sus dependientes activas, directas e indirectas) y `ancestrasDe(ids)` para el filtrado con ancestras; `descendientesDe` se conserva
- [X] T012 [P] Modificar `Domain/Repositories/UnidadRepositoryInterface.php` (lecturas: `todas` con el filtro de ancestras, `opciones(?reubicar)`, `impactoDeDesactivacion(id)`; escrituras: `crear` sin tipo, `actualizar`, `cambiarEstado` en cascada, `registrarCambios`) y crear `TipoUnidadRepositoryInterface.php` (`todos`, `crear(nombre, actor)`, `guardarCadena(token, borrador, actor)`, `cantidadDeUnidadesPorTipo`)
- [X] T013 [P] Modificar `Infrastructure/Persistence/Eloquent/Models/TipoUnidadModel.php` (+ `nivel`, `created_by`, `updated_by`, relación `unidades()`) y `UnidadModel.php` (calcula el tipo por nivel)
- [X] T014 Crear `Infrastructure/Persistence/Eloquent/Repositories/EloquentTipoUnidadRepository.php`: lecturas; `crear` en transacción que empieza con una escritura (nivel = máximo + 1, nombre normalizado único); `guardarCadena` (token, ids exactos, raíz primera, nombres únicos por índice, conflictos con `CadenaDeTipos`, dos pasos de nivel `n → −n → final`, auditoría de nombre y nivel, sin cambios = sin escritura); registrar el binding en `EstructuraServiceProvider.php` El token se calcula como en el contrato (SHA-1 de `id:nombre:nivel` por nivel).
- [X] T015 Modificar `EloquentUnidadRepository.php` (lecturas): listado con `tieneDependientes`, `admiteHijas`, `sigueCadena`, tipo con nivel y el filtro de ancestras para `q` y para `estado` distinto de Activa; `opciones` con `habilitada` y el parámetro `reubicar` (solo activas del tipo inmediatamente superior, sin la propia ni sus dependientes); `impactoDeDesactivacion` (dependientes activas y unidades de la rama con posiciones vigentes) `dependientes_activas` cuenta las unidades activas de la **rama** (directas e indirectas, vía `ArbolDeUnidades::rama`), no solo las hijas directas; `posiciones_vigentes` cuenta solo las asignadas a la unidad. El listado hace una consulta de unidades y una de posiciones, sin consultas por fila.
- [X] T016 [P] Modificar `backend/database/factories/UnidadFactory.php` (tipo por nivel de la padre; la raíz de nivel 1) y crear `TipoUnidadFactory.php`; modificar `backend/tests/Pest.php` con los helpers de cadena de tipos (`cadenaDeTipos(n)`, `arbolDeEjemplo()` de cuatro niveles)
- [X] T017 [P] Modificar `backend/database/seeders/TiposUnidadSeeder.php` (Gerencia General 1, Gerencia 2, Área 3, Departamento 4, Sección 5 con `nivel`) y `UnidadesSeeder.php` (12 unidades del plan; orden de creación: Gerencia General, Gerencia de Talento Humano, Gerencia de Tecnología, Selección y Desarrollo, Infraestructura, Desarrollo de Software, Reclutamiento, Capacitación (UO-008, inactiva con `fecha_supresion`), Soporte, Aplicaciones, Selección Técnica, Desarrollo Web; posiciones: una Ocupada en Desarrollo Web y las demás vigentes y Suprimidas fuera de las ramas de Infraestructura y de Selección Técnica) Reparto exacto de posiciones: la Ocupada en UO-012 (Desarrollo Web); las otras 2 vigentes del puesto "Jefe de talento humano" (1 Ocupada y 1 Vacante) en UO-002; las 2 Suprimidas de "Desarrollador de software" en UO-006; la Suprimida de "Asistente contable" en UO-008; sin posiciones en UO-005, UO-009 y UO-011 (rama para la cascada: UO-005 con UO-009; hoja libre: UO-011).
- [ ] T018 [P] Crear `backend/tests/Unit/Estructura/Unidades/CadenaDeTiposTest.php`, `ComparadorDeCambiosDeTipoTest.php` y ampliar `ArbolDeUnidadesTest.php` (rama, ancestras): reordenamientos válidos, conflictos (padre de nivel igual o mayor), saltos de nivel permitidos, tipo raíz inmovible, nombres iguales sin tildes ni mayúsculas
- [ ] T019 [P] Crear `backend/tests/Feature/Estructura/TiposUnidad/RepositorioTiposTest.php`: `nivel` y `nombre` únicos; dos pasos del reordenamiento sin violar la unicidad; niveles continuos tras guardar

### Frontend

- [X] T020 [P] Modificar `frontend/src/modules/estructura/unidades/api.ts` (tipos `TipoUnidad` con `nivel`, `es_raiz` y cantidad; `UnidadResumen` con `nivel_arbol`, `tiene_dependientes`, `admite_hijas`, `sigue_cadena`; `Opciones`, `Impacto`, `ErrorDeCadena`; funciones `listarTipos`, `crearTipo`, `guardarCadena`, `obtenerImpacto`; `obtenerOpciones(reubicar?)`; `crearUnidad` sin tipo) y `text.ts` (todos los textos nuevos de la spec)
- [X] T021 [P] Crear `frontend/src/shared/ui/Modal.tsx` (+ prueba `Modal.dom.test.tsx`): `role="dialog"`, `aria-modal`, título, foco atrapado, Escape, foco devuelto al abrir, pie de acciones; añadir a `shared/ui/icons.tsx` `IconChevronRight`, `IconLock`, `IconGripVertical`, `IconArrowUp` e `IconArrowDown` (SVG propios, `aria-hidden`)
- [X] T022 [P] Crear `frontend/src/modules/estructura/unidades/arbol.ts` (filas visibles según el conjunto de ramas expandidas y la búsqueda activa) y el hook `hooks/useExpansion.ts` (solo raíz al entrar, Expandir todo, Contraer todo, restaura la expansión previa al limpiar la búsqueda, expande el padre y sus ancestras al crear), con `arbol.test.ts`
- [X] T023 [P] Modificar `frontend/src/test/unidadesFixtures.ts` (cadena de cinco tipos y las 12 unidades sembradas, con `nivel_arbol`, `admite_hijas` y `sigue_cadena`; `tipos()`, `opciones()`, `impacto()`) y crear `hooks/useTiposUnidad.ts` (lista y mutaciones de tipos) ampliando `useUnidades.ts` (opciones con `reubicar`, impacto)

**Checkpoint**: Base lista

---

## Phase 3: User Story 1 - Consultar el árbol y la cadena de tipos (Priority: P1) 🎯 MVP

**Goal**: Ver la cadena y el árbol con expansión, búsqueda con ancestras, filtro y advertencias, según el rol.

**Independent Test**: Con unidades activas e inactivas, entrar como Usuario y como Administrador, expandir, buscar y filtrar.

### Tests for User Story 1

- [ ] T024 [P] [US1] Crear `backend/tests/Feature/Estructura/Unidades/ConsultarUnidadesTest.php` (reemplaza el existente): orden jerárquico; `nivel_arbol`; `tiene_dependientes`, `admite_hijas` y `sigue_cadena`; `q` devuelve coincidencias más ancestras sin distinguir tildes; `estado=Inactiva` devuelve las inactivas con sus ancestras; el Usuario solo recibe activas y nunca auditoría; 401; consulta de una inactiva; 404 `dependientes_activas` incluye las indirectas; prueba de volumen con 500 unidades en una sola respuesta, sin paginar (SC-003).
- [ ] T025 [P] [US1] Crear `backend/tests/Feature/Estructura/TiposUnidad/ConsultarTiposTest.php`: cadena por nivel con `es_raiz`, cantidad de unidades y token; disponible para el Usuario; 401; sin `DELETE`
- [ ] T026 [P] [US1] Crear `frontend/src/modules/estructura/unidades/pages/UnidadesPage.dom.test.tsx` (reemplaza): estados cargando, vacío, sin coincidencias, error; solo la raíz contraída al entrar; expandir y contraer con `aria-expanded`; Expandir todo / Contraer todo; búsqueda expandida con controles deshabilitados; resumen "N unidades · M activas"; cadena de tipos; advertencia "No sigue la cadena"; el Usuario no ve filtro ni botones de gestión; URL (`Activa` no se escribe)
- [ ] T027 [P] [US1] Crear `components/UnidadFila.dom.test.tsx` y `CadenaDeTipos.dom.test.tsx`: sangría de 24 px, control de expansión, prefijo oculto "Nivel N", badges, columnas nombre, código, tipo, estado y acciones

### Implementation for User Story 1

- [X] T028 [US1] Modificar `ListarUnidades.php`, `FiltroUnidadesDTO`, `UnidadDTO`, `UnidadResource` y `ListarUnidadesRequest` según T015; crear `ListarTiposDeUnidad.php`, `TipoUnidadDTO`/`CadenaDeTiposDTO`, `TipoUnidadResource`, `TipoUnidadController@index` y la ruta `GET /api/tipos-unidad`; ampliar `EstructuraService` y `EstructuraFacade` con la cadena de tipos (solo lectura) La unidad padre del listado incluye su `estado`.
- [X] T029 [P] [US1] Crear `components/CadenaDeTipos.tsx` (chips por nivel, botón "Editar tipos" solo Administrador) y reescribir `UnidadFila.tsx` y `UnidadesTree.tsx` (columnas, sangría, control de expansión, advertencia, acciones solo Administrador) usando `arbol.ts`
- [X] T030 [US1] Reescribir `pages/UnidadesPage.tsx` (encabezado, cadena, búsqueda, filtro de estado Administrador, Expandir todo / Contraer todo, resumen, estados cargando / vacío / error, mensaje de confirmación `aria-live`); eliminar `components/FormularioEnLinea.tsx`
- [ ] T031 [US1] Reescribir el E2E de consulta en `frontend/e2e/unidades.spec.ts`: el Usuario ve solo la raíz contraída, expande Gerencia de Talento Humano, busca "area" y "desarrollo" (coincidencias con ancestras y ramas expandidas), no ve inactivas aunque pida `Todas`, `DELETE` responde 404/405 y no ve acciones; el Administrador alterna Activas, Inactivas y Todas y ve la auditoría

**Checkpoint**: MVP de consulta

---

## Phase 4: User Story 2 - Crear una unidad (Priority: P1)

**Goal**: Crear una unidad bajo una padre; el tipo lo calcula el sistema.

### Tests for User Story 2

- [ ] T032 [P] [US2] Reescribir `backend/tests/Feature/Estructura/Unidades/CrearUnidadTest.php`: 201 con tipo del nivel siguiente, código `UO-###`, Activa, fecha de hoy; padre obligatoria; padre inactiva, inexistente o del último nivel → 422 ("Este tipo no admite subunidades."); `tipo_unidad_id`, `estado` y fechas en el body → 422; no se puede crear otra raíz; 401/403; nombre vacío o de más de 150; nombre repetido aceptado
- [ ] T033 [P] [US2] Reescribir `OpcionesDeUnidadTest.php`: cadena y unidades en orden de árbol con `habilitada`; 401/403
- [ ] T034 [P] [US2] Reescribir `backend/tests/Unit/Estructura/Unidades/CrearUnidadTest.php` con el doble en memoria (tipo calculado, padre del último nivel)
- [ ] T035 [P] [US2] Crear `components/UnidadModal.crear.dom.test.tsx`: campos Nombre y Unidad padre; "Selecciona una unidad padre para determinar el tipo" y "Se creará como: «tipo»"; opciones deshabilitadas (inactivas; último nivel con "Este tipo no admite subunidades"); "Agregar subunidad" preselecciona; validación y errores 422 junto al campo; botón bloqueado al guardar; el Usuario no ve botones

### Implementation for User Story 2

- [X] T036 [US2] Modificar `GuardarUnidadRequest.php` (nombre y padre obligatoria; prohíbe tipo, código, estado y fechas), `CrearUnidad.php` y `EloquentUnidadRepository::crear` (padre activa y que admite hijas; tipo por nivel; código y auditoría en la transacción), `ListarOpcionesDeUnidad.php`, `OpcionesDeUnidadResource` y `OpcionesDeUnidadRequest` (`reubicar`)
- [X] T037 [P] [US2] Crear `components/UnidadModal.tsx` (crear; "Agregar subunidad" es una acción de `UnidadFila` que lo abre con la padre preseleccionada); modificar `hooks/useGuardarUnidad.ts`, `validation.ts` (sin tipo; padre obligatoria) y retirar `UnidadForm.tsx`
- [X] T038 [US2] Integrar en `UnidadesPage.tsx` "Crear unidad" y "Agregar subunidad" (deshabilitado en inactivas y último nivel con su descripción), expandir padre y ancestras al crear y mostrar "Unidad creada."
- [ ] T039 [US2] Añadir al E2E la creación: modal vacío con errores, opciones deshabilitadas, crear una Sección bajo un Departamento y verla con sangría; "Agregar subunidad"; el Usuario recibe 403 en `POST` y `opciones`

---

## Phase 5: User Story 3 - Editar una unidad y reubicarla (Priority: P2)

### Tests for User Story 3

- [ ] T040 [P] [US3] Reescribir `ActualizarUnidadTest.php` y `ReglasDelArbolTest.php`: renombrar; reubicar bajo una padre del tipo superior con su rama; padre de otro tipo, propia, dependiente, inactiva o del último nivel → 422; la raíz solo se renombra y no se reubica; `tipo_unidad_id` en el body → 422; sin cambios no escribe; 409 por versión; conservar una padre que no sigue la cadena; unidad inactiva editable; 403
- [ ] T041 [P] [US3] Reescribir `backend/tests/Unit/Estructura/Unidades/ActualizarUnidadTest.php`
- [ ] T042 [P] [US3] Crear `components/UnidadModal.editar.dom.test.tsx` y `UnidadInfo.dom.test.tsx` (reemplaza): título, valores actuales, opciones con `reubicar` = id, selector de la raíz deshabilitado con "Ninguna (unidad raíz)", tipo y datos de solo lectura, auditoría solo si llega, conflicto 409 con "Recargar", 404, guardar sin cambios, versión congelada al abrir

### Implementation for User Story 3

- [X] T043 [US3] Modificar `ActualizarUnidad.php` y `EloquentUnidadRepository::actualizar` (regla del tipo superior; la raíz sin padre; conservar la padre actual) y el controlador/ruta `PUT`
- [X] T044 [US3] Extender `UnidadModal.tsx` con el modo editar y ajustar `UnidadInfo.tsx` (tipo, código, fechas, cantidades, auditoría); retirar la lectura previa de `FormularioEnLinea`, conservando la lectura fresca y la versión congelada
- [ ] T045 [US3] Añadir al E2E la edición: renombrar, reubicar con su rama, selector sin la propia ni sus dependientes, raíz, inactiva, sin cambios, conflicto concurrente simulado por la API; 403 al Usuario en `PUT`

---

## Phase 6: User Story 4 - Desactivar en cascada y reactivar (Priority: P2)

### Tests for User Story 4

- [ ] T046 [P] [US4] Reescribir `CambiarEstadoUnidadTest.php`: cascada con fecha del día en cada unidad; dependientes ya inactivas intactas; la raíz → 422; posiciones Ocupadas o Vacantes en cualquier unidad de la rama → 422 con unidades y cantidades y nada cambia; Suprimidas no bloquean; reactivar con padre activo, sin reactivar dependientes; padre inactiva → 422; mismo estado → 200 sin historial; `version` y `updated_by` no cambian; 403
- [ ] T047 [P] [US4] Crear `ImpactoDeDesactivacionTest.php` (dependientes activas, unidades con posiciones, `procede`; 403 al Usuario) y reescribir `SinEliminarUnidadesTest.php` (7 rutas de unidades sin `DELETE`) y el Unit `CambiarEstadoUnidadTest.php`
- [ ] T048 [P] [US4] Crear `DesactivacionAtomicaTest.php`: un fallo al escribir el historial revierte toda la cascada; una dependiente creada en paralelo no queda activa bajo una padre inactiva
- [ ] T049 [P] [US4] Crear `components/DesactivarModal.dom.test.tsx` y reescribir `UnidadEstado.dom.test.tsx`: confirmación simple, lista de dependientes y botón "Desactivar N unidades", bloqueo por posiciones sin confirmar, 422 en el modal, raíz deshabilitada ("La unidad raíz no se puede desactivar"), Reactivar sin diálogo y deshabilitado con padre inactiva ("Reactiva primero la unidad padre"), el Usuario no ve botones

### Implementation for User Story 4

- [X] T050 [US4] Crear `ConsultarImpactoDeDesactivacion.php`, `ImpactoDesactivacionDTO`/`Resource` y la ruta `GET /api/unidades/{unidad}/impacto-desactivacion`; modificar `CambiarEstadoUnidad.php` y `EloquentUnidadRepository::cambiarEstado` (cascada según Complexity Tracking, auditoría por unidad, rechazo de la raíz) y `CambiarEstadoUnidadRequest`
- [X] T051 [P] [US4] Crear `components/DesactivarModal.tsx` y modificar `hooks/useCambiarEstadoUnidad.ts`; en `UnidadFila.tsx` botones Desactivar (deshabilitado en la raíz) y Reactivar (deshabilitado con padre inactiva) con descripción accesible
- [X] T052 [US4] Integrar en `UnidadesPage.tsx` los avisos "Unidad desactivada." y "Unidad reactivada." y el error 422 al reactivar
- [ ] T053 [US4] Añadir al E2E el estado: desactivar la rama de Infraestructura con su lista, rechazo por la posición Ocupada de Desarrollo Web, desactivar una hoja, reactivar unidad por unidad y el rechazo con padre inactiva; 403 al Usuario en `PATCH`

---

## Phase 7: User Story 5 - Gestionar la cadena de tipos (Priority: P2)

### Tests for User Story 5

- [ ] T054 [P] [US5] Crear `backend/tests/Feature/Estructura/TiposUnidad/CrearTipoTest.php`: nivel = último + 1; nombre vacío, > 50 o repetido sin tildes ni mayúsculas → 422; 403; sin `DELETE`
- [ ] T055 [P] [US5] Crear `GuardarCadenaTest.php`: renombrar; mover; ids exactos; raíz primera; nombres únicos por índice; conflictos → 422 con `unidades_invalidas` (unidad, tipo y nivel nuevos, padre) y nada cambia; saltos de nivel permitidos y `sigue_cadena` falso; sin cambios → 200 sin escribir; 409 por token; el cambio de nivel no modifica el tipo de las unidades; 403
- [ ] T056 [P] [US5] Crear `CadenaAtomicaTest.php`: un fallo en el historial revierte renombres y niveles
- [ ] T057 [P] [US5] Crear `NuevoTipoModal.dom.test.tsx`, `EditarTiposModal.dom.test.tsx`, `VistaPreviaDeCadena.dom.test.tsx` y `BloqueoDeCadenaModal.dom.test.tsx`: texto de ayuda, validación, raíz con candado y sin asa ni flechas, subir y bajar con aviso, arrastre, vista previa resaltada, "Guardar cambios" deshabilitado sin cambios, errores por fila, 422 abre el bloqueo con "Ver en el árbol" y "Volver a editar", 409, el Usuario no ve los botones "Ver en el árbol" deja `estado=Todas` y `q=código` en la URL.

### Implementation for User Story 5

- [X] T058 [US5] Crear `CrearTipoDeUnidad.php`, `GuardarCadenaDeTipos.php`, `CrearTipoUnidadRequest`, `GuardarCadenaRequest`, `TipoUnidadController@store` y `@update` y las rutas `POST` y `PUT /api/tipos-unidad` con `admin`
- [X] T059 [P] [US5] Crear `components/NuevoTipoModal.tsx` y `hooks/useGuardarTipos.ts`
- [X] T060 [US5] Crear `components/EditarTiposModal.tsx` (borrador, arrastre nativo, subir / bajar, candado), `VistaPreviaDeCadena.tsx` y `BloqueoDeCadenaModal.tsx`; "Ver en el árbol" cierra los modales y busca por código "Ver en el árbol" cierra los modales, pone el estado en `Todas` y escribe el código en la búsqueda (que ya expande las ramas), de modo que la unidad aparezca aunque esté inactiva o dentro de una rama contraída.
- [X] T061 [US5] Integrar "Nuevo tipo" y "Editar tipos" en `UnidadesPage.tsx` y `CadenaDeTipos.tsx`; recargar cadena y listado tras guardar y mostrar el mensaje
- [ ] T062 [US5] Añadir al E2E de tipos: agregar un tipo, renombrar, mover con las flechas, vista previa, bloqueo con la lista y "Ver en el árbol", volver al borrador, conflicto concurrente simulado por la API y restablecimiento de la cadena; 403 al Usuario

---

## Phase 8: User Story 6 - Trazabilidad (Priority: P3)

- [ ] T063 [P] [US6] Reescribir `AuditoriaUnidadesTest.php`: creación con cuatro filas (nombre, tipo, padre, estado) y valor anterior nulo; edición; reubicación solo en la unidad movida; cascada con filas de estado y fecha en cada unidad; reactivación; sin cambios no escribe; inmutabilidad; no se mezclan con `puesto` ni `tipo_unidad`
- [ ] T064 [P] [US6] Crear `AuditoriaTiposTest.php`: creación (nombre y nivel con anterior nulo), renombre, una fila por cada tipo que cambió de nivel, `created_by` / `updated_by`
- [X] T065 [US6] Registrar la auditoría de tipos y de la cascada en los repositorios (comparadores de T008 y de unidades) y comprobar que `updated_by` de la unidad no cambia con el estado

---

## Phase 9: Polish & Cross-Cutting Concerns

- [ ] T066 [P] Auditar contraste y foco del árbol, la cadena, los modales y la lista de tipos en ambos temas (≥ 4.5:1, foco visible, tabulación, foco devuelto) y reflejarlo en el E2E
- [ ] T067 [P] Verificar sin scroll horizontal de la página a 320 y 1440 px el árbol con niveles profundos, la cadena y cada modal, en el E2E
- [ ] T068 [P] Revisar seguridad: rol verificado en el backend en todas las escrituras, `opciones` e `impacto-desactivacion`; el Usuario nunca recibe inactivas ni auditoría; estado, tipo y fechas del body se rechazan; invariantes del árbol y de la cadena bajo peticiones simultáneas; errores en español sin detalles internos; ningún módulo toca las tablas de unidades ni de tipos
- [ ] T069 [P] Revisar que `design/DESIGN.md` y `design/Unidades.dc.html` reflejan lo implementado
- [X] T070 Reiniciar la base de desarrollo con `php artisan migrate:fresh --seed` y comprobar 5 tipos, 12 unidades (UO-008 inactiva), la raíz única, las posiciones sembradas y las ramas de prueba
- [ ] T071 Ejecutar las puertas de calidad del backend: `vendor/bin/pint --test` y `vendor/bin/pest`
- [ ] T072 Ejecutar las puertas de calidad del frontend: `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `./node_modules/.bin/playwright test`
- [ ] T073 Ejecutar la validación de `quickstart.md` de punta a punta
- [ ] T074 Actualizar o retirar las pruebas de la versión anterior que dejan de aplicar: `UnidadForm.dom.test`, `UnidadForm.edit.dom.test`, `validation.test.ts`, `EstructuraFacadeUnidadesTest`, `RepositorioUnidadesTest`, `EscrituraAtomicaTest` y el E2E de foco y escala ya añadido a `unidades.spec.ts`. **Requiere aprobación expresa antes de borrar cualquier archivo de prueba**; lo que se pueda adaptar, se adapta (previa a T071 y T072)

---

## Dependencies & Execution Order

- **T074** va antes de T071 y T072.
- **Fase 1** (T001–T005) primero; T004 y T005 antes de cualquier tarea de frontend (constitución III).
- **Fase 2** bloquea todas las historias. Dentro de ella: T006 → T013/T014; T007–T009 → T010–T015.
- **US1 y US2 (P1)** dependen solo de la fase 2; US3 y US4 de US1; US5 de US1 (y de T007, T014); US6 de las anteriores.
- Dentro de cada historia: pruebas → backend → frontend → E2E. Tareas que tocan el mismo archivo (`UnidadesPage.tsx`, `EloquentUnidadRepository.php`, `unidades.spec.ts`) van en secuencia.

## Implementation Strategy

1. **MVP**: Fases 1–2 y US1 (consulta del árbol y la cadena) y US2 (crear).
2. **Entrega incremental**: US3 (editar), US4 (cascada), US5 (tipos), US6 (auditoría), pulido.
3. Cada historia termina con su E2E antes de pasar a la siguiente.

### Notas

- Todas las tareas están **pendientes**: aunque parte del código anterior se reutiliza, ninguna tarea de esta revisión está hecha.
- Resolver los **puntos por confirmar** de spec.md antes de implementar US4 y US5 solo si negocio cambia los valores por defecto (saltos de nivel permitidos, bloqueo sin otra vía, cascada rechazada con posiciones).

### Estado de la implementación (2026-10-15)

- **Hechas**: todo el código (backend, frontend, semillas y diseño) y la verificación de la base con `php artisan migrate:fresh --seed` (T070: 5 tipos, 12 unidades, UO-008 inactiva, posiciones repartidas; lectura del árbol por el repositorio sin errores).
- **Omitidas por indicación expresa** ("no hagas nada de pruebas"): todas las tareas de pruebas (T018, T019, T024–T027, T031, T032–T035, T039–T042, T045–T049, T053–T057, T062–T064), las partes de pruebas de T016, T021 y T022, y las puertas de calidad T001, T066–T068, T071–T073. Quedan pendientes, igual que T069 y T074.
- **Pruebas anteriores rotas**: las pruebas de la versión anterior (backend: dobles en memoria y tests de unidades que usan `tipo_unidad_id` o `UnidadConDependenciasEnUso`; frontend: `UnidadFila`, `UnidadForm*`, `UnidadesPage` y `validation`) ya no compilan contra el código nuevo. Se resuelven con T074, que exige aprobación expresa antes de borrar archivos de prueba.

### Ampliación desde 006-posiciones (2026-10-16)

- [ ] T075 Rechazar la reubicación de una unidad que dejaría una posición vigente reportando a otra de una unidad inferior o de otra rama (FR-019, ampliación). **Se implementa y se prueba desde 006-posiciones** (tareas T078 y T087 de `specs/006-posiciones/tasks.md`); queda aquí como referencia cruzada y se marca cuando 006 la cierre.
