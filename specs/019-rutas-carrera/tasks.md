---

description: "Tareas de la feature 019-rutas-carrera"
---

# Tasks: Rutas y planes de carrera

**Input**: Documentos de diseño en `/specs/019-rutas-carrera/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/rutas-api.md, contracts/rutas-puertos-y-facade.md, quickstart.md. Requiere 000-login, 004-catalogo-puestos y 001 (puestos y competencias en `EstructuraFacade`), 005-unidad-organizacional (unidades = departamentos), 005-fichas-colaboradores y 006-ingresos-reingresos (`PersonalFacade`, vínculos laborales, `colaborador_cuentas`) y 017/018 (patrón de módulo `Desarrollo`) ya implementadas.

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario (US1–US5 de spec.md) para implementarlas y probarlas de forma independiente. La feature añade cuatro tablas propias al módulo `Desarrollo` y **amplía `DesarrolloFacade`** con dos métodos de solo lectura (R2). No toca tablas ni clases internas de otros módulos.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1–US5)
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Desarrollo/…`. Abreviatura **`$D`** = `backend/app/Modules/Desarrollo`
- Frontend: `frontend/src/modules/desarrollo/rutas/…` (reutiliza `frontend/src/shared/ui/…`). Abreviatura **`$F`** = `frontend/src/modules/desarrollo/rutas`
- Pruebas backend: `backend/tests/Unit/Desarrollo/Rutas/` y `backend/tests/Feature/Desarrollo/Rutas/`; pruebas de componentes: `*.dom.test.tsx` junto al código (con `// @vitest-environment jsdom`); E2E en `frontend/e2e/rutas.spec.ts`
- Regla de módulos: Rutas **no lee tablas de Estructura ni de Personal**; usa `PuestosParaRutasInterface` (nuevo) y los puertos ya existentes de colaboradores y departamentos (`ColaboradoresVigentesInterface`, `CuentaColaboradorInterface`, `NombresDeReferenciaInterface`, `DepartamentoDeColaboradorInterface`, `NombresDeUsuarioInterface`, `DepartamentosInterface`), cuyos adaptadores llaman a `EstructuraFacade` y `PersonalFacade`. `puesto_id`, `departamento_id`, `colaborador_id` y los ids de usuario son enteros **sin clave foránea** hacia otros módulos; entre las cuatro tablas nuevas las FK son simples, **sin cascada** ni soft deletes
- Valores en base de datos (sin tildes; la interfaz muestra con tilde): `tipo` ∈ `Especialista` | `Gerencial` | `Interareas` | `JovenesProfesionales`; estado de ruta ∈ `Borrador` | `Vigente` | `Archivada`; estado de inscripción ∈ `Activa` | `Finalizada` | `Retirada`; tipo de historial ∈ `Ruta` | `Nivel` | `Inscripcion`; roles: capacidad `admin` (Administrador) y rol `user` (Colaborador), excluyentes
- Permisos: catálogo en código (`ClaveDePermisoRutas`) con las claves `desarrollo.rutas.ver`, `.gestionar`, `.inscribir`, `.inscripciones`, `.historial` y `.ver_propio`; `admin` recibe las cinco primeras y `user` solo `ver_propio`; un único middleware `permiso:<clave>` autoriza cada ruta (R4)
- Reutilizar sin modificar: `Button`, `Badge`, `Alert`, `ConfirmDialog`, `Modal`, `SelectField`, `SearchField`, `TextField`, `TextareaField`, `Tabs`, `Pagination`, `DataTable`, `EmptyState`, `ErrorState`, `Skeleton` y `AppShell`. No crear componentes equivalentes
- En frontend, `rutas` **no importa nada** de `actividades`, `reportes`, `plan-formacion`, `micro-learning`, `dnf` ni `catalogo-formativo`, salvo `modules/auth/login` (rol); copiar el patrón de `RequireRole` de `actividades` en lugar de importarlo
- Guía visual: `design/RutasAdmin.dc.html` (administrador: lista, modal «Nueva ruta», editor con pestañas Escalera y Participantes) y `design/RutasColaborador.dc.html` (colaborador) son **referencia primordial, no código para copiar**; en estilos manda `design/DESIGN.md`. **Manda la spec sobre los tableros** (plan.md, tabla «Diferencias entre tableros y spec»): se omiten «Ver como colaborador», el menú de prototipo, la duración en meses, «Registrar mi interés», «Mis propuestas de interés» y el panel de permisos simulados; «Área» pasa a «Departamento»; se añade la pestaña Historial y la sección «Rutas disponibles»
- **Competencias y conocimientos (R3, Opción A)**: solo las **competencias** del puesto llevan grado A–D y diferencial. La tarjeta «Conocimientos» es **informativa** (requisitos de Formación y Capacitación del puesto como texto, sin grado, sin comparación, sin diferencial). La bandera `desarrollo.conocimientos_con_grado` queda en `false` y **su rama `true` no se implementa**. Ninguna tarea crea tipo ni campo de «conocimiento con grado». `spec.md` conserva frases desalineadas (se corrigen con `/speckit-clarify`); donde difieran, manda research.md R3
- Insumo `plan-rutas.md` y plan.md: donde difieren, **prevalecen plan.md, research.md y data-model.md** (`tenant_id` añadido, `version` en rutas, `nombre_clave`, endpoints `permisos`, `opciones` y `colaboradores`)
- Sin dependencias nuevas
- Lecciones previas: en E2E localizar campos con `getByRole('textbox', { name, exact: true })`; escribir archivos con acentos con la herramienta Write (los heredocs de Bash fallan; tampoco hay Python); ver la memoria «Entorno local de pruebas» (OneDrive/Windows) al correr Pest, Vitest y Playwright; no asumir `IngresosSeeder` en E2E (feature 018)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Línea base verde y diseño registrado antes de tocar código

- [ ] T001 Verificar la línea base: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run` y `npx playwright test` en `frontend/` (sin cambios de archivos). Anotar los fallos preexistentes de la memoria del proyecto para no atribuirlos a esta feature
- [X] T002 Actualizar `design/DESIGN.md` (Constitución III) **antes** de cualquier tarea de frontend. **Aviso**: el archivo tiene cambios sin confirmar y su encabezado `# Design system — HRMS` está cortado («ign system — HRMS» tras un bloque de la feature 017); revisar ese estado con el usuario antes de editar y no sobrescribir sus cambios. Contenido a registrar: en §2 los colores de badge de estado de ruta (Borrador `--neutral`/`--neutralsoft`, Vigente `--accent`/`--soft`, Archivada `--warning`/`--warningsoft`), de estado de inscripción (Activa `--accent`/`--soft`, Finalizada `--neutral`/`--neutralsoft`, Retirada `--danger`/`--dangersoft`) y de tipo de ruta (tokens existentes); en §4 los componentes **Escalera de niveles** (nivel 1 abajo, actual resaltado, objetivo marcado), **Detalle de nivel en tres tarjetas** (Competencias con grado y diferencial · Conocimientos «Informativo» · Experiencia), **Cantera por nivel** y **Aviso «guía, no promesa»**; en §5 los patrones «Rutas de carrera (administrador)» y «Mi ruta de carrera»; las filas de la tabla «Diferencias entre tableros y spec» de plan.md. Sin cambios si ya está completo Registrar también el estado «Nivel con puesto inactivo» (advertencia con `--warning`/`--warningsoft` y texto).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Tablas, modelos, entidades, puertos, repositorio base, autorización por permiso, rutas protegidas y piezas de frontend que usan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend – datos

