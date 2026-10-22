# Feature Specification: Adecuación a puesto futuro y mapa de talento

**Feature Branch**: `020-adecuacion-puesto-futuro`

**Created**: 2026-10-21

**Status**: Draft

**Input**: User description: "Adecuación a puesto futuro y mapa de talento (documento fuente `specify-adecuacion.md`, copiado en esta carpeta). Mide qué tan preparado está un colaborador para un puesto objetivo comparando, en tres dimensiones (competencias, conocimientos y experiencia), lo que el puesto exige contra lo que la persona tiene hoy. Muestra las brechas hacia el puesto futuro junto a la adecuación al puesto actual, estima el tiempo para cerrarlas, permite comparar candidatos para un mismo puesto y ofrece un mapa de talento. El administrador (Gestor de Talento) genera adecuaciones, compara candidatos, consulta el mapa y mantiene los parámetros; el colaborador consulta «Mi adecuación». La feature aporta evidencia, no decide ni ejecuta ascensos, y no administra puestos, competencias, conocimientos ni datos de los colaboradores."

## Clarifications

### Session 2026-10-21

- Q: ¿Cómo se define el puesto objetivo por defecto si el colaborador tiene más de una inscripción Activa? → A: El administrador elige cuál inscripción usar; si solo hay una, se preselecciona.
- Q: ¿Qué ve el colaborador si tiene adecuaciones vigentes para varios puestos objetivo? → A: La vigente de cada puesto objetivo propio, con un selector para cambiar entre ellos; la más reciente aparece primero.
- Q: ¿El colaborador ve su adecuación al guardarse o el administrador debe liberarla? → A: La ve de inmediato al guardarse; no hay paso de liberación.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Generar la adecuación de un colaborador a un puesto objetivo (Priority: P1)

El administrador abre el asistente de generación. En el paso de Selección elige un colaborador (lista con búsqueda) y su puesto objetivo: por defecto, el nivel objetivo de su inscripción Activa en una ruta de carrera; o bien un puesto activo cualquiera («puesto directo»), caso en que puede ingresar los años de experiencia requeridos. En el paso de Diagnóstico ve, sin guardar nada, tres paneles (Competencias | Conocimientos | Experiencia) que comparan lo requerido por el puesto actual, el valor de la persona y lo requerido por el puesto objetivo, con la diferencia y el estado de cada requisito. En cada brecha ingresa el tiempo estimado de cierre en meses. En el paso de Confirmación ve la preparación calculada, escribe observaciones opcionales y confirma: se guarda la adecuación con código ADE-0001, ADE-0002…

**Why this priority**: Es el núcleo de la feature; sin diagnósticos guardados no hay historial, comparativa, mapa ni vista del colaborador.

**Independent Test**: Con un colaborador inscrito en una ruta (puesto actual con competencia X en grado C, nivel objetivo con X en grado B y 5 años de experiencia mínima, colaborador con X en grado C y 3 años), generar la adecuación y verificar que X muestra diferencia −1 (Con brecha), la experiencia diferencia −2, que se exige tiempo de cierre en ambas, que la preparación se calcula según el umbral y que el código es ADE-0001.

**Acceptance Scenarios**:

1. **Given** un colaborador con inscripción Activa, **When** el administrador lo elige en el paso de Selección, **Then** el puesto objetivo propuesto es el nivel objetivo de esa inscripción y la experiencia requerida es la experiencia mínima de ese nivel; el administrador puede cambiar a «puesto directo».
2. **Given** que se elige «puesto directo», **When** el administrador no ingresa la experiencia requerida, **Then** la dimensión Experiencia queda sin evaluarse y el diagnóstico lo indica con un aviso informativo; si la ingresa, se evalúa en años.
3. **Given** un colaborador sin inscripción Activa, **When** el administrador lo elige, **Then** solo puede continuar escogiendo un puesto directo y se le informa por qué no hay objetivo por defecto.
4. **Given** el paso de Diagnóstico, **When** el requerido del puesto objetivo es B y el valor de la persona es C, **Then** la diferencia es −1 y el estado es «Con brecha»; si fueran iguales, la diferencia es 0 y el estado es «Cumple»; si la persona tuviera A, la diferencia es +1 y el estado es «Supera».
5. **Given** un requisito para el que el colaborador no tiene valor registrado, **When** se muestra el diagnóstico, **Then** el estado es «Sin evaluación», no se calcula diferencia y aparece un aviso informativo.
6. **Given** que el perfil del puesto objetivo incluye competencias o conocimientos inactivos, **When** se muestra el diagnóstico, **Then** esos elementos se omiten y un aviso informativo indica cuántos se omitieron.
7. **Given** una o más brechas sin tiempo estimado de cierre, **When** el administrador intenta pasar al paso de Confirmación, **Then** el sistema lo impide y señala los resultados que faltan; el tiempo debe ser un número entero de meses mayor que cero y de 60 como máximo.
8. **Given** el paso de Confirmación, **When** el administrador confirma, **Then** se guarda la adecuación con código secuencial único, fecha de generación, usuario que la generó, puesto actual copiado del momento, preparación y todos los resultados tal como se calcularon.
9. **Given** una adecuación guardada, **When** se consulta su detalle, **Then** es de solo lectura: no existen acciones de editar ni de eliminar.
10. **Given** una adecuación guardada y luego un cambio en el perfil del puesto o en el nivel del colaborador, **When** se vuelve a abrir la adecuación, **Then** sus valores no cambian.

