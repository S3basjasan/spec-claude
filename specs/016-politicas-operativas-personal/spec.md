# Feature Specification: Configuración de políticas operativas de personal

**Feature Branch**: `016-politicas-operativas-personal`

**Created**: 2026-10-20

**Status**: Draft

**Input**: User description: "Configuración de políticas operativas de personal. Permitir al Administrador de Talento Humano configurar, con parámetros definidos por el sistema, las condiciones con que su organización gestiona permisos, ausencias, licencias, vacaciones, ascensos, traslados y offboarding. Cada política es general para la organización, se versiona (borrador, programada, vigente, sustituida, retirada) y una versión publicada no se edita ni se elimina. Las solicitudes conservan la versión con que se presentaron. Estructura empresarial proporciona los puestos destino (con los niveles de todos los puestos). No se crean tipos arbitrarios, fórmulas, flujos de aprobación libres, políticas por unidad o puesto, ni se modifican puestos, rutas de carrera, evaluaciones ni movimientos laborales. El Colaborador solo consulta las condiciones de sus solicitudes."

## Clarifications

### Session 2026-10-20

- Q: ¿Puede haber más de un borrador abierto a la vez para el mismo tipo de política? → A: No; un solo borrador activo por tipo. Crear otro exige publicar o descartar el existente.
- Q: ¿Qué pasa con un inicio de vigencia que cae en un horario inexistente o repetido por cambio de hora? → A: Se ajusta al primer instante válido posterior (si no existe) o a la primera ocurrencia (si se repite) y la confirmación muestra el momento exacto resultante.
- Q: ¿Con qué instante se decide qué versión aplica a una solicitud? → A: Con el instante de confirmación de la presentación, comparado con el inicio de vigencia de las versiones; una versión Programada nunca se usa antes de su inicio.
- Q: ¿Qué límites de longitud y cantidad tienen los textos y la plantilla? → A: Descripción 1000 caracteres, motivo del cambio y justificación de retiro 500, observaciones de la constancia 1000, nombre de tarea 150, descripción de tarea 500, hasta 30 tareas por plantilla; todos se recortan de espacios y se rechazan si exceden.
- Q: ¿Puede quien publica ser también quien figura como revisor en la constancia? → A: Sí; la constancia es una declaración funcional del administrador (no hay roles intermedios) y se registra con su autor, momento y observaciones.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Preparar, revisar y publicar una política (Priority: P1)

El Administrador de Talento Humano elige un tipo de política (por ejemplo, Permisos), crea un borrador, completa solo los parámetros admitidos para ese tipo, revisa el resumen de condiciones resultantes y las diferencias con la versión vigente, registra el motivo del cambio y la constancia de revisión, y publica la versión, ya sea de inmediato o con inicio en un momento futuro.

**Why this priority**: Sin políticas publicadas, los procesos que dependen de ellas (novedades, movimientos, offboarding) no pueden utilizarse. Es el valor central de la funcionalidad.

**Independent Test**: En una organización nueva, abrir el panel, crear un borrador de Permisos, completarlo, publicarlo de inmediato con motivo y constancia, y comprobar que el panel muestra la versión 1 como vigente con su inicio de vigencia, motivo y responsables, y que un segundo administrador del mismo tenant la ve igual.

**Acceptance Scenarios**:

1. **Given** una organización sin versiones publicadas, **When** el Administrador abre el panel, **Then** ve un registro por cada tipo con «Política sin configurar» y no se presenta ninguno como «Sin restricciones». (PO-CA01)
2. **Given** un borrador completo de un tipo, **When** el Administrador lo revisa antes de publicar, **Then** el sistema presenta las condiciones resultantes, las diferencias respecto de la versión vigente (o que no existe una), el momento de inicio, el efecto sobre nuevas solicitudes y el aviso de que las solicitudes anteriores conservan sus condiciones. (PO-CA02)
3. **Given** un borrador y la confirmación del Administrador con motivo del cambio y constancia de revisión (quién revisó, momento y observaciones), **When** elige publicar de inmediato, **Then** la versión queda vigente desde el momento de la confirmación con un número correlativo dentro del tipo y del tenant. (PO-CA03)
4. **Given** un borrador, **When** el Administrador elige un inicio de vigencia futuro y confirma, **Then** la versión queda programada y comienza a utilizarse al llegar ese momento, momento en que la anterior queda sustituida. (PO-CA04)
5. **Given** un intento de publicar sin motivo, sin constancia de revisión, con campos incompletos, con parámetros no admitidos para el tipo o con valores inválidos, **When** se confirma, **Then** no se publica, y el error se muestra junto al campo correspondiente o con un mensaje que identifica la contradicción. (PO-CA05)
6. **Given** un intento de publicar con un inicio de vigencia en el pasado, **When** se confirma, **Then** se rechaza. (PO-CA06)
7. **Given** una primera publicación de un tipo, **When** se confirma, **Then** también exige motivo y constancia de revisión; completar todos los campos no basta. (PO-CA07)
8. **Given** dos administradores editando el mismo borrador, **When** el segundo guarda tras un cambio reciente del primero, **Then** el sistema avisa del conflicto y no sobrescribe silenciosamente. (PO-CA08)