- [X] T003 Crear `backend/database/migrations/2026_10_23_000001_create_rutas_table.php` según data-model.md: `id`; `tenant_id` entero sin signo con índice (sin FK); `numero` entero sin signo (secuencial por tenant) con único `(tenant_id, numero)`; `codigo` `string(10)` (`CAR-0001`, generado desde `numero`) con único `(tenant_id, codigo)`; `nombre` `string(80)`; `nombre_clave` `string(80)` con único `(tenant_id, nombre_clave)`; `tipo` `string(20)` ∈ Especialista | Gerencial | Interareas | JovenesProfesionales; `descripcion` `string(500)` anulable; `departamento_id` entero sin signo anulable (sin FK; debe ser nulo si el tipo es Interareas); `estado` `string(10)` por defecto `Borrador` con índice; `version` entero sin signo por defecto 1; `created_by` y `updated_by` enteros sin signo anulables; timestamps
- [X] T004 [P] Crear `backend/database/migrations/2026_10_23_000002_create_ruta_niveles_table.php`: `id`; `tenant_id` entero sin signo con índice; `ruta_id` FK a `rutas` **sin cascada**; `orden` entero pequeño sin signo (consecutivo desde 1); `puesto_id` entero sin signo (sin FK); `nombre` `string(80)`; `experiencia_minima_anios` `decimal(4,1)` anulable, entre 0 y 60 (validación); timestamps. Únicas `(ruta_id, orden)` y `(ruta_id, puesto_id)`
- [X] T005 [P] Crear `backend/database/migrations/2026_10_23_000003_create_ruta_inscripciones_table.php`: `id`; `tenant_id` entero sin signo con índice; `ruta_id` FK a `rutas` sin cascada; `colaborador_id` entero sin signo (sin FK); `nivel_actual_id` y `nivel_objetivo_id` FK anulables a `ruta_niveles` sin cascada; `estado` `string(10)` ∈ Activa | Finalizada | Retirada con índice; `motivo` `string(500)` anulable; `resuelta_by` entero sin signo anulable (nulo = sistema); `resuelta_at` datetime anulable; `created_by` entero sin signo anulable; timestamps. **Índice único parcial** `(ruta_id, colaborador_id)` donde `estado = 'Activa'`; índices `(tenant_id, colaborador_id)` y `(ruta_id, estado)`
- [X] T006 [P] Crear `backend/database/migrations/2026_10_23_000004_create_ruta_historial_table.php`: `id`; `tenant_id` entero sin signo con índice; `ruta_id` FK a `rutas` sin cascada; `inscripcion_id` entero sin signo anulable; `tipo` `string(12)` ∈ Ruta | Nivel | Inscripcion; `accion` `string(30)`; `valor_anterior` y `valor_nuevo` json anulables; `motivo` `string(500)` anulable; `user_id` entero sin signo anulable (nulo = sistema); `created_at` y **sin** `updated_at`; índice `(ruta_id, created_at)`
- [X] T007 [P] Crear los modelos Eloquent `$D/Infrastructure/Persistence/Eloquent/Models/RutaModel.php`, `RutaNivelModel.php`, `RutaInscripcionModel.php` y `RutaHistorialModel.php` (el de historial con `UPDATED_AT = null`; ninguno con soft deletes), siguiendo la convención de `ActividadModel`; casts de `decimal` y `json`
- [X] T008 [P] Escribir `backend/tests/Feature/Desarrollo/Rutas/RutasMigracionTest.php`: las cuatro tablas existen con sus columnas; `codigo` y `nombre_clave` únicos; `(ruta_id, orden)` y `(ruta_id, puesto_id)` únicos; el índice parcial rechaza dos inscripciones Activas del mismo colaborador en la misma ruta pero admite una Activa más una Retirada; el historial no tiene `updated_at`

### Backend – dominio, puertos, autorización y repositorio base

