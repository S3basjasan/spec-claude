# Contrato HTTP: Plan de desarrollo individual (PDI)

Bases: `/api/pdi`, `/api/pdi-acciones`, `/api/pdi-hitos`. Todos los endpoints exigen `auth:sanctum` (401 sin sesión) y la clave de permiso indicada (403); los controladores no consultan el rol. Respuestas en `{ "data": … }`; errores 422 como `{ "message", "errors": { campo: [mensajes] } }`; 409 por versión desfasada (`{ "message", "version_actual" }`); errores seguros (sin trazas ni datos de otros módulos). **No existen endpoints `DELETE`**: los planes se cierran y las acciones se cancelan.

Un permiso con alternativas se escribe `a | b` (el middleware `permiso:` acepta varias claves separadas por `|`; basta una). Para las claves `_propio` el caso de uso exige que el plan sea del colaborador del usuario autenticado (si no, 404 en lecturas y 403 en acciones) **y** que sea visible para él (FR-047, ver abajo).

Orden de rutas: `/permisos`, `/opciones`, `/acciones-vinculables`, `/precarga`, `/mi-plan`, `/gantt`, `/sin-plan` van **antes** de `/{id}` (`whereNumber('id')`).

## Tipos comunes
- `tipo`: `Mejora | Carrera | Talento`. `estado`: `Borrador | Acordado | Cerrado`.
- `dimension`: `Competencia | Conocimiento | Experiencia | Meta`.
- `tipo_accion`: `Curso | Mentoring | Coaching | Autodesarrollo | Codesarrollo | NuevaResponsabilidad | Otra`.
- `estado_accion`: `Pendiente | EnCurso | Cumplida | Cancelada`. `estado_hito`: `Pendiente | Realizado`.
- `resultado_compromiso`: `Cerrada | Parcial | NoCerrada`. `resultado`: `BrechaCerrada | Parcial | NoCerrada`.
- **Plan (resumen)**: `{ id, codigo, colaborador: {id, nombre, departamento}, tipo, estado, objetivo, puesto_objetivo: {id, nombre}|null, responsable: {id, nombre, sin_permiso: bool}, fecha_inicio, fecha_fin, avance: 0-100|null, hitos_vencidos: n, version }`.
- **Plan (detalle)** añade: `origen: {tipo: "dnf"|"adecuacion", id}|null`, `acuerdo_responsable: {fecha, usuario}|null`, `aceptacion_colaborador: fecha|null`, `comentario_colaborador`, `resultado`, `conclusion`, `creado_por`, `creado_en`, `compromisos[]`, `hitos[]`, `acciones_permitidas` (qué botones habilita el estado: `editar`, `acordar`, `devolver`, `avance`, `vincular`, `cerrar`, `cambiar_responsable`).
- **Compromiso**: `{ id, orden, dimension, tipo_elemento, elemento: {id, nombre}|null, descripcion, nivel_actual, nivel_objetivo, indicador, fecha_inicio, fecha_fin, resultado, acciones[] }`.
- **Acción**: `{ id, orden, tipo, descripcion, persona_cargo, fecha_inicio, fecha_fin, estado, estado_derivado: bool, vinculo: {plan_accion_id, plan_codigo, plan_nombre, actividad_id|null, requiere_revision: bool}|null }`. `estado_derivado = true` indica solo lectura.
- **Hito**: `{ id, fecha, descripcion, estado, observaciones, vencido: bool }`.

## GET /api/pdi/permisos — sin permiso adicional
`{ "data": { "claves": ["desarrollo.pdi.gestionar", …] } }` del usuario autenticado.

## GET /api/pdi/opciones — `desarrollo.pdi.gestionar`
Query: `q`, `colaborador_id`. `{ colaboradores: [{id, codigo, nombre, departamento}], puestos: [{id, codigo, nombre}], departamentos: [{id, nombre}], responsables: [{id, nombre}], competencias: [{id, codigo, nombre}] (activas), dimensiones_disponibles: ["Competencia","Experiencia","Meta"] }` (incluye `"Conocimiento"` solo si `desarrollo.pdi.conocimientos_con_grado` es `true`). Con `colaborador_id`: `{ puesto_objetivo_sugerido: {id, nombre}|null, plan_abierto: { Mejora|Carrera|Talento: {id, codigo}|null } }`.

## GET /api/pdi/precarga — `desarrollo.pdi.gestionar`
Query: `colaborador_id` (req.), `tipo` (req.), `puesto_objetivo_id` (req. en Carrera y Talento). No guarda. 200:
```json
{ "data": { "origen": { "tipo": "dnf", "id": 31, "fecha": "2026-09-30" },
  "compromisos": [ { "dimension": "Competencia", "tipo_elemento": "Competencia", "elemento": {"id": 12, "nombre": "Trabajo en equipo"},
      "nivel_actual": "C", "nivel_objetivo": "B", "descripcion": null } ],
  "avisos": [ { "tipo": "inactivos_omitidos", "mensaje": "…", "cantidad": 1 } ] } }
```
Lista vacía (con `origen: null`) si no hay DNF/adecuación o no hay brechas. 422 si falta el puesto objetivo en Carrera o Talento.

