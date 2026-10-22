# Feature Specification: Actividades formativas

**Feature Branch**: `017-actividades-formativas`

**Created**: 2026-10-17

**Status**: Draft

**Input**: User description: "Actividades formativas (documento fuente `specify-actividades.md`). Gestiona la ejecución de las acciones formativas del plan de formación: dónde y cómo se realizan, quiénes asistieron y qué calificación obtuvo cada participante. El administrador (Gestor de Talento) crea la actividad a partir de una acción del plan en estado Ejecutando y registra asistencia y calificaciones; el colaborador consulta sus actividades, su asistencia y su calificación. El módulo no administra el diccionario de competencias, los datos de los colaboradores ni la definición de las acciones del plan: solo los consume a través de los Facades de Estructura Empresarial, Gestión de Personal y Plan de Formación."

## Clarifications

### Session 2026-10-17

- Q: ¿Se puede guardar una observación para un participante que sigue en asistencia Pendiente? → A: No; el campo de observaciones se habilita solo después de marcar Presente, Ausente o Justificado.
- Q: Si el plan retira a un colaborador inscrito que no tiene asistencia registrada, ¿sigue en la lista de la actividad? → A: No; la lista refleja siempre las inscripciones actuales de la acción y quien sale sin registro deja de aparecer y de contar como pendiente.
- Q: ¿Qué ve el colaborador si su asistencia sigue Pendiente en una actividad Finalizada? → A: Se muestra "Pendiente" tal cual, sin calificación.
- Q: ¿Los datos heredados de la acción reflejan el plan vigente o una copia congelada al crear la actividad? → A: Siempre se leen del plan vigente; si cambian las fechas, cambia el estado calculado.
- Q: ¿"Por participante" incluye a quienes fueron Presentes sin calificación? → A: No; solo aparecen colaboradores con al menos una calificación en el filtro.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Crear una actividad desde una acción del plan (Priority: P1)

El administrador pulsa "+ Nueva Actividad", busca y elige una acción no cancelada de un plan en estado Ejecutando que aún no tenga actividad, revisa sus datos heredados (solo lectura), indica el lugar o enlace y, opcionalmente, el departamento destinatario. El sistema asigna un código secuencial ACT-0001, ACT-0002, etc.

**Why this priority**: Sin actividades no se puede registrar asistencia ni calificaciones; es la base de toda la feature.

**Independent Test**: Con un plan Ejecutando que tenga una acción sin actividad, crear la actividad con un lugar y verificar el código, los datos heredados y que la acción deja de aparecer en el selector.

**Acceptance Scenarios**:

1. **Given** el formulario de nueva actividad, **When** se despliega el selector, **Then** solo lista acciones no canceladas de planes Ejecutando que no tienen actividad, con búsqueda por nombre y filtro por plan.
2. **Given** una acción elegida, **When** se muestra el formulario, **Then** se ven en solo lectura su nombre, tipo, competencias objetivo, fechas, formador, capacidad e inscritos.
3. **Given** una acción de tipo Presencial, OJT, Codesarrollo o Mentoring, **When** el administrador ingresa un lugar en texto libre y guarda, **Then** la actividad se crea con código ACT-NNNN único y no editable.
4. **Given** una acción de tipo Virtual o E-learning, **When** el lugar o enlace no es una URL válida, **Then** el sistema lo rechaza indicando que debe ser una URL.
5. **Given** que el lugar o enlace está vacío, **When** intenta guardar, **Then** el sistema lo impide indicando que es obligatorio.
6. **Given** una acción que ya tiene actividad, **When** otro administrador intenta crear una segunda, **Then** el sistema lo rechaza (una acción tiene como máximo una actividad).
7. **Given** una creación exitosa, **When** se consulta la actividad, **Then** consta quién la creó y cuándo.

---

### User Story 2 - Registrar asistencia y calificaciones (Priority: P1)

El administrador abre una actividad En curso o Finalizada y ve la lista de colaboradores inscritos en la acción. Para cada uno marca Presente, Ausente o Justificado, asigna una calificación de 1 a 5 (solo a los Presentes) y anota observaciones internas. Puede usar "Marcar todos presentes" y guarda todos los cambios juntos.

**Why this priority**: Es el valor central del módulo: dejar constancia de quién asistió y cómo le fue, y habilitar el progreso del plan.

