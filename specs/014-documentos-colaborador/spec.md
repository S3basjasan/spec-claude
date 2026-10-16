# Feature Specification: Gestión de documentos del colaborador

**Feature Branch**: `014-documentos-colaborador`

**Created**: 2026-10-16

**Status**: Draft

**Input**: User description: "Gestión de documentos del colaborador. Conservar documentos PDF asociados a una ficha, con clasificación, visibilidad y acceso controlado: carga, consulta, descarga y archivo. Título obligatorio de hasta 200 caracteres, categoría activa del catálogo al confirmar, archivo PDF válido por contenido, no vacío y de hasta 10 000 000 bytes, visibilidad para el colaborador desmarcada por defecto, estado Disponible o Archivado. El Administrador de Talento Humano carga, cambia la visibilidad, archiva y consulta archivados; el Colaborador solo accede a sus documentos visibles y disponibles con permiso documental. No hay edición del contenido, versionado, restauración, eliminación definitiva, firma electrónica, OCR ni IA. Los archivos son privados y su entrega se autoriza en cada apertura y descarga."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Cargar un documento PDF a una ficha (Priority: P1)

El Administrador de Talento Humano abre la sección Documentos de una ficha, selecciona Subir documento, indica el título, elige una categoría activa, adjunta un PDF y decide si el colaborador podrá verlo. El documento queda disponible solo cuando el archivo y su asociación a la ficha se completaron.

**Why this priority**: Sin carga no existe nada que consultar, descargar ni archivar; es el valor mínimo de la funcionalidad.

**Independent Test**: Con una ficha y una categoría activa, subir un PDF válido con título y comprobar que aparece Disponible con título, categoría, tamaño y fecha; intentar con un archivo renombrado a `.pdf`, uno vacío, uno de más de 10 000 000 bytes y sin categoría activa, y comprobar que ninguno queda registrado.

**Acceptance Scenarios**:

1. **Given** una ficha y al menos una categoría activa, **When** el Administrador completa título, categoría y un PDF válido y confirma, **Then** el documento se crea con estado Disponible, visibilidad desmarcada por defecto, y se muestra en la lista con título, categoría, tamaño y fecha. (DOC-CA01)
2. **Given** el formulario de carga, **When** el título está vacío (tras recortar espacios) o supera 200 caracteres, **Then** se muestra el error junto al campo y no se registra nada.
3. **Given** el formulario de carga, **When** el archivo no es un PDF válido por su contenido aunque se llame `.pdf`, **Then** se rechaza explicando el motivo (formato no válido) y no se registra nada. (DOC-CA02)
4. **Given** el formulario de carga, **When** el archivo está vacío o supera 10 000 000 bytes, **Then** se rechaza explicando el motivo (vacío o demasiado grande, con el máximo permitido) y no se registra nada; un archivo de exactamente 10 000 000 bytes se acepta.
5. **Given** que no hay categorías activas, **When** el Administrador abre Subir documento, **Then** se explica que necesita al menos una categoría activa y se ofrece ir al catálogo de categorías documentales; no se puede confirmar la carga.
6. **Given** una carga en curso, **When** la categoría elegida se desactiva antes de confirmar, **Then** la confirmación se rechaza con un mensaje claro, no se cambia a otra categoría y no se registra el documento. (DOC-CA03)
7. **Given** una carga en la que falla el almacenamiento del archivo o su asociación con la ficha, **When** el sistema no puede completar ambas, **Then** no queda ningún documento descargable ni se muestra éxito, los archivos incompletos se limpian y la persona ve que la carga falló.
8. **Given** una carga fallida, **When** el formulario se vuelve a mostrar, **Then** conserva el título y la categoría e indica si hace falta volver a seleccionar el archivo.
9. **Given** una confirmación de carga ya completada, **When** la misma operación se repite (por doble clic o reenvío), **Then** no se crea un segundo documento; una nueva carga explícita con otro envío sí crea otro documento aunque tenga el mismo título.
10. **Given** una persona con rol Colaborador, **When** intenta cargar un documento (por la interfaz o directamente), **Then** se le deniega y no se guarda nada.
11. **Given** una ficha inexistente o de otro tenant, **When** se intenta cargar, **Then** se responde como si no existiera, sin revelar datos ajenos.

---

### User Story 2 - Consultar y descargar documentos como Administrador (Priority: P1)

