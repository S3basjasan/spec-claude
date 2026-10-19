# Feature Specification: Reportes de evaluación de resultados

**Feature Branch**: `018-reportes-evaluacion`

**Created**: 2026-10-17

**Status**: Draft

**Input**: User description: "Reportes de evaluación de resultados (documento fuente `specify-reportes.md`). Evalúa los resultados del módulo de Desarrollo y Formación en dos niveles: validación (cumplimiento del plan, horas, satisfacción) y evaluación de impacto (brechas cerradas, aplicación de lo aprendido a 30-60-90 días y retorno de la inversión). El administrador (Gestor de Talento) accede a un dashboard con indicadores comparados contra el período anterior, gráficos, tabla detallada, seguimiento 30-60-90, evaluación de impacto por acción y exportación a Excel y PDF; el colaborador accede a su vista «Mi progreso» y responde encuestas de satisfacción y seguimientos. El módulo consolida datos de Estructura Empresarial, Gestión de Personal, DNF, Plan de Formación, Micro-Learning y Actividades Formativas a través de sus Facades, y solo captura por sí mismo la encuesta de satisfacción, el seguimiento 30-60-90 y la evaluación de impacto. No expone datos a otros módulos."

## Clarifications

### Session 2026-10-17

- Q: ¿Hasta cuándo puede un colaborador responder la encuesta de satisfacción de una actividad Finalizada? → A: Durante 30 días corridos desde la fecha de fin de la actividad; pasado ese plazo queda sin respuesta.
- Q: Con el filtro por departamento, ¿cómo se tratan el ROI y las evaluaciones de impacto? → A: Costo y beneficio estimado se prorratean con la misma proporción de asistentes del departamento; el porcentaje de ROI no cambia, pero sí los montos.
- Q: ¿Se puede registrar o editar la evaluación de impacto de una acción cuyo plan está Cerrado? → A: Sí; se permite siempre que la acción esté Finalizada, sin importar el estado del plan.
- Q: ¿Se ocultan los comentarios de satisfacción cuando hay pocas respuestas? → A: No; se muestran siempre, sin el nombre del autor, sin importar cuántas respuestas haya.
- Q: ¿Qué cuenta como brecha «detectada» en el indicador Brechas cerradas? → A: Solo las brechas cuya primera aparición «Con brecha» (por colaborador y competencia) cae en el período. Las cerradas se cuentan por la fecha de cierre, así que pueden superar a las detectadas y se muestran como dos cifras separadas.
- Q: ¿Desde cuándo se ofrecen encuestas y seguimientos a actividades ya terminadas? → A: No hay fecha de activación; se ofrecen para toda actividad Finalizada mientras su ventana (30 días la encuesta, 15 días cada hito) siga abierta.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar el dashboard de indicadores (Priority: P1)

El administrador abre el dashboard y ve, para el período elegido (por defecto el año actual), las tarjetas % Plan cumplido, Total horas, Satisfacción promedio, Brechas cerradas y ROI estimado, cada una comparada contra el período anterior equivalente (diferencia y variación porcentual). Debajo ve la fila de indicadores de gestión (inversión por colaborador formado, horas por colaborador formado, costo por hora) y los gráficos de horas por departamento, evolución de brechas y distribución por tipo de formación. Puede filtrar por rango de fechas, departamento, tipo de formación y plan.

**Why this priority**: Es el objetivo central de la feature: que el Gestor de Talento vea si la formación cumple lo planificado y con qué resultados.

**Independent Test**: Con un plan Ejecutando que tenga acciones Finalizadas, actividades con asistencia y encuestas, abrir el dashboard y verificar que cada indicador coincide con el cálculo manual y que la comparación contra el período anterior es correcta.

**Acceptance Scenarios**:

1. **Given** que el administrador abre el dashboard sin filtros, **When** carga, **Then** el período es el año actual y se muestran las cinco tarjetas, la fila de gestión y los tres gráficos.
2. **Given** un período con datos y un período anterior equivalente (mismo número de días inmediatamente previos) con datos, **When** se muestra una tarjeta, **Then** incluye el valor, la diferencia y la variación porcentual frente al período anterior.
3. **Given** que el período anterior no tiene datos, **When** se muestra una tarjeta, **Then** la comparación aparece sin valor con una nota explicativa.
4. **Given** acciones de planes Ejecutando o Cerrado, **When** se calcula % Plan cumplido, **Then** es acciones Finalizadas / acciones no canceladas × 100, y las acciones de planes en otro estado no cuentan.
5. **Given** actividades con asistencia Presente y micro-contenidos completados, **When** se calcula Total horas, **Then** suma las horas-persona de actividades (duración de la acción × participantes Presentes) y las horas de micro-contenidos completados, y se muestra el desglose entre ambas.
6. **Given** un indicador sin datos en el período, **When** se muestra, **Then** aparece un guion y una nota explicativa, sin error.
7. **Given** acciones Finalizadas con costo, **When** se calculan los indicadores de gestión, **Then** inversión por colaborador formado = costo de acciones Finalizadas / colaboradores distintos con asistencia Presente; horas por colaborador formado = horas-persona de actividades / esos mismos colaboradores; costo por hora = costo de acciones Finalizadas / horas-persona de actividades.
8. **Given** el gráfico de evolución de brechas, **When** se muestra, **Then** presenta por mes las brechas activas al cierre del mes (usando, para cada colaborador, su DNF más reciente a esa fecha) y las brechas cerradas en el mes.

