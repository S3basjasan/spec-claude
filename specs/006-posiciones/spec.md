# Feature Specification: Posiciones organizacionales

**Feature Branch**: `006-posiciones`

**Created**: 2026-10-15

**Status**: Draft

**Input**: User description: "Gestión de posiciones organizacionales (documento fuente `specify-posiciones.md`). Una posición es un lugar concreto de la estructura: se crea a partir de un puesto del catálogo de puestos, pertenece a una unidad organizacional, reporta a la posición de su supervisor y puede estar ocupada por un usuario o vacante. Las posiciones forman un árbol de supervisión con una única máxima autoridad, que es la única posición de la unidad raíz (la Gerencia General), y que respeta la jerarquía de unidades: una posición nunca reporta a otra de una unidad inferior ni de otra rama del árbol de unidades. Este módulo gestiona la estructura de las posiciones; la ocupación la gestiona el módulo de gestión de personal. Es la fuente única de la estructura de reporte para los demás módulos. El Administrador de Talento Humano lista (Ocupadas, Vacantes y Suprimidas), consulta, crea, edita (unidad, supervisor y modalidad) y suprime posiciones; los demás usuarios autenticados listan solo Ocupadas y Vacantes y consultan cualquiera. Cada posición tiene un código único generado por el sistema (POS-####). Las posiciones nunca se eliminan: se suprimen, y solo si están Vacantes y no tienen subordinadas vigentes. Se audita quién creó y modificó cada posición y cada cambio queda en un historial."

## Clarifications

### Session 2026-10-15

- Q: ¿Quién ve la auditoría (quién creó y quién modificó la posición) al desplegar su detalle? → A: Solo el Administrador; los demás usuarios ven el detalle sin esos datos.
- Q: ¿Qué opciones tiene el filtro de estado y cuál es la predeterminada? → A: "Vigentes" (Ocupadas y Vacantes, predeterminada), Ocupada y Vacante para todos; Suprimida y "Todas" solo para el Administrador.
- Q: ¿Cuántas posiciones muestra cada página del listado? → A: 10 por página, igual que los demás catálogos.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar las posiciones y su detalle (Priority: P1)

Cualquier persona con sesión iniciada (Administrador o Usuario) abre las posiciones y ve un listado con el código, el puesto, la unidad organizacional, a quién reporta, el ocupante, el estado y la modalidad de cada una. Busca, filtra por estado y por unidad y despliega una fila para ver su detalle completo: ocupante, supervisor, a quién supervisa, puesto, unidad y, para el Administrador, la auditoría.

**Why this priority**: Es el valor central de la feature: una fuente única y consultable de la estructura de reporte de la organización, que los demás módulos leen.

**Independent Test**: Con posiciones Ocupadas, Vacantes y Suprimidas repartidas en varias unidades, iniciar sesión como Usuario y como Administrador, buscar, filtrar, paginar y desplegar el detalle; comprobar qué ve cada rol y que el Usuario no tiene acciones de gestión.

**Acceptance Scenarios**:

1. **Given** existen posiciones Ocupadas, Vacantes y Suprimidas, **When** una persona autenticada abre las posiciones, **Then** ve por defecto solo las vigentes (Ocupadas y Vacantes), ordenadas por código ascendente y paginadas.
2. **Given** el listado visible, **When** se muestra cada fila, **Then** presenta código, puesto, unidad organizacional, "reporta a" (el código de la posición supervisora y, debajo, su puesto; en la máxima autoridad "—" y, debajo, "Máxima autoridad"), ocupante (el nombre del usuario, o "Sin ocupante" en estilo atenuado si no tiene), estado como etiqueta (Ocupada, Vacante o Suprimida, cada una con un color semántico distinto) y modalidad.
3. **Given** el listado visible, **When** escribe parte de un código, de un puesto, de una unidad, del código o puesto de la posición supervisora o del nombre del ocupante en la búsqueda, **Then** se muestran las posiciones que coinciden, sin distinguir mayúsculas ni tildes, y bajo la búsqueda se indica cuántas se encontraron (por ejemplo, "11 posiciones").
4. **Given** el listado visible, **When** elige una unidad en el filtro de unidad, **Then** se muestran las posiciones de esa unidad y de todas sus dependientes; el selector muestra las unidades en orden de árbol, con sangría.
5. **Given** el listado visible, **When** abre el filtro de estado, **Then** ofrece "Vigentes" (Ocupadas y Vacantes, seleccionada por defecto), Ocupada y Vacante; elegir una opción muestra solo las posiciones con ese estado. Las opciones Suprimida y "Todas" solo las ve el Administrador.
6. **Given** ninguna posición coincide con los filtros o la búsqueda, **When** se muestra el listado, **Then** aparece el mensaje "Ninguna posición coincide con los filtros."
7. **Given** una fila del listado, **When** la persona la despliega, **Then** ve el ocupante (nombre del usuario, o "Sin ocupante" si está Vacante), el supervisor (código, puesto y ocupante, con "Sin ocupante" si está Vacante; o "Máxima autoridad" si no tiene supervisor), la lista de posiciones que supervisa (código, puesto, ocupante y estado), el puesto (nombre y descripción) y la unidad (nombre, tipo y estado); solo una fila permanece abierta a la vez.
8. **Given** una persona con rol Administrador, **When** despliega una fila, **Then** ve además quién creó la posición y quién la modificó por última vez, con sus fechas; una persona con rol Usuario no ve esos datos.
9. **Given** una persona con rol Usuario, **When** abre las posiciones, **Then** solo ve Ocupadas y Vacantes, no ve la opción Suprimida del filtro y no ve los botones Nueva posición, Editar ni Suprimir.
10. **Given** una posición Suprimida a la que otro registro hace referencia (por ejemplo, un registro histórico), **When** cualquier persona autenticada con acceso a ese registro la consulta, **Then** puede ver sus datos completos, con la etiqueta Suprimida, su puesto, su unidad y su supervisor.
11. **Given** no hay posiciones o falla la carga, **When** se muestra el listado, **Then** aparece un estado vacío explicativo o un error con "Reintentar".