El Administrador ve la lista de documentos disponibles de una ficha, busca por título, filtra por categoría, abre o descarga cada PDF y consulta la vista de archivados.

**Why this priority**: Es la razón de conservar los documentos; sin consulta autorizada la carga no aporta valor.

**Independent Test**: Con documentos disponibles y archivados de una ficha, comprobar que el listado habitual muestra solo los disponibles con su visibilidad, que la búsqueda y el filtro por categoría funcionan, que se puede descargar un disponible y uno archivado y que un documento de otro tenant no entrega ningún byte.

**Acceptance Scenarios**:

1. **Given** una ficha con documentos, **When** el Administrador abre Documentos, **Then** ve por defecto los disponibles con título, categoría, tamaño, fecha, estado de visibilidad y las acciones permitidas, ordenados del más reciente al más antiguo.
2. **Given** el listado, **When** escribe parte de un título o elige una categoría, **Then** la lista muestra solo las coincidencias (sin distinguir mayúsculas ni tildes) y, si no hay ninguna, un mensaje de sin resultados con la opción de limpiar filtros; no existe filtro por tipo de documento.
3. **Given** un documento con categoría inactiva, **When** se lista o se abre, **Then** la categoría se identifica como inactiva y el acceso permitido no se impide.
4. **Given** un documento disponible, **When** el Administrador lo abre o descarga, **Then** recibe el PDF; si el navegador no admite vista previa se ofrece la descarga autorizada.
5. **Given** el listado, **When** el Administrador abre la vista de archivados, **Then** ve los documentos archivados con quién y cuándo los archivó y puede consultarlos y descargarlos.
6. **Given** una ficha sin documentos, **When** se abre Documentos, **Then** se muestra un estado vacío con la acción Subir documento.
7. **Given** un documento inexistente, de otra ficha o de otro tenant, **When** se pide consultar o descargar, **Then** se responde igual que si no existiera y no se entrega ningún byte.
8. **Given** un fallo al consultar, **When** la lista no puede cargarse, **Then** se muestra un error con Reintentar sin ocultar el resto de la ficha.

---

### User Story 3 - Cambiar la visibilidad y archivar documentos (Priority: P1)

El Administrador corrige la visibilidad de un documento disponible (visible o no para el colaborador) y archiva los documentos que ya no deben consultarse de forma habitual, sin perder el archivo ni sus metadatos.

**Why this priority**: Es la forma de controlar qué ve el colaborador y de retirar documentos sin eliminarlos, ya que no se edita ni se elimina contenido.

**Independent Test**: Con un documento visible, retirar su visibilidad y comprobar que el colaborador deja de verlo; archivarlo y comprobar que sale de la lista habitual y del acceso del colaborador, que el Administrador lo ve en archivados con autor y fecha del archivo, y que no existe restauración ni eliminación.

**Acceptance Scenarios**:

1. **Given** un documento disponible, **When** el Administrador marca o desmarca la visibilidad, **Then** el cambio se guarda, se conservan quién y cuándo hizo la última modificación y el colaborador gana o pierde acceso en la siguiente consulta.
2. **Given** un documento disponible, **When** el Administrador elige Archivar, **Then** se pide confirmación; al confirmar el documento pasa a Archivado, se conserva quién y cuándo lo archivó, sale de la lista habitual y deja de ser accesible para el colaborador aunque su marca visible permanezca. (DOC-CA04)
3. **Given** la confirmación de archivar, **When** el Administrador cancela, **Then** el documento sigue Disponible.
4. **Given** un rechazo o un fallo al archivar o al cambiar la visibilidad, **When** la operación no se completa, **Then** el documento conserva su estado y visibilidad previos y no se muestra éxito.
5. **Given** un documento archivado, **When** se intenta cambiar su visibilidad o archivarlo de nuevo, **Then** no se permite: solo los documentos disponibles admiten esos cambios.
6. **Given** el listado y la vista de archivados, **When** se consultan, **Then** no existe ninguna acción de restaurar, editar título o categoría, reemplazar el archivo ni eliminar.
7. **Given** un documento cuya categoría se desactivó, **When** se cambia su visibilidad o se archiva, **Then** la operación funciona con normalidad: desactivar una categoría no archiva documentos ni retira acceso.
8. **Given** una edición que otra persona modificó antes de guardarse, **When** se confirma, **Then** se rechaza la sobrescritura silenciosa, se indica que la información cambió y se pide actualizar.
9. **Given** una persona con rol Colaborador, **When** intenta cambiar visibilidad o archivar (por la interfaz o directamente), **Then** se le deniega y el documento no cambia.

