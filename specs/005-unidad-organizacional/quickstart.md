# Quickstart: validar las unidades organizacionales

## Prerrequisitos

- Features 000-login a 004-catalogo-puestos funcionando (usuarios `admin@hrms.test` y `usuario@hrms.test`, contraseña de `SEED_PASSWORD`; puestos con sus posiciones sembradas).
- Dependencias instaladas en `backend/` y `frontend/`.
- Diseño actualizado (`design/DESIGN.md` y `design/Unidades.dc.html`: cadena de tipos, árbol expandible, modales, edición de tipos y diálogo de cascada).

## Preparar el backend

```bash
cd backend
php artisan migrate:fresh --seed
```

Comprobar: 5 tipos (Gerencia General 1 … Sección 5) y 12 unidades (UO-008 inactiva con fecha de supresión), la raíz UO-001, una posición Ocupada en UO-012, una Ocupada y una Vacante en UO-002 y Suprimidas en UO-006 y UO-008.

## Validar la API (contrato en [contracts/unidades-api.md](contracts/unidades-api.md))

Con el token de cada rol (`POST /api/auth/login`):

1. `GET /api/tipos-unidad` (ambos roles): cadena por nivel y `meta.version`.
2. `GET /api/unidades`: el Usuario recibe solo activas; el Administrador, con `estado=Todas`, las 12. `q=desarrollo` devuelve coincidencias con sus ancestras.
3. `POST /api/unidades` como Administrador con `unidad_padre_id` de un Departamento: 201, tipo Sección, código UO-013. Con una Sección como padre: 422 "Este tipo no admite subunidades." Con `tipo_unidad_id`: 422. Sin padre: 422. Como Usuario: 403.
4. `PUT /api/unidades/{id}`: renombrar; reubicar bajo una padre del tipo superior (la rama se mueve); la raíz no se reubica; `version` vencida: 409.
5. `GET /api/unidades/{UO-005}/impacto-desactivacion` y `PATCH …/estado` con `Inactiva`: desactiva UO-005 y UO-009 con fecha de hoy. Sobre la rama con la posición Ocupada de UO-012: 422 con la unidad y la cantidad y nada cambia. La raíz: 422.
6. `PATCH` con `Activa` sobre UO-009 con UO-005 inactiva: 422 `padre_inactivo`; primero UO-005.
7. `POST /api/tipos-unidad` `{"nombre":"Subsección"}`: 201 con nivel 6. `PUT /api/tipos-unidad` con un orden que deja una unidad bajo una padre de nivel igual o mayor: 422 con `unidades_invalidas`; con un renombre: 200. Token viejo: 409.
8. `DELETE` sobre unidades o tipos: 404/405.

## Validar la interfaz

Con `npm run dev` en `frontend/`:

1. **Usuario**: `/unidades` muestra la cadena de tipos y solo la raíz contraída; expandir con el control; buscar "area"; sin filtro de estado ni botones de gestión.
2. **Administrador**: filtro de estado (Activas / Inactivas / Todas), "Expandir todo" / "Contraer todo", advertencia "No sigue la cadena" cuando corresponda.
3. **Crear unidad**: modal con padre y tipo de solo lectura ("Se creará como: «…»"); opciones inactivas y del último nivel deshabilitadas; "Agregar subunidad" con la padre preseleccionada.
4. **Editar**: padre limitada al tipo superior; la raíz con el selector deshabilitado; información de solo lectura y auditoría.
5. **Desactivar**: diálogo con la lista de dependientes y "Desactivar N unidades"; bloqueo por posiciones sin poder confirmar; la raíz deshabilitada; **Reactivar** sin confirmación y deshabilitado con padre inactiva.
6. **Tipos**: "Nuevo tipo"; "Editar tipos" con arrastre y flechas, candado en la raíz, vista previa y "Guardar cambios"; reordenamiento rechazado con el modal de bloqueo y "Ver en el árbol".
7. Teclado, tema oscuro y anchos 320 y 1440 px sin scroll horizontal de la página.

## Ejecutar las pruebas

```bash
cd backend && vendor/bin/pint --test && vendor/bin/pest
cd ../frontend && npm run lint && npx tsc -b && npx vitest run && npm run build && ./node_modules/.bin/playwright test
```
