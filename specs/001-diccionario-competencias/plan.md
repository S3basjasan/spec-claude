# Implementation Plan: Diccionario de competencias

**Branch**: `001-diccionario-competencias` | **Date**: 2026-10-10 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-diccionario-competencias/spec.md` (actualizada el 2026-10-10)

**Nota**: este plan **reemplaza** al del 2026-10-09 y ya existe una implementación de ese plan. La sección
[Cambios respecto de lo construido](#cambios-respecto-de-lo-construido) lista lo que hay que modificar,
añadir o quitar.

## Summary

Diccionario maestro de competencias con permisos por rol (el Administrador gestiona, el Usuario consulta),
código `COMP-NNNN` generado por el sistema, nombre único sin distinguir mayúsculas, cuatro grados A–D
obligatorios y **ciclo de vida por estado**: las competencias nunca se eliminan, se activan y desactivan.
Cada alta, edición y cambio de estado queda auditado (quién y cuándo; historial inmutable de estados).
El backend vive en el módulo `Estructura` (Clean Architecture) y expone `/api/competencias` (sin `DELETE`,
con `PATCH …/estado`) más una Facade para los demás módulos, que solo pueden asignar competencias activas y
conservan las referencias a las inactivas. El frontend añade al listado la columna Código, el badge de
estado, el filtro de estado (solo Administrador) y el botón Activar/Desactivar con confirmación al desactivar.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript 6 estricto + React 19 en frontend

**Primary Dependencies**: Laravel Sanctum, React Router, TanStack Query, Axios, React Hook Form, Zod,
Tailwind CSS 4, Vitest, Testing Library, jsdom y Playwright (todos ya instalados y aprobados). Ninguna
dependencia nueva.

**Storage**: SQLite. Tablas `competencias`, `competencia_grados` y `competencia_estado_historial`

**Testing**: Pest (Feature y Unit); Vitest + Testing Library (jsdom); E2E con Playwright

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: Listado paginado (10 por página, máximo 50); encontrar una competencia en menos de
15 s (SC-002); creación completa en menos de 3 min (SC-001)

**Constraints**: Nombre ≤ 50 caracteres, descripciones ≤ 500; código inmutable y sin reutilización; nombre
único entre activas e inactivas sin distinguir mayúsculas/espacios; nunca se borra una competencia ni su
historial; rol verificado en el backend (un Usuario nunca recibe inactivas); otros módulos solo acceden por
la Facade; errores sin información sensible; interfaz en español, WCAG 2.2 AA

**Scale/Scope**: Cientos de competencias como máximo; 5 endpoints (sin `DELETE`), 2 pantallas, 7 competencias
sembradas (una inactiva)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Código en `backend/app/Modules/Estructura/` con Domain, Application, Infrastructure y Presentation; casos de uso explícitos (`CrearCompetencia`, `ListarCompetencias`, `ObtenerCompetencia`, `ActualizarCompetencia`, `CambiarEstadoCompetencia`); controlador delgado; persistencia por repositorio. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (ya registrada). Las rutas son `/api/competencias` por indicación explícita del insumo. Ver Complexity Tracking. |
| I. Admin = Sanctum + capacidad `admin` | PASA. Crear, actualizar y cambiar estado usan `auth:sanctum` + middleware `admin`; consultar y listar solo `auth:sanctum`. La visibilidad de inactivas en el listado se decide en el borde de entrada según la capacidad `admin` del token. |
| I. Un módulo no accede a clases o tablas de otro | PASA. `created_by`/`updated_by`/`user_id` son enteros sin clave foránea a `users`: Estructura recibe el id del actor desde el borde de entrada y no toca tablas de `Shared`. Los demás módulos solo usan `EstructuraFacade`. |
| I. Migraciones, factories y seeders según convención | PASA. Migraciones ajustadas, `CompetenciaFactory` y `CompetenciasSeeder` (7 competencias, una inactiva). |
| I. Auditoría en los límites correspondientes | PASA. El actor se obtiene del token en el controlador; el repositorio escribe `created_by`, `updated_by` y el historial en la misma transacción que el cambio. |
| II. Frontend modular, `shared` sin módulos | PASA. Todo en `frontend/src/modules/estructura/competencias/`; los componentes reutilizables de `shared/ui` no importan módulos. `Badge` se extiende con una variante en lugar de crear otro componente. |
| II. Una funcionalidad no importa internals de otra | PASA. `estructura` usa solo el contrato público `modules/auth/login/index.ts` (`useCurrentUser`, `Role`). |
| II. Axios solo en cliente compartido; TanStack Query; escrituras sin reintento; filtros en URL | PASA. Búsqueda, tipo, estado y página viven en `useSearchParams`; el cambio de estado es una mutación sin reintentos. |
| II. App Shell, estados, formularios, confirmación accesible, WCAG | PASA. Desactivar usa `ConfirmDialog` (`alertdialog`); activar no confirma (FR-009). |
| II. E2E real por feature | PASA. `frontend/e2e/competencias.spec.ts` se actualiza. |
| III. `design/` como fuente | **AJUSTE REQUERIDO ANTES DE IMPLEMENTAR.** Los mockups no tienen columna Código, badge de estado, filtro de estado ni botón Activar/Desactivar. Constitución III exige actualizar primero el diseño: la tarea de `design/DESIGN.md` §9 (y §4, variante de Badge) precede a la de frontend. |
| IV. Dependencias | PASA. Sin dependencias nuevas; los iconos nuevos (si hacen falta) son SVG propios. |
| IV. Pruebas, seguridad | PASA. Pest, Vitest y E2E; validación en backend y frontend; el rol se verifica en cada petición. |

**Re-evaluación tras Fase 1**: sin cambios; solo queda pendiente la actualización de `design/` (se registra
como primera tarea de frontend).

## Cambios respecto de lo construido

La implementación actual sigue el plan del 2026-10-09. Para ajustarla a este plan:

### Backend (`backend/app/Modules/Estructura/`, `backend/database/`, `backend/tests/`)

| Qué | Hoy | Debe quedar |
|---|---|---|
| Código | `COM-` + id con 3 dígitos (`COM-001`) | `COMP-` + id con 4 dígitos (`COMP-0001`) |
| Valores de `tipo` | `cardinal` / `especifica` | `Cardinal` / `Especifica` (en base de datos y API) |
| Estado | no existe | columna `estado` (`Activo`/`Inactivo`, por defecto `Activo`) y enum `EstadoCompetencia` |
| Auditoría | no existe | `created_by`, `updated_by` (enteros anulables sin FK) y tabla `competencia_estado_historial` |
| Borrado | `DELETE /api/competencias/{id}`, `EliminarCompetencia`, `CompetenciaEnUso`, `VerificadoresDeUso`, `VerificadorDeUsoInterface`, `cascadeOnDelete` en grados | **se eliminan**; la FK de grados queda sin cascada; no existe `DELETE` |
| Cambio de estado | no existe | `PATCH /api/competencias/{id}/estado` + caso de uso `CambiarEstadoCompetencia` + excepción `EstadoSinCambio` |
| Listado | sin filtro de estado, todos ven todo | filtro `estado`; el Usuario solo recibe `Activo` aunque lo pida; el Administrador por defecto `Activo` |
| Facade | `getCompetencia`, `getCompetenciasPorIds`, `existeCompetencia`, `registrarVerificadorDeUso` | quita `registrarVerificadorDeUso`; añade `estaActiva`, `getCompetenciasActivas` y devuelve `estado` en todas |
| Edición | actualiza `updated_at` | además guarda `updated_by`; el cambio de estado **no** altera `version` ni `updated_by` |
| Semilla | 7 competencias, todas activas | 7 competencias (mezcla de tipos, una `Inactiva`) con su historial |
| Pruebas | cubren `DELETE`, verificador de uso, `COM-NNN` | reemplazar por cambio de estado, listado por rol/estado, auditoría, `COMP-NNNN`, ausencia de `DELETE` |

### Frontend (`frontend/src/`, `frontend/e2e/`, `design/`)

| Qué | Hoy | Debe quedar |
|---|---|---|
| Diseño | sin estado | `design/DESIGN.md`: columna Código, badge Activo/Inactivo, filtro de estado, botón Activar/Desactivar |
| Tabla | Nombre, Descripción, Tipo + Editar/Eliminar | Código, Nombre, Descripción, Tipo, Estado (badge) + Editar y Activar/Desactivar |
| Eliminar | `useEliminarCompetencia`, `ConfirmDialog` de eliminar | se elimina; `ConfirmDialog` pasa a confirmar solo la **desactivación** |
| Estado | — | `useCambiarEstadoCompetencia` (mutación sin reintentos) |
| Filtros | búsqueda y tipo | además filtro de estado (solo Administrador) con `estado` en la URL |
| Valores | `tipo` en minúsculas | `Cardinal`/`Especifica`, `Activo`/`Inactivo` |
| Pruebas | cubren eliminación | reemplazar por activar/desactivar, filtro de estado y visibilidad por rol |

## Project Structure

### Documentation (this feature)

```text
specs/001-diccionario-competencias/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── competencias-api.md
│   └── estructura-facade.md
└── tasks.md             # Se regenera con /speckit-tasks (el actual corresponde al plan anterior)
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Estructura/
│   ├── Domain/
│   │   ├── Entities/Competencia.php                       (+ estado, creadoPor, modificadoPor)
│   │   ├── ValueObjects/{TipoCompetencia,EstadoCompetencia,GradoLetra,PaginaCompetencias}.php
│   │   ├── Repositories/CompetenciaRepositoryInterface.php (sin eliminar; + cambiarEstado)
│   │   └── Exceptions/{CompetenciaNoEncontrada,NombreDuplicado,ConflictoDeVersion,EstadoSinCambio}.php
│   ├── Application/
│   │   ├── UseCases/{Crear,Listar,Obtener,Actualizar,CambiarEstado}Competencia(s).php
│   │   ├── DTO/{CompetenciaDTO,ListadoCompetenciasDTO,FiltroCompetenciasDTO,DatosCompetenciaDTO}.php
│   │   └── Services/EstructuraService.php                 (Facade: lectura y estaActiva)
│   ├── Infrastructure/
│   │   ├── Persistence/Eloquent/{Models/{CompetenciaModel,CompetenciaGradoModel,CompetenciaEstadoHistorialModel}.php,Repositories/EloquentCompetenciaRepository.php}
│   │   └── Providers/EstructuraServiceProvider.php
│   ├── Presentation/
│   │   ├── Controllers/CompetenciaController.php          (index, show, store, update, cambiarEstado)
│   │   ├── Requests/{GuardarCompetenciaRequest,ListarCompetenciasRequest,CambiarEstadoRequest}.php
│   │   ├── Resources/{CompetenciaResource,CompetenciaDetalleResource}.php
│   │   └── Facades/EstructuraFacade.php
│   └── Routes/api.php                                      (sin DELETE; + PATCH /{id}/estado)
├── database/
│   ├── migrations/<fecha>_create_competencias_table.php            (ajustada en su sitio)
│   ├── migrations/<fecha>_create_competencia_grados_table.php      (sin cascada)
│   ├── migrations/<fecha>_create_competencia_estado_historial_table.php   (nueva)
│   ├── factories/CompetenciaFactory.php
│   └── seeders/CompetenciasSeeder.php
└── tests/{Unit,Feature}/Estructura/Competencias/…

