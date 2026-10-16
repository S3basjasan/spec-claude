# Feature Specification: Fichas de colaboradores (GP-01)

**Feature Branch**: `005-fichas-colaboradores`

**Created**: 2026-10-15

**Status**: Draft

**Input**: User description: "Especificaciones funcionales v1.0 de Gestión de Personal, §1 Marco común y vocabulario (reglas RC-01 a RC-13 y matriz general de acceso) y §2 GP-01 Registro y mantenimiento de fichas de colaboradores. El Administrador de Talento Humano registra manualmente y mantiene una ficha única por identidad dentro de su tenant (identificación, datos personales complementarios y contacto de emergencia); el sistema asigna un código EMP-### único, inmutable y no reutilizable. La combinación tipo y número de identificación es única por tenant. La ficha nace con estado Sin vínculo vigente, no crea cuenta, vínculo, documentos ni desarrollo, y nunca se elimina. El administrador lista, busca, filtra y consulta todas las fichas de su tenant; el colaborador solo consulta la propia si tiene una asociación válida entre cuenta y ficha y el permiso de consulta. Los datos laborales y la trayectoria son de solo lectura y provienen de GP-02, de los hechos laborales y de Estructura Organizacional. Datos ausentes se muestran como «No registrado». Se evita la sobrescritura silenciosa en edición concurrente."

## Clarifications

### Session 2026-10-15

- Q: ¿Cómo maneja GP-01 los tenants, si hoy el sistema no los tiene? → A: GP-01 añade el tenant a la cuenta (y por tanto a la sesión) y a cada ficha; varios tenants funcionan desde esta feature y el aislamiento de FR-001 se prueba con al menos dos tenants.
- Q: ¿Cómo se asocian cuenta y ficha, y cómo se dan sus permisos? → A: GP-01 solo lee la asociación cuenta-ficha y los permisos de consulta de ficha, consulta documental y exportación; se cargan con datos iniciales y su gestión queda para una feature de identidad y acceso.
- Q: ¿El rol Colaborador es el rol Usuario de 000-login? → A: Sí. Administrador de Talento Humano y Colaborador son los roles Administrador y Usuario existentes de 000-login; no se crean ni renombran roles.
- Q: ¿Qué opciones muestra el filtro Estado del listado mientras GP-02 no exista? → A: «Todos» más los estados que tenga al menos una ficha del tenant; hoy solo Sin vínculo vigente, y la lista crece sola cuando GP-02 cree otros estados.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registrar una ficha mínima (Priority: P1)

El Administrador de Talento Humano abre el listado de colaboradores, pulsa Nueva ficha, completa como mínimo el tipo y número de identificación, los nombres y los apellidos, y guarda. El sistema valida los datos, comprueba que la identificación no exista en su tenant, asigna un código EMP-### y abre el detalle de la ficha con estado Sin vínculo vigente. Opcionalmente puede registrar los datos personales complementarios y un contacto de emergencia.

**Why this priority**: Sin fichas no existe la identidad persistente del colaborador sobre la que se apoyan el ingreso (GP-02), el desarrollo (GP-03) y los documentos (GP-05).

**Independent Test**: Como Administrador, crear una ficha solo con identificación, nombres y apellidos y comprobar que aparece con código automático, estado Sin vínculo vigente y sin cuenta de usuario creada; intentar crear otra con la misma identificación y comprobar el rechazo.

**Acceptance Scenarios**:

1. **Given** un Administrador autorizado en el formulario de nueva ficha, **When** lo abre, **Then** ve los grupos Identificación, Datos personales, Contacto y Contacto de emergencia, y en el código la indicación de que se asigna al guardar.
2. **Given** una identificación que no existe en el tenant, **When** el Administrador guarda tipo y número de identificación, nombres y apellidos válidos, **Then** se crea la ficha con un código generado por el sistema con el formato EMP-### (por ejemplo, EMP-007), estado Sin vínculo vigente, sin vínculo laboral y sin cuenta de usuario, y se abre su detalle con un mensaje de confirmación. (GP01-CA01)
3. **Given** ya existe una ficha con Cédula 0102030405, **When** el Administrador intenta crear otra con Cédula " 0102030405 " (con espacios exteriores), **Then** se rechaza con un error en el número de identificación, se le ofrece abrir la ficha existente y sigue habiendo una sola ficha. (GP01-CA02)
4. **Given** ya existe una ficha con Pasaporte "AB123", **When** se intenta crear otra con Pasaporte "ab123", **Then** se rechaza como duplicado; **When** se crea con Cédula "AB123" u Otro "AB123", **Then** se acepta, porque el tipo es distinto.
5. **Given** el formulario, **When** faltan los nombres, los apellidos, el tipo o el número de identificación, o alguno supera su longitud máxima, **Then** se muestran errores junto a cada campo y no se crea nada.
6. **Given** el formulario, **When** la fecha de nacimiento es futura, el número de hijos es negativo o no entero, o el correo de contacto no tiene un formato válido, **Then** se muestra el error junto al campo y no se crea nada.
7. **Given** el formulario, **When** se escribe solo la relación del contacto de emergencia, o solo su nombre, **Then** se exige el nombre y el teléfono del contacto de emergencia; **When** los tres campos están vacíos, **Then** se guarda sin contacto de emergencia.
8. **Given** el correo de contacto de otra ficha, **When** se registra el mismo correo en una ficha nueva, **Then** se acepta, porque el correo no es único ni es una credencial.
9. **Given** un formulario con errores, **When** el Administrador corrige un campo, **Then** el resto de valores escritos se conserva y puede reintentar.
10. **Given** el formulario, **When** el Administrador cancela antes de guardar, **Then** no se crea ninguna ficha.
11. **Given** un fallo al guardar, **When** el sistema no pudo registrar la ficha, **Then** no muestra un mensaje de éxito, conserva los datos del formulario y permite reintentar sin crear dos fichas para la misma identificación.
12. **Given** una persona con rol Colaborador, **When** intenta crear una ficha (por la interfaz o directamente), **Then** se le deniega por falta de permisos.

---

### User Story 2 - Consultar el listado y el detalle de las fichas (Priority: P1)

El Administrador abre el listado de colaboradores de su tenant, busca por nombres, apellidos, código o puesto, filtra por estado y unidad organizacional, y abre una ficha para ver su detalle: datos personales y de contacto, puesto y situación laboral vigentes, trayectoria interna y, cuando estén disponibles y autorizadas, las secciones Desarrollo y Documentos.

**Why this priority**: Es la forma en que Talento Humano localiza y consulta a cualquier persona; sin consulta, el registro no aporta valor.

**Independent Test**: Con varias fichas en dos tenants, iniciar sesión como Administrador de un tenant, buscar con y sin tildes, filtrar y abrir un detalle; comprobar que solo aparecen fichas del propio tenant y que los datos ausentes se muestran como «No registrado».

**Acceptance Scenarios**:

1. **Given** fichas en distintos estados, **When** el Administrador abre el listado, **Then** ve todas las fichas de su tenant (todos los estados) con código, nombres y apellidos, puesto, unidad organizacional, estado y la acción Ver ficha, ordenadas por apellidos, nombres y código, 20 por página.
2. **Given** el listado, **When** escribe parte de un nombre, apellido, código o puesto, **Then** solo se muestran las fichas que coinciden, sin distinguir mayúsculas ni tildes (por ejemplo, "nunez" encuentra "Núñez").
3. **Given** una búsqueda, **When** además elige un estado y una unidad organizacional, **Then** se muestran solo las fichas que cumplen la búsqueda y ambos filtros.
4. **Given** ninguna ficha coincide, **When** se muestra el listado, **Then** aparece el mensaje "Ningún colaborador coincide con la búsqueda".
5. **Given** fichas de otro tenant, **When** el Administrador lista, busca o pide una de ellas por su identificador o código, **Then** no las ve ni las obtiene, y la respuesta no revela si existen.
6. **Given** una ficha mínima, **When** el Administrador abre su detalle, **Then** los datos complementarios aparecen como «No registrado», el puesto y la unidad como «No registrado», el estado como Sin vínculo vigente y la trayectoria vacía; no se inventa una asignación laboral. (GP01-CA05)
7. **Given** una ficha con un ingreso efectivo y una contratación con fecha efectiva futura registrados por la funcionalidad responsable, **When** se consulta la trayectoria, **Then** cada hecho muestra su fecha efectiva, su tipo y la posición o el cambio disponible, del más reciente al más antiguo, y la contratación futura se identifica como programada y no como hecho efectivo.
8. **Given** que la fuente de los datos laborales o de la trayectoria no responde, **When** se consulta la ficha, **Then** los datos personales se muestran normalmente y esa sección indica «Información no disponible» con la opción de reintentar, no como ausencia confirmada.
9. **Given** el detalle de una ficha, **When** el Administrador no tiene el permiso documental o la funcionalidad de documentos no está disponible, **Then** la sección Documentos no se muestra aunque tenga permiso sobre la ficha; lo mismo aplica a Desarrollo.
10. **Given** el detalle, **When** se muestra, **Then** no aparecen indicadores de adecuación, potencial ni datos salariales.
11. **Given** no hay fichas en el tenant, **When** se abre el listado, **Then** aparece un estado vacío con la acción Nueva ficha; si falla la carga, un error con "Reintentar".