---

### User Story 4 - Consulta del Colaborador a sus documentos (Priority: P2)

El Colaborador, desde Mi ficha, ve y descarga únicamente sus propios documentos que estén visibles y disponibles, siempre que su cuenta tenga el permiso de consulta documental vigente y una asociación válida con su ficha.

**Why this priority**: Entrega valor al colaborador, pero depende de que ya existan documentos cargados y visibles (US1 y US3).

**Independent Test**: Con dos colaboradores, documentos visibles, no visibles y archivados en cada ficha, comprobar que cada uno solo ve y descarga los suyos visibles y disponibles, que sin permiso documental no accede a ninguno y que el identificador de un documento ajeno no entrega nada.

**Acceptance Scenarios**:

1. **Given** una cuenta con asociación válida a su ficha y permiso documental, **When** abre Mis documentos, **Then** ve solo los documentos de su ficha que estén visibles y Disponibles, con título, categoría, tamaño y fecha. (DOC-CA05)
2. **Given** un documento propio visible y disponible, **When** lo abre o lo descarga, **Then** recibe el PDF; el acceso se vuelve a comprobar en cada apertura y descarga.
3. **Given** un documento propio no visible, **When** el Colaborador intenta abrirlo o descargarlo (aun conociendo su identificador), **Then** no se entrega ningún byte y la respuesta no revela que existe.
4. **Given** un documento propio archivado, **When** el Colaborador intenta abrirlo o descargarlo, **Then** no se entrega ningún byte aunque su marca visible permanezca.
5. **Given** un documento de otra ficha o de otro tenant, **When** el Colaborador pide su identificador, **Then** se responde como si no existiera.
6. **Given** una cuenta sin el permiso documental (aunque tenga el permiso de consulta de ficha o de exportación), **When** abre Mis documentos o descarga, **Then** se le deniega y la interfaz explica que no tiene permiso para esa información.
7. **Given** una cuenta sin ficha asociada o con una asociación inválida, **When** abre Mis documentos, **Then** se informa de que no tiene una ficha asociada y no se muestra ningún documento.
8. **Given** que el permiso documental se retira o la visibilidad se quita después de abrir la lista, **When** el Colaborador intenta una nueva apertura o descarga, **Then** se rechaza, sin recuperar copias ya descargadas.
9. **Given** que el Colaborador usa la interfaz, **When** consulta sus documentos, **Then** no ve ninguna acción de cargar, cambiar visibilidad, archivar ni ver archivados.

---

### Edge Cases

- Archivo renombrado a `.pdf` con contenido distinto (imagen, texto, ejecutable): se rechaza por contenido.
- Archivo PDF cifrado, dañado o truncado: se rechaza como no válido si no puede reconocerse como PDF.
- Archivo de exactamente 0 bytes, de 10 000 000 bytes (válido) y de 10 000 001 bytes (rechazado).
- Nombre de archivo con caracteres especiales, muy largo o con rutas: no condiciona el almacenamiento ni la descarga; la descarga ofrece un nombre derivado del título.
- Dos documentos con el mismo título en la misma ficha: se aceptan, cada uno con su identidad.
- Doble clic o reenvío de la misma confirmación de carga: un solo documento.
- Fallo del almacenamiento a mitad de la carga o de la asociación con la ficha: no queda documento disponible y los restos se limpian.
- Categoría desactivada mientras el archivo se transfiere: la confirmación se rechaza sin cambiarla.
- Categoría renombrada o desactivada después de la carga: el documento conserva su categoría y muestra el nombre vigente.
- Retirar la visibilidad o archivar mientras el colaborador tiene la lista abierta: su siguiente apertura o descarga se rechaza.
- Descarga solicitada con un identificador ajeno, inexistente o de otro tenant: misma respuesta y ningún byte.
- Ficha inexistente o de otro tenant al listar, cargar o descargar: se responde como si no existiera.
- Almacenamiento no disponible al descargar: se informa del fallo sin entregar contenido parcial presentado como correcto.
- Cuenta con permiso documental pero sin asociación válida a la ficha: no accede.

## Requirements *(mandatory)*