- [X] T009 [P] Crear los objetos de valor en `$D/Domain/ValueObjects/`: `TipoDeRuta` (enum con `etiqueta()` y `admiteDepartamento()`, falso para Interareas), `EstadoDeRuta`, `EstadoDeInscripcion`, `CodigoDeRuta` (formato `CAR-` + 4 dígitos) y `NombreDeRuta` (normaliza: recorta, colapsa espacios, máximo 80 caracteres, expone `clave()` en minúsculas)
- [X] T010 [P] Crear `$D/Domain/ValueObjects/ClaveDePermisoRutas.php`: enum con las seis claves `desarrollo.rutas.ver`, `.gestionar`, `.inscribir`, `.inscripciones`, `.historial` y `.ver_propio`
- [X] T011 [P] Crear las excepciones de dominio en `$D/Domain/Exceptions/`: `RutaNoEncontrada`, `RutaNoPermitida` (con `codigo` de motivo), `NivelEnUso`, `InscripcionNoEncontrada`, `InscripcionNoPermitida` (con `codigo` de motivo), `ConflictoDeVersion` y `RutaInvalida` (errores por campo)
- [X] T012 Crear las entidades en `$D/Domain/Entities/`: `Ruta` (raíz con sus niveles, `version`, `puedePublicarse()`), `NivelDeRuta`, `InscripcionARuta` (`alcanzar()`, `retirar(motivo)`, estados terminales) y `EventoDeRuta`; sin Laravel ni Eloquent
- [X] T013 Crear `$D/Domain/Repositories/RutasRepositoryInterface.php` con todas las operaciones que necesitan las historias (crear y guardar ruta con niveles en transacción, obtener, listar con filtros y recuentos, siguiente código, inscripciones con filtros, inscripción Activa de un colaborador en una ruta, niveles en uso, cantera, historial paginado y registro de eventos). No hay operaciones de borrado de rutas, inscripciones ni historial
- [X] T014 [P] Crear **solo** el puerto `$D/Application/Ports/PuestosParaRutasInterface.php` (`perfil`, `activos`, `estaActivo`) según `contracts/rutas-puertos-y-facade.md`. El perfil del puesto expone **solo** competencias con grado y requisitos de texto (`formacion`, `capacitacion`, `experiencia`); sin conocimientos con grado. **No se crea un puerto de colaboradores**: se reutilizan los existentes `ColaboradoresVigentesInterface` (búsqueda y vigencia), `CuentaColaboradorInterface` (usuario→colaborador), `NombresDeReferenciaInterface` (nombres de colaboradores y puestos), `DepartamentoDeColaboradorInterface` (departamento actual), `NombresDeUsuarioInterface` (creador y último modificador), `DepartamentosInterface` y `RelojInterface`
- [X] T015 [P] Crear `$D/Infrastructure/Adapters/PuestosParaRutasDesdeEstructura.php` (sobre `EstructuraFacade::getPuesto`, `getPuestosActivos` y `puestoEstaActivo`; mapea `grado_requerido` a `grado` y agrupa los requisitos por tipo; memoiza por petición); ante falla de Estructura lanza la excepción de información no disponible (los casos de uso la degradan, ver T034a). Los adaptadores de colaboradores ya existen y no se tocan
- [X] T016 Crear `backend/config/desarrollo.php` con la bandera `conocimientos_con_grado` (por defecto `false`, leída de `DESARROLLO_CONOCIMIENTOS_CON_GRADO`) y un comentario que la documente como **punto de extensión sin rama `true` implementada** (R3). Ningún código la lee salvo `PuestosParaRutasDesdeEstructura`, que ante `true` lanza un `LogicException` «no implementado»
- [X] T017 Crear `$D/Application/Services/AutorizadorDeRutas.php` (`permisosDe(Usuario)`: `admin` → `ver`, `gestionar`, `inscribir`, `inscripciones`, `historial`; `user` → `ver_propio`) y `$D/Presentation/Middleware/EnsurePermiso.php` (responde 401 sin sesión y 403 si falta la clave); registrar el alias `permiso` en `backend/bootstrap/app.php` junto a `admin`
- [X] T018 Crear `$D/Infrastructure/Persistence/Eloquent/Repositories/EloquentRutasRepository.php` con las operaciones base de T013 que comparten todas las historias (código siguiente con bloqueo dentro de la transacción, guardado de ruta con niveles conservando ids y órdenes temporales negativos al reordenar, registro de historial); las consultas específicas de cada historia se añaden en su fase. Registrar los bindings de `RutasRepositoryInterface`, `PuestosParaRutasInterface` en `$D/Infrastructure/Providers/DesarrolloServiceProvider.php`
- [X] T019 Crear `$D/Presentation/Controllers/RutasController.php` (solo `permisos`), `$D/Application/UseCases/ObtenerPermisosDeRutas.php` y declarar en `$D/Routes/api.php` el grupo `Route::prefix('api/rutas')` con `GET /permisos` (solo `auth:sanctum`), dejando las rutas fijas (`opciones`, `mi-ruta`) **antes** de `/{id}` y el grupo `api/inscripciones-ruta`; no existe ningún DELETE
- [X] T020 [P] Escribir `backend/tests/Feature/Desarrollo/Rutas/RutasPermisosTest.php` y `backend/tests/Unit/Desarrollo/Rutas/AutorizadorDeRutasTest.php`: sin sesión → 401; administrador recibe las cinco claves de gestión y consulta; colaborador solo `ver_propio`; `EnsurePermiso` responde 403 si falta la clave; `GET /api/rutas/permisos` devuelve las claves del usuario autenticado
- [X] T021 [P] Escribir `backend/tests/Unit/Desarrollo/Rutas/ValueObjectsTest.php`: `NombreDeRuta` normaliza espacios y mayúsculas para la clave; `CodigoDeRuta` rechaza formatos inválidos; `TipoDeRuta::Interareas` no admite departamento
- [X] T020a Crear los dobles de prueba de la feature: `backend/tests/Support/Desarrollo/PuestosParaRutasFalso.php` (implementa `PuestosParaRutasInterface` con puestos, competencias con grado y requisitos de texto configurables, y puestos inactivos o con falla), `backend/tests/Support/Desarrollo/RutasHelpers.php` (`rutasFalsos()` que enlaza los dobles junto a `DepartamentosFalsos` y `ColaboradoresVigentesFalso` ya existentes, `rutaDe()`, `nivelDe()`, `inscripcionDe()` y `cuerpoRuta()`) y registrarlo con `require_once` en `backend/tests/Pest.php` junto a los demás helpers. Las pruebas T020–T073 dependen de esta tarea

### Frontend – base común

- [X] T022 [P] Crear `$F/text.ts` (todo el texto visible en español: títulos, etiquetas, tipos, estados, mensajes de validación, estados vacío/error, `aviso` «Esta ruta es una guía de desarrollo; no implica la promesa de un puesto.», etiqueta «Informativo») y `$F/estilosEstado.ts` (colores de badge de estado de ruta, de inscripción y de tipo, solo con tokens existentes; el color nunca es la única señal)
- [X] T023 [P] Crear `$F/api.ts` (funciones tipadas sobre el cliente HTTP compartido para todos los endpoints de `contracts/rutas-api.md`; tipos `Ruta`, `Nivel`, `Inscripcion`, `EventoDeRuta`, `MiRuta`; sin `any`) y `$F/permisos.ts` (tipo de claves y `tienePermiso`)
- [X] T024 [P] Crear `$F/components/RequireRole.tsx` (patrón de `actividades`: elige vista por `useCurrentUser().data.role`; `role="status"` mientras carga y `role="alert"` sin permisos), `$F/components/RequirePermiso.tsx` (oculta hijos si falta la clave, usando `usePermisosRutas`), `$F/hooks/usePermisosRutas.ts` y `$F/hooks/claves.ts` (claves de TanStack Query)
- [X] T025 [P] Crear `$F/nav.tsx` con el ítem «Rutas de carrera» (administrador) / «Mi ruta de carrera» (colaborador) usando un icono de `shared/ui/icons`, y `$F/components/AvisoGuia.tsx` con el aviso permanente de `text.ts`
- [X] T026 Registrar en `frontend/src/App.tsx` el ítem `rutasNav` en `navItems` (después de `reportesNav`) y las rutas `rutas` (lista por rol vía `RequireRole`), `rutas/:id` (editor, solo administrador) y `mi-ruta` o equivalente del colaborador, con páginas esqueleto `RutasPage`, `RutaEditorPage` y `MiRutaPage` que se completan en cada historia
- [X] T027 [P] Escribir `$F/validation.test.ts` y `$F/validation.ts` (esquemas Zod: ruta con nombre obligatorio de máximo 80 caracteres, descripción de máximo 500, departamento no permitido para Interáreas; nivel con experiencia entre 0 y 60 con un decimal; motivo de retiro obligatorio de máximo 500) y `$F/components/RequirePermiso.dom.test.tsx` (oculta y muestra según la clave)

