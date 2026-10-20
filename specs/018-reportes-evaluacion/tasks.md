---

description: "Tareas de la feature 018-reportes-evaluacion"
---

# Tasks: Reportes de evaluación de resultados

**Input**: Documentos de diseño en `/specs/018-reportes-evaluacion/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/reportes-api.md, contracts/reportes-puertos-y-facades.md, quickstart.md. Requiere 000-login, 001/004 (competencias en `EstructuraFacade`), 005-unidad-organizacional (unidades = departamentos), 005-fichas-colaboradores y 006-ingresos-reingresos (`PersonalFacade`, asignaciones laborales, `colaborador_cuentas`), 006-deteccion-necesidades-formativas (DNF), 013-plan-formacion-anual, 016-micro-learning y 017-actividades-formativas ya implementadas.

**Tests**: Incluidos. La constitución exige Pest, Vitest + Testing Library y un E2E real del flujo principal.

**Organization**: Tareas agrupadas por historia de usuario (US1–US8 de spec.md) para implementarlas y probarlas de forma independiente. La feature añade tres tablas propias al módulo `Desarrollo`; solo hace **cambios aditivos de lectura** en `PlanRepositoryInterface`, `DnfRepositoryInterface` y `PersonalFacade`. No expone Facade (FR-042).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: Historia de usuario a la que pertenece (US1–US8)
- Rutas relativas a la raíz del repositorio

## Path Conventions

- Backend: `backend/app/Modules/Desarrollo/…`. Abreviatura **`$D`** = `backend/app/Modules/Desarrollo`
- Frontend: `frontend/src/modules/desarrollo/reportes/…` (reutiliza `frontend/src/shared/ui/…`). Abreviatura **`$F`** = `frontend/src/modules/desarrollo/reportes`
- Pruebas backend: `backend/tests/Unit/Desarrollo/Reportes/` y `backend/tests/Feature/Desarrollo/Reportes/`; pruebas de componentes: `*.dom.test.tsx` junto al código (con `// @vitest-environment jsdom`); E2E en `frontend/e2e/reportes.spec.ts`
- Regla de módulos: Reportes **no lee tablas de Plan, Actividades, Micro-Learning ni DNF**; usa sus puertos (`Fuente*Interface`) cuyos adaptadores llaman a los repositorios existentes. Personal y Estructura solo por puertos cuyos adaptadores llaman a las Facades. `actividad_id`, `plan_accion_id`, `colaborador_id` y los ids de usuario son enteros **sin clave foránea** hacia otras features; las tres tablas nuevas no tienen FK entre sí ni cascadas
- Valores en base de datos (sin tildes; la interfaz muestra con tilde): `tipo` ∈ `Presencial` | `Virtual` | `ELearning` | `OJT` | `Codesarrollo` | `Mentoring` más `Microlearning` solo como filtro; estado de acción/actividad ∈ `Programada` | `EnCurso` | `Finalizada` | `Cancelada`; asistencia ∈ `Pendiente` | `Presente` | `Ausente` | `Justificado`; estado de brecha ∈ `ConBrecha` | `Cumple` | `Supera` | `SinEvaluacion`; roles: capacidad `admin` (Administrador) y rol `user` (Colaborador), excluyentes
- Reutilizar sin modificar: `Button`, `Badge`, `Alert`, `ConfirmDialog`, `Modal`, `SelectField`, `SearchField`, `TextField`, `TextareaField`, `Tabs`, `Pagination`, `EmptyState`, `ErrorState`, `Skeleton` y `AppShell`. No crear componentes equivalentes. La descarga de archivos usa el cliente compartido (patrón de `frontend/src/shared/api/pdf.ts`)
- En frontend, `reportes` **no importa nada** de `actividades`, `plan-formacion`, `micro-learning`, `dnf` ni `catalogo-formativo`, salvo `modules/auth/login` (rol); copiar el patrón de `RequireRole` de `actividades` en lugar de importarlo
- Guía visual: `design/ReportesAdmin.dc.html` y `design/ReportesColaborador.dc.html` son **referencia primordial, no código para copiar**; en estilos manda `design/DESIGN.md`. **Manda la spec sobre los tableros** (plan.md, tabla «Diferencias entre tableros y spec»): comparación contra el período anterior (no metas), filtros de plan y de Micro-learning, sin «Ver como colaborador», alerta de reingreso binaria, sección de evaluación de impacto y «Pendientes por responder» añadidas
- Insumo `plan-reportes.md` y plan.md: donde difieren, **prevalecen plan.md, research.md y data-model.md** (`tenant_id` añadido, puertos en lugar de Facades dentro del módulo, sin caché inicial)
- Sin dependencias nuevas: el `.xlsx` se escribe con `ZipArchive` y los gráficos con SVG/CSS
- Lecciones previas: en E2E localizar campos con `getByRole('textbox', { name, exact: true })`; escribir archivos con acentos con la herramienta Write (los heredocs de Bash fallan; tampoco hay Python); ver la memoria «Entorno local de pruebas» (OneDrive/Windows) al correr Pest, Vitest y Playwright

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Línea base verde y diseño registrado antes de tocar código

- [X] T001 Verificar la línea base: `vendor/bin/pest` y `vendor/bin/pint --test` en `backend/`; `npm run lint`, `npx tsc -b`, `npx vitest run` y `npx playwright test` en `frontend/` (sin cambios de archivos). Anotar los fallos preexistentes de la memoria del proyecto (Pest 111 rojos, Vitest 54, `tsc -b` 18 errores en `estructura/unidades`; `IngresosSeeder` falla por el índice `posiciones_una_maxima_autoridad`) para no atribuirlos a esta feature
- [X] T002 Actualizar `design/DESIGN.md` (Constitución III) **antes** de cualquier tarea de frontend: en §2 los colores semánticos de variación (positiva `--accent`, negativa `--danger`, neutra `--neutral`) y del badge «Alerta de reingreso» (`--danger`/`--dangersoft`) y de «Con evaluación» / «Sin evaluación»; en §4 los componentes **Tarjeta de indicador con comparación**, **Fila de indicadores de gestión**, **Gráfico de barras / línea / pastel con tabla accesible**, **Celda de hito 30-60-90**, **Formulario de encuesta** y **Formulario de seguimiento**; en §5 los patrones «Dashboard de reportes con pestañas», «Evaluación de impacto con modal» y «Mi progreso»; las filas de la tabla «Diferencias entre tableros y spec» de plan.md. Sin cambios si ya está completo

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Tablas, modelos, dominio compartido, puertos de lectura, repositorio base, rutas protegidas y piezas de frontend que usan todas las historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

### Backend – datos

- [X] T003 Crear `backend/database/migrations/2026_10_22_000001_create_encuestas_satisfaccion_table.php` según data-model.md: `id`; `tenant_id` entero sin signo con índice (sin FK); `actividad_id` entero sin signo (sin FK); `colaborador_id` entero sin signo (sin FK); `calificacion` entero pequeño sin signo entre 1 y 5; `comentario` texto anulable, máximo 1 000 caracteres (validación); `created_at` y **sin** `updated_at`. Única `(actividad_id, colaborador_id)`; índice `(tenant_id, actividad_id)`
- [X] T004 [P] Crear `backend/database/migrations/2026_10_22_000002_create_seguimientos_impacto_table.php`: `id`; `tenant_id` entero sin signo con índice (sin FK); `actividad_id` y `colaborador_id` enteros sin signo (sin FK); `hito` entero pequeño sin signo ∈ 30 | 60 | 90; `aplicacion` y `apoyo` enteros pequeños sin signo entre 1 y 5; `comentario` texto anulable, máximo 1 000 caracteres; `created_at` y **sin** `updated_at`. Única `(actividad_id, colaborador_id, hito)`; índice `(tenant_id, actividad_id)`
- [X] T005 [P] Crear `backend/database/migrations/2026_10_22_000003_create_impactos_accion_table.php`: `id`; `tenant_id` entero sin signo con índice (sin FK); `plan_accion_id` entero sin signo **único** (sin FK); `indicador` `string(150)`; `unidad` `string(50)`; `valor_antes` y `valor_despues` `decimal(14,4)`; `beneficio_estimado` `decimal(12,2)` mayor o igual a 0 (USD); `justificacion` texto obligatorio, máximo 2 000 caracteres (validación); `created_by` y `updated_by` enteros sin signo; timestamps
- [X] T006 [P] Crear los modelos Eloquent `$D/Infrastructure/Persistence/Eloquent/Models/EncuestaSatisfaccionModel.php` y `SeguimientoImpactoModel.php` (con `UPDATED_AT = null`, sin soft deletes) e `ImpactoAccionModel.php`, siguiendo la convención de `ActividadModel`; los casts numéricos conservan decimales y enteros
- [X] T007 [P] Escribir `backend/tests/Feature/Desarrollo/Reportes/ReportesMigracionTest.php`: las tres tablas existen con sus columnas; `(actividad_id, colaborador_id)` único en encuestas; `(actividad_id, colaborador_id, hito)` único en seguimientos; `plan_accion_id` único en impactos; encuestas y seguimientos no tienen `updated_at`

