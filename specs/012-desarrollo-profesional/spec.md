# Feature Specification: Información de desarrollo profesional (GP-03)

**Feature Branch**: `012-desarrollo-profesional`

**Created**: 2026-10-15

**Status**: Draft

**Input**: User description: "GP-03 · Información de desarrollo profesional. Mantener formación académica, experiencia laboral anterior y disponibilidad para movilidad como información complementaria de la ficha de colaborador (GP-01). La ausencia de estos datos no bloquea la creación de la ficha ni el ingreso. No se calculan grados, porcentajes, equivalencias, brechas ni aptitud para ocupar un puesto. El Administrador de Talento Humano agrega y corrige registros; el Colaborador consulta lo propio con permiso de ficha. Formación: varios registros con nivel, título o programa, institución y estado (En curso, Completada, Incompleta), y opcionales fechas de inicio y finalización y observaciones. Experiencia anterior: varios registros con organización, cargo y fecha de inicio, y opcionales fecha de finalización, funciones y observaciones. Movilidad: un valor actual (Disponible, No disponible, No informada; inicial No informada) con observaciones opcionales y fecha de última actualización. No existe acción de eliminar registros guardados. La trayectoria laboral interna no se vuelve a capturar como experiencia anterior."

## Clarifications

### Session 2026-10-15

- Q: ¿Debe esta funcionalidad suministrar al catálogo formativo el nivel de cada colaborador por competencia, o solo guarda formación, experiencia y movilidad? → A: Solo guarda información declarativa; no implementa la interfaz de niveles por competencia del catálogo formativo, que conserva su adaptador sin datos hasta que exista una evaluación de competencias.
- Q: ¿Dónde debe vivir el código de la información de desarrollo profesional: dentro de Gestión de Personal junto a las fichas, o dentro del módulo Desarrollo que ya existe para el catálogo formativo? → A: Dentro de Gestión de Personal, como una funcionalidad más junto a fichas e ingresos; la sección se llama «Desarrollo» en la ficha y el módulo Desarrollo conserva solo el catálogo formativo.
- Q: ¿Se rechazan las fechas futuras en formación y experiencia? → A: La fecha de inicio no puede ser futura en formación ni en experiencia, y la de finalización no puede ser futura en la experiencia; solo la formación admite una finalización futura (estudio en curso). «Hoy» es el de la zona horaria del tenant, igual que en GP-01.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Registrar y corregir la formación académica (Priority: P1)

El Administrador de Talento Humano abre la sección Desarrollo de una ficha, agrega un registro de formación con nivel, título o programa, institución y estado, y lo guarda. Más adelante puede corregir ese registro. Cada registro se valida completo al guardarse: un error rechaza solo esa fila y no altera las demás.

**Why this priority**: Es el grupo de información más usado para conocer el perfil académico de una persona y el que más se corrige; sin él la sección no tiene contenido.

**Independent Test**: Con una ficha sin desarrollo, agregar una formación con los cuatro campos obligatorios y sin fechas y comprobar que se guarda; intentar otra con finalización anterior al inicio y comprobar que se rechaza sin tocar la primera; corregir la primera y comprobar que solo cambia esa fila.

**Acceptance Scenarios**:

1. **Given** una ficha sin desarrollo registrado, **When** el Administrador abre Desarrollo, **Then** ve «Formación académica no registrada», «Experiencia anterior no registrada» y la movilidad «No informada», sin ningún cálculo de deficiencias, brechas ni porcentajes. (GP03-CA01)
2. **Given** el formulario de formación, **When** el Administrador completa nivel, título o programa, institución y estado y no escribe fechas, **Then** puede guardar el registro y aparece en la lista de formación. (GP03-CA02)
3. **Given** el formulario de formación, **When** falta o está vacío (tras recortar espacios) el nivel, el título o programa, la institución o el estado, **Then** se muestra el error junto a cada campo y no se guarda nada.
4. **Given** una formación con fecha de inicio y de finalización, **When** la finalización es anterior al inicio, **Then** se rechaza esa fila con un error en la fecha y no se alteran los demás registros. (GP03-CA03)
5. **Given** una formación con inicio y finalización iguales, **When** se guarda, **Then** se acepta.
6. **Given** un registro con estado Completada y sin fecha de finalización (o En curso con finalización), **When** se guarda, **Then** se acepta tal cual: el estado no deduce ni exige fechas.
7. **Given** un registro guardado, **When** el Administrador lo edita y guarda, **Then** esa fila se actualiza y las demás permanecen igual.
8. **Given** textos que superan su longitud máxima (nivel 100; título e institución 200; observaciones 2000), **When** se guarda, **Then** se muestra el error con el máximo y no se guarda nada.
9. **Given** una formación con título, **When** otro registro repite exactamente los mismos datos, **Then** se acepta (no hay unicidad entre registros de formación).
10. **Given** un fallo al guardar, **When** el sistema no pudo registrar la fila, **Then** no muestra éxito, conserva lo escrito y la lista conserva su versión previa.
11. **Given** el listado de formación, **When** se consulta, **Then** no existe ninguna acción para eliminar un registro guardado.
12. **Given** una persona con rol Colaborador, **When** intenta agregar o editar una formación (por la interfaz o directamente), **Then** se le deniega.

