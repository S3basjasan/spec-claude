# Feature Specification: Plan de formación anual

**Feature Branch**: `013-plan-formacion-anual`

**Created**: 2026-10-16

**Status**: Draft

**Input**: User description: "Plan de formación anual: organiza las acciones formativas de un período (qué se enseña, para qué competencias, de qué manera, cuándo, con quién, a qué costo y para quiénes) con un ciclo de vida Borrador, Revisión, Aprobado, Ejecutando y Cerrado. El módulo no administra el diccionario de competencias ni los datos de los colaboradores ni registra asistencia: solo los consume. Fuente: specify-formacion.md."

## Clarifications

### Session 2026-10-16

- Q: Si el aprobador designado de un plan en Revisión deja de ser Administrador o queda inactivo, ¿cómo se destraba el plan? → A: Cualquier Administrador puede reasignar el aprobador de un plan en Revisión y queda registrado en el historial.
- Q: ¿Puede el Administrador que envía un plan a revisión ser también su aprobador designado? → A: Sí; el aprobador puede ser quien creó o envió el plan.
- Q: Cuando un colaborador deja de tener vínculo laboral vigente después de aprobado el plan, ¿qué pasa con sus inscripciones en acciones que aún no finalizan? → A: La inscripción se conserva y sigue ocupando cupo; el administrador puede quitarla manualmente.
- Q: Cuando la DNF solicita un borrador de plan, ¿qué año y qué nombre recibe el plan creado? → A: Año actual y nombre automático tipo "Plan DNF DNF-0001"; el administrador puede cambiarlos mientras esté en Borrador.
- Q: ¿Qué ve el colaborador en las acciones canceladas de planes Aprobado, Ejecutando o Cerrado? → A: Se muestran en su lista con estado "Cancelada" y el motivo, sin contar en progreso ni próximas actividades.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Armar un plan en Borrador con el asistente (Priority: P1)

El Administrador (Gestor de Talento) crea un plan con un asistente de 3 pasos: datos generales (nombre, año, descripción), acciones formativas (opcionalmente partiendo de un curso del Catálogo Formativo) y participantes con aprobador. En cualquier paso puede guardar como Borrador, aun con acciones incompletas.

**Why this priority**: Sin plan y acciones no existe ninguna otra funcionalidad; es el núcleo del módulo.

**Independent Test**: Crear un plan con dos acciones, asignar participantes y guardar; verificar código PLAN-0001, estado Borrador y datos persistidos.

**Acceptance Scenarios**:

1. **Given** el asistente abierto, **When** el administrador completa nombre y año y guarda, **Then** el plan queda en Borrador con código secuencial no editable.
2. **Given** un plan de 2026 llamado "Plan técnico", **When** se crea otro plan 2026 con nombre " plan TÉCNICO ", **Then** se rechaza por duplicado; en otro año se permite.
3. **Given** una acción nueva, **When** se elige un curso del catálogo, **Then** se proponen tipo, formador y competencias del curso y el administrador puede modificarlos.
4. **Given** una acción en Borrador, **When** solo tiene nombre y competencias, **Then** se guarda; sin competencias objetivo se rechaza.
5. **Given** fechas fuera del año del plan o fin anterior al inicio, **When** se guarda la acción, **Then** se rechaza con mensaje claro.
6. **Given** una acción con capacidad 10, **When** se intenta inscribir un participante número 11 o repetir uno, **Then** se rechaza.
7. **Given** un colaborador inscrito en dos acciones con fechas cruzadas, **When** se asigna, **Then** se muestra un aviso junto al participante y la asignación se permite.
8. **Given** una competencia inactiva, **When** se eligen competencias objetivo, **Then** no se ofrece para nuevas asociaciones.

---

### User Story 2 - Enviar a revisión, aprobar o devolver (Priority: P1)

El administrador envía el plan a revisión cuando está completo. Solo el aprobador designado puede aprobarlo o devolverlo a Borrador con un motivo obligatorio.

**Why this priority**: El control de aprobación es la razón de ser del ciclo de vida.

**Independent Test**: Con un plan completo, enviarlo a revisión, devolverlo con motivo, reenviarlo y aprobarlo; verificar estados e historial.