### Backend – dominio, puertos y ampliaciones aditivas

- [X] T008 [P] Crear los objetos de valor en `$D/Domain/ValueObjects/`: `PeriodoReporte` (desde, hasta; año actual por defecto; `anterior()` de igual duración inmediatamente previa; lanza `PeriodoInvalido` si `desde > hasta`), `FiltroReportes` (periodo, departamentoId, tipo ∈ los seis tipos de acción o `Microlearning`, planId; inmutable), `HitoSeguimiento` (enum 30, 60, 90 con `vencimiento(fechaFin)`, `limiteVentana(fechaFin)` = vencimiento + 15 días) y `VariacionIndicador` (`valor`, `anterior`, `diferencia`, `variacionPct`; `variacionPct` nulo si el anterior es nulo o 0)
- [X] T009 [P] Crear las excepciones de dominio en `$D/Domain/Exceptions/`: `PeriodoInvalido` (`code: periodo_invalido`), `ActividadNoEncontradaParaReporte`, `AccionNoEncontradaParaReporte`, `EncuestaNoPermitida` con `code` ∈ `actividad_no_finalizada` | `sin_asistencia` | `ventana_cerrada` | `ya_respondida`, `SeguimientoNoPermitido` con `code` ∈ `hito_invalido` | `hito_no_vencido` | `ventana_cerrada` | `sin_asistencia` | `ya_respondida` | `actividad_no_finalizada`, `ImpactoNoPermitido` (`code: accion_no_finalizada`) y `FormatoDeExportacionInvalido` (`code: formato_invalido`)
- [X] T010 [P] Crear `$D/Domain/Services/CalculadorDePeriodo.php` (sin Laravel): `resolver(?string $desde, ?string $hasta, DateTimeImmutable $hoy): PeriodoReporte` (sin fechas = 1 ene – 31 dic del año de `$hoy`), `pertenece(PeriodoReporte, DateTimeImmutable $fecha): bool` (límites inclusivos) y `variacion(?float $actual, ?float $anterior): VariacionIndicador`. Escribir `backend/tests/Unit/Desarrollo/Reportes/CalculadorDePeriodoTest.php`: año actual por defecto, período anterior de igual duración (años bisiestos y rangos de 30 días), límites inclusivos, `desde > hasta` rechazado, variación con anterior nulo y con anterior 0 (nula), diferencia y porcentaje correctos
- [X] T011 [P] Crear `$D/Domain/Services/ReglasDeEncuesta.php` y `ReglasDeSeguimiento.php` (sin Laravel) y sus pruebas `backend/tests/Unit/Desarrollo/Reportes/ReglasDeEncuestaTest.php` y `ReglasDeSeguimientoTest.php`. Encuesta: `puedeResponder(estadoActividad, asistencia, fechaFin, hoy, yaRespondio)` con ventana de 30 días corridos desde `fecha_fin` (inclusive) y motivo ∈ `actividad_no_finalizada` | `sin_asistencia` | `ventana_cerrada` | `ya_respondida`; `vence(fechaFin)`. Seguimiento: `puedeResponder(hito, estadoActividad, asistencia, fechaFin, hoy, yaRespondio)` con ventana `[fechaFin + h, fechaFin + h + 15 días]` inclusive y motivo ∈ `hito_invalido` | `hito_no_vencido` | `ventana_cerrada` | `sin_asistencia` | `ya_respondida` | `actividad_no_finalizada`; `pendientes(...)` por actividad. Probar los días límite (día 30, 31; vencimiento −1, vencimiento, +15, +16), hitos 45 y 0 inválidos y cada motivo. No hay fecha de activación: una actividad Finalizada hace 10 días ofrece su encuesta y, si corresponde, su hito (spec Assumptions; research R10)
- [X] T012 [P] Crear `$D/Domain/Services/CalculadorDeRoi.php` (sin Laravel): `deAccion(float $beneficio, float $costo): ?float` = `(beneficio − costo) / costo × 100`, nulo si el costo es 0; `agregado(list<{beneficio, costo}>): ?float` = `(Σ beneficio − Σ costo) / Σ costo × 100`, nulo si la lista está vacía o `Σ costo` es 0. Escribir `backend/tests/Unit/Desarrollo/Reportes/CalculadorDeRoiTest.php` (5 000 USD sobre 2 000 → 150 %, costo cero, lista vacía, redondeo a 1 decimal en la presentación y no en el cálculo)
- [X] T013 [P] Crear `$D/Domain/Services/ProrrateadorPorDepartamento.php` (sin Laravel): `proporcion(int $asistentesDepartamento, int $asistentesTotal): float` (0 si el total es 0) y `aplicar(float $monto, float $proporcion): float`. Escribir `backend/tests/Unit/Desarrollo/Reportes/ProrrateadorPorDepartamentoTest.php` (proporciones 0, parcial y 1; costo y beneficio con la misma proporción dejan el porcentaje de ROI intacto)
- [X] T014 Crear los puertos en `$D/Application/Ports/`: `FuenteDePlanesInterface` (`acciones(tenantId, filtros)`, `planes(tenantId)`), `FuenteDeActividadesInterface` (`actividades`, `asistencias`, `actividadesDeColaborador`), `FuenteDeMicrolearningInterface` (`completaciones`, `certificadosDe`), `FuenteDeDnfInterface` (`evaluaciones`) y `DepartamentoDeColaboradorInterface` (`actuales(tenantId, colaboradorIds, fecha): array<int, int|null>`), con docblocks de forma como en contracts/reportes-puertos-y-facades.md
- [X] T015 Ampliar `$D/Domain/Repositories/PlanRepositoryInterface.php` y `EloquentPlanRepository::planesConAcciones` para que cada acción de la respuesta añada `nombre`, `competencia_ids` y `participante_ids` (los campos actuales no cambian). Ampliar `$D/Domain/Repositories/DnfRepositoryInterface.php` con `evaluacionesDeCompetencias(int $tenantId, array $filtros): array` (`fecha_desde`, `fecha_hasta`, `colaborador_id`, `colaborador_ids`; devuelve `{dnf_id, fecha, colaborador_id, competencia_id, estado, nivel_requerido, nivel_actual}` ordenado por fecha y `dnf_id`, leyendo `dnf_colaboradores` y `dnf_detalles`) e implementarlo en el repositorio Eloquent de DNF. Escribir `backend/tests/Feature/Desarrollo/Reportes/AmpliacionesDeFuentesTest.php`: la forma anterior de `planesConAcciones` se conserva, los campos nuevos aparecen y `evaluacionesDeCompetencias` ordena y filtra correctamente
- [X] T016 Añadir a `backend/app/Modules/GestionPersonal/Presentation/Facades/PersonalFacade.php` (y a su servicio) el método `getDepartamentoActual(int $tenantId, array $colaboradorIds, string $fecha): array` (`colaborador_id => unidad_id|null`) a partir de la asignación laboral vigente en la fecha y `EstructuraFacade::getPosicionesDeGestionPorIds (mediante el puerto LectorEstructuraPort de Gestión de Personal)`; lanza `InformacionNoDisponible` si la lectura falla, igual que `getDesarrollo`. Añadir su `@method`. Escribir `backend/tests/Feature/GestionPersonal/PersonalFacadeDepartamentoActualTest.php`: colaborador con asignación vigente, sin asignación (nulo), con asignación futura o terminada (nulo), de otro tenant (ausente) y cambio de departamento (devuelve el actual)
- [X] T017 [P] Crear los adaptadores en `$D/Infrastructure/Adapters/`: `PlanesParaReportes` (sobre `PlanRepositoryInterface::planesConAcciones`; calcula el estado de la acción con `CalculadorDeEstadoDeAccion` y `RelojInterface::hoy`), `ActividadesParaReportes` (sobre `ActividadRepositoryInterface::actividades/asistencias/actividadesDeColaborador`), `MicrolearningParaReportes` (sobre `ProgresoMicroRepositoryInterface::completaciones/progresosDe`), `DnfParaReportes` (sobre `DnfRepositoryInterface::evaluacionesDeCompetencias`) y `DepartamentoDeColaboradorDesdePersonal` (sobre `PersonalFacade::getDepartamentoActual`)
- [X] T018 Crear `$D/Domain/Repositories/ReportesRepositoryInterface.php` con las operaciones de contracts/reportes-puertos-y-facades.md (`registrarEncuesta`, `encuestasDe`, `encuestaDe`, `comentarios`, `registrarSeguimiento`, `seguimientosDe`, `seguimientoDe`, `impactoDe`, `impactosDe`, `guardarImpacto`) **sin** métodos para actualizar ni eliminar encuestas o seguimientos, y la implementación `$D/Infrastructure/Persistence/Eloquent/Repositories/EloquentReportesRepository.php` (todas las consultas filtran por `tenant_id`; `comentarios` devuelve solo `calificacion`, `comentario` y fecha, nunca `colaborador_id`)
- [X] T019 Crear `$D/Application/Services/RecolectorDeReportes.php`: dado `tenantId` y `FiltroReportes`, reúne con los puertos las acciones y planes, actividades, asistencias, completaciones, evaluaciones DNF y departamento actual de los colaboradores involucrados (una sola llamada por fuente, sin N+1); filtra por período según la regla de pertenencia de data-model.md (fecha de fin de acción/actividad, fecha de finalización del micro-contenido), por `tipo` (incluido `Microlearning`), `plan_id` y `departamento_id` (departamento **actual** del colaborador); y entrega un conjunto de datos común a dashboard, detalle, seguimiento y exportación. Cada fuente se consulta dentro de un `try/catch` de lectura: si una falla, el conjunto marca esa parte como `null` y devuelve `fuentes_no_disponibles` (`plan`, `actividades`, `microlearning`, `dnf`, `personal`) en lugar de propagar el error (FR-043; research R15). Los colaboradores sin departamento actual (`null`) se conservan como «Sin departamento» y se excluyen cuando se filtra por un departamento. Escribir `backend/tests/Feature/Desarrollo/Reportes/RecolectorDeReportesTest.php` con datos sembrados: el filtro por departamento deja solo los participantes de ese departamento actual, `tipo=Microlearning` excluye actividades, el período usa `fecha_fin`, aísla por `tenant_id`, una fuente caída (doble que lanza) produce `fuentes_no_disponibles` sin lanzar, un colaborador sin asignación vigente queda como «Sin departamento» y fuera al filtrar, y las respuestas de un colaborador dado de baja siguen contando sin filtro de departamento
- [X] T020 Crear el trait `$D/Presentation/Controllers/RespondeErroresDeReportes.php` (siguiendo `RespondeErroresDeActividad`): 404 para las excepciones `*NoEncontrada*`, 422 con `{message, code}` o `{message, errors}` para el resto
- [X] T021 Registrar en `$D/Infrastructure/Providers/DesarrolloServiceProvider.php` los *bindings* nuevos (`ReportesRepositoryInterface`→`EloquentReportesRepository` y las cinco fuentes/puertos de T014→sus adaptadores de T017) y añadir en `$D/Routes/api.php` el grupo `Route::prefix('api/reportes')->middleware('api')` con rutas fijas antes de `/{id}`: `auth:sanctum` en todas y `admin` en todas salvo `mi-progreso`, `encuestas` y `seguimientos`. Crear en la misma tarea los controladores vacíos `ReportesController`, `ReportesImpactoController` y `MiProgresoController` (sin acciones todavía, usando `RespondeErroresDeReportes`) y declarar solo las rutas (nombres `reportes.*`) de contracts/reportes-api.md que existan en cada historia, añadiendo cada ruta en la tarea de su historia, para que `php artisan route:list` funcione en todo momento

