# Feature Specification: Categorías por unidad y visibles por rol (01b.4)

**Feature Branch**: `026-01b.4-categorias-visibles-por-rol`

**Created**: 2026-11-03

**Status**: Draft

**Input**: User description: "01b.4 Categorías por unidad y visibles por rol (documento fuente `01b.4-categorias-visibles-por-rol.md`, copiado en esta carpeta; inc-01b Ajuste de categorías y parámetros, semana 3 del plan, 26–30 oct). Hoy todas las categorías son iguales para toda la organización; las unidades tienen trabajos distintos y no todas las conductas aplican a todas. El colaborador debe ver solo las categorías que corresponden a su unidad."

Origen: Nuestro. Depende de: 01b.3. Fuera de alcance: categorías por persona o por puesto; que las unidades creadas después se agreguen solas a una categoría marcada con «Todas las unidades».

**Roles** (en el repositorio de prueba): Gestor de Talento = Administrador; Líder = Colaborador o Administrador con equipo (su alcance es su equipo); Colaborador; Gerencia = solo consulta (Administrador).

## Clarifications

### Session 2026-11-03

- Q: Si las unidades forman una jerarquía, ¿una categoría asignada a una unidad también aplica a las personas de sus subunidades? → A: No; solo asignación directa, sin herencia hacia arriba ni hacia abajo.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Asignar categorías a unidades (Priority: P1)

Como Gestor, quiero asignar cada categoría a una o varias unidades (o a todas), para que cada equipo vea las conductas que le aplican.

**Why this priority**: Es la base de toda la función; sin la asignación no hay nada que filtrar por rol.

**Independent Test**: Crear una categoría eligiendo dos unidades activas y comprobar que queda con esas dos; editarla con «Todas las unidades» y comprobar que queda con todas las unidades activas; intentar guardar sin unidades.

**Acceptance Scenarios**:

1. **Given** el formulario de crear o editar una categoría, **When** el Gestor elige una o varias unidades activas y guarda, **Then** la categoría queda asignada exactamente a esas unidades.
2. **Given** el mismo formulario, **When** marca «Todas las unidades», **Then** quedan seleccionadas todas las unidades activas en ese momento y se guardan como asignación individual de cada una.
3. **Given** una selección sin ninguna unidad, **When** intenta guardar, **Then** no se guarda, se muestra el error en el campo de unidades («Elige al menos una unidad») y lo demás escrito se conserva.
4. **Given** «Todas las unidades» marcada, **When** el Gestor desmarca una unidad, **Then** «Todas las unidades» se desmarca y el resto de la selección se conserva.
5. **Given** una categoría asignada a unidades, **When** el Gestor cambia las unidades y guarda, **Then** el cambio queda en la auditoría de la categoría con las unidades anteriores y las nuevas.
6. **Given** el selector de unidades, **When** se abre, **Then** solo ofrece unidades activas y muestra el alcance de la selección actual en unidades («La verán N unidades» o «Nadie la verá»), sin conteo de personas.

---

### User Story 2 - El colaborador ve solo las categorías de su unidad (Priority: P1)

Como colaborador, quiero ver solo las categorías activas asignadas a mi unidad, para saber qué se reconoce en mi trabajo.

**Why this priority**: Es el resultado visible para quien recibe reconocimientos.

**Independent Test**: Con categorías asignadas a distintas unidades, entrar como colaborador de una unidad y comprobar que solo aparecen las activas asignadas a ella.

**Acceptance Scenarios**:

1. **Given** categorías activas asignadas a distintas unidades, **When** un colaborador abre el listado, **Then** solo ve las activas asignadas a su unidad actual.
2. **Given** una categoría inactiva asignada a su unidad, **When** abre el listado, **Then** no la ve.
3. **Given** que su unidad no tiene ninguna categoría activa asignada, **When** abre el listado, **Then** ve el estado vacío «Todavía no tienes categorías asignadas».
4. **Given** que el colaborador cambia de unidad, **When** vuelve a abrir el listado, **Then** ve las categorías de su unidad nueva.
5. **Given** una categoría no asignada a su unidad, **When** solicita su detalle por cualquier vía, **Then** no obtiene acceso (como si no existiera).
6. **Given** el colaborador, **When** abre el listado, **Then** no ve acciones de edición ni la asignación de unidades.
7. **Given** una categoría asignada a una unidad que está inactiva, **When** un colaborador de esa unidad abre el listado, **Then** no la ve; si la unidad se reactiva, vuelve a verla.