### Functional Requirements

**Carga y datos del documento**

- **FR-001**: El sistema MUST permitir al Administrador cargar un documento PDF en una ficha de su tenant indicando título, categoría, archivo y visibilidad.
- **FR-002**: El título MUST ser obligatorio (tras recortar espacios) y de hasta 200 caracteres; se admiten títulos repetidos y cada documento conserva identidad propia.
- **FR-003**: La categoría MUST ser obligatoria y MUST estar activa en el catálogo de categorías documentales al confirmar la carga; nunca se cambia en silencio a otra.
- **FR-004**: El archivo MUST ser un PDF válido, no vacío y de hasta 10 000 000 bytes; se valida por su contenido y formato además de su extensión, y un archivo renombrado a `.pdf` que no sea válido se rechaza.
- **FR-005**: La visibilidad para el colaborador MUST ser un valor booleano, desmarcado por defecto.
- **FR-006**: El sistema MUST registrar automáticamente formato, tamaño, autor y momento de carga.
- **FR-007**: El estado del documento MUST ser Disponible o Archivado; MUST nacer Disponible solo al completarse el almacenamiento del archivo y su asociación con la ficha.
- **FR-008**: Si falla cualquier parte de la carga, el sistema MUST NOT dejar un documento descargable presentado como correcto y MUST limpiar los archivos incompletos sin publicarlos.
- **FR-009**: La interfaz MUST explicar los motivos de rechazo del archivo (formato no válido, vacío, demasiado grande) y MUST conservar título y categoría tras un fallo, indicando si hace falta volver a seleccionar el archivo.
- **FR-010**: La repetición de una confirmación de carga ya completada MUST NOT crear un documento duplicado; una nueva carga explícita MUST poder aportar otro documento.
- **FR-011**: Sin categorías activas, la interfaz MUST explicar el requisito y ofrecer al Administrador ir al catálogo de categorías documentales, sin permitir confirmar la carga.

**Conservación, visibilidad y archivo**

- **FR-012**: Los archivos MUST conservarse de forma privada y MUST NOT publicarse mediante direcciones permanentes sin autorización; un identificador de documento MUST NOT constituir un permiso.
- **FR-013**: El Administrador MUST poder cambiar la visibilidad de un documento Disponible; retirarla impide nuevas consultas y descargas del colaborador sin recuperar copias ya descargadas.
- **FR-014**: El Administrador MUST poder archivar un documento Disponible tras confirmación; archivar conserva el archivo y los metadatos, lo excluye de la consulta habitual y del acceso del colaborador aunque su marca visible permanezca, y registra quién y cuándo lo archivó.
- **FR-015**: El Administrador MUST disponer de una vista de archivados donde puede consultarlos y descargarlos.
- **FR-016**: El sistema MUST NOT ofrecer restauración, eliminación definitiva, edición de título o categoría, reemplazo del archivo ni versionado; para aportar contenido corregido se carga otro documento y, cuando corresponda, se archiva el anterior.
- **FR-017**: Desactivar una categoría MUST NOT archivar sus documentos ni retirar acceso; las restricciones documentales se evalúan por separado.
- **FR-018**: Un rechazo o fallo al archivar o cambiar la visibilidad MUST conservar el estado y la visibilidad previos.
- **FR-019**: Ante dos operaciones concurrentes sobre un mismo documento, el sistema MUST rechazar la sobrescritura silenciosa, indicar que la información cambió y pedir actualizar.

**Acceso y permisos**

- **FR-020**: El Administrador MUST poder listar, consultar y descargar los documentos Disponibles y Archivados de las fichas de su tenant.
- **FR-021**: El Colaborador MUST poder consultar y descargar un documento solo si se cumplen a la vez: pertenece a su propia ficha con asociación válida, su cuenta tiene el permiso de consulta documental vigente, el documento está visible y su estado es Disponible; el sistema MUST volver a comprobarlo en cada apertura y descarga.
- **FR-022**: El permiso de consulta documental MUST ser independiente del permiso de consulta general de ficha y del de exportación.
- **FR-023**: Solo el Administrador MUST poder cargar, cambiar la visibilidad y archivar; el Colaborador no.
- **FR-024**: Toda operación MUST limitarse al tenant autenticado; los permisos se comprueban en el servidor, un permiso ausente se considera denegado y ocultar controles no sustituye esa comprobación.
- **FR-025**: Consultar o descargar un documento inexistente, de otra ficha, de otro tenant, privado o archivado (para el Colaborador) MUST NOT entregar ningún byte y MUST responder sin revelar si existe; cambiar identificadores en una petición no concede acceso.
- **FR-026**: Solo MUST utilizarse los dos roles definidos (Administrador de Talento Humano y Colaborador).

