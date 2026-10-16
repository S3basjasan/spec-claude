# Tasks: Fichas de colaboradores (GP-01)

**Input**: Design documents from `/specs/005-fichas-colaboradores/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/colaboradores-api.md, contracts/personal-facade.md, quickstart.md

**Tests**: Incluidas. La constitución (IV) exige cobertura automatizada y un E2E real por feature; el plan fija la trazabilidad a GP01-CA01…CA06.

**Organization**: Tareas agrupadas por historia de usuario (US1 registrar, US2 listar y consultar, US3 corregir, US4 Mi ficha).

## Format: `- [ ] [ID] [P?] [Story] Descripción con ruta`

- **[P]**: paralelizable (archivos distintos, sin dependencias pendientes)
- **[Story]**: US1–US4, solo en fases de historia
- Rutas relativas a la raíz del repositorio; `M` = `backend/app/Modules/GestionPersonal`, `F` = `frontend/src/modules/gestionPersonal/fichas`

---

## Phase 1: Setup

**Purpose**: Diseño primero (Constitución III), configuración y esqueleto del módulo.

- [X] T001 Ampliar `design/DESIGN.md` §4 (Alert con enlace «Abrir ficha existente», pares etiqueta-valor con «No registrado», línea de tiempo con hecho «Programado», pestañas de sección), §5 (patrón Detalle), §8 y §9 con las diferencias del prototipo `design/GP-01_Fichas-de-colaboradores.html` frente a la spec: título «Colaboradores», sin correo bajo el nombre en la tabla, «Estado» en vez de «Estado laboral», «Editar» en vez de «Editar datos», sin nombre de organización en el header, sin controles del prototipo ni funciones de GP-02/03/05/06/07
- [X] T002 [P] Crear el tablero `design/ColaboradorFicha.dc.html` (detalle con secciones Personal y puesto, Desarrollo y Documentos; estados cargando, «No registrado», «Información no disponible» con Reintentar, ficha inexistente, sin permisos, cuenta sin ficha) y añadir su entrada en `design/canvas.json`
- [X] T003 [P] Añadir `gestion_personal.escritura_habilitada` (env `GESTION_PERSONAL_ESCRITURA_HABILITADA`, por defecto `true`) en `backend/config/hrms.php` y documentarla en `backend/.env.example`
- [X] T004 Crear el esqueleto de carpetas de `M` (Domain, Application, Infrastructure, Presentation, Routes) y `GestionPersonalServiceProvider` en `M/Infrastructure/Providers/GestionPersonalServiceProvider.php`, registrado en `backend/bootstrap/providers.php` y cargando `M/Routes/api.php`, igual que `EstructuraServiceProvider`

---

## Phase 2: Foundational (bloquea todas las historias)

**Purpose**: Tenant, esquema, reglas puras, puertos y datos iniciales.

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase.

### Esquema y Shared

- [X] T005 Crear la migración `backend/database/migrations/2026_10_15_000001_create_tenants_table.php`: `id`; `nombre` string(150) NOT NULL; `zona_horaria` string(64) NOT NULL por defecto `America/Guayaquil`; timestamps; y `TenantModel` en `backend/app/Modules/Shared/Infrastructure/Persistence/Eloquent/Models/TenantModel.php`
- [X] T006 Crear la migración `backend/database/migrations/2026_10_15_000002_add_tenant_id_to_users_table.php`: `users.tenant_id` unsignedBigInteger NOT NULL con índice; inserta el tenant `1` y asigna `1` a las cuentas existentes; `down()` elimina la columna
- [X] T007 Crear la migración `backend/database/migrations/2026_10_15_000003_create_colaboradores_table.php` con exactamente las columnas de data-model.md: `tenant_id`; `codigo` string(40) NOT NULL; `tipo_identificacion` string(12) NOT NULL; `numero_identificacion` string(50) NOT NULL; `identificacion_clave` string(50) NOT NULL; `nombres`/`apellidos` string(150) NOT NULL; `nombres_busqueda`/`apellidos_busqueda` string(150) NOT NULL; `fecha_nacimiento` date NULL; `nacionalidad` string(100) NULL; `genero`/`estado_civil` string(80) NULL; `numero_hijos` unsignedInteger NULL (CHECK ≥ 0 si el builder lo admite); `direccion` string(500) NULL; `telefono` string(30) NULL; `correo_contacto` string(254) NULL; `emergencia_nombre` string(150) NULL; `emergencia_relacion` string(100) NULL; `emergencia_telefono` string(30) NULL; `version` unsignedInteger NOT NULL por defecto 1; `operacion_id` char(36) NOT NULL; `operacion_hash` char(64) NOT NULL; `created_by`/`updated_by` unsignedBigInteger NOT NULL sin FK; timestamps; únicos `(tenant_id, codigo)`, `(tenant_id, tipo_identificacion, identificacion_clave)`, `(tenant_id, operacion_id)` e índice `(tenant_id, apellidos_busqueda, nombres_busqueda, id)`. Sin columnas laborales ni financieras
- [X] T008 Crear la migración `backend/database/migrations/2026_10_15_000004_create_colaborador_cuentas_table.php`: `tenant_id`; `user_id` UNIQUE sin FK; `colaborador_id` UNIQUE con FK a `colaboradores.id`; `puede_consultar_ficha`, `puede_consultar_documentos`, `puede_exportar` boolean NOT NULL por defecto false; timestamps
- [X] T009 Ampliar `Shared` de forma aditiva: `tenantId` en `backend/app/Modules/Shared/Application/DTO/UsuarioDTO.php`, `tenant: {id, nombre}` en `Presentation/Resources/UsuarioResource.php`, `getContextoDeTenant(int $userId): array` (`{tenant_id, nombre, zona_horaria}`) en `Application/Services/SharedService.php` y su `@method` en `Presentation/Facades/SharedFacade.php`; `tenant_id` y relación en `UserModel`; ajustar las pruebas de 000-login que comparen el JSON exacto en `backend/tests/Feature/Shared/`

### Dominio y puertos

- [X] T010 [P] Implementar `M/Domain/Services/NormalizadorDeTexto.php`: `claveDeIdentificacion` = `Normalizer::normalize(trim(x), NFC)` + `mb_strtolower`, conservando ceros, separadores internos y tildes; `busquedaDeTexto` = `mb_strtolower(Str::ascii(trim(x)))`; con prueba Unit en `backend/tests/Unit/GestionPersonal/NormalizadorDeTextoTest.php` que confirme `Normalizer` disponible (NFD «e»+U+0301 ≡ NFC), «0102-03» ≠ «010203», « AB123 » = «ab123», «Núñez» → «nunez»
- [X] T011 [P] Crear los objetos de valor en `M/Domain/ValueObjects/`: `TipoIdentificacion` (`Cedula`|`Pasaporte`|`Otro`), `Identificacion`, `CodigoColaborador` (`EMP-` + id con mínimo 3 dígitos, crece sobre 999), `ContactoDeEmergencia`, `DatosFicha`, `EstadoLaboral` (`SinVinculoVigente`) y `Lectura` (`disponible($datos)`, `noInstalado()`, `noDisponible()`)
- [X] T012 [P] Crear las excepciones de dominio en `M/Domain/Exceptions/`: `ColaboradorNoEncontrado`, `IdentificacionDuplicada` (con id y código de la ficha existente), `FichaModificada`, `ClaveDeOperacionReutilizada`, `CuentaSinFicha`, `SinPermisoDeFicha`
- [X] T013 Crear los puertos en `M/Application/Ports/` según contracts/personal-facade.md: `ContextoDeTenantPort`, `LectorLaboralPort`, `LectorEstructuraPort`, `LectorDeCapacidadesPort`, `AccesosDeCuentaPort`, y los DTO `ContextoDeTenant` y `AccesoDeCuenta`
- [X] T014 [P] Crear los adaptadores en `M/Infrastructure/Adapters/`: `SharedContextoDeTenant` (sobre `SharedFacade::getContextoDeTenant`), `LectorLaboralNoInstalado`, `LectorEstructuraNoInstalado` y `LectorDeCapacidadesNoInstalado` (siempre `noInstalado()` / `false`; sin lanzar excepciones); enlazarlos en `GestionPersonalServiceProvider`
- [X] T015 Crear `M/Domain/Entities/Colaborador.php` y `M/Domain/Repositories/ColaboradorRepositoryInterface.php` con firmas que reciben `tenantId` obligatorio: `porId`, `porIdentificacion`, `porOperacion`, `crear`, `actualizar`, `listar`, `estadosPresentes`
- [X] T016 Crear `ColaboradorModel` y `ColaboradorCuentaModel` en `M/Infrastructure/Persistence/Eloquent/Models/` y `EloquentColaboradorRepository` en `M/Infrastructure/Persistence/Eloquent/Repositories/` con `porId`, `porIdentificacion` y `porOperacion` (siempre `where tenant_id`); enlazarlo en el provider
- [X] T017 Crear `M/Application/Services/ArmadorDeFichaDetalle.php`, `M/Application/DTO/{ColaboradorDTO,ColaboradorResumenDTO,FichaDetalleDTO}.php` y `M/Presentation/Resources/ColaboradorDetalleResource.php` que producen el detalle de contracts/colaboradores-api.md: bloques `laboral` y `trayectoria` con `disponibilidad` independiente, `hechos` por fecha efectiva descendente con `programado` según la zona del tenant, `capacidades` y `acciones.editar`; sin campos de adecuación, potencial ni económicos
- [X] T018 Crear `backend/database/factories/ColaboradorFactory.php`, `backend/database/seeders/PersonalSeeder.php` (tenants, fichas y accesos de data-model.md) y ampliar `UsuariosInicialesSeeder.php` y `DatabaseSeeder.php` con `admin2@hrms.test`, `colaborador2@hrms.test`, `sinficha@hrms.test`, `sinpermiso@hrms.test` y `tenant_id` en todas las cuentas
- [X] T019 [P] Frontend base en `F/`: `text.ts` (todos los textos en español: «Colaboradores», «Ficha», «Nueva ficha», «Ver ficha», «Mi ficha», «Sin vínculo vigente», «No registrado», «Información no disponible», «Ningún colaborador coincide con la búsqueda»), tipos y funciones HTTP en `api.ts` (cliente compartido, sin Axios en páginas) y `components/{RequireAdmin,DatoOpcional}.tsx` (`DatoOpcional` muestra «No registrado» para `null` y `0` para 0)
- [X] T020 [P] Registrar en `frontend/src/App.tsx` las rutas de plan.md (`/colaboradores`, `/colaboradores/nuevo`, `/colaboradores/:colaboradorId`, `/colaboradores/:colaboradorId/editar`, `/mi-ficha`) con páginas marcador, y crear `F/nav.tsx` («Colaboradores» solo Administrador; «Mi ficha»)
- [X] T021 [P] Crear `frontend/src/test/fichasFixtures.ts` con fichas sintéticas (mínima, completa, con tildes, mismo apellido, dos tenants)

**Checkpoint**: migraciones aplican (`php artisan migrate:fresh --seed`), puertos y adaptadores enlazados.

---

## Phase 3: User Story 1 - Registrar una ficha mínima (P1) 🎯 MVP

**Goal**: El Administrador crea una ficha con identificación, nombres y apellidos; recibe `EMP-###`, estado «Sin vínculo vigente», sin cuenta ni vínculo.