---

### User Story 3 - Corregir los datos de una ficha (Priority: P2)

El Administrador corrige los datos de una ficha existente, incluida la identificación. El código y la referencia de la ficha no cambian y todo lo asociado a ella (vínculos, trayectoria, desarrollo, documentos) sigue unido a la misma ficha.

**Why this priority**: Como las fichas no se eliminan, la edición es la única forma de corregir errores de registro.

**Independent Test**: Corregir la identificación de una ficha a una combinación libre y comprobar que conserva su código; intentar cambiarla a una combinación ocupada y comprobar que nada cambia; simular dos ediciones simultáneas y comprobar que la segunda no sobrescribe en silencio.

**Acceptance Scenarios**:

1. **Given** una ficha existente, **When** el Administrador abre su edición, **Then** ve el mismo formulario de creación con los valores actuales y el código visible pero no editable.
2. **Given** el formulario de edición, **When** corrige la identificación a una combinación libre y guarda, **Then** los cambios se reflejan en el detalle y el listado, y el código y las asociaciones de la ficha se conservan. (GP01-CA03)
3. **Given** el formulario de edición, **When** cambia la identificación a una combinación que ya usa otra ficha del tenant, **Then** se rechaza con un error en la identificación, se ofrece abrir la otra ficha y no se modifica ningún dato. (GP01-CA03)
4. **Given** el formulario de edición, **When** conserva su propia identificación o solo cambia sus mayúsculas, **Then** no se considera duplicado.
5. **Given** dos ediciones abiertas sobre la misma ficha, **When** se guarda la segunda después de que la primera ya guardó, **Then** el segundo guardado se rechaza con un aviso de que la ficha cambió y se pide actualizar la información; no se sobrescribe el cambio reciente. (GP01-CA06)
6. **Given** el formulario de edición, **When** deja vacío un campo obligatorio o introduce un valor inválido, **Then** se aplican las mismas validaciones que al crear y no se guarda nada parcialmente.
7. **Given** una ficha, **When** el Administrador la consulta o edita, **Then** no existe acción para eliminarla ni desactivarla.
8. **Given** una persona con rol Colaborador, **When** intenta editar cualquier ficha, incluida la propia, **Then** se le deniega por falta de permisos.

---

### User Story 4 - El colaborador consulta su propia ficha (Priority: P2)

Un colaborador con cuenta asociada a su ficha y con el permiso de consulta habilitado abre "Mi ficha" y ve sus datos personales y laborales, sin acceso al directorio general ni a fichas de otras personas.

**Why this priority**: Da transparencia al colaborador sobre la información que el tenant conserva de él, con privacidad garantizada respecto de las demás fichas.

**Independent Test**: Con dos colaboradores asociados a sus fichas, iniciar sesión como uno de ellos, consultar la propia ficha y luego pedir la del otro alterando el identificador; comprobar que la segunda petición se deniega y que el listado no está disponible.

**Acceptance Scenarios**:

1. **Given** un colaborador con asociación válida y permiso de consulta, **When** abre Mi ficha, **Then** ve sus datos personales, de contacto y su situación laboral en modo solo lectura. (GP01-CA04)
2. **Given** ese colaborador, **When** pide otra ficha, incluso alterando el identificador o el código en la petición, **Then** se le deniega el acceso sin revelar si esa ficha existe. (GP01-CA04)
3. **Given** un colaborador, **When** intenta abrir el listado de colaboradores, **Then** no tiene acceso a él.
4. **Given** un colaborador sin permiso de consulta de ficha, **When** abre Mi ficha, **Then** se le informa que no tiene permiso para consultarla.
5. **Given** una cuenta con rol Colaborador sin ficha asociada, **When** abre Mi ficha, **Then** se le informa que su cuenta no tiene una ficha asociada.
6. **Given** un colaborador con permiso de consulta de ficha pero sin permiso documental, **When** abre Mi ficha, **Then** no ve la sección Documentos.

---

### Edge Cases

- Dos administradores crean a la vez fichas con la misma identificación: solo una se guarda y la otra recibe el error de duplicado con la opción de abrir la existente.
- Un reintento del mismo guardado (por ejemplo, doble clic o reenvío tras un corte de red) no crea dos fichas: el segundo intento recibe el duplicado o el resultado del primero.
- Dos fichas creadas a la vez reciben códigos distintos; un código nunca se reutiliza aunque haya saltos en la numeración.
- Se supera EMP-999: el código sigue creciendo (EMP-1000) sin repetir ninguno.
- El número de identificación conserva ceros iniciales y separadores internos: "0102-03" y "010203" son números distintos; "  0102-03 " y "0102-03" son el mismo.
- No se valida dígito verificador: una cédula con dígito verificador incorrecto se acepta si cumple formato y unicidad.
- Nombres o apellidos con solo espacios se tratan como vacíos; los espacios exteriores se recortan antes de validar la longitud.
- La fecha de nacimiento igual a la fecha actual del tenant es válida; un día después no.
- Número de hijos 0 es un dato registrado y se muestra como 0, no como «No registrado»; un campo vacío se muestra como «No registrado».
- Género, estado civil y nacionalidad se guardan tal como se escriben; el sistema no los deduce de otros datos.
- El contacto de emergencia se borra dejando vacíos sus tres campos; si queda alguno lleno, nombre y teléfono siguen siendo obligatorios.
- Guardar una edición sin cambios reales no modifica la ficha ni su última modificación.
- La edición concurrente coincide con la creación de un vínculo en GP-02: la edición de los datos personales no altera los vínculos ni la trayectoria.
- Un colaborador cuyo vínculo terminó (salida laboral) conserva su ficha; su posibilidad de consultarla depende del estado de su cuenta y de su permiso, no del estado laboral.
- La ficha pedida no existe en el tenant: se informa que no existe, igual que si perteneciera a otro tenant.
- Listado con muchas fichas: búsqueda, filtros y página se conservan en la dirección de la página.
- La unidad organizacional o el puesto de una ficha cambian en Estructura: el listado y el detalle muestran siempre los valores vigentes de la fuente.
- La consulta de puesto y unidad falla al cargar el listado: las fichas se listan igualmente y esas columnas indican «Información no disponible».
- Sin sesión iniciada no se accede a ninguna ficha (feature 000-login).

## Requirements *(mandatory)*

### Functional Requirements

**Aislamiento, roles y autorización**

- **FR-001**: Toda consulta, búsqueda, creación y modificación de fichas MUST limitarse al tenant de la sesión autenticada. Cambiar en la petición el identificador de tenant, de ficha o el código MUST NOT dar acceso a datos de otro tenant, y la respuesta MUST NOT revelar si el registro existe (RC-01).
- **FR-002**: El sistema MUST usar únicamente dos roles: Administrador de Talento Humano y Colaborador, que son los roles Administrador y Usuario existentes de 000-login (RC-02).
- **FR-003**: El sistema MUST comprobar en el servidor, en cada operación, el rol y los permisos de quien la solicita; un permiso ausente MUST considerarse denegado. Ocultar acciones en la interfaz MUST NOT sustituir esa comprobación (RC-03).
- **FR-004**: Solo el Administrador MUST poder crear y modificar fichas (RC-05) y listar las fichas de su tenant.
- **FR-005**: Un Colaborador MUST poder consultar únicamente su propia ficha, y solo si su cuenta tiene una asociación válida con esa ficha y el permiso de consulta de ficha. MUST NOT acceder al listado general ni a otras fichas (RC-04).
- **FR-006**: Los permisos de consulta de ficha, consulta documental y exportación MUST ser independientes: tener uno MUST NOT conceder los otros (RC-04).