---

### User Story 2 - Configurar los parámetros de cada tipo de política (Priority: P1)

El Administrador configura, para cada tipo, únicamente los parámetros que el sistema admite: disponibilidad, justificación, documento de respaldo y anticipación mínima para novedades; disponibilidad, anticipación y documento adicional para ascensos y traslados; y una plantilla de tareas para offboarding.

**Why this priority**: El contenido de la política es lo que los procesos consumen; sin parámetros bien definidos y acotados, publicar no tiene efecto útil ni seguro.

**Independent Test**: Para cada tipo, comprobar que el borrador ofrece exactamente los parámetros admitidos, que los valores inválidos se rechazan y que lo publicado muestra los valores configurados.

**Acceptance Scenarios**:

1. **Given** un borrador de Permisos, Licencias o Vacaciones, **When** se configura, **Then** se pueden definir disponibilidad para nuevas solicitudes, justificación obligatoria, documento de respaldo obligatorio y anticipación mínima en días calendario enteros iguales o mayores que cero. (PO-CA09)
2. **Given** un borrador de Ausencias, **When** se configura, **Then** se admiten disponibilidad, justificación y documento de respaldo, pero no anticipación mínima. (PO-CA10)
3. **Given** una anticipación mínima negativa, con decimales o no numérica, **When** se intenta guardar o publicar, **Then** se rechaza con un error junto al campo; una anticipación de cero es válida y no autoriza fechas pasadas. (PO-CA11)
4. **Given** un borrador de Ascensos o Traslados, **When** se configura, **Then** se pueden definir disponibilidad para iniciar movimientos, anticipación mínima respecto de la fecha efectiva (días calendario enteros ≥ 0) y documento adicional obligatorio; la justificación del movimiento y la evaluación contra el puesto destino se presentan como reglas fijas no editables. (PO-CA12)
5. **Given** un borrador de Offboarding, **When** se define la plantilla, **Then** cada tarea tiene nombre obligatorio, descripción opcional e indicador de evidencia obligatoria; no se admiten tareas sin nombre ni nombres repetidos en la misma plantilla (comparados sin distinguir mayúsculas ni tildes). (PO-CA13)
6. **Given** cualquier tipo, **When** se intenta configurar algo fuera del catálogo (un parámetro desconocido, un permiso para una persona, una fórmula, un flujo de aprobadores, saldos de vacaciones, fechas pasadas, liquidaciones, salidas retroactivas o prolongación del vínculo), **Then** el sistema no ofrece ni acepta ese parámetro. (PO-CA14)
7. **Given** un texto libre en descripción, motivo, observaciones o tareas, **When** se guarda, **Then** se trata siempre como texto y nunca como instrucción que el sistema interprete. (PO-CA15)
8. **Given** una configuración que contradice una regla fija (por ejemplo, desactivar la evaluación del puesto destino o hacer que una tarea pendiente retenga la salida), **When** se intenta publicar, **Then** se rechaza identificando la contradicción. (PO-CA16)
9. **Given** una política de Ascensos o Traslados, **When** se configura o consulta, **Then** no se pide ni se muestra ningún puesto o nivel destino: los puestos destino y sus niveles los proporciona Estructura empresarial a los procesos de movimientos, y la política no los restringe, crea ni modifica. (PO-CA17)

---

### User Story 3 - Versiones, vigencia, retiro y comparación (Priority: P1)

El Administrador consulta el detalle de un tipo, ve las condiciones vigentes, las reglas fijas, la versión programada y las versiones anteriores, compara una propuesta con la vigente, crea un nuevo borrador a partir de cualquier versión, y retira una versión programada antes de su inicio.

