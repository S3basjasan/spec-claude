# Contrato HTTP: `/api/planes` y `/api/plan-acciones`

Todas las rutas exigen autenticación (**401** sin token). Las de administrador exigen la capacidad `admin` (**403**). Todo se acota al tenant del token; un recurso de otro tenant responde **404**. No existe ningún `DELETE`.

Orden de declaración en `Routes/api.php`: las rutas fijas (`mi-plan`, `opciones`, `colaboradores`, `cursos`) **antes** de `/{id}`, con `whereNumber('id')`.

## Enumeraciones

`estado` del plan: `Borrador` | `Revision` | `Aprobado` | `Ejecutando` | `Cerrado` · `tipo`: `Presencial` | `Virtual` | `ELearning` | `OJT` | `Codesarrollo` | `Mentoring` · `estado_accion` (calculado): `Programada` | `EnCurso` | `Finalizada` | `Cancelada` · `tipo` del historial: `Estado` | `Participante` | `Cancelacion` | `Aprobador`.

## Objetos

**Accion**
```json
{ "id": 7, "plan_id": 1, "curso_id": 3, "nombre": "Análisis con SQL", "tipo": "ELearning",
  "fecha_inicio": "2026-02-02", "fecha_fin": "2026-02-27", "duracion_horas": 24.0, "formador": "Ing. Lucía Vera",
  "capacidad_maxima": 20, "costo": 1200.00, "cancelada": false, "motivo_cancelacion": null, "estado_accion": "Finalizada",
  "competencias": [{"id": 4, "codigo": "COM-004", "nombre": "Análisis de datos", "activa": true}],
  "participantes": [{"colaborador_id": 12, "nombre": "Diego Paredes", "vinculo_vigente": true}],
  "completa": true, "faltantes": [] }
```
`faltantes` lista los campos pendientes (`tipo`, `fechas`, `duracion_horas`, `formador`, `capacidad_maxima`, `costo`, `participantes`); solo informativo.

**Plan**
```json
{ "id": 1, "codigo": "PLAN-0001", "nombre": "Plan técnico 2026", "anio": 2026, "descripcion": null, "estado": "Borrador",
  "aprobador": {"id": 5, "nombre": "Marta Ríos"}, "origen_dnf": {"id": 9, "codigo": "DNF-0009"}, "fecha_aprobacion": null,
  "cantidad_acciones": 4, "costo_total": 4800.00, "horas_totales": 96.0,
  "creado_por": {"id": 5, "nombre": "Marta Ríos"}, "creado_en": "2026-10-16T10:00:00-05:00",
  "modificado_por": {"id": 5, "nombre": "Marta Ríos"}, "modificado_en": "2026-10-16T10:30:00-05:00",
  "acciones": [ /* Accion[] */ ], "avisos_cruce": [{"colaborador_id": 12, "nombre": "Diego Paredes", "accion_id": 7, "con_accion_id": 9, "con_plan_id": 2}],
  "puede": {"editar": true, "enviar_revision": false, "aprobar": false, "devolver": false, "iniciar": false, "cerrar": false, "reasignar_aprobador": false, "cambiar_participantes": false, "cancelar_acciones": false} }
```
`origen_dnf` es `null` si no vino de una DNF. `puede` lo calcula el servidor según estado, rol y usuario (aprobar y devolver solo para el aprobador designado); la UI solo lo refleja.

**FilaPlan** (listado): `id, codigo, nombre, anio, estado, cantidad_acciones, costo_total, aprobador {id,nombre}|null, puede {…}`.

**Paginación**: `{ "data": [...], "meta": { "page": 1, "per_page": 10, "total": 37, "last_page": 4 } }`.

## `GET /api/planes/opciones` — admin
**200**
```json
{ "aprobadores": [{"id": 5, "nombre": "Marta Ríos"}], "tipos": ["Presencial","Virtual","ELearning","OJT","Codesarrollo","Mentoring"],
  "competencias": [{"id": 4, "codigo": "COM-004", "nombre": "Análisis de datos"}] }
```
Solo administradores activos y competencias **activas** (FR-008).

