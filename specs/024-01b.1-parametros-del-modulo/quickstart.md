# Quickstart: validar Parámetros del módulo (01b.1)

Contratos en [contracts/parametros-api.md](contracts/parametros-api.md); modelo en [data-model.md](data-model.md).

## Prerrequisitos

- Backend y frontend instalados (`composer install`, `npm install`).
- Migración nueva aplicada (`php artisan migrate`).
- Usuario Administrador (el Gestor de Talento en el repositorio de prueba) y un usuario sin rol `admin`.

## 1. Pruebas automatizadas

```bash
cd backend && php artisan test --filter=Parametros
cd backend && vendor/bin/pint --test
cd frontend && npm run lint && npx tsc --noEmit && npm test
```

Casos que deben pasar (Pest): GET sin filas devuelve los defectos (con una categoría activa de 150, puntos = 150); PUT fuera de rango, decimal o texto responde 422 sin guardar nada; puntos por debajo de la categoría más alta responde 422 con su nombre; tras cambiar puntos, `valorVigente` de hoy devuelve el valor anterior y el del día 1 del mes siguiente el nuevo; plazo de anulación aplica hoy; sensibilidad 0 o 4 responde 422; dos cambios sucesivos dejan dos filas sin modificar la primera; `version` desactualizada responde 409; sin `admin` 403; sin sesión 401.

Vitest: rangos, error de categoría bajo el campo, aviso 409, «Guardar» bloqueado sin cambios o con envío pendiente.

## 2. Recorrido manual (Gestor)

1. Entrar como Administrador y abrir `/compensaciones/parametros`: se ven seis tarjetas con sus valores iniciales y «Valor inicial» como último cambio.
2. Cambiar «Plazo de anulación» a 10 y guardar: confirmación, y bajo la tarjeta «Último cambio: {nombre}, {fecha} (de 7 a 10)».
3. Escribir 0, 31 o 2,5 en el plazo: error junto al campo y no se guarda.
4. Cambiar los puntos por colaborador: se ve actual → nuevo, «desde el día 1 del mes siguiente» y el ejemplo de 10 personas; al guardar aparece como valor programado y el valor vigente no cambia.
5. Escribir un valor menor que la categoría activa más alta: error con el nombre de la categoría.
6. En «Sensibilidad del radar»: tres opciones con su explicación, sin números.
7. Abrir la pantalla en dos pestañas, guardar en una y luego en la otra: aviso de recarga y no se sobrescribe.

## 3. Recorrido manual (otro rol)

Entrar sin rol `admin`: no aparece la pestaña «Parámetros» y `GET /api/compensaciones/parametros` responde 403.

## 4. E2E

```bash
cd frontend && npx playwright test e2e/parametros-modulo.spec.ts
```

El Gestor cambia el plazo de anulación y ve el último cambio bajo la tarjeta.
