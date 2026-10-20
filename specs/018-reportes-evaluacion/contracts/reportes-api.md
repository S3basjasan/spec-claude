# Contrato HTTP: Reportes de evaluación de resultados

Prefijo `/api/reportes` (desviación registrada de la Constitución I, igual que `/api/planes` y `/api/actividades`). Todos los endpoints exigen `auth:sanctum` (401 sin sesión). Los del administrador exigen además `admin` (403). Éxito: `{ "data": … }`; errores de negocio: `{ "message": "…", "code": "…", "errors"?: {campo: [mensajes]} }`. No existe PUT ni DELETE sobre encuestas ni seguimientos.

## Filtros comunes

Query: `fecha_desde`, `fecha_hasta` (AAAA-MM-DD; sin fechas = año actual), `departamento_id`, `tipo` (`Presencial|Virtual|ELearning|OJT|Codesarrollo|Mentoring|Microlearning`), `plan_id`. 422 `periodo_invalido` si `fecha_desde > fecha_hasta` o el formato no es válido.

## Administrador

| Operación | Endpoint | Respuesta |
|---|---|---|
| Opciones | `GET /api/reportes/opciones` | 200 `{data: {departamentos: [{id, nombre}], planes: [{id, codigo, nombre, anio, estado}]}}`: departamentos activos y planes Ejecutando o Cerrado, para los filtros. |
| Dashboard | `GET /api/reportes/dashboard` + filtros | 200 `data` con el periodo, el periodo anterior, los cinco indicadores, los de gestión y los datos de gráficos (abajo). |
| Detalle | `GET /api/reportes/detalle` + filtros, `q`, `page`, `per_page` | 200 `{data: [Fila], meta: {total, page, per_page, last_page, fuentes_no_disponibles}}` |
| Comentarios | `GET /api/reportes/actividades/{actividadId}/satisfaccion` + `page`, `per_page` | 200 `{data: {promedio, respuestas, comentarios: [{calificacion, comentario, fecha}]}, meta}` donde `fecha` es solo el día (`AAAA-MM-DD`, sin hora) para no permitir identificar al autor; 404 `actividad_no_encontrada` |
| Seguimiento | `GET /api/reportes/seguimiento` + filtros, `page`, `per_page` | 200 `{data: [FilaSeguimiento], meta}` (con `fuentes_no_disponibles` en `meta`) |
| Listar impactos | `GET /api/reportes/impactos` + `plan_id`, `evaluado=con\|sin`, `page`, `per_page` | 200 `{data: [FilaImpacto], meta}` |
| Registrar impacto | `PUT /api/reportes/impactos/{planAccionId}` | 200 `{data: Impacto}`; 404 `accion_no_encontrada`; 422 `validacion` o `accion_no_finalizada` |
| Exportar | `GET /api/reportes/exportar?formato=xlsx\|pdf` + filtros | 200 archivo (`Content-Type` y `Content-Disposition`); 422 `formato_invalido` |

### Dashboard

```json
{
  "data": {
    "periodo": {"desde": "2026-01-01", "hasta": "2026-12-31"},
    "periodo_anterior": {"desde": "2025-01-01", "hasta": "2025-12-31"},
    "fuentes_no_disponibles": [],
    "indicadores": {
      "plan_cumplido": {"valor": 62.5, "anterior": 50.0, "diferencia": 12.5, "variacion_pct": 25.0, "acciones_finalizadas": 5, "acciones_validas": 8},
      "total_horas": {"valor": 842.0, "anterior": null, "diferencia": null, "variacion_pct": null, "horas_actividades": 790.0, "horas_microlearning": 52.0},
      "satisfaccion": {"valor": 4.3, "anterior": 4.1, "diferencia": 0.2, "variacion_pct": 4.9, "respuestas": 27},
      "brechas_cerradas": {"valor": 11, "detectadas": 18, "anterior": 6, "diferencia": 5, "variacion_pct": 83.3},
      "roi_estimado": {"valor": 142.0, "anterior": null, "diferencia": null, "variacion_pct": null, "acciones_evaluadas": 3}
    },
    "gestion": {
      "inversion_por_colaborador": {"valor": 310.5, "anterior": 280.0, "diferencia": 30.5, "variacion_pct": 10.9},
      "horas_por_colaborador": {"valor": 31.2, "anterior": null, "diferencia": null, "variacion_pct": null},
      "costo_por_hora": {"valor": 9.9, "anterior": null, "diferencia": null, "variacion_pct": null}
    },
    "graficos": {
      "horas_por_departamento": {"disponible": true, "datos": [{"departamento_id": 4, "departamento": "Tecnología", "horas_actividades": 300.0, "horas_microlearning": 20.0}]},
      "evolucion_brechas": {"disponible": true, "datos": [{"mes": "2026-01", "activas": 14, "cerradas": 1}]},
      "distribucion_por_tipo": {"disponible": true, "datos": [{"tipo": "Presencial", "horas": 240.0}, {"tipo": "Microlearning", "horas": 52.0}]}
    }
  }
}
```

Un indicador sin datos devuelve `valor: null`; la comparación sin período anterior devuelve `anterior`, `diferencia` y `variacion_pct` en `null`. `variacion_pct` también es `null` si `anterior` vale 0. Con el filtro `tipo=Microlearning` solo `total_horas`, `horas_por_departamento` y `distribucion_por_tipo` traen valores. `brechas_cerradas` respeta solo `fecha_*` y `departamento_id`; `valor` (cerradas por fecha de cierre) puede ser mayor que `detectadas` (primera aparición dentro del período) y no debe presentarse como fracción.

