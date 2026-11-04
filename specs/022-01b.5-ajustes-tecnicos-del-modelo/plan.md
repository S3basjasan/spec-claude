# Implementation Plan: Ajustes técnicos del modelo de categorías (01b.5)

**Branch**: `022-01b.5-ajustes-tecnicos-del-modelo` | **Date**: 2026-11-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/022-01b.5-ajustes-tecnicos-del-modelo/spec.md`. Plan fuente: `01b.5-ajustes-tecnicos-del-modelo.md` (carpeta de planes de inc-01b) y `modelo-de-datos.dbml`.

## Summary

Refactorización sin cambio visible: los indicadores de cada categoría pasan de la tabla `indicadores_reconocimiento` (borrada y reinsertada en cada edición) a una columna JSON `indicadores` dentro de `categorias_reconocimiento`, y se quita la llave foránea de `competencia_id` hacia `competencias` (la competencia ya se valida solo por `CompetenciasPort` → Facade de Estructura). Una migración nueva y reversible copia los datos; la de inc-01 no se toca. El Dominio sigue validando las reglas (1 a 5, 5 a 250 caracteres, sin repetidos sin distinguir mayúsculas ni tildes). Las pruebas de inc-01 son el criterio de aceptación; solo se ajustan las que dependían de la tabla eliminada.

## Technical Context

**Language/Version**: PHP 8.3+ (`composer.json: ^8.3`; entorno con 8.4), Laravel 13 (`^13.17`)

**Primary Dependencies**: Eloquent, Pest 4; sin dependencias nuevas

**Storage**: SQLite (desarrollo y pruebas, `:memory:`); el modelo de datos objetivo es PostgreSQL con JSON, por lo que la migración no usa nada específico de SQLite

**Testing**: Pest (Feature y Unit); frontend con Vitest y E2E existentes, sin cambios

**Target Platform**: API web (Laravel) + SPA React existente

**Project Type**: Aplicación web (`backend/` + `frontend/`); solo cambia `backend/`

**Performance Goals**: sin cambios; el listado no carga indicadores y deja de hacer una consulta de relación (`with('indicadores')`) en detalle, activas y por ids. La prueba existente `RendimientoListadoTest` debe seguir pasando

**Constraints**: API, respuestas y pantallas idénticas; migración reversible sin pérdida de datos; no editar la migración original

**Scale/Scope**: catálogo de decenas a cientos de categorías (500 en la prueba de rendimiento), 1 a 5 indicadores cada una

Sin `NEEDS CLARIFICATION`; las decisiones abiertas se resolvieron en [research.md](research.md).

## Constitution Check

*GATE: debe pasar antes de la investigación y se reevalúa tras el diseño.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | ✅ Dominio (`Categoria`, `Indicador`, `Normalizador`) sigue sin depender de Laravel; el cambio vive en Infraestructura (modelo, repositorio, migración). Competencia por `CompetenciasPort`, sin acceso a tablas de otro módulo. Controladores y rutas sin tocar. |
| II. Frontend | ✅ Sin cambios; Vitest y E2E existentes deben seguir pasando. |
| III. Diseño | ✅ No hay pantallas nuevas ni modificadas. |
| IV. Calidad y dependencias | ✅ Sin dependencias nuevas. Hay cobertura automatizada (migración ida y vuelta, reglas, auditoría). Pint, análisis estático y Pest deben pasar. |

Una desviación puntual del principio I (lectura de `competencias` solo en el `down()` de la migración), justificada y registrada en Complexity Tracking. Sin otras violaciones. Reevaluación tras el diseño (Fase 1): sin cambios, ✅. Análisis estático: no está configurado en `backend/composer.json` (solo Pint) y no se instala en esta feature.

## Project Structure

### Documentation (this feature)

```text
specs/022-01b.5-ajustes-tecnicos-del-modelo/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── README.md        # la API no cambia: referencia a los contratos de inc-01
├── checklists/requirements.md
└── tasks.md             # lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── database/
│   ├── migrations/
│   │   └── 2026_11_03_000001_move_indicadores_into_categorias_reconocimiento.php   # NUEVA
│   ├── factories/CategoriaReconocimientoFactory.php        # crea la lista en la columna
│   └── seeders/CategoriasReconocimientoSeeder.php          # siembra la lista en la columna
├── app/Modules/Compensaciones/Infrastructure/Persistence/Eloquent/
│   ├── Models/CategoriaReconocimientoModel.php             # cast array, sin relación
│   ├── Models/IndicadorReconocimientoModel.php             # SE ELIMINA
│   └── Repositories/EloquentCategoriaRepository.php        # sin reemplazarIndicadores()
└── tests/
    ├── Feature/Compensaciones/                              # ajustes puntuales + prueba de migración nueva
    └── Unit/Compensaciones/DominioTest.php                  # sin cambios esperados