**Why this priority**: Garantiza trazabilidad y que ningún cambio altere silenciosamente el pasado; es la base de la confianza en las políticas.

**Independent Test**: Publicar dos versiones sucesivas del mismo tipo, comprobar que la primera queda sustituida y ambas se conservan, que una publicada no se edita, que una programada se retira con justificación sin reutilizar su número y que solo hay una vigente y una programada por tipo.

**Acceptance Scenarios**:

1. **Given** una versión vigente y otra que comienza su vigencia, **When** llega el momento de inicio, **Then** la nueva pasa a Vigente, la anterior a Sustituida, ambas se conservan y siempre hay como máximo una vigente por tipo y tenant. (PO-CA18)
2. **Given** una versión publicada (programada, vigente, sustituida o retirada), **When** el Administrador intenta editarla o eliminarla, **Then** no se permite; para cambiar condiciones crea un borrador nuevo a partir de ella. (PO-CA19)
3. **Given** una versión programada, **When** el Administrador la retira con justificación, **Then** pasa a Retirada, se conservan responsable, momento y justificación, y su número no se reutiliza. (PO-CA20)
4. **Given** una versión programada existente, **When** el Administrador intenta programar otra del mismo tipo, **Then** se le indica que debe retirar primero la anterior. (PO-CA21)
5. **Given** una versión que ya comenzó su vigencia, **When** se intenta retirarla, **Then** no se permite; se sustituye publicando otra. (PO-CA22)
6. **Given** una versión programada cuyo inicio llega al mismo tiempo que se intenta retirarla, **When** ambas operaciones compiten, **Then** el resultado es uno solo y coherente (queda retirada o comienza su vigencia), sin estados intermedios ni dos vigentes. (PO-CA23)
7. **Given** una propuesta en borrador, **When** el Administrador la compara con la versión vigente, **Then** se identifican los parámetros que cambian. (PO-CA24)
8. **Given** el detalle de un tipo, **When** se consulta, **Then** cada versión muestra parámetros, vigencia, motivo del cambio y responsables de revisión y publicación. (PO-CA25)
9. **Given** el listado, **When** el Administrador filtra por tipo o por situación de configuración (sin configurar, vigente, con versión programada), **Then** se muestran solo los registros que corresponden. (PO-CA26)

---

### User Story 4 - Aplicación de la política a cada solicitud y consulta del Colaborador (Priority: P2)

Al presentar formalmente una solicitud, el proceso consulta la política aplicable, la conserva junto con los parámetros que determinaron sus condiciones y el solicitante puede consultarla. El Colaborador ve únicamente las condiciones que afectan a sus solicitudes.

**Why this priority**: Es el contrato de integración con novedades, movimientos y offboarding. Aporta valor cuando esos procesos existan, pero la política debe quedar definida primero.

**Independent Test**: Con una política vigente, consultar las condiciones aplicables como si se presentara una solicitud, publicar un cambio, y comprobar que una solicitud ya presentada sigue mostrando la versión original mientras una nueva usa la actual; comprobar que un Colaborador no accede al panel ni a políticas de otras personas.

**Acceptance Scenarios**:

1. **Given** una política vigente, **When** un usuario va a presentar una solicitud, **Then** puede consultar las condiciones que se aplicarán; un formulario sin presentar no reserva versión. (PO-CA27)
2. **Given** una solicitud presentada, **When** se consulta su detalle, **Then** muestra la versión utilizada y sus requisitos relevantes, y no cambia aunque después se publique otra versión. (PO-CA28)
3. **Given** una solicitud presentada antes de un cambio de política, **When** se resuelve después del cambio, **Then** conserva su versión original y no se reasigna automáticamente. (PO-CA29)
4. **Given** una política publicada que deshabilita nuevas solicitudes, **When** un usuario intenta presentar una, **Then** no se permite con un mensaje claro, y las solicitudes ya existentes no se cancelan. (PO-CA30)
5. **Given** un tipo sin versión vigente, **When** se intenta presentar una solicitud que lo requiere, **Then** no se confirma la presentación y se informa que la política no está configurada; no se utilizan valores supuestos. (PO-CA31)
6. **Given** que no puede determinarse de manera confiable la versión aplicable, **When** se intenta presentar, **Then** no se confirma y se permite reintentar. (PO-CA32)
7. **Given** una solicitud con una política anterior, **When** se resuelve, **Then** el proceso vuelve a comprobar permisos y la disponibilidad de los recursos; conservar la política anterior no permite ocupar una posición que ya no está disponible. (PO-CA33)
8. **Given** un Colaborador, **When** intenta acceder al panel de configuración o a las condiciones de solicitudes ajenas, **Then** se le deniega sin revelar datos. (PO-CA34)
9. **Given** una fecha de presentación y una fecha inicial solicitada, **When** se evalúa la anticipación, **Then** se calcula en días calendario según la zona horaria de la organización y no con la fecha en que se resuelve. (PO-CA35)

