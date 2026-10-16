# Tasks: Catálogo de categorías documentales

**Input**: `specs/013-categorias-documentales/` (plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md)

**Prerequisites**: plan.md, spec.md. La constitución exige Pest, Vitest y un E2E del flujo principal, por lo que las pruebas están incluidas.

**Formato**: `- [ ] T### [P?] [US?] Descripción con ruta`. `[P]` = archivos distintos y sin dependencia con tareas incompletas. Rutas relativas a la raíz del repositorio. `GP = backend/app/Modules/GestionPersonal`, `FC = frontend/src/modules/gestionPersonal/categorias`, `BT = backend/tests`.

## Phase 1: Setup

- [X] T001 Crear migración `backend/database/migrations/2026_10_18_000001_create_categorias_documentales_table.php`: `id`, `tenant_id` unsignedBigInteger NOT NULL, `nombre` string(100) NOT NULL, `nombre_clave` string(100) NOT NULL, `nombre_busqueda` string(100) NOT NULL, `estado` string(8) NOT NULL default 'Activa', `version` unsignedInteger NOT NULL default 1, `created_by`/`updated_by` unsignedBigInteger NOT NULL, timestamps; `UNIQUE (tenant_id, nombre_clave)` (también para inactivas); índice `(tenant_id, estado, nombre_busqueda, id)`; sin FK, sin `padre_id`/`codigo`/tipo, sin soft delete; triggers SQLite BEFORE INSERT/UPDATE que abortan si `estado` no está en ('Activa','Inactiva'); `down()` elimina triggers y tabla (patrón de `2026_10_17_000001_create_formaciones_academicas_table.php`)
- [X] T002 [P] Crear `GP/Infrastructure/Persistence/Eloquent/Models/CategoriaDocumentalModel.php` (tabla `categorias_documentales`, `$guarded = []`) y `backend/database/factories/CategoriaDocumentalFactory.php` (tenant 1, nombre único, `estado` 'Activa', `version` 1, `created_by`/`updated_by` 1, derivando `nombre_clave` y `nombre_busqueda` en `configure()`)

## Phase 2: Foundational (bloquea todas las historias)

