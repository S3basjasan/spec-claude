# Implementation Plan: Plan de desarrollo individual (PDI) y compromisos de mejora

**Branch**: `021-plan-desarrollo-individual` | **Date**: 2026-10-22 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/021-plan-desarrollo-individual/spec.md` (incluye las aclaraciones de la sesión 2026-10-22), con el insumo técnico `plan-pdi.md` (copiado en esta carpeta y reconciliado en [research.md](research.md) R1) y, como referente visual **exacto**, los tableros `design/PDIAdmin.dc.html`, `design/PDIAsistente.dc.html`, `design/PDIDetalle.dc.html`, `design/PDISeguimiento.dc.html` (administrador) y `design/PDIColaborador.dc.html` (colaborador).

**Referencia única del administrador**: los cuatro tableros de administrador son el **mismo prototipo** (misma cabecera, menú, filtros y código; solo cambia la vista inicial: lista, asistente, detalle o Gantt). Se toma `PDIDetalle.dc.html` como referencia de estructura general, y los otros tres como vistas iniciales de sus pantallas.

## Summary

Novena feature de `Desarrollo`. El Administrador (Gestor de Talento) crea **planes de desarrollo individual** de tres tipos (Mejora, Carrera, Talento) con compromisos, acciones e hitos; los precarga desde la última DNF o adecuación; los acuerda en dos confirmaciones (responsable → colaborador); da seguimiento; vincula acciones al Plan de Formación; los cierra con resultado y consulta la carta Gantt y a quienes tienen brechas sin plan. El Colaborador consulta «Mi plan de desarrollo», acepta o devuelve con comentario y ve «Qué sigue».

Decisiones de arquitectura:

- **Clean Architecture dentro de `Desarrollo`**, como Rutas (019) y Adecuación (020): reglas en `Domain` puro (transiciones, completitud, avance, resultado, derivación del estado de acciones vinculadas); casos de uso explícitos; repositorio por contrato; controladores delgados.
- **Cinco tablas nuevas** (`pdis`, `pdi_compromisos`, `pdi_acciones`, `pdi_hitos`, `pdi_historial`) con `tenant_id`, sin soft deletes, sin `cascade` y **sin endpoints DELETE**. El único borrado físico es el reemplazo de hijos de un plan en Borrador dentro de la transacción de actualización (R10).
- **Aclaraciones de la spec incorporadas al diseño**: (1) el colaborador solo ve y responde un Borrador **después** del acuerdo del responsable; (2) el responsable se puede cambiar en un plan Acordado sin borrar acuerdos; (3) al cerrar se **congela** el estado de las acciones vinculadas (`estado_congelado`).
- **Estado de acciones vinculadas derivado**, no copiado: se calcula con la acción del plan y la asistencia de Actividades (R3), y se congela al cerrar. Lecturas en lote (sin N+1).
- **Concurrencia**: columna `version` (control optimista); los comandos reciben `version` y devuelven 409 si cambió (R9).
- **Unicidad en BD** del plan no Cerrado por colaborador y tipo, con índice único parcial (R8), además de la validación del caso de uso.
- **Permisos `desarrollo.pdi.*` sin tabla de permisos**: igual que 019/020, catálogo en código (`ClaveDePermisoPdi`), un único punto de autorización (`AutorizadorDePdi` vía `EnsurePermiso`) y `GET /api/pdi/permisos` para el frontend. El middleware aprende la forma `permiso:a|b` (cualquiera de las dos).
- **Colaboración solo por puertos y Facades**: DNF y Adecuación a través de `DesarrolloFacade` (con métodos hermanos aditivos para `dnf_id` y niveles), Plan de Formación y Actividades por puertos nuevos de solo lectura, Estructura y Personal por los puertos ya existentes. Ningún puerto toca tablas de otro módulo. El Plan de Formación y las Actividades nunca se modifican.
- **Facade propia ampliada** con `planesActivos`, `pdiPorFiltros`, `existePlanAbierto` y `getAccionesVinculablesDeColaborador`.
- **Enlaces con otras features**: se habilitan los botones de Adecuación (FR-044) y se ofrece «Generar nueva adecuación» al cerrar Carrera/Talento (R13).
- **Frontend**: se sigue **exactamente** el prototipo (ver «Diferencias entre tableros y spec»): una ruta `/pdi` con dos vistas según permisos (lista/Gantt del administrador y «Mi plan de desarrollo» del colaborador), asistente de tres pasos y detalle con cuatro pestañas.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel (versión de `backend/composer.json`); TypeScript estricto en React con Vite.

**Primary Dependencies**: las existentes (Laravel, Sanctum, Pest; React, React Router, TanStack Query, Axios, React Hook Form, Zod, Tailwind CSS 4, Vitest, Testing Library). Sin dependencias nuevas.

**Storage**: SQLite (desarrollo); cinco migraciones nuevas (`2026_10_25_000001…000005`) bajo las convenciones existentes de `Desarrollo`, incluido un índice único parcial por `DB::statement`.

**Testing**: Pest (unit de dominio, feature HTTP y de repositorio), Vitest + Testing Library (lógica de componentes y hooks) y un E2E real del flujo principal (crear y acordar → aceptar → «Mi plan»), con Playwright como en las features previas.

**Target Platform**: SPA web, 320–1440 px, temas claro y oscuro.

**Project Type**: web-service + SPA (backend Laravel modular y frontend React modular).

**Performance Goals**: la lista de planes de hasta 500 colaboradores, el Gantt y el indicador de «sin plan» cargan en < 3 s (apoyan SC-001 y SC-009); lecturas **sin N+1** (nombres, departamentos, asistencias y acciones del plan resueltos en lote por Facade).

**Constraints**: sin FK entre módulos; planes Cerrados inmutables; el colaborador solo recibe datos propios y solo planes con acuerdo del responsable; errores seguros; texto visible en español y centralizado; escrituras sin reintento automático.

**Scale/Scope**: hasta ~500 colaboradores por tenant, ≤ 30 compromisos y ≤ 10 acciones por compromiso por plan, ≤ 30 hitos; 20 endpoints; 3 rutas principales de frontend (+ edición) y 5 pantallas.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación | Estado |
|---|---|---|
| I. Backend modular con Clean Architecture | Todo dentro de `Modules/Desarrollo` con las carpetas existentes; dominio y aplicación sin Laravel; repositorio por contrato; controladores delgados sin consultas; colaboración por Facades y puertos; Pest, Pint y análisis estático al cierre. Se añaden métodos aditivos de solo lectura a `DesarrolloFacade` (contratos públicos). | Cumple |
| I. Rutas `/api/<modulo>/` | Las features 013–020 exponen rutas por funcionalidad (`/api/rutas`, `/api/adecuaciones`, `/api/reportes`…). Se mantiene el precedente: `/api/pdi`, `/api/pdi-acciones`, `/api/pdi-hitos`. **Desviación aprobada explícitamente por el usuario el 2026-10-22** (ver Complexity Tracking). | Cumple |
| I. Endpoints administrativos con autenticación y capacidad `admin` | Se autoriza por clave de permiso (`permiso:desarrollo.pdi.*`) tras `auth:sanctum`, como en 019/020, porque el insumo exige claves estables asignables a otros roles y el colaborador necesita `ver_propio` y `aceptar_propio`. **Aprobada explícitamente por el usuario el 2026-10-22.** | Cumple |
| II. Frontend modular, accesible y tipado | `frontend/src/modules/desarrollo/pdi/`; páginas sin Axios directo; TanStack Query sin reintento de escrituras; filtros, pestaña y paso en la URL; texto centralizado en `text.ts`; loading/empty/error/success; formularios con errores por campo; confirmación accesible en cierre y devoluciones; WCAG 2.2 AA; Vitest y E2E. | Cumple |
| III. Diseño como fuente de referencia | Se sigue `design/PDI*.dc.html`; las diferencias con la spec se registran aquí y se llevarán a `design/DESIGN.md` (sección «PDI (feature 021)») **antes** de implementar. | Cumple |
| IV. Calidad, seguridad y dependencias | Sin dependencias nuevas; entradas validadas; sin datos personales reales en seeders; errores seguros; auditoría en el historial; cobertura de transiciones, privacidad, permisos, congelamiento y concurrencia. | Cumple |

**Re-evaluación posterior al diseño**: sin cambios. Las dos desviaciones de la tabla (rutas `/api/pdi` y autorización por clave de permiso) fueron aprobadas explícitamente por el usuario el 2026-10-22; no hay otras violaciones sin justificar.

## Project Structure

### Documentation (this feature)

```text
specs/021-plan-desarrollo-individual/
├── plan.md              # Este archivo (/speckit-plan)
├── plan-pdi.md          # Insumo técnico original (reconciliado en research.md R1)
├── specify-pdi.md       # Documento fuente de la spec
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   ├── api.md           # Contrato HTTP
│   ├── facade.md        # Facade propia, cambios aditivos y puertos
│   └── ui.md            # Contrato de pantallas (tableros)
├── checklists/requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks — NO lo crea este comando)
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Desarrollo/
│   ├── Domain/
│   │   ├── Entities/            # Pdi, CompromisoDePdi, AccionDePdi, HitoDePdi
│   │   ├── ValueObjects/        # CodigoDePdi, TipoDePdi, EstadoDePdi, DimensionDeCompromiso, ResultadoDeCompromiso,
│   │   │                        # ResultadoDePdi, TipoDeAccionPdi, EstadoDeAccionPdi, EstadoDeHito, TipoDeHistorialPdi,
│   │   │                        # PeriodoDePdi, ClaveDePermisoPdi
│   │   ├── Services/            # ReglasDeTransicionDePdi, ReglasDeCompletitudDePdi, ReglasDeCompromiso,
│   │   │                        # CalculadorDeAvance, CalculadorDeResultadoDePdi, DerivadorDeEstadoDeAccion
│   │   ├── Exceptions/          # PlanDuplicadoPorTipo, PlanNoEditable, PlanIncompleto, AcuerdoNoVigente,
│   │   │                        # AccionesAbiertas, ResultadosIncompletos, VinculoInvalido, VersionDesfasada
│   │   └── Repositories/        # RepositorioDePdiInterface
│   ├── Application/
│   │   ├── Ports/               # AccionesVinculablesInterface, EstadoFormativoDeAccionInterface, BrechasParaPdiInterface
│   │   │                        # (+ puertos existentes: Aprobadores, CuentaColaborador, ColaboradoresVigentes,
│   │   │                        # DepartamentoDeColaborador, NombreDeColaborador, Competencias, Reloj, PuestosParaRutas)
│   │   ├── Services/            # AutorizadorDePdi, ArmadorDeDetalleDePdi, ResolvedorDeEstadoDeAcciones, PrecargaDePdi
│   │   ├── DTO/                 # PdiDTO, PdiResumenDTO, CompromisoDTO, AccionDTO, HitoDTO, PrecargaDTO, QueSigueDTO, GanttDTO
│   │   └── UseCases/            # PrecargarPdi, CrearPdi, ActualizarPdi, ListarPdi, ObtenerPdi, ConfirmarAcuerdoDePdi,
│   │                            # AceptarPdi, DevolverPdiDelColaborador, DevolverPdiABorrador, CambiarResponsableDePdi,
│   │                            # VincularAccionDePdi, ListarAccionesVinculables, RegistrarAvanceDeAccion,
│   │                            # RegistrarAvanceDeHito, CerrarPdi, ListarHistorialDePdi, ObtenerMiPlan,
│   │                            # ObtenerGanttDePdi, ListarColaboradoresSinPlan, ObtenerOpcionesDePdi,
│   │                            # ConsultarPdiParaOtrosModulos
│   ├── Infrastructure/
│   │   ├── Adapters/            # AccionesVinculablesDesdePlanes, EstadoFormativoDesdeActividades,
│   │   │                        # BrechasParaPdiDesdeDnfYAdecuacion
│   │   ├── Persistence/Eloquent/ # Models + EloquentPdiRepository
│   │   └── Providers/DesarrolloServiceProvider.php   # bindings nuevos
│   ├── Presentation/
│   │   ├── Controllers/PdiController.php, MiPlanPdiController.php, PdiAccionesController.php, PdiHitosController.php
│   │   ├── Requests/            # CrearPdiRequest, ActualizarPdiRequest, ListarPdiRequest, CerrarPdiRequest,
│   │   │                        # MotivoRequest, ComentarioRequest, ResponsableRequest, VinculoRequest, AvanceRequest, HitoRequest
│   │   ├── Resources/           # PdiResource, PdiResumenResource, MiPlanResource, GanttResource, HistorialResource
│   │   ├── Middleware/EnsurePermiso.php               # + autorizador de PDI y forma `a|b`
│   │   └── Facades/DesarrolloFacade.php               # + planesActivos, pdiPorFiltros, existePlanAbierto,
│   │                                                  #   getAccionesVinculablesDeColaborador, ultimaDnfConBrechas
│   └── Routes/api.php           # + grupos api/pdi, api/pdi-acciones, api/pdi-hitos
├── config/desarrollo.php        # + pdi.conocimientos_con_grado
├── database/migrations/         # 5 migraciones (2026_10_25_000001…000005)
├── database/seeders/            # PdisSeeder (+ registro en DatabaseSeeder)
└── tests/{Unit,Feature}/Desarrollo/Pdi/