**Independent Test**: Con una actividad En curso y tres inscritos, marcar uno Presente con calificación 4, otro Ausente y otro Justificado, guardar y verificar que los registros persisten y que la calificación solo existe en el Presente.

**Acceptance Scenarios**:

1. **Given** una actividad En curso o Finalizada con plan no Cerrado, **When** el administrador abre el registro de asistencia, **Then** ve a todos los inscritos con asistencia Pendiente por defecto, un control Presente / Ausente / Justificado, campo de calificación y campo de observaciones (ambos deshabilitados mientras la asistencia sea Pendiente).
2. **Given** un participante sin asistencia Presente, **When** se muestra su fila, **Then** el campo de calificación está deshabilitado.
3. **Given** un participante Presente, **When** el administrador ingresa una calificación, **Then** solo se aceptan enteros de 1 a 5.
4. **Given** un participante Presente con calificación 5, **When** el administrador cambia su asistencia a Ausente, Justificado o Pendiente y guarda, **Then** su calificación se elimina.
5. **Given** participantes en Pendiente y otros con registro previo, **When** pulsa "Marcar todos presentes", **Then** solo los Pendientes pasan a Presente y los demás no cambian.
6. **Given** que se cambia el registro de un participante que ya tenía uno, **When** pulsa Guardar, **Then** el sistema pide confirmación antes de guardar.
7. **Given** varios cambios en pantalla, **When** pulsa Guardar, **Then** todos se guardan juntos (si falla uno, no se guarda ninguno).
8. **Given** un colaborador que se inscribe en la acción después de creada la actividad, **When** el administrador abre el registro, **Then** aparece con asistencia Pendiente.
9. **Given** una actividad de tipo E-learning, **When** se marca Presente, **Then** significa que el colaborador participó en la acción.
10. **Given** un participante con asistencia registrada, **When** el administrador pulsa de nuevo la opción seleccionada del control, **Then** su asistencia vuelve a Pendiente y, al guardar, se eliminan su calificación y sus observaciones.

---

### User Story 3 - Consultar actividades como colaborador (Priority: P1)

El colaborador ve tarjetas de las actividades en las que está inscrito, de planes Ejecutando o Cerrado y cuya acción ya tiene actividad. Cada tarjeta muestra nombre, tipo, fecha, lugar o enlace, formador, estado de la actividad, su estado de asistencia y su calificación. Las actividades se agrupan en "Próximas actividades" e "Historial".

**Why this priority**: Es la contraparte del colaborador: saber dónde y cuándo participar y cómo le fue.

**Independent Test**: Con un colaborador inscrito en una actividad Programada y otra Finalizada con asistencia Presente y calificación 4, verificar que aparecen en las secciones correctas con sus datos y sin observaciones.

**Acceptance Scenarios**:

1. **Given** un colaborador inscrito en acciones con y sin actividad, **When** abre su vista, **Then** solo ve las acciones que ya tienen actividad creada.
2. **Given** actividades Programadas o En curso, **When** se muestra "Próximas actividades", **Then** están ordenadas por fecha de inicio ascendente.
3. **Given** actividades Finalizadas o Canceladas, **When** se muestra "Historial", **Then** están ordenadas de la más reciente a la más antigua.
4. **Given** una actividad con asistencia registrada, **When** el colaborador ve la tarjeta, **Then** ve su asistencia (Pendiente, Presente, Ausente o Justificado) y su calificación si la tiene, pero nunca las observaciones del formador.
5. **Given** una actividad Finalizada cuya asistencia del colaborador sigue sin registrarse, **When** ve la tarjeta, **Then** se muestra la asistencia "Pendiente" sin calificación.
6. **Given** un colaborador sin actividades, **When** abre su vista, **Then** ve un mensaje informativo.
7. **Given** actividades de otros colaboradores, **When** el colaborador navega o consulta datos, **Then** solo accede a las propias.
8. **Given** planes en estado distinto de Ejecutando o Cerrado, **When** se arma la vista, **Then** sus actividades no aparecen.
9. **Given** un colaborador con actividades, **When** abre su vista, **Then** ve un resumen con actividades inscritas, finalizadas, porcentaje de asistencia (Presentes sobre registros de asistencia) y calificación promedio, y muestra «—» cuando no hay datos para calcularlos.

---

### User Story 4 - Listar y filtrar actividades (Priority: P2)

