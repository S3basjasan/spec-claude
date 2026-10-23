# Feature Specification: Plan de desarrollo individual (PDI) y compromisos de mejora

**Feature Branch**: `021-plan-desarrollo-individual`

**Created**: 2026-10-22

**Status**: Draft

**Input**: User description: "Plan de desarrollo individual (PDI) y compromisos de mejora (documento fuente `specify-pdi.md`, copiado en esta carpeta). Registra, por colaborador, lo que acuerdan el administrador (Gestor de Talento) y la persona para cerrar brechas o avanzar hacia un puesto: compromisos de mejora, acciones, hitos de seguimiento y resultado al cierre. Es el puente entre el diagnóstico (DNF y adecuación a puesto futuro) y la ejecución (Plan de Formación y Actividades Formativas). Hay tres tipos de plan: Mejora, Carrera y Talento. El administrador crea el plan, lo acuerda, da seguimiento y lo cierra; el colaborador consulta su plan, lo acepta o lo devuelve con un comentario y ve qué sigue. No administra el Plan de Formación ni las actividades (solo las vincula) ni ejecuta ascensos o cambios de puesto."

En este documento, **elemento** significa una competencia o un conocimiento.

## Clarifications

### Session 2026-10-22

- Q: ¿Desde cuándo ve el colaborador su plan en Borrador y puede aceptarlo o devolverlo? → A: Solo después de que el responsable confirmó el acuerdo; antes no aparece en «Mi plan de desarrollo».
- Q: ¿Cómo se cambia el responsable de un plan ya Acordado? → A: El administrador con permiso de gestión puede cambiarlo sin devolver el plan a Borrador; los acuerdos se conservan y el cambio queda en el historial.
- Q: ¿Qué pasa con el estado de las acciones vinculadas al cerrar el plan? → A: Se guarda el estado final de cada una al cerrar; después ya no se deriva del Plan de Formación y el plan Cerrado no cambia.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Crear un plan en Borrador con compromisos, acciones e hitos (Priority: P1)

El administrador abre el asistente de creación. En el paso 1 elige colaborador y tipo de plan (Mejora, Carrera o Talento) y, en Carrera y Talento, el puesto objetivo. El sistema ofrece precargar los compromisos desde la última DNF del colaborador (tipo Mejora) o desde la última adecuación (tipos Carrera y Talento); el administrador puede quitar los precargados o agregar otros. En el paso 2 edita la tabla de compromisos y, dentro de cada uno, sus acciones. En el paso 3 define el responsable (por defecto, quien crea el plan), el periodo y el calendario de hitos, y guarda: el plan queda en Borrador con código PDI-0001, PDI-0002…

**Why this priority**: Es el núcleo de la feature; sin planes guardados no hay acuerdo, seguimiento, cierre ni vista del colaborador.

**Independent Test**: Con un colaborador con una DNF que registra una brecha de competencia X (nivel actual C, a alcanzar B), crear un plan de Mejora precargado, agregar una acción de tipo Curso al compromiso, un hito, fijar periodo y guardar. Verificar código PDI-0001, estado Borrador, nivel actual C y a alcanzar B precargados, responsable igual a quien lo creó y registro de quién lo creó y cuándo.

**Acceptance Scenarios**:

1. **Given** un colaborador y el tipo Mejora, **When** el administrador acepta la precarga, **Then** se generan compromisos de dimensión Competencia o Conocimiento desde las brechas activas de su última DNF, con elemento, nivel actual y nivel a alcanzar precargados, y la referencia de origen queda guardada.
2. **Given** el tipo Carrera o Talento, **When** el administrador no ha elegido puesto objetivo, **Then** no puede avanzar del paso 1; el puesto objetivo es obligatorio en esos dos tipos y no se pide en Mejora.
3. **Given** el tipo Carrera o Talento con puesto objetivo, **When** acepta la precarga, **Then** los compromisos se generan desde las brechas de la última adecuación de ese colaborador a ese puesto, con su origen guardado; si no existe adecuación o DNF, el sistema lo informa y permite crear el plan sin precarga.
4. **Given** un compromiso de dimensión Competencia o Conocimiento, **When** el administrador lo completa, **Then** el elemento es obligatorio; en Experiencia y Meta no hay elemento y la descripción es obligatoria.
5. **Given** un compromiso con periodo estimado, **When** las fechas quedan fuera del periodo del plan o la fecha de fin es anterior a la de inicio, **Then** el sistema lo impide y lo señala.
6. **Given** un colaborador que ya tiene un plan no Cerrado del mismo tipo, **When** el administrador intenta crear otro, **Then** el sistema lo impide e indica cuál es el plan existente; un plan Cerrado no impide crear uno nuevo.
7. **Given** el paso 3, **When** el administrador guarda, **Then** el plan queda en Borrador con código secuencial único no editable, el usuario y la fecha de creación registrados, y puede guardarse aunque aún falten compromisos o acciones (esos requisitos se exigen para acordar, no para guardar).
8. **Given** un plan en Borrador, **When** el administrador lo edita (compromisos, acciones, hitos, responsable o periodo), **Then** el cambio se guarda y cualquier acuerdo o aceptación previos se borran.
9. **Given** un plan ya creado, **When** se abre en edición, **Then** el tipo y el código no son modificables.

---

### User Story 2 - Acordar el plan: confirmación del responsable y aceptación del colaborador (Priority: P1)

