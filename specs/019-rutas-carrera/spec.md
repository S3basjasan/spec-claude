# Feature Specification: Rutas y planes de carrera

**Feature Branch**: `019-rutas-carrera`

**Created**: 2026-10-20

**Status**: Draft

**Input**: User description: "Rutas y planes de carrera (documento fuente `specify-rutas.md`, copiado en esta carpeta). Define rutas de carrera —secuencias de niveles dentro de un área o entre áreas— de tipo Especialista, Gerencial, Interáreas o Jóvenes profesionales, con cada nivel asociado a un puesto de Estructura Empresarial y una experiencia mínima. El administrador (Gestor de Talento) crea y mantiene las rutas, inscribe a los colaboradores y fija su nivel actual y objetivo; el colaborador consulta «Mi ruta de carrera» y lo que se exige para llegar a su objetivo. La ruta es una guía de desarrollo, no la promesa de un puesto. No administra puestos, competencias ni los requisitos del puesto (los lee de Estructura Empresarial), no registra movimientos ni ascensos (Gestión de Personal) ni define remuneraciones."

## Clarifications

### Session 2026-10-20

- Q: ¿Qué pasa si se intenta cambiar el puesto de un nivel que es nivel actual u objetivo de una inscripción Activa? → A: Se bloquea el cambio de puesto mientras el nivel esté en uso; solo se pueden editar el nombre del nivel y la experiencia mínima.
- Q: ¿Qué colaboradores se pueden inscribir en una ruta? → A: Solo colaboradores activos en la nómina, de cualquier departamento.
- Q: ¿Cómo se trata a un colaborador con inscripción Activa cuando es dado de baja? → A: Su inscripción pasa a Retirada automáticamente, con motivo «Baja del colaborador» y registro en el historial.
- Q: ¿Los conocimientos del detalle de nivel llevan grado A–D y diferencial? → A: No. Solo las competencias llevan grado y diferencial; los conocimientos con grado no existen en Estructura y quedan fuera de alcance. La tarjeta «Conocimientos» muestra como información (sin grado, comparación ni diferencial) los requisitos de Formación y Capacitación del puesto; «Experiencia» conserva los años mínimos guardados. Una bandera `desarrollo.conocimientos_con_grado` (`false`) documenta el punto de extensión sin implementar la rama activa.
- Q: ¿Cuándo se retira una inscripción Activa por la baja del colaborador? → A: Cuando se detecta que ya no tiene vínculo laboral vigente: al consultar rutas, inscripciones o «Mi ruta», o en el proceso programado de conciliación; no hay aviso instantáneo desde Gestión de Personal.
- Q: ¿Se ofrece «Ver mi adecuación» mientras no exista la vista de adecuación a puesto futuro? → A: No; el botón solo se ofrece con nivel objetivo y cuando esa vista existe.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Definir una ruta de carrera con sus niveles (Priority: P1)

El administrador crea una ruta indicando nombre, tipo, descripción y, si corresponde, el departamento donde nace. Luego arma la escalera de niveles: agrega niveles, elige para cada uno un puesto activo del Descriptivo de puestos, ajusta el nombre del nivel y la experiencia mínima en años, y los reordena o quita. El código CAR-0001 se asigna solo. Para cada nivel ve en tres columnas (Competencias | Conocimientos | Experiencia) lo requerido por el perfil del puesto; las competencias muestran además, en tono tenue, lo que cambia respecto del nivel anterior, y los conocimientos son solo informativos (sin grado ni diferencial).

**Why this priority**: Sin rutas con niveles no existe nada que consultar ni a lo que inscribir; es la base de toda la feature.

**Independent Test**: Crear una ruta Especialista con tres niveles ligados a puestos con perfiles distintos y verificar que el código es CAR-0001, que cada nivel muestra los requisitos del puesto y que el diferencial del nivel 2 y 3 coincide con la diferencia calculada a mano.

**Acceptance Scenarios**:

1. **Given** que el administrador abre «Nueva ruta», **When** guarda nombre, tipo y (opcional) descripción y departamento, **Then** la ruta queda en estado Borrador con el siguiente código CAR-NNNN, sin niveles, y el código no es editable.
2. **Given** una ruta existente llamada «Ruta Contable», **When** se intenta crear otra llamada « ruta contable » (otras mayúsculas o espacios en los extremos), **Then** el sistema rechaza el nombre por duplicado.
3. **Given** una ruta de tipo Interáreas, **When** se muestra el formulario, **Then** el campo Departamento no aplica y no se puede completar.
4. **Given** el editor de una ruta, **When** el administrador agrega un nivel y elige un puesto, **Then** el nivel recibe el siguiente orden consecutivo (el 1 es el nivel de ingreso), su nombre por defecto es el del puesto y puede editarlo.
5. **Given** una ruta con un nivel ligado al puesto P, **When** se intenta agregar otro nivel con el mismo puesto P en la misma ruta, o un puesto inactivo, **Then** el sistema lo rechaza con un mensaje claro.
6. **Given** un nivel 2 cuyo puesto exige la competencia C en grado C y el nivel 1 la exige en grado B, **When** se abre el detalle del nivel 2, **Then** la columna de competencias muestra C con grado C y, como diferencial, que sube de grado; las competencias nuevas aparecen marcadas como agregadas y la experiencia muestra los años adicionales respecto del nivel 1.
7. **Given** el detalle de un nivel, **When** el administrador intenta modificar las competencias o los conocimientos informativos, **Then** esas columnas son de solo lectura (se gestionan en Estructura Empresarial).
8. **Given** una ruta con niveles, **When** el administrador reordena o quita un nivel, **Then** los órdenes se renumeran sin huecos y el diferencial se recalcula.
9. **Given** un nivel que es nivel actual u objetivo de una inscripción Activa, **When** se intenta quitarlo o cambiar su puesto, **Then** el sistema lo impide e informa el motivo; sí permite editar el nombre del nivel y la experiencia mínima.

---

### User Story 2 - Publicar, archivar y listar rutas (Priority: P1)

El administrador pasa la ruta a Vigente cuando está lista, la archiva cuando ya no se usa y la vuelve a Vigente si hace falta. Ve todas las rutas en tarjetas agrupadas por tipo, con código, nombre, estado, cantidad de niveles y de inscritos, y puede filtrar por tipo, estado y departamento y buscar por nombre. Las rutas nunca se eliminan.

**Why this priority**: Controla qué rutas están disponibles para inscribir y para que los colaboradores las consulten.

**Independent Test**: Intentar publicar una ruta con un solo nivel (rechazo), publicar una con dos niveles de puestos activos (éxito), archivarla con inscritos (confirmación con la cantidad) y volver a ponerla Vigente.

**Acceptance Scenarios**:

1. **Given** una ruta Borrador con menos de 2 niveles o con algún nivel cuyo puesto ya no está activo, **When** se intenta pasar a Vigente, **Then** el sistema lo rechaza indicando qué falta.
2. **Given** una ruta Borrador con al menos 2 niveles y todos con puesto activo, **When** se pasa a Vigente, **Then** queda Vigente y admite inscripciones.
3. **Given** una ruta Vigente con inscripciones Activas, **When** el administrador la archiva, **Then** el sistema pide confirmación informando la cantidad de inscritos; al confirmar, la ruta queda Archivada y conserva todas sus inscripciones.
4. **Given** una ruta Archivada, **When** el administrador la vuelve a Vigente, **Then** pasa a Vigente si cumple las condiciones de publicación.
5. **Given** una ruta Borrador o Archivada, **When** se intenta inscribir a un colaborador, **Then** el sistema lo rechaza: solo las rutas Vigentes admiten nuevas inscripciones.
6. **Given** la lista de rutas, **When** el administrador filtra por tipo, estado o departamento y busca por nombre, **Then** las tarjetas se reducen a las coincidencias, agrupadas por tipo.
7. **Given** cualquier ruta, **When** se revisan las acciones disponibles, **Then** no existe acción de eliminar.

---

### User Story 3 - Inscribir colaboradores y fijar su nivel actual y objetivo (Priority: P1)

El administrador abre la pestaña Participantes de una ruta Vigente, inscribe a un colaborador, indica su nivel actual (o lo deja sin definir si aún no ocupa ninguno) y fija el nivel objetivo al que aspira. Puede cambiar los niveles después, registrar el nivel alcanzado y retirar la inscripción con un motivo.

**Why this priority**: Es el segundo objetivo central: registrar quién participa en cada ruta y a dónde apunta.

**Independent Test**: Inscribir un colaborador en la ruta (nivel actual 1, objetivo 3), registrar el nivel alcanzado (pasa a 3, que es el último → Finalizada), y comprobar con otro colaborador el flujo de retiro con motivo.

