# Feature Specification: Diccionario de competencias

> **Reemplazada por la feature 019** ([diccionario de competencias unificado](../019-diccionario-competencias-unificado/spec.md)): competencias, comportamientos y preguntas se gestionan ahora en un solo módulo. Este documento se conserva como histórico.

**Feature Branch**: `001-diccionario-competencias`

**Created**: 2026-10-09

**Updated**: 2026-10-10

**Status**: Draft

**Input**: User description: "Gestión del diccionario maestro de competencias organizacionales, fuente única de competencias para los demás módulos. El Administrador de Talento Humano crea, consulta, lista, edita y activa/desactiva competencias; los demás usuarios autenticados solo las consultan. Cada competencia tiene un código único generado por el sistema (COMP-0001, secuencial, no editable), nombre único, descripción, tipo (Cardinal o Específica), estado (Activo o Inactivo) y cuatro grados de desarrollo (A, B, C, D) con descripción obligatoria. Las competencias nunca se eliminan: se desactivan. El listado muestra código, nombre, descripción, tipo y estado, busca por nombre y filtra por tipo y estado; por defecto solo muestra las activas. Se audita quién creó y modificó cada competencia y cada cambio de estado. Los demás módulos solo pueden asignar competencias activas y conservan las referencias a las inactivas."

## Clarifications

### Session 2026-10-09

- Q: ¿Qué longitud máxima deben tener el nombre y las descripciones? → A: Nombre 50 caracteres; descripción de la competencia 500; descripción de cada grado 500.
- Q: ¿Qué debe mostrar el listado en la columna Descripción cuando el texto es largo? → A: Se recorta a unas 2 líneas con puntos suspensivos; el texto completo se ve al pulsar "Visualizar grados".
- Q: Si dos administradores editan a la vez la misma competencia, ¿qué debe pasar con el segundo guardado? → A: Se rechaza con un aviso de que la competencia cambió y se pide recargar para ver la versión actual.
- Q: ¿Cómo debe ordenarse el listado de competencias por defecto? → A: Alfabético por nombre (A–Z).

### Session 2026-10-10 (documento de especificación actualizado)

El documento de especificación revisado (`specify.md`) sustituye o amplía lo anterior:

- El código pasa a tener el formato `COMP-0001` (secuencial), en lugar de `COM-001` (reemplaza la decisión del 2026-10-09).
- Las competencias nunca se eliminan: se activan y desactivan (reemplaza "Eliminar" y el bloqueo por uso).
- Se añaden el estado (Activo/Inactivo), la columna Código y el filtro de estado en el listado, la auditoría de creación, modificación y cambios de estado, y reglas de uso para los demás módulos.
- Siguen vigentes los límites de longitud, el recorte de la descripción, el orden alfabético y el control de edición concurrente de la sesión anterior.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar el diccionario y sus grados (Priority: P1)

Cualquier persona con sesión iniciada (Administrador o Usuario) abre el diccionario, ve el listado de competencias con su código, nombre, descripción, tipo y estado, busca por nombre, filtra por tipo y despliega los cuatro grados de una competencia con "Visualizar grados". El Administrador además puede filtrar por estado.

**Why this priority**: Es el valor central del diccionario: una fuente única y consultable de competencias para todos.

**Independent Test**: Con competencias activas e inactivas, iniciar sesión como Usuario y como Administrador, abrir el listado, buscar y filtrar, y desplegar los grados de una competencia; comprobar qué ve cada rol y que el Usuario no tiene acciones de gestión.

**Acceptance Scenarios**:

1. **Given** existen competencias activas e inactivas, **When** una persona autenticada abre el diccionario, **Then** ve por defecto solo las activas, cada una con código, nombre, descripción, tipo y estado (como etiqueta).
2. **Given** el listado visible, **When** escribe parte de un nombre en la búsqueda, **Then** solo se muestran las competencias cuyo nombre coincide.
3. **Given** el listado visible, **When** filtra por tipo Cardinal o Específica, **Then** solo se muestran las de ese tipo; al combinar búsqueda y filtros se aplican todos.
4. **Given** una competencia en el listado, **When** pulsa "Visualizar grados", **Then** se despliegan los grados A, B, C y D con sus descripciones; al pulsar de nuevo se ocultan.
5. **Given** una persona con rol Usuario, **When** abre el diccionario, **Then** solo ve competencias activas, no ve el filtro de estado y no ve los botones Nueva competencia, Editar ni Activar/Desactivar.
6. **Given** una persona con rol Administrador, **When** abre el diccionario, **Then** ve el filtro de estado y puede elegir ver activas (valor por defecto), inactivas o todas.
7. **Given** no hay competencias o ningún resultado coincide, **When** se muestra el listado, **Then** aparece un estado vacío explicativo; si falla la carga, un error con "Reintentar".
8. **Given** una competencia inactiva a la que otro registro hace referencia, **When** cualquier persona autenticada con acceso a ese registro consulta la competencia, **Then** puede ver sus datos y grados completos.

