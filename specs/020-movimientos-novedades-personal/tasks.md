# Tasks: Registro de movimientos y novedades de personal

**Input**: documentos de diseño en `specs/020-movimientos-novedades-personal/` (plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md)

**Prerequisites**: feature 016 instalada (PoliticasFacade) y GP-02 (vínculos y asignaciones).

**Tests**: obligatorios por la constitución (Pest, Vitest y un E2E). Se escriben junto a cada historia.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: se puede ejecutar en paralelo (archivos distintos, sin dependencia pendiente)
- **[Story]**: historia de usuario a la que pertenece (US1…US5)
- Rutas relativas a la raíz del repositorio; `GP` = `backend/app/Modules/GestionPersonal`

---

## Phase 1: Setup (esquema)

- [X] T001 Crear `backend/database/migrations/2026_10_24_000001_create_actuaciones_personal_table.php` con la tabla común, CHECK de `tipo`/`categoria`/`estado`, índices `(tenant_id, operacion_id)` único, `(tenant_id, estado, tipo, presentada_en)`, `(tenant_id, colaborador_id, presentada_en)` y el índice único parcial «un movimiento aprobado pendiente por colaborador», más triggers de inmutabilidad, sin DELETE y motivo obligatorio en movimientos (data-model.md)
- [X] T002 Crear `backend/database/migrations/2026_10_24_000002_create_movimientos_novedades_detalle_tables.php` con `movimientos_personal`, `novedades_personal` (CHECK `fecha_fin >= fecha_inicio`) y `actuacion_documentos` (PK compuesta, FK RESTRICT)
- [X] T003 Crear `backend/database/migrations/2026_10_24_000003_create_actuacion_transiciones_table.php` append-only con índice único parcial de `operacion_id` y triggers que abortan UPDATE y DELETE
- [X] T004 Crear `backend/database/migrations/2026_10_24_000004_add_actuacion_id_to_asignaciones_laborales.php` (columna nullable con FK RESTRICT e índice único parcial `asignaciones_una_por_actuacion`; `down()` reversible)
- [X] T005 [P] Prueba de esquema `backend/tests/Feature/GestionPersonal/Movimientos/EsquemaDeActuacionesTest.php`: inmutabilidad de columnas selladas, transiciones append-only, índice de movimiento único pendiente, FK RESTRICT hacia `politica_versiones` y que las filas previas de `asignaciones_laborales` conservan `actuacion_id` nulo

---

## Phase 2: Foundational (bloquea todas las historias)