---

### User Story 2 - Consultar «Mi adecuación» como colaborador (Priority: P1)

El colaborador abre «Mi adecuación» y ve su adecuación vigente a su puesto objetivo en tres tarjetas (Competencias, Conocimientos, Experiencia). Cada requisito muestra barras de requerido contra actual y la diferencia (ejemplo: «Nivel actual: C · Requerido: B · Diferencia: −1»). Ve la insignia de preparación y el tiempo estimado de cierre de cada brecha. En cada brecha de competencia o conocimiento dispone de «Ver cursos sugeridos», que lo lleva al Catálogo Formativo filtrado por ese elemento; en las brechas de experiencia ve «Se desarrolla con práctica; revisa tu plan de desarrollo». Un aviso permanente aclara: «Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.»

**Why this priority**: Es el valor directo para la persona evaluada y exige la regla de privacidad (solo lo propio, sin puntajes de comparación).

**Independent Test**: Con un colaborador con adecuación vigente con una brecha de competencia y una de experiencia, abrir «Mi adecuación» y verificar tarjetas, diferencia, tiempo de cierre, botón de cursos que filtra el catálogo, mensaje de experiencia y aviso; verificar que no aparece ningún puntaje ni dato de otros candidatos.

**Acceptance Scenarios**:

1. **Given** un colaborador con adecuación vigente, **When** abre «Mi adecuación», **Then** ve únicamente sus propias adecuaciones vigentes, una por puesto objetivo, con un selector para cambiar entre ellas (la más reciente primero) y la fecha en que se generó cada una.
2. **Given** una brecha de competencia o conocimiento, **When** el colaborador pulsa «Ver cursos sugeridos», **Then** llega al Catálogo Formativo filtrado por ese elemento.
3. **Given** una brecha de experiencia, **When** se muestra la tarjeta, **Then** aparece el mensaje «Se desarrolla con práctica; revisa tu plan de desarrollo» y no el botón de cursos.
4. **Given** un colaborador sin puesto objetivo o sin adecuación generada, **When** abre «Mi adecuación», **Then** ve un estado vacío que lo explica.
5. **Given** cualquier colaborador, **When** consulta su adecuación, **Then** no ve puntajes de comparación, ranking ni datos de otros candidatos, y tampoco puede acceder a la adecuación de otra persona por ningún medio.
6. **Given** que la lista de rutas indica «Ver mi adecuación» (feature 019), **When** el colaborador la pulsa, **Then** llega a «Mi adecuación».

---

### User Story 3 - Consultar el historial de evaluaciones (Priority: P2)

El administrador consulta la pestaña «Evaluaciones» con el historial de adecuaciones: código, fecha, colaborador, puesto objetivo, preparación y cantidad de brechas. Filtra por fecha, colaborador, puesto y preparación; cada fila abre el detalle en solo lectura, que incluye quién la generó y cuándo.

**Why this priority**: Preserva la trazabilidad y es la base de auditoría, pero depende de que ya existan adecuaciones.

**Independent Test**: Con varias adecuaciones de distintos colaboradores, puestos y preparaciones, aplicar cada filtro y verificar que la lista y los conteos coinciden; abrir un detalle y verificar que es de solo lectura.

**Acceptance Scenarios**:

1. **Given** adecuaciones guardadas, **When** el administrador abre «Evaluaciones», **Then** las ve ordenadas de la más reciente a la más antigua con paginación.
2. **Given** filtros por rango de fechas, colaborador, puesto objetivo y preparación, **When** los aplica (solos o combinados), **Then** la lista muestra solo las que cumplen todos.
3. **Given** un colaborador con varias adecuaciones para el mismo puesto objetivo, **When** se muestra el historial, **Then** todas aparecen, y la más reciente se identifica como vigente.
4. **Given** el detalle de una adecuación, **When** se abre, **Then** muestra datos generales, resultados por dimensión, observaciones, usuario y fecha de generación, sin acciones de edición.

