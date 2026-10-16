# Implementation Plan: Registro de ingresos y reingresos laborales (GP-02)

**Branch**: `006-ingresos-reingresos` | **Date**: 2026-10-15 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/006-ingresos-reingresos/spec.md` (con sus 4 clarificaciones), el insumo técnico «Plan GP-02» (15-10-2026) y el prototipo `design/GP-02_Registrar-ingreso.html`.

## Summary

El Administrador registra el ingreso o reingreso de una ficha (GP-01): fecha efectiva (pasada, actual o futura), tipo de
contrato (4 opciones fijas) y una posición elegible. El sistema crea **vínculo laboral** y **asignación inicial** en una
sola transacción, sin superposiciones por ficha ni por posición, con alta idempotente por `Idempotency-Key`. El estado
laboral (Activo, Desvinculado, Sin vínculo vigente) se calcula siempre de los vínculos para la fecha de calendario del
tenant; no se guarda ni requiere tareas programadas.

El backend amplía el módulo `GestionPersonal` (tablas `vinculos_laborales` y `asignaciones_laborales`, casos de uso
`RegistrarIngreso`, `ObtenerOpcionesDeIngreso`, `ConsultarSituacionLaboral` y `ConsultarTrayectoria`) y **reemplaza**
los adaptadores «no instalado» de GP-01 por lectores reales. `Estructura` publica por su Facade lo que GP-02 necesita
(posiciones elegibles) y consume un contrato de ocupación para extender sus reglas de desactivación (clarificación 1).
El insumo asume PostgreSQL con restricciones GiST que **no existen en el repositorio**: el plan las sustituye por una
transacción de escritura serializada de SQLite más *triggers* de respaldo (clarificación 2; ver research.md).

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13) en backend; TypeScript estricto + React 19 en frontend

**Primary Dependencies**: Laravel Sanctum, React Router, TanStack Query, Axios, React Hook Form, Zod, Tailwind CSS 4,
Vitest, Testing Library y Playwright (todos instalados y aprobados). Ninguna dependencia nueva.

**Storage**: SQLite. Tablas nuevas `vinculos_laborales` y `asignaciones_laborales` (con `tenant_id`); columna nueva
`posiciones.tenant_id` (migración del módulo, con valor por defecto 1 como `users.tenant_id`). Sin PostgreSQL, sin
`btree_gist`.

**Testing**: Pest (Unit y Feature); la prueba de concurrencia usa un archivo SQLite temporal con dos procesos PHP reales,
no `:memory:`. Vitest + Testing Library; E2E con Playwright. Pint disponible; no hay análisis estático y no se añade.

**Target Platform**: Navegadores modernos de escritorio y móvil (320–1440 px)

**Project Type**: Aplicación web (API Laravel + SPA React)

**Performance Goals**: Registrar un ingreso en menos de 2 min de interacción (SC-001); la transacción del alta realiza
un número constante de consultas; el selector de posiciones pagina de 20 en 20 y resuelve ocupación por lote.

**Constraints**: aislamiento por `tenant_id` resuelto en servidor; intervalos `[inicio, fin)` con fin nulo = abierto;
vínculos de una ficha sin superposición (incluidos futuros); una posición con un ocupante por fecha; vínculo y
asignación en una transacción; idempotencia por clave de operación con huella; sin edición, cancelación ni
reprogramación; el alta no admite `fecha_salida`, `estado` ni `salario`; estado derivado, nunca almacenado; ninguna FK
hacia tablas de otro módulo; interfaz en español, WCAG 2.2 AA

**Scale/Scope**: Cientos a pocos miles de fichas por tenant y pocas decenas de vínculos por ficha; 6 operaciones HTTP
(1 escritura), 1 pantalla nueva (Registrar ingreso) y 2 secciones ampliadas (situación laboral y trayectoria)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | PASA. Código en `backend/app/Modules/GestionPersonal/` (Domain, Application, Infrastructure, Presentation); casos de uso explícitos; controladores delgados; persistencia por `VinculoLaboralRepositoryInterface`; reglas temporales puras (`PeriodoLaboral`, `ReglasDeIngreso`, `EstadoLaboralDerivado`) en Domain sin Laravel. |
| I. Rutas bajo `/api/<modulo>/` | DESVIACIÓN JUSTIFICADA (precedente 000–005). Las rutas cuelgan de `/api/colaboradores/{id}/…` y `/api/mi-ficha/…` por contrato del insumo y de GP-01. |
| I. Admin = Sanctum + capacidad `admin` | PASA. Registrar y opciones usan `auth:sanctum` + `admin`; las lecturas propias (`mi-ficha`) usan solo `auth:sanctum` y los mismos controles de asociación y permiso de GP-01. |
| I. Un módulo no accede a clases o tablas de otro | PASA con contratos nuevos. GP-02 lee posiciones, puestos y unidades **solo** por `EstructuraFacade`; Estructura lee la ocupación **solo** por `PersonalFacade`. `posicion_id` no lleva FK. Ver contracts/contratos-entre-modulos.md. |
| I. SQLite y migraciones | PASA. SQLite sigue siendo el almacenamiento (decisión 2); las migraciones usan SQL crudo solo para `CHECK` y *triggers*, que el constructor de esquema no ofrece. |
| I. Validación, autorización y auditoría en los límites | PASA. Formas en `RegistrarIngresoRequest`; reglas temporales y de posición en el caso de uso; actor y momento del registro como metadatos del vínculo. |
| I. Pest, Pint | PASA. Unit de dominio, Feature de API, de contratos entre módulos y de concurrencia real. |
| II. Frontend modular | PASA. Código en `frontend/src/modules/gestionPersonal/fichas/` (funcionalidad `fichas` ampliada con `ingreso/`); reutiliza `shared/ui` y el contrato público de autenticación. |
| II. Axios, TanStack Query, escrituras sin reintento, filtros en URL | PASA. Mutación sin reintentos; la clave `Idempotency-Key` se genera al abrir el formulario. |
| II. Estados, formularios, WCAG, E2E | PASA. Estados cargando/vacío/error/éxito; errores por campo y rechazo con motivo; E2E `frontend/e2e/ingresos.spec.ts`. |
| III. `design/` como fuente | **AJUSTE ANTES DE IMPLEMENTAR**. Actualizar `design/DESIGN.md` (Alert con motivo de rechazo, selector de posición con búsqueda, diferencias con `GP-02_Registrar-ingreso.html`) y, si hace falta, un tablero `ColaboradorIngreso.dc.html`. |
| IV. Dependencias | PASA. Sin dependencias nuevas. |
| IV. Calidad y seguridad | PASA. Datos sintéticos; un rechazo no deja registros parciales (probado). |

**Constitución**: no requiere enmienda (decisión 2). **Re-evaluación tras Fase 1**: sin cambios en los veredictos; el
diseño añade `tenant_id` a `posiciones` y modifica la desactivación de 004 y 005, ambos registrados en Complexity
Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/006-ingresos-reingresos/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── checklists/requirements.md
├── contracts/
│   ├── ingresos-api.md
│   └── contratos-entre-modulos.md
└── tasks.md             # Lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/GestionPersonal/
│   ├── Domain/
│   │   ├── Entities/{VinculoLaboral,AsignacionLaboral}.php
│   │   ├── Repositories/VinculoLaboralRepositoryInterface.php
│   │   ├── Services/{PeriodoLaboral,ReglasDeIngreso,SituacionLaboralDerivada}.php   (puros, sin Laravel)
│   │   ├── ValueObjects/{TipoContrato,TipoDeHecho,SituacionLaboral}.php
│   │   └── Exceptions/{PosicionNoDisponible,VinculoSuperpuesto,ContratoNoHabilitado,IngresoNoVerificable}.php
│   ├── Application/
│   │   ├── UseCases/{RegistrarIngreso,ObtenerOpcionesDeIngreso,ConsultarSituacionLaboral,ConsultarTrayectoria}.php
│   │   ├── Ports/PosicionesAsignablesPort.php            (nuevo; los demás puertos de GP-01 se amplían)
│   │   └── DTO/{IngresoDTO,OpcionesDeIngresoDTO,SituacionLaboralDTO,HechoLaboralDTO}.php
│   ├── Infrastructure/
│   │   ├── Persistence/Eloquent/{Models/{VinculoLaboralModel,AsignacionLaboralModel}.php,
│   │   │                        Repositories/{EloquentVinculoLaboralRepository,EloquentLectorLaboral}.php}
│   │   ├── Adapters/{EstructuraFacadeLector,PosicionesAsignablesEstructura,LectorDeCapacidadesGp02}.php
│   │   └── Providers/GestionPersonalServiceProvider.php  (se sustituyen los 3 bindings «no instalado» de GP-01)
│   ├── Application/Services/PersonalService.php          (+ posicionesConAsignacionVigenteOFutura)
│   ├── Presentation/{Controllers/{IngresoController,SituacionLaboralController}.php,
│   │                 Requests/RegistrarIngresoRequest.php,Resources/…}
│   └── Routes/api.php                                    (+ 6 rutas)
├── app/Modules/Estructura/
│   ├── Application/{Ports/OcupacionDePosicionesPort.php,Services/EstructuraService.php}
│   │                                                      (+ getPosicion, posicionesAsignables, posicionEsAsignable)
│   ├── Application/UseCases/{CambiarEstadoPuesto,CambiarEstadoUnidad}.php   (+ comprobación de ocupación)
│   └── Infrastructure/Adapters/OcupacionPorPersonalFacade.php
├── database/
│   ├── migrations/2026_10_16_00000{1..3}_{add_tenant_id_to_posiciones_table,create_vinculos_laborales_table,
│   │                                      create_asignaciones_laborales_table}.php
│   └── seeders/{IngresosSeeder,DatabaseSeeder}.php
├── config/hrms.php                                       (+ tipos_contrato, gestion_personal.ingresos_habilitados)
└── tests/{Unit,Feature}/GestionPersonal/Ingresos/…

frontend/src/modules/gestionPersonal/fichas/
├── api.ts, text.ts, validation.ts                        (ampliados)
├── hooks/{useOpcionesDeIngreso,useRegistrarIngreso}.ts
├── pages/RegistrarIngresoPage.tsx
└── components/{FormularioDeIngreso,SelectorDePosicion,ProximoIngreso}.tsx
frontend/e2e/ingresos.spec.ts
design/{DESIGN.md,ColaboradorIngreso.dc.html,canvas.json}
```