- [X] T006 [P] Crear value objects `GP/Domain/ValueObjects/TipoDeActuacion.php` (tipo ↔ `TipoDePolitica`, categoría, `esMovimiento`, `aceptaAnticipacion`), `EstadoDeActuacion.php` y `ResultadoDeEvaluacion.php`
- [X] T007 [P] Crear `GP/Domain/Services/MaquinaDeEstados.php` con las transiciones válidas de data-model.md (novedad, ausencia, movimiento) y estados terminales
- [X] T008 [P] Crear `GP/Domain/Services/ReglasDeActuacion.php`: validación de forma y de fechas por tipo (`fecha_fin >= fecha_inicio`, rango `[1900-01-01, hoy + 10 años]`), anticipación en días calendario con `hoy` de la zona del tenant (cero admite hoy, nunca pasado), ausencia no futura, justificación/documento según la política conservada
- [X] T009 [P] Crear `GP/Domain/Services/DetectorDeCruces.php` (periodos inclusivos, excluye la propia solicitud y las ausencias) y `GP/Domain/Services/VerificadorDeEvaluacion.php` (identidad, puesto destino, requisitos no vacíos, momento, resultado, vigencia; sin umbrales)
- [X] T010 [P] Crear excepciones de dominio en `GP/Domain/Exceptions/`: `SolicitudNoEncontrada`, `SolicitudModificada`, `EstadoNoResoluble`, `AnticipacionInsuficiente`, `FechaPasada`, `AusenciaFutura`, `JustificacionRequerida`, `DocumentoRequerido`, `SinVinculoVigente`, `CruceSinReconocer`, `EvaluacionNoVerificable`, `MovimientoIncompatible`, `AplicacionNoProcede`, `IncidenciaDeAplicacion`
- [X] T011 [P] Crear `GP/Domain/Entities/Actuacion.php` (inmutable: identidad, tipo, estado, versión, solicitante, política conservada, periodo/fecha efectiva, detalle de movimiento) y DTOs en `GP/Application/DTO/` (`ActuacionDTO`, `ResultadoDePresentacion`)
- [X] T012 Crear `GP/Domain/Repositories/ActuacionRepositoryInterface.php` conforme a contracts/puertos-y-procesos-internos.md
- [X] T013 Implementar `GP/Infrastructure/Persistence/Eloquent/Repositories/EloquentActuacionRepository.php` (presentar atómico, `porId`/`porOperacion`, listado, resumen, cruces, transición con versión optimista y operación idempotente, movimientos por aplicar, incidencia, aplicación con cierre y apertura de asignaciones, traducción de unicidad y triggers)
- [X] T014 [P] Crear `GP/Application/Ports/EvaluacionDelDestinoPort.php` y el adaptador `GP/Infrastructure/Adapters/EvaluacionDelDestinoNoInstalada.php`
- [X] T015 [P] Extender `ContextoDeTenantPort::deTenant(int)` y `SharedContextoDeTenant` (revisar los dobles de prueba que implementan el puerto)
- [X] T016 Crear `GP/Application/Services/ResolvedorDeSolicitante.php` (ficha propia del Colaborador por asociación, ficha afectada del Administrador, `en_nombre_de_colaborador`) y `GP/Application/Services/ValidadorDeRespaldos.php` (existencia, ficha, estado Disponible y accesibilidad para quien presenta)
- [X] T017 Registrar enlaces y el comando en `GP/Infrastructure/Providers/GestionPersonalServiceProvider.php`; agregar `Ascenso` y `Traslado` a `GP/Domain/ValueObjects/TipoDeHecho.php`
- [X] T018 [P] Helpers de prueba en `backend/tests/GestionPersonalHelpers.php`: `evaluadorDeDestinoFalso`, `politicaVigente`, `presentarNovedadApi`, `proponerMovimientoApi`, `colaboradorConCuenta`, `fichaConVinculo`
- [X] T019 [P] Pruebas unitarias en `backend/tests/Unit/GestionPersonal/Movimientos/`: `MaquinaDeEstadosTest`, `ReglasDeActuacionTest` (cero, pasado, rangos, ausencia), `DetectorDeCrucesTest`, `VerificadorDeEvaluacionTest`
- [X] T020 [P] Frontend base: `frontend/src/modules/gestionPersonal/movimientos/api.ts` (tipos y llamadas), `text.ts`, `validation.ts` (Zod), `nav.tsx`, `index.ts` y `frontend/src/test/movimientosFixtures.ts`

**Checkpoint**: esquema, dominio, repositorio y puertos listos; ninguna historia depende aún de HTTP.

---

## Phase 3: User Story 1 — Presentar permiso, licencia o vacaciones (P1) 🎯 MVP

**Goal**: presentar una novedad propia o en nombre del colaborador con la versión de política fijada, sin modificar ficha ni asignaciones.

**Independent Test**: política vigente → 201 con versión conservada; sin configurar / no admite / falla → no se confirma y se distingue la causa; anticipación en días calendario; doble envío no duplica.