**Datos de la ficha**

- **FR-007**: Cada ficha MUST tener un código generado por el sistema al crearla, con el formato `EMP-` seguido de un número de al menos 3 dígitos (por ejemplo, `EMP-007`), que se amplía al superar 999. El código MUST ser único por tenant, MUST NOT modificarse ni reutilizarse y MUST NOT exigirse numeración sin saltos.
- **FR-008**: Cada ficha MUST tener tipo de identificación (Cédula, Pasaporte u Otro), número de identificación (hasta 50 caracteres), nombres (1 a 150 caracteres) y apellidos (1 a 150 caracteres), todos obligatorios y medidos después de recortar los espacios exteriores. Un texto con solo espacios MUST tratarse como vacío.
- **FR-009**: La ficha MUST admitir los siguientes datos opcionales: fecha de nacimiento (fecha válida no futura), nacionalidad (hasta 100), género (hasta 80, sin inferirse de otros datos), estado civil (hasta 80), número de hijos (entero igual o mayor que cero), dirección (hasta 500), teléfono (hasta 30, admite prefijo internacional) y correo de contacto (formato válido, hasta 254, sin exigir unicidad y sin uso como credencial).
- **FR-010**: La ficha MUST admitir un contacto de emergencia opcional; si se registra cualquiera de sus datos, el nombre (hasta 150) y el teléfono (hasta 30) MUST ser obligatorios y la relación (hasta 100) opcional.
- **FR-011**: La combinación de tipo y número de identificación MUST ser única por tenant. El número MUST compararse sin espacios exteriores y sin distinguir mayúsculas, conservando ceros iniciales y separadores internos. El sistema MUST NOT aplicar validadores nacionales de dígito verificador (GP01-RN01).
- **FR-012**: Guardar con identificación, nombres y apellidos válidos MUST ser suficiente para crear la ficha. No existe estado Borrador y MUST NOT exigirse puesto, correo, cuenta ni vínculo (GP01-RN02).

**Creación y edición**

- **FR-013**: Ante una identificación duplicada, el sistema MUST rechazar la operación sin crear ni modificar nada y MUST ofrecer al Administrador abrir la ficha existente. El sistema MUST NOT fusionar personas automáticamente (GP01-RN03).
- **FR-014**: Los reintentos de una creación y las creaciones simultáneas MUST NOT producir dos fichas con la misma identificación ni dos fichas con el mismo código (GP01-RN04).
- **FR-015**: Crear una ficha MUST producir el estado Sin vínculo vigente y MUST NOT crear cuenta de usuario, conceder permisos, crear vínculos laborales, documentos ni registros de desarrollo. MUST NOT reutilizarse automáticamente una cuenta del portal de candidatos (GP01-RN06, RC-06).
- **FR-016**: Modificar la identificación MUST volver a comprobar la unicidad. El código y la referencia de la ficha MUST NOT cambiar, y los vínculos, la trayectoria y los demás datos asociados MUST seguir unidos a la misma ficha (GP01-RN05).
- **FR-017**: Crear y editar MUST usar el mismo formulario. El código MUST mostrarse en solo lectura al editar y, al crear, MUST indicarse que se asigna al guardar.
- **FR-018**: Las validaciones MUST ejecutarse también al guardar en el servidor. Un fallo MUST NOT presentarse como éxito, MUST NOT dejar la ficha creada o modificada parcialmente y el formulario MUST conservar los valores introducidos para corregir y reintentar. MUST impedirse el envío duplicado mientras un guardado está en curso (RC-09).
- **FR-019**: Al editar, si la ficha cambió desde que se abrió el formulario, el sistema MUST rechazar el guardado con un aviso que pida actualizar la información, sin sobrescribir el cambio reciente (RC-10).
- **FR-020**: Las fichas MUST NOT eliminarse ni desactivarse: no existen esas acciones y los errores se corrigen editando (GP01-RN07, RC-07).
- **FR-021**: El sistema MUST registrar quién creó cada ficha y cuándo, y quién la modificó por última vez y cuándo. Guardar sin cambios reales MUST NOT actualizar la última modificación.

**Listado**

