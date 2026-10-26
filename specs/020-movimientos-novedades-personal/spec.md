# Feature Specification: Registro de movimientos y novedades de personal

**Feature Branch**: `020-movimientos-novedades-personal`

**Created**: 2026-10-21

**Status**: Draft

**Input**: User description: "Registro de movimientos y novedades de personal. Permitir al Administrador de Talento Humano gestionar ascensos, traslados y novedades laborales (permiso, ausencia, licencia y vacaciones), y al Colaborador presentar las solicitudes de novedades habilitadas y consultar los resultados que le correspondan. La gestión distingue solicitar, revisar, aprobar o rechazar y aplicar: registrar una solicitud no modifica la posición, el vínculo ni el estado laboral, y aprobar un movimiento futuro no lo ejecuta anticipadamente. Consume las políticas publicadas (versión conservada por solicitud) y exige una evaluación verificable frente al puesto destino antes de aprobar ascensos y traslados. No crea ni modifica políticas, puestos, posiciones ni rutas de carrera; no usa IA para decidir, no calcula nómina ni saldos vacacionales y no implementa offboarding. Solo dos roles: Administrador de Talento Humano y Colaborador."

## Clarifications

### Session 2026-10-21

- Q: Cuando llega la fecha efectiva de un movimiento aprobado, ¿quién lo aplica? → A: El sistema lo aplica automáticamente en la fecha efectiva mediante una operación controlada; el Administrador conserva una acción manual para reintentar las incidencias.
- Q: Mientras no existan las reglas de superposición, ¿qué hace el sistema ante una novedad cuyo período se cruza con otra presentada o aprobada? → A: Permite presentar, pero señala el cruce al Administrador en la revisión y exige que lo reconozca (con constancia) antes de aprobar; no bloquea ni lo ignora.
- Q: ¿Se entregan primero la presentación y consulta de ascensos y traslados, con la aprobación habilitada solo cuando exista el contrato de evaluación? → A: Sí, por etapas: los movimientos se presentan y consultan desde el inicio y la aprobación permanece impedida («evaluación no verificable») hasta que exista el contrato; las novedades no dependen de él.
- Q: ¿Qué parte de un movimiento puede ver el Colaborador mientras no está aprobado o aplicado? → A: Nada: el Colaborador solo ve un movimiento propio cuando está aprobado, programado o aplicado, y únicamente el resultado y la fecha efectiva; propuestas, evaluación, revisiones, rechazos e incidencias de aplicación no se le muestran.
- Q: ¿Puede el solicitante retirar su solicitud de novedad antes de que se resuelva? → A: Sí, mientras esté Presentada o En revisión: queda «Retirada por el solicitante» con constancia de quién y cuándo, sin borrarse; una vez resuelta ya no se retira.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Presentar un permiso, una licencia o unas vacaciones (Priority: P1)

El Colaborador consulta las condiciones aplicables a su solicitud, completa los datos del tipo elegido, adjunta los respaldos exigidos y la presenta. El Administrador de Talento Humano puede registrarla en nombre del colaborador, dejando constancia de que actuó por él.

**Why this priority**: Es la acción cotidiana de mayor frecuencia y la primera que consume las políticas publicadas; sin ella no existe el flujo de novedades.

**Independent Test**: Con una política vigente de Permisos que admite solicitudes, presentar una solicitud propia con todos los datos y comprobar que queda registrada con su versión de política, sin modificar la ficha ni el vínculo; repetir con una política sin configurar y con una que no admite solicitudes y comprobar que no se confirma.

**Acceptance Scenarios**:

1. **Given** una política vigente de Permisos que admite nuevas solicitudes y un Colaborador con ficha asociada, **When** completa el período, el motivo y los respaldos exigidos y confirma, **Then** la solicitud queda presentada con su versión de política y los parámetros aplicados, y puede consultar su estado. (RMN-CA05)
2. **Given** una solicitud de vacaciones cuyo inicio incumple la anticipación mínima de la versión aplicable, **When** intenta presentarla, **Then** no se confirma y se explica la condición incumplida, calculada en días calendario con la fecha de presentación y la zona horaria de la organización. (RMN-CA06)
3. **Given** una anticipación de cero, **When** el inicio es la fecha actual, **Then** se admite; **When** el inicio es una fecha pasada, **Then** se rechaza: cero no autoriza fechas pasadas.
4. **Given** un tipo sin versión publicada, **When** se intenta presentar, **Then** se bloquea y se informa «Política sin configurar»; **Given** una política vigente que no admite nuevas solicitudes, **Then** se bloquea y se informa «No se admiten nuevas solicitudes»; **Given** una falla al consultar la política, **Then** no se confirma y se distingue la falla de las dos causas anteriores. (RMN-CA10)
5. **Given** que la política exige justificación o documento de respaldo, **When** falta alguno, **Then** no se confirma y se señala el campo; un documento exigido que no está disponible no se presume cumplido.
6. **Given** un Administrador que registra la solicitud por un colaborador, **When** confirma, **Then** la solicitud conserva por separado quién la registró y a quién corresponde, sin darle por ello la facultad de resolverla.
7. **Given** un borrador de formulario sin presentar, **When** se publica una política nueva, **Then** al presentar se aplica la versión vigente en ese momento: el borrador no reservó ninguna versión.
8. **Given** una solicitud que ya existe, **When** se envía de nuevo la misma confirmación (doble clic o reintento), **Then** no se duplica. (RMN-CA12)