Un plan en Borrador completo se acuerda en dos confirmaciones, en este orden: primero el responsable confirma el acuerdo (con lo cual el plan se hace visible para el colaborador) y luego el colaborador acepta su plan o lo devuelve con un comentario. Cuando existen ambas, el plan pasa a Acordado automáticamente. Si el colaborador lo devuelve, el plan sigue en Borrador, se borran los acuerdos y se guarda su comentario para que el administrador lo atienda.

**Why this priority**: Sin acuerdo bilateral el plan no es un compromiso; es la regla que habilita el seguimiento y el cierre.

**Independent Test**: Con un plan en Borrador con un compromiso y una acción, confirmar el acuerdo como responsable (el plan sigue en Borrador), aceptar como colaborador (el plan pasa a Acordado) y verificar las fechas y el usuario del acuerdo en el resumen. Repetir devolviendo el plan con comentario y verificar que el plan sigue en Borrador, sin acuerdos y con el comentario guardado.

**Acceptance Scenarios**:

1. **Given** un plan en Borrador sin compromisos, o con un compromiso sin acciones, o sin responsable o sin periodo, **When** el administrador intenta confirmar el acuerdo, **Then** el sistema lo impide y lista lo que falta.
2. **Given** un plan Borrador completo, **When** el administrador confirma el acuerdo, **Then** se registra la fecha y el usuario del acuerdo, el plan sigue en Borrador y desde ese momento el colaborador lo ve en «Mi plan de desarrollo» para aceptarlo o devolverlo.
3. **Given** el acuerdo del responsable ya dado, **When** el colaborador acepta su plan, **Then** se registra la fecha de aceptación y el plan pasa a Acordado de forma automática.
4. **Given** un plan en Borrador, **When** el colaborador lo devuelve con un comentario, **Then** el plan sigue en Borrador, se borran los acuerdos y la aceptación, y se guarda el comentario, que el administrador ve en el detalle; el comentario es obligatorio.
5. **Given** un plan en Borrador con el acuerdo del responsable dado, **When** el administrador lo edita, **Then** el acuerdo se borra, el plan deja de ser visible para el colaborador y debe confirmarse de nuevo.
6. **Given** un plan Acordado, **When** el administrador lo devuelve a Borrador indicando el motivo, **Then** el plan pasa a Borrador, se borran los acuerdos, y el motivo queda en el historial; sin motivo el sistema no lo permite.
7. **Given** un colaborador, **When** intenta aceptar o devolver un plan que no es suyo, que no está en Borrador o cuyo acuerdo del responsable aún no existe, **Then** el sistema lo impide.

---

### User Story 3 - Dar seguimiento a un plan Acordado (Priority: P1)

Con el plan Acordado, el administrador registra avances: cambia el estado de las acciones no vinculadas (Pendiente, En curso, Cumplida, Cancelada) y marca los hitos como Realizados con sus observaciones. La estructura del plan (compromisos, acciones, fechas) no se edita. El avance del plan se calcula como el porcentaje de acciones Cumplidas sobre las acciones no Canceladas, y los hitos Pendientes con fecha pasada se marcan como vencidos.

**Why this priority**: Es donde el plan produce valor sostenido y sin él el cierre carece de evidencia.

**Independent Test**: Con un plan Acordado con 4 acciones (una Cancelada, dos Cumplidas, una Pendiente) y un hito Pendiente con fecha anterior a hoy, verificar avance 67 % (2 de 3), hito vencido y que no se pueden editar fechas ni agregar compromisos.

**Acceptance Scenarios**:

1. **Given** un plan Acordado, **When** el administrador registra el avance de una acción no vinculada, **Then** el nuevo estado queda guardado y el historial registra usuario, fecha, valor anterior y valor nuevo.
2. **Given** un plan Acordado, **When** el administrador marca un hito como Realizado con observaciones, **Then** queda guardado y registrado en el historial.
3. **Given** un plan Acordado, **When** el administrador intenta agregar, quitar o cambiar compromisos, acciones o fechas, **Then** no existe esa opción y, si se intenta por otro medio, el sistema lo rechaza.
4. **Given** acciones Cumplidas y no Canceladas, **When** se muestra el avance, **Then** es Cumplidas ÷ no Canceladas en porcentaje; si todas están Canceladas o no hay acciones, el avance se muestra como no calculable y no como 0 %.
5. **Given** un hito Pendiente con fecha anterior a hoy, **When** se muestra la lista o el detalle, **Then** el hito aparece marcado como vencido y la fila del plan en la lista muestra el indicador de hitos vencidos.

---

### User Story 4 - Vincular acciones al Plan de Formación (Priority: P2)

En las acciones de tipo Curso, Mentoring y Codesarrollo, el administrador pulsa «Vincular a Plan de Formación», y un selector le muestra las acciones disponibles: solo de planes en estado Aprobado o Ejecutando en las que el colaborador ya sea participante. Al vincular, el estado de la acción pasa a derivarse de la acción formativa y deja de editarse a mano: Cumplida si la acción formativa está Finalizada con asistencia del colaborador, Cancelada si está Cancelada, y en otro caso Pendiente o En curso según las fechas. El administrador puede desvincular.

**Why this priority**: Conecta el plan con la ejecución real y evita capturar dos veces el cumplimiento, pero el plan funciona sin vínculos.

**Independent Test**: Con una acción formativa Finalizada con asistencia del colaborador, vincularla a una acción de tipo Curso y verificar que el estado pasa a Cumplida, es de solo lectura y aparece en el historial; desvincular y verificar que el estado vuelve a registrarse a mano.

**Acceptance Scenarios**:

1. **Given** una acción de tipo Autodesarrollo, Coaching, Nueva responsabilidad u Otra, **When** se edita, **Then** no ofrece el botón de vincular.
2. **Given** el selector de vinculación, **When** se abre, **Then** solo lista acciones formativas de planes Aprobado o Ejecutando donde el colaborador del PDI es participante.
3. **Given** una acción vinculada cuya acción formativa se Finaliza con asistencia del colaborador, **When** se consulta el plan, **Then** el estado es Cumplida sin intervención manual.
4. **Given** una acción vinculada cuya acción formativa se Cancela, **When** se consulta el plan, **Then** el estado es Cancelada.
5. **Given** una acción vinculada que no está Finalizada ni Cancelada, **When** se consulta, **Then** el estado es En curso si la fecha de hoy está dentro de sus fechas y Pendiente en otro caso.
6. **Given** una acción vinculada, **When** el administrador intenta cambiar su estado a mano, **Then** el sistema lo impide; solo puede desvincularla.
7. **Given** una vinculación o desvinculación, **When** se realiza, **Then** queda en el historial del plan con usuario, fecha y valores anterior y nuevo, y el Plan de Formación no se modifica.
8. **Given** una acción formativa vinculada que deja de estar disponible (por ejemplo, el colaborador deja de ser participante), **When** se consulta el plan, **Then** la acción conserva su referencia y se avisa que el vínculo requiere revisión.

---

### User Story 5 - Cerrar un plan con su resultado (Priority: P2)

Cuando todas las acciones están Cumplidas o Canceladas, el administrador cierra el plan. Un modal pide el resultado de cada compromiso (Cerrada, Parcial o No cerrada) y una conclusión opcional. El resultado del plan se calcula: Brecha cerrada si todos los compromisos están Cerrada, No cerrada si ninguno lo está y Parcial en otro caso. Un plan Cerrado queda en solo lectura. Al cerrar un plan de tipo Carrera o Talento, la pantalla ofrece generar una nueva adecuación para medir el resultado.

**Why this priority**: Cierra el ciclo y alimenta indicadores de cierre de brechas, pero requiere planes con seguimiento.

**Independent Test**: Con un plan Acordado con dos compromisos y todas sus acciones Cumplidas o Canceladas, cerrarlo marcando un compromiso Cerrada y otro Parcial y verificar resultado Parcial; repetir con ambos Cerrada (Brecha cerrada) y con ambos No cerrada (No cerrada). Verificar que el plan queda en solo lectura.

**Acceptance Scenarios**:

1. **Given** un plan Acordado con alguna acción Pendiente o En curso, **When** el administrador intenta cerrarlo, **Then** el sistema lo impide e indica cuántas acciones faltan por cumplirse o cancelarse.
2. **Given** el modal de cierre, **When** algún compromiso no tiene resultado, **Then** no se puede confirmar.
3. **Given** los resultados de todos los compromisos, **When** se confirma el cierre, **Then** el plan pasa a Cerrado, el resultado del plan se calcula con la regla de la descripción, se guarda la conclusión si existe y el resultado de cada compromiso queda registrado.
4. **Given** un plan Cerrado con acciones vinculadas, **When** la acción formativa de origen cambia después del cierre, **Then** el estado de la acción en el plan permanece como se guardó al cerrar.
5. **Given** un plan Cerrado, **When** se consulta, **Then** todas las pestañas están en solo lectura, no hay acciones de editar, avanzar, devolver ni eliminar, y no puede eliminarse por ningún medio.
6. **Given** un plan Cerrado de tipo Carrera o Talento, **When** se muestra el resultado del cierre, **Then** la pantalla ofrece «Generar nueva adecuación» que lleva al asistente de adecuación con el colaborador y el puesto objetivo preseleccionados; en el tipo Mejora no se ofrece.
7. **Given** un plan Cerrado, **When** el administrador crea un nuevo plan del mismo tipo para el mismo colaborador, **Then** se permite.

---

### User Story 6 - Consultar «Mi plan de desarrollo» como colaborador (Priority: P1)

El colaborador abre «Mi plan de desarrollo» y ve tarjetas con cada uno de sus planes (tipo, estado, avance y periodo). Al abrir una, ve sus compromisos con sus acciones e hitos. Si el plan está en Borrador, dispone de «Aceptar plan» y «Devolver con comentario». Una sección «Qué sigue» lista las próximas acciones y los próximos hitos con fecha, y las acciones vinculadas muestran el enlace a su actividad formativa. Si no tiene planes, ve un estado vacío.

**Why this priority**: Es la mitad de la doble confirmación y el acceso de la persona a lo que se acordó.

**Independent Test**: Con un colaborador con un plan Borrador y otro Acordado, verificar las tarjetas, que solo el Borrador ofrece aceptar o devolver, que «Qué sigue» ordena por fecha, que una acción vinculada muestra el enlace y que no puede ver el plan de otra persona.

**Acceptance Scenarios**:

1. **Given** un colaborador con planes, **When** abre «Mi plan de desarrollo», **Then** ve únicamente sus propios planes que ya tienen el acuerdo del responsable (Borrador con acuerdo, Acordado o Cerrado), con tipo, estado, avance y periodo; un Borrador sin acuerdo del responsable no aparece.
2. **Given** un plan en Borrador propio con el acuerdo del responsable, **When** lo abre, **Then** ve «Aceptar plan» y «Devolver con comentario», y este último exige el comentario.
3. **Given** un plan Acordado o Cerrado propio, **When** lo abre, **Then** no ve acciones de aceptar ni devolver y el contenido es de solo lectura.
4. **Given** acciones e hitos próximos, **When** se muestra «Qué sigue», **Then** aparecen ordenados por fecha ascendente solo los pendientes de planes Acordados; los hitos vencidos se destacan y las acciones vinculadas muestran el enlace a su actividad formativa.
5. **Given** un colaborador sin planes, **When** abre la pantalla, **Then** ve un estado vacío que lo explica.
6. **Given** cualquier colaborador, **When** intenta acceder al plan de otra persona o a datos de gestión, **Then** el sistema lo impide.

