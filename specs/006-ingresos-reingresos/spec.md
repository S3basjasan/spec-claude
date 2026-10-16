# Feature Specification: Registro de ingresos y reingresos laborales (GP-02)

**Feature Branch**: `006-ingresos-reingresos`

**Created**: 2026-10-15

**Status**: Draft

**Input**: User description: "GP-02: Registro de ingresos y reingresos laborales. El Administrador de Talento Humano registra el inicio de una relación laboral de una ficha de colaborador (GP-01) y su asignación inicial a una posición de Estructura Organizacional: personal existente con fecha de ingreso pasada, ingresos actuales, ingresos programados y reingresos que conservan la ficha y agregan un vínculo nuevo. Son obligatorios la ficha, la fecha efectiva de ingreso (pasada, actual o futura según la fecha de calendario del tenant), el tipo de contrato habilitado por el sistema y una posición que cumpla las condiciones de asignación; el puesto, la unidad y la jefatura se derivan de la posición. No se permiten vínculos superpuestos de una misma ficha (incluidos los programados) ni dos ocupantes en una posición para la misma fecha. Vínculo y asignación inicial se confirman como una sola operación, sin registros parciales, y repetir la confirmación o confirmar dos ingresos incompatibles a la vez deja como máximo un resultado válido. El estado laboral (Activo, Desvinculado, Sin vínculo vigente) se deriva de los vínculos y nunca se edita a mano. No corrige, cancela ni reprograma ingresos confirmados, no ejecuta movimientos, no gestiona salidas, no calcula antigüedad, no crea cuentas ni modifica puestos."

## Clarifications

### Session 2026-10-15

- Q: ¿Cómo debe saber el resto del sistema que la posición quedó ocupada, para que no se pueda desactivar su puesto ni su unidad? → A: La ocupación se deriva de las asignaciones por fecha y es la única fuente; GP-02 no escribe el estado de la posición, y 004 (puestos) y 005 (unidades) cuentan también las asignaciones vigentes o futuras al decidir una desactivación.

- Q: ¿Con qué base de datos debe garantizarse que nunca haya dos ocupantes en una posición ni vínculos superpuestos, incluso con confirmaciones simultáneas? → A: Se mantiene SQLite; la no superposición se garantiza en la aplicación dentro de una transacción de escritura única y se demuestra con una prueba de confirmaciones simultáneas reales; no se enmienda la constitución.

- Q: ¿Qué lista de tipos de contrato debe ofrecer el formulario de ingreso? → A: Lista fija del producto con 4 opciones genéricas: Indefinido, Plazo fijo, Temporal y Servicios profesionales; se guarda una clave estable por opción y el catálogo solo cambia con una versión del producto.

- Q: ¿Debe GP-02 incluir una forma mínima de crear posiciones, o se cargan como datos iniciales hasta que exista una funcionalidad de posiciones? → A: GP-02 no crea ni edita posiciones; asigna solo a las existentes (sembradas como datos iniciales) y la gestión de posiciones queda para una funcionalidad futura.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registrar un ingreso actual o con fecha pasada (Priority: P1)

El Administrador de Talento Humano abre la ficha de un colaborador que aún no tiene vínculo, pulsa Registrar ingreso, elige la fecha efectiva de ingreso (hoy o una fecha pasada, para personal que ya trabaja en la organización), el tipo de contrato y una posición disponible, y confirma. El sistema crea el vínculo laboral y la asignación inicial como una sola operación y muestra la ficha con su estado laboral resultante, su puesto y su unidad.

**Why this priority**: Sin vínculos y asignaciones, la ficha (GP-01) no tiene situación laboral y el resto del módulo (movimientos, salidas, desarrollo) no tiene sobre qué operar. También permite cargar al personal existente.

**Independent Test**: Con una ficha sin vínculo y una posición libre, registrar un ingreso con fecha de hoy y comprobar que la ficha queda Activo con el puesto y la unidad de la posición; repetir con otra ficha y una fecha pasada y comprobar que se conserva esa fecha como efectiva y que el momento del registro es el actual.

**Acceptance Scenarios**:

1. **Given** una ficha sin vínculo incompatible y una posición libre, **When** el Administrador registra un ingreso con fecha de hoy, **Then** se crean el vínculo y la asignación inicial, la ficha queda Activo y su detalle muestra el puesto y la unidad de la posición. (GP02-CA01)
2. **Given** una ficha apta, **When** se registra una fecha de ingreso pasada válida, **Then** se conserva esa fecha como fecha efectiva y el momento actual como momento del registro, junto con la persona que lo registró. (GP02-CA02)
3. **Given** una ficha sin cuenta de usuario, **When** se confirma el ingreso, **Then** se registra el vínculo y no se crea ninguna cuenta ni se conceden permisos. (GP02-CA08)
4. **Given** la pantalla Registrar ingreso, **When** se abre, **Then** muestra la fecha, los tipos de contrato habilitados y las posiciones disponibles para la fecha elegida, y al elegir una posición muestra, en solo lectura, su puesto, su unidad y su jefatura.
5. **Given** el formulario, **When** falta la fecha, el tipo de contrato o la posición, **Then** se muestran errores junto a cada campo y no se crea nada.
6. **Given** el formulario, **When** el Administrador cancela antes de confirmar, **Then** no se crea ningún hecho laboral.
7. **Given** una persona con rol Colaborador, **When** intenta registrar un ingreso (por la interfaz o directamente), **Then** se le deniega por falta de permisos y no aparece la acción Registrar ingreso.
8. **Given** una ficha de otro tenant o inexistente, **When** se intenta registrar un ingreso, **Then** la respuesta es la misma que para una ficha inexistente y no se crea nada.

---

### User Story 2 - Programar un ingreso futuro y ver el estado derivado (Priority: P1)

El Administrador registra el primer ingreso de una persona con una fecha futura. Hasta esa fecha, la ficha sigue mostrando Sin vínculo vigente y el ingreso aparece como programado; al llegar la fecha, la ficha muestra Activo sin que nadie edite nada.

**Why this priority**: Es el caso habitual de contrataciones confirmadas con inicio posterior; el estado debe ser siempre coherente con la fecha, sin tareas manuales.

**Independent Test**: Registrar un ingreso con fecha de mañana, consultar la ficha hoy (Sin vínculo vigente, ingreso programado) y comprobar que, con la fecha del tenant en el día del ingreso, la misma ficha se muestra Activo sin ninguna acción.

**Acceptance Scenarios**:

1. **Given** un primer ingreso futuro válido, **When** se consulta la ficha antes de su fecha, **Then** se muestra Sin vínculo vigente y el ingreso se indica como programado, con su fecha, posición, puesto y unidad. (GP02-CA03)
2. **Given** ese ingreso programado, **When** la fecha del tenant alcanza la fecha de ingreso, **Then** la ficha se muestra Activo sin edición manual. (GP02-CA03)
3. **Given** un ingreso futuro registrado, **When** se consulta la trayectoria, **Then** el hecho aparece identificado como programado y no como hecho efectivo.
4. **Given** una ficha Activo, **When** se consulta la ficha, **Then** el estado se obtiene siempre de los vínculos y no existe ninguna acción para cambiarlo a mano.
5. **Given** el listado de colaboradores (GP-01), **When** se filtra por estado, **Then** el filtro ofrece Activo y Desvinculado además de Sin vínculo vigente cuando alguna ficha del tenant tiene ese estado.

---

### User Story 3 - Registrar un reingreso conservando la historia (Priority: P1)

El Administrador registra un nuevo ingreso para una persona que ya tuvo un vínculo que terminó (por una salida efectiva registrada por el proceso de salidas). La ficha, el código y los vínculos anteriores se conservan; se agrega un vínculo nuevo con su propia asignación.

**Why this priority**: Evita duplicar fichas y conserva la historia laboral completa de la persona.

**Independent Test**: Con una ficha desvinculada y una posición libre desde la fecha de su salida, registrar un reingreso y comprobar que aparece un vínculo nuevo, que la ficha conserva su código y el vínculo anterior, y que no se creó ni reactivó ninguna cuenta.

**Acceptance Scenarios**:

1. **Given** una ficha desvinculada y una posición libre desde su salida, **When** se registra un reingreso compatible, **Then** aparece un vínculo nuevo y se conservan la ficha y la historia anterior. (GP02-CA06)
2. **Given** una ficha Desvinculado con un reingreso futuro registrado, **When** se consulta antes de la fecha del reingreso, **Then** el estado sigue siendo Desvinculado y el reingreso se indica como programado.
3. **Given** un reingreso, **When** se confirma, **Then** no se reactiva automáticamente ninguna cuenta ni se transfieren permisos. (GP02-RN09)
4. **Given** una ficha con un vínculo previo todavía abierto (sin salida), **When** se intenta registrar un ingreso con una fecha posterior al inicio de ese vínculo, **Then** se rechaza: el sistema no inventa ni modifica la salida del vínculo anterior.
5. **Given** un vínculo anterior que terminó en una fecha, **When** se registra un reingreso con fecha igual a la fecha de salida, **Then** se acepta, porque el intervalo del vínculo anterior excluye su fecha de salida.

---

### User Story 4 - Rechazar ingresos incompatibles y resistir reintentos y concurrencia (Priority: P1)

Cuando la posición está ocupada o se ocupará durante el periodo pedido, o el nuevo vínculo se superpone con otro de la misma ficha, el sistema rechaza el ingreso sin dejar nada a medias y explica el motivo conservando el formulario. Repetir una confirmación no duplica el ingreso, y dos confirmaciones incompatibles simultáneas no pueden prosperar ambas.

**Why this priority**: La integridad de quién ocupa qué posición y cuándo es la garantía central de la funcionalidad; un error aquí corrompe la historia laboral y la estructura.

**Independent Test**: Intentar ingresar a una persona en una posición ocupada en el periodo, comprobar el rechazo sin vínculo ni asignación; confirmar dos ingresos a la misma posición a la vez y comprobar que solo uno se registra; repetir una confirmación y comprobar que no se crea otro vínculo.

**Acceptance Scenarios**:

1. **Given** una posición ocupada durante el periodo solicitado, **When** se confirma otro ingreso, **Then** se rechaza indicando que la posición no está disponible, sin crear vínculo ni asignación parcial. (GP02-CA04)
2. **Given** dos ingresos incompatibles a la misma posición, **When** se confirman simultáneamente, **Then** solo uno se registra y el otro informa indisponibilidad. (GP02-CA05)
3. **Given** un vínculo actual o futuro de la ficha, **When** otro vínculo se superpone con él, **Then** se rechaza aunque se haya elegido otra posición. (GP02-CA07)
4. **Given** un ingreso rechazado, **When** el sistema responde, **Then** la ficha y sus relaciones laborales permanecen sin cambios y el formulario conserva lo escrito.
5. **Given** intervalos que solo se tocan en una fecha (el vínculo o la ocupación anterior termina el día en que empieza el nuevo), **When** se confirma, **Then** se acepta: el inicio se incluye y el fin se excluye.
6. **Given** una confirmación ya completada, **When** se repite con la misma clave de operación y los mismos datos (doble clic, reenvío tras un corte), **Then** no se crea otro vínculo y se devuelve el resultado de la primera.
7. **Given** una clave de operación ya usada, **When** se reutiliza con otros datos, **Then** se rechaza sin crear nada.
8. **Given** que no puede verificarse la posición con Estructura, **When** se confirma, **Then** el ingreso no se confirma y se informa que no se pudo verificar, con la opción de reintentar.
9. **Given** una condición que cambia entre elegir la posición y confirmar (la posición se ocupa, se suprime o su puesto se desactiva), **When** se confirma, **Then** se rechaza el conjunto completo sin registros parciales.
10. **Given** un cambio de fecha en el formulario, **When** la posición elegida deja de estar disponible para la nueva fecha, **Then** se vuelven a cargar las opciones, se descarta la selección incompatible y se avisa a la persona.

---

### User Story 5 - Consultar la situación laboral y la trayectoria (Priority: P2)

El Administrador y, en su propia ficha, el Colaborador consultan la situación laboral vigente (estado, puesto, unidad, jefatura, vínculo vigente y próximo ingreso programado) y la trayectoria con los hechos de ingreso y reingreso, de la más reciente a la más antigua.

