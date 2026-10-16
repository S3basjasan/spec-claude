# Feature Specification: Catálogo de categorías documentales

**Feature Branch**: `013-categorias-documentales`

**Created**: 2026-10-16

**Status**: Draft

**Input**: User description: "Catálogo de categorías documentales. Permitir al administrador definir una clasificación documental plana y propia del tenant: crear, consultar, editar, desactivar y reactivar categorías. Cada categoría tiene nombre obligatorio de hasta 100 caracteres, único dentro del tenant sin distinguir mayúsculas ni espacios exteriores, y estado Activa o Inactiva (nace Activa). Solo las activas se ofrecen en cargas nuevas; desactivar no elimina documentos ni asociaciones ni cambia su visibilidad. No existe eliminación, jerarquía de carpetas ni catálogo paralelo de tipos de documento. La carga y visibilidad de cada archivo pertenecen a la gestión de documentos del colaborador. Se conserva quién creó y quién modificó por última vez la categoría y cuándo. Se rechaza la sobrescritura silenciosa de un registro modificado."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Crear y consultar categorías documentales (Priority: P1)

El Administrador de Talento Humano abre «Categorías documentales», ve el listado de las categorías de su organización y crea una nueva con solo un nombre. La categoría nace Activa y aparece de inmediato en el listado.

**Why this priority**: Sin categorías no se puede clasificar ningún documento; es el valor mínimo del catálogo y habilita a la gestión de documentos del colaborador.

**Independent Test**: Con un catálogo vacío, crear «Contratos»; comprobar que aparece Activa en el listado, que repetir «  contratos  » se rechaza junto al campo y que un Colaborador no puede abrir ni usar el catálogo.

**Acceptance Scenarios**:

1. **Given** un tenant sin categorías, **When** el Administrador abre Categorías documentales, **Then** ve un estado vacío con la acción Nueva categoría.
2. **Given** el formulario de nueva categoría, **When** el Administrador escribe un nombre válido y guarda, **Then** la categoría se crea con estado Activa y el listado se actualiza mostrándola.
3. **Given** el formulario, **When** el nombre está vacío o solo tiene espacios, **Then** se muestra «Este campo es obligatorio» junto al campo y no se guarda nada.
4. **Given** el formulario, **When** el nombre supera 100 caracteres (tras recortar espacios exteriores), **Then** se muestra el máximo permitido junto al campo y no se guarda nada; con exactamente 100 se acepta.
5. **Given** una categoría «Contratos», **When** el Administrador intenta crear «contratos» o «  CONTRATOS  », **Then** se rechaza por nombre ya existente, con el error junto al campo, y el catálogo no cambia.
6. **Given** un tenant con categorías activas e inactivas, **When** el Administrador abre el listado, **Then** ve inicialmente solo las activas, con nombre y estado, ordenadas por nombre.
7. **Given** el listado, **When** el Administrador escribe parte de un nombre en la búsqueda o cambia el filtro de estado (Activas, Inactivas, Todas), **Then** el listado muestra solo las coincidencias y, si no hay ninguna, un mensaje de sin resultados con la opción de limpiar filtros.
8. **Given** una persona con rol Colaborador, **When** intenta listar, consultar o crear categorías (por la interfaz o directamente), **Then** se le deniega y no se revela cuáles existen.
9. **Given** dos organizaciones, **When** ambas crean una categoría con el mismo nombre, **Then** cada una conserva la suya: ninguna ve, comparte ni bloquea las de la otra.

---

### User Story 2 - Corregir el nombre de una categoría (Priority: P1)

El Administrador edita el nombre de una categoría, esté Activa o Inactiva. Los documentos ya clasificados siguen relacionados con la misma categoría y muestran el nombre vigente.

**Why this priority**: Los nombres se corrigen con frecuencia (errores, cambios de criterio); hacerlo sin romper las asociaciones es parte del ciclo de vida esencial.

**Independent Test**: Editar «Contratos» a «Contratos laborales» y comprobar que el listado muestra el nuevo nombre, que la unicidad se valida contra las demás categorías y que dos ediciones simultáneas no se pisan en silencio.

**Acceptance Scenarios**:

1. **Given** una categoría, **When** el Administrador abre Editar, cambia el nombre y guarda, **Then** el listado muestra el nombre nuevo y se conserva quién y cuándo la modificó por última vez.
2. **Given** una categoría Inactiva, **When** el Administrador corrige su nombre, **Then** el cambio se guarda y la categoría sigue Inactiva.
3. **Given** una edición, **When** el nombre nuevo coincide (sin distinguir mayúsculas ni espacios exteriores) con otra categoría del tenant, **Then** se rechaza con el error junto al campo y la categoría conserva su nombre previo.
4. **Given** una edición, **When** el nombre solo cambia en mayúsculas o espacios exteriores de la propia categoría, **Then** no se considera duplicado de sí misma y se acepta.
5. **Given** una categoría usada por documentos, **When** se corrige su nombre, **Then** esos documentos siguen asociados a la misma categoría y la presentan con el nombre vigente; no se guarda un historial de nombres.
6. **Given** una edición abierta, **When** otra persona modifica la categoría antes de que se guarde, **Then** se rechaza la sobrescritura, se indica que la información cambió y se ofrece actualizar; lo escrito se conserva en el formulario y la categoría mantiene los datos de la otra persona.
7. **Given** un guardado sin ningún cambio real en el nombre, **When** se confirma, **Then** se acepta sin alterar la fecha ni el autor de la última modificación.
8. **Given** un fallo al guardar o una denegación, **When** el sistema no pudo registrar el cambio, **Then** no muestra éxito, conserva lo escrito y la categoría mantiene sus datos y estado previos.

---

### User Story 3 - Desactivar y reactivar categorías (Priority: P1)

El Administrador desactiva una categoría que ya no debe usarse y puede reactivarla después. Desactivar la retira de las cargas nuevas, pero no toca ningún documento existente.

**Why this priority**: Como no existe eliminación, la desactivación es la única forma de retirar una categoría sin perder documentos ni su clasificación histórica.

**Independent Test**: Desactivar una categoría con documentos y comprobar que deja de ofrecerse en cargas nuevas, que los documentos conservan categoría, visibilidad y acceso, y que al reactivarla vuelve a ofrecerse.

**Acceptance Scenarios**:

1. **Given** una categoría Activa, **When** el Administrador elige Desactivar, **Then** aparece una confirmación que informa que los documentos se conservan; al confirmar, la categoría pasa a Inactiva.
2. **Given** la confirmación de desactivar, **When** el Administrador cancela, **Then** la categoría sigue Activa.
3. **Given** una categoría Inactiva, **When** el Administrador elige Reactivar y confirma, **Then** pasa a Activa y vuelve a estar disponible para cargas nuevas.
4. **Given** una categoría desactivada, **When** se consultan los documentos que ya la tenían, **Then** siguen asociados a ella, con la misma visibilidad y reglas de acceso; no se archiva, oculta ni elimina ninguno.
5. **Given** una categoría reactivada, **When** se consulta la gestión documental, **Then** no se reactivan documentos archivados ni se modifican sus permisos.
6. **Given** una categoría Inactiva, **When** se consulta el catálogo para cargas nuevas, **Then** no figura entre las opciones; la categoría Activa sí.
7. **Given** una carga de documento en curso con una categoría elegida, **When** la categoría se desactiva antes de confirmar el archivo, **Then** la confirmación rechaza esa clasificación con un mensaje claro y no la cambia en silencio a otra categoría.
8. **Given** el listado y el detalle de cada categoría, **When** se consultan, **Then** no existe ninguna acción Eliminar ni forma de eliminar por solicitud directa.
9. **Given** una desactivación o reactivación sobre un registro modificado desde que se abrió el listado, **When** se confirma, **Then** se rechaza sin cambiar el estado y se pide actualizar.
10. **Given** una persona con rol Colaborador, **When** intenta desactivar o reactivar una categoría (por la interfaz o directamente), **Then** se le deniega y el estado no cambia.

---

### User Story 4 - Consumo del catálogo por la gestión documental (Priority: P2)

La gestión de documentos del colaborador consulta el catálogo para ofrecer solo las categorías activas en cada carga nueva, y para mostrar la etiqueta vigente de cualquier categoría (activa o inactiva) en un documento que la persona está autorizada a ver.

**Why this priority**: Conecta el catálogo con su único consumidor; puede probarse con un consumidor simulado antes de que exista la gestión documental completa.

**Independent Test**: Consultar el catálogo como consumidor interno con una categoría activa y una inactiva; comprobar que las cargas nuevas solo reciben la activa, que un documento existente resuelve el nombre de la inactiva y que un Colaborador ve la etiqueta de su documento sin acceso al catálogo administrativo.

