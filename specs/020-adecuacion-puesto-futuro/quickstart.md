# Quickstart: validación de Adecuación a puesto futuro

Guía de validación de punta a punta. Detalles de datos en [data-model.md](data-model.md), endpoints en [contracts/api.md](contracts/api.md) y pantallas en [contracts/ui.md](contracts/ui.md). Trampas de entorno (OneDrive, Pest, Vitest, Playwright): ver la memoria «Entorno local de pruebas».

## Prerrequisitos
- Backend y frontend instalados; base SQLite migrada.
- Seeders de Estructura, Gestión de Personal y Rutas ejecutados (los E2E de esta feature **no** dependen de `IngresosSeeder`; preparan colaboradores con vínculo vigente).
- Banderas por defecto: `DESARROLLO_ADECUACION_CONOCIMIENTOS_CON_GRADO=false`, `DESARROLLO_ADECUACION_EXPERIENCIA_DISPONIBLE=true`.

## Preparación
```bash
cd backend && php artisan migrate && php artisan db:seed --class=AdecuacionesSeeder
```
El seeder deja parámetros 40/30/30 y 12 meses, cuatro adecuaciones (Lista, En desarrollo, No lista, Incompleta), un puesto objetivo con tres candidatos y una adecuación por puesto directo.

## Pruebas automáticas
```bash
cd backend && ./vendor/bin/pest --filter=Adecuacion && ./vendor/bin/pint --test
```
```bash
cd frontend && npx vitest run src/modules/desarrollo/adecuacion && npm run lint && npx tsc --noEmit && npm run build
```
E2E del flujo principal (administrador genera → colaborador lo ve):
```bash
cd frontend && npx playwright test adecuacion
```

## Escenarios manuales
1. **Permisos y privacidad** — Con el colaborador, `GET /api/adecuaciones` y `GET /api/adecuaciones/1` devuelven 403; `GET /api/adecuaciones/mi-adecuacion` devuelve solo lo propio y no contiene `puntaje`, `ranking` ni `created_by`. Sin sesión, 401.
2. **Generar (P1)** — Administrador: `/adecuacion` → pestaña Diagnóstico → elige un colaborador con inscripción Activa → el objetivo por defecto es su nivel objetivo → el diagnóstico muestra tres paneles (Experiencia, Conocimientos, Competencias) con diferencia y estado → ingresa meses en cada brecha → «Registrar esta evaluación» → mensaje con `ADE-000N`. Sin tiempos, el botón queda bloqueado.
3. **Clasificación** — Con umbral 12: brechas de 6 y 18 meses ⇒ «No lista»; solo de 6 y 12 ⇒ «En desarrollo»; sin brechas ⇒ «Lista»; un requisito «Sin evaluación» ⇒ «Incompleta».
4. **Puesto directo** — Elegir «Puesto directo»; sin experiencia requerida, la dimensión Experiencia no se evalúa y aparece el aviso; con experiencia requerida 5 y 3 años acumulados, diferencia −2.
5. **Inmutabilidad** — Intentar `PUT/PATCH/DELETE /api/adecuaciones/{id}` devuelve 404/405; tras cambiar el umbral a 6, la preparación guardada no cambia.
6. **Comparar** — Puesto con tres candidatos: ranking con puntajes; un Incompleto aparte; un candidato sin adecuación señalado; pesos 50/25/25 recalculan; pesos que no suman 100 ⇒ 422 y mensaje.
7. **Mapa** — Una fila por colaborador y puesto objetivo con la adecuación más reciente; filtros por Departamento, Ruta y Preparación; brechas y tiempo máximo coinciden.
8. **Parámetros** — Cambiar umbral y pesos genera historial (usuario, fecha, anterior, nuevo); suma ≠ 100 se rechaza.
9. **Mi adecuación** — Colaborador: `/adecuacion` muestra «Mi adecuación a {puesto}», tarjetas, tiempos de cierre, «Ver cursos sugeridos» que abre `/formacion?competencia=<id>`, el mensaje de experiencia y el aviso permanente. Con adecuaciones a dos puestos aparece el selector. Sin adecuación: estado vacío.
10. **Enlace desde Rutas** — «Mi ruta de carrera» muestra «Ver mi adecuación» cuando hay nivel objetivo y navega a `/adecuacion`.
11. **Degradación** — Con la fuente de Personal no disponible, las lecturas muestran el aviso con «Reintentar» y la generación responde 503 sin guardar.
12. **Accesibilidad y responsive** — Teclado completo en pestañas, diálogos y tablas; sin scroll horizontal entre 320 y 1440 px; temas claro y oscuro.

## Resultado esperado
Pest, Pint, análisis estático, ESLint, TypeScript, Vitest, build y el E2E pasan; los 12 escenarios se cumplen; la implementación coincide con los tableros `design/Adecuacion*.dc.html` salvo las diferencias registradas en `plan.md` y `design/DESIGN.md`.