### Frontend – base común

- [X] T022 [P] Crear `$F/text.ts` con **todo** el texto visible en español (navegación «Reportes», títulos de pestañas, etiquetas de filtros, nombres de indicadores, notas «Sin datos en el período», comparación, tablas, estados vacío/carga/error, diálogos, formularios, mensajes de error por `code`, confirmaciones, texto del colaborador) y `$F/estilosEstado.ts` con las variantes de `Badge` (alerta de reingreso, con/sin evaluación) y de variación positiva, negativa y neutra
- [X] T023 [P] Crear `$F/api.ts` con los tipos TypeScript estrictos (sin `any`) de contracts/reportes-api.md y las funciones de acceso con el cliente HTTP compartido de `frontend/src/shared/api`; las escrituras (`PUT`, `POST`) sin reintentos; `exportar` obtiene un `Blob` con `responseType: 'blob'` (patrón de `shared/api/pdf.ts`) y lee el error JSON. Crear `$F/hooks/claves.ts` (todas bajo `['reportes', …]`) y `$F/formato.ts` (números, moneda USD, porcentajes con 1 decimal, horas, fechas; «—» para nulos)
- [X] T024 [P] Crear `$F/components/RequireRole.tsx` (patrón de `actividades`: elige la vista por `useCurrentUser().data.role`, `admin` o `user`; `role="status"` mientras carga y `role="alert"` sin permisos) y `$F/nav.tsx` con el ítem «Reportes» (icono de `shared/ui/icons`)
- [X] T025 [P] Crear `$F/hooks/useFiltrosReportes.ts` y `$F/components/BarraDeFiltros.tsx`: filtros `fecha_desde`, `fecha_hasta`, `departamento_id`, `tipo` (seis tipos + «Micro-learning») y `plan_id` persistidos en la URL (`useSearchParams`); año actual por defecto; botón «Limpiar filtros»; etiquetas asociadas a los controles; valida `desde ≤ hasta` antes de consultar. Escribir `BarraDeFiltros.dom.test.tsx` y `useFiltrosReportes.test.ts` (estado inicial, escritura en la URL, limpieza, rango inválido)
- [X] T026 Registrar en `frontend/src/App.tsx` el ítem `reportesNav` en `navItems` (después de `actividadesNav`) y la ruta `reportes` (página raíz por rol vía `RequireRole`), con páginas esqueleto `ReportesPage` y `MiProgresoPage` que se completan en cada historia

**Checkpoint**: tablas, dominio compartido, fuentes de lectura, repositorio base, rutas protegidas y piezas comunes listas; las historias pueden empezar

---

## Phase 3: User Story 1 - Consultar el dashboard de indicadores (Priority: P1) 🎯 MVP

**Goal**: el administrador ve las cinco tarjetas con comparación contra el período anterior, los indicadores de gestión y los tres gráficos, con los filtros.

