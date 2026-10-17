# Tasks: Categorías e indicadores cualitativos de reconocimiento

**Input**: `specs/016-categorias-reconocimiento/` (plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md)

**Prerequisites**: plan.md, spec.md; Diccionario de Competencias (001) y su `EstructuraFacade` instalados; archivos `design/CategoriasReconocimiento.dc.html` y `design/V_cr-*.dc.html` (27) presentes (no se modifican).

**Formato**: `- [ ] T### [P?] [US?] Descripción con ruta`. `[P]` = archivos distintos y sin dependencia con tareas incompletas. Rutas relativas a la raíz del repositorio. `BE = backend/app/Modules/Compensaciones`, `BT = backend/tests`, `FE = frontend/src/modules/compensaciones/categorias`.

**Roles** (research D1/D2): solo `admin` (Administrador) y `user` (Usuario). Lectura = `auth:sanctum`; escritura y `competencias-disponibles` = `auth:sanctum` + middleware `EnsureAdmin`. Aislamiento: unicidad global (sin tenant).

## Phase 1: Setup

- [ ] T001 Crear el módulo con `php artisan make:module Compensaciones` (esqueleto Clean Architecture en `BE/`) y comprobar que existen `Domain/`, `Application/`, `Infrastructure/`, `Presentation/` y `Routes/api.php` sin tocar otros módulos.
- [ ] T002 Crear `BE/Infrastructure/Providers/CompensacionesServiceProvider.php` (carga `BE/Routes/api.php` bajo el prefijo `api/compensaciones`, sin enlaces todavía) y registrarlo en `backend/bootstrap/providers.php`.
- [ ] T003 [P] Migración `backend/database/migrations/2026_10_20_000001_create_categorias_reconocimiento_table.php` según data-model.md: `id`; `codigo` string(20) único; `nombre` string(100); `nombre_normalizado` string(100) único; `descripcion` text; `competencia_id` FK a `competencias` con `restrictOnDelete` e índice; `puntuacion_base` unsignedSmallInteger; `icono` string(50); `estado` string(10) defecto `Activo` con índice; `created_by` y `updated_by` unsignedBigInteger nullable sin FK; `created_at`/`updated_at` con precisión de microsegundos (`timestamp(6)`). Sin soft delete.
- [ ] T004 [P] Migración `backend/database/migrations/2026_10_20_000002_create_indicadores_reconocimiento_table.php`: `id`; `categoria_reconocimiento_id` FK con `restrictOnDelete` e índice; `orden` unsignedSmallInteger; `texto` string(250); `texto_normalizado` string(250); timestamps; únicos `(categoria_reconocimiento_id, orden)` y `(categoria_reconocimiento_id, texto_normalizado)`.
- [ ] T005 [P] Migración `backend/database/migrations/2026_10_20_000003_create_auditoria_categorias_reconocimiento_table.php`: `id`; `categoria_reconocimiento_id` FK con `restrictOnDelete` e índice; `campo` string(30); `valor_anterior` text nullable; `valor_nuevo` text nullable; `user_id` unsignedBigInteger nullable sin FK; solo `created_at` (sin `updated_at`). Tabla propia: no se escribe en `auditoria_cambios` de Estructura (research D10).
- [ ] T006 [P] Esqueleto frontend: crear `FE/` con subcarpetas `hooks/`, `components/`, `pages/` solo cuando haya archivos, y `FE/iconos.ts` con la constante única de los doce identificadores: `estrella, trofeo, corazon, bombilla, rayo, escudo, equipo, objetivo, mensaje, libro, bandera, diamante`.

## Phase 2: Foundational (bloquea todas las historias)

