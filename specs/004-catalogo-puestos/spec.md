# Feature Specification: Catálogo maestro de puestos

**Feature Branch**: `004-catalogo-puestos`

**Created**: 2026-10-13

**Status**: Draft

**Input**: User description: "Catálogo maestro de puestos organizacionales (documento fuente `specify-puestos.md`). Un puesto describe un cargo con independencia de la persona que lo ocupa: su misión, sus funciones, sus requisitos y las competencias que exige, cada una con el grado requerido. Es la fuente única de definición de puestos: las posiciones de la estructura organizacional se crean a partir de puestos de este catálogo. El Administrador de Talento Humano lista (activos e inactivos), consulta, crea, edita y activa/desactiva; los demás usuarios autenticados listan (solo activos) y consultan. Cada puesto tiene un código único generado por el sistema (PU-###, no editable), nombre, descripción, misión, estado (Activo o Inactivo; nace Activo), una lista ordenada de funciones (Esencial o Complementaria), una lista de requisitos (Formación, Experiencia o Capacitación) y una lista de competencias del diccionario de competencias con su grado requerido. Un puesto solo se puede desactivar si no tiene posiciones Ocupadas ni Vacantes. Los puestos nunca se eliminan. Se audita quién creó y modificó cada puesto y cada cambio con su valor anterior y nuevo."

## Clarifications

### Session 2026-10-13

- Q: ¿Se permite que dos puestos tengan el mismo nombre, o el nombre debe ser único en todo el catálogo? → A: El nombre es único entre todos los puestos, activos e inactivos, sin distinguir mayúsculas ni espacios al inicio o al final.
- Q: Cuando un puesto tiene una competencia que quedó inactiva en el diccionario, ¿puede el Administrador cambiar su grado requerido al editar, o solo dejarla tal como está o quitarla? → A: Solo mantenerla igual (con su grado bloqueado) o quitarla.
- Q: ¿Se deben rechazar funciones repetidas, o requisitos repetidos del mismo tipo, dentro de un mismo puesto cuando tienen exactamente el mismo texto? → A: Sí; se rechaza una función con descripción repetida y un requisito del mismo tipo con descripción repetida, sin distinguir mayúsculas ni espacios al inicio o al final.
- Q: Si el Administrador solo cambia el orden de las funciones, ¿debe quedar registrado en el historial de cambios y de qué forma? → A: No; el orden de las funciones no se audita. Solo se registran altas, bajas y cambios de texto o tipo.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar el catálogo y el detalle de un puesto (Priority: P1)

Cualquier persona con sesión iniciada (Administrador o Usuario) abre el catálogo de puestos, ve el listado con el código, nombre, descripción y estado de cada puesto, busca por código o nombre y despliega una fila para ver la información completa del puesto: misión, funciones, requisitos y competencias con su grado requerido. El Administrador además puede filtrar por estado.

**Why this priority**: Es el valor central del catálogo: una fuente única y consultable de la definición de cada puesto para todos los módulos y todas las personas.

**Independent Test**: Con puestos activos e inactivos, iniciar sesión como Usuario y como Administrador, abrir el listado, buscar y desplegar el detalle de un puesto; comprobar qué ve cada rol y que el Usuario no tiene acciones de gestión.

**Acceptance Scenarios**:

1. **Given** existen puestos activos e inactivos, **When** una persona autenticada abre el catálogo, **Then** ve por defecto solo los activos, ordenados por nombre de forma ascendente, cada uno con código, nombre, descripción y estado (como etiqueta).
2. **Given** el listado visible, **When** escribe parte de un código o de un nombre en la búsqueda, **Then** solo se muestran los puestos que coinciden, sin distinguir mayúsculas ni tildes (por ejemplo, "gestion" encuentra "Gestión de proyectos").
3. **Given** ningún puesto coincide con la búsqueda, **When** se muestra el listado, **Then** aparece el mensaje "Ningún puesto coincide con la búsqueda."
4. **Given** un puesto en el listado, **When** la persona despliega su fila, **Then** ve la misión, las funciones como lista numerada según su orden con el tipo de cada una, los requisitos agrupados por tipo (Formación, Experiencia, Capacitación) y las competencias en una tabla con competencia, tipo y grado requerido.
5. **Given** una fila desplegada, **When** la persona despliega otra fila, **Then** la primera se cierra y solo permanece abierta la nueva.
6. **Given** un puesto con una competencia que está inactiva en el diccionario de competencias, **When** se despliega su detalle, **Then** esa competencia se señala visualmente como inactiva y conserva su grado requerido.
7. **Given** una persona con rol Usuario, **When** abre el catálogo, **Then** solo ve puestos activos, no ve el filtro de estado y no ve los botones Nuevo puesto, Editar ni Activar/Desactivar.
8. **Given** una persona con rol Administrador, **When** abre el catálogo, **Then** ve el filtro de estado y puede elegir ver activos (valor por defecto), inactivos o todos; los inactivos muestran la etiqueta Inactivo.
9. **Given** un puesto inactivo al que una posición existente hace referencia, **When** cualquier persona autenticada con acceso a esa posición consulta el puesto, **Then** puede ver sus datos completos.
10. **Given** no hay puestos, **When** se muestra el listado, **Then** aparece un estado vacío explicativo; si falla la carga, un error con "Reintentar".

