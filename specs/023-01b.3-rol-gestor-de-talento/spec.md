# Feature Specification: Nombre del rol «Gestor de Talento» (01b.3)

**Feature Branch**: `023-01b.3-rol-gestor-de-talento`

**Created**: 2026-11-03

**Status**: Draft

**Input**: User description: "01b.3 Nombre del rol «Gestor de Talento» (documento fuente `01b.3-rol-gestor-de-talento.md`, copiado en esta carpeta; inc-01b Ajuste de categorías y parámetros, semana 3 del plan, 26–30 oct). Las pantallas y textos de categorías usan el nombre anterior «Administrador de Talento Humano»; el documento de alcance llama a ese rol «Gestor de Talento» y el nombre anterior se confunde con el Administrador técnico que registra empresas."

Origen: Técnica. Depende de: —. Fuera de alcance: cambiar nombres de roles en otros módulos; crear roles nuevos.

## Clarifications

### Session 2026-11-03

- Q: ¿Qué permisos con formato recurso.acción debe tener el módulo de categorías? → A: Un único permiso por ahora, `categorias.gestionar`, que el punto único de decisión de acceso consulta como `puede($usuario, $permiso)`. Ver categorías no lleva permiso: puede cualquier usuario autenticado. `parametros.gestionar` llega en 01b.1. La capacidad `admin` no se renombra: en el repositorio de prueba, `categorias.gestionar` es verdadero cuando el token tiene `admin`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - El módulo habla de «Gestor de Talento» (Priority: P1)

Como usuario de cualquier rol del módulo de categorías, quiero ver el rol con el nombre «Gestor de Talento» en todas las pantallas, textos, mensajes y avisos, para que coincida con el documento de alcance del proyecto y no se confunda con el Administrador técnico que registra empresas.

**Why this priority**: Es el objetivo completo del cambio; elimina la confusión entre dos roles distintos.

**Independent Test**: Recorrer todas las pantallas del módulo con cada rol y provocar los mensajes y avisos (sección restringida, errores, confirmaciones); en ningún texto visible aparece «Administrador de Talento Humano» y donde se nombra el rol aparece «Gestor de Talento».

**Acceptance Scenarios**:

1. **Given** un usuario sin permiso de gestión, **When** abre una sección del módulo reservada al rol, **Then** el aviso dice que es solo para el «Gestor de Talento».
2. **Given** cualquier pantalla, mensaje de error, confirmación o aviso del módulo, **When** se muestra al usuario, **Then** no contiene «Administrador de Talento Humano».
3. **Given** la maqueta de diseño del módulo, **When** muestra el selector de rol y los datos de ejemplo, **Then** el rol aparece como «Gestor de Talento».

---

### User Story 2 - Los permisos del Gestor se conceden al rol Administrador existente (Priority: P1)

Como equipo, queremos que en el repositorio de prueba los permisos del Gestor de Talento se concedan al rol Administrador que ya existe, sin crear un rol nuevo ni renombrar un rol del sistema, para cambiar solo el nombre mostrado y no la estructura de roles.

**Why this priority**: Evita introducir un rol nuevo o alterar roles del sistema; sin esto el cambio de nombre arrastraría efectos colaterales de acceso.

**Independent Test**: En el repositorio de prueba, comprobar que un usuario con el rol Administrador puede ejecutar todas las acciones del Gestor, que no existe ningún rol adicional y que el nombre interno de los roles del sistema no cambió.

**Acceptance Scenarios**:

1. **Given** el repositorio de prueba, **When** se consulta si un usuario con la capacidad `admin` tiene el permiso `categorias.gestionar`, **Then** es verdadero; y es falso para quien no tiene `admin`.
2. **Given** el repositorio de prueba, **When** se listan los roles, **Then** no hay un rol nuevo «Gestor de Talento» y ningún rol del sistema fue renombrado.
3. **Given** un usuario con el rol Administrador, **When** realiza una acción reservada al Gestor, **Then** la acción se permite; **Given** un usuario sin ese permiso, **Then** se le niega con el aviso que nombra al «Gestor de Talento».

---

### User Story 3 - Los permisos del módulo siguen la convención recurso.acción (Priority: P2)

Como equipo, queremos que todos los permisos del módulo se nombren con el formato recurso.acción, para que sean consistentes y fáciles de asignar y auditar.

**Why this priority**: Es una convención de consistencia que no cambia lo que ve el usuario, pero deja el nombrado ordenado junto con el cambio del rol.

**Independent Test**: Listar los permisos del módulo y verificar que cada nombre tiene la forma recurso.acción, y que cada acción protegida sigue funcionando para quien debe tenerla.

**Acceptance Scenarios**:

1. **Given** la lista de permisos del módulo, **When** se revisa cada nombre, **Then** el único permiso es `categorias.gestionar`, con forma recurso.acción.
2. **Given** un usuario con `admin`, **When** gestiona categorías (crear, editar, activar/desactivar, ver inactivas, auditoría), **Then** el acceso se comporta igual que antes del cambio.
3. **Given** cualquier usuario autenticado, **When** lista o consulta categorías, **Then** puede hacerlo sin ningún permiso específico.

---

### User Story 4 - Las pruebas existentes de inc-01 siguen pasando (Priority: P1)

Como equipo, queremos que todas las pruebas existentes de inc-01 sigan pasando tras el cambio, para confirmar que solo cambió el nombre y no el comportamiento.

