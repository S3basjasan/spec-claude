# Implementation Plan: Reportes de evaluación de resultados

**Branch**: `018-reportes-evaluacion` | **Date**: 2026-10-17 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/018-reportes-evaluacion/spec.md`, con el insumo técnico `plan-reportes.md` (carpeta `specify y plan`) y los tableros `design/ReportesAdmin.dc.html` y `design/ReportesColaborador.dc.html` como referente visual primordial.

## Summary

Sexta feature de `Desarrollo` y la que **consolida** las anteriores: el Administrador ve un dashboard de indicadores (% Plan cumplido, Total horas, Satisfacción, Brechas cerradas, ROI estimado) comparados con el período anterior equivalente, indicadores de gestión, tres gráficos, una tabla detallada con comentarios de satisfacción anónimos, el impacto a 30-60-90 días con alerta de reingreso, la evaluación de impacto por acción con ROI, y exporta a Excel y PDF. El Colaborador ve **Mi progreso** (tarjetas, evolución de competencias, certificados, plan de desarrollo) y responde sus **pendientes** (encuesta de satisfacción y seguimientos 30-60-90).

Decisiones de arquitectura:

- **Métricas bajo demanda, sin tablas de indicadores.** Se calculan en `Domain/Services` puros (sin Laravel) a partir de datos que entregan puertos de lectura. Solo se persisten los tres datos que ninguna otra feature recoge: `encuestas_satisfaccion`, `seguimientos_impacto` e `impactos_accion`.
- **Reportes vive dentro del módulo `Desarrollo`**, igual que Actividades (017). Por eso **no usa `DesarrolloFacade`** (reservada a consumidores externos) sino **puertos nuevos** de `Application/Ports` con adaptadores sobre los repositorios existentes (`PlanRepositoryInterface`, `ActividadRepositoryInterface`, `ProgresoMicroRepositoryInterface`, `DnfRepositoryInterface`), como se resolvió en 017 (R1). Las fuentes de **otros módulos** (departamento actual del colaborador, departamentos, competencias, nombres) se consumen por puertos cuyos adaptadores llaman a `PersonalFacade` y `EstructuraFacade`.
- **Tres ampliaciones aditivas de solo lectura** a código existente (ver `contracts/reportes-puertos-y-facades.md`): `PlanRepositoryInterface::planesConAcciones` (suma nombre, competencias y participantes de cada acción), `DnfRepositoryInterface::evaluacionesDeCompetencias` (método nuevo: estado y niveles por competencia, colaborador y DNF) y `PersonalFacade::getDepartamentoActual` (método nuevo). Las actividades, asistencias y completaciones de micro-learning ya se leen con `ActividadRepositoryInterface::actividades/asistencias` y `ProgresoMicroRepositoryInterface::completaciones/progresosDe`, sin cambios. Esta feature **no expone Facade** a otros módulos (FR-042).
- **Excel sin dependencia nueva.** No hay librería de hojas de cálculo preaprobada; el `.xlsx` se genera con `ZipArchive` y XML (extensión `zip` ya presente), en un adaptador de Infraestructura aislado. El PDF usa `dompdf`, ya instalado y usado por `CertificadoPdfDompdf`.
- **Gráficos sin librería.** La constitución no define ninguna; los tableros dibujan barras, línea y pastel con HTML/CSS/SVG. Se hace igual, con una tabla de datos accesible por gráfico, sin añadir dependencias.

Decisiones adicionales del análisis de consistencia:

- **Sin fecha de activación**: encuestas y hitos se calculan para toda actividad Finalizada mientras su ventana siga abierta (30 días la encuesta, 15 días cada hito).
- **Brechas**: «cerradas» se cuenta por fecha de cierre y «detectadas» por primera aparición; las cerradas pueden superar a las detectadas y la tarjeta las muestra como dos cifras (no como fracción).
- **Fuentes caídas**: el recolector captura la falla de cada fuente y el dashboard devuelve `fuentes_no_disponibles`; los indicadores que dependen de ella se muestran como «No disponible» (FR-043).
- **Sin departamento**: los colaboradores sin departamento actual se agrupan como «Sin departamento» en el gráfico de horas y se excluyen al filtrar por un departamento.
- **Anonimato**: la fecha de un comentario de satisfacción se entrega solo con el día, sin hora.
- **Costo de micro-learning**: no existe; solo aporta horas.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript estricto + React 19 en frontend (versiones de `composer.json` / `package.json`)

**Primary Dependencies**: Laravel Sanctum, `dompdf/dompdf` (PDF, ya instalado), React Router, TanStack Query, Axios, React Hook Form, Zod, Tailwind CSS 4, Vitest, Testing Library, Playwright (ya instaladas y aprobadas). **Ninguna dependencia nueva** (Excel con `ZipArchive`; gráficos con SVG/CSS).

**Storage**: SQLite. Migraciones nuevas: `encuestas_satisfaccion`, `seguimientos_impacto`, `impactos_accion` (todas con `tenant_id`, igual que el resto de `Desarrollo`). Encuestas y seguimientos **inmutables**: el repositorio no ofrece actualizar ni borrar. Sin soft deletes ni `cascade`. Los seguimientos pendientes y todas las métricas **no se guardan**.

**Testing**: Pest (Unit y Feature); Vitest + Testing Library (jsdom); E2E con Playwright. Pint disponible; no hay análisis estático instalado y no se añade (Constitución IV).

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: dashboard del año completo en menos de 5 s (SC-001) con 1 000 actividades, 20 000 registros de asistencia, 5 000 completaciones y 300 DNF; sin caché en la primera versión (ver R7). Exportación con los mismos datos sin truncar filas.

**Constraints**: métricas calculadas bajo demanda; departamento **actual** del colaborador (no el histórico); costo y beneficio prorrateados por la proporción de asistentes del departamento; encuesta con ventana de 30 días y seguimientos con ventana de 15 días calculadas, no guardadas; comentarios sin nombre del autor y sin umbral mínimo; impacto editable aunque el plan esté Cerrado; el colaborador solo opera sobre sus datos; un usuario tiene un solo rol; todo texto visible en español y centralizado, WCAG 2.2 AA; sin soft deletes

**Scale/Scope**: cientos de actividades por año y decenas de participantes por actividad; 5 pantallas (dashboard con detalle y comentarios, impacto 30-60-90, evaluación de impacto, Mi progreso) y 10 operaciones HTTP; 3 tablas nuevas

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Casos de uso en `Desarrollo/Application/UseCases`; cálculos (período anterior, prorrateo, brechas cerradas, ROI, alerta de reingreso, ventanas) en `Domain/Services` sin Laravel; repositorio por contrato `ReportesRepositoryInterface`; controladores delgados sin consultas. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (patrón 000–017). Prefijo `/api/reportes` por contrato del insumo. Ver Complexity Tracking. |
| I. Un módulo no accede a tablas de otro módulo | PASA. Plan, Actividades, Micro-Learning y DNF viven en `Desarrollo` pero Reportes no lee sus tablas: usa puertos propios con adaptadores sobre sus repositorios. Personal y Estructura solo por puertos que llaman a sus Facades. `actividad_id`, `plan_accion_id` y `colaborador_id` son referencias lógicas sin FK. |
| I. Endpoints admin con Sanctum + `admin` | PASA. Todos exigen `auth:sanctum`; los del colaborador (`mi-progreso`, `encuestas`, `seguimientos`) usan solo el usuario del token. |
| I. Pest, Pint y análisis estático | PASA. Pest + Pint; no hay análisis estático instalado (se documenta, no se añade). |
| II. Frontend modular, tipado, accesible | PASA. Código en `frontend/src/modules/desarrollo/reportes/`; Axios solo en el cliente compartido; TanStack Query sin reintentos en escrituras; filtros y paginación en la URL; sin `any`. |
| II. Estados, formularios, confirmaciones | PASA. Cada vista de datos cubre loading, empty, error con «Reintentar» y success; los formularios muestran errores por campo y bloquean envíos pendientes. Encuestas y seguimientos son irreversibles: se confirman con `ConfirmDialog` antes de enviar. |
| III. Diseño como fuente visual | PASA con diferencias registradas (tabla abajo, manda la spec). No se añade código a `design/`. |
| IV. Calidad, seguridad, dependencias | PASA. Sin dependencias nuevas; entradas validadas en `FormRequest` y dominio; errores públicos seguros; el autor de un comentario nunca viaja en la respuesta del administrador. |

## Project Structure

### Documentation (this feature)

```text
specs/018-reportes-evaluacion/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   ├── reportes-api.md
│   └── reportes-puertos-y-facades.md
├── checklists/requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks — no se crea aquí)
```

### Source Code (repository root)

```text
backend/
├── database/
│   ├── migrations/2026_10_22_00000{1,2,3}_create_{encuestas_satisfaccion,seguimientos_impacto,impactos_accion}_table.php
│   └── seeders/ReportesSeeder.php               # registrado en DatabaseSeeder tras ActividadesSeeder (y MicroLearningSeeder / DnfSeeder)
├── app/Modules/Desarrollo/
│   ├── Domain/
│   │   ├── ValueObjects/                        # PeriodoReporte, FiltroReportes, HitoSeguimiento, Roi, VariacionIndicador, BrechaDeCompetencia
│   │   ├── Services/                            # CalculadorDePeriodo, ProrrateadorPorDepartamento, CalculadorDeIndicadores, CalculadorDeBrechasCerradas,
│   │   │                                        # CalculadorDeSeguimiento, CalculadorDeRoi, ReglasDeEncuesta, ReglasDeSeguimiento, ReglasDeImpacto
│   │   ├── Exceptions/                          # EncuestaNoPermitida, SeguimientoNoPermitido, ImpactoNoPermitido, PeriodoInvalido, …
│   │   └── Repositories/ReportesRepositoryInterface.php
│   ├── Application/
│   │   ├── Ports/                               # FuenteDePlanesInterface, FuenteDeActividadesInterface, FuenteDeMicrolearningInterface,
│   │   │                                        # FuenteDeDnfInterface, DepartamentoDeColaboradorInterface, GeneradorDeExcelInterface, GeneradorDeReportePdfInterface
│   │   ├── Services/RecolectorDeReportes.php    # arma el conjunto de datos filtrado que consumen dashboard, detalle, seguimiento y exportación
│   │   └── UseCases/                            # (carpeta plana, como Actividades) ObtenerDashboard, ListarDetalle, ListarComentariosDeSatisfaccion, ListarSeguimiento, ListarImpactos,
│   │                                            # RegistrarImpacto, ExportarReportes, ObtenerMiProgreso, ResponderEncuesta, ResponderSeguimiento
│   ├── Infrastructure/
│   │   ├── Adapters/                            # PlanesParaReportes, ActividadesParaReportes, MicrolearningParaReportes, DnfParaReportes,
│   │   │                                        # DepartamentoDeColaboradorDesdePersonal, ExcelConZip, ReportePdfDompdf
│   │   ├── Persistence/Eloquent/Models/         # EncuestaSatisfaccionModel, SeguimientoImpactoModel, ImpactoAccionModel
│   │   ├── Persistence/Eloquent/Repositories/EloquentReportesRepository.php
│   │   └── Providers/DesarrolloServiceProvider.php   # bindings nuevos
│   ├── Presentation/
│   │   ├── Controllers/ReportesController.php, ReportesImpactoController.php, MiProgresoController.php
│   │   ├── Requests/                            # FiltrosReportesRequest, GuardarImpactoRequest, ResponderEncuestaRequest, ResponderSeguimientoRequest, ExportarReportesRequest
│   │   └── Resources/                           # ComentarioSatisfaccionResource, ImpactoResource
│   └── Routes/api.php                           # rutas /api/reportes
├── app/Modules/GestionPersonal/Presentation/Facades/PersonalFacade.php   # + getDepartamentoActual (aditivo)
└── tests/{Unit,Feature}/Desarrollo/Reportes/    # Pest