**Checkpoint**: tablas, autorización, puertos y base de frontend listos; las historias pueden comenzar

---

## Phase 3: User Story 1 - Definir una ruta de carrera con sus niveles (Priority: P1) 🎯 MVP

**Goal**: El administrador crea y edita rutas y arma su escalera de niveles; cada nivel muestra las competencias del puesto con grado y diferencial, la tarjeta informativa de conocimientos y la experiencia mínima.

**Independent Test**: Crear una ruta Especialista con tres niveles ligados a puestos con perfiles distintos y verificar código `CAR-0001`, rechazo de nombre duplicado y de puesto repetido o inactivo, el diferencial de competencias y de años en los niveles 2 y 3, la tarjeta «Informativo» sin grado, y el bloqueo de quitar o cambiar el puesto de un nivel en uso.

### Tests (US1)

- [X] T028 [P] [US1] Escribir `backend/tests/Unit/Desarrollo/Rutas/CalculadorDeDiferencialTest.php`: por competencia `nuevo`, `sube` (con `antes` y `delta`) e `igual` entre dos niveles consecutivos; el nivel 1 devuelve diferencial nulo; años adicionales de experiencia o `null` si falta alguno de los dos; los requisitos de texto no entran al cálculo Añade `baja` cuando el grado desciende y `quitada` para una competencia que tenía el nivel anterior y este no (contrato y R9).
- [X] T029 [P] [US1] Escribir `backend/tests/Unit/Desarrollo/Rutas/RutaEntityTest.php`: órdenes consecutivos desde 1; reordenar renumera sin huecos; puesto repetido en la misma ruta se rechaza; Interáreas rechaza departamento
- [X] T030 [P] [US1] Escribir `backend/tests/Feature/Desarrollo/Rutas/RutaCrearTest.php`: 201 en Borrador con código `CAR-NNNN` secuencial e inmutable; 422 por nombre vacío o duplicado sin distinguir mayúsculas ni espacios extremos, departamento con tipo Interáreas o inexistente/inactivo, puestos repetidos, inexistentes o inactivos; 403 al colaborador; el alta queda en el historial
- [X] T031 [P] [US1] Escribir `backend/tests/Feature/Desarrollo/Rutas/RutaActualizarTest.php`: conserva los ids de los niveles existentes; reordenar respeta el único `(ruta_id, orden)`; 409 si `version` no coincide; 422 si el orden no es consecutivo desde 1, si se quita un nivel referenciado por una inscripción, si se cambia el puesto de un nivel en uso (editar nombre y experiencia sí se permite) o si el reorden deja un objetivo Activo igual o anterior a su actual; cada cambio queda en el historial con valor anterior y nuevo
- [X] T032 [P] [US1] Escribir `backend/tests/Feature/Desarrollo/Rutas/RutaConsultarTest.php`: `GET /api/rutas/{id}` devuelve niveles ordenados con competencias (grado y diferencial), `conocimientos` con `informativo: true`, `formacion` y `capacitacion` sin grado ni diferencial, y `experiencia`; 404 si no existe; `GET /api/rutas/opciones` devuelve tipos, departamentos y puestos activos; creador y último modificador con fecha Si Estructura falla responde con `fuentes_no_disponibles` y sin inventar requisitos; un nivel cuyo puesto se desactivó trae `puesto.activo = false`; el creador y el último modificador traen nombre y fecha (FR-037).

### Implementation (US1)

- [X] T033 [US1] Crear `$D/Domain/Services/CalculadorDeDiferencial.php` (puro; compara competencias por grado y años de experiencia entre niveles consecutivos; R9)
- [X] T034 [US1] Ampliar `EloquentRutasRepository` con lectura de una ruta con niveles, recuento de inscritos activos por nivel y `nivelesEnUso`; crear los casos de uso `$D/Application/UseCases/CrearRuta.php`, `ActualizarRuta.php`, `ObtenerRuta.php` y `ObtenerOpcionesDeRutas.php` (validan con los puertos, calculan el diferencial con una lectura de perfil por puesto distinto, escriben el historial `creada`, `nivel_agregado`, `nivel_editado`, `nivel_reordenado`, `nivel_quitado` y `editada`) Resuelve `creada_por` y `modificada_por` con `NombresDeUsuarioInterface` (FR-037) y marca cada nivel con `puesto.activo` para la advertencia de puesto inactivo.
- [X] T034a [US1] Degradar las lecturas ante fuentes caídas: si `PuestosParaRutasInterface` falla, `ObtenerRuta` y `ObtenerMiRuta` devuelven la ruta y los niveles propios con `fuentes_no_disponibles: ["estructura"]`, sin competencias ni requisitos inventados; si Personal falla, la conciliación de bajas no retira a nadie y las listas muestran el colaborador como «No disponible». Probar ambos casos en `RutaConsultarTest` y `MiRutaTest`. En el frontend, `DetalleDeNivel` y `MiRutaPage` muestran un `Alert` neutro con «Reintentar» cuando llega `fuentes_no_disponibles`
- [X] T035 [US1] Crear `$D/Presentation/Requests/GuardarRutaRequest.php` (nombre máximo 80, descripción máximo 500, tipo del enum, niveles con `id?`, `orden`, `puesto_id`, `nombre` máximo 80 y `experiencia_minima_anios` entre 0 y 60, `version` en la actualización), ampliar `RutasController` con `opciones`, `show`, `store` y `update` (delgado, mapea excepciones a 404/409/422) y declarar las rutas con `permiso:desarrollo.rutas.gestionar` (`opciones`, `store`, `update`) y `permiso:desarrollo.rutas.ver` (`show`)

### Frontend (US1)