**Acceptance Scenarios**:

1. **Given** un plan sin acciones, sin aprobador, con acciones incompletas o con una acción sin participantes, **When** se intenta enviar a revisión, **Then** se rechaza listando lo que falta.
2. **Given** un plan completo, **When** el administrador lo envía, **Then** pasa a Revisión y queda de solo lectura.
3. **Given** un plan en Revisión, **When** un administrador distinto del aprobador designado intenta aprobar o devolver, **Then** se deniega.
4. **Given** el aprobador designado, **When** devuelve sin motivo, **Then** se rechaza; con motivo, el plan vuelve a Borrador y el motivo queda en el historial.
5. **Given** el aprobador designado, **When** aprueba tras confirmar, **Then** el plan pasa a Aprobado y se registra la fecha de aprobación.

---

### User Story 3 - Ejecutar, ajustar y cerrar un plan aprobado (Priority: P1)

Con el plan Aprobado, el administrador inicia la ejecución, puede cambiar participantes de una acción (respetando capacidad) y cancelar acciones con motivo; finalmente cierra el plan.

**Why this priority**: Completa el ciclo de vida y permite operar el plan durante el año.

**Independent Test**: Aprobar un plan, iniciar ejecución, cambiar participantes, cancelar una acción y cerrar cuando todas estén Finalizadas o Canceladas.

**Acceptance Scenarios**:

1. **Given** un plan Aprobado, **When** el administrador intenta editar nombre, fechas, costo u otros datos de una acción, **Then** no se permite; sí puede cambiar participantes y cancelar acciones.
2. **Given** un plan Aprobado o Ejecutando, **When** el administrador cancela una acción, **Then** se pide motivo, se advierte que los participantes dejarán de verla como pendiente y la acción queda Cancelada.
3. **Given** un plan Ejecutando con alguna acción Programada o En curso, **When** se intenta cerrar, **Then** se rechaza; con todas Finalizadas o Canceladas, se cierra tras confirmar.
4. **Given** un plan Cerrado, **When** se intenta modificar o retroceder de estado, **Then** no se permite.
5. **Given** cualquier plan, **When** se busca eliminarlo, o eliminar una acción desde Revisión en adelante, **Then** no existe esa opción.
6. **Given** acciones con fechas, **When** pasa el tiempo, **Then** su estado se calcula como Programada, En curso o Finalizada según la fecha actual, salvo Cancelada.

---

### User Story 4 - Consultar planes, acciones y calendario (Priority: P2)

El administrador consulta la tabla de planes (con botones según estado) y las acciones del año en las pestañas "Calendario" y "Lista", con filtros por estado del plan, tipo, competencia y rango de fechas.

**Why this priority**: Da visibilidad operativa, pero depende de que ya existan planes.

**Independent Test**: Con varios planes en distintos estados, verificar columnas, filtros, botones por estado y que el calendario muestre cada acción en los días que abarca.

**Acceptance Scenarios**:

1. **Given** planes en distintos estados, **When** el administrador abre la tabla, **Then** ve código, nombre, año, estado, cantidad de acciones, costo total, aprobador y los botones permitidos por estado.
2. **Given** la pestaña Lista, **When** se muestran acciones, **Then** por defecto son las del año actual y las canceladas llevan el badge "Cancelada".
3. **Given** la pestaña Calendario, **When** se hace clic en una acción, **Then** se abre su detalle; los colores distinguen el tipo.
4. **Given** filtros aplicados en una pestaña, **When** el administrador cambia de pestaña, **Then** los filtros se mantienen.

---

### User Story 5 - Mis acciones formativas como colaborador (Priority: P2)

El colaborador ve cronológicamente las acciones que tiene asignadas de planes Aprobado, Ejecutando o Cerrado, su % de plan completado y las tres próximas actividades.

**Why this priority**: Es el valor directo para el colaborador, pero depende de planes aprobados.

**Independent Test**: Con un colaborador inscrito en acciones de planes en distintos estados, verificar qué ve, el porcentaje y las próximas tres actividades.

**Acceptance Scenarios**:

1. **Given** acciones en planes Borrador o Revisión, **When** el colaborador abre su vista, **Then** no las ve.
2. **Given** acciones asignadas, **When** consulta, **Then** cada tarjeta muestra nombre, competencia, fechas, formador, estado y "Ver detalle".
3. **Given** 4 acciones asignadas no canceladas, 1 Finalizada con asistencia Presente, **When** consulta, **Then** el progreso es 25 %.
4. **Given** más de tres acciones futuras, **When** consulta, **Then** "Próximas actividades" muestra las tres no canceladas con inicio de hoy en adelante, las más cercanas primero.
5. **Given** un colaborador sin acciones, **When** consulta, **Then** ve un mensaje informativo.
6. **Given** otro colaborador, **When** intenta ver datos ajenos, **Then** se deniega.

---

### User Story 6 - Historial y detalle del plan (Priority: P2)

El administrador abre el detalle de un plan: datos generales, estado, aprobador, origen (con enlace a la DNF), acciones con costo y horas totales, e historial de cambios de estado y cambios posteriores a la aprobación.

**Why this priority**: Aporta trazabilidad y auditoría.

**Independent Test**: Realizar transiciones y cambios posteriores a la aprobación y verificar el historial.

**Acceptance Scenarios**:

1. **Given** transiciones de estado, **When** se abre el historial, **Then** cada una muestra usuario, fecha, estado anterior, estado nuevo y motivo.
2. **Given** un plan aprobado, **When** se agrega o quita un participante o se cancela una acción, **Then** el historial registra usuario, fecha, acción afectada y detalle.
3. **Given** un plan, **When** se consulta, **Then** se ven quién lo creó y cuándo, y quién lo modificó por última vez y cuándo.

---

### User Story 7 - Recibir un borrador desde la DNF (Priority: P3)

Cuando la DNF solicita un borrador de plan a partir de brechas, el sistema crea un plan en Borrador con una acción por cada competencia con brecha (esa competencia como objetivo) y los colaboradores con brecha como participantes; el resto de los datos queda por completar.

**Why this priority**: Integra con una feature ya implementada, pero el módulo funciona sin ella.

**Independent Test**: Solicitar el borrador con brechas de varios colaboradores y verificar acciones, participantes y origen.

**Acceptance Scenarios**:

1. **Given** brechas en 3 competencias, **When** se solicita el borrador, **Then** se crean 3 acciones, cada una con su competencia y los colaboradores con esa brecha.
2. **Given** el borrador creado, **When** se abre el detalle, **Then** el origen enlaza a la DNF de la que proviene.
3. **Given** un borrador desde DNF, **When** se intenta enviar a revisión, **Then** se aplican las mismas reglas de completitud.

---

### Edge Cases

- Dos planes del mismo año con nombres que difieren solo en mayúsculas o espacios laterales: se tratan como duplicados.
- Varios planes para un mismo año: permitido.
- Una competencia asociada se desactiva después: la referencia se conserva y sigue consultable, pero no se ofrece para nuevas asociaciones.
- Un colaborador pierde su vínculo laboral vigente tras la aprobación: su inscripción se conserva, sigue ocupando cupo y el administrador puede quitarla manualmente (queda en el historial).
- Cambio de participantes que supere la capacidad en un plan Aprobado o Ejecutando: se rechaza.
- Acción cancelada: deja de contar en costo y horas totales del plan.
- Aprobador designado que luego deja de ser Administrador o queda inactivo: cualquier Administrador puede reasignar el aprobador del plan en Revisión (FR-016a).
- Cierre de plan con todas las acciones Canceladas: se permite.
- Acción con fecha de fin pasada en un plan Aprobado: aparece Finalizada sin intervención.
- Colaborador sin vínculo laboral vigente como participante: no se ofrece en la búsqueda.

## Requirements *(mandatory)*

### Functional Requirements

**Planes**

