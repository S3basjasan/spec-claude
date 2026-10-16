# Feature Specification: Unidades organizacionales

**Feature Branch**: `005-unidad-organizacional`

**Created**: 2026-10-14

**Updated**: 2026-10-15

**Status**: Draft

**Input**: User description: "Gestión de unidades organizacionales y del catálogo de tipos de unidad (documento fuente `specify-unidades.md`, revisado el 2026-10-15, y modelo de datos `estructura_empresarial.dbml`). Las unidades forman un árbol con una única unidad raíz que define la estructura formal de la organización. Los tipos de unidad forman una cadena lineal de niveles (por ejemplo, Gerencia General → Gerencia → Área → Departamento → Sección): el tipo de cada unidad lo calcula el sistema como el del nivel inmediatamente inferior al de su unidad padre. Las posiciones se asignan a estas unidades. Es la fuente única de la estructura de unidades y de la cadena de tipos para los demás módulos. El Administrador de Talento Humano lista (activas e inactivas), consulta, crea, edita (nombre y reubicación), desactiva (en cascada) y reactiva unidades, y gestiona la cadena de tipos (crear, renombrar y reordenar); los demás usuarios autenticados ven la cadena de tipos, listan solo las unidades activas y consultan cualquiera. Cada unidad tiene un código único generado por el sistema (UO-###). Las unidades y los tipos nunca se eliminan. Se audita quién creó y modificó cada unidad y cada tipo, y cada cambio queda en un historial."

## Clarifications

### Session 2026-10-14

- Q: Cuando la búsqueda encuentra una unidad cuyas unidades superiores no coinciden, ¿el listado debe mostrar también esas unidades superiores? → A: Sí (respuesta revisada el 2026-10-15 con el documento fuente actualizado, que reemplaza la respuesta anterior «solo las que coinciden»): se muestran las unidades que coinciden junto con las unidades de las que dependen, con sus ramas expandidas y su sangría.
- Q: Al crear una unidad, ¿el historial debe guardar un registro por cada dato con el valor anterior vacío? → A: Sí; al crear se registra cada dato inicial con valor (nombre, tipo, unidad padre y estado) con valor anterior vacío.
- Q: Cuando una persona con rol Usuario consulta una unidad, ¿debe ver también quién la creó y quién la modificó? → A: No; quién creó y quién modificó solo lo ve el Administrador en el formulario de edición; el resto ve datos, fechas y cantidades.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar el árbol de unidades y la cadena de tipos (Priority: P1)

Cualquier persona con sesión iniciada (Administrador o Usuario) abre las unidades organizacionales y ve, sobre el listado, la cadena de tipos de unidad ordenada por nivel, y debajo el árbol de unidades: cada unidad debajo de su unidad padre, con sangría según su nivel, y un control para expandir o contraer las ramas. Cada fila muestra el nombre, el código, el tipo y el estado. Busca por código, nombre o tipo de unidad. El Administrador además puede filtrar por estado.

**Why this priority**: Es el valor central de la feature: una fuente única y consultable de la estructura formal de la organización y de su cadena de tipos, que los demás módulos leen.

**Independent Test**: Con un árbol de varias unidades activas e inactivas, iniciar sesión como Usuario y como Administrador, abrir el listado, expandir y contraer ramas, buscar y filtrar; comprobar la jerarquía, el orden, la cadena de tipos, qué ve cada rol y que el Usuario no tiene acciones de gestión.

**Acceptance Scenarios**:

1. **Given** existen unidades activas e inactivas, **When** una persona autenticada abre las unidades, **Then** ve el árbol con solo la unidad raíz, contraída, y solo las unidades activas por defecto.
2. **Given** una unidad con unidades dependientes, **When** pulsa el control de su fila, **Then** su rama se expande o se contrae; los dependientes aparecen debajo de ella, con una sangría mayor que la suya, y las unidades de un mismo nivel quedan ordenadas por código ascendente.
3. **Given** el árbol visible, **When** pulsa "Expandir todo" o "Contraer todo", **Then** se expanden todas las ramas o vuelve a mostrarse solo la raíz.
4. **Given** el listado visible, **When** se muestra cada fila, **Then** presenta nombre (con sangría y el control de expandir si tiene dependientes), código, tipo de unidad como etiqueta, estado como etiqueta (Activa / Inactiva) y, solo para el Administrador, las acciones.
5. **Given** el listado visible, **When** se muestra la parte superior, **Then** aparece la cadena de tipos ordenada desde el tipo raíz (nivel 1) hacia abajo, con el número de nivel de cada tipo; el botón "Editar tipos" solo es visible para el Administrador.
6. **Given** el listado visible, **When** escribe parte de un código, de un nombre o de un tipo de unidad en la búsqueda, **Then** se muestran las unidades que coinciden junto con las unidades de las que dependen, con sus ramas expandidas y la sangría de su nivel, sin distinguir mayúsculas ni tildes (por ejemplo, "area" encuentra las de tipo "Área"), y mientras la búsqueda está activa las ramas no se pueden contraer.
7. **Given** ninguna unidad coincide con la búsqueda, **When** se muestra el listado, **Then** aparece el mensaje "Ninguna unidad coincide con la búsqueda."
8. **Given** el listado visible, **When** se muestra, **Then** indica la cantidad de unidades mostradas y cuántas de ellas están activas (o, al buscar, la cantidad de coincidencias) y no está paginado, de modo que el árbol no se parte.
9. **Given** una unidad cuyo tipo no sigue la cadena (su unidad padre no es del nivel inmediatamente superior), **When** se muestra su fila, **Then** aparece un ícono de advertencia junto a su tipo con la descripción "No sigue la cadena: bajo «tipo de la unidad padre» corresponde «tipo esperado»".
10. **Given** una persona con rol Usuario, **When** abre las unidades, **Then** solo ve unidades activas, ve la cadena de tipos, no ve el filtro de estado y no ve los botones Nuevo tipo, Editar tipos, Crear unidad, Editar, Agregar subunidad ni Desactivar / Reactivar.
11. **Given** una persona con rol Administrador, **When** abre las unidades, **Then** ve el filtro de estado y puede elegir ver activas (valor por defecto), inactivas o todas; las inactivas muestran la etiqueta Inactiva.
12. **Given** una unidad inactiva a la que otro registro hace referencia (por ejemplo, una posición histórica), **When** cualquier persona autenticada con acceso a ese registro consulta la unidad, **Then** puede ver sus datos completos, incluidos su tipo, su fecha de creación, su fecha de supresión y la cantidad de unidades dependientes y de posiciones asignadas, pero no quién la creó ni quién la modificó.
13. **Given** no hay unidades que mostrar o falla la carga, **When** se muestra el listado, **Then** aparece un estado vacío explicativo o un error con "Reintentar".

---

### User Story 2 - Crear una unidad (Priority: P1)

El Administrador crea una unidad indicando su nombre y su unidad padre; el sistema calcula su tipo (el del nivel inmediatamente inferior al de la unidad padre), le asigna un código único y la crea Activa, con la fecha del día como fecha de creación. La unidad raíz no se crea desde el módulo: existe desde la carga inicial del sistema.

**Why this priority**: Sin unidades creadas bajo la raíz no hay estructura completa ni se pueden crear posiciones.

**Independent Test**: Como Administrador, crear una unidad bajo la raíz con "Crear unidad" y otra con "Agregar subunidad" desde una fila; comprobar el tipo calculado, el código, el estado Activa, la fecha de creación y que aparecen en el árbol bajo su unidad padre.

**Acceptance Scenarios**:

1. **Given** el Administrador en el listado, **When** pulsa "Crear unidad", **Then** se abre un modal con el campo Nombre, el selector Unidad padre y un campo de solo lectura que indica el tipo con el que se creará; el estado y el tipo no se eligen.
2. **Given** el modal sin unidad padre elegida, **When** se muestra, **Then** el campo de tipo dice "Selecciona una unidad padre para determinar el tipo".
3. **Given** el modal, **When** elige una unidad padre, **Then** el campo de tipo se actualiza con el texto "Se creará como: «tipo»", donde «tipo» es el tipo del nivel inmediatamente inferior al de la unidad padre.
4. **Given** el modal, **When** abre el selector de unidad padre, **Then** ve las unidades en orden de árbol; las unidades inactivas y las del último nivel de la cadena aparecen deshabilitadas, y estas últimas con la descripción "Este tipo no admite subunidades".
5. **Given** el listado, **When** pulsa "Agregar subunidad" en la fila de una unidad activa que admite subunidades, **Then** se abre el mismo modal con esa unidad padre preseleccionada y el tipo ya resuelto.
6. **Given** la fila de una unidad inactiva o de una del último nivel de la cadena, **When** se muestra al Administrador, **Then** "Agregar subunidad" aparece deshabilitado y, en el último nivel, con la descripción "Este tipo no admite subunidades".
7. **Given** el modal, **When** completa un nombre válido y una unidad padre válida y guarda, **Then** la unidad se crea con un código generado por el sistema con el formato UO-### (por ejemplo, UO-003), el tipo calculado, estado Activa y la fecha del día como fecha de creación, aparece en el árbol debajo de su unidad padre y se muestra un mensaje de confirmación sobre el listado.
8. **Given** el modal, **When** falta el nombre o la unidad padre, o el nombre supera los 150 caracteres, **Then** se muestran errores junto al campo y no se guarda nada.
9. **Given** ya existe otra unidad con el mismo nombre, **When** el Administrador guarda, **Then** se acepta, porque el nombre no tiene que ser único.
10. **Given** una petición directa (sin la interfaz) sin unidad padre, o con una unidad padre inactiva, del último nivel, inexistente o con un tipo elegido por quien llama, **When** se intenta crear, **Then** se rechaza con un error en el campo correspondiente y no se guarda nada: el tipo nunca viene del cliente y no se puede crear otra unidad raíz.
11. **Given** un formulario con errores, **When** se corrige un campo, **Then** el resto de valores escritos se conserva.
12. **Given** una persona con rol Usuario, **When** intenta crear una unidad (por la interfaz o directamente), **Then** se le deniega por falta de permisos.

---

### User Story 3 - Editar una unidad y reubicarla (Priority: P2)

El Administrador modifica el nombre de una unidad o cambia su unidad padre, esté activa o inactiva. Cambiar la unidad padre reubica la unidad junto con toda su rama; los tipos de la rama no cambian y las posiciones asignadas conservan su unidad. El tipo, el código, las fechas y el estado no cambian por esta vía. La unidad raíz solo puede renombrarse.

**Why this priority**: Mantiene correcta la estructura sin recrear unidades ni perder las posiciones asignadas.

**Independent Test**: Editar una unidad con dependientes: cambiar su nombre y luego moverla bajo otra unidad del mismo tipo que su padre actual; comprobar que su rama se movió con ella, que el tipo, el código, las fechas y el estado son los mismos y que queda registrado quién la modificó. Editar la unidad raíz y comprobar que solo se puede cambiar su nombre.

**Acceptance Scenarios**:

1. **Given** una unidad existente (activa o inactiva), **When** el Administrador pulsa Editar, **Then** se abre un modal con el nombre y la unidad padre actuales y, en solo lectura, el tipo, el código, las fechas de creación y de supresión, la cantidad de unidades dependientes activas, la cantidad de posiciones Ocupadas o Vacantes asignadas y los datos de auditoría.
2. **Given** el modal de edición, **When** abre el selector de unidad padre, **Then** ofrece solo las unidades activas del tipo inmediatamente superior al de la unidad, sin incluir la propia unidad ni ninguna de sus unidades dependientes.
3. **Given** el modal de edición de la unidad raíz, **When** se muestra, **Then** el selector de unidad padre está deshabilitado y muestra "Ninguna (unidad raíz)"; solo puede cambiarse el nombre.
4. **Given** el modal de edición, **When** cambia valores válidos y guarda, **Then** los cambios se reflejan en el árbol, el tipo, el código, las fechas y el estado permanecen iguales, se registra quién la modificó y cuándo y se muestra un mensaje de confirmación.
5. **Given** una unidad con unidades dependientes, **When** el Administrador cambia su unidad padre y guarda, **Then** la unidad aparece en su nueva posición del árbol con toda su rama debajo, los tipos de la rama no cambian y las posiciones asignadas a ella y a su rama conservan su unidad.
6. **Given** una petición directa (sin la interfaz), **When** intenta usar como unidad padre la propia unidad, una de sus dependientes, una unidad inactiva, una del último nivel o una cuyo tipo no sea el inmediatamente superior al de la unidad, o reubicar la unidad raíz, o cambiar su tipo, **Then** se rechaza con un error en el campo correspondiente y no se guarda nada.
7. **Given** el modal de edición, **When** deja vacío el nombre o supera los 150 caracteres, **Then** se muestran errores junto al campo y no se guarda.
8. **Given** el Administrador abre la edición y no cambia ningún valor, **When** guarda, **Then** no se actualiza quién la modificó ni cuándo, y no se añaden registros al historial.
9. **Given** dos administradores abren la edición de la misma unidad, **When** el segundo guarda después de que el primero ya guardó, **Then** el segundo guardado se rechaza con un aviso de que la unidad cambió y se pide recargar.
10. **Given** una persona con rol Usuario, **When** intenta editar (por la interfaz o directamente), **Then** se le deniega por falta de permisos.

---

### User Story 4 - Desactivar en cascada y reactivar una unidad (Priority: P2)

El Administrador desactiva una unidad que ya no forma parte de la estructura vigente: se desactivan con ella todas sus unidades dependientes activas. También puede reactivar una unidad inactiva, una por una. Las unidades nunca se eliminan, para preservar históricos y auditoría. Una rama no puede desactivarse mientras la unidad o alguna de sus dependientes activas tenga posiciones Ocupadas o Vacantes, y una unidad solo puede reactivarse si su unidad padre está activa. La unidad raíz no se puede desactivar.

**Why this priority**: Retira del uso las ramas obsoletas sin perder las posiciones históricas que las referencian, y mantiene la estructura coherente (nada activo cuelga de algo inactivo).

**Independent Test**: Desactivar una hoja sin posiciones (con confirmación) y comprobar que sale del listado por defecto y recibe fecha de supresión; desactivar una unidad con dependientes activas y comprobar que la confirmación las lista y que todas quedan inactivas; intentar desactivar una rama con posiciones y comprobar el bloqueo; reactivar una unidad y comprobar que sus dependientes siguen inactivas; intentar reactivar una cuyo padre está inactivo.

**Acceptance Scenarios**:

1. **Given** una unidad activa sin unidades dependientes activas ni posiciones Ocupadas o Vacantes, **When** el Administrador pulsa Desactivar, **Then** se abre un diálogo de confirmación que nombra la unidad.
2. **Given** una unidad activa con unidades dependientes activas, **When** el Administrador pulsa Desactivar, **Then** el diálogo lista las unidades dependientes que también se desactivarán y su botón de confirmación indica cuántas unidades se desactivarán en total.
3. **Given** el diálogo de confirmación, **When** confirma, **Then** la unidad y todas sus dependientes activas, directas e indirectas, pasan a Inactiva, cada una con la fecha del día como fecha de supresión, desaparecen del listado por defecto y se muestra un mensaje de confirmación; si cancela, no cambia nada.
4. **Given** una unidad activa cuya rama (ella misma o alguna de sus dependientes activas) tiene posiciones Ocupadas o Vacantes, **When** el Administrador pulsa Desactivar, **Then** el diálogo explica el bloqueo, indica cuántas posiciones hay y en qué unidades, y no permite confirmar.
5. **Given** una rama con posiciones Ocupadas o Vacantes, **When** se intenta desactivar directamente sin la interfaz, **Then** la acción se rechaza, ninguna unidad de la rama cambia (todas siguen Activas) y se indica cuántas posiciones hay y en qué unidades.
6. **Given** una rama cuyas únicas posiciones están Suprimidas y cuyas dependientes inactivas no se ven afectadas, **When** el Administrador la desactiva, **Then** la desactivación procede, porque las posiciones Suprimidas y las unidades ya inactivas no la impiden ni se vuelven a desactivar.
7. **Given** la fila de la unidad raíz, **When** se muestra al Administrador, **Then** el botón Desactivar aparece deshabilitado con la descripción "La unidad raíz no se puede desactivar"; un intento directo se rechaza.
8. **Given** una unidad inactiva cuya unidad padre está activa, **When** el Administrador pulsa Reactivar, **Then** pasa a Activa de inmediato, sin pedir confirmación, y su fecha de supresión se borra.
9. **Given** una unidad inactiva cuya unidad padre está inactiva, **When** se muestra su fila al Administrador, **Then** el botón Reactivar aparece deshabilitado con la descripción "Reactiva primero la unidad padre"; un intento directo se rechaza, la unidad sigue Inactiva y se indica el motivo.
10. **Given** una unidad con unidades dependientes inactivas, **When** se reactiva, **Then** las dependientes siguen inactivas hasta que se reactiven una a una: reactivar no reactiva ramas.
11. **Given** una unidad inactiva, **When** el Administrador la consulta o la edita, **Then** puede hacerlo normalmente y se indica visualmente con la etiqueta Inactiva; no puede recibir nuevas posiciones ni nuevas unidades dependientes.
12. **Given** el listado, **When** se muestra una unidad al Administrador, **Then** el botón alterna su texto: dice Desactivar si está activa y Reactivar si está inactiva.
13. **Given** una persona con rol Usuario, **When** intenta desactivar o reactivar (por la interfaz o directamente), **Then** se le deniega por falta de permisos.

---

### User Story 5 - Gestionar la cadena de tipos de unidad (Priority: P2)

El Administrador agrega tipos al final de la cadena, renombra cualquier tipo y cambia el nivel de los tipos que no son el tipo raíz. Los renombres y los cambios de nivel se preparan juntos, se revisan en una vista previa y se aplican al guardar en una sola operación. Si el nuevo orden dejaría una unidad bajo una unidad padre de nivel igual o más profundo que el suyo, no se aplica ningún cambio y se muestra qué unidades lo impiden.

**Why this priority**: La cadena de tipos determina el tipo de cada unidad nueva y las reglas de la jerarquía; sin poder ajustarla, la organización queda atada a la estructura de la carga inicial.

**Independent Test**: Agregar un tipo nuevo y comprobar que queda como último nivel y que las unidades del antiguo último nivel admiten subunidades; renombrar un tipo y comprobar que el nombre cambia en todas sus unidades; mover un tipo sin unidades afectadas y comprobar la vista previa y el guardado; intentar un orden que deje una unidad bajo una padre de nivel igual o más profundo y comprobar el bloqueo con su lista.

**Acceptance Scenarios**:

1. **Given** el Administrador en el listado, **When** pulsa "Nuevo tipo", **Then** se abre un modal con el campo "Nombre del tipo" que indica que se agregará al final de la cadena, como tipo hijo del último nivel actual.
2. **Given** el modal de nuevo tipo, **When** guarda un nombre válido, **Then** el tipo queda como último nivel de la cadena, las unidades del nivel que antes era el último pasan a admitir subunidades y se muestra un mensaje de confirmación.
3. **Given** el modal de nuevo tipo o el de edición de tipos, **When** el nombre está vacío, supera los 50 caracteres o coincide con el de otro tipo sin distinguir mayúsculas ni tildes, **Then** se muestra un error junto al campo y no se guarda nada.
4. **Given** el Administrador en el listado, **When** pulsa "Editar tipos", **Then** se abre un modal con la lista de tipos ordenada por nivel, donde cada tipo permite editar su nombre y, salvo el tipo raíz, cambiar de nivel con un asa de arrastre o con las flechas subir y bajar.
5. **Given** el modal de edición de tipos, **When** se muestra el tipo raíz, **Then** aparece con un ícono de candado, sin asa ni flechas y con la descripción "El tipo raíz solo puede renombrarse"; ningún otro tipo puede colocarse por encima de él.
6. **Given** el modal de edición de tipos, **When** cambia nombres y niveles, **Then** los demás tipos se reacomodan para que la cadena siga continua, sin huecos, y se muestra una vista previa con la cadena actual y la resultante, resaltando los tipos que cambian de nivel o de nombre.
7. **Given** el modal de edición de tipos sin cambios, **When** se muestra, **Then** el botón "Guardar cambios" está deshabilitado.
8. **Given** cambios preparados que no dejan ninguna unidad bajo una unidad padre de nivel igual o más profundo que el suyo, **When** guarda, **Then** todos los renombres y cambios de nivel se aplican a la vez, el cambio de nombre se refleja en todas las unidades de ese tipo y se muestra un mensaje de confirmación.
9. **Given** cambios preparados tras los cuales alguna unidad quedaría bajo una unidad padre cuyo tipo tendría, en la cadena resultante, un nivel igual o más profundo que el del tipo de la propia unidad, **When** guarda, **Then** no se aplica ningún cambio y se abre un modal de bloqueo con la cantidad de unidades afectadas y, por cada una, su tipo y nivel nuevos y su unidad padre con su tipo y nivel nuevos.
10. **Given** el modal de bloqueo, **When** pulsa "Ver en el árbol" en una unidad afectada, **Then** esa unidad se muestra en el listado; el modal también permite volver a editar el borrador sin perderlo.
11. **Given** cambios preparados tras los cuales una unidad deja de estar bajo una unidad padre del nivel inmediatamente superior pero el nivel de su unidad padre sigue siendo menor que el suyo (por ejemplo, al insertar un tipo nuevo entre ambos), **When** guarda, **Then** el cambio se permite y la unidad se marca en el listado con la advertencia "No sigue la cadena".
12. **Given** un cambio de nivel aplicado, **When** se consultan las unidades existentes, **Then** ninguna cambió de tipo: solo cambió el orden de la cadena y, con él, el tipo que se calcula para las unidades nuevas.
13. **Given** el catálogo de tipos, **When** se busca una acción para eliminar un tipo, **Then** no existe.
14. **Given** una persona con rol Usuario, **When** intenta crear, renombrar o reordenar tipos (por la interfaz o directamente), **Then** se le deniega por falta de permisos; puede ver la cadena de tipos.
15. **Given** dos administradores abren la edición de la cadena de tipos, **When** el segundo guarda después de que el primero ya guardó, **Then** el segundo guardado se rechaza con un aviso de que la cadena cambió y se pide recargar.

---

### User Story 6 - Trazabilidad de los cambios (Priority: P3)

El sistema registra quién creó cada unidad y cada tipo y cuándo, quién los modificó por última vez y cuándo, y cada cambio en un historial con el campo afectado, el valor anterior, el valor nuevo, el usuario y la fecha: en las unidades, el nombre, la unidad padre (reubicaciones), el estado y la fecha de supresión; en los tipos, el nombre y el nivel.

**Why this priority**: Garantiza auditoría de una estructura que cambia con el tiempo y cuyas unidades y tipos nunca se eliminan; ni la unidad ni el tipo tienen versionado, así que el historial es el único registro del detalle de los cambios.

**Independent Test**: Crear una unidad, cambiar su nombre, reubicarla, desactivarla en cascada y reactivarla, y renombrar y reordenar tipos con distintos administradores, e inspeccionar la auditoría para comprobar que refleja cada acción.

**Acceptance Scenarios**:

1. **Given** el Administrador crea una unidad, **When** se guarda, **Then** queda registrado quién la creó y cuándo, y el historial recibe un registro por cada dato inicial con valor (nombre, tipo, unidad padre y estado), con el valor anterior vacío.
2. **Given** una unidad existente, **When** un administrador la edita cambiando algún dato, **Then** queda registrado quién la modificó por última vez y cuándo, sin alterar quién la creó.
3. **Given** un administrador cambia el nombre o la unidad padre de una unidad, **When** guarda, **Then** el historial recibe un registro por cada campo cambiado, con el valor anterior, el valor nuevo, el usuario y la fecha.
4. **Given** una unidad con dependientes se reubica, **When** se guarda, **Then** el historial registra el cambio de unidad padre solo de la unidad movida; las unidades de su rama no reciben registros porque su unidad padre no cambió.
5. **Given** una unidad se desactiva en cascada, **When** se confirma, **Then** el historial recibe, por cada unidad desactivada, un registro del estado (anterior y nuevo) y otro de la fecha de supresión (asignada), con el usuario y la fecha; al reactivar una unidad recibe los registros inversos, y los registros anteriores se conservan.
6. **Given** el Administrador crea un tipo, **When** se guarda, **Then** queda registrado quién lo creó y cuándo, y el historial recibe un registro de su nombre y otro de su nivel, con el valor anterior vacío.
7. **Given** un administrador renombra o reordena tipos, **When** guarda, **Then** queda registrado quién modificó cada tipo cambiado y cuándo, y el historial recibe un registro por cada cambio de nombre y por cada tipo que cambió de nivel.
8. **Given** el historial de una unidad o de un tipo, **When** se consulta, **Then** los registros no se pueden modificar ni borrar desde la aplicación.

---

### Edge Cases

- Se intenta crear una unidad sin unidad padre (por ejemplo, directamente sin la interfaz): se rechaza; la única unidad raíz es la de la carga inicial.
- Se elige como unidad padre una unidad inactiva, del último nivel, inexistente, o que dejó de admitir subunidades porque otra persona cambió la cadena mientras el formulario estaba abierto: al guardar se rechaza con un error en el campo Unidad padre.
- Dos administradores crean unidades a la vez: cada una recibe un código distinto; si crean bajo el mismo padre, ambas se guardan.
- Se crea o se reubica una unidad bajo una unidad padre que otra persona está desactivando: la verificación y el cambio de estado se resuelven como una sola operación; si la unidad nueva llegó primero, se desactiva junto con las demás de la rama; si la desactivación llegó primero, la creación o reubicación se rechaza porque el padre ya no está activo.
- Una posición Vacante se asigna a una unidad de la rama justo mientras se desactiva: se resuelve como una sola operación; si la posición llegó primero, la desactivación se rechaza e indica cuántas posiciones hay y en qué unidades.
- Dos administradores desactivan o cambian el estado de unidades de la misma rama a la vez: no se registran cambios repetidos; las unidades que ya estaban inactivas no se vuelven a desactivar ni reciben registros nuevos.
- Dos administradores reubican a la vez unidades de modo que, juntas, violarían una regla del árbol (un ciclo, un padre inactivo o un tipo que no corresponde): solo se guarda la primera; la segunda se rechaza porque las reglas se comprueban contra el estado vigente al guardar.
- Dos administradores editan a la vez la misma unidad, o la cadena de tipos: el segundo guardado se rechaza con un aviso de que cambió y se pide recargar; no se sobrescribe en silencio.
- Nombre de unidad o de tipo con solo espacios: se trata como vacío. Nombre de unidad de más de 150 caracteres o de tipo de más de 50: se rechaza con un error en el campo y el formulario indica el límite.
- Varias unidades con el mismo nombre (incluso del mismo tipo y bajo el mismo padre): son válidas; se distinguen por su código. Dos tipos con el mismo nombre, sin distinguir mayúsculas ni tildes, no son válidos, tampoco entre dos renombres del mismo borrador.
- Se edita una unidad que no sigue la cadena, o una inactiva cuya unidad padre está inactiva, y no se toca la unidad padre: se guarda y conserva su unidad padre actual; para elegir otra solo se ofrecen unidades activas del tipo inmediatamente superior al de la unidad.
- Se edita la unidad raíz: solo se puede cambiar su nombre; su unidad padre no se puede elegir.
- Se agrega un tipo nuevo: las unidades del antiguo último nivel pasan a admitir subunidades y su "Agregar subunidad" se habilita.
- Un reordenamiento hace que una unidad deje de estar bajo una unidad padre del nivel inmediatamente superior, aunque el nivel de su padre sigue siendo menor que el suyo: se permite y la unidad se marca con "No sigue la cadena"; sus subunidades nuevas toman el tipo del nivel siguiente al de esa unidad.
- Un reordenamiento mueve un tipo que ninguna unidad usa, o solo renombra tipos: no puede dejar unidades en conflicto y se aplica sin bloqueo.
- Un reordenamiento se rechaza: la cadena y todas las unidades quedan exactamente como estaban y el borrador se conserva para seguir editándolo; "Ver en el árbol" muestra la unidad afectada aunque esté inactiva o dentro de una rama contraída.
- Se cierra el editor de tipos con cambios sin guardar: el borrador se descarta; no se aplica nada.
- Se desactiva una rama grande (hasta unas 500 unidades): se hace en una sola operación, todo o nada.
- Se supera el número 999 en el código: el código sigue creciendo (UO-1000) sin reutilizar ni repetir ninguno.
- Se pide una unidad que no existe: se informa que no existe.
- Un Usuario abre directamente la dirección de una unidad inactiva a la que tiene acceso por un registro histórico: puede consultarla, pero no aparece en su listado.
- El Administrador usa el filtro "Inactivas" y no hay ninguna: se muestra el estado vacío con la indicación de cambiar el filtro. Con ese filtro, las unidades inactivas se muestran junto con las unidades de las que dependen (aunque estén activas), para conservar el árbol.
- Sin sesión iniciada no se accede a las unidades ni a la cadena de tipos (feature 000-login).

## Requirements *(mandatory)*

### Functional Requirements

**Permisos y acceso**

- **FR-001**: El sistema MUST permitir al Administrador de Talento Humano ver la cadena de tipos; listar (unidades activas e inactivas), consultar, crear, editar (nombre y reubicación), desactivar y reactivar unidades; y crear, renombrar y reordenar tipos de unidad.
- **FR-002**: El sistema MUST permitir a cualquier otra persona autenticada ver la cadena de tipos, consultar una unidad (activa o inactiva) y listar solo las unidades activas, y MUST denegarle crear, editar, desactivar y reactivar unidades y crear, renombrar y reordenar tipos.
- **FR-003**: Toda operación MUST requerir sesión iniciada y el sistema MUST verificar el rol en cada petición, sin depender solo de lo que muestre la interfaz; en particular, un Usuario MUST NOT poder obtener unidades inactivas en el listado aunque lo pida directamente.

**Tipos de unidad y cadena**

