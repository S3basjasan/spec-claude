# Contrato HTTP: Actividades formativas

Prefijo `/api/actividades` (por contrato del insumo; ver Complexity Tracking del plan). Todas las rutas usan `auth:sanctum` (401 sin
sesión). Todas salvo `GET /mis-actividades` exigen el middleware `admin` (403 para el Colaborador). No existe `DELETE`. Las rutas
fijas (`acciones-disponibles`, `calificaciones`, `departamentos`, `mis-actividades`) se declaran antes de cualquier `/{id}`.
Errores de validación: 422 con `{ message, errors: { campo: [mensajes] } }`; errores de regla de negocio: 422 con
`{ message, code }` (y `errors` cuando pertenecen a un campo, como en el Plan). Las respuestas de éxito de un solo objeto van en `{ data }`; las listas en `{ data, meta }`. Mensajes en español y sin datos sensibles.

Tipos de acción en la API: `Presencial`, `Virtual`, `ELearning`, `OJT`, `Codesarrollo`, `Mentoring`. Estados de actividad:
`Programada`, `EnCurso`, `Finalizada`, `Cancelada`. Asistencia: `Pendiente`, `Presente`, `Ausente`, `Justificado`. Fechas `YYYY-MM-DD`.

## Resumen

| Operación | Método y ruta | Rol |
|---|---|---|
| Acciones disponibles | `GET /api/actividades/acciones-disponibles` | admin |
| Departamentos activos | `GET /api/actividades/departamentos` | admin |
| Opciones de filtros | `GET /api/actividades/opciones` | admin |
| Listar | `GET /api/actividades` | admin |
| Crear | `POST /api/actividades` | admin |
| Consultar | `GET /api/actividades/{id}` | admin |
| Actualizar | `PUT /api/actividades/{id}` | admin |
| Registrar asistencia | `PUT /api/actividades/{id}/asistencia` | admin |
| Historial | `GET /api/actividades/{id}/historial` | admin |
| Calificaciones | `GET /api/actividades/calificaciones` | admin |
| Mis actividades | `GET /api/actividades/mis-actividades` | colaborador (usuario del token) |

## Objetos comunes

**Actividad (resumen)**
```json
{
  "id": 7, "codigo": "ACT-0007",
  "accion_id": 31, "nombre": "Mentoring para nuevos líderes", "tipo": "Mentoring",
  "plan": { "id": 4, "codigo": "PLAN-0004", "nombre": "Plan de desarrollo 2026", "estado": "Ejecutando" },
  "fecha_inicio": "2026-07-06", "fecha_fin": "2026-10-30",
  "lugar_enlace": "Oficina del mentor, piso 5", "formador": "Dra. Paula Ortiz",
  "departamento": { "id": 3, "nombre": "Talento Humano" },
  "capacidad": 8, "inscritos": 6,
  "estado": "EnCurso", "asistencia_pendiente": false
}
```
`departamento` es `null` si no se asignó. `asistencia_pendiente` es `true` solo si `estado = Finalizada` y hay inscritos sin registro.

## `GET /api/actividades/acciones-disponibles`
Alimenta el selector. Acciones no canceladas de planes `Ejecutando` sin actividad.
Query: `plan_id?`, `q?` (nombre), `page?`, `per_page?` (máx. 50).
200: `{ data: [{ accion_id, nombre, tipo, plan: {id, codigo, nombre}, competencias: [{id, nombre}], fecha_inicio, fecha_fin, formador, capacidad, inscritos }], meta: {page, per_page, total, last_page} }`.

## `GET /api/actividades/departamentos`
200: `{ data: [{ id, nombre }] }` — unidades activas de Estructura (para el selector y el filtro).

## `GET /api/actividades/opciones`
Opciones de los filtros y del formulario. 200: `{ data: { departamentos: [{id, nombre}], competencias: [{id, nombre}], planes: [{id, codigo, nombre, anio, estado}] } }` — departamentos activos de Estructura, competencias del diccionario y planes Ejecutando o Cerrado del tenant (los que pueden tener actividades).

## `GET /api/actividades`
Query: `q?`, `tipo?`, `estado?`, `departamento_id?`, `competencia_id?`, `plan_id?`, `fecha_desde?`, `fecha_hasta?`, `page?`, `per_page?`.
Sin `fecha_desde` ni `fecha_hasta` se aplica el **año actual** (acciones cuyas fechas se superponen con el año). Orden: fecha de inicio descendente, luego código.
200: `{ data: [Actividad resumen], meta }`. Un filtro inválido da 422.

## `POST /api/actividades`
Body: `{ "plan_accion_id": 31, "lugar_enlace": "https://meet.ejemplo/abc", "departamento_id": 3 }` (`departamento_id` opcional).
- 201: `Actividad detalle` (ver abajo).
- 404: la acción no existe.
- 422: faltan campos; `lugar_enlace` vacío o, para `Virtual`/`ELearning`, no es una URL `http(s)` válida; `departamento_id` inexistente o inactivo; `code: "accion_no_elegible"` si la acción está cancelada, su plan no está `Ejecutando` o ya tiene actividad.

