# Implementation Plan: Diccionario de preguntas

**Branch**: `003-diccionario-preguntas` | **Date**: 2026-10-11 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/003-diccionario-preguntas/spec.md`

## Summary

Diccionario maestro de preguntas: para cada competencia **activa** del diccionario de competencias (001) el
Administrador crea un único registro con **exactamente cuatro preguntas** numeradas del 1 al 4 (de la competencia en
general, no de un grado) y lo edita (también si su competencia está inactiva). Cualquier persona autenticada lo
consulta; el Usuario solo ve los de competencias activas. El registro **no tiene estado propio**: código, nombre,
descripción, tipo y estado se leen siempre de la competencia (join), así que desactivar o reactivar la competencia en
001 cambia el registro sin sincronización. Nunca se elimina un registro. El backend extiende el módulo `Estructura`
con `/api/preguntas` (sin `DELETE`) y amplía la Facade; el frontend añade el listado (búsqueda, filtros,
"Visualizar preguntas") y un formulario de crear/editar con el `Combobox` de 002 y cuatro campos numerados fijos.
Es la feature hermana de 002 y reutiliza sus patrones, con un modelo más simple (sin grados ni listas repetibles).

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript 6 estricto + React 19 en frontend

**Primary Dependencies**: Laravel Sanctum, React Router, TanStack Query, Axios, React Hook Form, Zod, Tailwind CSS 4,
Vitest, Testing Library, jsdom y Playwright (todos ya instalados y aprobados). Ninguna dependencia nueva.

**Storage**: SQLite. Tablas nuevas `competencia_pregunta_registros` (cabecera) y `competencia_preguntas` (filas). No
se tocan `competencias` ni `competencia_grados`.

**Testing**: Pest (Feature y Unit); Vitest + Testing Library (jsdom); E2E con Playwright

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: Listado paginado (10 por página, máximo 50); encontrar un registro en menos de 15 s (SC-002);
crear un registro completo en menos de 3 min (SC-001)

**Constraints**: exactamente 4 preguntas por registro, cada una ≤ 500 caracteres y distinta de las otras tres
(sin distinguir mayúsculas ni espacios alrededor); una competencia ↔ un solo registro; competencia inmutable tras
crear; sin estado propio ni borrado; rol verificado en el backend (un Usuario nunca recibe registros de competencias
inactivas); otros módulos solo acceden por la Facade; guardar sin cambios reales no altera nada; interfaz en español,
WCAG 2.2 AA

**Scale/Scope**: Como mucho un registro por competencia (cientos); 5 endpoints (sin `DELETE`), 2 pantallas, 5
registros sembrados (uno de competencia inactiva)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Código en `backend/app/Modules/Estructura/` con Domain, Application, Infrastructure y Presentation; casos de uso explícitos (`ListarPreguntas`, `ObtenerPreguntas`, `ListarCompetenciasDisponiblesPreguntas`, `CrearPreguntas`, `ActualizarPreguntas`); controlador delgado; persistencia por repositorio propio. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (patrón ya aceptado en 000, 001 y 002). Las rutas son `/api/preguntas` por indicación explícita del insumo. Ver Complexity Tracking. |
| I. Admin = Sanctum + capacidad `admin` | PASA. Crear, actualizar y `competencias-disponibles` usan `auth:sanctum` + `admin`; consultar y listar solo `auth:sanctum`. La visibilidad de inactivas se decide en el borde según la capacidad `admin` del token. |
| I. Un módulo no accede a clases o tablas de otro | PASA. Todo vive en `Estructura`; las relaciones Eloquent (`Competencia → RegistroPreguntas → Pregunta`) son internas del módulo. Los ids de usuario son enteros sin clave foránea a `users`. Los demás módulos solo usan `EstructuraFacade`. |
| I. Auditoría en los límites correspondientes | PASA. El actor sale del token en el controlador; el repositorio escribe cabecera y filas en una transacción. |
| I. Migraciones, factories y seeders según convención | PASA. Dos migraciones nuevas, factory y `PreguntasSeeder` (depende de `CompetenciasSeeder`). |
| II. Frontend modular, `shared` sin módulos | PASA. Código en `frontend/src/modules/estructura/preguntas/`. Se reutilizan `Combobox`, `Alert` neutro y los demás componentes de `shared/ui`; no se crean componentes compartidos nuevos. |
| II. Una funcionalidad no importa internals de otra | PASA. `preguntas` no importa nada de `competencias` ni de `comportamientos`: tipos, textos, guarda `RequireAdmin` y parámetros de listado propios; el rol llega por el contrato público `modules/auth/login`. |
| II. Axios solo en cliente compartido; TanStack Query; escrituras sin reintento; filtros en URL | PASA. Búsqueda, tipo, estado y página en `useSearchParams`; mutaciones sin reintentos. |
| II. App Shell, estados, formularios, confirmación accesible, WCAG | PASA. Estados cargando/vacío/error/éxito; formularios con errores por campo; no hay acciones destructivas. |
| II. E2E real por feature | PASA. `frontend/e2e/preguntas.spec.ts` con Playwright. |
| III. `design/` como fuente | AJUSTE MENOR ANTES DE IMPLEMENTAR. `Combobox` y `Alert` neutro ya están en `DESIGN.md` (002). Falta registrar la **lista numerada de preguntas** en la fila desplegable (§5) y las diferencias con los mockups (§9); es la primera tarea de frontend. |
| IV. Dependencias | PASA. Sin dependencias nuevas; icono nuevo (si hace falta) como SVG propio. |
| IV. Pruebas, seguridad | PASA. Pest, Vitest y E2E; validación en backend y frontend; el rol se verifica en cada petición. |

**Re-evaluación tras Fase 1**: sin cambios. El insumo se amplía con una tabla cabecera (D-02) y con `version` en
`PUT`; ambas decisiones se justifican por requisitos de la spec y se registran en Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/003-diccionario-preguntas/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── preguntas-api.md
│   └── estructura-facade.md
└── tasks.md             # Lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Estructura/
│   ├── Domain/
│   │   ├── Entities/RegistroPreguntas.php
│   │   ├── Repositories/PreguntaRepositoryInterface.php
│   │   ├── ValueObjects/PaginaRegistrosPreguntas.php      (reutiliza CompetenciaAsociada de 002)
│   │   └── Exceptions/{PreguntasNoEncontradas,CompetenciaYaTienePreguntas}.php   (reutiliza CompetenciaNoDisponible y RegistroModificado de 002, de mensaje genérico)
│   ├── Application/
│   │   ├── UseCases/{ListarPreguntas,ObtenerPreguntas,ListarCompetenciasDisponiblesPreguntas,CrearPreguntas,ActualizarPreguntas}.php
│   │   ├── DTO/{RegistroPreguntasDTO,ListadoPreguntasDTO,FiltroPreguntasDTO}.php
│   │   └── Services/EstructuraService.php                 (existente: se amplía la Facade)
│   ├── Infrastructure/
│   │   ├── Persistence/Eloquent/{Models/{RegistroPreguntasModel,PreguntaModel}.php,Repositories/EloquentPreguntaRepository.php}
│   │   │   └── Models/CompetenciaModel.php                (existente: + relación `registroPreguntas()`)
│   │   └── Providers/EstructuraServiceProvider.php        (existente: + binding)
│   ├── Presentation/
│   │   ├── Controllers/PreguntaController.php
│   │   ├── Requests/{GuardarPreguntasRequest,ListarPreguntasRequest}.php
│   │   ├── Resources/{RegistroPreguntasResource,RegistroPreguntasDetalleResource}.php
│   │   └── Facades/EstructuraFacade.php                   (existente: + métodos)
│   └── Routes/api.php                                      (existente: + /api/preguntas)
├── database/
│   ├── migrations/2026_10_11_000003_create_competencia_pregunta_registros_table.php
│   ├── migrations/2026_10_11_000004_create_competencia_preguntas_table.php
│   ├── factories/RegistroPreguntasFactory.php
│   └── seeders/{PreguntasSeeder,DatabaseSeeder}.php
└── tests/{Unit,Feature}/Estructura/Preguntas/…

frontend/src/
├── modules/estructura/preguntas/
│   ├── api.ts, text.ts, validation.ts, nav.tsx
│   ├── hooks/{usePreguntas,useListadoParams,useGuardarPreguntas}.ts   (usePreguntas incluye lista, detalle y disponibles)
│   ├── pages/{PreguntasPage,PreguntasFormPage}.tsx
│   └── components/{PreguntasTable,PreguntasPanel,CompetenciaResumen,RequireAdmin}.tsx
├── shared/ui/icons.tsx                                      (+ IconHelp, SVG propio, si se usa en el menú)
├── test/preguntasFixtures.ts
├── App.tsx                                                  (rutas y navegación)
└── e2e/preguntas.spec.ts
design/DESIGN.md                                            (actualizar primero, §5 y §9)
```

