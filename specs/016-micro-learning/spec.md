# Feature Specification: Micro-learning

**Feature Branch**: `016-micro-learning`

**Created**: 2026-10-16

**Status**: Draft

**Input**: User description: "Micro-learning (documento fuente `specify-microlearning.md`). Repositorio de cápsulas de contenido breve (videos, PDF, artículos y quizzes) asociadas a competencias, que el colaborador consume a su ritmo y de las que queda registro de su progreso. El administrador (Gestor de Talento) crea, publica, archiva y elimina los contenidos. El módulo no administra el diccionario de competencias ni los datos de los colaboradores: solo los consume a través de los Facades de Estructura Empresarial y Gestión de Personal. Los micro-contenidos refuerzan conocimientos, no desarrollan competencias conductuales."

## Clarifications

### Session 2026-10-16

- Q: Cuando un colaborador abandona un quiz a mitad de camino, ¿ese intento incompleto se guarda y bloquea la edición de preguntas, o solo cuenta el intento terminado? → A: Solo cuenta el intento terminado; los incompletos no se guardan y las preguntas se bloquean únicamente cuando existe algún intento terminado.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Crear y publicar un micro-contenido (Priority: P1)

El administrador pulsa "+ Nuevo Contenido" y completa título, tipo (Video, PDF, Artículo o Quiz), competencias activas asociadas, duración en minutos, URL (salvo Quiz) y miniatura opcional. Puede guardar como Borrador aunque falten datos, excepto título, tipo y duración. Cuando el contenido cumple los requisitos, lo publica y queda disponible para los colaboradores. El sistema le asigna un código secuencial MICRO-0001, MICRO-0002, etc.

**Why this priority**: Sin contenidos publicados no hay nada que consumir; es la base de toda la feature.

**Independent Test**: Como administrador, crear un contenido de tipo Video como Borrador, verificar su código, completar los datos faltantes, publicarlo y comprobar que su estado cambia a Publicado.

**Acceptance Scenarios**:

1. **Given** el administrador en el formulario, **When** guarda con título, tipo y duración mayor que cero, **Then** se crea el contenido en estado Borrador con código MICRO-NNNN único y no editable.
2. **Given** un formulario sin título, sin tipo o con duración cero o vacía, **When** intenta guardar, **Then** el sistema lo impide e indica los campos obligatorios.
3. **Given** un Borrador de tipo Video sin URL o sin competencias, **When** pulsa Publicar, **Then** el sistema no publica y muestra la lista de datos que faltan.
4. **Given** un Borrador completo (título, duración, URL y al menos una competencia activa), **When** pulsa Publicar, **Then** pasa a Publicado y queda registrado el cambio de estado.
5. **Given** el selector de competencias, **When** se despliega, **Then** solo ofrece competencias activas del diccionario maestro.
6. **Given** un contenido ya creado, **When** el administrador lo edita, **Then** el campo tipo y el código aparecen no editables.
7. **Given** cualquier formulario o detalle, **When** se muestra, **Then** incluye la marca "Apto para conocimientos" y la nota de que los micro-contenidos refuerzan conocimientos y no reemplazan el desarrollo de competencias conductuales.

---

### User Story 2 - Construir un quiz (Priority: P1)

Al elegir tipo Quiz, en lugar de la URL el formulario muestra un constructor de preguntas de opción múltiple. El administrador agrega, quita y reordena preguntas (1 a 20) y opciones (2 a 5 por pregunta), y marca exactamente una opción correcta por pregunta. Para publicar se exige al menos una pregunta.

**Why this priority**: El quiz es uno de los cuatro tipos y el único con estructura propia; sin constructor no se puede ofrecer.

**Independent Test**: Crear un Quiz con 3 preguntas, reordenarlas, marcar la correcta de cada una, publicarlo y comprobar que se conservan el orden y las respuestas correctas.

**Acceptance Scenarios**:

1. **Given** el constructor de preguntas, **When** el administrador agrega una pregunta, **Then** puede definir enunciado (obligatorio) y entre 2 y 5 opciones, con exactamente una marcada como correcta.
2. **Given** un quiz con 20 preguntas, **When** intenta agregar otra, **Then** el sistema lo impide indicando el máximo.
3. **Given** una pregunta con 5 opciones o con 2 opciones, **When** intenta agregar una sexta o quitar hasta dejar una, **Then** el sistema lo impide.
4. **Given** una pregunta sin opción correcta marcada o sin enunciado, **When** intenta publicar el quiz, **Then** el sistema lo rechaza señalando la pregunta.
5. **Given** un quiz Borrador sin preguntas, **When** intenta publicar, **Then** el sistema informa que falta al menos una pregunta.
6. **Given** un quiz que ya tiene algún intento terminado de un colaborador, **When** el administrador intenta modificar sus preguntas u opciones, **Then** el sistema lo impide e indica que debe archivar el quiz y crear uno nuevo.

---

### User Story 3 - Consumir un video, PDF o artículo y registrar progreso (Priority: P1)

El colaborador abre un contenido publicado. En un Video ve el marcador de posición del reproductor y, si ya lo había iniciado, retoma desde la última posición guardada. En PDF y Artículo accede al contenido mediante su URL. En todos pulsa "Marcar como completado" para finalizarlo. Los contenidos ya completados muestran su estado y no ofrecen el botón.

**Why this priority**: Es el valor central para el colaborador: aprender a su ritmo con registro de avance.

**Independent Test**: Como colaborador, iniciar un Video, salir a mitad, volver y comprobar que retoma la posición; pulsar "Marcar como completado" y verificar el estado Completado con fecha.

**Acceptance Scenarios**:

1. **Given** un contenido Publicado que el colaborador no ha iniciado, **When** lo abre, **Then** se crea su registro de progreso En progreso con fecha de inicio.
2. **Given** un Video en progreso, **When** el colaborador sale y vuelve, **Then** la reproducción se reanuda desde la última posición guardada.
3. **Given** un Video, PDF o Artículo en progreso, **When** pulsa "Marcar como completado", **Then** el progreso pasa a Completado con fecha de finalización.
4. **Given** un contenido Completado, **When** el colaborador lo abre, **Then** ve su estado Completado y no se ofrece el botón.
5. **Given** un PDF o Artículo, **When** el colaborador lo abre, **Then** puede acceder al contenido mediante su URL.
6. **Given** un contenido Borrador, **When** un colaborador intenta abrirlo, **Then** el sistema deniega el acceso.

---

### User Story 4 - Resolver un quiz (Priority: P1)

El colaborador responde un quiz una pregunta a la vez, con el indicador "Pregunta 2 de 5". Al elegir una opción ve de inmediato si fue correcta o incorrecta y cuál era la correcta, y pasa a la siguiente. Al terminar ve su puntaje final (por ejemplo "4 de 5") y puede repetir el quiz. No hay puntaje mínimo: el quiz se completa al responder todas las preguntas de un intento.

**Why this priority**: Es la forma de consumo del tipo Quiz y refuerza el aprendizaje con retroalimentación inmediata.

**Independent Test**: Resolver un quiz de 5 preguntas, verificar retroalimentación inmediata por pregunta, puntaje final y estado Completado; repetirlo con puntaje menor y comprobar que sigue Completado.

**Acceptance Scenarios**:

1. **Given** un quiz Publicado, **When** el colaborador lo inicia, **Then** ve la primera pregunta con el indicador "Pregunta 1 de N".
2. **Given** una pregunta mostrada, **When** elige una opción, **Then** se indica de inmediato si fue correcta o incorrecta y cuál era la correcta, y avanza a la siguiente.
3. **Given** el colaborador responde la última pregunta, **When** termina el intento, **Then** se muestra el puntaje final (correctas sobre total), se guarda el intento con su puntaje y el progreso pasa a Completado.
4. **Given** un quiz Completado, **When** repite el quiz y obtiene un puntaje menor, **Then** se registra un nuevo intento y el estado sigue Completado.
5. **Given** un intento a medias, **When** el colaborador abandona antes de responder todo, **Then** el intento no cuenta como terminado y el contenido no pasa a Completado por él.

