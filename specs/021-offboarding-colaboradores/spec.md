# Feature Specification: Offboarding de colaboradores

**Feature Branch**: `021-offboarding-colaboradores`

**Created**: 2026-10-26

**Status**: Draft

**Input**: User description: "Offboarding de colaboradores. El Administrador de Talento Humano formaliza la desvinculación de un colaborador con vínculo vigente (motivo administrativo, fecha efectiva y respaldos) según la política de offboarding vigente; el sistema incorpora la plantilla de tareas de esa política como checklist, aplica la salida en la fecha efectiva (finaliza el vínculo y libera la posición, una sola vez), permite confirmar el cierre administrativo cuando la salida es efectiva y las tareas requeridas están completas con evidencia, y ofrece al colaborador una entrevista de salida voluntaria con cuestionario predefinido (valoraciones 1 a 5, motivos declarados y preguntas abiertas) cuya información se conserva estructurada, sin sobrescribirse, para un análisis futuro con IA que no se implementa aquí. Solo dos roles: Administrador de Talento Humano y Colaborador."

## Clarifications

### Session 2026-10-26

- Q: ¿Qué permiso se necesita para consultar las respuestas originales de las entrevistas de salida? → A: Solo el Administrador de Talento Humano con un permiso específico «consultar entrevistas de salida», concedido por el componente de acceso; cada consulta queda registrada.
- Q: ¿Cómo se gestiona la entrevista de salida respecto del checklist? → A: Es una tarea obligatoria del checklist de todo expediente, gestionada por el Administrador de Talento Humano y presentada al Colaborador como una tarea a cumplir; el Colaborador sigue respondiendo el contenido, y sus preguntas siguen siendo opcionales.
- Q: ¿Qué debe cumplirse en la tarea «Entrevista de salida» para poder confirmar el cierre administrativo? → A: Que esté resuelta: entrevista completada, o registrada por el Administrador como «no realizada» con motivo (declinada, sin respuesta o sin acceso); ninguna respuesta es obligatoria y nada se da por supuesto.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Formalizar la desvinculación y planificar el cierre (Priority: P1)

El Administrador de Talento Humano elige a un colaborador con vínculo vigente, registra el motivo administrativo de salida, la fecha efectiva y los respaldos que correspondan, y confirma. El sistema comprueba la política de offboarding vigente, crea el expediente asociado al vínculo, conserva la versión de política utilizada e incorpora al expediente las tareas de la plantilla publicada.

**Why this priority**: Es el punto de entrada del proceso; sin expediente formalizado no existen la salida efectiva, el checklist ni la entrevista.

**Independent Test**: Con una política de Offboarding publicada con tareas, formalizar la salida de un colaborador con vínculo vigente y comprobar que existe un expediente con la versión de política y su checklist, sin cambiar el vínculo ni la posición; repetir sin política publicada, con datos inválidos y con un colaborador sin vínculo vigente y comprobar que no se crea nada.

**Acceptance Scenarios**:

1. **Given** un colaborador con vínculo vigente y una política de Offboarding publicada y vigente, **When** el Administrador formaliza la salida con motivo y fecha efectiva válidos, **Then** se crea un expediente asociado al vínculo que conserva la versión de política utilizada y no modifica el vínculo ni la posición. (CA-01)
2. **Given** el inicio formal del proceso, **When** se crea el expediente, **Then** se incorporan las tareas de la plantilla con su nombre, descripción, orden y condición de evidencia, y siguen así aunque la política se actualice después. (CA-02)
3. **Given** que no existe política de Offboarding publicada, que no puede determinarse de forma confiable, que falta información obligatoria o que el vínculo no admite la salida pedida, **When** se intenta formalizar, **Then** se rechaza sin modificar la situación laboral y se distingue la causa. (CA-03)
4. **Given** una fecha efectiva anterior al inicio del vínculo, o una salida ya formalizada para el mismo vínculo, **When** se intenta formalizar, **Then** se rechaza explicando el motivo.
5. **Given** una confirmación repetida (doble clic o reintento), **When** se procesa, **Then** no se duplica el expediente.
6. **Given** que el Administrador consulta la política antes de formalizar, **When** abre el formulario, **Then** ve las condiciones aplicables y la advertencia de que al formalizar se fija la versión de política vigente.

