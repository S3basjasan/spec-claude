# Feature Specification: Detección de necesidades formativas (DNF)

**Feature Branch**: `006-deteccion-necesidades-formativas`

**Created**: 2026-10-15

**Status**: Draft

**Input**: User description: "Detección de necesidades formativas (DNF): comparar las competencias que exige el puesto de un colaborador con las que actualmente tiene, calculando una brecha por competencia. El resultado queda registrado como diagnóstico inmutable y es la entrada para generar el borrador del Plan de Formación. El módulo no administra el diccionario de competencias, los perfiles de puesto ni los niveles actuales: solo los consume. Fuente: specify-dnf.md."

## Clarifications

### Session 2026-10-15

- Q: ¿Se puede generar una nueva DNF para un colaborador que ya tiene una? → A: Se permite siempre; la más reciente (individual o masiva) reemplaza a las anteriores como brechas activas.
- Q: En una DNF masiva, ¿el borrador de plan es uno solo o uno por colaborador? → A: Un solo borrador por DNF que incluye las brechas de todos los colaboradores del departamento.
- Q: ¿Una DNF masiva incluye a los colaboradores de las subunidades del departamento? → A: Sí, los del departamento y los de todas sus subunidades.
- Q: ¿Se excluyen de la DNF los colaboradores sin vínculo laboral vigente? → A: Sí; no aparecen en el selector individual y en la masiva se omiten con aviso.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Generar una DNF individual (Priority: P1)

El Administrador (Gestor de Talento) elige un colaborador y el sistema compara, competencia por competencia, el nivel que exige su puesto con el nivel que el colaborador tiene hoy. El diagnóstico se guarda y se muestra en una tabla con la brecha en grados y en porcentaje, la prioridad y el estado.

**Why this priority**: Es el núcleo del módulo; sin el cálculo y el registro del diagnóstico no existe ninguna otra funcionalidad.

**Independent Test**: Con un colaborador que tiene puesto, perfil de competencias y niveles actuales, generar su DNF y verificar código, resultados y valores calculados.

**Acceptance Scenarios**:

1. **Given** un colaborador con nivel actual C y requerido A en una competencia, **When** el administrador genera su DNF, **Then** la fila muestra diferencia −2, brecha 50 %, prioridad Alta y estado "Con brecha".
2. **Given** un colaborador cuyo nivel actual iguala o supera el requerido, **When** se genera la DNF, **Then** el estado es "Cumple" o "Supera", la brecha es 0 % y no tiene prioridad.
3. **Given** una competencia exigida sin nivel actual registrado, **When** se genera la DNF, **Then** el estado es "Sin evaluación" y no se calcula diferencia, brecha ni prioridad.
4. **Given** un perfil con competencias inactivas, **When** se genera la DNF, **Then** esas competencias se omiten y se informa cuántas se omitieron.
5. **Given** una DNF generada, **When** después cambia el perfil del puesto o el nivel del colaborador, **Then** la DNF conserva los valores originales.

---

### User Story 2 - Consultar mi diagnóstico como colaborador (Priority: P1)

El colaborador ve, en su última DNF, una tarjeta por cada competencia con brecha, con barras de nivel requerido contra actual y la diferencia en grados; destaca sus tres brechas prioritarias y puede ir a los cursos sugeridos del Catálogo Formativo.

**Why this priority**: Es el valor directo para quien debe formarse y alimenta la sección de recomendados del catálogo.

**Independent Test**: Con una DNF existente con brechas, ingresar como ese colaborador y verificar tarjetas, orden de prioritarias y navegación al catálogo filtrado.

**Acceptance Scenarios**:

1. **Given** una DNF con brechas, **When** el colaborador abre su diagnóstico, **Then** ve una tarjeta por brecha con "Nivel actual: C · Requerido: A · Diferencia: −2".
2. **Given** más de tres brechas, **When** se muestra "Mis brechas prioritarias", **Then** aparecen tres, ordenadas por prioridad y, a igual prioridad, por mayor diferencia.
3. **Given** una tarjeta, **When** el colaborador pulsa "Ver cursos sugeridos", **Then** llega al Catálogo Formativo filtrado por esa competencia.
4. **Given** un colaborador sin DNF o sin brechas, **When** abre su diagnóstico, **Then** ve un mensaje informativo en lugar de tarjetas.
5. **Given** otro colaborador, **When** intenta consultar un diagnóstico ajeno, **Then** el acceso se deniega.

