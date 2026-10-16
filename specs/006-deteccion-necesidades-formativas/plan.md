# Implementation Plan: Detección de necesidades formativas (DNF)

**Branch**: `006-deteccion-necesidades-formativas` | **Date**: 2026-10-15 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification de `/specs/006-deteccion-necesidades-formativas/spec.md` y plan de partida `plan-dnf.md` (carpeta externa «specify y plan»).

## Summary

La DNF compara, por colaborador, el grado que exige su puesto contra el grado actual por competencia y guarda un diagnóstico **inmutable** con diferencia, brecha %, prioridad y estado. Se implementa dentro del módulo `Desarrollo` (ya existente por la feature 005) con Clean Architecture: un servicio de dominio puro calcula la brecha; los casos de uso obtienen perfiles, colaboradores y niveles a través de **puertos propios** con adaptadores que llaman a las Facades de Estructura y Gestión de Personal (patrón ya usado en 005); la persistencia es append-only (tres tablas, sin UPDATE/DELETE salvo la asignación única del plan). El frontend añade, en `frontend/src/modules/desarrollo/dnf/`, el asistente de dos pasos y el historial del administrador y la vista del colaborador, **siguiendo los tableros `design/DNFAdmin.dc.html` y `design/DNFColaborador.dc.html`**.

Hallazgo que condiciona el alcance (ver [research.md](research.md) R-01): Gestión de Personal aún no tiene puesto, vínculo ni nivel por competencia del colaborador, y no existe el módulo Plan de Formación. Por eso esas tres entradas entran por puertos con adaptadores provisionales y los contratos de ampliación de las Facades quedan documentados en [contracts/dnf-puertos-y-facades.md](contracts/dnf-puertos-y-facades.md).

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 13 (backend); TypeScript estricto, React, Vite, Tailwind CSS 4 (frontend).

**Primary Dependencies**: las ya instaladas (Sanctum, Scramble, Pest, Pint; React Router, TanStack Query, Axios, React Hook Form, Zod, Vitest, Testing Library). **Sin dependencias nuevas.** El gráfico de radar/barras se dibuja con SVG propio (el tablero ya lo hace así), sin librería de gráficos.

**Storage**: SQLite (predeterminado). Tablas nuevas `dnf`, `dnf_colaboradores`, `dnf_detalles`.

**Testing**: Pest (Unit del calculador y Feature de endpoints/inmutabilidad/permisos), Vitest + Testing Library (componentes y hooks) y un E2E del flujo principal del administrador y del colaborador, con la herramienta E2E que ya use el repositorio (no se añade otra).

**Target Platform**: SPA web 320–1440 px, temas claro/oscuro, API REST bajo `/api/dnf`.

**Project Type**: aplicación web (backend modular + frontend modular).

**Performance Goals**: SC-001 individual < 10 s; SC-002 masiva de 100 colaboradores < 60 s (una transacción, consultas por lote a las Facades, sin N+1).

**Constraints**: DNF inmutable; sin FK entre módulos; el controlador no consulta BD; ningún módulo toca tablas ajenas; textos en español centralizados; sin `any`.

**Scale/Scope**: 3 pantallas (asistente, historial/detalle, mi diagnóstico), 5 endpoints, 3 tablas.

## Constitution Check

| Principio | Cumplimiento | Estado |
|---|---|---|
| I. Backend modular / Clean Architecture | Todo en `Modules/Desarrollo` (Domain/Application/Infrastructure/Presentation). Cálculo en `Domain/Services`, casos de uso explícitos, repositorio por interfaz, controladores delgados, Requests para validar, Resources para responder. Integraciones solo por Facades de otros módulos, tras puertos. Rutas bajo `/api/dnf` (el prefijo `/api/<modulo>/` del texto constitucional se concreta aquí por contrato de la feature; 005 hizo lo mismo con `/api/catalogo-formativo`). | Pasa |
| II. Frontend modular, accesible, tipado | `frontend/src/modules/desarrollo/dnf/` con `api.ts`, `hooks/`, `components/`, `pages/`, `text.ts`, `validation.ts`. Axios solo en el cliente compartido; TanStack Query sin reintento de escrituras; filtros en la URL; loading/empty/error/success en toda vista; ConfirmDialog accesible para la masiva; WCAG 2.2 AA (las barras llevan texto alternativo y no dependen solo del color). | Pasa |
| III. Diseño como fuente visual | Se usan `design/DNFAdmin.dc.html` y `design/DNFColaborador.dc.html`. Quedan **desviaciones/ampliaciones a registrar primero en `design/DESIGN.md`** (tabla en «Diseño de frontend»). | Pasa con tarea previa |
| IV. Calidad, seguridad, dependencias | Pruebas por nivel; sin dependencias nuevas; entradas validadas; 401/403/404/422 seguros; auditoría de autor y fecha; colaborador solo ve lo propio con id tomado del token. | Pasa |