---

### User Story 2 - Seguir el checklist y cerrar administrativamente (Priority: P1)

El Administrador consulta las tareas pendientes y completadas del expediente, registra el cumplimiento de cada una con su evidencia cuando la tarea la exige, y confirma el cierre administrativo cuando la salida ya es efectiva y todas las tareas requeridas están completas con evidencia.

**Why this priority**: Es el seguimiento operativo que da valor al expediente y el cierre que conserva la evidencia del resultado.

**Independent Test**: Completar tareas con y sin evidencia exigida, comprobar que una sin respaldo válido no se completa, que el cierre se rechaza antes de la fecha efectiva o con tareas requeridas pendientes, y que se confirma cuando todo se cumple.

**Acceptance Scenarios**:

1. **Given** una tarea que exige evidencia, **When** se intenta completar sin un respaldo válido y accesible para el responsable, **Then** no se permite y se explica qué falta. (CA-04)
2. **Given** tareas completadas y pendientes, **When** el Administrador consulta el expediente, **Then** las distingue y conserva su información también después de la salida efectiva. (CA-05)
3. **Given** una salida aún no efectiva o con tareas requeridas pendientes o sin evidencia, **When** se intenta confirmar el cierre administrativo, **Then** se rechaza indicando qué falta. (CA-10)
4. **Given** una salida efectiva con todas las tareas requeridas completadas con evidencia, **When** el Administrador confirma el cierre, **Then** queda registrado quién y cuándo, y el expediente deja de admitir cambios en sus tareas.
5. **Given** que el avance del checklist cambia, **When** se consulta el vínculo, **Then** la vigencia de la relación laboral no se modifica por ello.
6. **Given** dos operaciones simultáneas sobre la misma tarea o el mismo cierre, **When** se procesan, **Then** solo una prospera y no se duplican hechos.

---

### User Story 3 - Aplicar la desvinculación efectiva (Priority: P1)

Llegada la fecha efectiva de una salida formalizada, el sistema finaliza el vínculo laboral y libera la posición desde esa fecha, una sola vez, conservando la ficha, los vínculos anteriores y la trayectoria. El Administrador puede supervisar el estado de la aplicación y reintentar una incidencia.

**Why this priority**: Es el efecto laboral real y el de mayor riesgo; debe ser coherente, idempotente y no depender del checklist ni de la entrevista.

**Independent Test**: Formalizar una salida con fecha efectiva futura y comprobar que el vínculo sigue vigente; avanzar el tiempo y comprobar que se cierra el vínculo y se libera la posición una sola vez, aun con tareas pendientes, repeticiones y ejecuciones simultáneas.

**Acceptance Scenarios**:

1. **Given** una salida formalizada con fecha efectiva futura, **When** se consulta antes de esa fecha, **Then** el vínculo y la posición siguen vigentes y la salida figura como programada. (CA-07)
2. **Given** llegada la fecha efectiva, **When** el sistema aplica la salida, **Then** el vínculo finaliza y la posición queda liberada desde esa fecha, y la ficha, los vínculos anteriores y la trayectoria se conservan. (CA-06)
3. **Given** tareas administrativas pendientes, **When** llega la fecha efectiva, **Then** la salida se aplica igualmente y las tareas siguen disponibles para seguimiento. (CA-08)
4. **Given** reintentos, fallos de procesamiento u operaciones concurrentes (incluida la aplicación automática frente a un reintento manual), **When** se procesan, **Then** la salida se ejecuta una sola vez y no se elimina la ficha. (CA-09)
5. **Given** una condición actual que impide aplicar (por ejemplo, un movimiento posterior cambió la asignación o el vínculo ya no es vigente), **When** el sistema intenta aplicar, **Then** registra una incidencia sin cambios parciales y el Administrador puede reintentar.
6. **Given** que la salida se aplicó, **When** se consulta la coordinación de la cuenta con Identidad y Acceso, **Then** se muestra como pendiente, coordinada o con incidencia, y nunca como «cuenta deshabilitada» sin verificación. (CA-11)
7. **Given** una entrevista sin realizar o un checklist abierto, **When** llega la fecha efectiva, **Then** la salida se aplica sin depender de ninguno.