- [X] T021 [P] [US1] Pruebas Feature en `backend/tests/Feature/GestionPersonal/Movimientos/PresentarNovedadTest.php` (presentación válida, versión y condiciones conservadas, causas de política, anticipación 0/hoy/ayer, justificación y documento, en nombre del colaborador, borrador sin versión, sin vínculo vigente)
- [X] T022 [P] [US1] Pruebas `IdempotenciaYConcurrenciaDePresentacionTest.php` (misma clave idéntica → 200 sin duplicado; otro contenido → 409; dos presentaciones simultáneas)
- [X] T023 [US1] Caso de uso `GP/Application/UseCases/PresentarNovedad.php` (valida, bloquea la ficha, `seleccionarParaPresentar` en la misma transacción, fija versión y copia, registra transición `Presentada`)
- [X] T024 [US1] `GP/Presentation/Requests/PresentarNovedadRequest.php`, `GP/Presentation/Resources/ActuacionResource.php` y `GP/Presentation/Controllers/ActuacionController.php` (acción `novedad`, traducción de excepciones a códigos de contracts/api-movimientos.md)
- [X] T025 [US1] Rutas `POST /api/movimientos-personal/novedades` y `GET /api/movimientos-personal/documentos` en `GP/Routes/api.php` y caso de uso `ListarDocumentosReferenciables`
- [X] T026 [US1] Casos de uso y controlador del Colaborador: `POST /api/mis-solicitudes/novedades`, `GET /api/mis-solicitudes/documentos` (`MiSolicitudController`) con la ficha resuelta por asociación
- [X] T027 [P] [US1] Frontend: `components/FormularioDeNovedad.tsx`, `components/SelectorDeRespaldos.tsx`, `hooks/useAccionesDeActuacion.ts` y páginas `NuevaSolicitudPage.tsx` (Colaborador) y `RegistrarNovedadPage.tsx` (Administrador) con `CondicionesAplicables`, advertencia de versión fija y mensajes por causa de política
- [X] T028 [P] [US1] Pruebas Vitest del formulario (`FormularioDeNovedad.dom.test.tsx`, `NuevaSolicitudPage.dom.test.tsx`): causas de política, conservación de valores en 422/409, bloqueo de doble envío

**Checkpoint**: US1 operativa y verificable por sí sola.

---

## Phase 4: User Story 2 — Revisar, resolver y consultar novedades (P1)

**Goal**: listado y detalle del Administrador, resolución con constancia, cruces reconocidos, retiro y consulta acotada del Colaborador.

**Independent Test**: dos solicitudes de colaboradores distintos, una aprobada y otra rechazada; cada colaborador ve solo la suya; usuario de otro tenant no obtiene nada.

- [X] T029 [P] [US2] Pruebas `ListadoYResumenTest.php` (filtros tipo/estado/grupo/colaborador/periodo, paginación, resumen, aislamiento por tenant)
- [X] T030 [P] [US2] Pruebas `ResolverNovedadTest.php` (revisión, aprobación, rechazo con razón, versión conservada tras nueva política, `CRUCE_SIN_RECONOCER`, reconocimiento con autor y momento, no editable, fallo sin cambios parciales)
- [X] T031 [P] [US2] Pruebas `RetiroDeNovedadTest.php` y `ConcurrenciaDeResolucionTest.php` (retiro por solicitante, retiro de resuelta rechazado, retiro vs. resolución simultáneos, dos resoluciones simultáneas)
- [X] T032 [P] [US2] Pruebas `PermisosYAislamientoDeMovimientosTest.php` (404 indistinguible entre ajeno, otro tenant e inexistente; Colaborador denegado en rutas de Administrador; Colaborador solo ve lo propio; forma comunicable sin campos restringidos)
- [X] T033 [US2] Casos de uso `ListarActuaciones`, `ResumirActuaciones`, `ObtenerActuacion` y `GP/Application/Services/ArmadorDeDetalleDeActuacion.php` (comprobaciones, cruces, historial)
- [X] T034 [US2] Casos de uso `PasarARevision`, `AprobarActuacion` (novedades; revalida estado, versión, vínculo, cruces), `RechazarActuacion` y `RetirarNovedad` con idempotencia por clave y transición registrada
- [X] T035 [US2] Casos de uso del Colaborador `ListarMisSolicitudes` y `ObtenerMiSolicitud` y `GP/Presentation/Resources/MiSolicitudResource.php` (sin campos restringidos; incidencia mostrada como aprobada pendiente)
- [X] T036 [US2] Peticiones, controlador y rutas: `GET /`, `GET /resumen`, `GET /{id}`, `POST /{id}/revision|aprobacion|rechazo|retiro` y `GET /api/mis-solicitudes`, `GET /{id}`, `POST /{id}/retiro`
- [X] T037 [P] [US2] Frontend administrador: `pages/MovimientosPage.tsx` (resumen, filtros en URL, tabla, estados loading/empty/error), `components/TablaDeActuaciones.tsx`, `FiltrosDeActuaciones.tsx`, `EstadoDeActuacion.tsx`, `hooks/useActuaciones.ts`
- [X] T038 [P] [US2] Frontend detalle y resolución: `pages/ActuacionDetallePage.tsx`, `components/ResumenDeComprobaciones.tsx`, `AvisoDeCruces.tsx`, `DialogoDeResolucion.tsx` (confirmación accesible, razón obligatoria, conflicto 409 con «Actualizar»)
- [X] T039 [P] [US2] Frontend Colaborador: `pages/MisSolicitudesPage.tsx` y `hooks/useMisSolicitudes.ts` (vacío, error con reintento, sin ficha asociada, retiro con confirmación)
- [X] T040 [P] [US2] Pruebas Vitest: `MovimientosPage.dom.test.tsx`, `ActuacionDetallePage.dom.test.tsx`, `MisSolicitudesPage.dom.test.tsx`