**Independent Test**: con un plan Ejecutando con acciones Finalizadas, asistencias, encuestas y una brecha cerrada, abrir el dashboard y verificar que cada indicador coincide con el cálculo manual y que la comparación contra el período anterior es correcta; sin datos, los indicadores muestran un guion y una nota.

### Tests (US1)

- [X] T027 [P] [US1] Escribir `backend/tests/Unit/Desarrollo/Reportes/CalculadorDeBrechasCerradasTest.php` (sin base de datos): brecha detectada en la primera DNF `ConBrecha` y cerrada en la primera posterior `Cumple` o `Supera` (la fecha de cierre es la de esa DNF); `SinEvaluacion` ignorada; una nueva `ConBrecha` tras un cierre abre otra brecha; una sola DNF no cierra nada; «detectadas del período» cuenta solo la primera aparición dentro del período; brechas activas al cierre de mes con la DNF más reciente de cada colaborador a esa fecha; serie mensual de activas y cerradas; una brecha detectada en un período anterior y cerrada en el actual cuenta como cerrada del actual y no como detectada del actual (las cerradas pueden superar a las detectadas)
- [X] T028 [P] [US1] Escribir `backend/tests/Unit/Desarrollo/Reportes/CalculadorDeIndicadoresTest.php` (sin base de datos): % Plan cumplido = Finalizadas / no canceladas × 100 solo de planes Ejecutando o Cerrado; Total horas = horas-persona de actividades (duración × Presentes) + horas de micro-contenidos, con desglose; satisfacción = promedio 1–5; ROI estimado con evaluaciones, sin ellas (nulo) y con costo total 0 (nulo); inversión por colaborador formado, horas por colaborador formado y costo por hora, con denominador 0 → nulo; filtro `Microlearning` deja solo horas y distribución
- [X] T029 [P] [US1] Escribir `backend/tests/Feature/Desarrollo/Reportes/DashboardTest.php`: 200 con la forma de contracts/reportes-api.md; período por defecto = año actual; período anterior equivalente con diferencia y variación; sin datos anteriores `anterior`/`diferencia`/`variacion_pct` nulos; indicadores sin datos con `valor: null`; el filtro por departamento recalcula métricas y prorratea costos (verificar un caso a mano); `brechas_cerradas` ignora `tipo` y `plan_id`; `tipo=Microlearning`; 422 `periodo_invalido`; con una fuente caída (doble que lanza) responde 200 con `fuentes_no_disponibles` y los indicadores dependientes con `valor: null` y `disponible: false`; `brechas_cerradas` con `valor` mayor que `detectadas` se devuelve sin error; colaboradores sin departamento actual aparecen como «Sin departamento» en `horas_por_departamento`; 401 sin sesión y 403 para Colaborador; aislamiento por `tenant_id`

### Implementation (US1)

- [X] T030 [US1] Crear `$D/Domain/Services/CalculadorDeBrechasCerradas.php` (sin Laravel) según R4 de research.md: máquina de estados por (colaborador, competencia) sobre evaluaciones ordenadas por fecha; devuelve brechas `{colaboradorId, competenciaId, detectadaEn, cerradaEn|null}`, el conteo de detectadas y cerradas en un período, la serie mensual (`mes`, `activas`, `cerradas`) y la evolución por competencia para Mi progreso
- [X] T031 [US1] Crear `$D/Domain/Services/CalculadorDeIndicadores.php` (sin Laravel): recibe el conjunto de datos filtrado de `RecolectorDeReportes` (ya con prorrateo aplicado mediante `ProrrateadorPorDepartamento`) y devuelve los cinco indicadores, los de gestión y los datos de gráficos (horas por departamento, distribución por siete tipos) de un período; el caso de uso lo ejecuta dos veces (período y período anterior) y combina con `CalculadorDePeriodo::variacion`. Redondeo solo en la presentación (1 decimal en el contrato). Los micro-contenidos no tienen costo: solo aportan horas y no entran en costos ni en inversión por colaborador ni costo por hora. Si el recolector informa una fuente caída, los indicadores y gráficos que dependen de ella se devuelven con `valor: null` y `disponible: false` y el resto se calcula
- [X] T032 [US1] Crear `$D/Application/UseCases/ObtenerDashboard.php`: resuelve el período con el reloj, ejecuta `RecolectorDeReportes` para el período y para el anterior (mismos filtros; `brechas_cerradas` solo con fechas y departamento), obtiene nombres de departamentos con `DepartamentosInterface`, lee las evaluaciones de impacto con `ReportesRepositoryInterface::impactosDe` para el ROI y las encuestas para la satisfacción, y arma el contrato
- [X] T033 [US1] Crear `$D/Presentation/Requests/FiltrosReportesRequest.php` (valida `fecha_desde`/`fecha_hasta` AAAA-MM-DD, `departamento_id` entero, `tipo` ∈ los siete valores, `plan_id` entero, `page`, `per_page`, `q`) y `$D/Presentation/Controllers/ReportesController.php` con la acción `dashboard` (delgada, sin consultas) y registrar en `$D/Routes/api.php` la ruta `GET /api/reportes/dashboard` (`auth:sanctum` + `admin`)
- [X] T034 [US1] Hacer pasar T027–T029; ejecutar `vendor/bin/pint` sobre los archivos nuevos

### Frontend (US1)

- [X] T035 [P] [US1] Crear `$F/hooks/useDashboard.ts` (TanStack Query con los filtros de la URL, sin reintentos propios de escritura) y `$F/components/TarjetaIndicador.tsx` (etiqueta, valor, nota, comparación con valor anterior, diferencia coloreada y variación; guion y nota explicativa si no hay datos; la variación no depende solo del color) con `TarjetaIndicador.dom.test.tsx`
- [X] T036 [P] [US1] Crear `$F/components/FilaIndicadoresGestion.tsx` (inversión por colaborador formado, horas por colaborador formado, costo por hora, con comparación) y `$F/components/GraficoBarrasHoras.tsx`, `GraficoLineaBrechas.tsx` y `GraficoPastelTipos.tsx` con SVG/CSS (sin librería), cada uno con `role="img"` + `aria-label` descriptivo y una tabla de datos equivalente; la línea muestra dos series (activas y cerradas) sin meta; el pastel lista los siete tipos; la barra incluye la categoría «Sin departamento» cuando existe; la tarjeta de brechas cerradas muestra «Cerradas» y «Detectadas» como dos cifras separadas, nunca como fracción. Escribir `*.dom.test.tsx` de la fila y de cada gráfico (estado con datos y vacío «Sin horas ejecutadas con estos filtros»)
- [X] T037 [US1] Completar `$F/pages/ReportesPage.tsx` con `Tabs` (`?vista=indicadores|detalle|seguimiento|impactos`, por defecto `indicadores`), la `BarraDeFiltros`, el encabezado «Reportes de evaluación de resultados», el aviso «Un indicador sin comparación es solo un dato» y la pestaña de indicadores (cinco tarjetas, fila de gestión y gráficos), con estados loading (`Skeleton`), empty, error con «Reintentar» (`ErrorState`) y success. Si `fuentes_no_disponibles` no está vacío, mostrar un `Alert` con las fuentes afectadas y «No disponible» en los indicadores y gráficos que dependen de ellas. Escribir `ReportesPage.dom.test.tsx` (datos, vacío, error, fuente no disponible, cambio de filtro actualiza la URL)

**Checkpoint**: el administrador ve el dashboard filtrable; MVP demostrable

---

## Phase 4: User Story 2 - Filtrar y analizar la tabla detallada (Priority: P1)

**Goal**: tabla paginada de actividades En curso o Finalizadas del período con métricas prorrateadas por departamento y acceso a los comentarios de satisfacción sin autor.

**Independent Test**: filtrar por un departamento y verificar que las filas muestran solo los participantes de ese departamento y el costo prorrateado; abrir los comentarios de una actividad y comprobar que no aparece ningún dato del autor.

### Tests (US2)