---

### User Story 3 - Generar una DNF masiva por departamento (Priority: P2)

El administrador elige un departamento; tras confirmar la cantidad de colaboradores a evaluar, el sistema genera una DNF con un resultado por colaborador que tenga puesto y perfil de competencias, y muestra primero un resumen por colaborador.

**Why this priority**: Multiplica la productividad del administrador, pero depende del cálculo individual.

**Independent Test**: Elegir un departamento con colaboradores completos e incompletos y verificar confirmación, resultados incluidos y avisos de exclusión.

**Acceptance Scenarios**:

1. **Given** un departamento, **When** el administrador inicia la generación, **Then** se pide confirmación indicando cuántos colaboradores se evaluarán.
2. **Given** colaboradores sin puesto o sin perfil, **When** se genera, **Then** se excluyen y se avisa quiénes.
3. **Given** la DNF generada, **When** el administrador elige un colaborador del resumen (cantidad de brechas y prioridad más alta), **Then** ve su tabla de diagnóstico.

---

### User Story 4 - Revisar, filtrar y visualizar el diagnóstico (Priority: P2)

El administrador ordena la tabla por prioridad, la filtra por prioridad y estado, y compara perfil ideal contra actual en un gráfico (radar o barras), con avisos informativos sobre omisiones, competencias sin evaluación y exclusiones.

**Why this priority**: Facilita la interpretación del resultado; el diagnóstico ya es útil sin ello.

**Independent Test**: Abrir una DNF con prioridades y estados mixtos y verificar orden, filtros, gráfico y avisos.

**Acceptance Scenarios**:

1. **Given** una DNF con varias prioridades, **When** se abre, **Then** la tabla va de mayor a menor prioridad por defecto.
2. **Given** filtros de prioridad y estado, **When** se aplican, **Then** la tabla muestra solo las filas que coinciden.
3. **Given** un colaborador seleccionado, **When** se muestra el gráfico, **Then** compara nivel requerido contra actual por competencia.

---

### User Story 5 - Historial y detalle de DNF (Priority: P2)

El administrador consulta el historial de diagnósticos, lo filtra y abre cualquier DNF en modo solo lectura. No existen acciones de editar ni eliminar.

**Why this priority**: Garantiza auditoría y trazabilidad, pero requiere DNF ya generadas.

**Independent Test**: Con varias DNF registradas, filtrar y abrir una en solo lectura; comprobar que no hay editar/eliminar.

**Acceptance Scenarios**:

1. **Given** DNF registradas, **When** el administrador abre el historial, **Then** ve código, fecha, alcance, colaborador o departamento, cantidad de brechas y prioridad más alta.
2. **Given** filtros por rango de fechas, colaborador, departamento y prioridad, **When** se aplican, **Then** el listado se acota en consecuencia.
3. **Given** una fila, **When** se abre, **Then** el detalle es solo lectura y muestra quién la generó y cuándo.

---

### User Story 6 - Generar borrador de Plan de Formación desde una DNF (Priority: P3)

Desde una DNF con brechas, el administrador pulsa "Generar Plan de Formación" para solicitar al módulo Plan de Formación un borrador basado en las brechas. Cada DNF genera como máximo un borrador.

**Why this priority**: Cierra el flujo de negocio, pero el Plan de Formación es otra feature.

**Independent Test**: Con una DNF con brechas, generar el borrador y verificar que el botón queda deshabilitado con el enlace al plan.

**Acceptance Scenarios**:

1. **Given** una DNF con brechas sin plan, **When** el administrador pulsa el botón, **Then** se solicita el borrador, se registra quién y cuándo, y se muestra el enlace.
2. **Given** una DNF sin brechas, **Then** el botón está deshabilitado con su motivo.
3. **Given** una DNF que ya generó plan, **Then** el botón está deshabilitado y se muestra el enlace al plan existente.

---

### Edge Cases

