# Data Model: Diccionario de competencias unificado

Estado final después de las migraciones nuevas. Convenciones del repositorio: ids de usuario sin clave foránea; sin borrado en cascada; las competencias nunca se eliminan.

## Competencia (`competencias`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `codigo` | texto | Único, formato `COMP-` + número de 4 dígitos; anulable solo mientras se crea la fila; nunca se modifica |
| `nombre` | texto, máximo 50 | Obligatorio; se guarda recortado |
| `nombre_clave` | texto, máximo 50 | Nombre recortado, en minúsculas y sin tildes; índice único |
| `descripcion` | texto, máximo 500 | Obligatoria; no puede ser solo espacios |
| `tipo` | `Cardinal` \| `Especifica` | Obligatorio |
| `estado` | `Activo` \| `Inactivo` | Por defecto `Activo`; solo cambia con `PATCH /{id}/estado`; no puede pasar a `Activo` si la competencia está incompleta |
| `version` | entero | Empieza en 1; **sube con cualquier escritura** (datos, comportamientos, preguntas, escala, estado) |
| `created_by`, `updated_by` | entero anulable | Autor de la creación y de la última escritura de cualquier tipo |
| `created_at`, `updated_at` | marcas de tiempo | Automáticas |

Relaciones: 4 `competencia_grados`; 4 `competencia_preguntas`; 0 o 4 `competencia_categorias`; 0 o más filas de historial de estado y de escala; 0 o más `puesto_competencias`.

**Completa** (derivado, no se guarda): tiene 4 grados con descripción, 4 comportamientos por grado (16) y 4 preguntas, todos con texto.

## Escala (`tipo_grados`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `nombre` | texto, máximo 30 | Único: `Letras`, `Números`, `Categorías` |
| `activo` | booleano | Por defecto falso; **solo uno** puede ser verdadero (índice único parcial); es la escala predeterminada del formulario de creación |

## Valor de escala (`tipo_grado_valores`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `tipo_grado_id` | FK a `tipo_grados.id` | Obligatoria |
| `valor` | texto, máximo 20 | Obligatorio |
| `orden` | entero 1–4 | 1 = grado más alto |

Únicos `(tipo_grado_id, valor)` y `(tipo_grado_id, orden)`. Cada escala tiene exactamente cuatro valores.

Datos del catálogo:

| Escala | orden 1 | orden 2 | orden 3 | orden 4 |
|---|---|---|---|---|
| Letras (activa) | A | B | C | D |
| Números | Nivel 1 | Nivel 2 | Nivel 3 | Nivel 4 |
| Categorías | Excelente | Muy buena | Buena | Normal |

**Etiqueta de un grado**: en Letras, `"Grado " + valor`; en Números, el valor; en Categorías, el nombre propio de la competencia en el mismo `orden` si existe, o el valor por defecto.

## Grado de desarrollo (`competencia_grados`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria; lo referencian puestos y comportamientos |
| `competencia_id` | FK a `competencias.id` | Obligatoria; sin cascada |
| `tipo_grado_valor_id` | FK a `tipo_grado_valores.id` | Obligatoria; define la escala y la posición |
| `descripcion` | texto, máximo 500 | Obligatoria; no solo espacios |
| `created_by`, `updated_by`, marcas de tiempo | | Auditoría de la fila |

Único `(competencia_id, tipo_grado_valor_id)`. Invariantes: los cuatro grados de una competencia son del mismo `tipo_grado` y cubren los órdenes 1 a 4. **Las filas nunca se borran ni se recrean**: al cambiar de escala se actualiza `tipo_grado_valor_id` de cada fila al valor del mismo `orden` en la escala nueva.

## Comportamiento (`competencia_comportamientos`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `competencia_grado_id` | FK a `competencia_grados.id` | Obligatoria; sin cascada |
| `orden` | entero 1–4 | |
| `descripcion` | texto, máximo 300 | Obligatoria; no solo espacios |
| `created_by`, `updated_by`, marcas de tiempo | | Auditoría de la fila |

Único `(competencia_grado_id, orden)`: como máximo cuatro por grado. Una competencia completa tiene 16. Se editan actualizando las filas existentes por grado y orden, sin insertar ni borrar.

## Pregunta (`competencia_preguntas`)

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `competencia_id` | FK a `competencias.id` | Obligatoria; sin cascada (sustituye a `registro_id`) |
| `orden` | entero 1–4 | |
| `pregunta` | texto, máximo 500 | Obligatoria; no solo espacios |
| `created_by`, `updated_by`, marcas de tiempo | | Auditoría de la fila |

Único `(competencia_id, orden)`: como máximo cuatro. Una competencia completa tiene 4.

## Categoría propia (`competencia_categorias`) — nueva

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `competencia_id` | FK a `competencias.id` | Obligatoria; sin cascada |
| `orden` | entero 1–4 | |
| `nombre` | texto, máximo 20 | Obligatorio; no solo espacios |
| `nombre_clave` | texto, máximo 20 | Nombre recortado, en minúsculas y sin tildes |
| `created_by`, `updated_by`, marcas de tiempo | | Auditoría de la fila |

Únicos `(competencia_id, orden)` y `(competencia_id, nombre_clave)` (sin repetir nombres dentro de la competencia). Hay **cuatro filas o ninguna** por competencia; se conservan al cambiar a otra escala.

## Historial de estado (`competencia_estado_historial`) — sin cambios

`competencia_id`, `estado_anterior`, `estado_nuevo`, `user_id` (anulable), `created_at`. Solo inserción. La migración de completitud inserta aquí un registro por cada competencia que pasa a Inactivo (con `user_id` nulo).

