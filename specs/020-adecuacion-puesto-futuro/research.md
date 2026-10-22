# Research: Adecuación a puesto futuro y mapa de talento

Resuelve las incógnitas del plan. Formato: Decisión · Justificación · Alternativas.

## R1 — Estructura del módulo y rutas HTTP
- **Decisión**: Clean Architecture dentro de `Modules/Desarrollo` (como 018 y 019); grupo de rutas `api/adecuaciones` con `auth:sanctum` y `permiso:<clave>` por operación; orden de rutas fijo (`/previsualizar`, `/mi-adecuacion`, `/mapa`, `/comparar`, `/parametros` antes de `/{id}` con `whereNumber`).
- **Justificación**: es el patrón vigente (`/api/rutas`, `/api/dnf`); evita colisiones entre `/{id}` y las rutas literales.
- **Alternativas**: prefijo `/api/desarrollo/adecuaciones` (rompe coherencia); controlador único (viola controladores delgados).

## R2 — Cálculo de grados sin duplicar la fórmula
- **Decisión**: la escala se toma de `NivelGrado::valorNumerico()` (único lugar donde se define D=1…A=4). La regla de estado (diferencia ≥ 0 Cumple/Supera; < 0 Con brecha; sin valor → Sin evaluación) se aplica en un `CalculadorDeResultado` del dominio de esta feature que **usa** `NivelGrado`. No se reutiliza `CalculadorDeBrecha` directamente porque devuelve `Brecha` de DNF con porcentaje y prioridad (conceptos propios de DNF) y exige `TipoCompetenciaDnf`.
- **Justificación**: cumple FR-011 («no duplicar la fórmula de la escala») sin acoplar la feature a la entidad `Brecha`. La experiencia se calcula en años con el mismo criterio de signo.
- **Alternativas**: llamar a `CalculadorDeBrecha` y descartar campos (acopla a DNF y a su `TipoCompetenciaDnf`); extraer un servicio compartido (refactor ajeno al alcance, se deja como mejora futura si DNF lo quiere).

## R3 — Valor de la persona en competencias
- **Decisión**: puerto `ValoresDeLaPersonaInterface::nivelesDeCompetencias(tenantId, colaboradorId): array<competenciaId,string>` con adaptador `ValoresDeLaPersonaDesdeDnf` que lee el **último nivel evaluado por competencia** del colaborador en sus DNF (repositorio de DNF, mismo módulo). Sin DNF o sin esa competencia → «Sin evaluación».
- **Justificación**: Gestión de Personal aún no expone nivel por competencia (`NivelesActualesSinDatos`/`NivelesDeColaboradorSinDatos` siguen enlazados); la DNF sí guarda `nivel_actual` por competencia evaluada. El puerto aísla el cambio futuro: cuando Personal lo exponga, se reemplaza el adaptador.
- **Alternativas**: reusar `NivelesActualesInterface` (devuelve vacío hoy y DNF depende de él → todo «Sin evaluación»); leer tablas de DNF con SQL (violaría el repositorio). **Confirmar con el equipo** que la DNF es la fuente válida de «nivel actual» mientras Personal no la sustituya.

## R4 — Conocimientos con grado
- **Decisión**: bandera `desarrollo.adecuacion.conocimientos_con_grado` (por defecto `false`, `env DESARROLLO_ADECUACION_CONOCIMIENTOS_CON_GRADO`) que, apagada, **desactiva la dimensión Conocimientos**: no se crean resultados, el diagnóstico agrega un aviso informativo y los pesos se reescalan. Encendida, el adaptador del perfil entrega los conocimientos con grado y el valor de la persona viene del puerto de valores (extensión no implementada en esta entrega).
- **Justificación**: la 019 estableció que Estructura no tiene conocimientos con grado; el documento del plan pide una bandera que desactive la dimensión. Evita que toda adecuación quede «Incompleta».
- **Alternativas**: listar conocimientos «Sin evaluación» (volvería Incompleta a todas); fingir grados (inventar datos).

