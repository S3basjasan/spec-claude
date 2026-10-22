# Implementation Plan: Adecuación a puesto futuro y mapa de talento

**Branch**: `020-adecuacion-puesto-futuro` | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/020-adecuacion-puesto-futuro/spec.md`, con el insumo técnico `plan-adecuacion.md` (copiado en esta carpeta) y, como referente visual **exacto**, los cuatro tableros `design/AdecuacionDiagnostico.dc.html`, `design/AdecuacionComparar.dc.html`, `design/AdecuacionMapa.dc.html` (administrador) y `design/AdecuacionColaborador.dc.html` (colaborador).

**Referencia única del administrador**: los tres tableros de administrador son el **mismo prototipo** (misma cabecera, menú, pestañas y código; solo cambian la pestaña inicial —Diagnóstico, Comparar candidatos o Mapa de talento— y la altura del lienzo). Se toma `AdecuacionDiagnostico.dc.html` como referencia de estructura y los otros dos como vistas iniciales de sus pestañas.

## Summary

Octava feature de `Desarrollo`. El Administrador genera **adecuaciones**: diagnósticos inmutables que comparan, por colaborador y puesto objetivo, lo que el puesto exige (competencias, conocimientos y experiencia) contra lo que la persona tiene, con diferencia, estado, tiempo estimado de cierre por brecha y una **preparación** (Lista, En desarrollo, No lista, Incompleta). Además compara candidatos (ranking ponderado calculado al vuelo), ofrece el mapa de talento y mantiene pesos y umbral. El Colaborador consulta «Mi adecuación».

Decisiones de arquitectura:

- **Clean Architecture dentro de `Desarrollo`**, como Rutas (019) y Reportes (018). Reglas (diferencia/estado, preparación, cumplimiento, puntaje con reescalado de pesos, validación de parámetros) en `Domain` puro; casos de uso explícitos en `Application`; repositorio por contrato; controladores delgados.
- **Cuatro tablas nuevas** (`adecuaciones`, `adecuacion_detalles`, `adecuacion_parametros`, `adecuacion_parametros_historial`), con `tenant_id`, sin soft deletes, sin `cascade`, **sin UPDATE ni DELETE** sobre adecuaciones y detalles (inmutables; el repositorio solo expone `guardar` y lecturas). La comparativa **no se guarda**.
- **El servidor recalcula todo**: `previsualizar` y `generar` obtienen perfil y valores por puertos, calculan y, al generar, solo aceptan del cliente los tiempos de cierre y las observaciones.
- **Colaboración solo por puertos y Facades**: `EstructuraFacade` (puesto, perfil, departamento), `PersonalFacade` (colaboradores vigentes, nombres, departamento, experiencia vía `getDesarrollo`) y la `DesarrolloFacade` propia para Rutas (`inscripcionesActivas`, `obtenerNivel`). Ningún puerto toca tablas de otro módulo.
- **Cálculo de grados sin duplicar la fórmula**: se reutiliza `NivelGrado::valorNumerico()` (D=1 … A=4, único lugar donde se define) y se aplica la misma regla de diferencia de `CalculadorDeBrecha` (actual − requerido; ≥0 Cumple/Supera; <0 Con brecha). `CalculadorDeBrecha` no se reutiliza tal cual porque añade porcentaje y prioridad de DNF que esta feature no usa; se extrae solo la regla de estado, sin copiar la escala (ver R2).
- **Valores de la persona — fuentes reales hoy**: no existe en Gestión de Personal un nivel por competencia ni por conocimiento (`NivelesActualesSinDatos`, `NivelesDeColaboradorSinDatos` siguen enlazados). Competencias: un adaptador propio lee el **último nivel evaluado** de la DNF del colaborador (mismo módulo, repositorio de DNF); si el colaborador no tiene DNF o competencia evaluada, es «Sin evaluación». Conocimientos: la bandera `desarrollo.adecuacion.conocimientos_con_grado` (por defecto `false`, alineada con `desarrollo.conocimientos_con_grado` de la 019) **desactiva la dimensión**: no se generan resultados de conocimientos, se muestra un aviso informativo y los pesos se reescalan sobre las demás (no vuelve «Incompleta» a la adecuación). Experiencia: se obtiene de `PersonalFacade::getDesarrollo` (años acumulados calculados sobre las experiencias registradas); si no hay dato, «Sin evaluación». Una bandera `desarrollo.adecuacion.experiencia_disponible` apaga la dimensión si la fuente no responde (ver R3–R5).
- **Puesto actual**: se copia al generar. Hoy no hay un método de `PersonalFacade` que devuelva el puesto actual; se agrega uno **aditivo, solo lectura** (`getPuestoActual`, análogo a `getDepartamentoActual`) y, si no pudiera completarse, el puesto actual queda sin dato y «requerido en el puesto actual» se muestra vacío (ver R6).
- **Permisos `desarrollo.adecuacion.*` sin tabla de permisos**: igual que la 019, un catálogo de claves en código con asignación por defecto (Administrador: las cinco de gestión, sin `ver_propio`; Colaborador: solo `ver_propio`), un **único punto de autorización** (middleware `permiso:<clave>`) y el endpoint de permisos para el frontend. Los controladores no consultan el rol.
- **Estados degradados**: si Estructura, Personal o Rutas fallan, las lecturas devuelven la parte disponible con `fuentes_no_disponibles` (la generación se rechaza con 503 en vez de guardar datos incompletos por falla, FR-036).
- **Preparación y umbral**: el umbral vigente se copia a la adecuación (`umbral_meses`) y la preparación se guarda sin recalcular (FR-017).
- **Enlace con Rutas (019)**: `ObtenerMiRuta` devuelve hoy `puede_ver_adecuacion = false` y el frontend ya enlaza a `/adecuacion`; esta feature lo enciende (`true` cuando existe la vista y la inscripción tiene nivel objetivo) y lo cubre con una prueba.
- **Ampliación de `DesarrolloFacade` (aditiva, solo lectura)** con `ultimaAdecuacion(tenantId, colaboradorId, puestoObjetivoId)` y `adecuacionesPorFiltros(tenantId, filtros)` para el Plan de Desarrollo Individual y Reportes.
- **Frontend**: se sigue **exactamente** el prototipo (ver «Diferencias entre tableros y spec»): una sola página «Adecuación a puesto futuro» con pestañas, y «Mi adecuación» para el colaborador.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel (versión de `backend/composer.json`); TypeScript estricto en React con Vite.

**Primary Dependencies**: las existentes (Laravel, Sanctum, Pest; React, React Router, TanStack Query, Axios, React Hook Form, Zod, Tailwind CSS 4, Vitest, Testing Library). Sin dependencias nuevas.

**Storage**: SQLite (desarrollo); cuatro migraciones nuevas bajo las convenciones existentes de `Desarrollo`.

**Testing**: Pest (unit de dominio, feature HTTP y de repositorio), Vitest + Testing Library (lógica de componentes y hooks), y un E2E real del flujo principal (generar → «Mi adecuación»), con la herramienta E2E ya usada por las features previas.

**Target Platform**: SPA web, 320–1440 px, temas claro y oscuro.

**Project Type**: web-service + SPA (backend Laravel modular y frontend React modular).

**Performance Goals**: SC-001 (generar < 3 min), SC-003 (ranking de 20 candidatos < 3 s) y SC-008 (mapa de 500 filas < 3 s) → lecturas del mapa y de la comparativa **sin N+1** (nombres, puestos y departamentos resueltos en lote por Facade).

**Constraints**: adecuaciones inmutables; sin FK entre módulos; el colaborador solo recibe datos propios y nunca puntajes; errores seguros; texto visible en español y centralizado.

**Scale/Scope**: hasta ~500 colaboradores por tenant, ≤ 15 requisitos por perfil, ≤ 20 candidatos por comparación; 9 endpoints; 2 rutas de frontend (administrador y colaborador) con 5 pestañas.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación | Estado |
|---|---|---|
| I. Backend modular con Clean Architecture | Todo dentro de `Modules/Desarrollo` con las carpetas existentes; dominio y aplicación sin Laravel; repositorio por contrato; controladores delgados; colaboración por Facades y puertos; Pest, Pint y análisis estático al cierre. Se agregan dos métodos aditivos a `DesarrolloFacade` y uno a `PersonalFacade` (contratos públicos, solo lectura). | Cumple |
| I. Rutas `/api/<modulo>/` | Las features 013–019 exponen rutas por funcionalidad (`/api/rutas`, `/api/dnf`, `/api/reportes`…). Se mantiene ese precedente: `/api/adecuaciones`. **Desviación registrada y aprobada explícitamente por el usuario el 2026-10-21** (precedente del módulo; ver Complexity Tracking). | Cumple |
| I. Endpoints administrativos con autenticación y capacidad `admin` | Como la 019, se autoriza por clave de permiso (`permiso:desarrollo.adecuacion.*`) tras `auth:sanctum`; el rol Administrador conserva todas las claves. La excepción ya fue aprobada para la 019, el propio documento de la feature la exige y el usuario la aprobó explícitamente para esta feature el 2026-10-21. | Cumple |
| II. Frontend modular, accesible y tipado | `frontend/src/modules/desarrollo/adecuacion/`; páginas sin Axios directo; TanStack Query sin reintento de escrituras; filtros y pestaña en la URL; texto centralizado en `text.ts`; loading/empty/error/success; WCAG 2.2 AA; Vitest y E2E. | Cumple |
| III. Diseño como fuente de referencia | Se sigue `design/Adecuacion*.dc.html`; las diferencias con la spec se registran aquí y en `design/DESIGN.md` (sección «Adecuación a puesto futuro (feature 020)») **antes** de implementar. | Cumple |
| IV. Calidad, seguridad y dependencias | Sin dependencias nuevas; entradas validadas; sin datos personales reales en seeders; errores seguros; cobertura de reglas de dominio, inmutabilidad, privacidad y permisos. | Cumple |

**Re-evaluación posterior al diseño**: sin cambios; ninguna violación sin justificar.

## Project Structure

### Documentation (this feature)

```text
specs/020-adecuacion-puesto-futuro/
├── plan.md              # Este archivo (/speckit-plan)
├── plan-adecuacion.md   # Insumo técnico original
├── specify-adecuacion.md
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   ├── api.md           # Contrato HTTP
│   ├── facade.md        # Facade propia y métodos aditivos
│   └── ui.md            # Contrato de pantallas (tableros)
├── checklists/requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks — NO lo crea este comando)
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Desarrollo/
│   ├── Domain/
│   │   ├── Entities/            # Adecuacion, ResultadoDeAdecuacion, ParametrosDeAdecuacion
│   │   ├── ValueObjects/        # CodigoDeAdecuacion, DimensionDeAdecuacion, EstadoDeResultado,
│   │   │                        # Preparacion, PesosPorDimension, ClaveDePermisoAdecuacion
│   │   ├── Services/            # CalculadorDeResultado, CalculadorDePreparacion,
│   │   │                        # CalculadorDeCumplimiento, CalculadorDePuntaje, ReglasDeParametrosDeAdecuacion
│   │   ├── Exceptions/          # PuestoObjetivoNoDefinido, SinRequisitosEvaluables, TiempoDeCierreFaltante,
│   │   │                        # PesosInvalidos, UmbralInvalido, FuenteNoDisponible (reutilizable si ya existe)
│   │   └── Repositories/        # AdecuacionesRepositoryInterface
│   ├── Application/
│   │   ├── Ports/               # PerfilDelPuestoObjetivoInterface (reutiliza PerfilesDePuestoInterface si cubre),
│   │   │                        # ValoresDeLaPersonaInterface, ExperienciaDelColaboradorInterface,
│   │   │                        # PuestoActualDelColaboradorInterface, RutasParaAdecuacionInterface
│   │   ├── Services/            # ArmadorDeDiagnostico, ArmadorDeAdecuaciones, AutorizadorDeAdecuacion
│   │   ├── DTO/                 # DiagnosticoDTO, ResultadoDTO, AdecuacionDTO, FilaDeMapaDTO, RankingDTO, ParametrosDTO
│   │   └── UseCases/            # PrevisualizarAdecuacion, GenerarAdecuacion, ListarAdecuaciones, ObtenerAdecuacion,
│   │                            # ObtenerMiAdecuacion, ObtenerMapaDeTalento, CompararCandidatos,
│   │                            # ObtenerParametrosDeAdecuacion, ActualizarParametrosDeAdecuacion,
│   │                            # ConsultarAdecuacionesParaOtrosModulos
│   ├── Infrastructure/
│   │   ├── Adapters/            # PerfilDelPuestoDesdeEstructura, ValoresDeLaPersonaDesdeDnf,
│   │   │                        # ExperienciaDesdePersonal, PuestoActualDesdePersonal, RutasParaAdecuacionDesdeFacade
│   │   ├── Persistence/Eloquent/ # Models + EloquentAdecuacionesRepository
│   │   └── Providers/DesarrolloServiceProvider.php   # bindings nuevos
│   ├── Presentation/
│   │   ├── Controllers/AdecuacionesController.php, MiAdecuacionController.php
│   │   ├── Requests/            # PrevisualizarRequest, GenerarRequest, ListarRequest, MapaRequest,
│   │   │                        # CompararRequest, ActualizarParametrosRequest
│   │   ├── Resources/           # AdecuacionResource, DiagnosticoResource, MapaResource, RankingResource…
│   │   └── Facades/DesarrolloFacade.php   # + ultimaAdecuacion, adecuacionesPorFiltros
│   └── Routes/api.php           # + grupo api/adecuaciones
├── app/Modules/GestionPersonal/Presentation/Facades/PersonalFacade.php   # + getPuestoActual (aditivo)
├── config/desarrollo.php        # + adecuacion.conocimientos_con_grado, adecuacion.experiencia_disponible
├── database/migrations/         # 4 migraciones
├── database/seeders/            # AdecuacionesSeeder, PermisosAdecuacionSeeder (o catálogo en código)
└── tests/{Unit,Feature}/Desarrollo/Adecuacion/