---

### User Story 2 - Crear un puesto (Priority: P1)

El Administrador crea un puesto indicando nombre, descripción y misión, y completando las listas de funciones, requisitos y competencias con su grado requerido; el sistema le asigna un código único y lo crea con estado Activo.

**Why this priority**: Sin puestos creados el catálogo no tiene contenido y no se pueden crear posiciones en la estructura organizacional.

**Independent Test**: Como Administrador, crear un puesto válido (con funciones, requisitos y competencias) y comprobar que aparece en el listado con su código, estado Activo y toda su información en el detalle.

**Acceptance Scenarios**:

1. **Given** el Administrador en el formulario de creación, **When** lo abre, **Then** ve cuatro secciones (Datos generales, Funciones, Requisitos y Competencias) y, en el código, la indicación de que se asigna al guardar.
2. **Given** el formulario, **When** completa nombre, descripción, misión, al menos una función (con al menos una Esencial), al menos un requisito de Formación y uno de Experiencia, y al menos una competencia con su grado, y guarda, **Then** el puesto se crea con un código generado por el sistema con el formato PU-### (por ejemplo, PU-014), estado Activo, y aparece en el listado.
3. **Given** el formulario, **When** falta el nombre, la descripción o la misión, o superan 50, 200 y 200 caracteres respectivamente, **Then** se muestran errores por campo y no se guarda nada.
4. **Given** ya existe un puesto (activo o inactivo) con el mismo nombre, sin distinguir mayúsculas ni espacios al inicio o al final, **When** el Administrador intenta guardar, **Then** se rechaza con un error en el campo nombre y no se guarda nada.
5. **Given** el formulario, **When** todas las funciones son Complementarias, o no hay ninguna función, **Then** se rechaza con un error en la sección Funciones.
6. **Given** el formulario, **When** falta un requisito de Formación o uno de Experiencia, **Then** se rechaza con un error en la sección Requisitos; no se exige ninguno de Capacitación.
7. **Given** el formulario, **When** dos funciones tienen la misma descripción, o dos requisitos del mismo tipo tienen la misma descripción (sin distinguir mayúsculas ni espacios al inicio o al final), **Then** se rechaza con un error en la fila repetida y no se guarda nada; el mismo texto en requisitos de tipos distintos sí se permite.
8. **Given** el formulario, **When** no hay ninguna competencia, o una competencia no tiene grado requerido, **Then** se rechaza con un error en la sección Competencias o en la fila afectada.
9. **Given** la sección Competencias, **When** el Administrador abre el selector, **Then** solo se ofrecen competencias activas del diccionario que aún no están en el puesto, y al elegir una el grado ofrece únicamente los grados definidos para esa competencia.
10. **Given** la sección Funciones, **When** agrega, quita o reordena filas, **Then** el orden queda como lo definió el usuario y la numeración empieza en 1.
11. **Given** un formulario con errores, **When** se corrige un campo, **Then** el resto de valores escritos se conserva.
12. **Given** una persona con rol Usuario, **When** intenta crear un puesto (por la interfaz o directamente), **Then** se le deniega por falta de permisos.

---

### User Story 3 - Editar un puesto (Priority: P2)

El Administrador modifica los datos generales, las funciones, los requisitos o las competencias de un puesto, esté activo o inactivo; el código no cambia y el estado no se modifica por esta vía.

**Why this priority**: Mantiene correcta la definición de cada puesto sin tener que recrearlo; los cambios aplican a todas las posiciones del puesto.

**Independent Test**: Editar un puesto activo y uno inactivo (cambiar nombre, reordenar funciones, agregar un requisito y quitar una competencia), guardar y comprobar los cambios, que el código y el estado son los mismos y que queda registrado quién lo modificó.

**Acceptance Scenarios**:

1. **Given** un puesto existente (activo o inactivo), **When** el Administrador abre su edición, **Then** ve el mismo formulario de creación con los valores actuales y el código visible pero no editable.
2. **Given** el formulario de edición, **When** cambia valores válidos y guarda, **Then** los cambios se reflejan en el listado y en el detalle, el código y el estado permanecen iguales y se registra quién lo modificó y cuándo.
3. **Given** el formulario de edición, **When** reordena las funciones y guarda, **Then** el detalle las muestra en el nuevo orden con numeración desde 1.
4. **Given** el formulario de edición, **When** deja vacío un campo obligatorio, quita la última función Esencial, el último requisito de Formación o de Experiencia, todas las competencias, o repite la descripción de otra función (o de otro requisito del mismo tipo), **Then** se muestran errores junto al campo o sección correspondiente y no se guarda.
5. **Given** el formulario de edición, **When** cambia el nombre por el de otro puesto (activo o inactivo, sin distinguir mayúsculas ni espacios al inicio o al final), **Then** se rechaza con un error en el campo nombre; conservar el nombre propio del puesto, o cambiar solo sus mayúsculas, no se considera duplicado.
6. **Given** un puesto con una competencia que pasó a inactiva en el diccionario, **When** el Administrador lo edita, **Then** puede mantener esa competencia con su grado, que aparece bloqueado y no se puede cambiar, o quitarla, y el selector no le permite agregar otras competencias inactivas.
7. **Given** el Administrador editó un puesto sin cambiar ningún valor, **When** guarda, **Then** no se actualiza quién lo modificó ni cuándo, y no se añaden registros al historial.
8. **Given** dos administradores abren la edición del mismo puesto, **When** el segundo guarda después de que el primero ya guardó, **Then** el segundo guardado se rechaza con un aviso de que el puesto cambió y se pide recargar.
9. **Given** una persona con rol Usuario, **When** intenta editar (por la interfaz o directamente), **Then** se le deniega por falta de permisos.

---

### User Story 4 - Activar y desactivar un puesto (Priority: P2)

El Administrador desactiva un puesto que ya no debe usarse para crear posiciones, o vuelve a activar uno inactivo. Los puestos nunca se eliminan, para preservar históricos y auditoría. Un puesto con posiciones Ocupadas o Vacantes no se puede desactivar.

**Why this priority**: Retira del uso los puestos obsoletos sin perder las posiciones que ya los referencian ni la auditoría.

**Independent Test**: Desactivar un puesto sin posiciones en uso (con confirmación) y comprobar que deja de verse en el listado por defecto y de poder usarse para nuevas posiciones; intentar desactivar uno con posiciones Ocupadas o Vacantes y comprobar el rechazo con el conteo; volver a activar el primero y verlo de nuevo.

**Acceptance Scenarios**:

1. **Given** un puesto activo sin posiciones Ocupadas ni Vacantes, **When** el Administrador pulsa Desactivar en el detalle desplegado, **Then** se pide confirmación que nombra el puesto.
2. **Given** el diálogo de confirmación, **When** confirma, **Then** el puesto pasa a Inactivo y desaparece del listado por defecto; si cancela, no cambia nada.
3. **Given** un puesto activo con una o más posiciones Ocupadas o Vacantes, **When** el Administrador intenta desactivarlo, **Then** la acción se rechaza, el puesto sigue Activo y se indica cuántas posiciones lo impiden.
4. **Given** un puesto activo cuyas únicas posiciones están Suprimidas, **When** el Administrador lo desactiva, **Then** la desactivación procede, porque las posiciones Suprimidas no lo impiden.
5. **Given** un puesto inactivo, **When** el Administrador pulsa Activar, **Then** pasa a Activo de inmediato, sin pedir confirmación y sin restricciones.
6. **Given** el detalle desplegado de un puesto, **When** se muestra, **Then** el botón alterna su texto: dice Desactivar si el puesto está activo y Activar si está inactivo.
7. **Given** un puesto inactivo, **When** otro módulo intenta usarlo para crear una nueva posición, **Then** no se le permite; las posiciones que ya lo usan se conservan y siguen siendo consultables.
8. **Given** un puesto inactivo, **When** el Administrador lo consulta o lo edita, **Then** puede hacerlo normalmente y se indica visualmente con la etiqueta Inactivo.
9. **Given** una persona con rol Usuario, **When** intenta activar o desactivar (por la interfaz o directamente), **Then** se le deniega por falta de permisos.

---

### User Story 5 - Trazabilidad de los cambios (Priority: P3)

El sistema registra quién creó cada puesto y cuándo, quién lo modificó por última vez y cuándo, y cada cambio con el campo afectado, el valor anterior, el valor nuevo, el usuario y la fecha, incluidos los cambios de estado y las altas, bajas y modificaciones de funciones, requisitos y competencias.

**Why this priority**: Garantiza auditoría y permite reconstruir la historia de un puesto, que no tiene versionado y nunca se elimina.

**Independent Test**: Crear un puesto, editar sus datos, agregar una función, quitar una competencia, cambiar el grado de otra, desactivarlo y activarlo con distintos administradores, e inspeccionar los registros de auditoría para comprobar que reflejan cada acción.

**Acceptance Scenarios**:

1. **Given** el Administrador crea un puesto, **When** se guarda, **Then** queda registrado quién lo creó y cuándo, y el historial recibe un registro por cada dato, función, requisito y competencia con que nació, con el valor anterior vacío.
2. **Given** un puesto existente, **When** un administrador lo edita, **Then** queda registrado quién lo modificó por última vez y cuándo, sin alterar quién lo creó.
3. **Given** un administrador cambia el nombre de un puesto, **When** guarda, **Then** el historial recibe un registro con el campo afectado, el valor anterior, el valor nuevo, el usuario y la fecha.
4. **Given** un administrador agrega una función, quita un requisito o cambia el grado de una competencia, **When** guarda, **Then** el historial recibe un registro por cada alta, baja o modificación, identificando de qué lista se trata.
5. **Given** un administrador solo cambia el orden de las funciones de un puesto, **When** guarda, **Then** el nuevo orden se conserva y se actualiza quién lo modificó y cuándo, pero el historial no recibe registros por el cambio de orden.
6. **Given** un puesto cambia de estado (a Inactivo o a Activo), **When** se confirma, **Then** el historial recibe un registro con el estado anterior y el nuevo, el usuario y la fecha, y los registros anteriores se conservan.
7. **Given** el historial de un puesto, **When** se consulta, **Then** los registros no se pueden modificar ni borrar desde la aplicación.

---

### Edge Cases

- Un puesto tiene solo posiciones Suprimidas: se puede desactivar. Tiene al menos una Ocupada o Vacante: se rechaza e indica cuántas son, sumando las Ocupadas y las Vacantes.
- Una posición Vacante se crea justo mientras se desactiva el mismo puesto: la verificación y el cambio de estado se resuelven como una sola operación; si la posición llegó primero, la desactivación se rechaza.
- Dos administradores cambian a la vez el estado del mismo puesto: no se registran cambios repetidos; el segundo recibe la situación actual y, si el puesto ya está en el estado pedido, no se añade un registro al historial.
- Dos administradores editan a la vez el mismo puesto: el segundo guardado se rechaza con un aviso de que el puesto cambió y se pide recargar; no se sobrescribe en silencio.
- Se desactiva en el diccionario una competencia que el Administrador acaba de agregar en el formulario, antes de guardar: al guardar se rechaza esa fila con un error, porque no pueden agregarse competencias inactivas.
- El Administrador quita del puesto una competencia inactiva y guarda: no puede volver a agregarla, porque el selector solo ofrece competencias activas.
- Se intenta guardar un puesto con un grado distinto en una competencia inactiva ya asignada (por ejemplo, directamente sin la interfaz): se rechaza con un error en la fila; la persona puede quitar la competencia o dejarla como estaba.
- El diccionario de competencias no tiene competencias activas disponibles: el formulario indica que no hay competencias que agregar y no permite guardar, porque un puesto exige al menos una.
- Una competencia ya está en el puesto: el selector no la ofrece; si se intenta guardar repetida, se rechaza con un error en la fila.
- El grado requerido no pertenece a los grados de la competencia elegida (por ejemplo, tras cambiar la competencia de una fila): se rechaza con un error en la fila, y al cambiar de competencia el grado elegido se reinicia.
- Descripciones, nombre o misión con solo espacios: se tratan como vacíos.
- Nombre de más de 50 caracteres, descripción o misión de más de 200, o descripción de una función o requisito de más de 500: se rechaza con un error en el campo y el formulario indica el límite.
- Una lista (funciones, requisitos o competencias) supera los 20 elementos: se rechaza con un error en la sección y el formulario indica el máximo; el botón de agregar se deshabilita al llegar a 20.
- Reordenar funciones: la numeración se recalcula siempre desde 1 sin saltos, también después de agregar o quitar filas. Si solo cambia el orden, el cambio se guarda y actualiza la última modificación, pero no añade registros al historial.
- Dos funciones, o dos requisitos del mismo tipo, con la misma descripción que difiere solo en mayúsculas o en espacios al inicio o al final: se consideran repetidos y se rechazan con un error en la fila repetida; el mismo texto en requisitos de tipos distintos (por ejemplo, Formación y Capacitación) es válido.
- Se supera el número 999 en el código: el código sigue creciendo (PU-1000) sin reutilizar ni repetir ninguno.
- Dos administradores crean a la vez puestos con el mismo nombre: solo uno se guarda y el otro recibe el error de nombre duplicado.
- Dos administradores crean puestos con nombres distintos a la vez: cada uno recibe un código distinto.
- Se pide un puesto que no existe: se informa que no existe.
- Un Usuario abre directamente la dirección de un puesto inactivo al que tiene acceso por una posición: puede consultarlo, pero no aparece en su listado.
- Listado con muchos puestos: se navega por páginas y la búsqueda, el filtro y la página se conservan en la dirección de la página.
- Todos los puestos están inactivos y la persona usa el filtro por defecto: se muestra el estado vacío con la indicación de que puede ver también los inactivos (si es Administrador).
- Cambia el nombre, el tipo o el estado de una competencia asignada en el diccionario: el puesto siempre muestra los valores actuales; no guarda copia.
- Sin sesión iniciada no se accede al catálogo (feature 000-login).

## Requirements *(mandatory)*

### Functional Requirements

**Permisos y acceso**

