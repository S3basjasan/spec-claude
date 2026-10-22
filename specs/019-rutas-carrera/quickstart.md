# Quickstart: validar Rutas y planes de carrera

Guía de validación de extremo a extremo. Contratos en [contracts/rutas-api.md](contracts/rutas-api.md) y modelo en [data-model.md](data-model.md).

## Prerrequisitos

- Backend: `cd backend && composer install && php artisan migrate:fresh --seed` (el `RutasSeeder` va después de `ReportesSeeder`).
- Frontend: `cd frontend && npm install`.
- Usuarios sembrados por `UsuariosInicialesSeeder`: un administrador y un colaborador con ficha en `PersonalSeeder`. Para inscribir y para que la conciliación de bajas no retire las inscripciones sembradas, los colaboradores deben tener un vínculo laboral vigente (en el entorno `e2e` lo cumplen las tres fichas de prueba).
- Entorno Windows/OneDrive: ver las notas de la memoria del proyecto antes de correr Pest, Vitest y Playwright.

## Pruebas automatizadas

```bash
cd backend && php artisan test --filter=Rutas && vendor/bin/pint --test
cd frontend && npm run lint && npx tsc --noEmit && npx vitest run src/modules/desarrollo/rutas && npm run build
cd frontend && npx playwright test e2e/rutas.spec.ts
```

## Escenarios manuales

1. **Crear y publicar** (Administrador): «Nueva ruta» → tipo Especialista → agregar 3 niveles con puestos distintos. Verificar código `CAR-000N`, diferencial de **competencias** y de años de experiencia en el detalle de los niveles 2 y 3, y que la tarjeta «Conocimientos» aparece como «Informativo» (Formación y Capacitación del puesto) sin grado ni diferencial; y que publicar con un solo nivel se rechaza. Publicar con 2+ niveles → estado Vigente.
2. **Nombre duplicado**: crear otra ruta con el mismo nombre en otras mayúsculas y con espacios extremos → rechazo.
3. **Interáreas**: elegir tipo Interáreas → el campo Departamento no aplica.
4. **Inscribir**: pestaña Participantes → «Inscribir colaborador» → nivel actual 1, objetivo 3. Intentar otra inscripción del mismo colaborador en la misma ruta → rechazo; objetivo igual o anterior al actual → rechazo.
5. **Nivel en uso**: intentar quitar el nivel 3 o cambiar su puesto → bloqueado; editar nombre y experiencia → permitido.
6. **Nivel alcanzado**: registrar nivel alcanzado → actual pasa a 3 y, al ser el último, la inscripción queda Finalizada.
7. **Retirar**: retirar otra inscripción sin motivo → no se confirma; con motivo → Retirada.
8. **Archivar con inscritos**: pide confirmación con la cantidad; la ruta conserva sus inscripciones y puede volver a Vigente.
9. **Cantera e historial**: la cantera cuenta solo inscripciones Activas por nivel actual; la pestaña Historial muestra cada cambio con usuario, fecha y valores.
10. **Baja**: dar de baja a un colaborador inscrito en Personal y abrir la ruta → su inscripción pasa a Retirada con motivo «Baja del colaborador».
11. **Colaborador**: entrar como colaborador inscrito → «Mi ruta de carrera» muestra escalera con nivel actual y objetivo, detalle del siguiente nivel, aviso «guía, no promesa» y rutas disponibles; sin inscripciones → estado vacío; no ve acciones de gestión ni la inscripción de otra persona.
12. **Permisos**: sin sesión → 401; colaborador contra `POST /api/rutas` → 403; administrador contra `GET /api/rutas/mi-ruta` → 403.
13. **Facade**: `DesarrolloFacade::inscripcionesActivas` y `obtenerNivel` devuelven los datos del contrato (cubierto por prueba Feature).
14. **Conocimientos informativos**: en el detalle de un nivel, la tarjeta «Conocimientos» muestra solo texto de Formación y Capacitación con la etiqueta «Informativo» (sin grado ni diferencial); con `desarrollo.conocimientos_con_grado = false` no hay otra rama.
15. **Puesto inactivo**: desactivar en Estructura el puesto de un nivel de una ruta Vigente → el nivel muestra la advertencia, la ruta sigue Vigente y no puede volver a publicarse (tras archivarla) hasta cambiar el puesto.
16. **Fuente caída**: con Estructura no disponible, el detalle de la ruta muestra los niveles propios, un aviso con «Reintentar» y ningún requisito inventado; con Personal caído, la conciliación de bajas no retira a nadie.
17. **Conflicto**: abrir la misma ruta en dos sesiones, guardar en una y luego en la otra → la segunda ve el aviso de conflicto con «Recargar».

## Criterios de salida

Pest, Pint, ESLint, TypeScript, Vitest y build pasan; el E2E del flujo principal (escenarios 1, 4, 6 y 11) pasa; el diseño sigue `RutasAdmin.dc.html` (administrador) y `RutasColaborador.dc.html` (colaborador) salvo las diferencias registradas en [plan.md](plan.md).
