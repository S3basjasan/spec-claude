# Adecuación a puesto futuro y mapa de talento

## 1. Objetivo
Medir qué tan preparado está un colaborador para un puesto objetivo comparando, en tres
dimensiones (competencias, conocimientos y experiencia), lo que el puesto exige contra lo
que la persona tiene hoy. El resultado muestra las brechas hacia el puesto futuro junto a
la adecuación al puesto actual, estima el tiempo para cerrarlas, permite comparar
candidatos para un mismo puesto y ofrece un mapa de talento para decidir quién entra a un
programa de desarrollo.

El administrador genera las adecuaciones, compara candidatos, consulta el mapa de talento y
mantiene los parámetros. El colaborador consulta su propia adecuación a su puesto objetivo.

Esta feature **no decide** ascensos ni los ejecuta: aporta la evidencia. Tampoco
administra puestos, competencias, conocimientos ni los datos de los colaboradores.

## 2. Roles y permisos
| Acción | Permiso | Administrador (Gestor de Talento) | Colaborador |
|---|---|---|---|
| Generar una adecuación | `desarrollo.adecuacion.generar` | Sí | No |
| Consultar adecuaciones e historial | `desarrollo.adecuacion.ver` | Sí | No |
| Comparar candidatos para un puesto | `desarrollo.adecuacion.comparar` | Sí | No |
| Ver el mapa de talento | `desarrollo.adecuacion.mapa` | Sí | No |
| Consultar y editar pesos y umbral de preparación | `desarrollo.adecuacion.parametros` | Sí | No |
| Crear un plan de desarrollo desde una adecuación | `desarrollo.pdi.gestionar` (Feature aun no implementada) | Sí (Feature aun no implementada) | No |
| Ver su propia adecuación a su puesto objetivo | `desarrollo.adecuacion.ver_propio` | No aplica | Sí (solo la propia) |
| Ver cursos sugeridos para una brecha | `desarrollo.adecuacion.ver_propio` | No aplica | Sí |

Notas:
- El colaborador nunca ve los puntajes de comparación ni los datos de otros candidatos.
- Por ahora el administrador realiza todas las acciones de gestión. En una etapa posterior
  las secciones de esta feature podrán asignarse a otros roles desde la administración de
  roles y permisos.

Las claves de la columna Permiso son estables y se siembran en un seeder del módulo. Por ahora se asignan todas al rol Administrador (Gestor de Talento) y solo las terminadas en `_propio` al rol Colaborador. En una etapa posterior, la administración de roles y permisos podrá asignarlas a otros roles sin cambiar esta feature.

## 3. Elementos

En este documento, **elemento** significa una competencia o un conocimiento.

### 3.1 Datos de una adecuación
- **Código**: único, generado por el sistema con formato ADE-0001 (secuencial). No
  editable.
- **Colaborador**: obligatorio.
- **Puesto actual**: copia del momento, tomada de Gestión de Personal.
- **Puesto objetivo**: obligatorio. Se define de una de dos formas:
  - **Desde una ruta**: nivel objetivo de una inscripción Activa del colaborador
    (Feature ya implementada). Es la opción por defecto.
  - **Puesto directo**: cualquier puesto activo, para promociones o desplazamientos
    laterales sin ruta.
- **Inscripción de origen**: referencia opcional a la inscripción de la ruta.
- **Experiencia requerida (años)**: viene de la experiencia mínima del nivel de la ruta. Si
  el objetivo es un puesto directo, el administrador puede ingresarla; si no la ingresa, la
  dimensión Experiencia no se evalúa.
- **Fecha de generación**: la asigna el sistema.
- **Preparación**: Lista, En desarrollo, No lista o Incompleta (ver 3.4).
- **Observaciones**: opcional.

### 3.2 Datos de un resultado (uno por requisito evaluado)
- **Dimensión**: Competencia, Conocimiento o Experiencia.
- **Elemento**: la competencia o el conocimiento evaluado. No aplica a Experiencia.
- **Requerido en el puesto actual** y **requerido en el puesto objetivo**: grado (A, B, C o
  D) para competencias y conocimientos; años para experiencia.
- **Valor de la persona**: grado actual del colaborador, o años de experiencia acumulada.
- **Diferencia**: valor de la persona menos lo requerido por el puesto objetivo. Un valor
  negativo indica brecha, cero indica que cumple y un valor positivo indica que supera lo
  requerido.
- **Estado**: Con brecha, Cumple, Supera o Sin evaluación.
- **Tiempo estimado de cierre (meses)**: obligatorio en cada resultado Con brecha; lo
  ingresa el administrador antes de guardar.

### 3.3 Cálculo
- Los grados se traducen a escala numérica con el mismo servicio de cálculo de la Feature 2
  (D = 1, C = 2, B = 3, A = 4). El grado A es el de mayor desarrollo.
