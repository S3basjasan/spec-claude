# Quickstart: validar Actividades formativas

Guía de validación de extremo a extremo. El detalle de endpoints está en [contracts/actividades-api.md](contracts/actividades-api.md) y el
de tablas en [data-model.md](data-model.md). Para trampas del entorno local (OneDrive/Windows con Pest, Vitest y Playwright) ver la
memoria del proyecto.

## Prerrequisitos

- Backend con migraciones y *seeders* al día; el seeder de actividades depende de `PlanFormacionSeeder` y `PersonalSeeder`.
- Usuarios de prueba de `.env.e2e`: administrador `admin@hrms.test` y colaboradora `usuario@hrms.test`.

## Preparación

```bash
cd backend
php artisan migrate:fresh --seed
```

## Pruebas automatizadas

```bash
cd backend
./vendor/bin/pest --filter=Actividad
./vendor/bin/pest            # regresión completa (incluye Plan: progreso y bloqueo de baja)
./vendor/bin/pint --test
```

```bash
cd frontend
npm run lint
npx tsc --noEmit
npx vitest run src/modules/desarrollo/actividades
npm run build
npx playwright test e2e/actividades.spec.ts e2e/plan-formacion.spec.ts e2e/micro-learning.spec.ts
```

## Escenarios de validación manual / E2E

1. **Crear (P1, SC-001)**: como administrador, `/actividades` → «+ Nueva Actividad» → elegir una acción de un plan Ejecutando sin actividad → ver datos heredados en solo lectura → indicar lugar → crear. *Esperado*: fila nueva con código ACT-NNNN; la acción ya no aparece en el selector; una acción Virtual con lugar «sala 3» es rechazada por URL inválida.
2. **Registrar asistencia (P1, SC-002/003)**: abrir «Asistencia» de una actividad En curso → «Marcar todos presentes» → cambiar uno a Ausente → calificar a un Presente con 4 → Guardar. *Esperado*: confirmación solo si se modifica un registro previo; calificación deshabilitada fuera de Presente; observaciones deshabilitadas en Pendiente; al volver un Presente a Ausente la calificación desaparece; el historial muestra anterior/nuevo.
3. **Bloqueos (FR-014/FR-021)**: la vista de asistencia de una actividad Programada o Cancelada, o de un plan Cerrado, aparece deshabilitada con su explicación; la API responde 422.
4. **Colaborador (P1, SC-005/006)**: como `usuario@hrms.test`, `/actividades` → tarjetas en «Próximas actividades» e «Historial» con asistencia (incluido «Pendiente») y calificación, sin observaciones; solo sus actividades; mensaje informativo si no tiene.
5. **Lista y filtros (P2, SC-007)**: año actual por defecto; filtrar por tipo, estado, departamento, competencia, plan y fechas; badge «Asistencia pendiente» solo en Finalizadas con pendientes; no hay botón de eliminar ni de cancelar.
6. **Calificaciones (P2, SC-008)**: pestaña «Calificaciones» → «Por actividad» (promedio sin valor si no hay notas) y «Por participante» (solo con al menos una nota; el detalle lista cada actividad).
7. **Integración con el Plan**: en `/plan-formacion` el progreso del colaborador sube cuando su actividad Finalizada tiene asistencia Presente; quitarlo de la acción con asistencia registrada es rechazado con mensaje claro.
8. **Plan Cerrado (SC-009)**: cerrar el plan → editar la actividad y guardar asistencia dan 422; la UI queda en solo lectura.
9. **Concurrencia**: abrir la asistencia en dos pestañas, guardar en una y luego en la otra → la segunda recibe 409 con el mensaje de recarga.

## Responsive y accesibilidad

Revisar 320, 768 y 1440 px, tema claro y oscuro, navegación por teclado del control Presente / Ausente / Justificado, foco en los
modales y mensajes `role="status"` al guardar.
