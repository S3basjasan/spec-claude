# Research: Ajustes técnicos del modelo de categorías (01b.5)

## R1. Forma del dato dentro de la categoría

- **Decisión**: columna `indicadores` JSON con lista ordenada de textos (`["texto 1", "texto 2"]`). El orden es la posición; el `orden` que expone la API (1..n) se deriva.
- **Rationale**: es lo que define `modelo-de-datos.dbml` ("lista ordenada de 1 a 5 textos") y coincide con `Categoria::indicadoresComoJson()`, que ya usa la auditoría; así el valor guardado y el auditado son el mismo formato. `texto_normalizado` y `orden` eran columnas derivadas y no se guardan: `Indicador::normalizado()` las calcula en Dominio.
- **Alternativas**: lista de objetos `{orden, texto}` (redundante, el orden ya es la posición); guardar también el texto normalizado (duplica un dato derivable).

## R2. Quitar la llave foránea de `competencia_id` en SQLite

- **Decisión**: usar `dropForeign(['competencia_id'])` dentro de la migración y verificarlo con la prueba de migración. Hay precedente en el repositorio (`2026_10_23_000004_fold_registros_into_competencia.php` ya usa `dropForeign`) y Laravel 13 lo soporta en SQLite reconstruyendo la tabla internamente. Si la prueba mostrara que no funciona, plan B: recrear la tabla dentro de la migración (indicado por el plan fuente).
- **Rationale**: es la opción mínima y portable a PostgreSQL (modelo objetivo).
- **Alternativas**: recrear la tabla siempre (más código y más riesgo con datos); dejar la FK (incumple CA-04).
- **Verificación**: la prueba consulta el esquema (`Schema::getForeignKeys('categorias_reconocimiento')`) y comprueba que no hay FK hacia `competencias`.

## R3. Qué pasa con `indicadores_reconocimiento` (decisión diferida desde la clarificación)

- **Decisión**: se elimina en `up()`; `down()` la recrea con el esquema original de inc-01 y vuelca los datos de vuelta.
- **Rationale**: conservarla dejaría dos fuentes de verdad y no cumpliría "ya no se borran filas de ninguna tabla" ni el modelo vigente. La reversibilidad queda garantizada porque `down()` reconstruye todo a partir del JSON.
- **Alternativas**: dejarla como respaldo (datos duplicados que se desincronizan); renombrarla (mismo problema).

## R4. Datos heredados que incumplan las reglas (decisión diferida)

- **Decisión**: la migración copia lo que haya, tal cual y en orden, sin revalidar ni descartar. Una categoría sin filas queda con `[]`. Las reglas se aplican en el siguiente guardado.
- **Rationale**: una migración de datos no debe perder ni alterar información; CA-01 exige conservar todos los existentes. Los datos de inc-01 ya pasaron validación en Dominio y por los índices únicos.
- **Alternativas**: fallar la migración ante datos inválidos (bloquea el despliegue por algo que el usuario no ve); recortar o deduplicar (pérdida de datos).

## R5. Actualización atómica con el token de concurrencia

- **Decisión**: incluir `indicadores` en el `UPDATE ... WHERE id = ? AND updated_at = <versión>` existente, codificando el JSON a mano.
- **Rationale**: `Model::query()->update([...])` es una actualización de consulta que no aplica los casts del modelo, por lo que un arreglo fallaría. `json_encode` con `JSON_UNESCAPED_UNICODE` mantiene el mismo formato que la auditoría. Como efecto, el reemplazo de la lista deja de ser un segundo paso y queda cubierto por la misma condición de versión (mejora de consistencia sin cambiar el comportamiento visible).
- **Alternativas**: cargar el modelo y usar `save()` (pierde el UPDATE condicional atómico).

## R6. Reversión: restaurar la FK con datos huérfanos

- **Decisión**: `down()` verifica antes de modificar que todo `competencia_id` exista en `competencias` (consulta a la tabla dentro de la migración, que es infraestructura de esquema); si hay huérfanos, lanza una excepción con el detalle y no cambia nada.
- **Rationale**: al quitarse la FK pudieron crearse categorías con una competencia que luego dejó de existir; restaurar la FK sin comprobarlo fallaría a mitad de la reversión o dejaría datos inconsistentes. Fallar antes es seguro y reintentable.
- **Alternativas**: restaurar la FK sin comprobar; borrar o anular los huérfanos (pérdida de datos).

## R7. Estrategia de pruebas de la migración

- **Decisión**: seguir el patrón de `tests/Feature/Estructura/Competencias/MigracionUnificacionTest.php`: una base SQLite temporal con su propia conexión (`migracion`, llaves foráneas activas), fuera de la transacción de `RefreshDatabase`. Se migran todas las migraciones salvo la nueva, se siembran datos con el esquema de inc-01 (categorías y filas de `indicadores_reconocimiento`, incluso fuera de orden o ausentes), se aplica la migración nueva (`migrate`) y se revierte (`migrate:rollback --step=1`), comprobando textos, orden, listas vacías, ausencia de la tabla y de la FK, y la FK restaurada.
- **Rationale**: quitar o restaurar una FK en SQLite reconstruye la tabla y el `PRAGMA foreign_keys` no tiene efecto dentro de una transacción; con una base propia la prueba ejercita la migración real y es la convención ya usada en el repositorio. Nivel de prueba mínimo que demuestra el comportamiento (principio IV).
- **Alternativas**: llamar `up()`/`down()` directamente bajo `RefreshDatabase` (riesgo de resultados engañosos por la transacción y por la FK); `migrate --path` hasta la migración anterior sobre `:memory:` (más frágil).

## R8. Impacto en pruebas, factory y seeder existentes

- **Decisión**: ajustar solo lo que usa la tabla/relación eliminada: `CategoriaReconocimientoFactory` (afterCreating ya no inserta filas: define `indicadores` en `definition`/estado), `CategoriasReconocimientoSeeder`, y las pruebas `ActualizarCategoriaTest` (`load('indicadores')`, `assertDatabaseCount('indicadores_reconocimiento', …)`), `CrearCategoriaTest` (dos `assertDatabaseCount`), `ObtenerCategoriaTest` (inserta filas de indicadores) y `CategoriasFacadeTest` (`withCount('indicadores')`). Las aserciones sobre la API y la auditoría no cambian.
- **Rationale**: CA-06; confirma que el comportamiento visible es el mismo.
