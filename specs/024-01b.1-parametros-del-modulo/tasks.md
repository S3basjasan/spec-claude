---

description: "Tareas de 01b.1 · Parámetros del módulo"
---

# Tasks: Parámetros del módulo (01b.1)

**Input**: Documentos de diseño en `/specs/024-01b.1-parametros-del-modulo/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/parametros-api.md, quickstart.md

**Tests**: Se incluyen: la constitución exige cobertura automatizada (Pest, Vitest y un E2E del flujo principal) y el plan de origen enumera las pruebas.

**Organization**: Tareas agrupadas por historia de usuario. La Phase 2 deja funcionando el esqueleto común (tabla, lectura del valor vigente, `GET` y `PUT` con rangos, pantalla con tarjetas); cada historia añade su regla y su parte visible.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia a la que pertenece (US1…US5)

## Path Conventions

- Backend: `backend/app/Modules/Compensaciones/`, pruebas en `backend/tests/Feature/Compensaciones/`
- Frontend: `frontend/src/modules/compensaciones/parametros/`, E2E en `frontend/e2e/`
- Diseño: `design/`
- Las demás specs del módulo (01b.2, 02.x, 03.x, 07.x) usan el servicio de Aplicación `ParametrosDelModulo` directamente, sin Facade.

---

## Phase 1: Setup

**Purpose**: Línea base y configuración compartida

- [ ] T001 Ejecutar la suite actual `cd backend && ./vendor/bin/pest tests/Feature/Compensaciones` y `cd frontend && npx vitest run src/modules/compensaciones`, y anotar que todo pasa (línea base)
- [X] T002 [P] Crear `backend/config/compensaciones.php` con `'zona_horaria' => 'America/Guayaquil'` (la reutiliza 02.2; no fijar la zona en el código)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Tabla, reglas de lectura/escritura base, `GET` y `PUT` genéricos y pantalla base. Ninguna historia puede empezar antes.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

### Backend

- [X] T003 Crear la migración `backend/database/migrations/2026_11_03_000002_create_parametros_modulo_table.php` con la tabla `parametros_modulo`: `id` bigint PK; `clave` string no nulo (en SQLite el enum se guarda como string; valores `puntos_por_colaborador`, `plazo_anulacion_dias`, `umbral_concentracion`, `plazo_max_desafio_dias`, `umbral_clima_unidad`, `sensibilidad_radar`); `valor` int no nulo; `vigente_desde` date no nula; `created_by` bigint no nulo **sin FK**; `created_at` timestamp; índice `(clave, vigente_desde)`; sin `updated_at` ni soft delete
- [X] T004 [P] Crear el enum/objeto de valor `backend/app/Modules/Compensaciones/Domain/ValueObjects/ClaveDeParametro.php` con rango y defecto de cada clave: puntos_por_colaborador 1 a 1000 (defecto: mayor entre 100 y la puntuación de la categoría activa más alta); plazo_anulacion_dias 1 a 30 (7); umbral_concentracion 1 a 100 (50); plazo_max_desafio_dias 1 a 90 (30); umbral_clima_unidad 1 a 100 (33); sensibilidad_radar 1, 2 o 3 (2); y si su vigencia es diferida (solo puntos)
- [X] T005 [P] Crear la entidad `backend/app/Modules/Compensaciones/Domain/Entities/CambioDeParametro.php` (`id`, `clave`, `valor`, `vigenteDesde`, `creadoPor`, `creadoEn`) y las excepciones `Domain/Exceptions/ValorDeParametroInvalido.php`, `ParametrosModificadosPorOtro.php` y `PuntosPorDebajoDeCategoria.php` (con nombre y puntos de la categoría)
- [X] T006 Crear el contrato `backend/app/Modules/Compensaciones/Domain/Repositories/ParametroRepository.php`: `ultimaPorClave`, `filaVigente(clave, fecha)` (la de `vigente_desde` más reciente que no supere la fecha; con empate, la de mayor `id`), `filaProgramada(clave, hoy)`, `anterior(fila)`, `insertar(...)` y `enTransaccion`. Sin métodos de actualización ni borrado
- [X] T007 Crear `backend/app/Modules/Compensaciones/Infrastructure/Persistence/Eloquent/Models/ParametroModuloModel.php` (sin `updated_at`; sin `fillable` de edición de filas existentes) y `Repositories/EloquentParametroRepository.php` que implementa T006
- [X] T008 [P] Crear el puerto `backend/app/Modules/Compensaciones/Application/Ports/NombresDeUsuarioPort.php` y el adaptador `Infrastructure/Adapters/NombresDeUsuarioDesdeShared.php` sobre `SharedFacade::getNombresDeUsuarios` (patrón del módulo Desarrollo; no leer la tabla `users`)
- [X] T009 Crear el servicio `backend/app/Modules/Compensaciones/Application/Services/ParametrosDelModulo.php` con `valorVigente(clave, ?fecha)`, `valorProgramado(clave)`, `hoy()` y `primeroDelMesSiguiente()` calculados con `config('compensaciones.zona_horaria')`; sin filas devuelve el defecto (para puntos, el mayor entre 100 y la puntuación de la categoría activa más alta leída con el `CategoriaRepository` del módulo — CA-02)
- [X] T010 Añadir a `backend/app/Modules/Compensaciones/Application/Services/PoliticaDeAcceso.php` la constante `PARAMETROS_GESTIONAR = 'parametros.gestionar'` resuelta con `tokenCan('admin')` (como `categorias.gestionar`); un permiso desconocido sigue denegándose
- [X] T011 Registrar en `backend/app/Modules/Compensaciones/Infrastructure/Providers/CompensacionesServiceProvider.php` los bindings `ParametroRepository` y `NombresDeUsuarioPort`
- [X] T012 Crear los casos de uso `Application/UseCases/ConsultarParametros.php` (las seis claves con valor vigente, rango, defecto y `version` = id de la última fila de la clave o 0) y `GuardarParametros.php` (esqueleto: autoriza con `PoliticaDeAcceso`, valida entero dentro del rango de cada clave, todo en una transacción, inserta una fila por clave cuyo valor cambió con `vigente_desde` = hoy y `created_by` = usuario; no inserta fila cuando el valor enviado es igual al último guardado de esa clave, programado si lo hay, vigente si no — FR-012; acepta `version` por clave y la valida en T039) más los DTOs `Application/DTOs/ParametroDTO.php` y `CambioSolicitadoDTO.php`
- [X] T013 Crear `Presentation/Requests/GuardarParametrosRequest.php` (`cambios` lista de `{clave, valor, version}`; valor entero estricto, rechaza decimales, texto y vacío; errores en `cambios.N.valor`), `Presentation/Resources/ParametrosResource.php`, `Presentation/Controllers/ParametrosController.php` (delgado) y las rutas `GET` y `PUT /parametros` en `backend/app/Modules/Compensaciones/Routes/api.php` dentro del grupo `['auth:sanctum', 'admin']`

### Frontend

- [X] T014 [P] Versionar `design/ParametrosModulo.dc.html` (ya existe sin versionar) y, si `design/DESIGN.md` no documenta el patrón de tarjetas de parámetros con un solo «Guardar», agregar una sección breve antes de dar por buena la interfaz
- [X] T015 [P] Crear `frontend/src/modules/compensaciones/parametros/text.ts` con todo el texto visible en español (títulos, explicaciones, rangos, mensajes 409 y de categoría, estados, «Valor inicial», «Último cambio: …», «de valor por defecto a {nuevo}») y `validation.ts` con el esquema Zod (entero dentro del rango de cada clave)
- [X] T016 [P] Crear `frontend/src/modules/compensaciones/parametros/api.ts` (tipos de la respuesta del contrato y llamadas `GET`/`PUT` por el cliente HTTP compartido) y `hooks/useParametros.ts` y `hooks/useGuardarParametros.ts` (TanStack Query; la mutación no se reintenta; invalida la consulta al guardar)
- [X] T017 Crear `components/TarjetaParametro.tsx` y `pages/ParametrosPage.tsx` en `frontend/src/modules/compensaciones/parametros/`: una tarjeta por parámetro numérico (título, explicación, campo numérico, rango, cuándo aplica), botones «Descartar» y «Guardar» para todos los cambios juntos (Guardar bloqueado sin cambios o con envío pendiente), estados cargando, error con «Reintentar» y éxito con confirmación, errores junto a cada campo conservando lo escrito; envía solo los campos modificados, cada uno como `{clave, valor, version}` con la `version` recibida en el `GET`
- [X] T018 Registrar la ruta `compensaciones/parametros` en `frontend/src/App.tsx` y crear `frontend/src/modules/compensaciones/parametros/nav.tsx` si hace falta para la navegación

**Checkpoint**: `GET` y `PUT` funcionan con rangos; la pantalla edita y guarda parámetros que aplican hoy.

---

## Phase 3: User Story 1 - Ajustar los puntos por colaborador (Priority: P1) 🎯 MVP

**Goal**: Cambiar los puntos por colaborador con vigencia diferida al día 1 del mes siguiente, piso por categoría y vista previa.

**Independent Test**: Cambiar los puntos, ver la vista previa y guardar; el valor vigente de hoy no cambia y el nuevo rige desde el día 1 del mes siguiente (quickstart pasos 4 y 5).

### Tests for User Story 1

- [X] T019 [P] [US1] Pruebas Pest en `backend/tests/Feature/Compensaciones/ParametrosPuntosTest.php`: `GET` sin filas devuelve 100 y, con una categoría activa de 150 puntos, devuelve 150 (CA-02); `PUT` menor que la categoría activa más alta responde 422 con el nombre y los puntos; `valorVigente` de hoy devuelve el anterior y el del día 1 del mes siguiente el nuevo (`Carbon::setTestNow`, zona de `compensaciones.zona_horaria`); un segundo cambio el mismo mes reemplaza al programado; sin categorías activas no hay piso; valor 1 y 1000 se aceptan, 0 y 1001 no; con dos categorías empatadas en la puntuación más alta se nombra la de menor `id`; al pasar al día 1 del mes siguiente (`Carbon::setTestNow`) el valor programado pasa a ser el vigente sin intervención; si la categoría más alta se desactiva después de fijado el parámetro, el valor guardado no cambia y la regla se aplica en el siguiente guardado
- [X] T020 [P] [US1] Pruebas Vitest en `frontend/src/modules/compensaciones/parametros/pages/ParametrosPage.dom.test.tsx`: la vista previa muestra actual → nuevo, la fecha de vigencia y el ejemplo «Un equipo de 10 personas tendría X puntos al mes»; el 422 de categoría aparece bajo el campo con nombre y puntos

### Implementation for User Story 1

- [X] T021 [US1] En `GuardarParametros.php` aplicar para `puntos_por_colaborador`: `vigente_desde` = día 1 del mes siguiente, y rechazar con `PuntosPorDebajoDeCategoria` si el valor es menor que la puntuación de la categoría activa más alta (mensaje «No puede ser menor que la puntuación de la categoría «{nombre}» ({puntos} puntos).»; empate: la de menor `id`)
- [X] T022 [US1] En `ConsultarParametros.php` y `ParametrosResource.php` devolver `valor_programado` `{valor, vigente_desde}` para puntos por colaborador (o `null`) además del valor vigente
- [X] T023 [P] [US1] Crear `frontend/src/modules/compensaciones/parametros/components/VistaPreviaPuntos.tsx`: valor actual → nuevo, fecha desde la que rige (día 1 del mes siguiente), ejemplo fijo «Un equipo de 10 personas tendría X puntos al mes» y valor programado si existe; sin cálculo por líder
- [X] T024 [US1] Integrar `VistaPreviaPuntos` y el mapeo del 422 de categoría al campo de puntos en `ParametrosPage.tsx` / `TarjetaParametro.tsx`

**Checkpoint**: US1 funcional y probada de forma independiente.

---

## Phase 4: User Story 2 - Plazo de anulación y duración máxima del desafío (Priority: P1)

**Goal**: Definir ambos plazos con rango y aplicación inmediata.

**Independent Test**: Cambiar ambos valores y comprobar que rigen hoy (quickstart paso 2 y 3).

### Tests for User Story 2

- [X] T025 [P] [US2] Pruebas Pest en `backend/tests/Feature/Compensaciones/ParametrosPlazosTest.php`: plazo de anulación 1 y 30 se aceptan, 0, 31, 2.5 y texto dan 422 sin guardar nada; el cambio aplica hoy (`vigente_desde` = hoy); plazo máximo del desafío 1 a 90; un valor inválido junto a uno válido en el mismo PUT no guarda ninguno
- [X] T026 [P] [US2] Prueba Vitest en `ParametrosPage.dom.test.tsx`: valores fuera de rango o no enteros muestran el error junto al campo y no envían el PUT

### Implementation for User Story 2

- [X] T027 [P] [US2] Añadir en `text.ts` y mostrar en `TarjetaParametro.tsx` la explicación, el rango y el «cuándo aplica» de ambos plazos; el del plazo máximo del desafío aclara que solo afecta a los desafíos que se asignen después. `GuardarParametros.php` (T012) ya inserta `vigente_desde` = hoy para estas dos claves, lo que prueba T025

**Checkpoint**: US1 y US2 funcionan de forma independiente.

---

## Phase 5: User Story 3 - Umbrales y sensibilidad de alertas (Priority: P2)

**Goal**: Umbral de concentración, umbral de clima y sensibilidad del radar en tres opciones sin números.

**Independent Test**: Cambiar umbrales y elegir sensibilidad; se guardan y la sensibilidad rige desde el siguiente cálculo (quickstart paso 6).

### Tests for User Story 3

- [X] T028 [P] [US3] Pruebas Pest en `backend/tests/Feature/Compensaciones/ParametrosAlertasTest.php`: umbrales de concentración y de clima 1 a 100 (0 y 101 → 422); `sensibilidad_radar` acepta 1, 2, 3 y rechaza 0 y 4; todos aplican hoy
- [X] T029 [P] [US3] Prueba Vitest en `ParametrosPage.dom.test.tsx`: la sensibilidad muestra exactamente tres opciones de radio (Baja, Normal recomendada, Alta) con su explicación, sin campo numérico

### Implementation for User Story 3

- [X] T030 [P] [US3] Crear `components/TarjetaSensibilidad.tsx` en `frontend/src/modules/compensaciones/parametros/` con tres radios y los textos: Baja «Solo alerta en casos claros. Menos avisos.», Normal (recomendada) «Equilibrio entre detectar a tiempo y no saturar al líder.», Alta «Detecta antes las señales leves. Más avisos.», y la nota «Aplica desde el siguiente cálculo semanal. Los líderes verán que la regla cambió en esa medición.»; los textos viven en `text.ts`
- [X] T031 [US3] Integrar `TarjetaSensibilidad` y las tarjetas de umbrales en `ParametrosPage.tsx`, mapeando 1/2/3 a Baja/Normal/Alta; el cliente envía el entero y nunca un umbral

**Checkpoint**: Las tres historias de valores funcionan.

---

## Phase 6: User Story 4 - Trazabilidad de cambios (Priority: P2)

**Goal**: Mostrar el último cambio de cada parámetro y no perder nunca uno anterior.

**Independent Test**: Cambiar un parámetro dos veces y verificar autor, fecha, valor anterior y nuevo, y que ambas filas siguen presentes (quickstart paso 2).

### Tests for User Story 4

- [X] T032 [P] [US4] Pruebas Pest en `backend/tests/Feature/Compensaciones/ParametrosAuditoriaTest.php`: dos cambios sucesivos dejan dos filas y la primera no se modifica; `ultimo_cambio` trae nombre del autor, fecha, `valor_anterior` y `valor_nuevo`; en el primer cambio de una clave `valor_anterior` es `null`; sin cambios `ultimo_cambio` es `null`; guardar sin cambiar nada no inserta fila; el repositorio no expone actualizar ni borrar
- [X] T033 [P] [US4] Prueba Vitest en `ParametrosPage.dom.test.tsx`: se muestra «Último cambio: {nombre}, {fecha} (de {anterior} a {nuevo})», «de valor por defecto a {nuevo}» en el primer cambio y «Valor inicial» si nunca cambió

### Implementation for User Story 4

- [X] T034 [US4] En `ConsultarParametros.php`/`ParametrosResource.php` construir `ultimo_cambio` (nombre vía `NombresDeUsuarioPort`, `created_at`, valor anterior = `valor` de la fila previa de la clave o `null` si es la primera, valor nuevo) sin columna `valor_anterior` (la regla de no insertar fila sin cambio real ya vive en T012)
- [X] T035 [US4] Mostrar el último cambio bajo cada tarjeta en `TarjetaParametro.tsx` con los textos de `text.ts`

**Checkpoint**: Trazabilidad completa.

---

## Phase 7: User Story 5 - Acceso restringido y edición concurrente (Priority: P1)

**Goal**: Solo el Gestor ve y cambia los parámetros; dos ediciones simultáneas no se sobrescriben.

**Independent Test**: Usuario sin `admin` no ve la pestaña y la API responde 403; dos ediciones simultáneas dan 409 (quickstart pasos 7 y sección 3).

### Tests for User Story 5

- [X] T036 [P] [US5] Pruebas Pest en `backend/tests/Feature/Compensaciones/ParametrosAccesoTest.php`: sin sesión 401, sin `admin` 403 en `GET` y `PUT`, con `admin` 200; y `PoliticaDeAccesoTest.php` cubre `parametros.gestionar` verdadero solo con `admin`
- [X] T037 [P] [US5] Pruebas Pest en `backend/tests/Feature/Compensaciones/ParametrosConcurrenciaTest.php`: `version` desactualizada responde 409 con el mensaje del contrato y no guarda nada ni siquiera de las otras claves; con `version = 0` una segunda primera edición recibe 409
- [X] T038 [P] [US5] Pruebas Vitest en `ParametrosPage.dom.test.tsx` y un test de la navegación: el 409 muestra «Otra persona cambió un parámetro mientras editabas. Recarga para ver los valores actuales.» con acción «Recargar» y conserva lo escrito hasta recargar; la pestaña «Parámetros» no se renderiza para un usuario con rol distinto de `admin`

### Implementation for User Story 5

- [X] T039 [US5] En `GuardarParametros.php` comparar la `version` enviada de cada clave con el id de su última fila (0 si no hay) dentro de la transacción y de un `Cache::lock` de aplicación; si difiere en cualquiera lanzar `ParametrosModificadosPorOtro` (409) sin insertar nada
- [X] T040 [US5] Mapear `ParametrosModificadosPorOtro` a 409 y `ValorDeParametroInvalido`/`PuntosPorDebajoDeCategoria` a 422 en `ParametrosController.php` con mensajes seguros
- [X] T041 [P] [US5] Crear `frontend/src/modules/compensaciones/PestanasCompensaciones.tsx` (dentro del módulo, sin modificar `frontend/src/shared/ui/Tabs.tsx`): usa `Tabs` con las pestañas «Categorías» y «Parámetros», `active` según la ruta actual, `onChange` que llama a `navigate()` a `/compensaciones/categorias` o `/compensaciones/parametros`, y el contenido de la página como `children`; solo se renderiza con pestañas si `usuario?.role === 'admin'` (misma regla que `CategoriasReconocimientoPage.tsx`) y, si no, devuelve solo los `children`. Usarla en `CategoriasReconocimientoPage.tsx` y `ParametrosPage.tsx` y volver a ejecutar las pruebas de categorías (`npx vitest run src/modules/compensaciones/categorias`)
- [X] T042 [US5] Manejar el 409 en `useGuardarParametros.ts` y `ParametrosPage.tsx`: aviso con «Recargar» que vuelve a pedir el `GET`, sin reintento automático de la escritura

**Checkpoint**: Todas las historias funcionales.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T043 [P] Crear el E2E `frontend/e2e/parametros-modulo.spec.ts`: el Gestor cambia el plazo de anulación y ve el último cambio bajo la tarjeta
- [X] T044 [P] Prueba Vitest de `frontend/src/modules/compensaciones/parametros/validation.test.ts` con los rangos de las seis claves y los límites exactos
- [ ] T045 Revisar accesibilidad y responsive de `ParametrosPage.tsx` entre 320 y 1440 px, tema claro/oscuro y foco visible
- [ ] T046 Ejecutar las puertas de calidad: `cd backend && ./vendor/bin/pint --test && ./vendor/bin/pest`; `cd frontend && npm run lint && npx tsc --noEmit && npm test && npm run build`
- [ ] T047 Recorrer `quickstart.md` de punta a punta y registrar el resultado

---

## Dependencies & Execution Order

- **Phase 1** → **Phase 2** (bloquea todo) → historias.
- **US1** y **US2** modifican `GuardarParametros.php` y `ParametrosPage.tsx`: no ejecutarlas en paralelo entre sí en esos archivos. **US3**, **US4** y **US5** también tocan esos archivos; ordenar en prioridad (US1, US2, US5, luego US3, US4) o por turnos.
- Las pruebas de cada historia se escriben antes y deben fallar antes de implementar.
- **Phase 8** al final.

### Parallel Opportunities

- T002, T004, T005, T008, T015, T016 pueden ir en paralelo dentro de la Phase 2 (archivos distintos).
- Pruebas de distintas historias (T019/T020, T025/T026, T028/T029, T032/T033, T036–T038) tocan archivos distintos y pueden escribirse en paralelo.
- T043 (E2E) y T044 (validación) en paralelo; T014 (diseño) ya se hizo en la fase 2.

## Implementation Strategy

- **MVP**: Phase 1 + Phase 2 + US1 (puntos por colaborador), con US5 (acceso) antes de exponerlo a usuarios reales.
- **Incremental**: US2 → US5 → US3 → US4 → Polish; validar cada checkpoint con su Independent Test.
- Fuera de alcance: validar la puntuación de categorías contra el parámetro (01b.2), presupuesto por líder (01b.4).
