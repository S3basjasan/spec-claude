---

description: "Tareas de la feature 005-catalogo-formativo"
---

# Tasks: Catálogo formativo de competencias

**Input**: Documentos de diseño en `/specs/005-catalogo-formativo/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/catalogo-formativo-api.md, contracts/desarrollo-puertos.md, contracts/estructura-facade-ampliacion.md. Requiere 000-login, 001-diccionario-competencias y 004 ya implementadas (competencias con `estado` y grados en `EstructuraFacade`, `Combobox`, `Alert` neutro, `ConfirmDialog`, `Pagination`, `Badge`, `SearchField`, `SelectField` en `shared/ui`, helpers de prueba y patrones de 001–004). Las features de Gestión de Personal, DNF, cursos y micro-contenidos **no existen**: se resuelven con puertos y tablas mínimas (ver plan.md).

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario para implementarlas y probarlas de forma independiente. La feature es **nueva** (módulo `Desarrollo`) salvo los archivos compartidos indicados como "modificar".

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1–US6)
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Desarrollo/…` (Domain, Application, Infrastructure, Presentation, Routes)
- Frontend: `frontend/src/modules/desarrollo/catalogo-formativo/…` (reutiliza `frontend/src/shared/ui/…`)
- Pruebas backend: `backend/tests/{Unit,Feature}/Desarrollo/`
- Pruebas de componentes: `*.dom.test.tsx` junto al código (con `// @vitest-environment jsdom`); E2E en `frontend/e2e/catalogo-formativo.spec.ts`
- Regla de módulos: `Desarrollo` **solo** usa `EstructuraFacade` para competencias (nunca sus modelos ni tablas); `competencia_id` y los ids de usuario son enteros **sin clave foránea** entre módulos. En frontend, `catalogo-formativo` **no importa nada** de `frontend/src/modules/estructura/*`; el rol llega solo por `frontend/src/modules/auth/login` (`index.ts`). Copiar (no importar) los patrones de 004
- Valores en base de datos (sin tildes; la interfaz muestra con tilde): `estado` de recurso ∈ `Disponible` | `Archivado`; `tipo` de curso ∈ `Online` | `Presencial` | `Mixto`; `tipo` de micro-contenido ∈ `Video` | `Lectura` | `Infografia` | `Audio`; `tipo_cambio` ∈ `AreaFormativa` | `Curso` | `Microcontenido`; `accion` ∈ `Asociado` | `Quitado` | `Cambiado` | `Renombrado`; `tipo` de competencia ∈ `Cardinal` | `Especifica`; roles: capacidad `admin` (Administrador) y rol `user` (Colaborador), excluyentes
- Reutilizar sin modificar: `Button`, `Badge`, `Alert`, `ConfirmDialog`, `Combobox`, `SelectField`, `SearchField`, `TextField`, `Pagination`, `Breadcrumb`, `EmptyState`, `ErrorState` y `Skeleton` (frontend). No crear componentes equivalentes
- Guía visual: `design/CatalogoAdmin.dc.html` y `design/CatalogoColaborador.dc.html` son **referencia, no código para copiar**. Donde difieren de la spec, manda la spec; en estilos, manda `design/DESIGN.md`. Las diferencias están en plan.md ("Diferencias entre los mockups y la spec")
- Insumo técnico `plan-catalogo.md` y plan.md: donde difieren, **prevalecen plan.md y research.md** (áreas creables y renombrables, historial `AreaFormativa`/`Renombrado`, cobertura solo con recursos disponibles, `version` en el `PUT`, `url_destino`, puertos provisionales)
- Lecciones de 002–004: en E2E localizar los campos con `getByRole('textbox', { name, exact: true })`; escribir los archivos con la herramienta Write (los heredocs de Bash fallan con acentos)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Línea base verde y diseño actualizado antes de tocar el frontend

