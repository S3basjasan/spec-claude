# Feature Specification: Categorías e indicadores cualitativos de reconocimiento

**Feature Branch**: `016-categorias-reconocimiento`

**Created**: 2026-10-16

**Status**: Draft

**Input**: User description: "Parametrización de categorías e indicadores cualitativos de reconocimiento. Gestionar el catálogo maestro de categorías de reconocimiento y los indicadores cualitativos de cada una. Cada categoría se vincula a una competencia del Diccionario de Competencias y tiene una puntuación base; es la fuente única de categorías para hitos, desafíos y ranking. La puntuación es solo un valor de ordenamiento del ranking interno: no es dinero ni se canjea por bienes, días libres ni bonos." (Fuente completa: `specify-categorias-reconocimiento-v3.md`.)

## Clarifications

### Session 2026-10-16

- Q: Si el Administrador guarda una edición sin cambiar ningún dato, ¿qué debe hacer el sistema? → A: Se acepta con mensaje de éxito sin modificar nada (ni fecha, ni autor de la última modificación, ni historial).
- Q: Si alguien escribe en el buscador más de 100 caracteres, ¿qué debe pasar? → A: El campo no admite más de 100 caracteres (se corta al escribir o pegar).
- Q: Si se pide Activar una categoría que ya está activa (o Desactivar una inactiva), ¿cómo responde el sistema? → A: Con marca vigente se acepta sin cambios ni auditoría; con marca desactualizada se rechaza por modificación concurrente.
- Q: ¿El filtro por competencia del listado ofrece también las inactivas? → A: Solo al Administrador (marcadas como inactivas); los demás roles ven solo las activas.
- Q: ¿Qué exige el sistema para que un Usuario sin rol de administración consulte una categoría inactiva? → A: Nada adicional: pueden consultar por identificador cualquier categoría inactiva; solo se oculta del listado.
- Q: ¿Cómo se acota el alcance a los roles existentes? → A: Solo Administrador (capacidad admin) y Usuario (cualquier otro autenticado); Líder, Colaborador, Gerencia y Candidato quedan fuera hasta que existan.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar el catálogo de categorías (Priority: P1)

Un usuario sin rol de administración (rol Usuario) abre «Categorías de reconocimiento», busca por nombre de categoría o de competencia, filtra por competencia y abre el detalle de una categoría para ver su descripción, puntuación base e indicadores (lista de viñetas en orden). El Administrador de Talento Humano ve además las categorías inactivas y puede filtrar por estado.

**Why this priority**: Es la lectura del catálogo que consumen los demás módulos y el valor mínimo para que cualquier usuario conozca qué conductas se reconocen.

**Independent Test**: Con categorías activas e inactivas precargadas, comprobar que un Usuario solo ve las activas, que quien no tiene sesión no accede, que la búsqueda ignora mayúsculas y tildes, y que la búsqueda, los filtros y la página quedan en la URL.

**Acceptance Scenarios**:

1. **Given** categorías activas e inactivas, **When** un Usuario abre el listado, **Then** ve solo las activas, ordenadas por código ascendente, con código, ícono, nombre, competencia asociada, puntuación base, estado y la acción «Ver».
2. **Given** las mismas categorías, **When** el Administrador abre el listado, **Then** ve por defecto solo las activas, dispone del filtro de estado (Todos, Activos, Inactivos) y de las acciones Editar y Activar / Desactivar por fila.
3. **Given** un listado de más de 15 categorías, **When** se navega, **Then** se muestran 15 por página y una página fuera de rango muestra la última.
4. **Given** el listado, **When** se escribe parte del nombre de una categoría o de su competencia (hasta 100 caracteres, sin distinguir mayúsculas ni tildes), **Then** se muestran solo las coincidencias; si no hay ninguna, el mensaje «No se encontraron categorías de reconocimiento que coincidan con la búsqueda.»
5. **Given** una búsqueda, filtro y página aplicados, **When** se recarga o se comparte la URL, **Then** se restaura el mismo estado de la vista.
6. **Given** una categoría cuya competencia está inactiva, **When** aparece en el listado, **Then** muestra junto a la competencia la advertencia «Competencia inactiva».
7. **Given** el listado, **When** se pulsa «Ver» en una categoría, **Then** se muestran todos sus datos y sus indicadores en orden como lista de viñetas; el Administrador ve además, en solo lectura, quién y cuándo la creó y la modificó por última vez.
8. **Given** un Usuario sin rol de administración, **When** intenta crear, editar, activar o desactivar categorías (por la interfaz o directamente), **Then** se le deniega y no ve los controles de gestión.
9. **Given** una persona sin sesión, **When** intenta acceder, **Then** se le exige autenticarse.
10. **Given** la vista del listado, **When** carga, falla o no hay datos, **Then** se muestran respectivamente los estados cargando, error con botón «Reintentar» y vacío.
11. **Given** una categoría inactiva, **When** un Usuario la consulta por su identificador (por ejemplo desde un hito o el historial del colaborador), **Then** puede consultarla; solo se oculta del listado.

---

### User Story 2 - Crear una categoría con sus indicadores (Priority: P1)

El Administrador pulsa «Nueva categoría», completa nombre, descripción, competencia (solo activas), puntuación base, ícono y de 1 a 5 indicadores. Al guardar, el sistema asigna el código CAT-### y la categoría nace Activa.

**Why this priority**: Sin categorías no existe nada que reconocer; es el segundo pilar del valor mínimo junto con la consulta.

**Independent Test**: Con un catálogo vacío, crear «Trabajo en equipo» con dos indicadores y comprobar que obtiene CAT-001, nace Activa y aparece en el listado; repetir el nombre con otra capitalización, tildes distintas o espacios exteriores se rechaza junto al campo.

**Acceptance Scenarios**:

1. **Given** el Administrador en el listado, **When** pulsa «Nueva categoría», **Then** se abre un modal con nombre, descripción, competencia, puntuación base, selector visual de ícono y la lista de indicadores con uno inicial.
2. **Given** datos válidos, **When** guarda, **Then** se asigna el código siguiente (CAT-001, CAT-002…; tras CAT-999 sigue CAT-1000), la categoría queda Activa, se muestra un mensaje de confirmación y el listado se actualiza.
3. **Given** el formulario, **When** el nombre tiene menos de 3 o más de 100 caracteres tras recortar espacios, o la descripción menos de 10 o más de 500, o la puntuación no es un entero entre 1 y 1000, o falta competencia o ícono, **Then** el error se muestra junto a cada campo, lo escrito se conserva y no se guarda nada.
4. **Given** una categoría «Liderazgo», **When** se intenta crear «  LIDERAZGO  » o «liderazgo» (con o sin tildes), **Then** se rechaza por nombre ya existente, incluso si la existente está inactiva.
5. **Given** el desplegable de competencia, **When** se abre, **Then** solo ofrece competencias activas.
6. **Given** la lista de indicadores, **When** hay 5, **Then** «Agregar indicador» está deshabilitado; **When** queda 1, «Eliminar» está deshabilitado; al eliminar uno intermedio los demás se renumeran.
7. **Given** indicadores, **When** alguno tiene menos de 5 o más de 250 caracteres tras recortar espacios, o dos indicadores coinciden (sin distinguir mayúsculas ni tildes), **Then** se rechaza con el error junto al indicador.
8. **Given** cualquier campo de texto con marcado HTML, **When** se guarda y luego se muestra, **Then** se trata como texto plano y se presenta escapado.
9. **Given** un envío pendiente, **When** se espera la respuesta, **Then** el botón de guardar permanece bloqueado.
10. **Given** cambios sin guardar, **When** se pulsa «Cancelar», **Then** se pide confirmación antes de cerrar.
11. **Given** una solicitud que incluye código, estado inicial, orden de indicadores o datos de auditoría, **When** llega al sistema, **Then** se rechaza.
12. **Given** una persona que no es Administrador, **When** intenta crear (por la interfaz o directamente), **Then** se le deniega.
13. **Given** dos creaciones simultáneas, **When** ambas se confirman, **Then** reciben códigos distintos; el sistema reintenta hasta obtener un código libre.