---

### User Story 4 - Responder la entrevista de salida (Priority: P2)

La entrevista de salida es una tarea obligatoria del checklist de todo expediente, gestionada por el Administrador de Talento Humano; el Colaborador la ve como una tarea a cumplir y, mientras conserve acceso autorizado, la responde con un cuestionario predefinido: valoraciones de 1 a 5 por seis dimensiones, motivos declarados (categorías, una o varias) y seis preguntas abiertas. Todas las preguntas son opcionales; puede guardar su avance, confirmarla o declinar. Si no responde, el Administrador la registra como «no realizada» con motivo y la tarea queda resuelta.

**Why this priority**: Aporta la retroalimentación del colaborador y la base de información del análisis futuro; su resolución (no su contenido) condiciona el cierre administrativo, pero nunca la salida efectiva.

**Independent Test**: Iniciar la entrevista, guardar respuestas parciales (queda «en curso»), omitir preguntas y confirmar (queda «completada» con fecha); repetir decidiendo no participar y comprobar que la salida y el cierre no se afectan.

**Acceptance Scenarios**:

1. **Given** un expediente de offboarding y un Colaborador con acceso autorizado, **When** consulta su desvinculación, **Then** ve la entrevista de salida como una tarea a cumplir, con la indicación de que responder es voluntario. (CA-12)
2. **Given** la entrevista, **When** el Colaborador participa, omite preguntas o declina, **Then** la desvinculación efectiva no se ve impedida y el cierre solo exige que la tarea esté resuelta (completada, o «no realizada» registrada por el Administrador con motivo). (CA-13)
2b. **Given** una entrevista pendiente o en curso, **When** el Administrador intenta confirmar el cierre administrativo sin resolverla, **Then** se rechaza indicando que la tarea de entrevista sigue sin resolver; **When** la registra como «no realizada» con motivo (declinada, sin respuesta o sin acceso), **Then** el cierre puede confirmarse y no se genera ninguna respuesta ni valoración supuesta.
2c. **Given** el checklist del expediente, **When** el Colaborador consulta su desvinculación, **Then** ve la entrevista como una tarea a cumplir, mientras el Administrador la gestiona (estado, motivo si no se realizó) sin poder responder ni modificar respuestas en su nombre.
3. **Given** el cuestionario, **When** se muestra, **Then** distingue preguntas estructuradas de valoración (seis dimensiones, escala común de 1 a 5 con su significado) y preguntas abiertas. (CA-14)
4. **Given** una valoración, **When** se guarda, **Then** solo acepta 1 a 5 o ausencia de respuesta; cualquier otro valor se rechaza. (CA-15)
5. **Given** una pregunta sin responder, **When** se guarda o confirma, **Then** se conserva como información no proporcionada, nunca como cero ni valoración negativa. (CA-16)
6. **Given** el catálogo de categorías, **When** el Colaborador selecciona varias, **Then** se conservan como motivos declarados sin modificar el motivo administrativo de la desvinculación. (CA-17)
7. **Given** una respuesta abierta, **When** se guarda, **Then** queda vinculada a su pregunta con su contenido original. (CA-18)
8. **Given** respuestas guardadas sin confirmar, **When** se consulta la entrevista, **Then** figura «en curso», no «completada». (CA-19)
9. **Given** una entrevista confirmada, **When** se intenta modificar sus respuestas, **Then** no se sobrescriben: se conserva la presentación original con su fecha. (CA-20)
10. **Given** un Colaborador que ya no conserva acceso autorizado, **When** intenta responder, **Then** no es posible y la entrevista queda sin realizar; el sistema no crea mecanismos de acceso para personas desvinculadas.

---

### User Story 5 - Consultar y organizar la información de salida (Priority: P2)

El Administrador consulta los expedientes y las entrevistas de su organización: salidas efectivas y programadas, motivos administrativos, estado de participación y respuestas originales cuando está autorizado. El Colaborador consulta su propia desvinculación comunicada y sus propias respuestas. La información queda estructurada con la plantilla conceptual para un análisis posterior, sin ejecutar ninguna IA.

**Why this priority**: Cierra el ciclo de información y prepara futuras funcionalidades, pero depende de las historias anteriores.