---

### User Story 5 - Ver mi aprendizaje y descargar certificados (Priority: P2)

El colaborador abre su vista de aprendizaje con tarjetas de resumen (completados, en progreso, horas acumuladas y certificados) y las pestañas "Asignado" y "Completado". "Asignado" muestra los contenidos publicados vinculados a una competencia en la que tiene una brecha activa (según DNF) y que aún no completó, más los que ya inició y no completó. En "Completado" cada tarjeta incluye el botón de descarga del certificado en PDF, con su nombre, título, competencias, duración y fecha de finalización.

**Why this priority**: Da visibilidad y reconocimiento del avance; depende de que ya exista consumo (historias 3 y 4).

**Independent Test**: Con un colaborador con una brecha activa, comprobar que aparecen en "Asignado" los contenidos vinculados; completar uno, verificar que pasa a "Completado", que las horas suman su duración y que el certificado se descarga con los datos correctos.

**Acceptance Scenarios**:

1. **Given** un colaborador con brecha activa en una competencia vinculada a contenidos publicados que no completó, **When** abre la pestaña Asignado, **Then** los ve listados.
2. **Given** un contenido que el colaborador inició y no completó, **When** abre Asignado, **Then** aparece aunque no esté vinculado a una brecha.
3. **Given** un contenido completado, **When** abre la pestaña Completado, **Then** aparece con botón de descarga de certificado y deja de estar en Asignado.
4. **Given** contenidos completados de 20 y 40 minutos, **When** se muestran las tarjetas de resumen, **Then** las horas acumuladas reflejan 1 hora y la cantidad de completados y certificados es 2.
5. **Given** una pestaña sin contenidos, **When** se muestra, **Then** aparece un mensaje informativo.
6. **Given** un contenido completado, **When** descarga el certificado, **Then** el PDF contiene el nombre del colaborador, título, competencias asociadas, duración y fecha de finalización.
7. **Given** un colaborador, **When** intenta ver o descargar datos de otro colaborador, **Then** el sistema lo impide: solo accede a los propios.
8. **Given** un contenido no asignado, **When** el colaborador lo inicia desde el Catálogo Formativo, **Then** pasa a su lista de En progreso y aparece en Asignado.

---

### User Story 6 - Administrar el repositorio: listar, editar, archivar y eliminar (Priority: P2)

El administrador ve una cuadrícula de tarjetas, con color por tipo (Video rojo, PDF azul, Artículo verde, Quiz púrpura), que muestra miniatura, título, tipo, competencia (la primera y "+N" si hay varias), duración, estado y número de completaciones. Busca por título y filtra por tipo, estado y competencia; por defecto ve Borrador y Publicado. Puede editar, archivar (con confirmación) y reactivar un archivado; elimina solo si ningún colaborador lo ha iniciado.

**Why this priority**: Completa el ciclo de vida y la gestión continua, pero el MVP puede operar con las historias 1 a 4.

**Independent Test**: Crear varios contenidos de distinto tipo y estado, filtrarlos, archivar uno publicado, reactivarlo y eliminar un Borrador sin progreso; verificar que uno con progreso no ofrece eliminar.

**Acceptance Scenarios**:

1. **Given** contenidos en todos los estados, **When** el administrador abre el repositorio, **Then** ve por defecto solo Borrador y Publicado, y puede incluir Archivados con el filtro de estado.
2. **Given** un contenido con 3 competencias, **When** se muestra su tarjeta, **Then** presenta la primera competencia y "+2".
3. **Given** un contenido sin miniatura, **When** se muestra, **Then** la interfaz usa una imagen genérica según el tipo.
4. **Given** un contenido Publicado, **When** pulsa Archivar, **Then** el sistema pide confirmación e informa que dejará de estar disponible para los colaboradores; al confirmar pasa a Archivado.
5. **Given** un contenido Archivado, **When** pulsa Reactivar, **Then** vuelve a Publicado (es la transición Archivado a Publicado).
6. **Given** un contenido sin registros de progreso, **When** el administrador pulsa Eliminar y confirma, **Then** se elimina junto con sus vínculos, preguntas y opciones.
7. **Given** un contenido con al menos un colaborador que lo inició, **When** se muestra su detalle o tarjeta, **Then** no se ofrece Eliminar y se indica que solo puede archivarse.
8. **Given** un contenido Publicado, **When** el administrador edita título, duración, URL, miniatura o competencias, **Then** los cambios se guardan sin cambiar su estado; el tipo no es editable.
9. **Given** un colaborador, **When** consulta el módulo, **Then** no ve ningún botón de crear, editar, publicar, archivar ni eliminar, ni el número de completaciones.
10. **Given** una transición no permitida (por ejemplo Borrador a Archivado), **When** se intenta, **Then** el sistema la rechaza.

---

### User Story 7 - Vista previa del administrador (Priority: P3)

Desde cada tarjeta y desde el formulario, el administrador abre una vista previa que muestra el contenido tal como lo verá el colaborador. La vista previa no registra progreso ni intentos.

**Why this priority**: Facilita la revisión antes de publicar, sin ser imprescindible para operar.

**Independent Test**: Abrir la vista previa de un quiz, responder todas las preguntas y comprobar que no se crean intentos ni progreso y que el número de completaciones no cambia.

**Acceptance Scenarios**:

1. **Given** un contenido en cualquier estado, **When** el administrador abre la vista previa, **Then** ve el contenido con la presentación del colaborador.
2. **Given** la vista previa de un quiz o video, **When** interactúa o "completa" el contenido, **Then** no se registra progreso ni intentos ni cambia el recuento de completaciones.
3. **Given** un colaborador, **When** intenta acceder a la vista previa, **Then** no está disponible.

---

### User Story 8 - Auditoría e historial (Priority: P3)

El sistema registra quién creó cada contenido y cuándo, quién lo modificó por última vez y cuándo, y cada cambio de estado en un historial con usuario, fecha, estado anterior y estado nuevo. El progreso y los intentos del colaborador conservan sus fechas. Los cambios de competencias asociadas se anotan en el historial del Catálogo Formativo.

**Why this priority**: Trazabilidad requerida, pero no bloquea el uso diario.

**Independent Test**: Publicar, archivar y reactivar un contenido y comprobar tres entradas de historial con usuario, fecha y estados; cambiar sus competencias y verificar la entrada en el historial del Catálogo Formativo.

**Acceptance Scenarios**:

1. **Given** un contenido creado, **When** el administrador consulta su detalle, **Then** ve creador, fecha de creación, último modificador y fecha de última modificación.
2. **Given** un cambio de estado, **When** ocurre, **Then** se añade una entrada con usuario, fecha, estado anterior y estado nuevo.
3. **Given** un cambio en las competencias asociadas, **When** se guarda, **Then** queda en el historial del Catálogo Formativo.

---

### Edge Cases

- Un contenido Archivado que el colaborador ya completó sigue visible en su historial y su certificado sigue descargable; el que tenía en progreso deja de estar disponible, conserva su avance y lo retoma si se vuelve a publicar.
- Un colaborador no puede abrir un contenido Archivado que no completó, ni un Borrador.
- Una competencia asociada que luego se desactiva: la referencia se conserva y sigue consultable (en tarjetas, certificados y detalle), pero no se ofrece para nuevas asociaciones.
- Un contenido Publicado cuya única competencia se desactiva sigue publicado; el Catálogo Formativo aplica sus propias reglas de cobertura.
- Un contenido Publicado vinculado a una brecha activa que el colaborador ya completó no aparece en Asignado.
- Si se pierde la conexión con el servicio de competencias o de colaboradores, la pantalla muestra un error con opción de reintentar sin romper el resto.
- Un intento de quiz abandonado no cuenta como terminado; solo un intento terminado bloquea las preguntas del quiz. Abrir el quiz sin terminarlo crea el progreso En progreso (que impide eliminarlo) pero no bloquea la edición de preguntas.
- Dos administradores publicando o creando a la vez no deben generar códigos duplicados.
- URL con formato inválido en Video, PDF o Artículo debe rechazarse; la miniatura vacía es válida.
- Un colaborador sin brechas activas ni contenidos iniciados ve un mensaje informativo en "Asignado".
- Un Video sin posición guardada empieza desde el inicio.
- Se intenta cambiar el tipo de un contenido ya creado: se rechaza.
- Eliminar un contenido con quiz que nadie inició borra también sus preguntas y opciones; con un solo registro de progreso no se permite.