---

### User Story 3 - Editar una categoría e indicadores (Priority: P1)

El Administrador edita los datos y la lista completa de indicadores de una categoría, activa o inactiva. Si otra persona la modificó mientras tanto, el sistema no sobrescribe en silencio.

**Why this priority**: Los criterios de reconocimiento evolucionan; sin edición habría que crear categorías nuevas y se fragmentaría el historial.

**Independent Test**: Editar una categoría cambiando puntuación e indicadores y verificar que queda auditado; abrir dos ediciones simultáneas y comprobar que la segunda se rechaza conservando lo escrito.

**Acceptance Scenarios**:

1. **Given** una categoría, **When** el Administrador pulsa Editar, **Then** el modal abre con los datos actuales precargados; el código no es editable y el estado no se elige en el formulario.
2. **Given** cambios válidos, **When** guarda, **Then** la lista de indicadores enviada reemplaza a la anterior, el orden se reasigna consecutivo desde 1, se muestra confirmación y el listado se actualiza.
3. **Given** el nombre editado, **When** solo difiere de su propio nombre en mayúsculas, tildes o espacios exteriores, **Then** no se considera duplicado de sí mismo; si coincide con otra categoría, se rechaza.
4. **Given** una categoría con competencia inactiva, **When** se edita sin cambiarla, **Then** esa competencia se muestra como valor actual deshabilitado con la advertencia y se acepta el guardado.
5. **Given** una categoría con competencia inactiva, **When** se reasigna a otra competencia, **Then** solo se admiten competencias activas y, al guardar, la categoría vuelve a ser seleccionable (si está Activa).
6. **Given** una edición abierta, **When** otra persona modifica la categoría antes de guardar, **Then** no se aplica nada, se muestra «La categoría fue modificada por otro usuario. Recarga para ver los cambios.» y el formulario conserva lo escrito.
7. **Given** una categoría Inactiva, **When** se edita, **Then** el cambio se guarda y sigue Inactiva.
8. **Given** una edición exitosa, **When** se guarda, **Then** se registra por cada campo cambiado (nombre, descripción, competencia, puntuación, ícono, indicadores) el valor anterior, el nuevo, el usuario y la fecha; los indicadores se registran como lista anterior y lista nueva.
9. **Given** una persona que no es Administrador, **When** intenta editar, **Then** se le deniega.
10. **Given** una edición sin ningún cambio real en los datos ni en los indicadores, **When** se guarda, **Then** se acepta con mensaje de éxito sin alterar la fecha ni el autor de la última modificación ni el historial.

---

### User Story 4 - Activar y desactivar categorías (Priority: P2)

El Administrador desactiva una categoría que ya no debe otorgarse y puede reactivarla. Desactivar no la elimina ni afecta lo ya otorgado.

**Why this priority**: Como las categorías nunca se eliminan, desactivar es la única forma de retirarlas de uso sin perder el historial.

**Independent Test**: Desactivar una categoría con hitos previos y comprobar que desaparece de los selectores de nuevos registros pero sigue visible en el historial; reactivarla y comprobar que vuelve a ser seleccionable.

**Acceptance Scenarios**:

1. **Given** una categoría Activa, **When** el Administrador pulsa «Desactivar», **Then** se pide confirmación indicando que no podrá usarse en nuevos hitos ni desafíos y, al confirmar, pasa a Inactiva con mensaje de confirmación.
2. **Given** una categoría Inactiva, **When** pulsa «Activar», **Then** pasa a Activa sin confirmación previa y con mensaje de confirmación.
3. **Given** una categoría desactivada, **When** otro módulo pide categorías para nuevos registros, **Then** no se le devuelve; las ya otorgadas siguen resolviéndose.
4. **Given** una categoría con competencia inactiva, **When** se activa o desactiva, **Then** el cambio se aplica y la advertencia «Competencia inactiva» se mantiene.
5. **Given** un estado desactualizado en pantalla, **When** se cambia el estado y otra persona ya modificó la categoría, **Then** se rechaza con el mensaje de modificación concurrente.
6. **Given** cualquier rol, **When** busca eliminar una categoría, **Then** no existe esa acción en la interfaz ni de forma directa.
7. **Given** una persona que no es Administrador, **When** intenta cambiar el estado, **Then** se le deniega.
8. **Given** una categoría que ya está en el estado solicitado y una marca de modificación vigente, **When** se pulsa Activar o Desactivar, **Then** se acepta sin cambios ni registro de auditoría; si la marca está desactualizada, se rechaza por modificación concurrente.

---

### User Story 5 - Proveer categorías seleccionables a otros módulos (Priority: P2)

Hitos, desafíos y ranking obtienen del catálogo únicamente categorías seleccionables (Activas con competencia activa) para nuevos registros, y pueden seguir resolviendo cualquier categoría en registros históricos. El ranking lee la puntuación base; al otorgar un hito se guarda una copia de ella.

**Why this priority**: Es la razón de ser de «fuente única de categorías», pero depende de que el catálogo (P1) exista y de módulos consumidores aún por construir.

**Independent Test**: Con categorías activas, inactivas y con competencia inactiva, comprobar que el contrato de consulta para nuevos registros devuelve solo las seleccionables, que usar una no seleccionable en un nuevo registro se rechaza y que cambiar luego la puntuación no altera un hito ya otorgado.

**Acceptance Scenarios**:

1. **Given** el catálogo, **When** otro módulo solicita categorías para nuevos hitos o desafíos, **Then** recibe solo las Activas cuya competencia está activa.
2. **Given** una categoría no seleccionable, **When** se intenta usar en un nuevo registro, **Then** el sistema lo rechaza.
3. **Given** una categoría inactiva o con competencia inactiva usada en un registro histórico, **When** otro módulo la consulta por identificador, **Then** se le devuelve con sus datos e indicadores.
4. **Given** una competencia que pasa a inactiva, **When** se consulta el catálogo, **Then** las categorías asociadas muestran la advertencia y dejan de ser seleccionables sin que se guarde un tercer estado; al reactivarse la competencia vuelven a serlo.
5. **Given** un hito ya otorgado, **When** después cambian la puntuación, la competencia o los indicadores de su categoría, **Then** la puntuación guardada en el hito no cambia.

---

### Edge Cases

- Catálogo vacío: el listado muestra el estado vacío; el Administrador conserva «Nueva categoría».
- Nombre idéntico a otro con distinta capitalización, tildes o espacios exteriores, inclusive entre categorías inactivas: se rechaza.
- Indicadores repetidos tras recortar espacios y normalizar mayúsculas/tildes: se rechaza.
- Lista de indicadores vacía o con más de 5 en una solicitud directa: se rechaza aunque la interfaz lo impida.
- Competencia inexistente o inactiva al crear o cambiarla: se rechaza.
- Una categoría inexistente se trata igual en consulta, edición y cambio de estado (sin revelar detalles).
- Puntuación con decimales, texto, cero, negativa o mayor a 1000: se rechaza.
- Ícono fuera de la lista cerrada definida en el sistema de diseño: se rechaza.
- Dos administradores editan o cambian el estado a la vez: gana el primero; el segundo recibe el aviso de modificación concurrente sin perder lo escrito.
- Página fuera de rango: se muestra la última; búsqueda de más de 100 caracteres: el campo no admite más de 100 (se corta al escribir o pegar).
- Falla de red o del servidor al guardar: no se muestra éxito, se conserva lo escrito y la categoría mantiene sus datos previos.
- Los mensajes de error no revelan información sensible ni la existencia de recursos de otras organizaciones.