**Acceptance Scenarios**:

1. **Given** una ruta Vigente y un colaborador sin inscripción Activa en ella, **When** el administrador lo inscribe con nivel actual y nivel objetivo, **Then** la inscripción queda Activa con fecha de registro.
2. **Given** un colaborador con inscripción Activa en la ruta, **When** se intenta inscribirlo de nuevo en esa misma ruta, **Then** el sistema lo rechaza; en cambio puede tener una inscripción Activa en otra ruta distinta.
3. **Given** un nivel actual N, **When** se fija un nivel objetivo igual o anterior a N, **Then** el sistema lo rechaza: el objetivo debe ser posterior al actual.
4. **Given** una inscripción sin nivel actual definido, **When** se fija el objetivo, **Then** se acepta cualquier nivel de la ruta como objetivo.
5. **Given** una inscripción Activa con objetivo en un nivel que no es el último, **When** el administrador pulsa «Registrar nivel alcanzado», **Then** el nivel actual pasa a ser el objetivo, el objetivo queda sin definir hasta que se fije otro y el sistema aclara que el cambio real de puesto lo ejecuta Gestión de Personal.
6. **Given** una inscripción Activa cuyo objetivo es el último nivel de la ruta, **When** se registra el nivel alcanzado, **Then** la inscripción pasa a Finalizada con fecha de resolución.
7. **Given** una inscripción Activa, **When** el administrador la retira, **Then** el sistema pide confirmación y un motivo obligatorio; sin motivo no se completa. Al confirmar queda Retirada con fecha de resolución y motivo.
8. **Given** una inscripción Finalizada o Retirada, **When** se revisa, **Then** no admite cambios de nivel ni nuevo registro de nivel alcanzado.
9. **Given** la pestaña Participantes, **When** se filtra por estado, **Then** la tabla muestra solo las inscripciones de ese estado con colaborador, nivel actual, nivel objetivo y estado.

---

### User Story 4 - Consultar «Mi ruta de carrera» como colaborador (Priority: P1)

El colaborador abre «Mi ruta de carrera» y ve la escalera de cada ruta en que está inscrito, con su nivel actual resaltado y su nivel objetivo marcado. Al seleccionar el siguiente nivel o su objetivo ve lo que se exige en competencias (con su grado A–D), conocimientos informativos (requisitos de Formación y Capacitación, sin grado) y experiencia. Si tiene nivel objetivo, puede ir a «Ver mi adecuación». También consulta la lista de rutas Vigentes disponibles. En todo momento ve el aviso «Esta ruta es una guía de desarrollo; no implica la promesa de un puesto.»

**Why this priority**: Es el valor para el colaborador y razón de ser del módulo como guía de desarrollo.

**Independent Test**: Con un colaborador inscrito (actual 1, objetivo 3), verificar que se resaltan ambos niveles, que el detalle del objetivo muestra los requisitos correctos, que el botón de adecuación aparece y que otro colaborador sin inscripciones ve el estado vacío.

**Acceptance Scenarios**:

1. **Given** un colaborador con una inscripción Activa, **When** abre «Mi ruta de carrera», **Then** ve la escalera de la ruta con su nivel actual resaltado y su nivel objetivo marcado.
2. **Given** la escalera, **When** selecciona el siguiente nivel o su nivel objetivo, **Then** ve las competencias requeridas con su grado, los conocimientos informativos (sin grado) y la experiencia mínima de ese nivel.
3. **Given** un colaborador con nivel objetivo definido, **When** abre su ruta, **Then** aparece el botón «Ver mi adecuación» que lo lleva a la vista de adecuación a puesto futuro, siempre que esa vista exista; sin nivel objetivo, o mientras la vista no exista, el botón no se ofrece.
4. **Given** un colaborador sin inscripción en ninguna ruta, **When** abre la pantalla, **Then** ve un estado vacío informativo y la lista de rutas Vigentes disponibles.
5. **Given** la lista de rutas disponibles, **When** se consulta, **Then** solo contiene rutas Vigentes (nunca Borrador ni Archivada) y es de solo lectura: el colaborador no puede inscribirse ni proponer interés.
6. **Given** cualquier vista del colaborador, **When** se muestra una ruta, **Then** el aviso «guía, no promesa» es visible de forma permanente.
7. **Given** un colaborador, **When** intenta ver la inscripción de otra persona, **Then** el sistema no lo permite: solo ve la propia.

