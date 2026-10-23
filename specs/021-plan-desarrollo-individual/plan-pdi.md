# Plan de desarrollo individual (PDI) y compromisos de mejora

## 1. Contexto de la feature
- Stack y arquitectura según constitution.md.
- Esta feature no crea ni modifica competencias, conocimientos, puestos ni colaboradores. Los
  consume solo a través de las Facades:
  - Estructura: datos de competencias y conocimientos activos, y puestos.
  - Gestión de Personal: colaboradores y su departamento.
- Un elemento es una competencia o un conocimiento, identificado por (tipo_elemento,
  elemento_id).
- Las demás features del módulo se consultan solo por sus Facades:
  - DNF (Feature ya implementada): brechasActivas(colaboradorId), para precargar planes de tipo Mejora.
  - Adecuación (Feature ya implementada): ultimaAdecuacion(colaboradorId, puestoObjetivoId), para precargar
    planes de tipo Carrera y Talento.
  - Rutas (Feature ya implementada): obtenerNivel(nivelId), para el puesto objetivo en planes de tipo
    Carrera.
  - Plan de Formación (Feature ya implementada): consultar acciones de planes Aprobado o Ejecutando y sus
    participantes, y su estado calculado.
  - Actividades Formativas (Feature ya implementada): consultar la asistencia de un colaborador en una
    acción.
- Esta feature expone su propia Facade con las operaciones:
  - planesActivos(colaboradorId): planes no Cerrados con avance; usada por Reportes (Feature
    ya implementada) para "Mi plan de desarrollo".
  - consultar planes por filtros (tipo, estado, resultado, fechas, departamento): usada por
    Reportes (Feature ya implementada).
  - existePlanAbierto(colaboradorId, tipo): usada por Adecuación (Feature ya implementada) para habilitar
    sus botones.
- Backend: backend/app/Modules/Desarrollo.
- Frontend: frontend/src/modules/desarrollo.
- Si la Facade de Estructura no expone conocimientos, una bandera de configuración desactiva
  esa dimensión en los compromisos.

## 2. Modelo de datos
- pdis: id, codigo (único, PDI-0001), colaborador_id (referencia lógica a Gestión de Personal
  sin FK entre módulos), tipo (Mejora | Carrera | Talento), objetivo, puesto_objetivo_id
  (nullable, referencia lógica a Estructura; obligatorio en Carrera y Talento), origen_dnf_id
  (nullable, referencia lógica a DNF), origen_adecuacion_id (nullable, referencia lógica a
  Adecuación), responsable_id (referencia lógica a usuarios con rol Administrador),
  fecha_inicio, fecha_fin, estado (Borrador | Acordado | Cerrado, default Borrador),
  acuerdo_responsable_at (nullable), acuerdo_responsable_by (nullable),
  aceptacion_colaborador_at (nullable), comentario_colaborador (nullable), resultado
  (BrechaCerrada | Parcial | NoCerrada, nullable), conclusion (nullable), created_by,
  updated_by, timestamps. Índice único parcial en (colaborador_id, tipo) donde estado sea
  Borrador o Acordado.
- pdi_compromisos: id, pdi_id (FK, sin cascade delete), dimension (Competencia | Conocimiento
  | Experiencia | Meta), tipo_elemento (Competencia | Conocimiento, nullable; solo en esas
  dimensiones), elemento_id (nullable, referencia lógica a Estructura), descripcion
  (nullable), nivel_actual (A | B | C | D, nullable), nivel_objetivo (A | B | C | D,
  nullable), indicador (nullable), fecha_inicio, fecha_fin, resultado (Cerrada | Parcial |
  NoCerrada, nullable), timestamps.
- pdi_acciones: id, compromiso_id (FK, sin cascade delete), tipo (Curso | Mentoring | Coaching
  | Autodesarrollo | Codesarrollo | NuevaResponsabilidad | Otra), descripcion, persona_cargo
  (nullable), fecha_inicio, fecha_fin, plan_accion_id (nullable, referencia lógica a
  plan_acciones; no único, porque una acción del plan puede servir a varios planes), estado
  (Pendiente | EnCurso | Cumplida | Cancelada; solo se usa si no hay vínculo), created_by,
  updated_by, timestamps.
- pdi_hitos: id, pdi_id (FK, sin cascade delete), fecha, descripcion, estado (Pendiente |
  Realizado, default Pendiente), observaciones (nullable), registrado_by (nullable),
  timestamps.
