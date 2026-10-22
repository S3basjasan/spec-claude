# Rutas y planes de carrera

## 1. Objetivo
Definir rutas de carrera (secuencias de niveles dentro de un área o entre áreas) y
registrar qué colaboradores participan en cada una, en qué nivel se encuentran y a cuál
aspiran llegar. La ruta es una guía de desarrollo: muestra el camino y lo que se exige en
cada nivel, pero **no promete** un puesto.

El administrador crea y mantiene las rutas, inscribe a los colaboradores y fija su nivel
objetivo. El colaborador consulta su ruta, su nivel actual y lo que se exige para llegar a
su nivel objetivo.

Este módulo **no administra** los puestos, las competencias ni los conocimientos (los
consume de Estructura Empresarial), **no registra** movimientos ni ascensos (los ejecuta
Gestión de Personal) y **no define** remuneraciones (Compensaciones).

## 2. Roles y permisos
| Acción | Permiso | Administrador (Gestor de Talento) | Colaborador |
|---|---|---|---|
| Listar y consultar rutas y sus niveles | `desarrollo.rutas.ver` | Sí (todas las rutas) | Sí (solo rutas Vigentes) |
| Crear y editar rutas y niveles | `desarrollo.rutas.gestionar` | Sí | No |
| Cambiar el estado de una ruta (Vigente, Archivada) | `desarrollo.rutas.gestionar` | Sí | No |
| Inscribir a un colaborador y fijar sus niveles actual y objetivo | `desarrollo.rutas.inscribir` | Sí | No |
| Cambiar niveles, registrar nivel alcanzado y retirar una inscripción | `desarrollo.rutas.inscribir` | Sí | No |
| Consultar las inscripciones y la cantera de talento | `desarrollo.rutas.inscripciones` | Sí | No |
| Consultar el historial de una ruta | `desarrollo.rutas.historial` | Sí | No |
| Ver su propia ruta de carrera, nivel actual y nivel objetivo | `desarrollo.rutas.ver_propio` | No aplica | Sí (solo la propia) |
| Ver lo que se exige en el siguiente nivel o en su objetivo | `desarrollo.rutas.ver_propio` | No aplica | Sí |

Por ahora el administrador realiza todas las acciones de gestión. En una etapa posterior
las secciones de esta feature podrán asignarse a otros roles desde la administración de
roles y permisos.

Las claves de la columna Permiso son estables y se siembran en un seeder del módulo. Por ahora se asignan todas al rol Administrador (Gestor de Talento) y solo las terminadas en `_propio` al rol Colaborador. En una etapa posterior, la administración de roles y permisos podrá asignarlas a otros roles sin cambiar esta feature.

## 3. Elementos

### 3.1 Datos de una ruta
- **Código**: único, generado por el sistema con formato CAR-0001 (secuencial). No
  editable.
- **Nombre**: obligatorio y único. La unicidad no distingue mayúsculas ni espacios al
  inicio o al final.
- **Tipo**: Especialista, Gerencial, Interáreas o Jóvenes profesionales.
- **Descripción**: opcional.
- **Departamento**: opcional, referencia al departamento donde nace la ruta. No aplica a
  las rutas Interáreas.
- **Estado**: Borrador, Vigente o Archivada.
- **Niveles**: lista ordenada de niveles (ver 3.2).

### 3.2 Datos de un nivel
- **Orden**: número consecutivo desde 1 (1 es el nivel de ingreso).
- **Puesto**: referencia al puesto del Descriptivo de puestos de Estructura Empresarial.
  Obligatorio, activo y no repetido dentro de la misma ruta.
- **Nombre del nivel**: por defecto, el nombre del puesto; editable.
- **Experiencia mínima (años)**: opcional. Es el único requisito que se guarda en esta
  feature.
- **Competencias y conocimientos requeridos**: no se guardan aquí. Se leen del perfil del
  puesto en Estructura Empresarial cada vez que se consulta, con su grado requerido (A, B,
  C o D).
- **Diferencial respecto del nivel anterior**: calculado por el sistema. Muestra qué
  competencias o conocimientos suben de grado, cuáles se agregan y cuántos años más de
  experiencia se piden.

### 3.3 Datos de una inscripción a la ruta
- **Colaborador** y **ruta**.
- **Nivel actual**: nivel de la ruta donde se ubica hoy el colaborador. Puede quedar sin
  definir si aún no ocupa ninguno.
- **Nivel objetivo**: nivel al que aspira llegar, fijado por el administrador. Debe ser
  posterior al nivel actual.
- **Estado**: Activa, Finalizada o Retirada.
- **Motivo**: obligatorio al retirar.
- **Fechas** de registro y de resolución.

### 3.4 Reglas
- Una ruta pasa a Vigente solo si tiene al menos 2 niveles, todos con puesto activo.
- Solo las rutas Vigentes admiten nuevas inscripciones. Una ruta Archivada conserva sus
  inscripciones y puede volver a Vigente.
- No se puede quitar un nivel que sea nivel actual u objetivo de una inscripción Activa.
- Un colaborador puede estar en varias rutas, pero solo con una inscripción Activa por
  ruta.
- "Registrar nivel alcanzado" mueve el nivel actual al nivel objetivo. Si era el último
  nivel de la ruta, la inscripción pasa a Finalizada; si no, el objetivo queda sin definir
  hasta que se fije otro. Este registro es informativo: el cambio real de puesto lo ejecuta
  Gestión de Personal.
- Las rutas y los puestos nunca se eliminan. Una ruta que ya no se use se archiva.

### 3.5 Cantera de talento
- Para cada ruta se muestra la cantidad de colaboradores con inscripción Activa en cada
  nivel.

## 4. Forma de visualización