## POST /api/pdi — `desarrollo.pdi.gestionar`
Crea en **Borrador**. Cuerpo:
```json
{ "colaborador_id": 4, "tipo": "Carrera", "objetivo": "…", "puesto_objetivo_id": 18, "responsable_id": 2,
  "fecha_inicio": "2026-11-02", "fecha_fin": "2027-03-31", "origen": { "tipo": "adecuacion", "id": 7 },
  "compromisos": [ { "dimension": "Competencia", "tipo_elemento": "Competencia", "elemento_id": 12, "nivel_actual": "C", "nivel_objetivo": "B",
      "indicador": "…", "fecha_inicio": "…", "fecha_fin": "…",
      "acciones": [ { "tipo": "Curso", "descripcion": "…", "persona_cargo": null, "fecha_inicio": "…", "fecha_fin": "…", "plan_accion_id": 55 } ] } ],
  "hitos": [ { "fecha": "2026-12-04", "descripcion": "Primera revisión" } ] }
```
201 con el detalle. **422** si: falta un dato obligatorio; el colaborador no tiene vínculo vigente; un compromiso usa una dimensión no disponible (Conocimiento con la bandera en `false`); un `plan_accion_id` no es vinculable (tipo de acción distinto de Curso/Mentoring/Codesarrollo o acción fuera de la lista de vinculables); falta puesto objetivo en Carrera/Talento (o se envía en Mejora); ya existe un plan no Cerrado de ese tipo para el colaborador (error en `tipo` con el código del plan existente); hay elementos inactivos/inexistentes o repetidos; niveles iguales; el responsable no es administrador activo; fechas fuera del periodo o invertidas; vínculo inválido. Puede guardarse sin compromisos ni acciones (se exigen para acordar).

## GET /api/pdi — `desarrollo.pdi.ver`
Filtros: `tipo`, `estado`, `colaborador_id`, `departamento_id`, `responsable_id`, `fecha_desde`, `fecha_hasta` (sobre `fecha_fin`), `page`, `per_page`. Orden: más reciente primero. `{ data: [Plan(resumen)], meta: { total, page, per_page, kpis: { borrador, acordado, cerrado, hitos_vencidos } } }`.

## GET /api/pdi/{id} — `desarrollo.pdi.ver | desarrollo.pdi.ver_propio`
Detalle completo con acciones resueltas. 404 si no existe, si es de otro tenant, **si es de otra persona** (con `ver_propio`) o **si es un Borrador sin acuerdo del responsable** visto por su colaborador (FR-047).

## PUT /api/pdi/{id} — `desarrollo.pdi.gestionar`
Cuerpo igual al de creación más `version`. Solo en **Borrador**: reemplaza datos generales, compromisos, acciones e hitos en una transacción, **borra acuerdos y aceptación**, incrementa `version`. No acepta `tipo`. 422 si el plan no está Borrador o falla la validación; 409 si `version` cambió; 404 si no existe.

## POST /api/pdi/{id}/acuerdo — `desarrollo.pdi.acordar`
Cuerpo: `{ version }`. Registra el acuerdo del responsable (usuario y fecha). **El plan sigue en Borrador** y desde ese momento el colaborador lo ve. 422 si no está en Borrador, ya tiene acuerdo vigente o está incompleto; la respuesta de error lista lo que falta en `errors.completitud` (`sin_compromisos`, `compromiso_sin_acciones`, `sin_responsable`, `sin_periodo`, `responsable_sin_permiso`). 409 por versión.

## POST /api/pdi/{id}/aceptar — `desarrollo.pdi.aceptar_propio`
Cuerpo: `{ version }`. Registra la aceptación del colaborador y **pasa el plan a Acordado** en la misma transacción. 403 si el plan no es del usuario; **404 si el plan no es visible para él, es decir, si aún no existe el acuerdo del responsable (o se borró porque el administrador editó o devolvió el plan)**; 422 si no está en Borrador; 409 por versión.

## POST /api/pdi/{id}/devolver-colaborador — `desarrollo.pdi.aceptar_propio`
Cuerpo: `{ comentario (1–1000), version }`. Guarda el comentario, **borra los acuerdos** y deja el plan en Borrador (deja de ser visible para el colaborador hasta un nuevo acuerdo). 403/404/409 como `aceptar`; 422 si falta el comentario, no está en Borrador o no existe el acuerdo del responsable.

## POST /api/pdi/{id}/devolver — `desarrollo.pdi.gestionar`
Cuerpo: `{ motivo (1–1000), version }`. Solo desde **Acordado**: pasa a Borrador, borra los acuerdos, registra el motivo en el historial. 422 si falta el motivo o no está Acordado.