---

### User Story 2 - Crear una posición (Priority: P1)

El Administrador crea una posición eligiendo un puesto activo, una unidad activa, su supervisor y la modalidad; el sistema le asigna un código único y la crea Vacante. La máxima autoridad es la única posición de la unidad raíz y la única sin supervisor; toda posición de otra unidad tiene supervisor, que debe estar en su misma unidad o en una unidad de la que esta depende.

**Why this priority**: Sin posiciones creadas no existe la estructura de reporte ni se puede registrar la ocupación de personas.

**Independent Test**: Como Administrador, crear la máxima autoridad (en la unidad raíz, sin supervisor) y luego una posición de otra unidad que reporte a ella; comprobar los códigos, el estado Vacante, que la unidad raíz ya no se puede elegir y que aparecen en el listado.

**Acceptance Scenarios**:

1. **Given** el Administrador en el listado, **When** pulsa "Nueva posición", **Then** se abre un modal con los campos Puesto, Unidad organizacional, Supervisor y Modalidad; el código no se muestra y el estado no se elige.
2. **Given** el modal, **When** abre el selector de puesto, **Then** ofrece solo los puestos activos del catálogo de puestos.
3. **Given** el modal, **When** abre el selector de unidad, **Then** ve las unidades en orden de árbol con sangría, de cualquier nivel de la cadena de tipos; las unidades inactivas aparecen deshabilitadas con la descripción "La unidad está inactiva", y la unidad raíz aparece deshabilitada, con la descripción "La Gerencia General ya tiene su posición (máxima autoridad)", si ya tiene su posición vigente.
4. **Given** el modal, **When** todavía no eligió la unidad, **Then** el selector de supervisor está deshabilitado; al elegirla se habilita y cada opción muestra el código de la posición, su puesto y el nombre de su ocupante, o "Sin ocupante" si está Vacante (por ejemplo, "POS-0302 · Jefe de aplicaciones · Camila Torres"); solo se ofrecen posiciones no Suprimidas de la misma unidad o de sus unidades superiores, agrupadas por unidad, desde la unidad elegida hasta la raíz.
5. **Given** el modal, **When** elige la unidad raíz, **Then** el supervisor queda fijo en "Sin supervisor (máxima autoridad)" y no se puede cambiar; si luego cambia la unidad y el supervisor elegido deja de ser válido, el selector se vacía y se pide elegirlo de nuevo.
6. **Given** el modal con datos válidos, **When** guarda, **Then** la posición se crea Vacante con un código generado por el sistema con el formato POS-#### (por ejemplo, POS-0101), aparece en el listado y se muestra un mensaje de confirmación.
7. **Given** el modal, **When** falta el puesto, la unidad, la modalidad o el supervisor (cuando ya existe la máxima autoridad), **Then** se muestran errores junto a cada campo y no se guarda nada.
8. **Given** la unidad raíz ya tiene su posición vigente, **When** se intenta crear otra en la unidad raíz o crear una posición sin supervisor (por la interfaz o directamente), **Then** se rechaza con un error en el campo correspondiente y no se guarda nada; y si una posición de otra unidad se envía sin supervisor, también se rechaza.
9. **Given** una petición directa con un puesto inactivo, una unidad inactiva, un supervisor Suprimido o un supervisor de una unidad dependiente de la elegida o de otra rama del árbol de unidades, **When** se intenta crear, **Then** se rechaza con un error en el campo correspondiente y no se guarda nada; el estado nunca viene del cliente y toda posición nueva nace Vacante.
10. **Given** se crean varias posiciones del mismo puesto en la misma unidad, **When** el Administrador guarda, **Then** se aceptan, porque se distinguen por su código.
11. **Given** una persona con rol Usuario, **When** intenta crear una posición (por la interfaz o directamente), **Then** se le deniega por falta de permisos.

---

### User Story 3 - Editar la unidad, el supervisor y la modalidad (Priority: P2)

El Administrador cambia la unidad, el supervisor o la modalidad de una posición Ocupada o Vacante. El puesto no se puede cambiar. Cambiar la unidad o el supervisor de una posición Ocupada cambia la asignación organizacional de su ocupante. En la máxima autoridad solo se puede cambiar la modalidad.

**Why this priority**: Mantiene correcta la estructura de reporte sin crear posiciones nuevas ni perder el historial.

**Independent Test**: Editar una posición con subordinadas: cambiar su unidad y su modalidad y luego moverla bajo otro supervisor; comprobar que sus subordinadas siguen reportándole, que el puesto y el código no cambiaron y que queda registrado quién la modificó.

**Acceptance Scenarios**:

1. **Given** una posición Ocupada o Vacante, **When** el Administrador pulsa Editar, **Then** se abre un modal con el puesto en solo lectura, los selectores Unidad organizacional, Supervisor y Modalidad con sus valores actuales y, en solo lectura, el código, el ocupante, el estado y los datos de auditoría.
2. **Given** el modal de edición, **When** abre el selector de supervisor, **Then** ofrece las posiciones no Suprimidas de la misma unidad o de sus unidades superiores, agrupadas por unidad y con el mismo formato que en la creación, sin incluir la propia posición ni ninguna de sus subordinadas, directas o indirectas.
3. **Given** el modal de edición de una posición con subordinadas vigentes, **When** abre el selector de unidad, **Then** las unidades que dejarían a alguna subordinada vigente en una unidad superior a la de su supervisora aparecen deshabilitadas con la descripción "Tiene subordinadas en unidades superiores a esta"; en la edición, la unidad raíz solo se ofrece si es la que la posición ya tiene.
4. **Given** el modal de edición, **When** cambia valores válidos y guarda, **Then** los cambios se reflejan en el listado, el puesto y el código permanecen iguales, el ocupante y el estado no cambian, se registra quién la modificó y cuándo y se muestra un mensaje de confirmación.
5. **Given** una posición con subordinadas, **When** cambia su supervisor, **Then** sus subordinadas siguen reportándole a ella.
6. **Given** la máxima autoridad, **When** el Administrador la edita, **Then** la unidad y el supervisor se muestran en solo lectura y solo puede cambiar la modalidad; una petición directa que intente cambiar su unidad o su supervisor se rechaza y no se guarda nada.
7. **Given** una petición directa (sin la interfaz), **When** intenta usar como supervisor la propia posición, una subordinada, una posición Suprimida, una posición de una unidad dependiente de la nueva o de otra rama del árbol de unidades, dejar sin supervisor a una posición de una unidad que no es la raíz, o mover una posición a una unidad superior a la de alguna de sus subordinadas vigentes, **Then** se rechaza con un error en el campo correspondiente (y, en el último caso, indicando cuántas subordinadas lo impiden) y no se guarda nada.
8. **Given** una petición directa que intenta cambiar el puesto, el código, el estado o el ocupante, **When** se intenta guardar, **Then** se rechaza y no se guarda nada.
9. **Given** una posición Suprimida, **When** se intenta editar (por la interfaz o directamente), **Then** no se ofrece Editar y la petición se rechaza.
10. **Given** el Administrador abre la edición y no cambia ningún valor, **When** guarda, **Then** no se actualiza quién la modificó ni cuándo, y no se añaden registros al historial.
11. **Given** dos administradores abren la edición de la misma posición, **When** el segundo guarda después de que el primero ya guardó, **Then** el segundo guardado se rechaza con un aviso de que la posición cambió y se pide recargar.
12. **Given** una persona con rol Usuario, **When** intenta editar (por la interfaz o directamente), **Then** se le deniega por falta de permisos.

---

### User Story 4 - Suprimir una posición (Priority: P2)

El Administrador suprime una posición que ya no se necesita. Solo puede suprimirse si está Vacante y no tiene subordinadas vigentes. Suprimir es definitivo: la posición no se reactiva ni se edita, y si se vuelve a necesitar se crea una nueva. Las posiciones nunca se eliminan.

**Why this priority**: Retira del uso las posiciones obsoletas sin perder el histórico y permite que su puesto y su unidad puedan desactivarse.

**Independent Test**: Suprimir una posición Vacante hoja (con confirmación) y comprobar que sale del listado por defecto; intentar suprimir una Ocupada y una Vacante con subordinadas y comprobar el rechazo con el motivo y el conteo; comprobar que la Suprimida sigue consultable.

**Acceptance Scenarios**:

1. **Given** una posición Vacante sin subordinadas vigentes, **When** el Administrador pulsa Suprimir, **Then** se pide una confirmación que nombra la posición.
2. **Given** el diálogo de confirmación, **When** confirma, **Then** la posición pasa a Suprimida, desaparece del listado por defecto y se muestra un mensaje de confirmación; si cancela, no cambia nada.
3. **Given** una posición Ocupada o con subordinadas vigentes, **When** se muestra su detalle al Administrador, **Then** el botón Suprimir aparece deshabilitado con la descripción del motivo (y, en su caso, cuántas subordinadas tiene).
4. **Given** una posición Ocupada o con subordinadas vigentes, **When** se intenta suprimir directamente sin la interfaz, **Then** la acción se rechaza, la posición conserva su estado e indica el motivo y, en su caso, cuántas subordinadas vigentes tiene.
5. **Given** una posición Vacante cuyas únicas subordinadas están Suprimidas, **When** el Administrador la suprime, **Then** procede, porque las subordinadas Suprimidas no lo impiden.
6. **Given** la máxima autoridad Vacante y sin subordinadas vigentes, **When** el Administrador la suprime, **Then** procede y la unidad raíz queda libre para crear una nueva máxima autoridad.
7. **Given** una posición Suprimida, **When** se muestra su detalle, **Then** no ofrece Editar ni Suprimir ni reactivar; se indica visualmente con la etiqueta Suprimida y no puede ser supervisora, ocuparse ni editarse.
8. **Given** una posición Suprimida, **When** su puesto o su unidad se desactivan después, **Then** la posición no cambia y sigue consultable.
9. **Given** una persona con rol Usuario, **When** intenta suprimir (por la interfaz o directamente), **Then** se le deniega por falta de permisos.

---

### User Story 5 - Trazabilidad de los cambios (Priority: P3)

El sistema registra quién creó cada posición y cuándo, quién la modificó por última vez y cuándo, y cada cambio en un historial con el campo afectado, el valor anterior, el valor nuevo, el usuario y la fecha.

**Why this priority**: Garantiza auditoría de una estructura que cambia con el tiempo y cuyas posiciones nunca se eliminan; la posición no tiene versionado, así que el historial es el único registro del detalle de los cambios.

**Independent Test**: Crear una posición, cambiar su unidad, su supervisor y su modalidad y suprimirla, con distintos administradores, e inspeccionar la auditoría.

**Acceptance Scenarios**:

1. **Given** el Administrador crea una posición, **When** se guarda, **Then** queda registrado quién la creó y cuándo, y el historial recibe un registro por cada dato inicial con valor (puesto, unidad, supervisor —si tiene—, modalidad y estado), con el valor anterior vacío.
2. **Given** un administrador cambia la unidad, el supervisor o la modalidad, **When** guarda, **Then** queda registrado quién la modificó por última vez y cuándo, sin alterar quién la creó, y el historial recibe un registro por cada campo cambiado, con el valor anterior, el valor nuevo, el usuario y la fecha.
3. **Given** una posición con subordinadas cambia de supervisor, **When** se guarda, **Then** el historial registra el cambio solo en la posición editada; sus subordinadas no reciben registros.
4. **Given** una posición se suprime, **When** se confirma, **Then** el historial recibe un registro del estado (de Vacante a Suprimida) con el usuario y la fecha, sin alterar quién la modificó por última vez por ediciones de contenido.
5. **Given** el historial de una posición, **When** se consulta, **Then** los registros no se pueden modificar ni borrar desde la aplicación.