- Colaborador sin puesto o sin perfil de competencias en una DNF individual: no se genera y se informa el motivo.
- Perfil con todas las competencias inactivas o sin ninguna evaluable: se informa que no hay competencias evaluables.
- Departamento sin colaboradores elegibles: no se genera DNF.
- Dos administradores generan DNF simultáneas: cada una recibe un código único y secuencial sin duplicados.
- Competencia que luego se inactiva o cuyo perfil cambia: la DNF histórica no cambia.
- Nivel requerido A con actual D (diferencia −3): prioridad Crítica y brecha 75 %.
- Diferencia de 1 grado: Media si la competencia es Cardinal, Baja si es Específica.
- Fallo de la solicitud del plan: no se marca la DNF como con plan y se puede reintentar.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema MUST permitir al Administrador generar una DNF individual para un colaborador seleccionado.
- **FR-002**: El sistema MUST permitir al Administrador generar una DNF masiva por departamento, con un resultado por cada colaborador que tenga puesto y perfil de competencias, incluyendo a los colaboradores de todas las subunidades del departamento elegido.
- **FR-002a**: El sistema MUST excluir de toda DNF a los colaboradores sin vínculo laboral vigente: no se ofrecen en el selector individual y, en una DNF masiva, se omiten informando el motivo en el aviso de exclusiones.
- **FR-003**: El sistema MUST asignar a cada DNF un código único secuencial con formato DNF-0001, no editable, y la fecha de generación.
- **FR-004**: El sistema MUST exigir colaborador cuando el alcance es Individual y departamento cuando es Departamento.
- **FR-005**: El sistema MUST traducir los grados a D=1, C=2, B=3, A=4 (A es el de mayor desarrollo) para el cálculo.
- **FR-006**: El sistema MUST calcular Diferencia (grados) = nivel actual − nivel requerido (negativa = brecha, cero = cumple, positiva = supera).
- **FR-007**: El sistema MUST calcular Brecha (%) = (requerido − actual) / requerido × 100 solo si el actual es menor que el requerido; en los demás casos es 0.
- **FR-008**: El sistema MUST marcar "Sin evaluación" las competencias exigidas sin nivel actual, sin calcular diferencia, brecha ni prioridad.
- **FR-009**: El sistema MUST evaluar solo competencias activas del perfil del puesto, omitir las inactivas e informar cuántas se omitieron.
- **FR-010**: El sistema MUST asignar prioridad solo a competencias con brecha: Crítica (3 grados), Alta (2), Media (1 grado, Cardinal), Baja (1 grado, Específica).
- **FR-011**: El sistema MUST asignar a cada brecha un estado: Con brecha, Cumple, Supera o Sin evaluación.
- **FR-012**: El sistema MUST guardar nivel requerido, nivel actual, diferencia, brecha y prioridad tal como se calcularon, sin que cambios posteriores del perfil o de los niveles los alteren.
- **FR-013**: El sistema MUST impedir editar o eliminar una DNF una vez generada y no ofrecer esas acciones.
- **FR-014**: El sistema MUST considerar brecha activa de un colaborador a toda brecha "Con brecha" de su DNF más reciente (individual o masiva), y exponerla al Catálogo Formativo. Las DNF anteriores se conservan en el historial pero dejan de ser la fuente de brechas activas.
- **FR-014a**: El sistema MUST permitir generar una nueva DNF para un colaborador que ya tiene una, sin bloqueo ni advertencia.
- **FR-015**: La vista de generación del administrador MUST ser un asistente de dos pasos: selección (colaborador con búsqueda, o departamento) y diagnóstico con tabla de competencia, requerido, actual, brecha en grados, brecha %, prioridad y estado.
- **FR-016**: La tabla MUST ordenarse por defecto de mayor a menor prioridad y permitir filtrar por prioridad y estado.
- **FR-017**: En alcance Departamento, el sistema MUST mostrar primero un resumen por colaborador (cantidad de brechas y prioridad más alta) y, al elegir uno, su tabla.
- **FR-018**: El sistema MUST mostrar un gráfico de radar o barras comparando perfil requerido y actual del colaborador seleccionado.
- **FR-019**: El sistema MUST mostrar avisos cuando se omitieron competencias inactivas, hay competencias sin evaluación o se excluyó un colaborador por no tener puesto o perfil.
- **FR-020**: La generación masiva MUST pedir confirmación indicando la cantidad de colaboradores que se evaluarán.
- **FR-021**: El sistema MUST ofrecer un historial con código, fecha, alcance, colaborador o departamento, cantidad de brechas y prioridad más alta, filtrable por rango de fechas, colaborador, departamento y prioridad. El filtro de prioridad coincide con la **prioridad más alta** de la DNF; el filtro de colaborador incluye las DNF masivas que lo evaluaron.
- **FR-022**: Cada fila del historial MUST abrir el detalle de la DNF en modo solo lectura.
- **FR-023**: El colaborador MUST ver solo su propio diagnóstico (última DNF) como tarjetas por competencia con brecha, con barras requerido contra actual y la diferencia en grados.
- **FR-024**: El sistema MUST mostrar al colaborador "Mis brechas prioritarias" con tres competencias ordenadas por prioridad y, a igual prioridad, por mayor diferencia.
- **FR-025**: Cada tarjeta MUST ofrecer "Ver cursos sugeridos", que navega al Catálogo Formativo filtrado por esa competencia.
- **FR-026**: Si el colaborador no tiene DNF o no tiene brechas, el sistema MUST mostrar un mensaje informativo en lugar de tarjetas.
- **FR-027**: El botón "Generar Plan de Formación" MUST ser visible solo para el administrador, solicitar al módulo Plan de Formación un borrador a partir de las brechas, y permitir como máximo un borrador por DNF. En una DNF masiva el borrador es uno solo e incluye las brechas de todos los colaboradores evaluados.
- **FR-028**: El botón de plan MUST estar deshabilitado, con su motivo, si la DNF no tiene brechas o ya generó un plan, mostrando en este último caso el enlace al plan.
- **FR-029**: Las acciones de generar DNF, consultar historial/detalle y generar plan MUST estar restringidas al Administrador; el colaborador solo accede a su propio diagnóstico.
- **FR-030**: El sistema MUST registrar quién generó cada DNF y cuándo, y quién generó el borrador de plan desde ella y cuándo.
- **FR-031**: El sistema MUST consumir perfiles de puesto, competencias, departamentos, colaboradores y niveles actuales exclusivamente por los contratos públicos de Estructura Empresarial y Gestión de Personal, sin administrarlos.
- **FR-032**: El sistema MUST exponer sus DNF registradas para el indicador de brechas cerradas del módulo de Reportes.

