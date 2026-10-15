# Implementation Plan: Unidades organizacionales

**Branch**: `005-unidad-organizacional` | **Date**: 2026-10-15 (revisión) | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/005-unidad-organizacional/spec.md` (actualizada el 2026-10-15), con el insumo técnico `plan-unidades.md` y el modelo de datos `estructura_empresarial.dbml` actualizado.

> **Revisión 2026-10-15**: este plan reemplaza al anterior (árbol con formulario en línea y catálogo de tipos de solo lectura). Parte del código del plan anterior ya existe en el repositorio y se **reutiliza o modifica**; `tasks.md` lo detalla como tareas de cambio. `research.md`, `data-model.md`, `contracts/` y `quickstart.md` quedan desactualizados hasta completar las tareas de documentación de la fase 1.

## Summary

Gestión de las unidades organizacionales y de la **cadena de tipos de unidad**. Los tipos forman una cadena lineal de niveles 1..N (el nivel 1 es el tipo raíz) que el Administrador amplía (siempre al final), renombra y reordena; los renombres y cambios de nivel se preparan en un borrador y se aplican en una sola operación, rechazada si alguna unidad quedaría bajo una padre de nivel igual o más profundo. Las unidades forman un árbol con **una única raíz sembrada**; el tipo de cada unidad nunca se elige: el backend lo calcula como el del nivel inmediatamente inferior al de su padre. Desactivar es **en cascada** (la unidad y todas sus dependientes activas, todo o nada, rechazada si la rama tiene posiciones Ocupadas o Vacantes); reactivar es unidad por unidad. Cada cambio de unidades y de tipos queda en `auditoria_cambios`.

El backend amplía el módulo `Estructura` con `/api/unidades` (7 operaciones) y `/api/tipos-unidad` (3 operaciones), sin `DELETE`. El frontend reemplaza el formulario en línea por **modales** (Crear / Editar unidad, Desactivar, Nuevo tipo, Editar tipos y Bloqueo de reordenamiento) y añade la cadena de tipos y el árbol expandible. El insumo da por hecho roles y permisos por acción, que **no existen en el repositorio**; el plan los resuelve con la capacidad `admin` (ver Complexity Tracking).

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript 6 estricto + React 19 en frontend

**Primary Dependencies**: Laravel Sanctum, React Router, TanStack Query, Axios, React Hook Form, Zod, Tailwind CSS 4, Vitest, Testing Library, jsdom y Playwright (todos ya instalados y aprobados). Ninguna dependencia nueva: el arrastre usa HTML5 drag and drop nativo, con subir y bajar como alternativa de teclado.

**Storage**: SQLite. `tipos_unidad` pasa a tener `nivel` (único), `created_by` y `updated_by`; `unidades_organizacionales` se conserva (con `version`); `posiciones.unidad_organizacional_id` (nullable) se conserva; `auditoria_cambios` se reutiliza con `entidad = 'unidad'` y `'tipo_unidad'`. Como la feature no se ha publicado, los cambios a `tipos_unidad` se hacen **editando su migración existente** (`migrate:fresh --seed`). No se tocan `competencias`, `puestos` ni `users`.

**Testing**: Pest (Feature y Unit); Vitest + Testing Library (jsdom); E2E con Playwright. Pint disponible; no hay análisis estático y no se añade (Constitución IV).

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: Árbol de hasta unas 500 unidades y cadena de tipos en menos de 3 s, sin paginar (SC-003); encontrar una unidad en menos de 15 s (SC-002); crear una unidad en menos de 1 min (SC-001); editar la cadena (renombre + cambio de nivel) en menos de 2 min (SC-014)

**Constraints**: nombre de unidad obligatorio ≤ 150 (no único); nombre de tipo obligatorio ≤ 50, único sin distinguir mayúsculas ni tildes; niveles continuos 1..N, únicos, raíz siempre en 1; tipo de unidad calculado en backend (nivel de la padre + 1) y nunca del cliente; padre obligatoria salvo la raíz, activa y de un tipo que admita hijas; al reubicar, padre del tipo inmediatamente superior; sin ciclos; una raíz sembrada que no se crea, desactiva ni reubica; desactivar en cascada atómica (todo o nada) y rechazada con posiciones Ocupadas/Vacantes en la rama; reactivar solo con padre activo; activa ⇒ ancestras activas; sin borrado de unidades ni tipos; edición concurrente de unidad rechazada con `version` y de la cadena con un token de cadena; guardar sin cambios reales no altera nada; estado y fechas nunca del cliente; quién creó y modificó solo lo ve el Administrador; rol verificado en el backend; otros módulos solo por la Facade; interfaz en español, WCAG 2.2 AA

**Scale/Scope**: Cientos de unidades y pocas decenas de tipos; 10 operaciones HTTP (sin `DELETE`); 1 pantalla (árbol + cadena) y 5 modales; 5 tipos y 12 unidades sembradas (1 inactiva)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Código en `backend/app/Modules/Estructura/` con Domain, Application, Infrastructure y Presentation; casos de uso explícitos (unidades: `ListarUnidades`, `ObtenerUnidad`, `ListarOpcionesDeUnidad`, `CrearUnidad`, `ActualizarUnidad`, `ConsultarImpactoDeDesactivacion`, `CambiarEstadoUnidad`; tipos: `ListarTiposDeUnidad`, `CrearTipoDeUnidad`, `GuardarCadenaDeTipos`); controladores delgados; persistencia por `UnidadRepositoryInterface` y `TipoUnidadRepositoryInterface`. Las reglas puras (árbol, rama, cadena, validación de reordenamiento, comparación de cambios) viven en servicios de dominio sin Laravel. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (patrón aceptado en 000–004): `/api/unidades` y `/api/tipos-unidad` por indicación del insumo. Ver Complexity Tracking. |
| I. Admin = Sanctum + capacidad `admin` | PASA, **sustituyendo** los permisos `unidades.crear/editar/cambiar_estado/gestionar_tipos` del insumo (no existen tablas de roles ni permisos). Escrituras, `opciones` e `impacto-desactivacion` usan `auth:sanctum` + `admin`; listar, consultar y ver la cadena solo `auth:sanctum`. La visibilidad de inactivas y de la auditoría se decide en el borde según la capacidad del token. |
| I. Un módulo no accede a clases o tablas de otro | PASA. Las relaciones Eloquent (`Unidad → TipoUnidad`, `Unidad → Unidad`, `Unidad → Posicion`, `TipoUnidad → Unidad`) son internas de `Estructura`. Los nombres de quien creó y modificó se obtienen por el puerto ya existente (adaptador sobre `SharedFacade::getNombresDeUsuarios`). Los demás módulos solo usan `EstructuraFacade` (ahora también la cadena de tipos). |
| I. Validación y autorización en los límites | PASA. Forma, longitudes y campos prohibidos en Requests; reglas que dependen del árbol y de la cadena en el repositorio, dentro de la transacción de escritura. |
| I. Auditoría | PASA. El actor sale del token; el repositorio escribe datos e historial en una sola transacción, con comparadores de dominio puros para unidades y tipos. |
| I. Migraciones, factories y seeders | PASA. Se editan las migraciones de tipos y se amplía el seeder y la factory. |
| I. Pest, Pint | PASA. Pruebas Unit (árbol, rama, cadena, comparadores, casos de uso) y Feature (API, repositorios, atomicidad, concurrencia). |
| II. Frontend modular, `shared` sin módulos | PASA. Código en `frontend/src/modules/estructura/unidades/`. Se reutilizan `shared/ui` y se añade **un componente compartido** `Modal` (no existe equivalente: `ConfirmDialog` es un diálogo de confirmación, no un contenedor genérico); ver Complexity Tracking. |
| II. Una funcionalidad no importa internals de otra | PASA. `unidades` no importa de `competencias`, `comportamientos`, `preguntas` ni `puestos`; el rol llega por el contrato público `modules/auth/login`. |
| II. Axios solo en cliente compartido; TanStack Query; escrituras sin reintento; filtros en URL | PASA. Búsqueda y estado en `useSearchParams`; mutaciones sin reintentos. |
| II. App Shell, estados, formularios, confirmación accesible, WCAG | PASA. Modales con foco atrapado, Escape y foco devuelto; el árbol usa `aria-expanded`; reordenar tiene alternativa de teclado (WCAG 2.5.7); la jerarquía no depende solo de la sangría ni del color. |
| II. E2E real por feature | PASA. `frontend/e2e/unidades.spec.ts` se reescribe con Playwright. |
| III. `design/` como fuente | **AJUSTE ANTES DE IMPLEMENTAR**. El tablero y `DESIGN.md` actuales corresponden a la spec anterior (formulario en línea, tipo elegido, desactivación simple). Debe actualizarse primero (ver Diseño de frontend): cadena de tipos, árbol expandible, modales, vista previa, bloqueo, diálogo de cascada y el patrón Modal. |
| IV. Dependencias | PASA. Sin dependencias nuevas. |
| IV. Pruebas, seguridad | PASA. Validación en backend y frontend; rol verificado en cada petición; errores sin detalles internos. |

**Re-evaluación tras Fase 1**: sin cambios en los veredictos. Las desviaciones nuevas (token de cadena, `Modal` compartido, lectura en `PUT` de la cadena sin `lockForUpdate`) quedan en Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/005-unidad-organizacional/
├── plan.md
├── research.md            # a actualizar (tarea de fase 1)
├── data-model.md          # a actualizar
├── quickstart.md          # a actualizar
├── checklists/requirements.md
├── contracts/
│   ├── unidades-api.md    # a actualizar (+ tipos-unidad)
│   └── estructura-facade.md
└── tasks.md
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Estructura/
│   ├── Domain/
│   │   ├── Entities/{Unidad,TipoUnidad}.php                      (Unidad: + tipo con nivel, tieneDependientes, admiteHijas, sigueCadena)
│   │   ├── Repositories/{UnidadRepositoryInterface,TipoUnidadRepositoryInterface}.php
│   │   ├── Services/{ArbolDeUnidades,CadenaDeTipos,ComparadorDeCambiosDeUnidad,ComparadorDeCambiosDeTipo,NormalizadorDeTexto}.php   (puros, sin Laravel)
│   │   ├── ValueObjects/…                                         (existentes + TipoDeUnidad con nivel, UnidadAfectadaPorCadena, ImpactoDeDesactivacion, BorradorDeTipo)
│   │   └── Exceptions/…                                           (existentes ajustadas + CadenaModificada, CadenaInvalida, DesactivacionBloqueada)
│   ├── Application/
│   │   ├── UseCases/{…unidades…,ConsultarImpactoDeDesactivacion,ListarTiposDeUnidad,CrearTipoDeUnidad,GuardarCadenaDeTipos}.php
│   │   ├── DTO/…                                                  (+ TipoUnidadDTO, CadenaDeTiposDTO, ImpactoDesactivacionDTO)
│   │   ├── Ports/NombresDeUsuariosInterface.php                   (existente)
│   │   └── Services/EstructuraService.php                         (+ métodos de tipos)
│   ├── Infrastructure/Persistence/Eloquent/
│   │   ├── Models/{UnidadModel,TipoUnidadModel}.php
│   │   └── Repositories/{EloquentUnidadRepository,EloquentTipoUnidadRepository}.php
│   ├── Presentation/
│   │   ├── Controllers/{UnidadController,TipoUnidadController}.php
│   │   ├── Requests/{GuardarUnidadRequest,CambiarEstadoUnidadRequest,OpcionesDeUnidadRequest,ListarUnidadesRequest,CrearTipoUnidadRequest,GuardarCadenaRequest}.php
│   │   ├── Resources/{UnidadResource,UnidadDetalleResource,OpcionesDeUnidadResource,TipoUnidadResource,ImpactoDesactivacionResource}.php
│   │   └── Facades/EstructuraFacade.php                           (+ cadena de tipos)
│   └── Routes/api.php                                              (/api/unidades y /api/tipos-unidad)
├── database/
│   ├── migrations/2026_10_14_000001_create_tipos_unidad_table.php  (editar: + nivel único, created_by, updated_by)
│   ├── factories/{UnidadFactory,TipoUnidadFactory}.php
│   └── seeders/{TiposUnidadSeeder,UnidadesSeeder}.php              (5 tipos y 12 unidades)
└── tests/{Unit,Feature}/Estructura/Unidades/…  y  …/TiposUnidad/…

frontend/src/
├── shared/ui/{Modal.tsx,icons.tsx}                                 (+ Modal; + IconChevronRight, IconLock, IconAlertTriangle ya existe, IconGripVertical, IconArrowUp/Down)
├── modules/estructura/unidades/
│   ├── api.ts, text.ts, validation.ts, nav.tsx, arbol.ts           (arbol.ts: filas visibles según expansión)
│   ├── hooks/{useUnidades,useTiposUnidad,useListadoParams,useExpansion,useGuardarUnidad,useCambiarEstadoUnidad,useGuardarTipos}.ts
│   ├── pages/UnidadesPage.tsx
│   └── components/{CadenaDeTipos,UnidadesTree,UnidadFila,UnidadModal,DesactivarModal,NuevoTipoModal,EditarTiposModal,VistaPreviaDeCadena,BloqueoDeCadenaModal,UnidadInfo,RequireAdmin}.tsx
├── test/unidadesFixtures.ts
├── App.tsx
└── e2e/unidades.spec.ts
design/DESIGN.md, design/Unidades.dc.html                           (actualizar primero)
```