---

### User Story 2 - Registrar y corregir la experiencia laboral anterior (Priority: P1)

El Administrador agrega registros de experiencia previa a la organización (organización, cargo y fecha de inicio, y opcionalmente fecha de finalización, funciones y observaciones) y los corrige. La experiencia se muestra de la más reciente a la más antigua.

**Why this priority**: Completa el perfil profesional previo de la persona; es independiente de la formación y se prueba por separado.

**Independent Test**: Agregar dos experiencias, una sin fecha de finalización y otra superpuesta con ella; comprobar que ambas se guardan, que se ordenan por fecha de inicio descendente y que la que no tiene finalización muestra la fecha como no registrada sin declararla vigente.

**Acceptance Scenarios**:

1. **Given** el formulario de experiencia, **When** el Administrador completa organización, cargo y fecha de inicio, **Then** puede guardar el registro aunque no haya finalización, funciones ni observaciones.
2. **Given** el formulario, **When** falta organización, cargo o fecha de inicio, **Then** se muestra el error junto a cada campo y no se guarda nada.
3. **Given** una experiencia sin fecha de finalización, **When** se consulta, **Then** la fecha final se muestra como «No registrado» y no se indica que la relación siga activa ni se etiqueta como empleo actual. (GP03-CA04)
4. **Given** una experiencia con finalización anterior al inicio, **When** se guarda, **Then** se rechaza esa fila y no se alteran las demás. (GP03-CA03)
5. **Given** varias experiencias con periodos que se superponen, **When** se guardan, **Then** todas se aceptan.
6. **Given** varias experiencias, **When** se listan, **Then** se ordenan por fecha de inicio descendente y, ante inicios iguales, siempre en el mismo orden (por antigüedad del registro).
7. **Given** funciones u observaciones de hasta 2000 caracteres, **When** se guardan, **Then** se aceptan; con más de 2000 se rechaza con el máximo indicado.
8. **Given** una ficha con vínculos laborales dentro de la organización, **When** se consulta Desarrollo, **Then** esos hechos no se muestran como experiencia anterior ni se piden de nuevo: la trayectoria interna se consulta en su propia sección.
9. **Given** un registro de experiencia guardado, **When** el Administrador lo corrige, **Then** esa fila cambia y no existe acción para eliminarla.
10. **Given** una persona con rol Colaborador, **When** intenta agregar o editar experiencia, **Then** se le deniega.

---

### User Story 3 - Registrar la disponibilidad para movilidad (Priority: P2)

El Administrador registra si la persona está disponible para movilidad (Disponible, No disponible o No informada) con observaciones opcionales. La movilidad es un único valor actual que se guarda como un bloque independiente de las listas, y conserva la fecha de su última actualización.

**Why this priority**: Es un dato único y simple, valioso para futuras decisiones de personal, pero no bloquea nada y es independiente de las listas.

**Independent Test**: Con movilidad «No informada», registrar «Disponible» con observaciones y comprobar que se guarda con su fecha de actualización y que no se crea ningún movimiento, vínculo ni asignación.

**Acceptance Scenarios**:

1. **Given** una ficha nueva, **When** se consulta la movilidad, **Then** muestra «No informada», sin fecha de actualización registrada.
2. **Given** movilidad «No informada», **When** el Administrador registra «Disponible», **Then** se guarda el estado con la fecha de actualización y no se produce ningún traslado, movimiento ni cambio de asignación. (GP03-CA05)
3. **Given** movilidad guardada, **When** el Administrador cambia el estado u observaciones, **Then** la fecha de última actualización se renueva; si guarda sin cambios reales, no se modifica.
4. **Given** movilidad «No informada», **When** se muestra, **Then** no se interpreta como «No disponible» en ninguna parte de la interfaz.
5. **Given** observaciones de más de 2000 caracteres, **When** se guarda, **Then** se rechaza con el máximo indicado y el bloque conserva su versión previa.
6. **Given** un fallo al guardar el bloque de movilidad, **When** ocurre, **Then** las listas de formación y experiencia no se ven afectadas.
7. **Given** una persona con rol Colaborador, **When** intenta modificar la movilidad, **Then** se le deniega.
8. **Given** una ficha con dirección o experiencia que sugiere cercanía o movilidad, **When** se consulta, **Then** el sistema no infiere ni cambia el estado de movilidad.

---

### User Story 4 - Consulta del Colaborador y vista de solo lectura (Priority: P2)

El Colaborador con cuenta asociada a su ficha y permiso de consulta de ficha abre Desarrollo y ve su formación, su experiencia y su movilidad, sin controles de edición y sin acceso a la información de otras personas. El Administrador ve las mismas secciones con los controles de edición.

**Why this priority**: Da transparencia a la persona sobre lo que la organización conserva; la privacidad respecto de otras fichas es obligatoria.

**Independent Test**: Con dos colaboradores asociados a sus fichas, iniciar sesión como uno, consultar su Desarrollo y luego pedir el de la otra ficha alterando el identificador; comprobar que lo primero muestra los datos sin controles de edición y lo segundo se deniega sin revelar si existe.

**Acceptance Scenarios**:

1. **Given** un Colaborador con permiso de ficha, **When** abre Desarrollo, **Then** ve su formación, su experiencia y su movilidad, sin botones de agregar ni editar. (GP03-CA06)
2. **Given** ese Colaborador, **When** intenta modificar un registro propio o de otra persona (por la interfaz o directamente), **Then** se le deniega. (GP03-CA06)
3. **Given** ese Colaborador, **When** pide el Desarrollo de otra ficha alterando el identificador, **Then** se le deniega sin revelar si esa ficha existe.
4. **Given** un Colaborador sin permiso de consulta de ficha o sin ficha asociada, **When** abre Mi ficha, **Then** la sección Desarrollo no se muestra y se aplican los mensajes de GP-01.
5. **Given** la ficha del Administrador, **When** abre el detalle, **Then** la sección Desarrollo aparece junto a Personal y puesto, y las acciones Agregar y Editar solo se muestran al Administrador.
6. **Given** fichas de otro tenant, **When** el Administrador pide su Desarrollo, **Then** la respuesta es la misma que para una ficha inexistente.
7. **Given** que la información de desarrollo no puede cargarse, **When** se abre la sección, **Then** se muestra un error con Reintentar y el resto de la ficha sigue visible.

---

### Edge Cases

- Una colección vacía de formación o de experiencia es válida: el mensaje de vacío lo explica y no es un error.
- Dos administradores editan a la vez el mismo registro: el segundo guardado sobre una versión desactualizada se rechaza y pide actualizar; no sobrescribe en silencio.
- Dos administradores agregan registros a la vez a la misma ficha: ambos se guardan; no hay unicidad.
- Se guarda movilidad al mismo tiempo que se edita una formación: son bloques independientes y no interfieren.
- Texto de solo espacios en un campo obligatorio se trata como vacío; los espacios exteriores se recortan antes de medir la longitud.
- Las fechas son de calendario, sin hora; una fecha de inicio futura se rechaza en formación y experiencia, y una finalización futura se rechaza en la experiencia pero se acepta en la formación, porque un estudio «En curso» puede tener una finalización esperada.
- Una formación «Completada» sin fechas es válida; una «En curso» con fecha de finalización también.
- El número de registros por ficha no tiene tope funcional, pero la pantalla mantiene su rendimiento con decenas de registros.
- Una experiencia con inicio posterior a la fecha de ingreso en la organización se acepta (el sistema no valida contra la trayectoria interna).
- Editar un registro sin cambios reales no modifica su última modificación.
- La ficha no existe, es de otro tenant o el registro pertenece a otra ficha: la respuesta es la misma que para algo inexistente.
- Sin sesión iniciada no se accede a ninguna información de desarrollo (feature 000-login).

## Requirements *(mandatory)*

### Functional Requirements

**Autorización y aislamiento**