frontend/src/modules/desarrollo/adecuacion/
├── api.ts  hooks/  text.ts  validation.ts  permisos.ts  nav.tsx  estilosEstado.ts
├── components/              # SelectorDeColaboradorYObjetivo, ResumenDeAdecuacion, LeyendaDeBarras, PanelDeDimension,
│                            # TablaDeBrechas, AccionesDeAdecuacion, HistorialDeColaborador, PanelDePesosYPuesto,
│                            # SelectorDeCandidatos, TablaDeRanking, KpisDelMapa, TablaDelMapa, BarraDeFiltrosMapa,
│                            # TarjetaDeDimensionColaborador, ListaLoQueMeFalta, FormularioDeParametros, HistorialDeParametros, Etiquetas
└── pages/                   # AdecuacionPage (pestañas), MiAdecuacionPage
```

**Structure Decision**: aplicación web con backend Laravel modular y frontend React modular; la feature vive en `Desarrollo` (backend) y `desarrollo/adecuacion` (frontend), y solo toca `GestionPersonal` con un método de lectura aditivo en su Facade.

## Diferencias entre tableros y spec (manda la spec en reglas y datos; los tableros en aspecto y estructura)

Instrucción del usuario: guiarse **exactamente** por los HTML. Donde el tablero contradice una regla de la spec, se conserva el aspecto del tablero y se adapta solo el dato o el rótulo.

| Tablero | Diferencia | Resolución |
|---|---|---|
| Los 4 | Menú lateral de prototipo (Empleados, Ajustes, «Mi perfil») y botón «Ver como colaborador». | **Se omiten**: el App Shell real ya existe y cada persona tiene un solo rol. El ítem «Adecuación» se agrega a la navegación declarativa con `aria-current` y su permiso. |
| Diagnóstico/Comparar/Mapa | Tres pestañas: **Diagnóstico**, Comparar candidatos, Mapa de talento. La spec pide Evaluaciones, Comparar, Mapa y Parámetros. | Se conservan las tres pestañas del tablero tal cual y se **añaden** «Evaluaciones» (historial global, FR-022) y «Parámetros» (FR-029, solo con permiso) con el mismo estilo de pestaña. «Diagnóstico» es la pantalla del asistente. |
| Diagnóstico | No hay asistente de 3 pasos: una sola pantalla con selectores, resumen, tres paneles, tabla de brechas y acciones. | Se conserva la pantalla única y se **mapean** los pasos: *Selección* = tarjeta de selectores (Colaborador, Puesto objetivo); *Diagnóstico* = resumen + paneles + tabla «Brechas y tiempo estimado de cierre»; *Confirmación* = bloque de acciones, al que se **agrega** el campo Observaciones y la preparación calculada. «Registrar esta evaluación en el historial» es la confirmación (con diálogo que muestra la preparación). |
| Diagnóstico | La preparación se clasifica por porcentaje: «Listo» desde 80 %, «Listo con desarrollo» desde 60 %, «No listo» por debajo (texto del aviso y KPIs del mapa). | **Manda la spec (FR-016)**: preparación por brechas y tiempo de cierre contra el umbral en meses: **Lista, En desarrollo, No lista, Incompleta**. Los porcentajes se conservan **solo como cumplimiento informativo** por dimensión y global. El texto del aviso se reemplaza por el de la spec. |
| Diagnóstico | Rótulos «Listo / Listo con desarrollo / No listo / Sin siguiente nivel». | Rótulos de la spec: «Lista», «En desarrollo», «No lista», «Incompleta»; «Sin siguiente nivel» se muestra como estado vacío de selección (FR-005). |
| Diagnóstico | Orden de paneles: Experiencia, Conocimientos, Competencias (y «Exp. / Conoc. / Comp.» en tablas). La spec lista Competencias | Conocimientos | Experiencia. | **Se conserva el orden del tablero** (Experiencia, Conocimientos, Competencias) en paneles y columnas de porcentaje. |
| Diagnóstico | El tiempo de cierre se pide en meses de 0 a 60. | La spec exige entero **mayor que cero** (FR-015); se conserva el campo numérico con `min=1`; el máximo se fija en 60 como tope razonable. |
| Diagnóstico | «Crear PDI» y «Incluir en programa» / «Registrar esta evaluación en el historial». | Rótulos de la spec: «Crear plan de desarrollo» e «Incluir en Programa de Talentos», **deshabilitados** (sin brechas o sin PDI, FR-034). «Registrar esta evaluación» se conserva. |
| Diagnóstico | Resumen con «tipoCambio» (promoción/lateral), «área» y «años de práctica». | Se conserva. Tipo de cambio se deduce del puesto objetivo (misma ruta/distinto puesto → «Promoción»; puesto directo → «Desplazamiento lateral o promoción sin ruta»); el área es el departamento actual. |
| Diagnóstico | Selector «Siguiente nivel de su ruta (por defecto)». | Se conserva y se adapta a la clarificación: con varias inscripciones Activas el selector lista cada una («Ruta X · Nivel N») y el administrador elige; con una sola se preselecciona; más la opción «Puesto directo». |
| Diagnóstico | «Historial de adecuaciones de {colaborador}» al pie. | Se conserva (últimas del colaborador, solo lectura). El historial global con filtros va en la pestaña «Evaluaciones». |
| Comparar | Pesos en el orden Experiencia, Conocim., Compet.; sin restricción de suma («pesos que sumen más de cero»). | Se conserva el orden y los rótulos; se **exige suma 100** (FR-027) con mensaje; los pesos de la consulta no modifican los guardados. Los candidatos Incompleta y «sin adecuación» se muestran en bloques propios bajo el ranking (no en el tablero). |
| Comparar | El ranking incluye barra y botón «Ver diagnóstico». | Se conserva; «Ver diagnóstico» abre el detalle de solo lectura de la adecuación vigente del candidato. |
| Mapa | Filtro «Área» con lista fija; opciones «Sin siguiente nivel». | Se llama **Departamento** y se alimenta de Estructura; se retira «Sin siguiente nivel». Filtros: Departamento, Ruta, Preparación (FR-028), todos en la URL. |
| Mapa | KPIs: Listos, Listos con desarrollo, No listos, En Programa de Talentos. | KPIs: Lista, En desarrollo, No lista, Incompleta (conteo de filas vigentes). «En Programa de Talentos» **se omite** (depende del PDI, aún no implementado). |
| Mapa | Columnas: Colaborador, Ruta, Puesto actual → objetivo, Exp./Conoc./Comp., Preparación, Programa de Talentos, Acciones. | Se conservan y se **añaden** «Brechas» y «Tiempo máx. (meses)» (FR-028). «Programa de Talentos» muestra «—» y «Incluir» queda deshabilitado con su motivo. |
| Mapa | «Solo se listan colaboradores inscritos en una ruta vigente». | La spec lista toda adecuación vigente (incluye puesto directo); el texto se ajusta a «Se muestra la adecuación vigente de cada colaborador por puesto objetivo». |
| Colaborador | Selector «Puesto a comparar» con puestos de su ruta o habilitados por RR. HH. | Se conserva como selector entre **sus adecuaciones vigentes** (una por puesto objetivo, la más reciente primero), según la clarificación. |
| Colaborador | Tarjetas con porcentaje y «dif». «Lo que me falta» con curso sugerido y sugerencia. | Se conserva; el botón «Ver cursos sugeridos» lleva al Catálogo Formativo filtrado por el elemento (competencia/conocimiento); en experiencia se muestra «Se desarrolla con práctica; revisa tu plan de desarrollo» (FR-031). Se muestra el tiempo estimado de cierre. |
| Colaborador | Sección «Programa de Talentos» con casilla «Simulación: estoy incluido en un programa». | La **simulación se omite**. La sección muestra «Por ahora no estás incluido en un Programa de Talentos» como estado informativo (el programa depende del PDI). |
| Colaborador | Aviso «Esto es una guía de desarrollo; no implica la promesa de un puesto.» + «Solo ves tu propia adecuación…». | Se conserva con el texto exacto de la spec: «Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.» |
| Colaborador | Muestra «Nivel de preparación» como porcentaje/categoría. | Insignia de la preparación de la spec; **no se muestra ningún puntaje de comparación** (SC-004). |

Estas diferencias se registran aquí (Constitución III) y en `design/DESIGN.md` (primera tarea de implementación).

## Complexity Tracking

| Desviación | Por qué es necesaria | Alternativa simple rechazada porque |
|---|---|---|
| Rutas `/api/adecuaciones` (no `/api/desarrollo/adecuaciones`) (**aprobada explícitamente por el usuario el 2026-10-21, Constitución I**) | Es la convención de todas las features de `Desarrollo` (`/api/rutas`, `/api/dnf`, `/api/reportes`) y de `plan-adecuacion.md`. | Un prefijo distinto rompería la coherencia del módulo y del cliente HTTP. |
| Autorización por clave de permiso en vez de capacidad `admin` (**aprobada explícitamente por el usuario el 2026-10-21, Constitución I**) | La 019 lo aprobó y la spec exige claves estables asignables a otros roles (`desarrollo.adecuacion.*`). | `admin` no distingue `ver_propio` del colaborador ni permite reasignar claves. |
| Método aditivo `getPuestoActual` en `PersonalFacade` | No existe otro contrato público que devuelva el puesto actual del colaborador (FR-004). | Leer tablas de Personal desde Desarrollo violaría la constitución. |

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/adecuacion` | **Una sola ruta, dos vistas según permisos** (como `rutas/:id` con `RequireRole`; la 019 ya enlaza «Ver mi adecuación» a `/adecuacion`): con alguna clave de gestión, Adecuación a puesto futuro con pestañas Diagnóstico (asistente), Comparar candidatos, Mapa de talento, Evaluaciones y Parámetros (`?pestana=`); con solo `desarrollo.adecuacion.ver_propio`, Mi adecuación (`?puesto=`) | Administrador (gestión) · Colaborador (`ver_propio`); cada pestaña exige su clave |
| `/adecuacion/evaluaciones/:id` | Detalle de solo lectura de una adecuación (desde Evaluaciones, Mapa o Ranking) | `desarrollo.adecuacion.ver` |