---

### User Story 5 - Ver la cantera de talento y el historial (Priority: P2)

El administrador ve, para cada ruta, cuántos colaboradores con inscripción Activa hay en cada nivel (cantera de talento) y consulta el historial de cambios de la ruta, de sus niveles y de las inscripciones, con usuario, fecha, valor anterior y nuevo, y motivo.

**Why this priority**: Aporta la visión de cantera y la trazabilidad; la feature funciona sin ellos, pero son parte del propósito (crear talento organizacional) y de la auditoría exigida.

**Independent Test**: Con inscripciones Activas repartidas en distintos niveles, verificar los conteos por nivel; modificar una ruta, un nivel y una inscripción y comprobar que cada cambio aparece en el historial con valores anterior y nuevo.

**Acceptance Scenarios**:

1. **Given** una ruta con inscripciones Activas, **When** el administrador abre su indicador de cantera, **Then** cada nivel muestra la cantidad de colaboradores Activos cuyo nivel actual es ese nivel; las inscripciones Finalizadas o Retiradas no cuentan.
2. **Given** una inscripción sin nivel actual definido, **When** se calcula la cantera, **Then** no se cuenta en ningún nivel y se informa aparte como «sin nivel».
3. **Given** cambios de alta, edición, reorden, baja o cambio de estado en una ruta o sus niveles, **When** se consulta el historial de la ruta, **Then** cada cambio figura con usuario, fecha, valor anterior y valor nuevo.
4. **Given** altas, cambios de nivel, nivel alcanzado y retiros de inscripciones, **When** se consulta el historial, **Then** cada evento figura con usuario, fecha, valores y motivo cuando aplica.
5. **Given** cualquier ruta, **When** se revisa su ficha, **Then** se muestra quién la creó y quién la modificó por última vez, con fecha.
6. **Given** un usuario sin el permiso del historial o de las inscripciones, **When** intenta acceder, **Then** no ve esas secciones ni puede consultarlas.

---

### Edge Cases

- Un puesto de un nivel se desactiva en Estructura Empresarial después de publicar la ruta: la ruta no se archiva sola, pero el nivel se marca con advertencia, no puede volver a publicarse mientras tenga un puesto inactivo y las consultas siguen mostrando el último perfil disponible.
- El perfil del puesto de un nivel cambia en Estructura Empresarial: la siguiente consulta refleja los requisitos y el diferencial nuevos, sin tocar la ruta.
- Un nivel sin experiencia mínima definida: se muestra «—» y el diferencial de experiencia se calcula solo si ambos niveles la tienen.
- El nivel 1 no tiene nivel anterior: no muestra diferencial.
- Reordenar niveles con inscripciones Activas: los niveles actual y objetivo de cada inscripción siguen apuntando al mismo nivel (no a la misma posición); si el reorden deja el objetivo igual o anterior al actual, se rechaza el reorden.
- Un colaborador cuyo puesto actual cambia en Gestión de Personal: la inscripción conserva el nivel registrado; el sistema no lo modifica solo.
- Un colaborador dado de baja con inscripciones Activas: todas pasan automáticamente a Retirada con motivo «Baja del colaborador» y quedan en el historial; siguen visibles para el administrador.
- Dos administradores editan la misma ruta a la vez: el segundo guardado recibe un aviso de conflicto en lugar de pisar el cambio.
- Intento de inscribir a un colaborador inexistente o que no está activo en la nómina (por ejemplo, dado de baja): se rechaza.
- Se archiva una ruta con inscripciones Activas: se conservan, no se admiten nuevas y siguen visibles; no se puede cambiar su nivel mientras la ruta esté Archivada.
- Fuente externa no disponible (Estructura Empresarial o Gestión de Personal): las pantallas muestran un aviso con «Reintentar» y no datos inventados.
- Nombre de ruta con espacios extremos: se normaliza antes de comparar y guardar.

## Requirements *(mandatory)*

### Functional Requirements

**Rutas**