**Why this priority**: Es la garantía de no regresión del cambio.

**Independent Test**: Ejecutar la suite completa de pruebas existentes de inc-01 y comprobar que todas pasan; las que verificaban el texto anterior se actualizan solo en ese texto.

**Acceptance Scenarios**:

1. **Given** el cambio aplicado, **When** se ejecuta la suite de inc-01, **Then** todas las pruebas pasan.
2. **Given** una prueba que comprobaba el texto «Administrador de Talento Humano», **When** se actualiza al nuevo nombre, **Then** pasa sin alterar su otra lógica.

---

### Edge Cases

- Un texto del módulo que compone la frase con el nombre del rol (por ejemplo, con artículo o género gramatical) debe seguir leyéndose correctamente con «Gestor de Talento».
- Textos en datos de ejemplo, maquetas de diseño y documentación del módulo que muestran el nombre anterior deben quedar coherentes con el nuevo.
- Pantallas de otros módulos que también mencionan el nombre anterior no se tocan en esta función (fuera de alcance).
- La capacidad `admin` sigue funcionando igual: ningún usuario pierde un acceso que tenía ni gana uno que no tenía.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Todas las pantallas, textos, mensajes y avisos del módulo MUST nombrar al rol como «Gestor de Talento».
- **FR-002**: Ningún texto visible del módulo MUST contener «Administrador de Talento Humano».
- **FR-003**: En el repositorio de prueba, el permiso de Gestor `categorias.gestionar` MUST ser verdadero cuando el token del usuario tiene la capacidad `admin` (rol Administrador existente).
- **FR-004**: El sistema MUST NOT crear un rol nuevo, renombrar un rol del sistema ni renombrar la capacidad `admin` como parte de este cambio.
- **FR-005**: Los permisos del módulo MUST nombrarse con el formato recurso.acción; el único permiso por ahora es `categorias.gestionar` (`parametros.gestionar` se agrega en 01b.1).
- **FR-009**: La decisión de acceso MUST resolverse en un único punto que responde a la consulta `puede($usuario, $permiso)`.
- **FR-010**: Ver (listar y consultar) categorías MUST NOT requerir permiso: puede cualquier usuario autenticado.
- **FR-006**: El comportamiento de acceso (quién puede y quién no puede hacer cada acción) MUST permanecer igual que antes del cambio.
- **FR-007**: Las pruebas existentes de inc-01 MUST seguir pasando; las que verificaban el nombre anterior se actualizan solo en el texto.
- **FR-008**: Los nombres de roles en otros módulos MUST permanecer sin cambios.

### Key Entities

- **Rol Gestor de Talento**: nombre mostrado al usuario para quien administra el módulo de categorías; no es un rol nuevo, sino el nombre visible del rol Administrador existente en el repositorio de prueba.
- **Permiso**: capacidad nombrada recurso.acción que habilita una acción del módulo. Por ahora solo `categorias.gestionar`, que se cumple cuando el token tiene `admin`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 0 apariciones de «Administrador de Talento Humano» en pantallas, textos, mensajes y avisos del módulo tras recorrerlos con todos los roles.
- **SC-002**: El 100 % de las pruebas existentes de inc-01 pasan después del cambio.
- **SC-003**: El 100 % de los permisos del módulo tiene nombre con formato recurso.acción.
- **SC-004**: 0 roles nuevos creados y 0 roles del sistema renombrados; el acceso de cada usuario existente a cada acción es idéntico al de antes.
- **SC-005**: Un usuario que lee cualquier aviso del módulo identifica al rol como «Gestor de Talento» sin confundirlo con el Administrador técnico.

## Assumptions

- «El módulo» es el de categorías de inc-01b (categorías de reconocimiento y demás pantallas de categorías); las menciones del nombre anterior en pantallas de otros módulos (por ejemplo posiciones y fichas) quedan fuera de alcance según «cambiar nombres de roles en otros módulos».
- El cambio es de nombre mostrado: no altera quién tiene acceso a qué.
- El plan 01b.3 define la forma de la decisión de acceso (`puede($usuario, $permiso)` con una constante por permiso); `parametros.gestionar` queda para 01b.1.
- «Repositorio de prueba» es el repositorio de datos de roles y permisos usado en las pruebas y en desarrollo, donde existe un rol Administrador.
- Los documentos de diseño (maquetas) del módulo se actualizan para mantener coherencia con el nuevo nombre.
- Depende de: — (ninguna otra función previa).

## Pantallas *(si la feature tiene UI)*

### Avisos de acceso restringido y textos del rol en categorías
- **Propósito**: Informar al usuario sin permiso que la sección es solo para el Gestor de Talento, y nombrar correctamente al rol en cualquier texto del módulo.
- **Datos que muestra**: Texto de aviso y nombre del rol «Gestor de Talento» donde se menciona (listados, selectores, mensajes).
- **Acciones disponibles** (y quién puede hacerlas): Solo lectura para todos los roles; las acciones de gestión las realiza quien tiene el permiso del Gestor.
- **Estados**: vacío, cargando, error, sin permisos (aviso con el nuevo nombre).
- **Mockup**: patrón Listado (design/CategoriasReconocimiento.dc.html); solo cambia el nombre del rol en selector de rol y datos de ejemplo.