- [X] T036 [P] [US1] Crear `$F/hooks/useRuta.ts`, `useOpcionesDeRutas.ts` y `useGuardarRuta.ts` (sin reintentos en escrituras; invalidan lista y detalle)
- [X] T037 [P] [US1] Crear `$F/components/EscaleraDeNiveles.tsx` y `$F/components/SelectorDePuesto.tsx` según `RutasAdmin.dc.html`: nivel 1 abajo, el más alto arriba, botones «Agregar nivel arriba», «Subir», «Bajar» y «Quitar» (deshabilitados con explicación si el nivel está en uso), selección de nivel en la URL; el selector lista solo puestos activos que no estén ya en la ruta
- [X] T038 [P] [US1] Crear `$F/components/DetalleDeNivel.tsx` con tres tarjetas: **Competencias** (nombre, grado A–D y diferencial «+N (antes X)», «Nuevo requisito» o «Sin cambio», solo lectura), **Conocimientos** (requisitos de Formación y Capacitación como lista de texto con la etiqueta «Informativo», sin grado ni diferencial) y **Experiencia** (años mínimos editables del nivel con su diferencial en años y los requisitos de experiencia del puesto como texto); campos de nombre del nivel y experiencia mínima Un nivel con `puesto.activo = false` muestra una advertencia visible («El puesto de este nivel está inactivo; la ruta no podrá volver a publicarse hasta cambiarlo»), no solo color.
- [X] T039 [US1] Crear `$F/components/FormularioRutaModal.tsx` («Nueva ruta» y edición: nombre, tipo, departamento —deshabilitado para Interáreas—, descripción; errores por campo, valores conservados y envío bloqueado mientras está pendiente) y completar `$F/pages/RutaEditorPage.tsx` con la pestaña **Escalera de niveles**, `AvisoGuia`, datos de la ruta con creador y último modificador, y estados loading/vacío/error con «Reintentar»/sin permisos Si guardar responde 409 `version_desactualizada`, muestra un `Alert` de conflicto con «Recargar» y conserva lo escrito.
- [X] T040 [P] [US1] Escribir `$F/components/EscaleraDeNiveles.dom.test.tsx` y `$F/components/DetalleDeNivel.dom.test.tsx`: la escalera ordena el nivel más alto arriba y deshabilita «Quitar» en niveles en uso; el detalle muestra el grado y el diferencial solo en competencias, la tarjeta de conocimientos con la etiqueta «Informativo» y sin grado, y los años de experiencia Añadir la advertencia de puesto inactivo.

**Checkpoint**: US1 operativa y probada de forma independiente (MVP)

---

## Phase 4: User Story 2 - Publicar, archivar y listar rutas (Priority: P1)

**Goal**: El administrador publica, archiva y reactiva rutas y las ve en tarjetas agrupadas por tipo con filtros y búsqueda; las rutas nunca se eliminan.

**Independent Test**: Intentar publicar con un solo nivel (rechazo), publicar con dos niveles de puestos activos, archivar con inscritos (pide confirmación con la cantidad), volver a Vigente, y filtrar la lista por tipo, estado, departamento y nombre.

### Tests (US2)

- [X] T041 [P] [US2] Escribir `backend/tests/Unit/Desarrollo/Rutas/ReglasDePublicacionDeRutaTest.php`: Borrador→Vigente exige ≥ 2 niveles y todos con puesto activo; Vigente→Archivada; Archivada→Vigente con las mismas condiciones; Borrador→Archivada y transiciones inversas inválidas
- [X] T042 [P] [US2] Escribir `backend/tests/Feature/Desarrollo/Rutas/RutaEstadoTest.php`: 422 al publicar con menos de 2 niveles o con un puesto inactivo (indica qué falta); 200 al publicar; al archivar con inscripciones Activas sin `confirmar_inscritos` responde `422 { code: "requiere_confirmacion", inscritos }` y con la confirmación archiva conservando las inscripciones; 409 con `version` desactualizada; el cambio queda en el historial; no existe ningún DELETE (`DELETE /api/rutas/{id}` → 405/404)
- [X] T043 [P] [US2] Escribir `backend/tests/Feature/Desarrollo/Rutas/RutaListarTest.php`: filtros `tipo`, `estado`, `departamento_id` y `q` por nombre; recuentos `niveles_count` e `inscritos_count`; paginación; un permiso `ver` sin `gestionar` solo recibe rutas Vigentes; 403 al colaborador

### Implementation (US2)

- [X] T044 [US2] Crear `$D/Domain/Services/ReglasDePublicacionDeRuta.php` y los casos de uso `$D/Application/UseCases/CambiarEstadoDeRuta.php` (transacción con control de `version`, historial `estado_cambiado`) y `ListarRutas.php`; ampliar `EloquentRutasRepository` con el listado filtrado y los recuentos
- [X] T045 [US2] Crear `$D/Presentation/Requests/CambiarEstadoRutaRequest.php` y `FiltroRutasRequest.php`, ampliar `RutasController` con `index` y `cambiarEstado` y declarar `GET /api/rutas` (`permiso:desarrollo.rutas.ver`) y `PATCH /api/rutas/{id}/estado` (`permiso:desarrollo.rutas.gestionar`)

### Frontend (US2)

- [X] T046 [P] [US2] Crear `$F/hooks/useRutas.ts`, `useFiltrosRutas.ts` (búsqueda, tipo, estado y departamento en la URL) y `useCambiarEstadoRuta.ts`
- [X] T047 [P] [US2] Crear `$F/components/TarjetaDeRuta.tsx` y `$F/components/BarraDeFiltrosRutas.tsx` según `RutasAdmin.dc.html`: tarjetas con código, nombre, `Badge` de estado y de tipo, departamento, niveles y participantes, botón «Abrir»; filtros Tipo, **Departamento**, Estado, búsqueda y «Limpiar filtros»; sin duración en meses
- [X] T048 [US2] Completar `$F/pages/RutasPage.tsx` (lista agrupada por tipo con contador, «Nueva ruta» solo con `gestionar`, `AvisoGuia`, estados loading/vacío «Ninguna ruta coincide con los filtros»/error con «Reintentar»/sin permisos) y añadir en `RutaEditorPage` el selector de estado de la ruta con `ConfirmDialog` que informa la cantidad de inscritos al archivar El archivado usa el recuento de inscritos del detalle y, si el servidor responde `requiere_confirmacion`, reabre el diálogo con la cantidad recibida; un 409 muestra el `Alert` de conflicto con «Recargar».
- [X] T049 [P] [US2] Escribir `$F/components/TarjetaDeRuta.dom.test.tsx` y `$F/pages/RutasPage.dom.test.tsx`: agrupación por tipo, filtros desde la URL, «Nueva ruta» oculta sin permiso, y confirmación al archivar con la cantidad de inscritos

**Checkpoint**: US1 y US2 operativas

---

## Phase 5: User Story 3 - Inscribir colaboradores y fijar su nivel actual y objetivo (Priority: P1)

**Goal**: El administrador inscribe colaboradores activos en rutas Vigentes, cambia sus niveles, registra el nivel alcanzado y retira inscripciones con motivo; las bajas de colaboradores retiran sus inscripciones automáticamente.

**Independent Test**: Inscribir un colaborador (actual 1, objetivo 3), registrar el nivel alcanzado (pasa a 3, último → Finalizada), retirar otra inscripción sin y con motivo, y comprobar que dar de baja a un colaborador en Personal deja su inscripción Retirada con motivo «Baja del colaborador».