---

### User Story 7 - Consultar y filtrar los planes, y ver quién tiene brechas sin plan (Priority: P2)

El administrador abre la lista de planes con código, colaborador, tipo, estado, avance, responsable, fecha de fin e indicador de hitos vencidos, y filtra por tipo, estado, departamento, responsable y rango de fechas. Un indicador muestra los colaboradores con brechas activas y sin plan de desarrollo, con acceso directo para crear su plan. El detalle muestra las pestañas Resumen, Compromisos y acciones, Hitos e Historial, con los botones Confirmar acuerdo, Devolver a Borrador, Registrar avance y Cerrar según el estado del plan.

**Why this priority**: Da visibilidad y gestión del conjunto, pero depende de planes ya creados.

**Independent Test**: Con planes de distintos tipos, estados, departamentos y responsables, aplicar cada filtro y verificar el resultado; con un colaborador con brecha activa y sin plan, verificar que aparece en el indicador y que desaparece al crearle un plan de ese tipo.

**Acceptance Scenarios**:

1. **Given** planes guardados, **When** el administrador abre la lista, **Then** los ve de más reciente a más antiguo con paginación y los filtros guardados en la URL.
2. **Given** filtros por tipo, estado, departamento, responsable y rango de fechas, **When** los aplica solos o combinados, **Then** la lista muestra solo los planes que cumplen todos.
3. **Given** colaboradores con brechas activas en su última DNF o adecuación y sin plan no Cerrado del tipo correspondiente, **When** se abre el indicador, **Then** aparecen con su departamento y un acceso a crear su plan.
4. **Given** el detalle de un plan, **When** su estado es Borrador, Acordado o Cerrado, **Then** solo se muestran los botones permitidos para ese estado y para los permisos del usuario.
5. **Given** la pestaña Historial, **When** se abre, **Then** lista cada cambio de estado, acuerdo, aceptación, devolución con su motivo o comentario, vinculación, desvinculación y registro de avance, con usuario, fecha, valor anterior y valor nuevo.

---

### User Story 8 - Ver la carta Gantt consolidada (Priority: P3)

El administrador abre la carta Gantt: una vista consolidada de las acciones de los planes Acordados, con filtros por tipo de plan, responsable y departamento, para dar seguimiento a las intervenciones.

**Why this priority**: Es una vista agregada útil pero no imprescindible para operar el ciclo del plan.

**Independent Test**: Con acciones de planes Acordados de dos tipos y departamentos y planes Borrador y Cerrados, verificar que solo aparecen acciones de planes Acordados, ubicadas por sus fechas, y que cada filtro reduce la vista.

**Acceptance Scenarios**:

1. **Given** planes en distintos estados, **When** se abre la carta Gantt, **Then** solo muestra acciones de planes Acordados, cada una en la franja de sus fechas con su estado.
2. **Given** filtros por tipo de plan, responsable y departamento, **When** se aplican, **Then** la vista muestra solo lo que cumple todos.
3. **Given** que no hay planes Acordados, **When** se abre, **Then** se muestra un estado vacío.

---

### Edge Cases

- ¿Qué pasa si el colaborador no tiene DNF ni adecuación? Se informa que no hay precarga posible y el administrador puede crear los compromisos a mano.
- ¿Qué pasa si una brecha precargada ya no corresponde porque la competencia o el conocimiento fue desactivado? Se omite de la precarga y se avisa cuántos se omitieron.
- ¿Qué pasa si el administrador quita todos los compromisos precargados? El plan puede guardarse en Borrador, pero no acordarse hasta tener al menos un compromiso.
- ¿Qué pasa si el responsable asignado deja de tener el rol de Administrador? El plan se conserva y se señala como «responsable sin permiso»; el administrador con permiso de gestión puede reasignarlo a un administrador vigente aunque el plan esté Acordado, y mientras no se reasigne un plan en Borrador no puede acordarse.
- ¿Qué pasa si el colaborador deja de estar activo con un plan abierto? El plan se conserva y se puede consultar y cerrar, pero no se pueden crear planes nuevos para esa persona.
- ¿Qué pasa si el periodo del plan se acorta de modo que quedan compromisos o acciones fuera de él? El sistema impide guardar y señala los elementos fuera del periodo.
- ¿Qué pasa si dos administradores editan el mismo plan a la vez? El segundo guardado se rechaza con un aviso de que el plan cambió, sin sobrescribir.
- ¿Qué pasa si el colaborador acepta o devuelve mientras el administrador edita el plan? Si la edición se guardó antes, el acuerdo del responsable se borró y la aceptación se rechaza porque el plan dejó de estar disponible; el colaborador debe esperar a que se confirme de nuevo.
- ¿Qué pasa si todas las acciones del plan están Canceladas? El plan puede cerrarse, y el avance se muestra como no calculable.
- ¿Qué pasa si una acción vinculada pasa a Cumplida por asistencia después de que el administrador registró un avance a mano antes de vincular? Al vincular se descarta el estado manual y prevalece el derivado.
- ¿Qué pasa si se intenta crear un plan de tipo Talento con periodo mayor a la duración definida? El periodo es libre; la duración la define el administrador y solo se exige fecha de inicio y de fin.
- ¿Qué pasa si se envía un compromiso de dimensión Conocimiento con la dimensión desactivada? Se rechaza con un mensaje que indica que no está disponible.
- ¿Qué pasa si se intenta crear un plan para un colaborador sin vínculo vigente? Se rechaza; un plan ya abierto de alguien que dejó de estar vigente se conserva y puede consultarse y cerrarse.
- ¿Qué pasa si el administrador devuelve a Borrador un plan Acordado? Se borran los acuerdos y el plan deja de ser visible para el colaborador hasta que el responsable confirme de nuevo.