**Independent Test**: Crear una ficha mínima como Administrador (201, código, `version` 1); repetir con la misma `Idempotency-Key` (200 misma ficha); crear otra con la misma identificación (422 `IDENTIFICACION_DUPLICADA`).

### Tests for User Story 1

- [X] T022 [P] [US1] Prueba Unit de `ReglasDeFicha` en `backend/tests/Unit/GestionPersonal/ReglasDeFichaTest.php`: contacto de emergencia (los tres vacíos o nombre y teléfono juntos), fecha de nacimiento igual a hoy válida y mañana inválida, `numero_hijos` 0 válido y -1 inválido, texto solo con espacios = vacío
- [X] T023 [P] [US1] Prueba Feature GP01-CA01/RN02/RN04 en `backend/tests/Feature/GestionPersonal/CrearFichaTest.php`: alta mínima 201 sin cuenta/vínculo, `EMP-1000` al superar 999 (forzando `sqlite_sequence`), reintento con misma clave 200, misma clave con otro cuerpo 422 `CLAVE_DE_OPERACION_REUTILIZADA`, sin cabecera 422 `CLAVE_DE_OPERACION_REQUERIDA`, envíos simultáneos con la misma clave resuelven en una ficha
- [X] T024 [P] [US1] Prueba Feature GP01-CA02/RN01/RN03 en `backend/tests/Feature/GestionPersonal/IdentificacionDuplicadaTest.php`: « 0102030405 » duplica, Pasaporte `ab123` duplica `AB123`, Cédula `AB123` y Otro `AB123` aceptadas, «0102-03» ≠ «010203», misma identificación en otro tenant aceptada, `ficha_existente` en el 422, colisión simultánea con claves distintas deja una sola ficha
- [X] T025 [P] [US1] Prueba Feature GP01-CA04 (escritura) en `backend/tests/Feature/GestionPersonal/AutorizacionCrearTest.php`: 401 sin token, 403 con rol `user`, `tenant_id`/`codigo`/`estado`/`id` en el cuerpo → 422 `CAMPO_NO_PERMITIDO`, `ESCRITURA_DESHABILITADA` 503 con la bandera en `false`
- [X] T026 [P] [US1] Pruebas Vitest en `F/validation.test.ts` y `F/components/FichaFormulario.dom.test.tsx`: errores por campo, contadores `n/máximo`, valores conservados tras error, botón bloqueado durante el guardado, código «Se asigna al guardar», sin acción eliminar