**Independent Test**: Con expedientes y entrevistas en distintos estados, comprobar los listados y filtros, que cada Colaborador ve solo lo suyo, que otro tenant no obtiene nada y que la vista estructurada conserva campos sin respuesta y distingue declaraciones, valores y procedencia.

**Acceptance Scenarios**:

1. **Given** expedientes del tenant, **When** el Administrador abre el listado, **Then** distingue salidas efectivas de programadas, cierre administrativo pendiente de confirmado, y entrevistas completadas de no realizadas, con filtros por estado, motivo y período. (CA-34)
2. **Given** un Administrador con el permiso «consultar entrevistas de salida», **When** consulta una entrevista, **Then** ve las respuestas originales vinculadas a su pregunta, versión de cuestionario y estado de participación, y la consulta queda registrada; **Given** un Administrador sin ese permiso, **Then** ve el estado de participación pero no las respuestas. (CA-30)
3. **Given** un Colaborador, **When** consulta, **Then** solo ve su desvinculación comunicada y sus propias respuestas; lo ajeno responde igual que lo inexistente. (CA-31, CA-32)
4. **Given** una entrevista, **When** se obtiene su representación estructurada, **Then** separa contexto, valoraciones (con dimensión, pregunta, escala y valor), respuestas abiertas, categorías declaradas y completitud, conserva los campos sin respuesta como ausentes y marca la procedencia de cada dato, con una referencia técnica que no contiene datos identificativos directos. (CA-21 a CA-27)
5. **Given** que no hay integración de IA, **When** se usa la funcionalidad, **Then** no se envía información a ningún servicio externo y no se muestra ningún análisis, causa inferida, tendencia ni recomendación automática. (CA-28, CA-29, CA-35)
6. **Given** resultados agregados, **When** el grupo sea menor al mínimo configurado, **Then** no se muestran para evitar identificar a participantes.
7. **Given** hechos del offboarding (formalización, aplicación, cierre), **When** se consulta la trayectoria, **Then** la salida efectiva figura como hecho laboral con referencia al expediente y a la política utilizada. (CA-33)

---

### Edge Cases

- Una salida formalizada puede ser **cancelada** por el Administrador antes de aplicarse (con razón, sin borrarse); una vez aplicada no se revierte desde aquí: la corrección requiere una operación auditada definida aparte.
- Un movimiento (feature 020) o un ingreso que afecta al mismo vínculo: la salida verifica de nuevo vínculo y asignación vigente al aplicar y, si no puede, deja incidencia.
- Fecha efectiva igual a hoy: se aplica de inmediato en la siguiente ejecución del proceso o por acción manual, nunca antes de formalizar.
- La política de Offboarding cambia después de formalizar: el expediente conserva su versión y sus tareas.
- Un documento de respaldo deja de estar accesible tras marcar una tarea: se informa y no se presume cumplimiento.
- La entrevista se confirma después de la fecha efectiva: se admite mientras el Colaborador conserve acceso autorizado.
- Dos administradores actúan a la vez sobre el mismo expediente: una sola operación prospera y la otra recibe un conflicto con lo escrito recuperable.
- Falla la comunicación con Identidad y Acceso: queda incidencia visible; no se presenta como cuenta deshabilitada.
- Un colaborador con ficha pero sin cuenta: el expediente se gestiona igual; el Colaborador no puede responder en el sistema, así que el Administrador registra la entrevista como «no realizada» (sin acceso) para poder cerrar.
- Un listado vacío no se confunde con un error de carga.

## Requirements *(mandatory)*

### Functional Requirements

**Formalización y política**