- **FR-001**: El sistema MUST permitir al Administrador de Talento Humano listar (puestos activos e inactivos), consultar, crear, editar, activar y desactivar puestos.
- **FR-002**: El sistema MUST permitir a cualquier otra persona autenticada consultar un puesto (activo o inactivo) y listar solo los puestos activos, y MUST denegarle las operaciones de crear, editar, activar y desactivar.
- **FR-003**: Toda operación MUST requerir sesión iniciada y el sistema MUST verificar el rol en cada petición, sin depender solo de lo que muestre la interfaz; en particular, un Usuario MUST NOT poder obtener puestos inactivos en el listado aunque lo pida directamente.

**Datos del puesto**

- **FR-004**: Cada puesto MUST tener un código único generado por el sistema al crearlo, con el formato `PU-` seguido de un número secuencial de al menos 3 dígitos (por ejemplo, `PU-014`), que MUST NOT poder modificarse nunca ni reutilizarse.
- **FR-005**: Cada puesto MUST tener nombre (máximo 50 caracteres), descripción (máximo 200 caracteres) y misión (máximo 200 caracteres) obligatorios; un texto con solo espacios se trata como vacío.
- **FR-006**: El nombre de cada puesto MUST ser único entre todos los puestos (activos e inactivos), comparándolo sin distinguir mayúsculas ni minúsculas ni espacios al inicio o al final. Un puesto MUST NOT chocar consigo mismo al editarse.
- **FR-007**: Cada puesto MUST tener un estado, Activo o Inactivo; todo puesto nuevo MUST crearse Activo.
- **FR-008**: Cada puesto MUST tener una lista ordenada de funciones con al menos una función y al menos una de tipo Esencial. Cada función MUST tener una descripción obligatoria (máximo 500 caracteres) y un tipo (Esencial o Complementaria). El orden lo define el usuario y la numeración MUST empezar en 1, sin saltos. Dos funciones del mismo puesto MUST NOT tener la misma descripción (sin distinguir mayúsculas ni espacios al inicio o al final). La lista MUST admitir como máximo 20 funciones.
- **FR-009**: Cada puesto MUST tener una lista de requisitos con al menos uno de tipo Formación y al menos uno de tipo Experiencia; los de tipo Capacitación son opcionales. Cada requisito MUST tener un tipo (Formación, Experiencia o Capacitación) y una descripción obligatoria (máximo 500 caracteres). Dos requisitos del mismo tipo en el mismo puesto MUST NOT tener la misma descripción (sin distinguir mayúsculas ni espacios al inicio o al final); el mismo texto en tipos distintos sí se permite. La lista MUST admitir como máximo 20 requisitos.
- **FR-010**: Cada puesto MUST tener una lista con al menos una competencia. Cada competencia MUST seleccionarse del diccionario de competencias, MUST NOT repetirse dentro del mismo puesto y MUST tener un grado requerido obligatorio que sea uno de los grados definidos para esa competencia en el diccionario. La lista MUST admitir como máximo 20 competencias.
- **FR-011**: Al agregar una competencia a un puesto, el sistema MUST aceptar solo competencias activas del diccionario. De cada competencia asignada el sistema MUST mostrar, como datos de solo lectura leídos del diccionario y sin almacenarlos en el puesto, su código, nombre, tipo y estado.

**Ciclo de vida**

- **FR-012**: El sistema MUST NOT permitir eliminar puestos: no existe la acción de eliminar y la única forma de retirarlos del uso es desactivarlos.
- **FR-013**: El sistema MUST permitir desactivar un puesto solo si no tiene posiciones Ocupadas ni Vacantes. Las posiciones Suprimidas MUST NOT impedir la desactivación. Si tiene posiciones en uso, el sistema MUST rechazar la acción, conservar el estado Activo e indicar cuántas posiciones la impiden.
- **FR-014**: El sistema MUST permitir reactivar un puesto inactivo sin restricciones. Desactivar MUST pedir una confirmación accesible que nombre el puesto; activar MUST NOT pedir confirmación.
- **FR-015**: Un puesto inactivo MUST conservarse, seguir siendo consultable y poder editarse. MUST NOT poder usarse para crear nuevas posiciones; las posiciones que ya lo usan MUST conservarse. Se MUST indicar visualmente con la etiqueta Inactivo.
- **FR-016**: Si una competencia asignada a un puesto se desactiva en el diccionario de competencias, el sistema MUST conservar la asignación con su grado requerido, MUST señalar visualmente que la competencia está inactiva y MUST NOT cambiar el estado del puesto. Al editar el puesto, esa competencia MUST poder mantenerse con su grado requerido sin cambios (el grado queda bloqueado) o quitarse, y MUST NOT poder agregarse ninguna otra competencia inactiva ni cambiarse el grado de una inactiva.

**Listado y detalle**