---

### Edge Cases

- Se intenta crear a la vez dos posiciones en la unidad raíz (o dos sin supervisor): solo una se guarda; la otra recibe el error de que la Gerencia General ya tiene su posición (máxima autoridad).
- Se pide como supervisor una posición de una unidad dependiente de la de la posición, o de otra rama del árbol de unidades (por ejemplo, directamente sin la interfaz): se rechaza con un error en el campo Supervisor; dentro de una misma unidad no se restringe quién supervisa a quién.
- Se mueve una posición a una unidad superior a la de alguna de sus subordinadas vigentes: se rechaza indicando cuántas subordinadas lo impiden; las subordinadas Suprimidas no lo impiden.
- Se suprime la máxima autoridad: la unidad raíz queda libre y se puede crear una nueva; mientras no exista, ninguna posición de otra unidad puede crearse sin supervisor.
- Se cambia la unidad padre de una unidad (reubicación en el módulo de unidades) y eso dejaría una posición reportando a otra de una unidad inferior o de otra rama: ese módulo debe impedirlo (ver FR-038).
- Dos administradores reubican a la vez posiciones de modo que, juntas, formarían un ciclo de supervisión (A bajo B y B bajo A): solo se guarda la primera; la segunda se rechaza porque las reglas se comprueban contra el estado vigente al guardar.
- Se suprime una posición supervisora mientras otra persona crea o reasigna una subordinada bajo ella: se resuelve como una sola operación; si la subordinada llegó primero, la supresión se rechaza; si la supresión llegó primero, la creación o reasignación se rechaza porque el supervisor ya está Suprimido.
- El módulo de gestión de personal ocupa una posición Vacante justo mientras se suprime: se resuelve como una sola operación; si la ocupación llegó primero, la supresión se rechaza porque la posición está Ocupada.
- Se desactiva un puesto o una unidad mientras otra persona crea una posición con ellos: se resuelve como una sola operación; si la posición llegó primero, la desactivación se rechaza indicando que tiene posiciones Ocupadas o Vacantes; si la desactivación llegó primero, la creación se rechaza porque el puesto o la unidad ya no están activos.
- Dos administradores editan a la vez la misma posición: el segundo guardado se rechaza con un aviso de que la posición cambió y se pide recargar; no se sobrescribe en silencio.
- Dos administradores suprimen a la vez la misma posición: no se registran cambios repetidos; el segundo recibe la situación actual y no se añade un registro al historial.
- Se edita una posición Ocupada: el cambio de unidad o de supervisor aplica a su ocupante actual; la ocupación y el estado no cambian.
- Se pide como supervisor una posición Suprimida (por ejemplo, directamente sin la interfaz): se rechaza con un error en el campo Supervisor.
- Varias posiciones del mismo puesto en la misma unidad: son válidas; se distinguen por su código.
- La unidad elegida es la unidad raíz o una del último nivel de la cadena: es válida; una posición puede estar en cualquier nivel.
- El filtro de unidad elige una unidad con dependientes: incluye las posiciones de toda su rama, también las de unidades dependientes inactivas si tienen posiciones Suprimidas visibles para el Administrador.
- Se supera el número 9999 en el código: el código sigue creciendo (POS-10000) sin reutilizar ni repetir ninguno.
- Se pide una posición que no existe: se informa que no existe.
- Un Usuario abre directamente la dirección de una posición Suprimida a la que tiene acceso por un registro histórico: puede consultarla, pero no aparece en su listado.
- El filtro "Suprimida" no tiene resultados para el Administrador: se muestra el mensaje de que ninguna posición coincide con los filtros.
- La posición supervisora está Suprimida o su ocupante cambió: el detalle muestra siempre los datos vigentes del supervisor y de su ocupante.
- Sin sesión iniciada no se accede a las posiciones (feature 000-login).

## Requirements *(mandatory)*

### Functional Requirements

**Permisos y acceso**

- **FR-001**: El sistema MUST permitir al Administrador de Talento Humano listar (Ocupadas, Vacantes y Suprimidas), consultar, crear, editar (unidad, supervisor y modalidad) y suprimir posiciones.
- **FR-002**: El sistema MUST permitir a cualquier otra persona autenticada consultar una posición (también Suprimida) y listar solo las Ocupadas y Vacantes, y MUST denegarle crear, editar y suprimir.
- **FR-003**: Toda operación MUST requerir sesión iniciada y el sistema MUST verificar el rol en cada petición, sin depender solo de lo que muestre la interfaz; en particular, un Usuario MUST NOT poder obtener posiciones Suprimidas en el listado aunque lo pida directamente, ni los datos de auditoría.

**Datos de la posición**