---

### User Story 2 - Revisar, resolver y consultar novedades (Priority: P1)

El Administrador ve las solicitudes de su organización, las revisa y las aprueba o rechaza con justificación. El Colaborador consulta únicamente sus propias solicitudes y el resultado comunicable.

**Why this priority**: Sin resolución y seguimiento la presentación no tiene efecto ni trazabilidad; es el cierre del ciclo de la User Story 1.

**Independent Test**: Presentar dos solicitudes de colaboradores distintos, resolver una como aprobada y otra como rechazada con razón, y comprobar que cada colaborador ve solo la suya, que el rechazo conserva responsable y justificación y que un usuario de otra organización no obtiene nada.

**Acceptance Scenarios**:

1. **Given** solicitudes presentadas, **When** el Administrador abre el listado, **Then** ve las de su organización con tipo, colaborador, fecha de presentación, responsable, período solicitado y estado, y puede filtrar por tipo, estado, colaborador y período; «sin registros» no se confunde con un error de carga. (RMN-4.1)
2. **Given** una novedad presentada, **When** el Administrador la pasa a revisión y luego la resuelve, **Then** se conserva quién, cuándo, el sentido y la justificación de cada paso.
2b. **Given** una novedad cuyo período se cruza con otra presentada o aprobada del mismo colaborador, **When** el Administrador la revisa, **Then** se le señala el cruce con las solicitudes involucradas y no puede aprobarla sin reconocerlo; rechazarla no exige ese reconocimiento y el reconocimiento queda con autor y momento.
3. **Given** una novedad pendiente, **When** el Administrador la rechaza indicando la razón, **Then** se guarda la resolución y el Colaborador autorizado ve su resultado comunicable. (RMN-CA08)
4. **Given** una solicitud presentada con la versión 1 de la política, **When** entra en vigor la versión 2 antes de resolverla, **Then** la solicitud mantiene las condiciones y la referencia de la versión 1, y la resolución sigue verificando permisos, estado y disponibilidad actuales. (RMN-CA09)
5. **Given** un Colaborador, **When** consulta o intenta resolver una solicitud ajena, o un usuario de otra organización intenta lo mismo, **Then** no obtiene información ni modifica nada y la respuesta es indistinguible de la de un registro inexistente. (RMN-CA11)
6. **Given** una solicitud ya resuelta, **When** alguien intenta cambiarla editando la resolución, **Then** no es posible; cualquier corrección requiere una operación auditada que se define aparte.
7. **Given** dos resoluciones simultáneas sobre la misma solicitud, **When** se procesan, **Then** solo una prospera y no se duplican hechos. (RMN-CA12)
8. **Given** una resolución que falla por una dependencia o conflicto, **When** termina, **Then** la solicitud no queda a medias: conserva su estado anterior.
9. **Given** una solicitud de novedad Presentada o En revisión, **When** el Colaborador (o el Administrador que la registró en su nombre) la retira, **Then** queda «Retirada por el solicitante» con constancia de quién y cuándo, deja de ser resoluble, se conserva y no se borra; **Given** una solicitud ya aprobada o rechazada, **Then** no admite retiro.
10. **Given** un retiro y una resolución simultáneos sobre la misma solicitud, **When** se procesan, **Then** solo uno prospera y el otro recibe un conflicto.

---

### User Story 3 - Registrar y revisar ausencias ocurridas (Priority: P2)

El Administrador registra una inasistencia ya ocurrida con su fecha o período y el motivo o respaldo disponible. El registro es un hecho informado que puede revisarse o justificarse después; no es una autorización.

**Why this priority**: Completa las novedades, pero depende de reglas temporales que pertenecen al proceso y puede entregarse después de permisos, licencias y vacaciones.

**Independent Test**: Registrar una ausencia ya ocurrida y comprobar que queda como hecho informado sin convertirse en permiso aprobado; revisarla y justificarla y comprobar que ambos estados se distinguen; comprobar que el Colaborador solo consulta las propias autorizadas.

**Acceptance Scenarios**:

1. **Given** una inasistencia ya ocurrida y un Administrador autorizado, **When** registra el hecho, **Then** se conserva como hecho informado para revisión sin declararlo permiso aprobado. (RMN-CA07)
2. **Given** una ausencia informada, **When** el Administrador la revisa o justifica, **Then** se registran actor, momento y resultado, y el estado «revisada o justificada» se diferencia de «informada».
3. **Given** el formulario de ausencia, **When** se abre, **Then** se identifica que se informa un hecho ocurrido, no que se solicita autorización anticipada, y no se aplica la anticipación mínima de los demás tipos.
4. **Given** un Colaborador, **When** intenta registrar o revisar ausencias, **Then** se le deniega; solo consulta las propias que estén autorizadas para comunicación.