---

### User Story 2 - Crear una competencia (Priority: P1)

El Administrador crea una competencia indicando nombre, descripción, tipo y la descripción de los cuatro grados; el sistema le asigna un código único y la crea con estado Activo.

**Why this priority**: Sin competencias creadas el diccionario no tiene contenido.

**Independent Test**: Como Administrador, crear una competencia válida y comprobar que aparece en el listado con su código, estado Activo y sus cuatro grados.

**Acceptance Scenarios**:

1. **Given** el Administrador en el formulario de creación, **When** completa nombre, descripción, tipo y las cuatro descripciones de grado y guarda, **Then** la competencia se crea con un código generado por el sistema, estado Activo, y aparece en el listado.
2. **Given** el formulario, **When** falta el nombre, la descripción, el tipo o la descripción de algún grado, **Then** se muestran errores por campo y no se guarda nada.
3. **Given** ya existe una competencia (activa o inactiva) con el mismo nombre (sin distinguir mayúsculas ni espacios alrededor), **When** intenta guardar, **Then** se rechaza con un error en el campo nombre.
4. **Given** un formulario con errores, **When** se corrige un campo, **Then** el resto de valores escritos se conserva.

---

### User Story 3 - Editar una competencia (Priority: P2)

El Administrador modifica el nombre, la descripción, el tipo o las descripciones de los grados de una competencia, esté activa o inactiva; el código y el estado no cambian por esta vía.

**Why this priority**: Mantiene el diccionario correcto sin recrear competencias.

**Independent Test**: Editar una competencia activa y una inactiva, cambiar nombre y un grado, guardar y comprobar los cambios, que el código y el estado son los mismos y que queda registrado quién la modificó.

**Acceptance Scenarios**:

1. **Given** una competencia existente (activa o inactiva), **When** el Administrador abre su edición, **Then** ve el formulario con los valores actuales y el código visible pero no editable.
2. **Given** el formulario de edición, **When** cambia valores válidos y guarda, **Then** los cambios se reflejan, el código y el estado permanecen iguales y se registra quién la modificó y cuándo.
3. **Given** el formulario de edición, **When** deja vacío un campo obligatorio o usa el nombre de otra competencia, **Then** se muestran errores por campo y no se guarda.
4. **Given** una persona con rol Usuario, **When** intenta editar (por la interfaz o directamente), **Then** se le deniega por falta de permisos.

---

### User Story 4 - Activar y desactivar una competencia (Priority: P2)

El Administrador desactiva una competencia que ya no debe asignarse, o vuelve a activar una inactiva. Las competencias nunca se eliminan, para preservar históricos y auditoría.

**Why this priority**: Retira competencias obsoletas del uso sin perder los registros que ya las referencian.

**Independent Test**: Desactivar una competencia activa (con confirmación) y comprobar que deja de verse en el listado por defecto y de poder asignarse, y volver a activarla (sin confirmación) y verla de nuevo.

**Acceptance Scenarios**:

1. **Given** una competencia activa, **When** el Administrador pulsa Desactivar, **Then** se pide confirmación que nombra la competencia e informa que no podrá asignarse en nuevos registros.
2. **Given** el diálogo de confirmación, **When** confirma, **Then** la competencia pasa a Inactiva y desaparece del listado por defecto; si cancela, no cambia nada.
3. **Given** una competencia inactiva, **When** el Administrador pulsa Activar, **Then** pasa a Activa de inmediato, sin pedir confirmación.
4. **Given** el listado, **When** se muestra una competencia, **Then** el botón alterna su texto: dice Desactivar si está activa y Activar si está inactiva.
5. **Given** una competencia desactivada que otros registros ya referencian, **When** se consultan esos registros, **Then** la referencia se conserva y la competencia sigue siendo consultable.
6. **Given** una persona con rol Usuario, **When** intenta activar o desactivar (por la interfaz o directamente), **Then** se le deniega por falta de permisos.