---

### User Story 4 - Comparar candidatos para un puesto (Priority: P2)

El administrador abre «Comparar candidatos», elige un puesto objetivo y los candidatos. El sistema toma la adecuación vigente de cada candidato para ese puesto y calcula un ranking con puntaje, preparación y cantidad de brechas. Los pesos por dimensión se pueden editar solo para esa consulta. Quien no tiene adecuación para ese puesto aparece señalado y no entra al ranking; quien tiene preparación Incompleta se lista aparte, sin puntaje. El resultado se calcula en el momento y no se guarda.

**Why this priority**: Apoya la decisión entre varias personas, pero requiere adecuaciones previas y parámetros.

**Independent Test**: Con tres candidatos con adecuaciones vigentes (uno Incompleto) y uno sin adecuación, comparar con los pesos por defecto y verificar el ranking calculado a mano, el candidato Incompleto aparte, el candidato sin adecuación señalado, y que cambiar los pesos de la consulta no modifica los parámetros guardados.

**Acceptance Scenarios**:

1. **Given** un candidato con cumplimiento de 0,75 en Competencias, 1,0 en Conocimientos y 0,5 en Experiencia y pesos 40/30/30, **When** se compara, **Then** su puntaje es 0,4×0,75 + 0,3×1,0 + 0,3×0,5 = 0,75 (75 %).
2. **Given** el cumplimiento de una dimensión, **When** se calcula, **Then** es el promedio, sobre sus requisitos, de «valor de la persona ÷ requerido», con tope de 1 por requisito (quien supera lo requerido no pasa de 1).
3. **Given** un candidato cuya dimensión Experiencia no se evaluó, **When** se calcula su puntaje, **Then** esa dimensión se excluye y los pesos de las demás se reescalan para sumar 100 %.
4. **Given** candidatos con preparación Incompleta, **When** se muestra el resultado, **Then** aparecen en una lista aparte, sin puntaje y sin posición en el ranking.
5. **Given** un candidato sin adecuación vigente para el puesto, **When** se muestra el resultado, **Then** aparece señalado como «Sin adecuación para este puesto» y fuera del ranking.
6. **Given** que el administrador edita los pesos de la consulta, **When** la suma no es 100, **Then** el sistema lo impide e informa; si es 100, recalcula el ranking sin cambiar los parámetros guardados.
7. **Given** una comparación mostrada, **When** se cierra o se recarga, **Then** el resultado no queda guardado en ninguna parte.

---

### User Story 5 - Ver el mapa de talento (Priority: P2)

El administrador abre «Mapa de talento»: una tabla con la adecuación vigente de cada colaborador por puesto objetivo, con colaborador, puesto actual, puesto objetivo, preparación, cantidad de brechas y tiempo máximo de cierre. La filtra por departamento, ruta y preparación. Es el punto de partida para decidir quién entra a un Programa de Talentos.

**Why this priority**: Da la vista agregada para decidir, pero depende de las adecuaciones ya generadas.

**Independent Test**: Con adecuaciones de colaboradores de dos departamentos y dos rutas, verificar que solo aparece la vigente por colaborador y puesto, que brechas y tiempo máximo son correctos y que cada filtro reduce la tabla como se espera.

**Acceptance Scenarios**:

1. **Given** un colaborador con dos adecuaciones al mismo puesto objetivo, **When** se abre el mapa, **Then** aparece una sola fila, la de la más reciente.
2. **Given** un colaborador con adecuaciones a dos puestos objetivo distintos, **When** se abre el mapa, **Then** aparece una fila por cada puesto objetivo.
3. **Given** una fila, **When** se muestra, **Then** la cantidad de brechas es el número de resultados Con brecha y el tiempo máximo de cierre es el mayor tiempo estimado entre ellas (vacío si no hay brechas).
4. **Given** filtros por departamento, ruta y preparación, **When** se aplican, **Then** la tabla muestra solo las filas que cumplen todos.

---

### User Story 6 - Mantener los parámetros de la adecuación (Priority: P3)

El administrador abre «Parámetros» y consulta o edita los pesos por dimensión (iniciales: Competencias 40 %, Conocimientos 30 %, Experiencia 30 %; deben sumar 100) y el umbral de preparación en meses (inicial 12). Cada cambio queda en un historial con usuario, fecha, valor anterior y valor nuevo.

**Why this priority**: Los valores iniciales permiten operar sin esta pantalla; ajustarlos es una mejora.