---

### User Story 2 - Filtrar y analizar la tabla detallada (Priority: P1)

El administrador consulta una tabla con una fila por actividad En curso o Finalizada del período, con código, nombre, tipo, fechas, departamento destinatario, asistentes sobre inscritos, horas-persona, costo, calificación promedio y satisfacción promedio. Aplica los mismos filtros del dashboard y, desde cada fila, abre la lista de comentarios de satisfacción de la actividad.

**Why this priority**: Permite auditar de dónde salen los indicadores y llegar al detalle de cada actividad.

**Independent Test**: Filtrar por un departamento y verificar que las filas muestran solo los participantes de ese departamento y el costo prorrateado; abrir los comentarios de una actividad y comprobar que no aparece el nombre del autor.

**Acceptance Scenarios**:

1. **Given** el período elegido, **When** se abre la tabla, **Then** lista las actividades En curso o Finalizadas cuya fecha de fin cae en el período, con paginación.
2. **Given** un filtro por departamento, **When** se aplica, **Then** asistentes, horas, calificación y satisfacción se recalculan solo con los participantes de ese departamento actual (según Gestión de Personal) y el costo se prorratea por la proporción de asistentes de ese departamento.
3. **Given** un filtro por tipo de formación, **When** se elige uno de los seis tipos de acción, **Then** la tabla muestra solo actividades de ese tipo.
4. **Given** que se pulsa una fila, **When** se abre la lista de comentarios de satisfacción, **Then** se ven los comentarios y la calificación sin el nombre del autor.
5. **Given** una actividad sin encuestas respondidas, **When** se muestra su fila, **Then** la satisfacción promedio aparece sin valor.

---

### User Story 3 - Responder la encuesta de satisfacción (Priority: P1)

El colaborador que asistió (asistencia Presente) a una actividad Finalizada ve la encuesta en su sección «Pendientes por responder», asigna una calificación general de 1 a 5 y, opcionalmente, un comentario. Se puede responder durante 30 días corridos desde la fecha de fin de la actividad, una sola vez por actividad, y no se modifica.

**Why this priority**: Sin encuestas no existe el indicador de satisfacción; es uno de los tres datos que solo esta feature captura.

**Independent Test**: Con un colaborador Presente en una actividad Finalizada, enviar una encuesta con calificación 4 y verificar que desaparece de pendientes, que no se puede volver a responder y que el promedio de la actividad la incluye.

**Acceptance Scenarios**:

1. **Given** un colaborador con asistencia Presente en una actividad Finalizada, **When** abre «Mi progreso», **Then** la encuesta aparece en «Pendientes por responder».
2. **Given** la encuesta abierta, **When** envía una calificación entera de 1 a 5 con o sin comentario, **Then** queda registrada con su fecha y la encuesta sale de pendientes.
3. **Given** una calificación fuera de 1 a 5 o vacía, **When** intenta enviar, **Then** el sistema lo impide indicando que es obligatoria y debe estar entre 1 y 5.
4. **Given** una encuesta ya respondida, **When** intenta responderla o modificarla de nuevo, **Then** el sistema lo rechaza.
5. **Given** un colaborador con asistencia Ausente, Justificado o Pendiente, o una actividad no Finalizada, **When** intenta responder la encuesta, **Then** el sistema lo rechaza.
6. **Given** una actividad cuya fecha de fin fue hace más de 30 días y una encuesta sin responder, **When** el colaborador abre «Mi progreso» o intenta responderla, **Then** la encuesta ya no aparece en pendientes y el sistema rechaza la respuesta.

---

### User Story 4 - Vista «Mi progreso» del colaborador (Priority: P1)

El colaborador consulta un resumen de su propio progreso: formaciones completadas, competencias adquiridas, horas acumuladas y certificados, junto con el gráfico de evolución de competencias, la lista de certificados descargables en PDF, sus próximas acciones del plan de desarrollo y los pendientes por responder.

**Why this priority**: Es la cara de la feature para el colaborador y concentra su motivación y seguimiento.