frontend/src/
├── shared/ui/{Badge,…}.tsx                                 (Badge: variante `neutral` para Inactivo)
├── modules/estructura/competencias/
│   ├── api.ts, text.ts, validation.ts, nav.tsx
│   ├── hooks/{useCompetencias,useCompetencia,useGuardarCompetencia,useCambiarEstadoCompetencia,useListadoParams}.ts
│   ├── pages/{CompetenciasPage,CompetenciaFormPage}.tsx
│   └── components/{CompetenciasTable,GradosPanel,RequireAdmin}.tsx
└── e2e/competencias.spec.ts
design/DESIGN.md                                            (actualizar primero)
```

**Structure Decision**: Aplicación web con backend y frontend separados, en el módulo `Estructura` como
indica el insumo. Las migraciones se ajustan en su sitio (no se añaden otras que reescriban tablas) porque la
feature no se ha desplegado; ver research.md D-02.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Rutas `/api/competencias` en el módulo `Estructura` (la constitución pide `/api/<modulo>/`) | El insumo fija `/api/competencias` y "competencias" es el recurso que consumen otros módulos | `/api/estructura/competencias` contradice el contrato indicado y alarga las rutas sin aportar claridad |
| Columna `version` y campo `version` obligatorio en `PUT` (no está en el modelo del insumo) | FR-022 exige rechazar el segundo guardado concurrente | Comparar `updated_at` falla con marcas de tiempo de un segundo y escrituras casi simultáneas |
| Tabla `competencia_estado_historial` (no listada en el modelo del insumo) | FR-017 exige un historial inmutable de cada cambio de estado, y el endpoint de estado "registra en el historial" | Guardar el historial en columnas de `competencias` pierde los cambios anteriores |

## Diseño de frontend

### Rutas

| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/competencias` | Listado de competencias | Administrador y Usuario (el Usuario solo ve activas) |
| `/competencias/nueva` | Formulario de competencia (crear) | Solo Administrador (Usuario ve "sin permisos") |
| `/competencias/:id/editar` | Formulario de competencia (editar; activa o inactiva) | Solo Administrador |

