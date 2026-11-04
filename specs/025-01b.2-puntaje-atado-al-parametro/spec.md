# Feature Specification: Puntaje atado al parámetro (01b.2)

**Feature Branch**: `025-01b.2-puntaje-atado-al-parametro`

**Created**: 2026-11-03

**Status**: Draft

**Input**: User description: "01b.2 Puntaje atado al parámetro (documento fuente `01b.2-puntaje-atado-al-parametro.md`, copiado en esta carpeta; inc-01b Ajuste de categorías y parámetros, semana 3 del plan, 26–30 oct). La puntuación base de una categoría no puede superar los puntos por colaborador, para que un solo reconocimiento no consuma más de lo que aporta una persona al presupuesto del líder."

Origen: Plan. Depende de: 01b.1. Fuera de alcance: ajustar automáticamente las puntuaciones cuando cambia el parámetro; puntuaciones distintas por unidad.

## Clarifications

### Session 2026-11-03

- Q: Si una categoría ya supera el máximo permitido y el Gestor solo cambia otro campo, ¿debe bajar primero la puntuación? → A: Sí; para guardar cualquier cambio la puntuación debe quedar dentro del rango.
- Q: (Aportado por el usuario) ¿Qué pasa al activar una categoría inactiva cuya puntuación supera el máximo permitido? → A: Se rechaza la activación (422) con el mensaje «Baja primero la puntuación a {máximo} o menos». La regla de 01b.1 solo mira categorías activas; sin esto una categoría reactivada podría quedar por encima del tope.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Puntuación acotada por el parámetro al crear o editar (Priority: P1)

Como Gestor, quiero que la puntuación base de una categoría sea un entero entre 1 y el máximo permitido, para que un solo reconocimiento no consuma más de lo que una persona aporta al presupuesto del líder. El máximo permitido es el valor vigente de puntos por colaborador o, si hay un cambio programado para el mes siguiente, el menor de los dos.

**Why this priority**: Es la regla central de la función; sin ella una categoría puede costar más que todo el aporte de una persona.

**Independent Test**: Con puntos por colaborador en 100, crear una categoría con puntuación 100 (se guarda) y otra con 101 (se rechaza); repetir editando una existente.

**Acceptance Scenarios**:

1. **Given** puntos por colaborador vigentes en 100 y ningún cambio programado, **When** el Gestor crea o edita una categoría con puntuación 100, **Then** se guarda.
2. **Given** el mismo parámetro, **When** intenta guardar una puntuación de 101, 0, negativa, decimal o vacía, **Then** no se guarda y se muestra el error en el campo.
3. **Given** puntos vigentes en 100 y un cambio programado a 60 para el mes siguiente, **When** el Gestor intenta guardar una puntuación de 80, **Then** se rechaza porque el máximo permitido es 60.
4. **Given** puntos vigentes en 100 y un cambio programado a 150 para el mes siguiente, **When** el Gestor guarda una puntuación de 120, **Then** se rechaza porque el máximo permitido es 100 (el menor de los dos).
5. **Given** el máximo permitido de 100, **When** guarda una puntuación de 1, **Then** se guarda (límite inferior).

---

### User Story 2 - Ver el máximo permitido en el formulario (Priority: P1)

Como Gestor, quiero ver el máximo permitido junto al campo de puntuación para saber el tope antes de escribir y entender por qué se rechaza un valor.

**Why this priority**: Evita prueba y error y explica cuándo el tope viene de un cambio programado.

**Independent Test**: Abrir el formulario de crear y de editar y comprobar que junto al campo aparece el máximo permitido correcto en cada situación (sin cambio programado, con cambio programado menor, con cambio programado mayor).

**Acceptance Scenarios**:

1. **Given** el formulario de crear o editar categoría, **When** se abre, **Then** junto al campo de puntuación se muestra el máximo permitido.
2. **Given** que el máximo viene de un cambio programado menor que el vigente, **When** se muestra el formulario, **Then** el texto indica que el tope baja por el cambio de puntos por colaborador que rige desde el día 1 del mes siguiente.
3. **Given** una puntuación fuera de rango, **When** el Gestor intenta guardar, **Then** el error aparece en el campo con el máximo permitido, lo escrito se conserva y no se guarda nada.
4. **Given** el parámetro cambió después de abrir el formulario, **When** el Gestor guarda, **Then** se valida con el máximo vigente en ese momento, no con el que se mostró al abrir.

---

### User Story 3 - Validación también en el servidor (Priority: P1)

Como organización, queremos que la regla se haga cumplir aunque la solicitud no venga del formulario, para que ninguna vía permita una categoría por encima del máximo.

**Why this priority**: La validación solo en pantalla se puede eludir; la regla debe ser confiable.

