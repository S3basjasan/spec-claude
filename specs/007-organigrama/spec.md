# Feature Specification: Organigrama de posiciones

**Feature Branch**: `007-organigrama`

**Created**: 2026-10-16

**Status**: Draft

**Input**: User description: "Organigrama de posiciones (documento fuente `specify-organigrama.md`). Muestra la estructura de reporte de la organización como un árbol gráfico construido a partir de las posiciones vigentes (Ocupadas y Vacantes). La raíz es la máxima autoridad (la única posición vigente de la unidad raíz, la Gerencia General) y cada posición cuelga de la posición de su supervisor. Permite recorrer la estructura (expandir, contraer, acercar, alejar y desplazar), buscar posiciones, filtrarlas por unidad organizacional y consultar el detalle de cada una. Es de solo lectura: consume el módulo de posiciones, que es la fuente única de la estructura de reporte, y siempre muestra el estado actual. Todos los usuarios autenticados ven el mismo organigrama; las posiciones Suprimidas nunca se muestran."

## Clarifications

### Session 2026-10-16

- Q: Cuando hay una búsqueda y además un filtro de unidad, ¿una tarjeta se resalta si cumple ambas condiciones o si cumple cualquiera? → A: Debe cumplir ambas: coincide con el texto buscado y pertenece a la unidad elegida o a sus dependientes.
- Q: Si la persona contrae una rama que contiene la posición seleccionada, ¿el panel sigue abierto o se cierra la selección? → A: La selección y el panel se mantienen; la tarjeta queda oculta con la rama y, al expandirla, vuelve a verse resaltada.
- Q: En una pantalla estrecha (por ejemplo, 320 px), ¿cómo se muestra el panel de detalle? → A: Como una capa superpuesta que ocupa el ancho de la pantalla, con foco atrapado y "Cerrar detalle" para volver al árbol.
- Q: Con una búsqueda o un filtro activo, ¿qué hacen "Contraer todo" y el botón de contraer de una rama con coincidencias? → A: Las ramas con coincidencias permanecen expandidas y sus botones de contraer quedan deshabilitados mientras haya búsqueda o filtro; "Contraer todo" contrae solo las ramas sin coincidencias.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver el organigrama de la organización (Priority: P1)

Cualquier persona con sesión iniciada abre el organigrama y ve la estructura de reporte como un árbol de arriba abajo, empezando por la máxima autoridad, con una tarjeta por posición vigente y líneas que unen cada posición con su supervisor. Cada tarjeta muestra la unidad, el código, el puesto, el ocupante y el estado. Un resumen indica cuántas posiciones hay, cuántas están ocupadas y cuántas vacantes.

**Why this priority**: Es el valor central de la feature: ver de un vistazo cómo reporta la organización, sin recorrer listados.

**Independent Test**: Con posiciones vigentes en varios niveles (Ocupadas y Vacantes) y alguna Suprimida, entrar como Usuario y como Administrador; comprobar que el árbol, las tarjetas, la leyenda y el resumen son iguales para ambos, que las Suprimidas no aparecen y que las hermanas están ordenadas por código.

**Acceptance Scenarios**:

1. **Given** existen posiciones vigentes, **When** una persona autenticada abre el organigrama, **Then** ve el título "Organigrama", el subtítulo "Estructura de reporte generada a partir de las posiciones vigentes.", el enlace "Ver listado de posiciones" y el árbol dibujado de arriba abajo desde la máxima autoridad, con líneas que unen cada posición con su supervisor y sin límite de profundidad.
2. **Given** el árbol visible, **When** se muestra cada tarjeta, **Then** presenta el nombre de la unidad de la posición, su código (por ejemplo, POS-0302), el nombre del puesto, el ocupante (nombre del usuario, o "Sin ocupante" en estilo atenuado) y el estado como etiqueta Ocupada o Vacante con los mismos colores que en el módulo de posiciones.
3. **Given** el árbol visible, **When** hay posiciones Vacantes, **Then** su tarjeta se distingue además por el borde discontinuo y el fondo, y una leyenda explica los dos estilos (Ocupada y Vacante).
4. **Given** una posición con varias subordinadas directas, **When** se dibujan, **Then** las tarjetas hermanas aparecen ordenadas por código ascendente.
5. **Given** el organigrama abierto, **When** se muestra el resumen, **Then** sin filtros dice "N posiciones vigentes · N ocupadas · N vacantes" y se anuncia a los lectores de pantalla cuando cambia.
6. **Given** existen posiciones Suprimidas, **When** cualquier persona abre el organigrama (también el Administrador), **Then** no aparecen y la pantalla indica "Las posiciones suprimidas no se muestran."
7. **Given** no hay posiciones vigentes, **When** se abre el organigrama, **Then** aparece el mensaje "Aún no hay posiciones vigentes para mostrar el organigrama."
8. **Given** los datos no se pueden obtener, o no forman un único árbol, **When** se abre el organigrama, **Then** aparece un mensaje de error con la opción de reintentar.
9. **Given** se están obteniendo los datos, **When** se abre el organigrama, **Then** se muestra un estado de carga.
10. **Given** la persona activa "Ver listado de posiciones", **When** lo hace, **Then** va al módulo de posiciones.
11. **Given** el Administrador, **When** abre el organigrama, **Then** no tiene acciones adicionales: no puede crear, editar ni suprimir posiciones desde esta pantalla.

---

### User Story 2 - Consultar el detalle de una posición (Priority: P1)

Al hacer clic en una tarjeta se abre un panel lateral con el detalle de la posición: código, puesto, estado, modalidad, ocupante, supervisor (con un enlace para ir a él), a quién supervisa (cada una con un enlace), puesto con su descripción y unidad con su tipo y estado.

**Why this priority**: Una tarjeta resume; el detalle y la navegación entre supervisor y subordinadas hacen del organigrama una herramienta de consulta, no solo una imagen.

**Independent Test**: Seleccionar una posición con supervisor y subordinadas, comprobar los datos del panel y navegar a su supervisor y a una subordinada; comprobar que solo hay una posición seleccionada a la vez y que el panel se cierra.

**Acceptance Scenarios**:

1. **Given** el árbol visible, **When** la persona hace clic en una tarjeta, **Then** se abre un panel lateral con código, puesto, etiqueta de estado y modalidad de esa posición, y la tarjeta queda resaltada.
2. **Given** el panel abierto, **When** se muestra el ocupante, **Then** aparece el nombre del usuario, o "Sin ocupante" si está Vacante.
3. **Given** el panel de una posición con supervisor, **When** se muestra el supervisor, **Then** aparecen su código, puesto y ocupante como un enlace; si la posición es la máxima autoridad aparece "Máxima autoridad".
4. **Given** el panel, **When** la persona activa el enlace del supervisor, **Then** se selecciona esa posición, su rama se expande y el árbol se desplaza hasta ella.
5. **Given** el panel de una posición con subordinadas, **When** se muestra "Supervisa a (N)", **Then** aparecen las posiciones que le reportan directamente, cada una con código, puesto, ocupante y estado, y cada una es un enlace que la selecciona; si no tiene, aparece "Ninguna posición le reporta."
6. **Given** el panel, **When** se muestran el puesto y la unidad, **Then** aparecen el nombre y la descripción del puesto, y el nombre, el tipo y el estado de la unidad.
7. **Given** el panel, **Then** no muestra la auditoría ni el historial de cambios (se consultan en el módulo de posiciones).
8. **Given** el panel abierto, **When** la persona selecciona otra posición, **Then** solo queda una seleccionada a la vez y el panel muestra la nueva.
9. **Given** el panel abierto, **When** la persona activa "Cerrar detalle", **Then** el panel se cierra.

---

### User Story 3 - Buscar y filtrar por unidad sin perder el contexto (Priority: P2)