### Tests (US3)

- [X] T050 [P] [US3] Escribir `backend/tests/Unit/Desarrollo/Rutas/InscripcionARutaTest.php` y `ReglasDeInscripcionTest.php`: objetivo posterior al actual; nivel actual opcional; `alcanzar()` mueve actual := objetivo y deja objetivo nulo, o pasa a Finalizada con `resuelta_at` si era el último nivel; `retirar` exige motivo; Finalizada y Retirada no admiten cambios
- [X] T051 [P] [US3] Escribir `backend/tests/Feature/Desarrollo/Rutas/InscripcionCrearTest.php`: 201 Activa; 422 si la ruta no es Vigente, si el colaborador no está activo, si ya tiene una Activa en esa ruta (pero sí en otra), si los niveles son de otra ruta o si el objetivo no es posterior al actual; 404 si ruta o colaborador no existen; historial `inscrita`; 403 sin `inscribir`
- [X] T052 [P] [US3] Escribir `backend/tests/Feature/Desarrollo/Rutas/InscripcionNivelesTest.php`: cambiar niveles solo en Activas y en rutas no Archivadas; `alcanzar` con objetivo no último deja el objetivo nulo y con objetivo último pasa a Finalizada e incluye la nota informativa de Gestión de Personal; 422 sin objetivo o no Activa; `retirar` 422 sin motivo; historial `niveles_cambiados`, `nivel_alcanzado` y `retirada` con valores y motivo
- [X] T053 [P] [US3] Escribir `backend/tests/Feature/Desarrollo/Rutas/InscripcionConciliarBajasTest.php`: un colaborador sin vínculo vigente (según `PersonalFacade::vigentesPorIds`) pasa a Retirada con motivo «Baja del colaborador», `resuelta_at` y historial con `user_id` nulo; es idempotente; si Personal falla no retira a nadie; el comando `rutas:conciliar-bajas` produce el mismo efecto; listar inscripciones, ver una ruta y abrir «Mi ruta» la disparan
- [X] T054 [P] [US3] Escribir `backend/tests/Feature/Desarrollo/Rutas/InscripcionListarTest.php`: filtros `ruta_id`, `colaborador_id` y `estado`, paginación, nombres de colaborador y niveles; `GET /api/inscripciones-ruta/colaboradores` devuelve solo colaboradores vigentes con búsqueda `q`; 403 sin `inscripciones` o `inscribir`

### Implementation (US3)

- [X] T055 [US3] Crear `$D/Domain/Services/ReglasDeInscripcion.php` y ampliar `EloquentRutasRepository` con la inscripción Activa por (ruta, colaborador), el bloqueo de fila dentro de la transacción y el listado filtrado con niveles y estado
- [X] T056 [US3] Crear los casos de uso en `$D/Application/UseCases/`: `InscribirEnRuta.php`, `CambiarNivelesDeInscripcion.php`, `RegistrarNivelAlcanzado.php`, `RetirarInscripcion.php`, `ListarInscripcionesDeRuta.php`, `BuscarColaboradoresInscribibles.php` y `ConciliarBajasDeInscripciones.php` (R5: consulta `vigentesEntre`, retira con motivo «Baja del colaborador», historial del sistema; ante falla de la fuente no hace nada); `ListarInscripcionesDeRuta` y `ObtenerRuta` invocan la conciliación al inicio Usa los puertos existentes `ColaboradoresVigentesInterface` (vigencia y búsqueda) y `CuentaColaboradorInterface`, y `RelojInterface` para la fecha de vigencia.
- [X] T057 [US3] Crear `$D/Infrastructure/Console/ConciliarBajasRutasCommand.php` (`rutas:conciliar-bajas`), registrarlo en el proveedor y programarlo en el scheduler del proyecto si existe una convención previa (si no existe, dejar solo el comando)
- [X] T058 [US3] Crear `$D/Presentation/Requests/InscribirRequest.php`, `CambiarNivelesRequest.php` y `RetirarRequest.php` (motivo obligatorio de 1 a 500 caracteres), `$D/Presentation/Controllers/InscripcionesRutaController.php` y declarar las rutas `GET /api/inscripciones-ruta` (`permiso:desarrollo.rutas.inscripciones`), `GET /colaboradores`, `POST /`, `PATCH /{id}/niveles`, `PATCH /{id}/alcanzar` y `PATCH /{id}/retirar` (`permiso:desarrollo.rutas.inscribir`), con las fijas antes de `/{id}`

### Frontend (US3)

- [X] T059 [P] [US3] Crear `$F/hooks/useInscripciones.ts`, `useColaboradoresInscribibles.ts`, `useInscribir.ts`, `useCambiarNiveles.ts`, `useAlcanzarNivel.ts` y `useRetirar.ts` (sin reintentos en escrituras)
- [X] T060 [P] [US3] Crear `$F/components/TablaDeParticipantes.tsx` según la pestaña Participantes de `RutasAdmin.dc.html`: colaborador, nivel actual, nivel objetivo, `Badge` de estado, filtro por estado y las acciones por fila «Cambiar niveles», «Registrar nivel alcanzado» y «Retirar» visibles solo con `inscribir`; estado vacío «Esta ruta no tiene participantes» El filtro por estado y la página van en la URL.
- [X] T061 [P] [US3] Crear `$F/components/FormularioInscripcion.tsx` (tarjeta «Inscribir colaborador» en línea, como en RutasAdmin.dc.html, no modal: búsqueda y selector de colaboradores activos, nivel actual opcional y nivel objetivo, texto de ayuda; errores por campo), `$F/components/CambiarNivelesModal.tsx` y `$F/components/RetirarModal.tsx` (motivo obligatorio con contador y `ConfirmDialog`); «Registrar nivel alcanzado» muestra la nota de que el cambio real de puesto lo ejecuta Gestión de Personal
- [X] T062 [US3] Añadir en `RutaEditorPage` la pestaña **Participantes (n)** con la tabla y los modales, «Inscribir colaborador» deshabilitado con explicación si la ruta no está Vigente, y los estados loading/vacío/error/sin permisos
- [X] T063 [P] [US3] Escribir `$F/components/TablaDeParticipantes.dom.test.tsx` y `$F/components/RetirarModal.dom.test.tsx`: acciones ocultas sin permiso, retiro bloqueado sin motivo, confirmación accesible y bloqueo del envío mientras está pendiente

**Checkpoint**: US1–US3 operativas

---

## Phase 6: User Story 4 - Consultar «Mi ruta de carrera» como colaborador (Priority: P1)