## Requirements *(mandatory)*

### Functional Requirements

**Planes y datos**

- **FR-001**: El sistema MUST generar para cada plan un código único secuencial con formato PDI-0001, no editable.
- **FR-002**: Cada plan MUST tener colaborador, tipo (Mejora, Carrera o Talento), objetivo del plan (texto obligatorio), responsable, periodo con fecha de inicio y de fin obligatorias, y estado (Borrador, Acordado o Cerrado).
- **FR-003**: El tipo del plan MUST no poder cambiarse después de crearlo.
- **FR-004**: El puesto objetivo MUST ser obligatorio en los tipos Carrera y Talento y no aplica en Mejora.
- **FR-005**: El plan MUST poder guardar una referencia opcional de origen a la DNF o a la adecuación desde la cual se precargó.
- **FR-006**: El responsable MUST ser un usuario con rol Administrador; por defecto es quien crea el plan y puede editarse en Borrador y también en un plan Acordado; en este último caso los acuerdos se conservan, el plan sigue Acordado y el cambio queda en el historial. Un plan Cerrado no admite el cambio.
- **FR-007**: El plan MUST guardar la fecha y el usuario del acuerdo del responsable, la fecha de aceptación del colaborador, el comentario del colaborador al devolver, el resultado (Brecha cerrada, Parcial o No cerrada) y la conclusión opcional al cerrar.
- **FR-008**: El sistema MUST permitir como máximo un plan no Cerrado por colaborador y tipo.

**Compromisos**

- **FR-009**: Cada compromiso MUST tener una dimensión (Competencia, Conocimiento, Experiencia o Meta).
- **FR-010**: En las dimensiones Competencia y Conocimiento, el elemento MUST ser obligatorio y activo; en Experiencia y Meta no aplica. Mientras Estructura Empresarial no exponga conocimientos con grado, la dimensión Conocimiento no está disponible al crear compromisos: el sistema no la ofrece y rechaza un compromiso de esa dimensión.
- **FR-011**: En las dimensiones Experiencia y Meta, la descripción MUST ser obligatoria.
- **FR-012**: En Competencia y Conocimiento, el compromiso MUST guardar un nivel actual y un nivel a alcanzar en la escala A, B, C o D, precargados desde la brecha de origen cuando exista, y el nivel a alcanzar MUST ser distinto del actual.
- **FR-013**: El compromiso MUST permitir un indicador opcional y un periodo estimado cuyas fechas estén dentro del periodo del plan.
- **FR-014**: El compromiso MUST guardar al cerrar su resultado (Cerrada, Parcial o No cerrada).
- **FR-015**: No MUST repetirse el mismo elemento en dos compromisos del mismo plan.

**Acciones**

- **FR-016**: Cada acción MUST tener un tipo (Curso, Mentoring, Coaching, Autodesarrollo, Codesarrollo, Nueva responsabilidad u Otra), una descripción, la persona a cargo (texto libre como mentor, coach u otra) y fechas dentro del periodo del plan.
- **FR-017**: Cada compromiso MUST tener al menos una acción para poder acordar el plan.
- **FR-018**: El vínculo con una acción del Plan de Formación MUST ser opcional y permitido solo para los tipos Curso, Mentoring y Codesarrollo.
- **FR-019**: El selector de vinculación MUST listar solo acciones de planes de formación en estado Aprobado o Ejecutando en las que el colaborador del PDI sea participante.
- **FR-020**: El estado de una acción vinculada MUST ser de solo lectura y derivarse así: Cumplida si la acción formativa está Finalizada con asistencia del colaborador, Cancelada si está Cancelada, y en otro caso En curso si hoy está dentro de sus fechas o Pendiente en otro caso.
- **FR-020a**: Al cerrar el plan, el sistema MUST guardar el estado final de cada acción vinculada; a partir de entonces el estado ya no se deriva y los cambios posteriores en el Plan de Formación no afectan al plan Cerrado, que conserva su referencia a la acción formativa.
- **FR-021**: El estado de una acción no vinculada MUST registrarse a mano entre Pendiente, En curso, Cumplida y Cancelada.
- **FR-022**: El sistema MUST permitir desvincular una acción; al hacerlo el estado pasa a registrarse a mano.
- **FR-023**: El sistema MUST NOT modificar el Plan de Formación ni las actividades formativas al vincular o desvincular.

**Hitos**

- **FR-024**: Cada hito MUST tener fecha, descripción, estado (Pendiente o Realizado) y observaciones, dentro del periodo del plan.
- **FR-025**: Un hito Pendiente con fecha anterior a hoy MUST mostrarse como vencido.

**Estados y reglas de transición**

