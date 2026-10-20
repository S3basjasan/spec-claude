# Implementation Plan: Organigrama de posiciones

**Branch**: `007-organigrama` | **Date**: 2026-10-16 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/007-organigrama/spec.md`, con el insumo técnico `plan-organigrama.md` (carpeta `007-ee-organigrama`)

## Summary

Pantalla de solo lectura que dibuja la estructura de reporte como un árbol de arriba abajo a partir de las posiciones vigentes (Ocupadas y Vacantes), con una tarjeta por posición. Cualquier persona autenticada la ve igual: busca por código, puesto, unidad u ocupante, filtra por unidad (el árbol no se recorta: resalta lo que coincide y atenúa el resto), expande y contrae ramas, acerca y aleja, desplaza el árbol y abre un panel con el detalle de cada posición y enlaces a su supervisor y a sus subordinadas. Las Suprimidas nunca se muestran.

El backend añade **un solo endpoint** `GET /api/organigrama` en el módulo `Estructura`: devuelve dos listas planas (posiciones vigentes y unidades activas) con todo lo que necesitan las tarjetas, el panel y el filtro, y comprueba antes que las posiciones vigentes formen un único árbol (si no, 500 con mensaje seguro). El árbol, la búsqueda, el filtro, las ramas, el zoom y la selección viven en el cliente. El frontend añade `modules/estructura/organigrama/` con una sola pantalla y su ruta `/organigrama`. No hay tablas, migraciones, seeders ni auditoría nuevos: la feature solo lee lo que implementa 006.

El insumo supone permisos por acción, relaciones Eloquent hacia `users` y un tablero ya completo; **el repositorio real usa la capacidad `admin`, un puerto de nombres de usuario y un tablero `design/Organigrama.dc.html` que necesita ajustes**. Además, `EstructuraService` tiene hoy **un error fatal** (métodos `getPosicion` duplicados entre 006 y gestión de personal) que debe resolverse antes de implementar. Todo está en Complexity Tracking y en [research.md](research.md).

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript 6 estricto + React 19 en frontend

**Primary Dependencies**: Laravel Sanctum, React Router, TanStack Query, Axios, Tailwind CSS 4, Vitest, Testing Library, jsdom y Playwright (todos ya instalados y aprobados). Ninguna dependencia nueva (el árbol se dibuja con listas anidadas y CSS; el zoom y el arrastre con APIs del navegador, sin librería de diagramas).

**Storage**: SQLite. Solo lectura de `posiciones`, `puestos`, `unidades_organizacionales`, `tipos_unidad` y, por el puerto de nombres, `users`. Sin migraciones, sin escrituras, sin `auditoria_cambios`.

**Testing**: Pest (Feature y Unit); Vitest + Testing Library (jsdom) para la lógica del árbol y los componentes; E2E con Playwright. Pint disponible; no hay análisis estático y no se añade (Constitución IV).

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: Estructura completa visible en menos de 5 s con hasta 2000 posiciones vigentes (SC-001); encontrar una posición y abrir su detalle en menos de 20 s (SC-002); sin consultas por fila en el endpoint (una consulta de posiciones, una de unidades y una de nombres)

**Constraints**: solo lectura (sin rutas de escritura ni acciones de gestión para nadie); las Suprimidas se excluyen en la consulta, nunca en el cliente; las posiciones vigentes deben formar un único árbol (una raíz, todas descienden de ella, sin ciclos): si no, 500 con mensaje seguro y los ids en el registro del servidor; el organigrama no corrige datos ni guarda copia ni fecha; el árbol, la búsqueda, el filtro, las ramas, el zoom y la selección se calculan en el cliente sobre una sola carga; con búsqueda y filtro a la vez, coincide solo lo que cumple ambos; las ramas con coincidencias no se pueden contraer mientras dure el filtro; contraer una rama con la selección no cierra el panel; el panel es una capa superpuesta en pantallas estrechas; todo controlable con teclado; interfaz en español, WCAG 2.2 AA entre 320 y 1440 px sin desplazamiento horizontal de la página

**Scale/Scope**: Cientos a pocos miles de posiciones vigentes, 12 unidades de ejemplo en cinco niveles; 1 operación HTTP de lectura, 1 pantalla y 1 panel de detalle

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Código en `backend/app/Modules/Estructura/`: caso de uso `ObtenerOrganigrama`, servicio de dominio puro `ComprobadorDeArbol` (una raíz, todas descienden, sin ciclos), método de lectura `vigentes()` en `PosicionRepositoryInterface`, controlador delgado y resources. Dominio y aplicación sin Laravel. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (patrón aceptado en 000–006): `/api/organigrama` por indicación del insumo. Ver Complexity Tracking. |
| I. Admin = Sanctum + capacidad `admin` | PASA. El endpoint es de lectura para cualquier persona autenticada: solo `auth:sanctum`, igual que listar posiciones; no hay endpoints administrativos. |
| I. Un módulo no accede a clases o tablas de otro | PASA. Los nombres de los ocupantes se obtienen con el puerto ya existente `NombresDeUsuariosInterface` (adaptador sobre `SharedFacade`), no con la relación Eloquent a `users` del insumo. Posiciones, puestos y unidades son del mismo módulo `Estructura`. |
| I. Validación y autorización en los límites | PASA. Sin entradas del cliente (no hay parámetros); autenticación en el borde. |
| I. Auditoría | N/A. No modifica datos, por lo que no registra cambios (FR-004, spec §Auditoría). |
| I. Migraciones, factories y seeders | N/A. Sin cambios de datos; usa los seeders de 004, 005 y 006. |
| I. Pest, Pint | PASA. Unit (`ComprobadorDeArbol`, caso de uso con dobles) y Feature (endpoint). |
| II. Frontend modular, `shared` sin módulos | PASA. Código en `frontend/src/modules/estructura/organigrama/`. Se reutilizan `Modal`, `SearchField`, `SelectField`, `Badge`, `Button`, `EmptyState`, `ErrorState` y `Skeleton` de `shared/ui`; ningún componente compartido nuevo. |
| II. Una funcionalidad no importa internals de otra | PASA. `organigrama` no importa de `posiciones` ni de `unidades`: tipos, textos y parámetros propios; el enlace "Ver listado de posiciones" es una ruta. |
| II. Axios solo en cliente compartido; TanStack Query; filtros en URL | PASA. Búsqueda y unidad en `useSearchParams`; una sola consulta. No hay escrituras. |
| II. App Shell, estados, confirmación, WCAG | PASA. Estados cargando/vacío/error/éxito; el estado se comunica con texto además del color; botones reales con nombre accesible; el panel superpuesto usa `Modal` (foco atrapado, Escape, foco devuelto). No hay acciones destructivas. |
| II. E2E real por feature | PASA. `frontend/e2e/organigrama.spec.ts` con Playwright. |
| III. `design/` como fuente | **AJUSTE ANTES DE IMPLEMENTAR**. `design/Organigrama.dc.html` existe (y fue modificado) pero el insumo lista cambios que necesita (marcador de búsqueda, árbol recursivo, área enfocable, estados de carga, vacío y error) y falta documentar el patrón en `DESIGN.md`. Es la primera tarea de frontend. |
| IV. Dependencias | PASA. Sin dependencias nuevas. |
| IV. Pruebas, seguridad | PASA. Rol verificado en el borde; error 500 sin detalles internos; sin datos sensibles (no se devuelve auditoría ni correo). |

**Re-evaluación tras Fase 1**: sin cambios en los veredictos. El diseño añade un servicio de dominio de comprobación del árbol y cambia dos puntos del insumo por la realidad del repositorio (nombres por puerto, tokens de color); todo queda en Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/007-organigrama/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── checklists/requirements.md
├── contracts/
│   └── organigrama-api.md
└── tasks.md             # Lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Estructura/
│   ├── Domain/
│   │   ├── Repositories/PosicionRepositoryInterface.php   (existente: + vigentes())
│   │   ├── Services/ComprobadorDeArbol.php                (puro, sin Laravel)
│   │   └── Exceptions/OrganigramaInconsistente.php
│   ├── Application/
│   │   ├── UseCases/ObtenerOrganigrama.php
│   │   └── DTO/{OrganigramaDTO,NodoDeOrganigramaDTO}.php
│   ├── Infrastructure/
│   │   └── Persistence/Eloquent/Repositories/EloquentPosicionRepository.php   (existente: + vigentes())
│   ├── Presentation/
│   │   ├── Controllers/OrganigramaController.php
│   │   └── Resources/OrganigramaResource.php
│   └── Routes/api.php                                      (existente: + GET /api/organigrama)
└── tests/{Unit,Feature}/Estructura/Organigrama/…

frontend/src/
├── modules/estructura/organigrama/
│   ├── api.ts, text.ts, nav.tsx
│   ├── arbol.ts                                           (lógica pura: construir, descendientes, coincidencias, ramas)
│   ├── hooks/{useOrganigrama,useListadoParams,useArrastre,useEstadoDelArbol}.ts
│   ├── pages/OrganigramaPage.tsx
│   └── components/{BarraDeHerramientas,Resumen,Leyenda,AreaDelArbol,OrganigramaNodo,TarjetaDePosicion,ControlesDeZoom,PanelDeDetalle}.tsx
├── shared/ui/icons.tsx                                     (+ IconSitemap ya existe; + icono de zoom si falta)
├── App.tsx                                                 (ruta /organigrama y navegación)
└── e2e/organigrama.spec.ts
design/{Organigrama.dc.html,DESIGN.md,canvas.json}         (actualizar primero)
```