**Structure Decision**: Aplicación web con backend y frontend separados, en `Estructura` y `modules/estructura/unidades/`. La cadena de tipos pertenece a la misma funcionalidad que las unidades (la validación de reordenamientos necesita las unidades), por lo que comparte módulo y pantalla. Una sola ruta de interfaz (`/unidades`); los formularios son modales.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Rutas `/api/unidades` y `/api/tipos-unidad` (la constitución pide `/api/<modulo>/`) | El insumo fija ambos recursos y los consumen otros módulos | `/api/estructura/…` contradice el contrato indicado |
| Capacidad `admin` en lugar de los permisos `unidades.crear/editar/cambiar_estado/gestionar_tipos` y de las tablas `roles`, `permisos`, `rol_permisos` del DBML | El repositorio no tiene esas tablas (`users` usa una columna `role`); la constitución define la autorización administrativa con Sanctum + `admin`, y 001–004 la cumplen así | Implantar roles y permisos por acción es otra feature; los cuatro permisos se asignan a un único rol, así que el comportamiento es idéntico. Los seeders del insumo "dependen de roles y permisos": aquí no hay tal dependencia |
| `tipos_unidad` y `unidades_organizacionales` se crean y editan en este repositorio (el insumo las da por existentes) | La tabla existente solo tiene `nombre`; hay que añadir `nivel`, `created_by` y `updated_by` | Esperar al DBML bloquea; se edita la migración porque la feature no se ha publicado |
| Columna `version` en `unidades_organizacionales` (no está en el DBML) | FR-048 exige rechazar el segundo guardado concurrente; además serializa la transacción de edición | Comparar `updated_at` falla con resolución de 1 s |
| **Token de cadena** (hash de id, nombre y nivel de todos los tipos) para el control de edición simultánea de la cadena, sin columna nueva | FR-015: el segundo guardado de la cadena se rechaza. `PUT /api/tipos-unidad` recibe `version` (el token) y devuelve 409 si no coincide | Una columna `version` por tipo no detecta altas ni reordenamientos ajenos; un contador global exige una tabla nueva |
| Índice único **parcial** de raíz activa (se conserva) | Red de seguridad de la invariante "una sola raíz activa" frente a escrituras directas; la API ya no crea ni desactiva raíces | Quitarlo deja la invariante sin garantía en base de datos |
| Reordenamiento en dos pasos (nivel → −nivel → nivel final) en una transacción | `nivel` es único y la restricción se valida fila por fila, también en SQLite | Cambiar niveles directamente viola la unicidad en pasos intermedios |
| Serialización de escrituras sin `lockForUpdate` (SQLite lo ignora): la transacción **empieza con una escritura** y valida después contra el estado vigente | Es la técnica usada en 004 y en la implementación actual; el insumo propone `lockForUpdate`/`sharedLock`, que en MySQL equivalen | `lockForUpdate` no protege en SQLite |
| Columna nullable `unidad_organizacional_id` en `posiciones` (el DBML la define NOT NULL) | `posiciones` es una tabla mínima de 004 con filas sembradas sin unidad; se vuelve obligatoria cuando exista la feature de posiciones | Hacerla obligatoria rompe el seeder de 004 |
| Cascada: la unidad objetivo se desactiva primero con una sentencia condicional y la rama se calcula después, dentro de la misma transacción; si hay posiciones vigentes se revierte todo | Evita que una dependiente creada en paralelo quede activa bajo una padre inactiva y da el todo o nada de FR-025 | Calcular la rama antes de la transacción deja una ventana de carrera |
| Componente compartido nuevo `shared/ui/Modal` | Cinco modales necesitan foco atrapado, Escape y foco devuelto; `ConfirmDialog` no sirve de contenedor general | Copiar la lógica en cada modal la duplica cinco veces; `ConfirmDialog` no se refactoriza para no tocar 000–004 |
| Datos de auditoría de unidades solo para el Administrador, en contra del insumo | La clarificación Q3 de la spec lo fija así | Seguir el insumo contradice la spec |
| `GET /api/unidades/opciones` con `reubicar` (en lugar de `excluir`) y `GET …/impacto-desactivacion` | Contrato del insumo; la consulta previa alimenta el diálogo de cascada | Calcular la rama en el cliente duplicaría reglas del backend |

