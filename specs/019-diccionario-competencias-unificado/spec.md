# Feature Specification: Diccionario de competencias unificado (competencias, comportamientos y preguntas)

**Feature Branch**: `019-diccionario-competencias-unificado`

**Created**: 2026-10-20

**Status**: Draft

**Input**: User description: "Gestión, en un solo módulo, del diccionario maestro de competencias organizacionales. Cada competencia reúne en un mismo registro sus cuatro grados de desarrollo, cuatro comportamientos esperados por grado (16 en total) y cuatro preguntas. Los grados se nombran con una escala elegida por competencia (letras, niveles numéricos o categorías). El Administrador de Talento Humano crea la competencia completa, edita por secciones (competencia y grados, comportamientos, preguntas), cambia la escala y activa/desactiva; los demás usuarios autenticados solo consultan las activas. Las competencias nunca se eliminan. Reemplaza a los diccionarios independientes de competencias (001), comportamientos (002) y preguntas (003)."

## Clarifications

### Session 2026-10-20

- Q: ¿Los nombres de la escala Categorías son fijos o editables por competencia? → A: Cada competencia define sus propios cuatro nombres.
- Q: Si dos administradores editan a la vez la misma competencia, ¿qué pasa con el segundo guardado? → A: Se rechaza con un aviso de que la competencia cambió y se pide recargar para ver la versión actual.
- Q: ¿Qué pasa con las competencias existentes que no tengan sus comportamientos o preguntas completos? → A: Se conservan, pero quedan Inactivas hasta que el administrador complete su contenido.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar el diccionario y el detalle de una competencia (Priority: P1)

Cualquier persona con sesión iniciada abre Competencias, ve las tarjetas de las competencias con su código, nombre y tipo, busca por nombre, filtra por tipo y despliega una tarjeta para ver, en tres pestañas, la competencia con sus grados, los comportamientos por grado y las preguntas.

**Why this priority**: Es el valor central del diccionario: una fuente única y consultable de competencias, grados, comportamientos y preguntas para toda la organización.

**Independent Test**: Con competencias activas e inactivas, iniciar sesión como Administrador y como otro usuario, buscar, filtrar, desplegar una tarjeta y recorrer las tres pestañas; comprobar qué ve cada rol.

**Acceptance Scenarios**:

1. **Given** existen competencias activas e inactivas, **When** un usuario que no es administrador abre Competencias, **Then** ve solo las activas, ordenadas por código, y no ve ninguna acción de gestión.
2. **Given** existen competencias activas e inactivas, **When** el administrador abre Competencias, **Then** ve ambas y reconoce las inactivas por el nombre atenuado.
3. **Given** el listado visible, **When** escribe parte de un nombre (sin importar mayúsculas ni tildes), **Then** el listado se filtra mientras escribe; al combinarlo con el filtro por tipo se aplican ambos.
4. **Given** una tarjeta, **When** la persona la despliega, **Then** se abre en la pestaña Competencia, que muestra la descripción completa y los cuatro grados con su descripción, nombrados con la escala de esa competencia.
5. **Given** una tarjeta desplegada, **When** elige la pestaña Comportamientos, **Then** ve los cuatro grados, cada uno con sus cuatro comportamientos como viñetas; **When** elige Preguntas, **Then** ve las cuatro preguntas numeradas del 1 al 4.
6. **Given** varias tarjetas, **When** despliega más de una, **Then** todas permanecen abiertas, cada una con su propia pestaña activa.
7. **Given** ninguna competencia coincide con la búsqueda y los filtros, **When** se muestra el listado, **Then** aparece "Ninguna competencia coincide con los filtros." con el botón "Limpiar filtros"; bajo la lista siempre se indica cuántas competencias se encontraron ("7 competencias" o "1 competencia").
8. **Given** una competencia inactiva a la que otro registro hace referencia, **When** una persona con acceso a ese registro la consulta, **Then** ve sus datos, grados, comportamientos y preguntas completos.

---

### User Story 2 - Crear una competencia completa (Priority: P1)

El administrador crea una competencia en un solo formulario: nombre, tipo, descripción, escala de los grados, las descripciones de los cuatro grados, los cuatro comportamientos de cada grado y las cuatro preguntas. El sistema asigna el código y la deja Activa.