**Structure Decision**: Aplicación web con backend y frontend separados, en el módulo `Estructura` (backend) y `modules/estructura/organigrama/` (frontend), como indica el insumo. Comparte con `posiciones` solo la base de datos del módulo y los componentes de `shared/ui`. Una sola ruta de interfaz (`/organigrama`).

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Ruta `/api/organigrama` en el módulo `Estructura` (la constitución pide `/api/<modulo>/`) | El insumo fija `/api/organigrama` y es una vista del mismo módulo | `/api/estructura/organigrama` contradice el contrato indicado |
| Sin permisos específicos: basta `auth:sanctum` | El insumo y el spec dicen que todos los usuarios autenticados ven lo mismo y no existen tablas de roles ni permisos | Crear un permiso `organigrama.ver` añade una tabla y una regla sin efecto |
| Los nombres de los ocupantes se obtienen con el puerto `NombresDeUsuariosInterface` en lugar de la relación Eloquent `Posicion → User` del insumo | La constitución prohíbe que un módulo acceda a las tablas de otro; ya lo resolvimos así en 005 y 006 | Un `join` o `with('ocupante')` hacia `users` desde `Estructura` viola la regla de módulos |
| Un caso de uso y un servicio de dominio nuevos para comprobar el árbol en el servidor | El spec (FR-006) pide mostrar el estado de error si los datos no forman un único árbol, y el insumo lo hace en el backend con el servicio de árbol de supervisión de 006 | Dejarlo solo al cliente duplica la lógica y no distingue "sin datos" de "datos corruptos" |
| `ArbolDeSupervision` de 006 no basta: ignora las Suprimidas pero no detecta raíces múltiples ni nodos inalcanzables | Se necesita un servicio nuevo `ComprobadorDeArbol` (una raíz, todas descienden, sin ciclos) | Modificar `ArbolDeSupervision` mezcla dos responsabilidades y cambia código ya usado por 006 |
| Colores de tarjeta y badge con los tokens de `DESIGN.md` (`--warning`, `--warningsoft`, `--accent`, `--soft`) en lugar de los hex del insumo (`#C98A2E`, `#FFFBF4`, `#F2C27A`, `#8A4B00`, `#FDF1E1`) | Constitución III: los tokens tienen una sola representación; los hex del tablero salen de él | Duplicar hex en el módulo crea una segunda paleta |
| El panel de detalle usa `Modal` de `shared/ui` en pantallas estrechas y un `aside` fijo en las anchas | El spec (clarificación) pide una capa superpuesta con foco atrapado en pantallas estrechas; `Modal` ya lo implementa | Reescribir el foco atrapado en el módulo duplica `shared/ui` |
| Zoom con `transform: scale()` y arrastre con pointer events propios (sin librería de diagramas) | Una dependencia nueva exige aprobación explícita (constitución IV) y el árbol es solo listas anidadas con CSS | Añadir una librería de grafos para un árbol de lectura es desproporcionado |
| Se actualiza `design/Organigrama.dc.html` y `DESIGN.md` antes de implementar | El tablero existe pero no cubre el marcador de búsqueda, el árbol recursivo, el área enfocable, los estados de vista, la leyenda de Suprimidas ni el panel con enlaces | Implementar sin actualizar el diseño contradice la constitución III |
| **Requisito previo ajeno a la feature**: `EstructuraService` declara `getPosicion` y `getPosicionesPorIds` dos veces (la versión de 006 y un adelanto de gestión de personal), lo que da un error fatal de PHP al cargar el módulo | Sin resolverlo no arranca nada del backend de `Estructura`, ni siquiera el endpoint nuevo | Ignorarlo bloquea la implementación y las pruebas de esta feature |
| Preferir el repositorio sobre la Facade (`EstructuraFacade`) para leer posiciones | El organigrama está dentro de `Estructura`; la Facade es la puerta de los **otros** módulos | Usar la Facade desde el propio módulo añade un salto sin beneficio |

