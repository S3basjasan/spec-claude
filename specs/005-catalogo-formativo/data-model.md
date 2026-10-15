# Modelo de datos: Catálogo formativo de competencias

Módulo `Desarrollo`. Siete tablas nuevas (SQLite). `competencia_id` y los ids de usuario son enteros **sin clave foránea** hacia
otros módulos: la validez de la competencia se comprueba con `EstructuraFacade`. Ninguna tabla de Estructura se lee ni se modifica.

## Tablas

### `areas_formativas`

| Columna | Tipo | Reglas |
|---|---|---|
| id | entero, PK | |
| nombre | texto(60) | obligatorio |
| nombre_clave | texto(60) | **único**; `nombre` en minúsculas, sin espacios en los extremos y con espacios internos colapsados |
| created_at, updated_at | fecha-hora | |

Sin eliminación. Renombrar actualiza `nombre` y `nombre_clave` y escribe historial.

### `cursos` *(mínima; amplía la feature de cursos)*

| Columna | Tipo | Reglas |
|---|---|---|
| id | entero, PK | |
| nombre | texto(120) | obligatorio |
| tipo | texto | `Online` \| `Presencial` \| `Mixto` |
| duracion_minutos | entero | > 0 |
| formador | texto(80) | nullable |
| estado | texto | `Disponible` \| `Archivado` (por defecto `Disponible`) |
| url_destino | texto | nullable; sin valor → "Próximamente" |
| created_at, updated_at | fecha-hora | |

### `microcontenidos` *(mínima; amplía Micro-Learning)*

Mismas columnas que `cursos`, con `tipo` = `Video` \| `Lectura` \| `Infografia` \| `Audio`.

### `competencia_formativa`

Una fila por competencia **configurada** (se crea en el primer guardado).

| Columna | Tipo | Reglas |
|---|---|---|
| id | entero, PK | |
| competencia_id | entero | **único**; referencia lógica a Estructura |
| area_formativa_id | entero, FK `areas_formativas`, nullable | sin cascada |
| version | entero | empieza en 1; sube solo cuando hay cambios reales |
| updated_by | entero | usuario de la última configuración (sin FK) |
| created_at, updated_at | fecha-hora | `updated_at` = última configuración |

### `competencia_curso`

| Columna | Tipo | Reglas |
|---|---|---|
| id | entero, PK | |
| competencia_id | entero | referencia lógica |
| curso_id | entero, FK `cursos` | sin cascada de borrado |
| created_by | entero | |
| created_at, updated_at | fecha-hora | |

Restricción **única** `(competencia_id, curso_id)`. Quitar una asociación elimina esta fila, no el curso.

### `competencia_microcontenido`

Igual que `competencia_curso`, con `microcontenido_id` (FK `microcontenidos`) y única `(competencia_id, microcontenido_id)`.

### `competencia_formativa_historial` *(solo inserción)*

| Columna | Tipo | Reglas |
|---|---|---|
| id | entero, PK | |
| competencia_id | entero, **nullable** | nulo en renombrados de área |
| tipo_cambio | texto | `AreaFormativa` \| `Curso` \| `Microcontenido` |
| accion | texto | `Asociado` \| `Quitado` \| `Cambiado` \| `Renombrado` |
| recurso_id | entero, nullable | id del curso, micro-contenido o área |
| valor_anterior | texto, nullable | nombre del recurso quitado, área anterior o nombre anterior del área |
| valor_nuevo | texto, nullable | nombre del recurso asociado, área nueva o nombre nuevo del área |
| user_id | entero | actor (sin FK) |
| created_at | fecha-hora | |

Combinaciones válidas: `Curso|Microcontenido` + `Asociado|Quitado`; `AreaFormativa` + `Cambiado` (con `competencia_id`) o
`Renombrado` (con `competencia_id` nulo). Sin `updated_at` ni endpoints de edición o borrado.

## Vista lógica de una competencia en el catálogo

| Campo | Origen |
|---|---|
| id, código, nombre, descripción, tipo, estado, grados A/B/C/D | `EstructuraFacade` (solo lectura) |
| área formativa | `competencia_formativa` → `areas_formativas` |
| cursos / micro-contenidos asociados | `competencia_curso` / `competencia_microcontenido` → `cursos` / `microcontenidos` |
| contadores y `cubierta` | solo recursos `Disponible` |
| última configuración (usuario, fecha), `version` | `competencia_formativa` |
| nivel actual del colaborador | puerto `NivelesDeColaboradorInterface` |
| brecha activa | puerto `BrechasActivasInterface` |

## Reglas e invariantes

- Una competencia sin fila en `competencia_formativa` y sin vínculos es válida: cuenta como "sin cobertura", área ausente.
- Cobertura = competencias **activas** con ≥ 1 curso o micro-contenido `Disponible` / competencias activas; 0 % sin activas.
- Nuevos vínculos solo con competencia activa y recurso `Disponible`; ningún vínculo duplicado.
- Las competencias que pasan a inactivas conservan sus vínculos (consultables por el Administrador) y no cuentan en la cobertura.
- Guardado sin diferencias → sin escrituras, sin historial, `version` sin cambio.
- El historial nunca se modifica ni se elimina.

## Transiciones

- Competencia: sin configurar → configurada (primer guardado con cambios) → `version` se incrementa con cada guardado con cambios.
- Recurso (propiedad de su módulo): `Disponible` ↔ `Archivado`; al archivarse deja de contar y de mostrarse al colaborador, pero
  el vínculo persiste.

## Siembra (`CatalogoFormativoSeeder`)

Depende de `CompetenciasSeeder`. Crea 4 áreas (Liderazgo y gestión, Habilidades interpersonales, Técnica y datos, Desempeño y
resultados), 8 cursos (uno `Archivado`) y 4 micro-contenidos de demostración, y asociaciones sobre las 7 competencias sembradas
por Estructura de modo que: ≥ 2 competencias activas queden sin cursos ni micro-contenidos, ≥ 1 competencia inactiva conserve
vínculos, ≥ 1 competencia activa tenga solo recursos archivados (demuestra la regla de cobertura) y un curso esté vinculado a
varias competencias. Los recursos sembrados son provisionales y serán reemplazados por los de sus features dueñas.