---

### User Story 5 - Plantilla de offboarding aplicada a procesos (Priority: P3)

El Administrador define una plantilla de tareas de cierre administrativo; al iniciar un offboarding se copia la plantilla de la versión aplicable y los cambios posteriores de la política no afectan a procesos ya iniciados.

**Why this priority**: Depende del proceso de offboarding, que es otro módulo; aquí solo se garantiza el contrato de plantilla y su inmutabilidad.

**Independent Test**: Publicar una plantilla con dos tareas, iniciar (o simular) un offboarding, publicar una versión con otras tareas y comprobar que el proceso conserva las dos originales.

**Acceptance Scenarios**:

1. **Given** una política de Offboarding vigente, **When** se inicia un proceso, **Then** se copian las tareas de la versión aplicable con su nombre, descripción e indicador de evidencia. (PO-CA36)
2. **Given** un proceso ya iniciado, **When** se publica otra versión de la plantilla, **Then** el proceso no gana ni pierde tareas. (PO-CA37)
3. **Given** una plantilla que menciona una persona o función, **When** se publica, **Then** no se otorgan permisos ni responsabilidades por ello; los responsables se asignan dentro del proceso de salida. (PO-CA38)
4. **Given** cualquier plantilla, **When** se registra una salida, **Then** las tareas pendientes no impiden el registro, no prolongan el vínculo ni mantienen ocupada la posición. (PO-CA39)

---

### Edge Cases

- Dos administradores publican simultáneamente un borrador del mismo tipo: solo una publicación prospera, con numeración correlativa sin duplicados ni dos vigentes.
- Se intenta programar una versión mientras otra ya está programada: se rechaza hasta retirar la anterior.
- Una versión programada llega a su inicio mientras el panel está abierto: el panel refleja el estado actual tras recargar y no permite operar sobre un estado obsoleto.
- Se publica un borrador creado a partir de una versión sustituida o retirada: las diferencias se calculan contra la versión vigente real, no contra la de origen.
- Se intenta crear un borrador de un tipo que ya tiene uno activo: se informa del borrador existente y se ofrece continuarlo o descartarlo; no se crea uno nuevo.
- Un tipo con una versión programada y sin vigente: sigue siendo «sin configurar» hasta que la programada comience; la presentación de solicitudes no usa la versión programada.
- La zona horaria de la organización difiere de la del navegador: la anticipación y el inicio de vigencia se interpretan siempre en la zona de la organización.
- Cambio de hora (un inicio en un momento que no existe o se repite en la zona de la organización): se ajusta al primer instante válido posterior o a la primera ocurrencia, y la confirmación muestra el momento exacto de inicio.
- Plantilla de offboarding con nombres que solo difieren en mayúsculas o tildes: se rechazan como repetidos.
- Texto con marcado o secuencias parecidas a instrucciones en descripción, motivo o tareas: se almacena y muestra como texto literal.
- Un administrador de otro tenant intenta ver, comparar o publicar versiones ajenas: se responde como si no existieran.
- Un borrador abandonado: se conserva (y bloquea crear otro del mismo tipo) hasta que un administrador lo publique o lo descarte; descartar no afecta versiones publicadas.
- Fallo al consultar la política aplicable durante la presentación de una solicitud: no se confirma la presentación y se puede reintentar.
- Estructura empresarial no disponible o un puesto destino sin nivel definido: las políticas siguen siendo consultables y editables porque no dependen de ese dato; el proceso de movimientos decide qué hacer con el destino.

## Requirements *(mandatory)*

### Functional Requirements

**Catálogo y alcance**

