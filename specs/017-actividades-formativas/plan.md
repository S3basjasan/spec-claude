# Implementation Plan: Actividades formativas

**Branch**: `017-actividades-formativas` | **Date**: 2026-10-17 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/017-actividades-formativas/spec.md`, con el insumo técnico `plan-actividades.md` (carpeta `specify y plan`) y los tableros `design/ActividadesAdmin.dc.html`, `design/ActividadesAsistencia.dc.html`, `design/ActividadesCalificaciones.dc.html` y `design/ActividadesColaborador.dc.html` como referente visual primordial.

## Summary

Ejecución de las acciones del Plan de Formación anual: el Administrador crea una **actividad** a partir de una acción no cancelada de un plan
Ejecutando (lugar o enlace + departamento opcional), registra **asistencia** (Presente / Ausente / Justificado), **calificación 1–5**
y **observaciones internas** de los inscritos, y consulta **calificaciones agregadas** por actividad y por participante. El
Colaborador ve **sus actividades** (próximas e historial) con su asistencia y calificación, nunca las observaciones.

Es la quinta feature de `Desarrollo` y se apoya en lo ya construido:

- El **Plan de Formación (013)** ya expone `PlanRepositoryInterface`, `plan_acciones` y `plan_accion_participantes`. Este plan **no
  modifica** esas tablas; añade tres tablas propias (`actividades`, `actividad_asistencias`, `actividad_asistencia_historial`) y
  lee el Plan por un **puerto nuevo del propio módulo** (`AccionesDePlanInterface`), de modo que los datos heredados salen siempre del
  plan vigente (aclaración 4) y no se duplican.
- El Plan ya definió el puerto `AsistenciaInterface` con un adaptador **provisional** (`AsistenciaSinDatos`) que devuelve `[]`.
  Esta feature entrega el adaptador definitivo (`AsistenciaDesdeActividades`) y cambia el *binding* en `DesarrolloServiceProvider`; el
  progreso del colaborador (`ObtenerMiPlan`) empieza a mostrar acciones completadas sin tocar ese caso de uso. También añade el
  puerto `AsistenciaRegistradaInterface` para que `CambiarParticipantes` rechace quitar a un participante con asistencia registrada.
- Las competencias, los departamentos (unidades de Estructura) y los colaboradores se obtienen **solo por puertos del módulo**
  cuyos adaptadores llaman a las Facades de Estructura y Gestión de Personal (ya existen `CompetenciasInterface` y
  `NombresDeReferenciaInterface`; solo se añade lectura de unidades activas).
- `DesarrolloFacade` publica métodos de solo lectura para Reportes (asistencias, calificaciones, horas y actividades).

Las pantallas siguen los cuatro tableros sobre `design/DESIGN.md`; las diferencias entre tableros y spec (manda la spec) están en
"Diferencias entre tableros y spec".

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript estricto + React 19 en frontend (versiones de `composer.json` /
`package.json`)

**Primary Dependencies**: Laravel Sanctum, React Router, TanStack Query, Axios, React Hook Form, Zod, Tailwind CSS 4, Vitest,
Testing Library, Playwright (ya instaladas y aprobadas). **Ninguna dependencia nueva.**

**Storage**: SQLite. Migraciones nuevas: `actividades`, `actividad_asistencias`, `actividad_asistencia_historial`. Sin soft deletes ni
`cascade`; **no existe DELETE** de actividades ni de historial (FR-009). La única eliminación física es la fila de
`actividad_asistencias` al volver un participante a Pendiente (sin fila = Pendiente); el cambio queda en el historial.

**Testing**: Pest (Unit y Feature); Vitest + Testing Library (jsdom); E2E con Playwright. Pint disponible; no hay análisis estático
instalado y no se añade (Constitución IV).

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: lista del Administrador y vista del colaborador en menos de 2 s con 1 000 actividades y 20 000 registros de
asistencia; guardar la asistencia de 30 participantes en menos de 2 s (SC-001/002 son de flujo humano, no de latencia)

**Constraints**: datos de la acción siempre del plan vigente (no se copian); estado y «Asistencia pendiente» calculados, no
persistidos; guardado de asistencia atómico (todo o nada); observaciones solo con asistencia registrada y nunca visibles al
colaborador; el colaborador solo opera sobre sus propios datos; actividades y registros nunca se eliminan; plan Cerrado ⇒ solo
lectura; un usuario tiene un solo rol; todo texto visible en español y centralizado, WCAG 2.2 AA; multi-tenant por `tenant_id` como el
resto de `Desarrollo`

**Scale/Scope**: cientos de actividades por año, decenas de participantes por actividad; 4 pantallas (lista, formulario en modal,
registro de asistencia, calificaciones) más la vista del colaborador; 11 operaciones HTTP y 4 métodos nuevos de Facade

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Casos de uso en `Desarrollo/Application/UseCases`, reglas puras (estado, validación de registros de asistencia, promedios, URL por tipo) en `Domain/Services` sin Laravel, repositorio por contrato `ActividadRepositoryInterface`, controladores delgados sin consultas. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (patrón aceptado en 000–016). Prefijo `/api/actividades` por contrato del insumo. Ver Complexity Tracking. |
| I. Un módulo no accede a tablas de otro módulo | PASA. Plan y Actividades viven ambos en `Desarrollo`, pero Actividades **no** lee `plan_acciones` directamente: usa `AccionesDePlanInterface`. Personal y Estructura solo por puertos que llaman a sus Facades. `departamento_id` y `colaborador_id` son referencias lógicas sin FK. |
| I. Endpoints admin con Sanctum + `admin` | PASA. Todos los endpoints exigen `auth:sanctum`; todo salvo `/mis-actividades` exige `admin`. El endpoint del colaborador usa solo el usuario del token. |
| I. Pest, Pint y análisis estático | PASA. Pest + Pint; no hay análisis estático instalado (se documenta, no se añade). |
| II. Frontend modular, tipado, accesible | PASA. Código en `frontend/src/modules/desarrollo/actividades/` con `api.ts`, `hooks/`, `components/`, `pages/`, `text.ts`, `validation.ts`; Axios solo en el cliente compartido; TanStack Query sin reintentos en escrituras; filtros y paginación en la URL; sin `any`. |
| II. Estados loading/empty/error/success; formularios; confirmaciones | PASA. Cada vista de datos cubre los cuatro estados; el formulario muestra errores por campo y bloquea envíos pendientes; guardar un registro ya existente exige confirmación accesible (`ConfirmDialog`). |
| III. Diseño como fuente visual | PASA con diferencias registradas. Se usan los tokens de `design/DESIGN.md` y los cuatro tableros; las diferencias con la spec se listan abajo. No se añade código a `design/`. |
| IV. Calidad, seguridad, dependencias | PASA. Sin dependencias nuevas; entradas validadas en `FormRequest` y en dominio; errores públicos seguros; observaciones nunca en respuestas del colaborador. |

## Project Structure

### Documentation (this feature)

```text
specs/017-actividades-formativas/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   ├── actividades-api.md
│   └── actividades-puertos-y-facades.md
├── checklists/requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks — no se crea aquí)
```

### Source Code (repository root)

```text
backend/
├── database/
│   ├── migrations/2026_10_21_00000{1,2,3}_create_actividad*_table.php
│   └── seeders/ActividadesSeeder.php            # registrado en DatabaseSeeder tras PlanFormacionSeeder
├── app/Modules/Desarrollo/
│   ├── Domain/
│   │   ├── ValueObjects/                        # CodigoActividad, AsistenciaParticipante, FiltroActividades, FiltroCalificaciones
│   │   ├── Services/                            # ReglasDeActividad, ValidadorDeRegistrosDeAsistencia, CalculadorDePromedios
│   │   ├── Exceptions/                          # ActividadNoEncontrada, AccionNoElegible, LugarInvalido, AsistenciaNoRegistrable, …
│   │   └── Repositories/ActividadRepositoryInterface.php
│   ├── Application/
│   │   ├── Ports/                               # AccionesDePlanInterface, DepartamentosInterface, AsistenciaRegistradaInterface
│   │   ├── Services/ArmadorDeActividades.php
│   │   └── UseCases/                            # CrearActividad, ActualizarActividad, ListarActividades, ObtenerActividad,
│   │                                            # ListarAccionesDisponibles, RegistrarAsistencia, ListarCalificaciones,
│   │                                            # ListarHistorialDeActividad, ObtenerMisActividades
│   ├── Infrastructure/
│   │   ├── Adapters/                            # AccionesDesdePlan, DepartamentosDesdeEstructura, AsistenciaDesdeActividades
│   │   ├── Persistence/Eloquent/Models/         # ActividadModel, ActividadAsistenciaModel, ActividadHistorialModel
│   │   ├── Persistence/Eloquent/Repositories/EloquentActividadRepository.php
│   │   └── Providers/DesarrolloServiceProvider.php   # bindings nuevos; AsistenciaInterface pasa al adaptador definitivo
│   ├── Presentation/
│   │   ├── Controllers/ActividadController.php, ActividadAsistenciaController.php, MisActividadesController.php
│   │   ├── Requests/                            # GuardarActividadRequest, RegistrarAsistenciaRequest, filtros
│   │   └── Facades/DesarrolloFacade.php         # @method nuevos
│   └── Routes/api.php                           # rutas /api/actividades
└── tests/{Unit,Feature}/Desarrollo/             # Pest

