# Research: Plan de desarrollo individual (PDI)

Decisiones que cierran los puntos abiertos del plan técnico. Ninguna requiere dependencias nuevas. Se verificaron contra el código actual de `backend/app/Modules/Desarrollo` y `frontend/src/modules/desarrollo`.

## R1. Reconciliación de `plan-pdi.md` con las aclaraciones de la spec (sesión 2026-10-22)

`plan-pdi.md` se escribió antes de las aclaraciones. **Manda la spec**. Cambios sobre el insumo:

| Punto del insumo | Cambio |
|---|---|
| `POST /{id}/acuerdo`: «si ya existe la aceptación del colaborador, pasa a Acordado» | El acuerdo del responsable **siempre es previo**. Nunca cambia el estado; hace visible el plan al colaborador. |
| `POST /{id}/aceptar` y `devolver-colaborador`: solo exigen Borrador | Además exigen `acuerdo_responsable_at` no nulo (422). Aceptar con el acuerdo vigente pasa el plan a Acordado en la misma transacción (FR-027). |
| `GET /mi-plan` y `GET /{id}` propio | Excluyen (404 en el detalle) un Borrador sin acuerdo del responsable (FR-047). |
| «No se edita el responsable en Acordado» (implícito) | Nuevo `PATCH /{id}/responsable` (`desarrollo.pdi.gestionar`) permitido en Borrador y Acordado, nunca en Cerrado; en Acordado conserva los acuerdos y deja historial (FR-006). |
| El estado de una acción vinculada «no se guarda: se calcula» | Se calcula **mientras el plan no esté Cerrado**; al cerrar se **persiste el estado final** de cada acción vinculada y deja de derivarse (FR-020a). |
| Modelo sin `tenant_id` | Todas las tablas llevan `tenant_id`, como `adecuaciones` y `rutas`. |
| `GET /pdi/sin-plan` solo mide una DNF | Se define con dos fuentes (R6). |
| Modal de «Devolver con comentario» del colaborador | Igual; además la devolución borra también `acuerdo_responsable_*`, por lo que el plan desaparece de «Mi plan» hasta un nuevo acuerdo. |

## R2. Permisos `desarrollo.pdi.*`: mismo patrón que Rutas y Adecuación

- **Decisión**: enum de dominio `ClaveDePermisoPdi` (nueve claves) con `deAdministrador()` (las siete claves de gestión: `gestionar`, `acordar`, `seguimiento`, `vincular`, `cerrar`, `gantt` y `ver`; sin las `_propio`) y `deColaborador()` (`ver_propio`, `aceptar_propio`); servicio `AutorizadorDePdi`; el middleware `EnsurePermiso` recibe el autorizador nuevo como tercer colaborador. `GET /api/pdi/permisos` devuelve las claves del usuario para el frontend.
- **Racional**: es el único punto de autorización ya establecido (019/020); cuando exista administración de roles solo cambia la fuente del autorizador. Los controladores no consultan el rol.
- **Alternativas**: tabla de permisos en BD (fuera de alcance, no existe aún); `admin` genérico (no distingue `ver_propio`/`aceptar_propio`).
- **Nota**: la regla «solo datos propios» para claves `_propio` la aplica el caso de uso (resuelve el colaborador del usuario con `CuentaColaboradorInterface`), no el controlador.

## R3. Estado de una acción vinculada

- **Fuentes reales**: `AccionesDePlanInterface::porIds` (acción con `cancelada`, `fechaInicio/fechaFin`, `planEstado`, `colaboradorIds`) y `DesarrolloFacade::getAsistenciaDeColaborador(tenant, colaborador, accionIds)` → `{accion_id, actividad_id, estado, asistencia, calificacion}` donde `estado` es el de la actividad (`Programada|EnCurso|Finalizada|Cancelada`) y `asistencia` ∈ `Pendiente|Presente|Ausente|Justificado`.
- **Decisión**: función pura de dominio `DerivadorDeEstadoDeAccion` con la regla de FR-020:
  1. acción del plan cancelada **o** actividad `Cancelada` → `Cancelada`;
  2. actividad `Finalizada` **y** asistencia `Presente` → `Cumplida` (`Justificado` y `Ausente` no cumplen);
  3. si no, `EnCurso` si hoy ∈ [fecha_inicio, fecha_fin] de la **acción del PDI**; en otro caso `Pendiente`. (La spec habla de «las fechas»; se usan las de la acción del PDI, que es lo que el administrador acordó.)