- **FR-001**: Toda consulta y modificación de información de desarrollo MUST limitarse al tenant de la sesión; cambiar identificadores de ficha o de registro en la petición MUST NOT dar acceso a datos de otro tenant ni de otra ficha, y la respuesta MUST NOT revelar si el registro existe (RC-01).
- **FR-002**: Solo el Administrador MUST poder agregar y corregir registros de formación, de experiencia y la movilidad; el Colaborador MUST NOT modificar nada, ni siquiera lo propio. La comprobación MUST hacerse en el servidor en cada operación (RC-03, RC-05).
- **FR-003**: El Colaborador MUST poder consultar únicamente su propio desarrollo, y solo si su cuenta tiene una asociación válida con su ficha y el permiso de consulta de ficha; MUST NOT acceder al desarrollo de otras personas (RC-04). El permiso de consulta de ficha MUST bastar para esta sección; no exige el permiso documental ni el de exportación.

**Formación académica**

- **FR-004**: Cada registro de formación MUST tener nivel (hasta 100 caracteres), título o programa (hasta 200), institución (hasta 200) y estado (En curso, Completada o Incompleta), todos obligatorios, y MAY tener fecha de inicio, fecha de finalización y observaciones (hasta 2000).
- **FR-005**: Los textos obligatorios MUST medirse tras recortar espacios exteriores; un texto de solo espacios MUST tratarse como vacío (RC-09).
- **FR-006**: Una ficha MAY tener cero o más registros de formación; no se exige unicidad entre ellos y la colección vacía es válida.

**Experiencia anterior**

- **FR-007**: Cada registro de experiencia MUST tener organización (hasta 200), cargo (hasta 200) y fecha de inicio, obligatorios, y MAY tener fecha de finalización, funciones (hasta 2000) y observaciones (hasta 2000).
- **FR-008**: Una experiencia sin fecha de finalización MUST mostrarse con la fecha final como «No registrado» y MUST NOT etiquetarse como empleo actual ni declararse vigente. Se MUST permitir experiencias superpuestas.
- **FR-009**: La experiencia MUST ordenarse por fecha de inicio descendente, con un criterio estable ante empates (siempre el mismo orden entre consultas).

**Movilidad**

- **FR-010**: Cada ficha MUST tener un único valor actual de movilidad: Disponible, No disponible o No informada, con observaciones opcionales (hasta 2000). El valor inicial MUST ser No informada, sin fecha de actualización.
- **FR-011**: El sistema MUST conservar la fecha de última actualización de la movilidad y renovarla solo cuando cambian el estado o las observaciones. Guardar sin cambios reales MUST NOT modificarla.
- **FR-012**: La movilidad MUST guardarse como un bloque independiente de las listas: un fallo en una MUST NOT afectar a la otra.
- **FR-013**: «Disponible» MUST NOT autorizar, crear ni programar movimientos, traslados, vínculos ni asignaciones; «No informada» MUST NOT tratarse como «No disponible»; el sistema MUST NOT inferir la movilidad de otros datos (RN05).

**Validación y guardado**

- **FR-014**: Si se proporcionan fecha de inicio y de finalización, la finalización MUST ser igual o posterior al inicio. La fecha de inicio MUST NOT ser futura en formación ni en experiencia, y la de finalización MUST NOT ser futura en la experiencia (en la formación sí puede serlo); «hoy» es el de la zona horaria del tenant. El sistema MUST NOT deducir fechas a partir del estado académico ni exigirlas por él (GP03-RN02).
- **FR-015**: Cada registro MUST validarse completo al guardarse, también en el servidor; una fila inválida MUST rechazarse sin crear ni modificar nada y MUST NOT alterar las demás filas ni el otro bloque (RC-09, GP03-RN01).
- **FR-016**: Un error de guardado MUST NOT presentarse como éxito, MUST conservar lo escrito en el formulario y MUST dejar el registro o bloque en su versión previa.
- **FR-017**: Al editar, si el registro o el bloque cambió desde que se abrió el formulario, el sistema MUST rechazar el guardado y pedir actualizar la información, sin sobrescribir el cambio reciente (RC-10).
- **FR-018**: El sistema MUST registrar quién creó y quién modificó por última vez cada registro y cada bloque de movilidad, y cuándo; guardar sin cambios reales MUST NOT actualizar la última modificación.
- **FR-019**: El sistema MUST NOT ofrecer eliminar ni desactivar registros guardados de ninguno de los tres grupos; los errores se corrigen editando (GP03-RN06).

**Alcance e integración**

