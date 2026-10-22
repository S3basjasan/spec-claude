---

description: "Lista de tareas de la feature 019: diccionario de competencias unificado"
---

# Tasks: Diccionario de competencias unificado

**Input**: Documentos de diseño de `/specs/019-diccionario-competencias-unificado/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/ (`competencias-api.md`, `estructura-facade.md`), quickstart.md

**Tests**: Incluidos. La constitución exige Pest en backend, Vitest + Testing Library en frontend y un E2E del flujo principal; además, "toda modificación de comportamiento MUST tener cobertura automatizada". Dentro de cada historia, las pruebas se escriben primero y deben fallar antes de implementar.

**Organization**: Las tareas se agrupan por historia de usuario. Esta feature **refactoriza** las funcionalidades 001, 002 y 003 ya implementadas, por eso la fase fundacional (esquema, modelos y retirada del código antiguo de comportamientos y preguntas) es grande y bloquea las historias.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: historia de la spec a la que pertenece (US1 a US6)
- Todas las rutas son relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Estructura/`, `backend/database/`, `backend/tests/`
- Frontend: `frontend/src/modules/estructura/competencias/`, `frontend/e2e/`
- Diseño: `design/` (ya actualizado: `design/Diccionario.dc.html`, `design/DESIGN.md`)
- Abreviatura **MOD** = `backend/app/Modules/Estructura`; **FE** = `frontend/src/modules/estructura/competencias`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Línea base antes de tocar el código

- [X] T001 Ejecutar la suite completa actual (`cd backend && ./vendor/bin/pest`, `cd frontend && npm run test`) y anotar en un comentario del PR qué pruebas ya fallaban, para distinguir regresiones de la refactorización
- [X] T002 [P] Verificar que existen `design/Diccionario.dc.html`, la sección "Listado de tarjetas con pestañas" de `design/DESIGN.md` y la entrada de `design/canvas.json`; son la referencia visual de todas las tareas de frontend (no hay que crearlos)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Esquema, modelos, dominio, retirada del código antiguo y contrato frontend base. Ninguna historia puede empezar hasta terminar esta fase.

**⚠️ CRITICAL**: Las migraciones eliminan las tablas "registro", por lo que el código antiguo de comportamientos y preguntas se retira en esta misma fase.

### Migraciones (en este orden; fechas `2026_10_23_00000N_*`; usar transacción donde SQLite lo permita)

- [X] T003 Crear `backend/database/migrations/2026_10_23_000001_create_tipo_grados_tables.php`: tablas `tipo_grados` (`nombre` texto máx. 30, único; `activo` booleano por defecto falso; índice único parcial `WHERE activo = 1` con `DB::statement`) y `tipo_grado_valores` (`tipo_grado_id` FK, `valor` texto máx. 20, `orden` entero 1–4 donde 1 = grado más alto; únicos `(tipo_grado_id, valor)` y `(tipo_grado_id, orden)`); sembrar en la propia migración Letras (A, B, C, D; activa), Números (Nivel 1 a Nivel 4) y Categorías (Excelente, Muy buena, Buena, Normal)
- [X] T004 Crear `backend/database/migrations/2026_10_23_000002_alter_competencia_grados_to_escala.php`: añadir `tipo_grado_valor_id` (FK a `tipo_grado_valores`, sin cascada) más `created_by`, `updated_by` (enteros anulables sin FK) y marcas de tiempo; rellenar con el valor de Letras del mismo `grado` (A→orden 1 … D→orden 4); hacerlo obligatorio; eliminar `grado` y su único `(competencia_id, grado)`; crear el único `(competencia_id, tipo_grado_valor_id)`; `descripcion` sigue siendo obligatoria, máx. 500
- [X] T005 [P] Crear `backend/database/migrations/2026_10_23_000003_create_competencia_categorias_table.php`: `competencia_id` FK a `competencias` (sin cascada), `orden` entero 1–4, `nombre` texto máx. 20, `nombre_clave` texto máx. 20 (nombre recortado, en minúsculas y sin tildes), `created_by`/`updated_by` anulables y marcas de tiempo; únicos `(competencia_id, orden)` y `(competencia_id, nombre_clave)`; cuatro filas o ninguna por competencia
- [X] T006 Crear `backend/database/migrations/2026_10_23_000004_fold_registros_into_competencia.php`: en `competencia_preguntas` añadir `competencia_id` (FK sin cascada, rellenada desde `competencia_pregunta_registros` por `registro_id`), eliminar `registro_id` y su único, crear el único `(competencia_id, orden)` (`orden` 1–4, `pregunta` máx. 500); eliminar `competencia_pregunta_registros` y `competencia_comportamiento_registros` (los comportamientos ya cuelgan de `competencia_grado_id`, único `(competencia_grado_id, orden)`, `descripcion` máx. 300)
- [X] T007 Crear `backend/database/migrations/2026_10_23_000005_alter_puesto_competencias_to_grado_id.php`: añadir `competencia_grado_id` (FK a `competencia_grados`, sin cascada), rellenarla haciendo coincidir `(competencia_id, grado_requerido)` con el grado de esa competencia por posición (A→1 … D→4), hacerla obligatoria y eliminar `grado_requerido`; conservar el único `(puesto_id, competencia_id)`
- [X] T008 [P] Crear `backend/database/migrations/2026_10_23_000006_create_competencia_escala_historial_table.php`: `competencia_id` FK sin cascada, `escala_anterior` y `escala_nueva` texto máx. 30, `user_id` entero anulable, solo `created_at` (sin `updated_at`; solo inserción)
- [X] T009 Crear `backend/database/migrations/2026_10_23_000007_deactivate_incomplete_competencias.php`: para cada competencia `Activo` que no esté completa (4 grados, 16 comportamientos con texto y 4 preguntas con texto), poner `estado` = `Inactivo`, sumar 1 a `version` e insertar una fila en `competencia_estado_historial` (`estado_anterior` = `Activo`, `estado_nuevo` = `Inactivo`, `user_id` nulo)
- [X] T010 Escribir `backend/tests/Feature/Estructura/Competencias/MigracionUnificacionTest.php` (Pest): partiendo de una base en el esquema anterior con competencias completas, incompletas y puestos con competencias asignadas, verificar que tras migrar las completas siguen activas e intactas, las incompletas quedan `Inactivo` con historial y `version` + 1, `puesto_competencias.competencia_grado_id` apunta al grado correcto por posición, no se pierde ninguna asignación y las tablas `competencia_pregunta_registros` y `competencia_comportamiento_registros` ya no existen. Técnica: ejecutar solo las migraciones anteriores a `2026_10_23` con `Artisan::call('migrate', ['--path' => …])` en una base temporal, insertar los datos de partida y después ejecutar las migraciones nuevas (depende de T003 a T009)

### Modelos y dominio