- **Congelamiento (FR-020a)**: `CerrarPdi` resuelve el estado de cada acción vinculada en la misma transacción y lo guarda en `pdi_acciones.estado`. Una columna `estado_congelado` (booleano) evita ambigüedad: con `plan.estado = Cerrado` el lector usa el guardado. Se conserva `plan_accion_id`.
- **Lecturas masivas**: una sola llamada a `getAsistenciaDeColaborador` por colaborador y una a `porIds` por lista (sin N+1), tanto en detalle como en Gantt.
- **Alternativa rechazada**: guardar el estado derivado en cada lectura (genera escrituras en GET y rompe la auditoría).

## R4. Acciones del Plan de Formación que se pueden vincular (Aprobado o Ejecutando)

- **Brecha**: `AccionesDePlanInterface::deColaborador` solo cubre planes Ejecutando/Cerrado, y `elegibles` solo Ejecutando. La spec exige **Aprobado o Ejecutando** con el colaborador como participante (FR-019).
- **Decisión**: método aditivo de solo lectura `accionesVinculablesDeColaborador(tenantId, colaboradorId)` en el puerto `AccionesDePlanInterface` y en `PlanRepositoryInterface` (consulta propia del módulo de planes), expuesto por la Facade como `getAccionesVinculablesDeColaborador`. Devuelve `[{accion_id, plan_id, plan_codigo, plan_nombre, plan_estado, nombre, tipo, fecha_inicio, fecha_fin, formador}]` excluyendo canceladas.
- **Validación al vincular**: el caso de uso vuelve a pedir la lista y exige que `plan_accion_id` esté en ella (no confía en el cliente), y que el tipo de la acción del PDI sea Curso, Mentoring o Codesarrollo (FR-018).
- **Tipo de acción del plan vs. tipo del PDI**: no se exige igualdad (un «Curso» del PDI puede vincularse a una acción Presencial, Virtual, ELearning u OJT; «Mentoring» y «Codesarrollo» pueden vincularse a cualquiera de las vinculables). Se **muestra** el tipo del plan en el selector. La validación de la spec es solo por tipo del PDI.
- **Participante después de vincular**: si el colaborador sale de la acción o la acción se cancela, se muestra «Requiere revisión» (acción cancelada ya deriva a Cancelada; salida de participante marca `requiere_revision = true` en la lectura).

## R5. Precarga: fuentes reales y límites

- **DNF (tipo Mejora)**: `DesarrolloFacade::brechasActivas(tenant, colaborador)` devuelve `{competencia_id, nivel_requerido, nivel_actual, diferencia, prioridad}`; **no devuelve `dnf_id`** y solo cubre competencias. **Decisión**: ampliar de forma aditiva el caso de uso/Facade con `ultimaDnfDeColaborador` (o incluir `dnf_id` en `brechasActivas` como campo extra) para guardar `origen_dnf_id`. Conocimientos no existen con grado: la dimensión Conocimiento queda detrás de la bandera `desarrollo.pdi.conocimientos_con_grado` (por defecto `false`), igual que 019/020 (R4 de 020) y como pide `plan-pdi.md` §1. Con `false` la dimensión **se desactiva**: la precarga no genera conocimientos, `GET /opciones` no la lista en `dimensiones_disponibles` y crear/editar un compromiso de Conocimiento responde 422. La rama `true` no se implementa.
- **Adecuación (Carrera/Talento)**: `DesarrolloFacade::ultimaAdecuacion(tenant, colaborador, puestoObjetivoId)` devuelve `{adecuacion_id, …, brechas: [{dimension, tipo_elemento, elemento_id, nombre, diferencia, tiempo_cierre_meses}]}`. Los niveles A–D no vienen en `brechas`; **decisión**: ampliar la Facade de forma aditiva para incluir `nivel_actual`/`nivel_objetivo` (ya existen en los detalles guardados), o resolver el detalle con `ObtenerAdecuacion`. La brecha de Experiencia se precarga como compromiso de dimensión Experiencia con descripción generada («Alcanzar N años de experiencia»), sin niveles.
- **Puesto objetivo en Carrera (FR-041)**: si el colaborador tiene inscripción Activa, `inscripcionesActivas` aporta `nivel_objetivo.puesto_id` y se ofrece como valor por defecto; el administrador puede elegir otro puesto activo.
- **Brechas activas «sin plan» (R6)** y **elementos inactivos**: la precarga omite con aviso los elementos que `CompetenciasInterface::estaActiva` rechaza.