**Why this priority**: Sin competencias completas el diccionario no tiene contenido; toda competencia nace con grados, comportamientos y preguntas.

**Independent Test**: Como administrador, crear una competencia válida y comprobar que aparece en el listado con su código, estado Activo y sus tres pestañas completas.

**Acceptance Scenarios**:

1. **Given** el administrador abre "Nueva competencia", **When** ve el formulario, **Then** los campos aparecen en este orden: nombre y tipo, descripción, escala de los grados, cuatro grados, comportamientos por grado y cuatro preguntas; la escala predeterminada viene seleccionada.
2. **Given** el formulario, **When** cambia la escala elegida, **Then** los rótulos de los cuatro grados cambian a los de esa escala.
3. **Given** el formulario con la escala Categorías elegida, **When** el administrador la selecciona, **Then** aparecen cuatro campos de nombre prellenados con Excelente, Muy buena, Buena y Normal, que puede modificar, y los rótulos de los grados usan esos nombres.
4. **Given** la escala Categorías con un nombre vacío o repetido, **When** intenta guardar, **Then** se rechaza con "Escribe las cuatro categorías, sin repetir nombres."
5. **Given** todos los campos completos y válidos, **When** pulsa "Guardar competencia", **Then** se crea la competencia con el siguiente código COMP-####, estado Activo, y aparece el aviso "Competencia creada correctamente."
6. **Given** un campo obligatorio vacío (incluido cualquiera de los 16 comportamientos o de las cuatro preguntas), **When** intenta guardar, **Then** no se guarda nada y cada error aparece junto a su campo.
7. **Given** ya existe "Liderazgo", **When** intenta crear " liderazgo " o "Lidérazgo", **Then** se rechaza con "Ya existe una competencia con este nombre."
8. **Given** el administrador cancela o cierra el formulario, **When** lo hace, **Then** no se crea nada.
9. **Given** una persona que no es administradora, **When** abre Competencias, **Then** no ve el botón "Nueva competencia" y no puede crear competencias por ninguna vía.

---

### User Story 3 - Editar una competencia por secciones (Priority: P2)

El administrador edita por separado la competencia y sus grados, los comportamientos o las preguntas, desde el botón de edición de la pestaña correspondiente. Puede editar competencias activas e inactivas.

**Why this priority**: Mantener el contenido vigente es la tarea recurrente del administrador; editar por secciones evita reabrir todo el formulario.

**Independent Test**: Editar cada sección de una competencia y comprobar que solo cambia esa sección, con su aviso, y que el código y el estado no cambian.

**Acceptance Scenarios**:

1. **Given** la pestaña Competencia, **When** el administrador pulsa "Editar grados de desarrollo", **Then** puede modificar nombre, descripción, tipo, escala (con los nombres de las categorías si corresponde) y las cuatro descripciones de grado, y al guardar aparece "Competencia actualizada correctamente."
2. **Given** la pestaña Comportamientos, **When** pulsa "Editar comportamientos", **Then** puede modificar los 16 comportamientos, y al guardar aparece "Comportamientos actualizados correctamente."
3. **Given** la pestaña Preguntas, **When** pulsa "Editar preguntas", **Then** puede modificar las cuatro preguntas, y al guardar aparece "Preguntas actualizadas correctamente."
4. **Given** una edición con un dato inválido o vacío, **When** intenta guardar, **Then** se muestran los errores junto a los campos y no se guarda nada.
5. **Given** una competencia inactiva, **When** el administrador edita cualquiera de sus secciones, **Then** se permite y la competencia sigue inactiva.
6. **Given** la edición del nombre, **When** el nuevo nombre ya pertenece a otra competencia, **Then** se rechaza con "Ya existe una competencia con este nombre."; conservar el nombre propio no es un error.
7. **Given** una persona que no es administradora, **When** consulta una competencia, **Then** no ve ningún botón de edición.
8. **Given** dos administradores con la misma competencia abierta para editar, **When** el segundo guarda después de que el primero ya guardó un cambio, **Then** su guardado se rechaza con un aviso de que la competencia cambió y que debe recargar; al recargar ve la versión actual y no se pierde el cambio del primero.

---

### User Story 4 - Activar y desactivar una competencia (Priority: P2)

El administrador desactiva una competencia que ya no debe usarse y la vuelve a activar cuando corresponda. Los comportamientos y las preguntas siguen el estado de la competencia.