---

## Phase 5: User Story 3 — Registrar y revisar ausencias (P2)

**Goal**: ausencia informada como hecho, revisable o justificable, sin convertirse en permiso.

**Independent Test**: ausencia pasada → `Informada`; futura rechazada; revisión/justificación; Colaborador no registra y ve solo las propias ya revisadas.

- [X] T041 [P] [US3] Pruebas `AusenciasTest.php` (informar, fecha futura, sin anticipación, nunca `Aprobada`, revisar y justificar, Colaborador denegado, visibilidad de las propias solo tras revisión, política sin configurar)
- [X] T042 [US3] Casos de uso `InformarAusencia` y `RevisarAusencia`; petición, controlador y rutas `POST /ausencias` y `POST /{id}/revision-de-ausencia`
- [X] T043 [P] [US3] Frontend: modo ausencia en `RegistrarNovedadPage.tsx` (aviso «se informa un hecho ocurrido»), acción de revisión en `ActuacionDetallePage.tsx` y pruebas Vitest

---

## Phase 6: User Story 4 — Proponer un ascenso o traslado y reunir evidencia (P2)

**Goal**: proponer sin tocar asignaciones; mostrar condiciones y evaluación como tres estados; impedir aprobar sin evaluación verificable.

**Independent Test**: propuesta trazable con asignación intacta; aprobar sin evaluación → `EVALUACION_NO_VERIFICABLE`; con evaluador válido de prueba se puede decidir; el Colaborador no ve propuestas.

- [X] T044 [P] [US4] Pruebas `ProponerMovimientoTest.php` (propuesta válida, asignación sin cambio, destino inexistente/suprimido/otro tenant/igual al actual, anticipación sobre la fecha efectiva, documento adicional, Colaborador denegado, sin vínculo vigente)
- [X] T045 [P] [US4] Pruebas `EvaluacionDelDestinoTest.php` (no instalada, no verificable por identidad o destino distinto o vencida, válida sin umbral, afirmación manual rechazada, rechazar y revisar siguen disponibles, nueva consulta tras instalar el contrato) y `VisibilidadDelColaboradorEnMovimientosTest.php`
- [X] T046 [US4] Casos de uso `ProponerMovimiento` y `ObtenerContextoDeMovimiento`; extender `AprobarActuacion` con verificación de evaluación y MOVIMIENTO_INCOMPATIBLE; petición, controlador y rutas `POST /movimientos`, `GET /contexto-de-movimiento`
- [X] T047 [P] [US4] Frontend: `pages/ProponerMovimientoPage.tsx` (situación actual, selector de destino con búsqueda, fecha efectiva, justificación) y sección «Comprobaciones» con control de aprobar ausente mientras `puede_aprobar` sea falso, con explicación de la causa
- [X] T048 [P] [US4] Pruebas Vitest de `ProponerMovimientoPage` y del detalle de movimiento (sin botón aprobar, tres estados separados)

---

## Phase 7: User Story 5 — Aprobar, programar y aplicar un movimiento (P2)

**Goal**: aprobación programada, aplicación atómica automática o manual, incidencias sin cambio parcial, historial laboral.

**Independent Test**: aprobar con fecha futura → `Programada` sin cambio; llegada la fecha el comando aplica una sola vez; destino ocupado → incidencia sin asignación nueva y reintento manual idempotente.

