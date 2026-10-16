# Feature Specification: Catálogo formativo de competencias

**Feature Branch**: `005-catalogo-formativo`

**Created**: 2026-10-14

**Status**: Draft

**Input**: User description: "Catálogo formativo de competencias (documento fuente `specify-catalogo.md`). Enriquece el diccionario maestro de competencias con la capa formativa: asocia a cada competencia un área formativa, cursos y micro-contenidos que permiten desarrollarla, y expone esa vista al administrador (matriz con detalle editable en panel lateral e indicador de cobertura formativa) y al colaborador (tarjetas agrupadas por área formativa con su nivel actual, y una sección de recomendados para cerrar sus brechas). Este módulo no administra el diccionario de competencias: lo consume desde Estructura Empresarial, su único dueño. Se audita quién configuró la vista formativa de cada competencia y cada cambio de asociaciones y de área formativa."

## Clarifications

### Session 2026-10-14

- Q: Para calcular la cobertura formativa, ¿una competencia activa cuyos únicos cursos o micro-contenidos asociados están archivados cuenta como cubierta? → A: No; solo cuentan los cursos y micro-contenidos disponibles (no archivados).
- Q: ¿Qué puede hacer el administrador con las áreas formativas además de crearlas? → A: Crear y renombrar; no se eliminan, y el nombre sigue siendo único.
- Q: Cuando se renombra un área formativa, ¿ese cambio se anota en el historial de auditoría? → A: Sí; una entrada con usuario, fecha, nombre anterior y nombre nuevo del área.
- Q: ¿Un mismo usuario puede tener a la vez el rol de administrador y de colaborador? → A: No; cada usuario tiene un solo rol. El administrador solo ve la matriz y no tiene vista de colaborador.
- Q: ¿Qué hace el botón "Iniciar" o "Ver" de un curso en este módulo? → A: Lleva a la pantalla del curso en el módulo de cursos; si esa pantalla aún no existe, el botón se muestra deshabilitado con el aviso "Próximamente".

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar la oferta formativa por competencia, como colaborador (Priority: P1)

El colaborador abre el catálogo formativo y ve sus competencias activas en tarjetas agrupadas por área formativa. Cada tarjeta muestra el nombre de la competencia, su tipo, el nivel actual del colaborador (cuatro puntos A/B/C/D con el nivel actual resaltado) y cuántos cursos hay disponibles. Al hacer clic en una tarjeta ve el detalle: los grados de la competencia y los cursos y micro-contenidos asociados, cada curso con su tipo, duración, formador y un botón "Iniciar" o "Ver".

**Why this priority**: Es el valor final para el colaborador: saber qué formación existe para desarrollar cada competencia y acceder a ella. Sin esta vista el catálogo no se aprovecha.

**Independent Test**: Con competencias activas que tengan cursos y micro-contenidos asociados, iniciar sesión como colaborador, revisar las tarjetas, abrir el detalle de una y comprobar que solo se ven recursos disponibles y que el nivel mostrado es el propio.

**Acceptance Scenarios**:

1. **Given** existen competencias activas con área formativa asignada, **When** el colaborador abre el catálogo, **Then** ve tarjetas agrupadas por área formativa, solo de competencias activas.
2. **Given** una tarjeta de competencia, **When** se muestra, **Then** contiene el nombre, el tipo, los cuatro puntos A/B/C/D con el nivel actual del colaborador resaltado y el contador "X cursos disponibles".
3. **Given** el colaborador no tiene nivel registrado en una competencia, **When** se muestra su tarjeta, **Then** los cuatro puntos aparecen sin resaltar.
4. **Given** una tarjeta, **When** el colaborador hace clic en ella, **Then** se abre el detalle con los grados A/B/C/D de la competencia y los cursos y micro-contenidos asociados.
5. **Given** el detalle de una competencia, **When** se muestra cada curso, **Then** indica su tipo (etiqueta de color), duración, formador y un botón "Iniciar" o "Ver".
6. **Given** una competencia con cursos o micro-contenidos archivados, **When** el colaborador ve la tarjeta o el detalle, **Then** los archivados no aparecen ni se cuentan.
7. **Given** el listado de micro-contenidos de una competencia, **When** se muestra, **Then** la interfaz indica que son aptos para desarrollo de conocimientos y no de competencias conductuales.
8. **Given** una competencia sin cursos ni micro-contenidos disponibles, **When** el colaborador abre su detalle, **Then** ve un mensaje de que aún no hay oferta formativa.
9. **Given** el colaborador, **When** usa el catálogo, **Then** no ve ningún control de gestión (asociar, quitar, guardar, área formativa, cobertura) ni el nivel de otras personas.