- **FR-001**: El sistema MUST generar un código único PLAN-NNNN secuencial, no editable, al crear un plan.
- **FR-002**: El sistema MUST exigir nombre y año en el plan; descripción opcional.
- **FR-003**: El sistema MUST garantizar nombre único dentro del mismo año sin distinguir mayúsculas ni espacios laterales, y permitir varios planes por año.
- **FR-004**: El sistema MUST manejar los estados Borrador, Revisión, Aprobado, Ejecutando y Cerrado, y registrar la fecha de aprobación al aprobar.
- **FR-005**: El sistema MUST permitir designar como aprobador a un usuario con rol Administrador, obligatorio para enviar a revisión.
- **FR-006**: El sistema MUST registrar el origen opcional (DNF) del plan.
- **FR-007**: El sistema MUST NOT permitir eliminar planes; las acciones solo se eliminan en Borrador (con confirmación) y desde Revisión solo se cancelan.

**Acciones formativas**

- **FR-008**: Cada acción MUST tener nombre y una o más competencias objetivo activas del diccionario maestro como únicos datos obligatorios en Borrador.
- **FR-009**: Cada acción MUST poder registrar: curso del catálogo (opcional), tipo (Presencial, Virtual, E-learning, OJT, Codesarrollo, Mentoring), fecha de inicio y fin, duración en horas (> 0), formador, capacidad máxima (entero > 0) y costo total en USD (≥ 0).
- **FR-010**: Al elegir un curso del catálogo, el sistema MUST proponer tipo, formador y competencias, modificables por el administrador.
- **FR-011**: El sistema MUST validar que la fecha de fin no sea anterior a la de inicio y que ambas estén dentro del año del plan.
- **FR-012**: El sistema MUST validar que los participantes no superen la capacidad máxima ni se repitan en la misma acción.
- **FR-013**: El sistema MUST calcular el estado de la acción: Programada (inicio futuro), En curso (hoy entre inicio y fin), Finalizada (fin pasado) o Cancelada (por el administrador, con motivo obligatorio).
- **FR-014**: El sistema MUST avisar, sin impedir, cuando un colaborador queda inscrito en acciones con fechas que se cruzan.

**Completitud y ciclo de vida**

- **FR-015**: Para enviar a revisión el sistema MUST exigir al menos una acción, todas las acciones con todos sus datos completos, aprobador designado y al menos un participante por acción.
- **FR-016**: El sistema MUST permitir solo estas transiciones: Borrador→Revisión (administrador), Revisión→Aprobado y Revisión→Borrador con motivo obligatorio (solo aprobador designado), Aprobado→Ejecutando (administrador), Ejecutando→Cerrado (administrador, solo si todas las acciones están Finalizadas o Canceladas).
- **FR-016a**: En un plan en Revisión, cualquier Administrador MUST poder reasignar el aprobador designado (en particular si dejó de ser Administrador o quedó inactivo); el cambio no altera el estado del plan y MUST registrarse en el historial con usuario, fecha, aprobador anterior y nuevo.
- **FR-017**: El sistema MUST aplicar reglas de edición por estado: Borrador editable por completo; Revisión de solo lectura salvo la reasignación del aprobador (FR-016a); Cerrado de solo lectura; Aprobado y Ejecutando permiten únicamente cambiar participantes (respetando capacidad) y cancelar acciones.

**Consulta y visualización**

- **FR-018**: El administrador MUST disponer de una tabla de planes con código, nombre, año, estado, cantidad de acciones, costo total, aprobador y botones según estado, con filtros por año, estado y búsqueda por nombre.
- **FR-019**: El administrador MUST disponer de las pestañas "Calendario" (vista mensual, acción en cada día que abarca, color por tipo, clic abre el detalle) y "Lista" (plan, nombre, competencia, tipo, fechas, formador, inscritos/capacidad, costo y estado), con filtros por estado del plan, tipo, competencia y rango de fechas; por defecto el año actual.
- **FR-020**: Devolver a Borrador MUST pedir motivo; aprobar, iniciar ejecución y cerrar MUST pedir confirmación; cancelar una acción MUST pedir motivo e informar el efecto sobre los participantes.
- **FR-021**: El detalle del plan MUST mostrar datos generales, estado, aprobador, origen con enlace a la DNF, acciones con costo y horas totales, e historial.
- **FR-022**: El colaborador MUST ver solo sus acciones asignadas de planes Aprobado, Ejecutando o Cerrado, en orden cronológico, con nombre, competencia, fechas, formador, estado y detalle. Las acciones canceladas MUST mostrarse con estado "Cancelada" y su motivo, sin contar en el progreso ni en las próximas actividades.
- **FR-023**: El sistema MUST mostrar al colaborador el "% del plan completado" = acciones Finalizadas con asistencia Presente / acciones asignadas no canceladas, y las tres próximas acciones no canceladas con inicio de hoy en adelante, las más cercanas primero; sin acciones, un mensaje informativo.
- **FR-024**: Mientras no exista el módulo Actividades Formativas, el sistema MUST tratar las acciones sin asistencia registrada como no completadas.