**Independent Test**: Cambiar el umbral de 12 a 6 y verificar que genera una entrada de historial; generar una adecuación con una brecha de 8 meses y verificar que ahora es «No lista»; intentar guardar pesos que suman 90 y verificar el rechazo.

**Acceptance Scenarios**:

1. **Given** un sistema recién instalado, **When** el administrador abre «Parámetros», **Then** ve pesos 40/30/30 y umbral de 12 meses.
2. **Given** pesos cuya suma no es 100 o un peso negativo, **When** intenta guardar, **Then** el sistema lo rechaza con un mensaje claro y no cambia nada.
3. **Given** un umbral que no es un entero mayor que cero, **When** intenta guardar, **Then** el sistema lo rechaza.
4. **Given** un cambio válido, **When** se guarda, **Then** se registra en el historial con usuario, fecha, valor anterior y valor nuevo, y rige para las adecuaciones que se generen en adelante, sin alterar las ya guardadas.

---

### User Story 7 - Continuar hacia un plan de desarrollo o un Programa de Talentos (Priority: P3)

Después de guardar una adecuación, el administrador ve los botones «Crear plan de desarrollo» e «Incluir en Programa de Talentos». Quedan deshabilitados si la adecuación no tiene brechas. Como el Plan de Desarrollo Individual aún no está implementado, en esta entrega los botones se muestran deshabilitados con la explicación de que dependen de esa feature.

**Why this priority**: Anticipa el encadenamiento con una feature futura sin bloquear el valor actual.

**Independent Test**: Guardar una adecuación con brechas y otra sin brechas y verificar el estado y el texto explicativo de los botones en ambos casos.

**Acceptance Scenarios**:

1. **Given** una adecuación recién guardada sin brechas, **When** se muestra la confirmación, **Then** ambos botones están deshabilitados con el motivo «No hay brechas».
2. **Given** una adecuación con brechas mientras el Plan de Desarrollo Individual no exista, **When** se muestra la confirmación, **Then** los botones se muestran deshabilitados con el motivo «Disponible cuando exista el Plan de Desarrollo Individual».

---

### Edge Cases

- **Perfil del puesto objetivo sin competencias ni conocimientos activos**: el diagnóstico solo puede evaluar Experiencia; si tampoco hay experiencia requerida, no hay nada que evaluar y el sistema no permite guardar, con un mensaje claro.
- **Conocimientos sin escala de grado**: mientras Estructura Empresarial no entregue conocimientos con grado A–D, la dimensión Conocimientos queda desactivada: no genera resultados, el diagnóstico muestra un aviso informativo y los pesos de las demás dimensiones se reescalan. Esto no vuelve «Incompleta» a la adecuación.
- **Colaborador sin dato de experiencia acumulada** con experiencia requerida: Experiencia queda «Sin evaluación».
- **Competencia del puesto objetivo que el colaborador no tiene registrada**: «Sin evaluación»; no se asume grado D.
- **Competencia requerida por el puesto objetivo pero no por el actual**: el requerido actual se muestra vacío («No exigida»), sin generar brecha.
- **Requerido de experiencia igual a 0**: el cumplimiento de ese requisito es 1 y el estado es Cumple.
- **Colaborador dado de baja** o sin vínculo laboral vigente: no se puede elegir para una nueva adecuación; las ya guardadas se conservan.
- **Inscripción Activa que cambia entre pasos del asistente** (otro administrador la retira o cambia el nivel objetivo): al confirmar, el sistema detecta que el puesto objetivo ya no coincide con el elegido y pide volver a generar el diagnóstico.
- **Puesto objetivo igual al puesto actual**: se permite (diagnóstico de adecuación al puesto actual); el sistema avisa de que no es un puesto futuro.
- **Dos administradores confirman al mismo tiempo**: cada una recibe su propio código sin duplicados ni saltos que repitan un número.
- **Todas las brechas con tiempo menor o igual al umbral**: preparación «En desarrollo»; basta una por encima del umbral para «No lista».
- **Hay a la vez brechas y requisitos Sin evaluación**: prevalece «Incompleta» (no se puede clasificar).
- **Cambio de parámetros tras guardar una adecuación**: la preparación guardada no se recalcula.
- **Fuente externa no disponible** (Estructura Empresarial, Gestión de Personal, Rutas): se muestra un aviso con «Reintentar», sin datos inventados, y no se permite guardar un diagnóstico incompleto por falla.

## Requirements *(mandatory)*

### Functional Requirements

**Generación y datos de la adecuación**

