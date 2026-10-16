# Quickstart: validar la DNF de extremo a extremo

Guía de validación; el detalle de datos y endpoints está en [data-model.md](data-model.md) y [contracts/](contracts/).

## Prerrequisitos
- Backend: PHP 8.3, `composer install`, migraciones aplicadas.
- Frontend: `npm install` en `frontend/`.
- Entorno Windows/OneDrive: ver la memoria «Entorno local de pruebas» (trampas al correr Pest, Vitest y Playwright).

## Preparación
```bash
cd backend
php artisan migrate
php artisan db:seed --class=DnfSeeder
```
El seeder crea 2 DNF de ejemplo (una Individual y una por Departamento) con las cuatro prioridades, un Cumple/Supera y un Sin evaluación.

## Pruebas automatizadas
```bash
cd backend
vendor/bin/pest --filter=Dnf
vendor/bin/pint --test
```
```bash
cd frontend
npx vitest run src/modules/desarrollo/dnf
npm run lint && npx tsc --noEmit && npm run build
```
Esperado: todo en verde. Incluye la batería de 16 combinaciones requerido/actual × Cardinal/Específica (SC-003) y la prueba de que PUT/PATCH/DELETE sobre `/api/dnf` devuelven 405 (SC-005).

## Escenarios manuales (con dobles de los puertos o datos de GP-02 cuando existan)

| # | Escenario | Resultado esperado |
|---|---|---|
| 1 | Admin → `/dnf` → Individual → Detectar necesidades | Se crea `DNF-000N`; tabla ordenada por prioridad; C vs A muestra −2, 50 %, Alta, Con brecha. |
| 2 | Competencia exigida sin nivel actual | Fila «Sin evaluación»; sin diferencia, brecha ni prioridad; aviso informativo. |
| 3 | Perfil con competencias inactivas | Se omiten; el aviso indica cuántas. |
| 4 | Admin → Departamento | Modal «Se evaluarán N colaboradores» (incluye subunidades); tras confirmar, resumen por colaborador; al elegir uno, su tabla. Excluidos (sin puesto/perfil/vínculo vigente) aparecen en el aviso. |
| 5 | Cambiar el perfil del puesto o el nivel del colaborador y reabrir la DNF | Los valores guardados no cambian. |
| 6 | Generar otra DNF del mismo colaborador | Se permite sin advertencia; la nueva define sus brechas activas. |
| 7 | «Generar Plan de Formación» en DNF con brechas | Se crea un borrador, botón deshabilitado y enlace al plan; segunda llamada → 422. En una masiva el borrador es uno solo. DNF sin brechas: botón deshabilitado con su motivo. |
| 8 | Historial `/dnf/historial` | Columnas y filtros (fechas, colaborador, departamento, prioridad) en la URL; cada fila abre el detalle de solo lectura; no hay editar/eliminar. |
| 9 | Colaborador → `/dnf` | Tarjetas con barras, «Nivel actual: C · Requerido: A · Diferencia: −2», tres brechas prioritarias ordenadas, «Ver cursos sugeridos» → `/formacion?competencia=:id`. |
| 10 | Colaborador sin DNF o sin brechas | Mensaje informativo en lugar de tarjetas. |
| 11 | Colaborador consulta `/api/dnf` o `/api/dnf/{id}` | 403. Sin token: 401. |
| 12 | Tema oscuro y 320 px | Sin scroll horizontal; contraste AA; barras con texto alternativo. |

## Nota de entorno
Mientras GP-02 y Plan de Formación no existan, `POST /api/dnf` responde 422 «No hay colaboradores evaluables» y `POST /api/dnf/{id}/plan` responde 503 (R-01); las pruebas usan dobles de los puertos y los escenarios 1–7 se ven con las DNF del seeder o con adaptadores de prueba.
