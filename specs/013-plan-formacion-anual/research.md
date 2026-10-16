# Research: Plan de formación anual

Decisiones de la Fase 0. No quedan puntos «NEEDS CLARIFICATION».

## R-01 — Datos de otros módulos que aún no existen

- **Hallazgo**: Gestión de Personal no publica búsqueda de colaboradores con vínculo vigente (solo `getColaboradoresPorIds`, `getCuentaDeUsuario`…); `Shared` no lista administradores; Actividades Formativas (asistencia) no existe.
- **Decision**: Tres puertos propios en `Application/Ports`: `ColaboradoresVigentesInterface` (buscar y filtrar por ids los colaboradores con vínculo vigente), `AprobadoresInterface` (administradores del tenant y comprobación «es administrador activo») y `AsistenciaInterface` (colaboradores con asistencia Presente por acción). Los dos primeros tienen adaptador definitivo que llama a **métodos aditivos de solo lectura** en `PersonalFacade` y `SharedFacade`; `AsistenciaInterface` tiene el adaptador provisional `AsistenciaSinDatos`.
- **Rationale**: Constitución I (un módulo no accede a tablas de otro). Es el mismo patrón de 005 y 006.
- **Alternativas**: leer las tablas `colaboradores`/`users` directamente (viola la constitución); posponer el buscador (bloquea la historia 1).

## R-02 — Mockups frente a la spec

- **Decision**: manda la spec. Se omiten del mockup administrador: período inicio/fin, presupuesto, objetivo, precarga desde DNF y «participantes por departamento»; del colaborador: «Lugar o plataforma», avance por acción y los estados «Completada/Inscrito/Por confirmar». Se añaden: selección múltiple de competencias, curso del catálogo y horas. Todo queda registrado en `design/DESIGN.md` §9 (Constitución III).
- **Rationale**: la spec ya está clarificada y la constitución exige registrar la desviación antes de implementar.

## R-03 — Unicidad de nombre por año

- **Decision**: `nombre_normalizado = lower(trim(colapsar espacios))`, único con `(tenant_id, anio, nombre_normalizado)`; la comprobación previa en el caso de uso da el mensaje claro y el índice único cubre la carrera.
- **Rationale**: FR-003; SQLite no tiene colación insensible a acentos y la spec solo exige ignorar mayúsculas y espacios laterales.
- **Alternativas**: colación NOCASE (depende del motor); comparar en memoria (sin garantía ante concurrencia).

## R-04 — Código PLAN-NNNN secuencial

- **Decision**: contador por tenant calculado dentro de la transacción de creación como `max(numero)+1` sobre una columna entera `numero` con único `(tenant_id, numero)`; se reintenta una vez ante colisión. El `codigo` se deriva de `numero` con 4 dígitos.
- **Rationale**: mismo criterio que `DNF-NNNN`; evita tablas de secuencias.

## R-05 — Estado calculado de la acción

- **Decision**: no se guarda; `CalculadorDeEstadoDeAccion(accion, hoy)`: `cancelada` → Cancelada; sin fechas → Programada (acción incompleta de un Borrador); `hoy < inicio` → Programada; `inicio ≤ hoy ≤ fin` → En curso; `hoy > fin` → Finalizada. El filtro por estado de acción no existe en el listado (la spec filtra por estado del **plan**).
- **Rationale**: FR-013; evita jobs que "avancen" estados.

## R-06 — Calendario y paginación

- **Decision**: el calendario pide `GET /api/plan-acciones` con `fecha_desde`/`fecha_hasta` del mes visible (extendido a la semana completa de la rejilla) y `per_page=200` (máximo permitido). Si `total > 200` se muestra un aviso «Hay más acciones de las que se pueden mostrar; usa los filtros». La lista usa `per_page` de 10–100.
- **Rationale**: una acción se pinta en cada día que abarca, así que el cliente necesita todas las del rango; el volumen esperado por mes es de decenas.
- **Alternativas**: endpoint dedicado sin paginación (duplica filtros).

## R-07 — Cruce de fechas

- **Decision**: `DetectorDeCruces` (dominio puro) compara, por colaborador, los intervalos `[inicio, fin]` de las acciones no canceladas **del mismo plan y de otros planes del tenant** en cualquier estado del plan y devuelve avisos `{colaborador_id, accion_id, con_accion_id, con_plan_id}`. El detalle del plan y el `PUT` de participantes los devuelven; no bloquean (FR-014).
- **Rationale**: la spec dice «dos acciones», sin limitar al mismo plan; incluir otros planes evita doble reserva real. Se excluyen acciones canceladas porque ya no ocupan al colaborador.

## R-08 — Reglas de edición por estado

- **Decision**: tabla única en `ReglasDePlan`: Borrador edita todo; Revisión solo `reasignarAprobador`; Aprobado y Ejecutando solo `cambiarParticipantes` y `cancelarAccion`; Cerrado nada. Los casos de uso consultan la regla antes de operar; ningún controlador la duplica.
- **Rationale**: FR-017 y FR-016a en un solo lugar probado con Pest Unit.

## R-09 — Borrador desde la DNF

- **Decision**: `CrearBorradorDesdeDnf(tenantId, actorId, dnfId, brechas)` crea un plan Borrador del año actual, nombre `Plan DNF {codigo de la DNF}`, `origen_dnf_id = dnfId`, una acción por competencia con brecha (nombre = nombre de la competencia, competencia objetivo, resto nulo) y los colaboradores con brecha en esa competencia como participantes, todo en una transacción. El código de la DNF se obtiene por `DnfRepositoryInterface` (mismo módulo). Si la competencia ya no está activa se crea igualmente la referencia (la brecha ya fue calculada) y la acción queda incompleta; no se bloquea la creación.
- **Rationale**: FR-029 y la aclaración 4. La capacidad máxima es nula, así que no se valida el cupo hasta completarla.

## R-10 — Historial desde la aprobación

- **Decision**: el cambio de estado siempre se registra. Participantes y cancelaciones solo se registran cuando el plan está Aprobado o Ejecutando (en Borrador no hay historial de participantes). La reasignación de aprobador en Revisión se registra con tipo `Aprobador`.
- **Rationale**: FR-027, FR-016a.

## R-11 — Curso del catálogo como punto de partida

- **Decision**: `GET /api/planes/cursos` lista cursos `Disponible` del catálogo (mismo módulo, `CatalogoFormativoRepositoryInterface`) con `{id, nombre, tipo_sugerido, formador, horas, competencias_ids}`. Mapeo de tipo: `Presencial→Presencial`, `Online→E-learning`, `Mixto→(sin sugerencia)`; horas = `duracion_minutos / 60` con dos decimales. El administrador puede cambiar todo (FR-010). Se guarda `curso_id` solo como referencia (FK permitida: mismo módulo).
- **Rationale**: la tabla `cursos` tiene tipo `Online|Presencial|Mixto`, distinto de los seis tipos de acción.

## R-12 — Reasignación del aprobador

- **Decision**: `PATCH /api/planes/{id}/aprobador` permitido solo en Revisión (en Borrador el aprobador se cambia con `PUT`); cualquier Administrador; el nuevo aprobador debe ser Administrador activo del tenant. No altera el estado ni la fecha de aprobación.
- **Rationale**: FR-016a.

## R-13 — Rutas y contrato HTTP

- **Decision**: se conservan `/api/planes` y `/api/plan-acciones` del documento de partida (como `/api/dnf` en 006). Las rutas fijas (`mi-plan`, `opciones`, `colaboradores`, `cursos`) se declaran antes de cualquier `/{id}`. No existe `DELETE`.
