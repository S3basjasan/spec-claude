# Quickstart: validar las posiciones organizacionales

## Prerrequisitos

- Features 000-login a 005-unidad-organizacional funcionando (usuarios `admin@hrms.test` y `usuario@hrms.test`, contraseña de `SEED_PASSWORD`; puestos y las 12 unidades sembradas).
- Dependencias instaladas en `backend/` y `frontend/`.
- Diseño actualizado (`design/Posiciones.dc.html`, `design/DESIGN.md` y `design/canvas.json`).

## Preparar el backend

```bash
cd backend
php artisan migrate:fresh --seed
```

Comprobar: todas las posiciones con código `POS-####`, una sola posición vigente sin supervisor (en la Gerencia General), una Ocupada en UO-012, ninguna Ocupada ni Vacante en UO-005, UO-009 ni UO-008, al menos una Suprimida y las cuatro modalidades.

## Validar la API (contrato en [contracts/posiciones-api.md](contracts/posiciones-api.md))

Con el token de cada rol (`POST /api/auth/login`):

1. `GET /api/posiciones`: el Usuario recibe solo Ocupadas y Vacantes (10 por página); con `estado=Suprimida` o `Todas` se trata como `Vigentes`. El Administrador, con `estado=Todas`, recibe también las Suprimidas.
2. `q=jefe`, `q=POS-0003` y `unidad_organizacional_id` de una unidad con dependientes: incluyen las posiciones de la rama.
3. `GET /api/posiciones/{id}` de una Suprimida: 200 para ambos roles; `auditoria` solo para el Administrador.
4. `POST /api/posiciones` como Administrador con puesto y unidad activos: 201, `Vacante`, código nuevo. Sin supervisor cuando ya existe la máxima autoridad: 422. Con puesto inactivo, unidad inactiva o supervisor Suprimido: 422. Con `estado` o `user_id`: 422. Como Usuario: 403.
5. `PUT` con otra unidad, supervisor y modalidad: 200; con la propia posición o una subordinada como supervisor: 422; con `version` vencida: 409; sobre una Suprimida: 422.
6. `POST …/suprimir` sobre una Vacante sin subordinadas: 200 y `Suprimida`. Sobre una Ocupada: 422 (`ocupada`). Sobre una Vacante con subordinadas: 422 con la cantidad.
7. `GET /api/posiciones/opciones` (admin): puestos activos, unidades con `habilitada`, posiciones candidatas y `hay_maxima_autoridad`; con `excluir`, sin la posición ni sus subordinadas.
8. Desactivar el puesto o la unidad de una posición vigente (features 004 y 005): rechazado mientras tenga Ocupadas o Vacantes. `DELETE`: 404/405.

## Validar la interfaz

Con `npm run dev` en `frontend/`:

1. **Usuario**: `/posiciones` muestra el listado con badges Ocupada y Vacante, búsqueda, filtro de unidad en árbol y filtro de estado sin Suprimida ni "Todas"; filas desplegables (una abierta a la vez) sin auditoría ni acciones.
2. **Administrador**: filtro de estado con Suprimida y "Todas"; "Nueva posición"; en el detalle, Editar y Suprimir (deshabilitado con el motivo si está Ocupada o tiene subordinadas).
3. **Nueva posición**: unidades inactivas deshabilitadas ("La unidad está inactiva"); "Sin supervisor (máxima autoridad)" deshabilitada si ya existe; errores por campo.
4. **Editar**: puesto, código, ocupante, estado y auditoría en solo lectura; supervisor sin la propia posición ni sus subordinadas; conflicto de edición con "Recargar".
5. **Suprimir**: confirmación que nombra la posición y mensaje de éxito; la posición sale del listado por defecto.
6. Teclado, tema oscuro y anchos 320 y 1440 px sin scroll horizontal de la página.

## Ejecutar las pruebas

```bash
cd backend && vendor/bin/pint --test && vendor/bin/pest
cd ../frontend && npm run lint && npx tsc -b && npx vitest run && npm run build && ./node_modules/.bin/playwright test
```

## Validar la revisión 2026-10-16 (jerarquía de unidades y unidad raíz)

API:

1. `GET /api/posiciones`: cada elemento trae `ocupante` (`{id, nombre}` o `null`); `q` encuentra por nombre de la unidad y por nombre del ocupante, sin tildes ni mayúsculas.
2. `POST /api/posiciones` en la unidad raíz con otra posición vigente ya en ella: 422 «La Gerencia General ya tiene su posición (máxima autoridad).»; con supervisor: 422; una posición de otra unidad sin supervisor: 422 «Selecciona el supervisor.».
3. `POST` con un supervisor de una unidad dependiente de la elegida o de otra rama: 422 «El supervisor debe pertenecer a la misma unidad o a una unidad superior.»; con un supervisor de la misma unidad o de una superior no inmediata: 201.
4. `PUT` que mueve una posición con subordinadas a una unidad por debajo de la de alguna de ellas: 422 con la cantidad; sobre la máxima autoridad, cambiar la unidad o el supervisor: 422, y cambiar solo la modalidad: 200.
5. `POST …/suprimir` sobre la máxima autoridad Vacante sin subordinadas: 200 y la raíz queda libre para crear otra.
6. `GET /api/posiciones/opciones?excluir={id}`: unidades con `superiores`, `habilitada`, `motivo_deshabilitada` (`inactiva` | `raiz_ocupada`) y `unidades_minimas`; posiciones con su unidad y ocupante.
7. `PUT /api/unidades/{id}` que reubica una unidad y dejaría una posición reportando a otra de una unidad inferior o de otra rama: 422 en `unidad_padre_id` con la cantidad; una reubicación que no afecta a ninguna posición: 200.

Interfaz:

1. Columnas Reporta a (código y puesto debajo; «—» y «Máxima autoridad») y Ocupante («Sin ocupante» atenuado), contador «N posiciones» bajo la búsqueda y badge Suprimida de peligro.
2. Modal: la unidad raíz aparece deshabilitada si ya tiene su posición; el supervisor está deshabilitado hasta elegir la unidad, agrupado por unidad hasta la raíz con «código · puesto · ocupante», fijo en «Sin supervisor (máxima autoridad)» si la unidad es la raíz y se vacía si deja de ser válido.
3. Al editar una posición con subordinadas, las unidades que las dejarían por encima aparecen deshabilitadas («Tiene subordinadas en unidades superiores a esta»); en la máxima autoridad solo se edita la modalidad.