- **FR-020**: Registrar formación o experiencia MUST NOT crear vínculos, asignaciones, documentos, cuentas ni movimientos; los comprobantes se gestionan en GP-05 (GP03-RN04).
- **FR-021**: La ausencia de información de desarrollo MUST NOT impedir crear una ficha, registrar un ingreso ni ninguna otra operación de Gestión de Personal.
- **FR-022**: El sistema MUST NOT calcular ni mostrar grados, porcentajes, equivalencias, brechas ni aptitud para ocupar un puesto a partir de esta información.
- **FR-023**: La trayectoria laboral interna MUST consultarse a partir de sus hechos laborales y MUST NOT repetirse ni capturarse como experiencia anterior (GP03-RN07).

**Visualización**

- **FR-024**: La sección Desarrollo MUST mostrar, en este orden, la lista de formación académica, la lista de experiencia anterior y el bloque de movilidad (estado, observaciones y última actualización). Sin datos MUST mostrar «Formación académica no registrada» y «Experiencia anterior no registrada», y la movilidad «No informada».
- **FR-025**: Un dato opcional ausente MUST mostrarse como «No registrado» y MUST NOT convertirse en cero, en una respuesta negativa ni en evidencia de incumplimiento (RC-08).
- **FR-026**: Los controles Agregar y Editar MUST mostrarse solo al Administrador; el Colaborador MUST ver las mismas secciones sin controles de edición.
- **FR-027**: La sección Desarrollo MUST aparecer en el detalle de la ficha (GP-01) y en Mi ficha solo cuando la persona esté autorizada a verla, sin alterar las demás secciones; si no puede cargarse, MUST mostrar un error con Reintentar sin ocultar el resto de la ficha.

**Experiencia y accesibilidad**

- **FR-028**: Toda la interfaz MUST estar en español y cumplir WCAG 2.2 AA entre 320 y 1440 px de ancho, sin desplazamiento horizontal de la página; las listas y formularios MUST cubrir los estados cargando, vacío, error con reintento y éxito.

**Fuera de alcance**: suministrar al catálogo formativo el nivel del colaborador por competencia (la interfaz de niveles del módulo de desarrollo sigue sin datos); eliminar o desactivar registros; adjuntar comprobantes o documentos (GP-05); calcular grados, equivalencias, brechas o idoneidad; movimientos o traslados derivados de la movilidad; niveles por competencia del colaborador; catálogos de niveles, títulos o instituciones; importación masiva; exportación (GP-06); historial campo a campo de los cambios.

### Key Entities *(include if feature involves data)*

- **Registro de formación**: estudio de una persona con nivel, título o programa, institución, estado (En curso, Completada, Incompleta) y, opcionalmente, fechas de inicio y finalización y observaciones. Pertenece a una sola ficha; una ficha puede tener varios.
- **Registro de experiencia anterior**: empleo previo a la organización con organización, cargo, fecha de inicio y, opcionalmente, fecha de finalización, funciones y observaciones. Pertenece a una sola ficha; una ficha puede tener varios; los periodos pueden superponerse.
- **Movilidad**: valor actual único por ficha (Disponible, No disponible o No informada), con observaciones opcionales y fecha de última actualización. Inicial: No informada.
- **Ficha de colaborador** *(externa, GP-01)*: persona dueña de la información; define el tenant y el acceso propio.
- **Cuenta de usuario** *(externa)*: asociada a una ficha con permiso de consulta de ficha para ver lo propio.
- **Trayectoria interna** *(externa, GP-02)*: hechos laborales dentro de la organización; no se captura aquí.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El Administrador agrega un registro de formación o de experiencia en menos de 2 minutos y actualiza la movilidad en menos de 1 minuto.
- **SC-002**: El 100 % de las filas inválidas (campos obligatorios vacíos o finalización anterior al inicio) se rechaza sin alterar ninguna otra fila ni el otro bloque.
- **SC-003**: El 100 % de los datos opcionales ausentes se muestra como «No registrado»; el 100 % de las experiencias sin finalización se muestra sin declararse vigente.
- **SC-004**: El 100 % de los intentos del Colaborador de modificar información de desarrollo, o de consultar la de otra persona, se deniega sin exponer datos ni confirmar su existencia; el 100 % de las fichas de otro tenant se rechaza igual que una inexistente.
- **SC-005**: El 100 % de las ediciones sobre una versión desactualizada se rechaza sin sobrescribir el cambio más reciente.
- **SC-006**: Registrar o corregir información de desarrollo no crea vínculos, asignaciones, documentos ni cuentas en ninguna prueba; ninguna acción de eliminación está disponible para registros guardados.
- **SC-007**: El 100 % de las fichas sin desarrollo se muestra con listas vacías explicadas y movilidad «No informada», sin ningún indicador de deficiencia, brecha o porcentaje.
- **SC-008**: La experiencia se muestra en el mismo orden (inicio descendente y estable ante empates) en el 100 % de las consultas repetidas.

