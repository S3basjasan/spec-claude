---

description: "Tareas de 01b.2 · Puntaje atado al parámetro"
---

# Tasks: Puntaje atado al parámetro (01b.2)

**Input**: Documentos de diseño en `/specs/025-01b.2-puntaje-atado-al-parametro/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/categorias-puntaje-api.md, quickstart.md

**Tests**: Se incluyen: la constitución exige cobertura automatizada (Pest, Vitest y un E2E del flujo principal) y el plan de origen enumera las pruebas.

**Organization**: Tareas agrupadas por historia de usuario. La Phase 2 deja el cálculo del máximo y la regla de dominio; cada historia añade su parte visible o su verificación. No hay migraciones ni tablas nuevas.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia a la que pertenece (US1…US5)

## Path Conventions

- Backend: `backend/app/Modules/Compensaciones/`, pruebas en `backend/tests/Feature/Compensaciones/` y `backend/tests/Unit/Compensaciones/`
- Frontend: `frontend/src/modules/compensaciones/categorias/`, E2E en `frontend/e2e/`
- Diseño: `design/`
- Rutas HTTP bajo `/api/compensaciones/`; mensajes exactos: «La puntuación base debe estar entre 1 y {máximo}.» y «Baja primero la puntuación a {máximo} o menos.»

---

## Phase 1: Setup

**Purpose**: Línea base

- [X] T001 Ejecutar la suite actual `cd backend && ./vendor/bin/pest tests/Feature/Compensaciones tests/Unit/Compensaciones` y `cd frontend && npx vitest run src/modules/compensaciones`, y anotar que todo pasa (línea base)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Cálculo único del máximo permitido y regla de dominio parametrizada. Ninguna historia puede empezar antes.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T002 Añadir a `backend/app/Modules/Compensaciones/Application/Services/ParametrosDelModulo.php` el método `puntuacionMaximaDeCategoria(): int` = menor entre `valorVigente(ClaveDeParametro::PuntosPorColaborador)` (hoy, zona del módulo) y el valor de `programado(PuntosPorColaborador)` si existe; no se almacena. Casos: vigente 100 sin programado → 100; programado 60 → 60; programado 150 → 100; programado 100 → 100 (ver tabla de `data-model.md`)
- [X] T003 [P] Modificar `backend/app/Modules/Compensaciones/Domain/ValueObjects/PuntuacionBase.php`: el constructor recibe un segundo argumento `int $maximo = self::MAXIMA` (1000 queda como límite exterior y valor por defecto, para que reconstruir categorías ya guardadas, p. ej. `EloquentCategoriaRepository` línea 245, siga siendo permisivo); si `valor < 1` o `valor > $maximo` lanza `DatosDeCategoriaInvalidos('puntuacion_base', "La puntuación base debe estar entre 1 y {$maximo}.")`; el mensaje de no entero no cambia
- [X] T004 Propagar el máximo: `backend/app/Modules/Compensaciones/Domain/Entities/Categoria.php` (`propuesta(..., ?int $puntuacionMaxima = null)` pasa el máximo a `new PuntuacionBase($puntuacion, $max)`; sin argumento conserva el comportamiento actual) y `backend/app/Modules/Compensaciones/Application/DTOs/DatosCategoriaDTO.php` (`comoPropuesta(EstadoCategoria $estado = EstadoCategoria::Activo, ?int $puntuacionMaxima = null)`)
- [X] T005 [P] Añadir pruebas unitarias en `backend/tests/Unit/Compensaciones/DominioTest.php`: `PuntuacionBase` con máximo 100 acepta 1 y 100, rechaza 0, 101 y `'1.5'` con «entre 1 y 100»; sin segundo argumento sigue aceptando hasta 1000; y una prueba de `puntuacionMaximaDeCategoria()` en `backend/tests/Feature/Compensaciones/PuntajeAtadoAlParametroTest.php` para los cuatro casos de T002 (programado creado con filas de `parametros_modulo` y `Carbon::setTestNow`)

**Checkpoint**: el máximo se calcula en un solo lugar y el dominio sabe validarlo.

---

## Phase 3: User Story 1 - Puntuación acotada al crear o editar (Priority: P1) 🎯 MVP

**Goal**: Crear y editar una categoría solo acepta enteros de 1 al máximo permitido, también cuando solo cambia otro campo (FR-001, FR-002, FR-006, FR-010).

**Independent Test**: Con puntos vigentes 100, crear y editar con 100 se guarda y con 101 se rechaza; con un programado de 60 el tope es 60; con programado de 150 sigue siendo 100.

### Tests for User Story 1

- [X] T006 [P] [US1] En `backend/tests/Feature/Compensaciones/PuntajeAtadoAlParametroTest.php` (mismo archivo que T005, tras él): crear y editar con puntuación igual al máximo → 201 / 200; máximo + 1 → 422 en `puntuacion_base` con «La puntuación base debe estar entre 1 y 100.»; límite inferior 1 se guarda; programado menor (vigente 100, programado 60): 60 se acepta y 61 se rechaza con «entre 1 y 60»; programado mayor (150): 101 se rechaza; vigente cambia entre abrir y guardar → se usa el máximo del momento de guardar
- [X] T007 [P] [US1] En el mismo archivo: editar **solo el nombre** de una categoría cuya puntuación ya supera el máximo → 422 y la categoría no cambia (FR-010); bajar la puntuación y cambiar el nombre a la vez → 200; guardar sin cambios con puntuación dentro del tope → 200 sin tocar fecha ni auditoría; con versión desactualizada y puntuación fuera de tope → 409 antes que 422

### Implementation for User Story 1

- [X] T008 [US1] Modificar `backend/app/Modules/Compensaciones/Application/UseCases/CrearCategoria.php`: inyectar `ParametrosDelModulo` y construir la propuesta con `$datos->comoPropuesta(EstadoCategoria::Activo, $this->parametros->puntuacionMaximaDeCategoria())` (calculado en el momento de guardar, antes de la transacción)
- [X] T009 [US1] Modificar `backend/app/Modules/Compensaciones/Application/UseCases/ActualizarCategoria.php`: inyectar `ParametrosDelModulo`; dentro de la transacción, tras el chequeo de versión, construir la propuesta con `comoPropuesta($actual->estado, máximo)` **antes** de comparar diferencias, de modo que el rechazo ocurra aunque solo cambie otro campo; actualizar el docblock `@throws`
- [X] T010 [US1] Comprobar que `CrearCategoria` y `ActualizarCategoria` se resuelven desde el contenedor con `ParametrosDelModulo` inyectado (ya está enlazado en `backend/app/Modules/Compensaciones/Infrastructure/Providers/CompensacionesServiceProvider.php` por 01b.1): criterio de cierre, las pruebas T006 y T007 pasan sin tocar el proveedor; solo modificarlo si fallan por no poder resolver la dependencia
- [X] T011 [US1] Ajustar las pruebas existentes que suponían el tope fijo de 1000: `backend/tests/Feature/Compensaciones/CrearCategoriaTest.php` (línea ~135 crea con `puntuacion_base` 1000; línea ~112 dataset `puntuación 1001`) y `ActualizarCategoriaTest.php` (línea ~204, 1001), dejando el límite exterior 1000 cubierto y usando valores ≤ máximo en los casos felices; ejecutar `./vendor/bin/pest tests/Feature/Compensaciones tests/Unit/Compensaciones`

**Checkpoint**: la regla se cumple en crear y editar para todo cliente de la API.

---

## Phase 4: User Story 2 - Ver el máximo permitido en el formulario (Priority: P1)

**Goal**: El formulario muestra «Máximo: {n} puntos» junto al campo, indica cuando el tope baja por un cambio programado y muestra el error en el campo conservando lo escrito (FR-003, FR-004).

**Independent Test**: Abrir el formulario de crear y de editar con y sin cambio programado y comprobar el texto; escribir un valor fuera de rango y ver el error en el campo sin guardar.

### Tests for User Story 2

- [X] T012 [P] [US2] En `backend/tests/Feature/Compensaciones/ParametrosPuntosTest.php` añadir: `GET /api/compensaciones/parametros` incluye `meta.puntuacion_maxima_categoria` correcto para vigente 100 sin programado (100), programado 60 (60) y programado 150 (100); `data` no cambia; sin `admin` sigue 403
- [X] T013 [P] [US2] En `frontend/src/modules/compensaciones/categorias/validation.test.ts`: `crearCategoriaSchema(100)` acepta `"1"` y `"100"`, rechaza `"0"`, `"101"`, `"1.5"` y vacío con «La puntuación base debe estar entre 1 y 100.»; sin máximo usa 1000
- [X] T014 [P] [US2] En `frontend/src/modules/compensaciones/categorias/components/CategoriaFormModal.dom.test.tsx`: muestra «Máximo: 100 puntos» junto al campo; con máximo proveniente de un programado menor muestra además la nota del cambio del mes siguiente; un valor fuera de rango muestra el error en el campo, conserva lo escrito y no llama al guardado; un 422 en `puntuacion_base` se muestra junto al campo; si la consulta del máximo falla o responde 403 (permiso `parametros.gestionar` distinto de `categorias.gestionar`) el formulario sigue usable con el texto genérico sin número y el servidor decide

### Implementation for User Story 2

- [X] T015 [US2] Modificar `backend/app/Modules/Compensaciones/Application/UseCases/ConsultarParametros.php` para devolver también el máximo (`puntuacionMaximaDeCategoria()`), y `backend/app/Modules/Compensaciones/Presentation/Resources/ParametrosResource.php` y `Presentation/Controllers/ParametrosController.php` para responder `{ "data": [...], "meta": { "puntuacion_maxima_categoria": N } }` tanto en `GET` como en el `PUT` exitoso (contrato en `contracts/categorias-puntaje-api.md`); si cambia el tipo de retorno, ajustar los usos existentes y `ParametrosAccesoTest`/`ParametrosPuntosTest`
- [X] T016 [P] [US2] Añadir a `frontend/src/modules/compensaciones/categorias/api.ts` `obtenerPuntuacionMaxima(signal?)` (`GET /api/compensaciones/parametros` → `meta.puntuacion_maxima_categoria`, sin importar código interno de `parametros/`) y crear `frontend/src/modules/compensaciones/categorias/hooks/usePuntuacionMaxima.ts` (TanStack Query, clave propia dentro de la familia de categorías, `refetchOnMount: 'always'`, sin reintento automático de errores 4xx) que devuelve `{ maximo, bajaPorProgramado }`: `maximo` = `meta.puntuacion_maxima_categoria` y `bajaPorProgramado` = `maximo` es menor que el `valor` de la entrada `puntos_por_colaborador` de `data` (decide si se muestra la nota del cambio programado); en 403 o error devuelve `maximo` indefinido
- [X] T017 [P] [US2] En `frontend/src/modules/compensaciones/categorias/text.ts` añadir los textos en español: «Máximo: {n} puntos», nota «Baja a {n} desde el día 1 del mes siguiente por el cambio de puntos por colaborador.», texto genérico cuando no se conoce el máximo, y reemplazar `validation.pointsRange` por una función `pointsRange(maximo)` = «La puntuación base debe estar entre 1 y {maximo}.»; actualizar `form.pointsHint` («Número entero de 1 al máximo permitido…») conservando la aclaración «no es dinero ni se canjea»
- [X] T018 [US2] En `frontend/src/modules/compensaciones/categorias/validation.ts` convertir `categoriaSchema` en `crearCategoriaSchema(maximo = 1000)` (mismo esquema; `puntuacion_base` valida entero y `1 ≤ n ≤ maximo` con `text.pointsRange(maximo)`), exportando también `categoriaSchema = crearCategoriaSchema()` solo si otro archivo lo sigue importando
- [X] T019 [US2] Modificar `frontend/src/modules/compensaciones/categorias/components/CategoriaFormModal.tsx`: usar `usePuntuacionMaxima()`, construir el resolver con `useMemo(() => zodResolver(crearCategoriaSchema(maximo)), [maximo])`, poner `max={maximo ?? 1000}` en el campo, mostrar el texto «Máximo: {n} puntos» (y la nota del programado cuando aplique) en el `<p>` de ayuda existente (`text-[13px] text-muted`, enlazado con `aria-describedby`), y dejar que un 422 del servidor se muestre con `problemaDeGuardado` ya existente
- [X] T020 [P] [US2] Actualizar `design/CategoriasReconocimiento.dc.html` (campo de puntuación: línea ~564 `max="1000"`, ayuda ~567 «Número entero de 1 a 1000…», validación ~998) para mostrar «Máximo: {n} puntos» y el error «La puntuación base debe estar entre 1 y {n}.», y añadir una línea en la sección Categorías de `design/DESIGN.md` (constitución III: el diseño se actualiza antes de la interfaz)

**Checkpoint**: el Gestor ve el tope antes de escribir y entiende por qué se rechaza.

---

## Phase 5: User Story 3 - Validación también en el servidor (Priority: P1)

**Goal**: Ninguna vía distinta del formulario puede dejar una categoría por encima del máximo (FR-005).

**Independent Test**: Llamar a la API directamente con puntuaciones mayores al máximo, decimales y texto y comprobar 422 con el error en `puntuacion_base` y sin cambios guardados.

### Tests for User Story 3

- [X] T021 [P] [US3] Crear `backend/tests/Feature/Compensaciones/PuntajeAtadoValidacionServidorTest.php`: `POST` y `PUT` con puntuación > máximo → 422 con `{message, errors.puntuacion_base: ["La puntuación base debe estar entre 1 y {máximo}."]}`; con `1.5`, `"abc"`, `null`, `0`, `-5` y `1001` → 422 en `puntuacion_base`; ninguna fila creada ni modificada y sin registros de auditoría; sin `admin` → 403 y sin sesión → 401 (no cambian)

### Implementation for User Story 3

- [X] T022 [US3] Cerrar US3 cuando `PuntajeAtadoValidacionServidorTest` (T021) pase completo. `CategoriaController::responder` ya traduce `DatosDeCategoriaInvalidos` a 422 en `store` y `update`, por lo que no se espera código de producción nuevo; si algún caso escapa como 500 o con otra forma, corregir `backend/app/Modules/Compensaciones/Presentation/Controllers/CategoriaController.php` o el mensaje `puntuacion_base.between` de `Presentation/Requests/GuardarCategoriaRequest.php` (el límite exterior 1000 se mantiene)

**Checkpoint**: la regla es confiable aunque se omita la interfaz.

---

## Phase 6: User Story 4 - Los registros ya hechos no cambian (Priority: P2)

**Goal**: Cambiar la puntuación de una categoría no modifica los puntos de reconocimientos ni de desafíos registrados (FR-007).

**Independent Test**: Con registros existentes, bajar la puntuación de su categoría y comprobar que sus puntos y las demás categorías no cambian.

### Tests for User Story 4

- [X] T023 [P] [US4] Crear `backend/tests/Feature/Compensaciones/PuntajeAtadoSinEfectosTest.php` (**cobertura parcial**: `reconocimientos` y `desafios` aún no existen en el repositorio y que sus puntos se copian al registrarse ya lo comprueban las pruebas de las specs de inc-02 e inc-03). Con una categoría de puntuación 100, bajarla a 60 por `PUT` y activar/desactivar otra → solo cambian filas de `categorias_reconocimiento` y su auditoría (comparar el conteo y contenido de las demás tablas antes y después), las otras categorías no cambian y una categoría nueva usa 60. Si `reconocimientos` o `desafios` ya existen cuando se implemente, añadir la comprobación de que sus `puntos` no varían

### Implementation for User Story 4

- [X] T024 [US4] Cerrar US4 cuando T023 pase y una búsqueda (`grep`) en `backend/app/Modules/Compensaciones/Application/UseCases/ActualizarCategoria.php`, `CrearCategoria.php` y `CambiarEstadoCategoria.php` confirme que no escriben fuera de `CategoriaRepository` y `AuditoriaDeCategoria`; no se espera código de producción nuevo (FR-009 queda cubierto de forma implícita por esta misma comprobación: ningún caso de uso ajusta puntuaciones fuera de crear, editar y activar)

**Checkpoint**: el historial queda intacto.

---

## Phase 7: User Story 5 - Activación y reglas existentes (Priority: P2)

**Goal**: Activar una categoría inactiva por encima del máximo se rechaza (FR-011) y todo lo demás de categorías se comporta como antes (FR-008).

**Independent Test**: Activar una inactiva con puntuación mayor que el máximo → 422 y sigue inactiva; igual al máximo → se activa; desactivar y las demás reglas sin cambios.

### Tests for User Story 5

- [X] T025 [P] [US5] Crear `backend/tests/Feature/Compensaciones/PuntajeAtadoActivacionTest.php`: `PATCH /categorias/{id}/estado` a `Activo` con puntuación > máximo → 422 con `errors.puntuacion_base: ["Baja primero la puntuación a {máximo} o menos."]`, sigue `Inactivo`, sin registro de auditoría; con puntuación = máximo → 200 y activa; tras bajarla por `PUT` y activar → 200; pedir `Activo` a una ya activa por encima del tope → 200 sin cambios; desactivar → 200 siempre; versión desactualizada → 409 antes que 422; máximo tomado del programado cuando es menor
- [X] T026 [P] [US5] En `frontend/src/modules/compensaciones/categorias/pages/CategoriasReconocimientoPage.dom.test.tsx`: al pulsar «Activar» y recibir 422 se muestra «Baja primero la puntuación a {máximo} o menos.» en la alerta de error del listado y la fila sigue Inactiva; un 409 conserva su comportamiento

### Implementation for User Story 5

- [X] T027 [US5] Modificar `backend/app/Modules/Compensaciones/Application/UseCases/CambiarEstadoCategoria.php`: inyectar `ParametrosDelModulo`; tras el chequeo de versión y el retorno por «mismo estado», si `$estado === EstadoCategoria::Activo` y `$actual->puntuacion->valor > máximo` lanzar `DatosDeCategoriaInvalidos('puntuacion_base', "Baja primero la puntuación a {$maximo} o menos.")`; no tocar la desactivación; actualizar el docblock `@throws`. Confirmar que `CategoriaController::cambiarEstado` ya lo traduce a 422 por `responder()`
- [X] T028 [US5] En `frontend/src/modules/compensaciones/categorias/pages/CategoriasReconocimientoPage.tsx` (callback `onError` de `cambiarEstado`, ~línea 101): si `toApiError(e).status === 422` mostrar el mensaje del servidor (`fieldErrors.puntuacion_base[0]`) en la alerta de error existente; añadir el caso a `frontend/src/modules/compensaciones/categorias/text.ts` solo si hace falta un texto de respaldo
- [X] T029 [US5] Ejecutar la suite completa de categorías (`./vendor/bin/pest tests/Feature/Compensaciones tests/Unit/Compensaciones`) y comprobar que `ListarCategoriasTest`, `ObtenerCategoriaTest`, `CategoriasFacadeTest` y `MigracionIndicadoresTest` siguen pasando sin modificaciones (FR-008)

**Checkpoint**: invariante completo: ninguna categoría guardada ni activada supera el máximo.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T030 [P] Ampliar `frontend/e2e/categorias-reconocimiento.spec.ts` con el flujo principal: abrir el formulario y ver «Máximo: {n} puntos», rechazo de una puntuación mayor con el error en el campo, guardado válido, y «Activar» rechazado en una inactiva por encima del tope
- [ ] T031 (pendiente: ver nota al final) Ejecutar las puertas de calidad: `cd backend && ./vendor/bin/pint && php artisan test`, y `cd frontend && npm run lint && npx tsc --noEmit && npm test && npm run build`; corregir fallos
- [ ] T032 (pendiente: manual) Recorrer a mano los pasos 1–6 de `quickstart.md` (incluido el cambio de mes con `Carbon::setTestNow` o ajustando la fila programada) y marcar `checklists/requirements.md` si aplica

---

## Dependencies & Execution Order

- **Phase 1 → Phase 2**: la línea base antes de tocar código. **Phase 2 bloquea todo**: T002 (máximo) y T003–T004 (dominio) son requisito de cada historia.
- **US1 (Phase 3)**: depende de la Phase 2. Es el MVP: sin ella no hay regla.
- **US2 (Phase 4)**: la parte de backend (T015) es independiente de US1; el frontend depende de T015 para consumir `meta`. T020 (diseño) debe hacerse antes de T019.
- **US3 (Phase 5)**: depende de US1 (la regla ya existe); solo agrega pruebas y verificación del controlador.
- **US4 (Phase 6)**: depende de US1 (necesita poder cambiar la puntuación); no cambia producción.
- **US5 (Phase 7)**: depende de la Phase 2; es independiente de US1–US4 salvo T028, que comparte `text.ts` con US2 (secuenciar si se trabaja en paralelo).
- **Polish (Phase 8)**: al final.

Orden por historia: US1 → (US2 ‖ US5) → US3 → US4. Dentro de cada fase, las pruebas se escriben antes de la implementación y deben fallar primero.

## Parallel Opportunities

- Phase 2: T003 ‖ T002; T005 después de ambas.
- US1: T006 y T007 (mismo archivo: secuenciales entre sí) en paralelo con ninguna implementación; T008 ‖ T009 en archivos distintos.
- US2: T012 ‖ T013 ‖ T014; T016 ‖ T017 ‖ T020; luego T018 → T019.
- US3, US4 y US5 (T025 ‖ T026) tienen pruebas en archivos propios y pueden escribirse en paralelo.
- Ejemplo: tras la Phase 2, una persona hace US1 (backend) y otra hace T020 + T016 + T017 (diseño y base del frontend).

## Implementation Strategy

1. **MVP**: Phase 1 + Phase 2 + US1. Con eso crear y editar ya respetan el tope en la API.
2. **Incremento 2**: US2 (la pantalla muestra y valida el máximo) y US5 (cierra el hueco de la reactivación); ambas son independientes entre sí.
3. **Incremento 3**: US3 y US4 (pruebas de contrato y de no efectos colaterales) y Polish con el E2E.
4. Cada incremento termina con la suite de categorías y parámetros en verde antes de pasar al siguiente.

## Notes

- Total: 32 tareas (US1: 6, US2: 9, US3: 2, US4: 2, US5: 5, Setup 1, Foundational 4, Polish 3).
- La ampliación de `GET /parametros` con `meta` es aditiva: la pantalla de parámetros (`frontend/src/modules/compensaciones/parametros/api.ts`) ignora `meta`.
- `PuntuacionBase` sin segundo argumento sigue siendo permisiva hasta 1000 a propósito: reconstruir una categoría ya guardada por encima del tope no debe fallar; la regla se aplica solo al guardar o activar.
- Los archivos de prueba nuevos (`PuntajeAtadoValidacionServidorTest`, `PuntajeAtadoSinEfectosTest`, `PuntajeAtadoActivacionTest`) ya figuran en la lista de archivos de `plan.md`.
- FR-007 / SC-004 tienen cobertura parcial en esta spec (T023); la completan las pruebas de inc-02 e inc-03.
- Nota T031 (2026-11-03): lo de esta feature pasa (Pest de Compensaciones 194/194, Vitest de compensaciones, ESLint y TypeScript sin errores en `compensaciones`, Pint sin cambios pendientes en los archivos tocados, E2E de categorías y parámetros). Siguen fallando por causas previas y ajenas: 47 pruebas Pest de `Estructura/Unidades` y `GestionPersonal/Exportacion`, 18 archivos Vitest de `desarrollo` y `estructura/unidades`, 23 errores de `tsc -b` y errores de ESLint en `desarrollo/micro-learning` y `AppShell.tsx`, y Pint marca `tests/Feature/GestionPersonal/Politicas/PoliticasEsquemaTest.php`; por eso `npm run build` no termina.