- **FR-001**: El sistema MUST permitir al administrador generar una adecuación mediante un asistente de tres pasos (Selección, Diagnóstico y Confirmación), y MUST guardar la adecuación solo en el último.
- **FR-002**: El sistema MUST asignar a cada adecuación un código único, secuencial, con formato ADE-0001, no editable.
- **FR-003**: El colaborador MUST ser obligatorio; solo se pueden elegir colaboradores con vínculo laboral vigente.
- **FR-004**: El sistema MUST copiar al momento de generar el puesto actual del colaborador desde Gestión de Personal.
- **FR-005**: El puesto objetivo MUST ser obligatorio y definirse desde el nivel objetivo de una inscripción Activa del colaborador (opción por defecto; con varias inscripciones Activas el administrador elige cuál, y con una sola se preselecciona) o como puesto directo (cualquier puesto activo).
- **FR-006**: Cuando el objetivo proviene de una inscripción, el sistema MUST guardar la referencia a la inscripción de origen y tomar la experiencia requerida de la experiencia mínima del nivel.
- **FR-007**: Cuando el objetivo es un puesto directo, el sistema MUST permitir ingresar la experiencia requerida (años, número mayor o igual a 0); si no se ingresa, la dimensión Experiencia MUST quedar sin evaluarse.
- **FR-008**: El sistema MUST registrar la fecha de generación, el usuario que la generó y las observaciones opcionales (máximo 500 caracteres).
- **FR-009**: El sistema MUST generar un resultado por cada requisito evaluado con: dimensión (Competencia, Conocimiento o Experiencia), elemento (no aplica a Experiencia), requerido en el puesto actual, requerido en el puesto objetivo, valor de la persona, diferencia, estado y tiempo estimado de cierre.
- **FR-010**: Los resultados MUST evaluar las competencias y los conocimientos activos del perfil del puesto objetivo, omitiendo los inactivos e informando cuántos se omitieron.

**Cálculo**

- **FR-011**: El sistema MUST traducir los grados a escala numérica con el servicio de cálculo ya existente de Detección de Necesidades de Formación (D = 1, C = 2, B = 3, A = 4) y NO MUST duplicar esa fórmula.
- **FR-012**: La diferencia MUST ser el valor de la persona menos lo requerido por el puesto objetivo, en grados para competencias y conocimientos y en años para experiencia.
- **FR-013**: El estado MUST ser «Con brecha» si la diferencia es negativa, «Cumple» si es cero, «Supera» si es positiva y «Sin evaluación» si la persona no tiene valor registrado (en cuyo caso no se calcula diferencia).
- **FR-014**: El requerido en el puesto actual MUST mostrarse para comparar y NO MUST generar brecha.
- **FR-015**: El sistema MUST exigir un tiempo estimado de cierre (meses enteros entre 1 y 60) en cada resultado Con brecha antes de guardar, y no admitirlo en los demás estados.
- **FR-016**: La preparación MUST clasificarse así: «Incompleta» si algún requisito está Sin evaluación; si no, «Lista» si no hay brechas; «En desarrollo» si hay brechas y todas tienen tiempo menor o igual al umbral; «No lista» si al menos una brecha supera el umbral.
- **FR-017**: El umbral MUST ser el vigente al momento de generar, y la preparación MUST guardarse con la adecuación sin recalcularse después.

**Ciclo de vida y auditoría**

- **FR-018**: Una adecuación guardada MUST ser inmutable: no se edita ni se elimina, y sus valores MUST conservarse aunque cambien el perfil del puesto o el nivel del colaborador.
- **FR-019**: La adecuación vigente de un colaborador para un puesto objetivo MUST ser la más reciente.
- **FR-020**: El historial de adecuaciones MUST servir como registro de auditoría de los diagnósticos, con usuario y fecha de generación.
- **FR-021**: Cada cambio de parámetros MUST registrarse con usuario, fecha, valor anterior y valor nuevo.

**Historial, comparativa y mapa**

- **FR-022**: El sistema MUST ofrecer un historial paginado con código, fecha, colaborador, puesto objetivo, preparación y cantidad de brechas, con filtros por fecha, colaborador, puesto y preparación, y detalle de solo lectura.
- **FR-023**: La comparativa MUST usar la adecuación vigente de cada candidato para el puesto elegido; quien no la tenga MUST aparecer señalado y quedar fuera del ranking.
- **FR-024**: El puntaje MUST ser la suma de los pesos de cada dimensión multiplicados por su cumplimiento; el cumplimiento de una dimensión MUST ser el promedio, sobre sus requisitos, de valor de la persona dividido entre lo requerido, con tope de 1 por requisito.
- **FR-025**: Las dimensiones no evaluadas MUST excluirse del puntaje y los pesos restantes MUST reescalarse a 100 %.
- **FR-026**: Los candidatos con preparación Incompleta MUST listarse aparte, sin puntaje.
- **FR-027**: La comparativa MUST permitir editar los pesos solo para la consulta (suma 100), sin modificar los parámetros guardados; su resultado MUST calcularse en el momento y no guardarse.
- **FR-028**: El mapa de talento MUST mostrar la adecuación vigente de cada colaborador por puesto objetivo con colaborador, puesto actual, puesto objetivo, preparación, cantidad de brechas y tiempo máximo de cierre, filtrable por departamento, ruta y preparación.

