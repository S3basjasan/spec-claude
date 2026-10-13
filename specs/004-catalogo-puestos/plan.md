# Implementation Plan: Catálogo maestro de puestos

**Branch**: `004-catalogo-puestos` | **Date**: 2026-10-13 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/004-catalogo-puestos/spec.md`, con el insumo técnico `plan-puestos.md` (carpeta `004-puestos`)

## Summary

Catálogo maestro de puestos: el Administrador crea, edita, activa y desactiva puestos; cualquier persona autenticada los
lista (el Usuario, solo los activos) y despliega el detalle completo de cada fila. Un puesto tiene código `PU-###`
generado por el sistema, nombre único, descripción, misión, estado y tres listas: **funciones** ordenadas (Esencial o
Complementaria), **requisitos** (Formación, Experiencia o Capacitación) y **competencias** del diccionario (001) con su
grado requerido. Desactivar solo se permite sin posiciones Ocupadas ni Vacantes; los puestos nunca se eliminan. Toda
creación y cambio queda en un historial con campo, valor anterior, valor nuevo, usuario y fecha.

El backend extiende el módulo `Estructura` con `/api/puestos` (6 operaciones, sin `DELETE`), seis casos de uso, un
repositorio propio y la ampliación de la Facade. El frontend añade `modules/estructura/puestos/` con el listado de filas
desplegables y un formulario único de crear/editar con cuatro secciones. Como el insumo da por hecho un modelo de datos y
un sistema de permisos que **no existen en el repositorio**, el plan los resuelve con migraciones nuevas y con la
capacidad `admin` (ver Complexity Tracking y [research.md](research.md)).

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript 6 estricto + React 19 en frontend

**Primary Dependencies**: Laravel Sanctum, React Router, TanStack Query, Axios, React Hook Form, Zod, Tailwind CSS 4,
Vitest, Testing Library, jsdom y Playwright (todos ya instalados y aprobados). Ninguna dependencia nueva.

**Storage**: SQLite. Tablas nuevas `puestos`, `puesto_funciones`, `puesto_requisitos`, `puesto_competencias`,
`auditoria_cambios` y una `posiciones` mínima (reservada para 005). No se tocan `competencias` ni `competencia_grados`.

**Testing**: Pest (Feature y Unit); Vitest + Testing Library (jsdom); E2E con Playwright. Pint disponible; **no hay
análisis estático instalado** y no se añade (Constitución IV).

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: Listado paginado (10 por página, máximo 50) sin funciones, requisitos ni competencias; el detalle
se carga solo al desplegar una fila (una petición); encontrar un puesto en menos de 15 s (SC-002); crear un puesto
completo en menos de 5 min (SC-001)

**Constraints**: nombre único (sin distinguir mayúsculas ni espacios alrededor) entre activos e inactivos; cada lista
con 1 a 20 elementos; ≥ 1 función Esencial, ≥ 1 requisito de Formación y ≥ 1 de Experiencia; funciones y requisitos del
mismo tipo sin texto repetido; competencias activas, sin repetir y con grado de esa competencia; una competencia inactiva
ya asignada solo se mantiene con su grado o se quita; desactivar solo sin posiciones Ocupadas ni Vacantes (comprobación y
cambio en una sola operación atómica); sin borrado de puestos; edición concurrente rechazada con `version`; guardar sin
cambios reales no altera nada; el orden de las funciones no se audita; rol verificado en el backend (un Usuario nunca
recibe puestos inactivos en el listado); otros módulos solo acceden por la Facade; interfaz en español, WCAG 2.2 AA