### Implementation for User Story 1

- [X] T027 [P] [US1] Implementar `M/Domain/Services/ReglasDeFicha.php` (contacto de emergencia, fecha no futura recibiendo «hoy» del tenant, hijos ≥ 0, telefonos `^\+?[0-9 ()\-]+$`)
- [X] T028 [P] [US1] Implementar `M/Application/DTO/DatosFichaDTO.php` y `M/Presentation/Requests/GuardarColaboradorRequest.php` con longitudes de data-model.md (`numero_identificacion` ≤ 50; `nombres`/`apellidos` 1–150; `nacionalidad` ≤ 100; `genero`/`estado_civil` ≤ 80; `direccion` ≤ 500; `telefono` ≤ 30; `correo_contacto` ≤ 254 formato válido; `emergencia_nombre` ≤ 150; `emergencia_relacion` ≤ 100; `emergencia_telefono` ≤ 30), recorte de texto y rechazo de campos no declarados con `CAMPO_NO_PERMITIDO`
- [X] T029 [US1] Añadir `crear` a `EloquentColaboradorRepository`: una transacción que inserta con `codigo` temporal `TMP-<operacion_id>`, lee el `id`, fija `codigo = EMP-` + id (≥ 3 dígitos), `version` 1, `created_by = updated_by`, claves normalizadas y `operacion_hash`; ante violación única cierra la transacción y devuelve el motivo para resolver en una nueva (depende de T015, T016)
- [X] T030 [US1] Implementar `M/Application/UseCases/RegistrarFicha.php` según research D-07: comprobar bandera de escritura, resolver operación existente (200 / reutilizada), validar, comprobar duplicidad, crear y, tras conflicto, resolver en nueva transacción (depende de T027–T029)
- [X] T031 [US1] Implementar `store` en `M/Presentation/Controllers/ColaboradorController.php` (201/200/422/503, errores con `code`; sin consultas de base de datos) y la ruta `POST /api/colaboradores` con `auth:sanctum` + `admin` en `M/Routes/api.php`; añadir `registrarFicha` y `getColaborador` a `M/Presentation/Facades/PersonalFacade.php`
- [X] T032 [P] [US1] Frontend: esquema Zod en `F/validation.ts` y `F/components/{FichaFormulario,AlertaDuplicada}.tsx` (cuatro `fieldset`: Identificación, Datos personales, Contacto, Contacto de emergencia; errores por campo con mapeo 422)
- [X] T033 [US1] Frontend: `F/hooks/useGuardarFicha.ts` (mutación sin reintentos, `Idempotency-Key` por `crypto.randomUUID()` al montar, regenerada tras `CLAVE_DE_OPERACION_REUTILIZADA`, invalidación de `["colaboradores"]`) y `F/pages/FichaFormPage.tsx` en modo crear con Cancelar sin confirmación y redirección al detalle con aviso

