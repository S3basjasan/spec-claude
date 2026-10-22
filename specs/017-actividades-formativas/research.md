# Research: Actividades formativas

No quedaron `NEEDS CLARIFICATION` en el Technical Context. Esta investigación registra las decisiones técnicas que el insumo
`plan-actividades.md` dejaba abiertas o que el estado real del repositorio obliga a ajustar.

## R1. Cómo lee Actividades los datos de la acción del Plan

- **Decision**: puerto nuevo `AccionesDePlanInterface` (Application/Ports) con adaptador `AccionesDesdePlan` que usa
  `PlanRepositoryInterface` (se le añaden métodos de lectura). Devuelve, siempre del plan vigente: `accion_id`, `plan_id`,
  `plan_codigo`, `plan_nombre`, `plan_estado`, `nombre`, `tipo`, `fecha_inicio`, `fecha_fin`, `duracion_horas`, `formador`,
  `capacidad_maxima`, `cancelada`, `competencia_ids` y `participantes` (ids de colaborador).
- **Rationale**: aclaración 4 (datos heredados siempre del plan vigente) y Constitución I (no acceder a tablas ajenas). Plan y
  Actividades están en el mismo módulo, pero son funcionalidades distintas; el puerto evita acoplar el repositorio de Actividades al
  esquema del Plan. El estado de la actividad usa el `CalculadorDeEstadoDeAccion` ya existente.
- **Alternatives considered**: (a) `JOIN` directo a `plan_acciones` desde `EloquentActividadRepository` — acopla esquemas;
  (b) copiar nombre, fechas y formador a `actividades` — se desactualiza al editar el plan; (c) llamar a `DesarrolloFacade` desde el
  mismo módulo — un módulo no debe usar su propia Facade pública (reservada a consumidores externos).

## R2. Integración inversa con el Plan (progreso y bloqueo de baja)

- **Decision**: reemplazar el binding provisional `AsistenciaInterface → AsistenciaSinDatos` por `AsistenciaDesdeActividades`
  (`presentesPorAccion` devuelve las acciones cuya actividad está **Finalizada** y cuyo registro del colaborador es **Presente**).
  Añadir `AsistenciaRegistradaInterface::tieneAsistencia(int $accionId, int $colaboradorId): bool`;
  `CambiarParticipantes` lanza la excepción de dominio `ParticipanteConAsistencia` (HTTP 422) si entre los `quitados` hay alguno
  con asistencia registrada. `AsistenciaSinDatos` se elimina.
- **Rationale**: `ObtenerMiPlan` ya consume `AsistenciaInterface`; solo cambia el adaptador. La regla «Finalizada + Presente» pertenece a
  Actividades (su estado se calcula allí), así que el adaptador la aplica y el Plan no necesita conocerla. Para el bloqueo se añade un
  único `if` al caso de uso existente (cambio mínimo y con prueba).
- **Alternatives considered**: calcular el progreso en el Plan con el estado de la acción — duplicaría la regla; bloquear la baja con
  un evento — la validación debe ser síncrona y parte de la misma transacción.

## R3. Tenant y códigos

- **Decision**: `actividades` lleva `tenant_id` (el insumo lo omitía) para ser coherente con `planes`, `microcontenido_progresos` y
  el resto de `Desarrollo`. El código ACT-NNNN se genera con una columna `numero` (secuencial por tenant, única junto con `tenant_id`) y
  el objeto de valor `CodigoActividad`, igual que `CodigoPlan`.
- **Rationale**: todos los repositorios del módulo filtran por tenant; sin la columna las consultas de Facade para Reportes no podrían
  aislar datos. `numero` evita parsear el texto del código.
- **Alternatives considered**: guardar solo `codigo` string — obliga a parsear para el siguiente número; secuencia global — mezcla tenants.

## R4. Concurrencia al guardar asistencia

- **Decision**: `actividades.version` (entero, +1 en cada guardado de asistencia). `PUT /asistencia` recibe `version`; si no
  coincide responde **409** con el mensaje «Otro administrador modificó el registro; recarga para ver los cambios» y no guarda nada. La
  respuesta de éxito devuelve la lista actualizada y la nueva `version`.
- **Rationale**: spec, caso límite de dos administradores; control optimista barato, sin bloqueos, y el cliente ya conserva los
  valores (formularios conservan datos).
- **Alternatives considered**: último gana — pierde datos en silencio; bloqueo pesimista — innecesario por la baja concurrencia.

## R5. Semántica de guardado de asistencia