- [X] T011 [P] Crear `MOD/Infrastructure/Persistence/Eloquent/Models/TipoGradoModel.php` y `TipoGradoValorModel.php` (relación `valores` ordenada por `orden`; método para obtener el tipo activo)
- [X] T012 [P] Crear `MOD/Infrastructure/Persistence/Eloquent/Models/CategoriaModel.php` (`competencia_categorias`; método estático para calcular `nombre_clave` recortado, en minúsculas y sin tildes) y `MOD/Infrastructure/Persistence/Eloquent/Models/CompetenciaEscalaHistorialModel.php` (solo inserción, sin `updated_at`)
- [X] T013 [P] Actualizar `MOD/Infrastructure/Persistence/Eloquent/Models/CompetenciaGradoModel.php`: relación `valor` (`tipo_grado_valor_id`), `comportamientos` ordenados por `orden`, auditoría de la fila; quitar el uso de la columna `grado`
- [X] T014 [P] Actualizar `MOD/Infrastructure/Persistence/Eloquent/Models/ComportamientoModel.php` (relación con `CompetenciaGradoModel`, sin referencia a registros) y `PreguntaModel.php` (`competencia_id` en lugar de `registro_id`)
- [X] T015 Actualizar `MOD/Infrastructure/Persistence/Eloquent/Models/CompetenciaModel.php`: relaciones `grados` (ordenados por el `orden` de su valor), `preguntas`, `categorias`, `historialEscala`; atributo `version`; método que calcula si la competencia está completa y qué le falta (`grados`, `comportamientos`, `preguntas`) (depende de T011 a T014)
- [X] T016 [P] Actualizar `MOD/Infrastructure/Persistence/Eloquent/Models/PuestoCompetenciaModel.php`: relación `grado` (`competencia_grado_id`) y accesor `grado_requerido` que devuelve la clave posicional A–D (orden 1–4)
- [X] T017 [P] Crear `MOD/Domain/ValueObjects/EscalaDeGrados.php` (id, nombre, valores por orden) y `MOD/Domain/ValueObjects/GradoDeCompetencia.php` (id, orden, clave A–D, etiqueta, descripción, comportamientos); etiqueta: en Letras `"Grado " + valor`, en Números el valor, en Categorías el nombre propio del mismo `orden` si existe o el valor por defecto
- [X] T018 [P] Crear `MOD/Domain/Services/ReglasDeCompletitud.php`: una competencia está completa si tiene exactamente 4 grados con descripción, 16 comportamientos (4 por grado) y 4 preguntas, todos con texto no vacío (un texto con solo espacios cuenta como vacío); devuelve la lista de faltantes
- [X] T019 [P] Crear `MOD/Domain/Services/ReglasDeCategorias.php`: exactamente 4 nombres, cada uno obligatorio de hasta 20 caracteres, sin vacíos y sin repetirse sin distinguir mayúsculas, tildes ni espacios
- [X] T020 Actualizar `MOD/Domain/Entities/Competencia.php` y `MOD/Domain/ValueObjects/CompetenciaConGrados.php` para que el agregado incluya escala, grados con comportamientos, preguntas, categorías, `version` y `completa`/`faltantes` (depende de T017 a T019)
- [X] T021 [P] Crear las excepciones `MOD/Domain/Exceptions/CompetenciaIncompleta.php` (mensaje "No se puede activar: faltan …" con lo que falte) y `MOD/Domain/Exceptions/EscalaSinCambio.php`; reutilizar `ConflictoDeVersion`, `EstadoSinCambio`, `NombreDuplicado` y `CompetenciaNoEncontrada`
- [X] T022 [P] Crear `MOD/Domain/Repositories/EscalaRepositoryInterface.php` y `MOD/Infrastructure/Persistence/Eloquent/Repositories/EloquentEscalaRepository.php` (listar escalas con sus 4 valores ordenados y la marca del tipo activo; buscar por id)
- [X] T023 Actualizar `MOD/Domain/Repositories/CompetenciaRepositoryInterface.php` y `MOD/Infrastructure/Persistence/Eloquent/Repositories/EloquentCompetenciaRepository.php`: cargar el agregado completo (grados con comportamientos, preguntas, categorías) con `with` para evitar N+1; método interno que incrementa `version` con `UPDATE competencias SET version = version + 1 WHERE id = ? AND version = ?` dentro de la transacción y lanza `ConflictoDeVersion` si afecta 0 filas; sin `DELETE` ni cascadas (depende de T015, T020, T021)
- [X] T024 Registrar en `MOD/Infrastructure/Providers/EstructuraServiceProvider.php` el binding de `EscalaRepositoryInterface` y quitar los de `ComportamientoRepositoryInterface` y `PreguntaRepositoryInterface` (después de T031)

### Retirada del código antiguo de comportamientos y preguntas (backend)

- [X] T025 Eliminar controladores y rutas antiguas: borrar `MOD/Presentation/Controllers/ComportamientoController.php` y `PreguntaController.php`, y los grupos `/api/comportamientos` y `/api/preguntas` de `MOD/Routes/api.php` (dejar solo `/api/competencias`; sin `DELETE`)
- [X] T026 [P] Eliminar casos de uso y DTO obsoletos en `MOD/Application/`: `UseCases/CrearComportamientos.php`, `ListarComportamientos.php`, `ObtenerComportamientos.php`, `CrearPreguntas.php`, `ListarPreguntas.php`, `ObtenerPreguntas.php`, `ListarCompetenciasDisponibles.php`, `ListarCompetenciasDisponiblesPreguntas.php`, `DTO/FiltroComportamientosDTO.php`, `FiltroPreguntasDTO.php`, `ListadoComportamientosDTO.php`, `ListadoPreguntasDTO.php`, `RegistroComportamientosDTO.php`, `RegistroPreguntasDTO.php`, `CompetenciaDisponibleDTO.php` (dejar `ActualizarComportamientos.php` y `ActualizarPreguntas.php` como stubs que lancen `LogicException` hasta T090)
- [X] T027 [P] Eliminar dominio, persistencia y presentación obsoletos: `MOD/Domain/Entities/RegistroComportamientos.php` y `RegistroPreguntas.php`; `MOD/Domain/Repositories/ComportamientoRepositoryInterface.php` y `PreguntaRepositoryInterface.php`; `MOD/Domain/ValueObjects/PaginaRegistrosComportamientos.php` y `PaginaRegistrosPreguntas.php`; excepciones `CompetenciaYaTienePreguntas`, `CompetenciaYaTieneRegistro`, `PreguntasNoEncontradas`, `RegistroModificado`, `RegistroNoEncontrado` (comprobar con `grep` que nada más las usa); `MOD/Infrastructure/Persistence/Eloquent/Models/RegistroComportamientosModel.php` y `RegistroPreguntasModel.php`; `Repositories/EloquentComportamientoRepository.php` y `EloquentPreguntaRepository.php`; `MOD/Presentation/Requests/ListarComportamientosRequest.php` y `ListarPreguntasRequest.php`; `MOD/Presentation/Resources/RegistroComportamientos*Resource.php`, `RegistroPreguntas*Resource.php` y `CompetenciaDisponibleResource.php` (después de T031)
- [X] T028 [P] Eliminar `backend/database/factories/RegistroComportamientosFactory.php`, `RegistroPreguntasFactory.php`, `backend/database/seeders/ComportamientosSeeder.php` y `PreguntasSeeder.php`, y las llamadas a esos seeders en `backend/database/seeders/DatabaseSeeder.php`
- [X] T029 [P] Eliminar las pruebas antiguas `backend/tests/Feature/Estructura/Comportamientos/`, `backend/tests/Feature/Estructura/Preguntas/`, `backend/tests/Unit/Estructura/Comportamientos/` y `backend/tests/Unit/Estructura/Preguntas/` (su cobertura se reemplaza en las fases de cada historia)

### Compatibilidad con otros módulos