**Checkpoint**: US1 verificable por API y formulario.

---

## Phase 4: User Story 2 - Listado y detalle (P1)

**Goal**: El Administrador lista, busca, filtra y consulta fichas de su tenant.

**Independent Test**: Con dos tenants, listar como Administrador (solo el propio), buscar «nunez», abrir un detalle con «No registrado» y trayectoria vacía; ficha ajena → 404.

### Tests for User Story 2

- [X] T034 [P] [US2] Prueba Feature GP01-CA04/CA05 (lectura) en `backend/tests/Feature/GestionPersonal/ListarYConsultarTest.php`: aislamiento entre tenants, 404 idéntico para inexistente y de otro tenant, 403 al `user`, orden apellidos/nombres/código, 20 por página, búsqueda sin tildes/mayúsculas por nombre, apellido y código, `%` y `_` escapados, filtros combinados y paginación, `unidad_id` con Estructura no instalada → 422, ficha mínima con `null` y `0` hijos preservado
- [X] T035 [P] [US2] Prueba Feature de lectores en `backend/tests/Feature/GestionPersonal/LectoresTest.php` con puertos falsos: `noInstalado` → «Sin vínculo vigente»/`null`; `noDisponible` → `laboral.disponibilidad = no_disponible` con datos personales presentes; filtro dependiente con lector caído → 503; hecho futuro con `programado: true`; consultas por lote (sin N+1)
- [X] T036 [P] [US2] Pruebas Vitest en `F/pages/ColaboradoresPage.dom.test.tsx` y `F/pages/FichaDetallePage.dom.test.tsx`: estados cargando, vacío, sin coincidencias, error con Reintentar, éxito; filtros y página en la URL; «No registrado» vs «Información no disponible»; Desarrollo/Documentos ausentes; sin Editar para el Usuario
- [X] T037 [P] [US2] E2E en `frontend/e2e/fichas.spec.ts` (flujo principal): Administrador crea una ficha, la encuentra por apellido sin tildes y abre su detalle

