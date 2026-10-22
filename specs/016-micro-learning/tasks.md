---

description: "Tareas de la feature 016-micro-learning"
---

# Tasks: Micro-learning

**Input**: Documentos de diseño en `/specs/016-micro-learning/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/microcontenidos-api.md, contracts/micro-puertos-y-facades.md, quickstart.md. Requiere 000-login, 001/004 (competencias en `EstructuraFacade`), 005-catalogo-formativo (módulo `Desarrollo`, tabla `microcontenidos` mínima, `competencia_microcontenido`, historial del Catálogo, `RequireRole`), 005-fichas-colaboradores (`PersonalFacade`, `colaborador_cuentas`) y 006-deteccion-necesidades-formativas (`BrechasActivasInterface`) ya implementadas. **Reportes no existe**: solo se publican los métodos de Facade que consumirá.

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario (US1–US8 de spec.md) para implementarlas y probarlas de forma independiente. La feature **amplía** el módulo `Desarrollo` existente y **no recrea** la tabla `microcontenidos`.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1–US8)
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Desarrollo/…`. Abreviatura **`$D`** = `backend/app/Modules/Desarrollo`
- Frontend: `frontend/src/modules/desarrollo/micro-learning/…` (reutiliza `frontend/src/shared/ui/…`). Abreviatura **`$F`** = `frontend/src/modules/desarrollo/micro-learning`
- Pruebas backend: `backend/tests/{Unit,Feature}/Desarrollo/` (prefijo `Micro`); pruebas de componentes: `*.dom.test.tsx` junto al código (con `// @vitest-environment jsdom`); E2E en `frontend/e2e/micro-learning.spec.ts`
- Regla de módulos: `Desarrollo` solo usa `CompetenciasInterface`, `BrechasActivasInterface`, `CuentaColaboradorInterface`, `NombresDeUsuarioInterface` y el nuevo `NombreDeColaboradorInterface` (cuyos adaptadores llaman a las Facades); nunca modelos ni tablas de otros módulos. `competencia_id`, `colaborador_id`, `tenant_id` y los ids de usuario son enteros **sin clave foránea** entre módulos. Entre tablas de micro-learning se usan FK **sin borrado en cascada** (`restrictOnDelete`) y el borrado es explícito dentro de una transacción. En frontend, `micro-learning` **no importa nada** de `catalogo-formativo`, `dnf` ni `plan-formacion`, salvo `modules/auth/login` (rol); si el `RequireRole` de otro módulo no es público, copiar el patrón
- Valores en base de datos (sin tildes; la interfaz muestra con tilde): `tipo` ∈ `Video` | `PDF` | `Articulo` | `Quiz`; `estado` del contenido ∈ `Borrador` | `Publicado` | `Archivado`; estado del progreso ∈ `EnProgreso` | `Completado`; estado del intento ∈ `EnCurso` | `Finalizado`; roles: capacidad `admin` (Administrador) y rol `user` (Colaborador), excluyentes
- Reutilizar sin modificar: `Button`, `Badge`, `Alert`, `ConfirmDialog`, `Modal`, `Combobox`, `SelectField`, `SearchField`, `TextField`, `Tabs`, `Pagination`, `Breadcrumb`, `EmptyState`, `ErrorState`, `Skeleton` y `AppShell`. No crear componentes equivalentes
- Guía visual: `design/MicroAdmin.dc.html`, `design/MicroColaborador.dc.html`, `design/MicroReproduccion.dc.html` y `design/MicroQuiz.dc.html` son **referencia, no código para copiar**; en estilos manda `design/DESIGN.md`. **Manda la spec sobre los tableros** (plan.md, tabla «Diferencias entre tableros y spec»): se omiten el umbral del 70 %, el panel «Mis certificados», «Ver como colaborador», el menú Empleados/Ajustes y las tarjetas KPI del administrador
- Insumo `plan-microlearning.md` y plan.md: donde difieren, **prevalecen plan.md y research.md** (tabla ampliada en vez de creada, `tenant_id` en el progreso, intentos `EnCurso` que no cuentan, vista previa solo en cliente)
- Lecciones previas: en E2E localizar campos con `getByRole('textbox', { name, exact: true })`; escribir archivos con acentos con la herramienta Write (los heredocs de Bash fallan); ver la memoria «Entorno local de pruebas» (OneDrive/Windows) al correr Pest, Vitest y Playwright

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Línea base verde y diseño registrado antes de tocar código