- **FR-026**: Para confirmar el acuerdo del responsable, el plan MUST tener al menos un compromiso, cada compromiso al menos una acción, un responsable y un periodo definidos.
- **FR-027**: El colaborador MUST poder aceptar o devolver un plan en Borrador solo después de que exista el acuerdo del responsable; el plan MUST pasar automáticamente de Borrador a Acordado cuando el colaborador acepta con el acuerdo del responsable vigente.
- **FR-028**: Cualquier edición de un plan en Borrador MUST borrar los acuerdos y la aceptación ya dados.
- **FR-029**: Cuando el colaborador devuelve el plan, este MUST seguir en Borrador, borrar los acuerdos y guardar el comentario, que es obligatorio.
- **FR-030**: El administrador MUST poder devolver un plan Acordado a Borrador indicando un motivo obligatorio; los acuerdos se borran.
- **FR-031**: En un plan Acordado MUST NOT poder editarse la estructura (compromisos, acciones, fechas ni periodo); solo se registran avances de acciones no vinculadas e hitos.
- **FR-032**: Para cerrar un plan, cada compromiso MUST tener su resultado y todas las acciones MUST estar Cumplidas o Canceladas; la conclusión es opcional.
- **FR-033**: El resultado del plan MUST calcularse: Brecha cerrada si todos los compromisos están Cerrada, No cerrada si ninguno lo está y Parcial en otro caso.
- **FR-034**: Un plan Cerrado MUST NOT poder editarse ni eliminarse.
- **FR-035**: El sistema MUST NOT ofrecer la eliminación de un plan Acordado; su fin de vida es el cierre.
- **FR-036**: Al cerrar un plan de tipo Carrera o Talento, la pantalla MUST ofrecer generar una nueva adecuación con el colaborador y el puesto objetivo preseleccionados.
- **FR-037**: El avance del plan MUST calcularse como el porcentaje de acciones Cumplidas sobre las acciones no Canceladas, y mostrarse como no calculable cuando no hay acciones no Canceladas.

**Precarga e integración con otros módulos**

- **FR-038**: Para el tipo Mejora, el sistema MUST ofrecer precargar compromisos desde las brechas activas de la última DNF del colaborador; para Carrera y Talento, desde las brechas de la última adecuación al puesto objetivo elegido.
- **FR-039**: La precarga MUST poder aceptarse, quitarse en parte o ignorarse, y MUST omitir con aviso los elementos inactivos.
- **FR-040**: El sistema MUST obtener colaboradores y departamentos de Gestión de Personal, y puestos, competencias y conocimientos activos de Estructura Empresarial, sin duplicar sus datos.
- **FR-041**: El sistema MUST derivar el nivel objetivo de los planes de tipo Carrera desde Rutas y planes de carrera cuando el colaborador tenga una inscripción Activa hacia ese puesto.
- **FR-042**: *(Fuera del alcance de esta entrega.)* La sugerencia de micro-contenidos de Micro-Learning en acciones de tipo Autodesarrollo queda para una etapa posterior; esta feature no los gestiona.
- **FR-043**: El sistema MUST ofrecer a Reportes los datos necesarios para «Mi plan de desarrollo» y para indicadores de cierre de brechas.
- **FR-044**: Los botones «Crear plan de desarrollo» e «Incluir en Programa de Talentos» de la adecuación (feature 020) MUST quedar habilitados hacia el asistente de creación con colaborador, tipo y puesto objetivo preseleccionados (Carrera y Talento respectivamente), y la restricción de FR-034 de esa feature deja de aplicar mientras exista esta feature.

**Permisos y acceso**

- **FR-045**: El sistema MUST proteger cada operación con una clave de permiso estable sembrada en el módulo: `desarrollo.pdi.gestionar`, `desarrollo.pdi.acordar`, `desarrollo.pdi.seguimiento`, `desarrollo.pdi.vincular`, `desarrollo.pdi.cerrar`, `desarrollo.pdi.gantt`, `desarrollo.pdi.ver`, `desarrollo.pdi.ver_propio` y `desarrollo.pdi.aceptar_propio`, con la correspondencia operación-permiso de la tabla de roles de esta spec.
- **FR-046**: Por ahora todas las claves MUST asignarse al rol Administrador (Gestor de Talento) y solo `desarrollo.pdi.ver_propio` y `desarrollo.pdi.aceptar_propio` al rol Colaborador; la administración de roles podrá reasignarlas sin cambiar esta feature.
- **FR-047**: El colaborador MUST ver solo sus propios planes, compromisos, acciones, hitos e historial, y solo desde que el plan tiene el acuerdo del responsable (un Borrador sin ese acuerdo no es visible para él), y no puede acceder a los de otra persona por ningún medio.
- **FR-048**: Los botones de crear, editar, acordar, registrar avance, vincular, cerrar y devolver MUST mostrarse solo al administrador y solo cuando el estado del plan lo permite; los permisos también MUST validarse al ejecutar la operación.

**Auditoría**

- **FR-049**: El sistema MUST registrar quién creó cada plan y cuándo.
- **FR-050**: El historial MUST registrar con usuario, fecha, valor anterior y valor nuevo cada cambio de estado, acuerdo, aceptación, devolución (con su motivo o comentario), vinculación y desvinculación con el Plan de Formación, y cada registro de avance de acciones e hitos.
- **FR-051**: El historial MUST ser de solo lectura y no poder editarse ni eliminarse.

**Alcance**

- **FR-052**: El sistema MUST NOT administrar el Plan de Formación ni las actividades formativas, ni ejecutar ascensos, cambios de puesto ni cambios en la ficha del colaborador.

### Roles y permisos