**Listado y consulta**

- **FR-027**: El listado MUST mostrar título, categoría, tamaño, fecha y acciones permitidas; el Administrador además ve la visibilidad y el acceso a archivados.
- **FR-028**: El listado MUST permitir buscar por título y filtrar por categoría, sin filtro de tipo de documento, y MUST conservar búsqueda, filtro y página en la dirección de la pantalla.
- **FR-029**: Una categoría inactiva MUST identificarse como tal sin impedir el acceso permitido; si el navegador no admite vista previa, MUST ofrecerse la descarga autorizada.
- **FR-030**: El listado y las vistas MUST tener estados de cargando, vacío, sin resultados, error con Reintentar y sin permisos.

**Auditoría y límites**

- **FR-031**: El sistema MUST conservar autor, fecha, formato y tamaño de la carga; quién y cuándo archivó; y los metadatos de última modificación cuando cambie la visibilidad.
- **FR-032**: Un fallo de carga MUST NOT registrarse como documento disponible ni como operación exitosa para el usuario.
- **FR-033**: El sistema MUST NOT agregar versionado del contenido, historial general por campo, tabla de auditoría adicional ni pantalla de consulta de auditoría general.
- **FR-034**: El sistema MUST NOT incluir OCR, firma electrónica, análisis por IA ni edición del contenido, y la exportación individual de ficha MUST NOT incrustar los adjuntos ni agregar su PDF al expediente.

### Key Entities *(include if feature involves data)*

- **Documento del colaborador**: PDF asociado a una ficha. Atributos: título (hasta 200 caracteres), categoría (referencia a una categoría del catálogo), visibilidad para el colaborador, estado (Disponible o Archivado), formato, tamaño en bytes, quién y cuándo lo cargó, quién y cuándo lo modificó por última vez, quién y cuándo lo archivó (cuando aplica) y una marca de versión para detectar modificaciones concurrentes. Pertenece a una sola ficha y a un tenant; no se elimina.
- **Archivo almacenado**: contenido privado del PDF, referenciado por el documento; se entrega solo mediante acceso autorizado y nunca por una dirección permanente.
- **Categoría documental** *(consumida del catálogo)*: clasificación plana del tenant; el documento conserva su referencia aunque se renombre o desactive.
- **Ficha del colaborador** *(consumida)*: propietaria del documento; puede existir sin documentos.
- **Permiso documental de la cuenta** *(consumido de Identidad y acceso)*: habilita al Colaborador a consultar sus documentos visibles; es independiente de los permisos de ficha y exportación.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un Administrador sube un PDF válido a una ficha y lo ve en la lista en menos de 1 minuto desde que abre la sección Documentos.
- **SC-002**: El 100 % de los archivos que no son PDF válidos por contenido, están vacíos o superan 10 000 000 bytes se rechaza con el motivo explicado y ninguno queda registrado.
- **SC-003**: El 100 % de las cargas fallidas deja cero documentos descargables y cero archivos huérfanos publicados.
- **SC-004**: El 100 % de las cargas confirmadas con una categoría desactivada antes de la confirmación se rechaza sin reasignar la categoría.
- **SC-005**: El 100 % de los intentos del Colaborador de abrir o descargar documentos no visibles, archivados, ajenos, de otro tenant, sin permiso documental o sin asociación válida es denegado y entrega cero bytes.
- **SC-006**: Tras retirar la visibilidad o archivar, el 100 % de las siguientes aperturas y descargas del colaborador se rechazan.
- **SC-007**: Desactivar una categoría no cambia el estado, la visibilidad ni el acceso de ninguno de sus documentos.
- **SC-008**: Una confirmación de carga repetida produce exactamente un documento.
- **SC-009**: No existe ninguna vía (interfaz o solicitud directa) para eliminar, restaurar, editar el contenido o crear versiones de un documento.
- **SC-010**: Cuando dos personas modifican un mismo documento a la vez, el 100 % de las segundas escrituras se rechaza con el aviso de información cambiada.
- **SC-011**: El listado de una ficha con hasta 200 documentos responde a búsquedas y filtros de forma percibida como instantánea (menos de 1 segundo).

