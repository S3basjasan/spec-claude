# Feature Specification: Parámetros del módulo (01b.1)

**Feature Branch**: `024-01b.1-parametros-del-modulo`

**Created**: 2026-11-03

**Status**: Draft

**Input**: User description: "01b.1 Parámetros del módulo (documento fuente `01b.1-parametros-del-modulo.md`, copiado en esta carpeta; inc-01b Ajuste de categorías y parámetros, semana 3 del plan, 26–30 oct). El Gestor de Talento ajusta seis parámetros de la organización sin cambiar código: presupuesto de reconocimiento por líder, plazo de anulación, umbrales de alertas y duración máxima de un desafío."

Origen: Nuestro. Depende de: —. Fuera de alcance: parámetros distintos por unidad o por líder; deshacer un cambio desde la pantalla; conversión de puntos a saldo (fija 1 a 1); tope diario de llamadas a la IA.

## Clarifications

### Session 2026-11-03

- Q: ¿Quién puede ver y cambiar los parámetros? → A: Solo el Gestor de Talento (en el repositorio de prueba, el rol Administrador). El permiso `parametros.gestionar` queda reservado para esta función (anunciado en 01b.3).
- Q: ¿El Gestor guarda los seis parámetros juntos con un solo botón, o cada uno por separado? → A: Un solo formulario y un solo «Guardar»; si un campo es inválido o hay conflicto, no se guarda ninguno.
- Q: ¿Qué muestra la vista previa del presupuesto? → A: Solo valor actual → nuevo, la fecha desde la que rige (día 1 del mes siguiente), el ejemplo fijo «Un equipo de 10 personas tendría X puntos al mes» y el valor programado si lo hay. Sin cálculo por líder: el EquipoPort llega en 01b.4.
- Q: Si se crea o sube la puntuación de una categoría por encima de los puntos por colaborador, ¿lo bloquea el módulo de categorías? → A: No se valida en esta función; esa regla es la spec 01b.2 «Puntaje atado al parámetro». En 01b.1 solo se valida el lado del parámetro (FR-005).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ajustar los puntos por colaborador (Priority: P1)

Como Gestor, quiero ajustar los puntos por colaborador al mes para controlar cuánto puede reconocer cada jefatura. El cambio aplica desde el día 1 del mes siguiente y la pantalla me muestra el valor actual → nuevo, la fecha de vigencia y un ejemplo de equipo de 10 personas.

**Why this priority**: Es el parámetro con mayor impacto y el único con regla de vigencia diferida y validación contra las categorías.

**Independent Test**: Abrir «Parámetros» como Gestor, cambiar los puntos por colaborador, ver la vista previa del presupuesto y guardar; verificar que el presupuesto del mes en curso no cambia y que el nuevo valor rige desde el día 1 del mes siguiente.

**Acceptance Scenarios**:

1. **Given** el valor inicial de 100 y sin categorías activas con puntuación mayor, **When** el Gestor abre la pestaña, **Then** ve 100 y el rango permitido (1 a 1000).
2. **Given** una categoría activa con puntuación mayor que 100 al crear el parámetro por primera vez, **When** se muestra el parámetro, **Then** su valor inicial es esa puntuación.
3. **Given** que el Gestor escribe un valor, **When** aún no guarda, **Then** la pantalla muestra valor actual → nuevo, desde cuándo aplica (día 1 del mes siguiente) y el ejemplo «Un equipo de 10 personas tendría X puntos al mes».
4. **Given** un valor menor que la puntuación de la categoría activa más alta, **When** intenta guardar, **Then** se rechaza y se indica el nombre de esa categoría.
5. **Given** un guardado exitoso, **When** se consulta el presupuesto del mes en curso, **Then** no cambió; el nuevo valor figura como pendiente hasta el día 1 del mes siguiente.

---

### User Story 2 - Definir plazo de anulación y duración máxima de un desafío (Priority: P1)

Como Gestor, quiero definir el plazo de anulación (días) y el plazo máximo de un desafío (días) para adaptar las reglas a la organización.

**Why this priority**: Parámetros de reglas operativas de uso frecuente, de aplicación inmediata y sin dependencias entre sí.

**Independent Test**: Cambiar ambos valores dentro de rango, guardar y comprobar que el plazo de anulación rige de inmediato y que el plazo máximo solo afecta a desafíos asignados después.

**Acceptance Scenarios**:

1. **Given** el plazo de anulación en 7 días, **When** el Gestor lo cambia a 10 y guarda, **Then** rige de inmediato.
2. **Given** desafíos ya asignados, **When** el Gestor cambia el plazo máximo, **Then** los desafíos existentes conservan su plazo y solo los asignados después usan el nuevo.
3. **Given** un valor fuera de rango (p. ej. 0 o 31 días de anulación) o no entero, **When** intenta guardar, **Then** se muestra el error en el campo y no se guarda nada.

---

### User Story 3 - Fijar umbrales y sensibilidad de alertas (Priority: P2)

Como Gestor, quiero fijar el umbral de concentración, el umbral de clima por unidad y la sensibilidad del radar de clima para decidir cuándo me avisan.

