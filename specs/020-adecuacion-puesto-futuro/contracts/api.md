# Contrato HTTP: Adecuación a puesto futuro

Base: `/api/adecuaciones`. Todos los endpoints exigen `auth:sanctum` (401 sin sesión) y la clave de permiso indicada (403). Los controladores no consultan el rol. Respuestas en `{ "data": … }`; errores 422 como `{ "message", "errors": { campo: [mensajes] } }`; errores seguros (sin trazas ni datos de otros módulos). No existen `PUT`, `PATCH` ni `DELETE` sobre adecuaciones.

Orden de rutas: `/permisos`, `/opciones`, `/previsualizar`, `/mi-adecuacion`, `/mapa`, `/comparar`, `/parametros` van **antes** de `/{id}` (`whereNumber('id')`).

## Tipos comunes
- `preparacion`: `Lista | EnDesarrollo | NoLista | Incompleta`.
- `dimension`: `Competencia | Conocimiento | Experiencia`.
- `estado`: `ConBrecha | Cumple | Supera | SinEvaluacion`.
- `resultado`:
  ```json
  { "dimension": "Competencia", "tipo_elemento": "Competencia", "elemento_id": 12, "nombre": "Trabajo en equipo",
    "requerido_actual": "C", "requerido_objetivo": "B", "valor": "C",
    "requerido_actual_anios": null, "requerido_objetivo_anios": null, "valor_anios": null,
    "diferencia": -1, "estado": "ConBrecha", "tiempo_cierre_meses": 6, "cumplimiento": 0.67 }
  ```
  Para Experiencia: `tipo_elemento`, `elemento_id` y niveles nulos; valores en `*_anios`.
- `cumplimiento_por_dimension`: `{ "Experiencia": 0.6, "Conocimiento": null, "Competencia": 0.75 }` (nulo si la dimensión no se evaluó) y `cumplimiento_actual` / `cumplimiento_objetivo` globales (informativos, FR-024).
- `avisos`: lista de `{ "tipo": "sin_evaluacion|inactivos_omitidos|conocimientos_desactivados|experiencia_no_evaluada|mismo_puesto", "mensaje": "…", "cantidad": n }`.
- `fuentes_no_disponibles`: lista de `estructura|personal|rutas|dnf` que no respondieron.

## GET /permisos — sin permiso adicional
Devuelve las claves `desarrollo.adecuacion.*` del usuario autenticado: `{ "data": { "claves": ["…"] } }`.

## GET /opciones — `desarrollo.adecuacion.generar`
Datos del paso de Selección. Query: `q` (texto), `colaborador_id` (opcional).
- Sin `colaborador_id`: `{ "colaboradores": [{ id, codigo, nombre, departamento }], "puestos": [{ id, codigo, nombre }], "departamentos": [{ id, nombre }], "rutas": [{ id, nombre }] }` (hasta 200 colaboradores con vínculo vigente, filtrables por `q`; puestos, departamentos y rutas alimentan los filtros de las pestañas).
- Con `colaborador_id`: `{ "inscripciones": [{ inscripcion_id, ruta_id, ruta_nombre, nivel_actual: {id,nombre,orden}|null, nivel_objetivo: {id,nombre,orden,puesto_id,puesto_nombre,experiencia_minima_anios}|null }], "puestos": [{ id, codigo, nombre }], "historial": [{ id, codigo, fecha, puesto_objetivo, preparacion }] }`.

## POST /previsualizar — `desarrollo.adecuacion.generar`
Calcula sin guardar.
```json
{ "colaborador_id": 4, "inscripcion_id": 9, "puesto_objetivo_id": null, "experiencia_requerida_anios": null }
```
- Define el objetivo con `inscripcion_id` (su nivel objetivo) **o** `puesto_objetivo_id` (puesto directo; `experiencia_requerida_anios` opcional, ≥ 0, un decimal). Si vienen ambos, 422.
- 200:
  ```json
  { "data": { "colaborador": {…}, "puesto_actual": {…}|null, "puesto_objetivo": {…}, "inscripcion_id": 9,
      "tipo_cambio": "Promocion|Lateral|MismoPuesto|PuestoDirecto", "experiencia_requerida_anios": 5.0,
      "resultados": [resultado…], "cumplimiento_por_dimension": {…}, "cumplimiento_actual": 0.82, "cumplimiento_objetivo": 0.64,
      "preparacion_provisoria": "Incompleta", "umbral_meses": 12, "avisos": […], "fuentes_no_disponibles": [] } }
  ```