## Assumptions

- Esta funcionalidad es la gestión documental (GP-05) y consume el catálogo de categorías documentales ya existente; la categoría se elige solo entre las activas para cargas nuevas.
- El permiso de consulta documental ya existe en la asociación cuenta-ficha y es independiente de los permisos de ficha y exportación; esta funcionalidad lo consume sin redefinirlo.
- Los dos roles existentes son los únicos; el Administrador gestiona y el Colaborador solo consulta lo propio.
- El almacenamiento de archivos es privado y propio de la plataforma; su tecnología se define en el plan. Hasta que exista un servicio externo, se usa el almacenamiento privado ya disponible.
- La carga es una operación en dos tiempos (archivo y confirmación) para no mantener bloqueos durante la transferencia; el archivo previo a la confirmación nunca es visible ni descargable para usuarios.
- «Contenido PDF válido» significa que el archivo se reconoce como PDF por su contenido, no solo por extensión o tipo declarado; no se analiza ni se interpreta su contenido más allá de eso.
- La descarga ofrece un nombre de archivo derivado del título del documento.
- El listado se pagina (20 por página); la lista del Administrador va de lo más reciente a lo más antiguo, y la del colaborador igual.
- No se exige antivirus ni escaneo de contenido malicioso en este incremento; queda como mejora posterior.
- La exportación individual de ficha integral es independiente: no incluye adjuntos.
- Mi ficha del Colaborador muestra la pestaña de documentos solo cuando su cuenta tiene el permiso documental; el Administrador ve la pestaña en el detalle de la ficha.

## Pantallas *(si la feature tiene UI)*

### Documentos (sección del detalle de ficha y de Mi ficha)
- **Propósito**: consultar y descargar los documentos de una persona; el Administrador además los carga, cambia su visibilidad y los archiva.
- **Datos que muestra**: lista de documentos con título, categoría (con marca de inactiva cuando corresponde), tamaño, fecha de carga y, para el Administrador, la visibilidad; vista de archivados con quién y cuándo los archivó; búsqueda por título, filtro por categoría y paginación.
- **Acciones disponibles** (y quién puede hacerlas): Subir documento, cambiar visibilidad, Archivar, ver archivados (solo Administrador); abrir y descargar (Administrador sobre todos; Colaborador sobre los propios visibles y disponibles). No hay eliminar, restaurar ni editar.
- **Estados**: cargando, vacío («Aún no hay documentos»), sin resultados con Limpiar filtros, error con Reintentar, éxito tras cada acción, sin permiso documental y sin ficha asociada (para el Colaborador).
- **Mockup**: pestaña «Documentos» del tablero de detalle de ficha (`design/ColaboradorFicha.dc.html`) con el prototipo `design/GP-05_Documentos-del-colaborador.html` como referencia; la lista reutiliza el patrón Listado de `design/DESIGN.md`.

### Subir documento (diálogo)
- **Propósito**: cargar un PDF a la ficha con su clasificación y visibilidad.
- **Datos que muestra**: título con contador `n/200`, categoría (solo activas), selector de archivo con el tamaño máximo y la restricción a PDF, casilla de visibilidad para el colaborador (desmarcada), mensajes de rechazo por motivo.
- **Acciones disponibles** (y quién puede hacerlas): Subir y Cancelar (solo Administrador); Ir a categorías documentales cuando no hay activas.
- **Estados**: validando, subiendo (botón bloqueado), error por campo, archivo rechazado con motivo, categoría desactivada antes de confirmar, fallo con título y categoría conservados.
- **Mockup**: formulario en modal (`design/GP-05_Documentos-del-colaborador.html`, patrón Formulario en modal de `design/DESIGN.md`).

### Confirmación de archivar
- **Propósito**: confirmar el archivo de un documento disponible.
- **Datos que muestra**: título del documento y el aviso de que se conserva el archivo y deja de ser accesible para el colaborador.
- **Acciones disponibles** (y quién puede hacerlas): Archivar y Cancelar (solo Administrador).
- **Estados**: confirmando (bloqueado), información cambiada, error que conserva el estado anterior.
- **Mockup**: ConfirmDialog (`design/Estados.dc.html`).