- **FR-017**: El listado MUST mostrar código, nombre, descripción (síntesis del puesto) y estado (etiqueta Activo / Inactivo) de cada puesto, ordenado por nombre de forma ascendente y paginado.
- **FR-018**: El listado MUST permitir buscar por código o nombre sin distinguir mayúsculas ni tildes. Por defecto MUST mostrar solo los puestos activos, para todos los roles. Si ningún puesto coincide, MUST mostrar el mensaje "Ningún puesto coincide con la búsqueda."
- **FR-019**: El Administrador MUST disponer de un filtro por estado (activos —valor por defecto—, inactivos o todos). El filtro MUST NOT mostrarse ni aplicarse a quien no sea Administrador: ese rol solo recibe puestos activos. Búsqueda, filtro y página MUST conservarse en la dirección de la página.
- **FR-020**: Cada fila del listado MUST ser desplegable y, al desplegarla, MUST mostrar la información completa del puesto: misión; funciones como lista numerada según su orden con el tipo de cada una; requisitos agrupados por tipo (Formación, Experiencia, Capacitación); y competencias en una tabla con competencia, tipo y grado requerido, señalando visualmente las inactivas. Solo una fila MUST permanecer abierta a la vez. El detalle MUST estar disponible para todas las personas autenticadas.
- **FR-021**: El listado MUST cubrir los estados cargando, vacío, error con reintento y éxito.

**Acciones del Administrador y formulario**

- **FR-022**: El botón "Nuevo puesto" MUST ser visible solo para el Administrador. Dentro del detalle desplegado, los botones Editar y Activar / Desactivar MUST ser visibles solo para el Administrador; el botón de estado MUST alternar su texto según el estado actual.
- **FR-023**: Crear y editar MUST usar el mismo formulario, con cuatro secciones: Datos generales (código, nombre, descripción y misión), Funciones, Requisitos y Competencias. El código MUST ser de solo lectura al editar y, al crear, MUST indicar que se asigna al guardar.
- **FR-024**: La sección Funciones MUST permitir agregar, quitar y reordenar filas con tipo y descripción. La sección Requisitos MUST permitir agregar y quitar filas con tipo y descripción. La sección Competencias MUST permitir agregar y quitar filas con la competencia y su grado requerido; el selector MUST ofrecer solo competencias activas que aún no están en el puesto y el grado MUST ofrecer únicamente los grados de la competencia elegida.
- **FR-025**: El sistema MUST mostrar los errores de validación junto al campo o sección correspondiente, conservar los valores escritos cuando la validación falla e impedir envíos duplicados mientras un guardado o cambio de estado está en curso.
- **FR-026**: Al editar, el sistema MUST rechazar el guardado si el puesto cambió desde que se abrió el formulario, con un aviso que pida recargar para ver la versión actual.

**Auditoría**

- **FR-027**: El sistema MUST registrar quién creó cada puesto y cuándo, y quién lo modificó por última vez y cuándo. Guardar una edición sin cambios reales MUST NOT actualizar la última modificación ni añadir registros al historial.
- **FR-028**: El sistema MUST registrar cada cambio de un puesto en un historial con el campo afectado, el valor anterior, el valor nuevo, el usuario y la fecha. Esto MUST incluir la creación del puesto (con valor anterior vacío), los datos del puesto, los cambios de estado y las altas, bajas y modificaciones de funciones (descripción y tipo), requisitos y competencias (incluido su grado requerido). El orden de las funciones, y los cambios de numeración que se derivan de reordenar, agregar o quitar funciones, MUST NOT generar registros en el historial; un cambio que solo reordena funciones sí cuenta como cambio real a efectos de la última modificación (quién y cuándo).
- **FR-029**: Los registros del historial MUST conservarse y MUST NOT poder modificarse ni borrarse desde la aplicación.

**Relación con otros módulos**

- **FR-030**: El catálogo MUST ser la fuente única de definición de puestos: ningún módulo mantiene su propia copia, y los módulos que consumen puestos (por ejemplo, organigrama y perfilamiento) MUST leerlos por un contrato público de solo lectura sin poder modificarlos.
- **FR-031**: Para crear nuevas posiciones, el módulo de estructura MUST poder usar solo puestos activos; las posiciones existentes de un puesto inactivo MUST conservarse y seguir siendo consultables para cualquier persona con acceso a esos registros.
- **FR-032**: Las competencias y sus grados MUST obtenerse exclusivamente del diccionario de competencias; este catálogo MUST NOT crear, editar ni duplicar datos de competencias.
- **FR-033**: El puesto MUST NOT tener versionado: los cambios en sus funciones, requisitos o competencias MUST aplicar a todas sus posiciones, y el detalle del cambio queda en el historial de auditoría.

**Experiencia y accesibilidad**

- **FR-034**: Toda la interfaz MUST estar en español y cumplir WCAG 2.2 AA entre 320 y 1440 px de ancho, sin desplazamiento horizontal de la página.

**Fuera de alcance**: gestión de posiciones y de la estructura organizacional (organigrama), asignación de personas a posiciones, perfilamiento, la gestión de competencias y sus grados (feature 001), la pantalla para consultar el historial de cambios (el historial se registra, pero esta feature no define cómo mostrarlo), el versionado de puestos, la importación o exportación masiva y la eliminación física de puestos.

### Key Entities *(include if feature involves data)*

