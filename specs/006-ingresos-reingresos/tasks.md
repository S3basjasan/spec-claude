# Tasks: Registro de ingresos y reingresos laborales (GP-02)

**Input**: Design documents from `/specs/006-ingresos-reingresos/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/ingresos-api.md, contracts/contratos-entre-modulos.md, quickstart.md

**Tests**: Incluidas. La constitución (IV) exige cobertura automatizada y un E2E real por feature; el plan fija la trazabilidad a GP02-CA01…CA08 y exige una prueba de concurrencia real.

**Organization**: Tareas agrupadas por historia de usuario (US1 ingreso actual o pasado, US2 ingreso programado y estado derivado, US3 reingreso, US4 rechazos, reintentos y concurrencia, US5 consulta de situación y trayectoria).

## Format: `- [ ] [ID] [P?] [Story] Descripción con ruta`

- **[P]**: paralelizable (archivos distintos, sin dependencias pendientes)
- **[Story]**: US1–US5, solo en fases de historia
- Rutas relativas a la raíz del repositorio. Abreviaturas: `M` = `backend/app/Modules/GestionPersonal`, `E` = `backend/app/Modules/Estructura`, `T` = `backend/tests`, `F` = `frontend/src/modules/gestionPersonal/fichas`

---

## Phase 1: Setup

**Purpose**: Diseño primero (Constitución III), configuración y base de datos.

- [X] T001 Actualizar `design/DESIGN.md` §4 (Alert con motivo de rechazo y acción, aviso neutro «Ingreso programado», Combobox con carga remota paginada y estados vacío / no disponible), §5 (patrón Formulario de ingreso) y §9 con las diferencias de `design/GP-02_Registrar-ingreso.html` frente a la spec (formulario de página en lugar de modal; sin formación, experiencia ni movilidad; sin controles del prototipo)
- [X] T002 [P] Crear el tablero `design/ColaboradorIngreso.dc.html` (formulario con fecha, contrato, selector de posición, puesto/unidad/jefatura en solo lectura; estados: cargando opciones, sin posiciones para la fecha, rechazo con motivo, selección descartada, Estructura no disponible con Reintentar, éxito) y su entrada en `design/canvas.json`
- [X] T003 [P] Añadir en `backend/config/hrms.php` `gestion_personal.tipos_contrato` con exactamente `indefinido` «Indefinido», `plazo_fijo` «Plazo fijo», `temporal` «Temporal» y `servicios_profesionales` «Servicios profesionales», y `gestion_personal.ingresos_habilitados` (env `GESTION_PERSONAL_INGRESOS_HABILITADOS`, por defecto `true`); documentar la variable en `backend/.env.example`
- [X] T004 [P] Añadir `busy_timeout` configurable (env `DB_BUSY_TIMEOUT`, por defecto 5000 ms) a la conexión `sqlite` de `backend/config/database.php` para que un escritor espere al otro en lugar de fallar de inmediato

---

## Phase 2: Foundational (bloquea todas las historias)

**Purpose**: Esquema con garantías temporales, dominio puro, contratos de Estructura y lectores reales que reemplazan los de GP-01.

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase.

### Esquema

- [X] T005 Crear `backend/database/migrations/2026_10_16_000001_add_tenant_id_to_posiciones_table.php`: `posiciones.tenant_id` unsignedBigInteger NOT NULL DEFAULT 1 con índice; `down()` lo elimina
- [X] T006 Crear `backend/database/migrations/2026_10_16_000002_create_vinculos_laborales_table.php` con SQL crudo (`CREATE TABLE`) y exactamente: `id` PK autoincremental; `tenant_id` NOT NULL; `colaborador_id` NOT NULL FK a `colaboradores.id` ON DELETE RESTRICT; `fecha_ingreso` date NOT NULL; `fecha_salida` date NULL con `CHECK (fecha_salida IS NULL OR fecha_salida >= fecha_ingreso)`; `tipo_contrato_clave` varchar(80) NOT NULL; `version` integer NOT NULL DEFAULT 1; `operacion_id` char(36) NOT NULL; `operacion_hash` char(64) NOT NULL; `created_by`/`updated_by` NOT NULL; marcas de tiempo; únicos `(tenant_id, operacion_id)` y parcial `(colaborador_id) WHERE fecha_salida IS NULL`; índices `(colaborador_id)` y `(tenant_id, colaborador_id, fecha_ingreso)`
- [X] T007 Crear `backend/database/migrations/2026_10_16_000003_create_asignaciones_laborales_table.php` con SQL crudo y exactamente: `id` PK; `tenant_id` NOT NULL; `vinculo_id` NOT NULL FK a `vinculos_laborales.id` ON DELETE RESTRICT; `posicion_id` NOT NULL **sin FK**; `fecha_inicio` date NOT NULL; `fecha_fin` date NULL con `CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)`; `version` integer NOT NULL DEFAULT 1; `created_by`/`updated_by`; marcas de tiempo; únicos parciales `(posicion_id) WHERE fecha_fin IS NULL` y `(vinculo_id) WHERE fecha_fin IS NULL`; índices `(vinculo_id, fecha_inicio)` y `(posicion_id, fecha_inicio)`
- [X] T008 Crear `backend/database/migrations/2026_10_16_000004_create_triggers_laborales.php` con los cinco *triggers* de data-model.md (`vinculos_sin_superposicion`, `posicion_sin_superposicion`, `vinculo_una_posicion_por_fecha`, `asignacion_contenida_en_vinculo`, `vinculo_contiene_asignaciones`), cada uno con `RAISE(ABORT, '<MENSAJE>')` y la fórmula de superposición `A.inicio < COALESCE(B.fin,'9999-12-31') AND B.inicio < COALESCE(A.fin,'9999-12-31')` sobre intervalos no vacíos; `down()` los elimina
- [X] T009 [P] Prueba Feature de esquema en `T/Feature/GestionPersonal/Ingresos/EsquemaLaboralTest.php`: los `CHECK`, los únicos parciales y cada *trigger* abortan con su mensaje; intervalos contiguos (fin = inicio) se aceptan; un intervalo vacío no ocupa; asignación fuera del vínculo y acortar el vínculo sobre una asignación se rechazan

### Dominio y modelos

- [X] T010 [P] Implementar `M/Domain/Services/PeriodoLaboral.php` (inicio, fin opcional abierto; `superpone`, `contiene`, `estaVacio`, `terminaEfectivamenteAntesDe`) con prueba Unit en `T/Unit/GestionPersonal/Ingresos/PeriodoLaboralTest.php`: contiguos, abiertos, vacíos, superposición total y parcial
- [X] T011 [P] Implementar `M/Domain/Services/SituacionLaboralDerivada.php` y `M/Domain/ValueObjects/SituacionLaboral.php`: Activo = `fecha_ingreso <= hoy` y (`fecha_salida` nula o `> hoy`); Desvinculado = sin vigente y alguna `fecha_salida <= hoy`; SinVinculoVigente en otro caso; próximo ingreso = `fecha_ingreso > hoy` más cercano; con prueba Unit en `T/Unit/GestionPersonal/Ingresos/SituacionLaboralDerivadaTest.php` (reingreso futuro no cambia Desvinculado)
- [X] T012 [P] Crear `M/Domain/ValueObjects/{TipoContrato,TipoDeHecho}.php` (`TipoContrato` lee las claves de `config('hrms.gestion_personal.tipos_contrato')`; `TipoDeHecho` = `Ingreso`|`Reingreso`) y las excepciones `M/Domain/Exceptions/{PosicionNoDisponible,VinculoSuperpuesto,ContratoNoHabilitado,IngresoNoVerificable,IngresosDeshabilitados}.php`
- [X] T013 Crear `M/Domain/Entities/{VinculoLaboral,AsignacionLaboral}.php`, `M/Domain/Repositories/VinculoLaboralRepositoryInterface.php` (con `tenantId` obligatorio: `vinculosDeFicha`, `asignacionesDePosicion`, `porOperacion`, `crearIngreso`, `estadosPresentes`) y los modelos `M/Infrastructure/Persistence/Eloquent/Models/{VinculoLaboralModel,AsignacionLaboralModel}.php`
- [X] T014 Implementar `M/Infrastructure/Persistence/Eloquent/Repositories/EloquentVinculoLaboralRepository.php` con las lecturas y `crearIngreso` (inserta vínculo y asignación inicial con las mismas fechas); enlazar en `M/Infrastructure/Providers/GestionPersonalServiceProvider.php`

### Estructura: contrato de posiciones

- [X] T015 [P] Ampliar `E/Application/Services/EstructuraService.php` y el `@method` de `E/Presentation/Facades/EstructuraFacade.php` con `getPosicion`, `getPosicionesPorIds`, `posicionesAsignables(tenantId, texto, pagina, porPagina)`, `posicionEsAsignable(id, tenantId)`, `getPosicionesPorPuesto` y `getPosicionesPorUnidad` según contracts/contratos-entre-modulos.md (asignable = del tenant, no `Suprimida`, puesto `Activo`, unidad no nula y `Activa`; sin filtrar por ocupación; `jefatura` = null); añadir `tenant_id` a `E/Infrastructure/Persistence/Eloquent/Models/PosicionModel.php`
- [X] T016 [P] Prueba Feature en `T/Feature/Estructura/PosicionesAsignablesTest.php`: cada motivo de no asignabilidad (suprimida, puesto inactivo, unidad inactiva o nula, otro tenant, inexistente), búsqueda sin tildes, paginación y que la posición `Ocupada`/`Vacante` guardada no cambia la elegibilidad

### Lectores reales de GP-01

- [X] T017 Implementar `M/Infrastructure/Persistence/Eloquent/Repositories/EloquentLectorLaboral.php` (`situacionVigente` devuelve Activo o Desvinculado con `posicion_id`, ausente = Sin vínculo vigente; `idsConVinculoVigente`, `idsPorEstado`, `colaboradoresEnPosiciones`, `trayectoria` con `Ingreso`/`Reingreso` y `programado`) y añadir `estadosPresentes(int $tenantId, string $fecha)` a `M/Application/Ports/LectorLaboralPort.php`
- [X] T018 [P] Implementar `M/Infrastructure/Adapters/EstructuraFacadeLector.php` (`LectorEstructuraPort` sobre `EstructuraFacade`; un fallo real → `Lectura::noDisponible()`) y `M/Infrastructure/Adapters/LectorDeCapacidadesGp02.php` (`registrar_ingreso` → true; el resto false)
- [X] T019 Sustituir en `M/Infrastructure/Providers/GestionPersonalServiceProvider.php` los tres bindings «no instalado» por `EloquentLectorLaboral`, `EstructuraFacadeLector` y `LectorDeCapacidadesGp02`; conservar los adaptadores «no instalado» solo para pruebas (`T/GestionPersonalHelpers.php` ya los falsea)
- [X] T020 Ajustar GP-01 a los nuevos estados: `M/Application/UseCases/ObtenerFiltrosDeColaboradores.php` ofrece «Todos» + `estadosPresentes`; `M/Application/UseCases/ListarColaboradores.php` usa `idsPorEstado` para los tres estados cuando el lector está instalado (Sin vínculo vigente excluye desvinculados); `M/Application/Services/ArmadorDeFichaDetalle.php` añade `laboral.proximo_ingreso`; actualizar `T/Feature/GestionPersonal/ListarYConsultarTest.php` y `LectoresTest.php` en consecuencia
- [X] T021 [P] Crear `backend/database/seeders/IngresosSeeder.php` (después de `PersonalSeeder`, `PuestosSeeder` y `UnidadesSeeder`) con los datos de data-model.md (posiciones A ocupada, B libre y C con ingreso futuro de `PU-002` con unidad activa y tenant 1; EMP-001 Activo, EMP-004 programado, EMP-005 Desvinculado, EMP-002 y EMP-003 sin vínculo; una posición y una ficha del tenant 2) y registrarlo en `backend/database/seeders/DatabaseSeeder.php`
- [X] T022 [P] Frontend base en `F/`: tipos (`SituacionLaboral`, `OpcionesDeIngreso`, `PosicionElegible`, `TipoContrato`) y funciones HTTP en `F/api.ts` (`obtenerOpcionesDeIngreso`, `registrarIngreso` con `Idempotency-Key`) y textos en `F/text.ts` (todo en español, centralizado)

**Checkpoint**: `php artisan migrate:fresh --seed` aplica; los tests de GP-01 pasan con los lectores reales.

---

## Phase 3: User Story 1 - Registrar un ingreso actual o con fecha pasada (P1) 🎯 MVP

**Goal**: El Administrador registra el ingreso de una ficha con fecha de hoy o pasada, contrato y posición libre; la ficha queda Activo con puesto y unidad.

**Independent Test**: Con una ficha sin vínculo y una posición libre, `POST …/ingresos` con fecha de hoy → 201 y la ficha Activo; con una fecha pasada → se conserva como efectiva y `registrado_en` es ahora; sin cuentas nuevas.

### Tests for User Story 1

- [X] T023 [P] [US1] Prueba Unit de `ReglasDeIngreso` en `T/Unit/GestionPersonal/Ingresos/ReglasDeIngresoTest.php`: fecha obligatoria con formato `AAAA-MM-DD` dentro de 1900-01-01 y hoy + 10 años, contrato dentro de las 4 claves, posición obligatoria
- [X] T024 [P] [US1] Prueba Feature GP02-CA01/CA02/CA08 en `T/Feature/GestionPersonal/Ingresos/RegistrarIngresoTest.php`: ingreso de hoy → 201 con vínculo y asignación iguales en fechas, ficha Activo con puesto y unidad; fecha pasada conserva fecha efectiva y momento de registro actual; `created_by` del actor; sin cuenta nueva ni cambio en `colaborador_cuentas`; campos `fecha_salida`/`estado`/`salario`/`tenant_id` → 422 `CAMPO_NO_PERMITIDO`; faltantes → 422 por campo
- [X] T025 [P] [US1] Prueba Feature de autorización y aislamiento en `T/Feature/GestionPersonal/Ingresos/AutorizacionIngresoTest.php`: 401 sin sesión, 403 al Usuario en `POST` y `GET opciones`, ficha o posición de otro tenant → mismo 404/422 que inexistente, `ingresos_habilitados=false` → 503 `INGRESOS_DESHABILITADOS`
- [X] T026 [P] [US1] Prueba Feature de opciones en `T/Feature/GestionPersonal/Ingresos/OpcionesDeIngresoTest.php`: 4 tipos de contrato, posiciones elegibles para la fecha sin las ocupadas desde esa fecha, búsqueda y paginación, `ingreso_programado` para fecha futura, 503 si Estructura falla (nunca lista vacía engañosa)
- [X] T027 [P] [US1] Pruebas Vitest en `F/components/FormularioDeIngreso.dom.test.tsx` y `F/pages/RegistrarIngresoPage.dom.test.tsx`: errores por campo, botón bloqueado al guardar, puesto/unidad/jefatura («No registrado») en solo lectura, Cancelar sin crear, el Usuario no ve la acción ni accede a la ruta

### Implementation for User Story 1

- [X] T028 [P] [US1] Implementar `M/Domain/Services/ReglasDeIngreso.php` (validación de forma y de contrato; usa `PeriodoLaboral` para el intervalo nuevo `[fecha, ∞)`)
- [X] T029 [P] [US1] Implementar `M/Presentation/Requests/RegistrarIngresoRequest.php` (`fecha_ingreso` date_format `Y-m-d`; `tipo_contrato` string; `posicion_id` integer min 1; rechaza campos no declarados con `CAMPO_NO_PERMITIDO`, igual que `GuardarColaboradorRequest`) y `M/Presentation/Requests/OpcionesDeIngresoRequest.php` (`fecha` obligatoria, `q` ≤ 50, `page`, `per_page` 1–50 por defecto 20)
- [X] T030 [US1] Implementar `M/Application/UseCases/ObtenerOpcionesDeIngreso.php` y el puerto `M/Application/Ports/PosicionesAsignablesPort.php` con su adaptador `M/Infrastructure/Adapters/PosicionesAsignablesEstructura.php`: posiciones asignables del tenant menos las que chocan con `[fecha, ∞)`, ocupación resuelta por lote; `conflicto_ficha` si la ficha no puede ingresar ese día
- [X] T031 [US1] Implementar `M/Application/UseCases/RegistrarIngreso.php` (camino principal: bandera `ingresos_habilitados`, ficha del tenant, contrato, posición asignable, `crearIngreso` en una transacción) y `M/Application/DTO/IngresoDTO.php`
- [X] T032 [US1] Implementar `M/Presentation/Controllers/IngresoController.php` (`store` 201 y `opciones`; errores con `code`) y las rutas `POST /api/colaboradores/{colaboradorId}/ingresos` y `GET …/opciones-ingreso` con `auth:sanctum` + `admin` en `M/Routes/api.php`
- [X] T033 [P] [US1] Frontend: hooks `F/hooks/{useOpcionesDeIngreso,useRegistrarIngreso}.ts` (mutación sin reintentos, `Idempotency-Key` por `crypto.randomUUID()` al montar, invalida `["colaboradores"]` y `["mi-ficha"]`, sin cambio optimista) y componentes `F/components/{SelectorDePosicion,FormularioDeIngreso}.tsx` (Combobox paginado con debounce de 300 ms; puesto, unidad y jefatura en solo lectura)
- [X] T034 [US1] Frontend: `F/pages/RegistrarIngresoPage.tsx`, ruta `/colaboradores/:colaboradorId/ingreso` bajo `RequireAdmin` en `frontend/src/App.tsx`, y la acción **Registrar ingreso** en `F/pages/FichaDetallePage.tsx` solo si `capacidades.registrar_ingreso`; al éxito vuelve al detalle con aviso

**Checkpoint**: US1 verificable por API y formulario.

---

## Phase 4: User Story 2 - Programar un ingreso futuro y ver el estado derivado (P1)

**Goal**: Un primer ingreso futuro se muestra como programado; al llegar la fecha del tenant la ficha pasa a Activo sin acción manual.

**Independent Test**: Registrar ingreso con fecha de mañana; hoy Sin vínculo vigente con `proximo_ingreso`; con el reloj en la fecha → Activo; el filtro de estado ofrece los estados presentes.

### Tests for User Story 2

- [X] T035 [P] [US2] Prueba Feature GP02-CA03 en `T/Feature/GestionPersonal/Ingresos/EstadoDerivadoTest.php` con `Carbon::setTestNow` en la zona del tenant: antes de la fecha Sin vínculo vigente con `proximo_ingreso.programado`, el día de la fecha Activo, medianoche en otra zona del tenant; el estado no depende de la cuenta (deshabilitar cuenta no lo cambia); el hecho futuro sale `programado` en la trayectoria
- [X] T036 [P] [US2] Prueba Feature del listado en `T/Feature/GestionPersonal/Ingresos/ListadoEstadosTest.php`: `GET /api/colaboradores/filtros` ofrece los estados presentes; `?estado=Activo`, `Desvinculado` y `SinVinculoVigente` (este excluye desvinculados y programados no vigentes se incluyen)
- [X] T037 [P] [US2] Pruebas Vitest en `F/components/ProximoIngreso.dom.test.tsx` y ampliación de `F/pages/ColaboradoresPage.dom.test.tsx`: bloque «Ingreso programado», aviso neutro en el formulario con fecha futura, opciones del filtro de estado

### Implementation for User Story 2

- [X] T038 [US2] Completar en `M/Application/Services/ArmadorDeFichaDetalle.php` y `M/Infrastructure/Persistence/Eloquent/Repositories/EloquentLectorLaboral.php` el `proximo_ingreso` (`fecha_ingreso`, contrato, posición, `programado`) y los hechos con `programado` calculado con `ContextoDeTenant::hoy()`
- [X] T039 [P] [US2] Frontend: `F/components/ProximoIngreso.tsx` en `F/components/SeccionLaboral.tsx` y el aviso neutro «Ingreso programado» en `F/components/FormularioDeIngreso.tsx` cuando la fecha es posterior a hoy (la fecha del navegador es una ayuda; decide el servidor)
- [X] T040 [P] [US2] Frontend: etiquetas «Activo», «Desvinculado» y «Sin vínculo vigente» en `F/text.ts` y uso en `F/components/ColaboradoresTable.tsx` y `F/pages/FichaDetallePage.tsx`

**Checkpoint**: US1 y US2 independientes y coherentes con GP-01.

---

## Phase 5: User Story 3 - Registrar un reingreso conservando la historia (P1)

**Goal**: Una ficha desvinculada recibe un vínculo nuevo; ficha, código y vínculos anteriores se conservan.

**Independent Test**: Con EMP-005 Desvinculado y una posición libre, `POST` con fecha igual a su salida → 201; vínculo anterior intacto; fecha anterior a la salida → rechazo; sin cuentas nuevas.

### Tests for User Story 3

- [X] T041 [P] [US3] Prueba Feature GP02-CA06/RN06/RN09 en `T/Feature/GestionPersonal/Ingresos/ReingresoTest.php`: reingreso compatible crea vínculo nuevo y conserva ficha, código y vínculo anterior; fecha igual a la salida aceptada; fecha anterior a la salida o con vínculo abierto → 422 `VINCULO_SUPERPUESTO`; Desvinculado con reingreso futuro sigue Desvinculado; no se crea ni reactiva cuenta ni se copian permisos; la trayectoria muestra `Ingreso` y luego `Reingreso`
- [X] T042 [P] [US3] Prueba Vitest en `F/components/Trayectoria.dom.test.tsx`: etiquetas «Ingreso» y «Reingreso», orden descendente y marca «Programado»

### Implementation for User Story 3

- [X] T043 [US3] En `M/Infrastructure/Persistence/Eloquent/Repositories/EloquentLectorLaboral.php` clasificar cada hecho como `Reingreso` cuando la ficha tiene un vínculo anterior (por `fecha_ingreso`), y asegurar en `M/Application/UseCases/RegistrarIngreso.php` que los vínculos anteriores no se modifican
- [X] T044 [P] [US3] Frontend: textos «Reingreso» y «Registrar reingreso» (cuando la ficha es Desvinculado) en `F/text.ts`, `F/components/Trayectoria.tsx` y el botón del detalle

**Checkpoint**: US1–US3 independientes.

---

## Phase 6: User Story 4 - Rechazar incompatibles y resistir reintentos y concurrencia (P1)

**Goal**: Ningún ingreso incompatible prospera, ni por superposición, ni por carrera, ni por reintento; un rechazo no deja registros parciales.

**Independent Test**: Ingresar a una posición ocupada (422 sin filas); dos procesos simultáneos a la misma posición (uno 201, otro 422); repetir una confirmación (200, un solo vínculo); y la desactivación de 004/005 respeta las asignaciones.

### Tests for User Story 4

- [X] T045 [P] [US4] Prueba Feature GP02-CA04/CA07 en `T/Feature/GestionPersonal/Ingresos/SuperposicionTest.php`: posición ocupada en el periodo (existente y futura) → 422 `POSICION_NO_DISPONIBLE`; ficha con vínculo actual o futuro → 422 `VINCULO_SUPERPUESTO` aunque la posición sea otra; intervalos adyacentes permitidos (ocupante sale el día que entra el nuevo); posición suprimida, puesto inactivo o unidad inactiva → 422; sin filas parciales en ningún rechazo
- [X] T046 [P] [US4] Prueba Feature de atomicidad en `T/Feature/GestionPersonal/Ingresos/AtomicidadTest.php`: un fallo al insertar la asignación revierte el vínculo (rollback); un *trigger* que aborta se traduce al error de negocio correcto; la posición que cambia entre elegirla y confirmar rechaza el conjunto
- [X] T047 [P] [US4] Prueba Feature de idempotencia en `T/Feature/GestionPersonal/Ingresos/IdempotenciaIngresoTest.php`: misma clave y cuerpo → 200 y un solo vínculo; misma clave con otros datos u otro actor → 409 `CLAVE_DE_OPERACION_REUTILIZADA`; sin cabecera o no UUID → 422 `CLAVE_DE_OPERACION_REQUERIDA`
- [X] T048 [P] [US4] Prueba de concurrencia real GP02-CA05 en `T/Feature/GestionPersonal/Ingresos/ConcurrenciaTest.php`: archivo SQLite temporal y dos procesos PHP (`Symfony\Component\Process`) que confirman a la vez ingresos incompatibles a la misma posición y otro par a la misma ficha, con una barrera de inicio; exactamente un 201 y un 422 en cada par, una sola asignación abierta por posición y por vínculo; repetir 20 veces
- [X] T049 [P] [US4] Prueba Feature de Estructura caída en `T/Feature/GestionPersonal/Ingresos/EstructuraNoVerificableTest.php`: con `EstructuraFacade` fallando, `POST` → 503 `ESTRUCTURA_NO_VERIFICABLE` sin escribir; tras agotar los reintentos de bloqueo, 503 y la misma clave puede repetirse
- [X] T050 [P] [US4] Pruebas de impacto en 004 y 005: `T/Feature/Estructura/Puestos/CambiarEstadoPuestoOcupacionTest.php` y `T/Feature/Estructura/Unidades/CambiarEstadoUnidadOcupacionTest.php` (posición suprimida con asignación vigente o futura bloquea y suma al conteo del mensaje; asignación terminada no bloquea; las reglas actuales siguen igual)
- [X] T051 [P] [US4] Pruebas Vitest en `F/pages/RegistrarIngresoPage.rechazo.dom.test.tsx`: Alert con el motivo y formulario conservado, cambio de fecha recarga opciones y descarta con aviso la selección incompatible, Estructura no disponible con Reintentar, 409 regenera la clave

### Implementation for User Story 4

- [X] T052 [US4] Endurecer `M/Application/UseCases/RegistrarIngreso.php` según research D-07: resolver el reintento fuera de la transacción; `DB::transaction` cuya primera instrucción es la escritura de bloqueo sobre la ficha; repetir la lectura idempotente; verificar la posición con `EstructuraFacade::posicionEsAsignable` en la misma conexión; comprobar superposición de la ficha (RN02, RN06) y de la posición (RN03) con `PeriodoLaboral`; insertar vínculo y asignación; guardar `operacion_hash`
- [X] T053 [US4] En `M/Infrastructure/Persistence/Eloquent/Repositories/EloquentVinculoLaboralRepository.php` traducir los mensajes de los *triggers* (`VINCULO_SUPERPUESTO`, `POSICION_SUPERPUESTA`, `ASIGNACION_SUPERPUESTA`) y las violaciones únicas a las excepciones de negocio, y reintentar hasta 3 veces `SQLITE_BUSY`/«database is locked» con espera de 50–200 ms y la misma operación; agotados → `IngresoNoVerificable`
- [X] T054 [US4] En `M/Presentation/Controllers/IngresoController.php` mapear `PosicionNoDisponible` y `VinculoSuperpuesto` a 422 con `errors` por campo y `message` con el motivo y fechas, `ClaveDeOperacionReutilizada` a 409, `IngresoNoVerificable` a 503 `ESTRUCTURA_NO_VERIFICABLE` e `IngresosDeshabilitados` a 503
- [X] T055 [P] [US4] Implementar `E/Application/Ports/OcupacionDePosicionesPort.php`, el adaptador `E/Infrastructure/Adapters/OcupacionPorPersonalFacade.php` (sobre `PersonalFacade::posicionesConAsignacionVigenteOFutura`), su binding en `E/Infrastructure/Providers/EstructuraServiceProvider.php` y el método `posicionesConAsignacionVigenteOFutura` en `M/Application/Services/PersonalService.php` y `M/Presentation/Facades/PersonalFacade.php`
- [X] T056 [US4] Extender `E/Application/UseCases/CambiarEstadoPuesto.php` y `E/Application/UseCases/CambiarEstadoUnidad.php` para consultar el puerto de ocupación dentro de la misma transacción de desactivación, rechazar con el conteo ampliado y reintentar el bloqueo de forma acotada; sin cambiar sus mensajes cuando no hay ocupación adicional
- [X] T057 [P] [US4] Frontend: en `F/pages/RegistrarIngresoPage.tsx` y `F/components/FormularioDeIngreso.tsx` el Alert de rechazo con motivo y acción (cambiar fecha o posición), descarte con aviso de la selección incompatible al cambiar la fecha, «No se pudo verificar la posición» con Reintentar y regeneración de la clave tras 409

**Checkpoint**: garantías temporales y atomicidad demostradas, incluida la prueba concurrente.

---

## Phase 7: User Story 5 - Consultar situación laboral y trayectoria (P2)

**Goal**: Administrador y Colaborador (propia) consultan situación y trayectoria, con «Información no disponible» si Estructura falla.

**Independent Test**: Tras un ingreso, ambas lecturas del Administrador y de `mi-ficha` muestran estado, puesto, unidad y hechos; el Colaborador no accede a otra ficha.

### Tests for User Story 5

- [X] T058 [P] [US5] Prueba Feature en `T/Feature/GestionPersonal/Ingresos/SituacionYTrayectoriaTest.php`: `situacion-laboral` y `trayectoria` del Administrador; las propias de `mi-ficha` con 403 `CUENTA_SIN_FICHA`/`SIN_PERMISO_FICHA`; el Colaborador no accede a las de otra persona (403); Estructura caída → `disponibilidad: no_disponible` con estado y fechas; el nombre de puesto es el vigente
- [X] T059 [P] [US5] Pruebas Vitest ampliando `F/pages/FichaDetallePage.dom.test.tsx` y `F/pages/MiFichaPage.dom.test.tsx`: situación laboral con vínculo vigente, próximo ingreso, «Información no disponible» con Reintentar que vuelve a pedir su sección

### Implementation for User Story 5

- [X] T060 [P] [US5] Implementar `M/Application/UseCases/{ConsultarSituacionLaboral,ConsultarTrayectoria}.php` y los DTO `SituacionLaboralDTO` y `HechoLaboralDTO` en `M/Application/DTO/`
- [X] T061 [US5] Implementar `M/Presentation/Controllers/SituacionLaboralController.php` y las rutas `GET /api/colaboradores/{id}/situacion-laboral`, `…/trayectoria` (`auth:sanctum` + `admin`) y `GET /api/mi-ficha/situacion-laboral`, `…/trayectoria` (solo `auth:sanctum` con los controles de GP-01) en `M/Routes/api.php`
- [X] T062 [P] [US5] Frontend: hooks de lectura por sección en `F/hooks/useColaboradores.ts` y uso del Reintentar por sección en `F/components/SeccionLaboral.tsx` y `F/components/Trayectoria.tsx`, mostrando jefatura y vínculo vigente (fecha de ingreso y tipo de contrato)

**Checkpoint**: las cinco historias completas.

---

## Phase 8: Polish & Cross-Cutting

- [X] T063 [P] E2E `frontend/e2e/ingresos.spec.ts`: Administrador registra un ingreso actual, ve el estado y la trayectoria; programa uno futuro; recibe el rechazo por posición ocupada con el formulario conservado; reingresa a un desvinculado; el Colaborador no ve la acción; 320 y 1440 px sin scroll horizontal
- [X] T064 [P] Verificar con búsqueda en `M/` que toda consulta del repositorio filtra por `tenant_id`, que ningún controlador consulta la base de datos y que ninguna clase de `M` o `E` importa clases internas del otro módulo (solo Facades y puertos)
- [X] T065 [P] Confirmar en `design/DESIGN.md` §9 que las diferencias de `GP-02_Registrar-ingreso.html` quedaron registradas y actualizar `specs/005-fichas-colaboradores/contracts/personal-facade.md` con `estadosPresentes` y los adaptadores reales
- [X] T066 Ejecutar `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`, y `npm run lint`, `npx tsc -b`, `npm test -- --run`, `npm run build` y `npx playwright test` en `frontend/`; las únicas fallas admitidas son las preexistentes ajenas a esta feature
- [X] T067 Ejecutar las validaciones de `specs/006-ingresos-reingresos/quickstart.md`, confirmar los ocho GP02-CA y re-sembrar la base de desarrollo con `php artisan migrate:fresh --seed`

---

## Dependencies & Execution Order

- **Setup (1)** → **Foundational (2)** bloquea todas las historias. Dentro de la fase 2: T005→T006→T007→T008→T009; T010–T012 paralelos; T013→T014; T015 y T016 paralelos entre sí; T017→T019→T020 (T018 antes de T019); T021 después de T014 y T015.
- **US1** depende de la fase 2. **US2** y **US3** dependen de US1 (necesitan poder registrar). **US4** endurece lo de US1 y debe terminar antes de dar la funcionalidad por completa. **US5** depende de la fase 2 y puede avanzar en paralelo con US2–US4.
- Dentro de cada historia: pruebas (fallan) → dominio/DTO → repositorio → caso de uso → controlador/rutas → frontend.
- **Polish** al final.

## Parallel Examples

```text
Fase 2:  T010 · T011 · T012  →  T015 · T016 · T018 · T021 · T022
US1:     T023 · T024 · T025 · T026 · T027  → luego T028 · T029 · T033
US4:     T045 · T046 · T047 · T048 · T049 · T050 · T051  → luego T052 → T053 → T054 ; T055 → T056
```

## Implementation Strategy

- **MVP**: Setup + Foundational + US1 (registrar un ingreso actual o pasado). Validar con quickstart pasos 1–5.
- **Incremental**: US2 (programado y estado), US3 (reingreso), US4 (rechazos y concurrencia: obligatoria antes de habilitar escritura en producción), US5 (consultas). Cada historia se valida de forma independiente.
- No se instalan dependencias nuevas; si la prueba de concurrencia exige un paquete no instalado, usar `Symfony\Component\Process` (ya presente por Laravel) y solicitar aprobación antes de añadir otro.

## Notes

- [P] = archivos distintos, sin dependencias pendientes. Commit por tarea o grupo lógico.
- No crear acciones de editar, cancelar ni reprogramar ingresos; no escribir `posiciones.estado`; no crear cuentas; no registrar salidas.
- La modificación a 004 y 005 (T055–T056) se prueba en sus propias suites y no debe alterar sus mensajes cuando no hay ocupación adicional.