**Scale/Scope**: Cientos de puestos; 6 operaciones HTTP (sin `DELETE`), 2 pantallas (listado y formulario), 6 puestos
sembrados (2 inactivos)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Código en `backend/app/Modules/Estructura/` con Domain, Application, Infrastructure y Presentation; casos de uso explícitos (`ListarPuestos`, `ObtenerPuesto`, `ListarCompetenciasParaPuestos`, `CrearPuesto`, `ActualizarPuesto`, `CambiarEstadoPuesto`); controlador delgado; persistencia por `PuestoRepositoryInterface`. Las reglas puras (validación de competencias, comparación para la auditoría) viven en Domain sin Laravel ni Eloquent. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (patrón ya aceptado en 000–003). Las rutas son `/api/puestos` por indicación explícita del insumo. Ver Complexity Tracking. |
| I. Admin = Sanctum + capacidad `admin` | PASA, **sustituyendo** los permisos `puestos.crear/editar/cambiar_estado` del insumo (no existen). Crear, actualizar, cambiar estado y `competencias-disponibles` usan `auth:sanctum` + `admin`; consultar y listar solo `auth:sanctum`. La visibilidad de inactivos se decide en el borde según la capacidad `admin` del token. |
| I. Un módulo no accede a clases o tablas de otro | PASA. Todo vive en `Estructura`; las relaciones Eloquent (`Puesto → Funcion/Requisito/Competencia asignada → Competencia`, `Puesto → Posicion`) son internas del módulo. Los ids de usuario son enteros sin clave foránea a `users`. Los demás módulos solo usan `EstructuraFacade`. |
| I. Validación y autorización en los límites | PASA. Formas, longitudes, mínimos, repeticiones y máximos en `GuardarPuestoRequest`; reglas que dependen de datos (competencia activa y grado válido) en el caso de uso con una regla de dominio pura. |
| I. Auditoría en los límites correspondientes | PASA. El actor sale del token en el controlador; el repositorio escribe el puesto, las filas hijas y el historial en una sola transacción. |
| I. Migraciones, factories y seeders según convención | PASA. Seis migraciones, factory de puestos y `PuestosSeeder` (depende de `CompetenciasSeeder`). |
| I. Pest, Pint | PASA. Pruebas Unit (reglas de dominio, comparador de auditoría) y Feature (API, repositorio, reglas de desactivación). |
| II. Frontend modular, `shared` sin módulos | PASA. Código en `frontend/src/modules/estructura/puestos/`. Se reutilizan los componentes de `shared/ui`; un único ícono nuevo, como SVG propio. |
| II. Una funcionalidad no importa internals de otra | PASA. `puestos` no importa nada de `competencias`, `comportamientos` ni `preguntas`: tipos, textos, guarda `RequireAdmin` y parámetros de listado propios; el rol llega por el contrato público `modules/auth/login`. |
| II. Axios solo en cliente compartido; TanStack Query; escrituras sin reintento; filtros en URL | PASA. Búsqueda, estado y página en `useSearchParams`; mutaciones sin reintentos. |
| II. App Shell, estados, formularios, confirmación accesible, WCAG | PASA. Estados cargando/vacío/error/éxito en listado y detalle; formulario con errores por campo y valores conservados; `ConfirmDialog` al desactivar. |
| II. E2E real por feature | PASA. `frontend/e2e/puestos.spec.ts` con Playwright. |
| III. `design/` como fuente | **AJUSTE ANTES DE IMPLEMENTAR**. `design/Puestos.dc.html` solo cubre el listado. Faltan el formulario, el filtro de estado, la paginación, los botones de gestión, el modal de confirmación, el tipo de cada función, los requisitos agrupados por tipo, el indicador de competencia inactiva y la reordenación. Es la primera tarea de frontend (ver Diseño de frontend). |
| IV. Dependencias | PASA. Sin dependencias nuevas; la reordenación usa botones Subir/Bajar, sin librería de arrastre. |
| IV. Pruebas, seguridad | PASA. Validación en backend y frontend; rol verificado en cada petición; errores sin detalles internos. |