**Structure Decision**: Se extiende `GestionPersonal` (backend) y `modules/gestionPersonal/fichas/` (frontend) en lugar
de crear un módulo nuevo: vínculos y asignaciones son parte de la misma bounded context de Personal y GP-01 ya definió
los puertos que GP-02 implementa. Estructura solo gana lecturas y una consulta de ocupación.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| SQLite en lugar de PostgreSQL con restricciones GiST (insumo) | Clarificación 2: la constitución fija SQLite y 000–005 dependen de ella | Pasar a PostgreSQL exige enmienda MAJOR/MINOR y migrar todo; es otra feature |
| *Triggers* SQLite de superposición y de contención, además de la comprobación en aplicación | Equivalen a las restricciones de exclusión del insumo: la regla se cumple aunque otro escritor omita el caso de uso | Solo validación en aplicación deja la integridad sin red de seguridad ante futuras features (movimientos, salidas) |
| Primera instrucción de la transacción es una escritura sobre la ficha (bloqueo de escritor) | SQLite no tiene `SELECT … FOR UPDATE`; hace falta tomar el bloqueo antes de leer para evitar `SQLITE_BUSY_SNAPSHOT` | `transaction_mode = IMMEDIATE` global cambia el comportamiento de todas las features |
| `posiciones.tenant_id` (columna nueva en tabla de Estructura) | RN04 exige que la posición pertenezca al tenant; el catálogo de posiciones hoy es global y dos tenants compartirían ocupación | Sin la columna, un ingreso en un tenant bloquearía posiciones de otro y revelaría su ocupación |
| `posicion_id` sin clave foránea | Constitución I: un módulo no accede a tablas de otro; el contrato de Estructura valida existencia | Una FK acopla el esquema y el orden de migraciones de dos módulos |
| Modificación del comportamiento de 004 (puestos) y 005 (unidades) | Clarificación 1 / FR-016b: la ocupación sale de las asignaciones | Dejar sin tocar permite desactivar un puesto o una unidad con una posición suprimida aún asignada |
| Reemplazo de los tres adaptadores «no instalado» de GP-01 y ampliación del filtro de estado | GP-01 los diseñó como reemplazables; ahora hay vínculos reales | Un segundo lector paralelo duplicaría la derivación del estado |
| Tipos de contrato en configuración del producto (`config/hrms.php`) | Clarificación 3: cuatro opciones fijas con clave estable | Una tabla `tipos_contrato` sugiere un catálogo configurable que la spec excluye |
| Prueba de concurrencia con procesos PHP y archivo SQLite temporal | `:memory:` y una sola conexión no demuestran SC-004 ni SC-005 | Simular la carrera en un solo proceso no ejercita el bloqueo de escritores |