frontend/
├── src/modules/desarrollo/reportes/
│   ├── api.ts  text.ts  validation.ts  nav.tsx  formato.ts  estilosEstado.ts
│   ├── hooks/        # useDashboard, useDetalle, useComentarios, useSeguimiento, useImpactos, useRegistrarImpacto,
│   │                 # useMiProgreso, useResponderEncuesta, useResponderSeguimiento, useExportar, useFiltrosReportes, claves.ts
│   ├── components/   # BarraDeFiltros, TarjetaIndicador, FilaIndicadoresGestion, GraficoBarrasHoras, GraficoLineaBrechas, GraficoPastelTipos,
│   │                 # TablaDetalle, ComentariosDialog, TablaSeguimiento, TablaImpactos, FormularioImpactoModal, BotonesExportar,
│   │                 # TarjetasProgreso, GraficoEvolucionCompetencias, ListaCertificados, PlanDeDesarrollo, PendientesPorResponder,
│   │                 # FormularioEncuesta, FormularioSeguimiento, RequireRole.tsx
│   └── pages/        # ReportesPage (admin: pestañas), MiProgresoPage
├── src/App.tsx                                  # rutas y navItems
└── e2e/reportes.spec.ts
```

**Structure Decision**: aplicación web con el módulo existente `Desarrollo` (backend) y `desarrollo` (frontend). Se crea la funcionalidad `reportes/` junto a `actividades/`, con las mismas capas; no se crean carpetas vacías.

## Diferencias entre tableros y spec (manda la spec)

| Tablero | Diferencia | Resolución |
|---|---|---|
| ReportesAdmin | Botón «Ver como colaborador». | **Se omite**: cada persona tiene un solo rol. |
| ReportesAdmin | Los indicadores se comparan con una **meta** y con **2025**, y con filtros activos la comparación se oculta. | La spec compara contra el **período anterior equivalente** (FR-008) y la comparación **se mantiene con filtros activos** (se recalcula con los mismos filtros). Se conserva la tarjeta, el valor, la diferencia coloreada y el aviso «Un indicador sin comparación es solo un dato». Se omite la meta. |
| ReportesAdmin | Filtros: Desde, Hasta, Departamento, Tipo (sin Micro-learning). Sin filtro de plan. | Se añaden **Plan** y el tipo **Micro-learning** (FR-003/004). Por defecto el año actual. |
| ReportesAdmin | Línea «Evolución de brechas cerradas (acumulado)» real frente a **meta anual**. | La spec pide por mes las **brechas activas al cierre** y las **cerradas en el mes** (FR-017): dos series, sin meta. |
| ReportesAdmin | Gráfico de barras y pastel solo con seis tipos y horas ejecutadas por avance. | La barra usa horas-persona de actividades **más** micro-learning por departamento; el pastel tiene **siete tipos**, incluido Micro-learning (FR-016/018). Las horas de la acción se cuentan con asistencia Presente, no por avance. |
| ReportesAdmin | Tabla «Detalle por acción formativa» con Brechas cerradas, ROI y Estado; sin código, asistentes ni calificación; sin paginación; sin comentarios. | Columnas de FR-019 (código, nombre, tipo, fechas, departamento, asistentes/inscritos, horas, costo, calificación y satisfacción), **paginación**, y botón por fila que abre los **comentarios**. «Brechas cerradas» por acción se omite (el indicador es global); el ROI de la acción se muestra en la sección de evaluación de impacto. |
| ReportesAdmin | Sección «Impacto a 30, 60 y 90 días»: porcentaje de aplicación por hito, «Brecha antes → después», cobertura del equipo y **riesgo alto/medio/bajo** según la caída 30→90 (15 y 5 puntos). | La spec define por hito **tasa de respuesta, promedio de aplicación y promedio de apoyo**, y una **alerta de reingreso binaria** (caída ≥ 1 punto con ≥ 3 respuestas, FR-028). Se conserva la estructura de tabla con una celda por hito y la franja `role="alert"`, y el badge de alerta; se omiten «Brecha antes → después» y los niveles de riesgo. |
| ReportesAdmin | No existe la lista de evaluaciones de impacto ni el formulario. | Se añade la sección **Evaluación de impacto** (lista con «Con/Sin evaluación», filtros, «Registrar/Editar impacto» y modal de formulario con el ROI calculado) con los componentes del design system (Modal, TextField, TextareaField, Badge). |
| ReportesAdmin | Aviso verde de estado tras exportar. | Se conserva el `Alert` de estado; la exportación descarga el archivo con el cliente compartido. |
| ReportesColaborador | Tarjetas, evolución con niveles A–D y marca del nivel requerido, certificados y plan. Sin «Pendientes por responder». | Se añade la sección **Pendientes por responder** con encuestas y seguimientos y sus formularios breves (FR-036). La evolución compara la **primera DNF** con la **más reciente** (FR-034), no «enero frente a hoy»; con una sola DNF muestra solo el nivel actual. |

Estas diferencias se registran aquí (Constitución III) y no obligan a modificar `design/`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Prefijo `/api/reportes` en lugar de `/api/desarrollo/reportes` | Contrato del insumo y consistencia con `/api/planes`, `/api/dnf`, `/api/actividades` (000–017) | Cambiar solo esta feature rompería la uniformidad del módulo. |
| Puertos de lectura propios aunque Plan, Actividades, Micro y DNF están en el mismo módulo | Reportes no debe depender de las tablas ni entidades internas de esas funcionalidades, y la regla de 017 prohíbe que un módulo use su propia Facade pública | Leer sus tablas desde `EloquentReportesRepository` acoplaría esquemas; usar `DesarrolloFacade` desde el mismo módulo contradice su propósito. |
| Generador de `.xlsx` propio con `ZipArchive` | No hay librería de hojas de cálculo preaprobada y la constitución exige aprobación para cualquier otra | Añadir PhpSpreadsheet requiere aprobación explícita; si se aprueba, solo cambia el adaptador `GeneradorDeExcelInterface`. |

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/reportes` | Dashboard de indicadores, Tabla detallada, Impacto 30-60-90 y Evaluación de impacto (pestañas en `?vista=indicadores\|detalle\|seguimiento\|impactos`); Mi progreso (colaborador) | Administrador y Colaborador (la vista la elige el rol; cada uno ve solo la suya) |

