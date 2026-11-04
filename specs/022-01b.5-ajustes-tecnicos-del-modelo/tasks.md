---

description: "Lista de tareas de 01b.5 Ajustes técnicos del modelo de categorías"
---

# Tasks: Ajustes técnicos del modelo de categorías (01b.5)

**Input**: Documentos de diseño en `/specs/022-01b.5-ajustes-tecnicos-del-modelo/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/README.md, quickstart.md

**Tests**: Incluidos. La constitución (principio IV) exige cobertura automatizada de todo cambio de comportamiento y el plan fija las pruebas de inc-01 como criterio de aceptación.

**Organization**: Es una refactorización de persistencia: el cambio de esquema, modelo y repositorio es común a todas las historias, por lo que va en la fase Fundacional. Cada historia aporta las pruebas que demuestran su criterio y los ajustes de las pruebas de inc-01 que dependían de la tabla eliminada.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: se puede ejecutar en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: historia de usuario a la que pertenece (US1 a US4)
- Todas las rutas son relativas a la raíz del repositorio; el backend vive en `backend/`. Comandos desde `backend/`.

## Path Conventions

- **Web app**: `backend/app/Modules/Compensaciones/…`, `backend/database/…`, `backend/tests/…`. `frontend/` no se modifica.

---

## Phase 1: Setup (línea base)

**Purpose**: Fijar el punto de partida antes de cambiar nada.

- [X] T001 Ejecutar `php artisan test --compact tests/Feature/Compensaciones tests/Unit/Compensaciones` en `backend/` y anotar el conteo de pruebas en verde como línea base (todas deben pasar antes de continuar; si alguna falla, detenerse y reportarlo)
- [X] T002 Buscar con Grep en `backend/app`, `backend/database` y `backend/tests` toda referencia a `IndicadorReconocimientoModel`, `indicadores_reconocimiento` y a la relación `->indicadores`/`with('indicadores')`/`withCount('indicadores')`, y confirmar que solo aparecen en los archivos listados en plan.md y research.md (R8): `CategoriaReconocimientoModel.php`, `IndicadorReconocimientoModel.php`, `EloquentCategoriaRepository.php`, `CategoriaReconocimientoFactory.php`, `CategoriasReconocimientoSeeder.php` y las pruebas `ActualizarCategoriaTest`, `CrearCategoriaTest`, `ObtenerCategoriaTest`, `CategoriasFacadeTest`; si aparece otro, añadirlo a las tareas de ajuste

---

## Phase 2: Foundational (cambio de esquema, modelo y repositorio)

**Purpose**: Mover los indicadores a la categoría y quitar la FK. Bloquea todas las historias.

**⚠️ CRITICAL**: Ninguna historia puede validarse hasta completar esta fase.

- [X] T003 Crear `backend/database/migrations/2026_11_03_000001_move_indicadores_into_categorias_reconocimiento.php` con `up()`: (1) agregar a `categorias_reconocimiento` la columna `indicadores` JSON; (2) por cada categoría copiar los `texto` de `indicadores_reconocimiento` ordenados por `orden` como lista JSON codificada con `JSON_UNESCAPED_UNICODE` (`[]` si no tiene filas), sin revalidar ni descartar nada (research R4); (3) quitar la FK de `competencia_id` con `dropForeign(['competencia_id'])` conservando la columna `bigint` (research R2; precedente en `2026_10_23_000004_fold_registros_into_competencia.php`; si la prueba de T011 demuestra que no funciona en SQLite, recrear la tabla dentro de la migración); (4) `Schema::drop('indicadores_reconocimiento')`. No editar `2026_10_20_000001_*`, `2026_10_20_000002_*` ni `2026_10_20_000003_*`
- [X] T004 En el mismo archivo de T003 implementar `down()` reversible (research R3 y R6): antes de modificar nada, comprobar que todo `competencia_id` existe en `competencias` y, si hay huérfanos, lanzar una excepción con los ids afectados sin cambiar nada; luego recrear `indicadores_reconocimiento` con su esquema original (`id`, `categoria_reconocimiento_id` FK `restrictOnDelete`, `orden` unsignedSmallInteger "1 a 5, consecutivo", `texto` string 250, `texto_normalizado` string 250, `timestamps`, únicos `indicadores_reconocimiento_orden_unico` sobre `(categoria_reconocimiento_id, orden)` e `indicadores_reconocimiento_texto_unico` sobre `(categoria_reconocimiento_id, texto_normalizado)`), volcar una fila por elemento de la lista (`orden = posición + 1`, `texto_normalizado = Normalizador::normalizar(texto)`), quitar la columna `indicadores` y restaurar la FK `competencia_id` → `competencias` con `restrictOnDelete`
- [X] T005 [P] Modificar `backend/app/Modules/Compensaciones/Infrastructure/Persistence/Eloquent/Models/CategoriaReconocimientoModel.php`: agregar `'indicadores'` a `$fillable`, declarar el cast `'indicadores' => 'array'` y eliminar la relación `indicadores()` y el `use … HasMany` que quede sin uso
- [X] T006 Modificar `backend/app/Modules/Compensaciones/Infrastructure/Persistence/Eloquent/Repositories/EloquentCategoriaRepository.php` (depende de T005): (a) en `crear` incluir `'indicadores' => <lista de textos de $nueva->indicadores>` en el `create` y eliminar la llamada a `reemplazarIndicadores`; (b) en `actualizarSiVigente` incluir `'indicadores' => json_encode(<lista de textos>, JSON_UNESCAPED_UNICODE)` dentro del mismo arreglo del `actualizarCondicional` (el `UPDATE` de consulta no aplica casts; research R5), eliminar la llamada a `reemplazarIndicadores`; (c) eliminar el método `reemplazarIndicadores` y los `use` de `IndicadorReconocimientoModel`; (d) quitar `->with('indicadores')` de `porId`, `porIds` y `activas`; (e) en `aEntidad` construir los `Indicador` desde `$m->indicadores` (`new Indicador($texto, $posicion + 1)`), manteniendo `[]` cuando `$conIndicadores` es falso (el listado sigue sin indicadores). La interfaz `CategoriaRepository`, el Dominio, la Aplicación y la Presentación no se tocan
- [X] T007 [P] Modificar `backend/database/factories/CategoriaReconocimientoFactory.php`: eliminar el `afterCreating` y el uso de `IndicadorReconocimientoModel`; en `afterMaking` asignar `$categoria->indicadores` con tantos textos como indique el atributo temporal `indicadores_a_crear` (por defecto 2), con el mismo formato `"Indicador $orden de {$categoria->codigo}"` (la prueba de auditoría de `ActualizarCategoriaTest` espera el texto `Indicador 1 de`), y conservar el estado `conIndicadores(int)`, incluido `conIndicadores(0)` que produce `[]`
- [X] T008 [P] Modificar `backend/database/seeders/CategoriasReconocimientoSeeder.php`: guardar la lista de textos en el campo `indicadores` del `create` de cada categoría y eliminar el bucle y el `use` de `IndicadorReconocimientoModel` (sigue siendo idempotente por nombre y con 1 a 5 indicadores)
- [X] T009 Eliminar `backend/app/Modules/Compensaciones/Infrastructure/Persistence/Eloquent/Models/IndicadorReconocimientoModel.php` (depende de T005 a T008; ya nada debe referenciarlo)

**Checkpoint**: el esquema nuevo se aplica y el código compila; las pruebas de inc-01 que dependían de la tabla aún pueden fallar hasta ajustarlas en las historias.

---

## Phase 3: User Story 1 - Los indicadores viven dentro de la categoría (Priority: P1) 🎯 MVP

**Goal**: Indicadores como lista ordenada dentro de la categoría, conservados en orden, reemplazo completo al guardar, migración reversible, respuestas idénticas (CA-01, CA-03, CA-05, CA-06).

**Independent Test**: `php artisan test --compact --filter=MigracionIndicadores` más `ObtenerCategoriaTest`, `CrearCategoriaTest`, `ActualizarCategoriaTest` y `CategoriasFacadeTest` en verde, con la API respondiendo igual que antes.

### Tests for User Story 1

- [X] T010 [P] [US1] Crear `backend/tests/Feature/Compensaciones/MigracionIndicadoresTest.php` (Pest) **siguiendo el patrón de `backend/tests/Feature/Estructura/Competencias/MigracionUnificacionTest.php`** (research R7): SIN `RefreshDatabase`; en `beforeEach` crear una base SQLite temporal propia (`sys_get_temp_dir()`), registrar la conexión `migracion` con `foreign_key_constraints => true`, copiar a una carpeta `antiguas` todas las migraciones de `database/migrations` salvo la nueva (`2026_11_03_`) y migrarlas con `Artisan::call('migrate', ['--database' => 'migracion', '--path' => …, '--realpath' => true, '--force' => true])`; en `afterEach` hacer `DB::purge('migracion')` y borrar el directorio. Los datos se siembran con `DB::connection('migracion')` (competencias, categorías y filas de `indicadores_reconocimiento`), se aplica la migración nueva con `Artisan::call('migrate', ['--database' => 'migracion', '--force' => true])` y se revierte con `Artisan::call('migrate:rollback', ['--database' => 'migracion', '--step' => 1, '--force' => true])`. Casos: (a) categorías con 1 y 5 indicadores → tras `migrate` cada `indicadores` es la lista con los mismos textos y orden; (b) indicadores insertados fuera de orden (`orden` 3, 1, 2) → la lista queda en orden 1, 2, 3; (c) categoría sin filas de indicadores → `indicadores` es `[]` y la migración no falla; (d) tras `migrate` no existe la tabla `indicadores_reconocimiento` y sí la columna `indicadores`; (e) ida y vuelta: `migrate` → `rollback` → la tabla `indicadores_reconocimiento` tiene las mismas filas (`orden` 1..n, `texto_normalizado` correcto) → `migrate` de nuevo devuelve las mismas listas
- [X] T011 [US1] En `backend/tests/Feature/Compensaciones/MigracionIndicadoresTest.php`, con la misma base temporal de T010, añadir los casos de esquema usando `Schema::connection('migracion')->getForeignKeys('categorias_reconocimiento')`: tras `migrate` no hay ninguna FK hacia `competencias` (CA-04); tras `rollback` la FK existe de nuevo; el `rollback` con una categoría cuyo `competencia_id` no existe en `competencias` lanza excepción y deja el esquema nuevo y los datos intactos. Si `dropForeign` no funciona en SQLite, aplicar el plan B de T003 y volver a ejecutar
- [X] T012 [P] [US1] Ajustar `backend/tests/Feature/Compensaciones/ObtenerCategoriaTest.php`: sustituir la inserción de filas con `IndicadorReconocimientoModel` por una categoría con `indicadores` = `['Primero indicador', 'Segundo indicador', 'Tercero indicador']` y mantener las aserciones de la API (`data.indicadores.*.texto`, `data.indicadores.*.orden` = `[1, 2, 3]`, `updated_at`, `seleccionable`); quitar el `use` de `IndicadorReconocimientoModel`
- [X] T013 [P] [US1] Ajustar `backend/tests/Feature/Compensaciones/CrearCategoriaTest.php`: reemplazar `assertDatabaseCount('indicadores_reconocimiento', 2)` y `assertDatabaseCount('indicadores_reconocimiento', 0)` por aserciones sobre la columna `indicadores` de la categoría creada (`['Comparte información con su equipo', 'Ayuda a quien lo necesita']`) y, en el caso de rechazo, por la ausencia de categoría creada
- [X] T014 [US1] Ajustar `backend/tests/Feature/Compensaciones/ActualizarCategoriaTest.php`: quitar `$categoria->load('indicadores')` y obtener los textos desde el atributo `indicadores`; reemplazar `assertDatabaseCount('indicadores_reconocimiento', 3)` por una aserción de que la columna `indicadores` quedó exactamente con los 3 textos enviados y en ese orden (reemplazo completo, CA-03); en el caso existente `rechaza con 409 si la categoría fue modificada y no aplica nada`, hacer que el cuerpo viejo (`$cuerpoViejo`) envíe también una lista de indicadores distinta de la guardada, y añadir la aserción de que tras el 409 `indicadores` sigue siendo la lista del guardado exitoso previo (el reemplazo ahora es atómico con el resto de campos)
- [X] T015 [US1] Ajustar `backend/tests/Feature/Compensaciones/CategoriasFacadeTest.php` (~línea 101): reemplazar `withCount('indicadores')` por el conteo de elementos del atributo `indicadores` de cada categoría sembrada, manteniendo el resultado esperado `[1, 2, 3, 4, 5]`

**Checkpoint**: US1 verificable por sí sola; la migración conserva y revierte los datos.

---

## Phase 4: User Story 2 - Las reglas de indicadores se mantienen (Priority: P1)

**Goal**: 1 a 5 indicadores, 5 a 250 caracteres, sin repetidos ignorando mayúsculas y tildes, todo en el servidor (CA-02).

**Independent Test**: los casos de rechazo de `CrearCategoriaTest`, `ActualizarCategoriaTest` y `DominioTest` en verde.

### Tests for User Story 2

- [X] T016 [P] [US2] En `backend/tests/Feature/Compensaciones/CrearCategoriaTest.php` verificar que ya existen y siguen pasando los casos de 0, 6, 4 y 251 caracteres, el repetido con otras mayúsculas y el límite aceptado de 250 caracteres con 5 indicadores (no duplicarlos); añadir únicamente al dataset de rechazos el caso de repetidos que solo difieren en tildes (`['Ágil en la entrega', 'agil EN la entrega']` → error en `indicadores.1`)
- [X] T017 [P] [US2] En `backend/tests/Feature/Compensaciones/ActualizarCategoriaTest.php` añadir el caso equivalente de edición (repetidos con distintas mayúsculas/tildes → 422 y la lista guardada no cambia)
- [X] T018 [P] [US2] En `backend/tests/Unit/Compensaciones/DominioTest.php` verificar (añadir si falta) que `Categoria::indicadoresDesde` rechaza 0 y 6 elementos, textos de 4 y 251 caracteres y repetidos con distinta capitalización o tildes, y acepta 1 y 5 elementos de 5 y 250 caracteres, para demostrar que las reglas viven en Dominio independientemente de la pantalla

**Checkpoint**: US2 verificable por sí sola.

---

## Phase 5: User Story 3 - La competencia se valida solo por la Facade de Estructura (Priority: P2)

**Goal**: La validación de la competencia no depende de la base de datos (CA-04).

**Independent Test**: guardar con competencia existente (se acepta) e inexistente (mismo rechazo que antes) y esquema sin FK.

### Tests for User Story 3

- [X] T019 [P] [US3] Verificar que el caso existente `rechaza una competencia inexistente o inactiva` de `backend/tests/Feature/Compensaciones/CrearCategoriaTest.php` sigue pasando sin cambios, y añadir solo el caso equivalente de edición en `ActualizarCategoriaTest.php` si no existe (competencia inexistente → mismo 422 y la categoría no cambia); el rechazo proviene de `CompetenciasPort` / `CompetenciasDesdeEstructura`, no de la base de datos
- [X] T020 [US3] En `backend/tests/Feature/Compensaciones/MigracionIndicadoresTest.php` (o un archivo nuevo `backend/tests/Feature/Compensaciones/EsquemaCategoriasTest.php`) añadir una prueba sobre el esquema vigente (sin ejecutar `down()`): `Schema::getForeignKeys('categorias_reconocimiento')` no incluye `competencias` y la columna `competencia_id` sigue existiendo

**Checkpoint**: US3 verificable por sí sola.

---

## Phase 6: User Story 4 - La auditoría sigue registrando indicadores como lista (Priority: P2)

**Goal**: El historial de la categoría conserva el formato de lista JSON (CA-07).

**Independent Test**: `CrearCategoriaTest` y `ActualizarCategoriaTest` con aserciones de auditoría en verde.

### Tests for User Story 4

- [X] T021 [US4] En `backend/tests/Feature/Compensaciones/ActualizarCategoriaTest.php` verificar que siguen pasando, sin duplicarlas, las aserciones existentes de auditoría (`valor_nuevo` = `["Indicador distinto"]`, `valor_anterior` contiene `Indicador 1 de`) y el caso `acepta un guardado sin cambios sin alterar la versión, el autor ni el historial` (0 filas de auditoría); añadir solo lo que falte; confirmar en `CrearCategoriaTest.php` que `valor_nuevo` del alta sigue siendo `["Comparte información con su equipo","Ayuda a quien lo necesita"]`

**Checkpoint**: Las cuatro historias verificadas.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Puertas de calidad de la constitución y comprobación final.

- [X] T022 Ejecutar la suite completa `php artisan test --compact` en `backend/` y comparar con la línea base de T001: mismas pruebas de inc-01 en verde (CA-06), sin pruebas eliminadas salvo las que sustituyeron aserciones sobre la tabla anterior; `RendimientoListadoTest` incluido — Resultado: 2472 pruebas, 47 fallan en Estructura/Unidades y GestionPersonal/Exportacion; sin mis cambios fallan las mismas 47 (preexistentes, ajenas a esta feature). Compensaciones: sin fallos.
- [X] T023 [P] Ejecutar `vendor/bin/pint --test` en `backend/` y corregir con `vendor/bin/pint` los archivos modificados si hace falta
- [X] T024 [P] Análisis estático: `backend/composer.json` no tiene PHPStan ni Larastan configurados y no se instalan en esta feature (la constitución exige solo el análisis «disponible»). Dejar constancia en el resumen final de que no hay análisis estático configurado; ejecutar únicamente el que exista (hoy, Pint en T023)
- [X] T025 [P] Desde `frontend/` ejecutar las pruebas Vitest y el E2E de categorías para confirmar que pasan sin cambios (el frontend no se modifica) — Vitest: 165/183 archivos pasan; los 18 que fallan son de estructura/unidades y desarrollo (preexistentes, frontend sin tocar). E2E de categorías: 4/4 pasan con `backend/.env.e2e` propio (ignorado por git, base `database/e2e.sqlite`). Fue necesario actualizar `frontend/e2e/categorias-reconocimiento.spec.ts`, desfasado respecto al menú y formulario actuales (sin cambiar lo que verifica sobre categorías e indicadores).
- [X] T026 Recorrer `specs/022-01b.5-ajustes-tecnicos-del-modelo/quickstart.md` de principio a fin y marcar cualquier desviación; confirmar con `git status` que no se editó ninguna migración existente de inc-01 (`2026_10_20_000001`, `000002`, `000003`) ni nada bajo `frontend/`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias; T001 antes de cualquier cambio.
- **Foundational (Phase 2)**: depende de Setup; bloquea las historias.
- **US1 a US4 (Phases 3 a 6)**: dependen de Fundacional. Se pueden hacer en paralelo si tocan archivos distintos, pero T013, T014, T016, T017, T019 y T021 editan los mismos archivos de prueba (`CrearCategoriaTest.php`, `ActualizarCategoriaTest.php`): hacerlos en secuencia por archivo.
- **Polish (Phase 7)**: depende de todas las historias.

### Within Phase 2

- T003 → T004 (mismo archivo).
- T005 → T006 (el repositorio usa el modelo con cast).
- T007 y T008 pueden ir en paralelo con T005.
- T009 al final de la fase.

### User Story Dependencies

- US1 es el MVP: demuestra la migración y el reemplazo de lista.
- US2, US3 y US4 son verificaciones independientes entre sí que solo requieren la fase Fundacional.

### Parallel Opportunities

- T005, T007 y T008 en paralelo (archivos distintos).
- T010, T012 y T013 en paralelo (archivos distintos; T011 va después de T010 porque edita el mismo archivo).
- T016 con T018, y T023 con T024 y T025.

## Parallel Example: Phase 2

```text
Task: "Modificar CategoriaReconocimientoModel.php (T005)"
Task: "Modificar CategoriaReconocimientoFactory.php (T007)"
Task: "Modificar CategoriasReconocimientoSeeder.php (T008)"
```

## Implementation Strategy

### MVP First (User Story 1)

1. Fase 1 (línea base) → Fase 2 (esquema, modelo, repositorio, factory, seeder) → Fase 3 (migración probada y pruebas de inc-01 ajustadas).
2. **Detenerse y validar**: `MigracionIndicadoresTest` y las pruebas de categorías en verde.

### Incremental Delivery

1. US1 → migración y almacenamiento validados.
2. US2 → reglas validadas sin regresión.
3. US3 → FK fuera y competencia solo por Facade validada.
4. US4 → auditoría validada.
5. Polish → suite completa, Pint, análisis estático, frontend y quickstart.

## Notes

- Las tareas de ajuste de pruebas existentes deben limitarse a lo que dependía de la tabla eliminada; no se modifican reglas de negocio ni respuestas esperadas de la API.
- `unidades_ids` pertenece a 01b.4 y no se agrega.
- Comprometer por tarea o grupo lógico; no hacer commit sin que el usuario lo pida.