---

### User Story 5 - Trazabilidad de los cambios (Priority: P3)

El sistema registra quién creó cada competencia y cuándo, quién la modificó por última vez y cuándo, y cada cambio de estado con su usuario, fecha, estado anterior y estado nuevo.

**Why this priority**: Garantiza auditoría y permite reconstruir la historia de una competencia, ya que nunca se elimina.

**Independent Test**: Crear una competencia, editarla, desactivarla y activarla con distintos usuarios administradores, e inspeccionar los registros de auditoría para comprobar que reflejan cada acción.

**Acceptance Scenarios**:

1. **Given** el Administrador crea una competencia, **When** se guarda, **Then** queda registrado quién la creó y cuándo.
2. **Given** una competencia existente, **When** un administrador la edita, **Then** queda registrado quién la modificó por última vez y cuándo, sin alterar quién la creó.
3. **Given** una competencia, **When** cambia de estado (a Inactivo o a Activo), **Then** se añade al historial un registro con el usuario, la fecha, el estado anterior y el estado nuevo, y los anteriores se conservan.
4. **Given** el historial de una competencia, **When** se consulta, **Then** los registros no se pueden modificar ni borrar desde la aplicación.

---

### Edge Cases

- Nombre duplicado que difiere solo en mayúsculas/minúsculas o en espacios alrededor: se considera duplicado, también frente a una competencia inactiva.
- Descripciones con solo espacios: se tratan como vacías.
- Nombre de más de 50 caracteres o descripción (de la competencia o de un grado) de más de 500: se rechaza con un error en el campo y el formulario indica el límite.
- Dos administradores crean a la vez competencias con el mismo nombre: solo una se guarda y la otra recibe el error de nombre duplicado.
- Dos administradores editan a la vez la misma competencia: el segundo guardado se rechaza con un aviso de que la competencia cambió y se pide recargar para ver la versión actual; no se sobrescribe en silencio.
- Dos administradores cambian a la vez el estado de la misma competencia: no se registran cambios repetidos; el segundo recibe la situación actual y, si ya está en el estado pedido, no se añade un registro de historial.
- Una competencia es desactivada por otra persona mientras se consulta o se edita: se informa del estado actual; la edición sigue siendo posible.
- Se pide una competencia que no existe: se informa que no existe.
- Un Usuario abre directamente la dirección de una competencia inactiva a la que tiene acceso por un registro histórico: puede consultarla, pero no aparece en su listado.
- Listado con muchas competencias: se navega por páginas y la búsqueda y los filtros se conservan en la dirección de la página.
- Todas las competencias están inactivas y la persona usa el filtro por defecto: se muestra el estado vacío con la indicación de que puede ver también las inactivas (si es Administrador).
- Sin sesión iniciada no se accede al diccionario (feature 000-login).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema MUST permitir al Administrador crear, consultar, listar, editar, activar y desactivar competencias.
- **FR-002**: El sistema MUST permitir a cualquier persona autenticada consultar una competencia (activa o inactiva) y listar las competencias activas, y MUST denegar al rol Usuario las operaciones de crear, editar, activar y desactivar.
- **FR-003**: Cada competencia MUST tener nombre (máximo 50 caracteres), descripción (máximo 500 caracteres) y tipo (Cardinal o Específica) obligatorios.
- **FR-004**: El sistema MUST generar para cada competencia un código único con el formato `COMP-` más un número secuencial de al menos 4 dígitos (`COMP-0001`, `COMP-0002`…), que MUST NOT poder modificarse nunca ni reutilizarse.
- **FR-005**: El nombre de cada competencia MUST ser único entre todas las competencias (activas e inactivas), comparándolo sin distinguir mayúsculas/minúsculas ni espacios al inicio o al final.
- **FR-006**: Cada competencia MUST tener exactamente cuatro grados de desarrollo (A, B, C y D), uno de cada letra, cada uno con una descripción obligatoria de máximo 500 caracteres.
- **FR-007**: Cada competencia MUST tener un estado, Activo o Inactivo; toda competencia nueva MUST crearse Activa.
- **FR-008**: El sistema MUST NOT permitir eliminar competencias: la única forma de retirarlas del uso es desactivarlas.
- **FR-009**: El Administrador MUST poder desactivar una competencia activa y volver a activar una inactiva. Desactivar MUST pedir una confirmación accesible que nombre la competencia e informe que no podrá asignarse en nuevos registros; activar MUST NOT pedir confirmación.
- **FR-010**: Una competencia inactiva MUST poder seguir editándose; la edición MUST NOT cambiar su código ni su estado.
- **FR-011**: El sistema MUST mostrar errores por campo en el formulario y conservar los valores escritos cuando la validación falla, e impedir envíos duplicados mientras un guardado o cambio de estado está en curso.
- **FR-012**: El listado MUST mostrar código, nombre, descripción (recortada a unas 2 líneas con puntos suspensivos si es larga), tipo y estado (como etiqueta) de cada competencia, y ofrecer solo al Administrador la acción de crear y los botones Editar y Activar/Desactivar; el botón de estado MUST alternar su texto según el estado actual.
- **FR-013**: Cada competencia del listado MUST ofrecer "Visualizar grados", disponible para todas las personas autenticadas, que despliega y oculta la descripción completa de la competencia y sus cuatro grados con sus descripciones.
- **FR-014**: El listado MUST permitir buscar por nombre y filtrar por tipo, combinables, y el Administrador además MUST poder filtrar por estado (activas, inactivas o todas). Por defecto, para todos los roles, MUST mostrar solo las competencias activas. Búsqueda, filtros y página MUST conservarse en la dirección de la página.
- **FR-015**: El listado MUST ordenarse alfabéticamente por nombre (A–Z), estar paginado y MUST cubrir los estados cargando, vacío, error con reintento y éxito. El filtro de estado MUST NOT mostrarse ni aplicarse a quien no sea Administrador: ese rol solo recibe competencias activas.
- **FR-016**: El sistema MUST registrar quién creó cada competencia y cuándo, y quién la modificó por última vez y cuándo.
- **FR-017**: El sistema MUST registrar cada cambio de estado en un historial con usuario, fecha, estado anterior y estado nuevo; los registros del historial MUST conservarse y MUST NOT poder modificarse ni borrarse desde la aplicación.
- **FR-018**: El diccionario MUST ser la fuente única de competencias: ningún módulo mantiene su propio catálogo y los demás módulos MUST consultarlas por un contrato público.
- **FR-019**: Para nuevas asignaciones, los demás módulos MUST poder usar solo competencias activas; las referencias existentes a competencias inactivas MUST conservarse y seguir siendo consultables desde los registros históricos por cualquier persona con acceso a esos registros.
- **FR-020**: Toda la interfaz MUST estar en español y cumplir WCAG 2.2 AA entre 320 y 1440 px.
- **FR-021**: Toda operación MUST requerir sesión iniciada y el sistema MUST verificar el rol en cada petición, sin depender solo de lo que muestre la interfaz; en particular, un Usuario MUST NOT poder obtener competencias inactivas en el listado aunque lo pida directamente.
- **FR-022**: Al editar, el sistema MUST rechazar el guardado si la competencia cambió desde que se abrió el formulario, con un aviso que pida recargar.