- **FR-004**: Cada posición MUST tener un código único generado por el sistema al crearla, con el formato `POS-` seguido de un número secuencial de al menos 4 dígitos (por ejemplo, `POS-0101`), que MUST NOT poder modificarse nunca ni reutilizarse.
- **FR-005**: Cada posición MUST tener un puesto obligatorio del catálogo de puestos. Al crear solo MUST aceptar puestos activos y el puesto MUST NOT poder cambiarse después. Sus datos (código, nombre y descripción) MUST mostrarse en solo lectura y no almacenarse en la posición.
- **FR-006**: Cada posición MUST tener una unidad organizacional obligatoria. Al crear o editar MUST aceptar solo unidades activas, de cualquier nivel de la cadena de tipos (también la raíz y las del último nivel). La unidad raíz (la unidad sin unidad padre, la Gerencia General) MUST tener una sola posición vigente (no Suprimida), que es la máxima autoridad; si ya existe, la unidad raíz MUST NOT poder elegirse para otra posición. Las demás unidades pueden tener varias posiciones. Los datos de la unidad (código, nombre, tipo y estado) MUST mostrarse en solo lectura.
- **FR-007**: Cada posición MUST tener un supervisor (otra posición), obligatorio salvo en la máxima autoridad. El supervisor MUST NOT ser una posición Suprimida, la propia posición ni ninguna de sus subordinadas, directas o indirectas, de modo que la estructura de supervisión nunca contenga ciclos. **Jerarquía de unidades**: la posición supervisora MUST estar en la misma unidad que la posición o en una unidad de la que esta depende (su unidad padre, la padre de esta, y así hasta la raíz); MUST NOT estar en una unidad dependiente ni en otra rama del árbol de unidades. Dentro de una misma unidad no se restringe quién supervisa a quién.
- **FR-008**: Una posición MUST estar sin supervisor si y solo si está en la unidad raíz: la máxima autoridad es la única posición sin supervisor y toda posición de otra unidad tiene supervisor. Crear o editar una posición de modo que la unidad raíz tenga dos posiciones vigentes, o que una posición de otra unidad quede sin supervisor, MUST rechazarse. Si la máxima autoridad se suprime, la unidad raíz queda libre para crear una nueva.
- **FR-009**: Cada posición MUST tener una modalidad obligatoria: Nombramiento, Contrato, Encargo o Subrogación. En una posición Vacante indica la modalidad con la que se cubrirá.
- **FR-010**: El ocupante MUST mostrarse en solo lectura y MUST NOT asignarse ni liberarse desde este módulo; una posición Vacante MUST NOT tener ocupante. La lista "Supervisa a" (posiciones que reportan a la posición) MUST obtenerse consultando la relación inversa y no almacenarse.
- **FR-011**: Cada posición MUST tener un estado: Ocupada, Vacante o Suprimida. Este módulo MUST crear solo posiciones Vacantes y MUST solo pasarlas a Suprimidas; el estado MUST NOT elegirse en ningún formulario ni venir del cliente.

**Ciclo de vida**

- **FR-012**: El sistema MUST NOT permitir eliminar posiciones: no existe la acción de eliminar, ni la de reactivar, ni la de asignar o liberar ocupante.
- **FR-013**: El sistema MUST permitir editar posiciones Ocupadas y Vacantes (unidad, supervisor y modalidad), aplicando de nuevo las reglas de FR-006 a FR-008. Cambiar la unidad o el supervisor de una Ocupada MUST cambiar la asignación organizacional de su ocupante sin cambiar la ocupación ni el estado. La nueva unidad MUST seguir siendo la misma o una unidad superior a la de cada subordinada vigente; si no lo es, MUST rechazarse indicando cuántas subordinadas lo impiden. En la máxima autoridad solo MUST poder cambiarse la modalidad: su unidad es la raíz y no tiene supervisor.
- **FR-014**: El sistema MUST permitir suprimir una posición solo si está Vacante y no tiene subordinadas vigentes. Si está Ocupada o tiene subordinadas vigentes, MUST rechazar la acción, conservar su estado e indicar el motivo y, en su caso, cuántas subordinadas vigentes tiene. Las subordinadas Suprimidas MUST NOT impedir la supresión. Suprimir la máxima autoridad MUST dejar libre la unidad raíz. Suprimir MUST pedir una confirmación accesible que nombre la posición.
- **FR-015**: Suprimida MUST ser un estado definitivo: la posición MUST NOT reactivarse, editarse, ocuparse ni ser supervisora. Si se vuelve a necesitar, se crea una posición nueva con otro código.
- **FR-016**: Una posición vigente MUST tener siempre su puesto y su unidad activos. El puesto y la unidad MUST NOT poder desactivarse (directamente ni en cascada) mientras tengan posiciones Ocupadas o Vacantes, y MUST NOT poder asignarse a una posición nueva si están inactivos; la verificación y el cambio MUST resolverse como una sola operación para que cambios simultáneos no puedan dejar la regla incumplida.
- **FR-017**: Una posición Suprimida MUST conservarse y seguir siendo consultable, con su puesto, su unidad y su supervisor; se MUST indicar visualmente con la etiqueta Suprimida y MUST NOT cambiar si su puesto o su unidad se desactivan después.
- **FR-018**: Las reglas de la estructura (sin ciclos, jerarquía de unidades, una sola máxima autoridad en la unidad raíz, supervisor no Suprimido, puesto y unidad activos) MUST comprobarse contra el estado vigente en el momento de guardar.

**Listado y detalle**

- **FR-019**: El listado MUST mostrar código, puesto, unidad organizacional, "reporta a" (el código de la posición supervisora y, debajo, su puesto; en la máxima autoridad "—" y, debajo, "Máxima autoridad"), ocupante (el nombre del usuario, o "Sin ocupante" en estilo atenuado), estado (etiqueta con un color semántico distinto para Ocupada, Vacante y Suprimida) y modalidad, ordenado por código ascendente (numéricamente por la parte secuencial) y paginado de 10 en 10.
- **FR-020**: El listado MUST permitir buscar por código, puesto, unidad, supervisor (código o puesto de la posición supervisora) u ocupante, sin distinguir mayúsculas ni tildes, e indicar bajo la búsqueda cuántas posiciones se encontraron (por ejemplo, "11 posiciones"). Si ninguna posición coincide, MUST mostrar el mensaje "Ninguna posición coincide con los filtros."
- **FR-021**: El listado MUST permitir filtrar por estado y por unidad organizacional. El filtro de unidad MUST incluir las posiciones de la unidad elegida y de todas sus dependientes y su selector MUST mostrar las unidades en orden de árbol con sangría. El filtro de estado MUST ofrecer "Vigentes" (Ocupadas y Vacantes, predeterminada), Ocupada y Vacante, y las opciones Suprimida y "Todas" MUST verlas solo el Administrador.
- **FR-022**: El listado MUST mostrar por defecto solo las posiciones vigentes (Ocupadas y Vacantes), para todos los roles. Quien no sea Administrador MUST recibir solo Ocupadas y Vacantes. Búsqueda, filtros y página MUST conservarse en la dirección de la página.
- **FR-023**: Cada fila del listado MUST ser desplegable y mostrar el detalle: ocupante (nombre, o "Sin ocupante" si está Vacante), supervisor (código, puesto y ocupante, con "Sin ocupante" si está Vacante; o "Máxima autoridad" si no tiene), "Supervisa a" (código, puesto, ocupante y estado), puesto (nombre y descripción), unidad (nombre, tipo y estado) y, solo al Administrador, la auditoría. Solo una fila MUST permanecer abierta a la vez. El detalle MUST estar disponible para todos los usuarios.
- **FR-024**: El listado MUST cubrir los estados cargando, vacío, error con reintento y éxito.

