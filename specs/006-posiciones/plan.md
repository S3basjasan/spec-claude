# Implementation Plan: Posiciones organizacionales

**Branch**: `006-posiciones` | **Date**: 2026-10-15 (revisado 2026-10-16) | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/006-posiciones/spec.md`, con el insumo técnico `plan-posiciones.md` (carpeta `006-posiciones`)

## Summary

Gestión de las posiciones de la estructura organizacional: lugares concretos creados a partir de un puesto activo, ubicados en una unidad activa, con un supervisor (otra posición) y una modalidad. El Administrador crea posiciones (siempre Vacantes), edita su unidad, supervisor y modalidad, y las suprime; cualquier persona autenticada las lista (el Usuario, solo Ocupadas y Vacantes) y consulta cualquiera, con su detalle desplegable. La estructura cumple cinco invariantes en todo momento: **sin ciclos de supervisión**, **jerarquía de unidades** (la supervisora está en la misma unidad o en una unidad superior de la posición), **una sola máxima autoridad, que es la única posición vigente de la unidad raíz, y sin supervisor si y solo si se está en la raíz**, **un supervisor nunca Suprimido** y **puesto y unidad activos en toda posición vigente**. Suprimir exige que la posición esté Vacante y sin subordinadas vigentes, es definitivo y se resuelve con una sola sentencia atómica. La ocupación (usuario y paso Ocupada ⇄ Vacante) **no** se gestiona aquí: la hace el módulo de gestión de personal, que aún no existe. Cada cambio queda en `auditoria_cambios`.

El backend extiende el módulo `Estructura` con `/api/posiciones` (6 operaciones, sin `DELETE`, sin reactivar ni asignar ocupante), seis casos de uso, un repositorio propio, un servicio de dominio puro para el árbol de supervisión y la ampliación de la Facade. El frontend añade `modules/estructura/posiciones/` con **una sola pantalla**: listado con filas desplegables, filtros, paginador y modales. El insumo supone que el modelo ya está completo, que existen roles con permisos por acción y que el diseño ya incluye el tablero "Posiciones"; **nada de eso es cierto en el repositorio**, y el plan lo resuelve con una migración que completa la tabla mínima `posiciones`, la capacidad `admin` y una tarea de diseño previa (ver Complexity Tracking y [research.md](research.md)).

**Revisión 2026-10-16**: el insumo `plan-posiciones.md` se actualizó con la regla de jerarquía de unidades, la unidad raíz con una sola posición, la columna Ocupante, la búsqueda por unidad y ocupante, el contador de resultados, los selectores de unidad y supervisor agrupados y las unidades mínimas al editar. Los cambios están en la sección "Revisión 2026-10-16" y en [research.md](research.md) (D-19 a D-26). Exigen además ampliar el módulo de unidades (005) para que una reubicación no rompa la jerarquía (FR-038).

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript 6 estricto + React 19 en frontend

**Primary Dependencies**: Laravel Sanctum, React Router, TanStack Query, Axios, React Hook Form, Zod, Tailwind CSS 4, Vitest, Testing Library, jsdom y Playwright (todos ya instalados y aprobados). Ninguna dependencia nueva.

**Storage**: SQLite. Una migración nueva completa `posiciones` (creada en 004, con `unidad_organizacional_id` añadida en 005): `codigo`, `user_id`, `posicion_jefe_id`, `modalidad`, `version`, `created_by` y `updated_by`, más un índice único parcial de la máxima autoridad. Se reutiliza `auditoria_cambios` con `entidad = 'posicion'`. No se tocan `puestos`, `unidades_organizacionales` ni `users`.

**Testing**: Pest (Feature y Unit); Vitest + Testing Library (jsdom); E2E con Playwright. Pint disponible; no hay análisis estático y no se añade (Constitución IV).

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: Página de 10 posiciones sobre hasta 2000 en menos de 3 s (SC-003); encontrar una posición en menos de 15 s (SC-002); crear una posición en menos de 1 min (SC-001)

**Constraints**: puesto y unidad activos al crear (unidad activa también al cambiarla); supervisor no Suprimido, distinto de la propia posición y de sus subordinadas (sin ciclos), y en la misma unidad o en una unidad superior de la posición (jerarquía de unidades); la unidad raíz tiene una sola posición vigente (la máxima autoridad) y solo ella no tiene supervisor (además garantizado por un índice único parcial); al editar, la nueva unidad es la misma o superior a la de cada subordinada vigente y la máxima autoridad solo cambia de modalidad; suprimir solo Vacante y sin subordinadas vigentes, en una sola sentencia atómica; Suprimida definitivo; sin borrado ni reactivación; el estado, el código, el ocupante y el puesto (al editar) nunca vienen del cliente; este módulo nunca escribe `user_id` ni pasa Ocupada ⇄ Vacante; edición concurrente rechazada con `version`; guardar sin cambios reales no altera nada; quién creó y modificó solo lo ve el Administrador; un Usuario nunca recibe posiciones Suprimidas en el listado; rol verificado en el backend; otros módulos solo acceden por la Facade; interfaz en español, WCAG 2.2 AA

**Scale/Scope**: Cientos a pocos miles de posiciones, paginadas de 10 en 10; 6 operaciones HTTP (sin `DELETE`), 1 pantalla (listado con filas desplegables y filtros), 2 modales y 1 diálogo de confirmación; unas 14 posiciones sembradas

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Código en `backend/app/Modules/Estructura/` con Domain, Application, Infrastructure y Presentation; casos de uso explícitos (`ListarPosiciones`, `ObtenerPosicion`, `ListarOpcionesDePosicion`, `CrearPosicion`, `ActualizarPosicion`, `SuprimirPosicion`); controlador delgado; persistencia por `PosicionRepositoryInterface`. Las reglas puras (árbol de supervisión, subordinadas, comparación de cambios) viven en servicios de dominio sin Laravel. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (patrón aceptado en 000–005): `/api/posiciones` por indicación del insumo. Ver Complexity Tracking. |
| I. Admin = Sanctum + capacidad `admin` | PASA, **sustituyendo** los permisos `posiciones.crear/editar/suprimir` del insumo (no existen tablas de roles ni permisos). Crear, actualizar, suprimir y `opciones` usan `auth:sanctum` + `admin`; listar y consultar solo `auth:sanctum`. La visibilidad de Suprimidas y de la auditoría se decide en el borde según la capacidad del token. |
| I. Un módulo no accede a clases o tablas de otro | PASA. Las relaciones Eloquent (`Posicion → Puesto`, `→ Unidad`, `→ Posicion`) son internas de `Estructura`. El **ocupante** y los nombres de la auditoría se obtienen por el puerto ya existente `NombresDeUsuariosInterface` (adaptador sobre `SharedFacade::getNombresDeUsuarios`); `user_id` es un entero sin lectura directa de `users`. Los demás módulos solo usan `EstructuraFacade`. |
| I. Validación y autorización en los límites | PASA. Forma, enumeraciones y campos prohibidos en `GuardarPosicionRequest`; reglas que dependen de la estructura (ciclos, máxima autoridad, puesto y unidad activos, supervisor vigente) en el repositorio dentro de la transacción de escritura. |
| I. Auditoría | PASA. El actor sale del token; el repositorio escribe la posición y el historial en una sola transacción, con un comparador de dominio puro. |
| I. Migraciones, factories y seeders según convención | PASA. Una migración, factory de posiciones y `PosicionesSeeder` (depende de puestos, unidades y usuarios). |
| I. Pest, Pint | PASA. Pruebas Unit (árbol de supervisión, comparador, casos de uso) y Feature (API, repositorio, atomicidad, concurrencia). |
| II. Frontend modular, `shared` sin módulos | PASA. Código en `frontend/src/modules/estructura/posiciones/`. Se reutilizan `Modal`, `ConfirmDialog`, `Pagination`, `SearchField`, `SelectField`, `Badge`, `Button`, `Alert`, `EmptyState`, `ErrorState` y `Skeleton` de `shared/ui`; ningún componente compartido nuevo. |
| II. Una funcionalidad no importa internals de otra | PASA. `posiciones` no importa de `puestos` ni de `unidades`: tipos, textos, guarda y parámetros propios; el selector de unidad usa la API de unidades por HTTP y el rol llega por el contrato público `modules/auth/login`. |
| II. Axios solo en cliente compartido; TanStack Query; escrituras sin reintento; filtros en URL | PASA. Búsqueda, estado, unidad y página en `useSearchParams`; mutaciones sin reintentos. |
| II. App Shell, estados, formularios, confirmación accesible, WCAG | PASA. Estados cargando/vacío/error/éxito; modales con foco atrapado; `ConfirmDialog` al suprimir; el estado se comunica con texto además del color. |
| II. E2E real por feature | PASA. `frontend/e2e/posiciones.spec.ts` con Playwright. |
| III. `design/` como fuente | **AJUSTE ANTES DE IMPLEMENTAR**. El insumo habla de un tablero "Posiciones" que **no existe en `design/`** (el menú de los demás tableros solo enlaza a `Posiciones.dc.html`). Hay que crearlo, registrarlo en `canvas.json` y documentar en `DESIGN.md` el badge Vacante (ámbar) y el filtro de unidad en árbol. Es la primera tarea de frontend. |
| IV. Dependencias | PASA. Sin dependencias nuevas. |
| IV. Pruebas, seguridad | PASA. Validación en backend y frontend; rol verificado en cada petición; errores sin detalles internos. |

**Re-evaluación tras Fase 1**: sin cambios en los veredictos. El diseño añade la columna `version`, un índice único parcial, la migración de la tabla y el tablero de diseño que el insumo no contemplaba, y cambia dos reglas del insumo por exigencia de la spec (auditoría solo para el Administrador, filtro de estado con "Vigentes"); todo queda en Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/006-posiciones/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── checklists/requirements.md
├── contracts/
│   ├── posiciones-api.md
│   └── estructura-facade.md
└── tasks.md             # Lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Estructura/
│   ├── Domain/
│   │   ├── Entities/Posicion.php
│   │   ├── Repositories/PosicionRepositoryInterface.php
│   │   ├── Services/{ArbolDeSupervision,ComparadorDeCambiosDePosicion,JerarquiaDeUnidades}.php   (reglas puras, sin Laravel; JerarquiaDeUnidades usa ArbolDeUnidades de 005)
│   │   ├── ValueObjects/{Modalidad,DatosPosicion,NodoDePosicion,InstantaneaDePosicion,FiltroDePosiciones,PaginaDePosiciones,OpcionesDePosicion}.php
│   │   └── Exceptions/{PosicionNoEncontrada,PosicionModificada,PosicionInvalida,PosicionNoSuprimible}.php
│   ├── Application/
│   │   ├── UseCases/{ListarPosiciones,ObtenerPosicion,ListarOpcionesDePosicion,CrearPosicion,ActualizarPosicion,SuprimirPosicion}.php
│   │   ├── DTO/{PosicionDTO,PosicionDetalleDTO,ListadoPosicionesDTO,FiltroPosicionesDTO,OpcionesDePosicionDTO}.php
│   │   ├── Ports/NombresDeUsuariosInterface.php           (existente: + idsPorNombre para buscar por ocupante)
│   │   └── Services/EstructuraService.php                 (existente: + Facade de posiciones)
│   ├── Infrastructure/
│   │   ├── Persistence/Eloquent/
│   │   │   ├── Models/PosicionModel.php                   (existente: + relaciones y campos)
│   │   │   └── Repositories/{EloquentPosicionRepository,EloquentUnidadRepository}.php   (la segunda existe: + validar la reubicación contra las posiciones, FR-038)
│   │   ├── Adapters/SharedNombresDeUsuarios.php           (existente: + idsPorNombre sobre SharedFacade)
│   │   └── Providers/EstructuraServiceProvider.php        (existente: + binding)
│   ├── Presentation/
│   │   ├── Controllers/PosicionController.php
│   │   ├── Requests/{GuardarPosicionRequest,ListarPosicionesRequest,OpcionesDePosicionRequest}.php
│   │   ├── Resources/{PosicionResource,PosicionDetalleResource,OpcionesDePosicionResource}.php
│   │   └── Facades/EstructuraFacade.php                   (existente: + métodos)
│   └── Routes/api.php                                      (existente: + /api/posiciones)
├── app/Modules/Shared/                                     (existente: + SharedFacade::buscarIdsDeUsuariosPorNombre y su servicio)
├── database/
│   ├── migrations/2026_10_16_000001_complete_posiciones_table.php
│   ├── factories/PosicionFactory.php
│   └── seeders/{PosicionesSeeder,DatabaseSeeder}.php
└── tests/{Unit,Feature}/Estructura/Posiciones/…

frontend/src/
├── modules/estructura/posiciones/
│   ├── api.ts, text.ts, validation.ts, nav.tsx
│   ├── hooks/{usePosiciones,useListadoParams,useGuardarPosicion,useSuprimirPosicion}.ts   (usePosiciones incluye lista, detalle y opciones)
│   ├── pages/PosicionesPage.tsx                           (listado, filtros, paginador y modales)
│   └── components/{PosicionesList,PosicionFila,PosicionDetalle,PosicionModal,SuprimirDialog,FiltroDeUnidad,RequireAdmin}.tsx
├── shared/ui/icons.tsx                                      (+ IconUsers para el menú si falta)
├── test/posicionesFixtures.ts
├── App.tsx                                                  (ruta /posiciones y navegación)
└── e2e/posiciones.spec.ts
design/{Posiciones.dc.html,DESIGN.md,canvas.json}           (crear y actualizar primero)
```

