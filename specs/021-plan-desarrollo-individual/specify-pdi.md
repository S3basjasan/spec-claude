# Plan de desarrollo individual (PDI) y compromisos de mejora

## 1. Objetivo
Registrar, por colaborador, lo que acuerdan el administrador y la persona para cerrar
brechas o avanzar hacia un puesto: los compromisos de mejora, las acciones para cumplirlos,
los hitos de seguimiento y el resultado al cierre. Es el puente entre el diagnóstico (DNF y
adecuación a puesto futuro) y la ejecución (Plan de Formación y Actividades Formativas).

El administrador crea el plan, lo acuerda, da seguimiento y lo cierra. El colaborador
consulta su plan, lo acepta o lo devuelve con un comentario, y ve qué sigue.

En este documento, **elemento** significa una competencia o un conocimiento.

Tipos de plan:
- **Mejora**: brechas de competencias o conocimientos del puesto actual, o metas de
  desempeño por mejorar.
- **Carrera**: brechas hacia el puesto objetivo.
- **Talento**: Programa de Talentos para personas de alto potencial, con duración definida.

Esta feature **no administra** el Plan de Formación ni las actividades; solo las vincula.
Tampoco ejecuta ascensos ni cambios de puesto.

## 2. Roles y permisos
| Acción | Permiso | Administrador (Gestor de Talento) | Colaborador |
|---|---|---|---|
| Crear y editar un plan en Borrador | `desarrollo.pdi.gestionar` | Sí | No |
| Precargar compromisos desde la DNF o la adecuación | `desarrollo.pdi.gestionar` | Sí | No |
| Confirmar el acuerdo como responsable del plan | `desarrollo.pdi.acordar` | Sí | No |
| Devolver un plan Acordado a Borrador | `desarrollo.pdi.gestionar` | Sí | No |
| Registrar avances de acciones e hitos en un plan Acordado | `desarrollo.pdi.seguimiento` | Sí | No |
| Vincular acciones del plan a acciones del Plan de Formación | `desarrollo.pdi.vincular` | Sí | No |
| Cerrar un plan con su resultado | `desarrollo.pdi.cerrar` | Sí | No |
| Ver la carta Gantt consolidada | `desarrollo.pdi.gantt` | Sí | No |
| Ver los colaboradores con brechas y sin plan | `desarrollo.pdi.gestionar` | Sí | No |
| Consultar planes, compromisos, acciones, hitos e historial | `desarrollo.pdi.ver` / `desarrollo.pdi.ver_propio` | Sí (todos) | Sí (solo los propios) |
| Aceptar el plan propio | `desarrollo.pdi.aceptar_propio` | No aplica | Sí |
| Devolver el plan propio con un comentario | `desarrollo.pdi.aceptar_propio` | No aplica | Sí |

Notas:
- El **responsable** del plan es un usuario con rol Administrador que acuerda y da
  seguimiento junto con el colaborador.
- Por ahora el administrador realiza todas las acciones de gestión. En una etapa posterior
  las secciones de esta feature podrán asignarse a otros roles (por ejemplo, jefaturas)
  desde la administración de roles y permisos.

Las claves de la columna Permiso son estables y se siembran en un seeder del módulo. Por ahora se asignan todas al rol Administrador (Gestor de Talento) y solo las terminadas en `_propio` al rol Colaborador. En una etapa posterior, la administración de roles y permisos podrá asignarlas a otros roles sin cambiar esta feature.

## 3. Elementos

### 3.1 Datos de un plan
- **Código**: único, generado por el sistema con formato PDI-0001 (secuencial). No editable.
- **Colaborador**: obligatorio.
- **Tipo**: Mejora, Carrera o Talento. No cambia después de crear el plan.
- **Objetivo del plan**: texto obligatorio.
- **Puesto objetivo**: obligatorio en los tipos Carrera y Talento.
- **Origen**: referencia opcional a una DNF (Feature ya implementada) o a una adecuación (Feature ya implementada) desde
  la cual se precargó el plan.