- [ ] T007 [P] Objeto de valor `BE/Domain/ValueObjects/Icono.php`: lista cerrada de los doce identificadores de T006; cualquier otro valor lanza excepción de dominio.
- [ ] T008 [P] Objeto de valor `BE/Domain/ValueObjects/PuntuacionBase.php`: entero de 1 a 1000; rechaza decimales, texto, 0, negativos y 1001.
- [ ] T009 [P] Objeto de valor `BE/Domain/ValueObjects/NombreCategoria.php`: recorta espacios, exige 3 a 100 caracteres, y expone la forma normalizada (minúsculas, sin tildes, espacios recortados y colapsados) usada para la unicidad; sin etiquetas HTML.
- [ ] T010 [P] Objeto de valor `BE/Domain/ValueObjects/Indicador.php`: texto de 5 a 250 caracteres tras recortar, sin HTML, con forma normalizada; y `BE/Domain/ValueObjects/EstadoCategoria.php` (`Activo` | `Inactivo`).
- [ ] T011 Entidad `BE/Domain/Entities/Categoria.php` (sin Laravel/Eloquent): código, nombre, descripción (10 a 500 caracteres tras recortar, sin HTML), competencia, puntuación, ícono, estado, lista de 1 a 5 indicadores sin repetidos por forma normalizada, y la regla `seleccionable` (estado Activo y competencia activa). Depende de T007–T010.
- [ ] T012 [P] Excepciones de dominio `BE/Domain/Exceptions/{CategoriaNoEncontrada,CategoriaModificadaPorOtro,CompetenciaNoDisponible,NombreDeCategoriaRepetido}.php`; el mensaje de `CategoriaModificadaPorOtro` es exactamente «La categoría fue modificada por otro usuario. Recarga para ver los cambios.»
- [ ] T013 [P] Contrato `BE/Domain/Repositories/CategoriaRepository.php` (buscar paginado con filtros `q`, `estado`, `competencia_id`; `porId`; `existeNombreNormalizado(excluyendoId)`; `crear`; `actualizarSiVigente(id, updated_at_leido, datos)`; `reemplazarIndicadores`) y puerto `BE/Application/Ports/CompetenciasPort.php` (`activas()`, `porId`, `porIds`, `idsPorNombre`).
- [ ] T014 [P] Modelos Eloquent `BE/Infrastructure/Persistence/Eloquent/Models/{CategoriaReconocimientoModel,IndicadorReconocimientoModel}.php`: `$dateFormat = 'Y-m-d H:i:s.u'`, sin `SoftDeletes`, relación a indicadores ordenada por `orden`; y factories `backend/database/factories/CategoriaReconocimientoFactory.php`.
- [ ] T015 Adaptador `BE/Infrastructure/Adapters/CompetenciasDesdeEstructura.php` que implementa `CompetenciasPort` solo con `EstructuraFacade` (`getCompetenciasActivas`, `getCompetencia`, `getCompetenciasPorIds`, `getTodasLasCompetencias`); `idsPorNombre` filtra por nombre normalizado en memoria. Ningún JOIN ni lectura de tablas de Estructura.
- [ ] T016 Repositorio `BE/Infrastructure/Persistence/Eloquent/Repositories/EloquentCategoriaRepository.php`: búsqueda sin distinguir mayúsculas ni tildes por `nombre_normalizado` o por `competencia_id IN (ids de idsPorNombre)`, orden por `codigo` ascendente, 15 por página con página fuera de rango = última, `actualizarSiVigente` con `UPDATE … WHERE id = ? AND updated_at = ?`.
- [ ] T017 [P] Servicio `BE/Application/Services/AuditoriaDeCategoria.php`: escribe en la tabla propia `auditoria_categorias_reconocimiento` (modelo `BE/Infrastructure/Persistence/Eloquent/Models/AuditoriaCategoriaModel.php`, sin tocar `auditoria_cambios` de Estructura) con `categoria_reconocimiento_id`, `campo` ∈ {nombre, descripcion, competencia_id, puntuacion_base, icono, indicadores, estado}, valor anterior/nuevo (indicadores como JSON de la lista de textos en orden; competencia como su id), `user_id`; una fila por campo realmente modificado; al crear, campos iniciales con `valor_anterior` nulo. Se accede mediante un puerto propio del módulo.
- [ ] T018 [P] `BE/Application/Services/PoliticaDeAcceso.php`: `puedeVer(usuario)` = autenticado; `puedeGestionar(usuario)` = `tokenCan('admin')`; único punto a cambiar cuando existan roles nuevos (research D2).
- [ ] T019 Enlazar en `CompensacionesServiceProvider` los contratos (`CategoriaRepository`, `CompetenciasPort`, auditoría) con sus implementaciones. Depende de T013–T017.
- [ ] T020 [P] Resource `BE/Presentation/Resources/CategoriaResource.php` según contracts/api-categorias.md: `competencia`, `competencia_activa`, `seleccionable`, `updated_at` ISO 8601 con microsegundos, `created_by`/`updated_by`/`created_at` solo para `admin`, `indicadores` omitidos en el listado.
- [ ] T021 [P] Frontend base: `FE/api.ts` (funciones sobre el cliente HTTP compartido de `frontend/src/shared/api`, sin importar Axios: listar, obtener, crear, actualizar, cambiarEstado, competenciasDisponibles, competenciasFiltro) con tipos de la contracts; `FE/text.ts` con todos los textos del diseño (incluidos «No se encontraron categorías de reconocimiento que coincidan con la búsqueda.», «Competencia inactiva» y el mensaje de conflicto), sin términos monetarios ni de canje para la puntuación (FR-040: es solo un valor de ordenamiento del ranking); `FE/nav.tsx` con la entrada de navegación y la ruta `/compensaciones/categorias` registrada en `frontend/src/App.tsx` dentro del App Shell.
- [ ] T022 [P] Pruebas unitarias de dominio en `BT/Unit/Compensaciones/DominioTest.php`: normalización de nombre y texto (mayúsculas, tildes, espacios), rango de puntuación (0, 1, 1000, 1001, decimal), lista de íconos y regla `seleccionable`.