---

### User Story 4 - Proponer un ascenso o traslado y reunir la evidencia (Priority: P2)

El Administrador selecciona una ficha con vínculo compatible, una posición destino definida por Estructura Empresarial, la fecha efectiva y la justificación, y presenta la propuesta. El sistema muestra la situación actual y las condiciones que deben comprobarse antes de resolver, incluida la evaluación del colaborador frente al puesto destino.

**Why this priority**: Es el núcleo de los movimientos, pero su aprobación depende de un contrato de evaluación que todavía debe acordarse; presentar y consultar puede avanzar sin él.

**Independent Test**: Presentar un ascenso y comprobar que existe una solicitud trazable sin cambiar la asignación; intentar aprobar sin evaluación verificable y comprobar que se rechaza con la evidencia pendiente; con una evaluación válida comprobar que se puede decidir, sin que la evaluación decida por sí sola.

**Acceptance Scenarios**:

1. **Given** una ficha con vínculo, una posición destino identificada y política vigente, **When** el Administrador presenta un ascenso con justificación, **Then** existe una solicitud trazable y la asignación actual no cambia. (RMN-CA01)
2. **Given** una propuesta de traslado sin evaluación verificable frente al puesto destino, **When** se intenta aprobar, **Then** se rechaza la aprobación, se informa la evidencia pendiente y se conserva la asignación anterior. (RMN-CA02)
3. **Given** una evaluación que identifica colaborador, puesto, requisitos considerados, momento y resultado, correspondiente al destino y vigente, **When** el Administrador la consulta, **Then** la ve como evidencia para decidir, sin porcentaje mínimo, sin exigir ausencia de brechas y sin aprobación automática.
4. **Given** el contrato de evaluación no disponible o evidencia no verificable, **When** se intenta aprobar, **Then** la aprobación queda impedida y no se admite una afirmación manual de «apto»; el Administrador sigue pudiendo presentar, consultar, pasar a revisión y rechazar el movimiento, y la pantalla explica «evaluación no verificable» en lugar de mostrar un control de aprobar.
4b. **Given** que el contrato de evaluación se incorpora más adelante, **When** una propuesta ya presentada vuelve a revisarse, **Then** se consulta la evaluación con la verificación de ese momento sin rehacer la propuesta ni alterar lo registrado.
5. **Given** una propuesta, **When** el sistema presenta las comprobaciones, **Then** distingue evaluación válida, evaluación ausente o no verificable, y condición estructural incumplida, y las evidencias ausentes se muestran como tales, sin puntuaciones ficticias.
6. **Given** una política de Ascensos o Traslados con anticipación mínima respecto de la fecha efectiva o documento adicional obligatorio, **When** no se cumple, **Then** no se presenta; la justificación es siempre obligatoria.
7. **Given** una posición destino inexistente, suprimida o de otra organización, **When** se intenta proponer, **Then** se rechaza.
8. **Given** un Colaborador, **When** intenta proponer o resolver un movimiento, **Then** se le deniega.
9. **Given** un movimiento propio en estado presentado, en revisión o rechazado, **When** el Colaborador consulta sus solicitudes, **Then** no aparece; **Given** el movimiento aprobado, programado o aplicado, **Then** ve solo el resultado y la fecha efectiva, sin propuesta, evaluación, deliberaciones, documentos restringidos ni incidencias de aplicación.

---

### User Story 5 - Aprobar, programar y aplicar un movimiento (Priority: P2)

El Administrador aprueba o rechaza el movimiento con justificación. Un movimiento aprobado con fecha efectiva futura queda programado y se aplica al llegar esa fecha, verificando de nuevo las condiciones actuales; si algo lo impide, queda una incidencia sin cambiar la asignación.

**Why this priority**: Es el efecto real sobre la trayectoria laboral y el punto de mayor riesgo; depende de las historias 4 y de las verificaciones de Estructura.

**Independent Test**: Aprobar un movimiento con fecha efectiva futura y comprobar que la posición no cambia y figura programado; llegado el día, aplicarlo y comprobar el cambio y el hecho laboral; repetir con un destino que dejó de admitir asignación y comprobar la incidencia sin asignación parcial.

**Acceptance Scenarios**:

1. **Given** una solicitud válida con fecha efectiva futura, **When** se aprueba, **Then** permanece la posición anterior hasta esa fecha y el movimiento figura programado. (RMN-CA03)
2. **Given** un movimiento programado y llegada su fecha efectiva, **When** el sistema lo aplica automáticamente mediante la operación controlada (sin que el Administrador deba intervenir), **Then** se verifican de nuevo la posición destino, el vínculo y la posibilidad de asignación, el cambio es efectivo y se registra el hecho laboral con referencia a la solicitud y a la política utilizada.
3. **Given** un movimiento autorizado cuyo destino ya no admite asignación al aplicar, **When** el sistema lo intenta en la fecha efectiva, **Then** se registra una incidencia de aplicación, no se ocupan posiciones incompatibles y no hay asignación parcial. (RMN-CA04)
4. **Given** un movimiento con incidencia de aplicación, **When** el Administrador usa la acción manual de reintento y las condiciones ya se cumplen, **Then** se aplica una sola vez con la misma verificación; si siguen sin cumplirse, la incidencia se conserva y se explica.
5. **Given** una aplicación que falla, **When** se consulta el resultado, **Then** no se comunica como aplicado y se conserva información suficiente para una recuperación controlada. (RMN-CA13)
6. **Given** confirmaciones repetidas u operaciones concurrentes sobre la misma actuación o posición (incluida la aplicación automática frente a un reintento manual simultáneo), **When** se procesan, **Then** no se duplican hechos ni se confirman asignaciones incompatibles. (RMN-CA12)
7. **Given** una solicitud que solo se presentó, **When** llega la fecha efectiva, **Then** no se aplica: presentar no ejecuta nada y solo se aplica lo aprobado.
8. **Given** un movimiento ya aplicado, **When** alguien intenta editarlo o eliminarlo, **Then** no es posible; las correcciones necesitan una operación trazable que se define aparte.
9. **Given** un colaborador con un movimiento aprobado, **When** se aplica otro movimiento o un ingreso que lo volvería simultáneo, **Then** se rechaza: nunca hay dos posiciones vigentes ni vínculos simultáneos para la misma persona en una fecha.

---

### Edge Cases

- Dos solicitudes de novedad con períodos que se superponen para el mismo colaborador: las reglas de superposición no están aprobadas, así que el sistema no bloquea la presentación ni decide por su cuenta; señala el cruce al Administrador en la revisión, con las solicitudes involucradas, y exige que lo reconozca dejando constancia antes de aprobar. No presenta el período como validado si no pudo comprobarlo.
- Vacaciones: el sistema no calcula ni muestra saldos; no se presenta ningún saldo estimado como verificado.
- Un documento de respaldo exigido deja de estar accesible entre la presentación y la resolución: se informa el problema y no se presume cumplimiento.
- La política cambia (nueva versión, o se apaga la disponibilidad) con solicitudes pendientes: conservan su versión; las nuevas usan la vigente; las existentes no se cancelan.
- Una solicitud se presenta justo cuando una versión programada entra en vigencia: la que confirma primero fija la versión, de manera consistente con el registro de la solicitud.
- La ficha, el vínculo o la posición de un movimiento cambian entre la propuesta y la aprobación o la aplicación: se verifica de nuevo y, si ya no es posible, se informa o se registra una incidencia.
- Colaborador sin ficha asociada, o con el vínculo ya terminado: no puede presentar solicitudes y se le explica.
- Un Administrador es también el solicitante registrado: se conservan por separado su intervención como registrador y como resolutor, sin inventar aprobadores adicionales.
- Dos administradores resuelven a la vez la misma solicitud: una sola resolución prospera y la otra recibe un conflicto con la información del formulario recuperable, sin reintento automático.
- Un fallo de carga en un listado: se muestra como error con reintento, nunca como «sin registros».
- Una solicitud rechazada: se conserva; los rechazos no se eliminan.
- Falla la integración con Estructura al verificar o aplicar un movimiento: la verificación no se da por cumplida y la aplicación no avanza.

## Requirements *(mandatory)*

### Functional Requirements

**Tipos y alcance**

- **FR-001**: El sistema MUST admitir exactamente seis tipos de actuación definidos por el sistema: ascenso, traslado (movimientos) y permiso, licencia, vacaciones y ausencia (novedades); ningún tenant puede crear otros ni alterar su comportamiento con campos libres, expresiones o flujos propios.
- **FR-002**: Descensos, cambios contractuales y comisiones de servicio MUST NOT habilitarse por cambiar el texto de una política; requieren una especificación posterior.
- **FR-003**: Esta funcionalidad MUST NOT crear ni modificar políticas, puestos, posiciones, perfiles de competencias ni rutas de carrera, MUST NOT usar IA para decidir, MUST NOT calcular nómina, remuneraciones ni saldos vacacionales y MUST NOT implementar offboarding ni alterar ingresos o reingresos de GP-02.

**Datos de una actuación**

- **FR-004**: Cada solicitud o registro MUST identificar: identificador único dentro del tenant, tipo, ficha del colaborador y vínculo laboral pertinente, usuario que la presenta o registra y en nombre de quién (si aplica), momento de presentación, justificación o motivo, fecha o período solicitado, estado, responsable, momentos de cambios de estado, versión de política y copia de los parámetros aplicados, respaldos referenciados y resolución con responsable y justificación.
- **FR-005**: La justificación MUST ser obligatoria para movimientos siempre y para novedades cuando la política lo establezca.
- **FR-006**: Los movimientos MUST incorporar la posición actual, la posición destino y la referencia a la evaluación frente al puesto destino, sin almacenar una segunda definición editable del puesto ni asignar dos posiciones simultáneas.
- **FR-007**: El sistema MUST diferenciar fecha de presentación, momento de resolución y fecha efectiva; una fecha desconocida MUST NOT sustituirse por la fecha actual ni por una inferencia.