**Independent Test**: Con un colaborador con actividades Finalizadas, micro-contenidos completados y dos DNF, verificar tarjetas, evolución de competencias, descarga de un certificado y que no ve datos de otros colaboradores.

**Acceptance Scenarios**:

1. **Given** un colaborador con datos, **When** abre «Mi progreso», **Then** ve formaciones completadas (actividades Finalizadas con asistencia Presente más micro-contenidos completados), competencias adquiridas (brechas cerradas propias), horas acumuladas (actividades con asistencia Presente más micro-contenidos completados) y certificados.
2. **Given** una competencia evaluada en al menos dos DNF, **When** se muestra la evolución, **Then** compara el nivel en la primera DNF con el nivel en la más reciente.
3. **Given** una competencia evaluada en una sola DNF, **When** se muestra la evolución, **Then** aparece únicamente el nivel actual.
4. **Given** certificados de micro-contenidos completados, **When** pulsa descargar, **Then** obtiene el certificado en PDF y solo puede descargar los propios.
5. **Given** acciones futuras del colaborador en el plan, **When** se muestra «Mi plan de desarrollo», **Then** lista sus próximas acciones formativas.
6. **Given** un colaborador sin datos, **When** abre la vista, **Then** ve un mensaje informativo y no un error.

---

### User Story 5 - Responder el seguimiento a 30, 60 y 90 días (Priority: P2)

Para cada actividad Finalizada a la que asistió, el colaborador tiene tres hitos de seguimiento, a 30, 60 y 90 días corridos después de la fecha de fin. Cada hito se puede responder desde su fecha de vencimiento y durante 15 días; pasada esa ventana queda sin respuesta. La respuesta incluye dos calificaciones de 1 a 5 (aplicación de lo aprendido en el trabajo y apoyo del entorno) y un comentario opcional, y no se modifica.

**Why this priority**: Aporta la medición del problema de reingreso, base de la evaluación de impacto; depende de que ya existan actividades Finalizadas y asistencia.

**Independent Test**: Con una actividad que terminó hace 30 días, verificar que el hito de 30 días aparece en pendientes, responderlo y comprobar que no se puede repetir; simular el paso de 16 días y comprobar que un hito no respondido ya no se ofrece.

**Acceptance Scenarios**:

1. **Given** una actividad Finalizada hace 30 días y un colaborador Presente, **When** abre «Mi progreso», **Then** el seguimiento a 30 días aparece en pendientes.
2. **Given** un hito antes de su fecha de vencimiento, **When** el colaborador intenta responderlo, **Then** no está disponible.
3. **Given** un hito dentro de su ventana de 15 días, **When** envía las dos calificaciones (1 a 5) con o sin comentario, **Then** queda registrado con su fecha.
4. **Given** una calificación fuera de 1 a 5 o faltante, **When** intenta enviar, **Then** el sistema lo impide.
5. **Given** un hito cuya ventana de 15 días pasó sin respuesta, **When** el colaborador intenta responderlo, **Then** el sistema lo rechaza y el hito queda sin respuesta.
6. **Given** un hito ya respondido, **When** intenta modificarlo o repetirlo, **Then** el sistema lo rechaza.

---

### User Story 6 - Revisar el impacto 30-60-90 días (Priority: P2)

El administrador consulta una sección con una fila por actividad Finalizada del período. Para cada hito (30, 60, 90) ve la tasa de respuesta, el promedio de aplicación y el promedio de apoyo. Las actividades con alerta de reingreso llevan un badge de alerta.

**Why this priority**: Convierte las respuestas de seguimiento en una señal accionable para el Gestor de Talento.

**Independent Test**: Con una actividad cuyo promedio de aplicación baja 1 punto o más entre dos hitos, ambos con al menos 3 respuestas, verificar que aparece el badge de alerta.

**Acceptance Scenarios**:

1. **Given** actividades Finalizadas del período, **When** se abre la sección, **Then** hay una fila por actividad con, por hito, tasa de respuesta (respuestas / colaboradores elegibles), promedio de aplicación y promedio de apoyo.
2. **Given** un hito sin respuestas, **When** se muestra, **Then** los promedios aparecen sin valor.
3. **Given** que el promedio de aplicación de un hito cae al menos 1 punto respecto del hito anterior y ambos hitos tienen al menos 3 respuestas, **When** se muestra la fila, **Then** lleva el badge de alerta de reingreso.
4. **Given** una caída de 1 punto o más pero con menos de 3 respuestas en alguno de los dos hitos, **When** se muestra la fila, **Then** no lleva badge de alerta.

---

### User Story 7 - Registrar la evaluación de impacto de una acción (Priority: P2)