- **FR-001**: El sistema MUST ofrecer exactamente siete tipos de política: Permisos, Ausencias, Licencias, Vacaciones, Ascensos, Traslados y Offboarding; el tenant no puede crear otros ni cambiar su significado.
- **FR-002**: Cada tipo MUST tener una única política general por organización; no existen políticas por unidad, puesto, antigüedad ni colaborador.
- **FR-003**: Una organización nueva MUST iniciar sin versiones publicadas, y el sistema MUST distinguir «Política sin configurar» de una política publicada que no permite nuevas solicitudes.

**Datos de una versión**

- **FR-004**: Cada versión MUST contener tipo (no editable), número de versión generado al publicar y correlativo dentro del tipo y del tenant, descripción, parámetros admitidos para el tipo, inicio de vigencia, motivo del cambio, constancia de revisión (quién revisó, momento y observaciones) y metadatos de creación y publicación registrados por el sistema.
- **FR-005**: El número de versión MUST asignarse solo al publicar, MUST NOT reutilizarse nunca (ni siquiera tras un retiro) y MUST ser consistente ante publicaciones concurrentes.
- **FR-006**: La publicación MUST exigir motivo del cambio y constancia de revisión, también en la primera publicación de un tipo.

**Parámetros por tipo**

- **FR-007**: Para Permisos, Ausencias, Licencias y Vacaciones el sistema MUST admitir disponibilidad para nuevas solicitudes, justificación obligatoria y documento de respaldo obligatorio.
- **FR-008**: Para Permisos, Licencias y Vacaciones el sistema MUST admitir además anticipación mínima en días calendario enteros ≥ 0; para Ausencias MUST NOT admitirla.
- **FR-009**: La anticipación MUST calcularse comparando la fecha de presentación con la fecha inicial solicitada en la zona horaria de la organización; una anticipación de cero MUST permitir inicio en la fecha actual y MUST NOT autorizar fechas pasadas.
- **FR-010**: Exigir documento de respaldo MUST significar que debe existir un respaldo válido y accesible para el responsable autorizado, sin conceder acceso documental a otros usuarios.
- **FR-011**: Para Ascensos y Traslados el sistema MUST admitir disponibilidad para iniciar movimientos, anticipación mínima respecto de la fecha efectiva (días calendario enteros ≥ 0) y documento adicional obligatorio.
- **FR-012**: Para Ascensos y Traslados la justificación del movimiento y la existencia de una evaluación del colaborador contra los requisitos del puesto destino MUST ser reglas fijas, visibles y no desactivables; la política MUST NOT definir porcentajes mínimos, exigencia de ausencia de brechas ni criterios de evaluación.
- **FR-013**: Para Offboarding el sistema MUST admitir una plantilla de tareas; cada tarea MUST tener nombre obligatorio, descripción opcional e indicador de evidencia obligatoria, y MUST NOT admitirse tareas sin nombre ni nombres repetidos dentro de la plantilla.
- **FR-014**: El sistema MUST NOT admitir parámetros fuera del catálogo: fórmulas, flujos o cadenas de aprobadores, saldos o acumulación de vacaciones, fechas pasadas, liquidaciones o cálculos financieros, salidas retroactivas, permisos por persona, ni excepciones expresadas como texto libre interpretado por el sistema.
- **FR-015**: Los puestos destino de ascensos y traslados, con los niveles de todos los puestos, los proporciona Estructura empresarial a los procesos de movimientos; la política MUST NOT crear destinos, definir qué puesto o nivel es el siguiente paso de carrera, restringir destinos ni modificar puestos, niveles o requisitos, y su edición MUST NOT depender de la disponibilidad de Estructura.

**Reglas no configurables**

- **FR-016**: Ninguna política MUST poder permitir acceso entre tenants, conceder permisos que el usuario no posee, crear vínculos o asignaciones incompatibles, permitir más de una posición vigente por colaborador, asignar una posición suprimida, eliminar o sobrescribir hechos laborales, omitir la evaluación contra el puesto destino, convertir el cierre de salida en extensión del vínculo, alterar requisitos del puesto o rutas de carrera.
- **FR-017**: La política de Offboarding MUST NOT omitir motivo o fecha efectiva de salida, autorizar salidas retroactivas, prolongar el vínculo por tareas pendientes, mantener ocupada la posición tras la salida efectiva, ni impedir registrar una salida por tareas administrativas pendientes.
- **FR-018**: El sistema MUST NOT ofrecer un mecanismo genérico para omitir reglas; las excepciones funcionales solo existen si el proceso correspondiente las define expresamente.

**Estados y vigencia**