`fuentes_no_disponibles` lista las fuentes que no respondieron (`plan`, `actividades`, `microlearning`, `dnf`, `personal`). Los indicadores y gráficos que dependen de ellas devuelven `valor: null` y `disponible: false`; el resto se calcula con normalidad. En `horas_por_departamento`, los colaboradores sin departamento actual llegan como `{"departamento_id": null, "departamento": "Sin departamento", …}`.

### Fila de detalle

`{actividad_id, codigo, nombre, tipo, fecha_inicio, fecha_fin, estado, departamento_id, departamento, asistentes, inscritos, horas_persona, costo, calificacion_promedio, satisfaccion_promedio, respuestas_satisfaccion}`. `costo`, `horas_persona`, `asistentes` y promedios se recalculan con los participantes del departamento filtrado; `calificacion_promedio` y `satisfaccion_promedio` son `null` sin datos.

### Fila de seguimiento

`{actividad_id, codigo, nombre, fecha_fin, elegibles, hitos: [{hito: 30|60|90, vencimiento, respuestas, tasa_respuesta, promedio_aplicacion, promedio_apoyo}], alerta_reingreso}`. Un hito sin respuestas devuelve los promedios en `null`; `tasa_respuesta` es `null` si no hay elegibles.

### Impacto

`FilaImpacto`: `{plan_accion_id, plan_id, plan_codigo, accion_nombre, tipo, fecha_fin, costo, evaluada, roi, impacto: Impacto|null}`.
`Impacto`: `{plan_accion_id, indicador, unidad, valor_antes, valor_despues, beneficio_estimado, justificacion, roi, creado_por, creado_en, modificado_por, modificado_en}`; `roi` es `null` si el costo es 0.

Cuerpo de `PUT`: `{indicador, unidad, valor_antes, valor_despues, beneficio_estimado, justificacion}`, todos obligatorios, `beneficio_estimado ≥ 0`.

### Exportación

Excel: hojas `Indicadores`, `Detalle`, `Impacto 30-60-90`, `Evaluación de impacto`. PDF: indicadores y tablas, sin gráficos. Ambos respetan los filtros; un período sin datos produce un archivo válido con indicadores en blanco y tablas vacías.

## Colaborador

| Operación | Endpoint | Respuesta |
|---|---|---|
| Mi progreso | `GET /api/reportes/mi-progreso` | 200 `{data: MiProgreso}` (vacío, no error, si no tiene datos) |
| Responder encuesta | `POST /api/reportes/encuestas` body `{actividad_id, calificacion, comentario?}` | 201 `{data: {actividad_id, calificacion, comentario, fecha}}`; 404 `actividad_no_encontrada`; 422 `validacion`, `actividad_no_finalizada`, `sin_asistencia`, `ventana_cerrada`, `ya_respondida` |
| Responder seguimiento | `POST /api/reportes/seguimientos` body `{actividad_id, hito, aplicacion, apoyo, comentario?}` | 201 `{data: {actividad_id, hito, aplicacion, apoyo, comentario, fecha}}`; 404 `actividad_no_encontrada`; 422 `validacion`, `hito_invalido`, `hito_no_vencido`, `ventana_cerrada`, `sin_asistencia`, `ya_respondida` |

Un usuario sin ficha de colaborador (por ejemplo, un administrador sin ficha) recibe en `mi-progreso` una respuesta vacía y en `encuestas` y `seguimientos` un 404 `actividad_no_encontrada`.

Los certificados se descargan con el endpoint de Micro-Learning (`CertificadoMicroController`); no hay endpoint propio.

### MiProgreso

```json
{
  "data": {
    "resumen": {"formaciones_completadas": 7, "competencias_adquiridas": 3, "horas_acumuladas": 96.5, "certificados": 4},
    "evolucion_competencias": [{"competencia_id": 8, "competencia": "Liderazgo", "nivel_primera": "D", "nivel_actual": "B", "nivel_requerido": "B", "solo_actual": false}],
    "certificados": [{"microcontenido_id": 31, "titulo": "…", "fecha": "2026-08-12", "descarga": "/api/…"}],
    "proximas_acciones": [{"accion_id": 12, "tipo": "Presencial", "nombre": "…", "fecha_inicio": "2026-11-04", "competencias": ["Comunicación"]}],
    "pendientes": {
      "encuestas": [{"actividad_id": 5, "codigo": "ACT-0005", "nombre": "…", "fecha_fin": "2026-10-10", "vence": "2026-11-09"}],
      "seguimientos": [{"actividad_id": 4, "codigo": "ACT-0004", "nombre": "…", "hito": 30, "vence": "2026-10-24"}]
    }
  }
}
```

`solo_actual: true` cuando la competencia se evaluó en una sola DNF (se omite `nivel_primera`). `vence` es el último día para responder. Sin datos, todas las listas vienen vacías y los contadores en 0.

## Seguridad

- El colaborador solo opera sobre el `colaborador_id` de su cuenta (`CuentaColaboradorInterface`); nunca recibe datos de otros ni las respuestas de administrador (403 en rutas de administrador).
- La respuesta de comentarios no incluye `colaborador_id`, nombre ni ninguna clave que permita identificar al autor.
- Errores sin trazas ni detalles internos.