---

### User Story 2 - Gestionar la matriz formativa, como administrador (Priority: P1)

El administrador (Gestor de Talento) abre una matriz con todas las competencias y, por cada una, su tipo, su área formativa y la cantidad de cursos y micro-contenidos asociados. Busca por nombre, filtra por tipo, área formativa, con/sin cursos asociados y por estado de la competencia, y ve en la parte superior el indicador de cobertura formativa. Por defecto ve solo las competencias activas.

**Why this priority**: Es la herramienta de gestión: sin ella no hay forma de saber qué competencias carecen de formación ni de construir la oferta que verá el colaborador.

**Independent Test**: Con competencias activas e inactivas y distintas asociaciones, iniciar sesión como administrador, recorrer la matriz, aplicar búsqueda y cada filtro, y verificar el porcentaje de cobertura.

**Acceptance Scenarios**:

1. **Given** existen competencias activas e inactivas, **When** el administrador abre la matriz, **Then** ve por defecto solo las activas, con columnas competencia (código y nombre), tipo, área formativa, cursos asociados (cantidad), micro-contenidos asociados (cantidad) y acciones.
2. **Given** la matriz, **When** escribe parte de un nombre de competencia, **Then** solo se muestran las que coinciden.
3. **Given** la matriz, **When** aplica el filtro por tipo, por área formativa o por "con cursos asociados" / "sin cursos asociados", **Then** el resultado respeta cada filtro y sus combinaciones.
4. **Given** la matriz, **When** elige el filtro de estado de la competencia, **Then** puede ver activas (valor por defecto), inactivas o todas.
5. **Given** 10 competencias activas de las cuales 4 tienen al menos un curso o un micro-contenido asociado y disponible, **When** se muestra la matriz, **Then** el indicador superior muestra una cobertura formativa del 40 %.
6. **Given** una competencia inactiva con asociaciones conservadas, **When** el administrador la consulta, **Then** ve sus asociaciones, pero no se cuenta en la cobertura.
7. **Given** no hay competencias activas, **When** se calcula la cobertura, **Then** el indicador muestra 0 % sin error.
8. **Given** ningún resultado coincide con la búsqueda o los filtros, **When** se muestra la matriz, **Then** aparece un mensaje de sin coincidencias; si falla la carga, un error con "Reintentar".

---

### User Story 3 - Asociar y quitar cursos y micro-contenidos de una competencia (Priority: P1)

Desde la matriz, el administrador pulsa "Ver detalle" y se abre un panel lateral. En él ve, en solo lectura, la descripción, tipo, estado y grados A/B/C/D de la competencia tal como vienen de Estructura Empresarial, y en una sección editable puede asociar y quitar cursos y micro-contenidos. "Asociar curso" abre un selector con los cursos disponibles aún no asociados a esa competencia. Los cambios se confirman con "Guardar".

**Why this priority**: Es la acción que construye el catálogo; sin asociaciones no hay cobertura ni oferta para el colaborador.

**Independent Test**: Con una competencia activa sin cursos, abrir su detalle, asociar dos cursos, guardar y comprobar que la matriz y la vista del colaborador reflejan el cambio; luego quitar uno con confirmación y comprobar que el curso sigue existiendo.

**Acceptance Scenarios**:

1. **Given** el administrador en la matriz, **When** pulsa "Ver detalle" de una competencia, **Then** se abre un panel lateral con la sección de solo lectura (descripción, tipo, estado, grados A/B/C/D) y la sección editable (área formativa, cursos y micro-contenidos asociados).
2. **Given** el panel de una competencia activa, **When** pulsa "Asociar curso", **Then** el selector muestra solo los cursos disponibles que aún no están asociados a esa competencia.
3. **Given** el administrador selecciona uno o más cursos o micro-contenidos, **When** pulsa "Guardar", **Then** las asociaciones quedan registradas, la matriz refleja las nuevas cantidades y la cobertura se recalcula.
4. **Given** una competencia ya asociada a un curso, **When** se intenta asociar el mismo curso (o el mismo micro-contenido) de nuevo, **Then** no es posible: el selector no lo ofrece y, si llega la solicitud por otra vía, se rechaza sin duplicar.
5. **Given** una asociación existente, **When** el administrador pulsa quitar, **Then** se pide confirmación que informa que solo se elimina el vínculo con la competencia; al confirmar, el curso o micro-contenido sigue existiendo y disponible para otras competencias.
6. **Given** una competencia inactiva, **When** el administrador abre su detalle, **Then** el botón "Asociar curso" y la asociación de micro-contenidos están impedidos y la interfaz informa el motivo; sí puede quitar asociaciones existentes y consultarlas.
7. **Given** un curso asociado a varias competencias, **When** el administrador lo asocia a otra competencia, **Then** la relación es válida (muchos a muchos); lo mismo aplica a micro-contenidos.
8. **Given** cambios sin guardar en el panel, **When** el administrador lo cierra, **Then** se le advierte que los perdería y puede cancelar o descartar.
9. **Given** un usuario sin rol de administrador, **When** intenta abrir el detalle editable o enviar cambios, **Then** no ve los botones "Ver detalle", "Asociar curso" ni "Guardar" y cualquier intento directo es rechazado.

---

### User Story 4 - Asignar el área formativa de una competencia (Priority: P2)

El administrador asigna a cada competencia un área formativa, elegida de un catálogo de áreas formativas que este módulo administra (nombre obligatorio y único, sin distinguir mayúsculas ni espacios al inicio o al final). Las tarjetas del colaborador se agrupan por esa área.

**Why this priority**: Organiza la oferta para el colaborador, pero el catálogo ya aporta valor con cursos asociados aunque la agrupación sea básica.

**Independent Test**: Crear un área formativa, asignarla a una competencia desde el panel de detalle, guardar y comprobar que la matriz la muestra y que el colaborador ve la tarjeta bajo esa área.

**Acceptance Scenarios**:

1. **Given** el panel de detalle, **When** el administrador abre el selector de área formativa, **Then** ve las áreas del catálogo y elige una sola.
6. **Given** un área formativa existente, **When** el administrador la renombra con un nombre libre, **Then** el nuevo nombre se ve en la matriz y en la vista del colaborador; si el nombre ya existe (sin distinguir mayúsculas ni espacios al inicio o al final), se rechaza.
2. **Given** el administrador crea un área formativa con un nombre que ya existe (sin distinguir mayúsculas ni espacios al inicio o al final), **When** guarda, **Then** se rechaza con un error en el campo nombre.
3. **Given** el administrador crea un área formativa sin nombre, **When** guarda, **Then** se rechaza con un error de campo obligatorio.
4. **Given** una competencia sin área formativa, **When** el colaborador la ve, **Then** su tarjeta aparece en un grupo "Sin área formativa" al final.
5. **Given** el administrador cambia el área de una competencia, **When** guarda, **Then** el colaborador ve la tarjeta bajo la nueva área.

---

### User Story 5 - Ver recomendados para cerrar mis brechas, como colaborador (Priority: P2)

Al inicio de su vista, el colaborador ve una sección destacada "Recomendados para cerrar mis brechas" con las competencias donde tiene una brecha activa (detectada en la DNF) y que cuentan con al menos un curso o micro-contenido asociado disponible.

**Why this priority**: Convierte el catálogo en una guía personalizada, pero depende de la DNF, que aún no está implementada; el catálogo funciona sin ella.

**Independent Test**: Con un colaborador con brechas activas en dos competencias (una con oferta y otra sin ella), verificar que solo aparece la que tiene oferta; con un colaborador sin DNF, verificar que la sección no se muestra.

**Acceptance Scenarios**:

1. **Given** el colaborador tiene una brecha activa en una competencia con oferta formativa disponible, **When** abre su vista, **Then** la sección destacada aparece al inicio con esa competencia y acceso a sus cursos y micro-contenidos.
2. **Given** una brecha activa en una competencia sin cursos ni micro-contenidos disponibles, **When** se arma la sección, **Then** esa competencia no se incluye.
3. **Given** el colaborador no tiene brechas activas o aún no se le ha realizado una DNF, **When** abre su vista, **Then** la sección no se muestra.
4. **Given** la DNF no está disponible, **When** el colaborador abre su vista, **Then** el resto del catálogo funciona con normalidad y la sección se omite sin mostrar un error.
5. **Given** dos colaboradores con brechas distintas, **When** cada uno abre su vista, **Then** cada uno ve solo sus propios recomendados.

---

### User Story 6 - Auditoría de la configuración formativa (Priority: P3)

El administrador puede saber quién configuró por última vez la vista formativa de una competencia y cuándo, y consultar el historial de cambios: cada asociación o desasociación de cursos y micro-contenidos, y cada cambio de área formativa.

**Why this priority**: Aporta trazabilidad, pero no es necesario para que el catálogo funcione.

**Independent Test**: Realizar una asociación, una desasociación y un cambio de área sobre una competencia, abrir su detalle y verificar la última configuración y las entradas del historial.

**Acceptance Scenarios**:

1. **Given** el administrador guarda cambios en una competencia, **When** abre su detalle, **Then** ve el usuario y la fecha de la última configuración.
2. **Given** se asocia o se quita un curso o micro-contenido, **When** se consulta el historial, **Then** hay una entrada por cada cambio con usuario, fecha, competencia, tipo de recurso (curso o micro-contenido), recurso y acción (asociado o quitado).
3. **Given** se cambia el área formativa, **When** se consulta el historial, **Then** hay una entrada con usuario, fecha, área anterior y área nueva.
4. **Given** el historial, **When** cualquier persona intenta modificarlo o eliminarlo, **Then** no existe ninguna acción que lo permita.
5. **Given** un guardado que no cambia nada, **When** se consulta el historial, **Then** no se agrega ninguna entrada.

---

### Edge Cases

- Una competencia se desactiva en Estructura Empresarial después de tener asociaciones: se conservan, siguen siendo consultables por el administrador, no se pueden agregar nuevas asociaciones y no se cuenta en la cobertura ni se muestra al colaborador.
- Una competencia sin cursos ni micro-contenidos es válida: aparece en la matriz con cantidades en cero y cuenta como "sin cobertura".
- Un curso o micro-contenido se archiva en su módulo dueño: deja de verse para el colaborador y deja de contarse como disponible y para la cobertura (una competencia con solo recursos archivados queda sin cobertura), pero el vínculo se conserva; el administrador lo ve señalado como archivado.
- Dos administradores editan la misma competencia a la vez: el segundo en guardar recibe un aviso de conflicto con opción de recargar, sin perder silenciosamente los cambios del primero.
- Un curso o micro-contenido es asociado y, antes de guardar, otro administrador ya lo asoció: se rechaza el duplicado con un mensaje claro.
- El módulo de Gestión de Personal no está disponible o el colaborador no tiene nivel registrado: las tarjetas se muestran con los puntos sin resaltar.
- El módulo de Estructura Empresarial no está disponible: la vista muestra un error con "Reintentar" y no usa datos antiguos como si fueran actuales.
- La competencia cambia de nombre, descripción o grados en Estructura Empresarial: este módulo siempre muestra la versión vigente, nunca una copia propia.
- Aún no existen micro-contenidos (Micro-Learning no implementado): la sección de micro-contenidos se muestra vacía y la asociación de micro-contenidos queda no disponible sin afectar a los cursos.
- Una competencia tiene muchos cursos asociados: el detalle y el selector soportan listas largas con búsqueda y desplazamiento.

## Requirements *(mandatory)*

### Functional Requirements

**Alcance y fuente de datos**