**Why this priority**: Las competencias nunca se eliminan; desactivar es la única forma de retirarlas sin perder histórico.

**Independent Test**: Desactivar una competencia activa y comprobar que los demás usuarios dejan de verla; reactivarla y comprobar que vuelve.

**Acceptance Scenarios**:

1. **Given** una competencia activa, **When** el administrador pulsa Desactivar, **Then** se pide confirmación indicando que la competencia, con sus grados, comportamientos y preguntas, no podrá asignarse en registros nuevos.
2. **Given** el diálogo de confirmación, **When** pulsa Desactivar, **Then** la competencia queda inactiva y aparece "Competencia desactivada."; **When** pulsa Cancelar, **Then** no cambia nada.
3. **Given** una competencia inactiva, **When** el administrador pulsa Activar, **Then** queda activa sin pedir confirmación y aparece "Competencia activada."
4. **Given** una competencia desactivada, **When** otro usuario abre Competencias, **Then** ya no la ve en el listado.
5. **Given** una competencia desactivada con asignaciones existentes en otros módulos, **When** se consulta el histórico, **Then** las asignaciones se conservan y la competencia sigue siendo consultable.
6. **Given** el módulo completo, **When** se busca una acción para eliminar una competencia, **Then** no existe.
7. **Given** una competencia existente que quedó Inactiva por tener comportamientos o preguntas incompletos, **When** el administrador intenta activarla, **Then** se rechaza indicando qué falta; **When** completa todo su contenido, **Then** puede activarla.

---

### User Story 5 - Ver y cambiar la escala de grados (Priority: P2)

Cualquier usuario puede previsualizar los nombres de los grados con otra escala (letras, números o categorías) sin guardar nada. El administrador puede aplicar la escala elegida a la competencia.

**Why this priority**: La escala define cómo se leen los grados en toda la organización; la vista previa permite decidir con seguridad antes de cambiar.

**Independent Test**: En una competencia, previsualizar otra escala, comprobar que los nombres cambian solo en pantalla, descartar y luego aplicar, y comprobar que el cambio persiste.

**Acceptance Scenarios**:

1. **Given** las pestañas Competencia o Comportamientos, **When** una persona elige una escala distinta de la actual en "Ver con escala:", **Then** los nombres de los grados cambian en pantalla, el título indica "(vista previa)" y no se guarda nada.
2. **Given** una vista previa activa, **When** pulsa "Descartar", **Then** se restablece la escala guardada.
3. **Given** una vista previa activa, **When** el administrador pulsa "Aplicar esta escala", **Then** la escala queda guardada y aparece "Escala de grados actualizada correctamente."
4. **Given** una vista previa activa, **When** una persona que no es administradora mira la pantalla, **Then** no ve "Aplicar esta escala".
5. **Given** que se cambia la escala de una competencia, **When** se revisa su contenido, **Then** solo cambiaron los nombres de los grados: las descripciones, los comportamientos y los grados requeridos ya asignados en otros módulos se mantienen.
6. **Given** la vista previa o la aplicación de la escala Categorías, **When** la competencia no tiene nombres de categoría propios, **Then** se usan Excelente, Muy buena, Buena y Normal, que el administrador puede cambiar luego desde "Editar grados de desarrollo".
7. **Given** una competencia con nombres de categoría propios, **When** se cambia a otra escala y luego se vuelve a Categorías, **Then** se conservan sus nombres propios.

---

### User Story 6 - Trazabilidad y uso por otros módulos (Priority: P3)

Cada creación, modificación, cambio de estado y cambio de escala queda registrado con quién y cuándo. Los demás módulos usan el diccionario como única fuente y solo asignan competencias activas.

**Why this priority**: Da confianza y control sobre un catálogo del que dependen otros módulos, sin ser parte del flujo diario.

**Independent Test**: Hacer una edición, un cambio de estado y un cambio de escala, y comprobar que cada uno queda registrado con su autor y fecha; intentar asignar una competencia inactiva desde otro módulo.

**Acceptance Scenarios**:

1. **Given** una competencia creada, **When** el administrador consulta su auditoría, **Then** muestra quién la creó y cuándo.
2. **Given** cualquier edición de sus secciones o de su escala, **When** el administrador consulta la auditoría, **Then** muestra quién la modificó por última vez y cuándo.
3. **Given** un cambio de estado o de escala, **When** se revisa el historial, **Then** aparece el valor anterior, el nuevo, el usuario y la fecha.
4. **Given** otro módulo que asigna competencias, **When** ofrece competencias para una asignación nueva, **Then** solo ofrece las activas.
5. **Given** asignaciones existentes a competencias hoy inactivas, **When** se consultan, **Then** se conservan y se pueden ver completas.

---

### Edge Cases

- Dos competencias con nombres que solo difieren en mayúsculas, tildes o espacios al inicio o al final se consideran el mismo nombre.
- Un nombre de exactamente 50 caracteres es válido; uno de 51 se rechaza con su mensaje. Lo mismo ocurre con los límites de 500 (descripción, descripción de grado, pregunta) y 300 (comportamiento).
- Un texto formado solo por espacios cuenta como vacío.
- Crear dos competencias casi a la vez no puede producir el mismo código ni dejar una competencia incompleta: o se guarda todo o no se guarda nada.
- Si otro administrador cambió la competencia mientras se editaba, el guardado se rechaza con un aviso para recargar (FR-035).
- Si falla el guardado de una edición, se conservan los valores escritos en el formulario y se muestra el error.
- Cambiar la escala de una competencia con grados ya requeridos por puestos no invalida esas asignaciones.
- Desactivar una competencia no altera sus asignaciones ni su contenido; reactivarla la devuelve exactamente como estaba.
- Si la carga del listado o del detalle falla, se muestra un error con la opción de reintentar; mientras carga, un indicador de carga.
- Con el listado muy largo, las tarjetas siguen siendo legibles en una columna en pantallas angostas (320 px) sin scroll horizontal.
- Los nombres de categoría "Buena" y " buena " (o "Búena") se consideran repetidos dentro de la misma competencia.
- Una vista previa de escala es propia de cada tarjeta y no afecta a las demás ni se conserva al recargar.

## Requirements *(mandatory)*

### Functional Requirements

**Datos de la competencia**

- **FR-001**: El sistema MUST asignar a cada competencia un código único con formato COMP-#### (número consecutivo de cuatro dígitos) al crearla; el código MUST NOT poder editarse.
- **FR-002**: El nombre MUST ser obligatorio, de hasta 50 caracteres y único en el diccionario; para la unicidad MUST ignorarse mayúsculas, tildes y espacios al inicio o al final.
- **FR-003**: La descripción MUST ser obligatoria y de hasta 500 caracteres.
- **FR-004**: El tipo MUST ser obligatorio y valer Cardinal (toda la organización) o Específica (determinados puestos).
- **FR-005**: El estado MUST ser Activo o Inactivo; toda competencia nueva MUST crearse Activa y el estado MUST cambiarse solo con la acción Activar / Desactivar, nunca en un formulario.
- **FR-006**: Cada competencia MUST tener una escala de grados con una de tres opciones: Letras (Grado A, B, C, D), Números (Nivel 1, 2, 3, 4) o Categorías (cuatro nombres propios de la competencia; ver FR-007).
- **FR-007**: Cuando la escala sea Categorías, cada competencia MUST definir sus propios cuatro nombres de grado; los cuatro campos MUST aparecer prellenados con "Excelente", "Muy buena", "Buena" y "Normal", MUST ser obligatorios, de hasta 20 caracteres cada uno, y MUST NOT estar vacíos ni repetirse entre sí (sin distinguir mayúsculas, tildes ni espacios), con el mensaje "Escribe las cuatro categorías, sin repetir nombres."
- **FR-008**: En las tres escalas el primer grado MUST ser el nivel más alto de desarrollo y el cuarto el más básico.
- **FR-009**: Cada competencia MUST tener exactamente cuatro grados de desarrollo, uno por posición de la escala y sin repetir, cada uno con una descripción obligatoria de hasta 500 caracteres.
- **FR-010**: Cada grado MUST tener exactamente cuatro comportamientos (16 por competencia), cada uno un texto obligatorio de hasta 300 caracteres, mostrado como viñeta en el orden registrado.
- **FR-011**: Cada competencia MUST tener exactamente cuatro preguntas, numeradas del 1 al 4, cada una un texto obligatorio de hasta 500 caracteres; las preguntas pertenecen a la competencia, no a un grado.
- **FR-012**: No MUST existir competencias incompletas activas: toda competencia activa MUST tener sus cuatro grados, sus 16 comportamientos y sus cuatro preguntas. Las competencias que existían antes de este módulo con contenido incompleto MUST conservarse en estado Inactivo hasta que el administrador lo complete, y el sistema MUST rechazar activarlas mientras falte contenido, indicando qué falta.