La persona busca por código, puesto, unidad u ocupante y filtra por unidad organizacional. El árbol no se recorta: las posiciones que coinciden se resaltan, las demás se atenúan y las ramas con coincidencias se expanden. El árbol se desplaza hasta la primera coincidencia de la búsqueda.

**Why this priority**: En organizaciones medianas encontrar una posición recorriendo el árbol es lento; hacerlo sin perder la cadena hasta la máxima autoridad conserva el contexto.

**Independent Test**: Buscar un código, un puesto, una unidad y un ocupante, y filtrar por una unidad con dependientes; comprobar el resaltado, la atenuación, la expansión de ramas, el resumen y que "Limpiar filtros" devuelve el árbol a como estaba.

**Acceptance Scenarios**:

1. **Given** el organigrama, **When** la persona escribe en la búsqueda parte de un código, de un puesto, de una unidad o del nombre de un ocupante, **Then** las posiciones que coinciden se resaltan, las que no coinciden se muestran atenuadas y el árbol no se recorta; la búsqueda no distingue mayúsculas ni tildes.
2. **Given** una búsqueda o un filtro con coincidencias en ramas contraídas, **When** se aplica, **Then** esas ramas se expanden aunque la persona las hubiera contraído.
3. **Given** una búsqueda, **When** se aplica, **Then** el árbol se desplaza hasta la primera coincidencia (por código).
4. **Given** el filtro de unidad, **When** la persona abre el selector, **Then** ofrece "Todas las unidades" y las unidades en orden de árbol con sangría; al elegir una unidad se resaltan las posiciones de esa unidad y de todas sus dependientes.
5. **Given** hay una búsqueda y además un filtro de unidad, **When** se aplican, **Then** se resaltan solo las posiciones que cumplen ambas condiciones (coinciden con el texto y pertenecen a la unidad elegida o a sus dependientes) y las demás se atenúan.
6. **Given** una búsqueda o un filtro activos, **When** se muestra el resumen, **Then** dice "N posiciones coinciden de M vigentes".
7. **Given** ninguna posición coincide, **When** se aplica la búsqueda o el filtro, **Then** el resumen dice "0 posiciones coinciden de M vigentes" y todas las tarjetas se muestran atenuadas.
8. **Given** hay búsqueda o filtro de unidad, **When** se muestra la barra, **Then** aparece el botón "Limpiar filtros", que borra ambos; sin búsqueda ni filtro no aparece.
9. **Given** hay una búsqueda o un filtro con coincidencias, **When** la persona intenta contraer una rama que las contiene, **Then** su botón de contraer está deshabilitado mientras dure la búsqueda o el filtro, y "Contraer todo" contrae solo las ramas sin coincidencias.
10. **Given** la persona contrajo ramas y seleccionó una posición, **When** busca o filtra, **Then** la búsqueda y el filtro no cambian su selección ni el estado de sus ramas contraídas; al limpiarlos, el árbol vuelve a como estaba.

---

### User Story 4 - Recorrer el árbol: expandir, contraer, acercar, alejar y desplazar (Priority: P2)

La persona expande o contrae ramas, amplía o reduce el tamaño del árbol y lo desplaza para moverse por una estructura grande.

**Why this priority**: Una estructura con muchos niveles no cabe en pantalla; sin estos controles el árbol sería ilegible.

**Independent Test**: Contraer y expandir ramas, usar "Expandir todo" y "Contraer todo", cambiar el zoom entre 50 % y 150 % y desplazar el árbol arrastrando y con el teclado.

**Acceptance Scenarios**:

1. **Given** el organigrama recién abierto, **When** se muestra el árbol, **Then** todas las ramas están expandidas.
2. **Given** una tarjeta con subordinadas, **When** está expandida, **Then** su botón dice "− Contraer"; al activarlo la rama se oculta y el botón indica cuántas posiciones oculta en total, directas e indirectas (por ejemplo, "+ 4 posiciones"); al activarlo de nuevo se expande.
3. **Given** una posición seleccionada dentro de una rama, **When** la persona contrae esa rama, **Then** el panel de detalle sigue abierto con esa posición, su tarjeta queda oculta con la rama y, al expandirla, vuelve a verse resaltada.
4. **Given** el organigrama, **When** la persona activa "Expandir todo", **Then** se abren todas las ramas; con "Contraer todo" queda visible solo la máxima autoridad.
5. **Given** el organigrama, **When** la persona usa Alejar o Acercar, **Then** el tamaño cambia en pasos de 10 % entre 50 % y 150 %, se muestra el porcentaje actual y al activarlo se restablece el 100 %.
6. **Given** el árbol más grande que el área visible, **When** la persona lo arrastra con el puntero, o usa las barras de desplazamiento o el teclado, **Then** el árbol se desplaza; arrastrar sobre una tarjeta o un botón no desplaza.
7. **Given** el árbol, **When** se muestra la ayuda bajo él, **Then** dice "Arrastra para desplazarte. Haz clic en una tarjeta para ver su detalle."

---

### User Story 5 - Usar el organigrama con teclado y lectores de pantalla (Priority: P3)

Toda la interacción es alcanzable con el teclado y los controles comunican su estado y su nombre.

**Why this priority**: Es un requisito de accesibilidad del producto; un árbol gráfico es especialmente propenso a excluir a quien no usa el puntero.

**Independent Test**: Recorrer el organigrama solo con el teclado: tarjetas, botones de rama, zoom, enlaces del panel y desplazamiento; comprobar los nombres accesibles y los estados.

**Acceptance Scenarios**:

1. **Given** el organigrama, **When** la persona usa el teclado, **Then** cada tarjeta, botón de rama, control de zoom y enlace del panel es un botón real alcanzable con el teclado.
2. **Given** un botón de rama, **When** lo lee un lector de pantalla, **Then** indica si está expandido y su nombre incluye el código de la posición ("Contraer POS-0201", "Expandir POS-0201").
3. **Given** el área del árbol, **When** la persona usa el teclado, **Then** puede desplazarla.
4. **Given** el panel de detalle, **Then** es una región con el nombre "Detalle de la posición".
5. **Given** una pantalla estrecha (por ejemplo, 320 px), **When** se abre el detalle de una posición, **Then** el panel se muestra como una capa superpuesta que ocupa el ancho de la pantalla, con el foco atrapado, que se cierra con "Cerrar detalle" o con Escape y devuelve el foco a la tarjeta que lo abrió.
6. **Given** cualquier ancho entre 320 y 1440 px y los temas claro y oscuro, **When** se usa el organigrama, **Then** no hay desplazamiento horizontal de la página (el árbol se desplaza dentro de su área) y el estado de una posición se comunica con texto, no solo con color.

---

### Edge Cases

- No hay posiciones vigentes: se muestra el estado vacío y no se dibuja árbol.
- Los datos no forman un único árbol (varias raíces, una posición sin camino hasta la máxima autoridad o un ciclo): es un error del módulo de posiciones; el organigrama no lo corrige y muestra el estado de error con la opción de reintentar.
- Solo existe la máxima autoridad: se dibuja una sola tarjeta sin botón de rama.
- La búsqueda o el filtro no encuentran nada: todas las tarjetas se atenúan y el resumen lo indica; no se muestra un estado vacío.
- Una unidad elegida en el filtro no tiene posiciones vigentes: el resumen dice "0 posiciones coinciden de M vigentes" y todo se atenúa.
- Se busca o filtra con una rama contraída por la persona: se expande para mostrar la coincidencia; al limpiar, vuelve a contraída.
- Se selecciona desde el panel una posición dentro de una rama contraída: la rama se expande y el árbol se desplaza hasta ella.
- El zoom llega a un extremo (50 % o 150 %): el control correspondiente no avanza más.
- Una posición cambia de ocupante, de estado, de unidad o de supervisor, o se suprime, mientras la pantalla está abierta: se refleja en la siguiente carga; el organigrama no se actualiza solo y no guarda copia ni fecha.
- Se contrae una rama que contiene la posición seleccionada: la selección y el panel se mantienen; la tarjeta queda oculta hasta que la rama se expanda.
- La posición seleccionada deja de ser vigente (se suprimió) antes de la siguiente carga: el panel sigue mostrando los datos cargados hasta que se recarga.
- Dos posiciones comparten puesto y unidad: se distinguen por su código.
- Sin sesión iniciada no se accede al organigrama (feature 000-login).