## `GET /api/actividades/{id}`
200: `Actividad detalle`:
```json
{
  "...": "campos de Actividad resumen",
  "competencias": [{ "id": 2, "nombre": "Liderazgo" }],
  "horas": 12.0,
  "version": 3,
  "puede_editar": true, "puede_registrar_asistencia": true, "motivo_bloqueo": null,
  "resumen_asistencia": { "presentes": 3, "ausentes": 1, "justificados": 1, "pendientes": 1, "calificados": 2, "promedio": 4.5 },
  "participantes": [
    { "colaborador_id": 12, "nombre": "Ana Pérez", "asistencia": "Presente", "calificacion": 5, "observaciones": "Participó activamente" },
    { "colaborador_id": 15, "nombre": "Luis Mora", "asistencia": "Pendiente", "calificacion": null, "observaciones": null }
  ],
  "auditoria": { "creado_por": "Marta Ríos", "creado_en": "2026-10-05T14:20:00Z", "modificado_por": "Marta Ríos", "modificado_en": "2026-10-06T09:00:00Z" }
}
```
`motivo_bloqueo` ∈ `null | "actividad_programada" | "actividad_cancelada" | "plan_cerrado"`. `participantes` son los inscritos **actuales** (aclaración 2). 404 si no existe.

## `PUT /api/actividades/{id}`
Body: `{ "lugar_enlace": "...", "departamento_id": 3 | null }` (reemplaza ambos). 200: `Actividad detalle`. 404. 422: validaciones de lugar y departamento; `code: "plan_cerrado"` si el plan está Cerrado. No acepta otros campos (se ignoran).

## `PUT /api/actividades/{id}/asistencia`
Body:
```json
{
  "version": 3,
  "registros": [
    { "colaborador_id": 12, "asistencia": "Presente", "calificacion": 4, "observaciones": "..." },
    { "colaborador_id": 15, "asistencia": "Ausente" },
    { "colaborador_id": 18, "asistencia": "Pendiente" }
  ]
}
```
Guarda **todo o nada** en una transacción, escribe el historial de cada cambio de asistencia o calificación y sube `version`.
200: `Actividad detalle` actualizado (con la nueva `version`). 404. 409 `{ message, code: "version_desactualizada" }` si `version` no coincide.
422: `code: "asistencia_no_registrable"` (actividad Programada o Cancelada); `code: "plan_cerrado"`; colaborador no inscrito o repetido;
`asistencia` inválida; `calificacion` no entera, fuera de 1–5 o con asistencia distinta de `Presente`; `observaciones` (> 1 000 caracteres) o `calificacion` con `Pendiente`; `registros` vacío.

## `GET /api/actividades/{id}/historial`
Query: `page?`, `per_page?`. 200: `{ data: [{ id, fecha, usuario, colaborador_id, participante, asistencia_anterior, asistencia_nueva, calificacion_anterior, calificacion_nueva }], meta }`, del más reciente al más antiguo. `null` = Pendiente / sin calificación. 404.

## `GET /api/actividades/calificaciones`
Query: `agrupar` (`actividad` | `participante`, obligatorio), `fecha_desde?`, `fecha_hasta?`, `tipo?`, `departamento_id?`, `plan_id?`, `colaborador_id?` (solo con `agrupar=participante`), `page?`, `per_page?`.
- `agrupar=actividad`: `{ data: [{ id, codigo, nombre, tipo, fecha_inicio, fecha_fin, calificados, promedio }], meta }`; `promedio` es `null` sin notas.
- `agrupar=participante`: `{ data: [{ colaborador_id, nombre, actividades_calificadas, promedio }], meta }`; **solo** colaboradores con al menos una calificación en el filtro.
- `agrupar=participante&colaborador_id=N`: `{ data: { colaborador_id, nombre, promedio, actividades: [{ id, codigo, nombre, tipo, fecha_inicio, asistencia, calificacion }] } }`.
Promedios con un decimal. 422 si falta o es inválido `agrupar`.

## `GET /api/actividades/mis-actividades`
Solo el usuario del token; sin ficha de colaborador → respuesta vacía. Planes `Ejecutando` o `Cerrado`; solo acciones con actividad. **Nunca incluye `observaciones`.**
```json
{
  "resumen": { "inscritas": 5, "finalizadas": 2, "asistencias_registradas": 2, "presentes": 2, "asistencia_porcentaje": 100, "calificadas": 2, "calificacion_promedio": 4.5 },
  "proximas":  [ { "id": 5, "codigo": "ACT-0005", "nombre": "...", "tipo": "Virtual", "fecha_inicio": "...", "fecha_fin": "...", "lugar_enlace": "...", "formador": "...", "estado": "EnCurso", "asistencia": "Presente", "calificacion": null } ],
  "historial": [ { "...": "mismos campos", "estado": "Finalizada", "asistencia": "Pendiente", "calificacion": null } ]
}
```
`proximas` = Programada o En curso por fecha de inicio ascendente; `historial` = Finalizada o Cancelada de la más reciente a la más antigua. `asistencia_porcentaje` (Presentes sobre registros de asistencia) y `calificacion_promedio` son `null` sin datos; `inscritas` no cuenta las canceladas.

## Códigos de estado
200 consulta/actualización; 201 creación; 401 sin sesión; 403 sin rol; 404 no existe; 409 versión desactualizada; 422 validación o regla de negocio.