- [X] T049 [P] [US5] Pruebas `AplicarMovimientoTest.php` (aprobación sin cambio, estados Aprobada/Programada, aplicación cierra y abre sin superposición ni vacío, no antes de la fecha, solo lo aprobado, no editable ni eliminable, hecho en trayectoria con referencia a actuación y política)
- [X] T050 [P] [US5] Pruebas `IncidenciasDeAplicacionTest.php` (destino ocupado, no asignable, vínculo terminado, origen cambiado, Estructura no verificable; sin asignación parcial; no se comunica como aplicado; reintento aplica una sola vez; reintento que sigue fallando conserva la incidencia)
- [X] T051 [P] [US5] Pruebas `ProcesoProgramadoTest.php` (comando aplica por zona horaria del tenant, aislamiento entre tenants, idempotente ante doble ejecución y ante reintento manual simultáneo, retraso deja Programada, no reintenta incidencias) y `ConcurrenciaDeMovimientosTest.php` (dos movimientos del mismo colaborador, dos colaboradores al mismo destino)
- [X] T052 [US5] Caso de uso `AplicarMovimiento` (bloqueo de ficha, reverificaciones, cierre y apertura atómicos, incidencia en transacción aparte) y `GP/Application/UseCases/AplicarMovimientosProgramados.php`
- [X] T053 [US5] Comando `GP/Infrastructure/Console/AplicarMovimientosProgramadosCommand.php`, programación cada cinco minutos en `backend/routes/console.php` y ruta `POST /{id}/aplicacion`
- [X] T054 [US5] Historial Laboral: hechos `Ascenso` y `Traslado` en `GP/Application/UseCases/ConsultarTrayectoria.php` y `GP/Infrastructure/Persistence/Eloquent/Repositories/EloquentLectorLaboral.php` (sin alterar ingresos y reingresos); prueba en `TrayectoriaConMovimientosTest.php`
- [X] T055 [P] [US5] Frontend: acciones «Aplicar ahora / Reintentar» con confirmación, panel de aplicación e incidencia en `ActuacionDetallePage.tsx`, nunca un ascenso futuro como efectuado; pruebas Vitest
- [X] T056 [P] [US5] E2E `frontend/e2e/movimientos.spec.ts`: Colaborador presenta permiso y consulta; Administrador propone un ascenso y lo ve bloqueado por evaluación no verificable; Administrador informa una ausencia; resolución de una novedad

---

## Phase 8: Polish y transversales

- [X] T057 [P] Registrar rutas y navegación en `frontend/src/App.tsx` (Administrador: `/movimientos-personal…`; Colaborador: `/mis-solicitudes…`) y el grupo del menú lateral
- [X] T058 [P] Actualizar `design/DESIGN.md` con las filas de listado, detalle con comprobaciones y formulario de novedades
- [X] T059 Ejecutar `vendor/bin/pint --dirty --format agent`, las pruebas Pest de `Movimientos` y de `Ingresos`/`Politicas` (regresión GP-02 y 016), y `php artisan route:list --path=api/movimientos-personal`
- [X] T060 Ejecutar `npx tsc -b`, `npx eslint`, `npx vitest run` (módulo `movimientos` y las páginas de fichas y políticas) y `npx vite build`
- [X] T061 Ejecutar el E2E `e2e/movimientos.spec.ts` y los de políticas (pasan: 3 y 5). Los de fichas, categorías documentales, desarrollo, documentos, exportación e ingresos fallan por causas previas ajenas a esta feature (informe de cierre)
- [X] T062 Validar [quickstart.md](quickstart.md) mediante las pruebas Pest, Vitest y E2E y reportar compuertas abiertas (P-1…P-4) y fallos preexistentes ajenos

---

## Dependencies & Execution Order

- Setup (T001–T005) → Foundational (T006–T020) → historias.
- US1 y US2 comparten repositorio y controlador; US2 depende del registro creado en US1. US3 depende de T023/T034 solo por reutilizar infraestructura (puede ir después de US1). US4 depende de US2 (resolución); US5 depende de US4.
- Orden recomendado: US1 → US2 → US3 → US4 → US5 → Polish.

## Parallel Opportunities

- Dentro de Foundational: T006–T011, T014, T015, T018–T020 en paralelo.
- Dentro de cada historia: pruebas [P] y frontend [P] en paralelo con el backend de la misma historia (archivos distintos).

## Implementation Strategy