**Ciclo de vida**

- **FR-013**: Las competencias MUST NOT eliminarse nunca; el sistema MUST NOT ofrecer ninguna acción de eliminar.
- **FR-014**: El administrador MUST poder desactivar una competencia activa y reactivar una inactiva; los comportamientos y las preguntas MUST seguir siempre el estado de su competencia.
- **FR-015**: Desactivar MUST pedir confirmación que informe que la competencia no podrá asignarse en registros nuevos; activar MUST NOT pedir confirmación.
- **FR-016**: Una competencia inactiva MUST poder seguir editándose en todas sus secciones y consultándose.

**Permisos**

- **FR-017**: El administrador de Talento Humano MUST poder crear, editar (competencia y grados, comportamientos, preguntas), aplicar escala y activar/desactivar; los demás usuarios autenticados MUST poder solo consultar y previsualizar escalas.
- **FR-018**: Los usuarios que no son administradores MUST ver únicamente competencias activas, y el sistema MUST rechazar cualquier intento suyo de escribir.

**Listado y consulta**

- **FR-019**: El listado MUST mostrar tarjetas ordenadas por código ascendente, con una competencia por fila en cualquier ancho de pantalla; cada tarjeta MUST mostrar código, nombre y tipo en su cabecera, sin descripción ni indicador de estado, y el nombre atenuado si está inactiva.
- **FR-020**: El listado MUST ofrecer búsqueda por nombre que ignore mayúsculas y tildes y filtre mientras se escribe, filtro por tipo ("Todos los tipos", Cardinal, Específica) y un botón "Limpiar filtros" visible solo con algún filtro aplicado; MUST NOT existir filtro por estado.
- **FR-021**: El listado MUST indicar bajo las tarjetas el número de competencias encontradas, y mostrar "Ninguna competencia coincide con los filtros." cuando no haya resultados.
- **FR-022**: Cada tarjeta MUST poder desplegarse (varias a la vez) con tres pestañas: Competencia (descripción y grados), Comportamientos (por grado, como viñetas) y Preguntas (numeradas); la pestaña inicial MUST ser Competencia y el código MUST NOT repetirse en lo desplegado.
- **FR-023**: Toda persona autenticada MUST poder previsualizar cualquier escala en las pestañas Competencia y Comportamientos sin guardar nada, con aviso "(vista previa)", y el administrador MUST poder aplicarla o descartarla.
- **FR-024**: Cambiar la escala MUST modificar solo los nombres de los grados y MUST NOT alterar descripciones, comportamientos ni los grados requeridos ya asignados en otros módulos.

**Formularios y avisos**

- **FR-025**: El formulario de creación MUST pedir todos los datos en una sola operación, con los campos en el orden: nombre y tipo, descripción, escala, grados, comportamientos por grado, preguntas; los rótulos de los grados MUST seguir la escala elegida y MUST preseleccionarse la escala predeterminada del sistema.
- **FR-026**: La creación MUST ser atómica: o se guarda la competencia con todo su contenido o no se guarda nada.
- **FR-027**: La edición MUST poder hacerse por separado para la sección Competencia (botón «Editar grados de desarrollo», ventana «Editar competencia»), con nombre, descripción, tipo, escala y cuatro grados, Comportamientos (16) y Preguntas (4), sin afectar a las demás secciones.
- **FR-028**: Los errores de validación MUST mostrarse junto al campo correspondiente, tras el primer intento de guardar, conservando lo escrito; el mensaje de nombre duplicado MUST ser "Ya existe una competencia con este nombre."
- **FR-029**: Los resultados MUST mostrarse en un aviso emergente centrado con el botón Aceptar, que se cierra también con Escape: "Competencia creada correctamente.", "Competencia actualizada correctamente.", "Comportamientos actualizados correctamente.", "Preguntas actualizadas correctamente.", "Escala de grados actualizada correctamente.", "Competencia activada." y "Competencia desactivada."
- **FR-030**: Todos los botones de ícono MUST tener un nombre accesible con la acción y el nombre de la competencia (por ejemplo, "Desactivar Liderazgo").