**Checkpoint**: dominio, persistencia, puertos, auditoría y resource listos; aún sin endpoints.

## Phase 3: User Story 1 — Consultar el catálogo (Priority: P1) 🎯 MVP

**Objetivo**: cualquier usuario autenticado lista (solo activas), busca, filtra, pagina y abre el detalle; el Administrador ve inactivas, filtro de estado y auditoría.
**Prueba independiente**: con categorías activas e inactivas sembradas, un Usuario solo ve activas, sin sesión se rechaza, la búsqueda ignora mayúsculas y tildes y el estado queda en la URL.

- [ ] T023 [P] [US1] Seeder `backend/database/seeders/CategoriasReconocimientoSeeder.php` (idempotente; depende de `CompetenciasSeeder`): al menos 18 categorías con nombres que mezclan mayúsculas y tildes e íconos variados; al menos 3 Inactivas; al menos 1 Activa con competencia inactiva; categorías con 1 y con 5 indicadores. Registrarlo en `DatabaseSeeder.php`.
- [ ] T024 [P] [US1] Casos de uso `BE/Application/UseCases/ListarCategorias.php` y `BE/Application/UseCases/ObtenerCategoria.php`: el listado aplica `q` (hasta 100 caracteres), `estado` (`Activo` | `Inactivo` | `Todos`, defecto `Activo`) y `competencia_id`; quien no es administrador recibe siempre solo `Activo` (se ignora `estado`); el detalle devuelve también categorías inactivas y 404 si no existe.
- [ ] T025 [P] [US1] `BE/Presentation/Requests/ListarCategoriasRequest.php` (422 si `q` > 100, `estado` fuera de la lista o `page`/`competencia_id` inválidos).
- [ ] T026 [P] [US1] Caso de uso `BE/Application/UseCases/ListarCompetenciasParaFiltro.php` y `GET /api/compensaciones/competencias-filtro` (`auth:sanctum`, sin exigir `admin`): el administrador recibe competencias activas e inactivas con `estado`; los demás, solo activas; `{id, codigo, nombre, tipo, estado}` ordenadas por nombre, vía `CompetenciasPort` (contracts/api-categorias.md).
- [ ] T027 [US1] `BE/Presentation/Controllers/CategoriaController.php` (delgado): `index` y `show`; rutas `GET /categorias`, `GET /categorias/{categoria}` y `GET /competencias-filtro` bajo `auth:sanctum` en `BE/Routes/api.php`. Depende de T024, T025.
- [ ] T028 [P] [US1] Pruebas `BT/Feature/Compensaciones/ListarCategoriasTest.php`: 401 sin sesión; Usuario solo activas aunque pida `estado=Todos`; administrador con filtro de estado; búsqueda por nombre de categoría y por nombre de competencia con otras mayúsculas y tildes; filtro por competencia; paginación de 15 con página fuera de rango = última; orden por código; 422 con parámetros inválidos.
- [ ] T029 [P] [US1] Pruebas `BT/Feature/Compensaciones/ObtenerCategoriaTest.php`: detalle con indicadores en orden; categoría inactiva responde 200; 404 inexistente; auditoría (`created_by`, `updated_by`) solo para administrador.
- [ ] T030 [P] [US1] Pruebas `BT/Feature/Compensaciones/CompetenciasFiltroTest.php`: 401 sin sesión; `user` recibe solo activas; `admin` recibe activas e inactivas con `estado`; orden por nombre.
- [ ] T031 [P] [US1] Hooks `FE/hooks/useListadoParams.ts` (búsqueda `q`, `competencia`, `estado`, `page` en la URL; al cambiar filtro o búsqueda la página vuelve a 1; el campo de búsqueda limita a 100 caracteres al escribir o pegar), `FE/hooks/useCategorias.ts` y `FE/hooks/useCategoria.ts` y `FE/hooks/useCompetenciasFiltro.ts` (TanStack Query; opciones del filtro desde `GET /competencias-filtro`).
- [ ] T032 [US1] Componentes y página según `design/CategoriasReconocimiento.dc.html`: `FE/components/CategoriasTable.tsx` (columnas código, ícono, nombre, competencia con la advertencia «Competencia inactiva», puntuación, badge de estado, acciones; «Ver» siempre), `FE/components/FiltrosCategorias.tsx` (filtro de competencia para todos, con opciones de `useCompetenciasFiltro` y las inactivas marcadas para el administrador; filtro de estado solo administrador), `FE/components/DetalleCategoria.tsx` (datos, indicadores como lista de viñetas y auditoría solo administrador) y `FE/pages/CategoriasReconocimientoPage.tsx` con estados cargando (skeleton), vacío, sin coincidencias, error con «Reintentar» y éxito. La guarda de ruta es solo de presentación.
- [ ] T033 [P] [US1] Pruebas Vitest `FE/pages/CategoriasReconocimientoPage.dom.test.tsx`: estados de la vista, parámetros en la URL, advertencia de competencia inactiva y controles de gestión ocultos para Usuario.