---

### User Story 3 - Consulta disponible de categorías de la unidad de la persona para reconocer y asignar desafíos (Priority: P1)

Como líder, quiero que al reconocer o asignar un desafío solo aparezcan las categorías de la unidad de la persona, para no equivocarme. Esta función entrega la **consulta** que hace posible esa regla; las pantallas y el rechazo 422 los implementan las funciones de desafíos (02.3) y reconocimientos (03.1), que aún no existen.

**Why this priority**: Evita reconocimientos con conductas que no aplican a la persona; todas las funciones consumidoras deben apoyarse en una sola regla.

**Independent Test**: Con una categoría asignada solo a Ventas, comprobar que la consulta de categorías seleccionables para Operaciones no la incluye y que la de Ventas sí.

**Acceptance Scenarios**:

1. **Given** la unidad de una persona, **When** una función de reconocimientos o desafíos consulta las categorías seleccionables de esa unidad, **Then** recibe solo categorías activas asignadas a ella (y con su competencia activa).
2. **Given** una categoría inactiva o no asignada a la unidad, **When** se consulta, **Then** no está en el resultado, de modo que 02.3 y 03.1 puedan rechazarla con 422 al registrar.
3. **Given** una unidad sin categorías activas asignadas, **When** se consulta, **Then** el resultado es vacío (el mensaje en pantalla lo muestran 02.3 y 03.1).
4. **Given** que la persona cambió de unidad, **When** se consulta con su unidad actual, **Then** el resultado corresponde a la unidad nueva.

---

### User Story 4 - Consulta de categorías seleccionables por unidad (Priority: P2)

Como función de desafíos o de reconocimientos, necesito una consulta del módulo que devuelva las categorías seleccionables para una unidad, para reutilizar una sola regla.

**Why this priority**: Evita duplicar la regla en cada función; depende de la asignación (P1) pero no la bloquea.

**Independent Test**: Consultar las categorías seleccionables de una unidad y comprobar que devuelve exactamente las activas asignadas a ella.

**Acceptance Scenarios**:

1. **Given** una unidad, **When** se consultan sus categorías seleccionables, **Then** devuelve solo las activas asignadas a ella.
2. **Given** una unidad inexistente o inactiva, **When** se consulta, **Then** no devuelve categorías ni expone datos de otras unidades.
3. **Given** que cambia la asignación o el estado de una categoría, **When** se vuelve a consultar, **Then** el resultado refleja el cambio de inmediato.

---

### User Story 5 - Líder y Gerencia consultan todas las categorías activas (Priority: P2)

Como líder o como Gerencia, quiero consultar todas las categorías activas con sus unidades, sin poder editarlas, para entender el catálogo completo.

**Why this priority**: Da visibilidad de lectura; no cambia datos.

**Independent Test**: Entrar como líder y como Gerencia y comprobar que ven todas las categorías activas con sus unidades y ninguna acción de edición; el intento de editar por solicitud directa se niega.

**Acceptance Scenarios**:

1. **Given** categorías activas e inactivas, **When** un líder o Gerencia abre el listado, **Then** ve todas las activas con las unidades a las que aplican y ninguna inactiva.
2. **Given** ese listado, **When** intenta crear, editar, activar, desactivar o cambiar unidades por solicitud directa, **Then** se le niega (403).
3. **Given** el Gestor, **When** abre el listado, **Then** ve todas las categorías, activas e inactivas, con sus unidades, y puede administrarlas.

---

### User Story 6 - Las categorías existentes siguen funcionando (Priority: P2)

Como Gestor, quiero que las categorías ya creadas no queden sin unidad al activarse esta función, para que nadie deje de poder recibir reconocimientos.

**Why this priority**: Evita una regresión masiva al entrar en vigor la regla de «al menos una unidad».

**Independent Test**: Con categorías creadas antes de esta función, comprobar que cada una queda asignada a todas las unidades activas y que los reconocimientos y desafíos previos no cambian.

**Acceptance Scenarios**:

1. **Given** categorías existentes sin asignación, **When** entra en vigor esta función, **Then** cada una queda asignada a todas las unidades activas existentes en ese momento.
2. **Given** reconocimientos y desafíos ya registrados, **When** cambia la asignación de unidades de su categoría, **Then** esos registros no cambian.