El administrador ve la lista de acciones Finalizadas con el indicador «Con evaluación» o «Sin evaluación» (filtrable por ese indicador y por plan). Con «Registrar impacto» o «Editar impacto» abre un formulario donde indica el indicador de negocio, unidad, valor antes, valor después, beneficio estimado en USD y justificación, y ve el ROI calculado de la acción.

**Why this priority**: Es el nivel de evaluación más valioso y alimenta el ROI estimado del dashboard, pero requiere acciones ya Finalizadas.

**Independent Test**: Registrar una evaluación con beneficio 5 000 USD sobre una acción de costo 2 000 USD y verificar ROI = 150 %; editarla y verificar que se actualiza sin crear una segunda.

**Acceptance Scenarios**:

1. **Given** acciones Finalizadas, **When** se abre la lista, **Then** cada una muestra «Con evaluación» o «Sin evaluación» y puede filtrarse por ese indicador y por plan.
2. **Given** una acción Finalizada sin evaluación, **When** el administrador completa todos los campos y guarda, **Then** la evaluación se crea y queda registrado quién la creó y cuándo.
3. **Given** una acción con evaluación, **When** la edita y guarda, **Then** se actualiza (máximo una por acción) y queda registrado quién la modificó por última vez y cuándo.
4. **Given** el formulario, **When** se ingresan beneficio y costo conocido, **Then** el ROI de la acción se muestra como (beneficio − costo) / costo × 100.
5. **Given** una acción con costo cero, **When** se muestra su ROI, **Then** indica que no aplica.
6. **Given** un campo obligatorio vacío, un beneficio negativo o valores no numéricos, **When** intenta guardar, **Then** el sistema lo impide indicando el motivo.
7. **Given** una acción que no está Finalizada, **When** se intenta registrar impacto, **Then** el sistema lo rechaza.
8. **Given** evaluaciones registradas, **When** se calcula el ROI estimado del dashboard, **Then** es (beneficio estimado total − costo total) / costo total × 100 sobre las acciones con evaluación, e indica en cuántas acciones se basa.

---

### User Story 8 - Exportar a Excel y PDF (Priority: P3)

El administrador exporta con los filtros aplicados. El Excel contiene hojas de indicadores, detalle, impacto 30-60-90 y evaluación de impacto; el PDF contiene los indicadores y las tablas, sin gráficos.

**Why this priority**: Facilita compartir resultados, pero depende de que el resto esté disponible.

**Independent Test**: Aplicar un filtro por departamento, exportar ambos formatos y verificar que el contenido coincide con la pantalla y que el Excel tiene las cuatro hojas.

**Acceptance Scenarios**:

1. **Given** filtros aplicados, **When** pulsa «Exportar Excel», **Then** se descarga un archivo con hojas de indicadores, detalle, impacto 30-60-90 y evaluación de impacto, con los datos filtrados.
2. **Given** filtros aplicados, **When** pulsa «Exportar PDF», **Then** se descarga un documento con indicadores y tablas, sin gráficos.
3. **Given** un período sin datos, **When** exporta, **Then** el archivo se genera con los indicadores sin valor y las tablas vacías.
4. **Given** un colaborador, **When** intenta exportar, **Then** el sistema lo rechaza.

---

### Edge Cases

- **Colaborador que cambió de departamento**: el filtro y los gráficos por departamento usan siempre el departamento actual de Gestión de Personal, no el de la fecha de la formación.
- **Filtro «Micro-learning»**: solo tienen datos las horas y la distribución por tipo; los demás indicadores y la tabla aparecen sin valor o vacíos.
- **Brecha cerrada en el límite del período**: se asigna según la fecha de la DNF que la muestra cerrada; un micro-contenido, según su fecha de finalización; una actividad o acción, según su fecha de fin.
- **Competencia que vuelve a brecha**: tras un cierre, una nueva DNF «Con brecha» se cuenta como brecha activa; un cierre posterior se cuenta como otra brecha cerrada.
- **Un solo DNF para un colaborador**: no puede haber brecha cerrada ni evolución; solo se muestra el nivel actual.
- **Costo cero o acciones sin costo**: el ROI de la acción no aplica y no entra en el ROI total; la inversión por colaborador y el costo por hora se muestran sin valor si el denominador es cero.
- **Período anterior sin datos o con valor cero**: la variación porcentual se muestra sin valor si el valor anterior es cero.
- **Acción cancelada o plan en otro estado**: no cuentan en % Plan cumplido; una acción cancelada no genera encuesta ni seguimiento.
- **Actividades terminadas antes de que la feature esté disponible**: no hay fecha de activación; se aplican las mismas ventanas a todas las actividades Finalizadas. Si la ventana de una encuesta (30 días) o de un hito (15 días) ya pasó, queda sin respuesta; si sigue abierta, se ofrece al colaborador.
- **Colaborador que deja la empresa**: sus respuestas históricas se conservan y siguen contando en los promedios y en los indicadores sin filtro de departamento. Al no tener departamento actual (asignación vigente), queda fuera cuando se filtra por un departamento y aparece como «Sin departamento» en el gráfico de horas por departamento.
- **Colaborador sin departamento actual**: sus horas se agrupan en «Sin departamento» en el gráfico de horas por departamento; con el filtro por un departamento concreto no se incluye.
- **Brechas cerradas mayores que las detectadas**: una brecha detectada en un período anterior y cerrada en el actual cuenta como cerrada del actual pero no como detectada del actual; por eso las cerradas pueden superar a las detectadas y ambas cifras se muestran por separado, sin presentarlas como fracción.
- **Acceso cruzado**: un colaborador nunca puede ver, responder ni descargar datos de otro colaborador, ni las vistas de administrador.
- **Exportación con muchos registros**: el archivo debe generarse completo con los filtros aplicados, sin truncar filas.
- **Falla de un módulo origen**: si una fuente (por ejemplo, Micro-Learning) no responde, el dashboard informa qué fuentes no están disponibles y muestra «No disponible» en los indicadores y gráficos que dependen de ellas, en lugar de mostrar valores incorrectos; los demás indicadores se calculan normalmente (FR-043).