### Implementation for User Story 2

- [X] T038 [P] [US2] Implementar `M/Application/DTO/{FiltroColaboradoresDTO,ListadoColaboradoresDTO}.php` y `M/Presentation/Requests/ListarColaboradoresRequest.php` (`q` ≤ 50, `estado`, `unidad_id`, `page` ≥ 1, `per_page` 1–50 por defecto 20)
- [X] T039 [US2] Añadir `listar` y `estadosPresentes` a `EloquentColaboradorRepository` según research D-10: tokens AND sobre `nombres_busqueda || ' ' || apellidos_busqueda`, OR con código y `id IN`, escape de comodines, orden `apellidos_busqueda, nombres_busqueda, id`, `where tenant_id` siempre
- [X] T040 [US2] Implementar `ListarColaboradores`, `ObtenerFiltrosDeColaboradores` y `ObtenerColaborador` en `M/Application/UseCases/` (enriquecimiento por lote, 503 `INFORMACION_NO_DISPONIBLE` solo con filtro activo dependiente, 404 uniforme)
- [X] T041 [US2] Añadir `index`, `show` y `filtros` a `ColaboradorController`, `ColaboradorResumenResource` y las rutas `GET` (con `/filtros` antes de `/{id}` y `whereNumber`) con `auth:sanctum` + `admin`; ampliar `PersonalFacade` con `getColaboradoresPorIds` y `buscarPorIdentificacion`
- [X] T042 [P] [US2] Frontend: `F/hooks/{useColaboradores,useListadoParams}.ts` (URL con `useSearchParams`, debounce 300 ms, cancelación por `signal`, página 1 al filtrar) y `F/components/ColaboradoresTable.tsx`, `F/pages/ColaboradoresPage.tsx` con cargando/vacío/sin coincidencias/error/éxito y «Nueva ficha»
- [X] T043 [P] [US2] Frontend: `F/components/{FichaDatos,SeccionLaboral,Trayectoria}.tsx` y `F/pages/FichaDetallePage.tsx` (encabezado nombre/código/estado, sección Personal y puesto, Reintentar de sección, hecho «Programado», Editar solo si `acciones.editar`, sin acciones aún no funcionales)