**Re-evaluación tras Fase 1**: sin cambios en los veredictos. El diseño añade columnas y tablas que el insumo descartaba
(D-02, D-03) y cambia dos reglas del insumo por exigencia de la spec (D-05, D-07); todo queda registrado en Complexity
Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/004-catalogo-puestos/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── checklists/requirements.md
├── contracts/
│   ├── puestos-api.md
│   └── estructura-facade.md
└── tasks.md             # Lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Estructura/
│   ├── Domain/
│   │   ├── Entities/Puesto.php
│   │   ├── Repositories/PuestoRepositoryInterface.php
│   │   ├── Services/{ComparadorDeCambiosDePuesto,ReglasDeCompetenciasDePuesto}.php   (reglas puras, sin Laravel)
│   │   ├── ValueObjects/{EstadoPuesto,TipoFuncion,TipoRequisito,EstadoPosicion,FuncionPuesto,RequisitoPuesto,CompetenciaConGrados,CompetenciaDePuesto,AsignacionDeCompetencia,DatosPuesto,InstantaneaDePuesto,CambioAuditado,PaginaPuestos}.php   (reutiliza CompetenciaAsociada, TipoCompetencia y GradoLetra)
│   │   └── Exceptions/{PuestoNoEncontrado,PuestoModificado,NombreDePuestoDuplicado,PuestoConPosicionesEnUso,CompetenciasDePuestoInvalidas}.php
│   ├── Application/
│   │   ├── UseCases/{ListarPuestos,ObtenerPuesto,ListarCompetenciasParaPuestos,CrearPuesto,ActualizarPuesto,CambiarEstadoPuesto}.php
│   │   ├── DTO/{PuestoDTO,CompetenciaParaPuestoDTO,ListadoPuestosDTO,FiltroPuestosDTO}.php
│   │   └── Services/EstructuraService.php                 (existente: se amplía la Facade)
│   ├── Infrastructure/
│   │   ├── Persistence/Eloquent/
│   │   │   ├── Models/{PuestoModel,PuestoFuncionModel,PuestoRequisitoModel,PuestoCompetenciaModel,PosicionModel,AuditoriaCambioModel}.php
│   │   │   ├── Models/CompetenciaModel.php                (existente: + relación `puestoCompetencias()`)
│   │   │   └── Repositories/EloquentPuestoRepository.php
│   │   └── Providers/EstructuraServiceProvider.php        (existente: + binding)
│   ├── Presentation/
│   │   ├── Controllers/PuestoController.php
│   │   ├── Requests/{GuardarPuestoRequest,ListarPuestosRequest,CambiarEstadoPuestoRequest}.php
│   │   ├── Resources/{PuestoResource,PuestoDetalleResource,CompetenciaParaPuestoResource}.php
│   │   └── Facades/EstructuraFacade.php                   (existente: + métodos)
│   └── Routes/api.php                                      (existente: + /api/puestos)
├── database/
│   ├── migrations/2026_10_13_00000{1..6}_create_{puestos,puesto_funciones,puesto_requisitos,puesto_competencias,posiciones,auditoria_cambios}_table.php
│   ├── factories/PuestoFactory.php
│   └── seeders/{PuestosSeeder,DatabaseSeeder}.php
└── tests/{Unit,Feature}/Estructura/Puestos/…

