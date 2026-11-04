# Quickstart: validar Puntaje atado al parámetro (01b.2)

Contratos en [contracts/categorias-puntaje-api.md](contracts/categorias-puntaje-api.md); modelo en [data-model.md](data-model.md).

## Prerrequisitos

- Backend y frontend instalados (`composer install`, `npm install`); 01b.1 ya aplicada (`php artisan migrate`).
- Usuario Administrador (el Gestor de Talento en el repositorio de prueba) y una competencia activa.
- No hay migraciones nuevas.

## 1. Pruebas automatizadas

```bash
cd backend && php artisan test --filter=Categoria
cd backend && php artisan test --filter=Parametros
cd backend && vendor/bin/pint --test
cd frontend && npm run lint && npx tsc --noEmit && npm test && npm run build
```

Casos que deben pasar (Pest, `PuntajeAtadoAlParametroTest`):

- Con puntos vigentes 100 y sin programado: crear y editar con 100 → 201 / 200; con 101 → 422 en `puntuacion_base`; con 0, `1.5`, `"abc"` o vacío → 422.
- Programado menor (vigente 100, programado 60): 60 se acepta, 61 se rechaza con «entre 1 y 60».
- Programado mayor (vigente 100, programado 150): el máximo sigue siendo 100.
- Editar solo el nombre de una categoría que ya supera el máximo → 422; bajar la puntuación y el nombre a la vez → 200.
- Activar una `Inactivo` con puntuación mayor que el máximo → 422 «Baja primero la puntuación a {máximo} o menos.» y sigue `Inactivo`; con puntuación igual al máximo → se activa; activar una ya activa → 200 sin cambios; desactivar siempre → 200.
- 409 por versión desactualizada se devuelve antes que el 422 por puntuación.
- `GET /parametros` incluye `meta.puntuacion_maxima_categoria` correcto en los tres escenarios de la tabla de [data-model.md](data-model.md).
- Cambiar la puntuación de una categoría no altera `puntos` de reconocimientos ni de desafíos registrados ni filas de otras categorías.
- Las pruebas existentes de categorías y parámetros siguen pasando (ajustar solo las que supusieran el tope fijo de 1000).

Casos que deben pasar (Vitest): el esquema con máximo 100 acepta 1 y 100, rechaza 0, 101, decimales y vacío con «entre 1 y 100»; sin máximo conocido usa 1000; el formulario muestra «Máximo: {n} puntos» y la nota de cambio programado cuando el máximo viene del valor programado; un 422 en `puntuacion_base` se muestra junto al campo y conserva lo escrito; el listado muestra el rechazo de «Activar» y la fila sigue Inactiva.

## 2. Recorrido manual (Gestor)

1. En «Parámetros» dejar los puntos por colaborador en 100 y abrir «Nueva categoría»: junto al campo dice «Máximo: 100 puntos».
2. Escribir 101 y guardar: el error aparece en el campo, lo escrito se conserva y no se crea nada. Con 100 se crea.
3. Programar los puntos en 60 para el mes siguiente y reabrir el formulario: dice «Máximo: 60 puntos» con la nota del cambio; 61 se rechaza.
4. Con una categoría inactiva de puntuación 90 y máximo 60: «Editar» y cambiar solo el nombre → rechazado hasta bajarla a 60 o menos; «Activar» → «Baja primero la puntuación a 60 o menos.» y sigue inactiva.
5. Bajar la puntuación a 60 y activar: se activa.
6. Comprobar que los puntos de reconocimientos y desafíos ya registrados con esa categoría no cambian.

## 3. E2E (Playwright)

Ampliar `frontend/e2e/categorias-reconocimiento.spec.ts` con el flujo: abrir el formulario, ver el máximo, rechazo por puntuación mayor, guardado válido y activación rechazada.

```bash
cd frontend && npx playwright test categorias-reconocimiento
```