El administrador consulta una tabla con todas las actividades, con código, nombre, tipo, fechas, lugar o enlace, formador, departamento, capacidad, inscritos y estado. Busca por nombre y filtra por tipo, estado, departamento, competencia, plan y rango de fechas; por defecto ve las del año actual. Las Finalizadas con participantes en Pendiente llevan el badge "Asistencia pendiente".

**Why this priority**: Da visibilidad y acceso rápido al registro, pero las historias anteriores funcionan sin una lista elaborada.

**Independent Test**: Con actividades de distintos tipos y estados, aplicar filtros combinados y verificar que la tabla responde y que el badge aparece solo donde corresponde.

**Acceptance Scenarios**:

1. **Given** actividades de varios años, **When** el administrador abre la lista, **Then** se muestran por defecto las del año actual.
2. **Given** la lista, **When** busca por nombre o aplica filtros (tipo, estado, departamento, competencia, plan, rango de fechas), **Then** la tabla se actualiza y los filtros se pueden combinar.
3. **Given** una actividad Finalizada con al menos un participante en Pendiente, **When** se muestra en la lista, **Then** lleva el badge "Asistencia pendiente".
4. **Given** una actividad Finalizada con todos los participantes con registro, **When** se muestra, **Then** no lleva ese badge.
5. **Given** la lista, **When** se revisan los botones por fila, **Then** no existe ninguno para eliminar una actividad.
6. **Given** un usuario con rol Colaborador, **When** intenta acceder a la lista, **Then** el acceso se deniega.

---

### User Story 5 - Consultar calificaciones agregadas (Priority: P2)

El administrador abre la vista de calificaciones con dos pestañas. "Por actividad" muestra nombre, tipo, fechas, cantidad de participantes calificados y promedio. "Por participante" muestra nombre, cantidad de actividades calificadas y promedio, y al elegir un participante se ve el detalle por actividad. Filtra por rango de fechas, tipo, departamento y plan.

**Why this priority**: Aporta análisis del desempeño en las actividades, dependiente de que haya calificaciones registradas.

**Independent Test**: Con dos actividades calificadas (4 y 5 para el mismo colaborador), verificar que su promedio es 4,5 y que el detalle lista ambas actividades.

**Acceptance Scenarios**:

1. **Given** una actividad con calificaciones 3, 4 y 5, **When** se ve "Por actividad", **Then** muestra 3 participantes calificados y promedio 4,0.
2. **Given** una actividad sin calificaciones, **When** se muestra, **Then** el promedio aparece sin valor.
3. **Given** un colaborador calificado en varias actividades dentro del filtro, **When** se ve "Por participante", **Then** el promedio considera solo las calificaciones de las actividades filtradas.
4. **Given** un participante sin calificaciones en el filtro, **When** se abre "Por participante", **Then** no aparece en la lista (solo se listan colaboradores con al menos una calificación).
5. **Given** un participante elegido, **When** se abre su detalle, **Then** lista cada actividad con su asistencia y calificación.
6. **Given** un usuario Colaborador, **When** intenta acceder a esta vista, **Then** el acceso se deniega.

---

### User Story 6 - Editar actividad y consultar historial de registros (Priority: P3)

El administrador edita el lugar o enlace y el departamento de una actividad mientras el plan no esté Cerrado, con el mismo formulario y la acción fija. También consulta el historial de cambios de asistencia y calificación de una actividad.

**Why this priority**: Corrige datos logísticos y da trazabilidad; es complementario al flujo principal.

**Independent Test**: Editar el lugar de una actividad y verificar la auditoría de modificación; cambiar una asistencia y verificar el historial con valor anterior y nuevo.

**Acceptance Scenarios**:

1. **Given** una actividad de un plan no Cerrado, **When** el administrador edita lugar o enlace y departamento, **Then** se guardan y consta quién modificó por última vez y cuándo.
2. **Given** el formulario de edición, **When** se muestra, **Then** la acción del plan y el código no son editables, y se aplican las mismas validaciones de URL por tipo.
3. **Given** un plan Cerrado, **When** se intenta editar la actividad o sus registros, **Then** el sistema no lo permite y todo se muestra de solo lectura.
4. **Given** cualquier registro o cambio de asistencia o calificación, **When** el administrador abre el historial de la actividad, **Then** ve usuario, fecha, participante, valor anterior y valor nuevo de cada cambio.
5. **Given** un cambio de calificación causado por la eliminación automática (al cambiar a Ausente), **When** se consulta el historial, **Then** queda registrada la calificación anterior y el valor nuevo vacío.