- **FR-001**: El sistema MUST consumir el diccionario de competencias exclusivamente desde Estructura Empresarial y MUST NOT mantener un catálogo propio de competencias; en este módulo la competencia (código, nombre, descripción, tipo, estado, grados A/B/C/D) es de solo lectura.
- **FR-002**: El sistema MUST mostrar siempre los datos vigentes de la competencia, sin copias propias que puedan quedar desactualizadas.

**Permisos**

- **FR-003**: El administrador (Gestor de Talento) MUST poder listar competencias activas e inactivas con su oferta formativa, consultar su detalle formativo, asociar y quitar cursos y micro-contenidos, asignar el área formativa y ver el indicador de cobertura formativa.
- **FR-004**: El colaborador MUST poder listar solo competencias activas con su oferta formativa y consultar su detalle formativo; MUST NOT poder asociar ni quitar recursos, asignar áreas formativas ni ver la cobertura.
- **FR-005**: El colaborador MUST ver únicamente su propio nivel actual y sus propios recomendados; el rol administrador no tiene vista de nivel actual ni de recomendados.
- **FR-005b**: Cada usuario MUST tener un único rol en este módulo; el administrador accede solo a la matriz y el colaborador solo a la vista de tarjetas, sin alternar entre ambas.
- **FR-006**: Toda operación de gestión MUST validarse en el servidor según el rol, independientemente de lo que muestre la interfaz.

**Área formativa**

- **FR-007**: Cada competencia MUST tener como máximo un área formativa, elegida de un catálogo de áreas formativas administrado por este módulo.
- **FR-008**: El nombre de un área formativa MUST ser obligatorio y único, sin distinguir mayúsculas ni espacios al inicio o al final.
- **FR-009**: El administrador MUST poder crear y renombrar áreas formativas, y asignar o cambiar el área formativa de una competencia. Las áreas formativas MUST NOT poder eliminarse; el renombrado respeta la unicidad de FR-008 y se refleja en todas las competencias asignadas.

**Asociaciones**

- **FR-010**: La relación entre competencias y cursos MUST ser de muchos a muchos; lo mismo aplica a micro-contenidos. Una competencia puede tener cero o más cursos y cero o más micro-contenidos.
- **FR-011**: El sistema MUST rechazar una asociación repetida (misma competencia con el mismo curso, o con el mismo micro-contenido).
- **FR-012**: El sistema MUST permitir crear nuevas asociaciones solo con competencias activas, y MUST rechazar el intento en el servidor si la competencia está inactiva.
- **FR-013**: Quitar una asociación MUST eliminar solo el vínculo; el curso o micro-contenido MUST seguir existiendo sin cambios.
- **FR-014**: Las asociaciones existentes de una competencia que luego se desactiva MUST conservarse y seguir siendo consultables por el administrador, y MUST poder quitarse.
- **FR-015**: Los cursos y micro-contenidos son propiedad de sus módulos; este módulo MUST gestionar únicamente el vínculo con la competencia.
- **FR-016**: Los micro-contenidos MUST etiquetarse como aptos para desarrollo de conocimientos, no de competencias conductuales (Alles, H.17, p. 125–126), y la interfaz MUST indicarlo junto al listado de micro-contenidos.

**Cobertura formativa**

- **FR-017**: Una competencia activa MUST considerarse cubierta si tiene al menos un curso o un micro-contenido asociado y disponible (no archivado); una competencia cuyos recursos asociados están todos archivados cuenta como sin cobertura.
- **FR-018**: La cobertura formativa MUST calcularse como competencias activas cubiertas dividido entre competencias activas totales, expresada en porcentaje, y MUST mostrar 0 % si no hay competencias activas.
- **FR-019**: El indicador de cobertura MUST mostrarse solo al administrador, en la parte superior de la matriz, y MUST actualizarse al guardar cambios.
- **FR-020**: Solo los cursos y micro-contenidos disponibles (no archivados) MUST contarse para la cobertura y para el contador del colaborador, y mostrarse al colaborador; el administrador puede ver los archivados señalados como tales en el detalle. Las cantidades de la matriz del administrador también cuentan solo recursos disponibles, y el detalle indica cuántos archivados tiene la competencia (por ejemplo, "2 cursos · 1 archivado") para que ambas vistas sean consistentes.

