# Tasks: GP-03 · Información de desarrollo profesional

**Input**: `specs/012-desarrollo-profesional/` (plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md)

**Tests**: incluidos (la constitución exige Pest, Vitest y un E2E del flujo principal).

**Formato**: `- [ ] T### [P?] [US?] Descripción con ruta`. `[P]` = archivos distintos, sin dependencia con tareas incompletas. Rutas relativas a la raíz del repositorio. `GP = backend/app/Modules/GestionPersonal`, `FD = frontend/src/modules/gestionPersonal/desarrollo`.

## Phase 1: Setup

- [X] T001 Crear migración `backend/database/migrations/2026_10_17_000001_create_formaciones_academicas_table.php`: id, `tenant_id` (unsignedBigInteger), `colaborador_id` FK restrictOnDelete, `nivel` string(100), `titulo_programa` string(200), `institucion` string(200), `estado` string(12) NOT NULL, `fecha_inicio`/`fecha_finalizacion` date nullable, `observaciones` string(2000) nullable, `version` unsignedInteger default 1, `created_by`/`updated_by`, timestamps; índice `(tenant_id, colaborador_id, fecha_inicio, id)`; sin UNIQUE; triggers SQLite BEFORE INSERT/UPDATE para `estado IN ('EnCurso','Completada','Incompleta')` y `fecha_finalizacion IS NULL OR fecha_inicio IS NULL OR fecha_finalizacion >= fecha_inicio`; `down()` elimina triggers y tabla
- [X] T002 [P] Crear migración `backend/database/migrations/2026_10_17_000002_create_experiencias_anteriores_table.php`: igual patrón; `organizacion` y `cargo` string(200) NOT NULL, `fecha_inicio` date NOT NULL, `fecha_finalizacion` date nullable, `funciones` y `observaciones` string(2000) nullable; índice `(tenant_id, colaborador_id, fecha_inicio, id)`; trigger `fecha_finalizacion IS NULL OR fecha_finalizacion >= fecha_inicio`
- [X] T003 [P] Crear migración `backend/database/migrations/2026_10_17_000003_add_movilidad_to_colaboradores_table.php`: `movilidad_estado` string(14) NOT NULL default 'NoInformada', `movilidad_observaciones` string(2000) nullable, `movilidad_version` unsignedInteger default 1, `movilidad_actualizada_at` timestamp nullable, `movilidad_actualizada_por` unsignedBigInteger nullable; trigger para `movilidad_estado IN ('Disponible','NoDisponible','NoInformada')`; `down()` aditivo reversible
- [X] T004 [P] Crear modelos `GP/Infrastructure/Persistence/Eloquent/Models/FormacionAcademicaModel.php` y `ExperienciaAnteriorModel.php` (tabla, `$guarded = []`) y factories `backend/database/factories/FormacionAcademicaFactory.php` y `ExperienciaAnteriorFactory.php`; añadir al `ColaboradorFactory` los valores por defecto de movilidad solo si hace falta

## Phase 2: Foundational (bloquea todas las historias)