Re-evaluación tras el diseño (Fase 1): sin violaciones nuevas. Las dos decisiones que se apartan del documento de partida (columnas de auditoría del plan y `tenant_id`) están justificadas en la sección siguiente, no son excepciones a la constitución.

### Desviaciones respecto a `plan-dnf.md`

| Punto | Documento de partida | Este plan | Motivo |
|---|---|---|---|
| Multi-tenant | La tabla `dnf` no tiene tenant | `dnf.tenant_id` obligatorio; el código DNF-NNNN es único **por tenant**; toda consulta filtra por el tenant del token | Los usuarios, colaboradores y cuentas ya llevan `tenant_id` (migraciones 2026_10_15); sin él una DNF filtraría datos entre tenants (Constitución IV). |
| Auditoría del plan | Solo `plan_formacion_id` | Se añaden `plan_generado_por` y `plan_generado_en` | FR-030 exige quién y cuándo generó el borrador. |
| Fecha de generación | No se nombra | Es `created_at` | FR-003. |
| «Mi diagnóstico» y puertos existentes | Facade `brechasActivas(colaboradorId)` | Se mantiene, y además un adaptador reemplaza `BrechasActivasSinDatos` traduciendo `usuarioId → colaboradorId` | El puerto de 005 se identifica por usuario; ver contracts. |
| Ruta de «Ver cursos sugeridos» | «competencia en la ruta» | `/formacion?competencia=:id` | Es el contrato ya publicado por 005 (`desarrollo-puertos.md`). |

## Project Structure

### Documentation (this feature)

```text
specs/006-deteccion-necesidades-formativas/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── dnf-api.md
│   └── dnf-puertos-y-facades.md
└── tasks.md            # lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Desarrollo/
│   ├── Domain/
│   │   ├── ValueObjects/    # NivelGrado (existe), PrioridadBrecha, EstadoBrecha, AlcanceDnf, TipoCompetencia
│   │   ├── Services/        # CalculadorDeBrecha (único lugar con la escala D=1…A=4)
│   │   ├── Entities/        # Dnf, ResultadoColaborador, Brecha (solo lectura tras crearse)
│   │   ├── Repositories/    # DnfRepositoryInterface
│   │   └── Exceptions/      # DnfNoEncontrada, SinColaboradoresEvaluables, DnfSinBrechas, PlanYaGenerado, ...
│   ├── Application/
│   │   ├── Ports/           # PerfilesDePuestoInterface, ColaboradoresEvaluablesInterface,
│   │   │                    # NivelesActualesInterface, PlanDeFormacionInterface (+ BrechasActivasInterface existente)
│   │   ├── DTO/             # DnfDTO, FilaHistorialDnfDTO, ResultadoColaboradorDTO, BrechaDTO, MiDiagnosticoDTO, AvisosDTO
│   │   └── UseCases/        # GenerarDnf, ListarDnf, ObtenerDnf, ObtenerMiDiagnostico, GenerarPlanDesdeDnf
│   ├── Infrastructure/
│   │   ├── Adapters/        # PerfilesDePuestoDesdeEstructura, CuentaColaboradorDesdePersonal, NombresDeReferenciaDesdeFacades (definitivos);
│   │   │                    # ColaboradoresEvaluablesSinDatos, NivelesActualesSinDatos, PlanDeFormacionNoDisponible (provisionales);
│   │   │                    # Pruebas/ (adaptadores solo para el entorno e2e); BrechasActivasDesdeDnf (reemplaza BrechasActivasSinDatos)
│   │   ├── Persistence/Eloquent/{Models,Repositories}/
│   │   └── Providers/DesarrolloServiceProvider.php   # nuevos bindings
│   ├── Presentation/
│   │   ├── Controllers/DnfController.php
│   │   ├── Requests/{GenerarDnfRequest,ListarDnfRequest}.php
│   │   ├── Resources/
│   │   └── Facades/DesarrolloFacade.php   # + brechasActivas(tenantId, colaboradorId), getDnfRegistradas(filtros)
│   └── Routes/api.php                      # rutas /api/dnf
├── database/migrations/2026_10_15_00001x_create_dnf*.php
├── database/seeders/DnfSeeder.php
└── tests/{Unit,Feature}/Desarrollo/Dnf*

frontend/src/modules/desarrollo/dnf/
├── api.ts  text.ts  validation.ts  nav.tsx
├── hooks/        # useGenerarDnf, useHistorialDnf, useDetalleDnf, useMiDiagnostico, useGenerarPlan, useDnfParams
├── components/   # PasoSeleccion, TablaDiagnostico, GraficoPerfil, ResumenPorColaborador, AvisosDnf,
│                 # BadgePrioridad, BadgeEstado, TarjetaBrecha, BrechasPrioritarias, BotonGenerarPlan
└── pages/        # DnfPage (despacha por rol), DnfAdminPage, DnfHistorialPage, DnfDetallePage, MiDiagnosticoPage
```

