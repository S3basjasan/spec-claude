# Feature Specification: Diccionario de comportamientos

> **Reemplazada por la feature 019** ([diccionario de competencias unificado](../019-diccionario-competencias-unificado/spec.md)): competencias, comportamientos y preguntas se gestionan ahora en un solo módulo. Este documento se conserva como histórico.

**Feature Branch**: `002-diccionario-comportamientos`

**Created**: 2026-10-11

**Status**: Draft

**Input**: User description: "Diccionario maestro de comportamientos organizacionales, fuente única de comportamientos para los demás módulos. Cada registro se construye a partir de una competencia existente del diccionario de competencias y define, para cada uno de sus cuatro grados (A, B, C, D), entre uno y cuatro comportamientos esperados. El Administrador de Talento Humano lista (de competencias activas e inactivas), consulta, crea y edita; los demás usuarios autenticados listan (solo de competencias activas) y consultan. Cada competencia tiene a lo sumo un registro y no se puede cambiar tras crearlo. El registro no tiene estado propio: siempre es el de su competencia. Nunca se eliminan. Se audita quién creó y modificó cada registro."

## Clarifications

### Session 2026-10-11

- Q: ¿Qué longitud máxima debe tener cada comportamiento esperado? → A: 300 caracteres por viñeta de comportamiento.
- Q: Si se guarda una edición sin cambios reales, ¿debe actualizar quién modificó el registro y cuándo? → A: No; la auditoría solo se actualiza si cambió algún comportamiento.
- Q: ¿El selector de competencias al crear debe permitir buscar por nombre? → A: Sí; se escribe para filtrar y las opciones se ordenan alfabéticamente.
- Q: Si cambia el nombre o la descripción de una competencia, ¿debe quedar rastro o copia en el registro de comportamientos? → A: No; el registro siempre muestra los valores actuales de la competencia y no guarda copia de los anteriores.
- Q: ¿El nombre de la competencia en el registro debe enlazar a su consulta en el diccionario de competencias? → A: No; solo se muestran sus datos, sin enlace.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar los comportamientos (Priority: P1)

Cualquier persona con sesión iniciada (Administrador o Usuario) abre el diccionario de comportamientos, ve el listado de registros con el código, nombre, descripción, tipo y estado de la competencia asociada, busca por nombre de la competencia, filtra por tipo y despliega con "Visualizar comportamientos" los cuatro grados con sus comportamientos esperados. El Administrador además puede filtrar por estado.

**Why this priority**: Es el valor central del diccionario: una fuente única y consultable de comportamientos esperados por grado para todos los módulos.

**Independent Test**: Con registros de competencias activas e inactivas, iniciar sesión como Usuario y como Administrador, abrir el listado, buscar y filtrar, y desplegar los comportamientos de un registro; comprobar qué ve cada rol y que el Usuario no tiene acciones de gestión.

**Acceptance Scenarios**:

1. **Given** existen registros de competencias activas e inactivas, **When** una persona autenticada abre el diccionario de comportamientos, **Then** ve por defecto solo los registros de competencias activas, cada uno con código, nombre, descripción, tipo y estado de su competencia (el estado como etiqueta).
2. **Given** el listado visible, **When** escribe parte del nombre de una competencia en la búsqueda, **Then** solo se muestran los registros cuya competencia coincide.
3. **Given** el listado visible, **When** filtra por tipo Cardinal o Específica, **Then** solo se muestran los registros de competencias de ese tipo; al combinar búsqueda y filtros se aplican todos.
4. **Given** un registro en el listado, **When** pulsa "Visualizar comportamientos", **Then** se despliegan los grados A, B, C y D y cada grado muestra sus comportamientos esperados como viñetas; al pulsar de nuevo se ocultan.
5. **Given** una persona con rol Usuario, **When** abre el diccionario, **Then** solo ve registros de competencias activas, no ve el filtro de estado y no ve los botones Nuevo registro ni Editar.
6. **Given** una persona con rol Administrador, **When** abre el diccionario, **Then** ve el filtro de estado y puede elegir ver activos (valor por defecto), inactivos o todos.
7. **Given** un registro cuya competencia está inactiva, **When** el Administrador lo ve en el listado o lo despliega, **Then** se indica visualmente que la competencia asociada está inactiva.
8. **Given** un registro de una competencia inactiva al que otra persona hace referencia desde un registro histórico, **When** cualquier persona autenticada con acceso a ese registro lo consulta, **Then** puede ver sus datos y comportamientos completos.
9. **Given** no hay registros o ningún resultado coincide, **When** se muestra el listado, **Then** aparece un estado vacío explicativo; si falla la carga, un error con "Reintentar".