```

**Structure Decision**: aplicación web existente; el cambio se limita a persistencia del módulo `Compensaciones` en `backend/`. Dominio, Aplicación, Presentación y `frontend/` no se modifican (verificado: `Categoria`, `Indicador`, `AuditoriaDeCategoria`, `GuardarCategoriaRequest` y `CategoriaResource` no dependen de la tabla ni del modelo de indicadores).

## Diseño

1. **Migración nueva** (`up`): agrega `indicadores` JSON; por cada categoría copia los `texto` de `indicadores_reconocimiento` ordenados por `orden` como lista JSON (`[]` si no tuviera); quita la FK de `competencia_id` (conservando la columna y su tipo); elimina `indicadores_reconocimiento`. (`down`): recrea la tabla con el esquema original, vuelca la lista (orden = posición 1..n, `texto_normalizado` recalculado con `Normalizador`), quita la columna `indicadores` y restaura la FK; si hay `competencia_id` sin competencia existente, aborta con mensaje claro antes de tocar nada. Detalles y alternativas en [research.md](research.md).
2. **Modelo**: `indicadores` en `$fillable` y cast `array`; se retira la relación `indicadores()`; se elimina `IndicadorReconocimientoModel`.
3. **Repositorio**:
   - `crear`: incluye `indicadores` (lista de textos) en el `create`.
   - `actualizarSiVigente`: `indicadores` va **en el mismo `UPDATE` condicional** que el resto de campos. `Model::query()->update()` no aplica casts, así que se codifica con `json_encode(..., JSON_UNESCAPED_UNICODE)`, igual que `Categoria::indicadoresComoJson()`. Así, el reemplazo de la lista es atómico y queda bajo el mismo token de concurrencia (antes se hacía después, en pasos separados).
   - Se eliminan `reemplazarIndicadores()` y los `with('indicadores')`; `aEntidad` arma los `Indicador` desde el arreglo (`orden = posición + 1`). El listado (`buscar`) sigue sin indicadores.
4. **Dominio y Aplicación**: sin cambios. La unicidad por texto normalizado deja de tener respaldo de índice único en la base de datos y descansa en Dominio y `GuardarCategoriaRequest` (ya presentes, CA-02).
5. **Competencia**: sin cambios de código; se verifica por prueba que el guardado con competencia inexistente sigue devolviendo el mismo error y que el esquema ya no declara la FK.
6. **Pruebas** (Pest): ver [quickstart.md](quickstart.md). Orden de trabajo: línea base verde → migración + prueba de ida y vuelta → modelo/repositorio → factory, seeder y ajustes de pruebas → suite completa, Pint y análisis estático.

## Complexity Tracking

Sin violaciones de los principios; una desviación puntual registrada:

| Desviación | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| El `down()` de la migración nueva lee la tabla `competencias` (de Estructura) desde Compensaciones (principio I: un módulo no accede a tablas de otro) | Solo para comprobar que no hay `competencia_id` huérfanos antes de restaurar la llave foránea original de inc-01. Se ejecuta únicamente al revertir (nunca en `up()` ni en la operación normal) y únicamente en el repositorio de prueba (el plan fuente indica que en el proyecto real inc-01 ya nace con este modelo y esta migración no existe) | Restaurar la llave sin comprobar fallaría a mitad de la reversión o dejaría datos inconsistentes; borrar o anular los huérfanos perdería datos (research R6). La validación de la competencia en la operación normal sigue siendo solo por `CompetenciasPort` |