- pdi_historial: id, pdi_id (FK), tipo (Estado | Acuerdo | Devolucion | Vinculo | Avance |
  Hito), referencia_id (nullable), valor_anterior (nullable), valor_nuevo (nullable), motivo
  (nullable), user_id, created_at.
- El estado de una acción vinculada no se guarda: se calcula a partir de la acción del plan
  (estado calculado, cancelación) y de la asistencia consultada por Facade. El estado guardado
  solo aplica a acciones sin vínculo.
- El avance del plan y los hitos vencidos se calculan, no se guardan.
- El resultado del plan se calcula al cerrar y se guarda.
- No se usa DELETE físico sobre planes ni soft deletes de Laravel. En un plan en Borrador, los
  compromisos, acciones y hitos se reemplazan dentro de la transacción de actualización, con
  borrado explícito y sin cascade.
- plan_accion_id se valida con la Facade de Plan de Formación, no con FK física hacia otro
  conjunto de tablas gestionado por otra feature.
- Seeder: 5 planes que incluyan los tres tipos y los tres estados (al menos uno Cerrado con
  resultado Brecha cerrada y otro Cerrado con resultado Parcial), con compromisos de las cuatro
  dimensiones, acciones de los siete tipos, al menos una acción vinculada a una acción del
  seeder del Plan de Formación y un hito vencido. Depende de los seeders de Estructura, Gestión
  de Personal, Plan de Formación, Actividades, DNF y Adecuación.

- Seeder de permisos: siembra las claves de la columna Permiso del specify (prefijo `desarrollo.`) y su asignación por defecto a los roles Administrador y Colaborador.

## 3. Backend
Todos los endpoints requieren autenticación (401). Cada operación se autoriza en un único punto (Policy o middleware del módulo) que verifica la clave de la columna Permiso (403); los controladores no verifican el nombre del rol. Por ahora el seeder asigna las claves de gestión al rol Administrador (Gestor de Talento) y las terminadas en `_propio` al rol Colaborador; con estas últimas solo se devuelven datos del usuario autenticado.