## Requirements *(mandatory)*

### Functional Requirements

**Permisos y acceso**

- **FR-001**: El sistema MUST permitir solo al administrador (Gestor de Talento) ver el dashboard, la tabla detallada, el impacto 30-60-90, los comentarios de satisfacción, registrar evaluaciones de impacto y exportar.
- **FR-002**: El sistema MUST permitir solo al colaborador ver su vista «Mi progreso», responder encuestas y seguimientos que le correspondan y descargar sus propios certificados, y MUST impedirle ver datos de otros colaboradores.

**Filtros y período**

- **FR-003**: El sistema MUST ofrecer filtros por rango de fechas, departamento, tipo de formación y plan, aplicables al dashboard, a la tabla y a las exportaciones.
- **FR-004**: El tipo de formación MUST incluir los seis tipos de acción del plan (Presencial, Virtual, E-learning, OJT, Codesarrollo, Mentoring) y además «Micro-learning».
- **FR-005**: El período por defecto MUST ser el año actual.
- **FR-006**: Una acción o actividad MUST pertenecer al período según su fecha de fin; un micro-contenido, según su fecha de finalización por el colaborador; una brecha cerrada, según la fecha de la DNF que la muestra cerrada.
- **FR-007**: El filtro por departamento MUST usar el departamento actual de cada colaborador (Gestión de Personal), recalcular las métricas con solo los participantes de ese departamento y prorratear los costos y el beneficio estimado de las evaluaciones de impacto según la proporción de asistentes de ese departamento (el porcentaje de ROI no cambia, solo los montos). Los colaboradores sin departamento actual no entran cuando se filtra por un departamento y se agrupan como «Sin departamento» en el gráfico de horas por departamento.

**Indicadores del dashboard**

- **FR-008**: El sistema MUST mostrar cada indicador con su valor del período y compararlo contra el período anterior equivalente (rango de igual duración inmediatamente anterior), con diferencia y variación porcentual; si el período anterior no tiene datos, la comparación se muestra sin valor.
- **FR-009**: El sistema MUST calcular % Plan cumplido como acciones Finalizadas / acciones no canceladas, de planes en estado Ejecutando o Cerrado, por cien.
- **FR-010**: El sistema MUST calcular Total horas como horas-persona de actividades (duración de la acción por cada participante con asistencia Presente) más horas de micro-contenidos completados, y mostrar el desglose entre ambas.
- **FR-011**: El sistema MUST calcular Satisfacción promedio como el promedio de las calificaciones de las encuestas de satisfacción, de 1 a 5.
- **FR-012**: El sistema MUST calcular Brechas cerradas como la cantidad de brechas cerradas en el período junto con la cantidad de detectadas (brechas cuya primera aparición «Con brecha», por colaborador y competencia, cae en el período); las dos cifras se muestran por separado y las cerradas pueden superar a las detectadas; una brecha está cerrada cuando una competencia de un colaborador figuraba «Con brecha» en una DNF y en una DNF posterior figura «Cumple» o «Supera». Este indicador MUST respetar solo los filtros de fechas y departamento.
- **FR-013**: El sistema MUST calcular ROI estimado como (beneficio estimado total − costo total) / costo total × 100 sobre las acciones con evaluación de impacto, indicando en cuántas acciones se basa; sin evaluaciones, se muestra sin valor.
- **FR-014**: El sistema MUST mostrar en una fila secundaria los indicadores de gestión: inversión por colaborador formado, horas por colaborador formado y costo por hora, según las definiciones de la Historia 1.
- **FR-015**: Un indicador sin datos MUST mostrarse con un guion y una nota explicativa.