## PATCH /api/pdi/{id}/responsable — `desarrollo.pdi.gestionar`
Cuerpo: `{ responsable_id, version }`. Permitido en **Borrador y Acordado**. En Acordado **conserva los acuerdos** y el plan sigue Acordado; en Borrador cuenta como edición (FR-028) y **borra los acuerdos**. Incrementa `version` en ambos casos. Historial `Responsable` con anterior y nuevo. 422 si está Cerrado o el destino no es administrador activo.

## PUT /api/pdi-acciones/{id}/vinculo — `desarrollo.pdi.vincular`
Cuerpo: `{ plan_accion_id | null, version }`; nulo desvincula (el estado vuelve a registrarse a mano; al vincular se descarta el manual). Permitido en planes Borrador y Acordado. 422 si el tipo de la acción no es Curso/Mentoring/Codesarrollo, si la acción del plan no está en un plan Aprobado o Ejecutando, está cancelada o el colaborador no es participante, o si el plan está Cerrado. En Borrador vincular o desvincular cuenta como edición (FR-028) y **borra los acuerdos**; en Acordado no altera los acuerdos ni el estado del plan. 404 si no existe. Historial `Vinculo`.

## GET /api/pdi/acciones-vinculables — `desarrollo.pdi.vincular`
Query: `colaborador_id` (req.), `tipo_accion`, `q`. `{ data: [{ plan_accion_id, plan_id, plan_codigo, plan_nombre, plan_estado, nombre, tipo, fecha_inicio, fecha_fin, formador }] }` solo de planes Aprobado/Ejecutando donde el colaborador participa; sin canceladas.

## PATCH /api/pdi-acciones/{id}/avance — `desarrollo.pdi.seguimiento`
Cuerpo: `{ estado, version }`. Solo acciones **sin vínculo**, en planes **Acordado**. Historial `Avance`. 422 si la acción está vinculada o el plan no está Acordado.

## PATCH /api/pdi-hitos/{id} — `desarrollo.pdi.seguimiento`
Cuerpo: `{ estado, observaciones?, version }`. Solo en planes **Acordado**. Historial `Hito`. 422 si el plan no está Acordado.

## POST /api/pdi/{id}/cerrar — `desarrollo.pdi.cerrar`
Cuerpo: `{ resultados: [{ compromiso_id, resultado }], conclusion?, version }`. Exige todas las acciones Cumplida/Cancelada y un resultado por **cada** compromiso. Congela el estado de las acciones vinculadas, guarda resultados y calcula el del plan. 200:
```json
{ "data": { "plan": {…}, "resultado": "Parcial", "ofrecer_adecuacion": { "colaborador_id": 4, "puesto_objetivo_id": 18 } } }
```
`ofrecer_adecuacion` es nulo en Mejora. 422 si faltan resultados, hay acciones abiertas (`errors.acciones_abiertas: n`) o el plan no está Acordado.

## GET /api/pdi/{id}/historial — `desarrollo.pdi.ver | desarrollo.pdi.ver_propio`
Paginación `page`, `per_page`. `{ data: [{ id, fecha, usuario, tipo, referencia, valor_anterior, valor_nuevo, motivo }], meta }`, más reciente primero. Mismas reglas de visibilidad que el detalle. 404 si no existe.

## GET /api/pdi/mi-plan — `desarrollo.pdi.ver_propio`
Solo planes del colaborador del usuario autenticado que ya tienen el **acuerdo del responsable** (Borrador con acuerdo, Acordado, Cerrado). Más recientes primero.
```json
{ "data": { "planes": [Plan(detalle)],
  "que_sigue": [ { "tipo": "accion|hito", "fecha": "…", "descripcion": "…", "plan_codigo": "PDI-0003", "tipo_plan": "Carrera",
      "vencido": false, "actividad_id": 91|null } ] } }
```
`que_sigue`: acciones `Pendiente|EnCurso` e hitos `Pendiente` de planes **Acordado**, orden ascendente por fecha (vencidos primero). Lista vacía y `planes: []` si no tiene planes (estado vacío).

## GET /api/pdi/gantt — `desarrollo.pdi.gantt`
Filtros: `tipo`, `responsable_id`, `departamento_id`, `page`, `per_page` (por plan). Solo planes **Acordado**. `{ data: [{ plan: Plan(resumen), acciones: [{id, compromiso, descripcion, tipo, fecha_inicio, fecha_fin, estado}], hitos: […] }], meta, hoy }`.

## GET /api/pdi/sin-plan — `desarrollo.pdi.gestionar`
Filtro `departamento_id`; `page`, `per_page`. `{ data: [{ colaborador: {id, nombre, departamento}, origen: "dnf|adecuacion", tipo_sugerido, puesto_objetivo: {id, nombre}|null, brechas: n }], meta: { total } }` (regla R6).