| Operación | Endpoint | Permiso | Notas |
|---|---|---|---|
| Precargar | GET /api/pdi/precarga | `desarrollo.pdi.gestionar` | Query: colaborador_id, tipo, puesto_objetivo_id (según el tipo). Devuelve compromisos sugeridos a partir de la DNF o la adecuación más reciente, sin guardar. Lista vacía si no hay brechas. |
| Crear | POST /api/pdi | `desarrollo.pdi.gestionar` | Recibe colaborador_id, tipo, objetivo, puesto_objetivo_id, responsable_id, fechas, origen opcional, compromisos con sus acciones y hitos. Crea el plan en Borrador en una transacción. 201 con el plan. 422 si falla la validación, si falta el puesto objetivo en Carrera o Talento, si ya hay un plan no Cerrado de ese tipo para el colaborador, si hay elementos inactivos o inexistentes, si el responsable no es administrador, o si las fechas salen del periodo del plan. |
| Listar | GET /api/pdi | `desarrollo.pdi.ver` | Filtros: tipo, estado, colaborador_id, departamento_id, responsable_id, fecha_desde, fecha_hasta. Paginación: page, per_page. Devuelve código, colaborador, tipo, estado, avance, responsable, fecha de fin e indicador de hitos vencidos. |
| Consultar | GET /api/pdi/{id} | `desarrollo.pdi.ver` / `desarrollo.pdi.ver_propio` | Incluye compromisos con acciones (con su estado resuelto), hitos y avance. 404 si no existe o no es accesible. |
| Actualizar | PUT /api/pdi/{id} | `desarrollo.pdi.gestionar` | Solo en estado Borrador. Reemplaza datos generales, compromisos, acciones y hitos en una transacción y borra los acuerdos. 422 si el plan no está en Borrador o si falla la validación. 404 si no existe. |
| Acuerdo del responsable | POST /api/pdi/{id}/acuerdo | `desarrollo.pdi.acordar` | Registra el acuerdo del responsable. Si ya existe la aceptación del colaborador, pasa a Acordado. 422 si el plan no está en Borrador o está incompleto (sin compromisos, compromisos sin acciones, sin responsable o sin periodo). |
| Aceptar | POST /api/pdi/{id}/aceptar | `desarrollo.pdi.aceptar_propio` | Registra la aceptación del colaborador. Si ya existe el acuerdo del responsable, pasa a Acordado. 403 si el plan no es del usuario. 422 si no está en Borrador. |
| Devolver con comentario | POST /api/pdi/{id}/devolver-colaborador | `desarrollo.pdi.aceptar_propio` | Body: { comentario }. Guarda el comentario y borra los acuerdos; el plan sigue en Borrador. 403 si el plan no es del usuario. 422 si falta el comentario o no está en Borrador. |
| Devolver a Borrador | POST /api/pdi/{id}/devolver | `desarrollo.pdi.gestionar` | Body: { motivo }. Solo desde Acordado. Borra los acuerdos y registra el historial. 422 si falta el motivo o no está Acordado. |
| Vincular acción | PUT /api/pdi-acciones/{id}/vinculo | `desarrollo.pdi.vincular` | Body: { plan_accion_id } o nulo para desvincular. Valida por Facade que la acción del plan esté en un plan Aprobado o Ejecutando, que el colaborador sea participante y que el tipo de la acción del PDI sea Curso, Mentoring o Codesarrollo. Permitido en planes Borrador y Acordado. 422 si no cumple. 404 si no existe. |
| Registrar avance de acción | PATCH /api/pdi-acciones/{id}/avance | `desarrollo.pdi.seguimiento` | Body: { estado }. Solo para acciones sin vínculo, en planes Acordado. Registra el historial. 422 si la acción está vinculada o el plan no está Acordado. |
| Gestionar hito | PATCH /api/pdi-hitos/{id} | `desarrollo.pdi.seguimiento` | Body: { estado, observaciones }. Solo en planes Acordado. Registra el historial. 422 si el plan no está Acordado. |
| Cerrar | POST /api/pdi/{id}/cerrar | `desarrollo.pdi.cerrar` | Body: { resultados por compromiso, conclusion? }. Exige que todas las acciones estén Cumplidas o Canceladas. Calcula y guarda el resultado del plan. 422 si faltan resultados, hay acciones abiertas o el plan no está Acordado. Devuelve el plan y, si es Carrera o Talento, la indicación de ofrecer una nueva adecuación. |
| Historial | GET /api/pdi/{id}/historial | `desarrollo.pdi.ver` | Paginación: page, per_page. 404 si no existe. |
| Mi plan | GET /api/pdi/mi-plan | `desarrollo.pdi.ver_propio` | Devuelve los planes del usuario autenticado con compromisos, acciones, hitos, avance y la sección "Qué sigue". Respuesta vacía si no tiene planes. |
| Gantt | GET /api/pdi/gantt | `desarrollo.pdi.gantt` | Acciones de planes Acordados. Filtros: tipo, responsable_id, departamento_id. Paginación: page, per_page. |
| Brechas sin plan | GET /api/pdi/sin-plan | `desarrollo.pdi.gestionar` | Colaboradores con brecha activa y sin plan no Cerrado. Filtro: departamento_id. Paginación: page, per_page. |

No existen endpoints DELETE. Los planes se cierran y las acciones se cancelan.

## 4. Frontend
- Usar los componentes y tokens de design/ (tabla, tarjetas, badges, modales, botones,
  pestañas, asistente por pasos, barra de progreso, línea de tiempo o Gantt, colores). No crear
  componentes nuevos si existe uno equivalente en el diseño.
- Vista del administrador: asistente de 3 pasos (colaborador y tipo con precarga, compromisos y
  acciones con vinculación, hitos y responsable), lista con filtros e indicador de hitos
  vencidos, detalle con pestañas (Resumen, Compromisos y acciones, Hitos, Historial), carta
  Gantt consolidada y vista de colaboradores con brechas sin plan.
- Badge de estado del plan (Borrador, Acordado, Cerrado), badge de tipo (Mejora, Carrera,
  Talento), badge de estado de acción (Pendiente, En curso, Cumplida, Cancelada), badge de
  resultado (Brecha cerrada, Parcial, No cerrada) y badge de tipo de elemento, todos con los
  colores semánticos del design system.
- Devolver a Borrador y Devolver con comentario abren un modal que exige el texto. Cerrar abre
  un modal con el resultado de cada compromiso y la conclusión.
- Vista del colaborador: tarjetas de planes con avance, detalle con compromisos, acciones e
  hitos, botones "Aceptar plan" y "Devolver con comentario" cuando el plan está en Borrador, y
  sección "Qué sigue" con enlace a la actividad formativa de las acciones vinculadas.
- Estado vacío para el colaborador sin planes.
- La visibilidad de botones y secciones se decide con las claves de permiso del usuario autenticado, no con el nombre del rol.