**Why this priority**: Afina alertas; el módulo funciona con los valores iniciales.

**Independent Test**: Cambiar los umbrales y elegir una sensibilidad; verificar que se guardan y que el radar usa la sensibilidad desde el siguiente cálculo.

**Acceptance Scenarios**:

1. **Given** la pantalla de parámetros, **When** el Gestor elige la sensibilidad, **Then** ve exactamente tres opciones (Baja, Normal, Alta), cada una con una explicación en palabras simples, y no escribe ningún umbral numérico.
2. **Given** un cambio de umbral de concentración o de clima, **When** guarda, **Then** rige de inmediato.
3. **Given** un cambio de sensibilidad, **When** guarda, **Then** aplica desde el siguiente cálculo del radar, no retroactivamente.

---

### User Story 4 - Ver trazabilidad de cambios (Priority: P2)

Como Gestor, quiero ver quién cambió un parámetro y cuándo para tener trazabilidad.

**Why this priority**: Control y rendición de cuentas; no bloquea el uso de los parámetros.

**Independent Test**: Cambiar un parámetro y verificar que la pantalla muestra al autor, la fecha y hora, el valor anterior y el nuevo; cambiarlo de nuevo y verificar que el cambio anterior sigue registrado.

**Acceptance Scenarios**:

1. **Given** un cambio guardado, **When** el Gestor mira el parámetro, **Then** ve quién lo hizo, cuándo, el valor anterior y el nuevo del último cambio.
2. **Given** varios cambios sucesivos, **When** se consulta el registro, **Then** todos los cambios anteriores siguen presentes; ninguno se borra ni se edita.
3. **Given** un parámetro nunca modificado, **When** se muestra, **Then** indica que está en su valor inicial.
4. **Given** el primer cambio de un parámetro, **When** se muestra su último cambio, **Then** dice «de valor por defecto a {nuevo}», sin número anterior; desde el segundo cambio muestra el valor anterior real.

---

### User Story 5 - Acceso restringido al Gestor y edición concurrente (Priority: P1)

Como organización, queremos que solo el Gestor vea y cambie los parámetros y que dos personas no se sobrescriban sin saberlo.

**Why this priority**: Seguridad y consistencia de datos de configuración que afectan a toda la organización.

**Independent Test**: Entrar con un usuario no Gestor (no ve la pestaña y la API responde 403) y simular dos ediciones simultáneas (la segunda se rechaza con aviso).

**Acceptance Scenarios**:

1. **Given** un usuario que no es Gestor, **When** abre el módulo, **Then** no ve la pestaña «Parámetros».
2. **Given** un usuario que no es Gestor, **When** consulta o intenta cambiar parámetros por la API, **Then** recibe 403.
3. **Given** que el Gestor A abrió un parámetro y el Gestor B guardó un cambio, **When** A intenta guardar, **Then** se le avisa del cambio y se le pide recargar; no se sobrescribe el valor de B.

### Edge Cases

- Valor vacío, decimal, negativo, texto o con separadores: se rechaza con error en el campo.
- Valor en el límite exacto del rango (1, 1000, 30, 90, 100): se acepta.
- Se guarda sin cambiar nada: no se registra un cambio.
- Se cambian varios parámetros a la vez y uno es inválido: no se guarda ninguno y se señala el campo con error.
- Hay un cambio de puntos pendiente para el mes siguiente y se vuelve a cambiar antes del día 1: rige el último valor guardado.
- La categoría con la puntuación más alta se desactiva después de fijado el parámetro: el valor ya guardado no se altera; la regla se aplica en el siguiente guardado.
- Dos categorías activas empatan en la puntuación más alta: el mensaje nombra al menos una de ellas.
- No hay categorías activas: no hay piso por categoría; rige solo el rango.
- Cambio de mes: el valor pendiente pasa a regir el día 1 sin intervención del Gestor.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema MUST ofrecer seis parámetros únicos para toda la organización, con estos rangos y valores iniciales: puntos por colaborador al mes (1 a 1000; 100), plazo de anulación (1 a 30 días; 7), umbral de concentración (1 a 100 %; 50), plazo máximo de un desafío (1 a 90 días; 30), umbral de clima por unidad (1 a 100 %; 33) y sensibilidad del radar de clima (Baja, Normal o Alta; Normal).
- **FR-002**: La sensibilidad del radar MUST elegirse entre exactamente tres opciones, cada una explicada en palabras simples; el Gestor no escribe umbrales.
- **FR-003**: Si al crearse el parámetro existe una categoría activa con puntuación mayor que 100, el valor inicial de los puntos por colaborador MUST ser esa puntuación.
- **FR-004**: El sistema MUST aceptar solo números enteros dentro del rango de cada parámetro; fuera de él MUST mostrar el error junto al campo y no guardar.
- **FR-005**: Los puntos por colaborador MUST NOT quedar por debajo de la puntuación de la categoría activa más alta; si ocurre, el mensaje MUST nombrar esa categoría.
- **FR-006**: Un cambio en los puntos por colaborador MUST aplicar desde el día 1 del mes siguiente; el presupuesto del mes en curso MUST NOT cambiar, y la pantalla MUST mostrar el valor actual → nuevo, la fecha desde la que rige, el ejemplo fijo «Un equipo de 10 personas tendría X puntos al mes» y el valor programado si lo hay, sin cálculo por líder.
- **FR-007**: Los otros cinco parámetros MUST aplicar de inmediato; el plazo máximo de un desafío MUST afectar solo a desafíos asignados después del cambio, y la sensibilidad del radar MUST regir desde el siguiente cálculo.
- **FR-008**: Cada cambio MUST registrarse con autor, fecha y hora, valor anterior y valor nuevo; los registros MUST NOT borrarse ni modificarse. La pantalla MUST mostrar el último cambio de cada parámetro.
- **FR-009**: Si otra persona guardó un cambio mientras se editaba, el sistema MUST avisar y pedir recargar, sin sobrescribir ningún parámetro del formulario.
- **FR-010**: Solo el Gestor de Talento MUST ver la pestaña «Parámetros» y consultar o cambiar los parámetros; para cualquier otro usuario la API MUST responder 403. El permiso se nombra `parametros.gestionar`.
- **FR-011**: El sistema MUST NOT validar la puntuación de las categorías contra los puntos por colaborador (lo hace 01b.2), ni ofrecer parámetros por unidad o por líder, deshacer desde la pantalla, ni convertir puntos a saldo como parámetro.
- **FR-012**: Los seis parámetros MUST guardarse juntos con un único «Guardar»; si cualquier campo es inválido o hay conflicto de modificación, no se guarda ninguno, y solo se registra un cambio por cada parámetro cuyo valor realmente cambió.