- **FR-022**: El listado MUST mostrar, para cada ficha del tenant, código, nombres y apellidos, puesto, unidad organizacional, estado y la acción Ver ficha.
- **FR-023**: El listado MUST permitir búsqueda parcial por nombres, apellidos, código o puesto, sin distinguir mayúsculas ni tildes, combinable con filtros por estado y por unidad organizacional. El filtro de estado MUST ofrecer «Todos» y solo los estados que tenga al menos una ficha del tenant. Si nada coincide MUST mostrar "Ningún colaborador coincide con la búsqueda".
- **FR-024**: Por defecto el listado MUST incluir todos los estados, ordenar por apellidos, nombres y código, y presentar 20 fichas por página. Búsqueda, filtros y página MUST conservarse en la dirección de la página.
- **FR-025**: El listado MUST cubrir los estados cargando, vacío, error con reintento y éxito.

**Detalle**

- **FR-026**: El detalle MUST presentar la sección Personal y puesto (identificación, datos personales, contacto, contacto de emergencia, puesto, unidad organizacional, estado laboral y trayectoria interna) y, cuando sus funcionalidades estén disponibles, las secciones Desarrollo (GP-03) y Documentos (GP-05). Cada sección MUST aplicar sus propios permisos; el permiso de ficha MUST NOT habilitar Documentos.
- **FR-027**: Un dato ausente MUST mostrarse como «No registrado» y MUST NOT convertirse en cero, en una respuesta negativa ni en evidencia de incumplimiento (RC-08).
- **FR-028**: Puesto, unidad organizacional, estado laboral y trayectoria MUST ser de solo lectura en esta funcionalidad y MUST obtenerse de sus fuentes (GP-02, hechos laborales y Estructura Organizacional). Gestión de Personal MUST NOT mantener un catálogo alternativo de puestos, posiciones ni unidades (RC-11, GP01-RN08).
- **FR-029**: Si la fuente de los datos laborales o de la trayectoria no responde, el detalle MUST mostrar esa información como «Información no disponible» con opción de reintento, distinta de «No registrado», y MUST seguir mostrando los datos personales (GP01-RN08).
- **FR-030**: La trayectoria MUST mostrar fecha efectiva, tipo de hecho y posición o cambio disponible de cada hecho, ordenados del más reciente al más antiguo. Un hecho con fecha efectiva futura (por ejemplo, una contratación) MUST identificarse como programado. La trayectoria MUST NOT editarse como texto libre (RC-12).
- **FR-031**: El detalle MUST NOT mostrar indicadores de adecuación, potencial, criticidad, salarios, nómina, cuentas bancarias, idiomas, periodo de prueba ni suspensión (RC-13).
- **FR-032**: La acción Editar del detalle MUST mostrarse solo al Administrador. Las acciones Registrar ingreso y Exportar MUST aparecer solo cuando sus funcionalidades estén implementadas y autorizadas; esta funcionalidad MUST funcionar sin ellas.

**Experiencia y accesibilidad**

- **FR-033**: Toda la interfaz MUST estar en español y cumplir WCAG 2.2 AA entre 320 y 1440 px de ancho, sin desplazamiento horizontal de la página.

**Fuera de alcance**: creación de vínculos, ingresos y reingresos (GP-02); información de desarrollo (GP-03); categorías y documentos (GP-05); exportación de la ficha; incorporación desde Selección; movimientos y offboarding; creación, habilitación y asociación de cuentas de usuario y la gestión de sus permisos; fusión de fichas duplicadas; validación de dígito verificador; historial detallado de cambios de la ficha; importación masiva; y los datos excluidos por RC-13.

### Key Entities *(include if feature involves data)*

