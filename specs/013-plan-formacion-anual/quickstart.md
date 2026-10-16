# Quickstart: validar el Plan de formación anual

Guía de validación de extremo a extremo. Los detalles de datos y endpoints están en [data-model.md](data-model.md) y [contracts/plan-api.md](contracts/plan-api.md).

## Prerrequisitos

- Backend y frontend instalados (como en 005 y 006); SQLite como base.
- Seeders de Estructura, Gestión de Personal, catálogo (cursos) y `PlanFormacionSeeder`.
- Un usuario Administrador (Gestor de Talento) y otro usuario con ficha de colaborador inscrito en acciones del seeder.

## Preparación

```bash
cd backend
php artisan migrate:fresh --seed
php artisan serve
```

```bash
cd frontend
npm run dev
```

## Pruebas automatizadas

```bash
cd backend && ./vendor/bin/pest --filter=Plan && ./vendor/bin/pint --test
cd frontend && npm run lint && npx tsc --noEmit && npm test && npm run build
```

El E2E del flujo principal usa el entorno `e2e` (adaptadores de prueba solo en ese entorno), con la herramienta E2E que ya use el repositorio.

## Escenarios de validación manual

1. **Crear un Borrador** (historia 1): como Administrador, «Nuevo plan» → nombre y año → dos acciones (una desde un curso del catálogo, que propone tipo, formador y competencias) → participantes y aprobador → «Crear plan en Borrador». Esperado: código `PLAN-000N`, estado Borrador. Un plan 2026 con el mismo nombre (cambiando mayúsculas o espacios laterales) se rechaza.
2. **Completitud y revisión** (historia 2): enviar a revisión con una acción sin participantes → rechazo con lista de faltantes. Completar y enviar → estado Revisión, de solo lectura.
3. **Aprobación**: con un Administrador **distinto** del aprobador, Aprobar/Devolver → 403. Con el aprobador: Devolver sin motivo → 422; con motivo → Borrador. Reenviar y Aprobar → Aprobado con fecha de aprobación.
4. **Aprobador inválido** (FR-016a): en un plan en Revisión, reasignar el aprobador con cualquier Administrador → el plan sigue en Revisión y el historial muestra el cambio.
5. **Ejecución y cierre** (historia 3): Iniciar ejecución; en una acción cambiar participantes (exceder capacidad → 422) y cancelar otra con motivo; cerrar con acciones En curso → 422; con todas Finalizadas o Canceladas → Cerrado. No existe ninguna opción de eliminar.
6. **Cruce de fechas**: inscribir al mismo colaborador en dos acciones solapadas → aviso junto al participante y la asignación se permite.
7. **Calendario y Lista** (historia 4): `/plan-formacion/acciones`; cambiar de pestaña conserva los filtros (estado del plan, tipo, competencia, fechas); las canceladas llevan el badge «Cancelada».
8. **Colaborador** (historia 5): ver solo planes Aprobado, Ejecutando y Cerrado; tarjeta de acción cancelada con su motivo; barra «% del plan completado» y tres «Próximas actividades»; sin acciones → mensaje informativo. Un colaborador no puede abrir `GET /api/planes/{id}` (403).
9. **Historial** (historia 6): el detalle muestra cambios de estado y, desde la aprobación, cambios de participantes y cancelaciones con usuario, fecha y detalle.
10. **Desde la DNF** (historia 7): en `/dnf/:id` pulsar «Generar Plan de Formación» → se crea `Plan DNF DNF-000N` en Borrador con una acción por competencia con brecha y los colaboradores como participantes; el detalle enlaza al origen.

## Resultado esperado

Todas las pruebas pasan, los 10 escenarios se cumplen y ninguna pantalla muestra datos de otro tenant ni de otro colaborador.