- [X] T030 Adaptar puestos a `competencia_grado_id` conservando el contrato externo (`grado_requerido` = clave A–D por posición, más `grado_etiqueta`): `MOD/Infrastructure/Persistence/Eloquent/Repositories/EloquentPuestoRepository.php`, `MOD/Domain/Services/ReglasDeCompetenciasDePuesto.php`, `MOD/Application/DTO/PuestoDTO.php`, `MOD/Presentation/Requests/GuardarPuestoRequest.php` y `MOD/Infrastructure/Persistence/Eloquent/Models/PuestoCompetenciaModel.php`; validar que la competencia nueva esté activa y completa y que el grado pertenezca a ella (depende de T016, T020)
- [X] T031 Actualizar `MOD/Application/Services/EstructuraService.php` (hacer antes de T024 y T027; usada por `EstructuraFacade`) conservando todas las firmas de `contracts/estructura-facade.md`: `grados` sigue siendo el mapa A–D por posición; añadir `escala`, `grados_detalle` y `completa`; `getComportamientos*`, `getPreguntas*`, `tieneComportamientosActivos` y `tienePreguntasActivas` se derivan de la competencia (las dos últimas exigen activa **y** completa); actualizar también el constructor y la inyección de dependencias del servicio (depende de T020, T023)
- [X] T032 [P] Verificar y ajustar `backend/app/Modules/Desarrollo/Infrastructure/Adapters/PerfilesDePuestoDesdeEstructura.php` (sigue leyendo `grado_requerido` como `nivel_requerido`) y los adaptadores `CompetenciasDesdeEstructura.php` de Compensaciones y Desarrollo y `EstructuraFacadeLector.php` de GestionPersonal, sin cambiar su contrato
- [X] T033 [P] Actualizar los tests de puestos que usan `grado_requerido` o `competencia_grados.grado` (`backend/tests/Feature/Estructura/Puestos/*.php`, `backend/tests/Unit/Estructura/Puestos/ReglasDeCompetenciasDePuestoTest.php`, `backend/tests/Pest.php`, `backend/database/factories/PuestoFactory.php`, `backend/database/seeders/PuestosSeeder.php`) para el nuevo esquema, manteniendo las mismas aserciones del contrato

### Semillas y fábricas

- [X] T034 [P] Crear `backend/database/seeders/TiposGradoSeeder.php` (idempotente: no hace nada si las tres escalas, con Letras activa, ya fueron sembradas por la migración T003) y llamarlo desde `backend/database/seeders/DatabaseSeeder.php` antes de `CompetenciasSeeder`
- [X] T035 Reescribir `backend/database/seeders/CompetenciasSeeder.php`: siete competencias completas tomadas de `design/Diccionario.dc.html` (COMP-0001 Trabajo en equipo Cardinal Letras; COMP-0002 Orientación a resultados Cardinal Números; COMP-0003 Liderazgo Específica Letras; COMP-0004 Análisis de datos Específica Letras; COMP-0005 Comunicación efectiva Cardinal Categorías con nombres propios; COMP-0006 Gestión de proyectos Específica Letras; COMP-0007 Gestión del tiempo Específica Letras Inactivo), cada una con 4 grados, 16 comportamientos y 4 preguntas; pasar por el repositorio para código y auditoría; autor `admin@hrms.test` si existe (depende de T023, T034)
- [X] T036 [P] Reescribir `backend/database/factories/CompetenciaFactory.php` para crear competencias completas (4 grados con escala, 16 comportamientos, 4 preguntas) con estados para incompleta, inactiva y escala Categorías con nombres propios

### Pruebas unitarias del dominio

- [X] T037 [P] Escribir `backend/tests/Unit/Estructura/Competencias/ReglasDeCompletitudTest.php`: completa, falta un comportamiento, falta una pregunta, texto solo con espacios cuenta como vacío
- [X] T038 [P] Escribir `backend/tests/Unit/Estructura/Competencias/ReglasDeCategoriasTest.php`: cuatro nombres válidos; vacío; 21 caracteres; "Buena" y " búena " repetidos; "Maestría" y "Maestria" cuentan como repetidos (FR-007 ignora tildes)
- [X] T039 [P] Escribir `backend/tests/Unit/Estructura/Competencias/EtiquetaDeGradoTest.php`: etiquetas en Letras ("Grado A"), Números ("Nivel 1"), Categorías con nombres propios y con valores por defecto

### Contrato y utilidades base del frontend

- [X] T040 [P] Reescribir `FE/api.ts` con los tipos y funciones del cliente HTTP compartido para todos los endpoints de `contracts/competencias-api.md` (listar, obtener, crear, actualizar competencia, actualizar comportamientos, actualizar preguntas, aplicar escala, cambiar estado, listar escalas); tipos estrictos, sin `any`; el detalle incluye `version`, `completa`, `faltantes`, `escala`, `categorias`, `grados` con `comportamientos`, `preguntas` y `auditoria`
- [X] T041 [P] Reescribir `FE/text.ts` con todos los textos visibles en español: título "Competencias", subtítulo "Competencias con sus comportamientos y preguntas.", búsqueda "Buscar por nombre", "Todos los tipos", "Limpiar filtros", "Ninguna competencia coincide con los filtros.", recuento "N competencias" / "1 competencia", pestañas, "Ver con escala:", "Aplicar esta escala", "Descartar", el botón «Editar grados de desarrollo» y la ventana «Editar competencia» (mismos nombres que FR-027), los siete avisos de la sección 4.5 del specify, el diálogo de desactivación, el aviso de contenido incompleto, el aviso de conflicto "Esta competencia cambió mientras la editabas. Recarga para ver la versión actual." y todos los mensajes de validación de `contracts/competencias-api.md`
- [X] T042 [P] Reescribir `FE/validation.ts` con esquemas Zod que replican el backend: nombre (1–50), descripción (1–500), tipo, escala, 4 grados (1–500 cada uno), 16 comportamientos (1–300), 4 preguntas (1–500) y, con Categorías, 4 nombres (1–20, sin repetir sin distinguir mayúsculas, tildes ni espacios); un texto solo con espacios cuenta como vacío; mapeo de errores 422 por ruta (`grados.2`, `comportamientos.1.3`, `categorias.0`, `preguntas.3`, `nombre`) a campos
- [X] T043 [P] Reescribir `FE/validation.test.ts` (Vitest) con los casos límite de T042: nombre de 50 y 51 caracteres, comportamiento de 300 y 301, categoría de 20 y 21, nombres de categoría repetidos, textos solo con espacios
- [X] T044 [P] Crear `FE/hooks/useEscalas.ts` (TanStack Query sobre `GET /api/tipo-grados`, caché larga porque es un catálogo) y `FE/hooks/useCompetencia.ts` (detalle por id)
- [X] T045 [P] Actualizar `frontend/src/test/competenciasFixtures.ts` con competencias completas, incompleta, inactiva y escalas (Letras, Números, Categorías con y sin nombres propios)

**Checkpoint**: esquema migrado, dominio y repositorios listos, código antiguo retirado, puestos y Facade compatibles, contrato frontend base creado. Las historias pueden empezar.

---

## Phase 3: User Story 1 - Consultar el diccionario y el detalle de una competencia (Priority: P1) 🎯 MVP

**Goal**: Cualquier usuario autenticado ve las tarjetas (código, nombre, tipo), busca, filtra y despliega el detalle con las pestañas Competencia, Comportamientos y Preguntas; el administrador ve activas e inactivas, el resto solo activas.

**Independent Test**: Con competencias activas e inactivas, iniciar sesión como administrador y como usuario, buscar, filtrar por tipo, desplegar una tarjeta y recorrer las tres pestañas; comprobar qué ve cada rol (escenarios 1 a 10 del quickstart, partes de consulta).

### Tests for User Story 1 ⚠️ (escribir primero; deben fallar)