---

### Edge Cases

- Actividad Programada o Cancelada: la vista de asistencia aparece deshabilitada con la explicación correspondiente y no se puede registrar nada.
- Actividad Cancelada (la acción se cancela en el plan): conserva los registros existentes, pasa al Historial del colaborador y no admite registros nuevos.
- Plan Cerrado: la actividad, sus registros y el historial quedan de solo lectura; el colaborador sigue viéndola.
- Intentar registrar asistencia de un colaborador que no está inscrito en la acción: se rechaza.
- Intentar quitar desde el plan a un colaborador que ya tiene asistencia registrada: el plan lo impide (consulta a esta feature).
- Una acción sin actividad no permite registrar asistencia y no cuenta como completada en el progreso del plan del colaborador.
- Una acción completada para el progreso requiere que la actividad esté Finalizada y el colaborador tenga asistencia Presente.
- Asistencia Pendiente nunca genera registro guardado; solo se guarda cuando el administrador la cambia, y volver a Pendiente elimina la calificación y las observaciones.
- Dos administradores guardan la asistencia de la misma actividad a la vez: se conserva un resultado consistente y se informa al segundo si sus datos estaban desactualizados.
- Calificación fuera de 1–5, decimal o asignada a un no Presente: se rechaza.
- Un codesarrollo o mentoring con varias sesiones se gestiona como varias acciones del plan, cada una con su propia actividad.
- El departamento asociado pasa a inactivo o se retira del módulo de Estructura: la actividad conserva su referencia histórica.

## Requirements *(mandatory)*

### Functional Requirements

#### Actividad

- **FR-001**: El sistema MUST generar un código único ACT-NNNN secuencial, no editable, al crear una actividad.
- **FR-002**: El sistema MUST crear toda actividad a partir de una única acción del plan y garantizar que una acción tenga como máximo una actividad; no existen actividades sueltas.
- **FR-003**: El sistema MUST permitir crear actividades solo desde acciones no canceladas de planes en estado Ejecutando que aún no tengan actividad.
- **FR-004**: El sistema MUST exigir el lugar o enlace; para acciones de tipo Virtual y E-learning MUST validar que sea una URL válida y para los demás tipos aceptar texto libre.
- **FR-005**: El sistema MUST permitir asociar opcionalmente un departamento, tomado del módulo de Estructura Empresarial.
- **FR-006**: El sistema MUST mostrar en solo lectura los datos heredados de la acción: nombre, tipo, competencias objetivo, fechas, formador, capacidad máxima, participantes inscritos y plan al que pertenece; siempre se leen del plan vigente (no se copian), de modo que si cambian las fechas cambia el estado calculado.
- **FR-007**: El sistema MUST calcular el estado de la actividad a partir de la acción: Programada (inicio futuro), En curso (hoy entre inicio y fin), Finalizada (fin pasado) o Cancelada (acción cancelada en el plan).
- **FR-008**: El sistema MUST marcar como "Asistencia pendiente" a toda actividad Finalizada con al menos un participante en Pendiente.
- **FR-009**: El sistema MUST NOT permitir eliminar actividades; para cancelarla se cancela la acción en el plan.
- **FR-010**: El sistema MUST permitir editar solo lugar o enlace y departamento mientras el plan no esté Cerrado; los demás datos se gestionan en el plan.
- **FR-011**: Cuando el plan pase a Cerrado, el sistema MUST dejar la actividad y sus registros de solo lectura.

#### Asistencia y calificación