**Structure Decision**: Aplicación web con backend y frontend separados, en el módulo `Estructura` (backend) y
`modules/estructura/preguntas/` (frontend), como indica el insumo. La funcionalidad es independiente de `competencias`
y `comportamientos`: comparte con ellas solo la base de datos del módulo y los componentes de `shared/ui`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Rutas `/api/preguntas` en el módulo `Estructura` (la constitución pide `/api/<modulo>/`) | El insumo fija `/api/preguntas` y es el recurso que consumen otros módulos | `/api/estructura/preguntas` contradice el contrato indicado |
| Tabla cabecera `competencia_pregunta_registros` (el insumo solo define `competencia_preguntas` con `competencia_id`) | La spec exige auditoría **por registro** aunque una edición no cambie filas (FR-018: solo cambia con cambios reales y debe haber dónde guardar "última modificación"), control de edición simultánea con versión (FR-021) y una sola creación ante competencias simultáneas (SC-005). Con solo cuatro filas no hay columna estable para `version` ni restricción de unicidad del **registro** (cada fila podría existir sola, sin las otras tres) | Poner `version`/auditoría en `competencias` está prohibido por el insumo; guardar `version` en cada una de las 4 filas duplica el dato y permite registros incompletos |
| Campo `version` obligatorio en `PUT` (no está en el insumo) | FR-021 exige rechazar el segundo guardado concurrente | Comparar marcas de tiempo falla con resolución de 1 s |
| La FK de las preguntas apunta a la cabecera (`registro_id`) en lugar de a `competencias` (insumo) | Con la cabecera, la competencia ya queda ligada por `competencia_id` único; las filas cuelgan del registro, lo que impide preguntas huérfanas y mantiene "registro completo ⇔ cabecera con 4 filas" | Duplicar `competencia_id` en las filas permitiría inconsistencias con la cabecera |