**Parámetros**

- **FR-029**: El sistema MUST mantener los pesos por dimensión (iniciales 40/30/30, no negativos, suma 100) y el umbral de preparación (entero en meses, inicial 12), editables solo con el permiso de parámetros.

**Vista del colaborador, permisos y privacidad**

- **FR-030**: El colaborador MUST poder ver únicamente su propia adecuación vigente de cada puesto objetivo (con selector entre ellos, la más reciente primero), sin puntajes de comparación ni datos de otros candidatos, y el sistema MUST rechazar cualquier intento de consultar datos ajenos.
- **FR-031**: Cada brecha de competencia o conocimiento en «Mi adecuación» MUST ofrecer «Ver cursos sugeridos», que lleva al Catálogo Formativo filtrado por ese elemento; las brechas de experiencia MUST mostrar el mensaje «Se desarrolla con práctica; revisa tu plan de desarrollo».
- **FR-032**: «Mi adecuación» MUST mostrar permanentemente el aviso «Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.» y un estado vacío cuando no hay puesto objetivo o adecuación.
- **FR-033**: El acceso MUST regirse por permisos estables: `desarrollo.adecuacion.generar`, `desarrollo.adecuacion.ver`, `desarrollo.adecuacion.comparar`, `desarrollo.adecuacion.mapa`, `desarrollo.adecuacion.parametros` (todos para el rol Administrador / Gestor de Talento, que **no** recibe `ver_propio`) y `desarrollo.adecuacion.ver_propio` (solo para el rol Colaborador, y únicamente sobre lo propio). Los controles de gestión MUST ser visibles solo con el permiso correspondiente, y las operaciones MUST validarse también en el servidor.
- **FR-034**: Los botones «Crear plan de desarrollo» e «Incluir en Programa de Talentos» MUST mostrarse tras guardar, deshabilitados si no hay brechas y, mientras no exista el Plan de Desarrollo Individual, deshabilitados con la explicación correspondiente.
- **FR-035**: La feature NO MUST administrar puestos, competencias, conocimientos ni datos de colaboradores (los lee de Estructura Empresarial, Gestión de Personal y Rutas), ni decidir ni ejecutar ascensos.
- **FR-036**: Si una fuente externa no responde, el sistema MUST mostrar un aviso con «Reintentar», sin datos inventados, y no permitir guardar un diagnóstico basado en datos incompletos por falla.

### Key Entities

- **Adecuación**: diagnóstico puntual e inmutable de un colaborador frente a un puesto objetivo. Atributos: código ADE-NNNN, colaborador, puesto actual (copia del momento), puesto objetivo, inscripción de origen (opcional), experiencia requerida (opcional), preparación, observaciones, usuario y fecha de generación.
- **Resultado de adecuación**: una fila por requisito evaluado de una adecuación. Atributos: dimensión, elemento, requerido en el puesto actual, requerido en el puesto objetivo, valor de la persona, diferencia, estado y tiempo estimado de cierre (en brechas).
- **Parámetros de adecuación**: pesos por dimensión y umbral de preparación vigentes.
- **Historial de parámetros**: cada cambio de los parámetros con usuario, fecha, valor anterior y valor nuevo.
- **Comparativa de candidatos** *(no persistente)*: ranking calculado en el momento a partir de adecuaciones vigentes.
- **Fila del mapa de talento** *(derivada)*: adecuación vigente de un colaborador por puesto objetivo con brechas y tiempo máximo de cierre.
- Relaciones externas (solo lectura): puesto y perfil (Estructura Empresarial), colaborador, puesto actual, niveles y experiencia (Gestión de Personal), inscripción y nivel (Rutas de carrera), cálculo de grados (Detección de Necesidades de Formación), cursos (Catálogo Formativo).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un administrador puede generar y guardar una adecuación completa (selección, diagnóstico con tiempos de cierre y confirmación) en menos de 3 minutos en un perfil de hasta 15 requisitos.
- **SC-002**: El 100 % de las diferencias, estados y preparaciones de las adecuaciones guardadas coincide con el cálculo manual de los casos de prueba definidos (grados, años, umbral).
- **SC-003**: El ranking de una comparativa de hasta 20 candidatos se muestra en menos de 3 segundos y coincide al 100 % con el cálculo manual del puntaje (incluido reescalado de pesos y tope de 1).
- **SC-004**: Ningún colaborador puede ver puntajes de comparación ni datos de otras personas: 0 casos en las pruebas de acceso entre usuarios.
- **SC-005**: El 100 % de las adecuaciones guardadas permanece sin cambios tras modificar el perfil del puesto, el nivel del colaborador o los parámetros.
- **SC-006**: El 100 % de los cambios de parámetros y de las generaciones de adecuación queda registrado con usuario y fecha.
- **SC-007**: Un colaborador entiende su brecha principal y llega al catálogo filtrado en menos de 3 clics desde «Mi adecuación», y al menos el 90 % de los usuarios de prueba lo logra a la primera.
- **SC-008**: El mapa de talento con hasta 500 filas se carga y filtra en menos de 3 segundos.