---

### User Story 2 - Crear un registro de comportamientos (Priority: P1)

El Administrador crea un registro eligiendo primero una competencia activa que todavía no tenga registro; al elegirla se muestran su nombre, descripción y tipo, y registra entre uno y cuatro comportamientos esperados para cada grado (A, B, C, D).

**Why this priority**: Sin registros creados el diccionario no tiene contenido.

**Independent Test**: Como Administrador, crear un registro para una competencia activa sin registro y comprobar que aparece en el listado con sus cuatro grados y comportamientos.

**Acceptance Scenarios**:

1. **Given** el Administrador en el formulario de creación, **When** abre la lista de competencias, **Then** solo aparecen competencias activas que todavía no tienen registro de comportamientos, ordenadas alfabéticamente, y puede escribir parte del nombre para filtrarlas.
2. **Given** el formulario, **When** elige una competencia, **Then** se muestran su nombre, descripción y tipo como datos de solo lectura.
3. **Given** una competencia elegida, **When** registra entre uno y cuatro comportamientos en cada uno de los grados A, B, C y D y guarda, **Then** el registro se crea, queda asociado a esa competencia y aparece en el listado.
4. **Given** el formulario, **When** no elige competencia, o algún grado queda sin comportamientos, o algún comportamiento está vacío, **Then** se muestran errores por campo y no se guarda nada.
5. **Given** un grado con cuatro comportamientos, **When** intenta añadir un quinto, **Then** no se permite y se indica el máximo.
6. **Given** un formulario con errores, **When** se corrige un campo, **Then** el resto de valores escritos se conserva.
7. **Given** no queda ninguna competencia activa sin registro, **When** el Administrador abre la creación, **Then** se informa que no hay competencias disponibles y no se puede guardar.

---

### User Story 3 - Editar los comportamientos (Priority: P2)

El Administrador modifica los comportamientos esperados de un registro, aunque su competencia esté inactiva; la competencia asociada se muestra en solo lectura y no puede cambiarse.

**Why this priority**: Mantiene los comportamientos correctos sin recrear registros.

**Independent Test**: Editar un registro, cambiar, añadir y quitar comportamientos de varios grados, guardar y comprobar los cambios, que la competencia es la misma y que queda registrado quién lo modificó; repetir con un registro de competencia inactiva.

**Acceptance Scenarios**:

1. **Given** un registro existente (de competencia activa o inactiva), **When** el Administrador abre su edición, **Then** ve la competencia asociada en solo lectura y los comportamientos actuales de cada grado.
2. **Given** el formulario de edición, **When** modifica, añade o quita comportamientos respetando entre uno y cuatro por grado y guarda, **Then** los cambios se reflejan y la competencia asociada permanece igual.
3. **Given** el formulario de edición, **When** deja un grado sin comportamientos, deja vacío un comportamiento o supera el máximo de cuatro, **Then** se muestran errores y no se guarda.
4. **Given** una persona con rol Usuario, **When** intenta editar (por la interfaz o directamente), **Then** se le deniega por falta de permisos.

---

### User Story 4 - El estado sigue al de la competencia (Priority: P2)

El registro no tiene estado propio: cuando el Administrador desactiva la competencia en el diccionario de competencias, el registro queda inactivo automáticamente y deja de verse para los Usuarios; al reactivar la competencia, vuelve a estar activo. No existen acciones de activar, desactivar ni eliminar en este diccionario.

**Why this priority**: Garantiza coherencia entre ambos diccionarios sin duplicar la gestión del estado.

**Independent Test**: Desactivar una competencia con registro y comprobar que su registro aparece como inactivo (visible solo para el Administrador, con indicación visual); reactivarla y comprobar que vuelve a estar activo y visible para todos.

**Acceptance Scenarios**:

1. **Given** un registro de una competencia activa, **When** el Administrador desactiva la competencia en el diccionario de competencias, **Then** el registro pasa a mostrarse como inactivo sin ninguna acción adicional y desaparece del listado de los Usuarios y del listado por defecto del Administrador.
2. **Given** un registro de una competencia inactiva, **When** el Administrador reactiva la competencia, **Then** el registro vuelve a estar activo y visible para todos.
3. **Given** cualquier pantalla de este diccionario, **When** se revisan sus acciones, **Then** no existen botones ni operaciones de activar, desactivar ni eliminar registros.
4. **Given** un registro de una competencia inactiva, **When** el Administrador lo edita, **Then** el cambio se guarda y el estado sigue siendo el de la competencia (inactivo).

---