- **FR-001**: El sistema MUST permitir al Administrador formalizar la desvinculación de un colaborador de su tenant con vínculo vigente, registrando motivo administrativo, fecha efectiva y respaldos referenciados.
- **FR-002**: El motivo administrativo MUST ser uno de un catálogo cerrado: renuncia voluntaria, terminación por decisión de la organización, finalización de contrato y jubilación; MUST distinguirse de los motivos declarados por el colaborador y MUST NOT modificarse desde la entrevista.
- **FR-003**: Al formalizar, el sistema MUST consultar la política de Offboarding vigente en el momento de la confirmación y fijar su versión; si no existe, no puede determinarse de forma confiable o falla la consulta, MUST NOT confirmar el inicio y MUST distinguir las causas.
- **FR-004**: La fecha efectiva MUST ser un día calendario de la zona horaria del tenant, no anterior al inicio del vínculo ni a hoy, y MUST NOT existir otra salida formalizada vigente para el mismo vínculo.
- **FR-005**: Formalizar MUST NOT modificar el vínculo, la asignación ni la posición; las confirmaciones repetidas MUST NOT duplicar el expediente.
- **FR-006**: El Administrador MUST poder cancelar una salida aún no aplicada indicando la razón; la cancelación se conserva y MUST NOT eliminarse el expediente.

**Checklist y cierre administrativo**

- **FR-007**: Al formalizar, el sistema MUST copiar al expediente las tareas de la plantilla publicada (nombre, descripción, orden, evidencia obligatoria) y MUST conservarlas aunque la política cambie. Además MUST agregar siempre la tarea obligatoria «Entrevista de salida», propia del sistema (no depende de la plantilla ni puede quitarla), con su propio estado y trazabilidad.
- **FR-008**: El Administrador MUST poder completar una tarea; una tarea con evidencia obligatoria MUST NOT completarse sin al menos un respaldo existente, disponible y accesible para quien la completa; completar MUST registrar quién y cuándo.
- **FR-009**: El sistema MUST distinguir tareas pendientes y completadas y conservarlas después de la salida efectiva.
- **FR-010**: El cierre administrativo MUST confirmarse solo cuando la salida es efectiva, todas las tareas requeridas están completadas con sus evidencias y la tarea «Entrevista de salida» está **resuelta** (entrevista completada, o «no realizada» registrada por el Administrador con motivo); MUST registrar quién y cuándo y MUST NOT modificar el vínculo ni la posición.
- **FR-011**: El avance del checklist MUST NOT modificar la vigencia del vínculo ni condicionar la salida efectiva.

**Desvinculación efectiva**

- **FR-012**: Llegada la fecha efectiva (zona horaria del tenant), el sistema MUST aplicar la salida automáticamente mediante una operación controlada con identidad de sistema: finalizar el vínculo y liberar la posición desde esa fecha, conservando la ficha, los vínculos anteriores y la trayectoria.
- **FR-013**: La aplicación MUST ser atómica e idempotente: una sola ejecución efectiva ante reintentos, fallos y concurrencia (incluida la aplicación automática frente a un reintento manual); un fallo MUST NOT dejar cambios parciales.
- **FR-014**: Al aplicar, el sistema MUST verificar de nuevo vínculo y asignación vigentes; si una condición actual lo impide MUST registrar una incidencia de aplicación sin cambios y permitir un reintento manual del Administrador con la misma verificación.
- **FR-015**: Mientras no llegue la fecha efectiva, la salida MUST figurar como programada y MUST NOT mostrarse como efectiva.
- **FR-016**: El sistema MUST registrar el estado de la coordinación del tratamiento de la cuenta con Identidad y Acceso (pendiente, coordinada, incidencia) y MUST NOT presentar la cuenta como deshabilitada mientras no se verifique; una falla de coordinación MUST registrarse como incidencia sin revertir la salida.
- **FR-017**: La salida efectiva MUST NOT depender de la entrevista ni del cierre del checklist.

**Entrevista de salida**