- **FR-019**: Una versión MUST estar en exactamente uno de los estados Borrador, Programada, Vigente, Sustituida o Retirada, con las transiciones: Borrador → Programada o Vigente (al publicar); Programada → Vigente (al llegar el inicio) o Retirada (antes del inicio); Vigente → Sustituida (cuando otra comienza).
- **FR-020**: Por tipo y tenant MUST existir como máximo una versión Vigente y como máximo una Programada; programar o publicar (también de inmediato) otra MUST requerir retirar primero la existente.
- **FR-021**: Publicar de inmediato MUST iniciar la vigencia en el momento de la confirmación; el sistema MUST rechazar inicios de vigencia en el pasado.
- **FR-022**: Cuando una versión comienza su vigencia, la anterior MUST quedar Sustituida para nuevas solicitudes y ambas MUST conservarse.
- **FR-023**: Una versión publicada (programada, vigente, sustituida o retirada) MUST NOT editarse ni eliminarse; para cambiar condiciones se crea un borrador nuevo a partir de una versión existente.
- **FR-024**: Retirar una versión programada MUST requerir justificación, conservar la versión con responsable, momento y justificación, y MUST NOT permitirse una vez comenzada la vigencia.
- **FR-024b**: Por tipo y tenant MUST existir como máximo un borrador activo; crear otro MUST rechazarse mientras exista, indicando que debe publicarse o descartarse el actual; cualquier Administrador del tenant puede continuarlo o descartarlo.
- **FR-024c**: Un inicio de vigencia en un horario inexistente o repetido por cambio de hora MUST ajustarse al primer instante válido posterior (inexistente) o a la primera ocurrencia (repetido), y la confirmación MUST mostrar el momento exacto resultante.
- **FR-024d**: La política aplicable a una solicitud MUST determinarse con el instante de confirmación de su presentación; una versión Programada MUST NOT usarse antes de su inicio de vigencia.
- **FR-024e**: Los textos MUST recortarse de espacios y respetar estos máximos, rechazándose si los exceden: descripción 1000 caracteres; motivo del cambio y justificación de retiro 500; observaciones de la constancia 1000; nombre de tarea 150; descripción de tarea 500; hasta 30 tareas por plantilla.
- **FR-024f**: La constancia de revisión MUST poder registrarla el mismo administrador que publica, quedando identificados su autor, momento y observaciones.
- **FR-025**: Los cambios de estado concurrentes (publicar, retirar, comenzar vigencia) MUST resolverse de forma atómica y coherente, y una edición concurrente MUST NOT sobrescribir cambios recientes silenciosamente.

**Revisión de consistencia y publicación**

- **FR-026**: Antes de publicar, el sistema MUST presentar un resumen con condiciones resultantes, diferencias respecto de la vigente, momento de inicio, efecto sobre nuevas solicitudes y el aviso de que las solicitudes anteriores conservan sus condiciones, y MUST solicitar confirmación.
- **FR-027**: El sistema MUST comprobar las contradicciones que pueda determinar con los datos disponibles, y MUST NOT publicar campos incompletos, parámetros desconocidos, valores inválidos ni configuraciones que contradigan las reglas fijas.
- **FR-028**: La constancia de revisión MUST entenderse como una revisión funcional del administrador; el sistema MUST NOT presentarla como validación legal ni como aceptación del cliente.

**Aplicación a solicitudes**

- **FR-029**: La política aplicable MUST seleccionarse cuando se presenta formalmente una solicitud, no antes; un formulario sin presentar MUST NOT reservar versión.
- **FR-030**: Cada solicitud MUST conservar la versión utilizada y los parámetros que determinaron sus condiciones; una solicitud presentada antes de un cambio MUST mantener su versión aunque se resuelva después, y MUST NOT reasignarse automáticamente.
- **FR-031**: Antes de resolver y aplicar efectos, el proceso MUST volver a comprobar permisos, integridad y disponibilidad de los recursos; conservar una política anterior MUST NOT permitir ocupar una posición no disponible.
- **FR-032**: Deshabilitar nuevas solicitudes MUST NOT cancelar las existentes.
- **FR-033**: Si no existe versión vigente o no puede determinarse de manera confiable la aplicable, el sistema MUST NOT confirmar la presentación ni utilizar valores supuestos.
- **FR-034**: Offboarding MUST copiar la plantilla de la versión aplicable al iniciar un proceso; los cambios posteriores MUST NOT agregar ni eliminar tareas de procesos iniciados, y la plantilla MUST NOT otorgar permisos por mencionar personas o funciones.