- Para competencias y conocimientos, la diferencia se expresa en grados.
- Para experiencia, la diferencia se expresa en años.
- Se evalúan las competencias y los conocimientos activos del perfil del puesto objetivo.
- Si el colaborador no tiene valor registrado en un requisito, el estado es Sin evaluación y
  no se calcula diferencia.
- El requerido en el puesto actual se muestra para comparar; no genera brecha.

### 3.4 Preparación
Es una clasificación del sistema sobre el resultado completo:
- **Lista**: ningún resultado Con brecha.
- **En desarrollo**: hay brechas y todas tienen tiempo estimado de cierre menor o igual al
  umbral.
- **No lista**: al menos una brecha supera el umbral.
- **Incompleta**: algún requisito quedó Sin evaluación; no se puede clasificar.

El umbral es un parámetro del sistema, con valor inicial de 12 meses.

### 3.5 Estado y ciclo de vida
- Una adecuación es un diagnóstico puntual: una vez generada no se edita ni se elimina, para
  preservar el histórico y la auditoría.
- Los valores se guardan tal como se calcularon, aunque después cambien el perfil del puesto
  o el nivel del colaborador.
- La **adecuación vigente** de un colaborador para un puesto objetivo es la más reciente.

### 3.6 Comparativa de candidatos
- El administrador elige un puesto objetivo y candidatos. Se usa la adecuación vigente de
  cada uno para ese puesto; quien no tenga, aparece señalado y no entra al ranking.
- Puntaje de cada candidato: suma de los pesos de cada dimensión multiplicados por su
  cumplimiento en esa dimensión.
- Cumplimiento de una dimensión: promedio, sobre sus requisitos, de valor de la persona
  dividido entre lo requerido, con tope de 1.
- Las dimensiones que no se evaluaron se excluyen y los pesos restantes se reescalan.
- Los candidatos con preparación Incompleta se listan aparte, sin puntaje.
- El resultado se calcula en el momento y no se guarda. Es una ayuda para decidir, no una
  decisión.

### 3.7 Parámetros
- **Pesos por dimensión**: valores iniciales Competencias 40 %, Conocimientos 30 % y
  Experiencia 30 %. Deben sumar 100.
- **Umbral de preparación**: meses, valor inicial 12.
- Cada cambio queda en el historial de parámetros.

### 3.8 Mapa de talento
- Tabla con la adecuación vigente de cada colaborador por puesto objetivo: colaborador,
  puesto actual, puesto objetivo, preparación, cantidad de brechas y tiempo máximo de
  cierre.
- Es el punto de partida para decidir quién entra a un Programa de Talentos, que se gestiona
  como un plan de tipo Talento en la Feature aun no implementada.

## 4. Forma de visualización

### 4.1 Vista del administrador: asistente de generación
- **Paso 1 – Selección**: el administrador elige colaborador (lista desplegable con
  búsqueda) y puesto objetivo: por defecto, el nivel objetivo de su inscripción Activa; o
  bien un puesto directo. Si es puesto directo, puede ingresar la experiencia requerida.
- **Paso 2 – Diagnóstico**: se muestra, sin guardar, el resultado en tres paneles
  (Competencias | Conocimientos | Experiencia). Cada panel tiene barras que comparan el
  requerido del puesto actual, el valor de la persona y el requerido del puesto objetivo, y
  una tabla con requisito, valores, diferencia y estado. En cada brecha se ingresa el tiempo
  estimado de cierre.
- **Paso 3 – Confirmación**: se muestra la preparación calculada y un campo de observaciones;
  al confirmar se guarda la adecuación.
- Avisos informativos cuando hay requisitos Sin evaluación o cuando se omitieron
  competencias o conocimientos inactivos.
- Después de guardar, botones "Crear plan de desarrollo" e "Incluir en Programa de
  Talentos". Quedan deshabilitados si no hay brechas.

### 4.2 Pestañas del administrador
- **Evaluaciones**: historial con código, fecha, colaborador, puesto objetivo, preparación y
  cantidad de brechas. Filtros por fecha, colaborador, puesto y preparación. Cada fila abre
  el detalle en solo lectura.
- **Comparar candidatos**: selector de puesto y candidatos, pesos editables solo para esta
  consulta y ranking con puntaje, preparación y cantidad de brechas.
- **Mapa de talento**: tabla filtrable por departamento, ruta y preparación.
- **Parámetros**: pesos y umbral, con su historial.