- **Puesto**: cargo descrito con independencia de la persona que lo ocupa; tiene código único e inmutable (PU-###), nombre único, descripción (síntesis), misión, estado (Activo o Inactivo) y las listas de funciones, requisitos y competencias; guarda quién lo creó y cuándo, y quién lo modificó por última vez y cuándo.
- **Función**: responsabilidad del puesto; tiene descripción, tipo (Esencial o Complementaria) y un número de orden desde 1 definido por el usuario. Pertenece a un solo puesto.
- **Requisito**: condición que debe cumplir quien ocupe el puesto; tiene tipo (Formación, Experiencia o Capacitación) y descripción. Pertenece a un solo puesto.
- **Competencia asignada**: vínculo entre un puesto y una competencia del diccionario, con el grado requerido (uno de los grados de esa competencia); no copia los datos de la competencia (código, nombre, tipo y estado se leen del diccionario). Una competencia aparece como máximo una vez por puesto.
- **Competencia** *(externa, diccionario de competencias)*: unidad del diccionario con código, nombre, tipo, estado y grados; solo las activas pueden agregarse a un puesto.
- **Posición** *(externa, estructura organizacional)*: plaza creada a partir de un puesto, con estado Ocupada, Vacante o Suprimida; determina si el puesto puede desactivarse.
- **Registro de cambios**: entrada inmutable del historial con el puesto, el campo o elemento afectado, el valor anterior, el valor nuevo, el usuario y la fecha.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El Administrador crea un puesto completo (con datos generales, 3 funciones, 2 requisitos y 3 competencias con su grado) en menos de 5 minutos.
- **SC-002**: Una persona encuentra un puesto por código o por nombre en menos de 15 segundos.
- **SC-003**: Una persona ve la información completa de un puesto (misión, funciones, requisitos y competencias) con una sola acción, sin salir del listado.
- **SC-004**: El 100 % de los intentos de crear, editar, activar o desactivar por parte de un Usuario se rechazan.
- **SC-005**: El 100 % de los puestos almacenados cumple las reglas de contenido: al menos una función Esencial, un requisito de Formación, uno de Experiencia y una competencia con grado válido; el 100 % de los intentos de guardar un puesto que no las cumple se rechaza.
- **SC-006**: El 100 % de los intentos de desactivar un puesto con posiciones Ocupadas o Vacantes se rechaza indicando el número correcto de posiciones, y ningún puesto inactivo tiene posiciones Ocupadas ni Vacantes.
- **SC-007**: El 100 % de los puestos permanece en el sistema: ninguna acción de la aplicación elimina un puesto.
- **SC-008**: Ninguna pareja de puestos comparte código ni nombre (sin distinguir mayúsculas), incluso con creaciones simultáneas y entre activos e inactivos.
- **SC-009**: El 100 % de las creaciones, ediciones y cambios de estado deja registrado el usuario y la fecha, y cada modificación conserva el campo afectado, el valor anterior y el nuevo.
- **SC-010**: El 100 % de los puestos inactivos queda fuera de las nuevas posiciones y del listado de los Usuarios, y el 100 % de las posiciones existentes de un puesto inactivo sigue siendo consultable.
- **SC-011**: El 100 % de las competencias inactivas asignadas a un puesto se muestra señalada como inactiva y conserva su grado requerido.

## Assumptions

- "Administrador de Talento Humano" corresponde al rol Administrador definido en 000-login; el resto de personas autenticadas tiene el rol Usuario y solo consulta y lista puestos activos.
- El módulo de posiciones de la estructura organizacional aún no existe. Las reglas que dependen de él (no desactivar con posiciones Ocupadas o Vacantes, crear posiciones solo con puestos activos, conservar las posiciones de puestos inactivos) se definen como contrato público que ese módulo deberá cumplir: guardar, para cada posición, su puesto y su estado (Ocupada, Vacante o Suprimida), y exigir que el puesto esté activo en la misma operación que crea una posición nueva. Hasta que ese módulo exista, este catálogo trae una estructura mínima de posiciones solo para esa comprobación; mientras no haya posiciones, ningún puesto tiene posiciones en uso y todos pueden desactivarse.
- La unicidad del nombre se resuelve igual que en el diccionario de competencias (001): se compara sin distinguir mayúsculas ni espacios alrededor, también frente a los puestos inactivos. Reactivar un puesto nunca puede generar un conflicto de nombre, porque el nombre es único entre todos.
- El código crece de forma secuencial, nunca se reutiliza y puede superar los 3 dígitos (PU-1000) si hiciera falta.
- La numeración de las funciones la define el orden que el usuario les da en el formulario y se recalcula siempre desde 1; no se guarda como dato editable aparte.
- Cada lista de un puesto (funciones, requisitos y competencias) admite como máximo 20 elementos; el límite lo fija el plan técnico y es configurable.
- Los grados de una competencia son los definidos en el diccionario de competencias (A, B, C y D); el puesto guarda solo cuál es el grado requerido, y el texto de cada grado se lee del diccionario.
- Una competencia inactiva ya asignada solo puede mantenerse tal como está (grado incluido) o quitarse; si se mantiene, su grado sigue siendo uno de los grados de esa competencia.
- La búsqueda es por coincidencia parcial sobre código o nombre (no sobre descripción ni misión) y el tamaño de página es el estándar del sistema.
- La desactivación y la reactivación se hacen desde el detalle desplegado de la fila; "Nuevo puesto" se ofrece en el encabezado del listado.
- La creación de un puesto se registra con su creador y fecha y también en el historial de cambios (valor anterior vacío), de modo que el historial permite reconstruir el valor de cualquier dato auditado en cualquier momento. El orden de las funciones no se audita: no se guarda su historia, aunque sí se conserva el orden vigente.
- El control de edición concurrente se comporta igual que en el diccionario de competencias (001): el segundo guardado sobre una versión desactualizada se rechaza.
- Activar o desactivar un puesto queda registrado en el historial de cambios, pero no actualiza «quién lo modificó por última vez y cuándo» ni la versión de edición: esos datos reflejan solo ediciones del contenido del puesto. Por eso un cambio de estado concurrente no hace fallar un guardado en curso.
- Quien tiene acceso a una posición que referencia un puesto inactivo puede consultar el puesto por su dirección o identificador, aunque no aparezca en su listado.
- El historial de cambios se registra pero esta feature no lo muestra en pantalla; su consulta se define en una feature posterior.
- Los textos de la interfaz usan "Puesto", "Funciones", "Requisitos", "Competencias" y "Grado requerido".

## Pantallas *(si la feature tiene UI)*

### Listado de puestos
- **Propósito**: consultar el catálogo, buscar, ver el detalle completo de cada puesto y, para el Administrador, crear y gestionar puestos y su estado.
- **Datos que muestra**: código, nombre, descripción (síntesis) y estado (etiqueta Activo / Inactivo) de cada puesto; al desplegar una fila, la misión, las funciones numeradas con su tipo, los requisitos agrupados por tipo y la tabla de competencias con su tipo y grado requerido (las inactivas señaladas); total y página actual.
- **Acciones disponibles** (y quién puede hacerlas): buscar por código o nombre, paginar y desplegar el detalle de una fila (Administrador y Usuario); filtrar por estado, Nuevo puesto, Editar y Activar/Desactivar (solo Administrador). No hay acción de eliminar.
- **Estados**: cargando (esqueleto de tabla), vacío (sin puestos), sin coincidencias ("Ningún puesto coincide con la búsqueda."), error con "Reintentar", éxito, sin permisos (el Usuario no ve las acciones de gestión ni el filtro de estado), confirmación al desactivar y aviso de rechazo cuando el puesto tiene posiciones Ocupadas o Vacantes (con su número).
- **Mockup**: `design/Puestos.dc.html` (listado con filas desplegables), con Estados (`design/Estados.dc.html`); columnas Código, Nombre, Descripción y Estado, y un único filtro (Estado) para el Administrador. Ese tablero no incluye el filtro de estado, la paginación, los botones Editar y Activar/Desactivar ni el diálogo de confirmación: se añaden según `design/DESIGN.md` §5 Listado (puntos 7 a 9), junto con el tipo de cada función, los requisitos agrupados por tipo y el indicador de competencia inactiva.

### Formulario de puesto (crear y editar)
- **Propósito**: crear un puesto o modificar uno existente (activo o inactivo).
- **Datos que muestra**: cuatro secciones. Datos generales: código (solo lectura al editar; al crear, "se asigna al guardar"), nombre, descripción y misión, con contador de caracteres. Funciones: filas con tipo y descripción, con agregar, quitar y reordenar. Requisitos: filas con tipo y descripción, con agregar y quitar. Competencias: filas con la competencia (y su código, tipo y estado de solo lectura) y su grado requerido, con agregar y quitar; indicación visual de las competencias inactivas, cuyo grado aparece bloqueado.
- **Acciones disponibles** (y quién puede hacerlas): agregar, quitar y reordenar filas, Guardar puesto y Cancelar (solo Administrador).
- **Estados**: cargando (al abrir una edición o las competencias disponibles), error de validación junto al campo o sección con alerta general, sin competencias disponibles para agregar, error de carga con reintento, conflicto de edición, guardando (botón bloqueado), éxito (vuelve al listado) y sin permisos.
- **Mockup**: `design/PuestosFormulario.dc.html` (nuevo tablero, tarea T003 de `tasks.md`) sobre el patrón Formulario (`design/Formulario.dc.html`), con las cuatro secciones en `fieldset` y los componentes Combobox y Alert neutro de `design/DESIGN.md` §4. **Requiere ampliar `design/DESIGN.md` antes de implementar** (Constitución III), porque ningún patrón actual cubre: filas repetibles con dos campos (tipo y texto) con agregar y quitar, la reordenación accesible de funciones (por ejemplo, botones Subir y Bajar), la fila de competencia con selector y grado dependiente, y el detalle desplegable con tres bloques.