**Why this priority**: Hace visible el resultado del registro y completa las secciones que GP-01 reservaba; es valioso, pero el registro (P1) funciona sin ella adicional.

**Independent Test**: Registrar un ingreso y abrir la ficha como Administrador y como el Colaborador asociado; comprobar que ambos ven el estado, el puesto, la unidad y el hecho en la trayectoria, y que el Colaborador no accede a la de otra persona.

**Acceptance Scenarios**:

1. **Given** una ficha con un vínculo vigente, **When** el Administrador consulta su situación laboral, **Then** ve el estado Activo, el puesto, la unidad, la jefatura y el vínculo vigente con su fecha de ingreso y tipo de contrato.
2. **Given** una ficha con ingresos pasado y futuro, **When** se consulta la trayectoria, **Then** cada hecho muestra fecha efectiva, tipo (ingreso o reingreso), tipo de contrato y posición, ordenados del más reciente al más antiguo, y el futuro marcado como programado.
3. **Given** un Colaborador con su cuenta asociada a su ficha y permiso de consulta, **When** consulta su situación y trayectoria, **Then** ve solo las propias y no puede pedir las de otra persona.
4. **Given** que Estructura no responde, **When** se consulta la situación o la trayectoria, **Then** el estado y las fechas se muestran, y el puesto y la unidad indican «Información no disponible» con Reintentar, sin inventar valores.
5. **Given** un puesto cuyo nombre cambió en Estructura después del ingreso, **When** se consulta la trayectoria, **Then** muestra el nombre vigente del puesto sin presentarlo como una definición histórica congelada.
6. **Given** una cuenta deshabilitada, **When** se consulta el estado laboral, **Then** no cambia por ello.

---

### Edge Cases

- Dos fichas distintas ingresan a posiciones distintas del mismo puesto: ambas se aceptan; compartir puesto no es compartir posición.
- Una posición con un ocupante que sale en una fecha futura: otro ingreso a esa posición se acepta si empieza en o después de esa fecha de salida.
- Una posición con un ocupante futuro ya programado: un ingreso cuyo periodo abierto lo alcanzaría se rechaza, aunque hoy esté libre.
- La fecha de ingreso coincide con la fecha de salida de otro vínculo de la misma ficha: se acepta (intervalos contiguos).
- Una ficha con un ingreso futuro programado intenta otro ingreso: se rechaza por superposición (no hay simultaneidad).
- Un ingreso con fecha anterior al ingreso de un vínculo que ya terminó, solapando su periodo: se rechaza; la historia no se reescribe.
- Un puesto o una unidad con posiciones cuyo estado guardado es `Vacante` pero con una asignación vigente o futura: no pueden desactivarse (FR-016b).
- Posiciones sembradas por 004 como `Ocupada` sin asignaciones registradas: para GP-02 están libres (la ocupación solo sale de las asignaciones) pero siguen bloqueando la desactivación por su estado guardado, hasta que otra funcionalidad lo corrija.
- La posición se suprime o su puesto se desactiva después de un ingreso confirmado: la asignación existente se conserva; la restricción solo aplica a nuevas asignaciones.
- La unidad de la posición está inactiva: la posición no es elegible para nuevas asignaciones.
- Cambia el reloj del tenant (cruce de medianoche en su zona horaria) entre consultas: el estado refleja la fecha de calendario del tenant, sin tarea manual.
- La ficha de otro tenant o una posición de otro tenant se envía por identificador: se rechaza como inexistente sin revelar su existencia.
- Fecha con formato inválido o fuera de un rango razonable: se rechaza con error por campo.
- Un intento de enviar fecha de salida, estado o salario en el alta: se rechaza; el alta no los admite.
- La ficha no existe en el momento de confirmar (error de red tras abrir el formulario): se informa que no existe.
- El vínculo y la asignación de un ingreso recién confirmado no tienen botón de editar, cancelar ni reprogramar.

## Requirements *(mandatory)*

### Functional Requirements

**Autorización y aislamiento**