- **FR-018**: La entrevista de salida MUST gestionarse como un proceso del expediente a cargo del Administrador de Talento Humano, con estados pendiente, en curso, completada y no realizada, y MUST presentarse al Colaborador, mientras conserve acceso autorizado, como una tarea a cumplir en su vista de desvinculación; el Colaborador responde el contenido, el Administrador no puede responder ni modificar las respuestas en su nombre.
- **FR-018b**: El Administrador MUST poder registrar la entrevista como «no realizada» indicando el motivo (declinada por el colaborador, sin respuesta o sin acceso), con constancia de quién y cuándo; una entrevista «no realizada» con motivo cuenta como tarea resuelta para el cierre. Una entrevista «no realizada» MUST NOT convertirse en respuestas ni en valoraciones supuestas.
- **FR-019**: El cuestionario MUST ser predefinido y versionado (sin diseñador por tenant): seis valoraciones (desarrollo profesional, supervisión, carga laboral, reconocimiento, comunicación interna, experiencia general) en escala 1 a 5 con su significado, un catálogo de ocho categorías de motivos declarados (selección múltiple) y seis preguntas abiertas.
- **FR-020**: Todas las preguntas MUST ser opcionales; una pregunta sin responder MUST conservarse como no proporcionada y MUST NOT tratarse como cero ni como respuesta negativa; una valoración fuera de 1 a 5 MUST rechazarse.
- **FR-021**: Cada respuesta MUST conservar el identificador de su pregunta, su dimensión o categoría, su valor o texto original y la versión del cuestionario; el texto MUST conservarse sin modificarse ni reinterpretarse.
- **FR-022**: Guardar avance MUST dejar la entrevista «en curso»; confirmar MUST dejarla «completada» con fecha y momento de presentación; una entrevista completada MUST NOT sobrescribirse silenciosamente y su contenido original MUST conservarse.
- **FR-023**: Participar, omitir preguntas o declinar la entrevista MUST NOT condicionar la salida efectiva ni los derechos laborales; el contenido sigue siendo voluntario. La tarea de entrevista es obligatoria solo en cuanto a que debe resolverse (FR-010), nunca en cuanto a responder.
- **FR-024**: El sistema MUST NOT crear mecanismos de autenticación ni de acceso para personas desvinculadas.

**Estructura para análisis futuro**

- **FR-025**: El sistema MUST poder producir, para cada entrevista, una representación estructurada con grupos de contexto (referencia técnica sin datos identificativos directos, versión de cuestionario, tipo de desvinculación), cuantitativo (dimensión, valoración o ausencia, escala), cualitativo (pregunta, respuesta, categorías declaradas) y control (completitud, versión de estructura), con la procedencia de cada dato.
- **FR-026**: La representación MUST conservar los campos sin respuesta como ausentes sin valores supuestos, MUST distinguir lo declarado de cualquier interpretación futura y de los hechos laborales registrados, y MUST NOT ejecutar modelos de IA, enviar datos a terceros ni generar resúmenes, clasificaciones, causas ni recomendaciones.
- **FR-027**: El contrato de resultados esperados del análisis futuro (resumen, factores con evidencia, cuantitativos calculados de forma determinista, relación entre respuestas, oportunidades diferenciadas de decisiones, limitaciones) MUST documentarse como referencia, sin implementarse.

**Consulta, permisos y privacidad**

- **FR-028**: El Administrador MUST poder listar y consultar expedientes y entrevistas de su tenant, con filtros por estado de salida (programada, efectiva, cancelada), cierre administrativo, motivo administrativo, estado de la entrevista y período; «sin registros» MUST NOT confundirse con un error de carga.
- **FR-029**: El Colaborador MUST consultar únicamente su desvinculación formalmente comunicada, las tareas de entrega que le correspondan y sus propias respuestas; los datos de otro colaborador o de otro tenant MUST responder igual que un registro inexistente.
- **FR-030**: Las respuestas originales de las entrevistas MUST ser visibles solo para el Administrador de Talento Humano que tenga el permiso específico «consultar entrevistas de salida» (concedido por el componente de acceso, no por esta funcionalidad); cada consulta MUST registrarse con quién, cuándo y qué entrevista; el permiso MUST verificarse en el servidor, MUST NOT concederse a partir de las respuestas, y el acceso a la ficha, al expediente o a la lista de entrevistas (estado de participación) MUST NOT conceder acceso a las respuestas ni a los respaldos documentales. Un Administrador sin ese permiso ve el estado de participación pero no el contenido.
- **FR-031**: Los resultados agregados MUST ocultarse cuando el grupo no alcance un mínimo para evitar identificar participantes, y MUST NOT incluir índices predictivos, relaciones causales ni valoraciones de desempeño.
- **FR-032**: Solo existen los roles Administrador de Talento Humano y Colaborador; MUST NOT crearse roles nuevos de negocio.
- **FR-033**: Toda autorización MUST verificarse en el servidor y circunscribirse al tenant autenticado.

