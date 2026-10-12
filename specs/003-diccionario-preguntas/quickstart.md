# Quickstart: validar el diccionario de preguntas

## Prerrequisitos

- Features 000-login, 001-diccionario-competencias y 002-diccionario-comportamientos funcionando (usuarios
  `admin@hrms.test` y `usuario@hrms.test`, contraseña de `SEED_PASSWORD`; siete competencias, `COMP-0007` inactiva).
- Dependencias instaladas en `backend/` y `frontend/`.
- Diseño actualizado (`design/DESIGN.md`: fila desplegable con lista numerada de preguntas).

## Preparar el backend

```bash
cd backend
php artisan migrate:fresh --seed
php artisan serve
```

Siembra usuarios, competencias, registros de comportamientos y cinco registros de preguntas (ver
[data-model.md](data-model.md)); quedan disponibles para crear `COMP-0003` y `COMP-0005`.

## Validar la API (contrato en [contracts/preguntas-api.md](contracts/preguntas-api.md))

1. Sin token, `GET /api/preguntas` → 401.
2. Como Usuario, `GET /api/preguntas` → 200 con **4** registros (competencias activas), ordenados por nombre de la
   competencia, con código, nombre, descripción, tipo y estado y sin preguntas.
3. Como Usuario, `?estado=Todos` y `?estado=Inactivo` → el mismo resultado (4).
4. Como Administrador: sin parámetros → 4; `?estado=Inactivo` → 1 (`Adaptabilidad`); `?estado=Todos` → 5; `?q=trab`,
   `?tipo=Cardinal` y combinaciones.
5. `GET /api/preguntas/1` → 200 con `preguntas` (4 textos) y `version`; para la competencia inactiva (id 7) también
   200 con el Usuario; para una competencia sin registro (id 3) → 404.
6. Como Usuario, `POST`, `PUT` y `GET …/competencias-disponibles` → 403; `DELETE` → 404/405.
7. Como Administrador, `GET …/competencias-disponibles` → 200 con `COMP-0003` y `COMP-0005`, sin `Adaptabilidad` ni las
   que ya tienen registro.
8. `POST` para la competencia 3 con cuatro textos válidos → 201 con `version` 1; repetir → 422; para la competencia 7
   (inactiva) → 422; con 3 o 5 textos, un texto vacío, de 501 caracteres o repetido (sin distinguir mayúsculas) → 422
   con errores por campo.
9. `PUT` con la `version` actual y un cambio → 200 y `version` +1; el mismo cuerpo otra vez → 200 sin subir la
   versión; con la `version` anterior → 409; con `competencia_id` distinto → 422; sobre la competencia inactiva
   (id 7) → 200; sin registro (id 5) → 404.
10. Estado derivado: desactivar la competencia 1 con `PATCH /api/competencias/1/estado` (001) → `GET /api/preguntas/1`
    muestra `estado` `Inactivo` y el Usuario ya no la ve en el listado; reactivarla → vuelve a verse.
11. Auditoría: `created_by` del alta, `updated_by` y `version` solo tras ediciones con cambios reales.

## Validar la interfaz

```bash
cd frontend
npm run dev
```

1. Como Usuario: "Preguntas" en el menú; el listado muestra Código, Nombre, Descripción (recortada), Tipo y Estado
   (badge) solo de competencias activas, sin filtro de estado ni botones de gestión.
2. Buscar por nombre y filtrar por tipo; recargar conserva búsqueda, filtro y página.
3. "Visualizar preguntas" despliega las cuatro preguntas numeradas del 1 al 4; pulsarlo otra vez las oculta.
4. Como Administrador: aparece el filtro de estado; en "Inactivos" se ve `Adaptabilidad` con badge Inactivo y, al
   desplegarla, el aviso "La competencia asociada está inactiva"; **Editar** funciona también en ella.
5. Crear: elegir la competencia escribiendo para filtrar (solo activas sin registro); al elegirla se ven nombre,
   descripción y tipo; cuatro campos numerados fijos (sin agregar ni quitar) con contador; errores por campo con el
   formulario incompleto o con una pregunta repetida.
6. Editar: la competencia en solo lectura; cambiar preguntas; guardar sin cambios vuelve al listado sin error.
7. Desactivar la competencia desde `/competencias` (001) y volver a preguntas: el registro aparece inactivo sin ninguna
   acción adicional.
8. No hay botones de activar, desactivar ni eliminar; estados vacío, cargando y error, ambos temas y anchos de 320 a
   1440 px sin scroll horizontal.

## Ejecutar las pruebas

```bash
cd backend && vendor/bin/pint --test && vendor/bin/pest
cd ../frontend && npm run lint && npx tsc -b && npx vitest run && npm run build && npx playwright test
```
