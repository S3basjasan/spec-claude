# Quickstart: Reportes de evaluación de resultados

Guía de validación de la feature. Los detalles de campos y respuestas están en [data-model.md](data-model.md) y [contracts/reportes-api.md](contracts/reportes-api.md).

## Prerrequisitos

- Entorno local según la memoria del proyecto: `attrib -R backend\bootstrap\cache` si Pest/artisan fallan por la caché; `backend/.env.e2e` con `APP_ENV=e2e` para Playwright.
- Dependencias instaladas (`composer install`, `npm install` en `frontend/`). No hay dependencias nuevas.
- Base de datos con las features 000–017: `php artisan migrate` y los seeders de Usuarios, Personal, Plan de Formación, Micro-Learning, DNF y Actividades. En esta máquina `IngresosSeeder` falla; sembrar cada seeder por separado sin él.

## 1. Migrar y sembrar

```bash
cd backend
php artisan migrate
php artisan db:seed --class=ReportesSeeder
```

Resultado esperado: tablas `encuestas_satisfaccion`, `seguimientos_impacto` e `impactos_accion` creadas; el seeder deja encuestas, seguimientos de los tres hitos (con un caso de alerta de reingreso) y evaluaciones de impacto, una con costo cero, y un colaborador evaluado en dos DNF con una brecha cerrada.

## 2. Pruebas backend

```bash
cd backend
php vendor/bin/pest tests/Unit/Desarrollo/Reportes tests/Feature/Desarrollo/Reportes
php vendor/bin/pint --test
```

Cubren: período anterior, variaciones con anterior nulo o cero, prorrateo por departamento, máquina de brechas (detectada, cerrada, reabierta, `SinEvaluacion`), activas por mes, alerta de reingreso (3 respuestas, caída de 1), ventanas de 30 y 15 días, ROI con costo cero, inmutabilidad (sin PUT/DELETE), permisos 401/403, errores 404/422 y que los comentarios no incluyen al autor.

## 3. Verificación manual de la API (token de administrador)

```bash
curl -H "Authorization: Bearer $TOKEN" "http://localhost:8000/api/reportes/dashboard"
curl -H "Authorization: Bearer $TOKEN" "http://localhost:8000/api/reportes/dashboard?departamento_id=4&tipo=Presencial"
curl -H "Authorization: Bearer $TOKEN" "http://localhost:8000/api/reportes/seguimiento"
curl -H "Authorization: Bearer $TOKEN" -o reportes.xlsx "http://localhost:8000/api/reportes/exportar?formato=xlsx"
```

Resultado esperado: el dashboard devuelve los cinco indicadores con comparación contra el período anterior; con filtros los montos se prorratean; el seguimiento marca `alerta_reingreso` en la actividad sembrada; el `.xlsx` abre con cuatro hojas.

## 4. Verificación manual del colaborador (token de colaborador)

```bash
curl -H "Authorization: Bearer $TOKEN_COL" "http://localhost:8000/api/reportes/mi-progreso"
curl -X POST -H "Authorization: Bearer $TOKEN_COL" -H "Content-Type: application/json" -d '{"actividad_id":5,"calificacion":4}' "http://localhost:8000/api/reportes/encuestas"
```

Resultado esperado: `mi-progreso` muestra resumen, evolución, certificados, próximas acciones y pendientes; la encuesta responde 201 y repetirla responde 422 `ya_respondida`; las rutas de administrador responden 403.

## 5. Pruebas y calidad del frontend

```bash
cd frontend
npx eslint src/modules/desarrollo/reportes
npx tsc -b
npx vitest run src/modules/desarrollo/reportes --testTimeout=30000 --maxWorkers=3
npm run build
```

Resultado esperado para la feature: sin errores. `tsc -b` y `build` conservan los 18 errores preexistentes de `estructura/unidades` (línea base, no de esta feature).

## 6. E2E del flujo principal

```bash
cd frontend
npx playwright test e2e/reportes.spec.ts
```

El E2E cubre: el administrador abre `/reportes`, aplica el filtro de departamento, ve las tarjetas con comparación, abre los comentarios de una actividad (sin autor), registra una evaluación de impacto y ve el ROI, y descarga el Excel; el colaborador abre `/reportes`, ve «Mi progreso», responde una encuesta pendiente y la ve desaparecer.

## 7. Rendimiento

Con el volumen objetivo (1 000 actividades, 20 000 asistencias, 5 000 completaciones, 300 DNF) el dashboard anual debe responder en menos de 5 s (SC-001). Si no, aplicar la caché de corta duración descrita en [research.md](research.md#r7-caché).
