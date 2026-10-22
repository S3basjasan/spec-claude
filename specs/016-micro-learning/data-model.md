# Data Model: Micro-learning

Todas las tablas viven en SQLite, sin soft deletes y sin `cascade`. Las referencias a otros módulos (`competencia_id`,
`colaborador_id`, `user_id`, `tenant_id`) son enteros **sin clave foránea**.

## `microcontenidos` (ampliación de la tabla mínima de la feature 005)

| Campo | Tipo | Notas |
|---|---|---|
| id | entero PK | autoincremental (no reutiliza ids) |
| codigo | texto(12), único, nulo hasta asignarse | `MICRO-NNNN`; se asigna en la misma transacción del alta; no editable |
| titulo | texto(120) | *antes* `nombre`; obligatorio |
| tipo | texto(10) | `Video` \| `PDF` \| `Articulo` \| `Quiz`; inmutable |
| url | texto(500), nulo | *antes* `url_destino`; obligatoria para publicar Video, PDF y Artículo; nula en Quiz |
| miniatura_url | texto(500), nulo | opcional |
| duracion_minutos | entero ≥ 1 | obligatorio siempre |
| estado | texto(10), índice | `Borrador` (por defecto) \| `Publicado` \| `Archivado` |
| created_by / updated_by | entero, nulo | `users.id` lógico |
| created_at / updated_at | timestamps | |

Se **elimina** `formador`. Conversión de datos existentes: estado `Disponible`→`Publicado`; tipo `Lectura`→`Articulo`,
`Infografia`→`PDF`, `Audio`→`Video`; `codigo` de las filas previas = `MICRO-` + id con 4 dígitos.

**Vínculo con competencias**: se conserva `competencia_microcontenido` (feature 005): `competencia_id` (lógico), `microcontenido_id`
(FK `restrictOnDelete`), `created_by`; única `(competencia_id, microcontenido_id)`. No se crea otra tabla de vínculo.

## `microcontenido_preguntas`

| Campo | Tipo | Notas |
|---|---|---|
| id | entero PK | |
| microcontenido_id | FK → microcontenidos, `restrictOnDelete` | solo contenidos de tipo Quiz |
| enunciado | texto(500) | obligatorio |
| orden | entero ≥ 1 | único por contenido `(microcontenido_id, orden)` |

## `microcontenido_opciones`

| Campo | Tipo | Notas |
|---|---|---|
| id | entero PK | |
| pregunta_id | FK → microcontenido_preguntas, `restrictOnDelete` | |
| texto | texto(300) | obligatorio |
| es_correcta | booleano | exactamente una `true` por pregunta (validado en la aplicación) |
| orden | entero ≥ 1 | único por pregunta `(pregunta_id, orden)` |

## `microcontenido_progresos`

| Campo | Tipo | Notas |
|---|---|---|
| id | entero PK | |
| microcontenido_id | FK → microcontenidos, `restrictOnDelete` | |
| colaborador_id | entero | lógico → Gestión de Personal |
| tenant_id | entero, índice | copiado de la cuenta del colaborador |
| estado | texto(11) | `EnProgreso` \| `Completado` |
| posicion_segundos | entero ≥ 0, nulo | solo Video |
| fecha_inicio | datetime | |
| fecha_completado | datetime, nulo | |
| timestamps | | |

Única `(microcontenido_id, colaborador_id)`. Índices `(tenant_id, estado)` y `(colaborador_id, estado)`.

## `microcontenido_intentos`

| Campo | Tipo | Notas |
|---|---|---|
| id | entero PK | |
| progreso_id | FK → microcontenido_progresos, `restrictOnDelete` | |
| estado | texto(10) | `EnCurso` \| `Finalizado` |
| puntaje | entero ≥ 0, nulo | respuestas correctas; nulo mientras `EnCurso` |
| total_preguntas | entero ≥ 1 | preguntas del quiz al iniciar el intento |
| finalizado_en | datetime, nulo | |
| timestamps | | |

Solo los `Finalizado` bloquean la edición de preguntas y puntúan. A lo sumo un `EnCurso` por progreso.

## `microcontenido_respuestas`

| Campo | Tipo | Notas |
|---|---|---|
| id | entero PK | |
| intento_id | FK → microcontenido_intentos, `restrictOnDelete` | |
| pregunta_id | FK → microcontenido_preguntas, `restrictOnDelete` | |
| opcion_id | FK → microcontenido_opciones, `restrictOnDelete` | |
| es_correcta | booleano | resultado en el momento de responder |
| created_at | timestamp | |

Única `(intento_id, pregunta_id)`.

## `microcontenido_historial` (solo inserción)

| Campo | Tipo | Notas |
|---|---|---|
| id | entero PK | |
| microcontenido_id | FK → microcontenidos, `restrictOnDelete` | |
| estado_anterior | texto(10), nulo | nulo en la creación |
| estado_nuevo | texto(10) | |
| user_id | entero, nulo | |
| created_at | timestamp | |

La creación registra `null → Borrador`. Sin endpoints de edición ni borrado; solo desaparece al eliminar el contenido sin progreso.

## Relaciones

```text
microcontenidos 1──* microcontenido_preguntas 1──* microcontenido_opciones
microcontenidos 1──* microcontenido_progresos 1──* microcontenido_intentos 1──* microcontenido_respuestas
microcontenidos 1──* microcontenido_historial
microcontenidos *──* competencias (lógico, vía competencia_microcontenido)
```

## Estados y transiciones

```text
Contenido:  (nuevo) → Borrador → Publicado ⇄ Archivado       (no existe Borrador → Archivado ni retorno a Borrador)
Progreso:   (abrir) → EnProgreso → Completado                 (Completado es final; repetir un quiz no lo revierte)
Intento:    (iniciar) → EnCurso → Finalizado | descartado      (descartado = borrado al reiniciar o al editar preguntas)
```

## Reglas de validación

- Siempre: título (1–120), tipo válido, duración entero ≥ 1.
- Publicar: URL válida `http(s)` si no es Quiz; al menos una pregunta si es Quiz; al menos una competencia **activa** vinculada.
- Quiz: 1–20 preguntas; enunciado obligatorio; 2–5 opciones con texto; exactamente una correcta por pregunta.
- Preguntas no modificables si existe algún intento `Finalizado`.
- Competencias nuevas solo activas; las ya vinculadas e inactivas se conservan.
- Eliminar solo sin filas en `microcontenido_progresos`.

## Datos de siembra (`MicroLearningSeeder`)

10 micro-contenidos de los cuatro tipos y los tres estados (al menos 5 Publicados y un Quiz de 5 preguntas con 4 opciones cada
una), progresos de ejemplo (EnProgreso y Completado, un Video con `posicion_segundos`) e intentos Finalizados para los
colaboradores del seeder de Gestión de Personal. Los vínculos con competencias los crea `CatalogoFormativoSeeder`, que pasa a
**resolver** los micro-contenidos por `codigo` en lugar de crearlos; `DatabaseSeeder` ejecuta `MicroLearningSeeder` antes.