## Diseño de backend

### Datos

- **tipos_unidad**: `id`, `nombre` (≤ 50, único; unicidad normalizada también en la aplicación con `NormalizadorDeTexto`), `nivel` (tinyint, único), `created_by`, `updated_by`, timestamps. Niveles continuos 1..N; el nivel 1 es el tipo raíz. Nunca se eliminan.
- **unidades_organizacionales**: sin cambios de estructura respecto a lo ya creado (`codigo`, `nombre`, `tipo_unidad_id`, `unidad_padre_id`, `estado`, fechas, `version`, `created_by`, `updated_by`). Código `UO-` + id con al menos tres dígitos, asignado en la transacción de creación.
- **Invariantes** (aplicación): una raíz de nivel 1, siempre Activa y sembrada; tipo de la unidad = tipo de nivel (nivel de la padre + 1) al crear; estado Inactiva ⇔ `fecha_supresion` no nula; activa ⇒ ancestras activas; sin ciclos; tras un reordenamiento ninguna unidad queda bajo una padre de nivel ≥ al suyo.
- **auditoria_cambios**: `entidad = 'unidad'` (campos `nombre`, `tipo_unidad_id`, `unidad_padre_id`, `estado`, `fecha_supresion`) y `'tipo_unidad'` (campos `nombre`, `nivel`); referencias como id; creación con valor anterior nulo; una fila por unidad en la cascada y por tipo y campo en el reordenamiento.
- **Semillas**: tipos Gerencia General (1), Gerencia (2), Área (3), Departamento (4), Sección (5); 12 unidades en 5 niveles según `plan-unidades.md` (UO-008 = Departamento de Capacitación, inactiva con `fecha_supresion`); posiciones: una Ocupada en UO-012 (Desarrollo Web, rechazo de la cascada); las otras 2 vigentes en UO-002; Suprimidas en UO-006 y UO-008; sin posiciones en UO-005 con UO-009 (rama para la cascada) ni en UO-011 (hoja libre).

