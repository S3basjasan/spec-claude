# Implementation Plan: Diccionario de comportamientos

**Branch**: `002-diccionario-comportamientos` | **Date**: 2026-10-11 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/002-diccionario-comportamientos/spec.md`

## Summary

Diccionario maestro de comportamientos: para cada competencia **activa** del diccionario de competencias (001) el
Administrador crea un único registro con entre 1 y 4 comportamientos esperados por cada grado A–D, y lo edita
(también si su competencia está inactiva). Cualquier persona autenticada lo consulta; el Usuario solo ve los de
competencias activas. El registro **no tiene estado propio**: código, nombre, descripción, tipo y estado se leen
siempre de la competencia (join), así que desactivar o reactivar la competencia en 001 cambia el registro sin
sincronización. Nunca se elimina un registro. El backend extiende el módulo `Estructura` con `/api/comportamientos`
(sin `DELETE`) y amplía la Facade; el frontend añade el listado (búsqueda, filtros, "Visualizar comportamientos") y
un formulario de crear/editar con un selector de competencias con búsqueda y listas de 1–4 campos por grado.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript 6 estricto + React 19 en frontend

**Primary Dependencies**: Laravel Sanctum, React Router, TanStack Query, Axios, React Hook Form (`useFieldArray`),
Zod, Tailwind CSS 4, Vitest, Testing Library, jsdom y Playwright (todos ya instalados y aprobados). Ninguna
dependencia nueva.

**Storage**: SQLite. Tablas nuevas `competencia_comportamiento_registros` (cabecera) y
`competencia_comportamientos` (filas). No se tocan `competencias` ni `competencia_grados`.

**Testing**: Pest (Feature y Unit); Vitest + Testing Library (jsdom); E2E con Playwright

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: Listado paginado (10 por página, máximo 50); encontrar un registro en menos de 15 s (SC-002);
crear un registro completo en menos de 5 min (SC-001)

**Constraints**: 1–4 comportamientos por grado, cada uno ≤ 300 caracteres y único dentro del grado; una competencia
↔ un solo registro; competencia inmutable tras crear; sin estado propio ni borrado; rol verificado en el backend
(un Usuario nunca recibe registros de competencias inactivas); otros módulos solo acceden por la Facade; guardar sin
cambios reales no altera nada; interfaz en español, WCAG 2.2 AA

**Scale/Scope**: Como mucho un registro por competencia (cientos); 5 endpoints (sin `DELETE`), 2 pantallas, 5 registros
sembrados (uno de competencia inactiva)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Código en `backend/app/Modules/Estructura/` con Domain, Application, Infrastructure y Presentation; casos de uso explícitos (`ListarComportamientos`, `ObtenerComportamientos`, `ListarCompetenciasDisponibles`, `CrearComportamientos`, `ActualizarComportamientos`); controlador delgado; persistencia por repositorio propio. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (patrón ya aceptado en 000 y 001). Las rutas son `/api/comportamientos` por indicación explícita del insumo. Ver Complexity Tracking. |
| I. Admin = Sanctum + capacidad `admin` | PASA. Crear, actualizar y `competencias-disponibles` usan `auth:sanctum` + `admin`; consultar y listar solo `auth:sanctum`. La visibilidad de inactivas se decide en el borde según la capacidad `admin` del token (igual que 001). |
| I. Un módulo no accede a clases o tablas de otro | PASA. Todo vive en `Estructura`; las relaciones Eloquent (`Competencia → CompetenciaGrado → Comportamiento`) son internas del módulo. Los ids de usuario son enteros sin clave foránea a `users`. Los demás módulos solo usan `EstructuraFacade`. |
| I. Auditoría en los límites correspondientes | PASA. El actor sale del token en el controlador; el repositorio escribe cabecera y filas en una transacción. |
| I. Migraciones, factories y seeders según convención | PASA. Dos migraciones nuevas, factory y `ComportamientosSeeder` (depende de `CompetenciasSeeder`). |
| II. Frontend modular, `shared` sin módulos | PASA. Código en `frontend/src/modules/estructura/comportamientos/`; los componentes nuevos de uso general (`Combobox`, `ListaDeCampos`) van a `shared/ui` sin importar módulos. |
| II. Una funcionalidad no importa internals de otra | PASA. `comportamientos` no importa nada de `competencias` (ni siquiera dentro del módulo `estructura`): tipos y textos propios; el rol llega por el contrato público `modules/auth/login`. |
| II. Axios solo en cliente compartido; TanStack Query; escrituras sin reintento; filtros en URL | PASA. Búsqueda, tipo, estado y página en `useSearchParams`; mutaciones sin reintentos. |
| II. App Shell, estados, formularios, confirmación accesible, WCAG | PASA. Estados cargando/vacío/error/éxito; formularios con errores por campo; no hay acciones destructivas en este módulo. |
| II. E2E real por feature | PASA. `frontend/e2e/comportamientos.spec.ts` con Playwright. |
| III. `design/` como fuente | **AJUSTE REQUERIDO ANTES DE IMPLEMENTAR.** No existen en `design/` un combobox con búsqueda, una lista de campos repetibles (agregar/quitar) ni el aviso "competencia inactiva". Constitución III exige actualizar primero el diseño (`DESIGN.md` §4, §5 y §9); es la primera tarea de frontend. |
| IV. Dependencias | PASA. Sin dependencias nuevas; iconos nuevos como SVG propios. |
| IV. Pruebas, seguridad | PASA. Pest, Vitest y E2E; validación en backend y frontend; el rol se verifica en cada petición. |

**Re-evaluación tras Fase 1**: sin cambios. El insumo se amplía con una tabla cabecera (D-02) y con `version` en
`PUT`; ambas decisiones se justifican por requisitos de la spec y se registran en Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/002-diccionario-comportamientos/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── comportamientos-api.md
│   └── estructura-facade.md
└── tasks.md             # Lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Estructura/
│   ├── Domain/
│   │   ├── Entities/RegistroComportamientos.php
│   │   ├── ValueObjects/{CompetenciaAsociada,PaginaRegistrosComportamientos}.php
│   │   ├── Repositories/ComportamientoRepositoryInterface.php
│   │   └── Exceptions/{RegistroNoEncontrado,CompetenciaNoDisponible,CompetenciaYaTieneRegistro,RegistroModificado}.php
│   ├── Application/
│   │   ├── UseCases/{ListarComportamientos,ObtenerComportamientos,ListarCompetenciasDisponibles,CrearComportamientos,ActualizarComportamientos}.php
│   │   ├── DTO/{RegistroComportamientosDTO,ListadoComportamientosDTO,FiltroComportamientosDTO,CompetenciaDisponibleDTO}.php
│   │   └── Services/EstructuraService.php                 (existente: se amplía la Facade)
│   ├── Infrastructure/
│   │   ├── Persistence/Eloquent/{Models/{RegistroComportamientosModel,ComportamientoModel}.php,Repositories/EloquentComportamientoRepository.php}
│   │   │   └── Models/CompetenciaGradoModel.php           (existente: + relación `comportamientos()`)
│   │   └── Providers/EstructuraServiceProvider.php        (existente: + binding)
│   ├── Presentation/
│   │   ├── Controllers/ComportamientoController.php
│   │   ├── Requests/{GuardarComportamientosRequest,ListarComportamientosRequest}.php
│   │   ├── Resources/{RegistroComportamientosResource,RegistroComportamientosDetalleResource,CompetenciaDisponibleResource}.php
│   │   └── Facades/EstructuraFacade.php                   (existente: + métodos)
│   └── Routes/api.php                                      (existente: + /api/comportamientos)
├── database/
│   ├── migrations/<fecha>_create_competencia_comportamiento_registros_table.php
│   ├── migrations/<fecha>_create_competencia_comportamientos_table.php
│   ├── factories/RegistroComportamientosFactory.php
│   └── seeders/{ComportamientosSeeder,DatabaseSeeder}.php
└── tests/{Unit,Feature}/Estructura/Comportamientos/…

frontend/src/
├── shared/ui/{Combobox,ListaDeCampos}.tsx                 (nuevos) + icons.tsx (+ IconList)
├── modules/estructura/comportamientos/
│   ├── api.ts, text.ts, validation.ts, nav.tsx
│   ├── hooks/{useComportamientos,useComportamiento,useCompetenciasDisponibles,useGuardarComportamientos,useListadoParams}.ts
│   ├── pages/{ComportamientosPage,ComportamientosFormPage}.tsx
│   └── components/{ComportamientosTable,ComportamientosPanel,CompetenciaResumen}.tsx
├── App.tsx                                                  (rutas y navegación)
└── e2e/comportamientos.spec.ts
design/DESIGN.md                                            (actualizar primero)
```