## Assumptions

- **Origen de los datos**: Estructura Empresarial entrega el perfil de competencias y conocimientos activos del puesto con sus grados; Gestión de Personal entrega el puesto actual, el nivel actual por competencia y conocimiento y los años de experiencia acumulada; Rutas de carrera entrega el nivel objetivo y la experiencia mínima. Esta feature solo los lee.
- **Conocimientos con grado (a confirmar)**: la escala A–D para conocimientos es un supuesto del documento fuente y debe confirmarse con Estructura Empresarial. La feature 019 estableció que hoy los conocimientos no llevan grado (existe la bandera `desarrollo.conocimientos_con_grado`, apagada). Con la bandera apagada, la dimensión Conocimientos se desactiva (sin resultados, con aviso informativo y pesos reescalados); con ella encendida y datos disponibles, se evalúan igual que las competencias.
- **Experiencia acumulada (a confirmar)**: depende de que Gestión de Personal entregue los años de experiencia por colaborador; si no hay dato, Experiencia queda «Sin evaluación».
- **Decisiones de diseño del sistema** (no literales en la bibliografía): la clasificación de preparación, el umbral inicial de 12 meses, la fórmula del puntaje, los pesos iniciales 40/30/30 y la regla de que el colaborador no ve puntajes ni a otros candidatos son decisiones del proyecto; cualquier cambio de reglas o nomenclatura debe ser una decisión explícita del equipo.
- **Colaboradores elegibles**: solo colaboradores con vínculo laboral vigente, de cualquier departamento.
- **Visibilidad para el colaborador**: la adecuación es visible para la persona evaluada desde el momento en que se guarda; no existe un paso de liberación.
- **Tiempo de cierre**: se expresa en meses enteros entre 1 y 60; no se estima automáticamente.
- **Ruta de la inscripción**: si el colaborador tiene varias inscripciones Activas, el administrador elige cuál usar; si solo tiene una, se preselecciona.
- **Roles**: por ahora el administrador (Gestor de Talento) realiza todas las acciones de gestión; los permisos se siembran en el módulo y la administración de roles podrá reasignarlos más adelante sin cambiar esta feature.
- **Plan de Desarrollo Individual**: aún no implementado; los botones de continuación se muestran deshabilitados y la referencia de vuelta a la adecuación se agregará cuando exista.
- **Reportes**: los indicadores de preparación en Reportes (feature 018) pueden usar estas adecuaciones; su ampliación queda fuera de esta entrega.
- **Fuera de alcance**: decidir o ejecutar ascensos, administrar puestos, competencias y conocimientos, estimar tiempos automáticamente, guardar comparativas, notificaciones y remuneraciones.
- **Fundamento bibliográfico**: Alles (H.35, H.1, H.50A, H.42B, H.50B, H.40A) y Navarro (Pasos 7 y 9), según el documento fuente copiado en esta carpeta.

## Pantallas *(si la feature tiene UI)*

> Nota de diseño: el tablero `design/AdecuacionDiagnostico.dc.html` es una sola pantalla con pestañas. Los tres pasos del asistente (Selección, Diagnóstico y Confirmación) son bloques de la pestaña «Diagnóstico»; el tipo de cambio (Promoción, Desplazamiento lateral, Mismo puesto, Puesto directo) es una etiqueta informativa calculada. Las diferencias con el tablero están en `plan.md`.