**Auditoría e integración**

- **FR-031**: El sistema MUST registrar quién creó cada competencia y cuándo, y quién la modificó por última vez y cuándo; editar grados, comportamientos, preguntas o escala cuenta como modificar la competencia. Quién creó y modificó cada competencia (nombre del usuario y fecha) solo lo ve el administrador; los demás usuarios no reciben esos datos.
- **FR-032**: El sistema MUST registrar en un historial cada cambio de estado y de escala (valor anterior, valor nuevo, usuario y fecha).
- **FR-033**: Este módulo MUST ser la fuente única de competencias, grados, comportamientos y preguntas; los demás módulos MUST NOT mantener su propio catálogo, MUST asignar solo competencias activas en registros nuevos y MUST conservar las referencias a las inactivas.
- **FR-034**: Los puestos MUST asignar el grado requerido apuntando al grado de la competencia (no a su nombre), de modo que cambiar la escala no altere las asignaciones.

**Concurrencia**

- **FR-035**: Cuando una competencia haya cambiado (por edición, cambio de escala o de estado) desde que el administrador abrió el formulario, el sistema MUST rechazar el guardado con un aviso de que la competencia cambió y pedir que recargue; MUST NOT sobrescribir el cambio ya guardado ni perder los demás datos. Las acciones puntuales (activar, desactivar y aplicar escala) no se rechazan por este motivo: se aplican sobre la versión vigente y, al hacerlo, hacen que se rechace el guardado de cualquier formulario que se abrió antes.

### Key Entities *(include if feature involves data)*

- **Competencia**: elemento del diccionario. Tiene código, nombre, descripción, tipo, estado y una escala de grados; agrupa sus grados, comportamientos y preguntas, y no se elimina.
- **Escala de grados**: forma de nombrar los cuatro grados (Letras, Números o Categorías); cada escala tiene cuatro posiciones ordenadas del nivel más alto al más básico. En Categorías, los cuatro nombres son propios de cada competencia.
- **Grado de desarrollo**: una de las cuatro posiciones de una competencia, con su descripción; es lo que otros módulos referencian como grado requerido.
- **Comportamiento**: conducta esperada de un grado; cuatro por grado, ordenados.
- **Pregunta**: pregunta de la competencia; cuatro por competencia, numeradas.
- **Registro de auditoría**: quién y cuándo creó o modificó una competencia, y el historial de sus cambios de estado y de escala.
- **Asignación de puesto**: vínculo de otro módulo entre un puesto, una competencia y el grado requerido (solo se consulta aquí).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100 % de las competencias del diccionario tiene sus cuatro grados, 16 comportamientos y cuatro preguntas; ninguna se puede guardar incompleta.
- **SC-002**: Un administrador crea una competencia completa en menos de 10 minutos, con el contenido ya preparado, y la ve en el listado inmediatamente.
- **SC-003**: Al escribir en la búsqueda, el listado se actualiza en menos de 1 segundo.
- **SC-004**: Una persona encuentra y lee los comportamientos o las preguntas de una competencia en 3 pasos o menos desde el listado (desplegar, elegir pestaña).
- **SC-005**: El 100 % de las personas que no son administradoras ve solo competencias activas y ninguna acción de gestión, y el 100 % de sus intentos de escritura se rechaza.
- **SC-006**: El 100 % de las asignaciones existentes en otros módulos se conserva sin cambios al cambiar la escala o al desactivar una competencia.
- **SC-007**: El 100 % de las creaciones, ediciones, cambios de estado y cambios de escala queda registrado con su autor y fecha.
- **SC-008**: La pantalla es utilizable sin scroll horizontal entre 320 y 1440 px de ancho y en tema claro y oscuro.
- **SC-009**: En las pruebas de aceptación con usuarios, al menos el 90 % completa a la primera la creación o edición de una competencia sin ayuda.

## Assumptions