- [X] T005 [P] Value objects `GP/Domain/ValueObjects/EstadoDeFormacion.php` (EnCurso, Completada, Incompleta) y `EstadoDeMovilidad.php` (Disponible, NoDisponible, NoInformada) como enums de string
- [X] T006 [P] Entidades inmutables `GP/Domain/Entities/FormacionAcademica.php`, `ExperienciaAnterior.php`, `Movilidad.php` (campos de data-model.md, incluidos `version` y auditoría)
- [X] T007 [P] Excepciones `GP/Domain/Exceptions/RegistroNoEncontrado.php` y `RegistroModificado.php` (reutilizar `DatosInvalidos`, `ColaboradorNoEncontrado`, `EscrituraDeshabilitada`)
- [X] T008 Servicio `GP/Domain/Services/ReglasDeDesarrollo.php` (PHP puro): `normalizar()` recorta espacios, vacío = ausente; `erroresFormacion(array $final, string $hoy)` y `erroresExperiencia(array $final, string $hoy)`: obligatorios; longitudes nivel 100, titulo_programa 200, institucion 200, observaciones 2000, organizacion 200, cargo 200, funciones 2000; estado válido; fechas reales `Y-m-d`; inicio ≤ hoy en ambos; fin ≤ hoy solo en experiencia; fin ≥ inicio; el estado no deduce ni exige fechas; `erroresMovilidad()` con estado válido y observaciones ≤ 2000
- [X] T009 Interfaz `GP/Domain/Repositories/DesarrolloRepositoryInterface.php`: listar formaciones/experiencias/movilidad por `(tenantId, colaboradorId)`; `crearFormacion/crearExperiencia`; `actualizarFormacion/actualizarExperiencia(tenantId, colaboradorId, id, version, actorId, cambios)` lanzando `RegistroModificado` / `RegistroNoEncontrado`; `actualizarMovilidad(tenantId, colaboradorId, version, actorId, cambios)`; `deColaboradores(tenantId, ids)` por lote; sin ningún método de eliminación
- [X] T010 Implementar `GP/Infrastructure/Persistence/Eloquent/Repositories/EloquentDesarrolloRepository.php`: todas las consultas filtran por `tenant_id` y `colaborador_id`; orden formación `fecha_inicio IS NULL, fecha_inicio DESC, id DESC`, experiencia `fecha_inicio DESC, id DESC`; actualización `WHERE id, tenant_id, colaborador_id, version` incrementando `version`; 0 filas ⇒ distinguir inexistente (`RegistroNoEncontrado`) de versión vieja (`RegistroModificado`); `updated_at` solo con cambios; movilidad con `movilidad_version`; enlazar en `GestionPersonalServiceProvider`
- [X] T011 [P] `GP/Application/DTO/DesarrolloDTO.php` y `GP/Application/Services/ArmadorDeDesarrollo.php` (compone `colaborador_id`, `hoy`, `formaciones`, `experiencias`, `movilidad`, `acciones.editar` según contracts/api-desarrollo.md)
- [X] T012 Caso de uso `GP/Application/UseCases/ObtenerDesarrollo.php` (resuelve la ficha con `ColaboradorRepositoryInterface::porId` ⇒ `ColaboradorNoEncontrado`) y `GP/Presentation/Resources/DesarrolloResource.php`
- [X] T013 `GP/Presentation/Controllers/DesarrolloController.php` (delgado, `responder()` traduce `ColaboradorNoEncontrado`→404 `FICHA_NO_ENCONTRADA`, `RegistroNoEncontrado`→404 `REGISTRO_NO_ENCONTRADO`, `RegistroModificado`→409 `REGISTRO_MODIFICADO`, `DatosInvalidos`→422 `VALIDACION`, `EscrituraDeshabilitada`→503) con `show`; ruta `GET /api/colaboradores/{colaboradorId}/desarrollo` en `GP/Routes/api.php` dentro del grupo `auth:sanctum` + `admin` con `whereNumber`
- [X] T014 [P] Adaptador `GP/Infrastructure/Adapters/LectorDeCapacidadesDesarrollo.php` (`desarrollo` ⇒ true, el resto false) y sustituir el binding de `LectorDeCapacidadesPort` en `GestionPersonalServiceProvider`; ajustar los tests de GP-01 que esperaban `capacidades.desarrollo=false` (`backend/tests/Feature/GestionPersonal/ListarYConsultarTest.php`, `MiFichaTest.php`)
- [X] T015 [P] Pruebas unitarias `backend/tests/Unit/GestionPersonal/ReglasDeDesarrolloTest.php`: cada límite en el máximo y máximo+1, espacios, obligatorios, `inicio=fin` válido, fin<inicio, inicio futuro, fin futuro en formación (ok) y en experiencia (error), estado sin deducir fechas
- [X] T016 [P] Frontend base: `FD/api.ts` (tipos `Desarrollo`, `Formacion`, `Experiencia`, `Movilidad`, `obtenerDesarrollo`), `FD/text.ts` (todos los textos en español: «Formación académica no registrada», «Experiencia anterior no registrada», «No informada», «No registrado», títulos de diálogos, errores), `FD/hooks/useDesarrollo.ts` (clave `['colaboradores','desarrollo',id]`, sin reintento en 4xx)
- [X] T017 Componente `FD/components/SeccionDesarrollo.tsx` con estados loading (Skeleton), error con Reintentar (ErrorState) y composición vacía de los tres bloques en el orden formación, experiencia, movilidad; integrar el panel en `frontend/src/modules/gestionPersonal/fichas/components/FichaContenido.tsx` (pestaña «Desarrollo» cuando `capacidades.desarrollo`, el panel se monta al activarse)
- [X] T018 Pruebas `backend/tests/Feature/GestionPersonal/DesarrolloConsultaTest.php` (GP03-CA01: ficha sin datos ⇒ listas vacías, movilidad NoInformada sin fecha; 401; 403 colaborador; 404 ficha inexistente/otro tenant idéntico; orden de experiencia y formación con nulos al final) y `FD/components/SeccionDesarrollo.dom.test.tsx` (loading, vacío, error con Reintentar sin ocultar el resto de la ficha)