**Structure Decision**: aplicación web con módulos espejo `Desarrollo` en backend y frontend, igual que 005. La funcionalidad se aísla en la subcarpeta `dnf/` del frontend (como `catalogo-formativo/`).

## Complexity Tracking

Sin violaciones de la constitución que justificar. Riesgo de alcance registrado, no de complejidad: ver R-01 en [research.md](research.md).

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/dnf` | Admin: Asistente de generación de DNF · Colaborador: Mi diagnóstico (se despacha por rol con `RequireRole`, como `/formacion`) | Administrador y Colaborador |
| `/dnf/historial` | Historial de DNF | Administrador |
| `/dnf/:id` | Detalle de una DNF (solo lectura; admite `?colaborador=` en masivas) | Administrador |

Navegación lateral: una entrada «DNF» (icono propio de `shared/ui/icons`) visible para ambos roles; el historial se alcanza desde el asistente. Los tableros traen además un botón «Ver como colaborador/administrador» y una barra de «Datos de solo lectura» que son **controles de prototipo**: no se implementan (cada persona tiene un solo rol, igual que en 005).

### Componentes
- **Nuevos** (no existe equivalente en `shared/ui`): `Stepper` de 2 pasos (si 005/otras features no lo introdujeron, nace en `shared/ui` porque lo podrán reutilizar), `BadgePrioridad` y `BadgeEstado` (envoltorios de `Badge`), `GraficoPerfil` (SVG barras actual + línea requerido), `TarjetaBrecha` con barras comparativas A–D, `BrechasPrioritarias`, `ResumenPorColaborador`, `AvisosDnf`, `BotonGenerarPlan`.
- **Del design system**: `AppShell`, `Button`, `Alert`, `Badge`, `Combobox` (selector de colaborador con búsqueda), `SelectField` (departamento, filtros), `SearchField`, `Modal`/`ConfirmDialog` (confirmación masiva), `Pagination`, `Skeleton`, `EmptyState`, `ErrorState`, `Breadcrumb`, y el patrón Listado para el historial.

**Registro de desviaciones del diseño (Constitución III) — ya incorporado a `design/DESIGN.md`: tokens semánticos (§2), componentes de la DNF (§4), patrones 13–15 (§5) y diferencias (§9):**

| Tablero | Qué no cubre el sistema de diseño actual | Resolución |
|---|---|---|
| `DNFAdmin.dc.html` | Stepper de pasos; tarjetas KPI del resultado; gráfico perfil ideal vs actual; selector de alcance (opciones tipo radio-tarjeta); vista previa del colaborador (puesto, departamento, nº competencias, última evaluación de desempeño) | Añadir al DESIGN.md. La «última evaluación de desempeño» **no existe en ninguna fuente de datos de la spec**: se omite de la vista previa hasta que exista (si no, mostraría «No registrado»). |
| `DNFAdmin.dc.html` | No hay pantalla de historial ni de detalle | Historial = patrón Listado (`Competencias.dc.html`) + filtros fecha/colaborador/departamento/prioridad; detalle = tabla del paso 2 en solo lectura. Se registra en §9. |
| `DNFColaborador.dc.html` | Tarjeta de brecha con dos barras, bloque «Mis brechas prioritarias» numerado, lista «Competencias que ya cumples» y mensaje «Aún no hay cursos asociados» | Añadir al DESIGN.md. El aviso «Avisaremos a Desarrollo y Formación» **no tiene función en la spec**: se muestra solo «Aún no hay cursos asociados en el catálogo» (sin prometer una notificación). |
| Ambos | Los tableros calculan la **prioridad por porcentaje** (Crítica ≥ 66 %, Alta ≥ 50 %…) y lo explican en una nota | **Manda la spec** (FR-010): prioridad por grados y tipo de competencia. Los tableros son solo referencia visual y sus cifras son de ejemplo; el cálculo es del backend. Registrado en DESIGN.md §9. |
| Ambos | Menú lateral del tablero (Perfilamiento, Empleados, Ajustes, Mi perfil) | Se usa el `AppShell` real con la navegación declarativa existente. |

### Datos y estado
- Admin generar: `POST /api/dnf` (mutación sin reintentos) → navega al paso 2 con la respuesta; para masivas el resumen sale de la misma respuesta y se abre un colaborador con `GET /api/dnf/{id}?colaborador_id=`.
- Historial: `GET /api/dnf` con `fecha_desde, fecha_hasta, colaborador_id, departamento_id, prioridad, page, per_page` **en la URL**; la caché `['dnf','lista', params]` se invalida tras `POST /api/dnf`.
- Detalle: `GET /api/dnf/{id}`; tras `POST /api/dnf/{id}/plan` se invalidan detalle y lista (cambia el botón y aparece el enlace).
- Colaborador: `GET /api/dnf/mi-diagnostico`; «Ver cursos sugeridos» navega a `/formacion?competencia=:id`.
- Selectores del paso 1 (colaboradores evaluables y departamentos): endpoint de apoyo `GET /api/dnf/opciones` (ver contracts).
- Filtros de la tabla del paso 2 (prioridad, estado, «solo con brecha») y la selección de colaborador son estado de pantalla sobre datos ya cargados.

### Validación
- Paso 1: alcance obligatorio; colaborador obligatorio si Individual; departamento obligatorio si Departamento (Zod espejo de `GenerarDnfRequest`).
- Errores 422 se mapean al campo (`colaborador_id`, `departamento_id`) o, si son de negocio («ningún colaborador evaluable»), a un `Alert` sobre el botón. Se bloquea el envío mientras la mutación está pendiente.

## Decisiones cerradas tras el análisis de consistencia

| Tema | Decisión |
|---|---|
| Cambio en otro módulo | Se añade `PersonalFacade::getCuentaDeUsuario(int $usuarioId): ?array` (`{tenant_id, colaborador_id}`), aditivo y de solo lectura sobre `colaborador_cuentas`; es necesario para «Mi diagnóstico» y para `BrechasActivasDesdeDnf` (no depende de GP-02). |
| Firma de la Facade | `DesarrolloFacade::brechasActivas(int $tenantId, int $colaboradorId)` con tenant explícito. |
| Detalle por colaborador en US3 | `ObtenerDnf` y `GET /api/dnf/{id}` (con `?colaborador_id=`) se construyen en la historia de la DNF masiva, porque la respuesta `201` de una masiva no trae el detalle de brechas; la historia del historial los reutiliza. |
| E2E del flujo principal | Un conjunto de adaptadores de prueba (`Infrastructure/Adapters/Pruebas/`) se registra **solo** cuando `app()->environment('e2e')`, para que el E2E genere una DNF real y un borrador de plan sin GP-02 ni Plan de Formación. En producción y desarrollo nunca se enlazan. |
| Filtros del diagnóstico | Prioridad, estado y «solo con brecha» se persisten en la URL del detalle (`/dnf/:id`), conforme a la constitución. En el paso 2 del asistente (pantalla transitoria sin URL propia) son estado local. |
| «Departamento» | Cualquier unidad organizacional activa de Estructura, con sus subunidades. |
| Filtro de prioridad del historial | Coincide con la prioridad más alta de la DNF (FR-021). |
