---

description: "Tareas de la feature 020-adecuacion-puesto-futuro"
---

# Tasks: Adecuación a puesto futuro y mapa de talento

**Input**: Documentos de diseño en `/specs/020-adecuacion-puesto-futuro/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api.md, contracts/facade.md, contracts/ui.md, quickstart.md. Requiere 000-login, 001/004 (puestos y competencias en `EstructuraFacade`), 005-unidad-organizacional (departamentos), 005-fichas-colaboradores y 006-ingresos-reingresos (`PersonalFacade`, vínculos, `colaborador_cuentas`), 006-deteccion-necesidades-formativas (DNF), 005-catalogo-formativo, 018-reportes-evaluacion y 019-rutas-carrera (`DesarrolloFacade::inscripcionesActivas` y `obtenerNivel`) ya implementadas.

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario (US1–US7 de spec.md) para implementarlas y probarlas de forma independiente. La feature añade cuatro tablas propias al módulo `Desarrollo`, **amplía `DesarrolloFacade`** con dos métodos de solo lectura y **`PersonalFacade`** con uno (`getPuestoActual`). No toca tablas ni clases internas de otros módulos.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1–US7)
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Desarrollo/…`. Abreviatura **`$D`** = `backend/app/Modules/Desarrollo`
- Frontend: `frontend/src/modules/desarrollo/adecuacion/…` (reutiliza `frontend/src/shared/ui/…`). Abreviatura **`$F`** = `frontend/src/modules/desarrollo/adecuacion`
- Pruebas backend: `backend/tests/Unit/Desarrollo/Adecuacion/` y `backend/tests/Feature/Desarrollo/Adecuacion/`; pruebas de componentes: `*.dom.test.tsx` junto al código (con `// @vitest-environment jsdom`); fixtures en `frontend/src/test/adecuacionFixtures.ts`; E2E en `frontend/e2e/adecuacion.spec.ts`
- Regla de módulos: Adecuación **no lee tablas de Estructura, Personal ni Rutas**; usa puertos de `Desarrollo` cuyos adaptadores llaman a `EstructuraFacade`, `PersonalFacade` y `DesarrolloFacade`. `colaborador_id`, `puesto_*_id`, `elemento_id` y los ids de usuario son enteros **sin clave foránea** hacia otros módulos; `inscripcion_id` y `nivel_objetivo_id` son FK internas de `Desarrollo`. Entre las tablas nuevas **sin cascada**, sin soft deletes y **sin UPDATE ni DELETE** sobre adecuaciones y detalles
- Valores en base de datos (sin tildes; la interfaz muestra con tilde): `preparacion` ∈ `Lista` | `EnDesarrollo` | `NoLista` | `Incompleta`; `dimension` ∈ `Competencia` | `Conocimiento` | `Experiencia`; `estado` ∈ `ConBrecha` | `Cumple` | `Supera` | `SinEvaluacion`
- Permisos: catálogo en código (`ClaveDePermisoAdecuacion`, mismo patrón que `ClaveDePermisoRutas`) con `desarrollo.adecuacion.generar`, `.ver`, `.comparar`, `.mapa`, `.parametros` y `.ver_propio`; `admin` recibe **solo las cinco de gestión** (no `ver_propio`, igual que en la 019), `user` solo `ver_propio`; un único middleware `permiso:<clave>` autoriza cada ruta. Los controladores no consultan el rol
- Reutilizar sin modificar: `Button`, `Badge`, `Alert`, `ConfirmDialog`, `Modal`, `SelectField`, `SearchField`, `TextField`, `TextareaField`, `Tabs`, `Pagination`, `DataTable`, `EmptyState`, `ErrorState`, `Skeleton` y `AppShell`. No crear componentes equivalentes
- En frontend, `adecuacion` **no importa nada** de `actividades`, `reportes`, `plan-formacion`, `micro-learning`, `dnf`, `rutas` ni `catalogo-formativo`, salvo `modules/auth/login` (rol); copiar el patrón de `RequirePermiso`/`RequireRole` de `rutas` en lugar de importarlo. El enlace al Catálogo Formativo es solo una URL (`/formacion?competencia=<id>`)
- Guía visual **exacta**: `design/AdecuacionDiagnostico.dc.html`, `AdecuacionComparar.dc.html`, `AdecuacionMapa.dc.html` (mismo prototipo del administrador) y `AdecuacionColaborador.dc.html`; el detalle por pantalla está en `contracts/ui.md`. Manda la spec en reglas y datos; los tableros, en aspecto y estructura (plan.md, tabla «Diferencias entre tableros y spec»). Orden de paneles: **Experiencia, Conocimientos, Competencias**
- Cálculo: la escala sale de `NivelGrado::valorNumerico()` (D=1…A=4); **no** se reescribe la escala ni se usa `CalculadorDeBrecha` (R2). Conocimientos: la bandera `desarrollo.adecuacion.conocimientos_con_grado` en `false` **desactiva la dimensión** (sin resultados, con aviso, pesos reescalados); su rama `true` no se implementa
- Lecciones previas: en E2E localizar campos con `getByRole('textbox', { name, exact: true })`; escribir archivos con acentos con la herramienta Write (los heredocs de Bash fallan; tampoco hay Python); ver la memoria «Entorno local de pruebas» (OneDrive/Windows) al correr Pest, Vitest y Playwright; no asumir `IngresosSeeder` en E2E (feature 018); no hay tabla de permisos
- Sin dependencias nuevas

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Línea base verde y configuración antes de tocar código