frontend/
├── src/modules/desarrollo/actividades/
│   ├── api.ts  text.ts  validation.ts  nav.tsx  estilosEstado.ts
│   ├── hooks/        # useActividades, useActividad, useAccionesDisponibles, useGuardarActividad,
│   │                 # useRegistrarAsistencia, useCalificaciones, useHistorialActividad, useMisActividades, claves.ts
│   ├── components/   # TablaActividades, FiltrosActividades, FormularioActividadModal, SelectorAccion, DatosHeredados,
│   │                 # TablaAsistencia, ResumenAsistencia, ConfirmarCambioDialog, TablaCalificaciones, DetalleParticipante,
│   │                 # HistorialActividad, TarjetaActividad, SeccionesColaborador, BadgeEstadoActividad, BadgeAsistencia,
│   │                 # RequireRole.tsx
│   └── pages/        # ActividadesPage, AsistenciaPage, MisActividadesPage
├── src/App.tsx                                  # rutas y navItems
└── e2e/actividades.spec.ts
```

**Structure Decision**: aplicación web con módulo existente `Desarrollo` (backend) y `desarrollo` (frontend). Se crea la funcionalidad
`actividades/` junto a `plan-formacion/` y `micro-learning/`, con las mismas capas; no se crean carpetas vacías.

## Diferencias entre tableros y spec (manda la spec)

| Tablero | Diferencia | Resolución |
|---|---|---|
| ActividadesAdmin | El modal «Nueva actividad» captura nombre, tipo, competencia, fechas, horas, formador, capacidad y campos de codesarrollo/mentoring/OJT. | La spec crea la actividad desde una **acción del plan**: el modal pasa a ser un **selector de acción** (búsqueda + filtro por plan) con los datos heredados en solo lectura y solo **lugar o enlace** y **departamento** editables. Se conserva el modal de 860 px, el encabezado, el aviso de error y los botones «Cancelar» / «Crear actividad». |
| ActividadesAdmin | Botón «Cancelar» por fila de la tabla. | **Se elimina**: la actividad no se cancela aquí (FR-009); se cancela la acción en el plan. La columna «Acciones» conserva «Asistencia» y añade «Editar». |
| ActividadesAdmin | Filtro de tipo sin E-learning; sin código, competencia, plan ni rango de fechas. | Se añade el tipo E-learning (FR-026), la columna **Código** (ACT-NNNN), los filtros de **competencia**, **plan** y **rango de fechas**, y el badge «Asistencia pendiente». Por defecto, año actual. |
| ActividadesAdmin | Botón «Ver como colaborador». | **Se omite**: cada persona tiene un solo rol; no se alterna de vista. |
| ActividadesAdmin | Panel «Fundamento de cada tipo (Alles)» y campos específicos de Codesarrollo/Mentoring/OJT. | **Se omiten**: el tipo y sus fechas se gestionan en el plan; cada sesión de un codesarrollo es otra acción y otra actividad. |
| ActividadesAsistencia | La calificación admite pasos de 0,5. | La spec exige **entero de 1 a 5** (FR-015): paso 1. |
| ActividadesAsistencia | (Volver a Pendiente: pulsar de nuevo la opción seleccionada, escenario 10 de US2.) Todos los estados de asistencia seleccionables, calificación siempre habilitada con asistencia Presente, sin observaciones condicionadas. | Se añade el estado visible **Pendiente** (sin registro), la **calificación y las observaciones deshabilitadas mientras sea Pendiente** (aclaración 1), la confirmación al cambiar un registro previo (FR-019), la explicación cuando la vista está deshabilitada (FR-021) y el acceso al historial. El resumen (Presentes / Ausentes / Justificados / Sin registrar) y «Marcar todos presentes» se conservan. |
| ActividadesCalificaciones | Pestañas internas con selector «Actividad» y un promedio por actividad que incluye ausentes. | Se aplican los filtros de la spec (fechas, tipo, departamento, plan); «Por participante» lista **solo** colaboradores con al menos una calificación (aclaración 5); el promedio es solo de calificaciones asignadas. |
| ActividadesColaborador | La tarjeta muestra observaciones. | **Nunca** se muestran (FR-030). Se agrega «Pendiente» tal cual (aclaración 3) y el estado de la actividad. |
| ActividadesColaborador | Cuatro tarjetas-resumen (inscritas, finalizadas, % de asistencia, calificación promedio). | Se conservan; la spec las recoge en FR-037 y en el escenario 9 de US3 como resumen informativo calculado por el endpoint del colaborador; no afecta el progreso del plan. |

Estas diferencias se registran aquí (Constitución III) y no obligan a modificar `design/`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Prefijo `/api/actividades` en lugar de `/api/desarrollo/actividades` | Contrato del insumo y consistencia con `/api/planes`, `/api/dnf`, `/api/microcontenidos` (000–016) | Cambiar solo esta feature rompería la uniformidad del módulo y los contratos ya publicados. |
| Puerto `AccionesDePlanInterface` aunque Plan y Actividades están en el mismo módulo | La spec exige que los datos de la acción sean siempre los del plan vigente y que Actividades no dependa de las tablas ni entidades internas del Plan | Leer `plan_acciones` desde el repositorio de Actividades acoplaría ambas funcionalidades; copiar datos los desactualizaría (aclaración 4). |

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/actividades` | Lista de actividades y pestaña Calificaciones (admin, `?vista=calificaciones`); Mis actividades (colaborador) | Administrador y Colaborador (la vista la elige el rol; cada uno ve solo la suya) |
| `/actividades/:id/asistencia` | Registro de asistencia (con acceso al historial) | Administrador |

El formulario de nueva/edición de actividad es un modal sobre `/actividades` (patrón «Formulario en modal», `design/ActividadesAdmin.dc.html`).

### Pantallas
Los detalles de cada pantalla están en la sección **Pantallas** del [spec.md](spec.md). Estado de datos común: `loading` (Skeleton), `empty`
(EmptyState), `error` con «Reintentar» (ErrorState) y `success`. Estados propios: asistencia deshabilitada con explicación; promedio sin
valor («—»); sin acciones elegibles en el selector; solo lectura con plan Cerrado.

### Navegación
Un ítem **Actividades** en `navItems` de `App.tsx` (icono del conjunto `shared/ui/icons`), visible para ambos roles, junto a Plan
anual y Micro-learning, como en el tablero.

## Post-design Constitution Re-check

Tras el diseño (data-model, contratos y quickstart) todas las puertas siguen en PASA: no se añadieron dependencias, el controlador no
consulta la base de datos, el colaborador no recibe observaciones, y las dos desviaciones están justificadas arriba.
