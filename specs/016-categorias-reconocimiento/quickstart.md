# Quickstart: validar categorías de reconocimiento

Guía de validación de extremo a extremo. Detalles en [contracts/api-categorias.md](contracts/api-categorias.md) y [data-model.md](data-model.md).

## Prerrequisitos

- Backend y frontend instalados; base SQLite migrada.
- Sembrar competencias y usuarios, y luego las categorías:

```bash
cd backend
php artisan migrate
php artisan db:seed --class=UsuariosInicialesSeeder
php artisan db:seed --class=CompetenciasSeeder
php artisan db:seed --class=CategoriasReconocimientoSeeder
```

- Usuarios de prueba: `admin@hrms.test` (capacidad `admin`) y `usuario@hrms.test` (capacidad `user`).

## Pruebas automáticas

```bash
cd backend && ./vendor/bin/pest --filter=Compensaciones && ./vendor/bin/pint --test
cd frontend && npm run lint && npx tsc --noEmit && npm test && npm run build
```

Esperado: todo en verde.

## Escenarios manuales

1. **Consulta** (`usuario@hrms.test`): abrir `/compensaciones/categorias`; ver solo activas, 15 por página, sin filtro de estado ni acciones de gestión. Buscar «colaboracion» (sin tilde) y comprobar coincidencias por competencia. Recargar con filtros en la URL y ver el mismo estado. El filtro de competencia lista solo activas para el Usuario y activas e inactivas (marcadas) para el administrador.
2. **Crear** (`admin@hrms.test`): «Nueva categoría» con 2 indicadores → recibe el siguiente `CAT-###`, nace Activa, aparece el mensaje de confirmación. Repetir el nombre con otra capitalización o tildes → error junto al campo.
3. **Límites**: llegar a 5 indicadores (Agregar deshabilitado) y bajar a 1 (Eliminar deshabilitado); puntuación 0, 1001 o decimal rechazada.
4. **Editar**: cambiar puntuación e indicadores; guardar sin cambios → éxito sin alterar fecha de modificación; abrir dos pestañas y guardar en ambas → la segunda muestra el mensaje de modificación concurrente conservando lo escrito.
5. **Competencia inactiva**: categoría sembrada con competencia inactiva muestra «Competencia inactiva»; se puede editar sin cambiar la competencia; no aparece como seleccionable.
6. **Activar/Desactivar**: Desactivar pide confirmación; Activar no; la categoría inactiva desaparece de `listarSeleccionables()` pero sigue en el listado del administrador con filtro Inactivos.
7. **Seguridad**: sin token → 401; `usuario@hrms.test` en POST/PUT/PATCH → 403; `DELETE` → 405/404; claves prohibidas en el body → 422.
8. **Auditoría**: tras editar nombre y puntuación, `auditoria_categorias_reconocimiento` tiene una fila por campo con valor anterior y nuevo.
9. **Diseño**: comparar cada estado con su variante `design/V_cr-*.dc.html` (listado por rol, cargando, vacío, error, detalle, diálogos, formulario y responsive 320/390/768/1024 px).