### Asistente de generación (administrador)
- **Propósito**: generar y guardar la adecuación de un colaborador a un puesto objetivo.
- **Datos que muestra**: paso 1 selector de colaborador con búsqueda, puesto objetivo (por defecto el de la inscripción Activa, o puesto directo con experiencia requerida opcional); paso 2 tres paneles (Competencias | Conocimientos | Experiencia) con barras de requerido actual, valor de la persona y requerido objetivo, tabla con requisito, valores, diferencia y estado, y campo de tiempo de cierre por brecha; paso 3 preparación calculada y observaciones; avisos por requisitos Sin evaluación y por elementos inactivos omitidos.
- **Acciones disponibles** (y quién puede hacerlas): administrador con permiso de generar — avanzar y retroceder entre pasos, ingresar tiempos, confirmar, y tras guardar «Crear plan de desarrollo» e «Incluir en Programa de Talentos» (deshabilitados según FR-034). Colaborador — sin acceso.
- **Estados**: vacío (sin colaborador elegido), validación de campos (tiempos faltantes), cargando, error con «Reintentar», sin permisos, conflicto (el objetivo cambió).
- **Mockup**: `design/AdecuacionDiagnostico.dc.html` (patrón de asistente en `design/DESIGN.md`; agregar paso de Selección y de Confirmación).

### Evaluaciones (administrador)
- **Propósito**: consultar el historial de adecuaciones y abrir su detalle.
- **Datos que muestra**: tabla con código, fecha, colaborador, puesto objetivo, preparación (badge) y cantidad de brechas; filtros por fecha, colaborador, puesto y preparación; detalle de solo lectura con usuario y fecha de generación.
- **Acciones disponibles** (y quién puede hacerlas): administrador con permiso de ver — filtrar y abrir detalle. No existen editar ni eliminar. Colaborador — sin acceso.
- **Estados**: vacío, cargando, error con «Reintentar», sin permisos.
- **Mockup**: patrón Listado/Detalle de `design/DESIGN.md`, sin mockup propio.

### Comparar candidatos (administrador)
- **Propósito**: ayudar a decidir entre varios candidatos para un puesto.
- **Datos que muestra**: selector de puesto y candidatos, pesos editables solo para la consulta, ranking con puntaje, preparación y cantidad de brechas, lista aparte de Incompletos y señalamiento de candidatos sin adecuación.
- **Acciones disponibles** (y quién puede hacerlas): administrador con permiso de comparar — elegir puesto y candidatos, ajustar pesos de la consulta, comparar. Colaborador — sin acceso.
- **Estados**: vacío (sin puesto o sin candidatos), validación de pesos (suma 100), cargando, error con «Reintentar», sin permisos.
- **Mockup**: `design/AdecuacionComparar.dc.html`.

### Mapa de talento (administrador)
- **Propósito**: ver la preparación de todos los colaboradores por puesto objetivo.
- **Datos que muestra**: tabla con colaborador, puesto actual, puesto objetivo, preparación, cantidad de brechas y tiempo máximo de cierre; filtros por departamento, ruta y preparación.
- **Acciones disponibles** (y quién puede hacerlas): administrador con permiso del mapa — filtrar y abrir la adecuación de una fila. Colaborador — sin acceso.
- **Estados**: vacío, cargando, error con «Reintentar», sin permisos.
- **Mockup**: `design/AdecuacionMapa.dc.html`.

### Parámetros (administrador)
- **Propósito**: mantener los pesos por dimensión y el umbral de preparación, y auditar sus cambios.
- **Datos que muestra**: pesos y umbral vigentes; historial con usuario, fecha, valor anterior y valor nuevo.
- **Acciones disponibles** (y quién puede hacerlas): administrador con permiso de parámetros — editar y guardar. Colaborador — sin acceso.
- **Estados**: vacío del historial, validación de campos, cargando, error con «Reintentar», sin permisos.
- **Mockup**: patrón Formulario/Listado de `design/DESIGN.md`, sin mockup propio (pestaña de `design/AdecuacionDiagnostico.dc.html` si aplica).

### Mi adecuación (colaborador)
- **Propósito**: que el colaborador vea qué le falta para su puesto objetivo y cómo cerrarlo.
- **Datos que muestra**: tres tarjetas (Competencias, Conocimientos, Experiencia) con barras de requerido contra actual y diferencia; insignia de preparación; tiempo estimado de cierre por brecha; aviso permanente de «guía, no promesa».
- **Acciones disponibles** (y quién puede hacerlas): colaborador — «Ver cursos sugeridos» en brechas de competencia o conocimiento (lleva al Catálogo Formativo filtrado). Administrador — no aplica.
- **Estados**: vacío (sin puesto objetivo o sin adecuación), cargando, error con «Reintentar».
- **Mockup**: `design/AdecuacionColaborador.dc.html`.