### Key Entities

- **Parámetro del módulo**: valor ajustable de la organización; atributos: nombre, valor vigente, rango permitido, unidad, momento desde el que rige y, para los puntos por colaborador, un valor pendiente con su fecha de inicio.
- **Registro de cambio**: historial inmutable de un cambio; autor, fecha y hora, parámetro, valor anterior y valor nuevo.
- **Categoría de reconocimiento** (existente): aporta la puntuación de la categoría activa más alta que fija el piso de los puntos por colaborador.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El Gestor ajusta cualquiera de los seis parámetros y ve el cambio confirmado en menos de 1 minuto.
- **SC-002**: El 100 % de los valores fuera de rango, no enteros o por debajo del piso de categorías se rechazan con un mensaje en el campo, sin guardar nada.
- **SC-003**: El 100 % de los cambios guardados aparece en el registro con autor, fecha y hora, valor anterior y nuevo, y ninguno se pierde tras cambios posteriores.
- **SC-004**: Tras un cambio de puntos por colaborador, el valor vigente de hoy permanece idéntico (el presupuesto del mes en curso no cambia) y el nuevo valor rige el día 1 del mes siguiente en el 100 % de los casos.
- **SC-005**: Ningún usuario que no es Gestor ve la pestaña ni obtiene datos de parámetros (0 respuestas distintas de 403).
- **SC-006**: En ediciones simultáneas, ningún cambio guardado se sobrescribe sin aviso (0 pérdidas silenciosas).

## Assumptions

- Los parámetros son únicos para toda la organización y se crean con sus valores iniciales la primera vez que el módulo los necesita.
- «Presupuesto» es lo que cada líder puede reconocer al mes (puntos por colaborador × colaboradores a su cargo). Esta función no lo calcula por líder: la vista previa usa un ejemplo fijo de 10 personas, porque el cálculo por equipo llega con 01b.4.
- La pestaña «Parámetros» vive en el módulo de reconocimiento, junto a Categorías.
- En el repositorio de prueba el rol Administrador equivale al Gestor de Talento (según 01b.3).
- El «día 1 del mes siguiente» se calcula con la fecha de la organización.
- Las reglas que consumen estos parámetros (anulación, alertas, desafíos, radar de clima) pertenecen a otras funciones; esta función solo los define, valida, guarda y audita.

## Pantallas *(si la feature tiene UI)*

### Parámetros del módulo
- **Propósito**: Ver y ajustar los seis parámetros de la organización y consultar quién cambió cada uno.
- **Datos que muestra**: por parámetro, nombre, valor vigente, rango permitido, texto de ayuda, y último cambio (quién, cuándo, valor anterior y nuevo); para los puntos por colaborador, valor pendiente con su fecha de inicio y vista previa (actual → nuevo, fecha de vigencia, ejemplo de equipo de 10 personas); para la sensibilidad, tres opciones con explicación.
- **Acciones disponibles** (y quién puede hacerlas): consultar, editar y guardar (solo el Gestor de Talento).
- **Estados**: cargando, error por campo, envío pendiente, conflicto de modificación concurrente con pedido de recargar, error general, sin permisos (la pestaña no se muestra).
- **Mockup**: formulario con tarjetas por parámetro, referencia `design/ParametrosModulo.dc.html` y patrón Formulario de `design/DESIGN.md`.