**Vista del administrador**

- **FR-021**: La matriz MUST mostrar las columnas competencia (código y nombre), tipo, área formativa, cursos asociados (cantidad), micro-contenidos asociados (cantidad) y acciones.
- **FR-022**: La matriz MUST ofrecer búsqueda por nombre de competencia y filtros por tipo, por área formativa, por "con cursos asociados" / "sin cursos asociados" (solo cursos disponibles; los micro-contenidos no cuentan para este filtro) y por estado de la competencia (solo administrador); por defecto MUST mostrar solo competencias activas. El indicador de cobertura MUST ofrecer un acceso "Ver sin cobertura" que lista las competencias activas sin ningún curso ni micro-contenido disponible.
- **FR-023**: "Ver detalle" MUST abrir un panel lateral con una sección de solo lectura (descripción, tipo, estado y grados A/B/C/D) y una sección editable (área formativa, cursos y micro-contenidos asociados, con agregar y quitar).
- **FR-024**: "Asociar curso" MUST abrir un selector con los cursos disponibles del módulo que aún no están asociados a esa competencia.
- **FR-025**: Los cambios del panel MUST confirmarse con "Guardar" y aplicarse de forma atómica: o se guardan todos o ninguno.
- **FR-026**: Quitar un curso o micro-contenido MUST pedir confirmación e informar que solo se elimina el vínculo con la competencia.
- **FR-027**: Si el administrador intenta asociar recursos a una competencia inactiva, la interfaz MUST impedirlo e informar el motivo.
- **FR-028**: Los botones "Ver detalle", "Asociar curso" y "Guardar" MUST ser visibles solo para el administrador.

**Vista del colaborador**

- **FR-029**: La vista del colaborador MUST mostrar tarjetas agrupadas por área formativa; las competencias sin área formativa MUST agruparse bajo "Sin área formativa".
- **FR-030**: Cada tarjeta MUST mostrar el nombre de la competencia, el tipo, el nivel actual del colaborador (cuatro puntos A/B/C/D con el actual resaltado) y el contador "X cursos disponibles"; sin nivel registrado, los puntos van sin resaltar.
- **FR-031**: Al hacer clic en una tarjeta MUST abrirse el detalle con los grados de la competencia y los cursos y micro-contenidos asociados; cada curso MUST mostrar tipo (etiqueta de color), duración, formador y un botón "Iniciar" o "Ver".
- **FR-032**: El colaborador MUST ver únicamente cursos y micro-contenidos disponibles; los archivados MUST NOT aparecer.

**Recomendados**

- **FR-033**: La vista del colaborador MUST incluir al inicio una sección destacada "Recomendados para cerrar mis brechas" con las competencias donde tiene una brecha activa (detectada en la DNF) y que tienen al menos un curso o micro-contenido disponible asociado.
- **FR-034**: Si el colaborador no tiene brechas activas o aún no se le ha realizado una DNF, o si la DNF no está disponible, la sección MUST omitirse sin mostrar error.

**Auditoría**

- **FR-035**: El sistema MUST registrar quién configuró por última vez la vista formativa de cada competencia y cuándo.
- **FR-036**: Cada asociación o desasociación MUST quedar en un historial con usuario, fecha, competencia, tipo de recurso, recurso y acción (asociado o quitado).
- **FR-037**: Cada cambio de área formativa MUST quedar en el historial con usuario, fecha, área anterior y área nueva; cada renombrado de un área formativa MUST quedar con usuario, fecha, nombre anterior y nombre nuevo.
- **FR-038**: El historial MUST ser de solo lectura y no admitir edición ni eliminación; un guardado sin cambios MUST NOT generar entradas.

**Integración con otros módulos**

- **FR-039**: Este módulo MUST obtener el nivel actual del colaborador por competencia desde Gestión de Personal y las brechas activas desde la DNF, a través de las interfaces públicas de esos módulos.
- **FR-040**: Este catálogo MUST poder ser consultado por la DNF para su acción "Ver cursos sugeridos", filtrando por la competencia con brecha.
- **FR-041**: La ausencia o falla de la DNF, de Gestión de Personal o de Micro-Learning MUST degradar solo la parte afectada (recomendados, nivel actual o micro-contenidos) sin impedir el resto del catálogo.