## R6. Indicador «Personas con brecha y sin PDI»

- **Decisión**: un colaborador entra al indicador si tiene **brecha activa en alguna fuente** y **no tiene plan no Cerrado del tipo que corresponde a esa fuente**:
  - brecha activa de DNF (`brechasActivas` no vacío) y sin plan Mejora abierto → «Mejora»;
  - adecuación vigente con brechas (`adecuacionesPorFiltros` con `brechas > 0`) y sin plan Carrera ni Talento abierto para ese puesto objetivo → «Carrera/Talento».
- El listado se arma por departamentos (Facade de Personal) y se pagina; cada fila lleva `origen` (`dnf|adecuacion`) y el acceso «Crear PDI» con colaborador, tipo sugerido y puesto objetivo precargados.
- **Rendimiento**: `brechasActivas` es por colaborador; se limita a colaboradores vigentes del filtro de departamento y se resuelve en lotes de la página, con tope de 200 candidatos por consulta.

## R7. Código secuencial PDI-0001

- **Decisión**: igual que `CodigoDeAdecuacion`: valor de objeto `CodigoDePdi`, siguiente número por `tenant_id` calculado en la transacción de creación con bloqueo del último código; índice único `(tenant_id, codigo)`. Reintento acotado ante colisión.

## R8. Unicidad «un plan no Cerrado por colaborador y tipo»

- **Decisión**: validación en `CrearPdi` dentro de la transacción **y** red de seguridad en BD. SQLite admite índices únicos parciales: `CREATE UNIQUE INDEX … ON pdis (tenant_id, colaborador_id, tipo) WHERE estado <> 'Cerrado'` vía `DB::statement` en la migración (Laravel no lo expresa en el Blueprint). El conflicto se traduce a 422 con el código del plan existente.
- **Alternativa**: columna booleana `abierto` única compuesta. Se rechaza por duplicar el estado.

## R9. Concurrencia (edge case de la spec)

- **Decisión**: control optimista con `version` (entero) en `pdis`. `PUT`, `acuerdo`, `aceptar`, `devolver`, `cerrar` y `responsable` reciben `version` y fallan con **409** si cambió. La edición de Borrador incrementa `version` y borra los acuerdos; una aceptación con versión anterior recibe 409 y el colaborador recarga (y ve que el plan ya no está disponible si el acuerdo se borró).

## R10. Reemplazo de hijos en Borrador sin DELETE físico

- **Decisión**: `plan-pdi.md` pide borrado explícito dentro de la transacción de actualización, sin cascade y sin soft deletes. Se mantiene: el repositorio reemplaza compromisos, acciones e hitos (delete explícito de las filas hijas del Borrador dentro de `transaccion()`); es lo único que borra filas y solo en Borrador. Los vínculos de acción se conservan al reemplazar cuando la acción mantiene su identidad lógica (se reenvía `plan_accion_id` en la edición). Los IDs de hijos **cambian** al editar un Borrador; el historial referencia el plan, no los hijos.
- **Consecuencia**: `pdi_historial.referencia_id` solo se usa con acciones e hitos de planes Acordado (cuya estructura no se reemplaza).

## R11. Responsables válidos

- **Decisión**: `AprobadoresInterface::esAdministradorActivo` valida al crear/editar/cambiar responsable (422 si no es administrador activo); `administradores(tenant)` alimenta el selector. Si el responsable de un plan pierde el rol, se marca `responsable_sin_permiso = true` en la lectura y el administrador puede reasignarlo (R1).