- [X] T046 [P] [US1] Escribir `backend/tests/Feature/Estructura/Competencias/ConsultarCompetenciasTest.php` (Pest): listado ordenado por código; administrador sin `estado` recibe activas e inactivas; usuario recibe siempre `Activo` aunque envíe `estado=Inactivo`; filtros `q` (sin tildes ni mayúsculas) y `tipo`; `per_page` por defecto 12 y entre 1 y 50; cada elemento incluye `id`, `codigo`, `nombre`, `tipo`, `estado`, `completa`; `meta.total`; detalle incluye escala, grados ordenados con 4 comportamientos, preguntas, `categorias`, `version` y `auditoria`; detalle de inactiva permitido; el usuario sin rol de administrador no recibe la clave `auditoria`; 404 si no existe; 401 sin sesión
- [X] T047 [P] [US1] Escribir `backend/tests/Feature/Estructura/Competencias/EscalasTest.php`: `GET /api/tipo-grados` devuelve las tres escalas con 4 valores ordenados y la marca del activo, para cualquier usuario autenticado
- [X] T048 [P] [US1] Escribir `backend/tests/Feature/Estructura/Competencias/SinBorradoTest.php` (reemplazar el existente): no existe `DELETE /api/competencias/{id}` y las rutas `/api/comportamientos` y `/api/preguntas` responden 404
- [X] T049 [P] [US1] Escribir `backend/tests/Unit/Estructura/Competencias/ConsultarCompetenciasTest.php` (reemplazar el existente) para los casos de uso de listar y obtener con repositorio en memoria (`RepositorioEnMemoria.php`)
- [X] T050 [P] [US1] Escribir `FE/pages/CompetenciasPage.dom.test.tsx` (Vitest + Testing Library): tarjetas con código, nombre y tipo; nombre atenuado si está inactiva; búsqueda que ignora tildes; botón "Limpiar filtros" solo con filtro aplicado; recuento; lista vacía; estados cargando y error con "Reintentar"; usuario sin rol de administrador sin acciones de gestión; tarjeta abierta con pestañas y navegación con flechas, Inicio y Fin
- [X] T051 [P] [US1] Escribir `FE/components/PestanasCompetencia.dom.test.tsx`: pestaña inicial Competencia con descripción y grados con la etiqueta de la escala, Comportamientos con 4 por grado como viñetas, Preguntas numeradas 1 a 4; varias tarjetas abiertas a la vez con su propia pestaña activa
- [X] T052 [P] [US1] Crear la parte de consulta de `frontend/e2e/competencias.spec.ts` (Playwright, reemplazar el existente): como administrador y como usuario, abrir Competencias, buscar, filtrar, desplegar y recorrer las tres pestañas

### Implementation for User Story 1

- [X] T053 [P] [US1] Actualizar `MOD/Presentation/Requests/ListarCompetenciasRequest.php`: `q`, `tipo` (`Cardinal`|`Especifica`), `estado` (`Activo`|`Inactivo`|`Todos`, opcional), `page` (mínimo 1) y `per_page` entre 1 y 50 con valor por defecto 12
- [X] T054 [P] [US1] Actualizar `MOD/Application/DTO/CompetenciaDTO.php`, `ListadoCompetenciasDTO.php` y `FiltroCompetenciasDTO.php` para el detalle completo (escala, categorías, grados con comportamientos, preguntas, `version`, `completa`, `faltantes`, auditoría con nombre de usuarios solo para el administrador, obtenidos por el puerto `NombresDeUsuariosInterface` de `MOD/Application/Ports`, como en `UnidadDetalleDTO` y `PosicionDetalleDTO`, sin consultar la tabla de usuarios) y el elemento de lista con `completa`
- [X] T055 [US1] Actualizar `MOD/Application/UseCases/ListarCompetencias.php`: sin estado por defecto para el administrador, `Activo` forzado para los demás, filtros `q` (normalizado igual que `nombre_clave`) y `tipo`, orden por `codigo`, total de resultados (depende de T053, T054)
- [X] T056 [US1] Actualizar `MOD/Application/UseCases/ObtenerCompetencia.php` para devolver el detalle completo aunque la competencia esté inactiva, o `CompetenciaNoEncontrada`
- [X] T057 [P] [US1] Actualizar `MOD/Presentation/Resources/CompetenciaResource.php` (elemento de lista) y `CompetenciaDetalleResource.php` (detalle según `contracts/competencias-api.md`: `grados` ordenados con `orden`, `etiqueta`, `descripcion` y `comportamientos`; `preguntas`; `categorias` o `null`; `escala`; `auditoria`)
- [X] T058 [P] [US1] Crear `MOD/Application/UseCases/ListarEscalas.php` y `MOD/Presentation/Controllers/EscalaController.php` con la ruta `GET /api/tipo-grados` en `MOD/Routes/api.php` (solo `auth:sanctum`)
- [X] T059 [US1] Actualizar `MOD/Presentation/Controllers/CompetenciaController.php` (`index` y `show`, delgados; la capacidad `admin` del token decide la visibilidad) y las rutas `GET /api/competencias` y `GET /api/competencias/{id}` en `MOD/Routes/api.php` (depende de T055 a T057)
- [X] T060 [P] [US1] Crear `FE/hooks/useListadoParams.ts` (reescribir): `q`, `tipo` y `page` en la URL con `useSearchParams`, y `FE/hooks/useCompetencias.ts` con debounce de la búsqueda
- [X] T061 [P] [US1] Crear `FE/components/CompetenciaCard.tsx` según `design/Diccionario.dc.html`: fila de cabecera como botón (`role="button"`, `aria-expanded`, `aria-label` "Ver información adicional de {nombre}" u "Ocultar información adicional de {nombre}", clic/Enter/Espacio salvo sobre botones internos), código (13 px `--muted`), nombre (16 px 600, `--muted` si inactiva), etiqueta de tipo, chevron que gira 180°; sin descripción ni badge de estado; altura mínima 64 px, radio 12 px
- [X] T062 [P] [US1] Crear `FE/components/PestanasCompetencia.tsx` reutilizando `frontend/src/shared/ui/Tabs.tsx` (`role="tablist"`, `aria-label` "Información de {nombre}", pestañas de 44 px, navegación con flechas, Inicio y Fin): Competencia (descripción completa y "Grados de desarrollo · {escala}" con cada grado como píldora y su descripción), Comportamientos (por grado, viñetas) y Preguntas (lista numerada); carga el detalle al desplegar con `useCompetencia`
- [X] T063 [US1] Reescribir `FE/pages/CompetenciasPage.tsx`: encabezado con título y subtítulo, barra de filtros (búsqueda con etiqueta accesible "Buscar competencia", selector de tipo con etiqueta "Tipo", "Limpiar filtros"), lista de una competencia por fila (12 px de separación), paginador `shared/ui/Pagination.tsx` (12 por página), recuento en región `aria-live`, estados cargando (`Skeleton`), vacío (`EmptyState` con "Limpiar filtros") y error (`ErrorState` con "Reintentar") (depende de T060 a T062)
- [X] T064 [US1] Actualizar `FE/nav.tsx` y `frontend/src/App.tsx`: dejar solo la ruta y el ítem de menú Competencias; quitar las importaciones y rutas de `comportamientos` y `preguntas` (`RequireAdminComportamientos`, `comportamientosNav`, `ComportamientosFormPage`, `ComportamientosPage`, y las equivalentes de preguntas) y sus ítems en `navItems`
- [X] T065 [P] [US1] Eliminar los módulos `frontend/src/modules/estructura/comportamientos/` y `frontend/src/modules/estructura/preguntas/`, `frontend/e2e/comportamientos.spec.ts`, `frontend/e2e/preguntas.spec.ts` y los fixtures `frontend/src/test/comportamientosFixtures.ts` y `frontend/src/test/preguntasFixtures.ts`
- [X] T066 [P] [US1] Eliminar los componentes y pruebas obsoletos de `FE`: `components/CompetenciasTable.tsx`, `components/GradosPanel.tsx`, `components/GradosPanel.dom.test.tsx`, `pages/CompetenciaFormPage.tsx`, `pages/CompetenciaFormPage.dom.test.tsx`, `pages/CompetenciaFormPage.edit.dom.test.tsx`, `pages/CompetenciasPage.estado.dom.test.tsx`, `hooks/useGuardarCompetencia.ts` y `hooks/useCambiarEstadoCompetencia.ts` (se recrean en US2, US3 y US4); comprobar con `grep` si algo importa `components/RequireAdmin.tsx` y, si no, eliminarlo