- **FR-012**: El sistema MUST considerar participantes a los colaboradores inscritos en la acción en el plan, sin modificar las inscripciones, y MUST NOT permitir registrar a quien no esté inscrito.
- **FR-013**: El sistema MUST manejar la asistencia Pendiente, Presente, Ausente o Justificado; todo participante comienza en Pendiente y solo se guarda registro cuando el administrador lo cambia.
- **FR-014**: El sistema MUST permitir registrar asistencia únicamente cuando la actividad esté En curso o Finalizada y el plan no esté Cerrado; en una actividad Cancelada MUST NOT permitir registros nuevos y MUST conservar los existentes.
- **FR-015**: El sistema MUST aceptar una calificación entera de 1 a 5, opcional, solo para participantes con asistencia Presente.
- **FR-016**: El sistema MUST eliminar la calificación de un participante cuando su asistencia cambie a Ausente, Justificado o Pendiente; al volver a Pendiente MUST eliminar además sus observaciones.
- **FR-017**: El sistema MUST permitir observaciones opcionales de texto por participante, de uso interno, visibles únicamente para el administrador; el campo solo se habilita cuando la asistencia es Presente, Ausente o Justificado, por lo que Pendiente nunca tiene observaciones.
- **FR-018**: El sistema MUST ofrecer "Marcar todos presentes", que marque Presente solo a los participantes en Pendiente sin alterar a quienes ya tienen registro.
- **FR-019**: El sistema MUST guardar de forma conjunta (todo o nada) los cambios de asistencia, calificación y observaciones de una actividad, y pedir confirmación si se modifica el registro de un participante que ya tenía uno.
- **FR-020**: El sistema MUST mantener la lista de participantes sincronizada con las inscripciones actuales de la acción: mostrar con asistencia Pendiente a quienes se inscriban después de creada la actividad, y dejar de mostrar (y de contar como pendiente) a quienes el plan retire sin asistencia registrada.
- **FR-021**: El sistema MUST deshabilitar la vista de registro, con la explicación correspondiente, si la actividad está Programada o Cancelada o si el plan está Cerrado.

#### Calificaciones agregadas

- **FR-022**: El sistema MUST calcular el promedio por actividad como el promedio de las calificaciones asignadas en ella, y mostrarlo sin valor si no hay ninguna.
- **FR-023**: El sistema MUST calcular el promedio por participante como el promedio de todas sus calificaciones en las actividades del filtro consultado, y listar en "Por participante" solo a los colaboradores con al menos una calificación en ese filtro.
- **FR-024**: El administrador MUST poder consultar calificaciones por actividad y por participante, con detalle por actividad al elegir un participante, filtrando por rango de fechas, tipo, departamento y plan.

#### Vistas

- **FR-025**: El administrador MUST disponer de una tabla de actividades con código, nombre, tipo, fechas, lugar o enlace, formador, departamento, capacidad, inscritos, estado (badge) y acciones, con badge adicional "Asistencia pendiente" cuando corresponda.
- **FR-026**: El sistema MUST ofrecer búsqueda por nombre y filtros por tipo, estado, departamento, competencia, plan y rango de fechas, mostrando por defecto las actividades del año actual (rango del 1 de enero al 31 de diciembre). Para ver otros años el administrador cambia el rango de fechas; «Limpiar filtros» restablece el año actual.
- **FR-027**: El formulario de nueva actividad MUST ofrecer un selector de acciones elegibles con búsqueda por nombre y filtro por plan; la edición usa el mismo formulario con la acción fija.
- **FR-028**: El colaborador MUST ver tarjetas de las actividades en que está inscrito, de planes Ejecutando o Cerrado y solo de acciones con actividad creada, con nombre, tipo, fecha, lugar o enlace, formador, estado de la actividad, estado de asistencia y calificación.
- **FR-029**: La vista del colaborador MUST incluir "Próximas actividades" (Programadas o En curso, por fecha de inicio ascendente) e "Historial" (Finalizadas o Canceladas, de la más reciente a la más antigua), y un mensaje informativo si no tiene actividades.
- **FR-030**: El colaborador MUST ver únicamente sus propias actividades, asistencia y calificación, y MUST NOT ver las observaciones.
- **FR-037**: La vista del colaborador MUST incluir un resumen con actividades inscritas, finalizadas, porcentaje de asistencia (Presentes sobre registros de asistencia) y calificación promedio, mostrando «—» cuando no hay datos; es informativo y no afecta el progreso del plan.

#### Permisos

- **FR-031**: Solo el administrador MUST poder listar, consultar el detalle, crear, editar, registrar asistencia, consultar calificaciones y ver el historial de registros; los botones correspondientes MUST ser visibles únicamente para él, y el acceso se valida también en el servidor.

#### Auditoría

- **FR-032**: El sistema MUST registrar quién creó la actividad y cuándo, y quién la modificó por última vez y cuándo.
- **FR-033**: El sistema MUST registrar en un historial cada registro o cambio de asistencia o calificación con usuario, fecha, participante, valor anterior y valor nuevo, incluidas las eliminaciones automáticas de calificación, y ponerlo a disposición del administrador por actividad.