**Checkpoint**: US1 y US2 operan juntas (MVP).

---

## Phase 5: User Story 3 - Corregir los datos (P2)

**Goal**: El Administrador corrige una ficha conservando código y asociaciones; la edición concurrente no sobrescribe.

**Independent Test**: Cambiar la identificación a una libre (código intacto, `version` +1); a una ocupada (422, sin cambios); con `version` obsoleta (409).

### Tests for User Story 3

- [X] T044 [P] [US3] Prueba Feature GP01-CA03/CA06/RN05 en `backend/tests/Feature/GestionPersonal/ActualizarFichaTest.php`: corrección conserva `id`/`codigo`/asociación de `colaborador_cuentas`, identificación ocupada 422 sin cambios, conservar la propia o cambiar solo mayúsculas no duplica, `null` vacía opcionales y rechaza obligatorios, contacto de emergencia validado sobre el conjunto resultante, campos omitidos intactos, sin cambios reales no altera `version`/`updated_at`, `version` obsoleta 409 conserva la primera escritura, `DELETE` 404/405, 403 al `user`
- [X] T045 [P] [US3] Pruebas Vitest en `F/pages/FichaFormPage.edit.dom.test.tsx` y E2E en `frontend/e2e/fichas.spec.ts`: código solo lectura, valores actuales, 409 con «Actualizar datos» conservando lo escrito, duplicado con «Abrir ficha existente»

### Implementation for User Story 3

- [X] T046 [US3] Añadir `actualizar` a `EloquentColaboradorRepository`: `UPDATE … WHERE id = ? AND tenant_id = ? AND version = ?`, recalcula claves y búsquedas, `version + 1`, `updated_by`; 0 filas ⇒ `FichaModificada`
- [X] T047 [US3] Implementar `M/Application/UseCases/ActualizarFicha.php` (bandera de escritura, ficha del tenant, validar estado resultante, duplicidad excluyendo la propia, sin cambios ⇒ sin escritura, `version` obsoleta ⇒ 409) y `update` (`PATCH /api/colaboradores/{id}`, `auth:sanctum` + `admin`) en controlador y rutas
- [X] T048 [US3] Frontend: modo editar en `F/pages/FichaFormPage.tsx` (carga con esqueleto, envía `version` y solo campos cambiados) y alerta de conflicto en `F/components/FichaFormulario.tsx`; enlace Editar del detalle

**Checkpoint**: US1–US3 independientes.

---

## Phase 6: User Story 4 - Mi ficha (P2)

**Goal**: El Colaborador consulta solo su ficha con asociación válida y permiso.

**Independent Test**: `usuario@hrms.test` ve EMP-001; `sinficha` y `sinpermiso` reciben su mensaje; pedir otra ficha → 403.

### Tests for User Story 4