- **Responsable**: usuario con rol Administrador que acuerda y da seguimiento con el
  colaborador. Por defecto, quien crea el plan; es editable.
- **Periodo**: fecha de inicio y de fin, obligatorias. En el tipo Talento la duración es
  definida (por ejemplo, uno o dos años).
- **Estado**: Borrador, Acordado o Cerrado.
- **Acuerdos**: fecha y usuario del acuerdo del responsable, y fecha de aceptación del
  colaborador.
- **Comentario del colaborador**: se guarda al devolver el plan.
- **Resultado**: Brecha cerrada, Parcial o No cerrada. Se completa al cerrar.
- **Conclusión**: texto opcional al cerrar.

### 3.2 Datos de un compromiso
- **Dimensión**: Competencia, Conocimiento, Experiencia o Meta.
- **Elemento**: la competencia o el conocimiento a mejorar. Obligatorio en esas dos
  dimensiones; no aplica en Experiencia ni Meta.
- **Descripción**: obligatoria en Experiencia y Meta.
- **Nivel actual y nivel a alcanzar**: grados A, B, C o D, en competencias y conocimientos. Se
  precargan desde la brecha de origen.
- **Indicador**: opcional; describe cómo se verificará la mejora.
- **Periodo estimado**: fechas dentro del periodo del plan.
- **Resultado**: Cerrada, Parcial o No cerrada. Se completa al cerrar el plan.

### 3.3 Datos de una acción
- **Tipo**: Curso, Mentoring, Coaching, Autodesarrollo, Codesarrollo, Nueva responsabilidad u
  Otra.
- **Descripción**, **persona a cargo** (texto: mentor, coach u otra) y **fechas**.
- **Vínculo con el Plan de Formación**: opcional, solo para los tipos Curso, Mentoring y
  Codesarrollo. Apunta a una acción de un plan en estado Aprobado o Ejecutando en la que el
  colaborador ya sea participante.
- **Estado**:
  - Si está vinculada, es de solo lectura y se deriva de la acción del plan: la acción
    Finalizada con asistencia del colaborador es Cumplida; una acción Cancelada es Cancelada;
    en otro caso, Pendiente o En curso según las fechas.
  - Si no está vinculada, se registra a mano: Pendiente, En curso, Cumplida o Cancelada.
- Cada compromiso necesita al menos una acción.

### 3.4 Datos de un hito de seguimiento
- **Fecha**, **descripción**, **estado** (Pendiente o Realizado) y **observaciones**.
- Son las revisiones intermedias que el responsable programa antes del cierre.

### 3.5 Estados y transiciones
- **Borrador a Acordado**: ocurre automáticamente cuando existen el acuerdo del responsable y
  la aceptación del colaborador. Requisitos para poder acordar: al menos un compromiso, cada
  compromiso con al menos una acción, responsable y periodo definidos.
- Cualquier edición del plan en Borrador borra los acuerdos ya dados.
- Si el colaborador devuelve el plan con comentario, el plan sigue en Borrador, se borran los
  acuerdos y se guarda el comentario.
- **Acordado a Borrador**: el administrador puede devolver un plan Acordado a Borrador
  indicando motivo; se borran los acuerdos.
- **Acordado a Cerrado**: exige que cada compromiso tenga su resultado, que todas las acciones
  estén Cumplidas o Canceladas y, opcionalmente, una conclusión. El resultado del plan se
  calcula: Brecha cerrada si todos los compromisos están Cerrada, No cerrada si ninguno lo
  está, y Parcial en otro caso.
- En un plan Acordado no se edita la estructura (compromisos, acciones, fechas): solo se
  registran avances en acciones e hitos.