### Key Entities *(include if feature involves data)*

- **Competencia (referencia)**: elemento del diccionario maestro propiedad de Estructura Empresarial; aquí solo se lee su código, nombre, descripción, tipo, estado y grados A/B/C/D.
- **Área formativa**: agrupación temática de la oferta formativa; tiene un nombre obligatorio y único. Una competencia tiene como máximo una.
- **Vista formativa de la competencia**: configuración propia de este módulo para una competencia: su área formativa, sus cursos y micro-contenidos asociados y la última configuración (usuario y fecha).
- **Asociación competencia–curso**: vínculo único entre una competencia y un curso; el curso es propiedad del módulo de cursos.
- **Asociación competencia–micro-contenido**: vínculo único entre una competencia y un micro-contenido; el micro-contenido es propiedad de Micro-Learning.
- **Historial de cambios**: registro inmutable de asociaciones y desasociaciones (usuario, fecha, competencia, tipo de recurso, recurso, acción) de cambios de área formativa de una competencia (usuario, fecha, área anterior, área nueva) y de renombrados de áreas formativas (usuario, fecha, nombre anterior, nombre nuevo).
- **Nivel actual del colaborador**: grado A/B/C/D que el colaborador tiene en una competencia, aportado por Gestión de Personal.
- **Brecha activa**: diferencia detectada en la DNF entre el nivel del colaborador y el requerido en una competencia, aportada por la DNF.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un colaborador encuentra los cursos disponibles para una competencia concreta y llega a "Iniciar" o "Ver" en no más de 3 clics desde el catálogo.
- **SC-002**: El administrador asocia un curso a una competencia, desde que abre la matriz hasta ver la cantidad actualizada, en menos de 1 minuto.
- **SC-003**: El indicador de cobertura coincide al 100 % con el cálculo manual (competencias activas cubiertas / competencias activas totales) en las pruebas de aceptación.
- **SC-004**: En ningún caso un colaborador ve controles de gestión, la cobertura formativa, el nivel de otra persona ni recursos archivados.
- **SC-005**: El 100 % de las asociaciones, desasociaciones y cambios de área formativa queda en el historial con su autor y fecha, y ninguna entrada puede modificarse.
- **SC-006**: Ninguna competencia inactiva recibe nuevas asociaciones ni ninguna asociación se duplica, incluso al intentar por vías distintas a la interfaz.
- **SC-007**: La matriz y la vista del colaborador se muestran en menos de 3 segundos con 200 competencias y 1.000 asociaciones.
- **SC-008**: Al menos el 90 % de los administradores de prueba completa sin ayuda el flujo "abrir detalle, asociar curso, guardar" al primer intento.
- **SC-009**: Cuando la DNF, Gestión de Personal o Micro-Learning no están disponibles, el resto del catálogo sigue utilizable en el 100 % de los casos probados.

## Assumptions

- Los roles son los ya existentes en el sistema: Administrador (Gestor de Talento) y Colaborador, con la sesión resuelta por el módulo de autenticación.
- El diccionario de competencias (feature 001) ya existe y es la única fuente; este módulo no crea, edita ni desactiva competencias.
- Los cursos pertenecen a este módulo, pero su alta y administración son parte de otra feature; esta feature asume que existe un catálogo de cursos consultable con tipo, duración, formador y estado disponible/archivado.
- Los micro-contenidos pertenecen a Micro-Learning, feature aún no implementada: hasta entonces la sección de micro-contenidos se muestra vacía y la asociación de micro-contenidos queda no disponible, sin afectar a los cursos.
- La DNF (feature 2) aún no está implementada: hasta entonces la sección de recomendados no se muestra. El botón "Ver cursos sugeridos" de la DNF es responsabilidad de esa feature; aquí solo se expone la consulta por competencia.
- El nivel actual del colaborador lo aporta Gestión de Personal; si no hay registro, se muestra sin resaltar.
- Los botones "Iniciar" y "Ver" de los cursos llevan al curso en su módulo dueño; si esa pantalla de destino aún no existe, el botón se muestra deshabilitado con el aviso "Próximamente". La ejecución del curso queda fuera de esta feature.
- Quedan fuera de alcance: crear o editar cursos y micro-contenidos, matricular o registrar avance, y modificar el diccionario de competencias.
- La administración de áreas formativas (crear y renombrar) la realiza el administrador desde este módulo; las áreas no se eliminan.
- El administrador usa el sistema en escritorio; la vista del colaborador debe ser usable también en pantallas pequeñas.
- Rendimiento y volumen son los habituales de una organización mediana (cientos de competencias, miles de asociaciones).