- [X] T038 [P] [US2] Escribir `backend/tests/Feature/Desarrollo/Reportes/DetalleTest.php`: una fila por actividad En curso o Finalizada con `fecha_fin` en el período (las Programadas y Canceladas no aparecen); columnas del contrato; filtros por departamento (asistentes, horas, calificación, satisfacción y costo recalculados con la proporción de asistentes), tipo y plan; búsqueda `q`; paginación (`meta`); satisfacción nula sin encuestas; 403 para Colaborador
- [X] T039 [P] [US2] Escribir `backend/tests/Feature/Desarrollo/Reportes/ComentariosSatisfaccionTest.php`: devuelve `promedio`, `respuestas` y la lista de `calificacion`, `comentario` y `fecha` (solo el día `AAAA-MM-DD`, sin hora) **sin** `colaborador_id`, nombre ni cualquier clave del autor (aserción sobre las claves de cada elemento y sobre el JSON completo); sin umbral mínimo (una sola respuesta se muestra); paginación; 404 `actividad_no_encontrada`; 403 para Colaborador; aislamiento por `tenant_id`

### Implementation (US2)

- [X] T040 [US2] Crear `$D/Application/UseCases/ListarDetalle.php` (usa `RecolectorDeReportes`, `ProrrateadorPorDepartamento` y las encuestas; ordena por fecha de fin descendente; pagina) y `$D/Application/UseCases/ListarComentariosDeSatisfaccion.php` (valida que la actividad exista con `FuenteDeActividadesInterface`; usa `ReportesRepositoryInterface::comentarios`)
- [X] T041 [US2] Crear `$D/Presentation/Resources/ComentarioSatisfaccionResource.php` (solo `calificacion`, `comentario`, `fecha`) y añadir a `ReportesController` las acciones `detalle` y `satisfaccion`; hacer pasar T038–T039; Pint

### Frontend (US2)

- [X] T042 [P] [US2] Crear `$F/hooks/useDetalle.ts` y `$F/hooks/useComentarios.ts` y `$F/components/TablaDetalle.tsx` (columnas de FR-019, `SearchField` por nombre, `Pagination`, botón por fila «Ver comentarios», estados loading/vacío/error) con `TablaDetalle.dom.test.tsx`
- [X] T043 [US2] Crear `$F/components/ComentariosDialog.tsx` (sobre `Modal`: promedio, cantidad de respuestas y lista paginada de calificación y comentario; **nunca** muestra autor; estado vacío «Aún no hay respuestas») con `ComentariosDialog.dom.test.tsx`, y conectar la pestaña «Detalle» de `ReportesPage` (paginación y `q` en la URL)

**Checkpoint**: dashboard y tabla detallada funcionando

---

## Phase 5: User Story 3 - Responder la encuesta de satisfacción (Priority: P1)

**Goal**: el colaborador con asistencia Presente en una actividad Finalizada responde una vez la encuesta (1–5 + comentario opcional) dentro de 30 días.

**Independent Test**: con un colaborador Presente en una actividad Finalizada, enviar la encuesta con calificación 4 y verificar que queda registrada con fecha, que repetirla se rechaza (`ya_respondida`), que fuera de la ventana de 30 días se rechaza y que el promedio de la actividad la incluye.

### Tests (US3)

- [X] T044 [P] [US3] Escribir `backend/tests/Feature/Desarrollo/Reportes/EncuestaTest.php`: 201 con `{actividad_id, calificacion, comentario, fecha}`; 422 `validacion` con calificación vacía, 0, 6, decimal o texto, y con comentario > 1 000 caracteres; 422 `actividad_no_finalizada` (En curso/Programada/Cancelada); 422 `sin_asistencia` con Ausente, Justificado o Pendiente; 422 `ventana_cerrada` al día 31 y 201 el día 30; 422 `ya_respondida` al repetir; 404 `actividad_no_encontrada`; un colaborador no puede responder por otro (el `colaborador_id` sale del token); 403 para Administrador; 401 sin sesión; no existen PUT ni DELETE (405/404)

### Implementation (US3)

- [X] T045 [US3] Crear `$D/Application/UseCases/ResponderEncuesta.php`: obtiene el colaborador con `CuentaColaboradorInterface`, valida existencia y estado de la actividad y la asistencia con `FuenteDeActividadesInterface`, aplica `ReglasDeEncuesta` con `RelojInterface::hoy` y registra con `ReportesRepositoryInterface::registrarEncuesta` (captura la colisión de la restricción única como `ya_respondida`)
- [X] T046 [US3] Crear `$D/Presentation/Requests/ResponderEncuestaRequest.php` (`actividad_id` entero requerido; `calificacion` entero 1–5 requerido; `comentario` texto opcional máximo 1 000 caracteres) y `$D/Presentation/Controllers/MiProgresoController.php` con la acción `responderEncuesta` (201); conectar la ruta; hacer pasar T044; Pint

### Frontend (US3)

- [X] T047 [P] [US3] Añadir a `$F/validation.ts` el esquema Zod de la encuesta (calificación entera 1–5 obligatoria; comentario opcional ≤ 1 000) con `validation.test.ts`, y crear `$F/hooks/useResponderEncuesta.ts` (sin reintentos; invalida `['reportes','mi-progreso']`)
- [X] T048 [US3] Crear `$F/components/FormularioEncuesta.tsx` (calificación 1–5 con control accesible, comentario opcional, errores por campo, botón bloqueado mientras envía, `ConfirmDialog` antes de enviar porque la respuesta no se puede modificar, mapeo de 422 `code` a mensajes) con `FormularioEncuesta.dom.test.tsx` (validación, confirmación, envío, error de `ya_respondida` y `ventana_cerrada`)

**Checkpoint**: encuestas registrables; el indicador de satisfacción del dashboard ya tiene datos reales

---

## Phase 6: User Story 4 - Vista «Mi progreso» del colaborador (Priority: P1)

**Goal**: el colaborador consulta su resumen, la evolución de sus competencias, sus certificados, su plan de desarrollo y sus pendientes.

**Dependencia explícita**: usa `CalculadorDeBrechasCerradas` (T030) y su prueba (T027) de la Historia 1; no puede completarse antes de ellos. Las demás tareas de esta fase no dependen de US1.

**Independent Test**: con un colaborador con actividades Finalizadas, micro-contenidos completados y dos DNF, verificar las tarjetas, la evolución, la descarga de un certificado y que no ve datos de otros colaboradores; sin datos, mensaje informativo.

### Tests (US4)

- [X] T049 [P] [US4] Escribir `backend/tests/Feature/Desarrollo/Reportes/MiProgresoTest.php`: resumen con formaciones completadas (actividades Finalizadas con asistencia Presente + micro-contenidos completados), competencias adquiridas (brechas cerradas propias), horas acumuladas y certificados; evolución con primera vs más reciente DNF y `solo_actual: true` con una sola DNF; certificados con su descarga (comprobar contra `CertificadoMicroController` que un colaborador recibe 403/404 al pedir el certificado de otro; si 016 ya lo prueba, referenciar esa prueba); próximas acciones; pendientes (encuestas dentro de la ventana de 30 días y seguimientos dentro de su ventana de 15 días); respuesta vacía sin datos (listas vacías y contadores 0); nunca incluye datos de otro colaborador; 403 para Administrador; 401 sin sesión

### Implementation (US4)

- [X] T050 [US4] Crear `$D/Application/UseCases/ObtenerMiProgreso.php`: toma el colaborador de `CuentaColaboradorInterface`; combina `FuenteDeActividadesInterface::actividadesDeColaborador`, `FuenteDeMicrolearningInterface` (completaciones, horas, certificados), `FuenteDeDnfInterface` + `CalculadorDeBrechasCerradas` (competencias adquiridas y evolución con nombres de `NombresDeReferenciaInterface`), `AccionesDePlanInterface::deColaborador` (próximas acciones) y los pendientes calculados con `ReglasDeEncuesta` y `ReglasDeSeguimiento` sobre las respuestas de `ReportesRepositoryInterface`
- [X] T051 [US4] Añadir a `MiProgresoController` la acción `show` (200 `{data}`) y conectar la ruta `GET /api/reportes/mi-progreso`; hacer pasar T049; Pint

