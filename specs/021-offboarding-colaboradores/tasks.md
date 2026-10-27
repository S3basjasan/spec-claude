# Tasks: Offboarding de colaboradores

**Input**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md · **Tests**: obligatorios (constitución). `GP` = `backend/app/Modules/GestionPersonal`.

## Phase 1: Setup (esquema)

- [X] T001 Migración `backend/database/migrations/2026_10_26_000001_create_offboarding_expedientes_table.php`: tabla con CHECK de motivo/estado/cierre, índices (operación única, una salida viva por vínculo, listado) y triggers de columnas selladas y sin DELETE
- [X] T002 Migración `2026_10_26_000002_create_offboarding_tareas_entrevistas_tables.php`: `offboarding_tareas`, `offboarding_documentos`, `offboarding_entrevistas`, `offboarding_respuestas` con CHECK 1..5, únicos y triggers de inmutabilidad tras estado terminal
- [X] T003 Migración `2026_10_26_000003_create_offboarding_eventos_table.php` append-only con operación única
- [X] T004 [P] Prueba de esquema `backend/tests/Feature/GestionPersonal/Offboarding/EsquemaDeOffboardingTest.php`

## Phase 2: Foundational

- [X] T005 [P] Dominio: value objects (`MotivoDeSalida`, `EstadoDeSalida`, `EstadoDeEntrevista`), `CuestionarioDeSalida` v1, `ReglasDeSalida`, `RepresentacionParaAnalisis`, `CalculadorDeAgregados`, `Expediente` y excepciones en `GP/Domain/`
- [X] T006 `GP/Domain/Repositories/ExpedienteRepositoryInterface.php` y `GP/Infrastructure/Persistence/Eloquent/Repositories/EloquentExpedienteRepository.php`
- [X] T007 [P] Puertos `CoordinadorDeCuentaPort`, `PermisoDeEntrevistasPort` y adaptadores «no instalado»; método `cerrarVinculoEnFecha` en `VinculoLaboralRepositoryInterface` y su implementación
- [X] T008 Servicios `ControlDeOffboarding`, `FormalizadorDeSalida`, `ArmadorDeExpediente`; enlaces en `GestionPersonalServiceProvider`; `TipoDeHecho::Salida`
- [X] T009 [P] Helpers de prueba `backend/tests/OffboardingHelpers.php` (requerido desde `Pest.php`) y pruebas unitarias `tests/Unit/GestionPersonal/Offboarding/`
- [X] T010 [P] Frontend base: `api.ts`, `text.ts`, `validation.ts`, `errores.ts`, `nav.tsx`, `index.ts`, `src/test/offboardingFixtures.ts`

## Phase 3: US1 — Formalizar y planificar (P1) 🎯 MVP

- [X] T011 [P] [US1] Pruebas `FormalizarSalidaTest.php` (feliz, política ausente/no disponible, datos inválidos, sin vínculo, ya formalizada, idempotencia, vínculo intacto, plantilla conservada)
- [X] T012 [US1] Casos `FormalizarSalida`, `ObtenerContextoDeSalida`, `CancelarSalida`
- [X] T013 [US1] Controlador, requests y rutas `POST /api/offboarding`, `GET /contexto`, `GET /documentos`, `POST /{id}/cancelacion`
- [X] T014 [P] [US1] Frontend: `FormalizarSalidaPage` con condiciones de política y pruebas Vitest

## Phase 4: US2 — Checklist y cierre (P1)

- [X] T015 [P] [US2] Pruebas `ChecklistYCierreTest.php` (evidencia, completar, cierre rechazado/confirmado, entrevista sin resolver, concurrencia, aislamiento)
- [X] T016 [US2] Casos `CompletarTarea`, `ConfirmarCierre`, `ObtenerExpediente`, `ListarExpedientes`, `ResumirOffboarding` y rutas
- [X] T017 [P] [US2] Frontend: `OffboardingPage`, `ExpedientePage` con checklist y diálogos, pruebas Vitest

## Phase 5: US3 — Aplicar la salida (P1)

- [X] T018 [P] [US3] Pruebas `AplicarSalidaTest.php` y `ProcesoDeSalidasTest.php` (futura, efectiva, tareas pendientes, idempotencia, incidencias, cuenta pendiente, zona horaria, tenants, trayectoria)
- [X] T019 [US3] Casos `AplicarSalida`, `AplicarSalidasProgramadas`, comando `offboarding:aplicar-salidas`, programación en `routes/console.php`, hecho `Salida` en `ConsultarTrayectoria` y `EloquentLectorLaboral`
- [X] T020 [P] [US3] Frontend: `PanelDeAplicacion` (programada/efectiva/incidencia/cuenta) y pruebas

## Phase 6: US4 — Entrevista de salida (P2)

- [X] T021 [P] [US4] Pruebas `EntrevistaDeSalidaTest.php` (guardar, ausente ≠ 0, rango, categorías múltiples, completar, inmutable, declinar, no realizada, sin ficha, permisos)
- [X] T022 [US4] Casos `GuardarEntrevista`, `ConfirmarEntrevista`, `DeclinarEntrevista`, `RegistrarEntrevistaNoRealizada`, `ObtenerMiDesvinculacion`, `MiDesvinculacionController` y rutas
- [X] T023 [P] [US4] Frontend: `MiDesvinculacionPage`, `EntrevistaDeSalidaPage` y pruebas

## Phase 7: US5 — Consulta y estructura (P2)

- [X] T024 [P] [US5] Pruebas `ConsultaYPrivacidadTest.php` (permiso, registro de consultas, estructura, agregados ocultos, sin IA, aislamiento)
- [X] T025 [US5] Casos `ConsultarRespuestas`, `ObtenerEstructura`, `ObtenerAgregados` y rutas
- [X] T026 [P] [US5] Frontend: sección de entrevista en el expediente y pruebas; E2E `e2e/offboarding.spec.ts` con siembra del tenant 2

## Phase 8: Polish

- [X] T027 [P] Rutas y navegación en `App.tsx`; filas en `design/DESIGN.md`
- [X] T028 Pint, Pest (módulo y regresión GP), `tsc`/`eslint`/Vitest/build y E2E; informe de compuertas abiertas

**Dependencias**: Setup → Foundational → US1 → US2 → US3 → US4 → US5 → Polish. **MVP**: US1 + US2 + US3.