- [X] T001 Verificar la línea base: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run` y `npx playwright test` en `frontend/` (sin cambios de archivos). Anotar los fallos preexistentes de la memoria del proyecto para no atribuirlos a esta feature
- [X] T002 `design/DESIGN.md` ya registra la sección «Adecuación a puesto futuro (feature 020)» y las seis filas de diferencias (hecho en `/speckit-plan`). Solo releerla antes de empezar frontend y añadir ahí cualquier ajuste visual nuevo antes de implementarlo (Constitución III)
- [X] T003 Añadir en `backend/config/desarrollo.php` la clave `adecuacion` con `conocimientos_con_grado` (`(bool) env('DESARROLLO_ADECUACION_CONOCIMIENTOS_CON_GRADO', false)`) y `experiencia_disponible` (`(bool) env('DESARROLLO_ADECUACION_EXPERIENCIA_DISPONIBLE', true)`), con comentarios, y documentarlas en `backend/.env.example` si el archivo existe

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Tablas, modelos, dominio, puertos, repositorio, autorización por permiso y piezas de frontend que usan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend – datos

- [X] T004 Crear `backend/database/migrations/2026_10_24_000001_create_adecuaciones_table.php` según data-model.md: `id`; `tenant_id` entero sin signo con índice; `codigo` `string(12)` (`ADE-0001`), único `(tenant_id, codigo)`; `colaborador_id` entero sin signo (sin FK); `puesto_actual_id` entero sin signo **anulable** (sin FK); `puesto_objetivo_id` entero sin signo (sin FK); `inscripcion_id` FK anulable a `ruta_inscripciones` sin cascada; `nivel_objetivo_id` FK anulable a `ruta_niveles` sin cascada; `experiencia_requerida_anios` `decimal(4,1)` anulable; `preparacion` `string(12)` ∈ Lista | EnDesarrollo | NoLista | Incompleta; `umbral_meses` entero pequeño sin signo (copia del parámetro vigente); `observaciones` `string(500)` anulable; `created_by` entero sin signo; `created_at`/`updated_at`. Índices `(tenant_id, colaborador_id, puesto_objetivo_id, created_at)`, `(tenant_id, preparacion)` y `(tenant_id, created_at)`. Sin soft deletes
- [X] T005 [P] Crear `backend/database/migrations/2026_10_24_000002_create_adecuacion_detalles_table.php`: `id`; `adecuacion_id` FK a `adecuaciones` **sin cascada**; `dimension` `string(12)` ∈ Competencia | Conocimiento | Experiencia; `tipo_elemento` `string(12)` anulable ∈ Competencia | Conocimiento (nulo en Experiencia); `elemento_id` entero sin signo anulable (sin FK; nulo en Experiencia); `nombre_elemento` `string(150)` anulable; `requerido_actual_nivel`, `requerido_objetivo_nivel`, `valor_nivel` `char(1)` anulables A–D; `requerido_actual_anios`, `requerido_objetivo_anios`, `valor_anios` `decimal(4,1)` anulables; `diferencia` `decimal(5,1)` anulable; `estado` `string(12)` ∈ ConBrecha | Cumple | Supera | SinEvaluacion; `tiempo_cierre_meses` entero pequeño sin signo anulable (obligatorio ≥ 1 si `estado = ConBrecha`; nulo en los demás); `created_at`. Única `(adecuacion_id, dimension, tipo_elemento, elemento_id)` e índice `(adecuacion_id)`
- [X] T006 [P] Crear `backend/database/migrations/2026_10_24_000003_create_adecuacion_parametros_table.php`: `id`; `tenant_id` entero sin signo **único**; `peso_competencias`, `peso_conocimientos`, `peso_experiencia` enteros pequeños sin signo (0–100; suman 100); `umbral_meses` entero pequeño sin signo (≥ 1; inicial 12); `updated_by` entero sin signo anulable; timestamps
- [X] T007 [P] Crear `backend/database/migrations/2026_10_24_000004_create_adecuacion_parametros_historial_table.php`: `id`; `tenant_id` entero sin signo con índice; `campo` `string(30)` ∈ peso_competencias | peso_conocimientos | peso_experiencia | umbral_meses; `valor_anterior` y `valor_nuevo` `string(20)`; `user_id` entero sin signo; solo `created_at`; índice `(tenant_id, created_at)`. Solo inserciones
- [X] T008 [P] Crear los modelos Eloquent en `$D/Infrastructure/Persistence/Eloquent/Models/`: `AdecuacionModel`, `AdecuacionDetalleModel`, `ParametrosAdecuacionModel` y `HistorialParametrosAdecuacionModel` (casts de decimales, relaciones internas). `AdecuacionModel` y `AdecuacionDetalleModel` **bloquean `updating` y `deleting`** lanzando una excepción de dominio de inmutabilidad (R13)

### Backend – dominio

- [X] T009 [P] Crear los objetos de valor en `$D/Domain/ValueObjects/`: `Preparacion` (enum Lista, EnDesarrollo, NoLista, Incompleta con etiqueta), `DimensionDeAdecuacion`, `EstadoDeResultado` (enums), `CodigoDeAdecuacion` (`ADE-` + número de 4 dígitos con relleno; construye desde número y valida el formato) y `PesosPorDimension` (tres enteros ≥ 0 que **suman 100**; método `reescalar(array $dimensionesPresentes)` que reparte los pesos sobre las dimensiones evaluadas)
- [X] T010 [P] Crear las excepciones en `$D/Domain/Exceptions/`: `PuestoObjetivoNoDefinido`, `ObjetivoAmbiguo`, `ColaboradorNoElegible`, `SinRequisitosEvaluables`, `TiempoDeCierreRequerido`, `PesosInvalidos`, `UmbralInvalido`, `ObjetivoCambio` y `AdecuacionInmutable`; reutilizar la excepción de fuente no disponible existente si la hay
- [X] T011 [P] Crear las entidades en `$D/Domain/Entities/`: `Adecuacion` (cabecera inmutable), `ResultadoDeAdecuacion` (valida que `ConBrecha` ⇒ `diferencia < 0` y `tiempo_cierre_meses ≥ 1`; `Cumple` ⇒ diferencia 0; `Supera` ⇒ diferencia > 0; `SinEvaluacion` ⇒ diferencia y tiempo nulos; niveles solo A|B|C|D) y `ParametrosDeAdecuacion` (pesos + umbral)
- [X] T012 [P] Crear `$D/Domain/Services/CalculadorDeResultado.php`: dado dimensión, requerido actual/objetivo (nivel o años) y valor de la persona (nivel o años, anulable), devuelve diferencia y estado. Grados con `NivelGrado::valorNumerico()` (no reescribir la escala); experiencia en años con un decimal; sin valor ⇒ `SinEvaluacion` sin diferencia
- [X] T013 [P] Crear `$D/Domain/Services/CalculadorDePreparacion.php` (FR-016, en este orden): `Incompleta` si algún resultado `SinEvaluacion`; si no, `Lista` sin brechas; `EnDesarrollo` si todas las brechas tienen `tiempo_cierre_meses` ≤ umbral; `NoLista` si alguna lo supera
- [X] T014 [P] Crear `$D/Domain/Services/CalculadorDeCumplimiento.php` (por resultado `min(1, valor ÷ requerido)`; requerido 0 ⇒ 1; por dimensión, promedio de los resultados evaluados; dimensión sin resultados evaluados ⇒ `null`) y `CalculadorDePuntaje.php` (Σ peso × cumplimiento sobre las dimensiones con cumplimiento no nulo, con pesos reescalados; devuelve porcentaje 0–100 con un decimal)
- [X] T015 [P] Crear `$D/Domain/Services/ReglasDeParametrosDeAdecuacion.php`: valida que los tres pesos sean enteros ≥ 0 que suman 100 y que el umbral sea un entero ≥ 1; calcula los campos modificados para el historial
- [X] T016 [P] Pruebas unitarias en `backend/tests/Unit/Desarrollo/Adecuacion/`: `CalculadorDeResultadoTest` (A–D contra A–D, −1/0/+1, sin valor, experiencia −2), `CalculadorDePreparacionTest` (las cuatro preparaciones, umbral en el límite, Incompleta prevalece sobre brechas), `CalculadorDeCumplimientoYPuntajeTest` (ejemplo de la spec 0,75 con 40/30/30; tope 1; dimensión excluida con pesos reescalados), `PesosPorDimensionTest`, `CodigoDeAdecuacionTest` y `ReglasDeParametrosTest`

### Backend – repositorio, puertos y adaptadores

- [X] T017 Crear `$D/Domain/Repositories/AdecuacionesRepositoryInterface.php` **sin** métodos de actualización ni eliminación: `guardar(Adecuacion, list<ResultadoDeAdecuacion>): Adecuacion` (asigna el código en la transacción), `obtener(tenantId, id)`, `listar(tenantId, filtros, pagina, porPagina)`, `vigentes(tenantId, filtros, pagina, porPagina)` (una por `(colaborador, puesto objetivo)`), `vigentesDeColaborador(tenantId, colaboradorId)`, `vigenteDe(tenantId, colaboradorId, puestoObjetivoId)`, `vigentesDeCandidatos(tenantId, puestoObjetivoId, colaboradorIds)`, `historialDeColaborador(tenantId, colaboradorId, limite)`, `obtenerParametros(tenantId)` (crea 40/30/30 y 12 si no existen) y `actualizarParametros(tenantId, parametros, actorId): list<cambios>` (actualiza e inserta una fila de historial por campo cambiado en una transacción)
- [X] T018 Implementar `$D/Infrastructure/Persistence/Eloquent/Repositories/EloquentAdecuacionesRepository.php` con las consultas **sin N+1**, vigente = mayor `created_at` con desempate por `id`, código `ADE-NNNN` por contador dentro de la transacción con reintento acotado ante colisión (R14), y la copia del umbral. Ningún método hace UPDATE/DELETE sobre adecuaciones o detalles
- [X] T019 [P] Pruebas de repositorio en `backend/tests/Feature/Desarrollo/Adecuacion/AdecuacionesRepositoryTest.php`: código secuencial y sin duplicados, vigente = la más reciente, intento de `update`/`delete` en los modelos lanza `AdecuacionInmutable`, parámetros iniciales 40/30/30 y 12, historial por campo, aislamiento por `tenant_id`
- [X] T020 [P] Crear los puertos en `$D/Application/Ports/`: `ValoresDeLaPersonaInterface` (`nivelesDeCompetencias(int $tenantId, int $colaboradorId): array<int,string>`), `ExperienciaDelColaboradorInterface` (`aniosDe(int $tenantId, array $colaboradorIds): array<int,?float>`), `PuestoActualDelColaboradorInterface` (`puestoActualDe(int $tenantId, array $colaboradorIds, string $fecha): array<int,?int>`) y `RutasParaAdecuacionInterface` (`inscripcionesActivas(tenantId, colaboradorId)`, `nivel(tenantId, nivelId)`, `rutaDeInscripcion(...)`); reutilizar sin duplicar `PerfilesDePuestoInterface`, `ColaboradoresVigentesInterface`, `CuentaColaboradorInterface`, `NombresDeReferenciaInterface`, `NombresDeUsuarioInterface`, `DepartamentoDeColaboradorInterface`, `DepartamentosInterface`
- [X] T021 [P] Crear `$D/Infrastructure/Adapters/ValoresDeLaPersonaDesdeDnf.php`: lee el **último nivel evaluado por competencia** de las DNF del colaborador mediante `DnfRepositoryInterface` (mismo módulo); sin DNF o sin la competencia ⇒ no figura (R3). Prueba en `backend/tests/Feature/Desarrollo/Adecuacion/ValoresDeLaPersonaTest.php`
- [X] T022 [P] Crear `$D/Infrastructure/Adapters/ExperienciaDesdePersonal.php` sobre `PersonalFacade::getDesarrollo` (suma años de las experiencias con `fecha_inicio`/`fecha_finalizacion`, sin superposición, un decimal; sin experiencias o `InformacionNoDisponible` ⇒ `null`; respeta `desarrollo.adecuacion.experiencia_disponible`). Prueba con la Facade simulada (R5)
- [X] T023 Añadir **`getPuestoActual(int $tenantId, array $colaboradorIds, string $fecha): array`** a `backend/app/Modules/GestionPersonal/Application/Services/PersonalService.php` (análogo a `getDepartamentoActual`: posición de la asignación vigente → puesto vía `LectorEstructuraPort`, ampliándolo si hace falta para devolver `puesto_id`; `InformacionNoDisponible` si falla Estructura), declararlo en el docblock `@method` de `PersonalFacade.php` y probarlo en `backend/tests/Feature/GestionPersonal/` (colaborador con asignación, sin asignación, fuente caída). Solo lectura y aditivo (R6)
- [X] T024 [P] Crear `$D/Infrastructure/Adapters/PuestoActualDesdePersonal.php` sobre `PersonalFacade::getPuestoActual` (degrada a `null` por colaborador y registra la fuente no disponible)
- [X] T025 [P] Crear `$D/Infrastructure/Adapters/RutasParaAdecuacionDesdeFacade.php` sobre `DesarrolloFacade::inscripcionesActivas` y `obtenerNivel` (más la ruta de una inscripción para el filtro del mapa). Prueba en `backend/tests/Feature/Desarrollo/Adecuacion/RutasParaAdecuacionTest.php`

### Backend – autorización, proveedor y rutas

- [X] T026 Crear `$D/Domain/ValueObjects/ClaveDePermisoAdecuacion.php` (catálogo de las seis claves y su asignación por defecto: `admin` solo las cinco de gestión —**sin** `ver_propio`—, `user` solo `desarrollo.adecuacion.ver_propio`), registrarlo en el mismo lugar donde `ClaveDePermisoRutas` alimenta el middleware `permiso:<clave>`, y crear `$D/Application/Services/AutorizadorDeAdecuacion.php` (único punto de autorización). Prueba en `backend/tests/Feature/Desarrollo/Adecuacion/AdecuacionPermisosTest.php` limitada a `GET /api/adecuaciones/permisos` y al catálogo (401 sin sesión, claves devueltas por rol: `admin` sin `ver_propio`, `user` solo `ver_propio`); los 401/403 de cada endpoint se prueban en la prueba de la historia que lo crea
- [X] T027 Enlazar en `$D/Infrastructure/Providers/DesarrolloServiceProvider.php` los puertos nuevos con sus adaptadores y `AdecuacionesRepositoryInterface` con el repositorio Eloquent
- [X] T028 Añadir en `$D/Routes/api.php` el grupo `Route::prefix('api/adecuaciones')->middleware(['api', 'auth:sanctum'])` con, por ahora, `GET /permisos` (sin permiso adicional; devuelve las claves del usuario, `AdecuacionesController@permisos`). Las rutas literales (`/opciones`, `/previsualizar`, `/mi-adecuacion`, `/mapa`, `/comparar`, `/parametros`) van **antes** de `/{id}` con `whereNumber('id')`; ninguna ruta PUT/PATCH/DELETE sobre adecuaciones (solo `PUT /parametros` en US6)

### Frontend – base compartida de la feature

- [X] T029 [P] Crear `$F/text.ts` con todo el texto visible en español (títulos, pestañas, etiquetas de selectores, avisos, estados vacíos, botones, errores y mensajes de `contracts/ui.md`), incluido el aviso «Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.»
- [X] T030 [P] Crear `$F/api.ts` con los tipos TypeScript estrictos (sin `any`) de `contracts/api.md` (`Preparacion`, `Resultado`, `Diagnostico`, `AdecuacionDetalle`, `ResumenAdecuacion`, `FilaMapa`, `Ranking`, `Parametros`, `MiAdecuacion`) y las funciones HTTP que usan el cliente compartido (nunca Axios en páginas): `obtenerPermisos`, `obtenerOpciones`, `previsualizar`, `generar`, `listar`, `obtener`, `miAdecuacion`, `mapa`, `comparar`, `obtenerParametros`, `actualizarParametros`; los 422 se mapean a errores por campo
- [X] T031 [P] Crear `$F/permisos.ts` (claves como constantes y `useTienePermiso`) y `$F/components/RequirePermiso.tsx` con su `RequireRole`, copiados del patrón de `rutas` (sin importar de él)
- [X] T032 [P] Crear `$F/estilosEstado.ts` y `$F/components/Etiquetas.tsx` (insignias de preparación: Lista éxito, En desarrollo acento, No lista peligro, Incompleta neutro; de estado: Con brecha peligro, Cumple éxito, Supera acento, Sin evaluación neutro; etiqueta de tipo de cambio) con tokens del design system
- [X] T033 [P] Crear `$F/components/BarrasTriples.tsx` (barras de requisito actual, evaluación de la persona y requisito futuro, con `role="img"` y texto alternativo) y `$F/components/LeyendaDeBarras.tsx` según `AdecuacionDiagnostico.dc.html`
- [X] T034 Crear `$F/nav.tsx` (ítem «Adecuación» visible con cualquier clave `desarrollo.adecuacion.*`), `$F/hooks/useAdecuacionPermisos.ts` (clave de caché `adecuacion/permisos`) y registrar en `frontend/src/App.tsx` las rutas `/adecuacion` (página que muestra la vista del administrador o «Mi adecuación» según permisos) y `/adecuacion/evaluaciones/:id`, con páginas provisionales `AdecuacionPage` y `MiAdecuacionPage` en `$F/pages/` que se completan en las historias; incorporar `adecuacionNav` al menú del App Shell
- [X] T035 [P] Crear `frontend/src/test/adecuacionFixtures.ts` con fixtures de diagnóstico, adecuación, fila de mapa, ranking y parámetros (datos de prueba, sin personas reales) y pruebas de `RequirePermiso` en `$F/components/RequirePermiso.dom.test.tsx`

**Checkpoint**: Base lista; las historias pueden empezar

---

## Phase 3: User Story 1 - Generar la adecuación de un colaborador a un puesto objetivo (Priority: P1) 🎯 MVP

**Goal**: El administrador elige colaborador y puesto objetivo, ve el diagnóstico (Experiencia, Conocimientos, Competencias), ingresa el tiempo de cierre de cada brecha y confirma: se guarda una adecuación inmutable `ADE-000N` con su preparación.

**Independent Test**: Con un colaborador con competencia X en C frente a B exigido y 3 años contra 5 exigidos, generar la adecuación: X con diferencia −1 y experiencia −2, ambas Con brecha, tiempo de cierre exigido, preparación según el umbral y código `ADE-0001`.

### Tests for User Story 1

- [X] T036 [P] [US1] Pruebas Feature en `backend/tests/Feature/Desarrollo/Adecuacion/PrevisualizarAdecuacionTest.php`: objetivo por inscripción (experiencia = mínima del nivel), puesto directo con y sin experiencia, 422 sin objetivo / con ambos / colaborador no vigente / puesto inexistente o inactivo / inscripción ajena / sin requisitos evaluables, competencias inactivas omitidas con aviso, requisitos «Sin evaluación» con aviso, conocimientos desactivados con aviso, 503 si el perfil no responde, nada se guarda
- [X] T037 [P] [US1] Pruebas Feature en `backend/tests/Feature/Desarrollo/Adecuacion/GenerarAdecuacionTest.php`: 201 con código `ADE-0001`, puesto actual copiado, `umbral_meses` copiado, `created_by`; 422 por brecha sin tiempo / tiempo 0 o 61 / tiempo en un resultado que no es brecha / observaciones > 500; el servidor ignora valores, estados y preparación enviados por el cliente; 409 `objetivo_cambio`; 503 sin guardar; preparación calculada para las cuatro clases; `PUT`/`PATCH`/`DELETE` sobre `/api/adecuaciones/{id}` no existen (404/405); dos generaciones seguidas dan códigos distintos
- [X] T038 [P] [US1] Prueba unitaria `backend/tests/Unit/Desarrollo/Adecuacion/ArmadorDeDiagnosticoTest.php` con puertos simulados: perfil + valores ⇒ resultados correctos por dimensión, requerido actual vacío si el puesto actual no lo exige, cumplimiento por dimensión y global, tipo de cambio (Promoción / Lateral / MismoPuesto / PuestoDirecto)

### Implementation for User Story 1

- [X] T039 [P] [US1] Crear los DTO en `$D/Application/DTO/`: `DiagnosticoDTO`, `ResultadoDTO`, `AvisoDTO`, `OpcionesDeSeleccionDTO` y `AdecuacionDTO` con la forma de `contracts/api.md`
- [X] T040 [US1] Crear `$D/Application/Services/ArmadorDeDiagnostico.php`: obtiene perfil del objetivo y del puesto actual (`PerfilesDePuestoInterface`), valores de la persona (`ValoresDeLaPersonaInterface`, `ExperienciaDelColaboradorInterface`), omite elementos inactivos, respeta las banderas (R4, R5), aplica `CalculadorDeResultado` y devuelve resultados, cumplimientos, avisos y `fuentes_no_disponibles`
- [X] T041 [P] [US1] Crear `$D/Application/UseCases/ObtenerOpcionesDeAdecuacion.php` (colaboradores vigentes por texto, y para un colaborador: inscripciones Activas con niveles, puestos activos y su historial reciente)
- [X] T042 [US1] Crear `$D/Application/UseCases/PrevisualizarAdecuacion.php` (valida objetivo por inscripción o puesto directo, elegibilidad del colaborador, experiencia requerida ≥ 0 con un decimal, llama a `ArmadorDeDiagnostico` y devuelve `preparacion_provisoria`)
- [X] T043 [US1] Crear `$D/Application/UseCases/GenerarAdecuacion.php`: recalcula en el servidor, relee la inscripción y responde `ObjetivoCambio` si el puesto objetivo difiere del enviado, exige tiempos enteros 1–60 en **cada** `ConBrecha` y rechaza tiempos en los demás, calcula la preparación con el umbral vigente, rechaza con `FuenteNoDisponible` en vez de guardar datos incompletos y guarda cabecera y detalles en una transacción
- [X] T044 [P] [US1] Crear los Form Requests en `$D/Presentation/Requests/`: `OpcionesAdecuacionRequest`, `PrevisualizarAdecuacionRequest` (`colaborador_id` requerido; `inscripcion_id` o `puesto_objetivo_id`; `experiencia_requerida_anios` numérico ≥ 0 con un decimal) y `GenerarAdecuacionRequest` (los mismos más `tiempos_cierre.*.dimension|elemento_id|meses` y `observaciones` ≤ 500)
- [X] T045 [P] [US1] Crear los Resources en `$D/Presentation/Resources/`: `DiagnosticoResource`, `AdecuacionResource` y `OpcionesResource` (sin trazas ni datos internos de otros módulos)
- [X] T046 [US1] Crear `$D/Presentation/Controllers/AdecuacionesController.php` (delgado) con `opciones`, `previsualizar` y `store`, y registrar `GET /opciones` (`permiso:desarrollo.adecuacion.generar`), `POST /previsualizar` y `POST /` (`permiso:desarrollo.adecuacion.generar`) en `$D/Routes/api.php`; mapear las excepciones de dominio a 422/409/503 con los códigos de `contracts/api.md`
- [X] T047 [US1] Crear `backend/database/seeders/AdecuacionesSeeder.php` y registrarlo en `DatabaseSeeder` (R17): parámetros 40/30/30 y 12; cuatro adecuaciones sobre los datos de `RutasSeeder`, Estructura y Personal que cubran Lista, En desarrollo, No lista e Incompleta; un puesto objetivo con tres candidatos y una adecuación por puesto directo; crea o reutiliza una DNF con niveles para los colaboradores sembrados; sin personas reales y sin depender de `IngresosSeeder`. Prueba de siembra en `backend/tests/Feature/Desarrollo/Adecuacion/AdecuacionesSeederTest.php`
- [X] T048 [P] [US1] Crear `$F/validation.ts` (Zod): tiempo de cierre entero 1–60 por brecha, observaciones ≤ 500, experiencia requerida ≥ 0 con un decimal; con `$F/validation.test.ts`
- [X] T049 [P] [US1] Crear los hooks de TanStack Query en `$F/hooks/`: `useOpcionesDeAdecuacion`, `usePrevisualizacion` (clave `adecuacion/previsualizar`) y `useGenerarAdecuacion` (mutación **sin reintento**; al éxito invalida lista, mapa, `mia` e historial del colaborador)
- [X] T050 [P] [US1] Crear `$F/components/SelectorDeColaboradorYObjetivo.tsx` según el tablero: Combobox «Colaborador», Select «Puesto objetivo» con «Siguiente nivel de su ruta (por defecto)» (una opción por inscripción Activa si hay varias; el administrador elige) y «Puesto directo» con `Experiencia requerida (años)` opcional, y el aviso de que no hay siguiente nivel
- [X] T051 [P] [US1] Crear `$F/components/ResumenDeAdecuacion.tsx` (tipo de cambio, puesto actual → objetivo, línea de departamento y años de práctica, «Adecuación al puesto actual/objetivo», insignia de preparación, y aviso con la cantidad de requisitos «Sin evaluación» sobre el total para que se vea el riesgo de preparación Incompleta) y `$F/components/PanelDeDimension.tsx` (cabecera «actual {pa} % · futuro {pf} %», lista con nombre, insignia de estado, resumen «Nivel actual: C · Requerido: B · Diferencia: −1» y barras triples; panel de Conocimientos con aviso cuando la dimensión está desactivada)
- [X] T052 [P] [US1] Crear `$F/components/TablaDeBrechas.tsx` («Brechas y tiempo estimado de cierre» con `input number` `min=1` `max=60`, `aria-label` «Tiempo estimado de cierre en meses: {elemento}», errores por fila, texto del estado del cierre y el vacío «No hay brechas…») y `$F/components/AvisosDelDiagnostico.tsx` (`Alert` neutro para «Sin evaluación» e inactivos omitidos)
- [X] T053 [P] [US1] Crear `$F/components/ConfirmarAdecuacion.tsx` (bloque de Confirmación: preparación calculada, `Observaciones` con contador `n/500`, botón «Registrar esta evaluación» bloqueado mientras falten tiempos o haya envío pendiente, diálogo de confirmación accesible) y `$F/components/HistorialDeColaborador.tsx` («Cada evaluación es un registro inmutable: no se edita ni se elimina.»)
- [X] T054 [US1] Completar `$F/pages/AdecuacionPage.tsx` con la cabecera, el aviso permanente, el mensaje de éxito/error, las pestañas (`role="tablist"`, la activa en `?pestana=`, ocultas si falta la clave) y la pestaña «Diagnóstico» que compone los componentes de T050–T053 (estados loading, vacío, error con «Reintentar», 409 «Recargar diagnóstico»); las demás pestañas quedan como marcadores hasta sus historias
- [X] T055 [P] [US1] Pruebas de componentes: `$F/components/TablaDeBrechas.dom.test.tsx` (tiempo obligatorio, límites 1–60, error por fila), `$F/components/PanelDeDimension.dom.test.tsx` (diferencia, estados, dimensión desactivada), `$F/components/SelectorDeColaboradorYObjetivo.dom.test.tsx` (varias inscripciones, puesto directo con experiencia) y `$F/pages/AdecuacionPage.dom.test.tsx` (flujo de registrar, bloqueo sin tiempos, 409)

**Checkpoint**: US1 funcional y comprobable de forma independiente (MVP)

---

## Phase 4: User Story 2 - Consultar «Mi adecuación» como colaborador (Priority: P1)

**Goal**: El colaborador ve solo sus adecuaciones vigentes (una por puesto objetivo) con barras, diferencias, tiempos de cierre, «Ver cursos sugeridos» y el aviso permanente, sin puntajes ni datos ajenos.

**Independent Test**: Con un colaborador con adecuación vigente con una brecha de competencia y una de experiencia, abrir «Mi adecuación» y comprobar tarjetas, diferencia, tiempo de cierre, enlace al catálogo, mensaje de experiencia y aviso; comprobar que no aparece ningún puntaje ni dato de otros.

### Tests for User Story 2

- [X] T056 [P] [US2] Pruebas Feature en `backend/tests/Feature/Desarrollo/Adecuacion/MiAdecuacionTest.php`: devuelve solo las adecuaciones vigentes del colaborador autenticado (una por puesto objetivo, la más reciente primero), lista vacía sin adecuación, `puesto_objetivo_id` opcional, sin `puntaje`, `ranking`, `created_by` ni datos de otras personas, no acepta `colaborador_id`, 403 al administrador (no tiene `ver_propio`), 401 sin sesión y 403 de `ver_propio` en `GET /api/adecuaciones` y `GET /api/adecuaciones/{id}`, enlace de curso solo en competencias/conocimientos
- [X] T057 [P] [US2] Ampliar `backend/tests/Feature/Desarrollo/Rutas/MiRutaTest.php` para comprobar que `puede_ver_adecuacion` es `true` solo si hay nivel objetivo (R19)

### Implementation for User Story 2

- [X] T058 [P] [US2] Crear `$D/Application/DTO/MiAdecuacionDTO.php` y `$D/Presentation/Resources/MiAdecuacionResource.php` (forma «propia» de `contracts/api.md`: resultados sin cumplimiento por resultado ni puntajes, brechas con tiempo de cierre y `curso`, aviso fijo)
- [X] T059 [US2] Crear `$D/Application/UseCases/ObtenerMiAdecuacion.php` (colaborador desde la sesión con `CuentaColaboradorInterface`; adecuaciones vigentes con nombres de puestos en lote; `curso.ruta = /formacion?competencia=<id>` solo para competencias y conocimientos, con `curso = null` y mensaje «Aún no hay cursos sugeridos» cuando `DesarrolloFacade::competenciaTieneOferta` devuelve `false`; degradación con `fuentes_no_disponibles`)
- [X] T060 [US2] Crear `$D/Presentation/Controllers/MiAdecuacionController.php` y registrar `GET /mi-adecuacion` (`permiso:desarrollo.adecuacion.ver_propio`) antes de `/{id}` en `$D/Routes/api.php`
- [X] T061 [US2] Modificar `$D/Application/UseCases/ObtenerMiRuta.php` para que `puede_ver_adecuacion` sea `true` cuando la inscripción tiene nivel objetivo (el frontend de Rutas ya enlaza a `/adecuacion`)
- [X] T062 [P] [US2] Crear `$F/hooks/useMiAdecuacion.ts` (clave `adecuacion/mia`, parámetro `puesto` en la URL) y `$F/components/TarjetaDeDimensionColaborador.tsx` (cabecera con título y porcentaje, filas con nombre, diferencia «−1» y resumen «Nivel actual: C · Requerido: B · Diferencia: −1», barras de «Tu nivel actual» y «Requisito del puesto»; tarjeta informativa si Conocimientos está desactivada), en el orden Experiencia, Conocimientos, Competencias
- [X] T063 [P] [US2] Crear `$F/components/ListaLoQueMeFalta.tsx` («Lo que me falta»: dimensión, texto de la brecha, tiempo estimado de cierre y «Ver cursos sugeridos» con `aria-label` «Ver cursos sugeridos para {elemento}» como enlace; «Aún no hay cursos sugeridos» sin oferta; en experiencia «Se desarrolla con práctica; revisa tu plan de desarrollo»; vacío «Cumples todos los requisitos de este puesto.») y `$F/components/ProgramaDeTalentosInfo.tsx` («Por ahora no estás incluido en un Programa de Talentos.»)
- [X] T064 [US2] Completar `$F/pages/MiAdecuacionPage.tsx` según `AdecuacionColaborador.dc.html`: cabecera «Mi adecuación a {puesto}», aviso permanente, selector «Puesto a comparar», insignia de preparación, botón «Mi ruta de carrera» (enlace a `/rutas`), tarjetas, «Lo que me falta», sección de Programa de Talentos y estados loading/vacío («Aún no tienes una adecuación…»)/error con «Reintentar». **Sin puntajes**
- [X] T065 [P] [US2] Pruebas de componentes: `$F/components/ListaLoQueMeFalta.dom.test.tsx` (enlace al catálogo, mensaje de experiencia, sin oferta) y `$F/pages/MiAdecuacionPage.dom.test.tsx` (selector entre puestos, estado vacío, ausencia de puntajes, aviso permanente)
- [X] T066 [US2] E2E del flujo principal en `frontend/e2e/adecuacion.spec.ts`: el administrador genera una adecuación con tiempos de cierre (colaborador con vínculo vigente preparado sin `IngresosSeeder`), cierra sesión, entra el colaborador y ve «Mi adecuación» con la brecha, el tiempo de cierre, el enlace «Ver cursos sugeridos» y el aviso; el colaborador no ve pestañas de gestión

**Checkpoint**: US1 y US2 funcionan; el flujo principal está cubierto de punta a punta

---

## Phase 5: User Story 3 - Consultar el historial de evaluaciones (Priority: P2)

**Goal**: El administrador filtra el historial de adecuaciones y abre cada detalle en solo lectura.

**Independent Test**: Con varias adecuaciones de distintos colaboradores, puestos y preparaciones, aplicar cada filtro y verificar lista y conteos; abrir un detalle y comprobar que no tiene acciones de edición.

### Tests for User Story 3

- [X] T067 [P] [US3] Pruebas Feature en `backend/tests/Feature/Desarrollo/Adecuacion/ListarAdecuacionesTest.php` (orden descendente, paginación `per_page` 1–50, filtros `fecha_desde`/`fecha_hasta`/`colaborador_id`/`puesto_objetivo_id`/`preparacion` solos y combinados, `es_vigente`, sin N+1) y `ObtenerAdecuacionTest.php` (detalle con resultados, `generada_por`, `acciones` deshabilitadas con motivo, 404 de otro tenant, 403 al colaborador)

### Implementation for User Story 3

- [X] T068 [P] [US3] Crear `$D/Application/UseCases/ListarAdecuaciones.php` y `$D/Application/UseCases/ObtenerAdecuacion.php` (nombres de colaborador, puestos y usuario en lote por puertos), `$D/Presentation/Requests/ListarAdecuacionesRequest.php` y los Resources `ResumenAdecuacionResource`/`DetalleAdecuacionResource`
- [X] T069 [US3] Añadir `index` y `show` a `AdecuacionesController` y registrar `GET /` y `GET /{id}` (`permiso:desarrollo.adecuacion.ver`, `whereNumber('id')`) en `$D/Routes/api.php`
- [X] T070 [P] [US3] Crear `$F/hooks/useEvaluaciones.ts` (listado con filtros y página en la URL; detalle) y `$F/components/BarraDeFiltrosEvaluaciones.tsx` (`Desde`, `Hasta`, `Colaborador`, `Puesto objetivo`, `Preparación`, «Limpiar filtros»)
- [X] T071 [US3] Crear la pestaña `$F/components/PestanaEvaluaciones.tsx` (tabla `Código · Fecha · Colaborador · Puesto objetivo · Preparación · Brechas` con `Pagination`, vacío y error con «Reintentar», sin botones de editar ni eliminar) y la página `$F/pages/DetalleAdecuacionPage.tsx` (`/adecuacion/evaluaciones/:id`: tres paneles en solo lectura y «Generada por {usuario} el {fecha}»); integrarlas en `AdecuacionPage`
- [X] T072 [P] [US3] Pruebas de componentes `$F/components/PestanaEvaluaciones.dom.test.tsx` (filtros en la URL, vacío, ausencia de acciones de edición) y `$F/pages/DetalleAdecuacionPage.dom.test.tsx`

**Checkpoint**: US1–US3 independientes y completas

---

## Phase 6: User Story 4 - Comparar candidatos para un puesto (Priority: P2)

**Goal**: El administrador compara candidatos con un ranking ponderado calculado al vuelo, con Incompletos y «sin adecuación» aparte.

**Independent Test**: Con tres candidatos con adecuación vigente (uno Incompleto) y uno sin adecuación, el ranking coincide con el cálculo a mano, el Incompleto va aparte, el sin adecuación se señala y cambiar los pesos no modifica los parámetros guardados.

### Tests for User Story 4

- [X] T073 [P] [US4] Pruebas Feature en `backend/tests/Feature/Desarrollo/Adecuacion/CompararCandidatosTest.php`: puntaje 75 con 40/30/30 (ejemplo de la spec), tope 1 por requisito, dimensión no evaluada excluida con pesos reescalados, `incompletos` sin puntaje, `sin_adecuacion`, usa la vigente de cada candidato, 422 por pesos ≠ 100 / negativos / ids repetidos / menos de 2 o más de 20 candidatos / puesto inexistente, pesos de la consulta no alteran `adecuacion_parametros`, no se guarda nada, 403 sin `comparar`

### Implementation for User Story 4

- [X] T074 [P] [US4] Crear `$D/Application/UseCases/CompararCandidatos.php` (vigentes por candidato con `vigentesDeCandidatos`; `CalculadorDeCumplimiento` y `CalculadorDePuntaje`; orden por puntaje descendente, desempate por menos brechas y luego por nombre), `$D/Presentation/Requests/CompararCandidatosRequest.php` y `$D/Presentation/Resources/RankingResource.php`
- [X] T075 [US4] Añadir `comparar` a `AdecuacionesController` y registrar `GET /comparar` (`permiso:desarrollo.adecuacion.comparar`) antes de `/{id}`
- [X] T076 [P] [US4] Crear `$F/hooks/useComparacion.ts` (puesto, candidatos y pesos en la URL) y ampliar `$F/validation.ts` con la regla de pesos (enteros ≥ 0 que suman 100) y su prueba
- [X] T077 [P] [US4] Crear `$F/components/PanelDePesosYPuesto.tsx` («Puesto y pesos»: Select «Puesto a cubrir», `Experiencia (%)`, `Conocim. (%)`, `Compet. (%)`, texto «Suma: {n} %» con error si ≠ 100 y la nota de que los pesos solo aplican a esta consulta), `$F/components/SelectorDeCandidatos.tsx` («Candidatos ({n})» con chips `aria-pressed` y «Selecciona al menos dos.») y `$F/components/TablaDeRanking.tsx` (`N.º · Candidato · Exp./Conoc./Comp. · Puntaje ponderado con barra · Preparación · Ver diagnóstico`, bloques «Preparación incompleta (sin puntaje)» y «Sin adecuación para este puesto» con enlace «Generar adecuación»)
- [X] T078 [US4] Crear la pestaña `$F/components/PestanaComparar.tsx` e integrarla en `AdecuacionPage` (vacío «Selecciona al menos dos candidatos y pesos que sumen 100 para ver el ranking.»; «Ver diagnóstico» abre `/adecuacion/evaluaciones/:id`)
- [X] T079 [P] [US4] Pruebas de componentes `$F/components/PanelDePesosYPuesto.dom.test.tsx` (suma ≠ 100) y `$F/components/PestanaComparar.dom.test.tsx` (ranking, incompletos, sin adecuación)

**Checkpoint**: US1–US4 funcionan de forma independiente

---

## Phase 7: User Story 5 - Ver el mapa de talento (Priority: P2)

**Goal**: Tabla con la adecuación vigente de cada colaborador por puesto objetivo, filtrable por departamento, ruta y preparación.

**Independent Test**: Con adecuaciones de dos departamentos y dos rutas, solo aparece la vigente por colaborador y puesto, brechas y tiempo máximo son correctos y cada filtro reduce la tabla como se espera.

### Tests for User Story 5

- [X] T080 [P] [US5] Pruebas Feature en `backend/tests/Feature/Desarrollo/Adecuacion/MapaDeTalentoTest.php`: una sola fila por `(colaborador, puesto objetivo)` con la más reciente, una fila por cada puesto objetivo distinto, `brechas` = resultados Con brecha, `tiempo_maximo_cierre_meses` = máximo (nulo sin brechas), filtros `departamento_id`/`ruta_id`/`preparacion`, `kpis` sobre el conjunto filtrado, adecuación por puesto directo sin ruta, sin N+1, degradación con `fuentes_no_disponibles`, 403 sin `mapa`

### Implementation for User Story 5

- [X] T081 [P] [US5] Crear `$D/Application/UseCases/ObtenerMapaDeTalento.php` (vigentes con filtros; nombres, puestos, departamentos y rutas en lote), `$D/Presentation/Requests/MapaDeTalentoRequest.php` y `$D/Presentation/Resources/MapaResource.php`
- [X] T082 [US5] Añadir `mapa` a `AdecuacionesController` y registrar `GET /mapa` (`permiso:desarrollo.adecuacion.mapa`) antes de `/{id}`
- [X] T083 [P] [US5] Crear `$F/hooks/useMapaDeTalento.ts` (filtros y página en la URL) y `$F/components/KpisDelMapa.tsx` (Lista, En desarrollo, No lista, Incompleta con cantidad y nota)
- [X] T084 [P] [US5] Crear `$F/components/BarraDeFiltrosMapa.tsx` (`Departamento`, `Ruta`, `Preparación`, «Limpiar filtros» visible con filtros activos, resumen `role="status"`) y `$F/components/TablaDelMapa.tsx` (columnas `Colaborador · Ruta · Puesto actual → objetivo · Exp./Conoc./Comp. · Preparación · Brechas · Tiempo máx. (meses) · Programa de Talentos «—» · Acciones`; «Diagnóstico» abre el detalle e «Incluir» deshabilitado con motivo; vacío «Ninguna persona coincide con los filtros.»; nota al pie)
- [X] T085 [US5] Crear la pestaña `$F/components/PestanaMapa.tsx` e integrarla en `AdecuacionPage` (con `Pagination`, estados loading/vacío/error)
- [X] T086 [P] [US5] Pruebas de componentes `$F/components/TablaDelMapa.dom.test.tsx` y `$F/components/PestanaMapa.dom.test.tsx` (filtros en la URL, «Limpiar filtros», vacío)

**Checkpoint**: US1–US5 funcionan de forma independiente

---

## Phase 8: User Story 6 - Mantener los parámetros de la adecuación (Priority: P3)

**Goal**: El administrador consulta y edita pesos y umbral, con historial de cambios.

**Independent Test**: Cambiar el umbral de 12 a 6 genera una entrada de historial; una brecha de 8 meses generada después es «No lista»; guardar pesos que suman 90 se rechaza.

### Tests for User Story 6

- [X] T087 [P] [US6] Pruebas Feature en `backend/tests/Feature/Desarrollo/Adecuacion/ParametrosDeAdecuacionTest.php`: valores iniciales 40/30/30 y 12, `PUT` válido con una fila de historial por campo cambiado (usuario, fecha, anterior, nuevo), 422 por suma ≠ 100 / peso negativo o no entero / umbral < 1 o no entero, no recalcula adecuaciones guardadas, el nuevo umbral rige para las siguientes, 403 sin `parametros`

### Implementation for User Story 6

- [X] T088 [P] [US6] Crear `$D/Application/UseCases/ObtenerParametrosDeAdecuacion.php` y `$D/Application/UseCases/ActualizarParametrosDeAdecuacion.php` (usa `ReglasDeParametrosDeAdecuacion`; historial en la misma transacción), `$D/Presentation/Requests/ActualizarParametrosRequest.php` y `$D/Presentation/Resources/ParametrosResource.php`
- [X] T089 [US6] Añadir `parametros` y `actualizarParametros` a `AdecuacionesController` y registrar `GET /parametros` y `PUT /parametros` (`permiso:desarrollo.adecuacion.parametros`) antes de `/{id}`
- [X] T090 [P] [US6] Crear `$F/hooks/useParametrosDeAdecuacion.ts` (lectura y mutación sin reintento que invalida `adecuacion/parametros`) y ampliar `$F/validation.ts` con pesos (suma 100) y umbral (entero ≥ 1), con su prueba
- [X] T091 [US6] Crear `$F/components/FormularioDeParametros.tsx` (`Competencias (%)`, `Conocimientos (%)`, `Experiencia (%)` con indicador de suma en vivo, `Umbral de preparación (meses)`, «Guardar cambios», errores 422 por campo, valores conservados) y `$F/components/HistorialDeParametros.tsx` (`Fecha · Usuario · Campo · Valor anterior · Valor nuevo`, vacío); crear la pestaña `PestanaParametros.tsx` e integrarla en `AdecuacionPage` (solo con `desarrollo.adecuacion.parametros`)
- [X] T092 [P] [US6] Pruebas de componentes `$F/components/FormularioDeParametros.dom.test.tsx` (suma ≠ 100, umbral inválido, errores por campo) y `$F/components/PestanaParametros.dom.test.tsx`

**Checkpoint**: US1–US6 funcionan de forma independiente

---

## Phase 9: User Story 7 - Continuar hacia un plan de desarrollo o Programa de Talentos (Priority: P3)

**Goal**: Tras guardar, los botones «Crear plan de desarrollo» e «Incluir en Programa de Talentos» quedan deshabilitados con su motivo («No hay brechas» o «Disponible cuando exista el Plan de Desarrollo Individual»).

**Independent Test**: Guardar una adecuación con brechas y otra sin brechas y comprobar el estado y el texto explicativo de los botones en ambos casos.

- [X] T093 [P] [US7] Prueba Feature en `backend/tests/Feature/Desarrollo/Adecuacion/AccionesDeAdecuacionTest.php`: `acciones.crear_plan` y `acciones.incluir_programa` en el detalle siempre `habilitado = false` con motivo «No hay brechas» (sin brechas) o «Disponible cuando exista el Plan de Desarrollo Individual» (con brechas)
- [X] T094 [US7] Verificar/ajustar `DetalleAdecuacionResource` (backend) y `$F/components/ConfirmarAdecuacion.tsx` y `DetalleAdecuacionPage.tsx` para mostrar los botones deshabilitados con el motivo (`aria-disabled` y texto visible, no solo `title`)
- [X] T095 [P] [US7] Prueba de componente `$F/components/AccionesDeAdecuacion.dom.test.tsx` con ambos motivos

**Checkpoint**: Las siete historias completas

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Integraciones de solo lectura con otros módulos, verificaciones transversales y puertas de calidad

- [X] T096 [P] Crear `$D/Application/UseCases/ConsultarAdecuacionesParaOtrosModulos.php` con `ultimaAdecuacion` y `adecuacionesPorFiltros` (contrato de `contracts/facade.md`: solo cabeceras vigentes, sin nombres ni observaciones), exponerlos en `$D/Application/Services/DesarrolloService.php` y declarar ambos en el docblock `@method` de `$D/Presentation/Facades/DesarrolloFacade.php`; pruebas de contrato en `backend/tests/Feature/Desarrollo/Adecuacion/AdecuacionFacadeTest.php` (forma, vigente = más reciente, filtros, tenant)
- [X] T097 [P] Prueba de arquitectura en `backend/tests/Unit/Desarrollo/Adecuacion/ArquitecturaDeAdecuacionTest.php`: `Domain` y `Application` no importan Laravel/Eloquent; ninguna clase de adecuación importa clases internas de `Estructura`, `GestionPersonal` ni `Rutas` (solo Facades); no existe ninguna ruta PUT/PATCH/DELETE sobre `/api/adecuaciones/{id}`
- [X] T098 [P] Prueba transversal de privacidad en `backend/tests/Feature/Desarrollo/Adecuacion/PrivacidadDelColaboradorTest.php` (SC-004): el colaborador autenticado no obtiene puntajes, rankings ni datos de otras personas en ningún endpoint, incluido probar ids ajenos
- [X] T099 [P] Pruebas de rendimiento ligeras en `backend/tests/Feature/Desarrollo/Adecuacion/RendimientoDeAdecuacionTest.php`: el mapa y la comparación no ejecutan consultas proporcionales al número de filas (conteo de consultas) para 50 colaboradores, y que la comparación de 20 candidatos y el mapa de 500 filas (SC-003, SC-008) respondan en menos de 3 s con una cota de tiempo razonable en la prueba
- [X] T100 Accesibilidad y responsive: revisar con teclado pestañas, diálogos y tablas, foco visible, contraste en tema claro y oscuro, y sin scroll horizontal entre 320 y 1440 px en `AdecuacionPage` y `MiAdecuacionPage`; corregir lo que falle
- [X] T101 Comparar la implementación con `design/AdecuacionDiagnostico.dc.html`, `AdecuacionComparar.dc.html`, `AdecuacionMapa.dc.html` y `AdecuacionColaborador.dc.html` (orden de paneles, textos, estructura, estados); registrar en `design/DESIGN.md` cualquier ajuste visual antes de aplicarlo
- [X] T102 Ejecutar las puertas de calidad: en `backend/` `vendor/bin/pest`, `vendor/bin/pint --test` y el análisis estático disponible; en `frontend/` `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `npx playwright test adecuacion`. Distinguir fallos preexistentes anotados en T001 de los propios
- [X] T103 Recorrer los 12 escenarios de `quickstart.md` con el seeder y confirmar el resultado esperado; anotar cualquier discrepancia
- [X] T104 Actualizar la memoria del proyecto `proyecto-speckit-020.md` con el estado final, los fallos preexistentes de la línea base y las trampas del entorno encontradas

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias
- **Foundational (Phase 2)**: depende de Setup; **bloquea todas las historias**
- **US1 (Phase 3)** y **US2 (Phase 4)**: dependen de Foundational; US2 necesita adecuaciones guardadas (se prueban con el seeder o con fixtures, pero el E2E T066 usa US1)
- **US3–US6 (Phases 5–8)**: dependen de Foundational; cada una es independiente de las demás (solo comparten `AdecuacionesController`, `Routes/api.php` y `AdecuacionPage`, por lo que sus ediciones a esos archivos se hacen en serie)
- **US7 (Phase 9)**: depende de US1 y US3 (detalle)
- **Polish (Phase 10)**: depende de las historias deseadas