- 422: objetivo no definido, ambos definidos, colaborador sin vínculo vigente o inexistente, puesto inexistente/inactivo, inscripción no Activa del colaborador, o sin requisitos evaluables (`codigo: sin_requisitos_evaluables`).
- 503: una fuente imprescindible (perfil del puesto) no respondió.

## POST / — `desarrollo.adecuacion.generar`
Mismos datos que previsualizar, más:
```json
{ "puesto_objetivo_esperado_id": 2,
  "tiempos_cierre": [{ "dimension": "Competencia", "elemento_id": 12, "meses": 6 }, { "dimension": "Experiencia", "elemento_id": null, "meses": 18 }],
  "observaciones": "Texto opcional (≤ 500)" }
```
- `puesto_objetivo_esperado_id` (opcional) es el puesto que vio el administrador en el diagnóstico: si el recalculado difiere, 409 `objetivo_cambio`.
- Recalcula en el servidor (no acepta valores, diferencias, estados ni preparación del cliente), exige `meses` entero 1–60 para **cada** resultado `ConBrecha` y rechaza tiempos para resultados que no son brecha. Guarda cabecera y detalles en una transacción; asigna `ADE-NNNN`, `created_by` y `umbral_meses` vigente.
- 201: `{ "data": adecuacion }` (ver detalle).
- 422: tiempo faltante o inválido (`errors["tiempos_cierre.N.meses"]`), observaciones > 500, sin requisitos evaluables, objetivo inválido.
- 409 `objetivo_cambio`: el puesto objetivo recalculado ya no coincide con el enviado (la inscripción cambió); el cliente debe previsualizar de nuevo.
- 503: fuente imprescindible no disponible; no se guarda.

## GET / — `desarrollo.adecuacion.ver`
Historial paginado (más reciente primero). Query: `fecha_desde`, `fecha_hasta` (AAAA-MM-DD), `colaborador_id`, `puesto_objetivo_id`, `preparacion`, `page`, `per_page` (1–50, por defecto 15).
```json
{ "data": [{ "id": 1, "codigo": "ADE-0001", "fecha": "2026-10-21T10:00:00Z", "colaborador": { "id", "nombre" },
    "puesto_objetivo": { "id", "nombre" }, "preparacion": "EnDesarrollo", "brechas": 2, "es_vigente": true }],
  "meta": { "page": 1, "per_page": 15, "total": 40, "last_page": 3 } }
```

## GET /{id} — `desarrollo.adecuacion.ver`
Detalle de solo lectura: cabecera (`codigo`, `fecha`, `generada_por` {id,nombre}, `colaborador`, `puesto_actual`, `puesto_objetivo`, `inscripcion` {id,ruta_nombre}|null, `experiencia_requerida_anios`, `preparacion`, `umbral_meses`, `observaciones`, `es_vigente`), `resultados`, `cumplimiento_por_dimension`, `brechas`, `tiempo_maximo_cierre_meses`, `acciones`: `{ crear_plan: { habilitado: false, motivo }, incluir_programa: { habilitado: false, motivo } }`. 404 si no existe en el tenant.

## GET /mi-adecuacion — `desarrollo.adecuacion.ver_propio`
Sin parámetros de identidad (el colaborador sale de la sesión). Query opcional: `puesto_objetivo_id`.
```json
{ "data": { "adecuaciones": [{ "id", "fecha", "puesto_objetivo": {…}, "preparacion",
      "resultados": [resultado sin 'cumplimiento' ni puntajes], "cumplimiento_por_dimension": {…},
      "brechas": [{ "dimension", "elemento_id", "nombre", "diferencia", "tiempo_cierre_meses",
                    "curso": { "disponible": true, "ruta": "/formacion?competencia=12" } | null }] }],
    "seleccionada": 5, "aviso": "Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.",
    "fuentes_no_disponibles": [] } }
```
Una adecuación vigente por puesto objetivo propio, la más reciente primero. Lista vacía si no tiene. **Nunca** incluye puntajes de comparación, posición en rankings, `created_by` ni datos de otras personas. En experiencia `curso` es null y el cliente muestra el mensaje fijo.