**Roles y auditoría**

- **FR-025**: Solo el Administrador MUST poder crear, editar, cambiar de estado, cambiar participantes, cancelar acciones y consultar planes, calendario e historial; el Colaborador solo sus acciones y su progreso.
- **FR-026**: El sistema MUST registrar quién creó el plan y cuándo, y quién lo modificó por última vez y cuándo.
- **FR-027**: El sistema MUST registrar cada cambio de estado (usuario, fecha, estado anterior y nuevo, motivo) y, desde la aprobación, cada cambio de participantes y cancelación (usuario, fecha, acción afectada, detalle).

**Integraciones**

- **FR-028**: El módulo MUST consumir competencias de Estructura Empresarial, colaboradores de Gestión de Personal y cursos del Catálogo Formativo únicamente a través de sus fachadas, sin administrar esos datos.
- **FR-029**: El módulo MUST exponer una fachada para que la DNF solicite un borrador con una acción por competencia con brecha, esa competencia como objetivo y los colaboradores con brecha como participantes. El borrador MUST crearse con el año actual y un nombre automático derivado del código de la DNF (por ejemplo "Plan DNF DNF-0001"), único dentro del año y editable mientras el plan esté en Borrador.
- **FR-030**: El módulo MUST NOT registrar asistencia ni calificaciones; MUST dejar disponibles planes, acciones, horas, costos y estados para Actividades Formativas y Reportes.

### Key Entities *(include if feature involves data)*

- **Plan de formación**: período anual con código, nombre, año, descripción, estado, aprobador, origen, fecha de aprobación y datos de auditoría.
- **Acción formativa**: unidad del plan con nombre, curso de catálogo opcional, tipo, fechas, horas, formador, capacidad, costo y estado calculado o cancelado con motivo.
- **Competencia objetivo**: referencia a una competencia del diccionario maestro asociada a una acción.
- **Participante**: colaborador inscrito en una acción.
- **Historial del plan**: registro de cambios de estado y de cambios posteriores a la aprobación.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un administrador crea un plan con 5 acciones y participantes y lo guarda como Borrador en menos de 10 minutos.
- **SC-002**: El 100 % de los intentos de enviar a revisión un plan incompleto es rechazado con la lista de faltantes.
- **SC-003**: El 100 % de las transiciones no permitidas o hechas por un usuario sin el rol requerido es rechazado, y el 100 % de las permitidas queda en el historial.
- **SC-004**: Ningún plan, ni acción desde Revisión en adelante, puede eliminarse.
- **SC-005**: Ninguna acción supera su capacidad ni repite participantes en ningún caso.
- **SC-006**: El administrador localiza las acciones de un mes y tipo concretos en menos de 30 segundos con los filtros.
- **SC-007**: El colaborador ve su progreso y próximas actividades en una sola pantalla, sin ver nunca planes en Borrador o Revisión.
- **SC-008**: El borrador generado desde una DNF contiene una acción por competencia con brecha y el 100 % de los colaboradores con brecha como participantes.

## Assumptions