## R12. Gantt y «Qué sigue»

- **Decisión**: ambas son lecturas calculadas. El Gantt devuelve acciones de planes Acordado (con estado resuelto) e hitos; paginado por plan. «Qué sigue» devuelve, de los planes Acordado propios, las acciones `Pendiente|EnCurso` y los hitos `Pendiente` ordenados por fecha (los vencidos primero y destacados), con `actividad_url` para las acciones vinculadas (enlace a «Mis actividades» de 017).
- **Enlace a la actividad**: `getAsistenciaDeColaborador` trae `actividad_id`; el frontend enlaza a la ruta de actividades del colaborador ya existente.

## R13. Integraciones con otras features (cambios aditivos)

- **020 (Adecuación)**: habilitar «Crear plan de desarrollo» e «Incluir en Programa de Talentos» (FR-044) apuntando a `/pdi/nuevo?colaborador=&tipo=&puesto_objetivo=`; para decidir si habilitarlos se usa `DesarrolloFacade::existePlanAbierto(tenant, colaborador, tipo)`.
- **018 (Reportes)**: la Facade expone `planesActivos(tenant, colaborador)` y `pdiPorFiltros(tenant, filtros)`; Reportes no se modifica en esta feature (solo se publican los contratos).
- **016 (Micro-Learning)**: la sugerencia de micro-contenidos en Autodesarrollo (FR-042) queda **fuera del alcance** de esta entrega: obligaría a un endpoint nuevo o a que el frontend importe otro módulo. `getMicrocontenidosPublicadosPorCompetencia` ya existe para cuando se retome.
- **Cerrar Carrera/Talento**: la respuesta incluye `ofrecer_adecuacion: { colaborador_id, puesto_objetivo_id }`; el frontend navega al asistente de adecuación con esos parámetros de URL (la 020 ya lee `?colaborador=`; si no los lee, se añade lectura aditiva de URL en `EntradaAdecuacionPage`).

## R14. Frontend

- **Decisión**: `frontend/src/modules/desarrollo/pdi/` con las convenciones de `adecuacion/` (api.ts, hooks/, components/, pages/, permisos.ts, nav.tsx, text.ts, validation.ts, estilosEstado.ts). Rutas: `/pdi` (lista, Gantt y «sin plan» por pestañas, con permisos), `/pdi/nuevo` (asistente), `/pdi/:id` (detalle) y, para el colaborador, `/pdi` resuelve a «Mi plan de desarrollo» cuando solo tiene `ver_propio` (mismo patrón que `EntradaAdecuacionPage`).
- **Formularios**: React Hook Form + Zod; el asistente mantiene los tres pasos como estado del formulario con validación por paso; compromisos y acciones como `useFieldArray` anidado.
- **Estado en URL**: pestaña, filtros, página y paso del asistente (`?paso=`).
- **E2E**: un spec `pdi.spec.ts` con la herramienta ya usada (Playwright): crear plan → acuerdo del responsable → aceptación del colaborador → «Mi plan».

## R15. Seeders y datos de demostración

- `PdisSeeder` (cinco planes: tres tipos, tres estados, un Cerrado Brecha cerrada, un Cerrado Parcial, compromisos de las cuatro dimensiones, acciones de los siete tipos, una acción vinculada a la acción de `PlanFormacionSeeder`, un hito vencido) y `PermisosPdiSeeder` no es necesario: el catálogo vive en código (R2). Depende de `PersonalSeeder`, `PuestosSeeder`, `PlanFormacionSeeder`, `ActividadesSeeder`, `DnfSeeder` y `AdecuacionesSeeder`. Se registra en `DatabaseSeeder` después de ellos. Los datos usan nombres ficticios (sin datos personales reales).
- **Trampa conocida del entorno**: los fallos preexistentes de seeders de 017 y dompdf (memoria del proyecto) no se atribuyen a esta feature.

## R16. Diferencias entre los tableros y la spec

Se registran en `plan.md` («Diferencias entre tableros y spec») y se llevarán a `design/DESIGN.md` como primera tarea de implementación (Constitución III).