**Checkpoint**: US1 funciona y se prueba sola: el diccionario se consulta de punta a punta con datos sembrados.

---

## Phase 4: User Story 2 - Crear una competencia completa (Priority: P1)

**Goal**: El administrador crea una competencia completa (datos, escala, 4 grados, 16 comportamientos, 4 preguntas) en una sola operación atómica.

**Independent Test**: Como administrador, crear una competencia válida (también con Categorías y nombres propios) y comprobar que aparece con su código, estado Activo y sus tres pestañas completas; comprobar los errores por campo (escenario 5 del quickstart).

### Tests for User Story 2 ⚠️

- [X] T067 [P] [US2] Escribir `backend/tests/Feature/Estructura/Competencias/CrearCompetenciaTest.php` (Pest, reemplazar el existente): 201 con detalle; código consecutivo COMP-####; estado `Activo` y `version` 1; `created_by`; 4 grados con la escala, 16 comportamientos, 4 preguntas y, con Categorías, 4 categorías; atomicidad (si falla un texto no se guarda nada); 422 por cada regla (nombre vacío, 51 caracteres, descripción de 501, tipo, escala, grados incompletos o de 501, comportamientos de 301 o faltantes, preguntas de 501 o faltantes, categorías vacías, de 21 o repetidas); nombre duplicado por `nombre_clave` (" liderazgo ", "Lidérazgo"); 403 para quien no es administrador; 401 sin sesión
- [X] T068 [P] [US2] Escribir `backend/tests/Unit/Estructura/Competencias/CrearCompetenciaTest.php` (reemplazar el existente) con repositorio en memoria
- [X] T069 [P] [US2] Escribir `backend/tests/Feature/Estructura/Competencias/RepositorioCompetenciaTest.php` (reemplazar el existente): generación de código dentro de la transacción, `nombre_clave` único, filas de grado con la escala elegida y sin cascadas, y dos creaciones simultáneas que verifican que el código COMP-#### no se repite
- [X] T070 [P] [US2] Escribir `FE/components/FormularioCompetencia.dom.test.tsx`: orden de campos; rótulos de grados que cambian con la escala; con Categorías aparecen cuatro campos prellenados "Excelente", "Muy buena", "Buena", "Normal"; contadores `n/máximo`; errores solo tras el primer intento y junto al campo; conserva los valores ante error; envío bloqueado mientras guarda; error 422 de nombre duplicado "Ya existe una competencia con este nombre."; Escape y Cancelar cierran sin crear
- [X] T071 [P] [US2] Añadir al E2E `frontend/e2e/competencias.spec.ts` el flujo de creación completa y el rechazo de un nombre duplicado

### Implementation for User Story 2