## R5 — Experiencia acumulada
- **Decisión**: puerto `ExperienciaDelColaboradorInterface::aniosDe(tenantId, colaboradorIds)` con adaptador `ExperienciaDesdePersonal` sobre `PersonalFacade::getDesarrollo` (experiencias anteriores con `fecha_inicio`/`fecha_finalizacion`), sumando años sin superposición, a un decimal. Sin experiencias o con `InformacionNoDisponible` → «Sin evaluación». Bandera `desarrollo.adecuacion.experiencia_disponible` (por defecto `true`) para desactivar la dimensión si la fuente no sirve.
- **Justificación**: es el único contrato público de Personal con experiencia (GP-06). La experiencia requerida solo se evalúa si existe (nivel de ruta o ingreso manual); con requerida nula la dimensión no se evalúa y no cuenta como requisito sin evaluar.
- **Alternativas**: contar antigüedad en la empresa (no está en el contrato y no es «experiencia» general); pedir el dato al administrador (rompe la regla de no capturar datos de Personal).
- **Pendiente de confirmar** con Gestión de Personal si la experiencia dentro de la empresa también debe sumarse.

## R6 — Puesto actual del colaborador
- **Decisión**: puerto `PuestoActualDelColaboradorInterface` con adaptador `PuestoActualDesdePersonal` que llama a un método **aditivo** `PersonalFacade::getPuestoActual(tenantId, colaboradorIds, fecha)` (puesto de la posición de la asignación laboral vigente, como `getDepartamentoActual` obtiene la unidad). Si no se puede resolver, `puesto_actual_id = null` y «Requerido en el puesto actual» se muestra vacío.
- **Justificación**: FR-004 exige copiar el puesto actual; no hay otro contrato público. El `LectorEstructuraPort` de Personal ya consulta posiciones de Estructura.
- **Alternativas**: pedir el puesto al administrador (dato ajeno); consultar `posicionesConAsignacionVigenteOFutura` (devuelve posiciones, no puestos).

## R7 — Perfil del puesto objetivo y actual
- **Decisión**: se reutiliza `PerfilesDePuestoInterface::perfilDe(puestoId)` (competencias activas e inactivas con `nivel_requerido`) vía su adaptador existente sobre `EstructuraFacade`; las inactivas se omiten y se cuentan en `avisos`. Los conocimientos no vienen del perfil (R4).
- **Justificación**: evita un puerto duplicado; es el mismo que usa DNF/Rutas.

## R8 — Fuente del puesto objetivo desde Rutas
- **Decisión**: puerto `RutasParaAdecuacionInterface` con adaptador sobre `DesarrolloFacade::inscripcionesActivas(tenantId, colaboradorId)` y `obtenerNivel(tenantId, nivelId)`. Se guarda `inscripcion_id` y `nivel_objetivo_id` (FK a tablas del mismo módulo) como referencias de origen. La experiencia requerida del nivel viene de `experiencia_minima_anios`.
- **Justificación**: son los contratos aditivos que la 019 dejó para esta feature. Con varias inscripciones Activas el administrador elige (clarificación 1).

## R9 — Preparación, cumplimiento y puntaje
- **Decisión**: `CalculadorDePreparacion` (FR-016, en este orden: Incompleta si algún resultado Sin evaluación; si no, Lista sin brechas; En desarrollo si todas ≤ umbral; No lista si alguna > umbral). `CalculadorDeCumplimiento`: por resultado `min(1, valor ÷ requerido)` (competencias con la escala numérica; experiencia en años; requerido 0 ⇒ 1); por dimensión, promedio de los resultados evaluados. `CalculadorDePuntaje`: Σ(peso_d × cumplimiento_d) sobre las dimensiones con resultados evaluados, con pesos reescalados (peso_d ÷ Σ pesos presentes). Redondeo a 1 decimal solo al mostrar.
- **Justificación**: textualmente FR-024/FR-025; el cumplimiento y el puntaje se derivan de los detalles guardados, por lo que no requieren columnas nuevas.
- **Alternativas**: guardar el puntaje (la comparativa no se guarda, FR-027).

## R10 — Adecuación vigente y consultas de lista, mapa y comparación
- **Decisión**: vigente = la más reciente por `(colaborador_id, puesto_objetivo_id)` (por `created_at` y `id` como desempate). Se resuelve en el repositorio con una consulta por grupos, sin N+1; nombres, puestos y departamentos se enriquecen en lote por Facade (`NombresDeReferenciaInterface`, `DepartamentoDeColaboradorInterface`, `EstructuraFacade::getPuestosPorIds`). El filtro «ruta» del mapa usa el `inscripcion_id`→`ruta_id` guardado; las adecuaciones por puesto directo no tienen ruta y aparecen sin ella.
- **Justificación**: SC-003 y SC-008.

