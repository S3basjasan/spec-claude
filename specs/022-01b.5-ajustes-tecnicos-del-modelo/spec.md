# Feature Specification: Ajustes técnicos del modelo de categorías (01b.5)

**Feature Branch**: `022-01b.5-ajustes-tecnicos-del-modelo`

**Created**: 2026-11-03

**Status**: Draft

**Input**: User description: "01b.5 Ajustes técnicos del modelo de categorías (documento fuente `01b.5-ajustes-tecnicos-del-modelo.md`, copiado en esta carpeta; inc-01b Ajuste de categorías y parámetros, semana 3 del plan). Los indicadores de cada categoría pasan a guardarse dentro de la categoría como lista ordenada y la competencia se valida solo por la Facade de Estructura, sin cambiar nada de lo que ve el usuario."

Origen: Técnica. Depende de: —. Fuera de alcance: cambiar reglas de negocio de categorías y unificar la auditoría con otros módulos (pendiente con el equipo).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Los indicadores viven dentro de la categoría (Priority: P1)

Como equipo, queremos que los indicadores de cada categoría se guarden como una lista ordenada dentro de la propia categoría, y que cada guardado reemplace la lista completa, de modo que el modelo coincida con el modelo de datos vigente y ya no se borren y reinserten filas en cada edición. El Gestor no percibe ningún cambio.

**Why this priority**: Es el núcleo del ajuste; sin él el modelo sigue desalineado con el modelo de datos vigente.

**Independent Test**: Con categorías existentes de inc-01, aplicar el cambio y verificar que cada categoría conserva todos sus indicadores en el mismo orden; luego editar una categoría y comprobar que la lista enviada reemplaza a la anterior y que el Gestor ve exactamente lo mismo que antes.

**Acceptance Scenarios**:

1. **Given** categorías existentes con indicadores, **When** se aplica el cambio de modelo, **Then** cada categoría conserva todos sus indicadores, en el mismo orden y con el mismo texto.
2. **Given** una categoría con indicadores, **When** el Gestor la guarda enviando la lista completa de indicadores, **Then** la lista enviada reemplaza a la anterior y la categoría muestra exactamente esa lista, en ese orden.
3. **Given** una categoría guardada, **When** se consulta por pantalla o API, **Then** las respuestas tienen la misma forma y contenido que antes del cambio.
4. **Given** el cambio ya aplicado, **When** se decide revertirlo, **Then** la reversión restituye el almacenamiento anterior de indicadores sin pérdida de datos.

---

### User Story 2 - Las reglas de indicadores se mantienen (Priority: P1)

Como equipo, queremos que las reglas de validación de indicadores sigan siendo las mismas y se apliquen en el servidor, para no cambiar el comportamiento de negocio.

**Why this priority**: Garantiza que el cambio técnico no altere el negocio; el cambio no es aceptable si las reglas se rompen.

**Independent Test**: Intentar guardar categorías con 0 y 6 indicadores, con indicadores de 4 y 251 caracteres y con duplicados que solo difieren en mayúsculas o tildes; todos deben rechazarse en el servidor.

**Acceptance Scenarios**:

1. **Given** una categoría, **When** se guarda con 0 o con más de 5 indicadores, **Then** se rechaza con el mismo mensaje que antes.
2. **Given** una categoría, **When** un indicador tiene menos de 5 o más de 250 caracteres, **Then** se rechaza con el mismo mensaje que antes.
3. **Given** una categoría, **When** dos indicadores solo difieren en mayúsculas o tildes, **Then** se rechaza por repetido.
4. **Given** la misma situación enviada saltándose la pantalla, **When** llega al servidor, **Then** el servidor aplica las mismas reglas.

---

### User Story 3 - La competencia se valida solo por la Facade de Estructura (Priority: P2)

Como equipo, queremos que la categoría valide la competencia asociada únicamente a través de la Facade de Estructura y que la base de datos no tenga una restricción hacia la tabla de competencias de otro módulo, para respetar que los módulos se relacionan solo por su Facade.

**Why this priority**: Es la segunda parte del alineamiento con el modelo vigente; no depende de la historia 1 y tiene menor riesgo de datos.

**Independent Test**: Guardar una categoría con una competencia existente (se acepta) y con una inexistente (se rechaza con el mismo mensaje que antes), y verificar que la base de datos ya no tiene restricción hacia la tabla de competencias.

**Acceptance Scenarios**:

1. **Given** una competencia existente, **When** se guarda una categoría asociada a ella, **Then** la validación se resuelve consultando la Facade de Estructura y se acepta.
2. **Given** una competencia inexistente, **When** se guarda una categoría asociada a ella, **Then** se rechaza con el mismo resultado que antes.
3. **Given** el cambio aplicado, **When** se revisa la base de datos, **Then** no existe ninguna restricción desde categorías hacia la tabla de competencias.

---

### User Story 4 - La auditoría sigue registrando indicadores como lista (Priority: P2)