### Frontend (US4)

- [X] T052 [P] [US4] Crear `$F/hooks/useMiProgreso.ts`, `$F/components/TarjetasProgreso.tsx` (formaciones completadas, competencias adquiridas, horas acumuladas y certificados) y `$F/components/GraficoEvolucionCompetencias.tsx` (barras de nivel primera DNF frente a actual con la marca del nivel requerido, tabla accesible; con `solo_actual` muestra solo el nivel actual) con sus `*.dom.test.tsx`
- [X] T053 [P] [US4] Crear `$F/components/ListaCertificados.tsx` (descarga en PDF con el cliente compartido y el endpoint de Micro-Learning, sin enlaces con Bearer; botón con `aria-label` «Descargar certificado: …»), `$F/components/PlanDeDesarrollo.tsx` («Mi plan de desarrollo», próximas acciones por fecha) y `$F/components/PendientesPorResponder.tsx` (lista de encuestas con `FormularioEncuesta` de US3 en un `Modal`; los seguimientos se añaden en US5) con `*.dom.test.tsx`
- [X] T054 [US4] Completar `$F/pages/MiProgresoPage.tsx` con tarjetas, evolución, certificados, plan de desarrollo y pendientes, con estados loading, error con «Reintentar» y el mensaje informativo para el colaborador sin datos; escribir `MiProgresoPage.dom.test.tsx` (con datos, sin datos, error)

**Checkpoint**: el colaborador ve su progreso y responde encuestas

---

## Phase 7: User Story 5 - Responder el seguimiento a 30, 60 y 90 días (Priority: P2)

**Goal**: el colaborador responde cada hito (30, 60, 90) desde su vencimiento y durante 15 días, con dos calificaciones y un comentario opcional.

**Independent Test**: con una actividad que terminó hace 30 días, verificar que el hito 30 está pendiente, responderlo y comprobar que no se repite; con 16 días después del vencimiento, el hito ya no se ofrece ni se acepta.

### Tests (US5)

- [X] T055 [P] [US5] Escribir `backend/tests/Feature/Desarrollo/Reportes/SeguimientoRespuestaTest.php`: 201 con `{actividad_id, hito, aplicacion, apoyo, comentario, fecha}` el día del vencimiento y hasta el día +15; 422 `hito_no_vencido` un día antes; 422 `ventana_cerrada` el día +16; 422 `hito_invalido` con 45; 422 `validacion` con `aplicacion` o `apoyo` fuera de 1–5, faltantes o con decimales; 422 `sin_asistencia` y `actividad_no_finalizada`; 422 `ya_respondida` al repetir el mismo hito (otro hito de la misma actividad sí se acepta); 404 `actividad_no_encontrada`; 403 para Administrador; no existen PUT ni DELETE

### Implementation (US5)

- [X] T056 [US5] Crear `$D/Application/UseCases/ResponderSeguimiento.php` (colaborador del token, validaciones con `ReglasDeSeguimiento` y `RelojInterface::hoy`, registro con `registrarSeguimiento`, colisión de la única `(actividad_id, colaborador_id, hito)` como `ya_respondida`), `$D/Presentation/Requests/ResponderSeguimientoRequest.php` (`actividad_id`, `hito` ∈ 30|60|90, `aplicacion` y `apoyo` enteros 1–5 requeridos, `comentario` opcional ≤ 1 000) y la acción `responderSeguimiento` de `MiProgresoController`; conectar la ruta; hacer pasar T055; Pint

### Frontend (US5)

- [X] T057 [P] [US5] Añadir a `$F/validation.ts` el esquema Zod del seguimiento (dos calificaciones enteras 1–5 obligatorias; comentario opcional ≤ 1 000) con `validation.test.ts`, y crear `$F/hooks/useResponderSeguimiento.ts` (sin reintentos; invalida `['reportes','mi-progreso']`)
- [X] T058 [US5] Crear `$F/components/FormularioSeguimiento.tsx` (aplicación y apoyo con control 1–5 accesible, comentario opcional, errores por campo, `ConfirmDialog` antes de enviar, mapeo de `code` a mensajes) con `FormularioSeguimiento.dom.test.tsx`, e integrarlo en `PendientesPorResponder` (seguimientos con su hito y fecha límite) actualizando su prueba

**Checkpoint**: seguimientos registrables; la sección de impacto del administrador ya tiene datos

---

## Phase 8: User Story 6 - Revisar el impacto 30-60-90 días (Priority: P2)

**Goal**: el administrador ve por actividad Finalizada del período la tasa de respuesta, el promedio de aplicación y el de apoyo de cada hito, con badge de alerta de reingreso.

**Independent Test**: con una actividad cuyo promedio de aplicación baja 1 punto o más entre dos hitos, ambos con al menos 3 respuestas, aparece el badge de alerta; con menos de 3 respuestas en alguno, no.

### Tests (US6)

- [X] T059 [P] [US6] Escribir `backend/tests/Unit/Desarrollo/Reportes/CalculadorDeSeguimientoTest.php` (sin base de datos): tasa de respuesta = respuestas / elegibles (Presentes), nula sin elegibles; promedios nulos sin respuestas; alerta con caída exacta de 1 y de más, sin alerta con caída de 0,9, sin alerta con 2 respuestas en alguno de los dos hitos, alerta en el par 60→90 y en el 30→60, sin alerta si el promedio sube
- [X] T060 [P] [US6] Escribir `backend/tests/Feature/Desarrollo/Reportes/SeguimientoAdminTest.php`: una fila por actividad Finalizada del período con los tres hitos (`respuestas`, `tasa_respuesta`, `promedio_aplicacion`, `promedio_apoyo`, `vencimiento`) y `alerta_reingreso`; filtros por departamento (solo participantes de ese departamento actual), tipo y plan; paginación; hito sin respuestas con promedios nulos; 403 para Colaborador

### Implementation (US6)

- [X] T061 [US6] Crear `$D/Domain/Services/CalculadorDeSeguimiento.php` (sin Laravel) según R11 de research.md y `$D/Application/UseCases/ListarSeguimiento.php` (usa `RecolectorDeReportes`, `seguimientosDe`, `ProrrateadorPorDepartamento` solo para elegibles por departamento; pagina); añadir la acción `seguimiento` a `ReportesController` y conectar la ruta; hacer pasar T059–T060; Pint

### Frontend (US6)

- [X] T062 [P] [US6] Crear `$F/hooks/useSeguimiento.ts` y `$F/components/TablaSeguimiento.tsx` (una fila por actividad con celdas de hito 30, 60 y 90 que muestran tasa de respuesta, promedio de aplicación y promedio de apoyo; badge «Alerta de reingreso» con `role` y texto, franja `role="alert"` con las actividades en alerta; «—» sin respuestas; estados loading/vacío/error) con `TablaSeguimiento.dom.test.tsx`
- [X] T063 [US6] Conectar la pestaña «Impacto 30-60-90» de `ReportesPage` (filtros comunes y paginación en la URL) y actualizar `ReportesPage.dom.test.tsx`

**Checkpoint**: impacto a 30-60-90 días visible con alertas

---

## Phase 9: User Story 7 - Registrar la evaluación de impacto de una acción (Priority: P2)

**Goal**: el administrador registra o edita la evaluación de impacto de una acción Finalizada, ve su ROI y el ROI estimado del dashboard refleja las evaluaciones.

**Independent Test**: registrar 5 000 USD de beneficio sobre una acción de 2 000 USD de costo y verificar ROI 150 %; editarla sin crear una segunda; una acción con costo cero muestra «no aplica»; el ROI estimado del dashboard cambia.