## Requirements *(mandatory)*

### Functional Requirements

**Acceso y contenido**

- **FR-001**: El sistema MUST permitir a cualquier persona autenticada (Administrador o Usuario) ver el organigrama, buscar, filtrar por unidad, expandir, contraer, acercar, alejar, desplazar y consultar el detalle de una posición, y MUST mostrar a todos exactamente el mismo organigrama.
- **FR-002**: El sistema MUST NOT ofrecer en esta pantalla acciones de gestión (crear, editar, suprimir posiciones ni asignar o liberar ocupantes), tampoco al Administrador.
- **FR-003**: El organigrama MUST mostrar solo las posiciones vigentes (Ocupadas y Vacantes) y MUST NOT mostrar nunca las Suprimidas, para ningún usuario, e indicarlo con el texto "Las posiciones suprimidas no se muestran."
- **FR-004**: El organigrama MUST ser de solo lectura y MUST reflejar siempre el estado actual de las posiciones: no mantiene una copia propia de la estructura de reporte, no la guarda por fechas y los cambios que se hagan en otros módulos se ven en la siguiente carga.
- **FR-005**: Los datos del organigrama (posición, supervisor, ocupante, estado, modalidad, puesto y unidad) MUST obtenerse exclusivamente del módulo de posiciones, fuente única de la estructura de reporte; el organigrama no puede modificarlo.
- **FR-006**: El organigrama MUST NOT comprobar ni corregir las reglas de la estructura (una sola máxima autoridad en la unidad raíz, supervisor en la misma unidad o en una superior, sin ciclos, sin supervisoras Suprimidas), que garantiza el módulo de posiciones. Si los datos no formaran un único árbol, MUST mostrar el estado de error.

**Árbol y tarjetas**

- **FR-007**: El sistema MUST dibujar las posiciones vigentes como un árbol de arriba abajo que empieza en la máxima autoridad (la única posición vigente de la unidad raíz) y une cada posición con su supervisor con líneas, sin límite de profundidad.
- **FR-008**: Cada tarjeta MUST mostrar el nombre de la unidad de la posición, su código, el nombre del puesto, el ocupante (nombre del usuario, o "Sin ocupante" en estilo atenuado) y el estado como etiqueta Ocupada o Vacante con los colores del módulo de posiciones.
- **FR-009**: Las posiciones Vacantes MUST distinguirse además por el borde discontinuo y el fondo de su tarjeta, y una leyenda MUST explicar los estilos Ocupada y Vacante.
- **FR-010**: Las tarjetas hermanas (que reportan al mismo supervisor) MUST ordenarse por código ascendente, comparando numéricamente la parte secuencial del código.
- **FR-011**: La pantalla MUST mostrar el título "Organigrama", el subtítulo "Estructura de reporte generada a partir de las posiciones vigentes.", el enlace "Ver listado de posiciones" que lleva al módulo de posiciones, la leyenda y el aviso de que las Suprimidas no se muestran.
- **FR-012**: El resumen, en una región que anuncia sus cambios, MUST decir sin filtros "N posiciones vigentes · N ocupadas · N vacantes" y con búsqueda o filtro "N posiciones coinciden de M vigentes" (también con "0").
- **FR-013**: La pantalla MUST cubrir los estados cargando, vacío ("Aún no hay posiciones vigentes para mostrar el organigrama."), error con opción de reintentar (también si los datos no forman un único árbol) y éxito.

**Detalle**