**Acceptance Scenarios**:

1. **Given** categorías activas e inactivas, **When** la gestión documental pide las opciones para una carga nueva, **Then** recibe solo las activas del tenant, ordenadas por nombre.
2. **Given** un documento clasificado con una categoría inactiva, **When** una persona autorizada lo consulta, **Then** ve el nombre vigente de la categoría.
3. **Given** un Colaborador con acceso a un documento propio, **When** lo consulta, **Then** ve la etiqueta de su categoría pero no puede listar, crear, editar ni desactivar categorías.
4. **Given** una categoría de otra organización, **When** un consumidor la solicita con un identificador ajeno, **Then** se trata como inexistente.
5. **Given** la gestión documental sin copia propia de las definiciones, **When** se corrige un nombre, **Then** el cambio se refleja en todos los consumidores sin migrar datos.
6. **Given** el catálogo no disponible, **When** un consumidor lo consulta, **Then** recibe un indicador de «no disponible» sin que se pierda ni altere ninguna asociación existente.

---

### Edge Cases

- Nombres con tildes o letras acentuadas («Educación» y «educación» son iguales para la unicidad; «Educacion» sin tilde es un nombre distinto).
- Dos solicitudes simultáneas para crear el mismo nombre: solo una prospera y la otra recibe el error de nombre existente.
- Dos ediciones simultáneas de la misma categoría: la segunda se rechaza por registro modificado.
- Una categoría desactivada mientras alguien tiene abierto el formulario de edición: puede guardarse el nombre (queda Inactiva) si la versión sigue vigente; si cambió, se pide actualizar.
- Eliminar o reasignar una categoría por solicitud directa: no está permitido (no existe esa operación).
- Tenant sin ninguna categoría activa: el catálogo para cargas nuevas queda vacío y la gestión documental no puede ofrecer clasificación hasta que se cree o reactive una.
- Identificador de categoría inexistente o de otro tenant: se responde igual que si no existiera, sin revelar la causa.
- Un Colaborador sin permiso que modifica identificadores en una petición no obtiene acceso a registros ajenos.

## Requirements *(mandatory)*

### Functional Requirements

**Catálogo y ciclo de vida**

- **FR-001**: El sistema MUST mantener un catálogo plano de categorías documentales propio de cada tenant, sin jerarquía de carpetas ni catálogo paralelo de tipos de documento.
- **FR-002**: Cada categoría MUST tener un nombre obligatorio de hasta 100 caracteres (medidos tras recortar espacios exteriores) y un estado Activa o Inactiva.
- **FR-003**: Una categoría nueva MUST nacer Activa.
- **FR-004**: El nombre MUST ser único dentro del tenant, comparado sin distinguir mayúsculas ni espacios exteriores; dos organizaciones pueden tener el mismo nombre sin interferir.
- **FR-005**: El nombre vacío o solo de espacios MUST rechazarse con el error junto al campo.
- **FR-006**: El Administrador MUST poder corregir el nombre de una categoría Activa o Inactiva; la categoría conserva su identidad y todos sus documentos siguen relacionados con ella, mostrando el nombre vigente. El sistema NO MUST conservar un historial de nombres.
- **FR-007**: El Administrador MUST poder desactivar una categoría Activa y reactivar una Inactiva, cada acción tras confirmación.
- **FR-008**: La confirmación de desactivar MUST informar que los documentos se conservan.
- **FR-009**: Desactivar NO MUST eliminar documentos, modificar su visibilidad ni eliminar asociaciones; los documentos conservan las reglas de acceso que ya tenían.
- **FR-010**: Reactivar NO MUST reactivar documentos archivados ni cambiar permisos.
- **FR-011**: El sistema NO MUST ofrecer ni permitir la eliminación de categorías, ni por la interfaz ni por solicitud directa.
- **FR-012**: Una operación que no cambie realmente los datos (mismo nombre, mismo estado) MUST aceptarse sin alterar la fecha ni el autor de la última modificación.

**Permisos y aislamiento**

