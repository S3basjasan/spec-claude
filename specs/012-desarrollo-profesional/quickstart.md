# Quickstart: validar GP-03

**Prerrequisitos**: dependencias instaladas, BD migrada y sembrada (`php artisan migrate --seed`), una ficha existente (PersonalSeeder). Administrador y un Colaborador con ficha asociada y permiso de ficha.

## Pruebas automáticas

```bash
cd backend && php artisan test --compact --filter=Desarrollo
cd backend && vendor/bin/pint --dirty --format agent
cd frontend && npm run lint && npx tsc --noEmit && npm run test -- desarrollo && npm run build
cd frontend && npx playwright test e2e/desarrollo.spec.ts
```

## Escenarios manuales (mapa a criterios de aceptación)

| Escenario | Pasos | Resultado esperado |
|---|---|---|
| GP03-CA01 | Abrir una ficha nueva → pestaña Desarrollo | «Formación académica no registrada», «Experiencia anterior no registrada», movilidad «No informada»; ningún porcentaje ni brecha |
| GP03-CA02 | Agregar formación con los 4 campos obligatorios, sin fechas | Se guarda y aparece en la lista |
| GP03-CA03 | Formación y experiencia con finalización anterior al inicio; inicio futuro; finalización futura en experiencia | Error junto a la fecha; no cambia ninguna otra fila. Finalización futura en formación `En curso` se acepta |
| GP03-CA04 | Experiencia sin finalización | Se muestra «No registrado» y no «actual»/«vigente»; dos experiencias superpuestas se aceptan |
| GP03-CA05 | Guardar movilidad «Disponible» con observaciones | Se guarda con fecha de actualización; no se crean vínculos, asignaciones ni movimientos; repetir el mismo valor no cambia la fecha |
| GP03-CA06 | Iniciar sesión como Colaborador → Mi ficha → Desarrollo; llamar a `PATCH …/formaciones/{id}` y `GET /api/colaboradores/{otra}/desarrollo` | Solo lectura, sin botones; escritura y ajena = 403 sin revelar existencia |
| Concurrencia | Abrir la misma formación en dos pestañas, guardar en ambas | La segunda recibe «La información cambió» con «Actualizar datos»; lo escrito se conserva |
| Aislamiento | Con dos tenants que comparten ids numéricos, pedir filas del otro tenant | 404 idéntico al de inexistente |
| Error de carga | Simular 500 en `/desarrollo` | Error con Reintentar; el resto de la ficha sigue visible |

Detalle de rutas y respuestas: [contracts/api-desarrollo.md](contracts/api-desarrollo.md); datos: [data-model.md](data-model.md).