## R11 — Privacidad del colaborador
- **Decisión**: `GET /api/adecuaciones/mi-adecuacion` resuelve el colaborador del usuario por `CuentaColaboradorInterface` (no se acepta `colaborador_id` en la consulta) y devuelve una **lista** de sus adecuaciones vigentes (una por puesto objetivo) con el DTO «propio»: sin puntajes, sin ranking, sin datos de otras personas, sin quién la generó. Es el único endpoint con `ver_propio`; `GET /{id}` exige `ver` y no admite `ver_propio`.
- **Justificación**: FR-030 y clarificación 2; evita enumeración por id.

## R12 — Autorización
- **Decisión**: `ClaveDePermisoAdecuacion` (catálogo de claves y roles por defecto, como `ClaveDePermisoRutas`), `AutorizadorDeAdecuacion` y el middleware `permiso:` existente; `GET /api/adecuaciones/permisos` devuelve las claves del usuario para el frontend.
- **Justificación**: un único punto de autorización (plan-adecuacion §3).

## R13 — Inmutabilidad
- **Decisión**: el repositorio expone `guardar` (inserta cabecera y detalles en una transacción) y lecturas; no hay `actualizar`/`eliminar`. Pruebas de arquitectura/feature: no existen rutas PUT/PATCH/DELETE y el modelo Eloquent bloquea `update/delete` (eventos que lanzan excepción de dominio).
- **Justificación**: FR-018 y SC-005.

## R14 — Concurrencia en el código
- **Decisión**: el código ADE-NNNN se asigna dentro de la transacción de guardado con la misma estrategia de contador usada por `CodigoDeRuta`/`CodigoPlan` (única restricción + reintento acotado del número).
- **Justificación**: dos confirmaciones simultáneas no repiten número.

## R15 — Cambio de inscripción durante el asistente
- **Decisión**: `GenerarAdecuacion` vuelve a leer la inscripción y el nivel; si el puesto objetivo recalculado difiere del enviado, responde 409 `objetivo_cambio` y el frontend pide volver a generar el diagnóstico.
- **Justificación**: caso límite de la spec.

## R16 — Frontend
- **Decisión**: una página `AdecuacionPage` con pestañas (la pestaña activa y los filtros en la URL), `MiAdecuacionPage`; hooks de TanStack Query por recurso; formularios con React Hook Form + Zod; barras triples y tablas con los componentes ya usados en 018/019. Visibilidad de pestañas y botones por claves de `GET /api/adecuaciones/permisos`.
- **Alternativas**: una ruta por pestaña (el tablero es una sola página).

## R17 — Siembra
- **Decisión**: `AdecuacionesSeeder` crea parámetros 40/30/30 y 12, y cuatro adecuaciones (Lista, En desarrollo, No lista, Incompleta) sobre los datos de `RutasSeeder`, Estructura y Personal, con un puesto objetivo de tres candidatos y una adecuación por puesto directo. No depende de `IngresosSeeder`; usa datos de prueba sin personas reales. Como la DNF es la fuente de niveles, el seeder crea o reutiliza una DNF con niveles para los colaboradores sembrados. Los permisos se siembran por catálogo en código (no hay tabla).
- **Justificación**: el insumo exige las cuatro preparaciones; memoria de proyecto: los E2E de 018 evitaron `IngresosSeeder`.

## R18 — Pruebas
- **Decisión**: Unit de dominio (calculadores, reglas de parámetros, vigente), Feature HTTP (permisos 401/403, previsualizar/generar/lista/detalle/mi-adecuacion/mapa/comparar/parámetros, inmutabilidad, privacidad, 422/409/503), un E2E (administrador genera una adecuación y el colaborador la ve en «Mi adecuación»), Vitest de validación, de pesos y de componentes clave.

## R19 — Rutas de frontend y enlace desde «Mi ruta»
- **Decisión**: ruta única `/adecuacion` que decide la vista por permisos (administrador o colaborador) y `/adecuacion/evaluaciones/:id` para el detalle. `ObtenerMiRuta` pasa `puede_ver_adecuacion` a `true` cuando hay nivel objetivo (la 019 ya enlaza a `/adecuacion`). El Catálogo Formativo se abre en `/formacion?competencia=<id>`, parámetro que `useListadoParams` ya admite.
- **Justificación**: coherencia con la 019 y reutilización del filtro existente del catálogo.