- **FR-004**: Cada tipo de unidad MUST tener un nombre obligatorio de máximo 50 caracteres, único sin distinguir mayúsculas ni tildes; un texto con solo espacios se trata como vacío.
- **FR-005**: Cada tipo MUST tener un nivel. Los niveles MUST ir de 1 a N, ser únicos, continuos y sin huecos. La cadena MUST ser lineal: cada tipo tiene exactamente un tipo hijo —el del nivel inmediatamente inferior— salvo el último, cuyas unidades MUST NOT poder tener unidades hijas.
- **FR-006**: El tipo raíz MUST ocupar siempre el nivel 1 y solo MUST poder renombrarse; ningún otro tipo MUST poder colocarse por encima de él.
- **FR-007**: Los tipos MUST NOT poder eliminarse: no existe la acción de eliminar un tipo.
- **FR-008**: Un tipo nuevo MUST agregarse al final de la cadena, como último nivel; desde ese momento las unidades del nivel que antes era el último MUST poder tener unidades hijas.
- **FR-009**: Cualquier tipo, incluido el tipo raíz, MUST poder renombrarse, y el cambio MUST reflejarse en todas las unidades de ese tipo.
- **FR-010**: Un tipo que no sea el tipo raíz MUST poder cambiar de nivel; los demás tipos MUST reacomodarse para que la cadena siga continua, sin huecos.
- **FR-011**: Los renombres y los cambios de nivel MUST prepararse juntos y aplicarse al guardar en una sola operación (todos o ninguno). Antes de guardar, el sistema MUST mostrar una vista previa con la cadena actual y la resultante, resaltando los tipos que cambian de nivel o de nombre.
- **FR-012**: El sistema MUST rechazar un reordenamiento si alguna unidad quedaría bajo una unidad padre cuyo tipo tenga, en la cadena resultante, un nivel igual o más profundo (número de nivel mayor o igual) que el de su propio tipo. En ese caso MUST NOT aplicar ningún cambio y MUST indicar cuántas unidades se verían afectadas, con la lista de cada unidad, su tipo y nivel nuevos, y su unidad padre con su tipo y nivel nuevos.
- **FR-013**: Si en la cadena resultante una unidad deja de estar bajo una unidad padre del nivel inmediatamente superior pero el nivel de esa unidad padre sigue siendo menor que el suyo, el sistema MUST permitir el cambio y MUST marcar la unidad como "No sigue la cadena".
- **FR-014**: Cambiar el nivel de un tipo MUST NOT modificar el tipo de ninguna unidad existente: solo cambia el orden de la cadena y, con él, el tipo que se calcula al crear unidades nuevas.
- **FR-015**: Al guardar la edición de la cadena, el sistema MUST rechazar el guardado si la cadena cambió desde que se abrió la edición, con un aviso que pida recargar.

**Datos de la unidad**

- **FR-016**: Cada unidad MUST tener un código único generado por el sistema al crearla, con el formato `UO-` seguido de un número secuencial de al menos 3 dígitos (por ejemplo, `UO-003`), que MUST NOT poder modificarse nunca ni reutilizarse.
- **FR-017**: Cada unidad MUST tener un nombre obligatorio de máximo 150 caracteres; un texto con solo espacios se trata como vacío. El nombre MUST NOT tener que ser único.
- **FR-018**: El tipo de una unidad MUST NOT elegirse: lo calcula el sistema al crearla, como el tipo del nivel inmediatamente inferior al de su unidad padre. MUST mostrarse en solo lectura y MUST NOT cambiar al editar ni al reubicar la unidad; el sistema MUST rechazar cualquier tipo enviado por quien llama.
- **FR-019**: Cada unidad, salvo la raíz, MUST tener una unidad padre. El sistema MUST aceptar solo una unidad padre activa y cuyo tipo admita hijas (que no sea del último nivel), y MUST rechazar la propia unidad y cualquiera de sus unidades dependientes, directas o indirectas, de modo que el árbol nunca contenga ciclos. Al reubicar una unidad, la nueva unidad padre MUST ser del tipo inmediatamente superior al de la unidad, para que la rama conserve sus tipos.
- **FR-019 (ampliación 2026-10-16, desde 006-posiciones)**: Reubicar una unidad (cambiar su unidad padre) MUST rechazarse si, en el árbol resultante, alguna posición vigente de la rama movida o cuyo supervisor esté en ella dejaría de tener a su supervisora en la misma unidad o en una unidad superior (FR-007 y FR-038 de 006). El rechazo se muestra junto al campo Unidad padre e indica cuántas posiciones lo impiden. Las posiciones Suprimidas no cuentan.
- **FR-020**: MUST existir exactamente una unidad raíz, de tipo raíz, creada con la carga inicial del sistema. El módulo MUST NOT permitir crear otra unidad raíz, desactivar la raíz ni reubicarla; sí MUST permitir renombrarla.
- **FR-021**: Cada unidad MUST tener un estado, Activa o Inactiva; toda unidad nueva MUST crearse Activa. El estado MUST NOT elegirse en ningún formulario: solo cambia con Desactivar / Reactivar.
- **FR-022**: Cada unidad MUST tener una fecha de creación, asignada por el sistema con la fecha del día en que se crea, que MUST NOT poder editarse. Cada unidad MUST tener una fecha de supresión, asignada por el sistema con la fecha del día en que se desactiva y borrada al reactivarla, de solo lectura.
- **FR-023**: El sistema MUST mostrar, como datos de solo lectura obtenidos consultando y sin almacenarlos, la cantidad de unidades dependientes activas de la unidad (directas e indirectas) y la cantidad de posiciones Ocupadas o Vacantes asignadas a ella.

**Ciclo de vida**

- **FR-024**: El sistema MUST NOT permitir eliminar unidades: no existe la acción de eliminar y la única forma de retirarlas del uso es desactivarlas.
- **FR-025**: Desactivar una unidad MUST desactivar también todas sus unidades dependientes activas, directas e indirectas, en una sola operación (todas o ninguna), asignando a cada una la fecha del día como fecha de supresión. Las unidades que ya estaban inactivas MUST NOT modificarse.
- **FR-026**: Antes de desactivar, el sistema MUST pedir una confirmación accesible que nombre la unidad. Si la unidad tiene dependientes activas, la confirmación MUST listar las unidades que también se desactivarán e indicar en su botón cuántas unidades se desactivarán en total. Reactivar MUST NOT pedir confirmación.
- **FR-027**: El sistema MUST rechazar desactivar si la unidad o alguna de sus dependientes activas tiene posiciones Ocupadas o Vacantes: MUST conservar todas las unidades Activas e indicar cuántas posiciones hay y en qué unidades. Las posiciones Suprimidas y las unidades dependientes ya inactivas MUST NOT impedir la desactivación. La confirmación MUST explicar el bloqueo y MUST NOT permitir confirmar.
- **FR-028**: El sistema MUST permitir reactivar una unidad inactiva solo si su unidad padre está activa, unidad por unidad. Si no se cumple, MUST rechazar la acción, conservar el estado Inactiva e indicar el motivo. Al reactivar, MUST borrar la fecha de supresión y MUST NOT reactivar sus unidades dependientes.
- **FR-029**: Una unidad activa MUST tener siempre activas a todas las unidades de las que depende. Ninguna operación (crear, editar, reubicar, desactivar o reactivar) MUST dejar una unidad activa bajo una unidad inactiva.
- **FR-030**: Una unidad inactiva MUST conservarse, seguir siendo consultable y poder editarse. MUST NOT poder recibir nuevas posiciones ni nuevas unidades dependientes; las posiciones y unidades que ya dependen de ella MUST conservarse. Se MUST indicar visualmente con la etiqueta Inactiva.
- **FR-031**: Cambiar la unidad padre de una unidad MUST mover con ella a toda su rama de unidades dependientes; los tipos de la rama MUST NOT cambiar y las posiciones asignadas MUST conservar su unidad. Las reglas del árbol (sin ciclos, padre activo, tipo inmediatamente superior, una sola raíz) MUST comprobarse contra el estado vigente en el momento de guardar, de forma que cambios simultáneos no puedan dejar el árbol inválido.

**Listado**