### User Story 5 - Trazabilidad de los cambios (Priority: P3)

El sistema registra quién creó cada registro y cuándo, y quién lo modificó por última vez y cuándo. Los cambios de estado no se registran aquí: quedan en el historial de la competencia, en el diccionario de competencias.

**Why this priority**: Garantiza auditoría de un diccionario que nunca se elimina.

**Independent Test**: Crear y editar un registro con distintos administradores e inspeccionar los registros de auditoría; desactivar y reactivar su competencia y comprobar que no se añade historial en este diccionario.

**Acceptance Scenarios**:

1. **Given** el Administrador crea un registro, **When** se guarda, **Then** queda registrado quién lo creó y cuándo.
2. **Given** un registro existente, **When** un administrador lo edita cambiando algún comportamiento, **Then** queda registrado quién lo modificó por última vez y cuándo, sin alterar quién lo creó; si guarda sin cambios reales, la auditoría no se modifica.
3. **Given** un registro cuya competencia cambia de estado, **When** se consulta la auditoría del registro, **Then** no aparece ningún cambio de estado propio; este queda en el historial de la competencia.

---

### Edge Cases

- Una competencia pasa a inactiva entre que el Administrador la elige y guarda la creación: la creación se rechaza con un mensaje que explica que ya no está disponible.
- Dos administradores crean a la vez el registro de la misma competencia: solo uno se guarda y el otro recibe un mensaje de que esa competencia ya tiene registro.
- Dos administradores editan a la vez el mismo registro: el segundo guardado se rechaza con un aviso de que el registro cambió y se pide recargar; no se sobrescribe en silencio.
- Un comportamiento con solo espacios se trata como vacío.
- Un comportamiento de más de 300 caracteres se rechaza con un error y el formulario indica el límite.
- Dos comportamientos que solo difieren en mayúsculas o en espacios alrededor dentro del mismo grado: se rechaza el duplicado.
- Un grado con cinco comportamientos (por la interfaz o directamente): se rechaza.
- Se guarda una edición sin ningún cambio real: no se modifica el registro, no cambia la auditoría ni el control de versión, y se vuelve al listado sin error.
- Se pide un registro que no existe: se informa que no existe.
- Se edita el nombre o la descripción de la competencia en el diccionario de competencias: el registro muestra siempre los datos actuales, porque no los almacena.
- Un Usuario abre directamente la dirección de un registro de competencia inactiva al que tiene acceso por un registro histórico: puede consultarlo, pero no aparece en su listado.
- Listado con muchos registros: se navega por páginas y la búsqueda y los filtros se conservan en la dirección de la página.
- Todos los registros están inactivos y la persona usa el filtro por defecto: se muestra el estado vacío con la indicación de que puede ver también los inactivos (si es Administrador).
- Sin sesión iniciada no se accede al diccionario (feature 000-login).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema MUST permitir al Administrador listar, consultar, crear y editar registros de comportamientos.
- **FR-002**: El sistema MUST permitir a cualquier persona autenticada consultar un registro (de competencia activa o inactiva) y listar los registros de competencias activas, y MUST denegar al rol Usuario las operaciones de crear y editar.
- **FR-003**: Cada registro MUST estar asociado a exactamente una competencia existente del diccionario de competencias, obligatoria al crearlo.
- **FR-004**: Solo MUST poder elegirse una competencia activa al crear un registro, y cada competencia MUST tener como máximo un registro de comportamientos.
- **FR-005**: La competencia asociada MUST NOT poder cambiarse después de crear el registro; al editar se muestra en solo lectura.
- **FR-006**: Cada registro MUST definir comportamientos esperados para cada uno de los cuatro grados de la competencia (A, B, C y D), con al menos uno y como máximo cuatro por grado.
- **FR-007**: Cada comportamiento MUST ser un texto obligatorio de máximo 300 caracteres, único dentro de su grado (comparado sin distinguir mayúsculas ni espacios alrededor), que se muestra como viñeta y conserva el orden en que se registró.
- **FR-008**: El sistema MUST NOT almacenar ni duplicar datos de la competencia, ni guardar copia o rastro de sus valores anteriores: código, nombre, descripción, tipo y estado MUST obtenerse siempre del diccionario de competencias y reflejar sus valores actuales.
- **FR-009**: El registro MUST NOT tener estado propio: su estado MUST ser siempre el de su competencia, de modo que al desactivar o reactivar la competencia el registro cambia automáticamente, sin ninguna acción en este diccionario.
- **FR-010**: Los registros MUST NOT poder eliminarse, y este diccionario MUST NOT ofrecer acciones de activar, desactivar ni eliminar.
- **FR-011**: Un registro de una competencia inactiva MUST conservarse, seguir siendo consultable y poder seguir editándose, y la interfaz MUST indicar visualmente que la competencia asociada está inactiva.
- **FR-012**: El sistema MUST mostrar errores por campo en el formulario y conservar los valores escritos cuando la validación falla, e impedir envíos duplicados mientras un guardado está en curso.
- **FR-013**: El listado MUST mostrar código, nombre, descripción (recortada a unas 2 líneas con puntos suspensivos si es larga), tipo y estado de la competencia asociada (el estado como etiqueta), y ofrecer solo al Administrador la acción de crear y el botón Editar.
- **FR-014**: Cada registro del listado MUST ofrecer "Visualizar comportamientos", disponible para todas las personas autenticadas, que despliega y oculta los cuatro grados con sus comportamientos como viñetas.
- **FR-015**: El listado MUST permitir buscar por nombre de la competencia y filtrar por tipo, combinables, y el Administrador además MUST poder filtrar por estado (activos, inactivos o todos). Por defecto, para todos los roles, MUST mostrar solo los registros de competencias activas. Búsqueda, filtros y página MUST conservarse en la dirección de la página.
- **FR-016**: El listado MUST ordenarse alfabéticamente por nombre de la competencia (A–Z), estar paginado y MUST cubrir los estados cargando, vacío, error con reintento y éxito. El filtro de estado MUST NOT mostrarse ni aplicarse a quien no sea Administrador: ese rol solo recibe registros de competencias activas.
- **FR-017**: Al crear, el formulario MUST pedir primero la competencia entre las activas sin registro (ordenadas alfabéticamente y filtrables escribiendo parte del nombre), mostrar su nombre, descripción y tipo al elegirla, y rechazar el guardado si la competencia ya no está activa o ya tiene registro.
- **FR-018**: El sistema MUST registrar quién creó cada registro y cuándo, y quién lo modificó por última vez y cuándo (solo cuando una edición cambia realmente algún comportamiento; guardar sin cambios no modifica el registro ni su auditoría); los cambios de estado MUST NOT registrarse aquí porque quedan en el historial de la competencia.
- **FR-019**: El diccionario de comportamientos MUST ser la fuente única de comportamientos: ningún módulo mantiene su propio catálogo y los demás módulos MUST consultarlos por un contrato público.
- **FR-020**: Para nuevas asignaciones, los demás módulos MUST poder usar solo comportamientos de competencias activas; las referencias existentes a comportamientos de competencias inactivas MUST conservarse y seguir siendo consultables desde los registros históricos por cualquier persona con acceso a esos registros.
- **FR-021**: Al editar, el sistema MUST rechazar el guardado si el registro cambió desde que se abrió el formulario, con un aviso que pida recargar.
- **FR-022**: Toda la interfaz MUST estar en español y cumplir WCAG 2.2 AA entre 320 y 1440 px.
- **FR-023**: Toda operación MUST requerir sesión iniciada y el sistema MUST verificar el rol en cada petición, sin depender solo de lo que muestre la interfaz; en particular, un Usuario MUST NOT poder obtener registros de competencias inactivas en el listado aunque lo pida directamente.