## Requirements *(mandatory)*

### Functional Requirements

**Roles y acceso**

- **FR-001**: El sistema MUST aplicar permisos por rol: el administrador (Gestor de Talento) lista todos los estados, crea, edita, publica, archiva, elimina, usa la vista previa y ve el número de completaciones; el colaborador solo consulta contenidos publicados (o archivados que ya completó), consume, registra su progreso, ve su resumen y descarga sus certificados.
- **FR-002**: El sistema MUST impedir que un colaborador acceda a datos de progreso, resumen o certificados de otro colaborador.
- **FR-003**: El sistema MUST ocultar al colaborador toda acción administrativa y el número de completaciones.

**Micro-contenido**

- **FR-004**: El sistema MUST generar para cada micro-contenido un código único, asignado en orden de creación y nunca reutilizado, con formato MICRO-0001, no editable.
- **FR-005**: El sistema MUST exigir siempre título, tipo (Video, PDF, Artículo o Quiz) y duración en minutos mayor que cero, incluso para guardar como Borrador.
- **FR-006**: El sistema MUST impedir cambiar el tipo después de creado el contenido.
- **FR-007**: El sistema MUST exigir URL válida para Video, PDF y Artículo, y no aplicarla a Quiz.
- **FR-008**: El sistema MUST aceptar una miniatura opcional por URL y mostrar una imagen genérica según el tipo cuando no exista.
- **FR-009**: El sistema MUST permitir asociar una o más competencias, solo activas del diccionario maestro de Estructura Empresarial, obtenidas únicamente a través de su Facade.
- **FR-010**: El sistema MUST conservar las referencias existentes a competencias que luego se desactivan y mantenerlas consultables, sin ofrecerlas para nuevas asociaciones.
- **FR-011**: El vínculo entre competencias y micro-contenidos MUST ser el mismo dato que se edita desde el Catálogo Formativo (Feature 005), sin duplicarse.

**Quiz**

- **FR-012**: El sistema MUST permitir en un quiz entre 1 y 20 preguntas de opción múltiple, cada una con enunciado obligatorio y entre 2 y 5 opciones, de las cuales exactamente una es la correcta.
- **FR-013**: El sistema MUST permitir al administrador definir y reordenar el orden de preguntas y de opciones, y agregarlas o quitarlas.
- **FR-014**: El sistema MUST exigir al menos una pregunta para publicar un quiz.
- **FR-015**: El sistema MUST impedir modificar las preguntas y opciones de un quiz que ya tenga algún intento terminado de un colaborador (los intentos incompletos no se guardan ni bloquean), indicando que debe archivarse y crear uno nuevo.
- **FR-016**: El sistema MUST completar el quiz al terminar de responder todas sus preguntas de un intento, sin puntaje mínimo de aprobación, y permitir repetirlo.
- **FR-017**: El sistema MUST guardar en cada intento terminado su puntaje (respuestas correctas sobre el total) y su fecha, y mostrar la retroalimentación inmediata de cada respuesta (correcta/incorrecta y cuál era la correcta).
- **FR-018**: El sistema MUST mantener el estado Completado aunque se repita el quiz con puntaje menor.

**Ciclo de vida**