**Trazabilidad e historial**

- **FR-034**: Cada formalización, cancelación, tarea completada, aplicación, incidencia, cierre y presentación de entrevista MUST registrar actor real (o sistema), momento y resultado en un registro que no se edita ni elimina.
- **FR-035**: La salida efectiva MUST alimentar el Historial Laboral con referencia al expediente y a la versión de política utilizada; el sistema MUST NOT eliminar hechos laborales ni evidencia histórica.

**Límites**

- **FR-036**: La funcionalidad MUST NOT calcular nómina, indemnizaciones o liquidaciones, MUST NOT modificar la configuración de políticas, el historial laboral, la gestión documental ni el control de acceso, y MUST NOT administrar accesos posteriores a la desvinculación.

### Key Entities *(include if feature involves data)*

- **Expediente de offboarding**: proceso de salida de un colaborador asociado a un vínculo; lleva motivo administrativo, fecha efectiva, estado de la salida (programada, efectiva, cancelada) y del cierre administrativo, respaldos referenciados, versión de política y estado de la coordinación de cuenta.
- **Tarea del expediente**: copia de una tarea de la plantilla con nombre, descripción, orden, evidencia obligatoria, estado, quién y cuándo la completó y sus respaldos.
- **Aplicación de la salida / incidencia**: efecto controlado en la fecha efectiva, con identidad de sistema o del Administrador, o el impedimento actual que lo detuvo.
- **Cuestionario de salida (versión)**: definición predefinida de dimensiones, escala, categorías y preguntas abiertas, identificada por versión.
- **Entrevista de salida**: tarea obligatoria del checklist, gestionada por el Administrador y presentada al Colaborador como tarea; contenido voluntario, con estado, fechas, motivo si no se realizó y versión de cuestionario.
- **Respuesta**: valoración (dimensión, valor 1 a 5 o ausente), motivo declarado (categoría) o respuesta abierta (pregunta y texto original).
- **Representación estructurada**: vista de una entrevista con contexto, cuantitativo, cualitativo, control y procedencia, destinada a un análisis posterior.
- **Registro de acontecimientos**: actuaciones del proceso con actor, momento y resultado.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un Administrador formaliza una salida y obtiene su checklist en menos de 3 minutos, viendo antes las condiciones de la política.
- **SC-002**: El 100 % de los expedientes conserva la versión de política y las tareas con las que se formalizó, tras cualquier número de publicaciones posteriores.
- **SC-003**: Ninguna salida se formaliza cuando la política no existe, no pudo consultarse o los datos son inválidos, y en cada caso el mensaje distingue la causa.
- **SC-004**: En el 100 % de las salidas efectivas el vínculo finaliza y la posición queda liberada desde la fecha efectiva, una sola vez, incluso con reintentos y operaciones simultáneas, y la ficha y la trayectoria se conservan.
- **SC-005**: Ninguna aplicación fallida deja un vínculo o una posición en estado parcial ni se comunica como efectiva.
- **SC-006**: Ningún cierre administrativo se confirma con la salida pendiente o con tareas requeridas sin completar o sin evidencia.
- **SC-007**: Ninguna entrevista omitida, parcial, declinada o no realizada impide la salida efectiva; el cierre se confirma solo con la tarea de entrevista resuelta (completada o «no realizada» con motivo), y ninguna pregunta sin responder se registra como cero.
- **SC-008**: El 100 % de las respuestas conserva su pregunta, dimensión o categoría, valor o texto original y versión de cuestionario, y ninguna entrevista confirmada se sobrescribe.
- **SC-009**: Ningún Colaborador ni usuario de otro tenant puede ver expedientes o respuestas ajenos, y la respuesta ante un registro ajeno es indistinguible de la de uno inexistente.
- **SC-010**: Una persona de Talento Humano identifica en menos de 1 minuto cuántas salidas están programadas, efectivas o con incidencia y cuántas entrevistas están completadas o sin realizar.
- **SC-011**: Ningún dato sale a servicios externos y ninguna pantalla presenta análisis, causas inferidas ni recomendaciones automáticas.

## Assumptions