## Requirements *(mandatory)*

### Functional Requirements

**Acceso y aislamiento**

- **FR-001**: El sistema MUST exigir sesión autenticada para toda operación y MUST distinguir en su respuesta la falta de sesión de la falta de permiso.
- **FR-002**: El sistema MUST aplicar los permisos en el servidor, no solo ocultando controles: el Administrador de Talento Humano puede listar (activas e inactivas), consultar, crear, editar, activar y desactivar; cualquier otro usuario autenticado (rol Usuario) solo puede listar categorías activas y consultar una por identificador, incluidas las inactivas (que solo se ocultan del listado). Los roles diferenciados Líder, Colaborador, Gerencia y Candidato no existen todavía en el sistema y quedan fuera de esta feature.
- **FR-003**: El sistema opera sobre una única organización (la instalación): el catálogo es único y no se segmenta por organización. Una categoría inexistente MUST responder igual en consulta, edición y cambio de estado, sin revelar información adicional.
- **FR-004**: El sistema MUST NOT ofrecer ninguna acción de eliminación de categorías, ni física ni lógica, a ningún rol.

**Datos de la categoría**

- **FR-005**: El sistema MUST generar el código con formato CAT-### secuencial (al menos tres dígitos; tras CAT-999 sigue CAT-1000), único e inmutable, y MUST garantizar que no se repita ante creaciones simultáneas.
- **FR-006**: El nombre MUST ser obligatorio, de 3 a 100 caracteres tras recortar espacios exteriores, y único entre todas las categorías (activas e inactivas) sin distinguir mayúsculas, tildes ni espacios exteriores; al editar, la categoría no compite consigo misma.
- **FR-007**: La descripción MUST ser obligatoria, de 10 a 500 caracteres tras recortar espacios.
- **FR-008**: La competencia asociada MUST ser obligatoria y provenir del Diccionario de Competencias; al crear o al cambiarla solo MUST admitirse competencias activas, y varias categorías pueden compartir la misma competencia.
- **FR-009**: La puntuación base MUST ser obligatoria, un número entero de 1 a 1000.
- **FR-010**: El ícono MUST ser obligatorio y pertenecer a la lista cerrada de los doce íconos del tablero de diseño de la feature; cualquier otro valor MUST rechazarse.
- **FR-011**: Los campos de texto MUST tratarse como texto plano (sin HTML) y mostrarse siempre escapados.
- **FR-012**: El sistema MUST rechazar toda solicitud del cliente que incluya código, estado al crear, orden de indicadores o datos de auditoría.

**Indicadores cualitativos**

- **FR-013**: Cada categoría MUST tener de 1 a 5 indicadores, validado también en el servidor; son una guía orientativa de la conducta reconocida y no condiciones que deba marcar quien otorga el reconocimiento.
- **FR-014**: El texto de cada indicador MUST ser obligatorio, de 5 a 250 caracteres tras recortar espacios, y no repetirse dentro de la misma categoría (sin distinguir mayúsculas ni tildes).
- **FR-015**: El sistema MUST asignar el orden de los indicadores, consecutivo desde 1 según la posición enviada, y renumerarlo al eliminar uno intermedio.
- **FR-016**: Al guardar, la lista completa de indicadores enviada MUST reemplazar a la anterior.

**Estado y ciclo de vida**

