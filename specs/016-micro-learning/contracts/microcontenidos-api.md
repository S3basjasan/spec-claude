# Contrato HTTP: `/api/microcontenidos`

Todos los endpoints requieren `auth:sanctum` (401 sin sesión). Los marcados **Admin** requieren además la capacidad `admin`
(403). Los marcados **Colab** exigen un usuario no administrador con ficha de colaborador (403 en otro caso) y operan siempre
sobre el usuario del token; un id inexistente o ajeno responde 404. Las rutas fijas (`opciones`, `mi-aprendizaje`, `intentos/...`) se declaran
antes de cualquier `/{id}`. No hay `PUT` de estado ni `DELETE` de progreso ni de historial.

Los errores 422 usan `{ "message": string, "errors": { campo: [mensaje] } }`; los de regla de negocio añaden `"code"`.

## Resumen

| Operación | Método y ruta | Rol |
|---|---|---|
| Crear | `POST /api/microcontenidos` | Admin |
| Listar | `GET /api/microcontenidos` | Todos (vista según rol) |
| Opciones del formulario | `GET /api/microcontenidos/opciones` | Admin |
| Mi aprendizaje | `GET /api/microcontenidos/mi-aprendizaje` | Colab |
| Consultar | `GET /api/microcontenidos/{id}` | Todos (vista según rol) |
| Actualizar | `PUT /api/microcontenidos/{id}` | Admin |
| Cambiar estado | `PATCH /api/microcontenidos/{id}/estado` | Admin |
| Eliminar | `DELETE /api/microcontenidos/{id}` | Admin |
| Historial | `GET /api/microcontenidos/{id}/historial` | Admin |
| Guardar avance | `PUT /api/microcontenidos/{id}/progreso` | Colab |
| Completar | `POST /api/microcontenidos/{id}/completar` | Colab |
| Iniciar intento | `POST /api/microcontenidos/{id}/intentos` | Colab |
| Responder | `POST /api/microcontenidos/intentos/{intentoId}/respuestas` | Colab |
| Certificado | `GET /api/microcontenidos/{id}/certificado` | Colab |

## Objetos

**Micro-contenido (lista)** — `FilaMicro`:

```json
{ "id": 7, "codigo": "MICRO-0007", "titulo": "Introducción a SQL", "tipo": "Video", "estado": "Publicado",
  "miniatura_url": null, "duracion_minutos": 12,
  "competencias": [{ "id": 3, "codigo": "CE-003", "nombre": "Análisis de datos", "estado": "Activo" }],
  "completaciones": 4,
  "apto_conocimientos": true }
```

`completaciones` solo se envía al Administrador. Para el colaborador, `GET /` solo devuelve Publicados y añade `progreso`
(`null` o `{ estado, posicion_segundos }`).

**Micro-contenido (detalle)** — `Micro`: lo anterior más `url`, `created_by`/`created_at`, `updated_by`/`updated_at` (nombres de
usuario, solo Admin), `puede_eliminarse` (booleano, solo Admin: no tiene progreso) y `tiene_intentos` (booleano, solo Admin: hay
intentos Finalizados). Si `tipo = "Quiz"`: `preguntas: [{ id, enunciado, orden, opciones: [{ id, texto, orden, es_correcta? }] }]`;
`es_correcta` **solo** lo recibe el Administrador. El colaborador recibe además `progreso`
(`{ estado, posicion_segundos, fecha_inicio, fecha_completado, ultimo_puntaje: { correctas, total } | null }`).

**Intento** — `{ id, estado, total_preguntas, respondidas: [{ pregunta_id, opcion_id, es_correcta }], preguntas: [...sin es_correcta] }`.

## Crear — `POST /api/microcontenidos` (Admin)

Body: `titulo` (1–120), `tipo` (`Video|PDF|Articulo|Quiz`), `duracion_minutos` (entero ≥ 1), `url?` (obligatoria al publicar si no
es Quiz; formato `http(s)`), `miniatura_url?`, `competencia_ids?` (array de ids de competencias **activas**, sin repetidos) y, si
`tipo = "Quiz"`, `preguntas?` (≤ 20) con `enunciado` y `opciones` (2–5, cada una `{ texto, es_correcta }`, exactamente una
correcta). El orden es el del array.

- `201` con `Micro` en estado `Borrador`, código asignado y primera entrada del historial (`null → Borrador`).
- `422` si falla la validación, si `url` se envía en un Quiz, si hay preguntas en un tipo distinto de Quiz, si una pregunta no
  tiene exactamente una opción correcta, o si alguna competencia no existe o está inactiva.

## Listar — `GET /api/microcontenidos`

Query: `tipo`, `estado` (`Borrador|Publicado|Archivado|Activos|Todos`, solo Admin; por defecto `Activos` = Borrador y Publicado),
`competencia_id`, `q` (título; sin tildes ni mayúsculas), `page`, `per_page` (por defecto 12, máximo 50).

- `200` `{ data: [FilaMicro], meta: { page, per_page, total } }` ordenado por `codigo` descendente.
- El colaborador ignora `estado`: solo ve Publicados.

## Opciones — `GET /api/microcontenidos/opciones` (Admin)

Competencias para el formulario y el filtro, obtenidas de Estructura Empresarial por su puerto.

- `200` `{ competencias_activas: [{ id, codigo, nombre }], competencias: [{ id, codigo, nombre, estado }] }` (la segunda incluye inactivas, para filtrar contenidos que aún las referencian).

## Mi aprendizaje — `GET /api/microcontenidos/mi-aprendizaje` (Colab)

- `200`:

```json
{ "resumen": { "completados": 2, "en_progreso": 1, "horas_acumuladas": 1.0, "certificados": 2 },
  "asignados": [ { "...FilaMicro": "", "progreso": null, "motivo": "brecha|iniciado" } ],
  "completados": [ { "...FilaMicro": "", "completado_en": "2026-10-16T10:00:00Z", "ultimo_puntaje": { "correctas": 4, "total": 5 } } ] }
```

- `asignados`: Publicados vinculados a una competencia con brecha activa y sin completar, más los iniciados sin completar y
  Publicados (`motivo`). `completados` incluye los Archivados que el colaborador ya completó.
- Sin brechas, sin DNF o sin contenidos: arreglos vacíos (nunca error). Si falla la lectura de brechas, `asignados` conserva los
  iniciados y la respuesta incluye `"avisos": ["brechas_no_disponibles"]`.

## Consultar — `GET /api/microcontenidos/{id}`

- `200` `Micro`. Admin: cualquier estado y con `es_correcta`. Colab: solo Publicado, o Archivado que ya completó, sin `es_correcta`.
- `404` si no existe o no es accesible para el rol.

## Actualizar — `PUT /api/microcontenidos/{id}` (Admin)

Reemplaza `titulo`, `url`, `miniatura_url`, `duracion_minutos`, `competencia_ids` y, en Quiz, `preguntas`, en una transacción. El
tipo, el código y el estado **no** se editan aquí (`tipo` distinto del guardado → `422`).

- `200` `Micro`. Si cambian las competencias, el repositorio del Catálogo registra el historial del Catálogo y sube la `version`
  de cada configuración afectada. Si se reemplazan las preguntas, se descartan los intentos `EnCurso`.
- `422` por validación; por intentar cambiar las preguntas con intentos Finalizados (`code: "quiz_con_intentos"`, indica archivar y
  crear uno nuevo); por competencia inactiva nueva. En estado Publicado se exige que siga cumpliendo los requisitos de publicación.
- `404` si no existe.

## Cambiar estado — `PATCH /api/microcontenidos/{id}/estado` (Admin)

Body: `{ "estado": "Publicado" | "Archivado" }`. Transiciones válidas: Borrador→Publicado, Publicado→Archivado, Archivado→Publicado.

- `200` `Micro` y una entrada en el historial (usuario, fecha, anterior, nuevo).
- `422` si la transición no es válida, si el estado ya es el pedido (`code: "transicion_invalida"`) o si faltan requisitos para
  publicar: `{ "code": "faltan_requisitos", "faltantes": ["url", "competencias", "preguntas", "titulo", "duracion"] }`.
- `404` si no existe.

## Eliminar — `DELETE /api/microcontenidos/{id}` (Admin)

- `204` si ningún colaborador lo ha iniciado; borra preguntas, opciones, historial y vínculos (anotando `Quitado` en el historial
  del Catálogo) en una transacción.
- `422` `code: "con_progreso"` con la indicación de archivarlo.
- `404` si no existe.

## Historial — `GET /api/microcontenidos/{id}/historial` (Admin)

Query `page`, `per_page` (20). `200` `{ data: [{ id, estado_anterior, estado_nuevo, usuario, fecha }], meta }`, más reciente primero.
Los cambios de competencias **no** aparecen aquí: están en el historial del Catálogo Formativo.

## Guardar avance — `PUT /api/microcontenidos/{id}/progreso` (Colab)

Body: `{ "posicion_segundos"?: entero ≥ 0 }` (solo Video). Crea o actualiza el progreso `EnProgreso`.

- `200` `{ estado, posicion_segundos, fecha_inicio }`.
- `422` si el contenido no está Publicado, si es Quiz o si ya está Completado. `404` si no existe o no es accesible.

## Completar — `POST /api/microcontenidos/{id}/completar` (Colab)

- `200` `{ estado: "Completado", fecha_completado }` (crea el progreso si no existía).
- `422` si no está Publicado, si es Quiz o si ya está Completado. `404` si no existe.

## Iniciar intento — `POST /api/microcontenidos/{id}/intentos` (Colab)

Crea el progreso si no existe y devuelve el intento `EnCurso` vigente o crea uno nuevo (descartando el anterior solo si se pide
`{ "reiniciar": true }`).

- `201` (nuevo) o `200` (vigente) con `Intento`; las preguntas llegan **sin** `es_correcta`.
- `422` si no está Publicado o no es Quiz. `404` si no existe.

## Responder — `POST /api/microcontenidos/intentos/{intentoId}/respuestas` (Colab)

Body: `{ "pregunta_id": int, "opcion_id": int }`.

- `200` `{ es_correcta, opcion_correcta_id, intento: { estado, respondidas, puntaje? , total_preguntas } }`. Al responder la última
  pregunta el intento pasa a `Finalizado` con `puntaje` y el progreso a `Completado` si no lo estaba (`completado_ahora: true`).
- `422` si la pregunta ya fue respondida, si la opción no pertenece a la pregunta, si el intento ya finalizó o si el quiz cambió
  (`code: "intento_descartado"`; el cliente reinicia). `404` si el intento no existe o no es del usuario.

## Certificado — `GET /api/microcontenidos/{id}/certificado` (Colab)

- `200` `application/pdf` con `Content-Disposition: attachment; filename="certificado-MICRO-0007.pdf"`. Contenido: nombre del
  colaborador, título, competencias vinculadas, duración y fecha de finalización.
- `404` si el contenido no existe o el colaborador no lo ha completado (incluye contenidos Archivados que sí completó: se emite).

## Códigos de error de negocio

`quiz_con_intentos`, `faltan_requisitos`, `transicion_invalida`, `con_progreso`, `intento_descartado`, `no_disponible`,
`ya_completado`, `tipo_inmutable`, `competencia_inactiva`.