## `GET /api/planes/colaboradores?q=&page=&per_page=` — admin
Colaboradores con vínculo vigente (buscador del paso 3). **200** `{data: [{id, codigo, nombre}], meta}`. (el puesto no se incluye mientras Gestión de Personal no lo publique por Facade)

## `GET /api/planes/cursos?q=` — admin
Cursos `Disponible` del catálogo. **200** `{data: [{id, nombre, tipo_sugerido|null, formador|null, duracion_horas, competencia_ids: []}]}`.

## `POST /api/planes` — admin
Cuerpo:
```json
{ "nombre": "Plan técnico 2026", "anio": 2026, "descripcion": "…", "aprobador_id": 5,
  "acciones": [ { "nombre": "…", "curso_id": 3, "tipo": "Virtual", "fecha_inicio": "2026-03-02", "fecha_fin": "2026-03-13",
                  "duracion_horas": 16, "formador": "…", "capacidad_maxima": 20, "costo": 800,
                  "competencia_ids": [4], "colaborador_ids": [12, 15] } ] }
```
Valida solo lo exigido a un Borrador. **201** con el **Plan**. **422** si: falta nombre o año; el nombre ya existe en ese año (sin distinguir mayúsculas ni espacios laterales); acción sin nombre o sin competencias; competencia inexistente o inactiva; fecha fuera del año o fin anterior al inicio; horas ≤ 0, capacidad ≤ 0 o costo < 0; participantes repetidos, inexistentes o sin vínculo vigente; participantes que superan la capacidad; aprobador inexistente o no administrador.

La respuesta **201** (y la **200** del `PUT`) incluye `avisos_cruce`, calculados contra las demás acciones no canceladas del tenant (otros planes incluidos). El asistente además calcula en el cliente los cruces entre las acciones del propio plan antes de guardar.

## `GET /api/planes` — admin
Filtros: `anio`, `estado`, `q` (nombre), `page`, `per_page`. **200** paginado de **FilaPlan** (sin acciones).

## `GET /api/planes/{id}` — admin
**200** Plan completo (acciones, participantes, totales, `avisos_cruce`, `puede`). **404** si no existe o es de otro tenant.

## `PUT /api/planes/{id}` — admin, solo Borrador
Reemplaza datos generales, aprobador y acciones (con competencias y participantes) en una transacción; las acciones ausentes se eliminan (con sus competencias y participantes). Cuerpo igual al de `POST`, con `id` opcional en cada acción. **200** Plan. **422** si el plan no está en Borrador (`code: plan_no_editable`) o falla la validación. **404**.

## `PATCH /api/planes/{id}/estado` — admin
Cuerpo `{ "estado": "Revision" | "Aprobado" | "Borrador" | "Ejecutando" | "Cerrado", "motivo"?: string }`; la transición se infiere del estado actual.
- **200** Plan con su nuevo estado; registra historial; al aprobar fija `fecha_aprobacion`.
- **403** si Aprobar o Devolver lo intenta un usuario distinto del aprobador designado.
- **422** (`code`): `transicion_invalida`; `plan_incompleto` (con `faltantes: [{accion_id|null, campo}]`, al enviar a revisión); `motivo_requerido` (al devolver); `acciones_sin_finalizar` (al cerrar); `plan_cambio_de_estado` (carrera).
- **404**.

## `PATCH /api/planes/{id}/aprobador` — admin, solo Revisión
Cuerpo `{ "aprobador_id": 6 }`. Cualquier Administrador (FR-016a). **200** Plan; registra historial `Aprobador`. **422**: el plan no está en Revisión; aprobador inexistente o no administrador activo. **404**.