## GET /mapa — `desarrollo.adecuacion.mapa`
Query: `departamento_id`, `ruta_id`, `preparacion`, `page`, `per_page`.
```json
{ "data": [{ "adecuacion_id": 1, "codigo": "ADE-0001", "colaborador": { "id", "nombre", "departamento": "…" },
    "ruta": { "id", "nombre" } | null, "puesto_actual": { "id", "nombre" } | null, "puesto_objetivo": { "id", "nombre" },
    "preparacion": "NoLista", "cumplimiento_por_dimension": {…}, "brechas": 3, "tiempo_maximo_cierre_meses": 18 }],
  "kpis": { "Lista": 1, "EnDesarrollo": 2, "NoLista": 1, "Incompleta": 1 },
  "meta": { "page", "per_page", "total", "last_page" }, "fuentes_no_disponibles": [] }
```
Una fila por `(colaborador, puesto objetivo)` con su adecuación vigente. Los `kpis` cuentan por preparación sobre el conjunto filtrado por departamento y ruta (no por preparación), para mostrar siempre la distribución.

## GET /comparar — `desarrollo.adecuacion.comparar`
Query: `puesto_objetivo_id` (obligatorio), `colaborador_ids[]` (2–20, sin repetir), `peso_competencias`, `peso_conocimientos`, `peso_experiencia` (enteros ≥ 0; si se envía uno se envían los tres y suman 100). Sin pesos usa los parámetros guardados.
```json
{ "data": { "puesto_objetivo": {…}, "pesos": { "Competencia": 40, "Conocimiento": 30, "Experiencia": 30 },
    "ranking": [{ "posicion": 1, "colaborador": {…}, "adecuacion_id": 7, "puntaje": 82.5, "cumplimiento_por_dimension": {…},
                  "preparacion": "EnDesarrollo", "brechas": 1, "puesto_actual": "…" }],
    "incompletos": [{ "colaborador": {…}, "adecuacion_id": 8 }],
    "sin_adecuacion": [{ "colaborador": {…} }] } }
```
Puntaje en % (0–100, un decimal). Dimensiones no evaluadas excluidas y pesos reescalados. Nada se guarda. 422: pesos que no suman 100, negativos, ids repetidos, menos de 2 o más de 20 candidatos, puesto inexistente.

## GET /parametros — `desarrollo.adecuacion.parametros`
`{ "data": { "pesos": { "Competencia": 40, "Conocimiento": 30, "Experiencia": 30 }, "umbral_meses": 12, "historial": [{ "campo", "valor_anterior", "valor_nuevo", "usuario": {id,nombre}, "fecha" }] } }` (historial reciente, paginable con `page`/`per_page`).

## PUT /parametros — `desarrollo.adecuacion.parametros`
```json
{ "pesos": { "Competencia": 40, "Conocimiento": 30, "Experiencia": 30 }, "umbral_meses": 12 }
```
Guarda y registra una fila de historial por campo modificado, en una transacción. 200 con los parámetros e historial actualizados. 422: pesos que no suman 100, algún peso negativo o no entero, umbral no entero o < 1.

## Códigos de error de dominio
| Código | HTTP | Cuándo |
|---|---|---|
| `objetivo_no_definido` | 422 | Falta puesto objetivo o inscripción |
| `objetivo_ambiguo` | 422 | Vienen inscripción y puesto a la vez |
| `colaborador_no_elegible` | 422 | Sin vínculo vigente o inexistente |
| `sin_requisitos_evaluables` | 422 | Nada que evaluar |
| `tiempo_cierre_requerido` | 422 | Brecha sin meses |
| `pesos_invalidos` | 422 | Suma ≠ 100 o negativos |
| `umbral_invalido` | 422 | Umbral < 1 |
| `objetivo_cambio` | 409 | La inscripción cambió |
| `fuente_no_disponible` | 503 | Estructura/Personal/Rutas no responde al generar |