- **FR-032**: El listado MUST mostrarse como un árbol: cada unidad debajo de su unidad padre, con sangría según su nivel, con las unidades de un mismo nivel ordenadas por código ascendente. MUST mostrar nombre, código, tipo de unidad (etiqueta), estado (etiqueta Activa / Inactiva) y, solo al Administrador, las acciones.
- **FR-033**: Las unidades con dependientes MUST tener un control para expandir y contraer su rama. Al entrar, el árbol MUST mostrar solo la unidad raíz, contraída. MUST haber acciones "Expandir todo" y "Contraer todo".
- **FR-034**: Sobre el listado MUST mostrarse la cadena de tipos, ordenada desde el tipo raíz (nivel 1) hacia abajo y con el número de nivel de cada tipo, visible para todos los usuarios.
- **FR-035**: El listado MUST permitir buscar por código, nombre o tipo de unidad, sin distinguir mayúsculas ni tildes. Al buscar, MUST mostrar las unidades que coinciden junto con las unidades de las que dependen, con sus ramas expandidas y su sangría; mientras haya una búsqueda activa, las ramas MUST NOT poder contraerse. Si ninguna unidad coincide, MUST mostrar el mensaje "Ninguna unidad coincide con la búsqueda."
- **FR-036**: El listado MUST mostrar por defecto solo las unidades activas, para todos los roles. El Administrador MUST disponer de un filtro por estado (activas —valor por defecto—, inactivas o todas). El filtro MUST NOT mostrarse ni aplicarse a quien no sea Administrador: ese rol solo recibe unidades activas. Búsqueda y filtro MUST conservarse en la dirección de la página.
- **FR-037**: El listado MUST NOT paginarse, para no partir el árbol, y MUST mostrar la cantidad de unidades mostradas según el filtro y cuántas de ellas están activas o, al buscar, la cantidad de coincidencias.
- **FR-038**: Las unidades que no siguen la cadena MUST mostrar un ícono de advertencia junto al tipo, con la descripción "No sigue la cadena: bajo «tipo de la unidad padre» corresponde «tipo esperado»".
- **FR-039**: El listado MUST cubrir los estados cargando, vacío, error con reintento y éxito. El listado MUST NOT tener panel de detalle: los datos principales están en sus columnas.

**Acciones del Administrador y formularios**

- **FR-040**: Los botones "Nuevo tipo", "Editar tipos" y "Crear unidad" MUST ser visibles solo para el Administrador. En cada fila, los botones Editar, Agregar subunidad y Desactivar / Reactivar MUST ser visibles solo para el Administrador; el botón de estado MUST alternar su texto según el estado actual.
- **FR-041**: "Agregar subunidad" MUST mostrarse deshabilitado en las unidades inactivas y en las del último nivel de la cadena; en este último caso, con la descripción "Este tipo no admite subunidades". "Desactivar" MUST mostrarse deshabilitado en la unidad raíz, con la descripción "La unidad raíz no se puede desactivar". "Reactivar" MUST mostrarse deshabilitado si la unidad padre está inactiva, con la descripción "Reactiva primero la unidad padre".
- **FR-042**: "Crear unidad" MUST abrir un modal con Nombre, un selector de Unidad padre con las unidades en orden de árbol (las inactivas y las del último nivel deshabilitadas; estas últimas con la descripción "Este tipo no admite subunidades") y un campo de solo lectura con el tipo de la nueva unidad, que se actualiza al elegir la unidad padre ("Se creará como: «tipo»") o, sin unidad padre, indica "Selecciona una unidad padre para determinar el tipo". Al abrirlo desde "Agregar subunidad", la unidad padre MUST venir preseleccionada y el tipo ya resuelto.
- **FR-043**: "Editar" MUST abrir un modal con Nombre y un selector de Unidad padre limitado a las unidades activas del tipo inmediatamente superior al de la unidad, sin la propia unidad ni sus dependientes; en la unidad raíz, el selector MUST estar deshabilitado y mostrar "Ninguna (unidad raíz)". MUST mostrar además, en solo lectura, el tipo, el código, las fechas de creación y de supresión, la cantidad de unidades dependientes activas, la cantidad de posiciones asignadas y los datos de auditoría. Si la unidad ya tiene una unidad padre que el selector no ofrece (porque no sigue la cadena o está inactiva), MUST conservarla seleccionada mientras no se cambie.
- **FR-044**: "Nuevo tipo" MUST abrir un modal con el campo "Nombre del tipo" que indique que se agregará al final de la cadena, como tipo hijo del último nivel actual.
- **FR-045**: "Editar tipos" MUST abrir un modal con la lista de tipos ordenada por nivel. Cada tipo MUST permitir editar su nombre y, salvo el tipo raíz, cambiar de nivel con un asa de arrastre o con las acciones subir y bajar. El tipo raíz MUST mostrar un ícono de candado, sin asa ni flechas, con la descripción "El tipo raíz solo puede renombrarse". El modal MUST incluir la vista previa de la cadena actual y la resultante, y "Guardar cambios" MUST estar deshabilitado mientras no haya cambios.
- **FR-046**: Si el reordenamiento se rechaza (FR-012), el sistema MUST abrir un modal de bloqueo con la cantidad y la lista de unidades afectadas. Cada unidad MUST tener la acción "Ver en el árbol", que la muestra en el listado, y el modal MUST permitir volver a editar el borrador.
- **FR-047**: Tras cada acción exitosa, el sistema MUST mostrar un mensaje de confirmación sobre el listado. MUST mostrar los errores de validación junto al campo correspondiente, conservar los valores escritos cuando la validación falla e impedir envíos duplicados mientras un guardado o cambio de estado está en curso.
- **FR-048**: Al editar una unidad, el sistema MUST rechazar el guardado si la unidad cambió desde que se abrió el formulario, con un aviso que pida recargar para ver la versión actual.

**Auditoría**

- **FR-049**: El sistema MUST registrar quién creó cada unidad y cada tipo y cuándo, y quién los modificó por última vez y cuándo; los datos de usuario de las unidades MUST mostrarse solo al Administrador (en el formulario de edición) y MUST NOT devolverse al resto de roles al consultar una unidad. Guardar una edición sin cambios reales MUST NOT actualizar la última modificación ni añadir registros al historial.
- **FR-050**: El sistema MUST registrar cada cambio de una unidad en un historial con el campo afectado, el valor anterior, el valor nuevo, el usuario y la fecha. Esto MUST incluir la creación (un registro por cada dato inicial con valor —nombre, tipo, unidad padre y estado—, con el valor anterior vacío), y después los cambios de nombre, unidad padre (reubicaciones), estado y fecha de supresión. Reubicar una unidad MUST registrar el cambio solo en la unidad movida; una desactivación en cascada MUST registrar el cambio en cada unidad desactivada.
- **FR-051**: El sistema MUST registrar cada cambio de un tipo en el historial con el campo afectado (nombre o nivel), el valor anterior, el valor nuevo, el usuario y la fecha; la creación de un tipo registra su nombre y su nivel con valor anterior vacío, y un reordenamiento registra el cambio de nivel de cada tipo que se movió.
- **FR-052**: Los registros del historial MUST conservarse y MUST NOT poder modificarse ni borrarse desde la aplicación.

**Relación con otros módulos**

- **FR-053**: Este módulo MUST ser la fuente única de la estructura de unidades y de la cadena de tipos: ningún módulo mantiene su propia copia, y los módulos que consumen unidades o tipos (por ejemplo, posiciones y organigrama) MUST leerlos por un contrato público de solo lectura sin poder modificarlos.
- **FR-054**: Para nuevas asignaciones, los demás módulos MUST poder usar solo unidades activas (nuevas posiciones y nuevas unidades dependientes); las referencias existentes a unidades inactivas MUST conservarse y seguir siendo consultables para cualquier persona con acceso a esos registros. Las posiciones Suprimidas conservan su unidad como dato histórico (la regla de desactivación está en FR-027).
- **FR-055**: La unidad y el tipo MUST NOT tener versionado: los cambios de nombre, de unidad padre o del nombre de su tipo MUST aplicar a todas sus posiciones y el detalle del cambio queda en el historial de auditoría.

**Experiencia y accesibilidad**

- **FR-056**: Toda la interfaz MUST estar en español y cumplir WCAG 2.2 AA entre 320 y 1440 px de ancho, sin desplazamiento horizontal de la página; la jerarquía del árbol MUST poder percibirse sin depender solo de la sangría ni del color; los modales MUST ser accesibles con teclado (foco atrapado, cierre con Escape y foco devuelto a quien los abrió) y cambiar el nivel de un tipo MUST poder hacerse sin arrastrar, con las acciones subir y bajar.

