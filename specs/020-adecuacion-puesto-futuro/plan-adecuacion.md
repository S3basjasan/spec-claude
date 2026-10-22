# Adecuación a puesto futuro y mapa de talento

## 1. Contexto de la feature
- Stack y arquitectura según constitution.md.
- Esta feature no crea ni modifica puestos, competencias, conocimientos ni niveles de
  colaboradores. Los consume solo a través de las Facades:
  - Estructura: puestos, perfil de competencias y conocimientos del puesto (con grado),
    estado activo o inactivo de cada uno.
  - Gestión de Personal: colaboradores, puesto actual, nivel actual por competencia y por
    conocimiento, y años de experiencia acumulada.
- Un elemento es una competencia o un conocimiento, identificado por (tipo_elemento,
  elemento_id).
- Rutas y planes de carrera (Feature ya implementada) se consulta solo por su Facade:
  obtenerNivel(nivelId) e inscripcionesActivas(colaboradorId).
- El cálculo de grados y diferencias usa el servicio único de la Feature ya implementada (conversión D=1,
  C=2, B=3, A=4). No se reimplementa la fórmula.
- Esta feature expone su propia Facade con las operaciones:
  - ultimaAdecuacion(colaboradorId, puestoObjetivoId): usada por el Plan de Desarrollo
    Individual (Feature aun no implementada).
  - consultar adecuaciones por filtros: usada por Reportes (Feature ya implementada).
- Backend: backend/app/Modules/Desarrollo.
- Frontend: frontend/src/modules/desarrollo.
- Si Estructura o Gestión de Personal no exponen conocimientos o años de experiencia, una
  bandera de configuración desactiva la dimensión correspondiente y la adecuación se evalúa
  con las dimensiones disponibles.

## 2. Modelo de datos
- adecuaciones: id, codigo (único, ADE-0001), colaborador_id (referencia lógica a Gestión de
  Personal sin FK entre módulos), puesto_actual_id (referencia lógica, copia del momento),
  puesto_objetivo_id (referencia lógica a Estructura), inscripcion_id (nullable, FK a
  ruta_inscripciones), nivel_objetivo_id (nullable, FK a ruta_niveles),
  experiencia_requerida_anios (decimal 4,1, nullable), preparacion (Lista | EnDesarrollo |
  NoLista | Incompleta), umbral_meses (entero, copia del parámetro vigente), observaciones
  (nullable), created_by, timestamps.
- adecuacion_detalles: id, adecuacion_id (FK, sin cascade delete), dimension (Competencia |
  Conocimiento | Experiencia), tipo_elemento (Competencia | Conocimiento, nullable; nulo en
  Experiencia), elemento_id (nullable, referencia lógica a Estructura; nulo en Experiencia),
  requerido_actual_nivel (A | B | C | D, nullable), requerido_objetivo_nivel (A | B | C | D,
  nullable), valor_nivel (A | B | C | D, nullable), requerido_actual_anios,
  requerido_objetivo_anios y valor_anios (decimal 4,1, nullable), diferencia (decimal,
  nullable), estado (ConBrecha | Cumple | Supera | SinEvaluacion), tiempo_cierre_meses
  (entero, nullable; obligatorio si estado es ConBrecha). Restricción única en
  (adecuacion_id, dimension, tipo_elemento, elemento_id).
- adecuacion_parametros: id, peso_competencias, peso_conocimientos, peso_experiencia
  (enteros que suman 100), umbral_meses (entero), updated_by, timestamps. Una sola fila
  vigente.
- adecuacion_parametros_historial: id, campo, valor_anterior, valor_nuevo, user_id,
  created_at.
- No se usa UPDATE ni DELETE sobre adecuaciones ni adecuacion_detalles: una vez generada, la
  adecuación es inmutable. No hay cascade.
- La comparativa de candidatos no se guarda: se calcula en cada consulta a partir de las
  adecuaciones vigentes.
- Seeder: 4 adecuaciones sobre los datos de los seeders de Rutas (Feature ya implementada), Estructura y
  Gestión de Personal, que cubran las cuatro preparaciones (Lista, En desarrollo, No lista e
  Incompleta), al menos un puesto objetivo con 3 candidatos y una adecuación con puesto
  directo. Parámetros iniciales 40/30/30 y 12 meses. Depende de los seeders de Estructura,
  Gestión de Personal y Rutas.

- Seeder de permisos: siembra las claves de la columna Permiso del specify (prefijo `desarrollo.`) y su asignación por defecto a los roles Administrador y Colaborador.