| Acción | Permiso | Administrador (Gestor de Talento) | Colaborador |
|---|---|---|---|
| Crear y editar un plan en Borrador | `desarrollo.pdi.gestionar` | Sí | No |
| Precargar compromisos desde la DNF o la adecuación | `desarrollo.pdi.gestionar` | Sí | No |
| Confirmar el acuerdo como responsable del plan | `desarrollo.pdi.acordar` | Sí | No |
| Devolver un plan Acordado a Borrador | `desarrollo.pdi.gestionar` | Sí | No |
| Registrar avances de acciones e hitos en un plan Acordado | `desarrollo.pdi.seguimiento` | Sí | No |
| Vincular acciones del plan a acciones del Plan de Formación | `desarrollo.pdi.vincular` | Sí | No |
| Cerrar un plan con su resultado | `desarrollo.pdi.cerrar` | Sí | No |
| Ver la carta Gantt consolidada | `desarrollo.pdi.gantt` | Sí | No |
| Ver los colaboradores con brechas y sin plan | `desarrollo.pdi.gestionar` | Sí | No |
| Consultar planes, compromisos, acciones, hitos e historial | `desarrollo.pdi.ver` / `desarrollo.pdi.ver_propio` | Sí (todos) | Sí (solo los propios) |
| Aceptar el plan propio | `desarrollo.pdi.aceptar_propio` | No aplica | Sí |
| Devolver el plan propio con un comentario | `desarrollo.pdi.aceptar_propio` | No aplica | Sí |

### Key Entities

- **Plan de desarrollo individual**: Acuerdo de desarrollo de un colaborador. Tiene código, colaborador, tipo, objetivo, puesto objetivo (Carrera y Talento), origen opcional, responsable, periodo, estado, acuerdos, comentario del colaborador, resultado y conclusión.
- **Compromiso de mejora**: Brecha o meta que el plan se compromete a cerrar. Pertenece a un plan; tiene dimensión, elemento o descripción, niveles actual y a alcanzar, indicador, periodo estimado y resultado.
- **Acción**: Intervención para cumplir un compromiso. Pertenece a un compromiso; tiene tipo, descripción, persona a cargo, fechas, estado y, opcionalmente, la referencia a una acción del Plan de Formación.
- **Hito de seguimiento**: Revisión intermedia programada por el responsable. Pertenece a un plan; tiene fecha, descripción, estado y observaciones.
- **Registro de historial**: Evento auditado de un plan con usuario, fecha, tipo de evento, valor anterior, valor nuevo y motivo o comentario.
- **Origen (DNF o adecuación)**: Referencia, no propiedad, a la evaluación desde la cual se precargó el plan; las propietarias son sus features.
- **Acción del Plan de Formación vinculada**: Referencia, no propiedad, a una acción formativa cuya ejecución y asistencia determinan el estado de la acción del PDI.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un administrador puede crear un plan de Mejora precargado desde una DNF, con al menos un compromiso, una acción y un hito, en menos de 5 minutos.
- **SC-002**: El 100 % de los planes Acordados tiene al menos un compromiso con al menos una acción, responsable, periodo y las dos confirmaciones registradas.
- **SC-003**: El 100 % de las ediciones de un plan en Borrador y de las devoluciones borra los acuerdos previos, verificable en pruebas de aceptación.
- **SC-004**: En el 100 % de los casos, un colaborador solo ve sus propios planes y ninguna operación de gestión, verificable intentando acceder a planes de otras personas.
- **SC-005**: El estado de una acción vinculada coincide con el de la actividad formativa de origen (Cumplida, Cancelada, En curso o Pendiente) en el 100 % de los casos de prueba definidos.
- **SC-006**: El resultado del plan coincide con la regla (Brecha cerrada, Parcial, No cerrada) en el 100 % de las combinaciones de resultados de compromisos probadas.
- **SC-007**: El 100 % de los eventos de estado, acuerdo, devolución, vinculación y avance aparece en el historial con usuario, fecha y valores anterior y nuevo.
- **SC-008**: Un colaborador puede aceptar o devolver su plan en Borrador en menos de 2 minutos desde que abre «Mi plan de desarrollo».
- **SC-009**: Un administrador identifica en menos de 1 minuto cuántos colaboradores con brechas activas no tienen plan y accede a crear el de uno de ellos.
- **SC-010**: Ningún plan Cerrado puede modificarse ni eliminarse por ningún flujo de la aplicación.

## Assumptions

- Los módulos Gestión de Personal, Estructura Empresarial, DNF (006), Plan de Formación (013), Micro-Learning (016), Actividades Formativas (017), Reportes (018), Rutas de carrera (019) y Adecuación a puesto futuro (020) ya están implementados y se consumen solo a través de sus interfaces públicas; esta feature no modifica sus datos.
- Por ahora solo existe el rol Administrador (Gestor de Talento) para la gestión; no existe el rol de jefatura. Los mockups usan «jefatura», «firma» y un alcance «Equipo»; esta spec los reemplaza por «responsable administrador», «acuerdo y aceptación» y alcance de todos los planes, y se documenta en la sección Pantallas.
- La escala de grados es A, B, C, D, donde A es el nivel más alto, igual que en las features 006 y 020.
- «Última DNF» y «última adecuación» significan la más reciente guardada del colaborador, y para la adecuación la más reciente al puesto objetivo elegido.
- Las brechas precargadas desde la DNF o la adecuación son una copia al momento de crear el plan; los cambios posteriores en la evaluación de origen no modifican el plan.
- Los hitos y las acciones deben caer dentro del periodo del plan; no hay límite de cantidad de compromisos, acciones o hitos por plan.
- No hay duración máxima para el periodo de un plan, incluido el tipo Talento, cuya duración definida (por ejemplo, uno o dos años) la fija el administrador al elegir las fechas.
- El historial se conserva sin límite de tiempo y solo se consulta; no se pagina más allá de lo habitual en la aplicación.
- La notificación al colaborador cuando se le asigna un plan queda fuera de alcance; el colaborador lo ve al abrir «Mi plan de desarrollo».
- La eliminación de planes en Borrador no se especifica en el documento fuente y queda fuera de alcance; un plan Acordado termina siempre en Cerrado.
- Los conocimientos con grado no existen hoy en Estructura Empresarial; la dimensión Conocimiento queda desactivada por configuración, igual que en Rutas y Adecuación, y se habilitará cuando exista esa fuente.
- Los permisos `desarrollo.pdi.*` se mantienen como un catálogo en código con asignación por defecto (como en Rutas y Adecuación), no como filas sembradas en una tabla de permisos.
- Fuera de alcance: la sugerencia de micro-contenidos (FR-042), ejecutar ascensos o cambios de puesto, administrar el Plan de Formación o las actividades, rol de jefatura y alcance por equipo, notificaciones, exportación del plan, y firma digital.