#### Integración con otros módulos

- **FR-034**: El sistema MUST consumir de Plan de Formación (vía su Facade) las acciones, fechas, formador, capacidad, competencias, participantes, estado de cancelación y estado del plan, y de Estructura Empresarial y Gestión de Personal (vía sus Facades) los departamentos, competencias y datos de colaboradores.
- **FR-035**: El sistema MUST exponer al Plan de Formación la asistencia de cada colaborador para que calcule su progreso (una acción cuenta como completada cuando la actividad está Finalizada y el colaborador tiene asistencia Presente; sin actividad no cuenta) y MUST permitirle impedir quitar de la acción a un participante con asistencia registrada.
- **FR-036**: El sistema MUST dejar disponibles las asistencias, calificaciones, horas y actividades para que el futuro módulo de Reportes las consuma, sin implementar ese módulo.

### Key Entities *(include if feature involves data)*

- **Actividad formativa**: ejecución de una acción del plan. Atributos: código ACT-NNNN, acción del plan (única), lugar o enlace, departamento opcional, estado calculado, marca de asistencia pendiente, datos de creación y última modificación. Nunca se elimina.
- **Registro de asistencia**: relación entre una actividad y un colaborador inscrito. Atributos: asistencia (Presente, Ausente, Justificado; Pendiente implícita sin registro), calificación 1–5 opcional, observaciones internas. Existe solo cuando el administrador lo cambia.
- **Historial de asistencia y calificación**: bitácora inmutable de cambios con usuario, fecha, participante, valor anterior y valor nuevo.
- **Acción del plan** *(propiedad del Plan de Formación)*: fuente de nombre, tipo, competencias, fechas, formador, capacidad, inscritos y cancelación; esta feature solo la consulta.
- **Colaborador, Departamento y Competencia** *(propiedad de Gestión de Personal y Estructura Empresarial)*: se consultan, no se administran aquí.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El administrador puede crear una actividad desde una acción elegible en menos de 1 minuto.
- **SC-002**: El administrador puede registrar la asistencia de una actividad de 30 participantes (con "Marcar todos presentes" y algunos ajustes) en menos de 3 minutos.
- **SC-003**: El 100 % de las calificaciones guardadas pertenece a participantes con asistencia Presente y está entre 1 y 5.
- **SC-004**: El 100 % de los cambios de asistencia y calificación queda en el historial con usuario, fecha, valor anterior y valor nuevo.
- **SC-005**: Ningún colaborador puede ver actividades, asistencia, calificaciones u observaciones de otro colaborador, ni las observaciones propias (0 casos en pruebas de permisos).
- **SC-006**: El colaborador encuentra dónde, cuándo y con quién es su próxima actividad y su calificación anterior en menos de 30 segundos desde que abre la vista.
- **SC-007**: Las actividades Finalizadas con participantes pendientes se identifican de inmediato en la lista, sin abrir cada actividad (100 % con badge).
- **SC-008**: Los promedios por actividad y por participante coinciden al 100 % con el cálculo manual sobre las calificaciones asignadas, en pruebas con datos conocidos.
- **SC-009**: Con el plan Cerrado, 0 modificaciones de actividades o registros son aceptadas.

## Assumptions

- Cuando se vuelve a Pendiente la fila de registro del participante se elimina (sin fila equivale a Pendiente); las actividades y el historial nunca se eliminan.

- El plan de formación anual (feature 013) ya está implementado y expone, vía su Facade, acciones, participantes, estado de cancelación, estado del plan y formador; aquí solo se agregan los puntos de consulta de asistencia que el plan necesita.
- Los roles existentes son Administrador (Gestor de Talento) y Colaborador; un colaborador solo ve los datos asociados a su propio perfil.
- El formador es un dato textual heredado de la acción; no se gestiona en este módulo.
- Las calificaciones son numéricas enteras de 1 a 5 y los promedios se muestran con un decimal; son convenciones del sistema (no literales en la bibliografía) y cambiarlas requiere decisión explícita del equipo.
- El estado de la actividad y la marca "Asistencia pendiente" se calculan al consultar y no se almacenan como datos editables.
- La fecha de referencia para el estado es la fecha actual del servidor.
- La asistencia no se importa ni se registra de forma masiva por archivo; solo manualmente desde la pantalla.
- Los datos de colaboradores y departamentos retirados se conservan como referencia histórica en las actividades y sus registros.
- Los indicadores y vistas de progreso del módulo de Reportes quedan fuera de alcance.
- Fundamento bibliográfico: Alles, *Las 50 herramientas de RRHH* (capacitación, codesarrollo, mentoring, guías OJT y fuera del trabajo) y Navarro, *Evaluación del Desempeño*, Paso 8 (seguimiento del avance de actividades formativas).