**Acciones del Administrador y formularios**

- **FR-025**: El botón "Nueva posición" MUST ser visible solo para el Administrador. Dentro del detalle desplegado, Editar y Suprimir MUST ser visibles solo para el Administrador y solo en posiciones no Suprimidas. Suprimir MUST mostrarse deshabilitado cuando la posición está Ocupada o tiene subordinadas vigentes, con la descripción del motivo.
- **FR-026**: "Nueva posición" MUST abrir un modal con los campos en este orden: Puesto (puestos activos), Unidad organizacional (unidades en orden de árbol con sangría; las inactivas deshabilitadas con la descripción "La unidad está inactiva"; la unidad raíz deshabilitada con "La Gerencia General ya tiene su posición (máxima autoridad)" si ya tiene su posición vigente), Supervisor y Modalidad (las cuatro). El Supervisor MUST habilitarse al elegir la unidad; cada opción MUST mostrar el código de la posición, su puesto y su ocupante ("Sin ocupante" si está Vacante), por ejemplo "POS-0302 · Jefe de aplicaciones · Camila Torres"; solo se ofrecen posiciones no Suprimidas de la misma unidad o de sus unidades superiores, agrupadas por unidad, desde la unidad elegida hasta la raíz. Si la unidad elegida es la raíz, el supervisor MUST quedar fijo en "Sin supervisor (máxima autoridad)" y no poder cambiarse; si al cambiar la unidad el supervisor elegido deja de ser válido, el selector MUST vaciarse y pedir elegirlo de nuevo. El código no se muestra: se asigna al guardar.
- **FR-027**: "Editar" MUST abrir un modal con el puesto en solo lectura y los selectores de unidad, supervisor (sin la propia posición ni sus subordinadas) y modalidad como en la creación; el selector de unidad MUST deshabilitar las unidades que dejarían a alguna subordinada vigente en una unidad superior, con la descripción "Tiene subordinadas en unidades superiores a esta". En la máxima autoridad la unidad y el supervisor MUST mostrarse en solo lectura y solo la modalidad es editable. En solo lectura se muestran el código, el ocupante, el estado y los datos de auditoría (estos, solo para el Administrador).
- **FR-028**: Tras cada acción exitosa el sistema MUST mostrar un mensaje de confirmación sobre el listado. MUST mostrar los errores de validación junto al campo correspondiente, conservar los valores escritos cuando la validación falla e impedir envíos duplicados mientras un guardado o una supresión está en curso.
- **FR-029**: Al editar, el sistema MUST rechazar el guardado si la posición cambió desde que se abrió el formulario, con un aviso que pida recargar para ver la versión actual.

**Auditoría**

- **FR-030**: El sistema MUST registrar quién creó cada posición y cuándo, y quién la modificó por última vez y cuándo; esos datos de usuario MUST mostrarse solo al Administrador y MUST NOT devolverse al resto de roles. Guardar una edición sin cambios reales MUST NOT actualizar la última modificación ni añadir registros al historial.
- **FR-031**: El sistema MUST registrar cada cambio de una posición en un historial con el campo afectado, el valor anterior, el valor nuevo, el usuario y la fecha. Este módulo MUST registrar el puesto (solo al crear), la unidad, el supervisor y la modalidad, y el estado al suprimir; la creación MUST registrar un registro por cada dato inicial con valor, con el valor anterior vacío. Cambiar el supervisor de una posición MUST registrar el cambio solo en esa posición. Los registros del historial MUST conservarse y MUST NOT poder modificarse ni borrarse desde la aplicación. Los cambios de ocupante y de estado Ocupada / Vacante los registra el módulo de gestión de personal en el mismo historial.

**Relación con otros módulos**

- **FR-032**: Los puestos MUST obtenerse exclusivamente del catálogo de puestos y las unidades exclusivamente del módulo de unidades organizacionales; este módulo MUST NOT crearlos, editarlos ni duplicar sus datos.
- **FR-033**: Este módulo MUST ser la fuente única de la estructura de reporte: ningún módulo mantiene su propia copia, y los módulos que consumen posiciones (por ejemplo, organigrama y perfilamiento) MUST leerlas por un contrato público de solo lectura sin poder modificarlas.
- **FR-034**: Solo el módulo de gestión de personal MUST poder cambiar el ocupante de una posición y su paso entre Ocupada y Vacante, y solo puede ocupar posiciones Vacantes; los ocupantes son usuarios del sistema y este módulo no crea ni edita usuarios.
- **FR-035**: La posición MUST NOT tener versionado: los cambios de unidad, supervisor o modalidad aplican a su ocupante actual y el detalle del cambio queda en el historial de auditoría.
- **FR-036**: Las posiciones Suprimidas MUST seguir siendo consultables desde los registros históricos para cualquier usuario con acceso a esos registros.

**Experiencia y accesibilidad**

- **FR-037**: Toda la interfaz MUST estar en español y cumplir WCAG 2.2 AA entre 320 y 1440 px de ancho, sin desplazamiento horizontal de la página; el estado de una posición MUST comunicarse con texto, no solo con color; los modales y diálogos MUST ser accesibles con teclado (foco atrapado, cierre con Escape y foco devuelto a quien los abrió).
- **FR-038**: La regla de jerarquía de FR-007 usa el árbol de unidades. El módulo de unidades organizacionales MUST impedir cualquier cambio de unidad padre (reubicación) que deje una posición reportando a otra de una unidad inferior o de otra rama; mientras no lo haga, esta feature no puede garantizar FR-007 después de crear la posición.