- La política de Offboarding (feature 016) ya existe y es la única fuente de la plantilla de tareas y de su versión; esta funcionalidad solo la consulta por su contrato público, con la misma coordinación de publicación que las novedades y movimientos.
- La liberación de la posición se produce al cerrar la asignación y el vínculo en la fecha efectiva (intervalos de inicio incluido y fin excluido, como en GP-02); no se crea ningún mecanismo nuevo de ocupación.
- La coordinación con Identidad y Acceso se limita a registrar y comunicar la solicitud de tratamiento de la cuenta mediante un contrato interno; mientras no exista ese contrato, el estado queda «pendiente» y nunca «coordinada». El control de acceso a la entrevista lo decide ese componente.
- El Colaborador solo puede responder mientras su cuenta siga habilitada; el sistema no habilita accesos adicionales.
- Mínimo para mostrar resultados agregados: 5 participantes por grupo (valor por defecto; ajustable por decisión posterior).
- El permiso «consultar entrevistas de salida» lo define y concede el componente de acceso; mientras ese contrato no exista, ninguna cuenta lo posee y las respuestas no se muestran a nadie salvo a su autor, sin que esta funcionalidad lo conceda por su cuenta.
- La cancelación de una salida solo es posible antes de aplicarse; la corrección de una salida ya efectiva requiere una operación auditada que se define aparte.
- El cuestionario inicial es la versión 1, predefinida por el sistema; los cambios futuros crearán nuevas versiones sin alterar las respuestas ya registradas.
- Zona horaria y fechas efectivas siguen las reglas de los movimientos (feature 020).
- Los respaldos documentales los conserva Gestión Documental con permisos propios; aquí solo se referencian.

## Pantallas *(si la feature tiene UI)*

### Listado de offboarding
- **Propósito**: seguimiento de salidas y entrevistas de la organización.
- **Datos que muestra**: colaborador, motivo administrativo, fecha efectiva, estado de la salida (programada, efectiva, cancelada, con incidencia), cierre administrativo, estado de la entrevista.
- **Acciones disponibles**: filtrar, abrir el expediente, iniciar una salida (Administrador).
- **Estados**: vacío, cargando, error con Reintentar, sin permisos.
- **Mockup**: patrón Listado de design/DESIGN.md con filtros en la URL.

### Formalizar desvinculación
- **Propósito**: registrar motivo, fecha efectiva y respaldos con las condiciones de la política a la vista.
- **Datos que muestra**: colaborador y vínculo, catálogo de motivos, condiciones aplicables y plantilla de tareas.
- **Acciones**: formalizar con confirmación (Administrador).
- **Estados**: política sin configurar, falla al consultarla, validación, conflicto, éxito.
- **Mockup**: patrón Formulario con el componente de condiciones aplicables.

### Expediente de offboarding
- **Propósito**: seguir checklist, aplicación de la salida y cierre.
- **Datos que muestra**: datos de la salida, tareas con evidencia, estado de aplicación e incidencia, estado de la cuenta, cierre, historial.
- **Acciones**: completar tarea, cancelar salida, reintentar aplicación, confirmar cierre, consultar entrevista (Administrador).
- **Estados**: pendiente, programada, efectiva, con incidencia, cerrada, conflicto de concurrencia.
- **Mockup**: patrón Detalle con diálogo de confirmación.

### Mi desvinculación y entrevista de salida (Colaborador)
- **Propósito**: conocer la salida comunicada y cumplir la tarea «Entrevista de salida» (contenido voluntario).
- **Datos que muestra**: fecha efectiva, actividades de entrega, cuestionario por grupos con escala y categorías.
- **Acciones**: guardar avance, confirmar o declinar (Colaborador); registrar «no realizada» con motivo (Administrador).
- **Estados**: pendiente, en curso, completada, no realizada, sin acceso.
- **Mockup**: patrón Formulario por secciones con guardado parcial.

### Respuestas de entrevistas (Administrador)
- **Propósito**: consultar respuestas originales y la representación estructurada.
- **Datos que muestra**: valoraciones, categorías declaradas, respuestas abiertas, completitud y procedencia.
- **Acciones**: consultar (Administrador autorizado).
- **Estados**: sin respuestas, grupo pequeño oculto, sin permisos.
- **Mockup**: patrón Detalle sin acciones de edición.