### 4.1 Vista del administrador
- **Lista de rutas** en tarjetas agrupadas por tipo, con código, nombre, estado, cantidad
  de niveles y cantidad de inscritos. Filtros por tipo, estado y departamento; búsqueda por
  nombre.
- **Editor de ruta**: escalera vertical con los niveles ordenados (el nivel 1 abajo) y
  botones para agregar, reordenar y quitar niveles.
- **Detalle del nivel**: tres columnas (Competencias | Conocimientos | Experiencia). Cada
  columna muestra lo requerido y, en tono tenue, el diferencial respecto del nivel
  anterior. Las columnas de competencias y conocimientos son de solo lectura.
- **Pestaña Inscripciones**: tabla con colaborador, nivel actual, nivel objetivo y estado.
  Acciones: inscribir, cambiar niveles, registrar nivel alcanzado y retirar. Filtro por
  estado.
- Indicador de cantera por nivel.
- Aviso permanente: "Esta ruta es una guía de desarrollo; no implica la promesa de un
  puesto."

### 4.2 Vista del colaborador
- **"Mi ruta de carrera"**: escalera con su nivel actual resaltado y su nivel objetivo
  marcado.
- Detalle del siguiente nivel o del objetivo: lo que se exige en competencias,
  conocimientos y experiencia.
- Botón "Ver mi adecuación", que lleva a la vista de adecuación a puesto futuro
  (Feature 8) cuando existe un nivel objetivo.
- Lista de rutas Vigentes disponibles para consulta.
- Aviso permanente de "guía, no promesa".
- Estado vacío si no está inscrito en ninguna ruta.
- La posibilidad de que el colaborador proponga su propio interés en una ruta queda para
  una etapa posterior (ver Decisiones de diseño).

### 4.3 Acciones del administrador
- Los botones de crear, editar, cambiar estado, inscribir, cambiar niveles, registrar
  nivel alcanzado y retirar solo son visibles para el administrador.
- Archivar una ruta con inscripciones activas pide confirmación e informa la cantidad de
  inscritos.
- Retirar una inscripción pide confirmación y motivo.

## 5. Auditoría
- Se registra quién creó cada ruta y quién la modificó por última vez, con fecha.
- Cada cambio en la ruta o en sus niveles (alta, edición, reorden, baja, cambio de estado)
  queda en un historial con usuario, fecha, valor anterior y valor nuevo.
- Cada cambio de una inscripción (alta, cambio de nivel, nivel alcanzado, retiro) queda en
  un historial con usuario, fecha, valores y motivo.

## 6. Relación con otros módulos
- **Estructura Empresarial** aporta, vía su Facade, los puestos, su perfil de competencias
  y conocimientos requeridos con sus grados, y los departamentos.
- **Gestión de Personal** aporta, vía su Facade, los colaboradores y su puesto actual. El
  movimiento real a un nuevo puesto se registra allí, no en esta feature.
- **Adecuación a puesto futuro (Feature aun no implementada)** usa el nivel objetivo de la inscripción como
  puesto objetivo por defecto y la experiencia mínima del nivel.
- **Plan de Desarrollo Individual (Feature aun no implementada)** toma el nivel objetivo para precargar los
  planes de tipo Carrera.
- **Reportes (Feature ya implementada)** puede usar las inscripciones para indicadores de cantera.

## 7. Fundamento
- Alles, *Las 50 herramientas de RRHH*, H.42A (Planes de carrera, p. 229–230): programa
  para desarrollar personas mediante un esquema de niveles con requisitos para pasar de uno
  a otro; su propósito es crear talento organizacional ("cantera de talentos"). Fig. 101
  (p. 230): cada nivel tiene requisitos y un diferencial necesario para el nivel superior en
  **conocimientos, competencias y experiencia**. Puede interconectar sectores distintos
  (interáreas, p. 230).
- Alles, p. 230: el plan "no implica que la organización 'promete' un puesto de trabajo
  superior… señala un camino a seguir para alcanzar ese puesto el día en que la vacante se
  produzca".
- Alles, H.8A (Carrera como especialista, p. 93) y H.8B (Carrera gerencial, p. 95): dos
  modalidades de carrera con niveles relacionados con la escala de remuneraciones de la
  organización.
- Alles, H.23 (Estructura de puestos, p. 141): documento con los niveles organizacionales y
  requisitos; base para asignar competencias a puestos.
- Alles, H.41A (Plan de jóvenes profesionales, p. 223): ruta de crecimiento en un lapso
  definido para recién egresados.
- Alles, H.35 (Mapa y ruta del talento, p. 182): las decisiones de desarrollo consideran
  conocimientos, experiencia y competencias.
- Bassett-Jones, *Strategic HRM*, Cap. 10 (según el resumen del proyecto): las
  organizaciones con orientación interna del mercado laboral construyen planes de carrera
  estructurados.

### Decisiones de diseño del sistema (no literales en la bibliografía)
- Los estados de la ruta (Borrador, Vigente, Archivada) y de la inscripción (Activa,
  Finalizada, Retirada) son convenciones del sistema.
- Alles muestra el diferencial por nivel. El sistema no lo guarda: lee el requisito
  absoluto del perfil del puesto y calcula la diferencia.
- La experiencia mínima por nivel se guarda aquí porque ningún otro módulo la define.
- Por ahora solo el administrador fija el puesto objetivo del colaborador. Que el
  colaborador pueda proponer su interés en una ruta (con aprobación posterior) es una
  mejora prevista para cuando se implementen los permisos por rol; Alles mide los
  proyectos personales (H.19, p. 129) pero no describe un proceso de aprobación.
- Si el equipo prefiere otras reglas o nomenclatura, debe ser una decisión explícita del
  grupo y no presentarse como si viniera de la bibliografía.