- **FR-014**: Al hacer clic en una tarjeta, el sistema MUST abrir un panel lateral con código, puesto, etiqueta de estado y modalidad; ocupante (o "Sin ocupante"); supervisor (código, puesto y ocupante, o "Máxima autoridad" si no tiene); "Supervisa a (N)" (posiciones que le reportan directamente, con código, puesto, ocupante y estado, o "Ninguna posición le reporta."); puesto (nombre y descripción) y unidad (nombre, tipo y estado).
- **FR-015**: El supervisor y cada posición de "Supervisa a" MUST ser enlaces que seleccionan esa posición. MUST haber una sola posición seleccionada a la vez, resaltada en el árbol; contraer una rama que la contiene no cierra el panel ni quita la selección. Al seleccionar una posición desde el panel, su rama MUST expandirse y el árbol desplazarse hasta ella.
- **FR-016**: El panel MUST NOT mostrar la auditoría ni el historial de cambios, y MUST poder cerrarse con el botón "Cerrar detalle".

**Recorrido**

- **FR-017**: Por defecto todas las ramas MUST estar expandidas. Cada tarjeta con subordinadas MUST tener un botón que contrae o expande su rama: expandida dice "− Contraer"; contraída indica cuántas posiciones oculta en total (directas e indirectas), por ejemplo "+ 4 posiciones".
- **FR-018**: "Expandir todo" MUST abrir todas las ramas y "Contraer todo" MUST dejar visible solo la máxima autoridad. Con una búsqueda o un filtro activo, las ramas con coincidencias MUST permanecer expandidas, sus botones de contraer MUST estar deshabilitados y "Contraer todo" MUST contraer solo las ramas sin coincidencias.
- **FR-019**: El zoom MUST ofrecer Alejar, Acercar y el porcentaje actual, que al activarlo restablece el 100 %; MUST ir del 50 % al 150 % en pasos de 10 %.
- **FR-020**: El árbol MUST poder desplazarse arrastrando con el puntero y con las barras de desplazamiento; arrastrar sobre una tarjeta o un botón MUST NOT desplazarlo. MUST mostrarse bajo el árbol "Arrastra para desplazarte. Haz clic en una tarjeta para ver su detalle."

**Búsqueda y filtro**

- **FR-021**: La búsqueda MUST encontrar por código, puesto, unidad u ocupante, sin distinguir mayúsculas ni tildes.
- **FR-022**: El filtro de unidad MUST ofrecer "Todas las unidades" y las unidades en orden de árbol con sangría, e incluir las posiciones de la unidad elegida y de todas sus dependientes.
- **FR-023**: Al buscar o filtrar, el árbol MUST NOT recortarse. Cuando hay búsqueda y filtro de unidad a la vez, una posición coincide solo si cumple ambos. Las posiciones que coinciden MUST resaltarse, las que no MUST atenuarse, las ramas que contienen coincidencias MUST expandirse aunque la persona las hubiera contraído y el árbol MUST desplazarse hasta la primera coincidencia de la búsqueda (por código). Si nada coincide, todas las tarjetas MUST mostrarse atenuadas.
- **FR-024**: "Limpiar filtros" MUST aparecer solo cuando hay búsqueda o filtro de unidad y MUST borrar ambos.
- **FR-025**: La búsqueda y el filtro MUST NOT cambiar la selección ni el estado de las ramas que la persona contrajo; al limpiarlos, el árbol MUST volver a como estaba.

**Experiencia y accesibilidad**

- **FR-026**: Cada tarjeta, botón de rama, control de zoom y enlace del panel MUST ser un botón real alcanzable con el teclado. Los botones de rama MUST indicar si están expandidos y su nombre accesible MUST incluir el código de la posición ("Contraer POS-0201", "Expandir POS-0201"). El área del árbol MUST poder desplazarse con el teclado y el panel MUST ser una región con el nombre "Detalle de la posición". En pantallas estrechas el panel MUST mostrarse como una capa superpuesta a todo el ancho, con el foco atrapado, cierre con "Cerrar detalle" o Escape y foco devuelto a la tarjeta que lo abrió.
- **FR-027**: Toda la interfaz MUST estar en español y cumplir WCAG 2.2 AA entre 320 y 1440 px de ancho, sin desplazamiento horizontal de la página; el estado de una posición MUST comunicarse con texto, no solo con color; la tarjeta Vacante MUST reconocerse además por su borde y fondo.
- **FR-028**: Los parámetros de búsqueda y de filtro de unidad MUST poder compartirse en la dirección de la página.