**Permisos y aislamiento**

- **FR-035**: Solo el Administrador de Talento Humano MUST poder consultar políticas y versiones en el panel, crear y editar borradores, publicar o programar y retirar versiones programadas; toda operación MUST limitarse al tenant autenticado y un recurso de otro tenant o inexistente MUST responder igual.
- **FR-036**: El Colaborador MUST poder consultar solo las condiciones aplicables a sus propias solicitudes autorizadas, sin acceso al panel, a borradores, ni a información de otras personas.
- **FR-037**: Administrar políticas MUST NOT conceder por sí solo permiso para aprobar solicitudes, consultar documentos o ejecutar movimientos; cada proceso comprueba sus propios permisos. Los permisos se comprueban en el servidor y un permiso ausente se considera denegado.

**Consulta y visualización**

- **FR-038**: El listado MUST mostrar un registro por tipo con nombre, disponibilidad para nuevas solicitudes (cuando corresponda), versión vigente, inicio de vigencia e indicación de versión programada, con filtros por tipo y por situación de configuración.
- **FR-039**: El detalle MUST mostrar condiciones vigentes, reglas fijas aplicables, versión programada y versiones anteriores con parámetros, vigencia, motivo y responsables, y MUST permitir comparar una propuesta con la vigente.

**Auditoría**

- **FR-040**: El sistema MUST registrar quién creó y modificó un borrador y cuándo; cada publicación con versión, autor, momento, inicio de vigencia, motivo y constancia de revisión; y cada retiro con responsable, momento y justificación.
- **FR-041**: Las versiones publicadas MUST permanecer disponibles mientras formen parte de antecedentes de procesos; la configuración de políticas es auditoría de administración y MUST NOT generar por sí sola eventos en el historial de todos los colaboradores.

### Key Entities *(include if feature involves data)*

- **Tipo de política**: uno de los siete tipos definidos por el sistema; fija qué parámetros se admiten. No lo crea ni cambia el tenant.
- **Versión de política**: instancia de un tipo con descripción, parámetros, inicio de vigencia, motivo, constancia de revisión, estado y metadatos. Una vez publicada es inmutable; se relaciona con las solicitudes que la usan.
- **Constancia de revisión**: quién revisó la configuración, cuándo y con qué observaciones; acompaña a cada publicación.
- **Parámetros de novedades**: disponibilidad, justificación obligatoria, documento de respaldo obligatorio y, según el tipo, anticipación mínima.
- **Parámetros de movimientos**: disponibilidad, anticipación mínima y documento adicional obligatorio, más las reglas fijas de justificación y evaluación. No incluyen puestos ni niveles destino (los aporta Estructura empresarial).
- **Plantilla de offboarding**: lista de tareas con nombre, descripción opcional e indicador de evidencia; se copia a cada proceso al iniciarse.
- **Condiciones aplicadas a una solicitud**: copia de la versión y de los parámetros que rigieron una solicitud presentada; la conserva el proceso que la presenta.
- **Registro de auditoría de administración**: creación y modificación de borradores, publicaciones y retiros con responsable, momento y justificación.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un Administrador puede preparar, revisar y publicar una política completa de un tipo en menos de 10 minutos sin ayuda externa.
- **SC-002**: El 100 % de los intentos de publicar con datos incompletos, valores inválidos, parámetros fuera del catálogo o contradicciones con las reglas fijas se rechaza, identificando el campo o la contradicción.
- **SC-003**: En el 100 % de los casos hay como máximo una versión vigente y una programada por tipo y tenant, incluso ante operaciones concurrentes.
- **SC-004**: El 100 % de las solicitudes presentadas conserva la versión con que se presentó tras cualquier número de publicaciones posteriores.
- **SC-005**: Ninguna versión publicada puede modificarse ni eliminarse, y los números de versión nunca se repiten ni se reutilizan.
- **SC-006**: Ningún Colaborador puede acceder al panel ni a las condiciones de solicitudes ajenas, y ningún usuario de un tenant puede ver o modificar versiones de otro.
- **SC-007**: Un tipo sin versión vigente se presenta siempre como «Política sin configurar», nunca como sin restricciones, y en ningún caso se confirma una solicitud con valores supuestos.
- **SC-008**: Un Administrador identifica en menos de 1 minuto qué parámetros cambian entre una propuesta y la versión vigente.
- **SC-009**: Cada publicación y cada retiro quedan con responsable, momento y justificación consultables.

