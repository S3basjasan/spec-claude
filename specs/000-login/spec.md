# Feature Specification: Autenticación mínima (login)

**Feature Branch**: `000-login`

**Created**: 2026-10-09

**Status**: Draft

**Input**: User description: "Autenticación mínima del HRMS, temporal, para poder probar las funcionalidades protegidas de los demás módulos. Dos roles: Administrador y Usuario. Usuarios cargados con datos iniciales; sin registro ni gestión de usuarios. Inicio y cierre de sesión con correo y contraseña; el sistema identifica quién es y qué rol tiene en cada petición. Sin sesión no se accede a nada."

## Clarifications

### Session 2026-10-09

- Q: ¿La sesión debe mantenerse al cerrar y volver a abrir el navegador, o terminar al cerrarlo? → A: Persiste hasta cerrar sesión explícitamente.
- Q: ¿Debe tratarse el correo sin distinguir mayúsculas/minúsculas y sin espacios alrededor? → A: Sí, se ignoran.
- Q: ¿Qué debe ver una persona ya autenticada si abre la pantalla de inicio de sesión? → A: Se redirige automáticamente a la pantalla principal, que por ahora está vacía.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Iniciar sesión (Priority: P1)

Una persona con una cuenta precargada (administrador o usuario de prueba) ingresa su correo y contraseña y accede al sistema con su rol.

**Why this priority**: Sin inicio de sesión no se puede probar ninguna funcionalidad protegida de los demás módulos.

**Independent Test**: Ingresar credenciales válidas del administrador y del usuario de prueba y comprobar que se accede al sistema y que se identifica correctamente a cada persona y su rol.

**Acceptance Scenarios**:

1. **Given** una cuenta de administrador precargada, **When** ingresa correo y contraseña correctos, **Then** accede al sistema identificada como Administrador.
2. **Given** una cuenta de usuario de prueba precargada, **When** ingresa correo y contraseña correctos, **Then** accede al sistema identificada como Usuario.
3. **Given** credenciales incorrectas o de una cuenta inexistente, **When** intenta iniciar sesión, **Then** no accede y ve un mensaje de error que no revela si el correo existe.
4. **Given** campos vacíos o con un correo mal formado, **When** intenta enviar el formulario, **Then** ve errores por campo y no se envía la solicitud.

---

### User Story 2 - Acceso restringido sin sesión (Priority: P1)

Quien no ha iniciado sesión no puede ver ninguna pantalla ni ejecutar ninguna operación del sistema; solo puede llegar a la pantalla de inicio de sesión.

**Why this priority**: Es la razón de ser de la funcionalidad: proteger el resto del sistema.

**Independent Test**: Sin sesión, intentar abrir una pantalla interna y una operación protegida y comprobar que ambas se rechazan.

**Acceptance Scenarios**:

1. **Given** no hay sesión iniciada, **When** se intenta abrir cualquier pantalla interna, **Then** se redirige a la pantalla de inicio de sesión.
2. **Given** no hay sesión iniciada, **When** se intenta ejecutar cualquier operación del sistema, **Then** se rechaza indicando que se requiere autenticación.
3. **Given** una sesión que ya no es válida, **When** se usa el sistema, **Then** se trata como no autenticado y se solicita iniciar sesión de nuevo.

---

### User Story 3 - Conocer identidad y rol en cada petición (Priority: P2)

En cada operación el sistema sabe quién la realiza y con qué rol (Administrador o Usuario), de modo que los demás módulos puedan restringir acciones por rol.

**Why this priority**: Permite probar funcionalidades protegidas y por rol de otros módulos.

**Independent Test**: Con sesión iniciada, consultar la identidad actual y comprobar que devuelve el correo y el rol correctos; con otra cuenta, que devuelve los datos de esa cuenta.

**Acceptance Scenarios**:

1. **Given** una sesión de administrador, **When** se consulta quién es el usuario actual, **Then** se obtiene su identidad y el rol Administrador.
2. **Given** una sesión de usuario de prueba, **When** se consulta quién es el usuario actual, **Then** se obtiene su identidad y el rol Usuario.
3. **Given** una operación reservada al Administrador, **When** la ejecuta un Usuario, **Then** se rechaza por falta de permisos.

---

### User Story 4 - Cerrar sesión (Priority: P2)

Una persona autenticada cierra su sesión y deja de tener acceso hasta volver a iniciar sesión.

**Why this priority**: Completa el ciclo de sesión y permite alternar entre roles al probar.

**Independent Test**: Iniciar sesión, cerrar sesión y comprobar que las pantallas y operaciones protegidas ya no son accesibles.

**Acceptance Scenarios**:

1. **Given** una sesión iniciada, **When** la persona cierra sesión, **Then** se muestra la pantalla de inicio de sesión.
2. **Given** una sesión cerrada, **When** se reutiliza la credencial de esa sesión, **Then** el sistema la rechaza.
3. **Given** una sesión cerrada, **When** se usa el botón de retroceso del navegador, **Then** no se muestran datos protegidos.

---

### Edge Cases