**Checkpoint**: la historia 1 es demostrable por sí sola con el seeder.

## Phase 4: User Story 2 — Crear categoría con indicadores (Priority: P1)

**Objetivo**: el Administrador crea una categoría con 1 a 5 indicadores; recibe `CAT-###` y nace Activa.
**Prueba independiente**: catálogo vacío → crear «Trabajo en equipo» con dos indicadores obtiene `CAT-001`, nace Activa; repetir el nombre con otra capitalización o tildes se rechaza.

- [ ] T034 [P] [US2] `BE/Presentation/Requests/GuardarCategoriaRequest.php` (base para crear y actualizar): `nombre` obligatorio, recortado, 3 a 100; `descripcion` obligatoria, recortada, 10 a 500; `competencia_id` obligatorio; `puntuacion_base` entero 1 a 1000 (rechaza decimales); `icono` en la lista cerrada; `indicadores` lista de 1 a 5, cada uno 5 a 250 caracteres recortado y sin repetirse (comparación normalizada); sin etiquetas HTML en nombre, descripción e indicadores; rechaza (422) las claves `codigo`, `estado`, `orden`, `created_by`, `updated_by`, `created_at` e `id`.
- [ ] T035 [US2] Caso de uso `BE/Application/UseCases/CrearCategoria.php` en una transacción: valida competencia existente y activa, unicidad por `nombre_normalizado` (activas e inactivas), genera el código `CAT-` + siguiente número con mínimo tres dígitos (tras `CAT-999` sigue `CAT-1000`) con reintento hasta 3 veces ante violación de unicidad, crea Activa con `created_by`, inserta indicadores con `orden` consecutivo desde 1 y escribe la auditoría inicial.
- [ ] T036 [P] [US2] `BE/Application/UseCases/ListarCompetenciasDisponibles.php` y `GET /api/compensaciones/competencias-disponibles` (administrador): competencias activas `{id, codigo, nombre, tipo}` por nombre.
- [ ] T037 [US2] En `CategoriaController` agregar `store` (201 con `CategoriaResource`; 422 por validación; solo `admin`) y el controlador de competencias disponibles; rutas `POST /categorias` y `GET /competencias-disponibles` con `EnsureAdmin`. Depende de T034–T036.
- [ ] T038 [P] [US2] Pruebas `BT/Feature/Compensaciones/CrearCategoriaTest.php`: 401; 403 para `user`; 201 con código `CAT-001`; 422 por nombre repetido con otras mayúsculas, tildes y espacios (también contra una inactiva), indicadores en 0, 6 o repetidos, puntuación 0/1001/decimal, ícono inválido, competencia inactiva o inexistente, claves prohibidas y HTML en texto; auditoría inicial; paso de `CAT-999` a `CAT-1000`; reintento por colisión de código.
- [ ] T039 [P] [US2] `FE/validation.ts` (esquemas Zod que replican la validación de T034 con los textos de `text.ts`) y `FE/validation.test.ts`.
- [ ] T040 [US2] Formulario modal `FE/components/CategoriaFormModal.tsx` + `FE/components/ListaIndicadores.tsx` (`useFieldArray`; «Agregar indicador» deshabilitado en 5, «Eliminar» deshabilitado cuando queda 1) + `FE/components/SelectorIcono.tsx` (doce íconos de `iconos.ts`) + `FE/hooks/useGuardarCategoria.ts` y `FE/hooks/useCompetenciasDisponibles.ts`: React Hook Form con Zod, errores por campo (los 422 se asignan al campo, incluido `indicadores.N`), valores conservados, Guardar bloqueado mientras hay envío pendiente, confirmación al cancelar con cambios, mensaje de éxito en región `aria-live` y listado actualizado; «Nueva categoría» solo para administrador. Sin reintento automático de la mutación.
- [ ] T041 [P] [US2] Pruebas Vitest `FE/components/CategoriaFormModal.dom.test.tsx`: límites de indicadores, errores por campo, envío bloqueado, mapeo de 422 y confirmación al descartar.