**Gráficos**

- **FR-016**: El sistema MUST mostrar un gráfico de barras de horas-persona de actividades y de micro-learning por departamento actual del colaborador.
- **FR-017**: El sistema MUST mostrar un gráfico de línea de evolución de brechas: por mes, brechas activas al cierre del mes (con la DNF más reciente de cada colaborador a esa fecha) y brechas cerradas en el mes.
- **FR-018**: El sistema MUST mostrar un gráfico de pastel con las horas por cada uno de los siete tipos de formación, incluido Micro-learning.

**Tabla detallada**

- **FR-019**: El sistema MUST mostrar una fila por actividad En curso o Finalizada del período, con código, nombre, tipo, fechas, departamento destinatario, asistentes sobre inscritos, horas-persona, costo, calificación promedio y satisfacción promedio, con paginación.
- **FR-020**: Las filas MUST respetar los filtros y el prorrateo del FR-007.
- **FR-021**: Desde cada fila el administrador MUST poder abrir los comentarios de satisfacción de la actividad, sin el nombre del autor y sin umbral mínimo de respuestas. Cada comentario muestra la calificación y la fecha (solo el día, sin hora) de la respuesta, y ningún dato que permita identificar al autor.

**Encuesta de satisfacción**

- **FR-022**: El sistema MUST permitir responder la encuesta solo a colaboradores con asistencia Presente en una actividad Finalizada, dentro de los 30 días corridos posteriores a su fecha de fin, una sola vez por actividad, sin posibilidad de modificarla ni eliminarla.
- **FR-023**: La encuesta MUST contener una calificación general entera de 1 a 5 (obligatoria) y un comentario opcional, y MUST registrarse con su fecha.

**Seguimiento 30-60-90 días**

- **FR-024**: El sistema MUST determinar (calcular, sin almacenarlos) para cada colaborador con asistencia Presente en una actividad Finalizada tres hitos de seguimiento a 30, 60 y 90 días corridos después de la fecha de fin de la actividad.
- **FR-025**: Cada hito MUST poder responderse desde su fecha de vencimiento y durante 15 días; pasada esa ventana queda sin respuesta y no se ofrece.
- **FR-026**: Cada respuesta MUST contener dos calificaciones enteras de 1 a 5 (aplicación de lo aprendido en el trabajo y apoyo del entorno) y un comentario opcional, y MUST registrarse con su fecha sin poder modificarse ni eliminarse.
- **FR-027**: El sistema MUST calcular por actividad y hito la tasa de respuesta (respuestas sobre colaboradores elegibles), el promedio de aplicación y el promedio de apoyo.
- **FR-028**: El sistema MUST marcar una alerta de reingreso cuando el promedio de aplicación de un hito cae al menos 1 punto respecto del hito anterior, con al menos 3 respuestas en ambos hitos.
- **FR-029**: La sección de impacto 30-60-90 MUST mostrar una fila por actividad Finalizada del período, con badge de alerta en las que tengan alerta de reingreso.

**Evaluación de impacto**

- **FR-030**: El administrador MUST poder registrar la evaluación de impacto de una acción Finalizada con: nombre del indicador de negocio, unidad, valor antes, valor después, beneficio estimado en USD (no negativo) y justificación, todos obligatorios.
- **FR-031**: El sistema MUST permitir como máximo una evaluación por acción y permitir actualizarla, incluso cuando el plan de la acción esté Cerrado.
- **FR-032**: El sistema MUST calcular el ROI de la acción como (beneficio estimado − costo de la acción) / costo de la acción × 100, y mostrar «no aplica» si el costo es cero.
- **FR-033**: El sistema MUST listar las acciones Finalizadas con el indicador «Con evaluación» o «Sin evaluación», filtrable por ese indicador y por plan, con el botón «Registrar impacto» o «Editar impacto».

**Mi progreso**

- **FR-034**: El sistema MUST mostrar al colaborador: formaciones completadas, competencias adquiridas, horas acumuladas, certificados, evolución de competencias, «Mi plan de desarrollo» y pendientes por responder, según las reglas de la Historia 4.
- **FR-035**: Los certificados MUST ser los de los micro-contenidos completados (Micro-Learning), descargables en PDF solo por su titular.
- **FR-036**: «Pendientes por responder» MUST listar las encuestas de satisfacción y los seguimientos disponibles para el colaborador, cada uno con un formulario breve.

**Exportación**

- **FR-037**: El sistema MUST permitir exportar a Excel con hojas de indicadores, detalle, impacto 30-60-90 y evaluación de impacto, respetando los filtros aplicados.
- **FR-038**: El sistema MUST permitir exportar a PDF los indicadores y las tablas, sin gráficos, respetando los filtros aplicados.