### Within Each Story

- Pruebas primero (deben fallar) → DTO/servicios → casos de uso → requests/resources → controlador y rutas → frontend (hooks → componentes → página) → pruebas de componentes
- T040 depende de T012, T014, T020–T022, T024; T043 depende de T040, T013 y T017; T046 depende de T042–T045

### Parallel Opportunities

- Foundational: T005–T008 (migraciones y modelos), T009–T016 (dominio y sus pruebas), T020–T022, T024, T025 y T029–T033, T035 se pueden hacer en paralelo (archivos distintos)
- US1: T036–T038, T039, T041, T044, T045, T048–T053 en paralelo
- Tras Foundational, un equipo puede asignar US3, US4, US5 y US6 a personas distintas coordinando los archivos compartidos

### Parallel Example: User Story 1

```text
Tarea: "Pruebas Feature PrevisualizarAdecuacionTest.php"  (T036)
Tarea: "Pruebas Feature GenerarAdecuacionTest.php"       (T037)
Tarea: "Prueba unitaria ArmadorDeDiagnosticoTest.php"    (T038)
Tarea: "Componentes SelectorDeColaboradorYObjetivo / ResumenDeAdecuacion / TablaDeBrechas" (T050–T053)
```

---

## Implementation Strategy

### MVP First (US1 + US2)