- **FR-001**: El sistema MUST permitir al administrador crear y editar rutas con nombre (obligatorio), tipo (Especialista, Gerencial, Interáreas o Jóvenes profesionales), descripción (opcional) y departamento (opcional).
- **FR-002**: El sistema MUST generar el código de cada ruta con formato CAR-0001 secuencial, único y no editable.
- **FR-003**: El sistema MUST exigir nombre único sin distinguir mayúsculas ni espacios al inicio o al final.
- **FR-004**: El sistema MUST NOT permitir departamento en las rutas de tipo Interáreas.
- **FR-005**: El sistema MUST manejar los estados de la ruta Borrador, Vigente y Archivada; toda ruta nace en Borrador.
- **FR-006**: El sistema MUST permitir pasar una ruta a Vigente solo si tiene al menos 2 niveles y todos con puesto activo.
- **FR-007**: El sistema MUST permitir archivar una ruta Vigente y volver a ponerla Vigente (cumpliendo FR-006), conservando siempre sus inscripciones.
- **FR-008**: Al archivar una ruta con inscripciones Activas, el sistema MUST pedir confirmación informando la cantidad de inscritos.
- **FR-009**: El sistema MUST NOT permitir eliminar rutas ni puestos; una ruta en desuso se archiva.
- **FR-010**: El sistema MUST listar las rutas en tarjetas agrupadas por tipo con código, nombre, estado, cantidad de niveles y cantidad de inscritos, con filtros por tipo, estado y departamento y búsqueda por nombre.

**Niveles**

- **FR-011**: El sistema MUST mantener los niveles de una ruta como lista ordenada con orden consecutivo desde 1 (1 es el nivel de ingreso) y permitir agregar, reordenar y quitar niveles, renumerando sin huecos.
- **FR-012**: Cada nivel MUST referenciar un puesto de Estructura Empresarial que sea obligatorio, activo y no repetido dentro de la misma ruta.
- **FR-013**: El nombre del nivel MUST tomar por defecto el nombre del puesto y ser editable.
- **FR-014**: El sistema MUST guardar para cada nivel solo la experiencia mínima en años (opcional) como requisito propio.
- **FR-015**: El sistema MUST leer del perfil del puesto en Estructura Empresarial, en cada consulta, las competencias requeridas (con grado A, B, C o D) y los requisitos de texto de Formación, Capacitación y Experiencia (sin grado), sin copiarlos ni permitir editarlos aquí. Los conocimientos con grado no existen en Estructura y quedan fuera de alcance; una bandera de configuración `desarrollo.conocimientos_con_grado` (por defecto `false`) deja documentado el punto de extensión, sin implementar la rama activa.
- **FR-016**: El sistema MUST calcular, sin almacenarlo, el diferencial de cada nivel respecto del anterior: las competencias que suben de grado, las que se agregan y los años adicionales de experiencia mínima. Los conocimientos informativos no tienen grado ni diferencial.
- **FR-017**: El sistema MUST impedir quitar un nivel, o cambiar su puesto, cuando sea nivel actual u objetivo de una inscripción Activa (solo se pueden editar su nombre y su experiencia mínima), y rechazar un reorden que deje algún objetivo igual o anterior a su nivel actual.

**Inscripciones**

- **FR-018**: El sistema MUST permitir al administrador inscribir a un colaborador activo en la nómina (con vínculo laboral vigente), de cualquier departamento, en una ruta Vigente, registrando nivel actual (puede quedar sin definir) y nivel objetivo.
- **FR-019**: El sistema MUST validar que el nivel objetivo sea posterior al nivel actual.
- **FR-020**: El sistema MUST permitir a un colaborador estar en varias rutas, con una sola inscripción Activa por ruta.
- **FR-021**: El sistema MUST manejar los estados de inscripción Activa, Finalizada y Retirada, con fechas de registro y de resolución.
- **FR-021a**: Cuando se detecta que un colaborador ya no tiene vínculo laboral vigente en Gestión de Personal —al consultar rutas, inscripciones o «Mi ruta», o en el proceso programado de conciliación—, el sistema MUST pasar a Retirada sus inscripciones Activas con motivo «Baja del colaborador», fecha de resolución y registro en el historial de inscripciones.
- **FR-022**: El sistema MUST permitir cambiar los niveles actual y objetivo de una inscripción Activa.
- **FR-023**: «Registrar nivel alcanzado» MUST mover el nivel actual al nivel objetivo; si era el último nivel de la ruta, la inscripción pasa a Finalizada; si no, el objetivo queda sin definir hasta que se fije otro.
- **FR-024**: El registro de nivel alcanzado MUST ser informativo: el sistema no modifica el puesto del colaborador, cuyo movimiento real lo ejecuta Gestión de Personal.
- **FR-025**: El sistema MUST permitir retirar una inscripción Activa solo con confirmación y motivo obligatorio.
- **FR-026**: El sistema MUST rechazar nuevas inscripciones en rutas que no estén Vigentes.
- **FR-027**: La pestaña Participantes MUST mostrar colaborador, nivel actual, nivel objetivo y estado, con filtro por estado.

