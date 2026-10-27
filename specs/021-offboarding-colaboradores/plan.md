# Implementation Plan: Offboarding de colaboradores

**Branch**: `021-offboarding-colaboradores` | **Date**: 2026-10-26 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/021-offboarding-colaboradores/spec.md`

## Summary

Gestionar la desvinculación dentro de `GestionPersonal`: formalizar la salida con la política de Offboarding (versión y plantilla fijadas), seguir el checklist (incluida la entrevista de salida como tarea obligatoria del sistema), aplicar la salida en la fecha efectiva de forma atómica e idempotente (cierra asignación y vínculo, libera la posición), confirmar el cierre administrativo y recopilar la entrevista voluntaria con un cuestionario predefinido, conservando toda la información estructurada para un análisis futuro que no se implementa. Detalle y alternativas en [research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.3+ (local 8.5.5), Laravel 13; TypeScript estricto, React 19.

**Primary Dependencies**: las existentes (Sanctum, Pest, Pint; Vite, React Router, TanStack Query, RHF + Zod, Vitest, Playwright). **Sin dependencias nuevas.**

**Storage**: SQLite con aislamiento por `tenant_id`, índices parciales y triggers (SQL crudo), como GP-02, 016 y 020.

**Testing**: Pest (Feature y Unit, SQLite `:memory:`), Vitest + Testing Library, un E2E Playwright sobre la base E2E (`APP_ENV=e2e`, tenant 2).

**Target Platform**: SPA + API Laravel monolítica con planificador para la aplicación automática.

**Project Type**: aplicación web (backend + frontend).

**Performance Goals**: listado paginado con índice `(tenant_id, estado, fecha_efectiva)`; resumen con agregaciones únicas; el procesador solo evalúa salidas con fecha alcanzada.

**Constraints**: cero estados parciales al aplicar; una sola ejecución efectiva ante reintentos y concurrencia; respuestas inmutables tras completarse; Colaborador sin acceso a lo ajeno; ningún dato sale a servicios externos; respaldos solo referenciados.

**Scale/Scope**: 5 historias, 2 roles, ~14 endpoints de Administrador y 5 del Colaborador, 5 pantallas.

## Constitution Check

| Principio | Evaluación |
|---|---|
| I. Backend modular | **Cumple.** Todo en `app/Modules/GestionPersonal/{Domain,Application,Infrastructure,Presentation}`; controladores delgados; casos de uso por acción; persistencia tras `ExpedienteRepositoryInterface`; política, vínculos, documentos, cuenta y permisos por puertos o contratos; rutas `/api/offboarding` (admin + `admin`) y `/api/mi-desvinculacion` (excepción justificada, igual que `/api/mi-ficha`). |
| II. Frontend | **Cumple.** `modules/gestionPersonal/offboarding/` con `api.ts`, `hooks/`, `components/`, `pages/`, `validation.ts`, `text.ts`; Axios solo vía `httpClient`; escrituras sin reintento; filtros en URL; textos centralizados; estados loading/empty/error/success; confirmación accesible; importa de `politicas` solo por su `index.ts`. |
| III. Diseño | **Cumple.** Se agregan composiciones a `design/DESIGN.md`. |
| IV. Calidad y dependencias | **Cumple.** Pruebas por comportamiento (permisos, aislamiento, atomicidad, idempotencia, inmutabilidad), sin dependencias nuevas y sin datos personales reales. |

Reevaluación tras el diseño: sin cambios ni violaciones.

## Project Structure

### Documentation (this feature)

```text
specs/021-offboarding-colaboradores/
├── plan.md  research.md  data-model.md  quickstart.md  tasks.md
├── contracts/{api-offboarding.md, analisis-futuro.md}
└── checklists/requirements.md
```

### Source Code (repository root)

```text
backend/app/Modules/GestionPersonal/
├── Domain/{Entities/Expediente.php, ValueObjects/{MotivoDeSalida,EstadoDeSalida,EstadoDeEntrevista}.php,
│           Services/{CuestionarioDeSalida,ReglasDeSalida,RepresentacionParaAnalisis,CalculadorDeAgregados}.php,
│           Repositories/ExpedienteRepositoryInterface.php, Exceptions/…}
├── Application/{Ports/{CoordinadorDeCuentaPort,PermisoDeEntrevistasPort}.php,
│               Services/{FormalizadorDeSalida,ControlDeOffboarding,ArmadorDeExpediente}.php,
│               UseCases/{FormalizarSalida,CancelarSalida,CompletarTarea,AplicarSalida,AplicarSalidasProgramadas,
│                         ConfirmarCierre,RegistrarEntrevistaNoRealizada,GuardarEntrevista,ConfirmarEntrevista,
│                         DeclinarEntrevista,ListarExpedientes,ResumirOffboarding,ObtenerExpediente,
│                         ObtenerContextoDeSalida,ConsultarRespuestas,ObtenerAgregados,ObtenerMiDesvinculacion,…}.php}
├── Infrastructure/{Adapters/{CoordinadorDeCuentaNoInstalado,PermisoDeEntrevistasNoInstalado}.php,
│                   Persistence/Eloquent/Repositories/EloquentExpedienteRepository.php,
│                   Console/AplicarSalidasProgramadasCommand.php}
├── Presentation/{Controllers/{OffboardingController,MiDesvinculacionController}.php,Requests/…}
└── Routes/api.php
backend/database/migrations/2026_10_26_00000{1,2,3}_*.php
backend/tests/{Feature,Unit}/GestionPersonal/Offboarding/
frontend/src/modules/gestionPersonal/offboarding/{api.ts,text.ts,validation.ts,nav.tsx,index.ts,errores.ts,hooks/,components/,pages/}
frontend/e2e/offboarding.spec.ts  frontend/e2e/support/sembrar-offboarding.php  frontend/src/test/offboardingFixtures.ts
```

**Structure Decision**: módulo existente `GestionPersonal` (backend) y carpeta de funcionalidad `offboarding` (frontend); nada en `Shared` salvo reutilizar el contrato de tenant.

## Complexity Tracking

Sin violaciones.

## Diseño de frontend

### Rutas

| Ruta | Pantalla | Roles |
|---|---|---|
| `/offboarding` | Listado con resumen y filtros en URL | Administrador |
| `/offboarding/formalizar` | Formalizar desvinculación | Administrador |
| `/offboarding/:id` | Expediente: checklist, aplicación, cierre, entrevista | Administrador |
| `/mi-desvinculacion` | Mi desvinculación y tareas | Colaborador |
| `/mi-desvinculacion/entrevista` | Entrevista de salida | Colaborador |

### Componentes
- **Nuevos**: `TablaDeExpedientes`, `FiltrosDeExpedientes`, `ChecklistDeSalida`, `PanelDeAplicacion`, `PanelDeEntrevista`, `FormularioDeEntrevista`, `DialogoDeAccion`.
- **Del design system**: `Alert`, `Badge`, `Button`, `ConfirmDialog`, `Modal`, `EmptyState`, `ErrorState`, `Pagination`, `SelectField`, `TextField`, `TextareaField`, `Combobox`, `Skeleton`, `Breadcrumb`; `CondicionesAplicables` de 016.

### Datos y estado
- Endpoints de [contracts/api-offboarding.md](contracts/api-offboarding.md); las claves de Query se parten por rol; mutaciones sin reintento que invalidan listado, resumen y detalle; `Idempotency-Key` por contenido; filtros en la URL.

### Validación
- Zod replica fechas, motivo del catálogo, razón obligatoria al cancelar, rango 1–5 y límites de texto; los 422 se asignan por campo; los códigos de negocio (política, evidencia, cierre, entrevista sin resolver) se muestran con texto propio, distinto de lista vacía y error de red. Un 409 conserva lo escrito y ofrece actualizar.