- **FR-019**: El sistema MUST crear todo micro-contenido nuevo en estado Borrador.
- **FR-020**: El sistema MUST permitir solo las transiciones Borrador a Publicado, Publicado a Archivado y Archivado a Publicado.
- **FR-021**: El sistema MUST exigir para publicar: título, duración, URL (si no es Quiz), al menos una pregunta (si es Quiz) y al menos una competencia activa asociada; si falta algo, MUST mostrar los datos que faltan.
- **FR-022**: El sistema MUST permitir editar un contenido Publicado (título, duración, URL, miniatura, competencias y, si no tiene intentos, preguntas), excepto su tipo.
- **FR-023**: El sistema MUST dejar de ofrecer un contenido Archivado a los colaboradores, salvo en el historial de quienes ya lo completaron, que además pueden descargar su certificado.
- **FR-024**: El sistema MUST conservar el avance de quien tenía un contenido en progreso al archivarse y permitirle retomarlo si se vuelve a publicar.
- **FR-025**: El sistema MUST permitir eliminar un contenido solo si ningún colaborador lo ha iniciado, eliminando también sus vínculos, preguntas y opciones; si existen registros de progreso, MUST impedirlo y permitir solo archivar.
- **FR-026**: El sistema MUST pedir confirmación al archivar (informando que el contenido dejará de estar disponible) y al eliminar.

**Progreso y asignación**

- **FR-027**: El sistema MUST mantener un registro de progreso por contenido y colaborador con estado En progreso o Completado, fecha de inicio y fecha de finalización.
- **FR-028**: El sistema MUST guardar la última posición de reproducción de un Video y reanudar desde ella.
- **FR-029**: El sistema MUST completar Video, PDF y Artículo cuando el colaborador pulsa "Marcar como completado", y no ofrecer el botón en contenidos ya completados.
- **FR-030**: El sistema MUST considerar un contenido Asignado cuando está Publicado y (a) está vinculado a una competencia con brecha activa del colaborador, según DNF (Feature 006), y aún no lo completó, o (b) el colaborador lo inició y aún no lo completó.
- **FR-031**: El sistema MUST permitir iniciar desde el Catálogo Formativo (Feature 005) un contenido Publicado no asignado, y que el Catálogo muestre solo los contenidos Publicados.
- **FR-032**: El sistema MUST calcular las horas acumuladas como la suma de las duraciones de los contenidos completados, expresada en horas.
- **FR-033**: El sistema MUST ofrecer para cada contenido completado un certificado de finalización descargable en PDF con nombre del colaborador, título, competencias asociadas, duración y fecha de finalización; el nombre se obtiene de Gestión de Personal a través de su Facade.

**Visualización**

- **FR-034**: El repositorio del administrador MUST mostrarse como cuadrícula de tarjetas coloreadas por tipo (Video rojo, PDF azul, Artículo verde, Quiz púrpura) con miniatura, título, tipo, competencia (primera y "+N"), duración, estado y número de completaciones.
- **FR-035**: El repositorio MUST permitir búsqueda por título y filtros por tipo, estado y competencia, mostrando por defecto Borrador y Publicado.
- **FR-036**: Los botones de crear, editar, publicar, archivar y eliminar MUST ser visibles solo para el administrador y solo cuando el estado del contenido lo permite.
- **FR-037**: Cada tarjeta y detalle MUST mostrar la marca "Apto para conocimientos", y el formulario y el detalle del colaborador MUST incluir la nota sobre el alcance en conocimientos.
- **FR-038**: El sistema MUST ofrecer una vista previa para el administrador que presente el contenido como lo ve el colaborador sin registrar progreso ni intentos.
- **FR-039**: La vista del colaborador MUST mostrar tarjetas de resumen (completados, en progreso, horas acumuladas, certificados) y pestañas "Asignado" y "Completado", con mensaje informativo cuando una pestaña esté vacía.
- **FR-040**: Todas las vistas MUST contemplar los estados de carga, vacío y error con opción de reintentar.

**Auditoría**