## Historial de escala (`competencia_escala_historial`) — nueva

| Campo | Tipo | Reglas |
|---|---|---|
| `id` | entero autoincremental | Clave primaria |
| `competencia_id` | FK a `competencias.id` | Obligatoria; sin cascada |
| `escala_anterior` | texto, máximo 30 | Nombre del tipo de grado anterior |
| `escala_nueva` | texto, máximo 30 | Nombre del tipo de grado nuevo; distinto del anterior |
| `user_id` | entero anulable | Quien hizo el cambio |
| `created_at` | marca de tiempo | Sin `updated_at`; solo inserción |

Se inserta una fila cada vez que cambia la escala de una competencia ya creada (por `PUT` o `PATCH …/escala`); la creación no escribe historial.

## Asignación de puesto (`puesto_competencias`) — columna modificada

| Campo | Cambio |
|---|---|
| `competencia_grado_id` | FK a `competencia_grados.id`, obligatoria; **sustituye** a `grado_requerido` |

Se conserva único `(puesto_id, competencia_id)`. Regla de aplicación: el grado debe pertenecer a la competencia indicada. El grado requerido que ven los consumidores sigue siendo la clave posicional A–D (orden 1–4).

## Tablas que desaparecen

- `competencia_comportamiento_registros` y `competencia_pregunta_registros`: sus datos pasan a las filas de comportamientos y preguntas (que ya tienen su propio autor y fechas). Su `version` y autor de cabecera se descartan; la versión vive en `competencias.version`.

## Transiciones

- **Crear**: en una transacción inserta la competencia (`Activo`, `version` 1, `created_by`), sus 4 grados con la escala elegida, 16 comportamientos, 4 preguntas y, si la escala es Categorías, las 4 categorías; asigna `codigo`. O se guarda todo o nada.
- **Actualizar competencia y grados**: exige `version`; actualiza nombre, descripción, tipo y las 4 descripciones de grado; si cambia la escala, actualiza `tipo_grado_valor_id` por orden y escribe historial de escala; si llegan `categorias`, reemplaza las 4 filas (o las crea); sube `version`; guarda `updated_by`.
- **Actualizar comportamientos / preguntas**: exige `version`; actualiza las 16 / 4 filas existentes (si faltan, las inserta); sube `version`; guarda `updated_by` de la competencia.
- **Aplicar escala**: sin versión de entrada; actualiza `tipo_grado_valor_id` por orden, escribe historial de escala, sube `version`; 422 si ya es esa escala.
- **Cambiar estado**: sin versión de entrada; al pasar a `Activo` valida que esté completa (422 con lo que falta); escribe historial de estado y sube `version`; 422 si ya está en ese estado.

## Reglas de visibilidad

- Administrador: ve activas e inactivas (por defecto, ambas).
- Usuario: el listado contiene solo `Activo`; no se acepta otro valor.
- Cualquier persona autenticada puede consultar por id una competencia activa o inactiva.
- Los demás módulos asignan solo competencias `Activo` y completas, y conservan sus referencias a las `Inactivo`.

## Migración de datos

Orden y reglas (migraciones nuevas, en este orden):

1. Crear `tipo_grados` y `tipo_grado_valores` y sembrar las tres escalas (Letras activa).
2. `competencia_grados`: añadir `tipo_grado_valor_id` y las columnas de auditoría, rellenar con el valor de Letras del mismo `grado` (A→orden 1 … D→orden 4), hacerlo obligatorio, eliminar `grado` y su único, crear el único nuevo.
3. Crear `competencia_categorias` (vacía).
4. `competencia_preguntas`: añadir `competencia_id` (desde la cabecera por `registro_id`), eliminar `registro_id` y su único, crear `(competencia_id, orden)`; eliminar `competencia_pregunta_registros`.
5. Eliminar `competencia_comportamiento_registros` (los comportamientos ya cuelgan del grado).
6. `puesto_competencias`: añadir `competencia_grado_id`, rellenar por `(competencia_id, grado_requerido)`, hacerlo obligatorio y eliminar `grado_requerido`.
7. Crear `competencia_escala_historial`.
8. Completitud: para cada competencia `Activo` que no esté completa, poner `Inactivo`, sumar 1 a `version` y añadir la fila de historial de estado (`user_id` nulo).

Cada migración usa transacción donde SQLite lo permite; las pruebas de migración parten de una base con datos de 001–003 y verifican que ninguna asignación de puesto se pierde.

## Datos iniciales

- **`TiposGradoSeeder`**: las tres escalas anteriores (idempotente).
- **`CompetenciasSeeder`**: siete competencias completas (grados, 16 comportamientos, 4 preguntas), tomadas del diseño:

| Código | Nombre | Tipo | Escala | Estado |
|---|---|---|---|---|
| COMP-0001 | Trabajo en equipo | Cardinal | Letras | Activo |
| COMP-0002 | Orientación a resultados | Cardinal | Números | Activo |
| COMP-0003 | Liderazgo | Específica | Letras | Activo |
| COMP-0004 | Análisis de datos | Específica | Letras | Activo |
| COMP-0005 | Comunicación efectiva | Cardinal | Categorías (con nombres propios) | Activo |
| COMP-0006 | Gestión de proyectos | Específica | Letras | Activo |
| COMP-0007 | Gestión del tiempo | Específica | Letras | Inactivo |

`created_by` y `user_id` toman el administrador de prueba (`admin@hrms.test`) si existe, o `null`. Sin datos personales reales.