**Fuera de alcance**: crear, editar o gestionar el estado de las competencias (se hace en el diccionario de competencias), gestión de los módulos que usan los comportamientos, asignación de comportamientos a cargos o personas, importación/exportación masiva, pantalla para consultar un historial de cambios de comportamientos y eliminación de registros.

### Key Entities *(include if feature involves data)*

- **Registro de comportamientos**: unidad del diccionario; asociado a una sola competencia (inmutable), con los comportamientos de sus cuatro grados; guarda quién lo creó y cuándo, y quién lo modificó por última vez y cuándo. No tiene estado propio.
- **Comportamiento esperado**: texto corto que describe una conducta esperada en un grado; pertenece a un grado de un registro y se muestra como viñeta.
- **Grado**: nivel A, B, C o D de la competencia asociada; en este diccionario agrupa entre uno y cuatro comportamientos.
- **Competencia (referenciada)**: dato del diccionario de competencias (código, nombre, descripción, tipo y estado) que este diccionario consulta pero nunca crea, edita ni almacena.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El Administrador crea un registro completo (competencia más comportamientos de los cuatro grados) en menos de 5 minutos.
- **SC-002**: Una persona encuentra el registro de una competencia por nombre o por tipo en menos de 15 segundos.
- **SC-003**: El 100 % de los intentos de crear o editar por parte de un Usuario se rechazan.
- **SC-004**: El 100 % de los registros permanece en el sistema y ninguna acción de la aplicación los elimina ni les cambia el estado directamente.
- **SC-005**: Ninguna competencia tiene más de un registro, incluso con creaciones simultáneas.
- **SC-006**: Todo registro mostrado o consultado tiene siempre los cuatro grados y cada grado entre uno y cuatro comportamientos.
- **SC-007**: El 100 % de los cambios de estado de una competencia se refleja en su registro de comportamientos sin intervención manual.
- **SC-008**: El 100 % de los registros de competencias inactivas queda fuera del listado de los Usuarios y de las nuevas asignaciones, y el 100 % de las referencias existentes a ellos sigue siendo consultable.
- **SC-009**: El 100 % de las creaciones y ediciones deja registrado el usuario y la fecha.