- **FR-001**: Solo el Administrador de Talento Humano MUST poder registrar ingresos y reingresos y consultar las opciones del formulario; el Colaborador MUST NOT registrar ni modificar ingresos, y la acción MUST NOT mostrarse para él. La comprobación MUST hacerse en el servidor en cada operación (RC-03).
- **FR-002**: Toda operación MUST limitarse al tenant de la sesión; una ficha o posición de otro tenant MUST responder igual que una inexistente y MUST NOT crear ni revelar nada (RC-01).
- **FR-003**: El Colaborador MUST poder consultar solo su propia situación laboral y trayectoria, y solo con asociación válida cuenta-ficha y permiso de consulta de ficha; MUST NOT acceder a las de otras personas (RC-04).

**Datos del ingreso**

- **FR-004**: Un ingreso MUST requerir ficha existente en el tenant, fecha efectiva de ingreso, tipo de contrato habilitado y posición elegible; sin cualquiera de ellos MUST rechazarse con error por campo y sin crear nada.
- **FR-005**: La fecha efectiva MUST poder ser pasada, actual o futura, y se evalúa como fecha de calendario en la zona horaria del tenant (GP02-RN01).
- **FR-006**: El tipo de contrato MUST elegirse entre las opciones habilitadas por el sistema; el sistema MUST NOT permitir texto libre ni opciones definidas por el tenant en este incremento. La lista inicial es fija y contiene exactamente: **Indefinido**, **Plazo fijo**, **Temporal** y **Servicios profesionales**; cada opción tiene una clave estable que es lo que se conserva en el vínculo, y la lista no pretende ser un catálogo legal.
- **FR-007**: El puesto, la unidad organizacional y la jefatura MUST obtenerse de la posición elegida (consultándola a Estructura) y MUST NOT copiarse a la ficha ni editarse en el formulario.
- **FR-008**: El sistema MUST conservar, como datos del negocio de cada ingreso, el momento del registro y la persona que lo registró, sin exigir una funcionalidad de auditoría separada.
- **FR-009**: El alta MUST NOT admitir fecha de salida, estado laboral ni salario; un intento de enviarlos MUST rechazarse.

**Reglas temporales y de ocupación**

- **FR-010**: Una ficha MUST NOT tener dos vínculos superpuestos, incluidos los programados; no se permite simultaneidad en este incremento. El rechazo MUST aplicar aunque se elija otra posición (GP02-RN02).
- **FR-011**: Una posición MUST tener como máximo un ocupante por fecha; el sistema MUST rechazar un ingreso cuya ocupación se superponga con asignaciones existentes o futuras de esa posición. Compartir puesto MUST requerir posiciones distintas (GP02-RN03).
- **FR-012**: Los periodos MUST tratarse con inicio incluido y fin excluido: un vínculo o una ocupación que termina en una fecha MUST permitir que otra empiece en esa misma fecha, y un ingreso y una salida en el mismo día MUST NOT producir ocupación efectiva (GP02-RN05).
- **FR-013**: El ingreso MUST producir efectos desde su fecha efectiva; el nuevo periodo nace abierto (sin fecha de fin) y MUST considerarse para las comprobaciones de superposición hasta que una salida registrada lo delimite.
- **FR-014**: El sistema MUST NOT permitir ingresar antes de la salida de un vínculo previo abierto y MUST NOT inventar ni modificar esa salida; la salida la registra la funcionalidad de salidas (GP02-RN06).
- **FR-015**: La asignación inicial MUST iniciar en la fecha de ingreso, pertenecer al vínculo y estar contenida en él.

**Condiciones de la posición**