- Un plan Cerrado no se edita ni se elimina.
- Solo puede existir un plan no Cerrado por colaborador y tipo.
- Al cerrar un plan de tipo Carrera o Talento, la pantalla ofrece generar una nueva
  adecuación (Feature ya implementada) para medir el resultado.

### 3.6 Avance
- El avance del plan es el porcentaje de acciones Cumplidas sobre las acciones no Canceladas.
- Un hito Pendiente con fecha anterior a hoy se marca como vencido.

## 4. Forma de visualización

### 4.1 Vista del administrador: asistente de creación
- **Paso 1 – Colaborador y tipo**: se elige colaborador, tipo y, según el tipo, puesto
  objetivo. El sistema ofrece precargar los compromisos desde la última DNF (tipo Mejora) o
  desde la última adecuación (tipos Carrera y Talento); el administrador puede quitarlos o
  agregar otros.
- **Paso 2 – Compromisos y acciones**: tabla editable de compromisos, cada uno con sus
  acciones. Las acciones formativas permiten el botón "Vincular a Plan de Formación", que
  abre un selector de acciones disponibles.
- **Paso 3 – Hitos, responsable y periodo**: fechas, responsable y calendario de hitos. Guarda
  el plan en Borrador.

### 4.2 Lista y detalle del administrador
- Lista con código, colaborador, tipo, estado, avance, responsable, fecha de fin e indicador
  de hitos vencidos. Filtros por tipo, estado, departamento, responsable y rango de fechas.
- Indicador de colaboradores con brechas activas y sin plan de desarrollo.
- Detalle con pestañas: Resumen, Compromisos y acciones, Hitos e Historial. En el resumen,
  botones según estado: Confirmar acuerdo, Devolver a Borrador, Registrar avance y Cerrar.
  Devolver y Cerrar abren un modal (el primero exige motivo).
- Cerrar abre un modal con el resultado de cada compromiso y la conclusión.

### 4.3 Carta Gantt (administrador)
- Vista consolidada de las acciones de los planes Acordados, con filtros por tipo de plan,
  responsable y departamento, para dar seguimiento a las intervenciones.

### 4.4 Vista del colaborador
- **"Mi plan de desarrollo"**: tarjetas de sus planes con tipo, estado, avance y periodo.
- Detalle: compromisos con sus acciones y hitos. En un plan Borrador, botones "Aceptar plan" y
  "Devolver con comentario".
- Sección "Qué sigue": las próximas acciones y hitos con fecha. Las acciones vinculadas
  muestran el enlace a su actividad formativa.
- Estado vacío si no tiene planes.

### 4.5 Acciones del administrador
- Los botones de crear, editar, acordar, registrar avance, vincular, cerrar y devolver solo
  son visibles para el administrador y solo cuando el estado del plan lo permite.
- Un plan Cerrado se muestra en solo lectura.

## 5. Auditoría
- Se registra quién creó cada plan y cuándo.
- Cada cambio de estado, cada acuerdo o aceptación, cada devolución con su motivo o
  comentario, cada vinculación o desvinculación con el Plan de Formación y cada registro de
  avance de acciones e hitos queda en un historial con usuario, fecha, valor anterior y valor
  nuevo.

## 6. Relación con otros módulos
- **Gestión de Personal** aporta, vía su Facade, los colaboradores y su departamento.
- **Estructura Empresarial** aporta, vía su Facade, los puestos y los datos de competencias y
  conocimientos activos.
- **DNF (Feature ya implementada)** aporta las brechas activas para precargar planes de tipo Mejora.
- **Adecuación a puesto futuro (Feature ya implementada)** aporta las brechas y el puesto objetivo para
  precargar planes de tipo Carrera y Talento, y recibe la solicitud de nueva adecuación al
  cerrar.
- **Rutas y planes de carrera (Feature ya implementada)** aporta el nivel objetivo para los planes de tipo
  Carrera.
- **Plan de Formación (Feature ya implementada)** es dueño de las acciones formativas vinculadas; este
  feature solo guarda la referencia y no las modifica.
