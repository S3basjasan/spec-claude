# Quickstart de validación: offboarding

Detalles en [contracts/api-offboarding.md](contracts/api-offboarding.md) y [data-model.md](data-model.md).

## Requisitos previos
- `cd backend && composer install`; las pruebas usan SQLite `:memory:` (no ejecutar `migrate:fresh` sobre la base de desarrollo; la de desarrollo necesita `php artisan migrate` para las tablas nuevas).
- `cd frontend && npm install`. Los E2E usan la base `e2e.sqlite` (`APP_ENV=e2e`) y siembran el tenant 2 con `e2e/support/sembrar-offboarding.php`.

## Escenarios
| # | Resultado | Esperado |
|---|---|---|
| 1 | Formalizar con política publicada | 201; expediente `Programada`, versión de política, tareas + entrevista; vínculo y posición intactos |
| 2 | Sin política / datos inválidos / sin vínculo vigente / ya formalizada | Rechazo con causa distinta; nada creado |
| 3 | Tarea con evidencia sin respaldo | `EVIDENCIA_REQUERIDA` |
| 4 | Fecha efectiva futura | Vínculo vigente; al llegar la fecha el comando cierra asignación y vínculo una sola vez, con tareas pendientes |
| 5 | Reintento, concurrencia, incidencia | Sin duplicados; incidencia sin cambios parciales y reintento manual |
| 6 | Cierre administrativo | Rechazado sin salida efectiva, sin tareas completas o con entrevista sin resolver; confirmado cuando todo se cumple |
| 7 | Entrevista | Guardar → `EnCurso`; confirmar → `Completada`; sin responder = ausente; no se sobrescribe; declinar o «no realizada» con motivo |
| 8 | Permisos | Colaborador solo lo propio; otro tenant 404 idéntico; respuestas solo con permiso y registradas |
| 9 | Análisis | Representación estructurada sin IA; agregados ocultos con menos de 5 |

## Ejecución
```bash
cd backend && php artisan test --compact tests/Feature/GestionPersonal/Offboarding tests/Unit/GestionPersonal/Offboarding
```
```bash
cd frontend && npx vitest run src/modules/gestionPersonal/offboarding && npx playwright test e2e/offboarding.spec.ts
```