frontend/src/modules/desarrollo/pdi/
├── api.ts  hooks/  text.ts  validation.ts  permisos.ts  nav.tsx  estilosEstado.ts
├── components/              # ver contracts/ui.md («Componentes»)
└── pages/                   # EntradaPdiPage (resuelve vista por permisos), ListaDePdiPage, SeguimientoGanttPage,
                             # AsistenteDePdiPage, DetalleDePdiPage, MiPlanDeDesarrolloPage
frontend/e2e/pdi.spec.ts
frontend/src/App.tsx         # + rutas /pdi, /pdi/nuevo, /pdi/:id, /pdi/:id/editar y pdiNav en el grupo «Talento y desarrollo»
```

**Structure Decision**: aplicación web con backend Laravel modular y frontend React modular; la feature vive en `Desarrollo` (backend) y `desarrollo/pdi` (frontend). Toca módulos ajenos solo con métodos de lectura aditivos en la Facade propia de Desarrollo y un cambio de presentación en Adecuación (botones, FR-044).

## Diferencias entre tableros y spec (manda la spec en reglas y datos; los tableros en aspecto y estructura)

Instrucción del usuario: usar los `design/PDI*.dc.html` como base del diseño. Donde el tablero contradice una regla de la spec, se conserva el aspecto del tablero y se adapta solo el dato o el rótulo.

| Tablero | Diferencia | Resolución |
|---|---|---|
| Los 5 | Menú lateral de prototipo (Empleados, Ajustes, Mi perfil), botón «Ver como colaborador» y panel «Permisos del rol (simulación del prototipo)» con alcance **Todos / Equipo (jefatura de Tecnología)**. | **Se omiten**: el App Shell real ya existe, cada persona tiene un solo rol y no existe el rol de jefatura (spec, Assumptions). La visibilidad se decide con las claves de permiso. El ítem «PDI» entra a la navegación declarativa con `aria-current`. |
| Admin, Detalle, Colaborador | **Jefatura** como actor que firma, es responsable y acuerda. | Se reemplaza por **Responsable** (administrador): «Firma de jefatura» → «Acuerdo del responsable»; «Jefatura responsable» → «Responsable»; «Responsable del PDI (jefatura)» → «Responsable del plan». |
| Detalle, Colaborador | **Firma** («Firmar como jefatura», «Firmar acuerdo», «Firma del colaborador»). | Se reemplaza por **acuerdo/aceptación**: «Confirmar acuerdo» (responsable) y «Aceptar plan» (colaborador). Se conserva la casilla de lectura «Revisé los compromisos, acciones y fechas de este plan y acepto cumplirlos» como confirmación previa de «Aceptar plan». |
| Detalle, Colaborador | El colaborador puede firmar a la par que la jefatura, en cualquier orden. | Manda la aclaración: el colaborador **solo ve y responde tras el acuerdo del responsable**. Antes de ese acuerdo el plan no existe para él. |
| Admin, Detalle | Cuatro estados: Borrador, Acordado, **En seguimiento**, Cerrado; botón «Iniciar seguimiento»; «Cerrar» solo desde En seguimiento. | La spec tiene **tres estados**. «En seguimiento» se **omite**: Acordado cubre el seguimiento; «Iniciar seguimiento» desaparece; «Cerrar» se habilita en Acordado cuando las acciones están Cumplidas o Canceladas. Se retira la opción «En seguimiento» del filtro Estado. |
| Asistente | Una sola «acción de mejora» por compromiso; campo «Competencia o meta a mejorar». | Un compromiso admite **varias acciones** y se separa en Dimensión (Competencia, Conocimiento, Experiencia, Meta), Elemento o Descripción y niveles A–D (precargados). El patrón visual de tarjeta por compromiso se conserva y se anidan las acciones. |
| Asistente | Tipo de acción «Plan de Formación» y «Nueva responsabilidad / experiencia». | Rótulos de la spec: **Curso**, Mentoring, Coaching, Autodesarrollo, Codesarrollo, **Nueva responsabilidad** y **Otra**. «Vincular a Plan de Formación» solo en Curso, Mentoring y Codesarrollo. |
| Asistente | «Responsable» por acción (Colaborador, Jefatura, Mentor/a, Formador/a) como lista cerrada. | La spec define **persona a cargo** como texto libre (mentor, coach u otra). Se usa un campo de texto con el mismo lugar visual. |
| Asistente | Paso 3 con «Duración del programa (meses)». | Se **omite**: el periodo se define por fecha de inicio y fin (Talento: duración definida por las fechas). |
| Asistente | Paso 1 sin aviso de plan duplicado ni origen formal; hitos con «campo propio del sistema». | Se añade el aviso/bloqueo de plan abierto del mismo tipo y el origen (DNF/adecuación). Los hitos se conservan como en el tablero. |
| Detalle | Estados de acción «Pendiente / En curso / **Hecha**». | Rótulos de la spec: **Pendiente, En curso, Cumplida, Cancelada** (se añade Cancelada). Las vinculadas se muestran en solo lectura. |
| Detalle | Panel «Registro de cierre» **en línea** con un único «Resultado» y «Observaciones». | La spec cierra con **modal**: resultado por compromiso, conclusión opcional, y el resultado del plan se **calcula**. El panel posterior al cierre («Resultado del cierre») se conserva. Se añade «Generar nueva adecuación» en Carrera/Talento. |
| Detalle | Texto «Al cerrar, el resultado queda disponible para el siguiente DNF y la próxima adecuación». | Se conserva como texto informativo, acotado a: «el resultado queda registrado y disponible para Reportes y para generar una nueva adecuación». |
| Admin | Filtros Tipo, Estado, Departamento, Responsable; sin rango de fechas ni indicador de hitos vencidos por fila. | Se **añaden** el rango de fechas y la marca **Hitos vencidos** por fila (spec 4.2). Los KPI se redefinen como Borradores, Acordados, Cerrados e Hitos vencidos. |
| Admin | Bloque «Personas con brecha y sin PDI». | Se conserva; su cálculo es el de [research.md](research.md) R6 (DNF o adecuación) y «Crear PDI» lleva la precarga en la URL. |
| Seguimiento | Filtros Tipo y Departamento; «PDI abiertos (Acordado o En seguimiento)». | Se **añade** el filtro Responsable (spec 4.3); solo planes **Acordado**. |
| Seguimiento | Leyenda «Hecha / En curso / Pendiente / Hito». | Se conserva con el rótulo «Cumplida» donde corresponde y una alternativa tabular accesible. |
| Colaborador | Selector de PDI por tarjetas; «Qué sigue» con tipo de PDI. | Se conserva; «Qué sigue» muestra solo planes Acordado, con vencidos destacados y enlace a la actividad en acciones vinculadas. Se añade **Devolver con comentario**. |
| Todos | Identificadores `PDI-01`. | Formato de la spec: **`PDI-0001`**. |
| Todos | «Destino» del plan. | Se conserva: puesto objetivo en Carrera/Talento y «Puesto actual» en Mejora. |

Estas diferencias se registran aquí (Constitución III) y en `design/DESIGN.md` (primera tarea de implementación, sección «PDI (feature 021)»).

## Complexity Tracking

| Desviación | Por qué es necesaria | Alternativa simple rechazada porque |
|---|---|---|
| Rutas `/api/pdi`, `/api/pdi-acciones`, `/api/pdi-hitos` (no `/api/desarrollo/pdi`) (**aprobada explícitamente por el usuario el 2026-10-22, Constitución I**) | Es la convención de todas las features de `Desarrollo` (`/api/rutas`, `/api/adecuaciones`, `/api/reportes`) y de `plan-pdi.md`. | Un prefijo distinto rompería la coherencia del módulo y del cliente HTTP. |
| Autorización por clave de permiso en vez de capacidad `admin` (**aprobada explícitamente por el usuario el 2026-10-22, Constitución I**) | El insumo exige claves estables (`desarrollo.pdi.*`) asignables a otros roles y el colaborador necesita `ver_propio` y `aceptar_propio`; es el mismo patrón ya aprobado para 019 y 020. | `admin` no distingue las claves `_propio` ni permite reasignarlas. |
| `EnsurePermiso` acepta `permiso:a\|b` | `GET /{id}` e `historial` sirven al administrador (`ver`) y al colaborador (`ver_propio`) con la misma ruta. | Duplicar rutas para cada rol rompería el contrato del insumo y multiplicaría las pruebas. |
| Índice único parcial creado con `DB::statement` | Garantiza en BD «un plan no Cerrado por colaborador y tipo» ante carreras (R8). | Solo validar en código no cubre concurrencia. |
| Borrado físico de hijos de un Borrador al reemplazarlos | El insumo lo define así (sin cascade ni soft deletes); es la forma más simple de reemplazar la estructura en una transacción (R10). | Soft deletes o versionado de hijos añaden columnas y consultas sin beneficio, y el historial ya audita lo relevante. |
| Métodos aditivos en `DesarrolloFacade` (`ultimaDnfConBrechas`, niveles en `ultimaAdecuacion`, `getAccionesVinculablesDeColaborador`) | No existe otro contrato público para obtener `dnf_id`, niveles de la brecha y acciones Aprobado/Ejecutando (R4, R5). | Leer tablas de DNF, Adecuación o Plan desde el PDI violaría la constitución. |

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/pdi` | **Una sola ruta, dos vistas según permisos** (patrón de `/adecuacion`): con alguna clave de gestión, **Lista de PDI** y **Seguimiento (Gantt)** (`?pestana=`); con solo `desarrollo.pdi.ver_propio`, **Mi plan de desarrollo** (`?plan=`) | Administrador (gestión) · Colaborador (`ver_propio`) |
| `/pdi/nuevo` | Asistente de creación (`?paso=`, `?colaborador=&tipo=&puesto_objetivo=`) | `desarrollo.pdi.gestionar` |
| `/pdi/:id` | Detalle con pestañas Resumen, Compromisos y acciones, Hitos e Historial (`?pestana=`); el colaborador ve su versión de solo lectura | `desarrollo.pdi.ver` · `desarrollo.pdi.ver_propio` |
| `/pdi/:id/editar` | Edición de un Borrador | `desarrollo.pdi.gestionar` |

