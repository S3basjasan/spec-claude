# Quickstart: validación del Plan de desarrollo individual (PDI)

Guía de validación de punta a punta. Datos en [data-model.md](data-model.md), endpoints en [contracts/api.md](contracts/api.md), Facades en [contracts/facade.md](contracts/facade.md) y pantallas en [contracts/ui.md](contracts/ui.md). Trampas de entorno (OneDrive, Pest, Vitest, Playwright): ver la memoria «Entorno local de pruebas» (`attrib -R backend\bootstrap\cache`, `backend/.env.e2e`, timeouts de Vitest).

## Prerrequisitos
- Backend y frontend instalados; base SQLite migrada.
- Seeders previos: Estructura, Gestión de Personal, Plan de Formación, Actividades, DNF y Adecuación (el E2E de esta feature **no** depende de `IngresosSeeder`; prepara colaboradores con vínculo vigente).
- Banderas por defecto: `DESARROLLO_PDI_CONOCIMIENTOS_CON_GRADO=false`.

## Preparación
```bash
cd backend && php artisan migrate && php artisan db:seed --class=PdisSeeder
```
El seeder deja cinco planes: Mejora Borrador sin acuerdo; Carrera Borrador con acuerdo del responsable; Talento Acordado con una acción vinculada y un hito vencido; Mejora Cerrado «Brecha cerrada»; Carrera Cerrado «Parcial».

## Pruebas automáticas
```bash
cd backend && ./vendor/bin/pest --filter=Pdi && ./vendor/bin/pint --test
```
```bash
cd frontend && npx vitest run src/modules/desarrollo/pdi --testTimeout=30000 && npm run lint && npx tsc --noEmit && npm run build
```
E2E del flujo principal (administrador crea y acuerda → colaborador acepta → «Mi plan»):
```bash
cd frontend && npx playwright test pdi
```

## Escenarios manuales
1. **Permisos y privacidad** — Con el colaborador, `GET /api/pdi` y `POST /api/pdi` devuelven 403; `GET /api/pdi/mi-plan` devuelve solo lo propio; `GET /api/pdi/{id}` de otra persona devuelve 404. Sin sesión, 401. No existe ningún `DELETE`.
2. **Crear con precarga (P1)** — Administrador: `/pdi/nuevo` → colaborador con DNF con brechas → tipo Mejora → los compromisos aparecen con niveles precargados → quitar uno y agregar una Meta → agregar una acción por compromiso → paso 3 con responsable por defecto = quien crea, periodo e hito → «Crear PDI (Borrador)» → código `PDI-000N`, estado Borrador.
3. **Unicidad** — Crear otro Mejora del mismo colaborador ⇒ 422 con el código del plan abierto; tras cerrar el primero, se permite.
4. **Carrera y Talento** — Sin puesto objetivo no se avanza; con inscripción Activa se sugiere el nivel objetivo; la precarga viene de la última adecuación (incluye Experiencia como compromiso de descripción).
5. **Acuerdo en dos pasos (aclaración 1)** — El colaborador **no** ve un Borrador sin acuerdo del responsable; tras «Confirmar acuerdo» el plan aparece en «Mi plan de desarrollo» con **Aceptar plan** y **Devolver con comentario**; `POST /aceptar` antes del acuerdo ⇒ 422. Al aceptar, el plan pasa a **Acordado**.
6. **Devolver y editar** — Devolver con comentario: sigue Borrador, acuerdos borrados, el plan desaparece de «Mi plan» y el administrador ve el comentario. Editar un Borrador con acuerdo borra el acuerdo y el plan deja de ser visible al colaborador.
7. **Completitud** — «Confirmar acuerdo» con un compromiso sin acciones ⇒ 422 con el detalle de lo que falta.
8. **Devolver un Acordado** — Sin motivo ⇒ 422; con motivo pasa a Borrador, borra acuerdos y registra el historial.
9. **Cambiar responsable (aclaración 2)** — En un plan Acordado, cambiar el responsable a otro administrador: el plan sigue Acordado, los acuerdos se conservan, historial `Responsable`; a un usuario sin rol de administrador ⇒ 422; en un plan Cerrado ⇒ 422.
10. **Seguimiento** — En un Acordado: cambiar el estado de una acción sin vínculo y marcar un hito Realizado quedan en el historial; intentar cambiar fechas/estructura se rechaza; el avance = Cumplidas ÷ no Canceladas; un hito Pendiente con fecha anterior a hoy se marca vencido y aparece el indicador en la lista.
11. **Vincular** — En una acción Curso: el selector solo lista acciones de planes Aprobado/Ejecutando con el colaborador como participante; vincular una acción de actividad Finalizada con asistencia Presente ⇒ `Cumplida` de solo lectura; cancelar la acción formativa ⇒ `Cancelada`; desvincular devuelve el estado manual; Coaching/Autodesarrollo/Nueva responsabilidad/Otra no ofrecen vincular.
12. **Cerrar** — Con acciones abiertas ⇒ 422 y la cantidad; con todas Cumplidas/Canceladas, el modal exige resultado por compromiso: todos Cerrada ⇒ «Brecha cerrada»; ninguno ⇒ «No cerrada»; mixto ⇒ «Parcial». El plan queda en solo lectura; en Carrera/Talento se ofrece «Generar nueva adecuación».
13. **Congelamiento (aclaración 3)** — Tras cerrar, cambiar la acción formativa vinculada (p. ej. cancelarla) no modifica el estado de la acción en el plan Cerrado.
14. **Gantt** — Solo acciones de planes Acordado; filtros por tipo, responsable y departamento; estado vacío sin planes Acordados.
15. **Brechas sin plan** — Un colaborador con brecha activa de DNF y sin plan Mejora abierto aparece en el indicador; deja de aparecer al crearle el plan.
16. **Mi plan de desarrollo** — Tarjetas con tipo, estado, avance y periodo; «Qué sigue» ordenado por fecha con enlace a la actividad en acciones vinculadas; estado vacío sin planes; plan Cerrado en solo lectura.
17. **Concurrencia** — Con dos pestañas, guardar el mismo plan con una `version` vieja ⇒ 409 y mensaje de recarga.
18. **Adecuación (020)** — «Crear plan de desarrollo» e «Incluir en Programa de Talentos» quedan habilitados con brechas y llevan a `/pdi/nuevo` con colaborador, tipo y puesto objetivo preseleccionados; con un plan abierto del tipo se muestran deshabilitados con su motivo.
19. **Auditoría** — El historial registra creación, cada cambio de estado, acuerdo, aceptación, devolución (con motivo/comentario), cambio de responsable, vinculación/desvinculación y cada avance, con usuario, fecha, valor anterior y nuevo.
20. **Accesibilidad y responsive** — Teclado completo en pasos, pestañas, modales y tablas; sin scroll horizontal entre 320 y 1440 px; temas claro y oscuro.

## Resultado esperado
Pest, Pint, análisis estático, ESLint, TypeScript, Vitest, build y el E2E pasan; los 20 escenarios se cumplen; la implementación coincide con los tableros `design/PDI*.dc.html` salvo las diferencias registradas en `plan.md` y `design/DESIGN.md`.
