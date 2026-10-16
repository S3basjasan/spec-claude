# Contrato HTTP: `/api/dnf`

Todas las rutas exigen autenticación (**401** sin token). Las de administrador exigen la capacidad `admin` (**403**). Todo se acota al tenant del usuario; un recurso de otro tenant responde **404**. Errores de validación: **422** con `message` y `errors{campo:[…]}`. **No existen PUT, PATCH ni DELETE.**

Orden de declaración en `Routes/api.php`: `opciones`, `mi-diagnostico` y `historial`-style fijas **antes** de `/{id}`, con `whereNumber('id')`.

## Enumeraciones

`alcance`: `Individual` | `Departamento` · `prioridad`: `Critica` | `Alta` | `Media` | `Baja` · `estado`: `ConBrecha` | `Cumple` | `Supera` | `SinEvaluacion` · `nivel`: `A` | `B` | `C` | `D`.

## Objetos

**Brecha**
```json
{ "competencia_id": 4, "codigo": "COM-004", "nombre": "Análisis de datos", "tipo": "Cardinal",
  "nivel_requerido": "A", "nivel_actual": "C", "diferencia": -2, "brecha_porcentaje": 50.0,
  "prioridad": "Alta", "estado": "ConBrecha", "competencia_activa": true }
```
`nivel_actual`, `diferencia`, `brecha_porcentaje`, `prioridad` son `null` cuando `estado = SinEvaluacion`; `prioridad` es `null` salvo `ConBrecha`.

**ResultadoColaborador**
```json
{ "colaborador_id": 12, "nombre": "Diego Paredes", "puesto": {"id": 3, "codigo": "PU-014", "nombre": "Analista de datos"},
  "cantidad_brechas": 3, "prioridad_mas_alta": "Alta", "sin_evaluacion": 1, "brechas": [ /* Brecha[] ordenadas por prioridad */ ] }
```

## `GET /api/dnf/opciones` — admin
Datos de los selectores del paso 1. **200**
```json
{ "colaboradores": [{"id": 12, "nombre": "Diego Paredes", "puesto": "Analista de datos", "departamento_id": 2}],
  "departamentos": [{"id": 2, "nombre": "Tecnología", "colaboradores_evaluables": 14}] }
```
Solo colaboradores con vínculo vigente, puesto y perfil (FR-002a); `colaboradores_evaluables` incluye subunidades (FR-002).

## `POST /api/dnf` — admin
Cuerpo: `{ "alcance": "Individual", "colaborador_id": 12 }` o `{ "alcance": "Departamento", "departamento_id": 2 }`.

Validación (422): `alcance` requerido y en la enumeración; `colaborador_id` requerido si Individual y prohibido si Departamento (y viceversa); el colaborador/departamento debe existir en el tenant; el colaborador debe ser evaluable; si **ningún** colaborador es evaluable → 422 con `message: "No hay colaboradores evaluables."`. Un colaborador sin puesto/perfil/vínculo en Individual → 422 con el motivo.

**201**
```json
{ "data": { "id": 7, "codigo": "DNF-0007", "alcance": "Departamento", "fecha": "2026-10-15T14:03:00Z",
    "departamento": {"id": 2, "nombre": "Tecnología"}, "generado_por": {"id": 1, "nombre": "Marta Ríos"},
    "competencias_omitidas": 2, "plan_formacion": null,
    "resumen": { "colaboradores": 14, "cantidad_brechas": 31, "prioridad_mas_alta": "Critica", "sin_evaluacion": 4 },
    "colaboradores": [ /* ResultadoColaborador sin "brechas" en masivas; con "brechas" si Individual */ ] },
  "avisos": { "competencias_omitidas": 2, "sin_evaluacion": 4,
              "excluidos": [{"colaborador_id": 20, "nombre": "…", "motivo": "SinPuesto|SinPerfil|SinVinculoVigente"}] } }
```
La generación es transaccional: ante cualquier fallo no queda nada guardado. Cabecera `Location: /api/dnf/7`.

## `GET /api/dnf` — admin
Query: `fecha_desde`, `fecha_hasta` (YYYY-MM-DD, `desde ≤ hasta`), `colaborador_id`, `departamento_id`, `prioridad` (la **prioridad más alta** de la DNF coincide), `page`, `per_page` (1–100, def. 15). Orden: `created_at` desc. **200**
```json
{ "data": [{ "id": 7, "codigo": "DNF-0007", "fecha": "…", "alcance": "Departamento",
             "colaborador": null, "departamento": {"id": 2, "nombre": "Tecnología"},
             "cantidad_brechas": 31, "prioridad_mas_alta": "Critica", "tiene_plan": true }],
  "meta": { "page": 1, "per_page": 15, "total": 42, "last_page": 3 } }
```
Sin detalle de brechas. Para `Individual`, `colaborador` = `{id, nombre}`. El filtro `colaborador_id` incluye DNF masivas que lo evaluaron.

## `GET /api/dnf/{id}` — admin
Query opcional `colaborador_id` (filtra `colaboradores` a uno en una masiva; 404 si no pertenece). La respuesta incluye además `avisos` `{competencias_omitidas, sin_evaluacion, excluidos: []}` (los colaboradores excluidos al generar no se guardan, por eso la lista va vacía). **200** → `{ "data": {…como en el 201, con "plan_formacion": {"id": …, "generado_por": {…}, "generado_en": "…"} | null, "colaboradores": [ResultadoColaborador con "brechas"]} }` (siempre con `brechas` cuando se pidió un colaborador o la DNF es Individual; en una masiva sin filtro solo el resumen por colaborador). **404** si no existe.

## `GET /api/dnf/mi-diagnostico` — colaborador autenticado
El colaborador se deduce del token; no acepta ids. **200**
```json
{ "data": { "dnf": {"id": 7, "codigo": "DNF-0007", "fecha": "…"},
            "puesto": {"codigo": "PU-014", "nombre": "Analista de datos"},
            "resumen": { "con_brecha": 3, "cumplen": 5, "sin_evaluacion": 1 },
            "prioritarias": [ /* hasta 3 Brecha */ ],
            "brechas": [ /* todas las ConBrecha, ordenadas */ ],
            "cumplen": [{"competencia_id": 1, "nombre": "…", "nivel_actual": "B", "estado": "Cumple"}],
            "tiene_oferta": { "4": true, "9": false } } }
```
`tiene_oferta` lo resuelve `DesarrolloFacade::competenciaTieneOferta` para habilitar «Ver cursos sugeridos» (si es `false` la tarjeta muestra «Aún no hay cursos asociados en el catálogo»). Sin DNF o sin ficha asociada → **200** con `{"data": null}`; el frontend muestra el mensaje informativo (FR-026). Con DNF sin brechas → `brechas: []`, `prioritarias: []`.

## `POST /api/dnf/{id}/plan` — admin
Sin cuerpo. Solicita al puerto de Plan de Formación un borrador con las competencias `ConBrecha` de **toda** la DNF (uno solo, también en masivas) y guarda la referencia con autor y fecha. **201** `{ "data": {"plan_formacion_id": 31, "generado_por": {…}, "generado_en": "…"} }` + `Location` del plan si el módulo la ofrece.
Errores: **404** DNF inexistente; **422** «La DNF no tiene brechas» o «La DNF ya generó un plan» (la comprobación es atómica, ver R-03); **503** seguro `Plan de Formación no disponible` si el puerto no está disponible (la DNF no se marca con plan y se puede reintentar).

## Códigos y mensajes
Los mensajes son en español, sin ids internos ni trazas. Los 5xx devuelven un mensaje genérico.