### Componentes
- **Nuevos**: los listados en la estructura (selector de colaborador y objetivo, resumen, leyenda y barras triples, panel por dimensión, tabla de brechas con tiempo, acciones y confirmación, historial del colaborador, pesos y puesto, chips de candidatos, ranking, KPIs y tabla del mapa, tarjetas del colaborador, formulario e historial de parámetros, etiquetas de preparación/estado).
- **Del design system**: Tabs, Select/Combobox, Input numérico, Button, Badge, Card, Table, Alert (error y neutro), Skeleton, Modal de confirmación, Paginación, Estados vacío/error — reutilizando los de las features 013–019.

### Datos y estado
- Endpoints por pantalla en [contracts/api.md](contracts/api.md); pantallas y textos en [contracts/ui.md](contracts/ui.md).
- Claves de caché: `adecuacion/opciones`, `adecuacion/previsualizar`, `adecuacion/lista?filtros`, `adecuacion/{id}`, `adecuacion/mapa?filtros`, `adecuacion/comparar?…`, `adecuacion/parametros`, `adecuacion/mia`. Tras `POST /api/adecuaciones` se invalidan lista, mapa, `mia` y el historial del colaborador; tras `PUT /parametros`, parámetros. Las mutaciones no se reintentan.
- Estado en URL: pestaña, filtros de Evaluaciones y Mapa (fechas, colaborador, puesto, preparación, departamento, ruta), página, puesto y candidatos de la comparación, y el selector de puesto en «Mi adecuación».

### Validación
- Tiempo de cierre: entero 1–60 por cada brecha; el botón «Registrar esta evaluación» se bloquea con el listado de brechas sin tiempo.
- Pesos (consulta y parámetros): enteros ≥ 0 que suman 100; umbral entero ≥ 1; observaciones ≤ 500; experiencia requerida ≥ 0 con un decimal.
- Errores 422 se mapean al campo (`tiempos_cierre.<n>`, `pesos`, `umbral_meses`, `observaciones`, `experiencia_requerida_anios`); 503 muestra el `Alert` neutro con «Reintentar».
