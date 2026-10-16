# Quickstart: validar ingresos y reingresos

## Prerrequisitos

- Features 000 a 005 funcionando (`admin@hrms.test`, `usuario@hrms.test`, `admin2@hrms.test`; contraseña de
  `SEED_PASSWORD`).
- Dependencias instaladas en `backend/` y `frontend/`.
- Diseño actualizado (`design/DESIGN.md` y, si aplica, `design/ColaboradorIngreso.dc.html`).
- SQLite (no se requiere PostgreSQL ni `btree_gist`).

## Preparar el backend

```bash
cd backend
php artisan migrate:fresh --seed
php artisan serve
```

Siembra los datos de [data-model.md](data-model.md): EMP-001 Activo, EMP-004 con ingreso programado, EMP-005
Desvinculado, EMP-002 y EMP-003 sin vínculo, y posiciones A (ocupada), B (libre) y C (ingreso futuro). Contrato de la
API en [contracts/ingresos-api.md](contracts/ingresos-api.md).

## Validar la API (Administrador del tenant 1)

1. Sin token → 401; como `usuario@hrms.test`, `POST …/ingresos` y `GET …/opciones-ingreso` → 403.
2. `GET /api/colaboradores/{EMP-002}/opciones-ingreso?fecha=<hoy>` → 4 tipos de contrato y las posiciones libres, sin
   la posición A; con `fecha` pasada anterior al vínculo de EMP-001 → la posición A tampoco aparece (ocupada desde
   hace un año).
3. `POST` EMP-002 con `fecha_ingreso` de hoy, `indefinido` y la posición B → 201; `GET …/situacion-laboral` → `Activo`
   con puesto y unidad; la ficha de GP-01 muestra el mismo estado (GP02-CA01, CA08: sin cuentas nuevas).
4. Repetir el mismo `POST` con la misma clave → 200 y un solo vínculo; con la misma clave y otra posición → 409.
5. `POST` EMP-003 con `fecha_ingreso` de hace 3 meses → 201; `registrado_en` es ahora y `fecha_efectiva` la pasada
   (GP02-CA02).
6. `POST` EMP-004 sobre la posición C con fecha futura → 201 `programado`; hoy `SinVinculoVigente` con
   `proximo_ingreso`; avanzar el reloj del tenant a esa fecha (prueba con reloj fijado) → `Activo` (GP02-CA03).
7. `POST` otra ficha a la posición A (ocupada) → 422 `POSICION_NO_DISPONIBLE` sin filas nuevas (GP02-CA04).
8. `POST` EMP-001 (Activo) con otra posición libre → 422 `VINCULO_SUPERPUESTO` (GP02-CA07).
9. `POST` EMP-005 (Desvinculado) con fecha igual a su salida y posición libre → 201 con un vínculo nuevo; el anterior
   y la ficha siguen intactos (GP02-CA06); con fecha anterior a su salida → 422.
10. `POST` con `fecha_salida`, `estado` o `salario` → 422 `CAMPO_NO_PERMITIDO`; clave de contrato desconocida → 422
    `CONTRATO_NO_HABILITADO`; `PUT`/`PATCH`/`DELETE` → 404/405.
11. Como `admin2@hrms.test`: la ficha o la posición del tenant 1 → 404 uniforme.
12. 004/005: `PATCH` desactivar el puesto o la unidad de una posición suprimida con asignación vigente → rechazo; sin
    asignación → procede. `GET /api/colaboradores?estado=SinVinculoVigente` no incluye a EMP-005.

## Validar concurrencia

```bash
cd backend
vendor/bin/pest tests/Feature/GestionPersonal/Ingresos/ConcurrenciaTest.php
```

Lanza dos procesos que confirman a la vez ingresos incompatibles contra un archivo SQLite temporal; debe haber
exactamente un 201 y un 422 y una sola asignación en la posición.

## Validar el frontend

```bash
cd frontend
npm run dev
```

1. Administrador → ficha sin vínculo → «Registrar ingreso» abre el formulario; Colaborador no ve la acción.
2. Elegir fecha, contrato y posición; el selector muestra puesto, unidad y jefatura («No registrado»); cambiar la fecha
   recarga opciones y avisa si descarta la selección.
3. Fecha futura muestra «Ingreso programado»; confirmar vuelve al detalle con el estado y la trayectoria nuevos, sin
   cambio previo a la confirmación.
4. Posición ocupada o vínculo superpuesto: Alert con el motivo y formulario conservado; Estructura caída: «No se pudo
   verificar la posición» con Reintentar.
5. 320 px y 1440 px sin desplazamiento horizontal; tema claro y oscuro.

## Puertas de calidad

```bash
cd backend && vendor/bin/pest && vendor/bin/pint --test
cd ../frontend && npm run lint && npx tsc -b && npm test -- --run && npm run build && npx playwright test ingresos
```