### Key Entities *(include if feature involves data)*

- **DNF**: Diagnóstico puntual e inmutable. Código, alcance (Individual/Departamento), colaborador o departamento, fecha de generación, generador, cantidad de competencias inactivas omitidas, referencia opcional al plan generado (con quién y cuándo).
- **Resultado por colaborador**: Resultado de una DNF para un colaborador; agrupa sus brechas. Una DNF individual tiene uno; una masiva, uno por colaborador elegible.
- **Brecha**: Una por competencia y colaborador. Competencia, tipo de competencia, nivel requerido, nivel actual, diferencia, brecha %, prioridad y estado.
- **Colaborador, Puesto, Perfil de competencias, Competencia, Departamento**: entidades externas consumidas de otros módulos.
- **Plan de Formación (borrador)**: entidad de otro módulo, referenciada desde la DNF.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El administrador genera una DNF individual y ve su resultado en menos de 10 segundos desde que avanza al paso de diagnóstico.
- **SC-002**: Para un departamento de hasta 100 colaboradores, la DNF masiva se genera y muestra su resumen en menos de 1 minuto.
- **SC-003**: El 100 % de las brechas calculadas coincide con las reglas de diferencia, porcentaje y prioridad en una batería de casos de verificación que cubre las 16 combinaciones de nivel requerido/actual y ambos tipos de competencia.
- **SC-004**: El 100 % de las DNF generadas conserva sus valores originales tras modificar el perfil de puesto o los niveles del colaborador.
- **SC-005**: Ningún usuario puede editar o eliminar una DNF, ni un colaborador ver un diagnóstico ajeno, en el 100 % de los intentos probados.
- **SC-006**: Un colaborador con brechas llega desde su diagnóstico a los cursos sugeridos de una competencia en un solo clic.
- **SC-007**: El administrador genera un borrador de plan desde una DNF con brechas en menos de 3 clics, y el 100 % de las DNF genera como máximo un borrador.
- **SC-008**: Cada DNF y cada borrador generado queda con autor y fecha consultables en el 100 % de los casos.