**Fuera de alcance**: gestión de los módulos que usan las competencias, asignación de competencias a cargos o personas, importación/exportación masiva, la pantalla para consultar el historial de cambios de estado (el historial se registra, pero esta feature no define cómo mostrarlo) y la eliminación física de competencias.

### Key Entities *(include if feature involves data)*

- **Competencia**: unidad del diccionario; tiene código único e inmutable, nombre único, descripción, tipo, estado y cuatro grados; guarda quién la creó y cuándo, y quién la modificó por última vez y cuándo.
- **Tipo**: clasificación de la competencia, con dos valores: Cardinal o Específica.
- **Estado**: situación de la competencia, Activo o Inactivo; solo las activas pueden asignarse en nuevos registros.
- **Grado de desarrollo**: nivel A, B, C o D de una competencia, con su descripción; toda competencia tiene los cuatro.
- **Historial de estado**: registro inmutable de cada cambio de estado de una competencia, con usuario, fecha, estado anterior y estado nuevo.
- **Referencia de otro módulo**: vínculo que un módulo externo guarda hacia una competencia; se conserva aunque la competencia pase a Inactiva.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El Administrador crea una competencia completa (con sus cuatro grados) en menos de 3 minutos.
- **SC-002**: Una persona encuentra una competencia por nombre o por tipo en menos de 15 segundos.
- **SC-003**: El 100 % de los intentos de crear, editar, activar o desactivar por parte de un Usuario se rechazan.
- **SC-004**: El 100 % de las competencias permanece en el sistema: ninguna acción de la aplicación elimina una competencia.
- **SC-005**: Ninguna pareja de competencias comparte código ni nombre (sin distinguir mayúsculas), incluso con creaciones simultáneas y entre activas e inactivas.
- **SC-006**: Toda competencia mostrada o consultada tiene siempre sus cuatro grados con descripción.
- **SC-007**: El 100 % de las creaciones, ediciones y cambios de estado deja registrado el usuario y la fecha, y cada cambio de estado conserva el estado anterior y el nuevo.
- **SC-008**: El 100 % de las competencias inactivas queda fuera de las nuevas asignaciones y del listado de los Usuarios, y el 100 % de las referencias existentes a ellas sigue siendo consultable.