**Structure Decision**: Aplicación web con backend y frontend separados, en el módulo `Estructura` (backend) y
`modules/estructura/comportamientos/` (frontend), como indica el insumo. La funcionalidad es independiente de
`competencias`: comparte con ella solo la base de datos del módulo y los componentes de `shared/ui`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Rutas `/api/comportamientos` en el módulo `Estructura` (la constitución pide `/api/<modulo>/`) | El insumo fija `/api/comportamientos` y es el recurso que consumen otros módulos | `/api/estructura/comportamientos` contradice el contrato indicado |
| Tabla cabecera `competencia_comportamiento_registros` (no está en el modelo del insumo) | La spec exige auditoría **por registro** (FR-018: quién lo creó y modificó por última vez, solo si hay cambios reales), control de edición simultánea con versión (FR-021) y una sola creación ante competencias simultáneas (SC-005). Con solo filas de comportamientos, quitar un comportamiento no dejaría rastro de quién editó, no habría dónde guardar `version` y la unicidad no tendría restricción propia | Poner `version`/auditoría en `competencias` o `competencia_grados` está prohibido por el insumo; calcular "última modificación" desde las filas pierde las ediciones que solo quitan comportamientos |
| Campo `version` obligatorio en `PUT` (no está en el insumo) | FR-021 exige rechazar el segundo guardado concurrente | Comparar marcas de tiempo falla con resolución de 1 s |
| Dos componentes nuevos en `shared/ui` (`Combobox`, `ListaDeCampos`) | El insumo pide "no crear componentes nuevos si existe uno equivalente": no existe ninguno que busque al escribir ni que agregue/quite campos | `SelectField` nativo no filtra al escribir (se pidió búsqueda en la clarificación); repetir campos a mano en el formulario duplicaría lógica de accesibilidad |