**Structure Decision**: Aplicación web con backend y frontend separados, en el módulo `Estructura` (backend) y `modules/estructura/posiciones/` (frontend), como indica el insumo. Comparte con `puestos` y `unidades` solo la base de datos del módulo (`posiciones`, `puestos`, `unidades_organizacionales` y `auditoria_cambios`) y los componentes de `shared/ui`. Una sola ruta de interfaz (`/posiciones`).

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Ruta `/api/posiciones` en el módulo `Estructura` (la constitución pide `/api/<modulo>/`) | El insumo fija `/api/posiciones` y es el recurso que consumen otros módulos | `/api/estructura/posiciones` contradice el contrato indicado |
| Capacidad `admin` en lugar de los permisos `posiciones.crear/editar/suprimir` y de las tablas de roles y permisos | No existen en el repositorio (`users` usa una columna `role`); la constitución define la autorización administrativa con Sanctum + `admin` y 001–005 la cumplen así | Implantar permisos por acción es otra feature; los tres permisos se asignan a un único rol, así que el comportamiento es idéntico; los seeders no dependen de roles ni permisos |
| Una migración **nueva** completa `posiciones` (el insumo dice "no se crean tablas ni columnas nuevas") | En el repositorio `posiciones` es la tabla mínima de 004 con la unidad añadida en 005; faltan `codigo`, `user_id`, `posicion_jefe_id`, `modalidad`, `version`, `created_by` y `updated_by`. Se agrega por migración (no se edita la de 004/005) porque la tabla ya tiene filas sembradas | Editar las migraciones anteriores rompería bases ya migradas; recrear la tabla invade 004 y 005 |
| `unidad_organizacional_id` y `codigo` quedan **anulables** en la base (el DBML los define obligatorios) | Las filas sembradas por 004 no tienen unidad ni código y SQLite no permite añadir una columna NOT NULL sin valor ni modificarla sin recrear la tabla; la aplicación los exige siempre y el seeder de 006 completa todas las filas | Recrear la tabla con restricciones estrictas es un cambio grande para un beneficio nulo mientras la API sea la única vía de escritura |
| Columna `version` en `posiciones` (no está en el DBML) | FR-029 exige rechazar el segundo guardado concurrente; además es el primer paso de escritura que serializa la edición | Comparar `updated_at` falla con resolución de 1 s |
| Índice único **parcial** de la máxima autoridad (`posicion_jefe_id` nulo y estado distinto de Suprimida) con SQL directo | FR-008 y SC-005: nunca dos máximas autoridades, ni siquiera con dos peticiones simultáneas; SQLite ignora `lockForUpdate` | Bloquear filas (insumo): SQLite no lo soporta; validar solo en PHP: no es atómico |
| Escrituras serializadas con "primero una escritura" y suprimir como **una sola sentencia condicional** (en lugar de `lockForUpdate`/`sharedLock` del insumo) | SQLite ignora los bloqueos de filas; es la técnica de 004 (D-09) y 005 (D-06, D-09) | `lockForUpdate` no protege en SQLite |
| Código `POS-####` derivado del `id` (en lugar de "consecutivo con reintento") | Es la técnica de 004 (D-08) y 005 (D-05): sin carrera ni reintentos | El cálculo con reintento añade código y una condición de carrera |
| Auditoría (quién creó y modificó) solo para el Administrador, en contra del insumo (cualquier autenticado) | La clarificación de la spec (2026-10-15) lo fija así | Seguir el insumo contradice la spec |
| Filtro de estado con "Vigentes" como valor predeterminado y "Todas" solo para el Administrador | La spec (clarificación) lo fija; el insumo admite `Todas` para cualquiera (tratada como Vigentes) | Mantener el contrato del insumo cambia lo que ve el Usuario sin decirlo |
| Se crea el tablero `design/Posiciones.dc.html` (el insumo lo da por existente) | No hay tablero de posiciones en `design/` y la constitución III exige diseño antes de implementar | Implementar sin diseño contradice la constitución |
| Se modifica la feature 005 (unidades): reubicar una unidad se rechaza si dejaría una posición reportando a otra de una unidad inferior o de otra rama (FR-038) | El insumo supone que la unidad padre no cambia; en 005 sí se puede reubicar, y sin esta validación la jerarquía se rompe después de crear la posición | Aceptar el riesgo contradice la spec (FR-007, SC-012); impedir toda reubicación elimina una función de 005 |
| Se amplía el puerto `NombresDeUsuariosInterface` y la Facade de `Shared` (`buscarIdsDeUsuariosPorNombre`) | La búsqueda por ocupante (FR-020) necesita encontrar usuarios por nombre sin que `Estructura` lea la tabla `users` | Un `join` a `users` desde `Estructura` viola la regla de módulos |
| Índice único parcial sobre «sin supervisor» (no sobre «unidad raíz») | La regla de la spec es «sin supervisor si y solo si raíz», que la aplicación impone; un índice sobre la raíz exigiría una subconsulta que SQLite no admite en índices | Quitar el índice deja la máxima autoridad única solo a cargo de PHP |
| Badge Suprimida con el color de peligro (`--danger`, `--dangersoft`) y Vacante con `--warning`; se añaden las variantes `danger` y `warning` a `Badge` | El tablero "Posiciones" del insumo usa esos colores; las variantes son del componente compartido | Colorear en el módulo duplica el componente |
| Seeder de posiciones con puestos repetidos (solo hay 4 puestos activos) | Reproducir 12 unidades con la estructura pedida exige más posiciones que puestos activos; no se amplía el catálogo de 004 | Añadir puestos a `PuestosSeeder` cambia datos y pruebas de 004 |