## Assumptions

- "Administrador de Talento Humano" corresponde al rol Administrador definido en 000-login; el resto de personas autenticadas tiene el rol Usuario y solo consulta.
- Este diccionario depende de 001-diccionario-competencias: existen competencias con sus cuatro grados y su estado, y sus datos se consultan por un contrato público.
- Un comportamiento no puede repetirse dentro del mismo grado; el orden de los comportamientos de un grado es el que registró el Administrador.
- Los grados son siempre los cuatro de la competencia (A–D); no se añaden ni se quitan grados desde este diccionario.
- El control de edición simultánea sigue el mismo criterio que el diccionario de competencias: el segundo guardado se rechaza y se pide recargar.
- Una competencia desactivada pasa a "inactiva" para su registro de forma inmediata, porque este dato no se copia: se lee siempre de la competencia.
- Quien tiene acceso a un registro histórico que referencia comportamientos de una competencia inactiva puede consultarlos por su identificador, aunque no aparezcan en su listado.
- Si ninguna competencia está disponible para crear, se informa y no se puede guardar.
- La búsqueda es solo por nombre de la competencia (no por descripción ni por comportamientos) y el tamaño de página es el estándar del sistema.
- No hay pantalla para consultar el historial de auditoría; la auditoría se registra pero no se muestra en esta feature.
- El nombre de la competencia se muestra como texto, sin enlace a su pantalla en el diccionario de competencias.
- Los textos de la interfaz usan "Comportamientos", "Estado" y "Grados de desarrollo".

## Pantallas *(si la feature tiene UI)*

### Listado de comportamientos
- **Propósito**: consultar el diccionario, buscar, filtrar, ver los comportamientos por grado y, para el Administrador, crear y editar registros.
- **Datos que muestra**: código, nombre, descripción (recortada), tipo y estado (etiqueta) de la competencia asociada; los cuatro grados con sus comportamientos como viñetas al desplegarlos; indicación visual cuando la competencia está inactiva; total y página actual.
- **Acciones disponibles** (y quién puede hacerlas): buscar por nombre, filtrar por tipo, paginar y "Visualizar comportamientos" (Administrador y Usuario); filtrar por estado, Nuevo registro y Editar (solo Administrador). No hay acciones de activar, desactivar ni eliminar.
- **Estados**: cargando (esqueleto de tabla), vacío (sin registros o sin resultados), error con "Reintentar", éxito y sin permisos (el Usuario no ve las acciones de gestión ni el filtro de estado).
- **Mockup**: patrón Listado (`design/Competencias.dc.html`), con Estados (`design/Estados.dc.html`); columnas Código, Nombre, Descripción, Tipo y Estado, y filas desplegables para los grados.

### Formulario de comportamientos (crear y editar)
- **Propósito**: crear el registro de una competencia o modificar los comportamientos de uno existente (de competencia activa o inactiva).
- **Datos que muestra**: al crear, selector con búsqueda por nombre de competencias activas sin registro y, tras elegir, nombre, descripción y tipo de solo lectura; al editar, la competencia en solo lectura; para cada grado A, B, C y D, entre uno y cuatro comportamientos con opción de añadir y quitar, y contador de comportamientos.
- **Acciones disponibles** (y quién puede hacerlas): elegir competencia (solo al crear), añadir, quitar y editar comportamientos, Guardar y Cancelar (solo Administrador).
- **Estados**: cargando (al abrir una edición o las competencias disponibles), error de validación por campo con alerta general, sin competencias disponibles, error de carga con reintento, conflicto de edición, guardando (botón bloqueado), éxito (vuelve al listado) y sin permisos.
- **Mockup**: patrón Formulario (`design/Formulario.dc.html`), con un `fieldset` por grado y una lista de campos de texto repetibles (entre uno y cuatro).