### 4.3 Vista del colaborador
- **"Mi adecuación"** a su puesto objetivo: tres tarjetas (Competencias, Conocimientos,
  Experiencia) con barras de requerido contra actual y la diferencia (ejemplo: "Nivel
  actual: C · Requerido: B · Diferencia: −1").
- Insignia de preparación y tiempo estimado de cierre de cada brecha.
- Botón "Ver cursos sugeridos" en cada brecha de competencia o conocimiento, que lleva al
  Catálogo Formativo (Feature ya implementada) filtrado por ese elemento.
- En las brechas de experiencia se muestra el mensaje "Se desarrolla con práctica; revisa tu
  plan de desarrollo".
- Aviso permanente: "Esta evaluación es una guía de desarrollo; no implica la promesa de un
  puesto."
- Estado vacío si no tiene puesto objetivo o aún no tiene adecuación.

### 4.4 Acciones del administrador
- Los botones de generar, comparar, ver mapa y editar parámetros solo son visibles para el
  administrador.
- No existen botones de editar ni eliminar una adecuación.

## 5. Auditoría
- Se registra quién generó cada adecuación y cuándo.
- El historial de adecuaciones es en sí mismo el registro de auditoría de los diagnósticos.
- Cada cambio de parámetros queda en un historial con usuario, fecha, valor anterior y valor
  nuevo.

## 6. Relación con otros módulos
- **Estructura Empresarial** aporta, vía su Facade, el perfil de competencias y conocimientos
  del puesto con sus grados, y los puestos.
- **Gestión de Personal** aporta, vía su Facade, el puesto actual, el nivel actual por
  competencia y por conocimiento, y los años de experiencia acumulada.
- **Rutas y planes de carrera (Feature ya implementada )** aporta el nivel objetivo y la experiencia
  mínima.
- **DNF (Feature ya implementada)** aporta el servicio de cálculo de grados y diferencias; esta feature no
  duplica la fórmula.
- **Catálogo Formativo (Feature ya implementada)** recibe la navegación desde "Ver cursos sugeridos".
- **Plan de Desarrollo Individual (Feature aun no implementada)** puede crearse desde una adecuación y guarda
  la referencia.
- **Reportes (Feature ya implementada)** puede usar las adecuaciones para indicadores de preparación.

## 7. Fundamento
- Alles, *Las 50 herramientas de RRHH*, H.35 (Mapa y ruta del talento, p. 182): toda decisión
  de programas internos debe considerar conocimientos, experiencia y competencias, medidos y
  comparados con los requeridos por el puesto a ocupar. Figs. 74, 75 y 76 (p. 182–183): cada
  comparación se divide en adecuación al puesto actual y brechas con el puesto futuro.
- Alles, p. 182: antes de decidir hay que "evaluar el tiempo estimado en el cual esa brecha
  se podría cerrar o reducir"; si tardara mucho, no es buena decisión designar a la persona
  en el nuevo puesto.
- Alles, H.1 (Adecuación persona-puesto, p. 72–74): la medición requiere un Descriptivo de
  puesto como parámetro.
- Alles, H.50A (Promociones internas, p. 263–264, Fig. 127): antes de designar a una persona
  a un nuevo puesto, también en desplazamientos laterales, se comparan sus capacidades con
  las del puesto a ocupar.
- Alles, H.42B (p. 232) y H.50B (p. 266): para elegir entre varias personas se sugiere la
  teoría de las decisiones, ponderando los distintos aspectos.
- Navarro, *Evaluación del Desempeño*, Paso 9 b) (p. 118): la persona a promocionar debe ser
  evaluada contra el perfil del cargo futuro, para evitar que "se pierda un buen soldado y se
  gane un mal capitán".
- Alles, H.40A (Personas clave, p. 217) y Navarro, Paso 9 g) (p. 121): los programas de
  talento se dirigen a personas de alto potencial.
- Navarro, Paso 7 (p. 90–94): la diferencia entre lo esperado y lo logrado.

### Decisiones de diseño del sistema (no literales en la bibliografía)
- La clasificación de preparación (Lista, En desarrollo, No lista, Incompleta) y el umbral
  de 12 meses son una ayuda del sistema. Alles solo pide evaluar el tiempo de cierre de las
  brechas.
- La fórmula del puntaje (suma ponderada con pesos configurables y cumplimiento limitado a 1)
  es una adaptación operativa. Alles menciona la teoría de las decisiones y ponderar los
  aspectos, pero en las páginas disponibles no da una fórmula. Los pesos iniciales (40/30/30)
  son valores de partida del sistema.
- La escala de grados A, B, C y D se reutiliza para los conocimientos como supuesto de
  trabajo; debe confirmarse con el módulo de Estructura Empresarial.
- Los años de experiencia como dato de la persona dependen de lo que entregue Gestión de
  Personal; debe confirmarse con ese módulo.
- La regla de que el colaborador no ve los puntajes ni a otros candidatos es una decisión del
  proyecto.
- Si el equipo prefiere otras reglas o nomenclatura, debe ser una decisión explícita del grupo
  y no presentarse como si viniera de la bibliografía.