## Diseño de frontend

### Rutas

| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/organigrama` | Organigrama (árbol, resumen, leyenda, búsqueda, filtro, zoom, desplazamiento y panel de detalle) | Administrador y Usuario (idéntica para ambos) |

No hay rutas de formulario. El panel de detalle no tiene ruta propia: es estado de la pantalla.

### Componentes

- **Reutilizados de `shared/ui`**: `Modal` (panel en pantallas estrechas), `SearchField`, `SelectField`, `Badge` (Ocupada normal, Vacante advertencia), `Button`, `EmptyState`, `ErrorState`, `Skeleton`.
- **Panel de detalle**: `aside` junto al árbol desde **1024 px** de ancho y capa superpuesta (`Modal`) por debajo de 1024 px (`matchMedia("(max-width: 1023px)")`).
- **Nuevos, solo del módulo**: `OrganigramaPage` (carga, composición y estado); `BarraDeHerramientas` (búsqueda, unidad, Expandir todo, Contraer todo, Limpiar filtros); `Resumen` (región `aria-live`); `Leyenda`; `AreaDelArbol` (zoom, desplazamiento, `tabindex=0` con nombre accesible); `OrganigramaNodo` (recursivo, listas anidadas); `TarjetaDePosicion` (botón con `aria-pressed`); `ControlesDeZoom` (iconos `IconZoomIn` e `IconZoomOut` en `shared/ui/icons.tsx` si faltan); `PanelDeDetalle`.
- **Lógica pura** en `arbol.ts` (sin React): construir el árbol, descendientes, unidades dependientes, coincidencias (texto Y unidad), ramas forzadas y ramas contraíbles. Es lo que más pruebas unitarias necesita.
- **Accesibilidad**: tarjetas y botones de rama son botones reales; nombres «Contraer POS-0201» / «Expandir POS-0201» con `aria-expanded`; el área del árbol se desplaza con las flechas; el panel es una región «Detalle de la posición»; el estado de la posición va en texto, y la Vacante además con borde discontinuo y fondo.

### Datos y estado

- Una consulta `GET /api/organigrama` al entrar (clave `["organigrama"]`, siempre nueva al montar, sin refetch al enfocar la ventana); sin mutaciones.
- En la URL (`useSearchParams`): `q` y `unidad`. En memoria: ramas contraídas por la persona (conjunto de ids), zoom (0,5–1,5 en pasos de 0,1), posición seleccionada.
- **Ramas**: visibles = no contraídas por la persona **o** forzadas (ancestros de coincidencias y de la selección elegida desde el panel). Mientras hay filtros, los botones de las ramas con coincidencias están deshabilitados y «Contraer todo» solo contrae las demás. Contraer una rama que contiene la selección no cierra el panel. Limpiar los filtros devuelve el árbol al estado previo.
- **Coincidencia**: texto (código, puesto, unidad u ocupante, sin tildes ni mayúsculas) **y** unidad (la elegida o sus dependientes) cuando ambos están activos.
- **Desplazamiento a la primera coincidencia** al cambiar la búsqueda; **a la tarjeta seleccionada** al cambiar la selección desde el panel (`scrollIntoView` centrado, sin animación si la persona prefiere menos movimiento).

### Validación

- No hay formularios. La búsqueda se limita a 50 caracteres; el filtro solo ofrece unidades de la respuesta.
- Errores: red o 500 → estado de error «No se pudo cargar el organigrama.» con «Reintentar»; sin posiciones → estado vacío.