- **Ficha de colaborador**: registro persistente de una persona dentro de un tenant. Tiene código único e inmutable (EMP-###), identificación (tipo y número, únicos en combinación por tenant), nombres, apellidos, datos personales complementarios opcionales, contacto opcional y contacto de emergencia opcional; guarda quién la creó y modificó y cuándo. Nunca se elimina. Puede existir sin cuenta y sin vínculo vigente.
- **Contacto de emergencia**: persona a avisar, con nombre, teléfono y relación opcional; pertenece a una sola ficha.
- **Tenant** *(externo)*: organización cuyo contexto de datos y permisos está aislado; toda ficha pertenece a exactamente uno.
- **Cuenta de usuario** *(externa, identidad y acceso)*: identidad para autenticarse con un rol (Administrador o Colaborador) y permisos independientes (consulta de ficha, consulta documental, exportación). Puede estar asociada a una ficha; su habilitación es independiente del estado laboral.
- **Vínculo laboral** *(externo, GP-02)*: relación laboral delimitada por fechas sobre una ficha; determina el estado laboral, el puesto y la unidad vigentes.
- **Hecho laboral** *(externo)*: ingreso, asignación, movimiento o salida con fecha efectiva y momento de registro; compone la trayectoria interna.
- **Puesto, posición y unidad organizacional** *(externos, Estructura Organizacional)*: se consultan por su contrato público; la ficha no guarda copia.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El Administrador registra una ficha mínima (identificación, nombres y apellidos) en menos de 1 minuto y una ficha con todos los datos complementarios en menos de 4 minutos.
- **SC-002**: El Administrador encuentra una ficha por nombre, apellido, código o puesto en menos de 15 segundos, escriba o no las tildes.
- **SC-003**: El 100 % de los intentos de registrar o cambiar a una identificación ya usada en el tenant se rechaza, incluidos los simultáneos y los reintentos, y ningún tenant tiene dos fichas con la misma identificación ni el mismo código.
- **SC-004**: El 100 % de los intentos de acceder a fichas de otro tenant, o de un colaborador a una ficha ajena, se deniega sin exponer datos ni confirmar su existencia.
- **SC-005**: El 100 % de los intentos de un Colaborador de crear, editar o listar fichas se rechaza.
- **SC-006**: El 100 % de las fichas creadas en esta funcionalidad queda con estado Sin vínculo vigente y sin cuenta, vínculo, documentos ni desarrollo creados.
- **SC-007**: El 100 % de las fichas permanece en el sistema: ninguna acción las elimina; y ningún guardado rechazado deja cambios parciales.
- **SC-008**: El 100 % de los datos ausentes se muestra como «No registrado» y el 100 % de las fuentes que no responden se muestra como «Información no disponible».
- **SC-009**: El 100 % de las ediciones sobre una versión desactualizada se rechaza sin sobrescribir el cambio más reciente.
- **SC-010**: La consulta de fichas funciona completa sin que las funcionalidades de exportación, documentos o desarrollo estén disponibles.

## Assumptions

- El rol Administrador de Talento Humano corresponde al rol Administrador de 000-login y el rol Colaborador al rol Usuario; no se crean roles nuevos.
- El sistema actual no tiene tenants. Esta feature introduce el tenant: cada cuenta pertenece a un tenant, la sesión lo lleva y cada ficha pertenece a exactamente uno; los datos iniciales incluyen al menos dos tenants para probar el aislamiento. El mecanismo de almacenamiento (por ejemplo, el esquema por tenant del C4 frente a SQLite, que la constitución fija como almacenamiento de desarrollo) se decide en el plan técnico y puede requerir actualizar la constitución.
- La asociación entre cuenta y ficha y la asignación de los permisos de consulta de ficha, consulta documental y exportación pertenecen a identidad y acceso, fuera de este alcance. GP-01 no ofrece pantalla para gestionarlas: solo las lee, y para probar esta feature se cargan con datos iniciales; sin asociación o sin permiso, el colaborador no consulta ninguna ficha.
- Mientras GP-02 y las posiciones de Estructura no existan, ninguna ficha tiene vínculo: el estado es Sin vínculo vigente, puesto y unidad se muestran como «No registrado», la trayectoria aparece vacía y el filtro de unidad solo ofrece las unidades que Estructura publique. El filtro de estado ofrece «Todos» y Sin vínculo vigente; los demás estados aparecen cuando GP-02 los cree y alguna ficha los tenga.
- Las secciones Desarrollo y Documentos no se muestran hasta que GP-03 y GP-05 estén implementadas; esta feature solo reserva su lugar en el detalle.
- La fecha usada para comprobar que la fecha de nacimiento no es futura es la fecha actual en la zona horaria del tenant.
- El tipo de identificación Otro no exige describir el tipo; el catálogo inicial de tipos (Cédula, Pasaporte, Otro) es fijo en esta versión.
- El teléfono se valida solo por longitud y caracteres habituales (dígitos, espacios, guiones, paréntesis y un "+" inicial); no se valida contra un plan de numeración nacional.
- Se registran el creador, el último modificador y sus fechas, como en las features anteriores; no se exige un historial campo a campo ni una tabla adicional de auditoría.
- El control de edición concurrente se comporta como en 001 y 004: el segundo guardado sobre una versión desactualizada se rechaza.
- Al crear una ficha con éxito se abre su detalle; Cancelar vuelve al listado sin pedir confirmación.
- La respuesta a una ficha de otro tenant o ajena al colaborador es la misma que para una ficha inexistente.
- Los textos de la interfaz usan "Colaboradores", "Ficha", "Nueva ficha", "Ver ficha", "Mi ficha", "Sin vínculo vigente" y "No registrado".

## Pantallas *(si la feature tiene UI)*

### Listado de colaboradores
- **Propósito**: localizar cualquier ficha del tenant y crear fichas nuevas.
- **Datos que muestra**: código, nombres y apellidos, puesto, unidad organizacional y estado (Badge) de cada ficha; total y página actual.
- **Acciones disponibles** (y quién puede hacerlas): buscar, filtrar por estado y por unidad, paginar, Ver ficha y Nueva ficha (solo Administrador). No hay eliminar ni Activar/Desactivar.
- **Estados**: cargando (esqueleto de tabla), vacío (sin fichas, con Nueva ficha), sin coincidencias ("Ningún colaborador coincide con la búsqueda"), error con "Reintentar", éxito, puesto y unidad «Información no disponible» si su fuente falla, sin permisos (el Colaborador no accede).
- **Mockup**: patrón Listado (`design/Competencias.dc.html`, `design/DESIGN.md` §5 puntos 1 a 5) con Estados (`design/Estados.dc.html`); columnas Código, Colaborador, Puesto, Unidad y Estado; filtros Estado y Unidad; la acción por fila es "Ver ficha" en lugar de editar.

### Formulario de ficha (crear y editar)
- **Propósito**: registrar una ficha o corregir sus datos.
- **Datos que muestra**: cuatro `fieldset` en una Card: Identificación (código en solo lectura o "Se asigna al guardar", tipo, número, nombres, apellidos), Datos personales (fecha de nacimiento, nacionalidad, género, estado civil, número de hijos), Contacto (dirección, teléfono, correo de contacto) y Contacto de emergencia (nombre, teléfono, relación), con contadores en los textos con límite.
- **Acciones disponibles** (y quién puede hacerlas): Guardar y Cancelar (solo Administrador); ante un duplicado, "Abrir ficha existente".
- **Estados**: cargando (al abrir una edición), errores por campo con alerta general, duplicado con enlace a la ficha existente, conflicto de edición, guardando (botón bloqueado), fallo al guardar con datos conservados, éxito (abre el detalle) y sin permisos.
- **Mockup**: patrón Formulario (`design/Formulario.dc.html`, `design/DESIGN.md` §5 Formulario puntos 1 a 6). El aviso de duplicado con acción "Abrir ficha existente" es un Alert con enlace que debe añadirse a `design/DESIGN.md` §4.

### Detalle de ficha y Mi ficha
- **Propósito**: consultar la ficha completa (Administrador) o la propia (Colaborador).
- **Datos que muestra**: encabezado con nombre completo, código y estado; sección Personal y puesto con identificación, datos personales, contacto, contacto de emergencia, puesto, unidad y trayectoria (fecha efectiva, tipo de hecho, posición o cambio, marca "Programado"); secciones Desarrollo y Documentos cuando estén disponibles y autorizadas.
- **Acciones disponibles** (y quién puede hacerlas): Editar (solo Administrador); Registrar ingreso y Exportar solo cuando sus features existan y estén autorizadas. Mi ficha no tiene acciones de edición.
- **Estados**: cargando, datos «No registrado», sección «Información no disponible» con reintento, ficha inexistente, sin permisos, cuenta sin ficha asociada y éxito tras crear o guardar.
- **Mockup**: **nuevo tablero** `design/ColaboradorFicha.dc.html` en el canvas de Claude Design (`design/DESIGN.md` §8): ningún patrón actual cubre una página de detalle con secciones o pestañas (Personal y puesto, Desarrollo, Documentos), pares etiqueta-valor con «No registrado» y la línea de tiempo de trayectoria con hechos programados. **Requiere ampliar `design/DESIGN.md` antes de implementar** (Constitución III).