- **MVP**: US1 (presentar novedades) + US2 (resolverlas y consultarlas).
- **Incremento 2**: US3 (ausencias).
- **Incremento 3**: US4 y US5 (movimientos). La aprobación permanece impedida por el contrato de evaluación ausente (FR-022b); las pruebas la habilitan con un evaluador falso.
- **Compuertas abiertas** (no son tareas): P-1 evaluación, P-2 reglas de superposición, P-3 saldos de vacaciones, P-4 corrección de movimientos aplicados.


## Reformulación de promociones internas · 2026-10-28 (pendiente)

T001–T062 conservan sus estados históricos. Esta revisión no ejecuta las nuevas tareas ni habilita fuentes pendientes.

- [ ] T063 Confirmar con Desarrollo la titularidad y procedencia de niveles evaluados, integrar la adecuación existente desde develop y revisar sus contratos reales sin mezclar trabajo local de 005 u offboarding; registrar dependencias y capacidades no disponibles.
- [ ] T064 Acordar y ampliar el contrato público de evidencia de Desarrollo con identidad, requisitos/versiones, método/escalas, vigencia, dimensiones, cobertura y no aplicabilidad fundamentada; añadir pruebas de contrato en el módulo propietario.
- [ ] T065 Acordar con Estructura el contrato atómico de ocupación y auditoría, identidad de colaborador/cuenta opcional, restricciones de concurrencia y compatibilidad con ingresos y offboarding. Definir migraciones compatibles si corresponden, sin consultas o escrituras a tablas ajenas.
- [ ] T066 Actualizar design/DESIGN.md y la referencia visual del detalle de movimientos antes del frontend: diagnóstico por dimensión, pendientes, origen/fecha, evidencia de aprobación frente a actual, revalidación y permisos propios.
- [ ] T067 Añadir pruebas de dominio y Feature de FR-020 y FR-045–FR-050: referencia verificable, tres dimensiones, cobertura incompleta, perfil distinto, vigencia, no aplicabilidad y brechas que permiten decisión humana. Verificar PI-01–PI-07.
- [ ] T068 Evolucionar EvaluacionDelDestinoPort y VerificadorDeEvaluacion; implementar adaptador a la fachada pública de Desarrollo y presentar motivos específicos seguros. Mantener bloqueo de aprobación hasta verificar el proveedor real.
- [ ] T069 Implementar instantánea de aprobación y operación de revalidación previa a aplicación, con versión, idempotencia, transiciones adicionales e historial original intacto; actualizar controlador, rutas, recursos y pruebas de autorización.
- [ ] T070 Coordinar aplicar movimiento y ocupación de Estructura en una transacción, registrar evidencia utilizada e incidencia sin efectos parciales y verificar perfil/vigencia. Probar carreras por destino, reintentos y rollback completo (PI-08–PI-09).
- [ ] T071 Extender frontend de movimientos con dimensiones, cobertura, acceso autorizado al diagnóstico, decisión fundamentada y revalidación; añadir Vitest de estados pendientes y permisos, sin captura de evaluaciones en Personal.
- [ ] T072 Ampliar los contratos de lectura y el seguimiento 005 con referencias autorizadas de movimiento, evidencia y revalidaciones; comprobar comparabilidad declarada por Desarrollo y visibilidad propia (PI-10–PI-12).
- [ ] T073 Añadir E2E de propuesta, evidencia real suficiente, decisión, revalidación y aplicación con ocupación coincidente; comprobar dos tenants, 320/1440 px y las doce condiciones PI. Usar una base aislada.
- [ ] T074 Ejecutar Pest/Pint, ESLint/TypeScript/Vitest/build y E2E pertinentes; verificar migraciones sobre copia y regresiones de novedades/políticas/ingresos/posiciones/offboarding. Registrar dependencias pendientes y fallos preexistentes sin declarar la integración completa por usar dobles de prueba.

Dependencias: T063 precede a T064 y T068; T065 precede a T070; T066 precede al frontend; T067 acompaña T068–T069; T069 y T070 preceden a T071–T073; T074 cierra la entrega. T072 coordina cambios con 005, sin duplicar su implementación. Los ajustes de ausencias y 016 son una entrega independiente y no se marcan completados por esta reformulación.