- **FR-041**: El sistema MUST registrar quién creó cada contenido y cuándo, y quién lo modificó por última vez y cuándo.
- **FR-042**: El sistema MUST registrar cada cambio de estado en un historial con usuario, fecha, estado anterior y estado nuevo.
- **FR-043**: Los cambios de competencias asociadas MUST quedar en el historial del Catálogo Formativo (Feature 005).
- **FR-044**: El sistema MUST registrar el progreso y los intentos de cada colaborador con sus fechas, y exponer completaciones, horas acumuladas y certificados para "Mi progreso" y los indicadores de el módulo de Reportes (aún por definir).

### Key Entities

- **Micro-contenido**: cápsula de contenido con código MICRO-NNNN, título, tipo, duración, URL (según tipo), miniatura, estado y datos de auditoría; se asocia a una o más competencias.
- **Vínculo competencia–micro-contenido**: relación compartida con el Catálogo Formativo.
- **Pregunta de quiz**: enunciado y orden, perteneciente a un micro-contenido de tipo Quiz.
- **Opción de pregunta**: texto, orden y marca de correcta; una correcta por pregunta.
- **Progreso del colaborador**: por contenido y colaborador, con estado, fechas de inicio y finalización y, en Video, última posición.
- **Intento de quiz**: respuestas, puntaje (correctas sobre total) y fecha de un intento terminado de un colaborador.
- **Historial de estado**: usuario, fecha, estado anterior y estado nuevo de cada cambio de ciclo de vida.
- **Certificado de finalización**: documento PDF derivado de un progreso Completado.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un administrador crea y publica un micro-contenido de tipo Video en menos de 3 minutos.
- **SC-002**: Un administrador arma y publica un quiz de 5 preguntas en menos de 8 minutos.
- **SC-003**: El 100 % de los intentos de publicar con datos faltantes se rechaza indicando qué falta.
- **SC-004**: El 100 % de los intentos de eliminar un contenido con progreso, de modificar preguntas con intentos y de transiciones de estado no permitidas se impide.
- **SC-005**: Un colaborador completa un Video, PDF o Artículo en no más de 2 acciones desde que lo abre (abrir y marcar como completado).
- **SC-006**: Un colaborador resuelve un quiz de 5 preguntas y conoce su puntaje final en menos de 3 minutos, con retroalimentación inmediata en cada pregunta.
- **SC-007**: La reanudación de un Video recupera la última posición guardada en el 100 % de los casos.
- **SC-008**: El resumen del colaborador (completados, en progreso, horas, certificados) coincide en el 100 % de los casos con sus registros de progreso.
- **SC-009**: El certificado se descarga en menos de 5 segundos y contiene los cinco datos exigidos.
- **SC-010**: Los listados del administrador y del colaborador cargan en menos de 2 segundos con 500 micro-contenidos.
- **SC-011**: Ningún colaborador accede a datos de progreso o certificados de otro colaborador, ni a contenidos en Borrador.
- **SC-012**: El 100 % de los cambios de estado queda en el historial con usuario, fecha y estados.

## Assumptions

- Existen los módulos Estructura Empresarial (diccionario maestro de competencias), Gestión de Personal (datos del colaborador), Catálogo Formativo (feature 005) y DNF (feature 006), y se consumen solo vía sus Facades; este módulo no los modifica.
- El Catálogo Formativo ya define una tabla mínima de micro-contenidos y su vínculo con competencias; esta feature la completa y amplía, sin duplicar el dato.
- Cada usuario tiene un solo rol (administrador o colaborador), como en los módulos previos.
- La reproducción de Video es un marcador de posición: no se hospeda ni transmite el video; el sistema guarda la posición reportada y enlaza la URL.
- El contenido de PDF y Artículo se abre por su URL externa; el sistema no valida que el recurso remoto exista.
- Los micro-contenidos no se vinculan a competencias conductuales de forma especial: todos se marcan como aptos para conocimientos.
- Se registra un intento de quiz solo cuando se responden todas sus preguntas; los intentos incompletos no se guardan.
- Los textos de la interfaz están en español, igual que el resto de la aplicación.
- El código MICRO-NNNN sigue el orden de creación y nunca se reutiliza: si se elimina un contenido, su número no se vuelve a asignar y puede quedar un hueco en la secuencia.
- El certificado muestra las competencias vinculadas al contenido en el momento de descargarlo, con su nombre vigente (incluidas las competencias que luego se desactivaron).
- La integración con Reportes (indicadores y "Mi progreso") es de lectura de datos; sus pantallas están fuera del alcance de esta feature.
- Fuera de alcance: puntaje mínimo de aprobación en quizzes, tipos de pregunta distintos de opción múltiple, hospedaje de archivos, notificaciones, y edición de brechas o del diccionario de competencias.
- La definición de "Asignado", el certificado por contenido completado y la ausencia de puntaje mínimo son decisiones del sistema; si el equipo prefiere otra regla debe acordarse explícitamente.