### Componentes
- **Nuevos**: los listados en [contracts/ui.md](contracts/ui.md) («Componentes»).
- **Del design system**: App Shell, Tabs, Select/Combobox, Input, Textarea, Button, Badge, Card, Table, Alert, Skeleton, Modal de confirmación, Paginación, barra de progreso y estados vacío/error — reutilizando los de las features 013–020.

### Datos y estado
- Endpoints por pantalla en [contracts/api.md](contracts/api.md); textos y pantallas en [contracts/ui.md](contracts/ui.md).
- Claves de caché: `pdi/permisos`, `pdi/opciones`, `pdi/precarga`, `pdi/lista?filtros`, `pdi/{id}`, `pdi/{id}/historial`, `pdi/gantt?filtros`, `pdi/sin-plan?filtros`, `pdi/mi-plan`, `pdi/acciones-vinculables?colaborador`. Tras cualquier mutación del plan se invalidan `pdi/{id}`, `pdi/{id}/historial`, la lista, `pdi/gantt`, `pdi/sin-plan` y `pdi/mi-plan`. Las mutaciones no se reintentan.
- Estado en URL: pestaña, filtros de la lista y del Gantt (tipo, estado, departamento, responsable, fechas), página, paso del asistente y plan elegido en «Mi plan».

### Validación
- Plan: objetivo 1–500; periodo con fin ≥ inicio; puesto objetivo obligatorio en Carrera y Talento; responsable administrador.
- Compromiso: elemento obligatorio en Competencia/Conocimiento; descripción obligatoria en Experiencia/Meta; niveles A–D distintos; fechas dentro del periodo del plan; elemento no repetido.
- Acción: tipo y descripción obligatorios; fechas dentro del periodo; vínculo solo en Curso, Mentoring y Codesarrollo.
- Hito: fecha dentro del periodo y descripción obligatoria.
- Devoluciones: motivo/comentario 1–1000. Cierre: resultado por cada compromiso.
- Errores 422 se mapean al campo (`compromisos.<i>.acciones.<j>.fecha_fin`, `tipo`, `responsable_id`, `completitud`, `resultados`); 409 muestra el aviso de recarga; 503/errores de red muestran el `Alert` con «Reintentar».