1. Completar Phase 1 y Phase 2.
2. Completar US1 (generar) y US2 (Mi adecuación) y ejecutar el E2E T066.
3. **Parar y validar**: el flujo de punta a punta funciona y el colaborador no ve puntajes.

### Incremental Delivery

1. Setup + Foundational → base lista.
2. US1 + US2 → MVP (generar y consultar).
3. US3 (historial) → auditoría de diagnósticos.
4. US4 (comparar) y US5 (mapa) → ayuda a la decisión.
5. US6 (parámetros) → ajuste de pesos y umbral.
6. US7 → botones del PDI deshabilitados con su motivo.
7. Polish → Facades para Reportes y PDI, calidad transversal.

### Notas

- Pendiente de confirmar con el equipo (research.md): que la DNF sea fuente válida de nivel actual mientras Personal no lo exponga (R3) y si la experiencia dentro de la empresa suma a los años acumulados (R5). Si se cambia la fuente, solo se reemplaza el adaptador.
- Confirmar con el usuario antes de commitear; esta lista no incluye commits.

---

## Notas de implementación

- **Sin clases `Resource`/DTO por respuesta**: como en la 019, los casos de uso devuelven arreglos con la forma de `contracts/api.md` y los controladores solo los serializan. El único DTO es `DiagnosticoDTO` (el diagnóstico calculado, aún sin guardar); T039, T045 y T058 se cumplen con esa forma de arreglo.
- **Cálculo de cumplimiento «actual»**: `cumplimiento_por_dimension_actual` y `cumplimiento_actual` (informativos) salen de `requerido_actual_*`; si el puesto actual no exige un requisito, esa dimensión queda en `—`.
- **Opciones generales**: `GET /api/adecuaciones/opciones` sin `colaborador_id` devuelve además `puestos`, `departamentos` y `rutas` para los filtros de las pestañas (no estaba en el contrato original); el selector de colaborador usa hasta 200 colaboradores con búsqueda local.
- **Pestañas**: el orden del tablero es Diagnóstico, Comparar candidatos, Mapa de talento y, añadidas, Evaluaciones y Parámetros; al cambiar de pestaña la URL se reinicia con solo `pestana`.
- **E2E**: `frontend/e2e/adecuacion.spec.ts`. En esta máquina `IngresosSeeder` falla (índice `posiciones_una_maxima_autoridad`), por lo que se corrió con una configuración temporal que siembra cada seeder por separado; esa configuración (`frontend/playwright.tmp.config.ts`) se elimina al terminar.
- **Línea base** (T001): Pest tenía 52 fallos previos (`Estructura/Unidades` e `Ingresos`) y `tsc -b` 23 errores previos (`micro-learning`, `estructura/unidades`); ninguno es de esta feature.
- **Cierre (T100–T104)**: se midió el scroll horizontal de las cinco pestañas y de «Mi adecuación» a 320, 768 y 1440 px (tras corregir las pestañas con `scrollable` y los selectores con `min-w-0`) y se revisaron capturas en tema claro y oscuro frente a los tableros; no se hizo una auditoría manual completa con lector de pantalla. Los 12 escenarios de `quickstart.md` quedan cubiertos por Pest, Vitest y los 3 E2E (no hubo un recorrido manual aparte).
- **Resultados finales**: Pest 2162 pruebas, solo los mismos 52 fallos previos; Vitest de la feature 92/92 (el resto de la suite conserva 13 archivos con fallos previos en `estructura/unidades`, `micro-learning`, `catalogo-formativo` y `rutas`, sin tocar); Pint limpio; ESLint y `tsc` sin errores propios (los previos son de `micro-learning` y `estructura/unidades`); `vite build` compila; E2E `adecuacion.spec.ts` 3/3. `e2e/rutas.spec.ts` ya fallaba antes (falta el `role="note"` en la lista de rutas del administrador) y solo se actualizó su aserción de «Ver mi adecuación».