- **FR-016**: La posición MUST pertenecer al tenant y cumplir las condiciones de asignación de Estructura: no suprimida, con puesto activo y con unidad activa. Su disponibilidad MUST comprobarse nuevamente al confirmar (GP02-RN04).
- **FR-016a**: La ocupación de una posición MUST derivarse exclusivamente de las asignaciones por fecha; GP-02 MUST NOT escribir ni depender del estado guardado de la posición (`Ocupada`, `Vacante`, `Suprimida`) para decidir si hay ocupante. Una posición con asignación vigente o futura MUST considerarse en uso aunque su estado guardado diga otra cosa.
- **FR-016b**: Desactivar un puesto (catálogo de puestos, 004) o una unidad organizacional (005) MUST rechazarse también cuando alguna de sus posiciones tiene una asignación vigente o futura, además de las reglas que ya aplican; las asignaciones terminadas efectivamente MUST NOT impedirlo. Esas funcionalidades MUST consultar esa ocupación por un contrato público de solo lectura.
- **FR-017**: Si no puede verificarse la posición con Estructura al confirmar, el ingreso MUST NOT confirmarse y MUST informarse con opción de reintentar.
- **FR-018**: El selector de opciones MUST ofrecer solo posiciones elegibles para la fecha elegida, con búsqueda y paginación, y MUST NOT reservar la posición: la confirmación revalida.

**Atomicidad, reintentos y concurrencia**

- **FR-019**: El vínculo y la asignación inicial MUST confirmarse como una sola operación; si una condición cambia mientras se guarda, el conjunto MUST rechazarse sin dejar registros parciales (GP02-RN07).
- **FR-020**: Repetir la confirmación de una misma operación MUST NOT crear otro vínculo y MUST devolver el resultado de la primera; reutilizar la clave con otros datos MUST rechazarse (GP02-RN08).
- **FR-021**: Dos ingresos incompatibles confirmados a la vez (misma posición con periodos superpuestos, o misma ficha con vínculos superpuestos) MUST NOT prosperar ambos: uno MUST registrarse y el otro MUST informar indisponibilidad o superposición (GP02-RN08).
- **FR-022**: Un rechazo MUST dejar la ficha, sus vínculos y las asignaciones sin cambios, y MUST explicar el motivo (posición no disponible, vínculo superpuesto, contrato no habilitado, información no verificable).

**Estado laboral derivado**

- **FR-023**: El estado laboral MUST derivarse siempre de los vínculos para la fecha de calendario del tenant, sin edición manual ni tareas programadas: **Activo** si existe un vínculo iniciado y no terminado efectivamente; **Desvinculado** si no hay vínculo vigente y existe una salida efectiva anterior (un reingreso futuro no lo cambia); **Sin vínculo vigente** si no hay vínculo vigente ni salida anterior (un primer ingreso futuro se indica como programado).
- **FR-024**: La deshabilitación o el estado de una cuenta MUST NOT alterar el estado laboral (RC-11).
- **FR-025**: El ingreso futuro MUST presentarse por separado como «programado», con su fecha, posición, puesto y unidad, mientras no esté vigente.
- **FR-026**: GP-01 MUST mostrar, en el listado y el detalle, el estado, el puesto y la unidad derivados de estos vínculos en lugar de «Sin vínculo vigente» y «No registrado», y MUST ofrecer en su filtro de estado los estados que alguna ficha tenga.

**Alcance excluido**

- **FR-027**: El sistema MUST NOT ofrecer corrección, cancelación ni reprogramación de un ingreso confirmado, ni edición directa del hecho laboral (GP02-RN10).
- **FR-028**: Un ingreso o reingreso MUST NOT crear ni reactivar cuentas, ni transferir permisos (GP02-RN09, RC-06), ni modificar puestos, ni calcular antigüedad o beneficios.
- **FR-029**: Un reingreso MUST conservar la ficha, su código y los vínculos y asignaciones anteriores; la historia MUST NOT eliminarse.

**Consulta**

- **FR-030**: El sistema MUST permitir consultar la situación laboral (estado, vínculo vigente con fecha de ingreso y tipo de contrato, puesto, unidad, jefatura y próximo ingreso) y la trayectoria (hechos de ingreso y reingreso con fecha efectiva, tipo de contrato y posición, del más reciente al más antiguo, con el futuro marcado «Programado»).
- **FR-031**: Si Estructura no responde, las lecturas MUST mostrar «Información no disponible» con Reintentar, distinta de «No registrado», sin inventar hechos ni valores; los demás datos MUST seguir visibles.
- **FR-032**: La trayectoria MUST conservar referencias y fechas; el nombre del puesto y de la unidad MUST mostrarse con el valor vigente de Estructura, sin presentarse como una definición histórica congelada.