**Relación con otros módulos**

- **FR-029**: El organigrama MUST mostrar los nombres, la descripción, el tipo y el estado de puestos y unidades tal como los entrega el módulo de posiciones, y usar el árbol de unidades solo para el filtro por unidad, sin modificarlos.
- **FR-030**: Los cambios de ocupante y el paso entre Ocupada y Vacante que registre el módulo de gestión de personal MUST reflejarse en el organigrama en la siguiente carga.

**Fuera de alcance**: crear, editar o suprimir posiciones y asignar o liberar ocupantes (módulo de posiciones y de gestión de personal), mostrar posiciones Suprimidas, un organigrama histórico (ver estructura en una fecha pasada), exportar o imprimir el árbol, agrupar visualmente las posiciones por unidad, cargar el árbol por ramas y la búsqueda y el filtro en el servidor.

### Key Entities *(include if feature involves data)*

- **Nodo del organigrama**: una posición vigente tal como la entrega el módulo de posiciones, con su código, estado, modalidad, puesto, unidad, ocupante y su posición supervisora; no se almacena: se construye en cada carga.
- **Árbol de supervisión**: conjunto de posiciones vigentes unidas por la relación "reporta a"; tiene una sola raíz, la máxima autoridad, y no tiene ciclos.
- **Posición** *(externa, módulo de posiciones)*: fuente única de la estructura de reporte.
- **Puesto** y **Unidad organizacional** *(externos)*: aportan el nombre, la descripción, el tipo y el estado que se muestran en las tarjetas y en el panel.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Una persona ve la estructura de reporte completa, desde la máxima autoridad hasta la última posición, en menos de 5 segundos desde que abre la pantalla, con una organización de hasta 2000 posiciones vigentes.
- **SC-002**: Una persona encuentra una posición por código, puesto, unidad u ocupante y llega a su detalle en menos de 20 segundos.
- **SC-003**: El 100 % de las posiciones vigentes aparece exactamente una vez en el árbol y el 0 % de las Suprimidas, para cualquier usuario.
- **SC-004**: El resumen y los datos de las tarjetas coinciden en el 100 % con los del módulo de posiciones en la misma carga.
- **SC-005**: El 100 % de los intentos de modificar posiciones desde esta pantalla es imposible: no existe ninguna acción de gestión.
- **SC-006**: El 100 % de los controles (tarjetas, ramas, zoom, enlaces del panel, desplazamiento) se puede usar solo con el teclado.
- **SC-007**: Con búsqueda o filtro activos, el 100 % de las posiciones sigue visible (resaltadas o atenuadas) y limpiar los filtros devuelve el árbol exactamente al estado previo (ramas y selección).
- **SC-008**: La pantalla no genera desplazamiento horizontal de la página entre 320 y 1440 px, y el estado de cada tarjeta es reconocible sin depender del color.

## Assumptions