**Presentación y política aplicable**

- **FR-008**: Al preparar una actuación el sistema MUST mostrar las condiciones aplicables sin reservar versión; al presentar formalmente MUST seleccionar la versión publicada vigente del tipo en el momento de la confirmación, de forma consistente con las publicaciones concurrentes, y registrar su referencia y los parámetros aplicados.
- **FR-009**: Si la política no existe, no admite nuevas solicitudes o la consulta falla, el sistema MUST NOT confirmar la presentación y MUST distinguir las tres causas.
- **FR-010**: Las solicitudes presentadas MUST conservar su versión y parámetros aunque entre otra versión; deshabilitar nuevas solicitudes MUST NOT cancelar las existentes; las condiciones históricas MUST NOT permitir omitir comprobaciones actuales de integridad, autorización o disponibilidad.
- **FR-011**: Para permisos, licencias y vacaciones el sistema MUST aplicar los parámetros publicados de disponibilidad, justificación, documento obligatorio y anticipación mínima; la anticipación MUST compararse con la fecha de presentación en la zona horaria de la organización, con cero admitiendo el día actual y nunca fechas pasadas.
- **FR-012**: Para ausencias el sistema MUST NOT aplicar anticipación mínima ni inventar un parámetro de retroactividad.
- **FR-013**: Para ascensos y traslados el sistema MUST aplicar disponibilidad, anticipación mínima respecto de la fecha efectiva y documento adicional cuando se configure; una política MUST NOT dispensar la evaluación frente al puesto destino.

**Novedades**

- **FR-014**: El Colaborador MUST poder presentar solicitudes propias de permiso, licencia o vacaciones cuando exista política aplicable que admita nuevas solicitudes; el Administrador MUST poder registrarlas por el colaborador con constancia de autoría.
- **FR-015**: Antes de confirmar, el sistema MUST comprobar datos obligatorios, condiciones temporales definidas y existencia de los documentos exigidos, mostrar la política aplicada y pedir confirmación.
- **FR-016**: El Administrador MUST poder registrar ausencias ocurridas con fecha o período y motivo o respaldo; el resultado MUST distinguir un hecho informado de su revisión o justificación, y el registro MUST NOT producir una autorización retroactiva.
- **FR-017**: El Administrador MUST resolver las novedades que exigen aprobación; el sistema MUST NOT incorporar cadenas de aprobadores, sustitución por Líder ni aprobación por transcurso del tiempo.
- **FR-018**: Las reglas temporales y de superposición no aprobadas MUST especificarse antes de convertirlas en bloqueos, y la interfaz MUST NOT presentar como validado un saldo o período que no pudo comprobar.
- **FR-018b**: Mientras no existan reglas de superposición aprobadas, el sistema MUST permitir presentar una novedad cuyo período se cruce con otra presentada o aprobada del mismo colaborador, MUST detectar y señalar el cruce al Administrador en la revisión (identificando las solicitudes involucradas) y MUST exigir que el Administrador lo reconozca, con constancia de quién y cuándo, antes de aprobar; MUST NOT bloquear ni aprobar por sí mismo por ese motivo.

**Movimientos**

- **FR-019**: El Administrador MUST poder proponer un ascenso o traslado seleccionando una ficha con vínculo compatible, una posición destino definida por Estructura Empresarial, fecha efectiva y justificación; el sistema MUST presentar la situación actual y las condiciones que se comprobarán.
- **FR-020**: Antes de aprobar, el sistema MUST exigir una evaluación verificable del colaborador frente a los requisitos del puesto destino que identifique colaborador, puesto, requisitos considerados, momento y resultado, que corresponda al destino y esté vigente según el proceso evaluador.
- **FR-021**: El sistema MUST diferenciar evaluación válida, evaluación ausente o no verificable y condición estructural incumplida; una evaluación válida MUST ser evidencia para decidir y MUST NOT aprobar automáticamente ni imponer ausencia de brechas ni un porcentaje mínimo.
- **FR-022**: La falta de contrato de evaluación o de evidencia verificable MUST impedir la aprobación y MUST NOT suplirse con una afirmación manual de «apto».
- **FR-022b**: Mientras no exista el contrato de evaluación, el sistema MUST permitir presentar, consultar, pasar a revisión y rechazar movimientos, y MUST impedir únicamente aprobarlos y, por tanto, aplicarlos; las novedades (permiso, licencia, vacaciones, ausencia) MUST funcionar sin depender de ese contrato.
- **FR-023**: Un movimiento aprobado con fecha efectiva futura MUST quedar programado y MUST NOT aplicarse antes de esa fecha; presentar o aprobar MUST NOT modificar la posición, el vínculo ni el estado laboral.
- **FR-023b**: Llegada la fecha efectiva de un movimiento programado, el sistema MUST aplicarlo automáticamente mediante una operación controlada, sin depender de una acción del Administrador, y MUST actuar con identidad de sistema registrada como aplicador; si el proceso automático se retrasa, el movimiento MUST seguir figurando programado y NO aplicado, y al ejecutarse MUST verificar las condiciones del momento.
- **FR-023c**: El Administrador MUST disponer de una acción manual para reintentar un movimiento con incidencia de aplicación; el reintento MUST usar la misma verificación y MUST ser idempotente frente a la aplicación automática y a otros reintentos.
- **FR-024**: Al aplicar, el sistema MUST verificar de nuevo la posición destino, el vínculo y la posibilidad de asignación, y MUST NOT crear posiciones ni alterar la jerarquía; si algo lo impide MUST registrar una incidencia de aplicación sin asignación parcial.
- **FR-025**: El sistema MUST NOT ocupar posiciones inexistentes, suprimidas u ocupadas de manera incompatible, ni crear simultaneidad de vínculos o dos posiciones vigentes para la misma persona en una fecha.