- [X] T072 [P] [US2] Crear `MOD/Presentation/Requests/GuardarCompetenciaRequest.php` con las reglas de creación según `contracts/competencias-api.md` (nombre ≤ 50, descripción ≤ 500, tipo, `tipo_grado_id` existente, `categorias` obligatorias solo con Categorías, `grados` 4 × ≤ 500, `comportamientos` 4 × 4 × ≤ 300, `preguntas` 4 × ≤ 500; textos recortados y solo espacios = vacío) y los mensajes de validación exactos
- [X] T073 [P] [US2] Actualizar `MOD/Application/DTO/DatosCompetenciaDTO.php` para el cuerpo completo (datos, escala, categorías, grados, comportamientos, preguntas)
- [X] T074 [US2] Reescribir `MOD/Application/UseCases/CrearCompetencia.php`: valida nombre único por `nombre_clave`, aplica `ReglasDeCategorias`, y delega al repositorio la creación atómica (código COMP-####, `Activo`, `version` 1, `created_by`) (depende de T072, T073)
- [X] T075 [US2] Implementar en `MOD/Infrastructure/Persistence/Eloquent/Repositories/EloquentCompetenciaRepository.php` el método `crear` en una transacción: competencia, 4 grados con `tipo_grado_valor_id` por orden, 16 comportamientos, 4 preguntas y categorías (cuando corresponda), con `created_by`; sin historial de escala en la creación
- [X] T076 [US2] Añadir `store` a `MOD/Presentation/Controllers/CompetenciaController.php` y la ruta `POST /api/competencias` (middleware `auth:sanctum` + `admin`) en `MOD/Routes/api.php` (depende de T074, T075)
- [X] T077 [P] [US2] Crear `FE/components/SelectorDeEscala.tsx`: radiogroup con tres tarjetas (mínimo 150 px, 56 px de alto, radio 10 px; Letras "Grado A, B, C, D", Números "Nivel 1, 2, 3, 4", Categorías "Excelente, Muy buena…"; la elegida con borde de 2 px `--accent` y fondo `--soft`) y, con Categorías, cuatro campos de nombre con contador `n/20`
- [X] T078 [P] [US2] Crear `FE/components/AvisoEmergente.tsx`: modal centrado `alertdialog` (`aria-modal`, máximo 400 px, círculo de 48 px con verificación, mensaje de 18 px, botón Aceptar con el foco inicial, se cierra con Aceptar o Escape), reutilizando `shared/ui/Modal.tsx`
- [X] T079 [US2] Crear `FE/hooks/useGuardarCompetencia.ts`: mutación sin reintentos para `POST /api/competencias`, invalida el listado, mapea 422 a campos y devuelve el aviso "Competencia creada correctamente." (depende de T040, T042)
- [X] T080 [US2] Crear `FE/components/FormularioCompetencia.tsx`: modal `dialog` de máximo 720 px (título "Nueva competencia", botón Cerrar, texto de ayuda "Los campos con * son obligatorios. Se guarda como una competencia activa."), campos en el orden Nombre * y Tipo * (2fr/1fr), Descripción *, Escala, cuatro grados rotulados con la escala elegida, 16 comportamientos agrupados por grado ("Comportamiento 1" a "4") y cuatro preguntas ("Pregunta 1" a "4"), con React Hook Form + Zod, contadores `n/máximo` y botones Cancelar y "Guardar competencia"; escala inicial = tipo activo de `useEscalas` (depende de T077, T079)
- [X] T081 [US2] Integrar en `FE/pages/CompetenciasPage.tsx` el botón "Nueva competencia" (fondo `--accent`, ícono de suma, solo administrador) que abre `FormularioCompetencia`, y mostrar `AvisoEmergente` tras crear (depende de T078, T080)

**Checkpoint**: US1 y US2 funcionan: se crea y se consulta el diccionario completo.

---

## Phase 5: User Story 3 - Editar una competencia por secciones (Priority: P2)

**Goal**: El administrador edita por separado la competencia y sus grados, los comportamientos o las preguntas, incluso en competencias inactivas; la edición concurrente se rechaza con aviso de recargar.

**Independent Test**: Editar cada sección y comprobar que solo cambia esa sección, con su aviso y con el código y estado intactos; abrir la edición en dos ventanas y comprobar el rechazo del segundo guardado (escenarios 6 y 7 del quickstart).

### Tests for User Story 3 ⚠️

- [X] T082 [P] [US3] Escribir `backend/tests/Feature/Estructura/Competencias/ActualizarCompetenciaTest.php` (Pest, reemplazar el existente): `PUT /api/competencias/{id}` actualiza nombre, descripción, tipo y 4 grados conservando `codigo`, `estado` y `created_by`; recalcula `nombre_clave`; conservar el nombre propio no es duplicado, pero uno de otra competencia sí (422); 422 si el cuerpo incluye `codigo` o `estado`; categorías reemplazadas cuando llegan y conservadas cuando se omiten; permitido en inactivas; 404; 403; `version` sube en 1
- [X] T083 [P] [US3] Escribir `backend/tests/Feature/Estructura/Competencias/ActualizarSeccionesTest.php`: `PUT …/comportamientos` actualiza las 16 filas por grado y orden sin insertar ni borrar (y completa una competencia incompleta), `PUT …/preguntas` actualiza las 4 filas, ambos conservan `created_by`, suben `version` y `updated_by`, rechazan texto vacío, de 301 (comportamiento) o de 501 (pregunta), y funcionan en inactivas
- [X] T084 [P] [US3] Escribir `backend/tests/Feature/Estructura/Competencias/ConcurrenciaCompetenciaTest.php`: un `PUT` con `version` desfasada responde 409 con el mensaje "Esta competencia cambió mientras la editabas. Recarga para ver la versión actual." y no escribe nada; el desfase también ocurre tras un cambio de estado o de escala; la condición se resuelve en una sola sentencia atómica (dos peticiones con la misma versión: una gana y la otra recibe 409)
- [X] T085 [P] [US3] Escribir `backend/tests/Unit/Estructura/Competencias/ActualizarCompetenciaTest.php` (reemplazar el existente) y `ActualizarSeccionesTest.php` con repositorio en memoria
- [X] T086 [P] [US3] Escribir `FE/components/FormularioSeccion.dom.test.tsx`: tres modales (competencia y grados con Tipo y escala; 16 comportamientos agrupados por grado; 4 preguntas) con datos precargados desde el detalle, "Guardar cambios", errores por campo, pestaña activa conservada tras guardar, y para un 409 el modal permanece abierto con el aviso y un botón para recargar los datos
- [X] T087 [P] [US3] Añadir al E2E `frontend/e2e/competencias.spec.ts` la edición de comportamientos y de preguntas y el conflicto de edición en dos contextos de navegador

### Implementation for User Story 3

- [X] T088 [P] [US3] Crear `MOD/Presentation/Requests/GuardarComportamientosRequest.php` y `GuardarPreguntasRequest.php` (reescritos): `version` obligatoria, `comportamientos` 4 × 4 textos ≤ 300 y `preguntas` 4 textos ≤ 500, con los mensajes de `contracts/competencias-api.md`; y añadir `version` y el rechazo de `codigo`/`estado` a `GuardarCompetenciaRequest.php` para el `PUT`
- [X] T089 [US3] Reescribir `MOD/Application/UseCases/ActualizarCompetencia.php`: exige `version`, valida nombre único en otra competencia, aplica `ReglasDeCategorias`, actualiza datos, grados por orden y categorías, escribe historial de escala si cambia `tipo_grado_id`, sube `version` y `updated_by` (depende de T088)
- [X] T090 [US3] Reescribir `MOD/Application/UseCases/ActualizarComportamientos.php` y `ActualizarPreguntas.php` sobre la competencia: exigen `version`, actualizan las filas existentes (insertan las que falten en una competencia incompleta), conservan `created_by`, suben `version` y `updated_by` de la competencia (depende de T088)
- [X] T091 [US3] Implementar en `EloquentCompetenciaRepository.php` los métodos de actualización de datos y grados, comportamientos y preguntas, usando el incremento atómico de versión de T023 y lanzando `ConflictoDeVersion` ante desfase
- [X] T092 [US3] Añadir `update`, `actualizarComportamientos` y `actualizarPreguntas` a `CompetenciaController.php` (capturando `ConflictoDeVersion` → 409, `NombreDuplicado`/validación → 422) y las rutas `PUT /api/competencias/{id}`, `PUT /api/competencias/{id}/comportamientos` y `PUT /api/competencias/{id}/preguntas` (`auth:sanctum` + `admin`) en `MOD/Routes/api.php` (depende de T089 a T091)
- [X] T093 [US3] Crear `FE/hooks/useGuardarSeccion.ts`: mutación sin reintentos para los tres `PUT`, invalida listado y detalle, conserva la pestaña activa, mapea 422 a campos y 409 a un aviso con acción de recargar (depende de T040, T042)
- [X] T094 [US3] Crear `FE/components/FormularioSeccion.tsx`: tres modales de máximo 640 px ("Editar competencia", "Editar comportamientos", "Editar preguntas", con el nombre de la competencia bajo el título y botones Cancelar y "Guardar cambios"); Editar competencia incluye Nombre, Descripción, Tipo, Escala (`SelectorDeEscala`) y 4 grados, y no muestra editables el código ni el estado; usa los datos de `useCompetencia`, incluida la `version` (depende de T077, T093)
- [X] T095 [US3] Añadir a `FE/components/PestanasCompetencia.tsx` el botón de edición de 44 × 44 px de cada pestaña (solo administrador; `aria-label` "Editar grados de desarrollo", "Editar comportamientos" o "Editar preguntas") que abre `FormularioSeccion`, y mostrar `AvisoEmergente` con "Competencia actualizada correctamente.", "Comportamientos actualizados correctamente." o "Preguntas actualizadas correctamente." (depende de T094)

**Checkpoint**: US1 a US3 funcionan; la edición por secciones y la concurrencia se prueban solas.

---

## Phase 6: User Story 4 - Activar y desactivar una competencia (Priority: P2)

**Goal**: El administrador desactiva (con confirmación) y reactiva competencias; no se puede activar una competencia incompleta.

**Independent Test**: Desactivar una competencia, comprobar que los demás usuarios dejan de verla, reactivarla; intentar activar una competencia heredada incompleta (escenarios 8 y 9 del quickstart).

### Tests for User Story 4 ⚠️

- [X] T096 [P] [US4] Escribir `backend/tests/Feature/Estructura/Competencias/CambiarEstadoCompetenciaTest.php` (Pest, reemplazar el existente): `PATCH …/estado` registra la fila en `competencia_estado_historial`, sube `version` y `updated_by`; 422 si el estado ya es el pedido; activar una competencia incompleta responde 422 "No se puede activar: faltan los comportamientos y las preguntas." (lista exactamente lo que falte) y no escribe nada; completar la sección y activarla funciona; 404; 403; los demás usuarios dejan de ver la competencia desactivada y las asignaciones de puestos se conservan
- [X] T097 [P] [US4] Escribir `backend/tests/Unit/Estructura/Competencias/CambiarEstadoCompetenciaTest.php` (reemplazar el existente) y `HistorialInmutableTest.php` (revisar) con repositorio en memoria
- [X] T098 [P] [US4] Escribir `FE/components/CompetenciaEstado.dom.test.tsx`: Desactivar abre un `alertdialog` con el texto "La competencia, con sus grados, comportamientos y preguntas, no podrá asignarse en nuevos registros." y los botones Cancelar y Desactivar (danger); Cancelar no cambia nada; Activar no pide confirmación; avisos "Competencia desactivada." y "Competencia activada."; `aria-label` "Desactivar {nombre}" / "Activar {nombre}"; con la competencia incompleta aparece el aviso neutro "Contenido incompleto…" y el 422 de activación se muestra al administrador
- [X] T099 [P] [US4] Añadir al E2E `frontend/e2e/competencias.spec.ts` desactivar con confirmación, reactivar, y comprobar que el usuario sin rol de administrador deja de ver la competencia desactivada

### Implementation for User Story 4

- [X] T100 [US4] Actualizar `MOD/Application/UseCases/CambiarEstadoCompetencia.php`: al pasar a `Activo` valida con `ReglasDeCompletitud` y lanza `CompetenciaIncompleta` con lo que falte; escribe el historial de estado y sube `version` sin exigir `version` de entrada; lanza `EstadoSinCambio` si ya está en ese estado
- [X] T101 [US4] Actualizar el método de cambio de estado en `EloquentCompetenciaRepository.php` (transacción: estado, historial, `version` + 1, `updated_by`)
- [X] T102 [US4] Actualizar `cambiarEstado` en `CompetenciaController.php` (422 con el mensaje de completitud) y verificar la ruta `PATCH /api/competencias/{id}/estado` (`auth:sanctum` + `admin`) en `MOD/Routes/api.php` (depende de T100, T101)
- [X] T103 [US4] Crear `FE/hooks/useCambiarEstadoCompetencia.ts` (mutación sin reintentos, invalida listado y detalle, mapea el 422 de completitud) y añadir a `CompetenciaCard.tsx` el IconButton Activar / Desactivar de 44 × 44 px (solo administrador): ícono de apagado en `--danger` en las activas e ícono de verificación en `--accent` en las inactivas, con los `aria-label` de T098
- [X] T104 [US4] Integrar en `FE/pages/CompetenciasPage.tsx` el `ConfirmDialog` de `frontend/src/shared/ui/ConfirmDialog.tsx` (título "¿Desactivar «{nombre}»?"), el `AvisoEmergente` con los dos mensajes y, en `PestanasCompetencia.tsx`, el `Alert` neutro de contenido incompleto (`role="status"`, solo administrador, cuando `completa` es falso)

**Checkpoint**: US1 a US4 funcionan; el ciclo de vida de una competencia queda completo.

---

## Phase 7: User Story 5 - Ver y cambiar la escala de grados (Priority: P2)

**Goal**: Cualquier usuario previsualiza otra escala sin guardar; el administrador la aplica y se registra el cambio.

**Independent Test**: Previsualizar otra escala, descartar y luego aplicar; comprobar que persiste, que las descripciones, comportamientos y asignaciones no cambian y que Categorías conserva los nombres propios (escenario 4 del quickstart).

### Tests for User Story 5 ⚠️

- [X] T105 [P] [US5] Escribir `backend/tests/Feature/Estructura/Competencias/AplicarEscalaTest.php` (Pest): `PATCH …/escala` actualiza `tipo_grado_valor_id` de las mismas filas por orden (mismos ids de grado), conserva descripciones y comportamientos, escribe `competencia_escala_historial` (anterior, nueva, usuario), sube `version`; 422 si ya es esa escala o no existe; 404; 403; los nombres de categoría propios se conservan al ir a otra escala y volver; aplicar Categorías sin nombres propios no crea filas y usa los valores por defecto; `puesto_competencias` no cambia
- [X] T106 [P] [US5] Escribir `FE/components/VistaPreviaEscala.dom.test.tsx`: chips Letras, Números y Categorías con `aria-pressed`; elegir otra escala cambia las etiquetas y añade "(vista previa)" sin llamar al servidor; "Descartar" restablece; "Aplicar esta escala" solo para el administrador y muestra "Escala de grados actualizada correctamente."; la vista previa es propia de cada tarjeta; Categorías usa los nombres propios o los de por defecto
- [X] T107 [P] [US5] Añadir al E2E `frontend/e2e/competencias.spec.ts` previsualizar, descartar y aplicar una escala, y comprobar que persiste tras recargar

### Implementation for User Story 5

- [X] T108 [US5] Crear `MOD/Application/UseCases/AplicarEscala.php`: valida que la escala exista y sea distinta, actualiza los 4 grados por orden, escribe el historial de escala y sube `version` sin exigir `version` de entrada
- [X] T109 [US5] Implementar el método de aplicar escala en `EloquentCompetenciaRepository.php` (transacción: grados por orden, historial, `version` + 1, `updated_by`) y añadir `aplicarEscala` a `CompetenciaController.php` con la ruta `PATCH /api/competencias/{id}/escala` (`auth:sanctum` + `admin`) en `MOD/Routes/api.php` (depende de T108)
- [X] T110 [P] [US5] Crear `FE/hooks/useAplicarEscala.ts` (mutación sin reintentos; invalida listado y detalle)
- [X] T111 [US5] Crear `FE/components/VistaPreviaEscala.tsx`: grupo `role="group"` con "Ver con escala:" (13 px `--muted`), tres chips de 44 px de alto (radio 999, 14 px 600, borde de 2 px `--accent` y fondo `--soft` el elegido), y con vista previa distinta de la guardada "Aplicar esta escala" (fondo `--accent`, solo administrador) y "Descartar"; calcula las etiquetas con `useEscalas` y los nombres de categoría de la competencia sin llamar al servidor (depende de T044, T110)
- [X] T112 [US5] Integrar `VistaPreviaEscala` en las pestañas Competencia y Comportamientos de `FE/components/PestanasCompetencia.tsx` (título con " (vista previa)" y estado por tarjeta) y mostrar `AvisoEmergente` "Escala de grados actualizada correctamente." al aplicar (depende de T111)

**Checkpoint**: US1 a US5 funcionan; la escala se previsualiza y se aplica sin alterar asignaciones.

---

## Phase 8: User Story 6 - Trazabilidad y uso por otros módulos (Priority: P3)

**Goal**: Cada creación, modificación, cambio de estado y de escala queda registrado; los demás módulos usan el diccionario solo por la Facade y solo asignan competencias activas y completas.

**Independent Test**: Hacer una edición, un cambio de estado y un cambio de escala y comprobar su registro; intentar asignar una competencia inactiva desde puestos (escenario 10 del quickstart).

### Tests for User Story 6 ⚠️

- [X] T113 [P] [US6] Escribir `backend/tests/Feature/Estructura/Competencias/AuditoriaCompetenciaTest.php` (Pest, reemplazar el existente): `created_by` y fecha en la creación; `updated_by` y fecha tras editar cualquier sección, aplicar escala o cambiar estado; historial de estado y de escala con valor anterior, nuevo, usuario y fecha; el detalle devuelve la auditoría con los nombres de usuario
- [X] T114 [P] [US6] Escribir `backend/tests/Feature/Estructura/Competencias/EstructuraFacadeTest.php` (reemplazar el existente) según `contracts/estructura-facade.md`: firmas conservadas, `grados` como mapa A–D por posición, `escala`, `grados_detalle` con `competencia_grado_id` y `completa`; `tieneComportamientosActivos` y `tienePreguntasActivas` exigen activa y completa; `getCompetenciasActivas` solo incluye activas; inactivas consultables por `getCompetencia`
- [X] T115 [P] [US6] Escribir `backend/tests/Feature/Estructura/Puestos/CompetenciasInactivasEnPuestoTest.php`: no se puede asignar una competencia inactiva o incompleta a un puesto, las asignaciones existentes a competencias hoy inactivas se conservan y se pueden consultar, el grado debe pertenecer a la competencia (`Ese grado no existe para la competencia.`) y cambiar la escala no altera la asignación

### Implementation for User Story 6

- [X] T116 [US6] Verificar que `EloquentCompetenciaRepository.php` escribe `updated_by` y la marca de tiempo de la competencia en toda escritura (datos, comportamientos, preguntas, escala y estado) y que el detalle devuelve `auditoria` con los nombres de usuario sin consultar tablas de otro módulo (ids sin FK; nombres vía el puerto `NombresDeUsuariosInterface` de `MOD/Application/Ports`, y solo para el administrador)
- [X] T117 [P] [US6] Completar `contracts/estructura-facade.md` y los comentarios de `MOD/Presentation/Facades/EstructuraFacade.php` si cambió alguna firma, y confirmar con `grep` que ningún otro módulo accede a clases o tablas internas del diccionario
- [ ] T118 [P] [US6] Actualizar `frontend/src/modules/estructura/puestos/components/CompetenciasFieldset.tsx`, `PuestoDetalle.tsx`, `frontend/src/modules/estructura/puestos/api.ts` y las pruebas asociadas para mostrar `grado_etiqueta` junto a la clave A–D y excluir competencias inactivas o incompletas de los selectores, sin cambiar el contrato `grado_requerido` **(Aplazada: el API de puestos conserva la clave A–D; falta mostrar `grado_etiqueta`.)**

**Checkpoint**: Las seis historias funcionan y se comprueban de forma independiente.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Calidad, accesibilidad y cierre de la refactorización

- [X] T119 [P] Ejecutar `cd backend && ./vendor/bin/pint` y el análisis estático disponible (`./vendor/bin/phpstan analyse` si está configurado) y corregir lo que reporten
- [X] T120 [P] Ejecutar `cd frontend && npm run lint && npx tsc --noEmit && npm run test && npm run build` y corregir lo que reporten
- [X] T121 Ejecutar `cd backend && ./vendor/bin/pest` completo y `cd frontend && npx playwright test e2e/competencias.spec.ts e2e/puestos.spec.ts` y confirmar que no hay regresiones respecto de la línea base de T001
- [X] T122 [P] Revisar accesibilidad (WCAG 2.2 AA): foco visible de 2 px, controles de al menos 44 px, `aria-label` con el nombre en cada botón de ícono, `alertdialog` con foco atrapado y Escape, regiones `aria-live` del recuento, tablist con flechas
- [X] T123 [P] Revisar el diseño responsive entre 320 y 1440 px, en tema claro y oscuro, sin scroll horizontal, comparando con `design/Diccionario.dc.html`
- [X] T124 Revisar rendimiento: sin consultas N+1 en el listado ni en el detalle (usar `with` y contar consultas en una prueba), y búsqueda que actualiza el listado en menos de 1 s con cientos de competencias sembradas
- [X] T125 Ejecutar la validación manual de `specs/019-diccionario-competencias-unificado/quickstart.md` (escenarios de administrador, de usuario sin rol de administrador y comprobaciones de contrato) y registrar las desviaciones
- [X] T126 [P] Retirar los restos de las funcionalidades antiguas: `grep` de `comportamientos`, `preguntas`, `competencias-disponibles`, `registro_id` y `grado_requerido` fuera de puestos y contratos; marcar en `specs/002-diccionario-comportamientos/` y `specs/003-diccionario-preguntas/` (y en `specs/001-diccionario-competencias/`) una nota de que quedaron reemplazadas por la 019

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias.
- **Foundational (Phase 2)**: depende de Setup; **bloquea** todas las historias. Dentro de la fase: migraciones T003 a T009 en orden → T010 → modelos (T011 a T016) → dominio (T017 a T023) → T031 → T024 y retirada del código antiguo (T025 a T029) → T030, T032 y T033 → semillas y fábricas (T034 a T036) → pruebas unitarias (T037 a T039); el bloque frontend base (T040 a T045) es independiente del backend y puede ir en paralelo.
- **US1 (P1)** y **US2 (P1)**: pueden empezar tras la fase fundacional; US2 reutiliza `FE/components/PestanasCompetencia.tsx` y `CompetenciasPage.tsx` de US1 solo para integrar el botón (T081).
- **US3 (P2)**: depende de la fase fundacional; la integración frontend (T095) necesita US1 (pestañas) y US2 (`SelectorDeEscala`, `AvisoEmergente`).
- **US4 (P2)**: depende de la fase fundacional; la integración frontend (T103, T104) necesita US1 y `AvisoEmergente` de US2.
- **US5 (P2)**: depende de la fase fundacional; la integración (T112) necesita US1 (pestañas) y `SelectorDeEscala` de US2.
- **US6 (P3)**: depende de las escrituras de US2 a US5 para comprobar la auditoría completa (T113); las pruebas de Facade y puestos (T114, T115) solo necesitan la fase fundacional.
- **Polish (Phase 9)**: depende de las historias deseadas.

### User Story Dependencies

- **US1** y **US2** son el MVP combinado (consultar y crear); ninguna depende de otra historia en backend.
- **US3, US4 y US5** dependen solo de la fase fundacional en backend; en frontend reutilizan componentes creados en US1 y US2, por lo que conviene hacerlas después.
- **US6** verifica transversalmente lo ya construido.

### Within Each User Story

- Las pruebas se escriben primero y deben fallar antes de implementar.
- Request/DTO → caso de uso → repositorio → controlador y ruta; hooks → componentes → integración en la página.
- Cada historia cierra con su checkpoint antes de pasar a la siguiente prioridad.

### Parallel Opportunities

- Fase 2: T005 y T008; T011 a T014, T016 a T019, T021 y T022; T026 a T029; T032 y T033; T034 y T036; T037 a T039; y todo el bloque T040 a T045.
- Dentro de cada historia, todas las pruebas marcadas [P] (backend y frontend) y los componentes de archivos distintos.
- Después de la fase fundacional, backend y frontend de una misma historia pueden avanzar en paralelo usando `contracts/competencias-api.md` como acuerdo.

---

## Parallel Example: User Story 1

```bash
# Pruebas de US1 en paralelo (archivos distintos):
Task: "T046 Escribir backend/tests/Feature/Estructura/Competencias/ConsultarCompetenciasTest.php"
Task: "T047 Escribir backend/tests/Feature/Estructura/Competencias/EscalasTest.php"
Task: "T050 Escribir FE/pages/CompetenciasPage.dom.test.tsx"
Task: "T051 Escribir FE/components/PestanasCompetencia.dom.test.tsx"

# Componentes de frontend de US1 en paralelo:
Task: "T061 Crear FE/components/CompetenciaCard.tsx"
Task: "T062 Crear FE/components/PestanasCompetencia.tsx"
Task: "T060 Crear FE/hooks/useListadoParams.ts y useCompetencias.ts"
```

---

## Implementation Strategy

### MVP First (US1 + US2)

1. Completar Phase 1: Setup.
2. Completar Phase 2: Foundational (crítico: esquema, retirada del código antiguo y compatibilidad con puestos).
3. Completar Phase 3 (US1) y Phase 4 (US2).
4. **PARAR y VALIDAR**: el diccionario completo se consulta y se crea de punta a punta; ejecutar los escenarios 1 a 5 del quickstart.
5. Desplegar o demostrar si está listo.

### Incremental Delivery

1. Setup + Foundational → base lista.
2. US1 → consulta (MVP de lectura) → validar.
3. US2 → creación completa → validar.
4. US3 → edición por secciones y concurrencia → validar.
5. US4 → activar/desactivar y completitud → validar.
6. US5 → escala → validar.
7. US6 → trazabilidad y verificación transversal → validar.
8. Polish → calidad y cierre.

### Parallel Team Strategy

1. El equipo completa Setup y Foundational junto (las migraciones son secuenciales; el bloque frontend base T040 a T045 puede ir en paralelo con el backend).
2. Después: A) US1 backend + frontend de consulta; B) US2 creación; C) US3 y US5 en backend mientras llegan los componentes compartidos.
3. Las historias se integran sin romper las anteriores.

---

## Notes

- [P] = archivos distintos, sin dependencias pendientes.
- [Story] enlaza cada tarea con su historia para trazabilidad.
- La refactorización toca puestos, Facade y adaptadores de otros módulos: T030 a T033 deben pasar antes de cualquier historia.
- Las migraciones traspasan datos reales: probarlas siempre sobre una copia de la base (T010).
- Verificar que las pruebas fallan antes de implementar; hacer commit tras cada tarea o grupo lógico.
- Evitar: tareas vagas, conflictos en el mismo archivo (varias tareas tocan `CompetenciaController.php`, `EloquentCompetenciaRepository.php`, `CompetenciasPage.tsx` y `PestanasCompetencia.tsx`: hacerlas en el orden de las historias, no en paralelo).