## Diseño de frontend

### Rutas

| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/posiciones` | Listado de posiciones con filas desplegables y, sobre él, los modales de posición y el diálogo de supresión | Administrador y Usuario (el Usuario solo ve Ocupadas y Vacantes y no ve ninguna acción de gestión) |

No hay rutas de formulario: crear y editar abren un modal. `RequireAdmin` protege la renderización de botones y modales, no una ruta.

### Componentes

- **Reutilizados de `shared/ui`**: `Modal`, `ConfirmDialog`, `Pagination`, `SearchField`, `SelectField` (opciones deshabilitadas), `Badge`, `Button`, `Alert`, `EmptyState`, `ErrorState`, `Skeleton`.
- **Nuevos, solo del módulo**: `PosicionesPage`, `PosicionesList`, `PosicionFila` (toda la fila es el botón que despliega, `aria-expanded`/`aria-controls`), `PosicionDetalle`, `PosicionModal` (crear y editar), `SuprimirDialog`, `FiltroDeUnidad` y `RequireAdmin`. El badge **Vacante** (ámbar) usa `--warning`/`--warningsoft`; Ocupada usa el estilo normal y **Suprimida la variante de peligro** (`--danger`/`--dangersoft`, como el tablero "Posiciones"). Las variantes `warning` y `danger` se añaden a `Badge`.
- **Accesibilidad**: el estado se muestra con texto; la fila desplegable sigue el patrón de `Puestos.dc.html` (punto 10); los motivos de Suprimir deshabilitado van en una descripción accesible.

### Datos y estado

- Listado: `GET /api/posiciones?q=&estado=&unidad_organizacional_id=&page=&per_page=10`; clave `["posiciones","lista",{…}]`. Todo en la URL (`useSearchParams`), con retardo de 300 ms al escribir; `estado` solo se escribe si no es `Vigentes` y solo para el Administrador; el Usuario nunca envía `estado`.
- Detalle: `GET /api/posiciones/{id}` al desplegar la fila (clave `["posiciones","detalle",id]`).
- Modales: `GET /api/posiciones/opciones[?excluir=id]` siempre fresco al abrir; al editar, el detalle fresco con la `version` congelada al abrir.
- Selector del filtro de unidad: `GET /api/unidades` (árbol con sangría por `nivel_arbol`).
- Crear: `POST`; editar: `PUT` con `version`; suprimir: `POST …/suprimir`. Mutaciones sin reintentos; tras éxito se invalidan `["posiciones"]`, se cierra el modal y se muestra el mensaje.

### Validación

- Puesto, unidad y modalidad obligatorios; supervisor obligatorio salvo en la unidad raíz (donde queda fijo en «Sin supervisor (máxima autoridad)»). Mensajes: "Selecciona el puesto.", "Selecciona la unidad organizacional.", "Selecciona el supervisor.", "Selecciona la modalidad.".
- Errores 422 a campos; 409 a alerta con "Recargar"; 404 cierra el modal con aviso; 422 de suprimir al diálogo.

## Revisión 2026-10-16: jerarquía de unidades y unidad raíz

Cambios que introduce la actualización del insumo y del spec, sobre lo ya implementado en la primera versión. Las decisiones están razonadas en [research.md](research.md) (D-19 a D-26).

### Backend

- **Servicio de dominio `JerarquiaDeUnidades`** (puro): dado el árbol de unidades (`ArbolDeUnidades`, ya existente), responde si una unidad es la misma o superior a otra (`esIgualOSuperior`), cuáles son sus superiores y cuál es la raíz. Lo usan el repositorio de posiciones (validar) y las opciones (calcular `superiores` y `unidades_minimas`).
- **Reglas de crear y actualizar** (dentro de la transacción, después del primer enunciado de escritura): la raíz es la unidad sin padre; si la unidad es la raíz, el supervisor debe ser nulo y no puede haber otra posición vigente en ella (422 «La Gerencia General ya tiene su posición (máxima autoridad).»); si no es la raíz, el supervisor es obligatorio («Selecciona el supervisor.»), su unidad debe ser la misma o una superior («El supervisor debe pertenecer a la misma unidad o a una unidad superior.»); al actualizar, la unidad nueva debe ser la misma o superior a la de cada subordinada directa vigente (422 con el motivo y cuántas lo impiden); la máxima autoridad solo admite cambiar la modalidad (unidad y supervisor distintos de los actuales dan 422).
- **Listado**: cada elemento incluye `ocupante` (`{id, nombre}` o nulo) además de lo anterior; la búsqueda `q` cubre código, puesto, nombre de la unidad, código o puesto del supervisor y nombre del ocupante (este último con `idsPorNombre` del puerto); la respuesta incluye el total.
- **Detalle**: `Supervisa a` incluye el ocupante de cada subordinada.
- **Opciones**: las unidades añaden `unidad_padre_id`, `superiores` (ids de la padre a la raíz), `habilitada` (falsa si está inactiva, o si es la raíz y ya tiene una posición vigente distinta de la excluida) y `motivo_deshabilitada` (`inactiva` o `raiz_ocupada`); las posiciones candidatas añaden `unidad_organizacional_id` y `unidad` (nombre); con `excluir` se añade `unidades_minimas` (ids de las unidades de sus subordinadas directas vigentes).
- **Unidades (005)**: `EloquentUnidadRepository::actualizar` rechaza una reubicación si, en el árbol resultante, alguna posición vigente de la rama movida, o cuyo supervisor esté en ella, ya no tiene a su supervisora en la misma unidad o en una superior (422 en `unidad_padre_id`).
- **Seeder**: se añade una Vacante de «Desarrollador de software» en Desarrollo Web que reporta a la Ocupada de la misma unidad (supervisión dentro de una misma unidad), manteniendo la sección que reporta a la gerencia (unidad superior no inmediata) y las dos ramas distintas.

### Frontend

- **Listado**: columnas Código, Puesto, Unidad organizacional, **Reporta a** (código y, debajo, puesto; «—» y «Máxima autoridad»), **Ocupante** (nombre o «Sin ocupante» atenuado y en cursiva), Estado y Modalidad; etiqueta de búsqueda «Buscar», marcador «Código, puesto, unidad u ocupante» y contador «N posiciones» en una región `aria-live` bajo la búsqueda.
- **Detalle**: Supervisor muestra «Máxima autoridad» si no tiene; «Supervisa a» con código, puesto, ocupante y estado.
- **Modal**: selector de unidad con `habilitada`/`motivo_deshabilitada` (en edición, también deshabilita las que no sean iguales ni superiores a todas las `unidades_minimas`, «Tiene subordinadas en unidades superiores a esta»); selector de supervisor deshabilitado hasta elegir la unidad, con grupos `optgroup` por unidad desde la elegida hasta la raíz, opción «código · puesto · ocupante», fijo en «Sin supervisor (máxima autoridad)» si la unidad es la raíz, vaciado si deja de ser válido y mensaje «No hay posiciones en esta unidad ni en sus unidades superiores» si no hay candidatos; en la máxima autoridad, unidad y supervisor en solo lectura.
- **Diseño**: actualizar `design/Posiciones.dc.html` y `DESIGN.md` antes de tocar el frontend (columnas, contador, selectores agrupados, badge Suprimida de peligro).