**Estados y resolución**

- **FR-026**: Las solicitudes MUST recorrer los estados Presentada, En revisión, Aprobada y Rechazada, más Retirada por el solicitante en las novedades; los movimientos MUST distinguir además Programada, Aplicada e Incidencia de aplicación; las ausencias MUST distinguir Informada y Revisada o justificada sin equipararlas con permisos o licencias.
- **FR-027**: Resolver MUST exigir permisos vigentes, estado resoluble, evidencia necesaria y verificación de condiciones actuales, y MUST registrar identidad del decisor, momento, sentido y justificación.
- **FR-028b**: El solicitante de una novedad (el Colaborador, o el Administrador que la registró en su nombre) MUST poder retirarla mientras esté Presentada o En revisión; el retiro MUST registrar quién y cuándo, dejar la solicitud no resoluble y conservarla, MUST NOT permitirse una vez resuelta y MUST resolverse de forma coherente ante un retiro y una resolución concurrentes.
- **FR-028**: Una aprobación, un rechazo o un movimiento aplicado MUST NOT sobrescribirse ni eliminarse; cualquier corrección MUST requerir una operación auditada definida por separado.
- **FR-029**: Las operaciones que fallen por error, conflicto o dependencia MUST NOT dejar modificaciones parciales en el vínculo, la asignación o la solicitud; las confirmaciones repetidas MUST NOT duplicar hechos y las operaciones concurrentes sobre la misma actuación o posición MUST resolverse de forma coherente.
- **FR-030**: El sistema MUST tratar la información faltante como requisito no cumplido, nunca como cumplido.

**Permisos y aislamiento**

- **FR-031**: Toda autorización MUST verificarse en el servidor y circunscribirse al tenant autenticado; un registro de otro tenant, ajeno o inexistente MUST responder de forma indistinguible.
- **FR-032**: El Administrador MUST poder consultar los movimientos y novedades de su tenant, presentar propuestas de ascenso o traslado, aprobar o rechazar, aplicar mediante la operación controlada y registrar o revisar ausencias; el Colaborador MUST poder presentar sus novedades habilitadas y consultar solo sus propias solicitudes y los resultados comunicables.
- **FR-033**: Solo existen los roles Administrador de Talento Humano y Colaborador; Líder, Gerencia, Candidato y Administrador técnico del tenant MUST NOT actuar como actores operativos.
- **FR-034**: Quien registra una solicitud MUST NOT recibir por ello permisos de resolución; la intervención como registrador y como resolutor MUST conservarse por separado.
- **FR-035**: La posesión del rol Colaborador MUST NOT permitir leer ni modificar solicitudes de terceros, y el acceso a la ficha MUST NOT conceder acceso a respaldos documentales, que tienen permisos independientes.

**Consulta y seguimiento**

- **FR-036**: El listado del Administrador MUST ofrecer filtros por tipo, estado, colaborador y período, y diferenciar solicitudes pendientes, resueltas, movimientos programados, movimientos aplicados e incidencias; «sin registros» MUST NOT confundirse con un error de carga ni viceversa.
- **FR-037**: El Colaborador MUST acceder exclusivamente a sus propias solicitudes de novedad y a sus movimientos comunicables, sin directorio general ni filtros que revelen otras fichas. Un movimiento propio MUST ser visible para el Colaborador solo cuando esté aprobado, programado o aplicado, y MUST mostrarle únicamente el resultado y la fecha efectiva; las propuestas, evaluaciones, revisiones, rechazos, deliberaciones e incidencias de aplicación MUST NOT mostrársele. Un movimiento aprobado con incidencia de aplicación se le muestra como aprobado y pendiente de aplicar, sin revelar la incidencia y sin comunicarlo nunca como aplicado.
- **FR-038**: El detalle de un movimiento MUST mostrar situación actual, posición destino, fecha efectiva, justificación, requisitos, evaluación del puesto destino, versión de política, respaldos autorizados y resoluciones; antes de aprobar MUST presentar el resumen de comprobaciones y advertir que aprobar puede no equivaler a aplicar.
- **FR-039**: La interfaz MUST NOT mostrar controles de aprobación cuando faltan permisos, evaluación obligatoria o información esencial, y el servidor MUST volver a verificar las mismas reglas.
- **FR-040**: Los errores de concurrencia MUST conservar la información recuperable del formulario y MUST NOT reintentar cambios obsoletos automáticamente.