**Fuera de alcance**: la gestión de puestos y de unidades, la asignación y liberación de ocupantes y el paso entre Ocupada y Vacante (gestión de personal), la creación y edición de usuarios, cambiar el puesto de una posición, reactivar posiciones Suprimidas, varias máximas autoridades, la pantalla para consultar el historial de cambios (el historial se registra, pero esta feature no define cómo mostrarlo), el versionado de posiciones, el organigrama y el perfilamiento (consumidores), la importación o exportación masiva y la eliminación física de posiciones.

### Key Entities *(include if feature involves data)*

- **Posición**: lugar concreto de la estructura; tiene código único e inmutable (POS-####), puesto (fijo), unidad organizacional, supervisor (otra posición de la misma unidad o de una unidad superior de su rama; ninguno solo en la máxima autoridad, que es la única posición de la unidad raíz), modalidad, ocupante (solo lectura, lo gestiona personal) y estado (Ocupada, Vacante o Suprimida); guarda quién la creó y cuándo, y quién la modificó por última vez y cuándo. Nunca se elimina.
- **Puesto** *(externo, catálogo de puestos)*: define las funciones y requisitos de la posición; solo se usan puestos activos para posiciones nuevas.
- **Unidad organizacional** *(externa, estructura organizacional)*: donde está la posición; solo se usan unidades activas para posiciones nuevas.
- **Usuario ocupante** *(externo)*: persona que ocupa una posición Ocupada.
- **Registro de cambios**: entrada inmutable del historial con la posición, el campo afectado, el valor anterior, el valor nuevo, el usuario y la fecha.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El Administrador crea una posición (puesto, unidad, supervisor y modalidad) en menos de 1 minuto.
- **SC-002**: Una persona encuentra una posición por código, puesto o supervisor en menos de 15 segundos.
- **SC-003**: El listado de hasta 2000 posiciones se muestra paginado y cada página aparece en menos de 3 segundos.
- **SC-004**: El 100 % de los intentos de crear, editar o suprimir por parte de un Usuario se rechaza.
- **SC-005**: Ninguna posición es su propio supervisor directo o indirecto (la estructura de supervisión nunca contiene ciclos) y nunca hay más de una posición vigente en la unidad raíz ni una posición sin supervisor fuera de ella, incluso con cambios simultáneos de varios administradores.
- **SC-012**: Ninguna posición vigente reporta a otra de una unidad inferior a la suya ni de otra rama del árbol de unidades.
- **SC-006**: Ninguna posición vigente tiene un supervisor Suprimido, un puesto inactivo ni una unidad inactiva.
- **SC-007**: El 100 % de los intentos de suprimir una posición Ocupada o con subordinadas vigentes se rechaza indicando el motivo y los conteos correctos.
- **SC-008**: El 100 % de las posiciones permanece en el sistema: ninguna acción de la aplicación elimina una posición ni reactiva una Suprimida.
- **SC-009**: Ninguna pareja de posiciones comparte código, incluso con creaciones simultáneas.
- **SC-010**: El 100 % de las creaciones, ediciones y supresiones deja registrado el usuario y la fecha, y cada modificación conserva el campo afectado, el valor anterior y el nuevo.
- **SC-011**: El 100 % de las posiciones Suprimidas queda fuera del listado y de los selectores de supervisor de los Usuarios, y el 100 % de las referencias existentes a ellas sigue siendo consultable.

## Assumptions

- "Administrador de Talento Humano" corresponde al rol Administrador definido en 000-login; el resto de personas autenticadas tiene el rol Usuario.
- **Puntos por confirmar** (la spec adopta el valor por defecto del documento fuente hasta que negocio decida): (1) la ocupación (ocupante y paso entre Ocupada y Vacante) la gestiona el módulo de gestión de personal; la alternativa es que el Administrador asigne y libere el ocupante al editar la posición. (2) El puesto no cambia después de crear la posición porque el ocupante hereda sus funciones y requisitos; la alternativa es permitirlo mientras esté Vacante. (3) Los puestos no tienen nivel en el modelo de datos, así que la jerarquía se decide por la unidad: la supervisora está en la misma unidad o en una unidad superior de la misma rama, y dentro de una misma unidad no se restringe quién supervisa a quién; las alternativas son comparar solo el nivel del tipo de unidad (permitiría reportar a una posición de otra rama) o añadir un nivel jerárquico al puesto (cambia el modelo de datos y el catálogo de puestos). (4) La Gerencia General (unidad raíz) tiene una sola posición vigente, la máxima autoridad; la alternativa es permitir otras (asesores o asistentes) que reporten a ella. (5) Cada opción del selector de supervisor muestra código, puesto y ocupante y se agrupa por unidad; la alternativa es mostrar la unidad en cada opción en lugar de agrupar.
- La regla de jerarquía se sostiene porque el módulo de unidades no debería permitir movimientos que dejen una posición reportando a otra de una unidad inferior o de otra rama (FR-038). Hoy ese módulo (feature 005) sí permite reubicar una unidad bajo otra de su tipo superior, así que su validación debe ampliarse para considerar las posiciones, o esta regla quedará incumplida tras una reubicación.
- El módulo de gestión de personal aún no existe. Mientras no exista, este módulo solo trabaja con el estado ya guardado de las posiciones (las Ocupadas existentes se muestran con su ocupante) y no puede ocuparlas ni liberarlas; las posiciones nuevas nacen Vacantes. Las reglas que dependen de ese módulo (solo ocupar posiciones Vacantes, registrar sus cambios en el mismo historial) se definen como contrato público que deberá cumplir.
- Las reglas que protegen al puesto y a la unidad (no desactivarse con posiciones Ocupadas o Vacantes, no asignarse a posiciones nuevas si están inactivos) ya existen en el catálogo de puestos y en las unidades organizacionales; esta feature las alimenta con las posiciones reales y exige la verificación en la misma operación en que se crea o edita la posición.
- El código crece de forma secuencial, nunca se reutiliza y puede superar los 4 dígitos (POS-10000) si hiciera falta; el orden por código es numérico según la parte secuencial.
- Cada posición vigente (Ocupada o Vacante) cuenta como subordinada vigente de su supervisor; las Suprimidas no.
- Editar una posición, o suprimirla, no versiona la posición. El control de edición simultánea se comporta igual que en los otros catálogos: el segundo guardado sobre datos desactualizados se rechaza y se pide recargar. Suprimir queda en el historial, pero no actualiza «quién la modificó por última vez y cuándo» ni el control de edición simultánea: esos datos reflejan solo ediciones de contenido.
- Quién creó y modificó una posición solo lo ve el Administrador, igual que en las unidades; el resto ve los datos de la posición, de su puesto, de su unidad y de su ocupante.
- La creación también entra en el historial (un registro por dato inicial con valor), y las referencias (puesto, unidad y supervisor) se identifican por su id.
- Quien tiene acceso a un registro histórico que referencia una posición Suprimida puede consultarla por su dirección o identificador, aunque no aparezca en su listado.
- El historial de cambios se registra pero esta feature no lo muestra en pantalla; su consulta se define en una feature posterior.
- El listado se pagina de 10 en 10, como los demás catálogos; el tamaño esperado es de cientos a pocos miles de posiciones.
- Los textos de la interfaz usan "Posición", "Puesto", "Unidad organizacional", "Supervisor", "Modalidad", "Ocupada", "Vacante" y "Suprimida" (en femenino, porque se refieren a la posición).

## Pantallas *(si la feature tiene UI)*

### Listado de posiciones
- **Propósito**: consultar la estructura de reporte, buscar y filtrar posiciones y, para el Administrador, crear, editar y suprimir posiciones.
- **Datos que muestra**: de cada posición, el código, el puesto, la unidad, "reporta a" (código y puesto de la supervisora, o "—" y "Máxima autoridad"), el ocupante ("Sin ocupante" atenuado), el estado (etiqueta) y la modalidad; el número de posiciones encontradas bajo la búsqueda; filas desplegables con el detalle (ocupante, supervisor con su ocupante, "Supervisa a" con código, puesto, ocupante y estado, puesto, unidad y, solo Administrador, auditoría); paginación.
- **Acciones disponibles** (y quién puede hacerlas): buscar, filtrar por estado y por unidad, desplegar el detalle y paginar (Administrador y Usuario); Nueva posición, y dentro del detalle Editar y Suprimir en posiciones no Suprimidas (solo Administrador). Suprimir pide confirmación y aparece deshabilitado, con el motivo, si la posición está Ocupada o tiene subordinadas vigentes. No hay acción de eliminar, reactivar ni de asignar ocupante.
- **Estados**: cargando (esqueleto), vacío, sin coincidencias ("Ninguna posición coincide con los filtros."), error con "Reintentar", éxito con mensaje de confirmación sobre el listado, y sin permisos (el Usuario no ve las acciones de gestión ni las opciones Suprimida y "Todas" del filtro).
- **Mockup**: patrón Listado con filas desplegables de `design/Puestos.dc.html` (patrón Listado, punto 10) y Estados (`design/Estados.dc.html`) para el diálogo de confirmación; el tablero `design/Posiciones.dc.html` (filtro de unidad en árbol, badges de estado Ocupada / Vacante / Suprimida y modales), registrado en `DESIGN.md`; debe actualizarse con la columna Ocupante, la columna "reporta a" con código y puesto, el contador de resultados y los selectores de unidad y supervisor agrupados antes de rehacer el frontend.

### Modal de posición (crear y editar)
- **Propósito**: crear una posición Vacante o modificar la unidad, el supervisor y la modalidad de una existente (Ocupada o Vacante).
- **Datos que muestra**: al crear, Puesto, Unidad organizacional (en orden de árbol con sangría; inactivas deshabilitadas y la raíz deshabilitada si ya tiene su posición), Supervisor (habilitado al elegir la unidad; opciones con código, puesto y ocupante agrupadas por unidad hasta la raíz; fijo en "Sin supervisor (máxima autoridad)" si la unidad es la raíz) y Modalidad. Al editar, el puesto en solo lectura, Unidad (con deshabilitadas las que dejarían subordinadas en una unidad superior), Supervisor (sin la propia posición ni sus subordinadas) y Modalidad y, en solo lectura, código, ocupante, estado y auditoría (solo Administrador); en la máxima autoridad, unidad y supervisor en solo lectura.
- **Acciones disponibles** (y quién puede hacerlas): Guardar y Cancelar (solo Administrador).
- **Estados**: cargando (al abrir los selectores o una edición), error de validación junto al campo con alerta general, error de carga con reintento, conflicto de edición, rechazo por regla de la estructura (ciclo, máxima autoridad duplicada, supervisor Suprimido, puesto o unidad inactivos) junto al campo correspondiente, guardando (botón bloqueado), éxito (se cierra el modal y se muestra un mensaje) y sin permisos.
- **Mockup**: modal sobre el patrón Formulario (`design/Formulario.dc.html`); por definir en el tablero de posiciones.

### Diálogo de supresión
- **Propósito**: confirmar la supresión definitiva de una posición Vacante sin subordinadas vigentes.
- **Datos que muestra**: el código y el puesto de la posición y la advertencia de que la supresión es definitiva.
- **Acciones disponibles** (y quién puede hacerlas): Cancelar y Suprimir (solo Administrador).
- **Estados**: confirmación, suprimiendo (botón bloqueado), rechazo del servidor con el motivo y, en su caso, el conteo de subordinadas, y éxito (se cierra y se muestra un mensaje).
- **Mockup**: patrón Estados (`design/Estados.dc.html`, confirmación destructiva).