- Credenciales válidas con diferencias de mayúsculas/minúsculas o espacios alrededor del correo: el correo se compara ignorando ambos y el inicio de sesión tiene éxito (la contraseña sí distingue mayúsculas).
- Envío repetido del formulario mientras la solicitud está pendiente.
- Una persona ya autenticada abre la pantalla de inicio de sesión: se redirige automáticamente a la pantalla principal.
- El sistema no está disponible al iniciar sesión: se muestra un error comprensible con opción de reintentar.
- Credenciales de una sesión cerrada o alterada.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema MUST contar con dos roles: Administrador y Usuario.
- **FR-002**: El sistema MUST cargar datos iniciales con un administrador y un usuario de prueba, sin registro ni gestión de usuarios desde la aplicación.
- **FR-003**: Una persona MUST poder iniciar sesión con correo y contraseña.
- **FR-004**: El sistema MUST rechazar credenciales inválidas con un mensaje que no revele si el correo existe.
- **FR-005**: El sistema MUST validar el formato del correo y la presencia de ambos campos, mostrando errores por campo y conservando lo ingresado.
- **FR-006**: El sistema MUST impedir envíos duplicados mientras un inicio de sesión está en curso.
- **FR-007**: Una persona autenticada MUST poder cerrar sesión, tras lo cual su credencial MUST dejar de ser válida.
- **FR-008**: La sesión MUST persistir al cerrar y reabrir el navegador y terminar solo al cerrar sesión explícitamente.
- **FR-009**: Tras iniciar sesión, y al abrir el inicio de sesión estando autenticada, la persona MUST ser llevada a la pantalla principal del sistema, que por ahora estará vacía (solo App Shell con identidad y cierre de sesión).
- **FR-010**: El sistema MUST identificar a la persona y su rol en cada petición autenticada y permitir consultarlos.
- **FR-011**: Sin sesión iniciada, el sistema MUST denegar el acceso a toda pantalla y operación, excepto el inicio de sesión.
- **FR-012**: El sistema MUST rechazar con falta de permisos las operaciones reservadas a un rol que quien las solicita no posee.
- **FR-013**: Las contraseñas MUST almacenarse de forma no reversible y MUST NOT aparecer en respuestas ni registros.
- **FR-014**: Toda la interfaz de esta funcionalidad MUST estar en español y ser accesible (WCAG 2.2 AA) entre 320 y 1440 px.
- **FR-015**: Los datos iniciales MUST ser exclusivamente de prueba, sin datos personales reales.

**Fuera de alcance**: registro, recuperación y cambio de contraseña, gestión de usuarios, bloqueo por intentos fallidos y expiración por inactividad.

### Key Entities *(include if feature involves data)*

- **Usuario**: persona con acceso al sistema; tiene correo único, contraseña protegida, nombre visible y un rol.
- **Rol**: Administrador o Usuario; determina qué operaciones se permiten.
- **Sesión**: periodo en que una persona está autenticada; se crea al iniciar sesión y termina al cerrarla.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Una persona con credenciales válidas completa el inicio de sesión en menos de 30 segundos.
- **SC-002**: El 100 % de las pantallas y operaciones del sistema se rechazan sin sesión iniciada.
- **SC-003**: El 100 % de las peticiones autenticadas identifican correctamente a la persona y su rol.
- **SC-004**: Tras cerrar sesión, el 100 % de los intentos de reutilizar esa sesión se rechazan.
- **SC-005**: Un equipo de pruebas puede alternar entre el rol Administrador y Usuario en menos de 1 minuto.

## Assumptions

- Es una solución temporal para probar los demás módulos; será reemplazada por la autenticación definitiva.
- Los dos usuarios de prueba se cargan con datos iniciales y sus credenciales viven solo en configuración de desarrollo.
- La sesión dura hasta que la persona cierra sesión (aunque cierre el navegador); no hay expiración por inactividad.
- Un mismo usuario puede tener varias sesiones simultáneas.
- Idioma de la interfaz: español.
- La pantalla principal está vacía en esta feature; los demás módulos la poblarán.
- Los demás módulos dependen de esta funcionalidad para identificar usuario y rol.

## Pantallas *(si la feature tiene UI)*

### Inicio de sesión
- **Propósito**: autenticarse con correo y contraseña.
- **Datos que muestra**: campos de correo y contraseña, errores por campo y mensaje general de error.
- **Acciones disponibles** (y quién puede hacerlas): iniciar sesión (cualquier persona sin sesión).
- **Estados**: cargando (envío pendiente), error (credenciales inválidas o servicio no disponible con reintento), éxito (redirige al sistema), sin permisos (no aplica; las personas ya autenticadas son redirigidas al sistema).
- **Mockup**: patrón Páginas públicas (`design/Login.dc.html`), sin "¿Olvidaste tu contraseña?" ni "Mantener la sesión iniciada"; admite tema claro y oscuro.

### Cierre de sesión (en el App Shell)
- **Propósito**: terminar la sesión actual.
- **Datos que muestra**: nombre o correo y rol de la persona autenticada.
- **Acciones disponibles** (y quién puede hacerlas): cerrar sesión (Administrador y Usuario).
- **Estados**: cargando (cierre en curso), error (con reintento), éxito (vuelve al inicio de sesión).
- **Mockup**: patrón App Shell (`design/Main.dc.html`); el menú de usuario solo muestra nombre, rol y "Cerrar sesión".