## Pantallas *(si la feature tiene UI)*

> Nota de diseño: los tableros `design/PDIAdmin.dc.html`, `design/PDIAsistente.dc.html`, `design/PDIDetalle.dc.html` y `design/PDISeguimiento.dc.html` son un mismo prototipo con distinta vista inicial; `design/PDIColaborador.dc.html` es el de la persona colaboradora. Las diferencias con esta spec (jefatura, firma, alcance «Equipo», panel de permisos simulados, «Ver como colaborador») se registrarán en `plan.md` y en la sección de diferencias de `design/DESIGN.md`.

### Lista de planes (administrador)
- **Propósito**: consultar el conjunto de planes y encontrar a quienes necesitan uno.
- **Datos que muestra**: indicadores del conjunto; tabla con código, colaborador, tipo, estado, avance, responsable, fecha de fin e indicador de hitos vencidos; filtros por tipo, estado, departamento, responsable y rango de fechas; bloque «Personas con brecha y sin PDI» con acceso a crear.
- **Acciones disponibles** (y quién puede hacerlas): administrador con permiso de ver — filtrar, abrir detalle; con permiso de gestionar — «Nuevo PDI» y «Crear PDI» desde el bloque de brechas sin plan. Colaborador — sin acceso.
- **Estados**: vacío, cargando, error con «Reintentar», sin permisos.
- **Mockup**: `design/PDIAdmin.dc.html` (patrón Listado de `design/DESIGN.md`).

### Asistente de creación (administrador)
- **Propósito**: crear un plan en Borrador en tres pasos.
- **Datos que muestra**: paso 1 colaborador, tipo, puesto objetivo y oferta de precarga; paso 2 tabla editable de compromisos con sus acciones y el selector «Vincular a Plan de Formación»; paso 3 responsable, periodo y calendario de hitos.
- **Acciones disponibles** (y quién puede hacerlas): administrador con permiso de gestionar — avanzar y retroceder entre pasos, agregar y quitar compromisos, acciones e hitos, vincular, guardar en Borrador. Colaborador — sin acceso.
- **Estados**: vacío (sin colaborador), validación de campos y de fechas, plan duplicado por colaborador y tipo, cargando, error con «Reintentar», sin permisos, conflicto de edición.
- **Mockup**: `design/PDIAsistente.dc.html` (patrón de asistente de `design/DESIGN.md`).

### Detalle del plan (administrador)
- **Propósito**: revisar un plan, acordarlo, dar seguimiento, devolverlo y cerrarlo.
- **Datos que muestra**: pestañas Resumen (datos, acuerdos, avance, comentario del colaborador), Compromisos y acciones, Hitos e Historial.
- **Acciones disponibles** (y quién puede hacerlas): administrador — Confirmar acuerdo, Devolver a Borrador (modal con motivo obligatorio), Registrar avance, Vincular y desvincular, Cerrar (modal con resultado por compromiso y conclusión), Generar nueva adecuación tras cerrar Carrera o Talento; solo las permitidas por el estado y los permisos. Colaborador — sin acceso a esta pantalla.
- **Estados**: cargando, error con «Reintentar», sin permisos, plan Cerrado en solo lectura, vínculo que requiere revisión.
- **Mockup**: `design/PDIDetalle.dc.html` (patrón Detalle de `design/DESIGN.md`).

### Carta Gantt (administrador)
- **Propósito**: dar seguimiento consolidado a las acciones de los planes Acordados.
- **Datos que muestra**: acciones de planes Acordados ubicadas en una línea de tiempo con su estado; filtros por tipo de plan, responsable y departamento.
- **Acciones disponibles** (y quién puede hacerlas): administrador con permiso de Gantt — filtrar y abrir el plan de una acción. Colaborador — sin acceso.
- **Estados**: vacío, cargando, error con «Reintentar», sin permisos.
- **Mockup**: `design/PDISeguimiento.dc.html`.

### Mi plan de desarrollo (colaborador)
- **Propósito**: que la persona consulte, acepte o devuelva su plan y vea qué sigue.
- **Datos que muestra**: tarjetas de sus planes (solo los que ya tienen el acuerdo del responsable) con tipo, estado, avance y periodo; detalle con compromisos, acciones e hitos; sección «Qué sigue» con las próximas acciones e hitos y el enlace a la actividad formativa de las acciones vinculadas.
- **Acciones disponibles** (y quién puede hacerlas): colaborador — «Aceptar plan» y «Devolver con comentario» solo en un plan Borrador propio. Administrador — no aplica.
- **Estados**: vacío (sin planes), cargando, error con «Reintentar», plan Cerrado en solo lectura.
- **Mockup**: `design/PDIColaborador.dc.html`.