## Pantallas *(si la feature tiene UI)*

### Matriz formativa (administrador)
- **Propósito**: ver la oferta formativa de todas las competencias, detectar las que carecen de cobertura y entrar a gestionarlas.
- **Datos que muestra**: indicador de cobertura formativa; por competencia: código y nombre, tipo, área formativa, cantidad de cursos y de micro-contenidos asociados; estado de la competencia cuando se ven inactivas; total y página actual.
- **Acciones disponibles** (y quién puede hacerlas): buscar por nombre, filtrar por tipo, área formativa, con/sin cursos asociados y estado de la competencia, paginar, "Ver detalle" (solo Administrador).
- **Estados**: cargando (esqueleto de tabla), vacío, sin coincidencias, error con "Reintentar", éxito, sin permisos (el colaborador no accede a esta vista).
- **Mockup**: `design/CatalogoAdmin.dc.html`, sobre el patrón Listado de `design/DESIGN.md`; agregar el indicador de cobertura en la parte superior y los filtros propios de esta pantalla.

### Detalle de la competencia (panel lateral, administrador)
- **Propósito**: consultar la competencia y editar su área formativa y sus cursos y micro-contenidos asociados.
- **Datos que muestra**: sección de solo lectura (descripción, tipo, estado, grados A/B/C/D); sección editable (área formativa, cursos asociados, micro-contenidos asociados con la nota de aptitud para conocimientos); última configuración (usuario y fecha) e historial de cambios.
- **Acciones disponibles** (y quién puede hacerlas): cambiar el área formativa, "Asociar curso", asociar micro-contenido, quitar (con confirmación), "Guardar", cerrar (solo Administrador).
- **Estados**: cargando, competencia inactiva (asociar impedido con el motivo), sin recursos asociados, guardando (botón bloqueado), error de validación o de duplicado, conflicto de edición, éxito, cambios sin guardar al cerrar.
- **Mockup**: `design/CatalogoAdmin.dc.html` (panel lateral); el selector de cursos y la confirmación de quitar se añaden según `design/DESIGN.md`.

### Catálogo formativo (colaborador)
- **Propósito**: descubrir y acceder a la formación que desarrolla cada competencia y priorizar la que cierra las propias brechas.
- **Datos que muestra**: sección "Recomendados para cerrar mis brechas" (cuando aplica); tarjetas agrupadas por área formativa con nombre, tipo, nivel actual (puntos A/B/C/D) y "X cursos disponibles".
- **Acciones disponibles** (y quién puede hacerlas): abrir el detalle de una tarjeta, "Iniciar" o "Ver" un curso o micro-contenido (Colaborador).
- **Estados**: cargando, vacío (sin competencias con oferta), error con "Reintentar", sección de recomendados oculta cuando no aplica, nivel sin registrar.
- **Mockup**: `design/CatalogoColaborador.dc.html`.

### Detalle de la competencia (colaborador)
- **Propósito**: ver los grados de la competencia y los recursos formativos disponibles para desarrollarla.
- **Datos que muestra**: grados A/B/C/D; cursos con tipo (etiqueta de color), duración, formador y botón "Iniciar" o "Ver"; micro-contenidos con la indicación de que son aptos para conocimientos.
- **Acciones disponibles** (y quién puede hacerlas): "Iniciar" / "Ver" y cerrar (Colaborador).
- **Estados**: cargando, sin oferta formativa disponible, error con "Reintentar".
- **Mockup**: `design/CatalogoColaborador.dc.html` (detalle de tarjeta).