- **FR-013**: El Administrador de Talento Humano MUST poder listar y consultar el catálogo administrativo (activas e inactivas de su tenant), crear, editar, desactivar y reactivar categorías.
- **FR-014**: El Colaborador NO MUST poder listar, consultar el catálogo administrativo, crear, editar, desactivar ni reactivar categorías; sí puede ver la etiqueta de la categoría de un documento al que tiene acceso, sin que eso le conceda acceso al catálogo.
- **FR-015**: Toda operación MUST limitarse al tenant autenticado; los permisos se comprueban en el servidor, un permiso ausente se considera denegado y ocultar controles no sustituye esa comprobación.
- **FR-016**: Un identificador de categoría inexistente o de otro tenant MUST recibir la misma respuesta, sin revelar si existe; cambiar identificadores en una petición no concede acceso a registros ajenos.
- **FR-017**: Solo MUST utilizarse los dos roles definidos (Administrador de Talento Humano y Colaborador).

**Consumo por otros módulos**

- **FR-018**: El sistema MUST ofrecer a la gestión de documentos del colaborador las categorías activas del tenant, ordenadas por nombre, para cargas nuevas.
- **FR-019**: El sistema MUST permitir resolver el nombre vigente de cualquier categoría (activa o inactiva) del tenant para documentos existentes.
- **FR-020**: Si una categoría se desactiva antes de confirmar una carga, la confirmación del archivo MUST rechazar esa clasificación con un mensaje claro y NO MUST cambiarla en silencio a otra categoría.
- **FR-021**: La categoría clasifica documentos; NO MUST determinar por sí sola su visibilidad ni permisos, que pertenecen a la gestión documental y a Identidad y acceso.
- **FR-022**: Los consumidores MUST consultar el catálogo y NO MUST mantener su propia copia de las definiciones.
- **FR-023**: Si el catálogo no está disponible, el consumidor MUST recibir un indicador de no disponibilidad sin que se pierda ni altere ninguna asociación existente.

**Interfaz del Administrador**

- **FR-024**: El listado MUST mostrar nombre y estado, ordenar por nombre, ofrecer búsqueda parcial por nombre (sin distinguir mayúsculas ni tildes) y filtro de estado, mostrando inicialmente las activas.
- **FR-025**: El listado MUST tener estados de cargando, vacío, sin resultados, error con Reintentar y sin permisos; NO MUST contener ningún botón Eliminar.
- **FR-026**: Los errores de nombre obligatorio, longitud o unicidad MUST aparecer junto al campo; una denegación o un fallo MUST conservar el estado anterior de la categoría y lo escrito en el formulario.
- **FR-027**: Al detectar un registro modificado desde que se abrió su edición (o desde que se cargó el listado, para cambios de estado), el sistema MUST rechazar la sobrescritura silenciosa, indicar que la información cambió y pedir actualizar, sin modificar la categoría.
- **FR-028**: Las acciones MUST evitar envíos duplicados mientras una operación está en curso y no MUST reintentarse automáticamente.

**Auditoría**

- **FR-029**: El sistema MUST conservar quién creó cada categoría y cuándo, y quién la modificó por última vez y cuándo (incluyendo cambios de nombre y de estado).
- **FR-030**: El sistema NO MUST exigir historial de nombres ni historial por campo, ni incorporar una tabla de auditoría adicional ni una pantalla de consulta de historial en este incremento.

### Key Entities *(include if feature involves data)*

- **Categoría documental**: clasificación plana de documentos propia de un tenant. Atributos: nombre (único en el tenant, hasta 100 caracteres), estado (Activa o Inactiva), quién la creó y cuándo, quién la modificó por última vez y cuándo, y una marca de versión para detectar modificaciones concurrentes. Se relaciona con los documentos del colaborador (uno a muchos) sin ser dueña de ellos.
- **Documento del colaborador** *(fuera de alcance, consumidor)*: pertenece a la gestión de documentos del colaborador; conserva la referencia a su categoría aunque esta se desactive o cambie de nombre.
- **Tenant**: organización a la que pertenece el catálogo; delimita unicidad y acceso.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un Administrador crea una categoría nueva y la ve en el listado en menos de 30 segundos desde que abre la pantalla.
- **SC-002**: El 100 % de los intentos de crear o renombrar una categoría con un nombre ya existente en el tenant (sin importar mayúsculas ni espacios exteriores) se rechaza, y el 0 % de los nombres iguales entre organizaciones distintas genera conflicto.
- **SC-003**: Tras desactivar una categoría, el 100 % de los documentos que la tenían conservan categoría, visibilidad y acceso, y la categoría desaparece del 100 % de las cargas nuevas.
- **SC-004**: Tras corregir un nombre, el 100 % de los documentos asociados presentan el nombre nuevo sin intervención ni migración de datos.
- **SC-005**: El 100 % de los intentos de un Colaborador de listar, crear, editar, desactivar o reactivar categorías es denegado, y el 0 % de las operaciones alcanza categorías de otra organización.
- **SC-006**: No existe ninguna vía (interfaz o solicitud directa) que elimine una categoría.
- **SC-007**: Cuando dos personas editan la misma categoría a la vez, el 100 % de las segundas escrituras se rechaza con el aviso de información cambiada y ninguna sobrescribe a la primera en silencio.
- **SC-008**: Al buscar o filtrar, el listado de hasta 200 categorías responde de forma que la persona percibe el resultado al instante (menos de 1 segundo).
- **SC-009**: El 100 % de los errores de validación se muestra junto al campo afectado y conserva lo escrito.