**Cantera**

- **FR-028**: El sistema MUST mostrar por ruta la cantidad de colaboradores con inscripción Activa en cada nivel actual, informando aparte los que no tienen nivel definido.

**Vista del colaborador**

- **FR-029**: El colaborador MUST poder ver su propia ruta: escalera con nivel actual resaltado y nivel objetivo marcado, y el detalle de lo exigido (competencias con grado, conocimientos informativos y experiencia) en el siguiente nivel y en su objetivo.
- **FR-030**: El sistema MUST ofrecer el botón «Ver mi adecuación», que lleva a la vista de adecuación a puesto futuro, solo cuando existe nivel objetivo y esa vista esté disponible.
- **FR-031**: El sistema MUST ofrecer al colaborador la lista de rutas Vigentes disponibles para consulta, solo lectura, y un estado vacío si no está inscrito en ninguna.
- **FR-032**: El sistema MUST mostrar de forma permanente el aviso «Esta ruta es una guía de desarrollo; no implica la promesa de un puesto.» en las vistas de administrador y de colaborador.
- **FR-033**: El colaborador MUST NOT poder inscribirse ni proponer su interés en una ruta en esta etapa; esa posibilidad queda para una etapa posterior.

**Permisos**

- **FR-034**: El sistema MUST controlar cada acción con permisos estables definidos por el módulo: `desarrollo.rutas.ver`, `desarrollo.rutas.gestionar`, `desarrollo.rutas.inscribir`, `desarrollo.rutas.inscripciones`, `desarrollo.rutas.historial` y `desarrollo.rutas.ver_propio`.
- **FR-035**: El sistema MUST asignar todos los permisos al rol Administrador (Gestor de Talento) y solo los terminados en `_propio` al rol Colaborador; la lista de rutas Vigentes disponibles y la propia inscripción del colaborador se entregan con `desarrollo.rutas.ver_propio`; un rol con `desarrollo.rutas.ver` sin `gestionar` solo ve rutas Vigentes.
- **FR-036**: Los botones de crear, editar, cambiar estado, inscribir, cambiar niveles, registrar nivel alcanzado y retirar MUST ser visibles solo para quien tenga el permiso correspondiente, y el sistema MUST rechazar la acción aunque se intente por otra vía.

**Auditoría**

- **FR-037**: El sistema MUST registrar quién creó cada ruta y quién la modificó por última vez, con fecha.
- **FR-038**: El sistema MUST registrar en un historial cada cambio de la ruta o de sus niveles (alta, edición, reorden, baja, cambio de estado) con usuario, fecha, valor anterior y valor nuevo.
- **FR-039**: El sistema MUST registrar en un historial cada cambio de una inscripción (alta, cambio de nivel, nivel alcanzado, retiro) con usuario, fecha, valores y motivo.
- **FR-040**: Los historiales MUST ser de solo lectura y no admitir edición ni borrado.

**Relación con otros módulos**

- **FR-041**: El sistema MUST consumir de Estructura Empresarial, mediante su interfaz pública, los puestos, su perfil de competencias con grados y sus requisitos de texto (Formación, Capacitación y Experiencia) y los departamentos, sin duplicar su administración.
- **FR-042**: El sistema MUST consumir de Gestión de Personal, mediante su interfaz pública, los colaboradores y su puesto actual, sin registrar movimientos ni ascensos.
- **FR-043**: El sistema MUST exponer el nivel objetivo de la inscripción y la experiencia mínima del nivel para que los módulos futuros de adecuación a puesto futuro y de Plan de Desarrollo Individual los usen, y las inscripciones para indicadores de cantera en Reportes.
- **FR-044**: Todas las pantallas MUST resolver los estados de carga, vacío, error con «Reintentar», éxito y sin permisos.

### Key Entities