## Pantallas *(si la feature tiene UI)*

### Lista de actividades (administrador)
- **Propósito**: consultar y acceder a las actividades formativas.
- **Datos que muestra**: código, nombre, tipo, fechas, lugar o enlace, formador, departamento, capacidad, inscritos, estado (badge) y badge "Asistencia pendiente".
- **Acciones disponibles** (y quién puede hacerlas): administrador — "+ Nueva Actividad", editar, registrar asistencia, ver calificaciones, ver historial; búsqueda por nombre y filtros por tipo, estado, departamento, competencia, plan y rango de fechas. Colaborador — sin acceso.
- **Estados**: vacío, cargando, error con "Reintentar", sin permisos.
- **Mockup**: patrón Listado (`design/ActividadesAdmin.dc.html`), con tabla y filtros propios de esta pantalla.

### Formulario de actividad (administrador)
- **Propósito**: crear una actividad desde una acción del plan o editar su lugar y departamento.
- **Datos que muestra**: selector de acción (con búsqueda y filtro por plan), datos heredados en solo lectura, lugar o enlace, departamento.
- **Acciones disponibles** (y quién puede hacerlas): administrador — Guardar, Cancelar; en edición la acción del plan es fija. Colaborador — sin acceso.
- **Estados**: sin acciones elegibles, validación de URL, solo lectura (plan Cerrado), error al guardar, sin permisos.
- **Mockup**: patrón Formulario (`design/ActividadesAdmin.dc.html` y `design/Formulario.dc.html`).

### Registro de asistencia (administrador)
- **Propósito**: registrar asistencia, calificación y observaciones de los inscritos.
- **Datos que muestra**: nombre del participante, control Presente / Ausente / Justificado (al pulsar de nuevo la opción seleccionada, vuelve a Pendiente), calificación 1–5 (habilitada solo si Presente), observaciones (deshabilitadas mientras sea Pendiente); datos y estado de la actividad.
- **Acciones disponibles** (y quién puede hacerlas): administrador — "Marcar todos presentes", Guardar (con confirmación si se modifica un registro previo), ver historial. Colaborador — sin acceso.
- **Estados**: deshabilitada con explicación (Programada, Cancelada o plan Cerrado), cargando, sin inscritos, error al guardar, sin permisos.
- **Mockup**: `design/ActividadesAsistencia.dc.html`.

### Calificaciones (administrador)
- **Propósito**: analizar las calificaciones por actividad y por participante.
- **Datos que muestra**: pestaña "Por actividad" (nombre, tipo, fechas, calificados, promedio) y "Por participante" (nombre, actividades calificadas, promedio, detalle por actividad).
- **Acciones disponibles** (y quién puede hacerlas): administrador — cambiar de pestaña, filtrar por rango de fechas, tipo, departamento y plan, abrir el detalle de un participante. Colaborador — sin acceso.
- **Estados**: vacío, promedio sin valor, cargando, error con "Reintentar", sin permisos.
- **Mockup**: `design/ActividadesCalificaciones.dc.html`.

### Mis actividades (colaborador)
- **Propósito**: consultar sus actividades formativas, su asistencia y su calificación.
- **Datos que muestra**: resumen (inscritas, finalizadas, % de asistencia, calificación promedio); tarjetas con nombre, tipo, fecha, lugar o enlace, formador, estado de la actividad, estado de asistencia y calificación; secciones "Próximas actividades" e "Historial".
- **Acciones disponibles** (y quién puede hacerlas): colaborador — solo consulta de lo propio; sin observaciones visibles. Administrador — no aplica.
- **Estados**: sin actividades (mensaje informativo), cargando, error con "Reintentar".
- **Mockup**: `design/ActividadesColaborador.dc.html`.