## `GET /api/planes/{id}/historial` — admin
`page`, `per_page`. **200**
```json
{ "data": [ { "id": 1, "tipo": "Estado", "accion_id": null, "accion_nombre": null, "valor_anterior": "Borrador", "valor_nuevo": "Revision",
              "motivo": null, "usuario": {"id": 5, "nombre": "Marta Ríos"}, "fecha": "2026-10-16T10:40:00-05:00" } ], "meta": {…} }
```
Más reciente primero. **404** si el plan no existe.

## `GET /api/plan-acciones` — admin
Alimenta Lista y Calendario. Filtros: `estado` (del plan), `tipo`, `competencia_id`, `fecha_desde`, `fecha_hasta` (acciones que **se solapan** con el rango), `anio` (por defecto el año actual si no se indica `plan_id`, `fecha_desde` ni `fecha_hasta`), `plan_id`, `page`, `per_page` (máx. 200). Ordena por `fecha_inicio`. **200**
```json
{ "data": [ { "id": 7, "plan": {"id": 1, "codigo": "PLAN-0001", "nombre": "Plan técnico 2026", "estado": "Aprobado"},
              "nombre": "…", "competencias": [{"id": 4, "nombre": "Análisis de datos"}], "tipo": "ELearning",
              "fecha_inicio": "2026-02-02", "fecha_fin": "2026-02-27", "formador": "…", "inscritos": 18, "capacidad_maxima": 20,
              "costo": 1200.00, "cancelada": false, "estado_accion": "Finalizada" } ], "meta": {…} }
```

## `PUT /api/plan-acciones/{id}/participantes` — admin
Cuerpo `{ "colaborador_ids": [12, 15] }`. Permitido en planes Borrador, Aprobado y Ejecutando. Reemplaza los participantes en una transacción; desde la aprobación registra en el historial un renglón `Participante` por cada agregado o quitado. **200** `{ "data": { "accion": Accion, "avisos_cruce": [...] } }`. **422** (`code`): `cupo_excedido`; `participantes_repetidos`; `colaborador_invalido` (inexistente o sin vínculo vigente **al agregarlo**; los ya inscritos se conservan); `plan_no_editable` (Revisión o Cerrado); `accion_cancelada`. **404**.

## `PATCH /api/plan-acciones/{id}/cancelar` — admin
Cuerpo `{ "motivo": string }`. Solo en planes Aprobado o Ejecutando. **200** `{ "data": Accion }` cancelada; registra historial `Cancelacion`. **422**: `motivo_requerido`; `accion_ya_cancelada`; `plan_no_editable`. **404**.

## `GET /api/planes/mi-plan` — colaborador (cualquier usuario autenticado con ficha)
Solo datos del usuario del token (id → colaborador por `CuentaColaboradorInterface`). **200** `{ "data": { … } }` con:
```json
{ "porcentaje_completado": 25, "acciones_asignadas": 4, "acciones_completadas": 1,
  "acciones": [ { "id": 7, "plan": {"id": 1, "nombre": "Plan técnico 2026", "anio": 2026}, "nombre": "…", "competencias": [{"id": 4, "nombre": "…"}],
                  "tipo": "ELearning", "fecha_inicio": "2026-02-02", "fecha_fin": "2026-02-27", "formador": "…", "duracion_horas": 24.0,
                  "estado_accion": "Finalizada", "motivo_cancelacion": null, "completada": true } ],
  "proximas": [ /* hasta 3 acciones no canceladas con inicio ≥ hoy, las más cercanas */ ] }
```
`acciones` en orden cronológico, solo de planes Aprobado, Ejecutando o Cerrado; incluye canceladas con su motivo, que no cuentan en el porcentaje ni en `proximas`. Sin acciones: `acciones: []`, `proximas: []`, `porcentaje_completado: 0`. Si el usuario no tiene ficha: respuesta vacía igual. Nunca devuelve datos de otro colaborador.

## Errores

Formato `{ "message": string, "code"?: string, "errors"?: {campo: [mensajes]} }`. Los mensajes no revelan datos de otros tenants ni detalles internos.
