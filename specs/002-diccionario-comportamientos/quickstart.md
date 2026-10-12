# Quickstart: validar el diccionario de comportamientos

## Prerrequisitos

- Features 000-login y 001-diccionario-competencias funcionando (usuarios `admin@hrms.test` y `usuario@hrms.test`,
  contraseña de `SEED_PASSWORD`; siete competencias, `COMP-0007` inactiva).
- Dependencias instaladas en `backend/` y `frontend/`.
- Diseño actualizado (`design/DESIGN.md`: Combobox, Lista de campos y aviso de competencia inactiva).

## Preparar el backend

```bash
cd backend
php artisan migrate:fresh --seed
php artisan serve
```

Siembra usuarios, competencias y cinco registros de comportamientos (ver [data-model.md](data-model.md)); quedan
disponibles para crear `COMP-0003` y `COMP-0005`.

## Validar la API (contrato en [contracts/comportamientos-api.md](contracts/comportamientos-api.md))

1. Sin token, `GET /api/comportamientos` → 401.
2. Como Usuario, `GET /api/comportamientos` → 200 con **4** registros (los de competencias activas), ordenados por
   nombre de la competencia, con código, nombre, descripción, tipo y estado y sin comportamientos.
3. Como Usuario, `?estado=Todos` y `?estado=Inactivo` → el mismo resultado (4): el parámetro se ignora.
4. Como Administrador: sin parámetros → 4; `?estado=Inactivo` → 1 (`Adaptabilidad`); `?estado=Todos` → 5;
   `?q=trab`, `?tipo=Cardinal` y `?q=…&tipo=…&estado=…` combinan.
5. `GET /api/comportamientos/1` → 200 con `comportamientos` A–D (1–4 textos por grado) y `version`; para la
   competencia inactiva (id 7) también 200 con el Usuario; para una competencia sin registro (id 3) → 404.
6. Como Usuario, `POST`, `PUT` y `GET …/competencias-disponibles` → 403; `DELETE` → 404/405 (no existe).
7. Como Administrador, `GET …/competencias-disponibles` → 200 con `COMP-0003` y `COMP-0005` (activas sin registro),
   sin `Adaptabilidad` ni las que ya tienen registro.
8. `POST` para la competencia 3 con cuatro grados válidos → 201 con `version` 1; repetir → 422 (ya tiene registro);
   para la competencia 7 (inactiva) → 422; con un grado vacío, sin `D`, con una clave `E`, con 5 textos, con un
   texto de 301 caracteres, vacío o repetido en el grado → 422 con errores por campo.
9. `PUT` con la `version` actual y un cambio → 200 y `version` +1; el mismo cuerpo otra vez → 200 sin subir la
   versión (sin cambios reales); con la `version` anterior → 409; con `competencia_id` distinto → 422; `PUT` sobre
   la competencia inactiva (id 7) → 200; sin registro (id 5) → 404.
10. Estado derivado: desactivar la competencia 1 con `PATCH /api/competencias/1/estado` (001) →
    `GET /api/comportamientos/1` muestra `estado` `Inactivo` y el Usuario ya no la ve en el listado; reactivarla →
    vuelve a verse.
11. Auditoría (en la base de datos o con las pruebas): `created_by` del alta, `updated_by` y `version` solo tras
    ediciones con cambios reales.

## Validar la interfaz

```bash
cd frontend
npm run dev
```

1. Como Usuario: "Comportamientos" en el menú; el listado muestra Código, Nombre, Descripción (recortada), Tipo y
   Estado (badge) solo de competencias activas, sin filtro de estado ni botones de gestión.
2. Buscar por nombre de la competencia y filtrar por tipo; recargar conserva búsqueda, filtro y página.
3. "Visualizar comportamientos" despliega los grados A–D con viñetas; pulsarlo otra vez los oculta.
4. Como Administrador: aparece el filtro de estado; en "Inactivos" se ve `Adaptabilidad` con badge Inactivo y, al
   desplegarla, el aviso "La competencia asociada está inactiva"; **Editar** funciona también en ella.
5. Crear: elegir la competencia escribiendo para filtrar (solo activas sin registro); al elegirla se ven nombre,
   descripción y tipo; registrar 1–4 comportamientos por grado con Agregar/Quitar (no deja pasar de 4 ni bajar de
   1); errores por campo con el formulario incompleto.
6. Editar: la competencia en solo lectura; cambiar, añadir y quitar comportamientos; guardar sin cambios vuelve
   al listado sin error y sin tocar nada.
7. Desactivar la competencia desde `/competencias` (001) y volver a comportamientos: el registro aparece inactivo
   sin ninguna acción adicional.
8. No hay botones de activar, desactivar ni eliminar; estados vacío, cargando y error, ambos temas y anchos de 320
   a 1440 px sin scroll horizontal.

## Ejecutar las pruebas

```bash
cd backend && vendor/bin/pint --test && vendor/bin/pest
cd ../frontend && npm run lint && npx tsc -b && npx vitest run && npm run build && npx playwright test
```