**Independent Test**: Enviar directamente a la API una creación y una edición con puntuación mayor al máximo y comprobar la respuesta 422 con el error en el campo de puntuación y sin cambios guardados.

**Acceptance Scenarios**:

1. **Given** una solicitud de crear con puntuación mayor al máximo permitido, **When** llega al servidor, **Then** responde 422 con el error en el campo de puntuación y no crea la categoría.
2. **Given** una solicitud de editar con puntuación mayor al máximo, **When** llega al servidor, **Then** responde 422 y la categoría no cambia.
3. **Given** una puntuación no entera o no numérica, **When** llega al servidor, **Then** responde 422.

---

### User Story 4 - Los registros ya hechos no cambian (Priority: P2)

Como Gestor, quiero cambiar la puntuación de una categoría sin alterar los puntos de reconocimientos ni de desafíos ya registrados, para conservar el historial intacto.

**Why this priority**: Protege datos históricos; la regla ya existe en inc-01 y debe seguir cumpliéndose.

**Independent Test**: Con un reconocimiento y un desafío registrados con una categoría, bajar la puntuación de la categoría y comprobar que los puntos de ambos registros no cambian.

**Acceptance Scenarios**:

1. **Given** reconocimientos registrados con una categoría, **When** el Gestor baja su puntuación, **Then** los puntos de esos reconocimientos no cambian.
2. **Given** desafíos registrados con una categoría, **When** cambia su puntuación, **Then** los puntos de esos desafíos no cambian.
3. **Given** un cambio de puntuación guardado, **When** se crean nuevos registros con la categoría, **Then** usan la puntuación nueva.

---

### User Story 5 - Las demás reglas de categorías se mantienen (Priority: P2)

Como Gestor, quiero que el resto del comportamiento de las categorías siga igual (nombre, descripción, competencia, ícono, indicadores, estado, auditoría y edición concurrente).

**Why this priority**: Evita regresiones; esta función solo acota la puntuación.

**Independent Test**: Ejecutar los escenarios existentes de categorías de inc-01 con una puntuación dentro del máximo y comprobar que dan el mismo resultado que antes.

**Acceptance Scenarios**:

1. **Given** una puntuación válida, **When** se crea o edita una categoría, **Then** las validaciones de los demás campos, la auditoría de cambios, el conflicto de modificación concurrente y los permisos se comportan como antes.
2. **Given** desactivar una categoría, o activar una cuya puntuación está dentro del máximo permitido, **When** se ejecuta, **Then** el comportamiento no cambia por esta función.
3. **Given** una categoría inactiva con puntuación mayor que el máximo permitido, **When** el Gestor intenta activarla, **Then** la activación se rechaza (422) con el mensaje «Baja primero la puntuación a {máximo} o menos» y la categoría sigue inactiva.
4. **Given** esa misma categoría, **When** el Gestor baja su puntuación al máximo o menos y luego la activa, **Then** la activación se completa.

### Edge Cases

- Sin cambio programado, el máximo permitido es exactamente el valor vigente de puntos por colaborador.
- Cambio programado igual al vigente: el máximo es ese mismo valor.
- Puntuación igual al máximo: se acepta; un punto por encima: se rechaza.
- Valor vacío, decimal, negativo, texto o con separadores: se rechaza con error en el campo.
- El parámetro cambia entre que se abre el formulario y se guarda: se valida con el máximo vigente al guardar.
- Una categoría existente quedó con puntuación mayor que el máximo (por ejemplo, inactiva): al editarla, el Gestor debe bajarla al rango antes de guardar cualquier cambio, incluso si solo toca otros campos; activarla se rechaza hasta que la baje; consultarla y desactivarla no se modifican por esta función.
- Activar una categoría cuya puntuación está justo en el máximo permitido: se acepta.
- Activar una categoría ya activa o inexistente: se comporta como antes.
- Cambio de mes: el cambio programado pasa a ser el valor vigente y el máximo permitido se actualiza sin intervención.
- Los reconocimientos y desafíos ya registrados con una puntuación mayor al máximo actual se conservan sin cambios.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Al crear o editar una categoría, la puntuación base MUST ser un número entero de 1 al máximo permitido.
- **FR-002**: El máximo permitido MUST ser el valor vigente de puntos por colaborador o, si hay un cambio programado para el mes siguiente, el menor de los dos valores.
- **FR-003**: El formulario de crear y editar MUST mostrar el máximo permitido junto al campo de puntuación, e indicar cuando el tope proviene de un cambio programado para el mes siguiente.
- **FR-004**: Una puntuación fuera de rango MUST mostrar el error en el campo, conservar lo escrito y no guardar nada.
- **FR-005**: El servidor MUST validar la regla en crear y editar y responder 422 con el error en el campo de puntuación cuando no se cumpla, sin depender de la validación del formulario.
- **FR-006**: La validación MUST usar el máximo permitido vigente en el momento de guardar.
- **FR-007**: Cambiar la puntuación de una categoría MUST NOT modificar los puntos de reconocimientos ni de desafíos ya registrados.
- **FR-008**: Las demás reglas de categorías de inc-01 (campos, indicadores, estado, auditoría de cambios, edición concurrente y permisos) MUST NOT cambiar, salvo la regla de activación de FR-011.
- **FR-009**: El sistema MUST NOT ajustar automáticamente las puntuaciones cuando cambia el parámetro, ni ofrecer puntuaciones distintas por unidad.
- **FR-010**: Al editar una categoría cuya puntuación actual supera el máximo permitido, el sistema MUST exigir una puntuación dentro del rango para guardar cualquier cambio, aunque el Gestor solo modifique otros campos.
- **FR-011**: Al activar una categoría inactiva cuya puntuación supera el máximo permitido vigente en ese momento, el sistema MUST rechazar la activación con 422 y el mensaje «Baja primero la puntuación a {máximo} o menos», dejando la categoría inactiva. Desactivar no se ve afectado.