- [X] T003 [P] Value object `GP/Domain/ValueObjects/EstadoDeCategoria.php`: enum de string `Activa`, `Inactiva` con `valores()`
- [X] T004 [P] Entidad inmutable `GP/Domain/Entities/CategoriaDocumental.php`: `id`, `tenantId`, `nombre`, `estado`, `version`, `createdBy`, `updatedBy`, `createdAt`, `updatedAt`
- [X] T005 [P] Excepciones `GP/Domain/Exceptions/{CategoriaNoEncontrada,CategoriaModificada,NombreDeCategoriaDuplicado,CategoriaInactiva}.php` con mensajes en español seguros (reutilizar `DatosInvalidos`, `EscrituraDeshabilitada`, `InformacionNoDisponible`)
- [X] T006 Añadir `claveDeNombre(string $nombre): string` a `GP/Domain/Services/NormalizadorDeTexto.php` (NFC, recorte exterior y minúsculas; conserva tildes; misma regla que `claveDeIdentificacion`) y reutilizar `busquedaDeTexto` para `nombre_busqueda`
- [X] T007 Servicio puro `GP/Domain/Services/ReglasDeCategoria.php`: `normalizar()` recorta el nombre y trata vacío como ausente; `errores(array $final)`: `nombre` obligatorio («Este campo es obligatorio.») y máximo 100 caracteres medidos tras recortar («No puede superar los 100 caracteres.»); `erroresEstado(?string)` con estado dentro de `EstadoDeCategoria::valores()`
- [X] T008 Interfaz `GP/Domain/Repositories/CategoriaRepositoryInterface.php`: `porId(tenantId, id)`, `listar(tenantId, ?q, estado, pagina, porPagina)` con orden por `nombre_busqueda` e `id` y total, `existePorClave(tenantId, clave, ?excluirId)`, `crear(tenantId, actorId, datos)`, `actualizar(tenantId, id, version, actorId, cambios)` lanzando `CategoriaNoEncontrada`/`CategoriaModificada` y `NombreDeCategoriaDuplicado` ante la restricción única, `confirmarActiva(tenantId, id)` lanzando `CategoriaInactiva`, `activas(tenantId)` y `porIds(tenantId, ids)`; sin ningún método de eliminación
- [X] T009 Implementar `GP/Infrastructure/Persistence/Eloquent/Repositories/EloquentCategoriaRepository.php`: toda consulta filtra por `tenant_id`; búsqueda parcial sobre `nombre_busqueda` escapando `%`, `_` y `\`; `crear` traduce `UniqueConstraintViolationException` a `NombreDeCategoriaDuplicado`; `actualizar` con `WHERE id, tenant_id, version` incrementando `version`, fijando `updated_by`/`updated_at` y 0 filas ⇒ `CategoriaNoEncontrada` si no existe o `CategoriaModificada` si cambió; `confirmarActiva` con `UPDATE … SET version = version WHERE id AND tenant_id AND estado = 'Activa'` (usando `DB::table`, sin tocar `updated_at`); enlazar en `GP/Infrastructure/Providers/GestionPersonalServiceProvider.php`
- [X] T010 [P] DTO `GP/Application/DTO/FiltroCategoriasDTO.php` y `GP/Application/DTO/ListadoCategoriasDTO.php` (items, total, página, porPágina)
- [X] T011 [P] Pruebas unitarias `BT/Unit/GestionPersonal/ReglasDeCategoriaTest.php` y casos de `claveDeNombre` en `BT/Unit/GestionPersonal/NormalizadorDeTextoTest.php`: nombre de 100 y 101 caracteres, solo espacios, espacios exteriores, mayúsculas, «Educación» ≠ «Educacion» (tildes se conservan), estado inválido
- [X] T012 [P] Frontend base: `FC/api.ts` (tipos `Categoria`, `EstadoCategoria`, `PaginaCategorias`, `listarCategorias`, `crearCategoria`, `editarCategoria`, `cambiarEstadoCategoria`), `FC/text.ts` (todos los textos en español: «Categorías documentales», «Nueva categoría», «Aún no hay categorías documentales», «Ninguna categoría coincide con la búsqueda.», avisos, errores), `FC/validation.ts` (esquema Zod del nombre: obligatorio tras recortar, máximo 100; mapeo de errores 422 a `nombre`) y `FC/validation.test.ts`
- [X] T013 [P] Hooks `FC/hooks/useCategorias.ts` (clave `['categorias-documentales', …]`, sin reintento en 4xx, `placeholderData` conservando la página previa) y `FC/hooks/useCategoriasParams.ts` (`q`, `estado` por defecto `Activa`, `page` guardados en la URL)

**Checkpoint**: dominio, persistencia y base del frontend listos; aún sin endpoints.

## Phase 3: User Story 1 — Crear y consultar categorías (Priority: P1) 🎯 MVP

**Objetivo**: el Administrador abre el catálogo, lo filtra y crea categorías; el Colaborador no accede.
**Prueba independiente**: con catálogo vacío crear «Contratos»; ver Activa en el listado; «  contratos  » se rechaza junto al campo; un Colaborador recibe 403; otro tenant no la ve.

- [X] T014 [P] [US1] Requests `GP/Presentation/Requests/ListarCategoriasRequest.php` (`q` ≤ 50, `estado` en Activa|Inactiva|Todas con valor inicial `Activa`, `page` ≥ 1, `per_page` 1–100 con 20 por defecto) y `GP/Presentation/Requests/GuardarCategoriaRequest.php` (forma y tipo de `nombre` y `version`; `version` obligatoria solo en PATCH; la regla de negocio vive en `ReglasDeCategoria`; ignora `tenant_id`, `created_by`, `id`; errores 422 con `code` `VALIDACION`)
- [X] T015 [US1] Casos de uso `GP/Application/UseCases/CrearCategoria.php` (comprueba `config('hrms.gestion_personal.escritura_habilitada')`, normaliza y valida con `ReglasDeCategoria`, comprobación previa de `nombre_clave` solo para el mensaje «Ya existe una categoría con ese nombre.», crea `Activa` con `version` 1 y actor) y `GP/Application/UseCases/ListarCategorias.php` y `GP/Application/UseCases/ObtenerCategoria.php` (404 uniforme si no existe o es de otro tenant)
- [X] T016 [US1] `GP/Presentation/Resources/CategoriaDocumentalResource.php` y `GP/Presentation/Controllers/CategoriaDocumentalController.php` (delgado; `responder()` traduce `CategoriaNoEncontrada`→404 `CATEGORIA_NO_ENCONTRADA`, `DatosInvalidos` y `NombreDeCategoriaDuplicado`→422 `VALIDACION` con `errors.nombre`, `CategoriaModificada`→409 `CATEGORIA_MODIFICADA`, `EscrituraDeshabilitada`→503) con `index`, `store` (201) y `show`; rutas `GET/POST /api/categorias-documentales` y `GET /{categoria}` con `whereNumber` en `GP/Routes/api.php` bajo `auth:sanctum` + `admin`; sin ruta DELETE
- [X] T017 [P] [US1] Pruebas `BT/Feature/GestionPersonal/CategoriasCrearListarTest.php`: creación Activa con `version` 1 y auditoría, 422 de nombre vacío/espacios/101 caracteres/100 aceptado, duplicado «Contratos» vs « contratos » (también contra una inactiva), mismo nombre en dos tenants, `tenant_id` del cuerpo ignorado, listado Activas por defecto, filtro Inactiva/Todas, búsqueda parcial sin tildes con `%` y `_` literales, orden por nombre e id, paginación y `per_page` fuera de rango ⇒ 422, 401 sin sesión, 403 Colaborador en cada ruta, 404 idéntico de inexistente y de otro tenant, sin DELETE (404/405), escritura deshabilitada ⇒ 503
- [X] T018 [P] [US1] `BT/Feature/GestionPersonal/CategoriasSinDocumentosTest.php`: el catálogo funciona (crear, listar, consultar) sin ninguna tabla documental en el esquema
- [X] T019 [US1] `FC/components/CategoriasTabla.tsx` (tabla con Nombre y Estado en `Badge`, acciones por fila solo para el Administrador, sin columna «Documentos» ni Eliminar), `FC/components/FormularioCategoria.tsx` (Modal con RHF+Zod, campo Nombre con contador `n/100`, error junto al campo y alerta general, botón bloqueado al enviar con guarda contra doble clic, foco devuelto al disparador; alta en esta tarea) y `FC/pages/CategoriasPage.tsx` (encabezado, `SearchField`, `SelectField` de estado Activas/Inactivas/Todas, `Pagination`, estados cargando/vacío/sin resultados/error con Reintentar, mensaje `role="status"` tras éxito); `FC/hooks/useGuardarCategoria.ts` (mutación de crear sin reintento que invalida `['categorias-documentales']`)
- [X] T020 [US1] Registrar la pantalla: `FC/nav.tsx` (entrada «Categorías documentales», solo rol `admin`, icono de carpeta añadido a `frontend/src/shared/ui/icons.tsx` si no existe), ruta protegida `categorias-documentales` en `frontend/src/App.tsx` con la guarda de administrador existente y entrada en `navItems`
- [X] T021 [P] [US1] Pruebas `FC/pages/CategoriasPage.dom.test.tsx` y `FC/components/FormularioCategoria.dom.test.tsx`: cargando, vacío, sin resultados con Limpiar filtros, error con Reintentar, listado Activas por defecto, búsqueda y filtro en la URL, alta válida, error de nombre junto al campo (obligatorio, 101 caracteres, duplicado del servidor) conservando lo escrito, doble clic ⇒ una sola petición, ausencia de botón Eliminar, Colaborador sin acceso

## Phase 4: User Story 2 — Corregir el nombre (Priority: P1)

**Objetivo**: corregir el nombre de categorías Activas o Inactivas sin romper la identidad ni pisar ediciones ajenas.
**Prueba independiente**: renombrar «Contratos» a «Contratos laborales» y ver el cambio; renombrar una inactiva; dos ediciones con la misma versión ⇒ la segunda recibe 409.

- [X] T022 [US2] Caso de uso `GP/Application/UseCases/EditarCategoria.php`: escritura habilitada; categoría del tenant (404); versión distinta de la actual ⇒ `CategoriaModificada`; normaliza y valida; duplicado contra otras categorías excluyendo la propia (cambio solo de mayúsculas o espacios exteriores sobre sí misma se acepta); mismo texto y misma clave ⇒ devuelve la actual sin escribir (sin tocar `version`, `updated_*`); cambio real ⇒ `actualizar` con `nombre`, `nombre_clave` y `nombre_busqueda`; no modifica el estado
- [X] T023 [US2] Método `update` en `GP/Presentation/Controllers/CategoriaDocumentalController.php` y ruta `PATCH /api/categorias-documentales/{categoria}` en `GP/Routes/api.php` (200 o 409/404/422/503)
- [X] T024 [P] [US2] Pruebas `BT/Feature/GestionPersonal/CategoriasEditarTest.php`: renombrar conserva el id, edita una Inactiva (sigue Inactiva), colisión con otra ⇒ 422 de `nombre` sin cambios, cambio solo de mayúsculas/espacios de sí misma ⇒ 200, sin cambio real ⇒ misma `version` y `updated_at`, versión obsoleta ⇒ 409 conservando la primera escritura, `version` ausente ⇒ 422, 404 cruzado de otro tenant, `estado` del cuerpo ignorado, Colaborador 403, escritura deshabilitada ⇒ 503, auditoría de `updated_by`
- [X] T025 [US2] Modo edición en `FC/components/FormularioCategoria.tsx` (valor inicial, estado actual como dato de solo lectura, envío con la versión de la fila vigente en el momento del envío) y mutación de editar en `FC/hooks/useGuardarCategoria.ts`; en 409 mostrar «La información cambió» con «Actualizar datos» (refetch conservando lo escrito); 404 «La categoría ya no existe»; botón Editar por fila en `FC/components/CategoriasTabla.tsx`
- [X] T026 [P] [US2] Pruebas `FC/components/FormularioCategoria.edit.dom.test.tsx`: edición válida, envío sin cambios, 422 junto al campo, 409 conserva lo escrito y tras «Actualizar datos» se envía la versión nueva, 404, fallo de red conserva lo escrito y el listado previo, foco devuelto al botón Editar

## Phase 5: User Story 3 — Desactivar y reactivar (Priority: P1)

**Objetivo**: retirar una categoría de las cargas nuevas y volver a habilitarla sin tocar documentos.
**Prueba independiente**: desactivar una categoría, comprobar que sale del filtro Activas, reactivarla y verla volver; versión obsoleta ⇒ 409; el Colaborador es denegado.

- [X] T027 [P] [US3] Request `GP/Presentation/Requests/CambiarEstadoCategoriaRequest.php` (`estado` obligatorio y `version` entera ≥ 1; 422 `VALIDACION`)
- [X] T028 [US3] Caso de uso `GP/Application/UseCases/CambiarEstadoCategoria.php`: escritura habilitada; categoría del tenant (404); versión obsoleta ⇒ `CategoriaModificada`; estado inválido ⇒ 422; estado igual al actual con versión vigente ⇒ devuelve la actual sin escribir; cambio ⇒ `actualizar` con `estado`, versión+1 y auditoría; no consulta ni modifica documentos
- [X] T029 [US3] Método `updateEstado` en `GP/Presentation/Controllers/CategoriaDocumentalController.php` y ruta `PATCH /api/categorias-documentales/{categoria}/estado` en `GP/Routes/api.php`
- [X] T030 [P] [US3] Pruebas `BT/Feature/GestionPersonal/CategoriasEstadoTest.php`: desactivar y reactivar con versión vigente, mismo estado ⇒ 200 sin cambiar versión ni auditoría, versión obsoleta ⇒ 409 sin forzar aunque el resultado sería equivalente, estado inválido o ausente ⇒ 422, 404 de otro tenant, Colaborador 403, escritura deshabilitada ⇒ 503, la desactivación no escribe en ninguna otra tabla (conteo de filas y esquema sin tabla documental), no existe eliminación
- [X] T031 [US3] `FC/components/ConfirmarEstadoCategoria.tsx` con `ConfirmDialog` (desactivar: «La categoría dejará de estar disponible para nuevas cargas. Sus documentos se conservarán.» en tono `primary`; reactivar: confirmación neutra «La categoría volverá a estar disponible para nuevas cargas. No se reactivan documentos archivados.»), mutación de estado en `FC/hooks/useGuardarCategoria.ts` sin reintento, botones Desactivar/Reactivar por fila en `FC/components/CategoriasTabla.tsx` con `aria-label` que incluye el nombre, 409 con «Actualizar datos», error que conserva el estado anterior y mensaje `role="status"` tras éxito
- [X] T032 [P] [US3] Pruebas `FC/components/ConfirmarEstadoCategoria.dom.test.tsx`: textos de la confirmación, cancelar no cambia nada, confirmar desactiva y refresca el listado (la fila sale de «Activas»), reactivar, 409 pide actualizar y no cambia el estado, doble clic ⇒ una petición, ausencia de botón Eliminar

## Phase 6: User Story 4 — Consumo por la gestión documental (Priority: P2)

**Objetivo**: GP-05 ofrece solo categorías activas en cargas nuevas, resuelve el nombre vigente de cualquiera y rechaza una publicación si la categoría se desactivó antes.
**Prueba independiente**: con una categoría activa y una inactiva, las cargas nuevas reciben solo la activa, un documento existente resuelve la inactiva, `confirmarActiva` lanza `CategoriaInactiva` tras desactivar, y el Colaborador no accede al catálogo administrativo.

- [X] T033 [P] [US4] Puerto `GP/Application/Ports/CatalogoDeCategoriasPort.php` (`activas`, `porIds`, `confirmarActiva` según [contracts/lectores.md](contracts/lectores.md)) y adaptador `GP/Infrastructure/Adapters/CatalogoDeCategoriasEloquent.php` (lecturas con `Lectura::disponible`/`noDisponible` sin lanzar; `confirmarActiva` delegando en el repositorio sin tocar `version`, `updated_by` ni `updated_at`); binding en `GP/Infrastructure/Providers/GestionPersonalServiceProvider.php`
- [X] T034 [US4] Ampliar `GP/Application/Services/PersonalService.php` con `getCategoriasActivas`, `getCategoriasPorIds` (lanzan `InformacionNoDisponible` si la lectura falla) y `confirmarCategoriaActiva` (lanza `CategoriaInactiva`), y los `@method` de `GP/Presentation/Facades/PersonalFacade.php`
- [X] T035 [P] [US4] Pruebas `BT/Feature/GestionPersonal/CatalogoDeCategoriasTest.php`: `activas` devuelve solo Activas del tenant ordenadas por nombre, `porIds` resuelve cualquier estado con el nombre vigente tras renombrar, ignora ids ajenos o inexistentes con una sola consulta, fallo de infraestructura ⇒ `Lectura::noDisponible()` y la fachada lanza `InformacionNoDisponible`, `confirmarActiva` acepta una activa, rechaza inactiva/inexistente/de otro tenant con `CategoriaInactiva` y no modifica versión ni auditoría, el lector funciona sin tabla documental
- [X] T036 [P] [US4] `FC/index.ts` (único punto público: `categoriasKey` y `useCategoriasActivas()` con `estado=Activa` y `per_page=100`) y prueba `FC/hooks/useCategoriasActivas.dom.test.tsx`: solo trae activas y se refresca al invalidar la familia `['categorias-documentales']` tras una mutación

## Phase 7: Concurrencia, E2E y pulido

- [X] T037 Pruebas `BT/Feature/GestionPersonal/CategoriasConcurrenciaTest.php` (reproducción determinista, patrón de `DesarrolloConcurrenciaTest.php`): dos creaciones con el mismo nombre ⇒ una 201 y una 422 de `nombre` (la restricción única decide, también si se salta la comprobación previa); dos ediciones con la misma versión ⇒ 200 y 409; desactivar y editar con la misma versión ⇒ una pierde; `confirmarActiva` tras desactivar ⇒ rechazado y tras `confirmarActiva` la desactivación sigue procediendo sin afectar el documento simulado
- [X] T038 [P] Actualizar `backend/database/seeders/PersonalSeeder.php` con Contratos y Certificados (Activas) y una categoría Inactiva en el tenant 1, idempotente (por `nombre_clave`), sin datos reales; añadir su prueba a `BT/Feature/GestionPersonal/CategoriasSinDocumentosTest.php`
- [X] T039 [P] E2E `frontend/e2e/categorias-documentales.spec.ts` (nombres únicos por ejecución, repetible): el Administrador crea una categoría, ve el rechazo de un duplicado con otras mayúsculas, la edita, la desactiva con la confirmación y la reactiva; sin botón Eliminar; el Colaborador no ve la entrada de navegación y `/categorias-documentales` muestra sin permisos; sin scroll horizontal a 320 y 1440 px; Escape devuelve el foco al botón
- [X] T040 [P] Diseño: registrar en `design/DESIGN.md` §9 las diferencias con `design/GP-05_Documentos-del-colaborador.html` (sin columna «Documentos», paginación, «Editar» en lugar de «Editar nombre», reactivar con confirmación) y revisar accesibilidad (foco en `Modal` y `ConfirmDialog`, etiquetas, 320–1440 px, tema oscuro)
- [X] T041 Puertas de calidad: `cd backend && vendor/bin/pint --dirty --format agent && php artisan test --compact --filter=Categoria`; `cd frontend && npm run lint && npx tsc -b && npm run test && npm run build`; ejecutar los escenarios de `quickstart.md`; anotar en `research.md` D4 cualquier ajuste a la coordinación con GP-05

## Dependencias y orden

- Setup (T001–T002) → Foundational (T003–T013) → historias. T006 → T007 → T009; T008 → T009; T009 → T015, T022, T028, T033.
- US1 es el MVP; US2 y US3 dependen de US1 (controlador, ruta base, tabla y formulario) pero no entre sí; US4 depende solo de la fase 2 y puede hacerse en paralelo con US2 y US3 (backend), y en el frontend solo del `api.ts` de T012.
- Fase 7 al final: T037 requiere US1–US3 y T033; T039 requiere T038 y las tres historias de UI.
- Dentro de cada historia: request y pruebas [P] → caso de uso → controlador/ruta → frontend.

## Paralelismo

- Setup: T002 tras T001 en paralelo con nada que dependa de la tabla.
- Foundational: T003, T004, T005, T010, T011, T012 y T013 en paralelo; T006–T009 en secuencia.
- US1: T014 y T017/T018 [P] en paralelo con T019 hasta la integración (T020).
- US3 y US4 (backend) en paralelo tras T016.

## Estrategia

MVP = Setup + Foundational + US1 (crear y consultar). Entrega incremental: US2 (corregir) → US3 (desactivar/reactivar) → US4 (lector para GP-05) → concurrencia, datos de ejemplo, E2E y pulido. Tras cada historia, ejecutar sus pruebas Pest/Vitest.