**Auditoría e historial**

- **FR-041**: Cada actuación MUST registrar quién la presentó, en nombre de quién, tenant, momento, tipo, versión de política y parámetros aplicados; las revisiones, aprobaciones, rechazos, incidencias y aplicaciones MUST registrar actor real, fecha y resultado.
- **FR-042**: Al cambiar de estado, la evidencia anterior MUST conservarse para explicar la decisión sin reconstruirla desde la configuración actual; los registros técnicos MUST NOT duplicar documentos completos, información clínica ni datos innecesarios.
- **FR-043**: Los hechos laborales efectivamente aplicados MUST alimentar el Historial Laboral con referencia a la solicitud y a la política utilizada; las solicitudes, evaluaciones y resoluciones MUST conservarse como antecedentes administrativos relacionados, sin eliminar solicitudes rechazadas.
- **FR-044**: Un cambio de estado MUST NOT conceder por sí mismo nuevos permisos; notificaciones, exportaciones y accesos a respaldos MUST obedecer sus permisos propios.

### Key Entities *(include if feature involves data)*

- **Solicitud de novedad**: permiso, licencia o vacaciones presentada por el Colaborador o por el Administrador en su nombre; lleva período, motivo, respaldos, versión de política y parámetros aplicados, estado y resolución.
- **Ausencia informada**: hecho de inasistencia ya ocurrido, con fecha o período y motivo o respaldo; se revisa o justifica sin equivaler a una autorización.
- **Solicitud de movimiento**: ascenso o traslado propuesto por el Administrador; lleva posición actual, posición destino, fecha efectiva, justificación, versión de política, referencia a la evaluación y estado.
- **Evaluación frente al puesto destino**: evidencia verificable aportada por otro módulo; identifica colaborador, puesto, requisitos considerados, momento y resultado; es insumo de la decisión, no la decisión.
- **Retiro de solicitud**: acto del solicitante que da por terminada una novedad aún no resuelta, con quién y cuándo; no equivale a rechazo ni se elimina.
- **Resolución**: decisión de aprobar o rechazar con decisor, momento, sentido y justificación; no se sobrescribe.
- **Aplicación de movimiento / Incidencia de aplicación**: efecto controlado de un movimiento aprobado, ejecutado automáticamente por el sistema en su fecha efectiva (o reintentado por el Administrador), o el registro del impedimento actual que lo detuvo, sin cambios parciales.
- **Condiciones aplicadas**: copia de la versión de política y de los parámetros con los que se presentó cada solicitud.
- **Registro de auditoría**: actuaciones, revisiones, resoluciones, incidencias y aplicaciones con actor real, momento y resultado.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un Colaborador presenta una solicitud de permiso completa en menos de 3 minutos, viendo antes las condiciones que debe cumplir.
- **SC-002**: El 100 % de las solicitudes presentadas conserva la versión de política y los parámetros con los que se presentó, tras cualquier número de publicaciones posteriores.
- **SC-003**: Ninguna solicitud se confirma cuando la política no existe, no admite solicitudes o no pudo consultarse, y en cada caso el mensaje distingue la causa.
- **SC-004**: Ningún ascenso o traslado se aprueba ni se aplica sin una evaluación válida frente al puesto destino, y ninguno modifica la asignación antes de su fecha efectiva.
- **SC-005**: En el 100 % de los movimientos aplicados queda la posición anterior y la nueva, la fecha efectiva, quién resolvió, por qué y con qué política, consultables desde un solo lugar.
- **SC-006**: Ninguna aplicación fallida deja un colaborador con dos posiciones vigentes, un vínculo duplicado ni una asignación parcial, incluso ante reintentos y operaciones concurrentes.
- **SC-007**: Ningún Colaborador ni usuario de otra organización puede ver o modificar solicitudes ajenas, y la respuesta ante un registro ajeno es indistinguible de la de uno inexistente.
- **SC-008**: Una persona del área de Talento Humano identifica en menos de 1 minuto cuántas solicitudes están pendientes, cuántos movimientos programados hay y cuáles presentan incidencias.
- **SC-009**: Cada aprobación, rechazo, incidencia y aplicación conserva decisor, momento y justificación consultables, y ninguna puede editarse ni eliminarse.

## Assumptions