**Goal**: El colaborador ve su escalera con nivel actual resaltado y nivel objetivo marcado, lo que se exige en el siguiente nivel y en su objetivo, las rutas Vigentes disponibles y el aviso permanente; solo accede a lo propio. Se exponen además los dos métodos de Facade para otros módulos.

**Independent Test**: Con un colaborador inscrito (actual 1, objetivo 3) verificar los niveles resaltados, el detalle correcto, que «Ver mi adecuación» no se ofrece mientras no exista la vista de destino, y que un colaborador sin inscripciones ve el estado vacío con las rutas Vigentes.

### Tests (US4)

- [X] T064 [P] [US4] Escribir `backend/tests/Feature/Desarrollo/Rutas/MiRutaTest.php`: `GET /api/rutas/mi-ruta` devuelve solo las inscripciones Activas del usuario del token con niveles, requisitos, `nivel_actual_id`, `nivel_objetivo_id`, `siguiente_nivel_id` y `puede_ver_adecuacion: false`; `rutas_disponibles` solo con rutas Vigentes; usuario sin ficha o sin inscripciones → `inscripciones: []`; ningún dato de otro colaborador; 403 al administrador
- [X] T065 [P] [US4] Escribir `backend/tests/Feature/Desarrollo/Rutas/RutasFacadeTest.php`: `DesarrolloFacade::inscripcionesActivas` y `obtenerNivel` devuelven la forma de `contracts/rutas-puertos-y-facade.md`; las inscripciones de quien ya no es vigente no se devuelven; `obtenerNivel` inexistente → `null`; los métodos ya existentes de la Facade no cambian

### Implementation (US4)

- [X] T066 [US4] Crear `$D/Application/UseCases/ObtenerMiRuta.php` (resuelve el colaborador por `colaboradorDeUsuario`, concilia bajas, arma niveles con competencias, conocimientos informativos y experiencia, y las rutas Vigentes disponibles; `puede_ver_adecuacion` queda en `false` hasta que exista la vista de adecuación) y `$D/Presentation/Controllers/MiRutaController.php`; declarar `GET /api/rutas/mi-ruta` con `permiso:desarrollo.rutas.ver_propio` antes de `/{id}`
- [X] T067 [US4] Ampliar `$D/Presentation/Facades/DesarrolloFacade.php` con `inscripcionesActivas(int $tenantId, int $colaboradorId)` y `obtenerNivel(int $tenantId, int $nivelId)` (anotaciones `@method` y delegación a casos de uso de solo lectura), sin tocar los métodos existentes. La Facade delega en `Application/Services/DesarrolloService`, que recibe los dos métodos nuevos.

### Frontend (US4)

- [X] T068 [P] [US4] Crear `$F/hooks/useMiRuta.ts` y `$F/components/EscaleraColaborador.tsx` según `RutasColaborador.dc.html`: nivel actual resaltado, nivel objetivo marcado, siguiente nivel indicado, niveles pasados atenuados
- [X] T069 [US4] Completar `$F/pages/MiRutaPage.tsx`: una escalera por ruta, detalle del siguiente nivel o del objetivo con las tarjetas Competencias (grado), Conocimientos «Informativo» y Experiencia (reutilizar `DetalleDeNivel` en modo de solo lectura sin diferencial editable), sección «Rutas disponibles» de solo lectura, `AvisoGuia` permanente, botón «Ver mi adecuación» solo si `puede_ver_adecuacion`, y estado vacío «No estás inscrito en ninguna ruta»; sin «Registrar mi interés» ni propuestas
- [X] T070 [P] [US4] Escribir `$F/pages/MiRutaPage.dom.test.tsx`: resalta actual y objetivo, muestra el aviso, no ofrece «Ver mi adecuación» sin `puede_ver_adecuacion`, muestra el estado vacío con las rutas disponibles y nunca muestra acciones de gestión

**Checkpoint**: US1–US4 operativas (P1 completo)

---

## Phase 7: User Story 5 - Ver la cantera de talento y el historial (Priority: P2)

**Goal**: El administrador ve cuántos colaboradores Activos hay en cada nivel y consulta el historial de la ruta, de sus niveles y de las inscripciones.

**Independent Test**: Con inscripciones Activas repartidas en distintos niveles verificar los conteos (los Finalizados y Retirados no cuentan y los «sin nivel» se informan aparte); modificar una ruta, un nivel y una inscripción y comprobar cada evento en el historial.

### Tests (US5)

- [X] T071 [P] [US5] Escribir `backend/tests/Unit/Desarrollo/Rutas/CalculadorDeCanteraTest.php`: cuenta Activas por nivel actual, excluye Finalizadas y Retiradas e informa aparte las Activas sin nivel actual
- [X] T072 [P] [US5] Escribir `backend/tests/Feature/Desarrollo/Rutas/RutaCanteraTest.php`: `GET /api/rutas/{id}` incluye `inscritos_activos` por nivel y `sin_nivel`; el colaborador no recibe la cantera
- [X] T073 [P] [US5] Escribir `backend/tests/Feature/Desarrollo/Rutas/RutaHistorialTest.php`: `GET /api/rutas/{id}/historial` pagina eventos de ruta, nivel e inscripción (más reciente primero) con usuario («Sistema» si es nulo), fecha, valores anterior y nuevo y motivo; 404 si la ruta no existe; 403 sin `historial`; no existen endpoints para editar ni borrar eventos

### Implementation (US5)

- [X] T074 [US5] Crear `$D/Domain/Services/CalculadorDeCantera.php`, el caso de uso `$D/Application/UseCases/ListarHistorialDeRuta.php`, la consulta de historial paginado en `EloquentRutasRepository` y declarar `GET /api/rutas/{id}/historial` (`permiso:desarrollo.rutas.historial`); integrar la cantera en `ObtenerRuta`

### Frontend (US5)

- [X] T075 [P] [US5] Crear `$F/hooks/useHistorialRuta.ts`, `$F/components/CanteraPorNivel.tsx` (personas por nivel con «sin nivel» aparte, según el bloque Cantera de `RutasAdmin.dc.html`) y `$F/components/HistorialDeRuta.tsx` (tabla de solo lectura con usuario, fecha, acción, valor anterior, valor nuevo y motivo, con paginación) La página del historial va en la URL.
- [X] T076 [US5] Integrar la cantera en la escalera y en la pestaña Participantes, y añadir la pestaña **Historial** a `RutaEditorPage` visible solo con `desarrollo.rutas.historial`, con loading/vacío/error/sin permisos
- [X] T077 [P] [US5] Escribir `$F/components/CanteraPorNivel.dom.test.tsx` y `$F/components/HistorialDeRuta.dom.test.tsx`: conteos por nivel, «sin nivel» aparte, pestaña oculta sin permiso, usuario «Sistema» para eventos automáticos