Como equipo, queremos que la auditoría de la categoría siga registrando los cambios de indicadores como lista, para no perder trazabilidad.

**Why this priority**: Protege la trazabilidad existente; es una verificación de no regresión.

**Independent Test**: Editar los indicadores de una categoría y comprobar que el registro de auditoría muestra el antes y el después como listas.

**Acceptance Scenarios**:

1. **Given** una categoría con indicadores, **When** el Gestor cambia sus indicadores y guarda, **Then** la auditoría registra el valor anterior y el nuevo como lista de indicadores.

---

### Edge Cases

- Categoría existente cuyo orden de indicadores solo estaba implícito: se conserva el orden que el usuario veía antes del cambio.
- Categoría sin indicadores en los datos existentes (si la hubiera): el cambio no falla ni inventa indicadores.
- Guardado que envía los mismos indicadores sin cambios: el resultado es idéntico y la auditoría no registra un cambio de indicadores.
- Guardado concurrente de la misma categoría: la última lista guardada reemplaza a la anterior, sin mezclar indicadores de dos envíos.
- Reversión del cambio después de que se hayan editado categorías con el modelo nuevo: los indicadores vigentes se restituyen al almacenamiento anterior sin pérdida.
- Competencia asociada que ya no existe al guardar: se rechaza con el mismo mensaje que antes, aunque ya no haya restricción en la base de datos.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Los indicadores de cada categoría MUST guardarse como una lista ordenada dentro de la propia categoría.
- **FR-002**: El cambio MUST conservar todos los indicadores existentes, con el mismo texto y en el mismo orden.
- **FR-003**: El sistema MUST seguir exigiendo de 1 a 5 indicadores por categoría.
- **FR-004**: El sistema MUST seguir exigiendo de 5 a 250 caracteres por indicador.
- **FR-005**: El sistema MUST seguir rechazando indicadores repetidos dentro de la categoría sin distinguir mayúsculas ni tildes.
- **FR-006**: Las reglas de los indicadores MUST validarse en el servidor, independientemente de la pantalla.
- **FR-007**: Al guardar una categoría, el envío MUST incluir la lista completa de indicadores y reemplazar a la anterior; ya no se borran filas de ninguna tabla.
- **FR-008**: La competencia asociada a una categoría MUST validarse únicamente por la Facade de Estructura.
- **FR-009**: La base de datos MUST NOT tener una restricción desde categorías hacia la tabla de competencias.
- **FR-010**: El cambio de datos MUST realizarse con una migración nueva y reversible que copie los datos existentes; la migración original de inc-01 MUST NOT editarse.
- **FR-011**: Pantallas, API y respuestas MUST permanecer sin cambios para el usuario.
- **FR-012**: Las pruebas de inc-01 MUST seguir pasando; solo se ajustan las que dependían de la estructura anterior de indicadores.
- **FR-013**: La auditoría de la categoría MUST seguir registrando los cambios de indicadores como lista.

### Key Entities *(include if feature involves data)*

- **Categoría**: agrupador gestionado por el Gestor; tiene una competencia asociada y una lista ordenada de 1 a 5 indicadores que forma parte de la propia categoría.
- **Indicador**: texto de 5 a 250 caracteres que pertenece a una sola categoría y ocupa una posición en su lista; no tiene existencia independiente.
- **Competencia (de Estructura)**: pertenece a otro módulo; la categoría solo la referencia y se valida a través de la Facade de Estructura.
- **Registro de auditoría de categoría**: guarda el antes y el después de los cambios, incluidos los indicadores como lista.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100 % de las categorías existentes conserva el mismo número de indicadores, el mismo texto y el mismo orden después del cambio.
- **SC-002**: Los casos de validación de indicadores (cantidad, longitud, repetidos) producen los mismos resultados que antes en el 100 % de los casos probados.
- **SC-003**: Las pantallas y respuestas de categorías no presentan diferencias visibles ni de contenido respecto a antes del cambio.
- **SC-004**: La suite de pruebas de inc-01 pasa completa, con ajustes únicamente en las pruebas que dependían de la estructura anterior.
- **SC-005**: El cambio puede aplicarse y revertirse sin pérdida de datos de categorías ni de indicadores.
- **SC-006**: La auditoría muestra los cambios de indicadores como lista en el 100 % de las ediciones probadas.
- **SC-007**: Ninguna estructura de categorías conserva dependencia directa con la tabla de competencias de otro módulo.

## Assumptions

- El Gestor es el único usuario de la funcionalidad y no percibe diferencias; no hay roles ni permisos nuevos.
- Las reglas de negocio de categorías de inc-01 son las vigentes y no se modifican.
- La Facade de Estructura ya existe y ofrece la validación de existencia de una competencia.
- El orden actual de los indicadores es el que el usuario ve en pantalla y es el que se debe conservar.
- La unificación de la auditoría con otros módulos queda pendiente con el equipo y no forma parte de este cambio.
- No hay pantallas nuevas ni modificadas.