- [X] T049 [P] [US4] Prueba Feature GP01-CA04 en `backend/tests/Feature/GestionPersonal/MiFichaTest.php`: 200 con `acciones.editar=false`, 403 `CUENTA_SIN_FICHA`, 403 `SIN_PERMISO_FICHA`, colaborador pidiendo `/api/colaboradores/{id}` ajeno → 403, asociación de otro tenant no concede acceso, `capacidades.documentos=false` sin permiso, permisos independientes
- [X] T050 [P] [US4] Pruebas Vitest en `F/pages/MiFichaPage.dom.test.tsx`: solo lectura, mensajes de cuenta sin ficha y sin permiso, sin «Colaboradores» en el menú

### Implementation for User Story 4

- [X] T051 [P] [US4] Implementar `EloquentAccesosDeCuenta` en `M/Infrastructure/Persistence/Eloquent/Repositories/` (solo lectura, misma `tenant_id` de cuenta y ficha) y enlazar `AccesosDeCuentaPort`
- [X] T052 [US4] Implementar `M/Application/UseCases/ObtenerMiFicha.php`, `M/Presentation/Controllers/MiFichaController.php` y la ruta `GET /api/mi-ficha` con solo `auth:sanctum`
- [X] T053 [US4] Frontend: `F/hooks/useMiFicha.ts` y `F/pages/MiFichaPage.tsx` reutilizando `FichaDatos`, `SeccionLaboral` y `Trayectoria`; ítem «Mi ficha» en `F/nav.tsx`

**Checkpoint**: las cuatro historias completas.

---

## Phase 7: Polish & Cross-Cutting

- [X] T054 [P] Revisión de aislamiento: comprobar con búsqueda en `M/` que toda consulta del repositorio filtra por `tenant_id` y que ningún controlador consulta la base de datos
- [X] T055 [P] Verificar 320 px y 1440 px sin desplazamiento horizontal, foco, `aria-*` y contraste (WCAG 2.2 AA) en listado, formulario, detalle y Mi ficha; temas claro y oscuro
- [X] T056 [P] Confirmar en `design/DESIGN.md` §9 que todas las diferencias del prototipo quedaron registradas y que `design/` no contiene lógica de aplicación
- [X] T057 Ejecutar `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`, y `npm run lint`, `npx tsc -b`, `npm test -- --run`, `npm run build` y `npx playwright test fichas` en `frontend/`
- [X] T058 Ejecutar las validaciones de `specs/005-fichas-colaboradores/quickstart.md` y confirmar los seis GP01-CA

---

## Dependencies & Execution Order

- **Setup (1)** → **Foundational (2)** bloquea todas las historias. Dentro de la fase 2: T005→T006→T009; T007→T008; T010–T012 paralelos; T013→T014; T015→T016→T017→T018.
- **US1** y **US2** (P1) dependen solo de la fase 2; el redireccionamiento tras crear usa la página de detalle (T043), por lo que el MVP completo es US1+US2.
- **US3** depende de T016/T029/T039 (repositorio) pero no de la interfaz de US2; **US4** depende de T017 y T043 para reutilizar componentes.
- Dentro de cada historia: pruebas (fallan) → reglas/DTO → repositorio → caso de uso → controlador/rutas → frontend.
- **Polish** al final.

## Parallel Examples

```text
Fase 2:  T010 · T011 · T012 · T019 · T020 · T021
US1:     T022 · T023 · T024 · T025 · T026  → luego T027 · T028 · T032
US2:     T034 · T035 · T036 · T037         → luego T038 · T042 · T043
```

## Implementation Strategy

- **MVP**: Setup + Foundational + US1 + US2 (registrar y consultar); validar con quickstart pasos 1–9.
- **Incremental**: añadir US3 (corregir), luego US4 (Mi ficha); cada historia se valida de forma independiente.
- No se instalan dependencias nuevas; `Normalizer` llega por el polyfill ya presente (T010 lo confirma).

## Notes

- [P] = archivos distintos, sin dependencias pendientes. Commit por tarea o grupo lógico.
- No crear acciones de eliminar, desactivar, ingreso ni exportación; no tocar tablas de Estructura ni la autenticación más allá de T009.