### Edge Cases

- Una unidad asignada pasa a inactiva: deja de contar para quién ve la categoría; la asignación se conserva y, si la unidad se reactiva, vuelve a aplicar. Si todas las unidades asignadas quedan inactivas, la categoría sigue activa pero nadie la ve; el Gestor ve una advertencia en el listado.
- Una unidad creada después de marcar «Todas las unidades» no se agrega sola a la categoría.
- Una categoría asignada a una unidad padre no aplica a sus subunidades (ni al revés); cada unidad se asigna por separado.
- Colaborador sin unidad actual: no ve categorías (estado vacío).
- Una categoría se desasigna de la unidad de la persona entre abrir la pantalla y enviar: el servidor la rechaza con 422.
- El Gestor edita otros campos sin tocar las unidades: la asignación no cambia y no se audita un cambio de unidades.
- Dos Gestores editan la misma categoría: se aplica el control de edición concurrente ya existente.
- Desactivar una categoría conserva su asignación de unidades; al reactivarla vuelve a aplicar.
- Una persona que es colaborador y líder a la vez: en el listado ve lo que corresponde a su rol de mayor alcance (todas las activas); la restricción por unidad aplica a la acción de reconocer o asignar un desafío a una persona.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Al crear o editar una categoría, el Gestor MUST poder elegir una o varias unidades activas, o «Todas las unidades», que selecciona todas las unidades activas en ese momento.
- **FR-002**: Una categoría MUST tener al menos una unidad; sin unidades no se guarda, tanto desde el formulario como por solicitud directa al servidor (422 con el error en el campo de unidades).
- **FR-003**: Solo MUST poder asignarse unidades activas; asignar una unidad inexistente o inactiva se rechaza con 422.
- **FR-004**: El Gestor MUST ver y administrar todas las categorías, activas e inactivas, con sus unidades.
- **FR-005**: El líder y Gerencia MUST poder consultar todas las categorías activas con sus unidades, sin crear, editar, activar, desactivar ni cambiar unidades.
- **FR-006**: El colaborador MUST ver solo las categorías activas asignadas a su unidad actual, sin acceso a las demás ni por detalle directo.
- **FR-007**: El módulo MUST dejar disponible, para reconocer y asignar desafíos, la regla de que solo pueden elegirse categorías activas asignadas a la unidad actual de la persona. El rechazo con 422 y la lista vacía con mensaje los implementan las funciones de desafíos (02.3) y reconocimientos (03.1) apoyándose en la consulta de FR-008.
- **FR-008**: El módulo MUST ofrecer una consulta de categorías seleccionables para una unidad (activas y asignadas a ella), disponible para las funciones de desafíos y reconocimientos.
- **FR-009**: Todo cambio en las unidades de una categoría MUST quedar en la auditoría de la categoría, con las unidades anteriores, las nuevas, quién lo hizo y cuándo; si las unidades no cambian, no se audita.
- **FR-010**: Al entrar en vigor la función, cada categoría existente MUST quedar asignada a todas las unidades activas existentes en ese momento.
- **FR-011**: Marcar «Todas las unidades» MUST asignar las unidades activas del momento; las unidades creadas después NO se agregan solas.
- **FR-012**: Cambiar las unidades de una categoría MUST NOT modificar reconocimientos ni desafíos ya registrados.
- **FR-013**: Las demás reglas de categorías (campos, puntuación, indicadores, estado, auditoría, edición concurrente) MUST NOT cambiar.
- **FR-014**: El selector de unidades MUST mostrar el alcance de la selección actual en unidades («La verán N unidades»); sin unidades muestra «Nadie la verá». No muestra conteo de personas porque ningún módulo publica ese dato.
- **FR-015**: El listado MUST adaptarse al rol: el Gestor ve el editor de asignación; líder y Gerencia ven las unidades como texto; el colaborador no ve unidades ni edición.
- **FR-017**: La asignación MUST ser directa: una persona solo ve y recibe las categorías asignadas exactamente a su unidad actual, sin herencia desde unidades que la contienen ni hacia subunidades; «Todas las unidades» selecciona cada unidad activa individualmente.
- **FR-016**: Las categorías por persona o por puesto NO forman parte de esta función.

### Key Entities

