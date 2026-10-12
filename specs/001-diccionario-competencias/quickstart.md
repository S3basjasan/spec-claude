# Quickstart: validar el diccionario de competencias

## Prerrequisitos

- Feature 000-login funcionando (usuarios `admin@hrms.test` y `usuario@hrms.test`, contraseña de
  `SEED_PASSWORD`).
- Dependencias instaladas en `backend/` y `frontend/`.
- Diseño actualizado (`design/DESIGN.md`: estado, filtro y botón Activar/Desactivar).

## Preparar el backend

```bash
cd backend
php artisan migrate:fresh --seed
php artisan serve
```

Siembra los usuarios de prueba y siete competencias (ver [data-model.md](data-model.md)); `Adaptabilidad`
queda inactiva.

## Validar la API (contrato en [contracts/competencias-api.md](contracts/competencias-api.md))

1. Sin token, `GET /api/competencias` → 401.
2. Como Usuario, `GET /api/competencias` → 200 con **6** competencias activas (sin `Adaptabilidad`), ordenadas
   por nombre, con `codigo` `COMP-NNNN`, `estado` y sin grados.
3. Como Usuario, `GET /api/competencias?estado=Todos` y `?estado=Inactivo` → mismo resultado (6 activas): el
   parámetro se ignora.
4. Como Administrador, `GET /api/competencias` → 6 activas; `?estado=Inactivo` → 1; `?estado=Todos` → 7;
   `?q=trab`, `?tipo=Cardinal` y `?q=…&tipo=…&estado=…` combinan.
5. `GET /api/competencias/{id}` → 200 con los cuatro grados A–D y `estado`; para `Adaptabilidad` también 200
   con el Usuario; id inexistente → 404.
6. Como Usuario, `POST`, `PUT` y `PATCH …/estado` → 403; `DELETE /api/competencias/{id}` → 404/405 (no existe).
7. Como Administrador, `POST` válido → 201 con `codigo` `COMP-0008`, `estado` `Activo` y `version` 1.
8. `POST` con nombre repetido (cambiando mayúsculas o acentos), con un grado vacío, sin la letra `D` o con 51
   caracteres en el nombre → 422 con errores por campo.
9. `PUT` con la `version` actual → 200 y `version` +1, mismo `codigo` y mismo `estado`; repetir con la
   `version` anterior → 409; `PUT` sobre una inactiva → 200.
10. `PATCH …/estado` con `{"estado":"Inactivo"}` sobre una activa → 200; repetirlo → 422 "ya está en ese
    estado"; con `{"estado":"Activo"}` → 200; con un valor inválido → 422; id inexistente → 404. La
    `version` no cambió con los cambios de estado.
11. Auditoría (con la base de datos o las pruebas): `created_by` del alta, `updated_by` tras el `PUT`, y una
    fila de `competencia_estado_historial` por cada cambio de estado con usuario, fecha, estado anterior y
    nuevo.

## Validar la interfaz

```bash
cd frontend
npm run dev
```

1. Como Usuario: "Competencias" en el menú; el listado muestra Código, Nombre, Descripción (recortada), Tipo y
   Estado (badge) solo de competencias activas, sin filtro de estado ni botones de gestión.
2. Buscar por nombre y filtrar por tipo; recargar conserva búsqueda, filtro y página.
3. "Visualizar grados" despliega la descripción completa y los grados A–D; pulsarlo de nuevo los oculta.
4. Como Administrador: aparece el filtro de estado (Activas por defecto); en "Inactivas" se ve `Adaptabilidad`
   con badge Inactivo y botón **Activar**; en las activas, el botón dice **Desactivar**.
5. Desactivar pide confirmación que nombra la competencia e informa que no podrá asignarse en nuevos
   registros; cancelar no cambia nada; confirmar la pasa a Inactiva y sale del listado por defecto.
   Activar actúa de inmediato, sin confirmación.
6. Crear una competencia (errores por campo con el formulario incompleto), editarla (el código es de solo
   lectura; también una inactiva) y comprobar que su estado no cambia al editar.
7. Estados vacío, cargando y error, ambos temas y anchos de 320 a 1440 px sin scroll horizontal.

## Ejecutar las pruebas

```bash
cd backend && vendor/bin/pint --test && vendor/bin/pest
cd ../frontend && npm run lint && npx tsc -b && npx vitest run && npm run build && npx playwright test
```