frontend/src/
├── modules/estructura/puestos/
│   ├── api.ts, text.ts, validation.ts, nav.tsx
│   ├── hooks/{usePuestos,useListadoParams,useGuardarPuesto,useCambiarEstadoPuesto}.ts   (usePuestos incluye lista, detalle y competencias disponibles)
│   ├── pages/{PuestosPage,PuestoFormPage}.tsx
│   └── components/{PuestosList,PuestoFila,PuestoDetalle,FuncionesFieldset,RequisitosFieldset,CompetenciasFieldset,FilaTipoTexto,RequireAdmin}.tsx
├── shared/ui/icons.tsx                                      (+ IconFileText, SVG propio, para el menú)
├── test/puestosFixtures.ts
├── App.tsx                                                  (rutas y navegación)
└── e2e/puestos.spec.ts
design/
├── Puestos.dc.html                                          (existente: solo listado)
├── PuestosFormulario.dc.html                                (nuevo tablero del formulario; + entrada en canvas.json)
└── DESIGN.md                                                (actualizar primero: §4, §5 y §9)
```

**Structure Decision**: Aplicación web con backend y frontend separados, en el módulo `Estructura` (backend) y
`modules/estructura/puestos/` (frontend), como indica el insumo. La funcionalidad es independiente de `competencias`,
`comportamientos` y `preguntas`: comparte con ellas solo la base de datos del módulo (lee `competencias` y
`competencia_grados`) y los componentes de `shared/ui`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Rutas `/api/puestos` en el módulo `Estructura` (la constitución pide `/api/<modulo>/`) | El insumo fija `/api/puestos` y es el recurso que consumen otros módulos | `/api/estructura/puestos` contradice el contrato indicado; mismo precedente en 000–003 |
| Migraciones para `puestos`, las tres tablas hijas y `auditoria_cambios` (el insumo dice que el modelo "ya existe" y que no se crean tablas) | El repositorio no contiene el DBML ni ninguna de esas tablas; sin ellas no hay dónde guardar nada | Esperar a un DBML que no está disponible bloquea la feature; se siguen al pie de la letra los campos, longitudes y restricciones que el insumo sí detalla |
| Tabla `posiciones` mínima (`id`, `puesto_id`, `estado`, marcas de tiempo) creada aquí aunque la feature 005 es su dueña | La regla de desactivación (FR-013) y su prueba necesitan posiciones Ocupadas, Vacantes y Suprimidas reales; el insumo supone que ya existen | Un contador simulado no demuestra el rechazo ni la atomicidad con datos reales. 005 la amplía con sus propias migraciones y no debe recrearla |
| Columnas `nombre_clave`, `nombre_busqueda` y `version` en `puestos` (el insumo: "no se crean columnas nuevas") | `nombre_clave` único exige nombre único sin distinguir mayúsculas ni espacios (FR-006); `nombre_busqueda` permite buscar sin distinguir tildes (FR-018), algo que SQLite no hace con `like`; `version` rechaza la edición simultánea (FR-026) | Comparar marcas de tiempo falla con resolución de 1 s; buscar en PHP sobre todo el catálogo no escala ni pagina bien |
| Capacidad `admin` en lugar de los permisos `puestos.crear`, `puestos.editar` y `puestos.cambiar_estado` | No existe tabla ni servicio de roles y permisos; la constitución define el control como `auth:sanctum` + `admin`, igual que 001–003 | Construir un sistema de permisos es una feature distinta y exigiría aprobar cambios de arquitectura |
| Una competencia inactiva ya asignada **no** admite cambiar su grado (el insumo lo permite) | Clarificación de la spec (Q2): solo se mantiene igual o se quita; el backend rechaza otro grado con error en la fila | Seguir el insumo contradice la spec y la interfaz bloqueada |
| Auditoría **por elemento** de cada lista, con el orden excluido (el insumo: una fila por campo con las listas serializadas) | US5 y FR-028 piden un registro por cada alta, baja o modificación, y Q4 excluye el orden de las funciones | Serializar la lista completa registra un solo cambio opaco y no permite saber qué elemento cambió |
| Código `PU-###` derivado del `id` en la misma transacción (el insumo: máximo + 1 con reintento si falla la restricción única) | Es el patrón ya usado en `COMP-` (001): atómico, sin reintentos y sin reutilización posible | El cálculo con reintento añade un bucle y una condición de carrera que el `id` autoincremental evita |
| Campo `version` obligatorio en `PUT` (no está en el insumo) | FR-026 exige rechazar el segundo guardado concurrente | Ver `version` arriba |
| Reemplazo de las filas hijas en cada edición con cambios (el insumo ya lo indica: "reemplaza los conjuntos") | Las funciones tienen `orden` único por puesto; actualizar en sitio chocaría con la restricción al reordenar | Actualizar fila por fila exige ids estables en el cuerpo, que el insumo no define. Las filas hijas no tienen otras referencias, así que sus `id` cambian sin consecuencias |

## Diseño de frontend

### Rutas

| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/puestos` | Listado de puestos (con detalle desplegable) | Administrador y Usuario (el Usuario solo ve los activos) |
| `/puestos/nuevo` | Formulario de puesto (crear) | Solo Administrador (Usuario ve "sin permisos") |
| `/puestos/:puestoId/editar` | Formulario de puesto (editar; activo o inactivo) | Solo Administrador |

### Componentes

- **Reutilizados de `shared/ui`**: `Combobox` (selector de competencia), `Alert` (error general y tono neutro),
  `Badge` (variante neutra para Inactivo), `Button`, `ConfirmDialog`, `Breadcrumb`, `EmptyState`, `ErrorState`,
  `Skeleton`, `Pagination`, `SearchField`, `SelectField`, `TextField`, `TextareaField`.
- **Nuevos, solo del módulo**: `PuestosPage`, `PuestoFormPage`, `PuestosList`, `PuestoFila` (botón de la fila con
  `aria-expanded`), `PuestoDetalle` (misión, funciones numeradas con su tipo, requisitos agrupados por tipo y tabla de
  competencias), `FuncionesFieldset`, `RequisitosFieldset`, `CompetenciasFieldset`, `FilaTipoTexto` (fila común de tipo
  y texto, con Quitar y, en funciones, Subir y Bajar), `RequireAdmin` y `nav.tsx` (ítem "Puestos"). Ningún componente
  compartido nuevo.
- **Design system (primera tarea de frontend)**: actualizar `design/DESIGN.md` y añadir el tablero
  `design/PuestosFormulario.dc.html` (con su entrada en `canvas.json`) antes de implementar. Debe cubrir: fila
  desplegable de puesto (patrón de `Puestos.dc.html`: `h2` con botón, panel `role="region"`), fila repetible de tipo y
  texto, Subir/Bajar con anuncio accesible de la nueva posición, fila de competencia (selector, datos de solo lectura y
  grado, o grado bloqueado con aviso si la competencia está inactiva), filtro de estado, paginación, botones Editar y
  Activar/Desactivar dentro del detalle, `ConfirmDialog` de desactivación con el motivo de rechazo y el estado Inactivo
  del puesto. Las diferencias del tablero con la spec se registran en `DESIGN.md` §9: "Clasificación" se muestra como
  "Tipo"; "Conocimientos" es "Capacitación"; la competencia "Gerencial" no existe en el modelo (solo Cardinal y
  Específica); las funciones muestran su tipo; los requisitos se agrupan por tipo; el menú del tablero incluye
  Organigrama y Perfilamiento, que pertenecen a otras features y no se implementan aquí.

### Datos y estado

- Listado: `GET /api/puestos?q=&estado=&page=&per_page=`; clave `["puestos","lista",{q,estado,page}]`. Búsqueda, estado y
  página en la URL (`useSearchParams`), con retardo de 300 ms al escribir y vuelta a la página 1 al cambiar filtros.
  `estado` se escribe en la URL solo si es `Inactivo` o `Todos` y solo para el Administrador.
- Detalle: `GET /api/puestos/{id}` solo al desplegar la fila y al editar; clave `["puestos","detalle",id]`; la edición
  fuerza lectura fresca. Una sola fila abierta a la vez; el identificador abierto no se guarda en la URL.
- Crear: `GET /api/puestos/competencias-disponibles` para el selector y `POST /api/puestos`; editar: `PUT
  /api/puestos/{id}` con `version`; estado: `PATCH /api/puestos/{id}/estado`. Mutaciones sin reintentos; tras guardar se
  invalidan `["puestos"]` sin esperar la recarga y se vuelve al listado con aviso.
- Desactivar abre `ConfirmDialog` que nombra el puesto; si el backend responde 422 por posiciones en uso, el diálogo
  muestra el mensaje con la cantidad y el puesto sigue Activo. Activar actúa sin confirmación.
- Un puesto inactivo se indica con el badge neutro "Inactivo" en el listado y en el detalle. Una competencia inactiva se
  indica con un badge neutro junto a su nombre, con el grado bloqueado en el formulario.
- Rol: `useCurrentUser` del contrato público del módulo de autenticación.

### Validación

- Replica de `GuardarPuestoRequest`: nombre ≤ 50, descripción ≤ 200 y misión ≤ 200 (con contador `n/máximo`);
  funciones 1–20 con ≥ 1 Esencial y sin descripciones repetidas; requisitos 1–20 con ≥ 1 de Formación y ≥ 1 de
  Experiencia y sin repetir descripción dentro de un tipo; competencias 1–20, sin repetir y con grado obligatorio;
  descripciones ≤ 500. Los botones Agregar se deshabilitan al llegar a 20.
- Mapeo de errores: 422 a campos (`nombre`, `funciones`, `funciones.N.descripcion`, `requisitos.N.tipo`,
  `competencias.N.competencia_id`, `competencias.N.grado_requerido`…); 409 de edición concurrente a la alerta "El puesto
  cambió" con "Recargar"; 404 a "ya no existe"; 422 por posiciones en uso al diálogo de desactivación.