Los comentarios de satisfacción y el formulario de evaluación de impacto son diálogos (`Modal`) sobre `/reportes`.

### Componentes
- **Nuevos**: los listados en la estructura de arriba (tarjetas de indicador, gráficos SVG/CSS, tablas, diálogos y formularios de la feature).
- **Del design system**: `Tabs`, `Modal`, `ConfirmDialog`, `Alert`, `Badge`, `Button`, `TextField`, `TextareaField`, `SelectField`, `SearchField`, `Pagination`, `Skeleton`, `EmptyState`, `ErrorState` y los tokens de `design/DESIGN.md`.

### Datos y estado
- Endpoints por pantalla (ref. `contracts/reportes-api.md`): dashboard → `GET /api/reportes/dashboard`; detalle → `GET /api/reportes/detalle` y `GET /api/reportes/actividades/{id}/satisfaccion`; seguimiento → `GET /api/reportes/seguimiento`; impactos → `GET /api/reportes/impactos` y `PUT /api/reportes/impactos/{planAccionId}`; exportar → `GET /api/reportes/exportar`; Mi progreso → `GET /api/reportes/mi-progreso`, `POST /api/reportes/encuestas`, `POST /api/reportes/seguimientos` y, para certificados, el endpoint de Micro-Learning.
- Caché e invalidación: `claves.ts` agrupa las consultas bajo `['reportes', …]`. Registrar impacto invalida `impactos` y `dashboard` (ROI) y `seguimiento` no. Responder encuesta invalida `mi-progreso`; responder seguimiento invalida `mi-progreso`. Las escrituras no se reintentan.
- Estado en URL: `fecha_desde`, `fecha_hasta`, `departamento_id`, `tipo`, `plan_id`, `vista`, `page`, `q`, `evaluado`.

### Validación
- Encuesta: calificación entera de 1 a 5 obligatoria, comentario opcional (límite de longitud del backend).
- Seguimiento: `aplicacion` y `apoyo` enteros de 1 a 5 obligatorios, comentario opcional.
- Impacto: todos los campos obligatorios; valores numéricos; beneficio estimado mayor o igual a 0 en USD.
- Mapeo de errores 422 a campos por el nombre del campo; los de regla de negocio (`code`) se muestran como aviso del formulario.

## Post-design Constitution Re-check

Tras el diseño (data-model, contratos y quickstart) todas las puertas siguen en PASA: no se añadieron dependencias, los controladores no consultan la base de datos, el colaborador solo opera sobre sus datos, el administrador no recibe el autor de los comentarios y las tres desviaciones están justificadas arriba.