- **Ruta de carrera**: guía de desarrollo identificada por código CAR-NNNN, con nombre único, tipo, descripción, departamento opcional y estado (Borrador, Vigente, Archivada); contiene niveles ordenados y registra creador y último modificador.
- **Nivel de ruta**: peldaño ordenado de una ruta; referencia un puesto de Estructura Empresarial, tiene nombre propio y experiencia mínima opcional. Sus competencias, los requisitos de texto del puesto y el diferencial de competencias se derivan, no se guardan.
- **Inscripción a la ruta**: vínculo entre un colaborador y una ruta; guarda nivel actual (opcional), nivel objetivo, estado (Activa, Finalizada, Retirada), motivo de retiro y fechas de registro y resolución.
- **Historial de ruta**: evento de cambio sobre la ruta o sus niveles, con usuario, fecha, valor anterior y valor nuevo.
- **Historial de inscripción**: evento de cambio sobre una inscripción, con usuario, fecha, valores y motivo.
- **Puesto, perfil, departamento y colaborador**: datos de otros módulos referenciados de forma lógica y leídos por su interfaz pública; no se gestionan aquí.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un administrador puede crear una ruta de tres niveles, publicarla e inscribir a un colaborador en menos de 10 minutos sin ayuda.
- **SC-002**: El 100 % de las rutas que pasan a Vigente cumplen, en ese momento, las condiciones de publicación (al menos 2 niveles, todos con puesto activo); ninguna ruta inválida llega a Vigente.
- **SC-003**: El 100 % de los diferenciales mostrados coincide con la diferencia calculada manualmente entre los perfiles de dos niveles consecutivos, incluso tras cambiar un perfil en Estructura Empresarial.
- **SC-004**: Un colaborador identifica su nivel actual, su nivel objetivo y lo que le falta para llegar en menos de 1 minuto desde que abre «Mi ruta de carrera».
- **SC-005**: Ningún colaborador puede tener dos inscripciones Activas en la misma ruta ni un nivel objetivo igual o anterior al actual; las validaciones rechazan el 100 % de esos intentos.
- **SC-006**: El 100 % de los cambios en rutas, niveles e inscripciones aparece en el historial con usuario, fecha y valores anterior y nuevo.
- **SC-007**: Las vistas de lista, escalera e inscripciones responden en menos de 2 segundos con hasta 100 rutas y 1 000 inscripciones.
- **SC-008**: Un usuario sin el permiso correspondiente no ve ni logra ejecutar ninguna acción restringida, y un colaborador solo accede a su propia inscripción (0 filtraciones en las pruebas).
- **SC-009**: El aviso «guía, no promesa» está presente en el 100 % de las pantallas que muestran una ruta.

## Assumptions

- La ruta siempre es una guía de desarrollo y no genera ni promete un puesto; ninguna acción de este módulo modifica el puesto, la remuneración ni el historial laboral del colaborador.
- Por ahora el administrador (Gestor de Talento) realiza todas las acciones de gestión; la reasignación de secciones a otros roles queda para una etapa posterior de administración de roles y permisos.
- Las claves de permiso son un catálogo estable definido por el módulo (todavía no existe una tabla de permisos) y no cambian; reasignarlas en el futuro no exige modificar esta feature.
- «Activo en la nómina» significa tener un vínculo laboral vigente según Gestión de Personal; la baja se refleja en las inscripciones cuando se detecta ese cambio, no de forma instantánea.
- Los estados de ruta e inscripción, y su nomenclatura, son convenciones del sistema y no literales de la bibliografía; cualquier cambio debe ser una decisión explícita del equipo.
- La experiencia mínima se guarda aquí porque ningún otro módulo la define; es el único requisito propio del nivel.
- El diferencial se calcula en cada consulta contra el perfil vigente del puesto; no se guarda histórico del diferencial.
- Un nivel actual sin definir significa que el colaborador aún no ocupa ningún nivel de la ruta; el objetivo siempre es fijado por el administrador.
- La feature de adecuación a puesto futuro y el Plan de Desarrollo Individual aún no existen: el botón «Ver mi adecuación» solo se activa cuando exista la vista de destino, y la precarga de planes de tipo Carrera queda a cargo de esa futura feature.
- Reportes ya está implementado y podrá leer las inscripciones para indicadores de cantera; incorporarlos a sus pantallas no forma parte de esta feature.
- Las rutas Jóvenes profesionales no imponen un plazo definido en el sistema; el tiempo de cada nivel no se modela.
- El módulo se ubica dentro de Desarrollo, reutiliza el sistema de diseño, los componentes compartidos y el esquema de roles ya existentes, y sigue las mismas convenciones de auditoría que las features anteriores.
- Fuera de alcance: proponer interés en una ruta con aprobación posterior, remuneraciones por nivel, registro de movimientos o ascensos, y edición de competencias, requisitos o puestos, y conocimientos con grado A–D (no existen en Estructura Empresarial).