**Checkpoint**: la pestaña Desarrollo existe y consulta datos vacíos.

## Phase 3: User Story 1 — Formación académica (P1) 🎯 MVP

**Objetivo**: agregar y corregir formaciones, validación completa por fila.
**Prueba independiente**: guardar formación válida sin fechas; fin<inicio se rechaza sin tocar otras; editar cambia solo esa fila.

- [X] T019 [P] [US1] Request `GP/Presentation/Requests/GuardarFormacionRequest.php` (forma, tipos y `version` obligatoria en PATCH; la validación de negocio vive en `ReglasDeDesarrollo`; ignora `colaborador_id`, `tenant_id`, `created_by`)
- [X] T020 [US1] Casos de uso `GP/Application/UseCases/AgregarFormacion.php` y `CorregirFormacion.php`: comprobar `config('hrms.gestion_personal.escritura_habilitada')`, ficha existente, reconstruir estado final (actual + cambios), validar con `ReglasDeDesarrollo` y `ContextoDeTenant::hoy()`, comparar y devolver sin escritura si no hay cambios reales, comparar `version` antes de escribir
- [X] T021 [US1] Rutas `POST /api/colaboradores/{colaboradorId}/formaciones` y `PATCH …/formaciones/{formacionId}` en `GP/Routes/api.php` y métodos `storeFormacion`/`updateFormacion` en `DesarrolloController` (201/200); sin ruta DELETE
- [X] T022 [P] [US1] Pruebas `backend/tests/Feature/GestionPersonal/FormacionTest.php`: CA02 (sin fechas), campos obligatorios faltantes/espacios, límites 100/200/200/2000, fin<inicio (CA03) sin alterar otras filas, inicio=fin ok, Completada sin fechas ok, En curso con fin futura ok, inicio futuro 422, filas idénticas aceptadas, PATCH parcial valida estado final, sin cambios ⇒ misma `version` y `updated_at`, 409 con versión vieja, 404 fila de otra ficha y de otro tenant, `colaborador_id` en el cuerpo ignorado, DELETE ⇒ 405/404, colaborador 403, escritura deshabilitada 503
- [X] T023 [P] [US1] Validación Zod `FD/validation.ts` (esquema de formación; inicio ≤ `hoy` del servidor; fin ≥ inicio; límites; recorte) y `FD/validation.test.ts`
- [X] T024 [P] [US1] Mutaciones en `FD/api.ts` y `FD/hooks/useGuardarDesarrollo.ts` (`useMutation` de crear/corregir formación sin reintento; invalida la clave de desarrollo; mapea 422 a campos y 409 a conflicto)
- [X] T025 [US1] `FD/components/ListaFormacion.tsx` (nivel, título, institución, estado con Badge, fechas «No registrado», observaciones; botón «Agregar formación»/«Editar» solo si `acciones.editar`; sin eliminar) y `FormularioFormacion.tsx` en `shared/ui/Modal` con RHF+Zod, contadores `n/máx`, errores por campo con alerta general, botón bloqueado al enviar, conflicto con «Actualizar datos» conservando lo escrito, foco devuelto al disparador; seguir `design/GP-03_Desarrollo-profesional.html`
- [X] T026 [P] [US1] Pruebas `FD/components/ListaFormacion.dom.test.tsx` y `FormularioFormacion.dom.test.tsx`: vacío, error por campo, doble clic ⇒ una sola petición, 409 conserva lo escrito, ausencia de botón eliminar y de controles sin `editar`

## Phase 4: User Story 2 — Experiencia anterior (P1)

**Objetivo**: agregar y corregir experiencia, orden estable, sin declarar vigencia.
**Prueba independiente**: dos experiencias superpuestas, una sin fin; orden por inicio desc; fin vacío muestra «No registrado».