- **Decision**: el cuerpo trae `registros: [{colaborador_id, asistencia, calificacion?, observaciones?}]` con `asistencia ∈
  {Pendiente, Presente, Ausente, Justificado}`. En una sola transacción y **solo para los registros que difieren del estado actual**:
  - `Pendiente` borra la fila existente (físicamente: es la única eliminación permitida y la fila equivale a «sin registro»);
    elimina así calificación y observaciones (aclaración 1 y FR-016/FR-017).
  - `Ausente`/`Justificado` crean o actualizan la fila con `calificacion = null`; se admiten observaciones.
  - `Presente` admite calificación 1–5 y observaciones.
  - Cada cambio de `asistencia` o `calificacion` escribe una fila en `actividad_asistencia_historial` (también la eliminación
    automática de calificación). Un cambio solo de observaciones no genera historial (FR-033 lo limita a asistencia y calificación).
  - `Pendiente` con `calificacion` u `observaciones` informadas es 422 (aclaración 1); `calificacion` con asistencia distinta de
    Presente es 422.
- **Rationale**: respeta «solo se guarda registro cuando el administrador lo cambia» y deja el historial como la única memoria de lo
  borrado. «No existe DELETE sobre actividades» sigue cierto: se borra una fila de registro, no una actividad ni su historial.
- **Alternatives considered**: guardar `Pendiente` como valor de columna — contradice «solo se guarda registro cuando se cambia» y
  complica el conteo; soft delete — prohibido por el insumo.

## R6. Qué participantes se listan

- **Decision**: la lista sale siempre de `AccionesDePlanInterface` (inscritos actuales) unida a los registros existentes. No hay
  sincronización de filas (aclaración 2). Un registro cuyo colaborador ya no está inscrito no se lista ni cuenta como pendiente; el
  bloqueo de R2 hace este caso imposible por la UI, pero la API lo ignora sin error.
- **Rationale**: simplicidad y consistencia con las inscripciones.
- **Alternatives considered**: copiar inscritos al crear la actividad — exigiría sincronizar altas y bajas.

## R7. Listado, filtros y «Asistencia pendiente»

- **Decision**: `ListarActividades` obtiene las actividades del tenant (con departamento), consulta el puerto por lotes de
  `accion_id` y aplica en memoria los filtros que dependen de la acción (nombre, tipo, estado calculado, competencia, plan, rango de
  fechas) antes de paginar. «Asistencia pendiente» = estado Finalizada y `inscritos − registrados > 0`, con los conteos de
  registrados por lote. Sin parámetros de fecha, el backend aplica el año actual (superposición con las fechas de la acción). La interfaz siempre envía un rango explícito (por defecto el año actual); para ver otros años se cambian las fechas y «Limpiar filtros» restablece el año actual (FR-026).
- **Rationale**: nombre, tipo, fechas, estado y competencia son datos del Plan (R1) y el estado no se persiste; con cientos de
  actividades por año el filtrado en memoria cumple el objetivo de 2 s. Evita un `JOIN` entre esquemas.
- **Alternatives considered**: columnas desnormalizadas — se desactualizan; traducir el estado a condiciones SQL sobre
  `plan_acciones` — acopla esquemas y duplica la regla de estado.

## R8. Calificaciones agregadas

- **Decision**: la consulta agrega en SQL sobre `actividad_asistencias` (`AVG` y `COUNT` de `calificacion IS NOT NULL`) por
  actividad y por colaborador, y el caso de uso une los datos de la acción por el puerto y aplica los filtros de fecha, tipo,
  departamento y plan. `agrupar=actividad` devuelve todas las actividades del filtro (promedio `null` si no hay notas);
  `agrupar=participante` devuelve solo colaboradores con al menos una calificación (aclaración 5) y, con `colaborador_id`, el detalle por
  actividad. Promedios con un decimal (`round(x, 1)`), calculados en `CalculadorDePromedios` para tener una única regla.
- **Rationale**: las agregaciones simples se resuelven mejor en la base; el redondeo vive en dominio y se prueba.
- **Alternatives considered**: calcular todo en PHP — transfiere filas innecesarias.

## R9. Departamentos

- **Decision**: puerto `DepartamentosInterface` (`activos()`, `porIds($ids)`) con adaptador `DepartamentosDesdeEstructura` sobre
  `EstructuraFacade::getUnidadesActivas()` y `getUnidadesPorIds()`. Se valida que el `departamento_id` exista y esté activo al crear o
  cambiarlo; si la unidad se desactiva después, la actividad conserva la referencia y se muestra su nombre.