**Experiencia y accesibilidad**

- **FR-033**: Al cambiar la fecha en el formulario, el sistema MUST volver a cargar las opciones y descartar con aviso una selección que deje de ser elegible.
- **FR-034**: Tras confirmar, la ficha, la situación laboral, el listado y la trayectoria MUST mostrar el resultado sin aplicar cambios optimistas antes de la confirmación; ante un rechazo, el formulario MUST conservar los valores.
- **FR-035**: Toda la interfaz MUST estar en español y cumplir WCAG 2.2 AA entre 320 y 1440 px de ancho, sin desplazamiento horizontal de la página.

**Fuera de alcance**: correcciones, cancelaciones y reprogramaciones de ingresos; movimientos de posición posteriores al ingreso; salidas y offboarding (el sistema solo las lee); cálculo de antigüedad o beneficios; nómina y salarios; creación, habilitación o asociación de cuentas; contratos configurables por tenant; simultaneidad de vínculos; gestión de posiciones, puestos y unidades; versionado histórico de los datos de Estructura; importación masiva.

### Key Entities *(include if feature involves data)*

- **Vínculo laboral**: relación laboral de una ficha, con fecha efectiva de ingreso, fecha de salida opcional (solo la fija el proceso de salidas), tipo de contrato, momento y autor del registro. Una ficha puede tener varios en el tiempo, sin superposición.
- **Asignación laboral**: ocupación de una posición dentro de un vínculo, con fecha de inicio y de fin opcional; la asignación inicial nace con el vínculo y su inicio coincide con el ingreso. Una posición tiene como máximo un ocupante por fecha.
- **Tipo de contrato**: opción habilitada por el sistema que califica el vínculo; no es una colección configurable por tenant.
- **Estado laboral** *(derivado)*: Activo, Desvinculado o Sin vínculo vigente, calculado de los vínculos para la fecha del tenant; nunca almacenado como dato editable.
- **Hecho laboral / trayectoria** *(derivado)*: ingreso o reingreso con fecha efectiva, tipo de contrato y posición; se muestra del más reciente al más antiguo y distingue programados.
- **Ficha de colaborador** *(externa, GP-01)*: persona sobre la que se registra el ingreso; se conserva en reingresos.
- **Posición, puesto y unidad organizacional** *(externos, Estructura)*: se consultan por su contrato público; de ellos provienen el puesto, la unidad y la jefatura mostrados.
- **Cuenta de usuario** *(externa)*: puede o no existir; el ingreso no la crea ni la modifica.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El Administrador registra un ingreso (fecha, contrato y posición) en menos de 2 minutos desde que abre la ficha.
- **SC-002**: El 100 % de los ingresos confirmados deja vínculo y asignación inicial consistentes, o ninguno de los dos; ningún rechazo deja registros parciales.
- **SC-003**: El 100 % de los intentos de superponer vínculos de una misma ficha o ocupaciones de una misma posición se rechaza, incluidos los programados y los simultáneos; ninguna posición tiene dos ocupantes para una misma fecha.
- **SC-004**: En confirmaciones simultáneas incompatibles, exactamente una prospera y la otra informa indisponibilidad en el 100 % de los casos.
- **SC-005**: Repetir una confirmación (doble clic o reenvío) nunca crea un segundo vínculo en el 100 % de los casos.
- **SC-006**: El estado laboral mostrado coincide con los vínculos para la fecha del tenant en el 100 % de las consultas, y cambia al llegar la fecha de un ingreso programado sin intervención manual.
- **SC-007**: El 100 % de los intentos del Colaborador de registrar ingresos, o de consultar la situación o trayectoria de otra persona, se deniega sin exponer datos; el 100 % de las fichas y posiciones de otro tenant se rechaza sin revelar su existencia.
- **SC-008**: Ningún ingreso crea cuentas, concede permisos ni modifica puestos, y ningún reingreso elimina o altera vínculos anteriores.
- **SC-009**: El 100 % de las lecturas con Estructura caída muestra «Información no disponible» en puesto y unidad, sin valores inventados.

## Assumptions