**Auditoría e integración**

- **FR-039**: Las encuestas de satisfacción y los seguimientos MUST quedar registrados con su fecha y no poder modificarse ni eliminarse.
- **FR-040**: El sistema MUST registrar quién creó cada evaluación de impacto y cuándo, y quién la modificó por última vez y cuándo. Las exportaciones no se registran.
- **FR-041**: El sistema MUST obtener departamentos y competencias de Estructura Empresarial, colaboradores y departamento actual de Gestión de Personal, niveles y brechas de DNF, planes, acciones, horas, costos y estados de Plan de Formación, completaciones, horas y certificados de Micro-Learning, y actividades, asistencias y calificaciones de Actividades Formativas, siempre a través de sus Facades y sin duplicar esos datos.
- **FR-042**: El sistema MUST NOT exponer datos de esta feature a otros módulos.
- **FR-043**: Si una fuente de datos de otro módulo no responde, el dashboard MUST indicar qué fuentes no están disponibles y mostrar «No disponible» en los indicadores y gráficos que dependen de ellas, sin mostrar valores calculados con datos incompletos y sin impedir el resto del dashboard.

### Key Entities *(include if feature involves data)*

- **Encuesta de satisfacción**: respuesta de un colaborador a una actividad Finalizada; atributos: actividad, colaborador, calificación general (1–5), comentario opcional, fecha. Única por colaborador y actividad; inmutable; se acepta hasta 30 días corridos después de la fecha de fin de la actividad.
- **Seguimiento 30-60-90**: respuesta de un colaborador a un hito de una actividad Finalizada; atributos: actividad, colaborador, hito (30, 60 o 90), calificación de aplicación (1–5), calificación de apoyo (1–5), comentario opcional, fecha. Única por colaborador, actividad y hito; inmutable. El vencimiento y la ventana de 15 días se derivan de la fecha de fin de la actividad.
- **Evaluación de impacto**: evaluación de una acción Finalizada; atributos: acción, indicador de negocio, unidad, valor antes, valor después, beneficio estimado (USD), justificación, creador y fecha de creación, último modificador y fecha de modificación. Una por acción.
- **Indicadores y vistas consolidadas** (no persistidas como datos propios): % Plan cumplido, horas, brechas, ROI, indicadores de gestión, tabla detallada y «Mi progreso», calculados a partir de las fuentes de los demás módulos.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El administrador obtiene el dashboard completo del año con filtros en menos de 5 segundos en una operación normal.
- **SC-002**: El 100 % de los indicadores del dashboard coincide con un cálculo manual sobre un conjunto de datos de verificación, incluida la comparación con el período anterior.
- **SC-003**: El administrador identifica una actividad con alerta de reingreso en menos de 30 segundos desde que abre la sección de impacto 30-60-90.
- **SC-004**: El colaborador responde una encuesta de satisfacción en menos de 1 minuto y un seguimiento en menos de 2 minutos desde «Mi progreso».
- **SC-005**: El administrador registra una evaluación de impacto completa y ve su ROI en menos de 3 minutos.
- **SC-006**: Los archivos de Excel y PDF reflejan exactamente los datos y filtros visibles en pantalla en el 100 % de las pruebas.
- **SC-007**: En el 100 % de las pruebas de acceso, ningún colaborador ve datos de otro colaborador ni las vistas de administrador, y ningún comentario de satisfacción muestra el nombre de su autor.
- **SC-008**: El 100 % de las encuestas y seguimientos enviados quedan registrados con su fecha y ninguno puede modificarse después.
- **SC-009**: Al menos el 90 % de los colaboradores de prueba completa su primera tarea en «Mi progreso» (consultar horas, descargar un certificado o responder un pendiente) sin ayuda.

## Assumptions

- Las features de Estructura Empresarial, Gestión de Personal, DNF, Plan de Formación, Micro-Learning y Actividades Formativas ya están implementadas y exponen la información necesaria mediante sus Facades.
- El administrador es el Gestor de Talento; el rol de colaborador corresponde a cualquier usuario con ficha de colaborador.
- Los costos de las acciones provienen del Plan de Formación en dólares (USD); el beneficio estimado de la evaluación de impacto también se registra en USD.
- Los seguimientos 30-60-90 cuentan días corridos desde la fecha de fin de la actividad. No hay fecha de activación: las encuestas y los hitos se calculan para todas las actividades Finalizadas y se ofrecen mientras su ventana siga abierta.
- Los micro-contenidos no tienen costo en el plan: no entran en los costos ni en los indicadores de gestión (inversión por colaborador formado y costo por hora), solo en las horas.
- Los límites de longitud de los textos (comentarios 1 000 caracteres, justificación 2 000, indicador de negocio 150, unidad 50) son decisiones del sistema, no vienen del documento fuente.
- Las definiciones operativas (% Plan cumplido, brecha cerrada, prorrateo de costos, período anterior equivalente, encuesta de una pregunta, cuestionario de seguimiento de dos preguntas, ventana de 15 días, alerta de reingreso y beneficio estimado manual) son convenciones del sistema, decididas por el grupo, y cambiarlas requiere una decisión explícita.
- Los certificados descargables se limitan a los de micro-contenidos; las actividades formativas no generan certificado en esta feature.
- Las exportaciones no se auditan y no se envían por correo; se descargan directamente.
- El tablero es de consulta: no edita datos de otros módulos ni notifica por correo o push los pendientes.