**Checkpoint**: las historias 1 y 2 funcionan de forma independiente.

## Phase 5: User Story 3 — Editar categoría e indicadores (Priority: P1)

**Objetivo**: el Administrador edita datos e indicadores; una edición concurrente no se sobrescribe en silencio.
**Prueba independiente**: editar puntuación e indicadores queda auditado; dos ediciones simultáneas → la segunda recibe el aviso y conserva lo escrito.

- [ ] T042 [US3] Caso de uso `BE/Application/UseCases/ActualizarCategoria.php` en una transacción: `updated_at` obligatorio; detecta «sin cambios reales» (200 sin tocar `updated_at`, autor ni auditoría); `UPDATE … WHERE id = ? AND updated_at = ?` y, si no afecta filas, `CategoriaModificadaPorOtro` (409 sin aplicar nada); la competencia solo se exige activa si cambia; el nombre no compite consigo mismo; reemplaza los indicadores (eliminar e insertar en la misma transacción, reasignando `orden` desde 1); asigna `updated_by`; una fila de auditoría por campo realmente modificado.
- [ ] T043 [US3] En `CategoriaController` agregar `update` (`PUT /categorias/{categoria}`, `EnsureAdmin`; 200, 404, 409, 422) reutilizando `GuardarCategoriaRequest` con `updated_at` requerido. Depende de T042.
- [ ] T044 [P] [US3] Pruebas `BT/Feature/Compensaciones/ActualizarCategoriaTest.php`: 403 para `user`; 404; 409 con token desactualizado sin cambios aplicados; PUT sin cambios → 200 sin alterar `updated_at` ni auditoría; nombre que difiere de sí misma solo en mayúsculas es aceptado; coincidir con otra se rechaza; competencia inactiva sin cambiarla se acepta y al cambiarla a una inactiva se rechaza; indicadores reemplazados y renumerados; una fila de auditoría por campo (indicadores como lista anterior y nueva); editar una categoría Inactiva la deja Inactiva.
- [ ] T045 [US3] Frontend de edición: `CategoriaFormModal` en modo edición con datos precargados, código no editable, competencia inactiva como valor actual deshabilitado con la advertencia y enviada sin cambios, y manejo del 409: muestra el mensaje de la spec, conserva lo escrito y ofrece «Recargar» que vuelve a consultar y reemplaza los valores.
- [ ] T046 [P] [US3] Ampliar `FE/components/CategoriaFormModal.dom.test.tsx`: precarga, competencia inactiva deshabilitada y 409 con «Recargar».