- [X] T001 Verificar la línea base: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run` y `npx playwright test` en `frontend/` deben pasar antes de empezar (sin cambios de archivos). Anotar los fallos preexistentes de la memoria del proyecto para no atribuirlos a esta feature
- [X] T002 Actualizar `design/DESIGN.md` (Constitución III) **antes** de cualquier tarea de frontend: en §2 los colores por tipo de micro-contenido (Video `--danger`/`--dangersoft`, PDF `--info`/`--infosoft`, Artículo `--accent`/`--soft`, Quiz `--violet`/`--violetsoft`) y por estado (Borrador `--neutral`/`--neutralsoft`, Publicado `--accent`/`--soft`, Archivado borde `--border` con `--muted`); en §4 los componentes **Tarjeta de micro-contenido**, **Etiqueta de tipo**, **Constructor de preguntas**, **Reproductor (marcador de posición)**, **Opción de quiz con retroalimentación** y **Tarjeta de aprendizaje**; en §5 los patrones «Repositorio de micro-contenidos (cuadrícula)», «Mi aprendizaje», «Reproducción» y «Quiz»; en §9 las filas de la tabla «Diferencias entre tableros y spec» de plan.md. Sin cambios si ya está completo

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Tablas, modelos, dominio base, adaptación del Catálogo a los nuevos nombres y estados, siembra y piezas de frontend que usan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend – datos

- [X] T003 Crear `backend/database/migrations/2026_10_20_000001_extend_microcontenidos_table.php` que **amplía** la tabla de la feature 005 (no la recrea) según data-model.md: renombrar `nombre`→`titulo` (`string(120)`) y `url_destino`→`url` (`string(500)` anulable); eliminar `formador`; añadir `codigo` `string(12)` anulable con índice único (`MICRO-NNNN`), `miniatura_url` `string(500)` anulable, `created_by` y `updated_by` enteros sin signo anulables; cambiar el valor por defecto de `estado` a `Borrador` (`string(10)` con índice); convertir datos existentes en `up()`: estado `Disponible`→`Publicado`; tipo `Lectura`→`Articulo`, `Infografia`→`PDF`, `Audio`→`Video`; `codigo` de las filas previas = `MICRO-` + id con 4 dígitos. `tipo` queda `string(10)` ∈ `Video` | `PDF` | `Articulo` | `Quiz`. `down()` revierte nombres, valores y columnas
- [X] T004 [P] Crear `backend/database/migrations/2026_10_20_000002_create_microcontenido_preguntas_table.php`: `id`; `microcontenido_id` FK a `microcontenidos.id` con `restrictOnDelete`; `enunciado` `string(500)`; `orden` entero sin signo ≥ 1. Única `(microcontenido_id, orden)`
- [X] T005 [P] Crear `backend/database/migrations/2026_10_20_000003_create_microcontenido_opciones_table.php`: `id`; `pregunta_id` FK a `microcontenido_preguntas.id` con `restrictOnDelete`; `texto` `string(300)`; `es_correcta` boolean; `orden` entero sin signo ≥ 1. Única `(pregunta_id, orden)`. La regla «exactamente una opción correcta por pregunta» se valida en la aplicación
- [X] T006 [P] Crear `backend/database/migrations/2026_10_20_000004_create_microcontenido_progresos_table.php`: `id`; `microcontenido_id` FK a `microcontenidos.id` con `restrictOnDelete`; `colaborador_id` entero sin signo (referencia lógica, sin FK); `tenant_id` entero sin signo con índice (sin FK); `estado` `string(11)` ∈ `EnProgreso` | `Completado`; `posicion_segundos` entero sin signo anulable (solo Video, ≥ 0); `fecha_inicio` datetime; `fecha_completado` datetime anulable; timestamps. Única `(microcontenido_id, colaborador_id)`; índices `(tenant_id, estado)` y `(colaborador_id, estado)`
- [X] T007 [P] Crear `backend/database/migrations/2026_10_20_000005_create_microcontenido_intentos_table.php`: `id`; `progreso_id` FK a `microcontenido_progresos.id` con `restrictOnDelete`; `estado` `string(10)` ∈ `EnCurso` | `Finalizado`; `puntaje` entero sin signo anulable (nulo mientras `EnCurso`); `total_preguntas` entero sin signo ≥ 1; `finalizado_en` datetime anulable; timestamps
- [X] T008 [P] Crear `backend/database/migrations/2026_10_20_000006_create_microcontenido_respuestas_table.php`: `id`; `intento_id` FK a `microcontenido_intentos.id`, `pregunta_id` FK a `microcontenido_preguntas.id` y `opcion_id` FK a `microcontenido_opciones.id`, todas con `restrictOnDelete`; `es_correcta` boolean; solo `created_at`. Única `(intento_id, pregunta_id)`
- [X] T009 [P] Crear `backend/database/migrations/2026_10_20_000007_create_microcontenido_historial_table.php` (solo se agrega): `id`; `microcontenido_id` FK a `microcontenidos.id` con `restrictOnDelete`; `estado_anterior` `string(10)` anulable (nulo en la creación); `estado_nuevo` `string(10)`; `user_id` entero sin signo anulable (sin FK); solo `created_at`

### Backend – modelos, factories y dominio base

- [X] T010 Actualizar `$D/Infrastructure/Persistence/Eloquent/Models/MicrocontenidoModel.php`: `$fillable` = `codigo`, `titulo`, `tipo`, `url`, `miniatura_url`, `duracion_minutos`, `estado`, `created_by`, `updated_by`; relaciones `preguntas()` (ordenadas por `orden`), `progresos()`, `historial()`; sin soft deletes
- [X] T011 [P] Crear los modelos Eloquent en `$D/Infrastructure/Persistence/Eloquent/Models/`: `MicroPreguntaModel.php`, `MicroOpcionModel.php`, `MicroProgresoModel.php`, `MicroIntentoModel.php`, `MicroRespuestaModel.php` y `MicroHistorialModel.php` (este último sin `updated_at`), con sus tablas, `$fillable`, casts (`es_correcta` boolean, fechas) y relaciones
- [X] T012 [P] Ajustar `backend/database/factories/MicrocontenidoFactory.php` a las columnas nuevas (`titulo`, `tipo` ∈ `Video`|`PDF`|`Articulo`|`Quiz`, `url` válida salvo Quiz, `estado` `Borrador` por defecto, `codigo` generado, estados `publicado()` y `archivado()`) y crear `MicroPreguntaFactory.php` (con 4 opciones, una correcta) y `MicroProgresoFactory.php` (estados `enProgreso()` y `completado()`) en `backend/database/factories/`
- [X] T013 [P] Crear los value objects en `$D/Domain/ValueObjects/`: `TipoMicro.php`, `EstadoMicro.php` (con la tabla de transiciones permitidas), `EstadoProgresoMicro.php`, `EstadoIntento.php` y `FiltroMicro.php` (`tipo`, `estado` ∈ `Borrador`|`Publicado`|`Archivado`|`Activos`|`Todos`, `competenciaId`, `q`, `page`, `perPage` por defecto 12 y máximo 50)
- [X] T014 [P] Crear las excepciones de dominio en `$D/Domain/Exceptions/`: `MicroNoEncontrado`, `MicroNoPublicable` (con la lista `faltantes`), `TransicionMicroInvalida`, `MicroConProgreso`, `QuizConIntentos`, `QuizInvalido`, `MicroNoDisponible`, `MicroYaCompletado`, `RespuestaInvalida`, `IntentoDescartado` y `TipoInmutable`, cada una con su `code` de negocio de contracts/microcontenidos-api.md
- [X] T015 Crear los contratos de repositorio en `$D/Domain/Repositories/`: `MicrocontenidoRepositoryInterface.php` (alta y edición con vínculos y código, listado filtrado, historial de estados, borrado explícito, conteo de completaciones, `tieneProgreso`, `tieneIntentosFinalizados`) y `ProgresoMicroRepositoryInterface.php` (progreso por contenido y colaborador, intentos, respuestas, resumen y completaciones), según contracts/micro-puertos-y-facades.md

### Backend – Catálogo, siembra y pruebas previas

- [X] T016 Adaptar `$D/Infrastructure/Persistence/Eloquent/Repositories/EloquentCatalogoFormativoRepository.php` a los nuevos nombres y estados (plan.md A-06): para micro-contenidos leer `titulo` en lugar de `nombre`, considerar *disponible* solo `Publicado` (en `recursosDisponibles`, `idsDisponibles` y `recurso()`), entregar `formador = null` y `url_destino = '/micro-learning/{id}'`; los cursos no cambian. Añadir `sincronizarCompetenciasDeMicrocontenido(int $microId, array $competenciaIds, ?int $actorId): void` que escribe los vínculos, registra `tipo_cambio = Microcontenido` (`Asociado`/`Quitado`) en `competencia_formativa_historial` y sube `version` de cada `competencia_formativa` afectada, sin abrir transacción propia (la del llamador); exponerlo en `Domain/Repositories/CatalogoFormativoRepositoryInterface.php`
- [X] T017 Crear `backend/database/seeders/MicroLearningSeeder.php` con 10 micro-contenidos de los cuatro tipos y los tres estados (al menos 5 Publicados, y un Quiz de 5 preguntas con 4 opciones cada una, una correcta), con códigos `MICRO-0001`… estables; aceptar la opción `--grande` (o un método `sembrarMuchos(500)`) que crea 500 micro-contenidos Publicados adicionales solo para medir SC-010 (no se ejecuta por defecto); ajustar `backend/database/seeders/CatalogoFormativoSeeder.php` para **resolver** los micro-contenidos por `codigo` en lugar de crearlos, y `backend/database/seeders/DatabaseSeeder.php` para ejecutar `MicroLearningSeeder` antes del Catálogo
- [X] T018 Ajustar las pruebas existentes de 005 y 013 que usan `nombre`, `url_destino`, `formador` o el estado `Disponible` de micro-contenidos (`backend/tests/Feature/Desarrollo/ActualizarConfiguracionTest.php`, `DesarrolloFacadeTest.php`, `DetalleFormativoColaboradorTest.php`, `HistorialTest.php`, `ListarCatalogoAdminTest.php`, `ListarCatalogoColaboradorTest.php`, `RecursosDisponiblesTest.php`, `backend/tests/Pest.php`, `backend/tests/Unit/Desarrollo/ComparadorDeCambiosFormativosTest.php`, `ReglasDeConfiguracionFormativaTest.php`) y crear `backend/tests/Feature/Desarrollo/MicroSincronizarCompetenciasTest.php` que verifique: altas y bajas de vínculos, entradas `Asociado`/`Quitado` con `tipo_cambio = Microcontenido`, `version` incrementada y que un `Borrador`/`Archivado` no cuenta como disponible en el Catálogo. Crear además `backend/tests/Feature/Desarrollo/MicroMigracionMicrocontenidosTest.php` que parta de filas con la forma de la feature 005 (`nombre`, `tipo` `Lectura`/`Infografia`/`Audio`, `estado` `Disponible`, `formador`, `url_destino`) y verifique tras la migración T003: columnas renombradas, tipos y estados convertidos, `codigo` asignado a cada fila previa, vínculos de `competencia_microcontenido` intactos y que el id de una fila eliminada **no se reutiliza** al insertar la siguiente (`AUTOINCREMENT` conservado tras la reconstrucción de la tabla)

### Frontend – base compartida de la funcionalidad

- [X] T019 [P] Crear `$F/api.ts` (tipos `TipoMicro`, `EstadoMicro`, `FilaMicro`, `Micro`, `PreguntaQuiz`, `Opcion`, `Progreso`, `Intento`, `ResumenAprendizaje`, `MiAprendizaje`, `HistorialMicro`, y las funciones de cada endpoint de contracts/microcontenidos-api.md sobre `shared/api/httpClient`) y `$F/text.ts` (todo el texto visible en español, centralizado, incluida la nota «Los micro-contenidos refuerzan conocimientos y no reemplazan el desarrollo de competencias conductuales», «Apto para conocimientos» y los mensajes de estado vacío)
- [X] T020 [P] Crear `$F/estilosTipo.ts` (clases de color por tipo y por estado según T002), `$F/components/EtiquetaTipo.tsx` (píldora con texto, nunca solo color), `$F/components/AvisoConocimientos.tsx` (`Alert` neutro con la nota de alcance) y añadir a `frontend/src/shared/ui/icons.tsx` los íconos SVG que falten (video, documento, artículo, quiz, certificado, reproducir/pausar)
- [X] T021 [P] Crear `$F/nav.tsx` (ítem «Micro-learning» después de «Plan anual», ruta `/micro-learning`) y `$F/components/RequireRole.tsx` (guarda de presentación por rol con el contrato público `modules/auth/login`; el backend mantiene la autoridad), y registrar el ítem en `navItems` de `frontend/src/App.tsx`

**Checkpoint**: tablas migradas, Catálogo y siembra adaptados, pruebas previas verdes, base de frontend lista

---

## Phase 3: User Story 1 – Crear y publicar un micro-contenido (Priority: P1) 🎯 MVP

**Goal**: El administrador crea un contenido (Video, PDF o Artículo) como Borrador con código MICRO-NNNN, lo ve en el repositorio y lo publica cuando cumple los requisitos.

**Independent Test**: Como administrador, crear un Video como Borrador, verificar su código, ver que *Publicar* indica los datos faltantes, completarlos, publicarlo y comprobar el estado Publicado y su aparición en el Catálogo.

### Tests for User Story 1

- [X] T022 [P] [US1] Prueba unitaria `backend/tests/Unit/Desarrollo/MicroReglasDeMicrocontenidoTest.php`: transiciones permitidas (Borrador→Publicado, Publicado→Archivado, Archivado→Publicado) y rechazo de las demás; requisitos de publicación (título, duración, `url` si no es Quiz, al menos una pregunta si es Quiz, al menos una competencia activa) devolviendo la lista `faltantes`; tipo inmutable
- [X] T023 [P] [US1] Prueba Feature `backend/tests/Feature/Desarrollo/CrearMicrocontenidoTest.php`: 201 en `Borrador` con `codigo` `MICRO-NNNN` único y primera entrada del historial (`null → Borrador`); 422 sin `titulo`, sin `tipo` o con `duracion_minutos` < 1; 422 si se envía `url` en un Quiz o `url` con formato inválido; 422 si una competencia no existe o está inactiva; 401 sin sesión y 403 para un colaborador; los vínculos se escriben con historial del Catálogo (T016); dos altas consecutivas producen códigos distintos y, tras eliminar el último contenido, el siguiente alta **no reutiliza** su número
- [X] T024 [P] [US1] Prueba Feature `backend/tests/Feature/Desarrollo/ListarMicrocontenidosAdminTest.php`: por defecto solo Borrador y Publicado; filtros `tipo`, `estado` (`Activos`, `Borrador`, `Publicado`, `Archivado`, `Todos`), `competencia_id` y `q` (sin tildes ni mayúsculas); paginación `page`/`per_page` con máximo 50; `completaciones` y `competencias`; `GET /api/microcontenidos/opciones` devuelve las competencias activas para el formulario
- [X] T025 [P] [US1] Prueba Feature `backend/tests/Feature/Desarrollo/CambiarEstadoMicroTest.php`: `Borrador→Publicado` con éxito; 422 `faltan_requisitos` con la lista de faltantes; Publicado↔Archivado con entrada de historial (usuario, fecha, anterior, nuevo); 422 `transicion_invalida` para Borrador→Archivado y para repetir el estado; 404 si no existe; 403 para colaborador
- [X] T026 [P] [US1] Prueba Feature `backend/tests/Feature/Desarrollo/MicroFacadeOfertaTest.php`: `DesarrolloFacade::getMicrocontenidosPublicadosPorCompetencia` devuelve solo Publicados vinculados con `url_destino = '/micro-learning/{id}'`; Borrador y Archivado no aparecen; `getOfertaPorCompetencia` y `competenciaTieneOferta` cuentan solo Publicados

### Implementation for User Story 1

- [X] T027 [P] [US1] Crear `$D/Domain/Services/ReglasDeMicrocontenido.php` (sin Laravel): validación de transiciones usando `EstadoMicro`, cálculo de `faltantes` para publicar y rechazo del cambio de tipo
- [X] T028 [P] [US1] Crear los DTO en `$D/Application/DTO/`: `MicroDTO.php`, `FilaMicroDTO.php`, `DatosMicroDTO.php` (`titulo` 1–120, `tipo`, `duracionMinutos` ≥ 1, `url`, `miniaturaUrl`, `competenciaIds`, `preguntas` opcional), `ListadoMicroDTO.php` y `OpcionesMicroDTO.php`
- [X] T029 [US1] Implementar `$D/Infrastructure/Persistence/Eloquent/Repositories/EloquentMicrocontenidoRepository.php` (alta con asignación de `codigo = 'MICRO-' . str_pad(id, 4, '0', STR_PAD_LEFT)` en la misma transacción, obtención con competencias, listado filtrado con conteo de completaciones, cambio de estado con entrada de historial, vínculos por `sincronizarCompetenciasDeMicrocontenido`) y enlazarlo en `$D/Infrastructure/Providers/DesarrolloServiceProvider.php`
- [X] T030 [US1] Crear los casos de uso en `$D/Application/UseCases/`: `CrearMicrocontenido`, `ListarMicrocontenidos`, `ObtenerMicrocontenido` (en esta historia solo la vista del Administrador, con `es_correcta` y `completaciones`; la vista del colaborador se añade en T051), `ObtenerOpcionesMicro` (competencias activas vía `CompetenciasInterface`) y `CambiarEstadoMicro` (valida competencias activas con `CompetenciasInterface::estaActiva` y aplica `ReglasDeMicrocontenido`)
- [X] T031 [P] [US1] Crear `$D/Presentation/Requests/GuardarMicrocontenidoRequest.php`, `CambiarEstadoMicroRequest.php` y `ListarMicroRequest.php` (formas, longitudes, `competencia_ids` sin repetidos, `url` `http(s)`), y `$D/Presentation/Resources/MicroResource.php` y `FilaMicroResource.php` con la forma de contracts/microcontenidos-api.md
- [X] T032 [US1] Crear `$D/Presentation/Controllers/MicrocontenidoController.php` (`store`, `index`, `opciones`, `show`, `estado`; delgado, el actor sale del token) y declarar en `$D/Routes/api.php` el bloque `/api/microcontenidos` con `opciones` **antes** de `/{id}`, `auth:sanctum` en todo y `admin` en escritura; mapear las excepciones de T014 a 422/404 con `code`
- [X] T033 [US1] Añadir `getMicrocontenidosPublicadosPorCompetencia(int $competenciaId): array` a `$D/Application/Services/DesarrolloService.php` y a la anotación `@method` de `$D/Presentation/Facades/DesarrolloFacade.php`

### Frontend for User Story 1

- [X] T034 [P] [US1] Crear `$F/validation.ts` (Zod: `titulo` 1–120, `tipo`, `duracion_minutos` entero ≥ 1, `url` `http(s)` obligatoria al publicar salvo Quiz, `miniatura_url` opcional, competencias) y `$F/validation.test.ts`
- [X] T035 [P] [US1] Crear los hooks en `$F/hooks/`: `useListadoParams.ts` (`q`, `tipo`, `estado`, `competencia_id`, `page` en la URL; `estado` por defecto `Activos`), `useRepositorio.ts`, `useMicro.ts`, `useOpcionesMicro.ts`, `useGuardarMicro.ts` y `useCambiarEstado.ts` (TanStack Query; escrituras sin reintento; invalidan el repositorio y el detalle)
- [X] T036 [P] [US1] Crear `$F/components/TarjetaMicro.tsx` (miniatura o imagen genérica por tipo, `EtiquetaTipo`, título h3, competencia + «+N», duración, `Badge` de estado, «Apto para conocimientos», completaciones; color por tipo según T002) y `$F/components/FiltrosRepositorio.tsx` (búsqueda por título, tipo, estado con «Activos» por defecto, competencia, «Limpiar filtros»), con `TarjetaMicro.dom.test.tsx` y `FiltrosRepositorio.dom.test.tsx`
- [X] T037 [US1] Crear `$F/components/FormularioMicro.tsx` (`Modal` de 640 px con título, tipo, selección múltiple de competencias activas con `Combobox` + chips «Quitar», duración, URL y miniatura; errores por campo; «Guardar borrador» siempre visible; «Publicar» muestra los datos que faltan; `AvisoConocimientos`) y `FormularioMicro.dom.test.tsx`
- [X] T038 [US1] Crear `$F/pages/RepositorioAdminPage.tsx` (cuadrícula `repeat(auto-fill, minmax(280px, 1fr))`, `Pagination`, «+ Nuevo Contenido», estados cargando/vacío/error con «Reintentar») y `$F/pages/MicroLearningPage.tsx` (elige la vista por rol), y registrar la ruta `micro-learning` en `frontend/src/App.tsx`; con `RepositorioAdminPage.dom.test.tsx`
- [X] T039 [US1] Crear `frontend/e2e/micro-learning.spec.ts` con el escenario del administrador: crear un Video como Borrador, verificar el código, ver los faltantes al publicar, completarlos y publicar; comprobar que aparece en «Formación» (Catálogo)

**Checkpoint**: el administrador puede crear, ver y publicar contenidos no-Quiz

---

## Phase 4: User Story 2 – Construir un quiz (Priority: P1)

**Goal**: El administrador arma un quiz de 1 a 20 preguntas con 2 a 5 opciones y una correcta, lo publica y no puede alterar sus preguntas una vez que existe un intento terminado.

**Independent Test**: Crear un Quiz con 3 preguntas, reordenarlas, marcar las correctas, publicarlo y comprobar que se conservan el orden y las respuestas correctas; con un intento Finalizado, el cambio de preguntas se rechaza.

### Tests for User Story 2

- [X] T040 [P] [US2] Prueba unitaria `backend/tests/Unit/Desarrollo/MicroReglasDeQuizTest.php`: 1–20 preguntas; enunciado obligatorio; 2–5 opciones con texto; exactamente una correcta por pregunta; rechazo en tipos distintos de Quiz
- [X] T041 [P] [US2] Prueba Feature `backend/tests/Feature/Desarrollo/QuizAdminTest.php`: crear un Quiz con preguntas ordenadas (el orden es el del array); 422 con 21 preguntas, con 1 o 6 opciones, con 0 o 2 correctas o con enunciado vacío; publicar un Quiz sin preguntas devuelve `faltan_requisitos`; `PUT /api/microcontenidos/{id}` reemplaza título, URL, miniatura, duración, competencias y preguntas; 422 `quiz_con_intentos` si existe un intento `Finalizado`; con intentos `EnCurso` solamente, las preguntas se reemplazan y esos intentos se descartan (respuestas e intento borrados); 422 si el `tipo` enviado difiere del guardado; 404 si no existe; el Administrador recibe `es_correcta` y `tiene_intentos` en el detalle

### Implementation for User Story 2

- [X] T042 [P] [US2] Crear `$D/Domain/Services/ReglasDeQuiz.php` (sin Laravel): valida 1–20 preguntas, enunciado obligatorio, 2–5 opciones con texto y exactamente una correcta, lanzando `QuizInvalido` con los errores por campo
- [X] T043 [US2] Ampliar `EloquentMicrocontenidoRepository` con el reemplazo de preguntas y opciones (borrado explícito de las anteriores y de los intentos `EnCurso` con sus respuestas dentro de la transacción, `orden` desde 1), `tieneIntentosFinalizados` y la carga de preguntas en el detalle (T029)
- [X] T044 [US2] Crear `$D/Application/UseCases/ActualizarMicrocontenido.php` (reemplaza título, URL, miniatura, duración, competencias y preguntas en una transacción; rechaza el cambio de tipo con `TipoInmutable`; lanza `QuizConIntentos` si hay intentos `Finalizado`; en Publicado exige que siga cumpliendo los requisitos de publicación) y extender `CrearMicrocontenido`, `GuardarMicrocontenidoRequest` y `MicroResource` con `preguntas` (`es_correcta` solo para el Administrador, `tiene_intentos`)
- [X] T045 [US2] Añadir `update` a `$D/Presentation/Controllers/MicrocontenidoController.php` y la ruta `PUT /api/microcontenidos/{id}` (`admin`) en `$D/Routes/api.php`

### Frontend for User Story 2

- [X] T046 [P] [US2] Ampliar `$F/validation.ts` con el esquema de preguntas (1–20, enunciado obligatorio, 2–5 opciones con texto, exactamente una correcta) y sus casos en `$F/validation.test.ts`
- [X] T047 [US2] Crear `$F/components/ConstructorDeQuiz.tsx` (lista de preguntas con enunciado, 2–5 opciones con radio «correcta», Subir/Bajar y Quitar con `aria-label`, contadores `n/20` preguntas y `n/5` opciones, numeración recalculada con `role="status"`; solo lectura con `Alert` neutro si `tiene_intentos`) con `ConstructorDeQuiz.dom.test.tsx`, e integrarlo en `FormularioMicro.tsx` en lugar de la URL cuando el tipo es Quiz
- [X] T048 [US2] Ampliar `frontend/e2e/micro-learning.spec.ts` con la creación y publicación de un Quiz de 3 preguntas (incluida una de 5 y otra de 2 opciones) y el rechazo de agregar la pregunta 21

**Checkpoint**: el administrador puede crear y editar quizzes con las reglas de la spec

---

## Phase 5: User Story 3 – Consumir un video, PDF o artículo y registrar progreso (Priority: P1)

**Goal**: El colaborador abre un contenido publicado, retoma el Video desde la última posición y lo completa con «Marcar como completado».

**Independent Test**: Como colaborador, iniciar un Video, salir a mitad, volver y comprobar que retoma la posición; pulsar «Marcar como completado» y verificar el estado Completado con fecha.

### Tests for User Story 3

- [X] T049 [P] [US3] Prueba Feature `backend/tests/Feature/Desarrollo/ProgresoMicroTest.php`: `PUT /{id}/progreso` crea el progreso `EnProgreso` con `fecha_inicio` y guarda `posicion_segundos`; `POST /{id}/completar` pasa a `Completado` con `fecha_completado`; 422 si el contenido no está Publicado, si es Quiz o si ya está Completado; 404 para un Borrador, un id inexistente o un Archivado no completado; 403 para el Administrador y para un usuario sin ficha; un colaborador no puede tocar el progreso de otro; `GET /{id}` para el colaborador devuelve su `progreso` y nunca `es_correcta` ni `completaciones`; un Archivado completado por él sigue consultable

### Implementation for User Story 3

- [X] T050 [US3] Implementar `$D/Infrastructure/Persistence/Eloquent/Repositories/EloquentProgresoMicroRepository.php` (progreso por contenido y colaborador con `tenant_id` copiado de `CuentaColaboradorInterface`, guardado de avance, completar) y enlazarlo en `DesarrolloServiceProvider.php`
- [X] T051 [US3] Crear `$D/Application/UseCases/GuardarAvanceMicro.php` y `CompletarMicro.php` (usan `CuentaColaboradorInterface` para el colaborador y el tenant; lanzan `MicroNoDisponible` y `MicroYaCompletado`; Quiz → 422) y completar `ObtenerMicrocontenido` con la vista del colaborador: solo Publicados o Archivados que ya completó, sin `es_correcta` ni `completaciones`, con su progreso
- [X] T052 [US3] Crear `$D/Presentation/Requests/GuardarAvanceRequest.php` (`posicion_segundos` entero ≥ 0, solo Video) y `$D/Presentation/Controllers/ProgresoMicroController.php` (`avance`, `completar`; rechaza al Administrador con 403) y declarar `PUT /{id}/progreso` y `POST /{id}/completar` en `$D/Routes/api.php`

### Frontend for User Story 3

- [X] T053 [P] [US3] Crear `$F/tiempo.ts` (formateo `mm:ss` de la posición y la duración) con su prueba, y los hooks `$F/hooks/useAvance.ts` (guarda con *debounce* cada 10 s y al salir, sin reintento) y `$F/hooks/useCompletar.ts`
- [X] T054 [P] [US3] Crear `$F/components/ReproductorVideo.tsx` (marcador de posición con Reproducir/Pausar que avanza 1 s por segundo, barra de posición, «Reanudar desde mm:ss» y prop `modo="previa"` que no llama a `useAvance`) y `$F/components/VisorDocumento.tsx` (PDF y Artículo: enlace a la `url` en pestaña nueva), con `ReproductorVideo.dom.test.tsx` y `VisorDocumento.dom.test.tsx`
- [X] T055 [US3] Crear `$F/pages/ReproduccionPage.tsx` (según el tipo: video, documento; botón «Marcar como completado» oculto si ya completado, estado Completado visible, `AvisoConocimientos`, estados cargando/error/no disponible) y registrar la ruta `micro-learning/:id` en `frontend/src/App.tsx`, con `ReproduccionPage.dom.test.tsx`
- [X] T056 [US3] Ampliar `frontend/e2e/micro-learning.spec.ts` con el escenario del colaborador: abrir un Video, avanzar, salir, volver y ver «Reanudar desde»; marcarlo como completado y comprobar que el botón desaparece

**Checkpoint**: el colaborador consume y completa Video, PDF y Artículo

---

## Phase 6: User Story 4 – Resolver un quiz (Priority: P1)

**Goal**: El colaborador responde un quiz una pregunta a la vez con retroalimentación inmediata, obtiene su puntaje y puede repetirlo sin perder el estado Completado.

**Independent Test**: Resolver un quiz de 5 preguntas, verificar la retroalimentación por pregunta, el puntaje final y el estado Completado; repetirlo con puntaje menor y comprobar que sigue Completado.

### Tests for User Story 4

- [X] T057 [P] [US4] Prueba unitaria `backend/tests/Unit/Desarrollo/MicroCalculadorDeIntentoTest.php`: puntaje = respuestas correctas sobre el total; un intento termina al responder todas las preguntas; ninguna respuesta repetida
- [X] T058 [P] [US4] Prueba Feature `backend/tests/Feature/Desarrollo/IntentoQuizTest.php`: `POST /{id}/intentos` crea el progreso y un intento `EnCurso` (201) sin `es_correcta` en la respuesta, o devuelve el vigente (200) con lo ya respondido; `{ "reiniciar": true }` descarta el `EnCurso` anterior; `POST /intentos/{intentoId}/respuestas` devuelve `es_correcta` y `opcion_correcta_id`; al responder la última pregunta el intento pasa a `Finalizado` con `puntaje` y el progreso a `Completado` (con `fecha_completado`) si no lo estaba; un nuevo intento con menor puntaje no revierte `Completado`; 422 si la pregunta ya fue respondida, si la opción no pertenece a la pregunta o si el quiz cambió (`intento_descartado`); 422 si el contenido no es Quiz o no está Publicado; 404 si el intento es de otro colaborador; el intento incompleto no cuenta ni bloquea la edición de preguntas

### Implementation for User Story 4

- [X] T059 [P] [US4] Crear `$D/Domain/Services/CalculadorDeIntento.php` (sin Laravel): puntaje, finalización y validación de la respuesta
- [X] T060 [US4] Ampliar `EloquentProgresoMicroRepository` con intentos y respuestas: obtener o crear el intento `EnCurso`, descartarlo (borrado explícito de respuestas e intento), registrar la respuesta, finalizar con `puntaje` y `finalizado_en`, y el último puntaje del colaborador por contenido
- [X] T061 [US4] Crear `$D/Application/UseCases/IniciarIntento.php` y `ResponderPregunta.php` (crean el progreso si no existe, marcan `Completado` en el primer intento finalizado, nunca lo revierten) y `$D/Application/DTO/IntentoDTO.php`
- [X] T062 [US4] Crear `$D/Presentation/Requests/ResponderRequest.php`, `$D/Presentation/Resources/IntentoResource.php` y `$D/Presentation/Controllers/IntentoMicroController.php` y declarar `POST /{id}/intentos` y `POST /intentos/{intentoId}/respuestas` en `$D/Routes/api.php` (la segunda **antes** de cualquier `/{id}`; ambas rechazan al Administrador con 403)

### Frontend for User Story 4

- [X] T063 [P] [US4] Crear `$F/hooks/useIntentoQuiz.ts` (iniciar, retomar, responder y reiniciar el intento; sin reintento en escrituras)
- [X] T064 [P] [US4] Crear `$F/components/QuizPlayer.tsx` («Pregunta N de M», opciones en `radiogroup` de al menos 44 px, retroalimentación inmediata con `role="status"` que indica si fue correcta y cuál era la correcta, «Siguiente», prop `modo="previa"` que evalúa en el cliente sin llamar a la API) y `$F/components/ResultadoQuiz.tsx` («N de M», mensaje neutro sin aprobado/reprobado, «Reintentar quiz» y «Volver a Mi aprendizaje»), con `QuizPlayer.dom.test.tsx` y `ResultadoQuiz.dom.test.tsx`
- [X] T065 [US4] Integrar el Quiz en `$F/pages/ReproduccionPage.tsx` (tipo Quiz → `QuizPlayer`; un Quiz completado muestra su estado y permite repetir) y ampliar `ReproduccionPage.dom.test.tsx`
- [X] T066 [US4] Ampliar `frontend/e2e/micro-learning.spec.ts` con el escenario de resolver un Quiz (retroalimentación, puntaje «N de M», «Reintentar quiz» con puntaje menor y estado que sigue Completado)

**Checkpoint**: el colaborador resuelve quizzes y los repite

---

## Phase 7: User Story 5 – Mi aprendizaje y certificados (Priority: P2)

**Goal**: El colaborador ve su resumen, sus pestañas Asignado y Completado, y descarga el certificado PDF de cada contenido completado.

**Independent Test**: Con un colaborador con brecha activa, ver los contenidos vinculados en «Asignado»; completar uno, comprobar que pasa a «Completado», que las horas suman su duración y que el certificado se descarga con los cinco datos exigidos.

### Tests for User Story 5

- [X] T067 [P] [US5] Pruebas unitarias `backend/tests/Unit/Desarrollo/MicroCalculadorDeAsignacionTest.php` y `MicroCalculadorDeResumenTest.php`: Asignado = Publicado y (vinculado a una brecha activa sin completar, o iniciado sin completar); un completado nunca es Asignado; un Archivado en progreso no es Asignado; horas = suma de `duracion_minutos` de los completados ÷ 60 (20 + 40 min = 1 h); contadores de completados, en progreso y certificados
- [X] T068 [P] [US5] Prueba Feature `backend/tests/Feature/Desarrollo/MiAprendizajeTest.php`: con un doble de `BrechasActivasInterface`, `asignados` incluye los vinculados a brechas (`motivo: "brecha"`) y los iniciados (`motivo: "iniciado"`); `completados` incluye Archivados ya completados con `ultimo_puntaje`; sin brechas ni contenidos devuelve arreglos vacíos; si falla la lectura de brechas conserva los iniciados y añade `avisos: ["brechas_no_disponibles"]`; solo datos propios; 403 para el Administrador
- [X] T069 [P] [US5] Prueba Feature `backend/tests/Feature/Desarrollo/CertificadoMicroTest.php`: 200 `application/pdf` con `Content-Disposition` y nombre `certificado-MICRO-NNNN.pdf` para un contenido completado (también si luego se archivó); contenido del PDF con nombre del colaborador, título, competencias vinculadas, duración y fecha de finalización (con dobles de `NombreDeColaboradorInterface` y `CertificadoPdfInterface`); 404 si no lo completó o no existe; no se puede descargar el de otro colaborador
- [X] T070 [P] [US5] Prueba Feature `backend/tests/Feature/Desarrollo/MicroFacadeReportesTest.php`: `getResumenAprendizaje(tenantId, colaboradorId)` y `getCompletaciones(tenantId, filtros)` (filtros `colaborador_id`, `microcontenido_id`, `desde`, `hasta`) devuelven las formas de contracts/micro-puertos-y-facades.md y respetan el tenant

### Implementation for User Story 5

- [X] T071 [P] [US5] Crear `$D/Domain/Services/CalculadorDeAsignacion.php` y `CalculadorDeResumen.php` (sin Laravel) según las reglas de spec FR-030 y FR-032
- [X] T072 [P] [US5] Crear los puertos `$D/Application/Ports/NombreDeColaboradorInterface.php` y `CertificadoPdfInterface.php`, el DTO `$D/Application/DTO/CertificadoDTO.php`, el adaptador `$D/Infrastructure/Adapters/NombreDeColaboradorDesdePersonal.php` (`PersonalFacade::getColaborador`) y `$D/Infrastructure/Adapters/CertificadoPdfDompdf.php` con el HTML armado en el propio adaptador (sin PHP embebido, sin recursos remotos, `isRemoteEnabled` y `isPhpEnabled` en falso, todos los datos escapados con `e()`; falla con `CertificadoNoDisponible` → 503), y enlazarlos en `DesarrolloServiceProvider.php`
- [X] T073 [US5] Ampliar `EloquentProgresoMicroRepository` con las consultas de mi aprendizaje (progresos y contenidos del colaborador, contenidos Publicados vinculados a competencias dadas), resumen y completaciones por tenant
- [X] T074 [US5] Crear `$D/Application/UseCases/ObtenerMiAprendizaje.php` (usa `BrechasActivasInterface::brechasDe(usuarioId)` con degradación parcial) y `GenerarCertificado.php` (competencias vigentes vía `CompetenciasInterface::porIds`, nombre vía `NombreDeColaboradorInterface`; 404 si no está Completado)
- [X] T075 [US5] Crear `$D/Presentation/Resources/MiAprendizajeResource.php`, `$D/Presentation/Controllers/MiAprendizajeController.php` y `CertificadoMicroController.php` y declarar `GET /api/microcontenidos/mi-aprendizaje` (**antes** de `/{id}`) y `GET /api/microcontenidos/{id}/certificado` en `$D/Routes/api.php`
- [X] T076 [US5] Añadir `getResumenAprendizaje(int $tenantId, int $colaboradorId): array` y `getCompletaciones(int $tenantId, array $filtros = []): array` a `$D/Application/Services/DesarrolloService.php` y a las anotaciones `@method` de `$D/Presentation/Facades/DesarrolloFacade.php`
- [X] T077 [US5] Ampliar `backend/database/seeders/MicroLearningSeeder.php` con progresos de ejemplo (`EnProgreso` y `Completado`, un Video con `posicion_segundos`) e intentos `Finalizado` del Quiz para los colaboradores del seeder de Gestión de Personal. Verificar que en el entorno `e2e` (`backend/database/e2e.sqlite`, adaptadores de prueba del `DesarrolloServiceProvider`) existe al menos un colaborador **no administrador con ficha asociada** y una **brecha activa** en una competencia vinculada a un micro-contenido Publicado sin completar; si falta, crearlos en este seeder o en `DnfSeeder` para que los E2E de US3 y US5 no dependan de datos manuales

### Frontend for User Story 5

- [X] T078 [P] [US5] Crear `$F/hooks/useMiAprendizaje.ts` y `$F/hooks/useDescargarCertificado.ts` (reutiliza `frontend/src/shared/api/pdf.ts`; muestra error comprensible si falla)
- [X] T079 [P] [US5] Crear `$F/components/ResumenAprendizaje.tsx` (cuatro `StatCard`: completados, en progreso, horas acumuladas y certificados) y `$F/components/TarjetaAprendizaje.tsx` (miniatura, título, `EtiquetaTipo`, competencia, duración, estado; botón «Iniciar»/«Continuar»/«Ver»; en Completado, «Descargar certificado»; último puntaje «N de M» en Quiz), con `ResumenAprendizaje.dom.test.tsx` y `TarjetaAprendizaje.dom.test.tsx`
- [X] T080 [US5] Crear `$F/pages/MiAprendizajePage.tsx` (resumen, `Tabs` Asignado/Completado con la pestaña en la URL, mensaje informativo por pestaña vacía, aviso si las brechas no están disponibles, estados cargando/error con «Reintentar»; añadir el mismo caso de error con «Reintentar» a `RepositorioAdminPage.dom.test.tsx` (T038) y `ReproduccionPage.dom.test.tsx` (T055)) con `MiAprendizajePage.dom.test.tsx` y mostrarla en `MicroLearningPage.tsx` para el colaborador
- [X] T081 [US5] Ampliar `frontend/e2e/micro-learning.spec.ts` con el escenario: contenido en «Asignado», completarlo, verlo en «Completado», resumen actualizado y descarga del certificado (respuesta `application/pdf`); incluir iniciar desde «Formación» (Catálogo) un contenido no asignado y comprobar que pasa a «Asignado» como iniciado

**Checkpoint**: el colaborador ve su aprendizaje y descarga certificados

---

## Phase 8: User Story 6 – Administrar el repositorio (Priority: P2)

**Goal**: El administrador edita, archiva, reactiva y elimina contenidos respetando las reglas de la spec; el colaborador no ve ninguna acción administrativa.

**Independent Test**: Archivar un contenido publicado, reactivarlo y eliminar un Borrador sin progreso; verificar que uno con progreso no ofrece Eliminar.

### Tests for User Story 6

- [X] T082 [P] [US6] Prueba Feature `backend/tests/Feature/Desarrollo/EliminarMicroTest.php`: 204 sin progreso, borrando preguntas, opciones, historial y vínculos (con entradas `Quitado` en el historial del Catálogo) en una transacción; 422 `con_progreso` si hay algún progreso (incluso `EnProgreso`) indicando archivar; 404 si no existe; 403 para colaborador; `puede_eliminarse` en el detalle refleja la regla
- [X] T083 [P] [US6] Prueba Feature `backend/tests/Feature/Desarrollo/MicroVisibilidadPorRolTest.php`: el colaborador solo lista Publicados y no ve `completaciones` ni acciones; los Archivados que completó siguen en su historial y su certificado se descarga; un Archivado en progreso conserva el avance y se retoma al volver a publicar; el Administrador ve todos los estados; editar un Publicado (título, duración, URL, miniatura, competencias) no cambia su estado

### Implementation for User Story 6

- [X] T084 [US6] Crear `$D/Application/UseCases/EliminarMicrocontenido.php` y el borrado explícito en `EloquentMicrocontenidoRepository` (orden: opciones, preguntas, historial, vínculos con `Quitado` en el historial del Catálogo, contenido; sin `cascade`), y exponer `puede_eliminarse` en `MicroResource`
- [X] T085 [US6] Añadir `destroy` a `MicrocontenidoController` y la ruta `DELETE /api/microcontenidos/{id}` (`admin`) en `$D/Routes/api.php`, mapeando `MicroConProgreso` a 422 con `code: "con_progreso"`

### Frontend for User Story 6

- [X] T086 [US6] Añadir las acciones por estado a `$F/components/TarjetaMicro.tsx` (visibles solo para el Administrador y solo cuando el estado las permite): Editar, Publicar, Archivar, Reactivar y Eliminar; Eliminar solo si `puede_eliminarse`, y si no, una nota «Solo puede archivarse»; con `ConfirmDialog` de Archivar (informa que el contenido dejará de estar disponible para los colaboradores) y de Eliminar (nombra el contenido y advierte que no se puede deshacer), y mensajes de rechazo del servidor
- [X] T087 [US6] Añadir el modo edición a `$F/components/FormularioMicro.tsx` (precarga del detalle, tipo y código no editables, preguntas en solo lectura si `tiene_intentos` con indicación de archivar y crear uno nuevo, «Publicar» muestra los faltantes) y ampliar `FormularioMicro.dom.test.tsx` y `TarjetaMicro.dom.test.tsx`
- [X] T088 [US6] Ampliar `frontend/e2e/micro-learning.spec.ts` con editar un contenido publicado, archivar (con confirmación), reactivar y eliminar un Borrador; comprobar que un contenido con progreso no ofrece Eliminar y que el colaborador no ve acciones administrativas

**Checkpoint**: el ciclo de vida completo funciona desde la interfaz

---

## Phase 9: User Story 7 – Vista previa del administrador (Priority: P3)

**Goal**: El administrador ve el contenido tal como lo verá el colaborador sin registrar progreso ni intentos.

**Independent Test**: Abrir la vista previa de un Quiz, responder todo y comprobar que no se crean intentos ni progreso y que el número de completaciones no cambia.

- [X] T089 [US7] Crear `$F/components/VistaPreviaMicro.tsx` (`Modal` «Vista previa · así lo verá el colaborador» que reutiliza `ReproductorVideo`, `VisorDocumento` y `QuizPlayer` con `modo="previa"` y los datos de `GET /api/microcontenidos/{id}`; no usa los hooks de avance, completar ni intentos) con `VistaPreviaMicro.dom.test.tsx` que verifica que **no se emite ninguna petición** de progreso ni de intentos
- [X] T090 [US7] Añadir «Vista previa» a `$F/components/TarjetaMicro.tsx` y al `FormularioMicro.tsx` (con los datos aún no guardados cuando el contenido es nuevo)
- [X] T091 [US7] Ampliar `frontend/e2e/micro-learning.spec.ts`: abrir la vista previa de un Quiz, responder todo y verificar con una consulta de la API que `completaciones` no cambia y que un colaborador no tiene acceso a la vista previa

**Checkpoint**: la revisión previa a publicar no deja rastro

---

## Phase 10: User Story 8 – Auditoría e historial (Priority: P3)

**Goal**: Quedan registrados quién creó y modificó cada contenido, cada cambio de estado y los cambios de competencias (en el Catálogo).

**Independent Test**: Publicar, archivar y reactivar un contenido y comprobar tres entradas con usuario, fecha y estados; cambiar sus competencias y verificar la entrada en el historial del Catálogo.

### Tests for User Story 8

- [X] T092 [P] [US8] Prueba Feature `backend/tests/Feature/Desarrollo/HistorialMicroTest.php`: `GET /{id}/historial` pagina (`page`, `per_page` 20) con usuario, fecha, estado anterior y estado nuevo, más reciente primero; incluye la entrada de creación `null → Borrador`; 404 si no existe; 403 para colaborador; el detalle del Administrador muestra `created_by`/`created_at` y `updated_by`/`updated_at` con nombres vía `NombresDeUsuarioInterface`; el historial es de solo inserción (no existen PUT, PATCH ni DELETE sobre él); un cambio de competencias aparece en `GET /api/catalogo-formativo/{competenciaId}/historial`

### Implementation for User Story 8

- [X] T093 [US8] Crear `$D/Application/UseCases/ListarHistorialDeMicro.php`, `$D/Presentation/Resources/HistorialMicroResource.php` y la acción `historial` en `MicrocontenidoController` con la ruta `GET /api/microcontenidos/{id}/historial` (`admin`) en `$D/Routes/api.php`; completar en `MicroResource` los datos de auditoría con nombres de usuario

### Frontend for User Story 8

- [X] T094 [US8] Crear `$F/hooks/useHistorialMicro.ts` y `$F/components/HistorialMicro.tsx` (lista paginada: usuario y fecha en 13 px `--muted`, estado anterior → nuevo; vacío «Aún no hay cambios registrados.») y añadir «Historial» y los datos de auditoría al detalle del administrador, con `HistorialMicro.dom.test.tsx`

**Checkpoint**: la trazabilidad completa es visible

---

## Phase 11: Polish & Cross-Cutting Concerns

- [ ] T095 [P] Revisar accesibilidad y responsive de `$F/**` entre 320 y 1440 px en tema claro y oscuro (foco visible, `aria-label` en IconButton, `role="status"` en la retroalimentación del quiz, formulario y vista previa en `Modal` con foco atrapado y Escape, sin scroll horizontal) y corregir lo que falle
- [X] T096 [P] Verificar que `design/DESIGN.md` §2, §4, §5 y §9 reflejan lo implementado (colores por tipo y estado, componentes, patrones y diferencias con los tableros) y que los tableros `design/Micro*.dc.html` no se modificaron
- [X] T097 Ejecutar la validación completa: `vendor/bin/pest`, `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `npx playwright test` en `frontend/`; confirmar que las pruebas de 005 y 013 siguen verdes; con la siembra grande de T017, medir y anotar SC-010 (listados en menos de 2 s con 500 micro-contenidos) y SC-009 (certificado en menos de 5 s). **Resultado (2026-10-16)**: Pest 482 verdes + 4 errores previos de `PlanSeederTest` (`posiciones_una_maxima_autoridad`, trabajo de posiciones); Pint, ESLint y `tsc` limpios salvo `estructura/unidades`; Vitest 835 verdes y 54 fallos de la línea base (`estructura/unidades` y `CatalogoAdminPage`); E2E de micro-learning 4/4 (la descarga del PDF exige `dompdf` instalado)
- [ ] T098 Recorrer manualmente los escenarios de `specs/016-micro-learning/quickstart.md` (Administrador y Colaborador) y anotar cualquier desviación en la spec o el plan; cronometrar SC-001 (crear y publicar un Video en menos de 3 min) y SC-002 (armar y publicar un Quiz de 5 preguntas en menos de 8 min)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias.
- **Foundational (Phase 2)**: depende de Setup; **bloquea** todas las historias. T003 antes de todo lo demás de backend (su prueba de migración está en T018); T016 y T018 antes de ejecutar cualquier prueba de Desarrollo.
- **US1 (P1)**: tras Foundational. Es el MVP.
- **US2 (P1)**: tras US1 (usa el repositorio, el formulario y el endpoint de creación).
- **US3 (P1)**: tras US1 (necesita contenidos publicados); independiente de US2.
- **US4 (P1)**: tras US3 (comparte `EloquentProgresoMicroRepository` y `ReproduccionPage`). Su backend se prueba con las factories de T012; US2 solo hace falta para crear quizzes desde la interfaz en el E2E.
- **US5 (P2)**: tras US3 y US4 (necesita progreso y completados).
- **US6 (P2)**: tras US1; las acciones de edición de preguntas usan US2.
- **US7 (P3)**: tras US2, US3 y US4 (reutiliza `ReproductorVideo`, `VisorDocumento` y `QuizPlayer`).
- **US8 (P3)**: tras US1 (historial de estados se escribe desde T029); la UI tras US6.
- **Polish (Phase 11)**: tras las historias deseadas.

### Within Each User Story

- Las pruebas se escriben primero y deben fallar antes de implementar.
- Dominio y DTO antes de repositorio; repositorio antes de casos de uso; casos de uso antes de controlador y rutas.
- Hooks y componentes antes de la página; la página antes del E2E.

### Parallel Opportunities

- Migraciones T004–T009 en paralelo (T003 antes por la FK a `microcontenidos`); modelos T011, factories T012, value objects T013 y excepciones T014 en paralelo.
- Frontend base T019–T021 en paralelo con el backend de la fase 2.
- Dentro de cada historia: las pruebas marcadas [P], los servicios de dominio [P] y los componentes de frontend [P] se pueden repartir entre personas; el backend y el frontend de una misma historia avanzan en paralelo una vez fijado el contrato.
- US2 y US3 pueden avanzar en paralelo tras US1 (archivos distintos salvo `Routes/api.php`, `DesarrolloServiceProvider.php` y `frontend/e2e/micro-learning.spec.ts`, que se editan en serie).

---

## Parallel Example: User Story 1

```text
# Pruebas (en paralelo)
T022 MicroReglasDeMicrocontenidoTest · T023 CrearMicrocontenidoTest · T024 ListarMicrocontenidosAdminTest
T025 CambiarEstadoMicroTest · T026 MicroFacadeOfertaTest

# Dominio, DTO y requests (en paralelo)
T027 ReglasDeMicrocontenido · T028 DTO · T031 Requests y Resources

# Frontend (en paralelo con el backend)
T034 validation.ts · T035 hooks · T036 TarjetaMicro y FiltrosRepositorio
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Completar Phase 1 y Phase 2 (incluida la adaptación del Catálogo y las pruebas previas).
2. Completar US1 y **validar**: crear, listar y publicar un Video, y verlo en el Catálogo.
3. Demostrar al administrador antes de seguir.

### Incremental Delivery

1. Setup + Foundational → base lista.
2. US1 → repositorio y publicación (MVP).
3. US2 → quizzes administrables.
4. US3 y US4 → consumo y resolución por el colaborador (ya hay valor de extremo a extremo).
5. US5 → Mi aprendizaje y certificados.
6. US6 → ciclo de vida completo.
7. US7 y US8 → vista previa y auditoría.

Cada historia agrega valor sin romper las anteriores.

---

## Notes

- [P] = archivos distintos, sin dependencias pendientes.
- La etiqueta [USn] traza cada tarea a su historia de usuario.
- Verificar que las pruebas fallan antes de implementar; confirmar tras cada tarea o grupo lógico.
- No añadir dependencias nuevas (dompdf ya está instalada).
- Evitar: tareas vagas, conflictos en el mismo archivo entre tareas [P], dependencias entre historias que rompan su independencia.