- [X] T027 [P] [US2] Request `GP/Presentation/Requests/GuardarExperienciaRequest.php`
- [X] T028 [US2] Casos de uso `GP/Application/UseCases/AgregarExperiencia.php` y `CorregirExperiencia.php` (mismo patrón que T020; `fecha_inicio` obligatoria no futura, fin no futura)
- [X] T029 [US2] Rutas `POST /api/colaboradores/{colaboradorId}/experiencias` y `PATCH …/experiencias/{experienciaId}` y métodos en `DesarrolloController`
- [X] T030 [P] [US2] Pruebas `backend/tests/Feature/GestionPersonal/ExperienciaTest.php`: organización/cargo/inicio obligatorios, funciones y observaciones 2000/2001, fin<inicio (CA03), inicio futuro y fin futura 422, sin fin se conserva null sin campo de vigencia (CA04), superposición aceptada, orden por inicio desc e id desc estable en consultas repetidas, 409, 404 cruzado, colaborador 403
- [X] T031 [P] [US2] Esquema Zod de experiencia en `FD/validation.ts` (+ casos en `validation.test.ts`) y mutaciones en `FD/api.ts` / `FD/hooks/useGuardarDesarrollo.ts`
- [X] T032 [US2] `FD/components/ListaExperiencia.tsx` (fin ausente ⇒ «No registrado», sin etiqueta «actual») y `FormularioExperiencia.tsx` (misma estructura que T025)
- [X] T033 [P] [US2] Pruebas `FD/components/ListaExperiencia.dom.test.tsx` y `FormularioExperiencia.dom.test.tsx`

## Phase 5: User Story 3 — Movilidad (P2)

**Objetivo**: bloque único, versión propia, fecha de actualización solo con cambio real.
**Prueba independiente**: NoInformada → Disponible con observaciones; no se crea vínculo/asignación; repetir no cambia la fecha.

- [X] T034 [P] [US3] Request `GP/Presentation/Requests/GuardarMovilidadRequest.php` (`estado`, `observaciones`, `version`)
- [X] T035 [US3] Caso de uso `GP/Application/UseCases/ActualizarMovilidad.php`: validar con `erroresMovilidad`, comparar `movilidad_version`, sin cambios reales ⇒ 200 sin escritura, con cambios ⇒ estado/observaciones/`movilidad_actualizada_at`/`por` y `movilidad_version`++ sin tocar `version`, `updated_by` ni `updated_at` de la ficha; no escribe en otras tablas
- [X] T036 [US3] Ruta `PATCH /api/colaboradores/{colaboradorId}/movilidad` y método en `DesarrolloController`
- [X] T037 [P] [US3] Pruebas `backend/tests/Feature/GestionPersonal/MovilidadTest.php`: valor inicial, CA05 (sin vínculos/asignaciones/movimientos creados), observaciones 2000/2001 con bloque intacto, mismo valor no cambia fecha ni versión, 409, PATCH de movilidad y PATCH de ficha en secuencia sin conflicto cruzado, fallo de movilidad no altera las listas, colaborador 403, NoInformada ≠ NoDisponible en la respuesta
- [X] T038 [P] [US3] Esquema Zod de movilidad, mutación y `FD/components/BloqueMovilidad.tsx` + `FormularioMovilidad.tsx` (etiquetas «Disponible», «No disponible», «No informada»; nota «Estar disponible no significa que exista un traslado aprobado.»; sin botones de traslado ni inferencias)
- [X] T039 [P] [US3] Pruebas `FD/components/BloqueMovilidad.dom.test.tsx`

## Phase 6: User Story 4 — Consulta del Colaborador y solo lectura (P2)

**Objetivo**: vista propia sin controles; aislamiento por tenant y por ficha.
**Prueba independiente**: dos colaboradores; el primero ve lo suyo y es denegado al pedir lo del otro.