## Phase 6: User Story 4 — Activar y desactivar (Priority: P2)

**Objetivo**: el Administrador desactiva (con confirmación) y reactiva categorías sin eliminarlas.
**Prueba independiente**: desactivar una categoría con hitos previos la saca de los selectores de nuevos registros pero sigue en el historial; reactivarla la devuelve.

- [ ] T047 [US4] Caso de uso `BE/Application/UseCases/CambiarEstadoCategoria.php`: `estado` (`Activo`|`Inactivo`) y `updated_at`; si ya tiene ese estado y el token está vigente responde 200 sin cambios ni auditoría; si no, actualización condicional como en T042 (409 si desactualizado) y auditoría del campo `estado`; permite activar una categoría con competencia inactiva.
- [ ] T048 [US4] En `CategoriaController` agregar `cambiarEstado` (`PATCH /categorias/{categoria}/estado`, `EnsureAdmin`; 200, 404, 409, 422) y confirmar que no existe ruta `DELETE`.
- [ ] T049 [P] [US4] Pruebas `BT/Feature/Compensaciones/CambiarEstadoCategoriaTest.php`: 403 para `user`; 404; 409; PATCH al mismo estado → 200 sin cambios ni auditoría; desactivar y activar con auditoría de `estado`; activar con competencia inactiva; `DELETE` no existe (404/405).
- [ ] T050 [US4] Frontend: `FE/hooks/useCambiarEstadoCategoria.ts` y `FE/components/ConfirmarDesactivacion.tsx` (diálogo de confirmación que informa que la categoría no podrá usarse en nuevos hitos ni desafíos; Activar sin confirmación); botón de estado que alterna su texto; errores (incluido 409) en el diálogo o en un mensaje sobre el listado; sin botón de eliminar.
- [ ] T051 [P] [US4] Pruebas Vitest `FE/components/CategoriasTable.dom.test.tsx`: acciones por rol, texto alternante del botón de estado, confirmación solo al desactivar y mutaciones sin reintento.

## Phase 7: User Story 5 — Facade pública para otros módulos (Priority: P2)

**Objetivo**: Desafíos, Hitos y Ranking obtienen solo categorías seleccionables y siguen resolviendo las históricas.
**Prueba independiente**: con categorías activas, inactivas y con competencia inactiva, `listarSeleccionables()` devuelve solo las seleccionables y `obtener()` resuelve cualquiera.