## Assumptions

- La información de desarrollo pertenece a Gestión de Personal (misma ficha, mismo tenant y mismos permisos que GP-01 y GP-02); el módulo Desarrollo del catálogo formativo no se modifica ni la conoce.
- Los roles son los de GP-01: Administrador de Talento Humano = Administrador y Colaborador = Usuario; no se crean roles ni permisos nuevos. La consulta propia usa el permiso de consulta de ficha ya existente.
- GP-01 reservó la sección «Desarrollo» del detalle y de Mi ficha: esta funcionalidad la instala, y GP-01 pasa a mostrarla (capacidad «desarrollo» disponible) para quienes estén autorizados, sin alterar sus otras secciones.
- «Desarrollo» en la ficha es una sección de información personal; no es el catálogo formativo (cursos y áreas formativas) del módulo de desarrollo existente, y esta funcionalidad no proporciona al catálogo niveles por competencia del colaborador ni implementa su interfaz de niveles: ese dato nacerá de una evaluación de competencias futura.
- Las fechas son de calendario, sin hora, y se validan contra el «hoy» del tenant: inicio nunca futuro, finalización futura solo en formación; además, la finalización no puede ser anterior al inicio.
- La lista de formación se presenta de la más reciente a la más antigua por fecha de inicio, con los registros sin fecha al final y un orden estable; la spec solo exige estabilidad.
- El control de edición concurrente se comporta como en GP-01: el segundo guardado sobre una versión desactualizada se rechaza; aplica por registro y por el bloque de movilidad.
- Se registran creador, último modificador y sus fechas, como en las features anteriores; no se exige historial campo a campo.
- El número de registros por ficha no tiene tope funcional; no se pagina la sección.
- Los textos no se normalizan ni se comparan entre registros: no hay detección de duplicados.
- El almacenamiento sigue la constitución vigente (SQLite) y el aislamiento por tenant de GP-01.

## Pantallas *(si la feature tiene UI)*

### Desarrollo (sección del detalle de ficha y de Mi ficha)
- **Propósito**: consultar la formación académica, la experiencia anterior y la movilidad de una persona; el Administrador además las mantiene.
- **Datos que muestra**: lista de formación (nivel, título o programa, institución, estado, fechas y observaciones cuando existen); lista de experiencia ordenada por inicio descendente (organización, cargo, fechas con «No registrado» si falta la final, funciones y observaciones); bloque de movilidad (estado, observaciones, última actualización).
- **Acciones disponibles** (y quién puede hacerlas): Agregar formación, Agregar experiencia, Editar un registro y Editar movilidad (solo Administrador); el Colaborador solo consulta. No hay eliminar.
- **Estados**: cargando, vacío por grupo («Formación académica no registrada», «Experiencia anterior no registrada»), movilidad «No informada», error con Reintentar, éxito tras guardar y sin permisos.
- **Mockup**: pestaña/sección «Desarrollo» del tablero de detalle (`design/ColaboradorFicha.dc.html`) con el prototipo `design/GP-03_Desarrollo-profesional.html` como referencia; las listas reutilizan Pares etiqueta-valor y el patrón de lista de `DESIGN.md`.

### Formulario de formación, de experiencia y de movilidad
- **Propósito**: agregar o corregir un registro, o el bloque de movilidad.
- **Datos que muestra**: formación: nivel, título o programa, institución, estado, fechas, observaciones; experiencia: organización, cargo, fecha de inicio, fecha de finalización, funciones, observaciones; movilidad: estado y observaciones; contadores `n/máximo` en los textos con límite.
- **Acciones disponibles** (y quién puede hacerlas): Guardar y Cancelar (solo Administrador).
- **Estados**: errores por campo con alerta general, guardando (botón bloqueado), conflicto de edición con «Actualizar datos», fallo al guardar con datos conservados, éxito (actualiza la fila o el bloque) y sin permisos.
- **Mockup**: patrón Formulario (`design/Formulario.dc.html`, `design/DESIGN.md` §5) presentado como diálogo o panel dentro de la sección; las diferencias con el prototipo `GP-03_Desarrollo-profesional.html` se registran en `DESIGN.md` §9.
