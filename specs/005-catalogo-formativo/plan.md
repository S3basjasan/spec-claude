# Implementation Plan: Catálogo formativo de competencias

**Branch**: `005-catalogo-formativo` | **Date**: 2026-10-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/005-catalogo-formativo/spec.md`, con el insumo técnico `plan-catalogo.md` (carpeta `specify y plan`)

## Summary

Capa formativa sobre el diccionario de competencias. El Administrador ve una **matriz** de competencias con su área formativa y
la cantidad de cursos y micro-contenidos asociados, el indicador de **cobertura formativa**, y un **panel lateral** donde asigna
el área, asocia y quita cursos y micro-contenidos y guarda en una sola operación. El Colaborador ve **tarjetas agrupadas por
área formativa** con su nivel actual (A/B/C/D), el detalle con los recursos disponibles y la sección **Recomendados para cerrar
mis brechas**. Todo cambio queda en un historial inmutable.

Es el **primer código del módulo `Desarrollo`** (backend `backend/app/Modules/Desarrollo`, frontend
`frontend/src/modules/desarrollo/`). Las competencias se leen solo por `EstructuraFacade`. El nivel actual del colaborador y las
brechas activas provienen de módulos que **aún no existen** (Gestión de Personal y DNF): se consumen mediante dos puertos del
módulo con adaptadores provisionales que devuelven "sin datos", de modo que el catálogo funciona hoy y se conecta después sin
tocar casos de uso ni pantallas. La interfaz sigue `design/CatalogoAdmin.dc.html` y `design/CatalogoColaborador.dc.html` como
guía visual, sobre los componentes y patrones de `design/DESIGN.md`, para quedar en sintonía con Competencias, Comportamientos,
Preguntas y Puestos.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript 6 estricto + React 19 en frontend

**Primary Dependencies**: Laravel Sanctum, React Router, TanStack Query, Axios, React Hook Form, Zod, Tailwind CSS 4, Vitest,
Testing Library, jsdom y Playwright (todos ya instalados y aprobados). Ninguna dependencia nueva.

**Storage**: SQLite. Tablas nuevas `areas_formativas`, `competencia_formativa`, `competencia_curso`,
`competencia_microcontenido`, `competencia_formativa_historial`, más `cursos` y `microcontenidos` **mínimas** (ver Complexity
Tracking). No se tocan las tablas de Estructura.

**Testing**: Pest (Feature y Unit); Vitest + Testing Library (jsdom); E2E con Playwright. Pint disponible; no hay análisis
estático instalado y no se añade (Constitución IV).

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: matriz y vista del colaborador en menos de 3 s con 200 competencias y 1.000 asociaciones (SC-007);
asociar un curso desde la matriz en menos de 1 min (SC-002)

**Constraints**: competencias solo por `EstructuraFacade` (sin FK ni acceso a sus tablas); nuevas asociaciones solo con
competencias activas; sin duplicados (restricción única + validación); quitar elimina solo el vínculo; guardado atómico;
cobertura sobre recursos **disponibles** (no archivados); un solo rol por usuario; historial inmutable y sin entradas cuando
no hay cambios; edición concurrente rechazada con `version`; el colaborador solo ve activas, recursos disponibles y lo propio;
degradación parcial si faltan Gestión de Personal, DNF o micro-contenidos; interfaz en español, WCAG 2.2 AA

**Scale/Scope**: cientos de competencias, miles de asociaciones; 4 áreas sembradas; 2 vistas principales (matriz con panel
lateral, y tarjetas con detalle) y 9 operaciones HTTP

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Nuevo módulo `Desarrollo` con Domain, Application, Infrastructure y Presentation; un caso de uso por acción; controlador delgado; persistencia por `CatalogoFormativoRepositoryInterface`. Las reglas puras (cobertura, validación de la configuración, comparación para el historial) viven en Domain sin Laravel. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (patrón ya aceptado en 000–004). Prefijo `/api/catalogo-formativo` por contrato del insumo. Ver Complexity Tracking. |
| I. Admin = Sanctum + capacidad `admin` | PASA. Escritura, cobertura, historial y gestión de áreas usan `auth:sanctum` + `admin`; el resto, solo `auth:sanctum`. La visibilidad (inactivas, recursos archivados, nivel propio) se decide en el borde por la capacidad `admin` del token. Un usuario tiene un único rol (`Rol`), sin alternancia (FR-005b). |
| I. Un módulo no accede a clases/tablas de otro | PASA. Competencias: solo `EstructuraFacade`. Nivel del colaborador y brechas: **puertos propios** (`NivelesDeColaboradorInterface`, `BrechasActivasInterface`) cuyo adaptador futuro llamará a las Facades de Gestión de Personal y DNF. Nombres de usuario para la auditoría: tercer puerto `NombresDeUsuarioInterface`, cuyo adaptador usa `SharedFacade::getNombresDeUsuarios` (ampliación aditiva de Shared); `Desarrollo` **no lee** la tabla `users`. `competencia_id` y `user_id` son enteros sin FK entre módulos. |
| I. Validación y autorización en los límites | PASA. Formas, ids repetidos y existencia en `GuardarConfiguracionRequest`; competencia activa, recursos disponibles y áreas en el caso de uso con una regla de dominio pura. |
| I. Auditoría en los límites | PASA. El actor sale del token en el controlador; el repositorio escribe la configuración, los vínculos y el historial en una sola transacción. |
| I. Migraciones, factories y seeders | PASA. Siete migraciones, factories de áreas, cursos y micro-contenidos, y `CatalogoFormativoSeeder` (depende de `CompetenciasSeeder`). |
| I. Pest, Pint | PASA. Unit (cobertura, reglas de configuración, comparador del historial) y Feature (API, repositorio, degradación de puertos). |
| II. Frontend modular, `shared` sin módulos | PASA. Código en `frontend/src/modules/desarrollo/catalogo-formativo/`. Reutiliza `shared/ui`; ningún componente compartido nuevo salvo los marcados en "Diseño de frontend" como adiciones a `design/DESIGN.md`. |
| II. Una funcionalidad no importa internals de otra | PASA. No importa de `estructura/*`; tipos, textos y guardas propios; el rol llega por el contrato público `modules/auth/login`. |
| II. Axios solo en cliente compartido; TanStack Query; escrituras sin reintento; filtros en URL | PASA. Búsqueda, filtros y página en `useSearchParams`; mutaciones sin reintentos. |
| II. App Shell, estados, formularios, confirmación accesible, WCAG | PASA. Estados cargando/vacío/error/éxito en matriz, panel, tarjetas y detalle; `ConfirmDialog` al quitar un recurso; panel lateral como `dialog` modal con foco atrapado. |
| II. E2E real por feature | PASA. `frontend/e2e/catalogo-formativo.spec.ts` (administrador y colaborador). |
| III. `design/` como fuente | **AJUSTE ANTES DE IMPLEMENTAR**. Los dos tableros cubren la matriz, el panel y las tarjetas, pero difieren de la spec y no cubren varios elementos. `design/DESIGN.md` §4, §5 y §9 se actualizan **primero** (ver "Diseño de frontend → Guía visual" y la primera tarea de frontend). |
| IV. Dependencias | PASA. Sin dependencias nuevas; íconos como SVG propios en `shared/ui/icons.tsx`, igual que 004. |
| IV. Pruebas, seguridad | PASA. Validación en backend y frontend; errores sin detalles internos; el colaborador nunca recibe datos de gestión ni ajenos. |

**Re-evaluación tras Fase 1**: sin cambios en los veredictos. El diseño añade tablas de recursos mínimas, puertos con
adaptadores provisionales y tres ajustes sobre el insumo; todo queda en Complexity Tracking y en "Cambios respecto al insumo".

## Cambios respecto al insumo (`plan-catalogo.md`)

El insumo se respeta en lo esencial (tablas, endpoints, módulo y estructura). Estos puntos se ajustan por las aclaraciones ya
integradas en la spec o por la realidad del repositorio:

| # | Insumo | Plan | Motivo |
|---|---|---|---|
| A-01 | Solo `GET /areas` | Se añaden `POST /areas` y `PATCH /areas/{areaId}` (crear y renombrar), solo Administrador; sin `DELETE` | Spec Q2 y FR-009: las áreas se crean y renombran, no se eliminan |
| A-02 | `tipo_cambio`: `Area \| Curso \| Microcontenido` y acción `Cambiado` | Se añade `AreaFormativa` como tipo y `Renombrado` como acción; `recurso_id` apunta al área; `competencia_id` pasa a ser **nullable** | Spec Q3 y FR-037: el renombrado de un área afecta a varias competencias y no pertenece a una sola |
| A-03 | Cobertura: "recursos disponibles" solo en contadores del listado | Cobertura, contadores y flag `cubierta` cuentan solo recursos **disponibles** para todos los roles; el detalle del Administrador sí lista los archivados, señalados | Spec Q1 y FR-017/FR-020 |
| A-04 | "Rol Administrador / colaborador" | Dos roles exclusivos (`admin` y `user`); sin ruta ni botón para alternar | Spec Q4 y FR-005b |
| A-05 | `PUT` reemplaza la configuración | Se exige `version` (bloqueo optimista) y el PUT devuelve la configuración guardada | Edge case de edición concurrente de la spec |
| A-06 | `GET /{competenciaId}/historial` solo para cambios de la competencia | Incluye también los renombrados de las áreas que la competencia tuvo asignadas | Spec FR-037 |
| A-07 | Botones "Iniciar"/"Ver" sin destino definido | Cada recurso expone `url_destino` (nullable); sin destino, el botón sale deshabilitado con "Próximamente" | Spec Q5 y FR-031 |
| A-08 | Seeder "depende de los seeders de cursos y micro-contenidos" | Esas features no existen; este plan crea tablas **mínimas** `cursos` y `microcontenidos` con sus factories y siembra demostrativa | Ver Complexity Tracking |
| A-09 | Facades de Gestión de Personal y DNF | Puertos propios con adaptadores provisionales ("sin dato") hasta que esos módulos existan | Esos módulos no existen en el repositorio |
| A-10 | Sin endpoint para listar recursos asociables | `GET /api/catalogo-formativo/recursos-disponibles` (Administrador) para los selectores "Asociar curso" y "Asociar contenido" | FR-024 |
| A-11 | Competencias leídas con `EstructuraFacade` directamente | Puerto `CompetenciasInterface` con adaptador `CompetenciasDesdeEstructura` (Application no depende de la Facade de Laravel) | Constitución I: Dominio y Aplicación sin Laravel |
| A-12 | Prueba de 001 `HistorialInmutableTest` prohibía cualquier ruta con "historial" | Se acota a `api/competencias` | Esta feature expone `/{id}/historial` por contrato |

## Project Structure

### Documentation (this feature)

```text
specs/005-catalogo-formativo/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── checklists/requirements.md
├── contracts/
│   ├── catalogo-formativo-api.md
│   ├── desarrollo-puertos.md
│   ├── estructura-facade-ampliacion.md
│   └── shared-facade-ampliacion.md
└── tasks.md             # Lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Desarrollo/                                  (módulo nuevo)
│   ├── Domain/
│   │   ├── Entities/ConfiguracionFormativa.php
│   │   ├── Repositories/CatalogoFormativoRepositoryInterface.php
│   │   ├── Services/{CalculadorDeCobertura,ReglasDeConfiguracionFormativa,ComparadorDeCambiosFormativos}.php   (puras, sin Laravel)
│   │   ├── ValueObjects/{TipoRecurso,EstadoRecurso,AccionHistorial,TipoCambioHistorial,NivelGrado,Cobertura,CambioHistorial,DatosConfiguracion}.php
│   │   └── Exceptions/{ConfiguracionModificada,CompetenciaNoEncontrada,CompetenciaInactiva,RecursoNoDisponible,AreaNoEncontrada,NombreDeAreaDuplicado}.php
│   ├── Application/
│   │   ├── Ports/{NivelesDeColaboradorInterface,BrechasActivasInterface,NombresDeUsuarioInterface}.php
│   │   ├── UseCases/{ListarCatalogo,ObtenerDetalleFormativo,ActualizarConfiguracionFormativa,ObtenerCobertura,
│   │   │             ListarRecomendados,ListarRecursosDisponibles,ListarAreas,CrearArea,RenombrarArea,ListarHistorial}.php
│   │   ├── DTO/{FiltroCatalogoDTO,FilaCatalogoDTO,ListadoCatalogoDTO,DetalleFormativoDTO,RecursoDTO,AreaDTO,CoberturaDTO,RecomendadoDTO,HistorialDTO}.php
│   │   └── Services/DesarrolloService.php                   (Facade del módulo: consulta por competencia para la DNF)
│   ├── Infrastructure/
│   │   ├── Persistence/Eloquent/
│   │   │   ├── Models/{AreaFormativaModel,CompetenciaFormativaModel,CompetenciaCursoModel,CompetenciaMicrocontenidoModel,
│   │   │   │           HistorialFormativoModel,CursoModel,MicrocontenidoModel}.php
│   │   │   └── Repositories/EloquentCatalogoFormativoRepository.php
│   │   ├── Adapters/{NivelesDeColaboradorSinDatos,BrechasActivasSinDatos,NombresDeUsuarioDesdeShared}.php   (los dos primeros provisionales; el tercero usa SharedFacade; ver contratos)
│   │   └── Providers/DesarrolloServiceProvider.php          (bindings; se registra en bootstrap/providers.php)
│   ├── Presentation/
│   │   ├── Controllers/{CatalogoFormativoController,AreaFormativaController}.php
│   │   ├── Requests/{ListarCatalogoRequest,GuardarConfiguracionRequest,GuardarAreaRequest,ListarHistorialRequest}.php
│   │   ├── Resources/{FilaCatalogoResource,DetalleFormativoResource,AreaFormativaResource,RecomendadoResource,HistorialResource}.php
│   │   └── Facades/DesarrolloFacade.php
│   └── Routes/api.php                                       (/api/catalogo-formativo)
├── app/Modules/Estructura/…                                 (existente: + getTodasLasCompetencias() en Service y Facade)
├── app/Modules/Shared/…                                     (existente: + caso de uso ObtenerNombresDeUsuarios y getNombresDeUsuarios() en Service y Facade)
├── database/
│   ├── migrations/2026_10_14_00000{1..7}_create_{areas_formativas,cursos,microcontenidos,competencia_formativa,competencia_curso,competencia_microcontenido,competencia_formativa_historial}_table.php
│   ├── factories/{AreaFormativaFactory,CursoFactory,MicrocontenidoFactory}.php
│   └── seeders/{CatalogoFormativoSeeder,DatabaseSeeder}.php
└── tests/{Unit,Feature}/Desarrollo/…

frontend/src/
├── modules/desarrollo/catalogo-formativo/
│   ├── api.ts, text.ts, validation.ts, nav.tsx
│   ├── hooks/{useCatalogo,useListadoParams,useDetalleFormativo,useGuardarConfiguracion,useAreas,useRecomendados,useCobertura,useHistorial}.ts
│   ├── pages/{CatalogoAdminPage,CatalogoColaboradorPage,CatalogoPage}.tsx
│   └── components/{CoberturaIndicador,CatalogoMatriz,DetalleCompetenciaPanel,SelectorDeRecursos,RecursoItem,AreaFormativaSelect,
│                   GestionAreasDialog,HistorialLista,NivelPuntos,TarjetaCompetencia,GrupoPorArea,Recomendados,DetalleColaborador,RequireRole}.tsx
├── shared/ui/icons.tsx                                      (+ iconos del menú y de recursos, SVG propios)
├── test/catalogoFormativoFixtures.ts
├── App.tsx                                                  (rutas y navegación)
└── e2e/catalogo-formativo.spec.ts
design/
├── CatalogoAdmin.dc.html, CatalogoColaborador.dc.html       (existentes: guía visual; sin cambios de código)
└── DESIGN.md                                                (actualizar primero: §4, §5 y §9)
```

**Structure Decision**: Aplicación web con backend y frontend separados, en el **módulo nuevo `Desarrollo`** (backend) y
`modules/desarrollo/catalogo-formativo/` (frontend), como indica el insumo. `CatalogoPage` decide la vista por el rol del
token (`admin` → matriz; `user` → tarjetas); no existe interruptor entre ambas. La ruta de menú es "Formación", la misma
etiqueta de los mockups.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Rutas `/api/catalogo-formativo` (la constitución pide `/api/<modulo>/`) | El insumo fija el prefijo y la DNF lo consumirá por ese nombre | `/api/desarrollo/catalogo-formativo` contradice el contrato indicado; mismo precedente en 000–004 |
| Tablas `cursos` y `microcontenidos` mínimas creadas aquí aunque sus features son las dueñas | Las claves foráneas de `competencia_curso` y `competencia_microcontenido`, el estado Disponible/Archivado y las pruebas de cobertura necesitan registros reales; el insumo supone que ya existen | Un contador simulado no demuestra duplicados, archivados ni el selector. Sus features las amplían con migraciones propias y no deben recrearlas (mismo criterio que `posiciones` en 004) |
| Siembra demostrativa de cursos y micro-contenidos en `CatalogoFormativoSeeder` | El insumo exige 4 áreas y asociaciones con ≥ 2 competencias activas sin cobertura y ≥ 1 inactiva con vínculos | Sin recursos sembrados no se puede demostrar la matriz; las features dueñas reemplazarán esta siembra |
| Puertos con adaptadores provisionales en lugar de las Facades de Gestión de Personal y DNF | Esos módulos no existen; sin puertos el catálogo no podría mostrar nivel ni recomendados ni degradarse (FR-041) | Importar clases inexistentes no compila; omitir la sección impide probar la integración. Al llegar cada módulo solo cambia el adaptador y el binding |
| Ampliar `SharedFacade` con `getNombresDeUsuarios(array $ids)` | La auditoría debe mostrar el nombre de quien configuró; los ids de usuario no tienen FK y `users` pertenece a Shared | Leer `users` desde `Desarrollo` viola la constitución (acceso a tablas de otro módulo) |
| Ampliar `EstructuraFacade` con `getTodasLasCompetencias()` | La matriz del Administrador lista competencias activas **e inactivas**; hoy la Facade solo da las activas o las pedidas por id | Pedir ids requiere conocerlos de antemano; leer las tablas de Estructura viola la constitución |
| Columna `version` en `competencia_formativa` y `competencia_id` nullable en el historial | `version` rechaza la edición simultánea (edge case); el renombrado de un área no pertenece a una competencia | Comparar marcas de tiempo falla con resolución de 1 s; un historial aparte para áreas duplica consulta y pantalla |
| Listado paginado en memoria sobre el arreglo de la Facade | Se combinan datos de dos módulos (competencia + configuración local) con filtros por tipo, estado y nombre | Un `JOIN` entre módulos viola la constitución; con cientos de competencias el costo es despreciable (SC-007) |
| `PUT` único que reemplaza la configuración (no un endpoint por asociación) | FR-025 exige guardar de forma atómica los cambios del panel con un solo "Guardar" | Un endpoint por vínculo no es atómico y multiplica los registros de historial parciales |

## Diseño de frontend

### Guía visual y coherencia con el resto de la app

La fuente de referencia son `design/CatalogoAdmin.dc.html` (matriz, indicador de cobertura, panel lateral y selector de
recursos) y `design/CatalogoColaborador.dc.html` (recomendados, tarjetas por área y detalle). **Son guía, no código para copiar**:
`design/DESIGN.md` manda sobre los mockups en estilos, y la spec manda sobre ambos en comportamiento. Para quedar en sintonía con
las pantallas existentes:

- Misma **App Shell** (header 64 px, menú lateral 248 px, footer, tema claro/oscuro persistido) que Competencias, Comportamientos,
  Preguntas y Puestos; el módulo aporta solo el ítem **Formación** al menú.
- Mismos **tokens** (`var(--accent)`, `--surface`, `--border`, `--input-border`…), tipografía Public Sans, controles de 44 px,
  radios y sin sombra en tarjetas y tablas.
- Misma **estructura de listado** (§5 Listado): encabezado h1 + subtítulo, barra de filtros con `SearchField` + selects,
  `DataTable`, `Pagination`, estados con `Skeleton`/`EmptyState`/`ErrorState` y mensaje "Ninguna competencia coincide con la
  búsqueda." como en los demás listados.
- Mismos **componentes compartidos** de `shared/ui`: `Button`, `Badge` (variante neutra para Inactiva/Archivado), `Alert` (y
  neutro), `ConfirmDialog`, `Combobox`, `SelectField`, `SearchField`, `Pagination`, `Skeleton`, `EmptyState`, `ErrorState`,
  `Breadcrumb`. No se crean componentes equivalentes.
- Mismos **textos y tono** (§7): tú, verbos + objeto, errores con "qué pasó + qué hacer", textos centralizados en `text.ts`.

### Diferencias entre los mockups y la spec (se registran en `DESIGN.md` §9)

| Mockup | Spec vigente / decisión |
|---|---|
| Admin: botón "Ver como colaborador"; colaborador: "Ver como administrador" | Un usuario tiene un solo rol (FR-005b): **se omiten** ambos botones |
| Admin: "Cobertura por área formativa" (barras por área) | No está en la spec: **se omite**; solo el indicador global (FR-019). El enlace del mockup se conserva como "Ver sin cobertura" y lista las competencias activas sin ningún curso ni micro-contenido disponible (`cobertura=sin_oferta`) |
| Admin: columna "Nivel requerido" y encabezado del panel con el nivel | No existe en la spec ni en el modelo: **se omite** |
| Admin: tipo "Gerencial" en el filtro | Los tipos son Cardinal y Específica: se usan los del diccionario |
| Admin: "Los cambios se guardan al instante" y chip "Listo" | La spec exige botón **Guardar** y atomicidad (FR-025): se añade Guardar, advertencia de cambios sin guardar y estado "Guardando…" |
| Admin: el panel no muestra área formativa editable, historial ni última configuración | Se añaden: selector de área con creación/renombrado, "Última configuración: usuario y fecha" e historial paginado (§US4, US6) |
| Admin: menú con Perfilamiento, Empleados, Ajustes | Pertenecen a otras features: no se implementan; el ítem **Formación** se agrega al menú real |
| Colaborador: leyenda "Niveles A–D" y marca de "Nivel requerido (si tienes brecha)" en tarjetas | Las tarjetas muestran el **nivel actual** (FR-030); el nivel requerido solo aparece en Recomendados y en el detalle cuando existe brecha (dato de la DNF) |
| Colaborador: subtítulo con el puesto del usuario ("Analista de datos (PU-014)") | Requiere Gestión de Personal y no está en la spec: **se omite** |
| Colaborador: "Para empezar" (curso sugerido) en cada recomendado | No hay regla de prioridad en la spec: **se omite**; cada recomendado enlaza al detalle de su competencia |
| Colaborador: estado del curso (En curso/Completado) y botón "Continuar" | No hay seguimiento de avance en la spec: solo **Iniciar** o **Ver**; deshabilitado con "Próximamente" sin destino (FR-031) |
| Ningún mockup define: selector de área, gestión de áreas, historial, indicador de archivado, aviso de competencia inactiva en el panel, diálogo de cambios sin guardar | Se **añaden** a `DESIGN.md` §4/§5 (primera tarea de frontend) reutilizando `Combobox`, `Alert` neutro, `Badge` neutro y `ConfirmDialog` |

### Rutas

| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/formacion` | Matriz formativa con panel lateral (admin) o Catálogo formativo en tarjetas (user) | Administrador y Colaborador, cada uno con su vista |
| `/formacion?competencia=:id` | Panel de detalle abierto (admin) o detalle de tarjeta abierto (user); también la entrada de la DNF "Ver cursos sugeridos" | Ambos |

El panel y el detalle se abren sobre la misma página (sin ruta propia de pantalla completa) y su identificador viaja en la
URL para poder compartirlo y volver con "Atrás". El filtro que la DNF aplica por competencia usa `?competencia=:id`.

### Componentes

- **Reutilizados de `shared/ui`**: los listados arriba; `Pagination` y `SearchField` en la matriz.
- **Nuevos, solo del módulo**: ver la lista de `components/` en Source Code. `DetalleCompetenciaPanel` es un `dialog` modal
  lateral (foco atrapado, Escape y botón cerrar, advertencia si hay cambios sin guardar); `SelectorDeRecursos` es el selector de
  "Asociar curso"/"Asociar contenido" con búsqueda y selección múltiple; `NivelPuntos` dibuja los cuatro puntos A/B/C/D con el
  nivel resaltado y texto accesible ("Tu nivel: B"); `GestionAreasDialog` crea y renombra áreas.
- **Design system (primera tarea de frontend)**: actualizar `design/DESIGN.md` con los componentes y patrones nuevos —
  *Panel lateral (slide-over)*, *Indicador de cobertura* (barra con `role="progressbar"`), *Tarjeta de competencia con puntos de
  nivel*, *Selector de recursos con selección múltiple* e *Historial de cambios*— y las filas de §9 de la tabla anterior,
  **antes** de implementar (Constitución III).

### Datos y estado

- Matriz: `GET /api/catalogo-formativo?q=&tipo=&area_formativa_id=&cobertura=&estado=&page=&per_page=`; clave
  `["catalogo","lista",{…}]`. Búsqueda, filtros y página en la URL (`useSearchParams`), con retardo de 300 ms al escribir y vuelta
  a la página 1 al cambiar filtros. `estado` se escribe en la URL solo si es `Inactivo` o `Todos` y solo para el Administrador.
- Cobertura: `GET /api/catalogo-formativo/cobertura` (clave `["catalogo","cobertura"]`), solo Administrador.
- Detalle: `GET /api/catalogo-formativo/{competenciaId}` al abrir el panel/tarjeta (clave `["catalogo","detalle",id]`); la
  edición fuerza lectura fresca.
- Guardar: `PUT /api/catalogo-formativo/{competenciaId}` con `version`; sin reintentos; tras guardar se invalidan `["catalogo"]`
  (lista, cobertura, detalle e historial) y el panel muestra el aviso de éxito y los datos devueltos.
- Selectores: `GET /api/catalogo-formativo/recursos-disponibles?tipo=curso|microcontenido&q=` y `GET /api/catalogo-formativo/areas`.
- Áreas: `POST /api/catalogo-formativo/areas` y `PATCH /api/catalogo-formativo/areas/{areaId}`; invalidan `["catalogo","areas"]`.
- Colaborador: `GET /api/catalogo-formativo` (ya filtrado a su rol, con su nivel) y `GET /api/catalogo-formativo/recomendados`
  (la sección se oculta si devuelve vacío o falla de forma recuperable).
- Cambios locales del panel (área, vínculos agregados y quitados) viven en el estado del formulario hasta pulsar Guardar.
  Quitar un recurso abre `ConfirmDialog` ("Se quitará el vínculo con la competencia; el curso seguirá existiendo.").

### Validación

- Replica de `GuardarConfiguracionRequest`: `area_formativa_id` opcional y existente; `curso_ids` y `microcontenido_ids` sin
  repetidos; en competencia inactiva los selectores y "Asociar" quedan deshabilitados con el motivo. Nombre de área obligatorio,
  único sin distinguir mayúsculas ni espacios, con contador `n/máximo` (60).
- Mapeo de errores: 422 a campos (`area_formativa_id`, `curso_ids.N`, `microcontenido_ids.N`, `nombre`) y a la alerta general;
  409 de edición concurrente a la alerta "La configuración cambió" con "Recargar"; 404 a "ya no existe"; 422 por competencia
  inactiva al aviso neutro del panel.
- Degradación: nivel sin dato → puntos sin resaltar; recomendados vacíos o fallo de DNF → sección oculta sin error;
  micro-contenidos no disponibles → sección vacía y "Asociar contenido" deshabilitado con el motivo.