### Componentes

- **Nuevos**: `useCambiarEstadoCompetencia` (hook). Ningún componente nuevo: se reutilizan `Badge` (nueva
  variante de color), `ConfirmDialog`, `SelectField`, `Button`, `Pagination`, `EmptyState`, `ErrorState`,
  `Skeleton` y `Alert`.
- **Modificados**: `CompetenciasTable` (columnas Código y Estado, botón Activar/Desactivar), `CompetenciasPage`
  (filtro de estado, diálogo de desactivar), `api.ts`, `text.ts` y `Badge`.
- **Retirados**: `useEliminarCompetencia` y los textos y flujos de eliminar.

### Datos y estado

- Listado: `GET /api/competencias?q=&tipo=&estado=&page=&per_page=`; clave `["competencias","lista",{q,tipo,estado,page}]`.
  Búsqueda, tipo, estado y página en la URL (`useSearchParams`), con retardo de 300 ms al escribir y vuelta
  a la página 1 al cambiar filtros. `estado` en la URL solo para el Administrador (`Activo` por defecto, no se
  escribe; `Inactivo` o `Todos`). Para el Usuario el parámetro no se envía ni se ofrece.
- Grados: `GET /api/competencias/{id}` solo al desplegar; clave `["competencias","detalle",id]`.
- Crear: `POST`; Actualizar: `PUT` con `version`; Cambiar estado: `PATCH /{id}/estado`. Mutaciones sin
  reintentos; tras ellas se invalidan `["competencias"]` (sin esperar la recarga).
- Al desactivar, la competencia sale del listado por defecto (se refresca); al activar desde la vista de
  inactivas, sale de esa vista. Avisos de estado con `role="status"`.
- Rol: `useCurrentUser` del contrato público del módulo de autenticación.

### Validación

- Nombre obligatorio, recortado, ≤ 50; descripción obligatoria ≤ 500; tipo obligatorio (`Cardinal` |
  `Especifica`); grados A, B, C y D obligatorios ≤ 500, con contador. Replican `GuardarCompetenciaRequest`.
- Mapeo de errores: 422 a campos; 409 de edición concurrente a la alerta "La competencia cambió" con
  "Recargar"; 422 de estado repetido (`estado`) a aviso "ya estaba en ese estado" y refresco del listado;
  404 a "ya no existe".