### Endpoints

| Operación | Endpoint | Acceso | Notas |
|---|---|---|---|
| Listar unidades | `GET /api/unidades` | autenticado | `q`, `estado`; orden jerárquico y `nivel_arbol` sobre el árbol completo; con `q` o estado distinto de Activa incluye las ancestras; cada elemento: id, código, nombre, estado, tipo (id, nombre, nivel), unidad padre, `nivel_arbol`, `tiene_dependientes`, `dependientes_activas`, `posiciones_vigentes`, `admite_hijas`, `sigue_cadena`. No Administrador: solo activas |
| Consultar | `GET /api/unidades/{unidad}` | autenticado | + fechas, `version`, dependientes y, solo con `admin`, auditoría |
| Crear | `POST /api/unidades` | admin | `nombre`, `unidad_padre_id` (obligatorio); el tipo lo calcula el backend; 422 con "Este tipo no admite subunidades." |
| Actualizar | `PUT /api/unidades/{unidad}` | admin | `nombre`, `unidad_padre_id`, `version`; reubica si cambia; la raíz sigue sin padre; rechaza `codigo`, `tipo_unidad_id`, `estado` y fechas; 409 por versión |
| Opciones | `GET /api/unidades/opciones` | admin | cadena de tipos; unidades en orden de árbol con `habilitada`; con `reubicar=id`, solo activas del tipo inmediatamente superior, sin la propia ni sus dependientes |
| Impacto de desactivación | `GET /api/unidades/{unidad}/impacto-desactivacion` | admin | dependientes activas que se desactivarán y unidades de la rama con posiciones vigentes; indica si procede |
| Cambiar estado | `PATCH /api/unidades/{unidad}/estado` | admin | desactivar en cascada (422 si es la raíz o la rama tiene posiciones, con unidades y cantidades); reactivar solo con padre activo (422 si no) |
| Listar tipos | `GET /api/tipos-unidad` | autenticado | por nivel; id, nombre, nivel, `es_raiz`, cantidad de unidades y el token de cadena |
| Crear tipo | `POST /api/tipos-unidad` | admin | `nombre`; nivel = último + 1 |
| Guardar cadena | `PUT /api/tipos-unidad` | admin | `version` (token) y `tipos` (lista completa en el nuevo orden, con id y nombre); valida ids, raíz primera, nombres, token y unidades en conflicto; 422 con `unidades_invalidas`; sin cambios, 200 sin escribir |

