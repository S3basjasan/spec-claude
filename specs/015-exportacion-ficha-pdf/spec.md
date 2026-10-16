# Feature Specification: Exportación individual de ficha integral

**Feature Branch**: `015-exportacion-ficha-pdf`

**Created**: 2026-10-16

**Status**: Draft

**Input**: User description: "Exportación individual de ficha integral. Generar un PDF individual con una representación legible de los datos guardados de una ficha: código, identificación, datos personales complementarios, estado laboral, vínculo vigente o ingreso programado, tipo de contrato, posición, puesto, unidad, formación académica, experiencia anterior, movilidad, trayectoria interna disponible y fecha y hora de generación. El Administrador de Talento Humano exporta cualquier ficha de su tenant; el Colaborador solo la propia y con permiso de exportación, independiente de la consulta por pantalla y de la consulta documental. No incluye listados, CSV, exportaciones masivas, adjuntos, información financiera ni indicadores calculados. La autorización se comprueba al solicitar y al entregar, sin direcciones públicas permanentes; un fallo no produce un archivo parcial presentado como válido."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Exportar una ficha como Administrador (Priority: P1)

El Administrador de Talento Humano abre la ficha de cualquier colaborador de su organización, selecciona Exportar ficha y obtiene un PDF legible con los datos guardados de esa persona. El archivo se descarga con el nombre `Ficha_EMP-###.pdf`.

**Why this priority**: Es el valor central de la funcionalidad y la única forma de entregar una representación portátil de la ficha integral.

**Independent Test**: Con una ficha completa (datos personales, vínculo vigente, formación, experiencia y movilidad), exportarla y comprobar que el PDF contiene todas las secciones rotuladas con título, código, paginación y momento de generación, que el nombre sugerido es `Ficha_EMP-###.pdf` y que la ficha no cambió.

**Acceptance Scenarios**:

1. **Given** una ficha con datos completos, **When** el Administrador selecciona Exportar ficha, **Then** recibe un PDF con título, código del colaborador, identificación, datos personales complementarios, estado laboral, vínculo vigente (tipo de contrato, posición, puesto y unidad), formación académica, experiencia anterior, movilidad, trayectoria interna y la fecha y hora de generación, cada sección claramente rotulada. (EXP-CA01)
2. **Given** una ficha con campos sin datos, **When** se exporta, **Then** los datos ausentes se muestran como «No registrado», la movilidad sin declarar como «No informada» y las secciones sin registros se identifican como vacías en lugar de omitirse. (EXP-CA02)
3. **Given** una ficha con un ingreso programado a futuro, **When** se exporta, **Then** el ingreso aparece como programado y no como vínculo vigente; los vínculos anteriores aparecen en la trayectoria. (EXP-CA03)
4. **Given** una ficha sin vínculo vigente, **When** se exporta, **Then** el estado laboral se presenta como sin vínculo vigente y las secciones laborales lo reflejan sin inventar datos.
5. **Given** listas extensas (por ejemplo muchas experiencias o muchos hechos de trayectoria), **When** se exporta, **Then** continúan en páginas sucesivas sin recortar registros ni superponer texto y todas las páginas llevan paginación.
6. **Given** una ficha con textos largos en observaciones o funciones, **When** se exporta, **Then** el texto completo se muestra con saltos de línea correctos, sin cortes ni desbordes.
7. **Given** una ficha ajena de otro tenant o inexistente, **When** el Administrador intenta exportarla, **Then** se responde como si no existiera y no se genera ningún archivo.
8. **Given** una exportación, **When** termina, **Then** la ficha, sus vínculos y sus documentos no cambian, no se crea ningún vínculo ni registro, y el PDF no se agrega al expediente documental.
9. **Given** una ficha con documentos cargados, **When** se exporta, **Then** el PDF no incrusta archivos adjuntos ni enlaces de descarga ni referencias de acceso a ellos.

---

### User Story 2 - Exportar la propia ficha como Colaborador (Priority: P1)

El Colaborador con permiso de exportación y una ficha asociada exporta su propia ficha con el mismo contenido y alcance de datos que el Administrador, aunque no tenga el permiso de consulta por pantalla.

**Why this priority**: Permite entregar a la persona su propia información sin depender del Administrador; el permiso es independiente y debe respetarse.

**Independent Test**: Con dos colaboradores con distintos permisos, comprobar que el que tiene permiso de exportación obtiene el PDF de su ficha (aunque no pueda consultarla por pantalla), que no puede exportar la ajena aun conociendo su identificador, y que quien solo tiene permiso de consulta de ficha o de documentos no puede exportar.

**Acceptance Scenarios**:

1. **Given** una cuenta con asociación válida a su ficha y permiso de exportación, **When** selecciona Exportar mi ficha, **Then** recibe el PDF de su propia ficha con las mismas secciones y el mismo alcance de datos personales y laborales que recibe el Administrador. (EXP-CA04)
2. **Given** una cuenta con permiso de exportación pero sin permiso de consulta por pantalla, **When** exporta, **Then** obtiene el PDF: el permiso es independiente.
3. **Given** una cuenta con permiso de consulta de ficha o de consulta documental pero sin permiso de exportación, **When** intenta exportar, **Then** se le deniega y no se genera ningún archivo. (EXP-CA05)
4. **Given** una cuenta con permiso de exportación, **When** intenta exportar la ficha de otra persona (cambiando el identificador), **Then** se responde como si no existiera y no se genera ningún archivo.
5. **Given** una cuenta sin ficha asociada, **When** intenta exportar, **Then** se informa de que no tiene una ficha asociada y no se genera nada.
6. **Given** que el permiso de exportación se revoca después de solicitar y antes de entregar el archivo, **When** se intenta entregar, **Then** el documento no se entrega y se informa de que no tiene permiso.
7. **Given** que la persona exporta su ficha, **When** el PDF se genera, **Then** no incluye documentos ni sus archivos aunque tenga permiso documental.
8. **Given** el Colaborador usa la interfaz, **When** abre su vista, **Then** solo ve la acción de exportar su propia ficha; no ve exportaciones de listados, CSV ni de otras fichas.

---

### User Story 3 - Progreso, errores y entrega segura (Priority: P2)

La persona ve el progreso de la exportación, no puede lanzar solicitudes repetidas por accidente mientras la misma operación está en curso, y si algo falla recibe un mensaje claro y puede reintentar sin obtener un archivo parcial.

**Why this priority**: Evita estados ambiguos (archivos incompletos, doble generación) y garantiza que la entrega siempre esté autorizada, pero se apoya en que la exportación base funcione.

**Independent Test**: Con una fuente de datos fallando, intentar exportar y comprobar que se informa el error sin archivo parcial y que se puede reintentar; con la acción en curso, comprobar que un segundo clic no lanza otra generación.

**Acceptance Scenarios**:

1. **Given** una exportación en curso, **When** la persona vuelve a pulsar Exportar, **Then** no se inicia una segunda generación; la interfaz indica que ya está en curso.
2. **Given** una exportación en curso, **When** termina correctamente, **Then** la interfaz indica el resultado y la descarga comienza con el nombre `Ficha_EMP-###.pdf`.
3. **Given** que falla la obtención de datos necesarios (por ejemplo, la situación laboral, la formación o la estructura organizacional), **When** se intenta exportar, **Then** no se confirma la exportación, no se entrega ningún archivo, se informa del error y se permite reintentar; la sección afectada nunca se disfraza de vacía. (EXP-CA06)
4. **Given** que falla la generación del PDF, **When** el sistema no puede completarlo, **Then** no se entrega un archivo parcial presentado como válido, se informa el error y se permite reintentar.
5. **Given** una descarga entregada, **When** la persona intenta reutilizar o compartir la dirección de la descarga, **Then** no existe una dirección pública permanente: cada entrega se autoriza en el momento.
6. **Given** que la persona recarga la página o cierra la sesión durante la generación, **When** vuelve, **Then** la exportación no queda en un estado que parezca completada y puede solicitarla de nuevo.
7. **Given** un error de red al solicitar, **When** la persona reintenta, **Then** obtiene un PDF coherente con los datos guardados en ese momento.

---

### Edge Cases

- Ficha creada sin vínculo, formación, experiencia ni movilidad: el PDF se genera con todas las secciones identificadas como vacías y movilidad «No informada».
- Datos cambiados mientras se genera la exportación: el PDF refleja una lectura coherente de un mismo momento y muestra su fecha y hora de generación.
- Cambios sin guardar en un formulario abierto: no aparecen en el PDF.
- Nombres, textos y observaciones con tildes, eñes y caracteres especiales: se muestran correctamente.
- Ficha con muchos registros de formación, experiencia y trayectoria: continúa en varias páginas sin pérdida.
- Ingreso programado a futuro junto a vínculos previos: el futuro no se representa como vigente.
- Unidad, puesto o posición cuya definición organizacional cambió o está inactiva: se muestra el nombre vigente de la definición sin recalcular ni inventar.
- Dependencia no disponible (situación laboral, estructura, desarrollo): la exportación no se confirma y no se oculta el fallo.
- Permiso revocado entre solicitud y entrega: no se entrega.
- Identificador de otra ficha o de otro tenant: respuesta idéntica a «no existe», sin archivo.
- Cuenta con permiso de exportación pero con asociación inválida (ficha de otro tenant): sin acceso.
- Solicitud repetida mientras la anterior está en curso: no genera una segunda.

## Requirements *(mandatory)*

### Functional Requirements