## Assumptions

- "Administrador de Talento Humano" corresponde al rol Administrador definido en 000-login; el resto de personas autenticadas tiene el rol Usuario y solo consulta.
- Todos los campos de una competencia, salvo el código y el estado, son editables en el formulario, incluido el tipo; el estado solo cambia con Activar/Desactivar.
- Los grados A–D son siempre los mismos cuatro niveles; solo varía su descripción.
- El número del código es secuencial, nunca se reutiliza y puede superar los 4 dígitos si hiciera falta.
- Aún no existen otros módulos que usen competencias; la regla de "solo competencias activas en nuevas asignaciones" y la conservación de referencias se definen como contrato público que cada módulo futuro deberá cumplir.
- Quien tiene acceso a un registro histórico que referencia una competencia inactiva puede consultarla por su dirección o identificador, aunque no aparezca en su listado.
- La búsqueda es solo por nombre (no por descripción) y el tamaño de página es el estándar del sistema.
- El historial de cambios de estado se registra pero esta feature no lo muestra en pantalla; su consulta se define en una feature posterior.
- Las modificaciones de datos distintas del estado se auditan solo con "última modificación" (quién y cuándo), sin guardar versiones anteriores.
- Los textos de la interfaz usan "Tipo", "Estado" y "Grados de desarrollo".

## Pantallas *(si la feature tiene UI)*

### Listado de competencias
- **Propósito**: consultar el diccionario, buscar, filtrar, ver grados y, para el Administrador, gestionar competencias y su estado.
- **Datos que muestra**: código, nombre, descripción (recortada), tipo y estado (etiqueta) de cada competencia; los cuatro grados con su descripción al desplegarlos; total y página actual.
- **Acciones disponibles** (y quién puede hacerlas): buscar por nombre, filtrar por tipo, paginar y "Visualizar grados" (Administrador y Usuario); filtrar por estado, Nueva competencia, Editar y Activar/Desactivar (solo Administrador).
- **Estados**: cargando (esqueleto de tabla), vacío (sin competencias o sin resultados), error con "Reintentar", éxito, sin permisos (el Usuario no ve las acciones de gestión ni el filtro de estado) y confirmación al desactivar.
- **Mockup**: patrón Listado (`design/Competencias.dc.html`), con Estados (`design/Estados.dc.html`); columnas Código, Nombre, Descripción, Tipo y Estado.

### Formulario de competencia (crear y editar)
- **Propósito**: crear una competencia o modificar una existente (activa o inactiva).
- **Datos que muestra**: nombre, descripción, tipo, código (solo lectura al editar) y la descripción de los grados A, B, C y D.
- **Acciones disponibles** (y quién puede hacerlas): Guardar competencia y Cancelar (solo Administrador).
- **Estados**: cargando (al abrir una edición), error de validación por campo con alerta general, error de carga con reintento, conflicto de edición, guardando (botón bloqueado), éxito (vuelve al listado) y sin permisos.
- **Mockup**: patrón Formulario (`design/Formulario.dc.html`), con los grados en un `fieldset`.