## Assumptions

- Este incremento solo cubre el catálogo; la carga de archivos, su visibilidad y las reglas de acceso son de la gestión de documentos del colaborador (GP-05), que se construye aparte y consulta este catálogo.
- El catálogo vive dentro del módulo de Gestión de Personal (mismo tenant y mismo esquema de roles que las fichas); no se crea un módulo nuevo.
- Los dos roles existentes son los únicos; el Administrador de Talento Humano gestiona y el Colaborador solo ve etiquetas a través de documentos autorizados.
- Las categorías nacen vacías: no se siembran categorías por defecto en producción; los datos de ejemplo solo se incluyen en el entorno de pruebas y demostración.
- La búsqueda parcial por nombre ignora mayúsculas y tildes; la unicidad ignora mayúsculas y espacios exteriores pero distingue tildes.
- El catálogo es pequeño (decenas de categorías por organización); aun así el listado se pagina (20 por página, máximo 100) y la página, la búsqueda y el filtro se conservan en la dirección de la pantalla.
- La concurrencia se resuelve con una marca de versión por categoría, igual que en las demás funcionalidades del módulo.
- Hasta que exista la gestión documental, la comprobación de «categoría usada» no bloquea ninguna operación, porque desactivar nunca depende de si hay documentos.
- La zona horaria del tenant se usa para presentar fechas de auditoría; los instantes se almacenan de forma uniforme.

## Pantallas *(si la feature tiene UI)*

### Categorías documentales (listado)
- **Propósito**: consultar y administrar el catálogo de categorías de la organización.
- **Datos que muestra**: nombre y estado (Activa o Inactiva) de cada categoría, ordenadas por nombre; resumen de la cantidad mostrada.
- **Acciones disponibles** (y quién puede hacerlas): Nueva categoría, Editar, Desactivar y Reactivar (solo Administrador); búsqueda por nombre y filtro de estado (Activas por defecto, Inactivas, Todas). El Colaborador no accede. No hay Eliminar.
- **Estados**: cargando, vacío («Aún no hay categorías documentales»), sin resultados, error con Reintentar, éxito tras cada acción (mensaje `role="status"`) y sin permisos.
- **Mockup**: patrón Listado (design/Competencias.dc.html) con columnas Nombre y Estado y acciones por fila, referencia de categorías en `design/GP-05_Documentos-del-colaborador.html`; confirmaciones con ConfirmDialog de `design/Estados.dc.html`.

### Formulario de categoría (modal)
- **Propósito**: crear una categoría o corregir su nombre.
- **Datos que muestra**: campo Nombre con contador `n/100`; en edición, el estado actual como dato de solo lectura.
- **Acciones disponibles** (y quién puede hacerlas): Guardar y Cancelar (solo Administrador); ante información cambiada, «Actualizar datos» conservando lo escrito.
- **Estados**: validando, guardando (botón bloqueado), error de nombre junto al campo, conflicto de nombre existente, información cambiada, fallo conservando lo escrito.
- **Mockup**: patrón Formulario en modal (design/Unidades.dc.html), un solo campo.

### Confirmación de desactivar y reactivar
- **Propósito**: confirmar el cambio de estado de una categoría.
- **Datos que muestra**: nombre de la categoría y, al desactivar, el aviso «Los documentos se conservan».
- **Acciones disponibles** (y quién puede hacerlas): Confirmar y Cancelar (solo Administrador).
- **Estados**: confirmando (bloqueado), información cambiada con petición de actualizar, error que conserva el estado anterior.
- **Mockup**: ConfirmDialog (design/Estados.dc.html).
