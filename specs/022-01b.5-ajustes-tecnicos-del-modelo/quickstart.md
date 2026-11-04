# Quickstart: validar 01b.5

Prerrequisitos: dependencias del backend instaladas; ejecutar desde `backend/`. Las pruebas usan SQLite en memoria.

## 1. Línea base (antes de cambiar nada)

```bash
php artisan test --compact tests/Feature/Compensaciones tests/Unit/Compensaciones
```

Resultado esperado: todo en verde. Guardar el conteo como referencia (criterio CA-06).

## 2. Migración de datos (CA-01, CA-04, CA-05)

```bash
php artisan test --compact --filter=MigracionIndicadores
```

Esperado: categorías con 1 y 5 indicadores conservan textos y orden en ida y vuelta; `down()` recrea la tabla legacy y la FK; `up()` deja la columna `indicadores`, sin tabla `indicadores_reconocimiento` y sin FK de `competencia_id`; `down()` se niega a actuar si hay competencias inexistentes.

Comprobación manual opcional sobre una base de desarrollo con datos de inc-01:

```bash
php artisan migrate
php artisan migrate:rollback --step=1
php artisan migrate
```

## 3. Reglas y reemplazo de lista (CA-02, CA-03, CA-07)

```bash
php artisan test --compact tests/Feature/Compensaciones/CrearCategoriaTest.php tests/Feature/Compensaciones/ActualizarCategoriaTest.php
```

Esperado: 0 y 6 indicadores, 4 y 251 caracteres, HTML y repetidos con otras mayúsculas o tildes → 422; guardar reemplaza la lista y la auditoría registra el antes y el después como lista JSON.

## 4. Suite completa y calidad (CA-06)

```bash
php artisan test --compact
vendor/bin/pint --test
```

Esperado: misma cantidad de pruebas de inc-01 en verde que en la línea base, solo con ajustes en factory, seeder y las pruebas que usaban la tabla eliminada. Ejecutar además el análisis estático configurado en el proyecto.

## 5. Frontend (sin cambios)

Desde `frontend/`: pruebas Vitest y el E2E de categorías deben seguir pasando sin modificaciones.

Referencias: [data-model.md](data-model.md), [contracts/README.md](contracts/README.md), [research.md](research.md).