## Diseño de frontend

### Rutas

| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/comportamientos` | Listado de comportamientos | Administrador y Usuario (el Usuario solo ve los de competencias activas) |
| `/comportamientos/nuevo` | Formulario de comportamientos (crear) | Solo Administrador (Usuario ve "sin permisos") |
| `/comportamientos/:competenciaId/editar` | Formulario de comportamientos (editar; competencia activa o inactiva) | Solo Administrador |

### Componentes

- **Nuevos**: en `shared/ui`: `Combobox` (campo con búsqueda al escribir y lista de opciones filtrada, patrón
  ARIA combobox) y `ListaDeCampos` (campos de texto repetibles con "Agregar" hasta el máximo y "Quitar" hasta el
  mínimo, con contador). En el módulo: `ComportamientosPage`, `ComportamientosFormPage`, `ComportamientosTable`,
  `ComportamientosPanel` (grados con viñetas), `CompetenciaResumen` (nombre, descripción, tipo, estado en solo
  lectura) y `nav.tsx` (ítem "Comportamientos").
- **Del design system** (`design/DESIGN.md` §4): Button, TextField, TextareaField, SearchField, SelectField,
  Badge (variante neutra ya existente), Alert, EmptyState, ErrorState, Skeleton, Pagination, Breadcrumb.

### Datos y estado

- Listado: `GET /api/comportamientos?q=&tipo=&estado=&page=&per_page=`; clave
  `["comportamientos","lista",{q,tipo,estado,page}]`. Búsqueda, tipo, estado y página en la URL (`useSearchParams`),
  con retardo de 300 ms al escribir y vuelta a la página 1 al cambiar filtros. `estado` se escribe en la URL solo
  si es `Inactivo` o `Todos` y solo para el Administrador.
- Comportamientos por grado: `GET /api/comportamientos/{competenciaId}` solo al desplegar y al editar; clave
  `["comportamientos","detalle",competenciaId]`; la edición fuerza lectura fresca.
- Crear: `GET /api/comportamientos/competencias-disponibles` para el selector y `POST /api/comportamientos`;
  editar: `PUT /api/comportamientos/{competenciaId}` con `version`. Mutaciones sin reintentos; tras guardar se
  invalidan `["comportamientos"]` sin esperar la recarga y se vuelve al listado con aviso.
- Una competencia inactiva se indica con el badge neutro "Inactivo" en el listado y con un aviso "La competencia
  asociada está inactiva" en el panel de grados y en el formulario.
- Rol: `useCurrentUser` del contrato público del módulo de autenticación.

### Validación

- Competencia obligatoria al crear. Por grado A–D: entre 1 y 4 comportamientos, cada uno obligatorio, recortado,
  ≤ 300 caracteres y distinto de los demás del mismo grado (sin distinguir mayúsculas). Replican
  `GuardarComportamientosRequest`.
- Mapeo de errores: 422 a campos (`competencia_id`, `comportamientos.A`, `comportamientos.B.2`…); 409 de edición
  concurrente a la alerta "El registro cambió" con "Recargar"; 404 a "ya no existe"; 422 de competencia no
  disponible o con registro a un aviso en el selector y recarga de las disponibles.