- **FR-017**: Toda categoría nueva MUST nacer Activa; el estado MUST cambiarse solo con Activar y Desactivar, no desde el formulario de datos.
- **FR-018**: Una categoría inactiva MUST conservarse, poder consultarse y editarse, no aparecer en selectores de nuevos hitos ni desafíos y seguir visible en el historial del colaborador.
- **FR-019**: Una categoría MUST considerarse seleccionable solo si está Activa y su competencia está activa; el sistema MUST rechazar su uso en nuevos registros cuando no lo sea.
- **FR-020**: Si la competencia asociada pasa a inactiva, el sistema MUST mostrar la advertencia «Competencia inactiva» y dejar de considerar seleccionable la categoría; esto MUST calcularse al consultar y no constituir un tercer estado ni persistirse.
- **FR-021**: El Administrador MUST poder seguir editando, activando y desactivando una categoría con competencia inactiva; al editar sin cambiar la competencia no se exige que esté activa.

**Concurrencia**

- **FR-022**: Al editar o cambiar el estado, el sistema MUST detectar si la categoría fue modificada desde que se leyó; en ese caso MUST NOT aplicar nada y MUST responder «La categoría fue modificada por otro usuario. Recarga para ver los cambios.», conservando lo escrito en el formulario.

**Listado y detalle**

- **FR-023**: El listado MUST mostrar código, ícono, nombre, competencia asociada, puntuación base, estado (Activo / Inactivo) y acciones, con la advertencia «Competencia inactiva» junto a la competencia cuando aplique; orden por defecto por código ascendente; por defecto solo categorías activas.
- **FR-024**: El listado MUST ofrecer búsqueda parcial (el campo no admite más de 100 caracteres, cortando al escribir o pegar; sin distinguir mayúsculas ni tildes) por nombre de categoría o de competencia, filtro por competencia para todos los usuarios (el Administrador dispone de todas las competencias, con las inactivas marcadas como tales; los demás roles, solo de las activas; las opciones las provee el sistema sin exigir permisos de administración) y filtro por estado (Todos, Activos, Inactivos) solo para el Administrador.
- **FR-025**: El listado MUST paginar de 15 en 15, mostrar la última página ante una fuera de rango y guardar búsqueda, filtros y página en la URL.
- **FR-026**: El listado MUST presentar los estados cargando, vacío, error con «Reintentar» y éxito, y el mensaje «No se encontraron categorías de reconocimiento que coincidan con la búsqueda.» cuando no haya coincidencias.
- **FR-027**: Por fila MUST ofrecerse «Ver» a todos los roles con acceso, y «Editar» y «Activar / Desactivar» (con texto alternante según el estado) solo al Administrador.
- **FR-028**: El detalle MUST mostrar todos los datos y los indicadores en orden como lista de viñetas, y al Administrador además, en solo lectura, los datos de auditoría.

**Formulario y acciones**

- **FR-029**: Crear y editar MUST usar un modal con nombre, descripción, competencia (desplegable de activas; al editar con competencia inactiva se muestra como valor actual deshabilitado con la advertencia), puntuación base, selector visual de ícono y lista de indicadores con «Agregar indicador» deshabilitado al llegar a 5 y «Eliminar» deshabilitado cuando queda 1.
- **FR-030**: El formulario MUST mostrar los errores junto a cada campo, conservar los valores escritos, bloquear el guardado mientras haya un envío pendiente y pedir confirmación al cancelar con cambios sin guardar.
- **FR-031**: Desactivar MUST pedir confirmación informando que la categoría no podrá usarse en nuevos hitos ni desafíos; Activar MUST NOT requerirla.
- **FR-032**: Tras cada acción exitosa MUST mostrarse un mensaje de confirmación; si falla, un mensaje de error comprensible que no revele información sensible.

**Auditoría**

- **FR-033**: El sistema MUST registrar quién creó la categoría y cuándo, y quién la modificó por última vez y cuándo.
- **FR-034**: Un guardado sin cambios reales, o un cambio de estado hacia el estado actual con marca vigente, MUST aceptarse sin alterar la auditoría ni la fecha de última modificación.
- **FR-035**: El sistema MUST registrar cada cambio con campo afectado, valor anterior, valor nuevo, usuario y fecha para nombre, descripción, competencia, puntuación base, ícono, indicadores (lista anterior y nueva) y estado. La consulta del historial desde la interfaz queda fuera de alcance.