- [ ] T052 [US5] `BE/Application/Services/CategoriasService.php` y `BE/Presentation/Facades/CategoriasFacade.php` según contracts/facade-categorias.md: `listarSeleccionables()`, `obtener(id)`, `obtenerPorIds(ids)`, `esSeleccionable(id)`; solo lectura; registrar el accessor `compensaciones-categorias-facade` en `CompensacionesServiceProvider`.
- [ ] T053 [P] [US5] Pruebas `BT/Feature/Compensaciones/CategoriasFacadeTest.php`: excluye inactivas y las de competencia inactiva; `obtener` devuelve inactivas con `competencia_activa`, `seleccionable` y `puntuacion_base`; al reactivarse la competencia la categoría vuelve a ser seleccionable sin guardar un tercer estado; cambiar la puntuación no altera un valor copiado previamente.

## Phase 8: Polish & cross-cutting

- [ ] T054 [P] E2E `frontend/e2e/categorias-reconocimiento.spec.ts` (Playwright): administrador crea, lista, edita y desactiva una categoría; Usuario ve solo activas sin controles de gestión.
- [ ] T055 [P] Prueba de rendimiento ligera `BT/Feature/Compensaciones/RendimientoListadoTest.php`: sembrar 500 categorías con indicadores y comprobar que `GET /api/compensaciones/categorias` responde la primera página en menos de 2 s (SC-010), con índices de `estado` y `competencia_id` en uso.
- [ ] T056 [P] Contraste campo por campo del diseño con la spec: recorrer `CategoriasReconocimiento.dc.html` y las 26 variantes `V_cr-*.dc.html` (en especial el contador de 250 caracteres de los indicadores, no localizado en la revisión previa) y registrar cualquier diferencia en `research.md` §D8 antes de cerrar la feature; no modificar `design/`.
- [ ] T057 [P] Revisión de accesibilidad (WCAG 2.2 AA): foco atrapado y retorno en modales, `aria-live` en confirmaciones, etiquetas de los íconos y 320/390/768/1024 px según las variantes responsive.
- [ ] T058 Puertas de calidad: `vendor/bin/pint --test` y análisis estático sin errores, `vendor/bin/pest` completo; en frontend `npm run lint`, `npx tsc --noEmit`, `npm test` y `npm run build`.
- [ ] T059 Ejecutar los escenarios de `quickstart.md` y anotar el resultado; confirmar con la feature de Desafíos el comportamiento de desafíos en curso al desactivar (supuesto de la spec).

## Dependencies & Execution Order

- **Setup (T001–T006)** → **Foundational (T007–T022)** → historias. Dentro de Foundational: T007–T010 antes de T011; T013 antes de T015–T016; T019 después de T013–T017.
- **US1** no depende de otras historias (usa el seeder). **US2**, **US3** y **US4** dependen de Foundational y comparten `CategoriaController` (T027 → T037 → T043 → T048: secuenciales por el mismo archivo). **US3** reutiliza `GuardarCategoriaRequest` de US2 (T034). **US5** solo depende de Foundational.
- **Polish** al final.

## Parallel Opportunities

- Setup: T003, T004, T005 y T006 en paralelo.
- Foundational: T007–T010, T012–T014, T017, T018, T020–T022 en paralelo.
- US1: T023–T026 y las pruebas T028–T031 en paralelo; US2: T034, T036, T039; US5 puede avanzar en paralelo a US2–US4 tras Foundational.
- Frontend y backend de una misma historia pueden avanzar en paralelo cuando el contrato (contracts/) está fijado.

## Implementation Strategy

1. **MVP**: Setup + Foundational + US1 (consulta con seeder). Demostrable de inmediato.
2. Añadir US2 (crear) y US3 (editar) — ambas P1 — para completar la gestión.
3. US4 (estado) y US5 (Facade) como P2; US5 es lo que habilita a Desafíos, Hitos y Ranking.
4. Cerrar con E2E, contraste de diseño y puertas de calidad.