**Alcance y permisos**

- **FR-001**: El sistema MUST permitir al Administrador de Talento Humano exportar la ficha individual de cualquier colaborador de su tenant.
- **FR-002**: El sistema MUST permitir al Colaborador exportar únicamente su propia ficha, con asociación válida y permiso de exportación vigente.
- **FR-003**: El permiso de exportación propia MUST ser independiente del permiso de consulta de ficha por pantalla y del permiso de consulta documental; tener uno no concede los otros.
- **FR-004**: El sistema MUST NOT ofrecer exportación de listados, CSV ni de múltiples fichas, para ningún rol.
- **FR-005**: Toda operación MUST limitarse al tenant autenticado; los permisos se comprueban en el servidor, un permiso ausente se considera denegado y ocultar controles no sustituye esa comprobación.
- **FR-006**: Una ficha inexistente, de otro tenant o ajena para el Colaborador MUST recibir la misma respuesta, sin revelar si existe; cambiar identificadores en una petición no concede acceso.
- **FR-007**: Solo MUST utilizarse los dos roles definidos (Administrador de Talento Humano y Colaborador).

**Contenido del PDF**

- **FR-008**: El PDF MUST incluir título, código del colaborador, identificación, datos personales complementarios, estado laboral, vínculo vigente o ingreso programado, tipo de contrato, posición, puesto, unidad, formación académica, experiencia anterior, movilidad, trayectoria interna disponible y la fecha y hora de generación.
- **FR-009**: Las secciones MUST estar claramente rotuladas y las secciones sin registros MUST identificarse como vacías; los datos ausentes MUST mostrarse como «No registrado» y la movilidad sin declarar como «No informada».
- **FR-010**: Un ingreso programado a futuro MUST NOT presentarse como vigente; los vínculos anteriores MUST presentarse en la trayectoria.
- **FR-011**: El PDF MUST construirse con información guardada y una lectura coherente para esa generación, sin cambios no guardados.
- **FR-012**: El PDF MUST tener paginación; las listas extensas MUST continuar en páginas sucesivas sin recortar registros ni superponer texto.
- **FR-013**: El PDF MUST NOT incrustar archivos adjuntos, enlaces de descarga privados ni referencias de acceso, información financiera, ni porcentajes, grados o indicadores de adecuación calculados.
- **FR-014**: El exportador MUST NOT recalcular ni inventar situaciones laborales, asignaciones o trayectoria: presenta lo que las demás funcionalidades registran.
- **FR-015**: Los datos personales y laborales del PDF MUST tener el mismo alcance que la consulta por pantalla para esa persona; el permiso de exportar no habilita documentos ni fichas ajenas.

**Generación y entrega**

- **FR-016**: Si falla la obtención de cualquier dato necesario, el sistema MUST NOT confirmar la exportación ni entregar archivo, MUST informar el error y MUST permitir reintentar; una sección afectada MUST NOT disfrazarse de vacía.
- **FR-017**: Un fallo de generación MUST NOT producir un archivo parcial presentado como válido; MUST informarse el error y permitirse reintentar.
- **FR-018**: El sistema MUST comprobar la autorización al solicitar y al entregar el archivo; si el permiso se revoca antes de la entrega, el documento MUST NOT entregarse.
- **FR-019**: El sistema MUST NOT ofrecer una dirección pública permanente para el PDF; cada entrega se autoriza en el momento.
- **FR-020**: El nombre de descarga propuesto MUST ser `Ficha_EMP-###.pdf`, con el código real de la ficha.
- **FR-021**: La interfaz MUST mostrar el progreso y el resultado y MUST evitar solicitudes repetidas accidentales mientras la misma operación está en curso.

**Naturaleza de la operación y auditoría**

- **FR-022**: Exportar MUST ser una lectura: no modifica la ficha, no crea vínculos y no agrega automáticamente el PDF al expediente documental.
- **FR-023**: El PDF MUST indicar el momento de generación y representar información guardada de la ficha.
- **FR-024**: El sistema MUST NOT exigir una tabla de exportaciones, un historial por generación ni una pantalla de historial de exportaciones o auditoría en este incremento.

### Key Entities *(include if feature involves data)*