- [X] T001 Verificar la línea base: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run` y `npx playwright test` en `frontend/` deben pasar antes de empezar (sin cambios de archivos)
- [X] T002 [P] Modificar `design/DESIGN.md` **antes** de cualquier tarea de frontend (constitución III): §4 componentes nuevos — **Panel lateral (slide-over)** (`dialog` modal anclado a la derecha, ancho máx. 560px, foco atrapado, Escape y botón cerrar, cabecera con código y nombre, pie con Cancelar y Guardar), **Indicador de cobertura** (StatCard con barra de progreso `role="progressbar"` con `aria-valuenow/min/max`, texto "X de Y competencias activas" y enlace "Ver sin cobertura"), **Tarjeta de competencia con puntos de nivel** (`NivelPuntos`: cuatro puntos A/B/C/D con el nivel actual resaltado y texto accesible "Tu nivel: B"; sin nivel, puntos sin resaltar y texto "Sin nivel registrado"), **Selector de recursos de selección múltiple** (lista con búsqueda, casillas, nombre/tipo/duración/formador y "usado en N competencias", botón "Asociar") e **Historial de cambios** (lista paginada con usuario, fecha, acción y recurso); §5 Listado: patrón **matriz** con indicador de cobertura arriba, filtros (tipo, área, con/sin cursos, estado solo Administrador), columnas competencia (código y nombre), tipo, área, cursos, micro-contenidos y acción "Ver detalle"; patrón **tarjetas agrupadas** por área con sección destacada "Recomendados para cerrar mis brechas"; §9 diferencias con `design/CatalogoAdmin.dc.html` y `design/CatalogoColaborador.dc.html` (se omiten "Ver como colaborador/administrador", "Cobertura por área formativa", columna "Nivel requerido", el puesto del usuario, el tipo "Gerencial", "Para empezar" y los estados "En curso/Continuar"; "Los cambios se guardan al instante" pasa a botón **Guardar** con advertencia de cambios sin guardar; se añaden selector de área, gestión de áreas, historial, indicador de archivado y aviso de competencia inactiva). No tocar los `*.dc.html`. Referenciar (no repetir) `Combobox`, `Alert – neutro` y `ConfirmDialog` ya definidos

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Módulo `Desarrollo`, modelo de datos, dominio, lecturas del repositorio, puertos, siembra y piezas de frontend que necesitan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend

- [X] T003 Crear `backend/database/migrations/2026_10_14_000001_create_areas_formativas_table.php`: `id`; `nombre` `string(60)`; `nombre_clave` `string(60)` **único** (nombre en minúsculas Unicode, recortado y con espacios internos colapsados); timestamps. Sin soft delete
- [X] T004 [P] Crear `backend/database/migrations/2026_10_14_000002_create_cursos_table.php`: tabla **mínima** de la feature de cursos: `id`; `nombre` `string(120)`; `tipo` `string(15)` (`Online` | `Presencial` | `Mixto`); `duracion_minutos` entero sin signo (> 0); `formador` `string(80)` anulable; `estado` `string(12)` por defecto `Disponible` **con índice** (`Disponible` | `Archivado`); `url_destino` `string(255)` anulable; timestamps. Comentario: "la feature de cursos amplía esta tabla con migraciones propias; no recrearla"
- [X] T005 [P] Crear `backend/database/migrations/2026_10_14_000003_create_microcontenidos_table.php`: mismas columnas que `cursos` con `tipo` `string(15)` (`Video` | `Lectura` | `Infografia` | `Audio`) y el mismo comentario hacia Micro-Learning
- [X] T006 Crear `backend/database/migrations/2026_10_14_000004_create_competencia_formativa_table.php`: `id`; `competencia_id` entero sin signo **único** (referencia lógica a Estructura, **sin FK**); `area_formativa_id` FK a `areas_formativas.id` **anulable y sin borrado en cascada** (`restrictOnDelete`); `version` entero sin signo por defecto 1; `updated_by` entero sin signo anulable **sin clave foránea**; timestamps (`updated_at` = última configuración)
- [X] T007 [P] Crear `backend/database/migrations/2026_10_14_000005_create_competencia_curso_table.php`: `id`; `competencia_id` entero sin signo con índice (**sin FK**); `curso_id` FK a `cursos.id` **sin borrado en cascada** (`restrictOnDelete`); `created_by` entero sin signo anulable sin FK; timestamps; restricción **única en `(competencia_id, curso_id)`**
- [X] T008 [P] Crear `backend/database/migrations/2026_10_14_000006_create_competencia_microcontenido_table.php`: igual que `competencia_curso` con `microcontenido_id` FK a `microcontenidos.id` (`restrictOnDelete`) y única en `(competencia_id, microcontenido_id)`
- [X] T009 [P] Crear `backend/database/migrations/2026_10_14_000007_create_competencia_formativa_historial_table.php`: historial **de solo inserción**: `id`; `competencia_id` entero sin signo **anulable** con índice (nulo en renombrados de área); `tipo_cambio` `string(15)` (`AreaFormativa` | `Curso` | `Microcontenido`); `accion` `string(12)` (`Asociado` | `Quitado` | `Cambiado` | `Renombrado`); `recurso_id` entero sin signo anulable; `valor_anterior` `string(255)` anulable; `valor_nuevo` `string(255)` anulable; `user_id` entero sin signo anulable sin FK; solo `created_at` (sin `updated_at`)
- [X] T010 [P] Crear los enums de dominio en `backend/app/Modules/Desarrollo/Domain/ValueObjects/`: `TipoRecurso` (`Curso`, `Microcontenido`), `EstadoRecurso` (`Disponible`, `Archivado`; método `estaDisponible()`), `AccionHistorial` (`Asociado`, `Quitado`, `Cambiado`, `Renombrado`), `TipoCambioHistorial` (`AreaFormativa`, `Curso`, `Microcontenido`) y `NivelGrado` (`A`, `B`, `C`, `D`; propio de Desarrollo, **sin importar** `GradoLetra` de Estructura)
- [X] T011 [P] Crear los value objects en `backend/app/Modules/Desarrollo/Domain/ValueObjects/`: `Cobertura` (`activasTotales`, `activasCubiertas`, `porcentaje` entero), `CambioHistorial` (`tipoCambio`, `accion`, `recursoId` anulable, `valorAnterior` y `valorNuevo` anulables, `competenciaId` anulable), `DatosConfiguracion` (`areaFormativaId` anulable, `cursoIds` y `microcontenidoIds` como listas de enteros sin repetidos: es la entrada del caso de uso y del repositorio, sin DTO ni Request) y `RecursoFormativo` (`id`, `tipoRecurso`, `nombre`, `tipo`, `duracionMinutos`, `formador` anulable, `estado`, `urlDestino` anulable)
- [X] T012 [P] Crear las excepciones finales (`RuntimeException`) en `backend/app/Modules/Desarrollo/Domain/Exceptions/`: `CompetenciaNoEncontrada` ("La competencia no existe."), `ConfiguracionModificada` ("La configuración cambió desde que la abriste. Recarga para ver la versión actual."), `CompetenciaInactiva` ("La competencia está inactiva: no se pueden asociar recursos nuevos."), `RecursoNoDisponible` (recibe un mapa clave de error → mensaje, p. ej. `curso_ids.1`), `AreaNoEncontrada` ("El área formativa no existe.") y `NombreDeAreaDuplicado` ("Ya existe un área formativa con ese nombre.")
- [X] T013 Crear la entidad `backend/app/Modules/Desarrollo/Domain/Entities/ConfiguracionFormativa.php` (`competenciaId`, `areaFormativaId` anulable, `version`, `actualizadoPor` anulable, `actualizadoEn` anulable, `cursos` y `microcontenidos` como `RecursoFormativo`; para una competencia nunca configurada: `version` 0, sin área, listas vacías)
- [X] T014 Crear el servicio de dominio puro `backend/app/Modules/Desarrollo/Domain/Services/CalculadorDeCobertura.php` (sin Laravel): `calcular(array $recursosDisponiblesPorCompetenciaActiva): Cobertura` donde la entrada es un mapa `competencia_id => cantidad de recursos Disponible (cursos + micro-contenidos)` de **solo competencias activas**; cubierta = cantidad ≥ 1; `porcentaje` = redondeo a entero de cubiertas / totales × 100, y **0 si no hay activas**
- [X] T015 [P] Crear `backend/tests/Unit/Desarrollo/CalculadorDeCoberturaTest.php`: 10 activas con 4 cubiertas → 40 %; sin activas → 0 % sin error; todas cubiertas → 100 %; competencia con solo recursos archivados (cantidad 0) → no cubierta; redondeo (1 de 3 → 33; 2 de 3 → 67)
- [X] T016 Crear el servicio de dominio puro `backend/app/Modules/Desarrollo/Domain/Services/ReglasDeConfiguracionFormativa.php`: `validar(bool $competenciaActiva, DatosConfiguracion $solicitado, array $cursosActuales, array $microsActuales, array $disponiblesCursos, array $disponiblesMicros, array $areasExistentes)` según research.md D-08: lanza `CompetenciaInactiva` si la competencia está inactiva y la solicitud **agrega** algún recurso (las bajas y el cambio de área se permiten); lanza `RecursoNoDisponible` (mapa `curso_ids.N` / `microcontenido_ids.N`) si un id **nuevo** no existe o no está `Disponible` (un id ya vinculado que se archivó puede conservarse); lanza `AreaNoEncontrada` si el área indicada no existe; ids repetidos → `RecursoNoDisponible`
- [X] T017 [P] Crear `backend/tests/Unit/Desarrollo/ReglasDeConfiguracionFormativaTest.php`: competencia activa agrega curso disponible → ok; inactiva agrega → `CompetenciaInactiva`; inactiva solo quita → ok; inactiva solo cambia el área → ok; recurso nuevo archivado → `RecursoNoDisponible` con la clave del índice; recurso ya vinculado y archivado que se conserva → ok; id inexistente → error; ids repetidos → error; área inexistente → `AreaNoEncontrada`; `area_formativa_id` nulo → ok
- [X] T018 Crear el servicio de dominio puro `backend/app/Modules/Desarrollo/Domain/Services/ComparadorDeCambiosFormativos.php`: `comparar(int $competenciaId, ?int $areaAnterior, ?string $nombreAreaAnterior, ?int $areaNueva, ?string $nombreAreaNueva, array $cursosAntes, array $cursosAhora, array $microsAntes, array $microsAhora): array<CambioHistorial>` según research.md D-13: una fila `Curso`/`Microcontenido` + `Asociado` (valor nuevo = nombre del recurso) por alta y `Quitado` (valor anterior = nombre) por baja; una fila `AreaFormativa` + `Cambiado` con nombres anterior y nuevo si el área cambia (nulo = "Sin área formativa" no se escribe: el valor queda `null`); sin diferencias → lista vacía
- [X] T019 [P] Crear `backend/tests/Unit/Desarrollo/ComparadorDeCambiosFormativosTest.php`: alta de dos cursos → dos filas `Asociado`; baja de un micro-contenido → una fila `Quitado`; cambio de área con nombres; de sin área a un área (valor anterior `null`); sin diferencias → lista vacía; el orden de los ids no genera filas
- [X] T020 Crear `backend/app/Modules/Desarrollo/Domain/Repositories/CatalogoFormativoRepositoryInterface.php` con las **lecturas base**: `configuracionDe(int $competenciaId): ConfiguracionFormativa` (con recursos vinculados, incluidos los archivados; vacía y `version` 0 si no existe), `configuracionesDe(array $competenciaIds): array` (mapa id → `ConfiguracionFormativa`, para la matriz sin consultas por fila), `areas(): array`, `area(int $id): ?array` y `recursosDisponibles(TipoRecurso $tipo, ?string $q, ?int $excluirCompetenciaId): array` (cada uno con `competenciasCount`). Los métodos de escritura, historial y recomendados se añaden en sus historias
- [X] T021 [P] Crear los modelos Eloquent en `backend/app/Modules/Desarrollo/Infrastructure/Persistence/Eloquent/Models/`: `AreaFormativaModel` (helper estático `claveDeNombre()`: minúsculas Unicode, recortado y espacios internos colapsados), `CursoModel`, `MicrocontenidoModel`, `CompetenciaFormativaModel` (relación `area()`), `CompetenciaCursoModel` (relación `curso()`), `CompetenciaMicrocontenidoModel` (relación `microcontenido()`) y `HistorialFormativoModel` (`UPDATED_AT = null`); **sin tocar tablas de Estructura**
- [X] T022 Crear `backend/app/Modules/Desarrollo/Infrastructure/Persistence/Eloquent/Repositories/EloquentCatalogoFormativoRepository.php` con las **lecturas** de T020 (la matriz usa `configuracionesDe` con una consulta por tabla y agrupa en memoria; las búsquedas de `recursosDisponibles` normalizan el texto sin tildes y sin distinguir mayúsculas, filtran `estado = Disponible` y, con `excluirCompetenciaId`, omiten los ya vinculados)
- [X] T023 [P] Crear los puertos `backend/app/Modules/Desarrollo/Application/Ports/NivelesDeColaboradorInterface.php` (`nivelesDe(int $usuarioId): array` → `[competencia_id => 'A'|'B'|'C'|'D']`), `BrechasActivasInterface.php` (`brechasDe(int $usuarioId): array` → `[ {competencia_id, nivel_requerido} ]`) y `NombresDeUsuarioInterface.php` (`nombresDe(array $usuarioIds): array` → `[id => nombre]`), según contracts/desarrollo-puertos.md
- [X] T024 [P] Crear los adaptadores en `backend/app/Modules/Desarrollo/Infrastructure/Adapters/`: `NivelesDeColaboradorSinDatos` y `BrechasActivasSinDatos` (devuelven `[]`; comentario con la nota de sustitución por las Facades de Gestión de Personal y DNF) y `NombresDeUsuarioDesdeShared` (llama a `SharedFacade::getNombresDeUsuarios($ids)` de T025 y **no** lee la tabla `users`; devuelve "Usuario eliminado" para ids ausentes del resultado)
- [X] T025 Ampliar las Facades públicas. **Shared** (según contracts/shared-facade-ampliacion.md): crear el caso de uso `backend/app/Modules/Shared/Application/UseCases/ObtenerNombresDeUsuarios.php` (recibe ids, devuelve `[id => nombre]` solo de los existentes, una consulta, sin exponer correo ni otros datos), añadir `getNombresDeUsuarios(array $ids): array` a `backend/app/Modules/Shared/Application/Services/SharedService.php` y su docblock en `backend/app/Modules/Shared/Presentation/Facades/SharedFacade.php`, con la prueba `backend/tests/Feature/Shared/SharedFacadeNombresDeUsuariosTest.php`. **Estructura** (según contracts/estructura-facade-ampliacion.md): modificar `backend/app/Modules/Estructura/Application/Services/EstructuraService.php` (+ `getTodasLasCompetencias(): array` → `{id, codigo, nombre, descripcion, tipo, estado}` de **todas** las competencias, por nombre, delegando en el repositorio existente) y el docblock de `backend/app/Modules/Estructura/Presentation/Facades/EstructuraFacade.php`; sin cambiar ningún método existente. Añadir la prueba `backend/tests/Feature/Estructura/EstructuraFacadeTodasLasCompetenciasTest.php` (incluye inactivas, forma del arreglo, orden por nombre)
- [X] T026 Crear `backend/app/Modules/Desarrollo/Infrastructure/Providers/DesarrolloServiceProvider.php` (binding `CatalogoFormativoRepositoryInterface` → `EloquentCatalogoFormativoRepository`; los tres puertos de T023 → sus adaptadores de T024; carga `Routes/api.php`), registrarlo en `backend/bootstrap/providers.php` (modificar) y crear `backend/app/Modules/Desarrollo/Routes/api.php` con el grupo `Route::prefix('api/catalogo-formativo')->middleware('api')` y el comentario "No existe DELETE: los vínculos se quitan con PUT y las áreas no se eliminan"; las rutas se añaden en cada historia, declarando `/cobertura`, `/recomendados`, `/recursos-disponibles` y `/areas` **antes** de `/{competenciaId}` (restringido con `whereNumber`)
- [X] T027 [P] Crear las factories `backend/database/factories/{AreaFormativaFactory,CursoFactory,MicrocontenidoFactory}.php` (curso/micro-contenido con estados `archivado()` y `sinDestino()`) y el seeder `backend/database/seeders/CatalogoFormativoSeeder.php` según data-model.md ("Siembra"): depende de `CompetenciasSeeder`; 4 áreas (Liderazgo y gestión, Habilidades interpersonales, Técnica y datos, Desempeño y resultados), 8 cursos (uno `Archivado`, uno con `url_destino`) y 4 micro-contenidos sobre las 7 competencias sembradas, de modo que ≥ 2 competencias activas queden sin recursos, ≥ 1 inactiva conserve vínculos, ≥ 1 activa tenga solo recursos archivados y un curso esté vinculado a varias competencias; modificar `backend/database/seeders/DatabaseSeeder.php` para invocarlo después de `PuestosSeeder`
- [X] T028 [P] Crear los helpers de prueba `backend/tests/Feature/Desarrollo/ayudas.php` (o el patrón de 004: crear usuario admin y user con token, sembrar competencias activas/inactivas, vincular recursos) y fakes `NivelesDeColaboradorFalso` y `BrechasActivasFalsas` en `backend/tests/Support/Desarrollo/` para reemplazar los puertos en pruebas

### Frontend

- [X] T029 [P] Crear `frontend/src/modules/desarrollo/catalogo-formativo/api.ts` con los tipos TypeScript (`any` prohibido) y las funciones que usan el cliente HTTP compartido, según contracts/catalogo-formativo-api.md: `FilaCatalogo`, `DetalleFormativo`, `Recurso`, `AreaFormativa`, `Cobertura`, `Recomendado`, `EntradaHistorial`, `listarCatalogo`, `obtenerDetalle`, `obtenerCobertura`, `listarAreas`, `crearArea`, `renombrarArea`, `listarRecursosDisponibles`, `guardarConfiguracion`, `listarRecomendados`, `listarHistorial` (las funciones de cada historia se añaden en ella)
- [X] T030 [P] Crear `frontend/src/modules/desarrollo/catalogo-formativo/text.ts` con **todo** el texto visible en español (tú, verbo + objeto): títulos "Catálogo formativo" y "Catálogo formativo de competencias", etiquetas de columnas y filtros, estados vacío/error/cargando, "Ninguna competencia coincide con la búsqueda.", tipos con tilde ("Específica", "Capacitación"), "Próximamente", "Sin área formativa", la nota de micro-contenidos ("Aptos para desarrollo de conocimientos, no de competencias conductuales"), mensajes de confirmación y de éxito
- [X] T031 [P] Crear `frontend/src/modules/desarrollo/catalogo-formativo/nav.tsx` con el ítem "Formación" (ruta `/formacion`; icono SVG propio en `frontend/src/shared/ui/icons.tsx`, modificar) y `components/RequireRole.tsx` (presentación: decide por el rol de `modules/auth/login` si renderiza la vista de Administrador o de Colaborador; el backend mantiene la autoridad)
- [X] T032 Crear `frontend/src/modules/desarrollo/catalogo-formativo/pages/CatalogoPage.tsx` **mínimo** (usa `RequireRole` de T031 y renderiza por ahora solo un `h1` con el título del módulo; T047 lo completa con las vistas) y modificar `frontend/src/App.tsx`: ruta `formacion` dentro de `AuthenticatedLayout` que renderiza `CatalogoPage` (elige la vista por rol; **sin interruptor** entre vistas) y añadir `catalogoFormativoNav` a `navItems`, de modo que `tsc` y `lint` pasen al terminar la fase
- [X] T033 [P] Crear `frontend/src/test/catalogoFormativoFixtures.ts` con filas, detalles, recursos, áreas, cobertura, recomendados e historial de ejemplo (incluida una competencia inactiva, un recurso archivado y uno sin `url_destino`) siguiendo el patrón de `puestosFixtures.ts`

**Checkpoint**: La base está lista; las historias pueden empezar.

---

## Phase 3: User Story 1 - Consultar la oferta formativa, como colaborador (Priority: P1) 🎯 MVP

**Goal**: El colaborador ve tarjetas por área con su nivel actual, abre el detalle y ve solo recursos disponibles.

**Independent Test**: Con competencias activas con cursos y micro-contenidos (uno archivado), iniciar sesión como colaborador, revisar las tarjetas, abrir el detalle y comprobar que no hay archivados, controles de gestión ni niveles ajenos.

### Tests for User Story 1

- [X] T034 [P] [US1] Crear `backend/tests/Feature/Desarrollo/ListarCatalogoColaboradorTest.php`: el user solo recibe competencias `Activo`; `estado=Todos` se ignora; contadores sin recursos archivados; `nivel_actual` desde un puerto falso y `null` sin dato; el user no recibe ids ajenos (el puerto se invoca con su propio id); sin token → 401; `area_formativa` `null` si no tiene; orden por nombre; paginación
- [X] T035 [P] [US1] Crear `backend/tests/Feature/Desarrollo/DetalleFormativoColaboradorTest.php`: 200 con grados, área, cursos y micro-contenidos **solo disponibles**, `nivel_actual` y `nivel_requerido` solo si hay brecha; sin `version` ni `ultima_configuracion`; competencia inactiva → 404 para el user; inexistente → 404; el puerto lanza excepción → 200 con `nivel_actual` `null`
- [X] T036 [P] [US1] Crear `backend/tests/Feature/Desarrollo/AreasListadoTest.php`: `GET /areas` devuelve las áreas ordenadas por nombre para admin y user; sin token → 401

### Implementation for User Story 1

- [X] T037 [P] [US1] Crear los DTO `FiltroCatalogoDTO`, `FilaCatalogoDTO`, `ListadoCatalogoDTO`, `DetalleFormativoDTO`, `RecursoDTO` y `AreaDTO` en `backend/app/Modules/Desarrollo/Application/DTO/` (la fila lleva `cubierta` y contadores solo con recursos `Disponible`)
- [X] T038 [US1] Crear el caso de uso `ListarCatalogo` en `backend/app/Modules/Desarrollo/Application/UseCases/ListarCatalogo.php`: obtiene las competencias por `EstructuraFacade` (activas si el rol no es admin), combina con `configuracionesDe`, calcula contadores y `cubierta`, aplica los filtros (`q` sin tildes ni mayúsculas, `tipo`, `area_formativa_id`, `cobertura`, `estado`, `competencia`) y pagina en memoria; para el user añade `nivel_actual` desde `NivelesDeColaboradorInterface` con el id del usuario autenticado y tolera fallos del puerto
- [X] T039 [US1] Crear el caso de uso `ObtenerDetalleFormativo` en `backend/app/Modules/Desarrollo/Application/UseCases/ObtenerDetalleFormativo.php`: competencia y grados por `getCompetencia`; `CompetenciaNoEncontrada` si no existe o (para el user) está inactiva; para el user filtra los recursos a `Disponible`, añade `nivel_actual` y `nivel_requerido` (puerto de brechas) y omite `version` y `ultima_configuracion`; para el admin incluye archivados señalados, `version` y `ultima_configuracion` con el nombre vía `NombresDeUsuarioInterface`
- [X] T040 [US1] Crear el caso de uso `ListarAreas` (`Application/UseCases/ListarAreas.php`) que devuelve las áreas por nombre
- [X] T041 [P] [US1] Crear `Presentation/Requests/ListarCatalogoRequest.php` (valida `q` ≤ 50, `tipo` ∈ `Cardinal`|`Especifica`, `area_formativa_id` entero, `cobertura` ∈ `con`|`sin`|`sin_oferta` (`con`/`sin` = con/sin **cursos** disponibles; `sin_oferta` = ningún curso ni micro-contenido disponible), `estado` ∈ `Activo`|`Inactivo`|`Todos`, `page` ≥ 1, `per_page` 1–50 por defecto 10, `competencia` entero) y los recursos `FilaCatalogoResource`, `DetalleFormativoResource` y `AreaFormativaResource` en `backend/app/Modules/Desarrollo/Presentation/` (tipo `Especifica` sin tilde; sin campos de gestión para el user)
- [X] T042 [US1] Crear `Presentation/Controllers/CatalogoFormativoController.php` (delgado: `index` y `show`; el rol sale de la capacidad `admin` del token y el id de usuario de `$request->user()`) y `AreaFormativaController.php` (`index`); registrar `GET /`, `GET /areas` y `GET /{competenciaId}` con `auth:sanctum` en `Routes/api.php` (areas antes del id); mapear `CompetenciaNoEncontrada` a 404 con su mensaje
- [X] T043 [P] [US1] Crear los hooks `hooks/useCatalogo.ts` (lista, clave `["catalogo","lista",{…}]`), `hooks/useDetalleFormativo.ts` (clave `["catalogo","detalle",id]`) y `hooks/useAreas.ts` (clave `["catalogo","areas"]`) en `frontend/src/modules/desarrollo/catalogo-formativo/`
- [X] T044 [P] [US1] Crear `components/NivelPuntos.tsx` (cuatro puntos A/B/C/D con el nivel resaltado y texto accesible; sin nivel, sin resaltar) y `components/RecursoItem.tsx` (nombre, `Badge` de tipo con color semántico, duración, formador y botón "Iniciar"/"Ver"; **deshabilitado con "Próximamente"** si `url_destino` es nulo; marca de archivado solo en la vista de administrador) con sus pruebas `NivelPuntos.dom.test.tsx` y `RecursoItem.dom.test.tsx`
- [X] T045 [P] [US1] Crear `components/TarjetaCompetencia.tsx` (nombre, tipo, `NivelPuntos` y "X cursos disponibles"; toda la tarjeta abre el detalle) y `components/GrupoPorArea.tsx` (encabezado con el área y rejilla de tarjetas; el grupo "Sin área formativa" al final) con prueba `GrupoPorArea.dom.test.tsx`
- [X] T046 [US1] Crear `components/DetalleColaborador.tsx` (grados A/B/C/D de la competencia con el nivel actual marcado, sección Cursos, sección Micro-contenidos con la nota de conocimientos y mensaje "aún no hay oferta formativa" si no hay recursos; estados cargando/error con "Reintentar"; el identificador viaja en `?competencia=` de la URL) y `pages/CatalogoColaboradorPage.tsx` (agrupa por área, estados cargando con `Skeleton`/vacío/error, abre el detalle)
- [X] T047 [US1] Completar `pages/CatalogoPage.tsx` creado en T032 (usa `RequireRole`: `admin` → `CatalogoAdminPage` cuando exista; `user` → `CatalogoColaboradorPage`) y su prueba `CatalogoColaboradorPage.dom.test.tsx` (tarjetas por área, nivel resaltado, sin nivel, sin controles de gestión ni cobertura, "Próximamente", estados vacío/error)

**Checkpoint**: El colaborador puede consultar el catálogo; es el MVP funcional.

---

## Phase 4: User Story 2 - Gestionar la matriz formativa, como administrador (Priority: P1)

**Goal**: El administrador ve la matriz con filtros y el indicador de cobertura.

**Independent Test**: Con competencias activas e inactivas y distintas asociaciones, recorrer la matriz, aplicar búsqueda y cada filtro y verificar el porcentaje de cobertura.

### Tests for User Story 2

- [X] T048 [P] [US2] Crear `backend/tests/Feature/Desarrollo/ListarCatalogoAdminTest.php`: por defecto solo activas; `estado=Inactivo|Todos`; filtros `tipo`, `area_formativa_id`, `cobertura=con|sin|sin_oferta` (una competencia con solo micro-contenidos disponibles cuenta como `sin` pero no como `sin_oferta`), `q` sin tildes ni mayúsculas y sus combinaciones; contadores con solo recursos disponibles; el admin **no** recibe `nivel_actual`; sin coincidencias → `data` vacío; 422 por parámetros inválidos
- [X] T049 [P] [US2] Crear `backend/tests/Feature/Desarrollo/CoberturaTest.php`: 10 activas con 4 cubiertas → 40 %; una activa con solo recursos archivados no cuenta; las inactivas no cuentan aunque conserven vínculos; sin activas → 0 %; el user recibe 403; sin token → 401

### Implementation for User Story 2

- [X] T050 [US2] Crear el caso de uso `ObtenerCobertura` (`Application/UseCases/ObtenerCobertura.php`) que usa `getCompetenciasActivas`, las cantidades disponibles de `configuracionesDe` y `CalculadorDeCobertura`; crear `CoberturaDTO`; añadir `GET /cobertura` con `auth:sanctum` + `admin` en `Routes/api.php` (antes de `/{competenciaId}`) y el método en el controlador
- [X] T051 [P] [US2] Crear `hooks/useListadoParams.ts` (búsqueda con retardo de 300 ms, `tipo`, `area_formativa_id`, `cobertura` y `estado` en `useSearchParams`; vuelta a la página 1 al cambiar filtros; `estado` solo se escribe si es `Inactivo` o `Todos` y solo para el Administrador) y `hooks/useCobertura.ts` (clave `["catalogo","cobertura"]`)
- [X] T052 [P] [US2] Crear `components/CoberturaIndicador.tsx` (porcentaje, barra `role="progressbar"`, "X de Y competencias activas" y enlace "Ver sin cobertura" que aplica `cobertura=sin_oferta`) con prueba `CoberturaIndicador.dom.test.tsx` (0 %, 40 %, 100 %, atributos ARIA, enlace)
- [X] T053 [US2] Crear `components/CatalogoMatriz.tsx` (DataTable con columnas competencia —código y nombre—, tipo, área formativa, cursos asociados, micro-contenidos asociados y acción "Ver detalle" con `aria-label` que incluye el nombre; `Badge` neutro "Inactiva" cuando se ven inactivas; "Sin cursos"/"Sin micro-contenidos" cuando el contador es 0; los contadores son solo de recursos **disponibles**; `Pagination`) y `pages/CatalogoAdminPage.tsx` (encabezado, aviso "El diccionario de competencias es de solo lectura", `CoberturaIndicador`, barra de filtros con `SearchField` y selects tipo, área, con/sin cursos y estado, estados cargando/vacío/sin coincidencias/error; abrir el detalle fija `?competencia=`); conectar en `CatalogoPage.tsx`
- [X] T054 [US2] Crear `components/CatalogoMatriz.dom.test.tsx` y `CatalogoAdminPage.dom.test.tsx` (filtros que actualizan la URL, estado solo visible para admin, "Ver sin cobertura", estados vacío/error/cargando, `aria-label` de las acciones)

**Checkpoint**: Administrador y colaborador pueden consultar; el administrador ya detecta competencias sin cobertura.

---

## Phase 5: User Story 3 - Asociar y quitar cursos y micro-contenidos (Priority: P1)

**Goal**: Desde el panel lateral el administrador asocia y quita recursos y guarda de forma atómica.

**Independent Test**: En una competencia activa sin cursos, asociar dos, guardar y comprobar la matriz y la vista del colaborador; quitar uno con confirmación y comprobar que el curso sigue existiendo.

### Tests for User Story 3

- [X] T055 [P] [US3] Crear `backend/tests/Feature/Desarrollo/ActualizarConfiguracionTest.php`: asocia dos cursos y un micro-contenido (200, contadores, `version` 1, `updated_by`); quitar un vínculo no borra el curso; mismo curso en varias competencias; ids repetidos → 422; id inexistente → 422; recurso nuevo archivado → 422 con clave `curso_ids.N`; competencia inactiva con recursos nuevos → 422 y solo quitar → 200; sin cambios → 200 sin tocar `version` ni historial; `version` desactualizada → 409; competencia inexistente → 404; user → 403; sin token → 401; atomicidad (si una validación falla no se guarda nada); un recurso ya vinculado y archivado puede conservarse
- [X] T056 [P] [US3] Crear `backend/tests/Feature/Desarrollo/RecursosDisponiblesTest.php`: `tipo` obligatorio (422); solo recursos `Disponible`; `competencia_id` excluye los ya vinculados; búsqueda sin tildes; `competencias_count`; sin micro-contenidos registrados → lista vacía; user → 403
- [X] T057 [P] [US3] Crear `backend/tests/Feature/Desarrollo/EloquentCatalogoFormativoRepositoryTest.php`: guardado en transacción (si falla el historial no queda nada), restricción única de vínculos, primera configuración crea la fila con `version` 1, sin cambios no escribe

### Implementation for User Story 3

- [X] T058 [US3] Ampliar `CatalogoFormativoRepositoryInterface` y `EloquentCatalogoFormativoRepository` con `guardarConfiguracion(int $competenciaId, int $versionEsperada, DatosConfiguracion $datos, array $cambios, ?int $actorId): ConfiguracionFormativa`: **en una transacción** verifica la versión (lanza `ConfiguracionModificada`), crea o actualiza `competencia_formativa` (incrementa `version` y fija `updated_by` solo si hay cambios reales), sincroniza `competencia_curso` y `competencia_microcontenido` (altas y bajas, `created_by`) e inserta las filas de `competencia_formativa_historial` con `user_id` y el nombre del recurso en `valor_nuevo`/`valor_anterior`; sin cambios no escribe nada
- [X] T059 [US3] Crear el caso de uso `ActualizarConfiguracionFormativa` (`Application/UseCases/ActualizarConfiguracionFormativa.php`): comprueba la existencia y el estado con `existeCompetencia`/`estaActiva`, carga la configuración y los recursos disponibles, ejecuta `ReglasDeConfiguracionFormativa`, calcula los cambios con `ComparadorDeCambiosFormativos` (nombres del área anterior y nueva incluidos) y llama al repositorio; devuelve el detalle actualizado
- [X] T060 [US3] Crear el caso de uso `ListarRecursosDisponibles` (`Application/UseCases/ListarRecursosDisponibles.php`) y `Presentation/Requests/GuardarConfiguracionRequest.php` (`area_formativa_id` entero anulable que exista; `curso_ids` y `microcontenido_ids` arreglos de enteros sin repetidos; `version` entero obligatorio ≥ 0) y `ListarRecursosDisponiblesRequest` (`tipo` ∈ `curso`|`microcontenido` obligatorio, `q` ≤ 50, `competencia_id` entero)
- [X] T061 [US3] Registrar en `Routes/api.php` `GET /recursos-disponibles` (`auth:sanctum` + `admin`, antes de `/{competenciaId}`) y `PUT /{competenciaId}` (`auth:sanctum` + `admin`, `whereNumber`); añadir los métodos al controlador mapeando `ConfiguracionModificada` → 409, `CompetenciaInactiva`/`RecursoNoDisponible`/`AreaNoEncontrada` → 422 con `errors` por clave (la inactiva bajo `competencia`) y `CompetenciaNoEncontrada` → 404
- [X] T062 [P] [US3] Ampliar `api.ts` con `listarRecursosDisponibles` y `guardarConfiguracion`; crear `hooks/useGuardarConfiguracion.ts` (sin reintentos; invalida `["catalogo"]` sin esperar la recarga) y `validation.ts` (esquema Zod de la solicitud: ids sin repetidos, `version`)
- [X] T063 [P] [US3] Crear `components/SelectorDeRecursos.tsx` (selector de selección múltiple con búsqueda: lista de recursos disponibles aún no asociados, nombre/tipo/duración/formador y "usado en N competencias", botón "Asociar", mensaje "Todos los recursos disponibles ya están asociados a esta competencia" y, si no hay micro-contenidos, "Aún no hay micro-contenidos disponibles"; `Escape` y "Volver al detalle") con prueba `SelectorDeRecursos.dom.test.tsx`
- [X] T064 [US3] Crear `components/DetalleCompetenciaPanel.tsx`: panel lateral modal (foco atrapado, Escape y cerrar) con sección de **solo lectura** (descripción, tipo, estado y grados A/B/C/D con la etiqueta "Estructura Empresarial · solo lectura") y sección **editable** (cursos y micro-contenidos asociados con `RecursoItem` y "Quitar", botones "Asociar curso" y "Asociar contenido"); el estado de los cambios (altas, bajas) vive en el formulario hasta pulsar **Guardar** (bloqueado mientras guarda); quitar abre `ConfirmDialog` ("Se quitará el vínculo con la competencia; el curso seguirá existiendo."); con competencia **inactiva** muestra el `Alert` neutro con el motivo y deshabilita los botones de asociar (puede quitar); los recursos archivados se señalan con `Badge` neutro "Archivado" y el encabezado de cada sección indica cuántos hay ("2 cursos · 1 archivado"), de modo que coincida con los contadores de la matriz; junto al listado de micro-contenidos muestra la nota de aptitud para conocimientos (FR-016); al cerrar con cambios sin guardar abre un `ConfirmDialog` de descarte; mapea 422 a campos y alerta general, 409 a la alerta "La configuración cambió" con "Recargar" y 404 a "ya no existe"
- [X] T065 [US3] Conectar el panel en `CatalogoAdminPage.tsx` ("Ver detalle" abre el panel con `?competencia=`; tras guardar, la matriz y la cobertura se actualizan) y crear `DetalleCompetenciaPanel.dom.test.tsx` (asociar y guardar, quitar con confirmación, cambios sin guardar, inactiva con asociar deshabilitado, 409 con "Recargar", 422 por campo, botones de gestión solo para admin)

**Checkpoint**: El administrador puede construir la oferta que ve el colaborador.

---

## Phase 6: User Story 4 - Asignar el área formativa (Priority: P2)

**Goal**: Asignar el área a una competencia y crear y renombrar áreas.

**Independent Test**: Crear un área, asignarla desde el panel, guardar y comprobar la matriz y la vista del colaborador; renombrarla y probar el nombre duplicado.

### Tests for User Story 4

- [X] T066 [P] [US4] Crear `backend/tests/Feature/Desarrollo/AreasGestionTest.php`: crear (201), nombre vacío o solo espacios → 422, > 60 → 422, duplicado sin distinguir mayúsculas ni espacios alrededor ni espacios internos repetidos → 422; renombrar (200) con historial `AreaFormativa`/`Renombrado` (nombre anterior y nuevo, usuario, `competencia_id` nulo); renombrar con la misma capitalización distinta del propio nombre → 200; sin cambio real → sin historial; área inexistente → 404; user → 403; **no existe DELETE** (405/404)
- [X] T067 [P] [US4] Ampliar `ActualizarConfiguracionTest.php` con el cambio de área (historial `AreaFormativa`/`Cambiado` con nombres anterior y nuevo; de sin área a un área; a `null`) y el área inexistente → 422

### Implementation for User Story 4

- [X] T068 [US4] Ampliar el repositorio con `crearArea(string $nombre, ?int $actorId): array` (valida unicidad por `nombre_clave`, lanza `NombreDeAreaDuplicado`) y `renombrarArea(int $id, string $nombre, ?int $actorId): array` (lanza `AreaNoEncontrada` o `NombreDeAreaDuplicado`; si el nombre cambia, en la misma transacción actualiza `nombre` y `nombre_clave` e inserta el historial `AreaFormativa`/`Renombrado`; sin cambio real no escribe)
- [X] T069 [US4] Crear los casos de uso `CrearArea` y `RenombrarArea` en `Application/UseCases/`, `Presentation/Requests/GuardarAreaRequest.php` (`nombre` obligatorio, recortado, ≤ 60) y registrar `POST /areas` y `PATCH /areas/{areaId}` (`auth:sanctum` + `admin`, `whereNumber`) con los métodos en `AreaFormativaController`; mapear `NombreDeAreaDuplicado` → 422 en el campo `nombre` y `AreaNoEncontrada` → 404
- [X] T070 [P] [US4] Ampliar `api.ts` con `crearArea` y `renombrarArea`, crear `components/AreaFormativaSelect.tsx` (selector del área en el panel con opción "Sin área formativa", reutiliza `SelectField`) y `components/GestionAreasDialog.tsx` (diálogo para crear y renombrar áreas con contador `n/60`, error por campo y confirmación de éxito; invalida `["catalogo","areas"]` y `["catalogo","lista"]`)
- [X] T071 [US4] Integrar el selector en `DetalleCompetenciaPanel.tsx` (el cambio de área se guarda con **Guardar**) y el botón "Gestionar áreas" en `CatalogoAdminPage.tsx`; crear `AreaFormativaSelect.dom.test.tsx` y `GestionAreasDialog.dom.test.tsx` (crear, duplicado, renombrar, validación por campo)

**Checkpoint**: La agrupación por área funciona y es administrable.

---

## Phase 7: User Story 5 - Recomendados para cerrar mis brechas (Priority: P2)

**Goal**: El colaborador ve primero las competencias con brecha activa que tienen oferta.

**Independent Test**: Con brechas activas en dos competencias (una con oferta y otra sin ella), solo aparece la primera; sin brechas o sin DNF la sección no se muestra.

### Tests for User Story 5

- [X] T072 [P] [US5] Crear `backend/tests/Feature/Desarrollo/RecomendadosTest.php` con `BrechasActivasFalsas` y `NivelesDeColaboradorFalso`: solo brechas de competencias activas con ≥ 1 recurso disponible; una con solo recursos archivados no aparece; sin brechas → `data` vacío; el puerto lanza excepción → 200 vacío; dos usuarios ven cada uno solo lo suyo; el admin recibe lista vacía; sin token → 401; con el adaptador `SinDatos` real → vacío

### Implementation for User Story 5

- [X] T073 [US5] Crear el caso de uso `ListarRecomendados` (`Application/UseCases/ListarRecomendados.php`) y `RecomendadoDTO`: `brechasDe(usuario autenticado)`, intersecta con competencias activas con recursos disponibles (`getCompetenciasPorIds`), añade área, nivel actual, nivel requerido y contadores; ante fallo del puerto registra sin datos personales y devuelve vacío; `RecomendadoResource` y `GET /recomendados` (`auth:sanctum`, antes de `/{competenciaId}`)
- [X] T074 [P] [US5] Ampliar `api.ts` con `listarRecomendados`, crear `hooks/useRecomendados.ts` y `components/Recomendados.tsx` (sección destacada al inicio con tarjeta por competencia: nombre, tipo, nivel actual y requerido, "X cursos disponibles" que abre el detalle; **no se renderiza** si la lista está vacía o la petición falla) con prueba `Recomendados.dom.test.tsx`; integrarla arriba de las tarjetas en `CatalogoColaboradorPage.tsx`

**Checkpoint**: Los recomendados funcionan hoy con "sin dato" y se activarán al conectar la DNF.

---

## Phase 8: User Story 6 - Auditoría de la configuración formativa (Priority: P3)

**Goal**: El administrador ve quién configuró por última vez y el historial de cambios.

**Independent Test**: Asociar, quitar y cambiar el área y renombrar un área; abrir el detalle y verificar la última configuración y las entradas.

### Tests for User Story 6

- [X] T075 [P] [US6] Crear `backend/tests/Feature/Desarrollo/HistorialTest.php`: entradas de asociaciones, desasociaciones, cambio de área y renombrado de un área que la competencia tuvo asignada, con usuario (nombre), fecha y valores; orden más reciente primero; paginación; un guardado sin cambios no agrega entradas; competencia inexistente → 404; user → 403; sin token → 401; **no existen** rutas de edición ni borrado del historial; `ultima_configuracion` del detalle (usuario y fecha) se actualiza tras guardar

### Implementation for User Story 6

- [X] T076 [US6] Ampliar el repositorio con `historialDe(int $competenciaId, int $pagina, int $porPagina)` (cambios de la competencia más los renombrados de las áreas que tuvo asignadas según el historial `AreaFormativa`/`Cambiado` y su área actual), crear `ListarHistorial` (nombres de usuario vía `NombresDeUsuarioInterface`), `HistorialDTO`, `ListarHistorialRequest` (`page`, `per_page` 1–50 por defecto 10), `HistorialResource` y `GET /{competenciaId}/historial` (`auth:sanctum` + `admin`, `whereNumber`)
- [X] T077 [P] [US6] Ampliar `api.ts` con `listarHistorial`, crear `hooks/useHistorial.ts` y `components/HistorialLista.tsx` (usuario, fecha, acción y recurso o valores anterior y nuevo; `Pagination`; estado vacío) con prueba `HistorialLista.dom.test.tsx`; mostrar en `DetalleCompetenciaPanel.tsx` la "Última configuración: usuario y fecha" y la sección de historial (solo admin)

**Checkpoint**: Todas las historias son funcionales.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Consulta para la DNF, E2E, accesibilidad, coherencia visual y verificación final

- [X] T078 [P] Crear `backend/app/Modules/Desarrollo/Application/Services/DesarrolloService.php`, `Presentation/Facades/DesarrolloFacade.php` y su binding (`desarrollo-facade`) en `DesarrolloServiceProvider.php`, con `getOfertaPorCompetencia(int $competenciaId): array` y `competenciaTieneOferta(int $competenciaId): bool` según contracts/desarrollo-puertos.md (solo recursos `Disponible`; solo arreglos); prueba `backend/tests/Feature/Desarrollo/DesarrolloFacadeTest.php`
- [X] T079 [P] Crear `frontend/e2e/catalogo-formativo.spec.ts` (Playwright, datos de `CatalogoFormativoSeeder` en `backend/.env.e2e`): administrador abre Formación, ve la cobertura, abre el detalle de una competencia sin cursos, asocia dos, guarda, ve las cantidades y el historial, quita uno con confirmación y restablece los datos; colaborador ve las tarjetas por área, abre un detalle, no ve controles de gestión, y los botones sin destino aparecen deshabilitados con "Próximamente"; las pruebas son seriales y restablecen lo que cambian
- [X] T080 [P] Revisión de accesibilidad: el panel lateral y los diálogos atrapan el foco y se cierran con Escape, el indicador de cobertura expone `progressbar`, las acciones de fila incluyen el nombre de la competencia en `aria-label`, los estados se anuncian con `role="status"`/`role="alert"`, contraste en tema claro y oscuro, sin scroll horizontal entre 320 y 1440 px; corregir lo que falle
- [X] T081 [P] Revisión de coherencia visual contra `design/CatalogoAdmin.dc.html`, `design/CatalogoColaborador.dc.html`, `design/Puestos.dc.html` y `design/DESIGN.md`: mismo shell, tokens, controles de 44 px, tabla, paginación y estados que Competencias y Puestos; confirmar que las diferencias documentadas en `DESIGN.md` §9 son las únicas; ajustar `DESIGN.md` primero si hace falta una desviación (constitución III)
- [X] T082 Ejecutar `quickstart.md` completo (los 10 escenarios) con `php artisan migrate:fresh --seed` y anotar cualquier discrepancia; corregir o documentarla en plan.md
- [X] T083 Puertas de calidad: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `npx playwright test` en `frontend/` deben pasar; confirmar que no se añadieron dependencias y que `Desarrollo` no importa clases ni tablas de otros módulos (búsqueda de `App\Modules\Estructura` fuera de `EstructuraFacade` en `backend/app/Modules/Desarrollo`)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias; T002 antes de cualquier tarea de frontend.
- **Foundational (Phase 2)**: depende de Setup; **bloquea todas las historias**.
- **US1 (P1)** y **US2 (P1)**: dependen solo de Foundational; US1 es el MVP. US2 reutiliza `ListarCatalogo` y `CatalogoPage` de US1.
- **US3 (P1)**: depende de Foundational y se prueba mejor sobre US2 (necesita la matriz para abrir el panel).
- **US4 (P2)**: depende de US3 (el selector de área vive en el panel y se guarda con el `PUT`).
- **US5 (P2)**: depende de US1 (reutiliza tarjetas y detalle).
- **US6 (P3)**: depende de US3 (el historial lo escribe el guardado) y de US4 para los renombrados.
- **Polish**: depende de las historias deseadas.

### Within Each User Story

- Pruebas Pest/Vitest primero (deben fallar), luego repositorio → casos de uso → requests/recursos/controlador/rutas → hooks → componentes → páginas.
- Las rutas fijas (`/cobertura`, `/recomendados`, `/recursos-disponibles`, `/areas`) se declaran antes de `/{competenciaId}`.

### Parallel Opportunities

- Fase 2: migraciones T004/T005/T007/T008/T009, enums y value objects T010/T011/T012, pruebas unitarias T015/T017/T019, puertos y adaptadores T023/T024 y todo el bloque de frontend T029–T033 en paralelo.
- US1: pruebas T034–T036 y componentes T044/T045 en paralelo.
- Backend y frontend de una misma historia pueden avanzar en paralelo una vez fijado el contrato (`contracts/catalogo-formativo-api.md`).

---

## Parallel Example: User Story 1

```text
Task: T034 ListarCatalogoColaboradorTest.php
Task: T035 DetalleFormativoColaboradorTest.php
Task: T036 AreasListadoTest.php
Task: T044 NivelPuntos.tsx y RecursoItem.tsx
Task: T045 TarjetaCompetencia.tsx y GrupoPorArea.tsx
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1 y Phase 2 (incluida la siembra).
2. Phase 3 (US1): el colaborador consulta el catálogo.
3. **Detener y validar** con el escenario 7 del quickstart.

### Incremental Delivery

1. Fundación → US1 (consulta del colaborador) → US2 (matriz y cobertura) → US3 (asociar y guardar): el núcleo de valor.
2. US4 (áreas) y US5 (recomendados) mejoran la organización y la personalización.
3. US6 (auditoría) y Polish cierran la feature.

### Notas

- Cuando existan Gestión de Personal y DNF, solo cambian los adaptadores de T024 y el binding de T026; ningún caso de uso ni pantalla se modifica.
- Cuando existan las features de cursos y micro-contenidos, reemplazan la siembra de T027 y rellenan `url_destino`.
- Marcar cada tarea con `[X]` al completarla y confirmar que las pruebas relacionadas pasan.