- **Categoría de reconocimiento** (existente): catálogo maestro; ahora tiene una o más unidades asignadas.
- **Asignación categoría–unidad**: relación entre una categoría y una unidad activa; define quién ve y puede recibir la categoría.
- **Unidad organizacional** (existente, módulo de estructura): agrupa colaboradores; puede estar activa o inactiva.
- **Colaborador / persona** (existente): pertenece a una unidad actual; la categoría que puede recibir depende de ella.
- **Auditoría de la categoría** (existente): registra, ahora también, los cambios de unidades.
- **Categorías seleccionables de una unidad**: vista derivada (activas y asignadas a la unidad) que consumen reconocimientos y desafíos.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100 % de los colaboradores ve únicamente categorías activas asignadas a su unidad actual; ninguna de otra unidad ni inactiva aparece en su listado ni es accesible por solicitud directa.
- **SC-002**: La consulta de categorías seleccionables para una unidad excluye el 100 % de las categorías inactivas, de otras unidades, con competencia inactiva o sin unidades.
- **SC-003**: Ninguna categoría puede quedar guardada sin al menos una unidad activa asignada.
- **SC-004**: El Gestor asigna unidades a una categoría (elegir, ver el alcance y guardar) en menos de 1 minuto.
- **SC-005**: El 100 % de los cambios de unidades queda registrado en la auditoría con valores anteriores y nuevos.
- **SC-006**: Tras activarse la función, el 100 % de las categorías existentes queda asignado a todas las unidades activas y ningún reconocimiento ni desafío previo cambia.
- **SC-007**: El líder ve el 100 % de las categorías activas y ningún intento de edición por su parte tiene efecto. Gerencia queda como limitación del repositorio de prueba (ver Assumptions).

## Assumptions

- La unidad «actual» de una persona proviene del módulo de estructura (unidad organizacional vigente); cada persona tiene una sola unidad actual.
- El rol «Gestor de Talento» ya existe por 01b.3; Líder y Gerencia se resuelven con los roles del repositorio de prueba descritos arriba.
- **Limitación de Gerencia**: en el repositorio de prueba Gerencia es el Administrador, que también gestiona; un modo de solo consulta para Gerencia llegará con el módulo de autorización y no se verifica en esta función (FR-005 y SC-007 se comprueban con el líder).
- Si una solicitud trae a la vez una lista de unidades y «Todas las unidades», prevalece «Todas las unidades».
- Las categorías existentes se migran a «todas las unidades activas» para no interrumpir el uso actual (decisión por defecto; el Gestor las ajusta después).
- Una unidad inactiva se excluye de la visibilidad pero la asignación se conserva.
- El selector no muestra conteo de personas por unidad: no existe un método publicado para contarlas. Si Gestión de Personal lo publica, se agrega en una función posterior.
- Las funciones de reconocimientos (03.1) y desafíos (02.3) consumen la consulta de categorías seleccionables; el rechazo 422, la lista vacía con mensaje y sus pantallas los implementan ellas. Esta función entrega y prueba la consulta.

## Pantallas *(si la feature tiene UI)*

### Categorías de reconocimiento (listado por rol)
- **Propósito**: Ver las categorías que corresponden a cada rol y, para el Gestor, asignarlas a unidades.
- **Datos que muestra**: nombre, competencia, puntuación y «Aplica a: {unidades}» (o «todas las unidades»); para el Gestor también el estado y las inactivas; un banner por rol explica el alcance.
- **Acciones disponibles** (y quién puede hacerlas): el Gestor asigna unidades dentro del formulario (modal) de crear y editar categoría, con la casilla «Todas las unidades» y una casilla por unidad activa, y lo guarda con el «Guardar» del formulario; líder consulta; el colaborador solo ve las de su unidad, sin unidades ni edición. Gerencia: ver Assumptions.
- **Estados**: vacío («Todavía no tienes categorías asignadas»), cargando, error, sin permisos, error en unidades («Elige al menos una unidad.»), éxito (se reutiliza el mensaje de éxito existente al guardar una categoría), advertencia de categoría sin unidades o sin unidades activas.
- **Mockup**: patrón Listado y Formulario (`design/CategoriasReconocimiento.dc.html`, que se actualiza con el selector; ver `design/DESIGN.md`). `design/CategoriasPorRol.dc.html` es referencia del listado por rol; sus diferencias (panel lateral con Guardar/Descartar propio, mensaje «Asignación guardada…» y conteo de personas) se registran en `plan.md`.