## Pantallas *(si la feature tiene UI)*

### Dashboard de indicadores (administrador)
- **Propósito**: evaluar el cumplimiento del plan, las horas, la satisfacción, las brechas cerradas y el ROI del período.
- **Datos que muestra**: barra de filtros (fechas, departamento, tipo de formación, plan); tarjetas % Plan cumplido, Total horas (con desglose), Satisfacción promedio, Brechas cerradas (cerradas / detectadas) y ROI estimado (con acciones en que se basa), cada una con comparación contra el período anterior; fila de indicadores de gestión; gráficos de horas por departamento, evolución de brechas y distribución por tipo de formación.
- **Acciones disponibles** (y quién puede hacerlas): administrador — cambiar filtros, «Exportar Excel», «Exportar PDF», navegar a tabla, impacto 30-60-90 y evaluación de impacto. Colaborador — sin acceso.
- **Estados**: indicadores sin datos (guion y nota), vacío, cargando, error con «Reintentar», sin permisos.
- **Mockup**: patrón Listado/dashboard (`design/ReportesAdmin.dc.html`).

### Tabla detallada (administrador)
- **Propósito**: auditar las actividades del período y consultar la satisfacción de cada una.
- **Datos que muestra**: código, nombre, tipo, fechas, departamento destinatario, asistentes sobre inscritos, horas-persona, costo, calificación promedio y satisfacción promedio; paginación.
- **Acciones disponibles** (y quién puede hacerlas): administrador — filtrar, paginar, abrir los comentarios de satisfacción de una actividad (sin autor). Colaborador — sin acceso.
- **Estados**: vacío, satisfacción sin valor, cargando, error con «Reintentar», sin permisos.
- **Mockup**: patrón Listado (`design/ReportesAdmin.dc.html`), con columnas propias de esta pantalla.

### Impacto 30-60-90 días (administrador)
- **Propósito**: detectar si lo aprendido se aplica y si el entorno lo apoya.
- **Datos que muestra**: una fila por actividad Finalizada del período con, por hito, tasa de respuesta, promedio de aplicación y promedio de apoyo; badge de alerta de reingreso.
- **Acciones disponibles** (y quién puede hacerlas): administrador — consultar y filtrar. Colaborador — sin acceso.
- **Estados**: vacío, hitos sin respuestas (promedios sin valor), cargando, error con «Reintentar», sin permisos.
- **Mockup**: `design/ReportesAdmin.dc.html` (sección de impacto).

### Evaluación de impacto (administrador)
- **Propósito**: registrar y consultar el retorno de la inversión de las acciones Finalizadas.
- **Datos que muestra**: lista de acciones Finalizadas con indicador «Con evaluación» / «Sin evaluación», filtro por ese indicador y por plan; formulario con indicador de negocio, unidad, valor antes, valor después, beneficio estimado (USD), justificación y ROI calculado de la acción.
- **Acciones disponibles** (y quién puede hacerlas): administrador — «Registrar impacto», «Editar impacto», Guardar, Cancelar. Colaborador — sin acceso.
- **Estados**: sin acciones Finalizadas, validación de campos, ROI «no aplica» (costo cero), error al guardar, cargando, sin permisos.
- **Mockup**: patrón Formulario (`design/ReportesAdmin.dc.html` y `design/Formulario.dc.html`).

### Mi progreso (colaborador)
- **Propósito**: que el colaborador vea su avance formativo y responda sus pendientes.
- **Datos que muestra**: tarjetas de formaciones completadas, competencias adquiridas, horas acumuladas y certificados; gráfico de barras de evolución de competencias; lista de certificados con descarga; «Mi plan de desarrollo»; «Pendientes por responder» con formularios breves de encuesta de satisfacción y seguimiento 30-60-90.
- **Acciones disponibles** (y quién puede hacerlas): colaborador — descargar certificados propios, responder encuestas y seguimientos. Administrador — no aplica.
- **Estados**: sin datos (mensaje informativo), sin pendientes, cargando, error con «Reintentar».
- **Mockup**: `design/ReportesColaborador.dc.html`.