## 3. Backend
Todos los endpoints requieren autenticación (401). Cada operación se autoriza en un único punto (Policy o middleware del módulo) que verifica la clave de la columna Permiso (403); los controladores no verifican el nombre del rol. Por ahora el seeder asigna las claves de gestión al rol Administrador (Gestor de Talento) y las terminadas en `_propio` al rol Colaborador; con estas últimas solo se devuelven datos del usuario autenticado.

| Operación | Endpoint | Permiso | Notas |
|---|---|---|---|
| Previsualizar | POST /api/adecuaciones/previsualizar | `desarrollo.adecuacion.generar` | Recibe colaborador_id y puesto_objetivo_id o inscripcion_id, y experiencia_requerida_anios (opcional si es puesto directo). Obtiene perfil y valores vía Facades, calcula diferencias y estados, y devuelve el resultado sin guardar, con avisos de requisitos inactivos omitidos y sin evaluación. 422 si no se define el puesto objetivo, si el colaborador o el puesto no existen, o si el puesto no tiene requisitos evaluables. |
| Generar | POST /api/adecuaciones | `desarrollo.adecuacion.generar` | Recibe los mismos datos que previsualizar, más tiempo_cierre_meses por cada resultado Con brecha y observaciones. Recalcula en el servidor (no confía en valores del cliente), calcula la preparación y guarda todo en una transacción. 201 con la adecuación. 422 si falta el tiempo de cierre en alguna brecha, si falla la validación o si no hay requisitos evaluables. |
| Listar | GET /api/adecuaciones | `desarrollo.adecuacion.ver` | Historial. Filtros: fecha_desde, fecha_hasta, colaborador_id, puesto_objetivo_id, preparacion. Paginación: page, per_page. Devuelve código, fecha, colaborador, puesto objetivo, preparación y cantidad de brechas. |
| Consultar | GET /api/adecuaciones/{id} | `desarrollo.adecuacion.ver` | Incluye resultados por dimensión. 404 si no existe. |
| Mi adecuación | GET /api/adecuaciones/mi-adecuacion | `desarrollo.adecuacion.ver_propio` | Devuelve la adecuación vigente del usuario autenticado para su puesto objetivo, con tiempos de cierre y preparación. Respuesta vacía si no tiene. Nunca incluye datos de otras personas. |
| Mapa de talento | GET /api/adecuaciones/mapa | `desarrollo.adecuacion.mapa` | Adecuación vigente por colaborador y puesto objetivo. Filtros: departamento_id, ruta_id, preparacion. Paginación: page, per_page. |
| Comparar candidatos | GET /api/adecuaciones/comparar | `desarrollo.adecuacion.comparar` | Query: puesto_objetivo_id, colaborador_ids, y pesos opcionales para esa consulta. Calcula el puntaje con la adecuación vigente de cada candidato; los Incompleta y los que no tienen adecuación se devuelven en listas aparte. 422 si los pesos no suman 100 o si hay ids repetidos. |
| Consultar parámetros | GET /api/adecuaciones/parametros | `desarrollo.adecuacion.parametros` | Pesos, umbral e historial reciente. |
| Actualizar parámetros | PUT /api/adecuaciones/parametros | `desarrollo.adecuacion.parametros` | Body: pesos y umbral. Registra el historial. 422 si los pesos no suman 100, si algún peso es negativo o si el umbral no es positivo. |

No existen endpoints PUT, PATCH ni DELETE sobre las adecuaciones.

## 4. Frontend
- Usar los componentes y tokens de design/ (tabla, tarjetas, badges, modales, botones,
  pestañas, asistente por pasos, barras, colores). No crear componentes nuevos si existe uno
  equivalente en el diseño.
- Vista del administrador: asistente de 3 pasos (selección, diagnóstico con campo de tiempo de
  cierre por brecha, confirmación), tres paneles con barras de requerido actual, valor de la
  persona y requerido objetivo, y pestañas Evaluaciones, Comparar candidatos, Mapa de talento
  y Parámetros.
- Badge de preparación (Lista, En desarrollo, No lista, Incompleta), badge de estado del
  resultado (Con brecha, Cumple, Supera, Sin evaluación) y badge de tipo de elemento con los
  colores semánticos del design system.
- Vista del colaborador: tres tarjetas por dimensión, insignia de preparación, tiempo de
  cierre por brecha, botón "Ver cursos sugeridos" que navega al Catálogo Formativo (Feature ya implementada)
  con el elemento en la ruta, y aviso permanente de "guía, no promesa de puesto".
- Los botones "Crear plan de desarrollo" e "Incluir en Programa de Talentos" navegan al
  asistente de la Feature aun no implementada con la adecuación en la ruta.
- Estados vacíos para el colaborador sin puesto objetivo o sin adecuación.
- La visibilidad de botones y secciones se decide con las claves de permiso del usuario autenticado, no con el nombre del rol.