- Este módulo reemplaza a los diccionarios independientes de competencias (001), comportamientos (002) y preguntas (003); no se mantienen registros separados de comportamientos ni de preguntas.
- Existen dos tipos de usuario: Administrador de Talento Humano (gestiona) y los demás usuarios autenticados (consultan).
- La escala predeterminada para competencias nuevas es Letras; quién cambia la escala predeterminada del sistema queda fuera del alcance de esta feature.
- El estado de una competencia es el único estado: grados, comportamientos y preguntas no tienen estado propio.
- Los comportamientos y las preguntas de cada competencia se registran en su creación. Las competencias ya existentes conservan su contenido: las que ya tengan todo quedan como estaban y las incompletas quedan Inactivas hasta completarse (ver FR-012).
- El listado se pagina (12 competencias por página, con la página en la URL) y el recuento bajo las tarjetas indica el total de resultados encontrados, no el de la página.
- El administrador ve las competencias inactivas junto a las activas, sin filtro por estado, reconociéndolas por el nombre atenuado.
- Se sigue la lista de cambios de mejora ya acordada: una competencia por fila, descripción solo al desplegar y sin indicador de estado en las tarjetas; el diseño de referencia del archivo adjunto (descripción en la cabecera de la tarjeta) se ajusta a esta especificación y debe incorporarse a `design/` antes de implementar.
- El diálogo de confirmación al desactivar y el campo Tipo en la edición no figuran en el diseño de referencia y se añaden.
- Los marcos comunes de la aplicación (barra superior, menú lateral, pie de página, tema claro/oscuro) ya existen y se reutilizan.
- Depende de los módulos de autenticación y de puestos existentes; las columnas de auditoría que el modelo de datos aún no tiene se resuelven en el plan.

## Pantallas *(si la feature tiene UI)*

### Listado de competencias
- **Propósito**: consultar el diccionario y acceder al detalle de cada competencia.
- **Datos que muestra**: por tarjeta, código, nombre y tipo; conteo de competencias encontradas. Desplegada: pestañas Competencia (descripción y grados con la escala), Comportamientos (por grado) y Preguntas.
- **Acciones disponibles** (y quién puede hacerlas): buscar por nombre, filtrar por tipo, limpiar filtros, desplegar tarjetas, cambiar de pestaña y previsualizar escala (todos); "Nueva competencia", Activar / Desactivar, botones de edición por pestaña y "Aplicar esta escala" (solo administrador).
- **Estados**: vacío ("Ninguna competencia coincide con los filtros." con "Limpiar filtros"), cargando, error con reintentar, sin permisos (las acciones de gestión no aparecen).
- **Mockup**: patrón Listado de tarjetas desplegables con pestañas (design/Diccionario.dc.html, del archivo Competencia-html.zip, por incorporar a `design/`); con una competencia por fila y con la descripción en la pestaña Competencia.

### Formulario Nueva competencia
- **Propósito**: crear una competencia completa.
- **Datos que muestra**: nombre, tipo, descripción, selector de escala con ejemplos (y, con Categorías, cuatro campos de nombre), cuatro grados, 16 comportamientos agrupados por grado y cuatro preguntas.
- **Acciones disponibles** (y quién puede hacerlas): guardar y cancelar (solo administrador).
- **Estados**: errores por campo tras el primer intento, guardando (envío bloqueado), error de guardado.
- **Mockup**: modal de formulario (design/Diccionario.dc.html, "Nueva competencia").

### Formularios de edición por sección
- **Propósito**: modificar la competencia y sus grados, los comportamientos o las preguntas.
- **Datos que muestra**: los campos de la sección elegida con el nombre de la competencia en el título.
- **Acciones disponibles** (y quién puede hacerlas): guardar cambios y cancelar (solo administrador).
- **Estados**: errores por campo, guardando, error de guardado.
- **Mockup**: modal de edición (design/Diccionario.dc.html, "Editar competencia / comportamientos / preguntas"); se añade el campo Tipo a la edición de la competencia.

### Confirmación y avisos
- **Propósito**: confirmar la desactivación e informar el resultado de cada acción.
- **Datos que muestra**: advertencia de que la competencia no podrá asignarse en registros nuevos; mensajes de resultado de la sección 4.5 del documento de origen.
- **Acciones disponibles** (y quién puede hacerlas): Cancelar / Desactivar en la confirmación; Aceptar o Escape en el aviso.
- **Estados**: éxito.
- **Mockup**: aviso emergente (design/Diccionario.dc.html); el diálogo de confirmación sigue el patrón de confirmación de los módulos de unidades organizacionales y posiciones.