### Key Entities

- **Categoría de reconocimiento** (existente): catálogo maestro con puntuación base que ahora debe respetar el máximo permitido.
- **Parámetro «puntos por colaborador»** (de 01b.1): aporta el valor vigente y, si existe, el valor programado para el día 1 del mes siguiente.
- **Máximo permitido**: valor derivado (el menor entre el vigente y el programado) que acota la puntuación; no se almacena como dato propio.
- **Reconocimiento y desafío registrados** (existentes): conservan los puntos con los que se registraron.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100 % de los intentos de guardar una categoría con puntuación mayor al máximo permitido, menor que 1 o no entera se rechaza, tanto desde el formulario como por solicitud directa al servidor.
- **SC-002**: Ninguna categoría puede quedar guardada ni activada con una puntuación mayor al máximo permitido vigente en el momento de guardar o activar.
- **SC-003**: El Gestor ve el máximo permitido antes de escribir la puntuación en el 100 % de las aperturas del formulario, y el error de un valor rechazado aparece junto al campo sin recargar la página ni perder lo escrito.
- **SC-004**: Tras cambiar la puntuación de una categoría, los puntos del 100 % de los reconocimientos y desafíos ya registrados permanecen idénticos.
- **SC-005**: Los escenarios existentes de categorías de inc-01 con puntuación válida siguen dando el mismo resultado (0 regresiones).

## Assumptions

- El único usuario de esta función es el Gestor de Talento (en el repositorio de prueba, el rol Administrador), con los permisos de categorías ya existentes.
- «Valor programado» es el cambio de puntos por colaborador que 01b.1 deja pendiente para el día 1 del mes siguiente; esta función lo consulta, no lo define.
- La regla de 01b.1 que impide fijar los puntos por colaborador por debajo de la categoría activa más alta se mantiene; esta función cubre el lado de las categorías.
- La regla se exige siempre que se guarde el formulario de una categoría, incluso si solo se editan otros campos, según CA-01; una categoría que ya supere el máximo debe bajarse antes de guardar cambios.
- La activación valida la puntuación contra el máximo permitido porque la regla de 01b.1 solo considera categorías activas; esta es la única excepción a «las demás reglas no cambian» (CA-05), y desactivar no cambia.
- El rango máximo de 1000 de inc-01 queda reemplazado por el máximo permitido, que nunca supera 1000 porque ese es el tope del parámetro.
- La puntuación sigue siendo solo un valor de ordenamiento del ranking, sin valor monetario.

## Pantallas *(si la feature tiene UI)*

### Formulario de categoría (crear y editar)
- **Propósito**: Crear o editar una categoría respetando el máximo de puntuación permitido por el parámetro.
- **Datos que muestra**: los campos existentes de la categoría; junto a la puntuación base, el máximo permitido y, cuando aplique, la nota de que el tope baja por el cambio programado del mes siguiente.
- **Acciones disponibles** (y quién puede hacerlas): crear y editar, solo el Gestor de Talento. En el listado, «Activar» de una categoría por encima del máximo muestra el mensaje «Baja primero la puntuación a {máximo} o menos».
- **Estados**: error por campo (incluida la puntuación fuera de rango, con el máximo), envío pendiente, conflicto de modificación concurrente, error general, sin permisos.
- **Mockup**: modal del patrón Formulario existente de categorías (`design/DESIGN.md`); solo se agrega el texto de máximo permitido junto al campo de puntuación.