## Assumptions

- Los procesos de novedades, movimientos y offboarding son funcionalidades separadas; esta entrega define, versiona y expone las políticas y el contrato para que esos procesos consulten y conserven la versión aplicable, sin implementar los procesos.
- Estructura empresarial es la fuente de los puestos destino de ascensos y traslados: proporciona los puestos de toda la organización con sus niveles, requisitos y posiciones. Las políticas no crean ni modifican esa información ni restringen qué puestos son destinos válidos (esa decisión es del proceso de movimientos).
- La evaluación de adecuación al puesto destino pertenece a otro módulo; esta entrega solo establece que su existencia es una regla fija de ascensos y traslados.
- Los siete tipos y sus parámetros son los únicos de esta entrega; ampliar el catálogo requiere una nueva especificación.
- La zona horaria de la organización ya existe como dato del tenant y se utiliza para fechas de presentación, anticipación e inicio de vigencia.
- Por tipo y tenant existe como máximo un borrador activo; es compartido por los administradores del tenant (la concurrencia se controla con detección de conflictos). Para empezar otro hay que publicar o descartar el existente.
- Un borrador puede descartarse por un administrador; descartar no afecta versiones publicadas y se registra como auditoría de administración.
- La constancia de revisión identifica a una persona de la organización por su nombre y registra su revisión como texto; puede ser el propio administrador que publica; no implica una aprobación formal ni un flujo de aprobación dentro del sistema.
- Se trabaja con los dos roles definidos (Administrador de Talento Humano y Colaborador); no existen roles intermedios como líder o revisor con permisos propios.
- Las políticas no generan eventos en el historial laboral; los procesos que las usan registran sus propios hechos con referencia a la versión utilizada.

## Pantallas *(si la feature tiene UI)*

### Listado de políticas
- **Propósito**: ver el estado de configuración de cada tipo de política de la organización.
- **Datos que muestra**: tipo, disponibilidad para nuevas solicitudes (cuando corresponde), versión vigente, inicio de vigencia, indicación de versión programada, «Política sin configurar» cuando no hay publicada.
- **Acciones disponibles** (y quién puede hacerlas): abrir el detalle, filtrar por tipo y situación (Administrador).
- **Estados**: cargando, error con Reintentar, sin permisos (Colaborador); no existe estado vacío porque siempre hay siete tipos.
- **Mockup**: patrón Listado (design/DESIGN.md), sin paginación porque son siete registros fijos; columnas propias: tipo, disponibilidad, versión vigente, vigencia desde, programada.

### Detalle de política y versiones
- **Propósito**: ver condiciones vigentes, reglas fijas, versión programada y versiones anteriores, y comparar.
- **Datos que muestra**: parámetros, vigencia, motivo, responsables de revisión y publicación, estado de cada versión.
- **Acciones disponibles** (y quién puede hacerlas): crear borrador (nuevo o a partir de una versión), comparar con la vigente, retirar una versión programada (Administrador).
- **Estados**: sin configurar, cargando, error, sin permisos, versión no encontrada.
- **Mockup**: patrón Detalle (design/DESIGN.md) con línea de tiempo de versiones.

### Editor de borrador y publicación
- **Propósito**: completar los parámetros admitidos del tipo y publicar con revisión.
- **Datos que muestra**: formulario de parámetros del tipo, resumen de condiciones, diferencias con la vigente, momento de inicio, efecto sobre nuevas solicitudes.
- **Acciones disponibles** (y quién puede hacerlas): guardar borrador, descartar, revisar y publicar de inmediato o con inicio futuro, con motivo y constancia (Administrador).
- **Estados**: validación por campo, conflicto de edición concurrente, error de publicación, confirmación.
- **Mockup**: patrón Formulario (design/DESIGN.md) con diálogo de confirmación.

### Condiciones aplicables a una solicitud
- **Propósito**: mostrar al solicitante las condiciones que se aplicarán o aplicaron.
- **Datos que muestra**: versión utilizada y requisitos relevantes (justificación, documento, anticipación, plantilla).
- **Acciones disponibles** (y quién puede hacerlas): solo lectura (Administrador con acceso al proceso; Colaborador en sus solicitudes).
- **Estados**: cargando, error, política no configurada, sin permisos.
- **Mockup**: bloque de información dentro de los procesos que consumen la política.