- Los roles son los de GP-01: Administrador de Talento Humano = Administrador y Colaborador = Usuario; no se crean roles.
- GP-02 es el responsable de los vínculos y asignaciones que GP-01 leía como «no instalado»; al implementarse, GP-01 pasa a mostrar el estado, el puesto, la unidad y la trayectoria reales y el botón Registrar ingreso aparece según FR-032 de GP-01.
- Las salidas (offboarding) aún no existen: hoy ningún vínculo termina, por lo que los reingresos solo pueden probarse con vínculos cerrados cargados como datos iniciales; GP-02 nunca fija una fecha de salida.
- No existe aún una funcionalidad de posiciones: GP-02 solo asigna a posiciones ya existentes, cargadas como datos iniciales (con puesto y unidad), y MUST NOT crearlas, editarlas ni suprimirlas.
- La jefatura se obtiene de la posición cuando Estructura la publique; si no la publica, se muestra «No registrado».
- La zona horaria del tenant es la de GP-01; las fechas son de calendario, sin hora.
- El nombre del puesto y de la unidad mostrados en la trayectoria son los vigentes en Estructura; no hay versionado histórico de Estructura en este incremento.
- Los cambios en la posición (supresión, puesto inactivo, unidad inactiva) posteriores al ingreso no alteran asignaciones ya confirmadas.
- El insumo técnico propone restricciones de exclusión y bloqueos propios de PostgreSQL; se decidió mantener SQLite (ver Clarifications): la garantía de no superposición se implementa en la aplicación dentro de una transacción de escritura única, y su verificación con confirmaciones simultáneas reales es parte de la entrega. Las pruebas de PostgreSQL del insumo no aplican.
- No se crean contratos configurables por tenant ni se infiere una colección de contratos legales; las cuatro opciones iniciales son genéricas y se amplían en una versión posterior del producto.
- Esta funcionalidad obliga a modificar el comportamiento de desactivación de puestos (004) y de unidades (005) según FR-016b; ese cambio se planifica y prueba como parte de GP-02, sin añadir nuevos estados ni pantallas a esas funcionalidades.
- Las operaciones del formulario son del Administrador de un tenant; no hay importación masiva de ingresos.

## Pantallas *(si la feature tiene UI)*

### Registrar ingreso
- **Propósito**: registrar el vínculo y la asignación inicial de una ficha.
- **Datos que muestra**: encabezado con el nombre y el código de la ficha; campos Fecha efectiva de ingreso, Tipo de contrato y Posición (selector con búsqueda y paginación); puesto, unidad y jefatura de la posición elegida en solo lectura; aviso «Ingreso programado» cuando la fecha es futura.
- **Acciones disponibles** (y quién puede hacerlas): Confirmar ingreso y Cancelar (solo Administrador).
- **Estados**: cargando opciones, sin posiciones disponibles para la fecha, errores por campo, rechazo con motivo y formulario conservado, selección descartada por cambio de fecha (aviso), información de Estructura no disponible con Reintentar, confirmando (botón bloqueado), éxito (vuelve a la ficha con aviso) y sin permisos.
- **Mockup**: patrón Formulario (`design/Formulario.dc.html`) con el prototipo `design/GP-02_Registrar-ingreso.html` como referencia; el Alert con motivo del rechazo y el selector de posición con búsqueda (Combobox) se documentan en `design/DESIGN.md`.

### Situación laboral y trayectoria (en el detalle de la ficha y en Mi ficha)
- **Propósito**: mostrar el estado derivado, la asignación vigente y los hechos de ingreso y reingreso.
- **Datos que muestra**: estado (Badge), puesto, unidad, jefatura, vínculo vigente (fecha de ingreso, tipo de contrato), próximo ingreso programado y la línea de tiempo de trayectoria.
- **Acciones disponibles** (y quién puede hacerlas): Registrar ingreso en el detalle (solo Administrador); sin acciones de edición ni de cancelación.
- **Estados**: cargando, sin hechos, «No registrado», «Información no disponible» con Reintentar y éxito.
- **Mockup**: componentes Par etiqueta-valor y Línea de tiempo ya definidos para `design/ColaboradorFicha.dc.html`; la acción Registrar ingreso se añade al encabezado del detalle.