## Assumptions

- La autenticación y los roles (Administrador y Colaborador) ya existen en el sistema; el colaborador está asociado a una ficha de Gestión de Personal.
- Estructura Empresarial y Gestión de Personal ofrecen, mediante contratos públicos, el perfil de competencias con grado requerido y tipo (Cardinal/Específica), los departamentos, los colaboradores con su puesto y el nivel actual por competencia.
- El Catálogo Formativo y el Plan de Formación disponen de los puntos de integración descritos; este módulo no gestiona el plan.
- Una DNF masiva incluye solo colaboradores del departamento y sus subunidades con puesto y perfil; los demás se excluyen con aviso.
- Una DNF masiva con colaboradores sin brechas se genera igualmente, y el botón de plan se habilita si existe al menos una brecha en cualquiera de ellos; el borrador resultante es único para toda la DNF.
- Las prioridades y la escala numérica son decisiones de diseño del sistema (no literales en la bibliografía) y pueden cambiarse solo por decisión explícita del equipo.
- El sistema es multi-tenant: cada DNF pertenece al tenant del administrador que la genera, todas las consultas se acotan a ese tenant y el código DNF-NNNN es secuencial y único **por tenant**.
- «Departamento» equivale a cualquier unidad organizacional activa de Estructura Empresarial; la DNF masiva incluye también sus subunidades.
- Fundamento: Alles (H.11), Navarro (Paso 7) y Bassett-Jones (Cap. 10).
- Fuera de alcance: administrar competencias, perfiles, niveles actuales, el Plan de Formación y los reportes.

## Pantallas *(si la feature tiene UI)*

### Asistente de generación de DNF (Administrador)
- **Propósito**: generar una DNF individual o masiva y revisar su diagnóstico.
- **Datos que muestra**: paso 1 selector de colaborador con búsqueda o de departamento; paso 2 resumen por colaborador (solo masiva), tabla de diagnóstico, gráfico requerido contra actual y avisos.
- **Acciones disponibles** (y quién puede hacerlas): generar DNF y "Generar Plan de Formación" (solo Administrador); filtrar por prioridad y estado. Sin editar ni eliminar.
- **Estados**: vacío (sin selección), cargando, error, sin permisos, sin competencias evaluables, confirmación de generación masiva.
- **Mockup**: `design/DNFAdmin.dc.html`; el stepper, el gráfico radar/barras, los Badges de prioridad y el resumen por colaborador no están en los patrones actuales de `design/DESIGN.md`, por lo que debe ampliarse antes de implementar (Constitución III).

### Historial y detalle de DNF (Administrador)
- **Propósito**: auditar los diagnósticos generados.
- **Datos que muestra**: código, fecha, alcance, colaborador o departamento, cantidad de brechas, prioridad más alta; el detalle en solo lectura con autor y fecha.
- **Acciones disponibles** (y quién puede hacerlas): filtrar y abrir detalle (solo Administrador).
- **Estados**: vacío, cargando, error, sin permisos.
- **Mockup**: patrón Listado (`design/Competencias.dc.html`) con filtros de fecha, colaborador, departamento y prioridad; el detalle reutiliza la tabla de `design/DNFAdmin.dc.html`.

### Mi diagnóstico (Colaborador)
- **Propósito**: que el colaborador conozca sus brechas y acceda a cursos sugeridos.
- **Datos que muestra**: "Mis brechas prioritarias" (tres) y tarjetas por competencia con barras requerido contra actual y diferencia en grados.
- **Acciones disponibles** (y quién puede hacerlas): "Ver cursos sugeridos" (solo Colaborador, su propio diagnóstico).
- **Estados**: sin DNF, sin brechas, cargando, error, sin permisos.
- **Mockup**: `design/DNFColaborador.dc.html`; requiere ampliar `design/DESIGN.md` con la tarjeta de brecha y las barras comparativas.