## Diseño de frontend

### Rutas

| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/colaboradores/:colaboradorId/ingreso` | Registrar ingreso | Solo Administrador |
| `/colaboradores/:colaboradorId` | Detalle (situación laboral y trayectoria ampliadas) | Solo Administrador |
| `/mi-ficha` | Mi ficha (situación laboral y trayectoria propias) | Cuenta autenticada; el servidor decide |

### Componentes

- **Reutilizados de `shared/ui`**: `Combobox` (selector de posición con búsqueda), `SelectField`, `TextField`, `Alert`
  (rechazo con motivo; neutro para el aviso de ingreso programado), `Button`, `Breadcrumb`, `Skeleton`, `ErrorState`,
  `EmptyState`.
- **Nuevos, solo del módulo**: `RegistrarIngresoPage`, `FormularioDeIngreso`, `SelectorDePosicion` (Combobox paginado
  con puesto, unidad y jefatura de solo lectura), `ProximoIngreso` (bloque «Ingreso programado» en el detalle) y la
  acción **Registrar ingreso** en el encabezado del detalle cuando `capacidades.registrar_ingreso` es verdadero.
- **Design system (primera tarea de frontend)**: actualizar `design/DESIGN.md` §4/§5/§9 y, si se necesita, añadir
  `design/ColaboradorIngreso.dc.html` antes de implementar. Debe cubrir: Alert de rechazo con el motivo y acción
  (cambiar fecha o posición), aviso neutro «Ingreso programado», selector de posición con estados vacío («No hay
  posiciones disponibles para esa fecha») y no disponible (Estructura caída, con Reintentar), y el descarte de una
  selección con aviso. Diferencias del prototipo `GP-02_Registrar-ingreso.html` con la spec se registran en §9.

### Datos y estado

- Opciones: `GET /api/colaboradores/{id}/opciones-ingreso?fecha=&q=&page=`; clave
  `["colaboradores","ingreso","opciones",id,{fecha,q,page}]`; se vuelve a consultar al cambiar la fecha o la búsqueda
  (debounce de 300 ms) y la selección incompatible se descarta con aviso.
- Registrar: `POST /api/colaboradores/{id}/ingresos` con `Idempotency-Key` (`crypto.randomUUID()` al montar; se
  regenera tras 409 por clave reutilizada). Sin reintentos y **sin cambio optimista**; al éxito se invalidan
  `["colaboradores"]` y `["mi-ficha"]` y se vuelve al detalle con aviso.
- Detalle y Mi ficha: reutilizan `GET /api/colaboradores/{id}` y `GET /api/mi-ficha` (GP-01); las rutas
  `situacion-laboral` y `trayectoria` existen para consumo directo y se usan para reintentar su sección.

### Validación

- Fecha obligatoria con formato `AAAA-MM-DD` y dentro de un rango razonable (1900-01-01 a hoy + 10 años); contrato
  obligatorio entre las 4 claves; posición obligatoria.
- Mapeo de errores: 422 por campo (`fecha_ingreso`, `tipo_contrato`, `posicion_id`); `POSICION_NO_DISPONIBLE` y
  `VINCULO_SUPERPUESTO` a un Alert con el motivo y formulario conservado; 503 `ESTRUCTURA_NO_VERIFICABLE` a
  «No se pudo verificar la posición» con Reintentar; 409 a «La operación ya se usó con otros datos»; 404 a «La ficha no
  existe».