- **Ficha exportada (documento generado)**: representación legible y efímera de una ficha en un instante: código, identificación, datos personales, situación laboral y vínculo (o ingreso programado), formación, experiencia, movilidad, trayectoria y momento de generación. No se conserva como documento ni como registro; se entrega al solicitante autorizado.
- **Ficha del colaborador** *(consumida)*: origen de código, identificación y datos personales guardados.
- **Situación y trayectoria laboral** *(consumidas de ingresos y reingresos)*: estado laboral, vínculo vigente o ingreso programado, tipo de contrato, posición y hechos laborales.
- **Desarrollo profesional** *(consumido)*: formación académica, experiencia anterior y movilidad registradas.
- **Definiciones organizacionales** *(consumidas de Estructura)*: puesto y unidad de la posición asignada.
- **Permiso de exportación de la cuenta** *(consumido de Identidad y acceso)*: habilita al Colaborador a exportar su propia ficha; es independiente de los permisos de ficha y documentos.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un Administrador obtiene el PDF de una ficha completa en menos de 10 segundos desde que selecciona Exportar ficha.
- **SC-002**: El 100 % de los PDF generados contienen todas las secciones del alcance, con los datos ausentes marcados como «No registrado» y las secciones vacías identificadas, sin omitir ninguna.
- **SC-003**: El 100 % de las exportaciones con listas extensas conserva todos los registros en páginas sucesivas, sin recortes ni texto superpuesto.
- **SC-004**: El 100 % de los intentos de exportar una ficha ajena, de otro tenant o sin permiso de exportación es denegado y no genera ningún archivo.
- **SC-005**: El 100 % de las exportaciones en las que falla una fuente de datos o la generación termina sin archivo y con el error informado, y ninguna presenta una sección afectada como vacía.
- **SC-006**: Si el permiso se revoca entre la solicitud y la entrega, el 100 % de esas entregas se rechaza.
- **SC-007**: El PDF nunca incrusta adjuntos, enlaces de descarga ni indicadores calculados, y exportar nunca modifica datos ni crea registros ni agrega el PDF al expediente documental.
- **SC-008**: Una solicitud repetida mientras la primera está en curso produce una sola generación en el 100 % de los casos.
- **SC-009**: El Colaborador con permiso de exportación y sin permiso de consulta por pantalla obtiene su PDF en el 100 % de los intentos, y quien tiene consulta de ficha o de documentos sin exportación no lo obtiene en ninguno.

## Assumptions

- Esta funcionalidad (GP-06) consume lo ya implementado: fichas (GP-01), ingresos, situación y trayectoria laboral (GP-02), desarrollo profesional (GP-03) y las definiciones de Estructura; ya existe una lectura por lote de desarrollo y los lectores laborales y estructurales del módulo.
- El permiso de exportación ya existe en la asociación cuenta-ficha, independiente de los de ficha y documentos; esta funcionalidad lo consume sin redefinirlo.
- Los dos roles existentes son los únicos; el Administrador exporta cualquier ficha de su tenant y el Colaborador solo la propia.
- La exportación es una operación solicitada y entregada de forma inmediata; el PDF no se almacena ni se versiona, y cada solicitud lo vuelve a generar.
- El PDF usa el idioma español, el formato de fecha del producto y la zona horaria del tenant para el momento de generación.
- El alcance de los datos personales y laborales del PDF es el mismo de la consulta por pantalla; no se agregan campos que la pantalla no muestre.
- La trayectoria interna se presenta como la consultan hoy las demás funcionalidades; si la lectura laboral falla, no se confirma la exportación.
- Un código de ficha siempre existe (se asigna al crear la ficha), por lo que el nombre `Ficha_EMP-###.pdf` siempre puede formarse.
- La visualización de la acción de exportar en la interfaz depende de las capacidades de la ficha; el servidor es la autoridad.

## Pantallas *(si la feature tiene UI)*

### Exportar ficha (acción en el detalle de ficha y en Mi ficha)
- **Propósito**: obtener el PDF individual de una ficha.
- **Datos que muestra**: botón «Exportar ficha» (Administrador) o «Exportar mi ficha» (Colaborador), indicador de progreso y resultado, mensaje de error con Reintentar.
- **Acciones disponibles** (y quién puede hacerlas): Exportar ficha (Administrador sobre fichas de su tenant; Colaborador sobre la propia con permiso de exportación); Reintentar tras un error. No hay exportación masiva ni CSV.
- **Estados**: inactivo, generando (acción bloqueada con progreso), éxito con descarga iniciada, error con Reintentar, sin permiso, sin ficha asociada.
- **Mockup**: acción en el tablero de detalle de ficha (`design/ColaboradorFicha.dc.html`) con el prototipo `design/GP-06_Exportar-ficha-PDF.html` como referencia.

### Documento PDF de la ficha
- **Propósito**: representación legible, paginada e imprimible de la ficha.
- **Datos que muestra**: título, código, identificación y datos personales; estado laboral y vínculo vigente o ingreso programado (tipo de contrato, posición, puesto, unidad); formación académica; experiencia anterior; movilidad; trayectoria; paginación y momento de generación; secciones vacías identificadas.
- **Acciones disponibles** (y quién puede hacerlas): consulta e impresión del archivo descargado por quien lo solicitó.
- **Estados**: completo; nunca parcial.
- **Mockup**: `design/GP-06_Exportar-ficha-PDF.html` (vista previa del PDF).