- **Actividades Formativas (Feature ya implementada)** aporta la asistencia del colaborador, vía su Facade,
  para derivar el estado Cumplida de las acciones vinculadas.
- **Micro-Learning (Feature ya implementada)** puede sugerir micro-contenidos al elegir acciones de tipo
  Autodesarrollo. Este feature no los gestiona.
- **Reportes (Feature ya implementada)** puede usar los planes para "Mi plan de desarrollo" y para
  indicadores de cierre de brechas.

## 7. Fundamento
- Navarro, *Evaluación del Desempeño*, Paso 8 (p. 108), Hoja de Compromisos de Mejora:
  plantilla donde se registra lo acordado con el colaborador para mejorar sus brechas.
  Contiene competencia o meta a mejorar, acciones de mejora, indicadores (no obligatorio) y
  periodo de tiempo estimado.
- Navarro, Paso 17 (p. 177–178): la jefatura prepara con antelación los compromisos de mejora
  y planes de desarrollo, los presenta al colaborador y los firma junto con la evaluación si
  son aceptados.
- Navarro, Paso 9 f) (p. 120–121) y j) (p. 123): ante brechas, la persona siempre participa de
  algún proceso de formación, y los compromisos de mejora individuales son una consecuencia
  posible. Paso 19 (p. 188): tabla de consecuencias y encargados; la inclusión en un programa
  de talentos la aplica Capacitación.
- Navarro, Paso 20, Acción 54 (p. 190): recopilar los planes de mejora individuales,
  sistematizar la información y generar una carta Gantt.
- Navarro, Paso 9 g) (p. 121): el programa de talentos integra a personas de alto potencial,
  con capacitaciones técnicas, mentoring, coaching y nuevas responsabilidades, por un tiempo
  definido, y termina con una evaluación frente al perfil del nuevo cargo.
- Alles, *Las 50 herramientas de RRHH*, H.35, Fig. 72 (p. 180): el modelo para construir
  talento incluye planes individuales para alcanzar un nivel superior y planes individuales
  para crear talento, con el rol de los jefes. H.36A (Mentoring, p. 189), H.20A (Entrenamiento
  experto, p. 133) y H.42A (p. 229, que lista autodesarrollo y codesarrollo como herramientas
  relacionadas).
- Navarro, Pasos 21 y 22 (p. 193, 196): el informe final incluye las posibilidades de
  desarrollo de carrera y el proceso se repite cada año aprendiendo del anterior.

### Decisiones de diseño del sistema (no literales en la bibliografía)
- Los tres tipos de plan, los estados (Borrador, Acordado, Cerrado), el resultado de cierre y
  el cálculo del avance son convenciones del sistema.
- En el Paso 20 el libro describe solo la Acción 54 (p. 190–191). La estructura de seis
  componentes del plan de mejora (diagnóstico, objetivo, acciones, hitos, indicadores y
  cierre) proviene del resumen del proyecto sobre Navarro, no de esas páginas; se usa como
  guía, no como cita textual. Lo literal del libro es la Hoja de Compromisos de Mejora
  (p. 108).
- Los hitos intermedios son un recurso del sistema para el seguimiento del responsable.
- El Programa de Talentos se modela como un tipo de plan y no como un módulo aparte.
- El acuerdo por doble confirmación (responsable y colaborador) adapta la firma de los
  compromisos descrita en el Paso 17. Navarro atribuye la preparación de los compromisos a la
  jefatura; como por ahora no existe el rol de jefatura, el responsable es un administrador.
- La regla de un plan no Cerrado por colaborador y tipo, y la exigencia de que el colaborador
  ya sea participante de la acción vinculada, son decisiones del sistema.
- Si el equipo prefiere otras reglas o nomenclatura, debe ser una decisión explícita del grupo
  y no presentarse como si viniera de la bibliografía.
