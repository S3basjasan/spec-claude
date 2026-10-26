# Quickstart de validación: movimientos y novedades de personal

Guía de verificación de extremo a extremo. Los detalles de campos y errores están en [contracts/api-movimientos.md](contracts/api-movimientos.md) y [data-model.md](data-model.md).

## Requisitos previos

- Backend: `cd backend && composer install`. Las pruebas usan SQLite en memoria (`:memory:`); **no** ejecutar `migrate:fresh` contra la base de desarrollo.
- Frontend: `cd frontend && npm install`.
- La base de desarrollo necesita las migraciones nuevas: `php artisan migrate` (solo agrega tablas y una columna nullable).
- Datos de ejemplo: `PersonalSeeder` ya crea las políticas de ejemplo de la feature 016. Con una política `Permisos` vigente y `disponible = true`, el flujo del Colaborador queda habilitado.

## Escenarios (cada uno prueba un resultado independiente)

| # | Resultado | Cómo comprobarlo | Esperado |
|---|---|---|---|
| 1 | Solicitud registrada (US1) | Presentar un Permiso con política vigente | 201, estado `Presentada`, versión y condiciones conservadas, ficha y asignaciones intactas |
| 2 | Política ausente o apagada (US1.4) | Presentar sin versión publicada, con `disponible = false` y con la consulta fallando | `POLITICA_SIN_CONFIGURAR`, `POLITICA_NO_ADMITE_SOLICITUDES` y `POLITICA_NO_DISPONIBLE`, sin fila creada |
| 3 | Anticipación en días calendario (US1.2/3) | Vacaciones que incumplen; anticipación 0 con inicio hoy y con inicio ayer | rechazo con `ANTICIPACION_INSUFICIENTE`; hoy aceptado; ayer `FECHA_PASADA` |
| 4 | Decisión fundamentada (US2) | Pasar a revisión, aprobar y rechazar con razón | transiciones con actor, momento y justificación; el Colaborador ve solo el resultado comunicable |
| 5 | Cruce de períodos (US2.2b) | Aprobar una novedad cuyo período se cruza | `CRUCE_SIN_RECONOCER` sin `reconoce_cruce`; con él, aprobada y reconocimiento con autor y momento |
| 6 | Ausencia informada (US3) | Informar una ausencia pasada; intentar una futura | `Informada`, nunca `Aprobada`; la futura `AUSENCIA_FUTURA` |
| 7 | Movimiento sin evaluación (US4) | Proponer un ascenso y aprobarlo con el adaptador por defecto | 422 `EVALUACION_NO_VERIFICABLE`; asignación sin cambio; sigue pudiendo rechazarse |
| 8 | Movimiento efectivo coherente (US5) | Con un evaluador de prueba válido, aprobar con fecha futura, avanzar el reloj y ejecutar `php artisan movimientos:aplicar-programados` | `Programada` hasta la fecha; luego `Aplicada`, asignación anterior cerrada y nueva abierta, hecho en la trayectoria |
| 9 | Incidencia sin cambio parcial (US5.3) | Ocupar el destino antes de la fecha efectiva | `IncidenciaDeAplicacion`, sin asignación nueva; reintento manual aplica una sola vez |
| 10 | Aislamiento y permisos (US2.5) | Colaborador y usuario de otro tenant sobre solicitudes ajenas; Colaborador intentando proponer/aprobar/aplicar | 404 indistinguible del inexistente; 403 en rutas de Administrador |
| 11 | Concurrencia e idempotencia | Misma `Idempotency-Key` dos veces; dos aprobaciones simultáneas; dos movimientos del mismo colaborador | una sola fila/transición; una sola prospera |
| 12 | Retiro (US2.9) | Retirar una novedad presentada; intentar retirar una aprobada | `Retirada` con constancia; la aprobada rechaza el retiro |

## Ejecución

```bash
cd backend && php artisan test --compact tests/Feature/GestionPersonal/Movimientos tests/Unit/GestionPersonal/Movimientos
```

```bash
cd backend && vendor/bin/pint --dirty --format agent
```

```bash
cd frontend && npx vitest run src/modules/gestionPersonal/movimientos && npx tsc -b && npx eslint src/modules/gestionPersonal/movimientos
```

```bash
cd frontend && npx playwright test e2e/movimientos.spec.ts
```

El E2E usa la base `database/e2e.sqlite` (nunca la de desarrollo): `playwright.config.ts` envía `APP_ENV=e2e` al servidor porque `artisan serve --env=e2e` solo reenvía esa variable (sin ella el servidor leía `backend/.env`). Los datos de partida de `e2e/movimientos.spec.ts` se siembran con `e2e/support/sembrar-movimientos.php` solo en el tenant 2, para no alterar lo que esperan los E2E del tenant 1.

## Verificación manual de la interfaz

1. Entrar como Administrador → «Movimientos y novedades»: el listado muestra los conteos (pendientes, programados, incidencias) y filtros en la URL.
2. «Proponer movimiento»: elegir colaborador, destino, fecha efectiva y justificación; el detalle muestra «Evaluación no disponible» y **no** muestra el botón «Aprobar» mientras no exista evaluación verificable.
3. Entrar como Colaborador → «Mis solicitudes»: presentar un permiso viendo antes las condiciones y la advertencia de que se fija una versión de política; consultar su estado.