**Checkpoint**: todas las historias operativas

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: datos de prueba, E2E, regresión y puertas de calidad

- [X] T078 Crear `backend/database/seeders/RutasSeeder.php` y registrarlo en `backend/database/seeders/DatabaseSeeder.php` **después** de `ReportesSeeder` (sin depender de `IngresosSeeder`): 4 rutas sobre los puestos de `PuestosSeeder` —Especialista Vigente de 4 niveles, Gerencial Vigente de 4, Interáreas en Borrador de 2 y Jóvenes profesionales Archivada de 3—, experiencia mínima en al menos dos niveles, e inscripciones Activas (con y sin nivel actual), Finalizada y Retirada sobre colaboradores de `PersonalSeeder`, con su historial. Escribir `backend/tests/Feature/Desarrollo/Rutas/RutasSeederTest.php` que verifique el contenido y que el seeder es repetible **Inscribe solo a colaboradores con vínculo vigente**: la conciliación de bajas retiraría a los demás en la primera consulta y `IngresosSeeder` puede no dejar vínculos vigentes; crear los vínculos necesarios o elegir fichas que ya los tengan, y documentarlo. El test verifica que tras la conciliación las inscripciones sembradas Activas siguen Activas.
- [X] T079 Escribir `frontend/e2e/rutas.spec.ts` (Playwright, datos de `backend/.env.e2e`; pruebas seriales; documentar al inicio los datos sembrados como en `actividades.spec.ts`): el administrador crea una ruta, agrega niveles, ve el diferencial y la tarjeta «Informativo», la publica, inscribe a un colaborador, registra el nivel alcanzado y retira otra inscripción con motivo; después el colaborador abre «Mi ruta de carrera», ve su escalera, el aviso y no ve acciones de gestión En el entorno `e2e` solo tres fichas se consideran vigentes (`ColaboradoresVigentesDePrueba`): inscribir únicamente a esas.
- [ ] T080 [P] Revisar responsive (320, 768 y 1440 px sin scroll horizontal; tablas con desplazamiento interno; `Tabs` con `scrollable` si las tres pestañas no caben), tema claro y oscuro, foco en modales, navegación por teclado de la escalera, pestañas y formularios, `aria-current` en el nivel actual, `aria-live` al guardar y que el color nunca sea la única señal
- [X] T081 [P] Verificar el rendimiento de SC-007 (100 rutas y 1 000 inscripciones: lista, escalera e inscripciones en menos de 2 s) y que el detalle de ruta hace una lectura de `getPuesto` por puesto distinto y no por nivel; si no se cumple, memoizar o precargar en `PuestosParaRutasDesdeEstructura` y registrarlo en research.md
- [ ] T082 Ejecutar las puertas de calidad: en `backend/` `vendor/bin/pest` (completo, incluyendo Plan, micro-learning, DNF, Actividades y Reportes) y `vendor/bin/pint --test`; en `frontend/` `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `npx playwright test` (completo, porque `App.tsx` y las rutas compartidas cambian). Comparar contra la línea base de T001 y reportar solo regresiones
- [ ] T083 Recorrer los escenarios de `specs/019-rutas-carrera/quickstart.md`, comprobar los criterios SC-001 a SC-009 de spec.md, confirmar que no se añadieron dependencias (`composer.json` y `package.json` sin cambios), que `DesarrolloFacade` solo recibió `inscripcionesActivas` y `obtenerNivel`, que no existe ningún endpoint DELETE, que ningún archivo contiene un tipo o campo «conocimiento con grado» y que la bandera `desarrollo.conocimientos_con_grado` vale `false`; marcar las tareas como hechas

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Fase 1)**: sin dependencias
- **Foundational (Fase 2)**: depende de Setup; **bloquea todas las historias**. T020a (dobles de prueba) debe estar lista antes de cualquier prueba de backend de las historias
- **US1 (P1)**: depende de Foundational. Es el MVP: definir rutas y niveles
- **US2 (P1)**: depende de Foundational; usa `ObtenerRuta`/listado de US1 solo para la UI del editor, el backend es independiente
- **US3 (P1)**: depende de Foundational y de US2 para inscribir en una ruta Vigente (en pruebas se puede sembrar el estado); la conciliación de bajas (T056–T057) se reutiliza en US4 y US5
- **US4 (P1)**: depende de Foundational y de US3 (inscripciones) y reutiliza `DetalleDeNivel` de US1
- **US5 (P2)**: depende de Foundational y de US3 (datos de cantera e historial de inscripciones)
- **Polish (Fase 8)**: depende de las historias que se entreguen

### Within Each User Story

- Pruebas primero (deben fallar), luego dominio → repositorio → casos de uso → request/controlador/rutas → hooks → componentes → página
- Backend y frontend de una historia pueden avanzar en paralelo una vez fijado el contrato

### Parallel Opportunities

- Foundational: T004–T008 tras T003; T009–T011, T014–T015 y T021 en paralelo; T022–T025 y T027 en paralelo
- US1: T028–T032 (pruebas) en paralelo; T036–T038 (frontend) en paralelo
- US2 en paralelo con US1 tras Foundational (backend); US5 puede empezar el backend en paralelo con US4 una vez listo US3
- T080 y T081 en paralelo en Polish

---

## Implementation Strategy

### MVP First

1. Fases 1 y 2 (Setup y Foundational).
2. US1 (rutas y niveles) → **validar**: código, diferencial de competencias, tarjeta informativa y bloqueo de niveles en uso.
3. US2 (publicar, archivar, listar), US3 (inscripciones) y US4 (Mi ruta) → **P1 completo**: valor central para administrador y colaborador.

### Incremental Delivery

1. + US5 (cantera e historial) → visión de talento y auditoría.
2. Polish: seeder, E2E, rendimiento y puertas de calidad.

## Notes

- Cada tarea cita su archivo; los valores restringidos del modelo (longitudes, enumeraciones, rangos) están copiados de data-model.md y no se deciden al implementar
- Commit por tarea o por grupo lógico; detenerse en cada *checkpoint* para validar la historia por separado
- Historial inmutable: ninguna tarea añade actualización ni borrado de eventos; las pruebas verifican que no existen PUT/DELETE
- Las tareas con sufijo (T020a, T034a) se añadieron tras el análisis de consistencia para no renumerar el resto; se ejecutan en el orden en que aparecen
- Decisiones ya cerradas que no se reabren al implementar: permisos por catálogo en código y middleware `permiso:<clave>`, retiro por baja con conciliación, control de `version`, bloqueo de borrado de niveles con historial de inscripciones y Opción A de conocimientos informativos
- Pendiente fuera de esta lista: corregir con `/speckit-clarify` las frases de `spec.md` que asignan grado A–D a los conocimientos