**Relación con otros módulos**

- **FR-036**: El sistema MUST leer competencias solo mediante el contrato público del Diccionario de Competencias (listar activas y consultar una por identificador con su estado), sin modificar ni duplicar sus datos ni acceder a su almacenamiento.
- **FR-037**: El sistema MUST exponer a los módulos de desafíos y logros un contrato público que devuelva solo categorías seleccionables para nuevos registros y que siga resolviendo categorías inactivas para registros históricos.
- **FR-038**: El sistema MUST exponer la puntuación base al ranking; al otorgarse un hito, la puntuación MUST copiarse al hito, de modo que cambios posteriores de puntuación, competencia o indicadores no alteren lo ya otorgado.
- **FR-039**: Ningún otro módulo MUST mantener su propio catálogo de categorías de reconocimiento.
- **FR-040**: La puntuación base MUST tratarse solo como valor de ordenamiento del ranking interno; el sistema MUST NOT presentarla ni usarla como dinero ni como canjeable por bienes, días libres o bonos.

### Key Entities

- **Categoría de reconocimiento**: Elemento del catálogo maestro. Atributos: código (CAT-###, inmutable), nombre, descripción, puntuación base, ícono, estado (Activo / Inactivo) y datos de auditoría (creación y última modificación). Se vincula a una competencia; nunca se elimina.
- **Indicador cualitativo**: Guía textual de la conducta reconocida. Pertenece a una categoría (de 1 a 5), con texto y orden asignado por el sistema.
- **Competencia** (externa): Elemento del Diccionario de Competencias, solo de lectura aquí; aporta identificador, nombre y estado activo/inactivo.
- **Registro de cambios**: Historial de modificaciones de una categoría: campo, valor anterior, valor nuevo, usuario y fecha.
- **Seleccionabilidad** (derivada): Condición calculada al consultar: categoría Activa con competencia activa.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El Administrador puede crear una categoría completa (con tres indicadores) en menos de 3 minutos sin ayuda.
- **SC-002**: Una persona encuentra una categoría concreta por nombre de categoría o de competencia en menos de 30 segundos, sin importar mayúsculas ni tildes.
- **SC-003**: El 100 % de los intentos de usar en un nuevo hito o desafío una categoría inactiva o con competencia inactiva es rechazado.
- **SC-004**: El 100 % de los intentos de acceso sin sesión o de modificación por un Usuario sin rol de administración es denegado, verificado en el servidor.
- **SC-005**: El 100 % de los hitos ya otorgados conserva su puntuación original tras editar la categoría.
- **SC-006**: Ante dos ediciones simultáneas de la misma categoría, en el 100 % de los casos solo se aplica la primera y la segunda conserva lo escrito.
- **SC-007**: Cero códigos de categoría duplicados, incluso con creaciones simultáneas.
- **SC-008**: El 100 % de los cambios en campos auditados queda registrado con valor anterior, nuevo, usuario y fecha.
- **SC-009**: El 95 % de los usuarios de prueba completa su tarea principal (consultar, crear, editar o cambiar estado, según su rol) al primer intento.
- **SC-010**: El listado muestra su primera página en menos de 2 segundos con un catálogo de hasta 500 categorías.

## Assumptions

- **Puntos históricos**: se adopta lo especificado: la puntuación se congela en el hito al otorgarlo; no se recalcula el ranking con la puntuación vigente.
- **Desactivar con desafíos en curso**: se adopta la propuesta: los desafíos ya asignados continúan y solo se bloquea el uso en nuevos registros; se confirmará con la feature de desafíos.
- **Tipo de competencia**: el listado no muestra ni filtra por tipo (Cardinal / Específica) en esta versión.
- **Competencia repetida**: varias categorías pueden compartir competencia; no se exige una categoría por competencia.
- Esta feature usa solo los dos roles que existen hoy en el sistema de autenticación: Administrador (de Talento Humano) y Usuario (cualquier otro usuario autenticado). Los roles Líder, Colaborador, Gerencia y Candidato no existen todavía; cuando se incorporen, se ajustarán los permisos de lectura (por ejemplo, para excluir al Candidato) en una feature posterior.
- Los módulos de hitos, desafíos y ranking consumirán el contrato público; esos módulos quedan fuera de esta feature, que solo define el contrato y las reglas.
- La instalación equivale a una única organización: no hay segmentación multiorganización en esta versión (el entorno usa un único esquema de datos).
- El historial de cambios se registra pero no tiene pantalla de consulta en esta feature.
- Los códigos de respuesta, rutas y cuerpos de la interfaz de programación se definen en el plan; este documento fija solo el comportamiento.
- No hay importación ni exportación masiva de categorías.
- El Diccionario de Competencias (feature 001) está disponible y expone competencias con su estado.

## Pantallas *(si la feature tiene UI)*

### Categorías de reconocimiento (listado)
- **Propósito**: Consultar el catálogo maestro y, para el Administrador, gestionarlo.
- **Datos que muestra**: código, ícono, nombre, competencia asociada (con advertencia «Competencia inactiva» si aplica), puntuación base, estado (badge Activo / Inactivo) y acciones.
- **Acciones disponibles** (y quién puede hacerlas): Ver (Administrador y Usuario); Nueva categoría, Editar, Activar / Desactivar (solo Administrador); buscar y filtrar por competencia (todos); filtrar por estado (solo Administrador).
- **Estados**: cargando, vacío, error con «Reintentar», sin coincidencias, sin sesión.
- **Mockup**: patrón Listado (`design/Competencias.dc.html`), con columnas Código, Ícono, Nombre, Competencia, Puntuación base, Estado y Acciones, filtro de competencia, filtro de estado solo Administrador y paginación de 15. El tablero «Categorías de reconocimiento» aún no existe en `design/`; hasta crearlo se siguen los componentes y tokens de `design/DESIGN.md` y la desviación se registra en el plan.

### Detalle de categoría
- **Propósito**: Ver todos los datos de una categoría y sus indicadores.
- **Datos que muestra**: código, nombre, descripción, competencia, puntuación base, ícono, estado, indicadores como lista de viñetas en orden y, solo al Administrador, auditoría en solo lectura.
- **Acciones disponibles** (y quién puede hacerlas): consultar (Administrador y Usuario); Editar y Activar / Desactivar solo Administrador.
- **Estados**: cargando, error, categoría no encontrada, sin permisos.
- **Mockup**: patrón Detalle de un registro de `design/DESIGN.md`.

### Formulario de categoría (modal)
- **Propósito**: Crear o editar una categoría y sus indicadores.
- **Datos que muestra**: nombre, descripción, competencia (desplegable), puntuación base, selector visual de ícono y lista de indicadores con Agregar y Eliminar.
- **Acciones disponibles** (y quién puede hacerlas): Guardar y Cancelar (solo Administrador); confirmación al cancelar con cambios.
- **Estados**: errores por campo, envío pendiente, conflicto de modificación concurrente, error general.
- **Mockup**: patrón Formulario en modal (`design/Unidades.dc.html`) con lista dinámica de indicadores y selector de ícono.

### Confirmación de desactivación
- **Propósito**: Evitar desactivar por error.
- **Datos que muestra**: nombre de la categoría y aviso de que no podrá usarse en nuevos hitos ni desafíos.
- **Acciones disponibles** (y quién puede hacerlas): Confirmar o Cancelar (solo Administrador).
- **Estados**: pendiente, error.
- **Mockup**: ConfirmDialog (patrón Estados, `design/Estados.dc.html`).