**Fuera de alcance**: la gestión de posiciones y el organigrama, la asignación de personas a posiciones, crear otra unidad raíz desde el módulo, cambiar el tipo de unidades concretas para resolver un bloqueo de reordenamiento, suprimir o reasignar posiciones como parte de una desactivación en cascada, la pantalla para consultar el historial de cambios (el historial se registra, pero esta feature no define cómo mostrarlo), el versionado de unidades y de tipos, la importación o exportación masiva y la eliminación física de unidades y de tipos.

### Key Entities *(include if feature involves data)*

- **Unidad organizacional**: nodo del árbol de la estructura formal; tiene código único e inmutable (UO-###), nombre, tipo de unidad (calculado al crearla), unidad padre (obligatoria salvo en la raíz), estado propio (Activa o Inactiva), fecha de creación y fecha de supresión (solo mientras está inactiva); guarda quién la creó y cuándo, y quién la modificó por última vez y cuándo. Nunca se elimina.
- **Tipo de unidad**: eslabón de la cadena lineal de niveles; tiene nombre único y nivel (1 = tipo raíz, continuo hasta N); guarda quién lo creó y cuándo, y quién lo modificó por última vez y cuándo. Nunca se elimina. Este módulo lo gestiona; los demás lo leen.
- **Cadena de tipos**: el conjunto ordenado de los tipos por nivel; determina el tipo de cada unidad nueva y qué unidades admiten subunidades (todas menos las del último nivel).
- **Posición** *(externa, estructura organizacional)*: plaza asignada a una unidad, con estado Ocupada, Vacante o Suprimida; determina si una unidad o su rama puede desactivarse.
- **Registro de cambios**: entrada inmutable del historial con la entidad (unidad o tipo), el campo afectado, el valor anterior, el valor nuevo, el usuario y la fecha.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El Administrador crea una unidad (nombre y unidad padre) en menos de 1 minuto.
- **SC-002**: Una persona encuentra una unidad por código, nombre o tipo en menos de 15 segundos.
- **SC-003**: El árbol completo de hasta 500 unidades, con la cadena de tipos, se muestra en una sola vista, sin paginar, y la persona ve su jerarquía en menos de 3 segundos.
- **SC-004**: El 100 % de las unidades mostradas aparece debajo de su unidad padre y con la sangría de su nivel, también al buscar.
- **SC-005**: El 100 % de los intentos de crear, editar, desactivar o reactivar unidades, o de crear, renombrar o reordenar tipos, por parte de un Usuario se rechaza.
- **SC-006**: Ninguna unidad es su propio ancestro (el árbol nunca contiene ciclos) y existe siempre exactamente una unidad raíz, incluso con cambios simultáneos de varios administradores.
- **SC-007**: Ninguna unidad activa depende de una unidad inactiva.
- **SC-008**: El 100 % de las unidades nuevas tiene el tipo del nivel inmediatamente inferior al de su unidad padre, y ningún tipo elegido por quien llama se acepta.
- **SC-009**: El 100 % de las desactivaciones deja inactivas a la unidad y a todas sus dependientes activas, cada una con fecha de supresión; el 100 % de los intentos de desactivar una rama con posiciones Ocupadas o Vacantes se rechaza indicando las posiciones y las unidades correctas, sin cambiar ninguna unidad.
- **SC-010**: El 100 % de las unidades y de los tipos permanece en el sistema: ninguna acción de la aplicación elimina una unidad ni un tipo.
- **SC-011**: Ninguna pareja de unidades comparte código, incluso con creaciones simultáneas.
- **SC-012**: Los niveles de la cadena de tipos son siempre continuos de 1 a N, sin huecos ni repetidos, el tipo raíz es siempre el nivel 1 y ningún nombre de tipo se repite.
- **SC-013**: El 100 % de los reordenamientos que dejarían una unidad bajo una unidad padre de nivel igual o más profundo se rechaza sin cambiar la cadena ni ninguna unidad, y muestra la lista completa de unidades afectadas.
- **SC-014**: El Administrador prepara un renombre y un cambio de nivel, revisa la vista previa y guarda en menos de 2 minutos.
- **SC-015**: El 100 % de las creaciones, ediciones y cambios de estado de unidades, y de las creaciones, renombres y cambios de nivel de tipos, deja registrado el usuario y la fecha, y cada modificación conserva el campo afectado, el valor anterior y el nuevo, incluida una fila por cada unidad desactivada en cascada.
- **SC-016**: El 100 % de las unidades inactivas queda fuera de las nuevas asignaciones y del listado de los Usuarios, y el 100 % de las referencias existentes a ellas sigue siendo consultable.
- **SC-017**: Reubicar una unidad con su rama completa se hace con una sola acción de edición y el 100 % de las unidades y posiciones de la rama conserva su tipo y su unidad.

## Assumptions

- "Administrador de Talento Humano" corresponde al rol Administrador definido en 000-login; el resto de personas autenticadas tiene el rol Usuario. El modelo de datos (`estructura_empresarial.dbml`) ahora incluye roles, permisos y la relación de cada usuario con un rol; esta spec mantiene la distinción de la fuente (Administrador frente al resto) y cómo se traducen esas acciones a los roles y permisos del modelo se resuelve en el plan.
- El módulo de posiciones de la estructura organizacional aún no está completo. Las reglas que dependen de él (no desactivar una rama con posiciones Ocupadas o Vacantes, asignar posiciones solo a unidades activas, conservar las posiciones de unidades inactivas) se definen como contrato público que ese módulo deberá cumplir; el modelo de datos establece que toda posición pertenece a una unidad. Mientras el módulo no exista, la cantidad de posiciones asignadas es 0 y no impide ninguna desactivación; este módulo solo consulta las posiciones, nunca las crea ni las modifica.
- La carga inicial del sistema trae la cadena de tipos y la unidad raíz (de tipo raíz). La cadena de ejemplo de la fuente es Gerencia General → Gerencia → Área → Departamento → Sección; los nombres y niveles exactos de la carga inicial se definen en el plan.
- En la cadena, el nivel 1 es el tipo raíz y un número de nivel mayor es más profundo: "inmediatamente inferior" es el nivel siguiente (N + 1) e "inmediatamente superior" el anterior (N − 1). Un tipo está en el último nivel cuando no existe un nivel mayor.
- El código crece de forma secuencial, nunca se reutiliza y puede superar los 3 dígitos (UO-1000) si hiciera falta. El orden por código ascendente es numérico según la parte secuencial del código (UO-9 antes que UO-10 y UO-1000), no alfabético.
- La cantidad de unidades dependientes activas cuenta las unidades activas de la rama de la unidad (directas e indirectas), que son las que una desactivación en cascada alcanzaría. La cantidad de posiciones de la información de una unidad cuenta las asignadas directamente a ella; para decidir si una desactivación procede, el sistema considera además las posiciones de sus dependientes activas y las detalla por unidad en el bloqueo.
- Las fechas de creación y de supresión son fechas de calendario (día, mes y año); la auditoría registra además la hora de cada cambio.
- Una unidad que no sigue la cadena, o una inactiva con la unidad padre inactiva, conserva su unidad padre actual mientras no se cambie; para elegir otra solo se ofrecen unidades activas del tipo inmediatamente superior al de la unidad.
- Editar una unidad, o desactivarla y reactivarla, no versiona la unidad. El control de edición simultánea se comporta igual que en los otros catálogos: el segundo guardado sobre datos desactualizados se rechaza y se pide recargar; lo mismo aplica a la edición de la cadena de tipos. El modelo de datos actual no trae un campo de versión para ello; cómo se resuelve se define en el plan.
- Activar o desactivar una unidad (también en cascada) queda registrado en el historial, pero no actualiza «quién la modificó por última vez y cuándo» ni el control de edición simultánea: esos datos reflejan solo ediciones del contenido de la unidad.
- Gracias a que la creación también entra en el historial, este permite reconstruir el valor de cualquier dato auditado en cualquier momento. En el historial, las referencias (tipo de unidad y unidad padre) se identifican por su id.
- Con el filtro de estado "Inactivas", las unidades inactivas se muestran con las unidades de las que dependen (aunque estén activas) y sus ramas expandidas, igual que al buscar; al borrar la búsqueda o el filtro, el árbol vuelve al estado de expansión que tenía antes. El estado de expansión no se conserva entre visitas: al entrar siempre se muestra solo la raíz.
- Quien tiene acceso a un registro histórico que referencia una unidad inactiva puede consultarla por su dirección o identificador, aunque no aparezca en su listado.
- El historial de cambios se registra pero esta feature no lo muestra en pantalla; su consulta se define en una feature posterior.
- El tamaño esperado del árbol es de cientos de unidades (hasta unos 500) y el de la cadena de tipos, de pocas decenas como máximo; por eso el listado no se pagina y la cadena se edita completa en un solo modal. Cerrar el editor de tipos sin guardar descarta el borrador.
- **Puntos por confirmar** (la spec adopta el valor por defecto del documento fuente hasta que negocio decida): (1) las relaciones que saltan un nivel tras un reordenamiento se permiten y se marcan con "No sigue la cadena"; la alternativa estricta rechazaría casi cualquier cambio de nivel de un tipo en uso. (2) Un reordenamiento bloqueado solo se resuelve no aplicándolo: reubicar unidades no lo resuelve, porque todas las unidades de un tipo comparten nivel; ofrecer otra vía, como cambiar el tipo de unidades concretas, queda fuera de esta feature. (3) La desactivación en cascada se rechaza si hay posiciones Ocupadas o Vacantes en cualquier unidad de la rama; la alternativa es suprimir o reasignar esas posiciones como parte de la cascada.
- Los textos de la interfaz usan "Unidad", "Unidad padre", "Tipo de unidad", "Activa" e "Inactiva" (en femenino, porque se refieren a la unidad).

## Pantallas *(si la feature tiene UI)*

### Listado de unidades organizacionales (árbol y cadena de tipos)
- **Propósito**: consultar la estructura formal de la organización y su cadena de tipos, buscar unidades y, para el Administrador, crear, editar, reubicar, desactivar y reactivar unidades y gestionar los tipos.
- **Datos que muestra**: la cadena de tipos (por nivel, con su número); de cada unidad, el nombre (con sangría según el nivel y el control de expandir o contraer su rama), el código, el tipo (etiqueta, con advertencia "No sigue la cadena" cuando corresponda) y el estado (etiqueta Activa / Inactiva); la cantidad de unidades y de unidades activas o de coincidencias; para el Administrador, las acciones de cada fila.
- **Acciones disponibles** (y quién puede hacerlas): buscar por código, nombre o tipo, expandir y contraer ramas, Expandir todo y Contraer todo (Administrador y Usuario); filtrar por estado, Nuevo tipo, Editar tipos, Crear unidad, y por fila Editar, Agregar subunidad y Desactivar / Reactivar (solo Administrador). Desactivar pide confirmación y aparece deshabilitado en la raíz; Reactivar aparece deshabilitado si el padre está inactivo; Agregar subunidad aparece deshabilitado en unidades inactivas y del último nivel. No hay acción de eliminar ni panel de detalle.
- **Estados**: cargando (esqueleto de árbol), vacío, sin coincidencias ("Ninguna unidad coincide con la búsqueda."), error con "Reintentar", éxito con mensaje de confirmación sobre el listado, y sin permisos (el Usuario no ve las acciones de gestión ni el filtro de estado).
- **Mockup**: `design/Unidades.dc.html` (listado en árbol), con Estados (`design/Estados.dc.html`) para el diálogo de confirmación. El tablero vigente refleja la versión anterior de la spec (formulario en línea sobre el listado, tipo elegido a mano y desactivación sin cascada) y no incluye la cadena de tipos, el control de expandir y contraer, "Expandir todo / Contraer todo", la advertencia "No sigue la cadena", "Agregar subunidad", los modales ni la columna reorganizada (nombre, código, tipo, estado, acciones, sin unidad padre); debe actualizarse antes de implementar.

### Modal de unidad (crear y editar)
- **Propósito**: crear una unidad bajo una unidad padre, o modificar el nombre y la ubicación de una existente (activa o inactiva).
- **Datos que muestra**: al crear, Nombre (con contador de caracteres), Unidad padre (selector en orden de árbol con las inactivas y las del último nivel deshabilitadas) y el tipo con el que se creará, en solo lectura. Al editar, Nombre, Unidad padre (limitada a las activas del tipo inmediatamente superior; deshabilitada en la raíz) y, en solo lectura: tipo, código, fechas de creación y de supresión, cantidad de unidades dependientes activas, cantidad de posiciones asignadas y datos de auditoría (solo Administrador).
- **Acciones disponibles** (y quién puede hacerlas): Guardar y Cancelar (solo Administrador); se abre con "Crear unidad", "Agregar subunidad" (con la unidad padre preseleccionada) o Editar.
- **Estados**: cargando (al abrir una edición o los selectores), error de validación junto al campo con alerta general, error de carga con reintento, conflicto de edición, rechazo por regla del árbol (ciclo, padre inactivo o del último nivel, tipo de la unidad padre que no corresponde) junto al campo Unidad padre, guardando (botón bloqueado), éxito (se cierra el modal, el árbol se actualiza y se muestra un mensaje) y sin permisos.
- **Mockup**: modal sobre el patrón Formulario (`design/Formulario.dc.html`); por definir en `design/Unidades.dc.html`.

### Diálogo de desactivación
- **Propósito**: confirmar la desactivación de una unidad y, si la tiene, de su rama activa.
- **Datos que muestra**: el nombre de la unidad; si hay dependientes activas, la lista de las unidades que también se desactivarán; si hay posiciones Ocupadas o Vacantes en la rama, el bloqueo con la cantidad de posiciones y las unidades donde están.
- **Acciones disponibles** (y quién puede hacerlas): Cancelar y Desactivar, con la cantidad total de unidades que se desactivarán (solo Administrador); sin acción de confirmar cuando hay bloqueo.
- **Estados**: confirmación, bloqueo por posiciones, desactivando (botón bloqueado), rechazo del servidor con el motivo y éxito (se cierra y se muestra un mensaje).
- **Mockup**: patrón Estados (`design/Estados.dc.html`, confirmación destructiva).

### Modales de tipos de unidad (Nuevo tipo, Editar tipos y bloqueo)
- **Propósito**: agregar un tipo al final de la cadena, renombrar tipos y cambiar su nivel, con revisión previa y sin dejar unidades en conflicto.
- **Datos que muestra**: en Nuevo tipo, el campo "Nombre del tipo" y la indicación de que se agregará al final como tipo hijo del último nivel actual. En Editar tipos, la lista de tipos por nivel con su nombre editable, el asa y las flechas subir y bajar (el tipo raíz, con candado y la descripción "El tipo raíz solo puede renombrarse") y la vista previa de la cadena actual y la resultante con los cambios resaltados. En el bloqueo, la cantidad y la lista de unidades afectadas, con su tipo y nivel nuevos y su unidad padre con su tipo y nivel nuevos.
- **Acciones disponibles** (y quién puede hacerlas): Guardar, Guardar cambios (deshabilitado sin cambios), Cancelar, "Ver en el árbol" y volver a editar el borrador (solo Administrador).
- **Estados**: error de validación junto al campo (vacío, más de 50 caracteres, nombre repetido), guardando (botón bloqueado), conflicto de edición de la cadena, bloqueo por reordenamiento rechazado, éxito (se cierra y se muestra un mensaje) y sin permisos.
- **Mockup**: modales por definir en `design/Unidades.dc.html`, sobre el patrón Formulario y Estados.