- **Rationale**: el «departamento» del documento fuente es una unidad organizacional de Estructura Empresarial; `NombresDeReferencia`
  ya resuelve nombres de unidades pero no lista las activas.
- **Alternatives considered**: tabla local de departamentos — duplica Estructura.

## R10. Estado del plan y solo lectura

- **Decision**: el estado del plan viene del puerto (`plan_estado`). Crear exige `Ejecutando`; editar y registrar exigen `!= Cerrado`;
  registrar asistencia exige además actividad En curso o Finalizada. Los errores son excepciones de dominio con códigos estables
  (`accion_no_elegible`, `plan_cerrado`, `asistencia_no_registrable`) mapeadas a 422 por el trait de respuestas del módulo.
- **Rationale**: las reglas son de dominio, no de controlador, y el frontend las usa para deshabilitar la vista con la explicación
  correcta (FR-021) a partir de `puede_editar` / `puede_registrar_asistencia` / `motivo_bloqueo` que devuelve el detalle.
- **Alternatives considered**: que el cliente deduzca los bloqueos — la autoridad es el backend.

## R11. URL válida por tipo

- **Decision**: `ReglasDeActividad::validarLugar(TipoAccion, string)` — `Virtual` y `ELearning` exigen URL absoluta `http`/`https`
  sin espacios (máx. 500 caracteres); los demás aceptan texto libre (máx. 500). Sin llamadas de red.
- **Rationale**: FR-004; regla pura probada con Pest sin framework.
- **Alternatives considered**: `filter_var` solo en el `FormRequest` — la regla pertenece al dominio y depende del tipo de la acción,
  que el request no conoce.

## R12. Frontend

- **Decision**: `RequireRole` propio de la funcionalidad (mismo patrón que micro-learning) para elegir la vista por rol en
  `/actividades`; pestañas con `Tabs` compartido y `?vista=calificaciones` en la URL; filtros, búsqueda y paginación en la URL;
  formulario en `Modal` con React Hook Form + Zod; guardado de asistencia con una mutación sin reintentos y `ConfirmDialog` cuando el
  borrador modifica registros previos; todo texto en `text.ts`. Badges de tipo reutilizan los colores de `estilosTipo.ts` de
  plan-formacion mediante una copia de tokens en `estilosEstado.ts` solo si la importación cruzaría módulos (la constitución prohíbe
  importar implementaciones internas de otra funcionalidad): se usa `shared/ui/Badge` con las variantes semánticas del diseño.
- **Rationale**: Constitución II y III.
- **Alternatives considered**: importar `ChipTipo` de plan-formacion — viola la regla de no importar internos de otra funcionalidad.

## R13. Datos de prueba (seeder y E2E)

- **Decision**: `ActividadesSeeder` (tras `PlanFormacionSeeder`) crea 6 actividades. El seeder actual del Plan deja los tipos
  Virtual, E-learning y la acción cancelada en un plan **Aprobado** (no elegible), por lo que `ActividadesSeeder` **añade** acciones
  propias (marcadas con prefijo `[Act]`) al plan Ejecutando existente para cubrir los seis tipos y los cuatro estados, y a ese plan o al
  Cerrado les crea la actividad: Presencial (plan Cerrado, Finalizada), Codesarrollo (En curso), Mentoring (Finalizada con
  asistencia pendiente), OJT (Programada), Virtual (En curso) y E-learning (cancelada tras crearla). Incluye registros Pendiente,
  Presente, Ausente y Justificado y calificaciones 1–5. No modifica `PlanFormacionSeeder` para no alterar los datos de 013.
- **Rationale**: aísla el riesgo de romper pruebas del Plan; los tests de Plan usan sus propios *factories*.
- **Alternatives considered**: editar `PlanFormacionSeeder` — cambia los conteos que verifican pruebas y E2E existentes.
- **Riesgo**: el E2E del Plan y el de micro-learning comparten la base `e2e.sqlite`; las tareas deben volver a ejecutar ambos.

## R14. Facade para Reportes

- **Decision**: `DesarrolloFacade` añade `getAsistenciaDeColaborador`, `tieneAsistenciaRegistrada`, `getActividades` y
  `getAsistencias` (solo lectura; sin observaciones). Detalle en `contracts/actividades-puertos-y-facades.md`.
- **Rationale**: el insumo pide las tres consultas; `getActividades` entrega las horas desde la acción para los indicadores.