- "Administrador de Talento Humano" corresponde al rol Administrador definido en 000-login; el resto de personas autenticadas tiene el rol Usuario. Ambos ven lo mismo.
- **Decisiones por defecto del documento fuente** (la spec las adopta hasta que negocio decida): (1) el filtro de unidad atenúa en lugar de recortar, para conservar la cadena hasta la máxima autoridad; la alternativa es mostrar solo la rama de la unidad elegida. (2) Se cargan todas las posiciones vigentes de una vez, lo que permite buscar, filtrar y contar las posiciones ocultas sin más peticiones; si la organización llega a varios miles de posiciones, la alternativa es cargar por ramas con búsqueda y filtro en el servidor. (3) Solo se muestra el estado actual; un organigrama histórico requeriría versionar las posiciones, que el modelo de datos no tiene. (4) Exportar o imprimir queda fuera de alcance; la alternativa es exportar el árbol visible a PDF o imagen. (5) Se dibuja el árbol de posiciones con la unidad en cada tarjeta; la alternativa es agrupar visualmente las posiciones de una misma unidad.
- Las reglas que dan forma al árbol (una sola máxima autoridad en la unidad raíz, supervisor vigente de la misma unidad o de una superior, sin ciclos) las garantiza el módulo de posiciones (feature 006); esta feature depende de él y de sus datos de ejemplo.
- Esta feature solo consume los datos de posiciones, puestos y unidades que ya expone el módulo de posiciones; no crea datos propios ni registra auditoría, porque no modifica nada.
- El módulo de gestión de personal aún no existe; mientras tanto, el ocupante es el que ya guarda cada posición.
- El tamaño esperado es de cientos a pocos miles de posiciones; el criterio SC-001 se mide con hasta 2000.
- Los textos de la interfaz usan "Posición", "Puesto", "Unidad organizacional", "Supervisor", "Ocupante", "Ocupada" y "Vacante" (en femenino, porque se refieren a la posición).
- La búsqueda, el filtro, el zoom, la expansión de ramas y la selección son preferencias de la sesión de la pantalla: solo la búsqueda y el filtro de unidad pueden compartirse en la dirección de la página.
- El tablero `design/Organigrama.dc.html` ya existe y sirve de referencia visual; si no cubre algo de esta especificación (leyenda, aviso de Suprimidas, resumen con filtros, panel con enlaces), se actualiza `design/` antes de implementar.

## Pantallas *(si la feature tiene UI)*

### Organigrama
- **Propósito**: ver la estructura de reporte como árbol, buscar y filtrar posiciones sin perder el contexto y consultar el detalle de cada una.
- **Datos que muestra**: título, subtítulo y enlace "Ver listado de posiciones"; resumen ("N posiciones vigentes · N ocupadas · N vacantes" o "N posiciones coinciden de M vigentes"); leyenda de estilos de tarjeta y aviso de las Suprimidas; el árbol con una tarjeta por posición (unidad, código, puesto, ocupante, estado); botones de rama; controles de zoom; ayuda de desplazamiento.
- **Acciones disponibles** (y quién puede hacerlas): buscar, filtrar por unidad, limpiar filtros, expandir y contraer ramas, expandir y contraer todo, acercar, alejar, restablecer el zoom, desplazar, seleccionar una posición y abrir el enlace al listado (Administrador y Usuario; las mismas para ambos). No hay acciones de gestión.
- **Estados**: cargando, vacío ("Aún no hay posiciones vigentes para mostrar el organigrama."), error con reintento (también si los datos no forman un único árbol), éxito, sin coincidencias (todo atenuado y resumen con 0 coincidencias) y con búsqueda o filtro activos. Sin estado de permisos: ambos roles ven lo mismo.
- **Mockup**: `design/Organigrama.dc.html` (tablero ya existente); revisar que incluya el panel de detalle con enlaces, la leyenda, el aviso de Suprimidas, el resumen con filtros y los botones de rama con su texto, y los estados de `design/Estados.dc.html`.

### Panel de detalle de la posición
- **Propósito**: consultar el detalle de la posición seleccionada y navegar a su supervisor y a sus subordinadas.
- **Datos que muestra**: código, puesto, etiqueta de estado y modalidad; ocupante; supervisor (enlace) o "Máxima autoridad"; "Supervisa a (N)" con enlaces; puesto (nombre y descripción); unidad (nombre, tipo y estado). Sin auditoría.
- **Acciones disponibles** (y quién puede hacerlas): seleccionar el supervisor o una subordinada y "Cerrar detalle" (Administrador y Usuario).
- **Estados**: cerrado, abierto con la posición seleccionada y sin subordinadas ("Ninguna posición le reporta.").
- **Mockup**: panel lateral de `design/Organigrama.dc.html`, si lo incluye; si no, se define en `design/` antes de implementar.