## Diseño de frontend

### Rutas

| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/preguntas` | Listado de preguntas | Administrador y Usuario (el Usuario solo ve los de competencias activas) |
| `/preguntas/nuevo` | Formulario de preguntas (crear) | Solo Administrador (Usuario ve "sin permisos") |
| `/preguntas/:competenciaId/editar` | Formulario de preguntas (editar; competencia activa o inactiva) | Solo Administrador |

### Componentes

- **Reutilizados de `shared/ui`**: `Combobox` (selector con búsqueda, de 002), `Alert` (tono neutro para
  "La competencia asociada está inactiva"), `TextField`/`TextareaField`, Button, SearchField, SelectField, Badge
  (variante neutra), EmptyState, ErrorState, Skeleton, Pagination, Breadcrumb.
- **Nuevos, solo del módulo**: `PreguntasPage`, `PreguntasFormPage`, `PreguntasTable`, `PreguntasPanel` (lista
  numerada `<ol>` de 4 preguntas), `CompetenciaResumen`, `RequireAdmin` y `nav.tsx` (ítem "Preguntas"). Ningún
  componente compartido nuevo: las cuatro preguntas son cuatro `TextField` numerados fijos.

### Datos y estado

- Listado: `GET /api/preguntas?q=&tipo=&estado=&page=&per_page=`; clave `["preguntas","lista",{q,tipo,estado,page}]`.
  Búsqueda, tipo, estado y página en la URL (`useSearchParams`), con retardo de 300 ms al escribir y vuelta a la
  página 1 al cambiar filtros. `estado` se escribe en la URL solo si es `Inactivo` o `Todos` y solo para el
  Administrador.
- Preguntas: `GET /api/preguntas/{competenciaId}` solo al desplegar y al editar; clave
  `["preguntas","detalle",competenciaId]`; la edición fuerza lectura fresca.
- Crear: `GET /api/preguntas/competencias-disponibles` para el selector y `POST /api/preguntas`; editar:
  `PUT /api/preguntas/{competenciaId}` con `version`. Mutaciones sin reintentos; tras guardar se invalidan
  `["preguntas"]` sin esperar la recarga y se vuelve al listado con aviso.
- Una competencia inactiva se indica con el badge neutro "Inactivo" en el listado y con un aviso "La competencia
  asociada está inactiva" en el panel y en el formulario.
- Rol: `useCurrentUser` del contrato público del módulo de autenticación.

### Validación

- Competencia obligatoria al crear. Cuatro preguntas obligatorias, recortadas, ≤ 500 caracteres y distintas entre sí
  (sin distinguir mayúsculas). Replican `GuardarPreguntasRequest`. Contador `n/500` por campo.
- Mapeo de errores: 422 a campos (`competencia_id`, `preguntas.0`…`preguntas.3`); 409 de edición concurrente a la
  alerta "El registro cambió" con "Recargar"; 404 a "ya no existe"; 422 de competencia no disponible o con registro a
  un aviso en el selector.