## Pantallas *(si la feature tiene UI)*

### Lista de rutas (administrador)
- **Propósito**: encontrar, crear y administrar las rutas de carrera.
- **Datos que muestra**: tarjetas agrupadas por tipo con código, nombre, estado, cantidad de niveles y cantidad de inscritos; filtros por tipo, estado y departamento; búsqueda por nombre; aviso permanente de «guía, no promesa».
- **Acciones disponibles** (y quién puede hacerlas): administrador — «Nueva ruta», abrir una ruta, cambiar estado (con confirmación si hay inscritos). Colaborador — sin acceso a esta pantalla.
- **Estados**: vacío, cargando, error con «Reintentar», sin permisos.
- **Mockup**: patrón Listado (`design/RutasAdmin.dc.html`), con tarjetas agrupadas por tipo.

### Editor de ruta (administrador)
- **Propósito**: armar y mantener la escalera de niveles de una ruta.
- **Datos que muestra**: datos de la ruta (código, nombre, tipo, descripción, departamento, estado, creador y último modificador); escalera vertical con el nivel 1 abajo; indicador de cantera por nivel; detalle del nivel en tres columnas (Competencias con grado y diferencial en tono tenue | Conocimientos informativos, sin grado | Experiencia); advertencia en niveles con puesto inactivo.
- **Acciones disponibles** (y quién puede hacerlas): administrador — editar datos, agregar, reordenar y quitar niveles, cambiar nombre y experiencia del nivel, pasar a Vigente o Archivar, abrir el historial. Colaborador — sin acceso.
- **Estados**: vacío (ruta sin niveles), validación de campos, bloqueo al quitar un nivel en uso, cargando, error con «Reintentar», sin permisos.
- **Mockup**: `design/RutasAdmin.dc.html` (pestaña Escalera).

### Participantes de la ruta (administrador)
- **Propósito**: registrar y gestionar a los colaboradores que participan en la ruta.
- **Datos que muestra**: tabla con colaborador, nivel actual, nivel objetivo y estado; filtro por estado; indicador de cantera por nivel.
- **Acciones disponibles** (y quién puede hacerlas): administrador — «Inscribir colaborador», cambiar niveles, «Registrar nivel alcanzado», «Retirar» (con confirmación y motivo). Colaborador — sin acceso.
- **Estados**: vacío, validación de campos, cargando, error con «Reintentar», sin permisos, ruta no Vigente (inscribir deshabilitado con su explicación).
- **Mockup**: `design/RutasAdmin.dc.html` (pestaña Participantes).

### Historial de la ruta (administrador)
- **Propósito**: auditar los cambios de la ruta, sus niveles y sus inscripciones.
- **Datos que muestra**: eventos con usuario, fecha, valor anterior, valor nuevo y motivo.
- **Acciones disponibles** (y quién puede hacerlas): administrador — consultar y filtrar. Colaborador — sin acceso.
- **Estados**: vacío, cargando, error con «Reintentar», sin permisos.
- **Mockup**: patrón Listado/Detalle de `design/DESIGN.md`, sin mockup propio.

### Mi ruta de carrera (colaborador)
- **Propósito**: que el colaborador vea su camino y lo que se exige para llegar a su objetivo.
- **Datos que muestra**: escalera de cada ruta en que está inscrito con nivel actual resaltado y nivel objetivo marcado; detalle del siguiente nivel o del objetivo (competencias con grado, conocimientos informativos sin grado, experiencia); lista de rutas Vigentes disponibles; aviso permanente de «guía, no promesa».
- **Acciones disponibles** (y quién puede hacerlas): colaborador — seleccionar un nivel para ver su detalle, «Ver mi adecuación» (si hay nivel objetivo y existe la vista de adecuación), consultar rutas Vigentes. Administrador — no aplica.
- **Estados**: vacío (sin inscripción en ninguna ruta), cargando, error con «Reintentar».
- **Mockup**: `design/RutasColaborador.dc.html`.