### Tests (US7)

- [X] T064 [P] [US7] Escribir `backend/tests/Unit/Desarrollo/Reportes/ReglasDeImpactoTest.php` (sin base de datos): acción Finalizada válida (aunque el plan esté Cerrado), acción no Finalizada rechazada, `beneficio_estimado ≥ 0` (−1 rechazado, 0 aceptado), campos obligatorios, longitudes máximas (`indicador` 150, `unidad` 50, `justificacion` 2 000), `valor_antes`/`valor_despues` numéricos
- [X] T065 [P] [US7] Escribir `backend/tests/Feature/Desarrollo/Reportes/ImpactoTest.php`: `PUT` crea (200 con `Impacto` y `roi`) y registra `creado_por`/`creado_en`; un segundo `PUT` actualiza la misma fila (una sola por acción) y registra `modificado_por`/`modificado_en` sin cambiar el creador; `roi` nulo con costo 0; 422 `validacion` (campos vacíos, beneficio negativo, valores no numéricos, textos demasiado largos); 422 `accion_no_finalizada`; 404 `accion_no_encontrada`; plan Cerrado permitido; `GET /impactos` lista acciones Finalizadas con `evaluada` y `roi`, filtros `plan_id` y `evaluado=con|sin`, paginación; el ROI estimado del dashboard cambia tras registrar; 403 para Colaborador; 401 sin sesión

### Implementation (US7)

- [X] T066 [US7] Crear `$D/Domain/Services/ReglasDeImpacto.php` (sin Laravel) con las validaciones de T064 y `$D/Application/UseCases/RegistrarImpacto.php` (verifica la acción y su estado Finalizada con `FuenteDePlanesInterface`, *upsert* transaccional por `plan_accion_id` con `created_by`/`updated_by` del usuario autenticado, devuelve el impacto con `CalculadorDeRoi::deAccion`) y `$D/Application/UseCases/ListarImpactos.php` (acciones Finalizadas con costo, evaluación, ROI y nombres de usuario de `NombresDeUsuarioInterface`)
- [X] T067 [US7] Crear `$D/Presentation/Requests/GuardarImpactoRequest.php` (`indicador` string requerido ≤ 150; `unidad` string requerido ≤ 50; `valor_antes` y `valor_despues` numéricos requeridos; `beneficio_estimado` numérico requerido ≥ 0 con 2 decimales; `justificacion` string requerido ≤ 2 000), `$D/Presentation/Resources/ImpactoResource.php` y `$D/Presentation/Controllers/ReportesImpactoController.php` con `index` y `update`; conectar las rutas; hacer pasar T064–T065; Pint

### Frontend (US7)

- [X] T068 [P] [US7] Añadir a `$F/validation.ts` el esquema Zod del impacto (todos obligatorios; números; beneficio ≥ 0; longitudes del backend) y la función de ROI de vista previa (`(beneficio − costo) / costo × 100`, «No aplica» con costo 0) con `validation.test.ts`; crear `$F/hooks/useImpactos.ts` y `$F/hooks/useRegistrarImpacto.ts` (sin reintentos; invalida `['reportes','impactos']` y `['reportes','dashboard']`)
- [X] T069 [P] [US7] Crear `$F/components/TablaImpactos.tsx` (acción, plan, costo, ROI, badge «Con evaluación» / «Sin evaluación», filtros por plan y por estado de evaluación en la URL, botón «Registrar impacto» o «Editar impacto») y `$F/components/FormularioImpactoModal.tsx` (sobre `Modal`; indicador, unidad, valor antes, valor después, beneficio estimado USD y justificación; ROI calculado visible; errores por campo desde 422; botón bloqueado mientras guarda; muestra creador y último modificador al editar) con `*.dom.test.tsx`
- [X] T070 [US7] Conectar la pestaña «Evaluación de impacto» de `ReportesPage` y actualizar `ReportesPage.dom.test.tsx` (registrar, editar, ROI, errores)

**Checkpoint**: evaluaciones de impacto registrables y reflejadas en el ROI del dashboard

---

## Phase 10: User Story 8 - Exportar a Excel y PDF (Priority: P3)

**Goal**: el administrador descarga el reporte con los filtros aplicados en Excel (cuatro hojas) y PDF (indicadores y tablas, sin gráficos).

**Independent Test**: aplicar un filtro por departamento, exportar ambos formatos y verificar que el contenido coincide con la pantalla y que el Excel tiene las cuatro hojas; un período sin datos exporta archivos válidos.

### Tests (US8)

- [X] T071 [P] [US8] Escribir `backend/tests/Feature/Desarrollo/Reportes/ExportarTest.php`: `formato=xlsx` devuelve 200 con `Content-Type` de hoja de cálculo, `Content-Disposition` con nombre `.xlsx`, abre como zip y contiene las hojas `Indicadores`, `Detalle`, `Impacto 30-60-90` y `Evaluación de impacto`; `formato=pdf` devuelve `application/pdf` que empieza con `%PDF`; ambos respetan los filtros (comparar filas con `detalle`); período sin datos exporta archivos válidos con tablas vacías; 422 `formato_invalido`; 403 para Colaborador; 401 sin sesión; las exportaciones no escriben auditoría (sin filas nuevas en ninguna tabla)

### Implementation (US8)

- [X] T072 [P] [US8] Crear los puertos `$D/Application/Ports/GeneradorDeExcelInterface.php` (`generar(array $hojas): string`, con `hojas` = lista de `{nombre, filas}`) y `GeneradorDeReportePdfInterface.php` (`generar(array $datos): string`), y los adaptadores `$D/Infrastructure/Adapters/ExcelConZip.php` (libro mínimo SpreadsheetML con `ZipArchive`: `[Content_Types].xml`, `_rels/.rels`, `xl/workbook.xml`, `xl/_rels/workbook.xml.rels`, `xl/styles.xml` y una hoja por entrada con cadenas en línea y números como numéricos; nombres de hoja ≤ 31 caracteres sin caracteres prohibidos) y `ReportePdfDompdf.php` (vista HTML con indicadores y tablas, sin gráficos, patrón de `CertificadoPdfDompdf`); registrar sus *bindings* en `DesarrolloServiceProvider`
- [X] T073 [US8] Crear `$D/Application/UseCases/ExportarReportes.php` (reutiliza los mismos casos de uso de dashboard, detalle completo sin paginar, seguimiento e impactos con los filtros recibidos; delega en el generador según el formato) y `$D/Presentation/Requests/ExportarReportesRequest.php` (`formato` ∈ `xlsx`|`pdf` más los filtros comunes); añadir la acción `exportar` a `ReportesController` con las cabeceras de descarga; conectar la ruta; hacer pasar T071; Pint

### Frontend (US8)

- [X] T074 [P] [US8] Crear `$F/hooks/useExportar.ts` (descarga con `Blob` por el cliente compartido, nombre del archivo desde `Content-Disposition`, estados cargando/éxito/error con `role="status"`) y `$F/components/BotonesExportar.tsx` («Exportar Excel» y «Exportar PDF» con los filtros activos, botones bloqueados mientras exportan) con `BotonesExportar.dom.test.tsx`; incluirlos en el encabezado de `ReportesPage` y actualizar su prueba

**Checkpoint**: todas las historias entregadas

---

## Phase 11: Polish & Cross-Cutting Concerns

**Purpose**: datos de prueba, E2E, regresión y puertas de calidad