- [X] T040 [US4] Caso de uso `GP/Application/UseCases/ObtenerMiDesarrollo.php` (reutiliza `AccesosDeCuentaPort`: `CuentaSinFicha`, `SinPermisoDeFicha`; `acciones.editar=false`), `GP/Presentation/Controllers/MiDesarrolloController.php` y ruta `GET /api/mi-ficha/desarrollo` (solo `auth:sanctum`)
- [X] T041 [P] [US4] Pruebas `backend/tests/Feature/GestionPersonal/MiDesarrolloTest.php` (CA06): lectura propia, sin permiso ⇒ 403 `SIN_PERMISO_FICHA`, sin ficha ⇒ 403 `CUENTA_SIN_FICHA`, escritura propia o ajena por API ⇒ 403, `/api/colaboradores/{otra}/desarrollo` ⇒ 403 sin revelar existencia, permiso documental/exportación no requerido, dos tenants con ids numéricos coincidentes
- [X] T042 [P] [US4] Adaptar `api.ts`/`useDesarrollo` con `obtenerMiDesarrollo` (clave `['mi-ficha','desarrollo']`) y mostrar la sección en `frontend/src/modules/gestionPersonal/fichas/pages/MiFichaPage.tsx` sin controles de edición
- [X] T043 [P] [US4] Prueba `FD/components/SeccionDesarrollo.solo-lectura.dom.test.tsx`: sin botones Agregar/Editar cuando `acciones.editar=false`

## Phase 7: Lector por lote para GP-06 y datos de ejemplo

- [X] T044 [P] Puerto `GP/Application/Ports/LectorDeDesarrolloPort.php`, adaptador `GP/Infrastructure/Adapters/LectorDeDesarrolloEloquent.php` (tres consultas `whereIn`; errores ⇒ `Lectura::noDisponible()`), binding y `PersonalFacade::getDesarrollo` + `PersonalService`; prueba `backend/tests/Feature/GestionPersonal/LectorDeDesarrolloTest.php` (sin N+1, ids de otro tenant ignorados, fichas sin datos con listas vacías)
- [X] T045 [P] Actualizar `backend/database/seeders/PersonalSeeder.php` con formaciones en los tres estados, experiencias (una sin fin y dos superpuestas) y movilidad Disponible/NoDisponible/NoInformada

## Phase 8: Concurrencia, E2E y pulido

- [X] T046 Prueba con procesos reales `backend/tests/Feature/GestionPersonal/DesarrolloConcurrenciaTest.php`: doble PATCH de la misma formación (uno 409), dos POST simultáneos a la misma ficha (ambos 201), PATCH de movilidad y de ficha simultáneos (ambos aplican) siguiendo el patrón multiproceso de las pruebas previas
- [X] T047 [P] E2E `frontend/e2e/desarrollo.spec.ts` (identificadores únicos y repetible): administrador agrega formación y experiencia, guarda movilidad, ve el rechazo de fin<inicio; colaborador abre Mi ficha → Desarrollo solo lectura
- [X] T048 [P] Regresión de GP-01: `ActualizarFichaTest.php` confirma que `PATCH /api/colaboradores/{id}` ignora/rechaza columnas de movilidad y no las expone en `datos`
- [X] T049 [P] Diseño: registrar en `design/DESIGN.md` §9 las diferencias con `GP-03_Desarrollo-profesional.html` (si existen) y revisar accesibilidad (foco en `Modal`, etiquetas, 320–1440 px, tema oscuro)
- [X] T050 Puertas de calidad: `vendor/bin/pint --dirty --format agent`, `php artisan test --compact --filter=Desarrollo`; `npm run lint`, `npx tsc --noEmit`, `npm run test`, `npm run build`; ejecutar los escenarios de `quickstart.md`; anotar en research D8 la tarea de integración del lector de capacidades con GP-02

## Dependencias y orden

- Setup (T001–T004) → Foundational (T005–T018) → historias. US1 y US2 son independientes entre sí tras la fase 2 (comparten `DesarrolloController`, `api.ts`, `validation.ts` y `useGuardarDesarrollo.ts`, así que se implementan en secuencia o se coordinan); US3 y US4 también. Fase 7 depende de T010. Fase 8 al final.
- T008 → T015, T020, T028, T035. T010 → T012. T014 → T017 (la pestaña requiere la capacidad).
- Dentro de cada historia: request y pruebas [P] → caso de uso → ruta/controlador → frontend.

## Paralelismo

- Setup: T002, T003, T004 tras T001 no dependen entre sí.
- Foundational: T005, T006, T007, T011, T014, T015, T016 en paralelo.
- Por historia: backend (T019–T022) en paralelo con frontend (T023–T024, T026) hasta la integración (T025).

## Estrategia

MVP = Setup + Foundational + US1 (formación). Entrega incremental: US2 → US3 → US4 → lector y E2E. Tras cada historia, ejecutar sus pruebas Pest/Vitest.