- La aplicación automática no es una decisión: solo aplica lo que ya fue aprobado por un Administrador; la aprobación sigue siendo humana y registrada.
- La configuración de políticas (feature 016) ya está instalada y es la única fuente de las versiones publicadas; esta funcionalidad solo las consulta mediante su contrato público y conserva la referencia y la copia de los parámetros.
- Los puestos destino, los niveles y la ocupación permitida de las posiciones los proporciona Estructura Empresarial; Gestión de Personal los consulta y verifica sin mantener un catálogo alternativo.
- Los ingresos y reingresos siguen siendo competencia de GP-02; esta funcionalidad gestiona cambios posteriores y no modifica sus reglas.
- Los respaldos documentales los conserva Gestión Documental con permisos independientes; aquí solo se referencian.
- El Historial Laboral recibe los hechos aplicados y las referencias; esta funcionalidad no implementa ni edita su línea de tiempo ni adopta event sourcing.
- Se trabaja con los dos roles definidos; no existen aprobadores intermedios, líderes ni gerencias.
- La zona horaria de la organización ya existe como dato del tenant y rige la anticipación y las fechas de presentación.
- **Dependencias abiertas que no autorizan reglas implícitas.** Los casos que dependan de ellas no se consideran aprobados hasta que existan sus decisiones:
  - Contrato y titularidad de la evaluación frente al puesto destino (Selección u otro módulo responsable); sin él la aprobación de ascensos y traslados permanece impedida, aunque se presentan, consultan, revisan y rechazan desde el inicio (FR-022b).
  - Reglas de fechas y superposición de novedades; mientras no existan, el cruce de períodos se señala y debe reconocerse al aprobar (FR-018b), sin convertirse en bloqueo.
  - Tratamiento de saldos de vacaciones; no se calculan ni se muestran como verificados.
  - Mecanismo de recuperación y corrección de movimientos ya efectivos.
  - Una eventual comunicación formal de movimientos aún no aprobados, o de rechazos, queda fuera de esta entrega: el Colaborador no los ve en el sistema (FR-037).
- El historial de políticas y la gestión de borradores pertenecen a la feature 016 y no se reproducen aquí.

## Pantallas *(si la feature tiene UI)*

### Listado de movimientos y novedades
- **Propósito**: seguimiento de solicitudes y registros de la organización (Administrador) o de las propias (Colaborador).
- **Datos que muestra**: tipo, colaborador, fecha de presentación, responsable, fecha o período solicitado y estado distinguible; separaciones entre pendientes, resueltas, programados, aplicados e incidencias.
- **Acciones disponibles** (y quién puede hacerlas): filtrar por tipo, estado, colaborador y período y abrir el detalle (Administrador); consultar las propias (Colaborador).
- **Estados**: vacío («sin registros»), cargando, error con Reintentar, sin permisos.
- **Mockup**: patrón Listado de design/DESIGN.md con paginación y filtros en la URL; columnas propias: tipo, colaborador, presentación, período o fecha efectiva, estado.

### Formulario de novedad (permiso, licencia, vacaciones)
- **Propósito**: presentar una solicitud con las condiciones de la política aplicable a la vista.
- **Datos que muestra**: campos del tipo elegido, requisitos de la política (justificación, documento, anticipación) y la versión aplicada.
- **Acciones disponibles** (y quién puede hacerlas): presentar con confirmación (Colaborador para sí mismo; Administrador en nombre del colaborador).
- **Estados**: validación por campo, política sin configurar, no admite solicitudes, falla temporal al consultarla, conflicto o duplicado.
- **Mockup**: patrón Formulario con el componente de condiciones aplicables de la feature 016.

### Registro de ausencia
- **Propósito**: informar un hecho ocurrido, no solicitar autorización.
- **Datos que muestra**: fecha o período, motivo o respaldo y su estado (informada, revisada o justificada).
- **Acciones disponibles** (y quién puede hacerlas): registrar y revisar o justificar (Administrador); consultar las propias autorizadas (Colaborador).
- **Estados**: validación, vacío, error, sin permisos.
- **Mockup**: patrón Formulario con aviso visible de que se informa un hecho ocurrido.

### Propuesta y detalle de ascenso o traslado
- **Propósito**: proponer, comprobar condiciones, resolver y seguir el movimiento hasta su aplicación.
- **Datos que muestra**: situación actual, posición destino, fecha efectiva, justificación, requisitos, evaluación del puesto destino (válida, ausente o no verificable, o condición estructural incumplida), versión de política, respaldos autorizados, resoluciones, estado (programado, aplicado, incidencia).
- **Acciones disponibles** (y quién puede hacerlas): proponer, pasar a revisión, aprobar o rechazar con justificación y reintentar manualmente una incidencia de aplicación (Administrador); la aplicación en la fecha efectiva la realiza el sistema; ver solo lo comunicable (Colaborador).
- **Estados**: sin evaluación, dependencia no disponible, destino ocupado, incidencia, conflicto de concurrencia, éxito.
- **Mockup**: patrón Detalle con resumen de comprobaciones antes de aprobar y diálogo de confirmación con justificación.

### Mis solicitudes (Colaborador)
- **Propósito**: consultar el estado y la resolución de las propias solicitudes.
- **Datos que muestra**: tipo, fecha o período, estado, requisitos comunicables y resolución cuando proceda.
- **Acciones disponibles** (y quién puede hacerlas): consultar y presentar una nueva novedad (Colaborador).
- **Estados**: vacío, cargando, error con Reintentar, sin ficha asociada.
- **Mockup**: patrón Listado simplificado, sin filtros que revelen otras fichas.