- [X] T075 Crear `backend/database/seeders/ReportesSeeder.php` y registrarlo en `backend/database/seeders/DatabaseSeeder.php` **después** de `ActividadesSeeder`, `MicroLearningSeeder` y `DnfSeeder` (y sin depender de `IngresosSeeder`): sobre las actividades Finalizadas del seeder de Actividades, con distintos departamentos y tipos, encuestas de satisfacción dentro de la ventana de 30 días, seguimientos de los tres hitos (incluyendo un caso de alerta de reingreso: al menos 3 respuestas por hito con una caída de 1 punto o más), evaluaciones de impacto (una con costo cero) y, si el seeder de DNF no lo trae, un colaborador evaluado en dos DNF con una brecha cerrada escribiendo la segunda DNF por repositorio. Escribir `backend/tests/Feature/Desarrollo/Reportes/ReportesSeederTest.php` (idempotente y con los casos anteriores)
- [X] T076 Escribir `frontend/e2e/reportes.spec.ts` (Playwright, datos de `backend/.env.e2e`; pruebas seriales; documentar al inicio los datos sembrados como en `actividades.spec.ts`): el administrador abre `/reportes`, aplica el filtro de departamento, ve las tarjetas con comparación, abre los comentarios de una actividad (sin autor), registra una evaluación de impacto y ve su ROI, y descarga el Excel; el colaborador abre `/reportes`, ve «Mi progreso», responde una encuesta pendiente y la ve desaparecer
- [X] T077 [P] Revisar responsive (320, 768 y 1440 px sin scroll horizontal; tablas con desplazamiento interno), tema claro y oscuro, foco en modales, navegación por teclado de pestañas y formularios, `role="img"` + tabla accesible en cada gráfico, `role="status"`/`aria-live` al exportar y al enviar, y que la variación no dependa solo del color, contra `design/ReportesAdmin.dc.html` y `design/ReportesColaborador.dc.html`; corregir las desviaciones o registrarlas en plan.md y `design/DESIGN.md`
- [X] T078 [P] Medir el rendimiento de SC-001 con el volumen objetivo (1 000 actividades, 20 000 asistencias, 5 000 completaciones, 300 DNF) en una base de prueba; si el dashboard anual supera 5 s, aplicar `Cache::remember` con TTL corto por (tenant, filtros) solo en `ObtenerDashboard` (R7) y registrarlo en research.md
- [X] T079 Ejecutar las puertas de calidad: en `backend/` `vendor/bin/pest` (completo, incluyendo Plan, micro-learning, DNF y Actividades) y `vendor/bin/pint --test`; en `frontend/` `npm run lint`, `npx tsc -b`, `npx vitest run`, `npm run build` y `npx playwright test` (completo, porque `reportes`, `actividades`, `plan-formacion` y `micro-learning` comparten la base E2E). Comparar con los fallos preexistentes anotados en T001: no debe haber rojos nuevos
- [X] T080 Recorrer los escenarios de `specs/018-reportes-evaluacion/quickstart.md`, comprobar los criterios SC-001 a SC-009 de spec.md, confirmar que no se añadieron dependencias (`composer.json` y `package.json` sin cambios), confirmar que `DesarrolloFacade` no recibió ningún `@method` nuevo (FR-042) y marcar como hecho en este archivo; actualizar `design/DESIGN.md` si la revisión de T077 cambió algo visual

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Fase 1)**: sin dependencias
- **Foundational (Fase 2)**: depende de Setup; **bloquea todas las historias**
- **US1 (P1)**: depende de Foundational. Es el MVP: dashboard
- **US2 (P1)**: depende de Foundational; puede ir en paralelo a US1 (comparte el recolector, no los casos de uso)
- **US3 (P1)**: depende de Foundational; la verificación de extremo a extremo se beneficia de US4 (donde se muestra el formulario)
- **US4 (P1)**: depende de Foundational y de `CalculadorDeBrechasCerradas` (T030, de US1) y de `FormularioEncuesta` (US3) para el formulario de pendientes
- **US5 (P2)**: depende de Foundational y de US4 (se integra en «Pendientes por responder»)
- **US6 (P2)**: depende de Foundational y de US5 para probar de extremo a extremo (el backend se prueba con datos sembrados)
- **US7 (P2)**: depende de Foundational; el ROI del dashboard (US1) refleja sus datos al registrarse
- **US8 (P3)**: depende de US1, US2, US6 y US7 (exporta sus datos)
- **Polish (Fase 11)**: depende de las historias que se entreguen

### Within Each User Story

- Pruebas primero (deben fallar), luego dominio → repositorio → casos de uso → request/controlador/rutas → hooks → componentes → página
- Backend y frontend de una historia pueden avanzar en paralelo una vez fijado el contrato

### Parallel Opportunities

- Foundational: T004, T005, T006, T007 tras T003; T008–T013 (dominio puro) en paralelo; T017 y T022–T025 en paralelo
- US1: T027–T029 (pruebas) en paralelo; T035 y T036 (frontend) en paralelo
- US2 en paralelo con US1 tras Foundational; US3 en paralelo con US2
- US6 y US7 pueden ejecutarse en paralelo entre sí; T072 (adaptadores de exportación) puede adelantarse a US8

---

## Implementation Strategy

### MVP First

1. Fases 1 y 2 (Setup y Foundational).
2. US1 (dashboard) → **validar**: los indicadores coinciden con el cálculo manual y se comparan con el período anterior.
3. US2 (detalle), US3 (encuesta) y US4 (Mi progreso) → **P1 completo**: se puede demostrar el valor central para ambos roles.

### Incremental Delivery

1. + US5 (seguimiento del colaborador) → datos de reingreso.
2. + US6 (impacto 30-60-90 del administrador) → alertas.
3. + US7 (evaluación de impacto y ROI) → retorno de la inversión.
4. + US8 (exportación) → reportes compartibles.
5. Polish: seeder, E2E, rendimiento y puertas de calidad.

## Notes

- Cada tarea cita su archivo; los valores restringidos del modelo (longitudes, enumeraciones, rangos) están copiados de data-model.md y no se deciden al implementar
- Commit por tarea o por grupo lógico; detenerse en cada *checkpoint* para validar la historia por separado
- Encuestas y seguimientos son **inmutables**: ninguna tarea añade actualización ni borrado; las pruebas verifican que no existen PUT/DELETE
- Si se aprueba PhpSpreadsheet, solo cambia el adaptador de T072 tras `GeneradorDeExcelInterface`

## Notas de ejecución (2026-10-18)

- **Resultado**: 80/80 tareas. Backend: 1 813 pruebas Pest en total; las de Reportes (unitarias y de API, ~250) pasan y Pint queda limpio. Rojos restantes (119) solo en Estructura/Unidades, Estructura/Posiciones y Puestos, y GestionPersonal/Ingresos y Exportación (migración de posiciones sin confirmar y dompdf ausente): ninguno en Desarrollo. Frontend: ESLint limpio; Vitest 109/109 en reportes y shared/ui; tsc con solo los 18 errores preexistentes de estructura/unidades. E2E de Reportes 5/5 (flujo principal, colaborador, responsive y tema oscuro).
- **Ajustes frente al diseño**: endpoint nuevo GET /api/reportes/opciones (filtros); meta con last_page y fuentes_no_disponibles; gráficos con {disponible, datos}; Tabs con prop scrollable (4 pestañas no caben a 320 px); sin clases Resource (arrays directos); ReportesSeeder deja intactos los datos de Ana y añade una 2.ª DNF de Luis.
- **Rendimiento (SC-001)**: 4,40 s con 1 000 actividades, 20 000 asistencias, 5 200 completaciones y 300 DNF (RendimientoTest, solo con REPORTES_RENDIMIENTO=1): cumple pero con poco margen; si se degrada, aplicar la caché de R7.
- **Pruebas ajenas tocadas**: actividades.spec.ts espera ACT-0008 (el ACT-0007 lo siembra Reportes) y PlanSeederTest excluye las acciones [Rep].
- **E2E en esta máquina**: se corrieron con una configuración temporal sin IngresosSeeder (ya eliminada). Con 113 pruebas, 70 pasaron; fallaron 9 ajenas a Reportes (catalogo-formativo, competencias, comportamientos, puestos, unidades, ingresos, exportación y certificado de micro-learning), por dompdf/IngresosSeeder/datos de Estructura; no se comparó contra una corrida sin ReportesSeeder.