Las rutas `opciones` e `impacto-desactivacion` se declaran antes de `{unidad}` (numérico). No existe `DELETE`.

### Reglas de escritura

- **Crear**: la transacción empieza con una escritura; verifica padre existente, activo y de tipo que admite hijas; calcula el tipo por nivel; asigna código y registra el historial.
- **Actualizar**: sin cambios reales no escribe; el primer paso sube `version` (409 si no coincide); si cambia la padre, valida existencia, actividad, tipo del nivel inmediatamente superior y ausencia de ciclo; conserva la padre actual si no cambia (aunque no siga la cadena).
- **Cascada**: ver Complexity Tracking; auditoría de estado y `fecha_supresion` por cada unidad desactivada; `version`, `updated_by` y `updated_at` no cambian.
- **Guardar cadena**: transacción que empieza con una escritura; compara el token; valida y calcula las unidades en conflicto con `CadenaDeTipos` (dominio puro); aplica el reordenamiento en dos pasos; audita nombre y nivel.

## Diseño de frontend

### Rutas

| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/unidades` | Listado en árbol con la cadena de tipos; sobre él, los modales de unidad, desactivación y tipos | Administrador y Usuario (el Usuario solo ve el árbol activo y la cadena, sin acciones) |

No hay rutas de formulario: `RequireAdmin` protege la renderización de botones y modales.

### Componentes

- **Reutilizados**: `TextField`, `SelectField` (opciones deshabilitadas con descripción), `SearchField`, `Badge`, `Button`, `ConfirmDialog` (solo si encaja), `Alert`, `EmptyState`, `ErrorState`, `Skeleton`.
- **Nuevo compartido**: `Modal` (`role="dialog"`, `aria-modal`, foco atrapado, Escape, foco devuelto).
- **Nuevos del módulo**: `CadenaDeTipos` (chips por nivel), `UnidadesTree` y `UnidadFila` (sangría de 24 px, `aria-expanded`, prefijo oculto "Nivel N", advertencia "No sigue la cadena"), `UnidadModal` (crear y editar), `DesactivarModal`, `NuevoTipoModal`, `EditarTiposModal` (borrador, arrastre nativo y flechas), `VistaPreviaDeCadena`, `BloqueoDeCadenaModal`, `UnidadInfo`, `RequireAdmin`.
- **Accesibilidad**: cada botón deshabilitado lleva descripción accesible con el motivo; los mensajes de confirmación van en una región `aria-live`; reordenar con teclado equivale a arrastrar.

### Datos y estado

- Listado `GET /api/unidades?q=&estado=`; búsqueda y estado en la URL (retardo de 300 ms; `Activa` no se escribe). La respuesta ya trae orden y niveles; el cliente solo oculta las filas cuyas ancestras están contraídas. La expansión vive en el cliente: al entrar, solo la raíz contraída; con búsqueda o filtro de estado distinto de Activa todo se muestra expandido y los controles se deshabilitan; al limpiar vuelve la expansión previa; al crear se expanden el padre y sus ancestras.
- Cadena `GET /api/tipos-unidad` (clave `["tipos-unidad"]`); opciones `GET /api/unidades/opciones[?reubicar=id]` siempre frescas al abrir un modal; detalle fresco al editar, con la `version` congelada al abrir.
- Crear tipo, guardar cadena, crear, editar y cambiar estado: mutaciones sin reintentos; tras éxito se invalidan `["unidades"]` y `["tipos-unidad"]` y se muestra el mensaje.
- Desactivar: consulta el impacto, muestra la lista o el bloqueo y confirma con `PATCH`. Si el servidor responde 422, el modal muestra el mensaje.
- Editar tipos: borrador local (nombre y orden); la vista previa compara actual y resultante; "Guardar cambios" deshabilitado sin cambios; 422 con `unidades_invalidas` abre el bloqueo ("Ver en el árbol" cierra los modales y deja `estado=Todas` y `q=código`, para que aparezca aunque esté inactiva o en una rama contraída; "Volver a editar" conserva el borrador); 409 pide recargar.

### Validación

- Nombre de unidad obligatorio (recortado, "El nombre es obligatorio.") y ≤ 150; unidad padre obligatoria al crear ("Selecciona la unidad padre."); nombre de tipo obligatorio, ≤ 50 y único en el borrador sin distinguir mayúsculas ni tildes.
- Errores 422 a campos; 409 a alerta con "Recargar"; 404 cierra el modal con aviso.

### Cambios al diseño

`design/Unidades.dc.html` y `design/DESIGN.md` deben actualizarse **antes** del frontend con: filtro de estado y ocultar acciones al Usuario; cadena de tipos; árbol expandible y "Expandir todo / Contraer todo"; modales Crear / Editar (tipo de solo lectura, padre limitada, información de solo lectura); confirmación también sin dependientes y bloqueo por posiciones; vista previa y bloqueo de reordenamiento; textos "Reactivar", "La unidad raíz no se puede desactivar" y "Reactiva primero la unidad padre"; sin validación de nombre duplicado; patrón Modal. El tablero no tiene posiciones: el bloqueo por posiciones se ve con los datos sembrados.