- Los costos se expresan en USD; el costo total, las horas totales y la cantidad de acciones del plan cuentan solo las acciones no canceladas.
- El aprobador puede ser el mismo administrador que crea o envía el plan (sin restricción de separación de funciones).
- La fecha de referencia para el estado de las acciones es la fecha actual en la zona horaria de la organización (tenant).
- «Próximas actividades» del colaborador son las tres acciones no canceladas con fecha de inicio igual o posterior a hoy, ordenadas de la más cercana a la más lejana; una acción ya En curso no se incluye.
- En el asistente, el aviso de cruce de fechas se muestra al instante para las acciones del propio plan y, para las de otros planes, al guardar el borrador.
- Los colaboradores elegibles son los que Gestión de Personal informa como disponibles.
- Los nombres de estado del plan son convención del sistema inspirada en Navarro (Paso 10); cambiarlos requiere decisión explícita del equipo.
- Actividades Formativas y Reportes no están implementados; el progreso depende de asistencia que aún no existe (ver FR-024).
- Fundamento bibliográfico: Navarro, *Evaluación del Desempeño* (Pasos 8, 10, 22); Bassett-Jones, *Strategic HRM* (cap. 10); Alles, *Las 50 herramientas de RRHH* (H.7, H.9, H.30, H.31, H.36A).
- Depende de las features ya implementadas Catálogo Formativo y DNF.

## Pantallas *(si la feature tiene UI)*

### Acciones formativas: Calendario y Lista (Administrador)
- **Propósito**: consultar las acciones de todos los planes por fecha y en tabla.
- **Datos que muestra**: lista con plan, nombre, competencia, tipo, fechas, formador, inscritos/capacidad, costo y estado; calendario mensual con color por tipo.
- **Acciones disponibles** (y quién puede hacerlas): filtrar y abrir detalle (solo Administrador).
- **Estados**: vacío, cargando, error, sin permisos.
- **Mockup**: `design/PlanAnualAdmin.dc.html`; el calendario mensual no está en `design/DESIGN.md` y debe agregarse antes de implementar (Constitución III).

### Planes de formación (Administrador)
- **Propósito**: gestionar el ciclo de vida de los planes.
- **Datos que muestra**: código, nombre, año, estado, cantidad de acciones, costo total, aprobador.
- **Acciones disponibles** (y quién puede hacerlas): Editar y Enviar a revisión (Borrador), Aprobar y Devolver (Revisión, solo aprobador designado), Iniciar ejecución (Aprobado), Cerrar plan (Ejecutando), Ver detalle (todos).
- **Estados**: vacío, cargando, error, sin permisos, modales de motivo y confirmación.
- **Mockup**: patrón Listado (`design/Competencias.dc.html`) con filtros de año, estado y búsqueda.

### Asistente de creación (Administrador)
- **Propósito**: crear o editar un plan en Borrador en 3 pasos.
- **Datos que muestra**: paso 1 datos generales; paso 2 lista de acciones con agregar, editar y quitar; paso 3 participantes por acción con capacidad usada, avisos de cruce y selector de aprobador.
- **Acciones disponibles** (y quién puede hacerlas): guardar borrador en cualquier paso (solo Administrador).
- **Estados**: vacío, cargando, error, validación, sin permisos, confirmación al quitar acción.
- **Mockup**: `design/PlanAnualAdmin.dc.html`; el stepper no está en los patrones actuales de `design/DESIGN.md`.

### Detalle del plan (Administrador)
- **Propósito**: revisar datos, acciones e historial.
- **Datos que muestra**: datos generales, estado, aprobador, origen con enlace a la DNF, acciones con costo y horas totales, historial.
- **Acciones disponibles** (y quién puede hacerlas): cambiar participantes y cancelar acciones según estado (solo Administrador).
- **Estados**: cargando, error, sin permisos.
- **Mockup**: patrón Detalle de un registro de `design/DESIGN.md`.

### Mi plan de formación (Colaborador)
- **Propósito**: que el colaborador conozca sus acciones y su avance.
- **Datos que muestra**: tarjetas cronológicas, barra "% del plan completado" y "Próximas actividades".
- **Acciones disponibles** (y quién puede hacerlas): "Ver detalle" (solo Colaborador, lo propio).
- **Estados**: sin acciones asignadas, cargando, error, sin permisos.
- **Mockup**: `design/PlanAnualColaborador.dc.html`; la barra de progreso y la tarjeta de acción deben agregarse a `design/DESIGN.md`.