## Pantallas *(si la feature tiene UI)*

### Repositorio de micro-contenidos (administrador)
- **Propósito**: gestionar el repositorio de cápsulas de aprendizaje.
- **Datos que muestra**: cuadrícula de tarjetas con miniatura, título, tipo (color), competencia (+N), duración, estado y completaciones; marca "Apto para conocimientos".
- **Acciones disponibles** (y quién puede hacerlas): administrador — Nuevo contenido, editar, vista previa, publicar, archivar, reactivar, eliminar (si no tiene progreso); búsqueda por título y filtros por tipo, estado y competencia. Colaborador — sin acceso.
- **Estados**: vacío ("Aún no hay micro-contenidos"), cargando (Skeleton), error con "Reintentar", sin permisos.
- **Mockup**: patrón Listado adaptado a cuadrícula (`design/MicroAdmin.dc.html`); tarjetas coloreadas por tipo.

### Formulario de micro-contenido (administrador)
- **Propósito**: crear y editar un contenido, incluido el constructor de quiz.
- **Datos que muestra**: título, tipo (no editable al editar), competencias activas (selección múltiple), duración, URL o constructor de preguntas, miniatura, nota de alcance en conocimientos, datos faltantes para publicar.
- **Acciones disponibles** (y quién puede hacerlas): administrador — Guardar borrador, Publicar, agregar/quitar/reordenar preguntas y opciones, marcar correcta, vista previa.
- **Estados**: cargando, error de validación por campo, error de guardado, preguntas bloqueadas por intentos existentes.
- **Mockup**: patrón Formulario en modal o página (`design/MicroAdmin.dc.html`); constructor de preguntas propio.

### Mi aprendizaje (colaborador)
- **Propósito**: ver contenidos asignados y completados, y el avance personal.
- **Datos que muestra**: tarjetas de resumen (completados, en progreso, horas, certificados); pestañas Asignado y Completado con tarjetas (miniatura, título, tipo, competencia, duración, estado).
- **Acciones disponibles** (y quién puede hacerlas): colaborador — abrir un contenido, descargar certificado en Completado. Administrador — sin acceso.
- **Estados**: vacío por pestaña con mensaje informativo, cargando, error con "Reintentar".
- **Mockup**: `design/MicroColaborador.dc.html`.

### Reproducción de contenido (colaborador y vista previa)
- **Propósito**: consumir un Video, PDF o Artículo.
- **Datos que muestra**: marcador del reproductor con posición, o acceso por URL; estado; nota de alcance en conocimientos.
- **Acciones disponibles** (y quién puede hacerlas): colaborador — "Marcar como completado" (oculto si ya completado). Administrador — solo vista previa, sin registrar progreso.
- **Estados**: cargando, error, contenido no disponible (archivado o borrador).
- **Mockup**: `design/MicroReproduccion.dc.html`.

### Quiz (colaborador y vista previa)
- **Propósito**: resolver un quiz una pregunta a la vez.
- **Datos que muestra**: "Pregunta N de M", enunciado, opciones, retroalimentación inmediata, puntaje final.
- **Acciones disponibles** (y quién puede hacerlas): colaborador — elegir opción, repetir el quiz. Administrador — vista previa sin registro.
- **Estados**: cargando, error, resultado final.
- **Mockup**: `design/MicroQuiz.dc.html`.
